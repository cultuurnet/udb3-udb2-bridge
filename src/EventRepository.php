<?php

namespace CultuurNet\UDB3\UDB2;

use Broadway\Domain\AggregateRoot;
use Broadway\Domain\DomainEventStream;
use Broadway\Domain\DomainMessage;
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
use CultureFeed_Cdb_Item_Event;
use CultuurNet\Entry\BookingPeriod;
use CultuurNet\Entry\EntityType;
use CultuurNet\Entry\Language;
use CultuurNet\Entry\Number;
use CultuurNet\Entry\String;
use CultuurNet\UDB3\Event\Event;
use CultuurNet\UDB3\Event\Events\BookingInfoUpdated;
use CultuurNet\UDB3\Event\Events\CollaborationDataAdded;
use CultuurNet\UDB3\Event\Events\ContactPointUpdated;
use CultuurNet\UDB3\Event\Events\DescriptionTranslated;
use CultuurNet\UDB3\Event\Events\DescriptionUpdated;
use CultuurNet\UDB3\Event\Events\EventCreated;
use CultuurNet\UDB3\Event\Events\EventCreatedFromCdbXml;
use CultuurNet\UDB3\Event\Events\EventDeleted;
use CultuurNet\UDB3\Event\Events\EventUpdatedFromCdbXml;
use CultuurNet\UDB3\Event\Events\ImageAdded;
use CultuurNet\UDB3\Event\Events\ImageRemoved;
use CultuurNet\UDB3\Event\Events\ImageUpdated;
use CultuurNet\UDB3\Event\Events\LabelAdded;
use CultuurNet\UDB3\Event\Events\LabelsApplied;
use CultuurNet\UDB3\Event\Events\LabelsMerged;
use CultuurNet\UDB3\Event\Events\MainImageSelected;
use CultuurNet\UDB3\Event\Events\MajorInfoUpdated;
use CultuurNet\UDB3\Event\Events\OrganizerDeleted;
use CultuurNet\UDB3\Event\Events\OrganizerUpdated;
use CultuurNet\UDB3\Event\Events\TitleTranslated;
use CultuurNet\UDB3\Event\Events\TranslationApplied;
use CultuurNet\UDB3\Event\Events\TranslationDeleted;
use CultuurNet\UDB3\Event\Events\TypicalAgeRangeDeleted;
use CultuurNet\UDB3\Event\Events\TypicalAgeRangeUpdated;
use CultuurNet\UDB3\Event\Events\LabelDeleted;
use CultuurNet\UDB3\EventHandling\DelegateEventHandlingToSpecificMethodTrait;
use CultuurNet\UDB3\Location;
use CultuurNet\UDB3\OrganizerService;
use CultuurNet\UDB3\PlaceService;
use CultuurNet\UDB3\UDB2\Media\EditImageTrait;
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
    use EditImageTrait {
        applyImageAdded as applyOfferImageAdded;
        applyImageUpdated as applyOfferImageUpdated;
        applyImageRemoved as applyOfferImageRemoved;
        applyMainImageSelected as applyOfferMainImageSelected;
    }
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

    /**
     * @param TranslationApplied $translationApplied
     * @param DomainMessage $domainMessage
     */
    private function applyTranslationApplied(
        TranslationApplied $translationApplied,
        DomainMessage $domainMessage
    ) {
        $fields = [];

        if ($translationApplied->getTitle() !== null) {
            $fields['title'] = $translationApplied->getTitle()->toNative();
        }

        if ($translationApplied->getShortDescription() !== null) {
            $fields['shortdescription'] = $translationApplied->getShortDescription()->toNative();
        }

        if ($translationApplied->getLongDescription() !== null) {
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
     * @param TranslationDeleted $translationDeleted
     * @param DomainMessage $domainMessage
     */
    private function applyTranslationDeleted(
        TranslationDeleted $translationDeleted,
        DomainMessage $domainMessage
    ) {
        $this->createEntryAPI($domainMessage)
            ->deleteTranslation(
                $translationDeleted->getEventId()->toNative(),
                $translationDeleted->getLanguage()
            );
    }

    /**
     * @param CollaborationDataAdded $collaborationDataAdded
     * @param DomainMessage $domainMessage
     */
    private function applyCollaborationDataAdded(
        CollaborationDataAdded $collaborationDataAdded,
        DomainMessage $domainMessage
    ) {
        $collaborationData = $collaborationDataAdded->getCollaborationData();

        // EntryAPI on UDB2 does not have separate properties for text,
        // keyword, article and image, but expects them to be a single encoded
        // json string.
        $description = [
            'text' => (string) $collaborationData->getText(),
            'keyword' => (string) $collaborationData->getKeyword(),
            'article' => (string) $collaborationData->getArticle(),
            'image' => (string) $collaborationData->getImage(),
        ];
        $encodedDescription = json_encode($description);

        $this->createEntryAPI($domainMessage)
            ->createCollaborationLink(
                (string) $collaborationDataAdded->getEventId(),
                $collaborationDataAdded->getLanguage()->getCode(),
                (string) $collaborationData->getSubBrand(),
                $encodedDescription,
                (string) $collaborationData->getPlainText(),
                (string) $collaborationData->getTitle(),
                (string) $collaborationData->getCopyright(),
                (string) $collaborationData->getLink()
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
        $this->setLocation($eventCreated->getLocation(), $event);
        $this->setCalendar($eventCreated->getCalendar(), $event);

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

        $this->setLocation($infoUpdated->getLocation(), $event);
        $this->setCalendar($infoUpdated->getCalendar(), $event);

        $categories = $event->getCategories();
        $newCategories = new CultureFeed_Cdb_Data_CategoryList();

        $typesToDrop = ['eventtype', 'theme'];
        foreach ($categories as $key => $category) {
            $categoryHasToBeDropped = in_array(
                $category->getType(),
                $typesToDrop
            );

            if ($categoryHasToBeDropped) {
                continue;
            }

            $newCategories->add($category);
        }

        $newCategories->add(
            new CultureFeed_Cdb_Data_Category(
                'eventtype',
                $infoUpdated->getEventType()->getId(),
                $infoUpdated->getEventType()->getLabel()
            )
        );

        if ($infoUpdated->getTheme() !== null) {
            $newCategories->add(
                new CultureFeed_Cdb_Data_Category(
                    'theme',
                    $infoUpdated->getTheme()->getId(),
                    $infoUpdated->getTheme()->getLabel()
                )
            );
        }

        $event->setCategories($newCategories);

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
        $event = $entryApi->getEvent($domainEvent->getItemId());
        $bookingInfo = $domainEvent->getBookingInfo();

        $entityType = new EntityType('event');

        $this->updateCdbItemByBookingInfo($event, $bookingInfo);

        // Save contact info.
        $entryApi->updateContactInfo(
            $domainEvent->getItemId(),
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
                $domainEvent->getItemId(),
                $bookingPeriod
            );
        }

    }

    /**
     * Set the location on the cdb event based on an eventCreated event.
     *
     * @param Location $eventLocation
     * @param CultureFeed_Cdb_Item_Event $cdbEvent
     * @throws \CultuurNet\UDB3\EntityNotFoundException
     */
    private function setLocation(Location $eventLocation, CultureFeed_Cdb_Item_Event $cdbEvent)
    {
        $placeEntity = $this->placeService->getEntity($eventLocation->getCdbid());
        $place = json_decode($placeEntity);

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

    public function applyImageAdded(
        ImageAdded $imageAdded,
        DomainMessage $domainMessage
    ) {
        $this->applyOfferImageAdded($imageAdded, $domainMessage);
    }

    public function applyImageRemoved(
        ImageRemoved $imageRemoved,
        DomainMessage $domainMessage
    ) {
        $this->applyOfferImageRemoved($imageRemoved, $domainMessage);
    }

    public function applyImageUpdated(
        ImageUpdated $imageUpdated,
        DomainMessage $domainMessage
    ) {
        $this->applyOfferImageUpdated($imageUpdated, $domainMessage);
    }

    public function applyMainImageSelected(
        MainImageSelected $mainImageSelected,
        DomainMessage $domainMessage
    ) {
        $this->applyOfferMainImageSelected($mainImageSelected, $domainMessage);
    }
}
