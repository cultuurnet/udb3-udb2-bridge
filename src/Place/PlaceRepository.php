<?php

namespace CultuurNet\UDB3\UDB2\Place;

use Broadway\Domain\AggregateRoot;
use Broadway\Domain\DomainMessage;
use Broadway\Repository\AggregateNotFoundException;
use Broadway\Repository\RepositoryInterface;
use CultureFeed_Cdb_Data_Address;
use CultureFeed_Cdb_Data_Category;
use CultureFeed_Cdb_Data_CategoryList;
use CultureFeed_Cdb_Data_ContactInfo;
use CultureFeed_Cdb_Data_EventDetail;
use CultureFeed_Cdb_Data_EventDetailList;
use CultureFeed_Cdb_Data_Location;
use CultureFeed_Cdb_Item_Event;
use CultuurNet\Entry\BookingPeriod;
use CultuurNet\Entry\EntityType;
use CultuurNet\Entry\Language;
use CultuurNet\Entry\Number;
use CultuurNet\Entry\String;
use CultuurNet\UDB3\EntityServiceInterface;
use CultuurNet\UDB3\EventHandling\DelegateEventHandlingToSpecificMethodTrait;
use CultuurNet\UDB3\Place\Events\BookingInfoUpdated;
use CultuurNet\UDB3\Place\Events\ContactPointUpdated;
use CultuurNet\UDB3\Place\Events\DescriptionTranslated;
use CultuurNet\UDB3\Place\Events\DescriptionUpdated;
use CultuurNet\UDB3\Place\Events\FacilitiesUpdated;
use CultuurNet\UDB3\Place\Events\LabelAdded;
use CultuurNet\UDB3\Place\Events\LabelDeleted;
use CultuurNet\UDB3\Place\Events\MajorInfoUpdated;
use CultuurNet\UDB3\Place\Events\OrganizerDeleted;
use CultuurNet\UDB3\Place\Events\OrganizerUpdated;
use CultuurNet\UDB3\Place\Events\PlaceCreated;
use CultuurNet\UDB3\Place\Events\PlaceDeleted;
use CultuurNet\UDB3\Place\Events\TitleTranslated;
use CultuurNet\UDB3\Place\Events\TypicalAgeRangeDeleted;
use CultuurNet\UDB3\Place\Events\TypicalAgeRangeUpdated;
use CultuurNet\UDB3\Place\Place;
use CultuurNet\UDB3\UDB2\ActorRepository;
use CultuurNet\UDB3\UDB2\EntryAPIImprovedFactoryInterface;
use CultuurNet\UDB3\UDB2\Media\EditImageTrait;
use CultuurNet\UDB3\UDB2\Udb3RepositoryTrait;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

/**
 * Repository decorator that first updates UDB2.
 *
 * When a failure on UDB2 occurs, the whole transaction will fail.
 */
