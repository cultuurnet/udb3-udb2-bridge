<?php
/**
 * @file
 */

namespace CultuurNet\UDB3\UDB2;

use Broadway\Domain\AggregateRoot;
use Broadway\Domain\DomainEventStream;
use Broadway\Domain\DomainMessage;
use Broadway\Domain\Metadata;
use Broadway\EventSourcing\EventStreamDecoratorInterface;
use Broadway\Repository\AggregateNotFoundException;
use Broadway\Repository\RepositoryInterface;
use CultureFeed_Cdb_Data_Address;
use CultureFeed_Cdb_Data_Address_PhysicalAddress;
use CultureFeed_Cdb_Data_Category;
use CultureFeed_Cdb_Data_CategoryList;
use CultureFeed_Cdb_Data_ContactInfo;
use CultureFeed_Cdb_Data_EventDetail;
use CultureFeed_Cdb_Data_EventDetailList;
use CultureFeed_Cdb_Data_Location;
use CultureFeed_Cdb_Default;
use CultureFeed_Cdb_Item_Event;
use CultuurNet\Entry\BookingPeriod;
use CultuurNet\Entry\EntityType;
use CultuurNet\Entry\Keyword;
use CultuurNet\Entry\Language;
use CultuurNet\Entry\Number;
use CultuurNet\Entry\String;
use CultuurNet\UDB3\Event\DescriptionTranslated;
use CultuurNet\UDB3\Event\Event;
use CultuurNet\UDB3\Event\Events\BookingInfoUpdated;
use CultuurNet\UDB3\Event\Events\ContactPointUpdated;
use CultuurNet\UDB3\Event\Events\DescriptionUpdated;
use CultuurNet\UDB3\Event\Events\EventCreated;
use CultuurNet\UDB3\Event\Events\EventCreatedFromCdbXml;
use CultuurNet\UDB3\Event\Events\EventDeleted;
use CultuurNet\UDB3\Event\Events\EventUpdatedFromCdbXml;
use CultuurNet\UDB3\Event\Events\EventWasLabelled;
use CultuurNet\UDB3\Event\Events\ImageAdded;
use CultuurNet\UDB3\Event\Events\ImageDeleted;
use CultuurNet\UDB3\Event\Events\ImageUpdated;
use CultuurNet\UDB3\Event\Events\LabelsApplied;
use CultuurNet\UDB3\Event\Events\LabelsMerged;
use CultuurNet\UDB3\Event\Events\MajorInfoUpdated;
use CultuurNet\UDB3\Event\Events\OrganizerDeleted;
use CultuurNet\UDB3\Event\Events\OrganizerUpdated;
use CultuurNet\UDB3\Event\Events\TranslationApplied;
use CultuurNet\UDB3\Event\Events\TypicalAgeRangeDeleted;
use CultuurNet\UDB3\Event\Events\TypicalAgeRangeUpdated;
use CultuurNet\UDB3\Event\Events\Unlabelled;
use CultuurNet\UDB3\Event\TitleTranslated;
use CultuurNet\UDB3\EventHandling\DelegateEventHandlingToSpecificMethodTrait;
use CultuurNet\UDB3\OrganizerService;
use CultuurNet\UDB3\PlaceService;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

/**
 * Repository decorator that first updates UDB2.
 *
 * When a failure on UDB2 occurs, the whole transaction will fail.
 */