class PlaceRepository extends ActorRepository implements RepositoryInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;
    use EditImageTrait;
    use Udb3RepositoryTrait;
    use DelegateEventHandlingToSpecificMethodTrait;

    /**
     * @var PlaceImporterInterface
     */
    protected $placeImporter;

    /**
     * @var boolean
     */
    protected $syncBack = false;

    /**
     * @var EntityServiceInterface
     */
    protected $organizerService;

    private $aggregateClass;

    public function __construct(
        RepositoryInterface $decoratee,
        EntryAPIImprovedFactoryInterface $entryAPIImprovedFactory,
        PlaceImporterInterface $placeImporter,
        EntityServiceInterface $organizerService,
        array $eventStreamDecorators = array()
    ) {
        parent::__construct(
            $decoratee,
            $entryAPIImprovedFactory,
            $eventStreamDecorators
        );

        $this->placeImporter = $placeImporter;
        $this->organizerService = $organizerService;
        $this->aggregateClass = Place::class;
    }

    public function syncBackOn()
    {
        $this->syncBack = true;
    }

    public function syncBackOff()
    {
        $this->syncBack = false;
    }

    public function load($id)
    {
        try {
            $place = $this->decoratee->load($id);
        } catch (AggregateNotFoundException $e) {
            $place = $this->placeImporter->createPlaceFromUDB2($id);

            if (!$place) {
                throw $e;
            }
        }

        return $place;
    }

    /**
     * {@inheritdoc}
     */
    public function save(AggregateRoot $aggregate)
    {

        if ($this->syncBack) {
            // We can not directly act on the aggregate, as the uncommitted events will
            // be reset once we retrieve them, therefore we clone the object.
            $double = clone $aggregate;
            $domainEventStream = $double->getUncommittedEvents();
            $eventStream = $this->decorateForWrite(
                $aggregate,
                $domainEventStream
            );

            /** @var DomainMessage $domainMessage */
            foreach ($eventStream as $domainMessage) {
                $domainEvent = $domainMessage->getPayload();
                $this->handle($domainMessage);
            }
        }

        $this->decoratee->save($aggregate);
    }

    /**
     * Listener on the placeCreated event. Send a new place also to UDB2 as event.
     * @param PlaceCreated $placeCreated
     * @param DomainMessage $domainMessage
     * @return
     */
    public function applyPlaceCreated(PlaceCreated $placeCreated, DomainMessage $domainMessage)
    {
        $event = new CultureFeed_Cdb_Item_Event();
        $event->setCdbId($placeCreated->getPlaceId());
        $event->addKeyword('UDB3 place');

        $nlDetail = new CultureFeed_Cdb_Data_EventDetail();
        $nlDetail->setLanguage('nl');
        $nlDetail->setTitle($placeCreated->getTitle());

        $details = new CultureFeed_Cdb_Data_EventDetailList();
        $details->add($nlDetail);
        $event->setDetails($details);

        // Set location and calendar info.
        $this->setLocationForPlaceCreated($placeCreated, $event);
        $this->setCalendar($placeCreated->getCalendar(), $event);

        // Set event type and theme.
        $event->setCategories(new CultureFeed_Cdb_Data_CategoryList());
        $eventType = new CultureFeed_Cdb_Data_Category(
            'eventtype',
            $placeCreated->getEventType()->getId(),
            $placeCreated->getEventType()->getLabel()
        );
        $event->getCategories()->add($eventType);

        if ($placeCreated->getTheme() !== null) {
            $theme = new CultureFeed_Cdb_Data_Category(
                'theme',
                $placeCreated->getTheme()->getId(),
                $placeCreated->getTheme()->getLabel()
            );
            $event->getCategories()->add($theme);
        }

        // Empty contact info.
        $contactInfo = new CultureFeed_Cdb_Data_ContactInfo();
        $event->setContactInfo($contactInfo);

        $this->createEntryAPI($domainMessage)
            ->createEvent($event);

        return $placeCreated->getPlaceId();
    }

    /**
     * Listener on the placeDeleted event.
     * Also send a request to remove the place in UDB2.
     * @param PlaceDeleted $placeDeleted
     * @param DomainMessage $domainMessage
     * @return static
     */
    public function applyPlaceDeleted(PlaceDeleted $placeDeleted, DomainMessage $domainMessage)
    {
        $entryApi = $this->createEntryAPI($domainMessage);
        return $entryApi->deleteEvent($placeDeleted->getPlaceId());
    }

    /**
     * Set the location on the cdbEvent based on a PlaceCreated event.
     * @param PlaceCreated $placeCreated
     * @param CultureFeed_Cdb_Item_Event $cdbEvent
     */
    private function setLocationForPlaceCreated(PlaceCreated $placeCreated, CultureFeed_Cdb_Item_Event $cdbEvent)
    {

        $address = $placeCreated->getAddress();
        $cdbAddress = new CultureFeed_Cdb_Data_Address(
            $this->getPhysicalAddressForUdb3Address($address)
        );

        $location = new CultureFeed_Cdb_Data_Location($cdbAddress);
        $location->setLabel($placeCreated->getTitle());
        $cdbEvent->setLocation($location);

    }

    /**
     * Send the updated major info to UDB2.
     * @param MajorInfoUpdated $infoUpdated
     * @param DomainMessage $domainMessage
     */
    public function applyMajorInfoUpdated(MajorInfoUpdated $infoUpdated, DomainMessage $domainMessage)
    {
        $entryApi = $this->createEntryAPI($domainMessage);
        $event = $entryApi->getEvent($infoUpdated->getPlaceId());

        $this->setCalendar($infoUpdated->getCalendar(), $event);

        // Set event type and theme.
        $categories = $event->getCategories();
        foreach ($categories as $key => $category) {
            if ($category->getType() == 'eventtype' ||
                $category->getType() == 'theme') {
                $categories->delete($key);
            }
        }

        $eventType = new CultureFeed_Cdb_Data_Category(
            'eventtype',
            $infoUpdated->getEventType()->getId(),
            $infoUpdated->getEventType()->getLabel()
        );
        $event->getCategories()->add($eventType);

        if ($infoUpdated->getTheme() !== null) {
            $theme = new CultureFeed_Cdb_Data_Category(
                'theme',
                $infoUpdated->getTheme()->getId(),
                $infoUpdated->getTheme()->getLabel()
            );
            $event->getCategories()->add($theme);
        }

        $entryApi->updateEvent($event);

    }

    /**
     * Send the updated description also to CDB2.
     * @param DescriptionUpdated $descriptionUpdated
     * @param DomainMessage $domainMessage
     */
    private function applyDescriptionUpdated(
        DescriptionUpdated $descriptionUpdated,
        DomainMessage $domainMessage
    ) {

        $entryApi = $this->createEntryAPI($domainMessage);

        $newDescription = $descriptionUpdated->getDescription();
        $entityId = $descriptionUpdated->getPlaceId();
        $entityType = new EntityType('event');
        $description = new String($newDescription);
        $language = new Language('nl');

        if (!empty($newDescription)) {
            $entryApi->updateDescription(
                $entityId,
                $entityType,
                $description,
                $language
            );
        } else {
            $entryApi->deleteDescription($entityId, $entityType, $language);
        }
    }

    /**
     * Send the updated age range also to CDB2.
     * @param TypicalAgeRangeUpdated $ageRangeUpdated
     * @param DomainMessage $domainMessage
     */
    private function applyTypicalAgeRangeUpdated(
        TypicalAgeRangeUpdated $ageRangeUpdated,
        DomainMessage $domainMessage
    ) {

        $entryApi = $this->createEntryAPI($domainMessage);

        $entityType = new EntityType('event');
        $ages = explode('-', $ageRangeUpdated->getTypicalAgeRange());
        $age = new Number($ages[0]);
        $entryApi->updateAge($ageRangeUpdated->getPlaceId(), $entityType, $age);

    }

    /**
     * Send the deleted age range also to CDB2.
     * @param TypicalAgeRangeDeleted $ageRangeDeleted
     * @param DomainMessage $domainMessage
     */
    private function applyTypicalAgeRangeDeleted(
        TypicalAgeRangeDeleted $ageRangeDeleted,
        DomainMessage $domainMessage
    ) {

        $entryApi = $this->createEntryAPI($domainMessage);

        $entityType = new EntityType('event');
        $entryApi->deleteAge($ageRangeDeleted->getPlaceId(), $entityType);

    }

    /**
     * Apply the organizer updated event to the event repository.
     * @param OrganizerUpdated $organizerUpdated
     * @param DomainMessage $domainMessage
     */
    private function applyOrganizerUpdated(
        OrganizerUpdated $organizerUpdated,
        DomainMessage $domainMessage
    ) {

        $organizerJSONLD = $this->organizerService->getEntity(
            $organizerUpdated->getOrganizerId()
        );

        $organizer = json_decode($organizerJSONLD);

        $entryApi = $this->createEntryAPI($domainMessage);

        $entityType = new EntityType('event');
        $organiserName = new String($organizer->name);

        $entryApi->updateOrganiser(
            $organizerUpdated->getPlaceId(),
            $entityType,
            $organiserName
        );

    }

    /**
     * Delete the organizer also in UDB2..
     *
     * @param OrganizerDeleted $organizerDeleted
     * @param DomainMessage $domainMessage
     */
    private function applyOrganizerDeleted(
        OrganizerDeleted $organizerDeleted,
        DomainMessage $domainMessage
    ) {

        $entryApi = $this->createEntryAPI($domainMessage);
        $entityType = new EntityType('event');
        $entryApi->deleteOrganiser(
            $organizerDeleted->getPlaceId(),
            $entityType
        );

    }

    /**
     * Updated the contact info in udb2.
     *
     * @param ContactPointUpdated $domainEvent
     * @param DomainMessage $domainMessage
     */
    private function applyContactPointUpdated(
        ContactPointUpdated $domainEvent,
        DomainMessage $domainMessage
    ) {

        $entryApi = $this->createEntryAPI($domainMessage);
        $event = $entryApi->getEvent($domainEvent->getPlaceId());
        $contactPoint = $domainEvent->getContactPoint();

        $this->updateCdbItemByContactPoint($event, $contactPoint);

        $entryApi->updateContactInfo(
            $domainEvent->getPlaceId(),
            new EntityType('event'),
            $event->getContactInfo()
        );

    }

    /**
     * Updated the booking info in udb2.
     *
     * @param BookingInfoUpdated $domainEvent
     * @param DomainMessage $domainMessage
     */
    private function applyBookingInfoUpdated(
        BookingInfoUpdated $domainEvent,
        DomainMessage $domainMessage
    ) {

        $entryApi = $this->createEntryAPI($domainMessage);
        $event = $entryApi->getEvent($domainEvent->getPlaceId());
        $bookingInfo = $domainEvent->getBookingInfo();

        $this->updateCdbItemByBookingInfo($event, $bookingInfo);

        // Save contact info.
        $entryApi->updateContactInfo(
            $domainEvent->getPlaceId(),
            new EntityType('event'),
            $event->getContactInfo()
        );

        // Save the bookingperiod.
        if ($bookingInfo->getAvailabilityStarts() &&
            $bookingInfo->getAvailabilityEnds()) {
            $startDate = new \DateTime($bookingInfo->getAvailabilityStarts());
            $endDate = new \DateTime($bookingInfo->getAvailabilityEnds());
            $bookingPeriod = new BookingPeriod(
                $startDate->format('d/m/Y'),
                $endDate->format('d/m/Y')
            );

            $entryApi->updateBookingPeriod(
                $domainEvent->getPlaceId(),
                $bookingPeriod
            );
        }

    }

    /**
     * Apply the facilitiesupdated event to udb2.
     * @param FacilitiesUpdated $facilitiesUpdated
     * @param DomainMessage $domainMessage
     */
    private function applyFacilitiesUpdated(
        FacilitiesUpdated $facilitiesUpdated,
        DomainMessage $domainMessage
    ) {

        // Create the XML.
        $dom = new \DOMDocument('1.0', 'utf-8');
        $facilitiesElement = $dom->createElement('facilities');

        // Add the new facilities.
        foreach ($facilitiesUpdated->getFacilities() as $facility) {
            $facilitiesElement->appendChild(
                $dom->createElement('facility', $facility->getId())
            );
        }
        $dom->appendChild($facilitiesElement);

        $entryApi = $this->createEntryAPI($domainMessage);

        $entryApi->updateFacilities($facilitiesUpdated->getPlaceId(), $dom);

    }

    /**
     * @param LabelAdded $labelAdded
     * @param DomainMessage $domainMessage
     */
    private function applyLabelAdded(
        LabelAdded $labelAdded,
        DomainMessage $domainMessage
    ) {
        $this->createEntryAPI($domainMessage)
            ->addKeywords(
                $labelAdded->getItemId(),
                array($labelAdded->getLabel())
            );
    }

    /**
     * @param LabelDeleted $labelDeleted
     * @param DomainMessage $domainMessage
     */
    private function applyLabelDeleted(
        LabelDeleted $labelDeleted,
        DomainMessage $domainMessage
    ) {
        $this->createEntryAPI($domainMessage)
            ->deleteKeyword(
                $labelDeleted->getItemId(),
                $labelDeleted->getLabel()
            );
    }

    /**
     * @param TitleTranslated $domainEvent
     * @param DomainMessage $domainMessage
     */
    private function applyTitleTranslated(
        TitleTranslated $domainEvent,
        DomainMessage $domainMessage
    ) {
        $this->createEntryAPI($domainMessage)
            ->translateEventTitle(
                $domainEvent->getItemId(),
                $domainEvent->getLanguage(),
                $domainEvent->getTitle()->toNative()
            );
    }

    /**
     * @param DescriptionTranslated $domainEvent
     * @param DomainMessage $domainMessage
     */
    private function applyDescriptionTranslated(
        DescriptionTranslated $domainEvent,
        DomainMessage $domainMessage
    ) {
        $this->createEntryAPI($domainMessage)
            ->translateEventDescription(
                $domainEvent->getItemId(),
                $domainEvent->getLanguage(),
                $domainEvent->getDescription()->toNative()
            );
    }
}