class EventRepository implements RepositoryInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;
    use Udb2UtilityTrait;
    use Udb3RepositoryTrait;
    use DelegateEventHandlingToSpecificMethodTrait;

    /**
     * @var RepositoryInterface
     */
    protected $decoratee;

    /**
     * @var boolean
     */
    protected $syncBack = false;

    /**
     * @var OrganizerService
     */
    protected $organizerService;
    /**
     * @var PlaceService
     */
    protected $placeService;

    /**
     * @var EventStreamDecoratorInterface[]
     */
    private $eventStreamDecorators = array();

    /**
     * @var EventImporterInterface
     */
    protected $eventImporter;

    private $aggregateClass;

    public function __construct(
        RepositoryInterface $decoratee,
        EntryAPIImprovedFactoryInterface $entryAPIImprovedFactory,
        EventImporterInterface $eventImporter,
        PlaceService $placeService,
        OrganizerService $organizerService,
        array $eventStreamDecorators = array()
    ) {
        $this->decoratee = $decoratee;
        $this->setEntryAPIImprovedFactory($entryAPIImprovedFactory);
        $this->eventStreamDecorators = $eventStreamDecorators;
        $this->organizerService = $organizerService;
        $this->placeService = $placeService;
        $this->aggregateClass = Event::class;
        $this->eventImporter = $eventImporter;
    }

    public function syncBackOn()
    {
        $this->syncBack = true;
    }

    public function syncBackOff()
    {
        $this->syncBack = false;
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
     * @param EventWasLabelled $labelled
     * @param DomainMessage $domainMessage
     */
    private function applyEventWasLabelled(
        EventWasLabelled $labelled,
        DomainMessage $domainMessage
    ) {
        $this->createEntryAPI($domainMessage)
            ->addKeywords(
                $labelled->getEventId(),
                array($labelled->getLabel())
            );
    }

    /**
     * @param Unlabelled $unlabelled
     * @param DomainMessage $domainMessage
     */
    private function applyUnlabelled(
        Unlabelled $unlabelled,
        DomainMessage $domainMessage
    ) {
        $this->createEntryAPI($domainMessage)
            ->deleteKeyword(
                $unlabelled->getEventId(),
                $unlabelled->getLabel()
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
                $domainEvent->getEventId(),
                $domainEvent->getLanguage(),
                $domainEvent->getTitle()
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
                $domainEvent->getEventId(),
                $domainEvent->getLanguage(),
                $domainEvent->getDescription()
            );
    }

    /**
     * @param TranslationApplied $translationApplied
     * @param DomainMessage $domainMessage
     */
    private function applyTranslationApplied(
        TranslationApplied $translationApplied,
        DomainMessage $domainMessage
    ) {
        $fields = [];

        if ($translationApplied->getTitle()->toNative() !== null) {
            $fields['title'] = $translationApplied->getTitle()->toNative();
        }

        if ($translationApplied->getShortDescription()->toNative() !== null) {
            $fields['shortdescription'] = $translationApplied->getShortDescription()->toNative();
        }

        if ($translationApplied->getLongDescription()->toNative() !== null) {
            $fields['longdescription'] = $translationApplied->getLongDescription()->toNative();
        }

        $this->createEntryAPI($domainMessage)
            ->translate(
                $translationApplied->getEventId()->toNative(),
                $translationApplied->getLanguage(),
                $fields
            );
    }

    /**
     * @param AggregateRoot $aggregate
     * @param DomainEventStream $eventStream
     * @return DomainEventStream|\Broadway\Domain\DomainEventStreamInterface
     */
    private function decorateForWrite(
        AggregateRoot $aggregate,
        DomainEventStream $eventStream
    ) {
        $aggregateType = $this->getType();
        $aggregateIdentifier = $aggregate->getAggregateRootId();

        foreach ($this->eventStreamDecorators as $eventStreamDecorator) {
            $eventStream = $eventStreamDecorator->decorateForWrite(
                $aggregateType,
                $aggregateIdentifier,
                $eventStream
            );
        }

        return $eventStream;
    }

    /**
     * {@inheritdoc}
     *
     * Ensures an event is created, by importing it from UDB2 if it does not
     * exist locally yet.
     */
    public function load($id)
    {
        try {
            $event = $this->decoratee->load($id);
        } catch (AggregateNotFoundException $e) {
            $event = $this->eventImporter->createEventFromUDB2($id);

            if (!$event) {
                throw new AggregateNotFoundException($id);
            }
        }

        return $event;
    }

    /**
     * Listener on the eventCreated event. Send a new event also to UDB2.
     * @param EventCreated $eventCreated
     * @param DomainMessage $domainMessage
     */
    public function applyEventCreated(EventCreated $eventCreated, DomainMessage $domainMessage)
    {

        $event = new CultureFeed_Cdb_Item_Event();
        $event->setCdbId($eventCreated->getEventId());

        $nlDetail = new CultureFeed_Cdb_Data_EventDetail();
        $nlDetail->setLanguage('nl');
        $nlDetail->setTitle($eventCreated->getTitle());

        $details = new CultureFeed_Cdb_Data_EventDetailList();
        $details->add($nlDetail);
        $event->setDetails($details);

        // Set location and calendar info.
        $this->setLocationForEventCreated($eventCreated, $event);
        $this->setCalendarForItemCreated($eventCreated, $event);

        // Set event type and theme.
        $event->setCategories(new CultureFeed_Cdb_Data_CategoryList());
        $eventType = new CultureFeed_Cdb_Data_Category(
            'eventtype',
            $eventCreated->getEventType()->getId(),
            $eventCreated->getEventType()->getLabel()
        );
        $event->getCategories()->add($eventType);

        if ($eventCreated->getTheme() !== null) {
            $theme = new CultureFeed_Cdb_Data_Category(
                'theme',
                $eventCreated->getTheme()->getId(),
                $eventCreated->getTheme()->getLabel()
            );
            $event->getCategories()->add($theme);
        }

        // Empty contact info.
        $contactInfo = new CultureFeed_Cdb_Data_ContactInfo();
        $event->setContactInfo($contactInfo);

        $this->createEntryAPI($domainMessage)
            ->createEvent($event);
    }

    /**
     * Listener on the EventDeleted event.
     * Also send a request to remove the event in UDB2.
     * @param EventDeleted $eventDeleted
     * @param DomainMessage $domainMessage
     * @return static
     */
    public function applyEventDeleted(EventDeleted $eventDeleted, DomainMessage $domainMessage)
    {
        $entryApi = $this->createEntryAPI($domainMessage);
        $entryApi->deleteEvent($eventDeleted->getEventId());
    }

    /**
     * Send the updated major info to UDB2.
     * @param MajorInfoUpdated $infoUpdated
     * @param DomainMessage $domainMessage
     */
    public function applyMajorInfoUpdated(
        MajorInfoUpdated $infoUpdated,
        DomainMessage $domainMessage
    ) {

        $entryApi = $this->createEntryAPI($domainMessage);
        $event = $entryApi->getEvent($infoUpdated->getEventId());

        $this->setLocationForEventCreated($infoUpdated, $event);
        $this->setCalendarForItemCreated($infoUpdated, $event);

        // Set event type and theme.
        $categories = $event->getCategories();
        foreach ($categories as $key => $category) {
            if ($category->getType() == 'eventtype' || $category->getType() == 'theme') {
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
        $entityId = $descriptionUpdated->getEventId();
        $entityType = new EntityType('event');
        $description = new String($newDescription);
        $language = new Language('nl');

        if (!empty($newDescription)) {
            $entryApi->updateDescription($entityId, $entityType, $description, $language);
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

        $ages = explode('-', $ageRangeUpdated->getTypicalAgeRange());
        $entityType = new EntityType('event');
        $age = new Number($ages[0]);
        $entryApi->updateAge($ageRangeUpdated->getEventId(), $entityType, $age);

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
        $entryApi->deleteAge($ageRangeDeleted->getEventId(), $entityType);

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

        $entryApi->updateOrganiser($organizerUpdated->getEventId(), $entityType, $organiserName);

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
        $entryApi->deleteOrganiser($organizerDeleted->getEventId(), $entityType);

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
        $event = $entryApi->getEvent($domainEvent->getEventId());
        $contactPoint = $domainEvent->getContactPoint();

        $this->updateCdbItemByContactPoint($event, $contactPoint);

        $entryApi->updateContactInfo(
            $domainEvent->getEventId(),
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
        $event = $entryApi->getEvent($domainEvent->getEventId());
        $bookingInfo = $domainEvent->getBookingInfo();

        $entityType = new EntityType('event');

        $this->updateCdbItemByBookingInfo($event, $bookingInfo);

        // Save contact info.
        $entryApi->updateContactInfo(
            $domainEvent->getEventId(),
            $entityType,
            $event->getContactInfo()
        );

        // Save the bookingperiod.
        if ($bookingInfo->getAvailabilityStarts() && $bookingInfo->getAvailabilityEnds()) {
            $startDate = new \DateTime($bookingInfo->getAvailabilityStarts());
            $endDate = new \DateTime($bookingInfo->getAvailabilityEnds());
            $bookingPeriod = new BookingPeriod(
                $startDate->format('d/m/Y'),
                $endDate->format('d/m/Y')
            );

            $entryApi->updateBookingPeriod(
                $domainEvent->getEventId(),
                $bookingPeriod
            );
        }

    }

    /**
     * Apply the imageAdded event to udb2.
     * @param ImageAdded $domainEvent
     * @param DomainMessage $domainMessage
     */
    private function applyImageAdded(
        ImageAdded $domainEvent,
        DomainMessage $domainMessage
    ) {

        $entryApi = $this->createEntryAPI($domainMessage);
        $event = $entryApi->getEvent($domainEvent->getEventId());

        $this->addImageToCdbItem($event, $domainEvent->getMediaObject());
        $entryApi->updateEvent($event);

    }

    /**
     * Apply the imageUpdated event to udb2.
     * @param ImageUpdated $domainEvent
     * @param DomainMessage $domainMessage
     */
    private function applyImageUpdated(
        ImageUpdated $domainEvent,
        DomainMessage $domainMessage
    ) {

        $entryApi = $this->createEntryAPI($domainMessage);
        $event = $entryApi->getEvent($domainEvent->getEventId());

        $this->updateImageOnCdbItem($event, $domainEvent->getIndexToUpdate(), $domainEvent->getMediaObject());
        $entryApi->updateEvent($event);

    }

    /**
     * Apply the imageDeleted event to udb2.
     * @param ImageDeleted $domainEvent
     * @param DomainMessage $domainMessage
     */
    private function applyImageDeleted(
        ImageDeleted $domainEvent,
        DomainMessage $domainMessage
    ) {

        $entryApi = $this->createEntryAPI($domainMessage);
        $event = $entryApi->getEvent($domainEvent->getEventId());

        $this->deleteImageOnCdbItem($event, $domainEvent->getIndexToDelete());
        $entryApi->updateEvent($event);

    }

    /**
     * Set the location on the cdb event based on an eventCreated event.
     *
     * @param EventCreated $eventCreated
     * @param CultureFeed_Cdb_Item_Event $cdbEvent
     */
    private function setLocationForEventCreated(EventCreated $eventCreated, CultureFeed_Cdb_Item_Event $cdbEvent)
    {

        $placeEntity = $this->placeService->getEntity($eventCreated->getLocation()->getCdbid());
        $place = json_decode($placeEntity);

        $eventLocation = $eventCreated->getLocation();

        $physicalAddress = new CultureFeed_Cdb_Data_Address_PhysicalAddress();
        $physicalAddress->setCountry($place->address->addressCountry);
        $physicalAddress->setCity($place->address->addressLocality);
        $physicalAddress->setZip($place->address->postalCode);


        // @todo This is not an exact mapping, because we do not have a separate
        // house number in JSONLD, this should be fixed somehow. Probably it's
        // better to use another read model than JSON-LD for this purpose.
        $streetParts = explode(' ', $place->address->streetAddress);

        if (count($streetParts) > 1) {
            $number = array_pop($streetParts);
            $physicalAddress->setStreet(implode(' ', $streetParts));
            $physicalAddress->setHouseNumber($number);
        } else {
            $physicalAddress->setStreet($eventLocation->getStreet());
        }

        $address = new CultureFeed_Cdb_Data_Address($physicalAddress);

        $location = new CultureFeed_Cdb_Data_Location($address);
        $location->setLabel($eventLocation->getName());
        $cdbEvent->setLocation($location);

    }

    /**
     * @param EventCreatedFromCdbXml $eventCreatedFromCdbXml
     * @param DomainMessage $domainMessage
     */
    public function applyEventCreatedFromCdbXml(
        EventCreatedFromCdbXml $eventCreatedFromCdbXml,
        DomainMessage $domainMessage
    ) {
        // Get EventXmlString Object
        $eventXmlString = $eventCreatedFromCdbXml->getEventXmlString();
        $eventXmlStringWithCdbid = $eventXmlString->withCdbidAttribute(
            $eventCreatedFromCdbXml->getEventId()
        )->toNative();

        // Send to EntryApi UDB2.
        $this->createEntryAPI($domainMessage)
            ->createEventFromRawXml((string)$eventXmlStringWithCdbid);
    }

    /**
     * @param EventUpdatedFromCdbXml $eventUpdatedFromCdbXml
     * @param DomainMessage $domainMessage
     */
    public function applyEventUpdatedFromCdbXml(
        EventUpdatedFromCdbXml $eventUpdatedFromCdbXml,
        DomainMessage $domainMessage
    ) {
        $eventId = $eventUpdatedFromCdbXml->getEventId();

        // Get EventXmlString Object
        $eventXmlString = $eventUpdatedFromCdbXml->getEventXmlString();
        $eventXmlStringWithCdbid = $eventXmlString->withCdbidAttribute(
            $eventId
        )->toNative();

        // Send to EntryApi UDB2.
        $this->createEntryAPI($domainMessage)
            ->updateEventFromRawXml($eventId, (string)$eventXmlStringWithCdbid);
    }

    public function applyLabelsMerged(
        LabelsMerged $labelsMerged,
        DomainMessage $domainMessage
    ) {
        $this->createEntryAPI($domainMessage)
            ->addKeywords(
                $labelsMerged->getEventId()->toNative(),
                $labelsMerged->getLabels()->asArray()
            );
    }
}
