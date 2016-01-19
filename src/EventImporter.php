<?php
/**
 * @file
 */

namespace CultuurNet\UDB3\UDB2;

use Broadway\EventHandling\EventListenerInterface;
use Broadway\EventStore\EventStoreException;
use Broadway\Repository\AggregateNotFoundException;
use Broadway\Repository\RepositoryInterface;
use CultuurNet\UDB2DomainEvents\EventCreated;
use CultuurNet\UDB2DomainEvents\EventUpdated;
use CultuurNet\UDB3\Cdb\EventItemFactory;
use CultuurNet\UDB3\EntityNotFoundException;
use CultuurNet\UDB3\Event\Event;
use CultuurNet\UDB3\EventHandling\DelegateEventHandlingToSpecificMethodTrait;
use CultuurNet\UDB3\OrganizerService;
use CultuurNet\UDB3\PlaceService;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;

/**
 * Listens for update/create events coming from UDB2 and applies the
 * resulting cdbXml to the UDB3 events.
 */
class EventImporter implements EventListenerInterface, EventImporterInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;
    use DelegateEventHandlingToSpecificMethodTrait;

    /**
     * @var EventCdbXmlServiceInterface
     */
    private $cdbXmlService;

    /**
     * @var RepositoryInterface
     */
    protected $repository;

    /**
     * @var OrganizerService
     */
    protected $organizerService;

    /**
     * @var PlaceService
     */
    protected $placeService;

    /**
     * @param EventCdbXmlServiceInterface $cdbXmlService
     */
    public function __construct(
        EventCdbXmlServiceInterface $cdbXmlService,
        RepositoryInterface $repository,
        PlaceService $placeService,
        OrganizerService $organizerService
    ) {
        $this->cdbXmlService = $cdbXmlService;
        $this->repository = $repository;
        $this->placeService = $placeService;
        $this->organizerService = $organizerService;
        $this->logger = new NullLogger();
    }

    /**
     * @param EventCreated $eventCreated
     */
    private function applyEventCreated(EventCreated $eventCreated)
    {
        // @todo Should we add additional layer to check for author and timestamp?
        $this->createEventFromUDB2((string)$eventCreated->getEventId());
    }

    /**
     * @param EventUpdated $eventUpdated
     */
    private function applyEventUpdated(EventUpdated $eventUpdated)
    {
        // @todo Should we add additional layer to check for author and timestamp?
        $this->updateEventFromUDB2((string)$eventUpdated->getEventId());
    }

    /**
     * @inheritdoc
     */
    public function updateEventFromUDB2($eventId)
    {
        return $this->update($eventId);
    }

    /**
     * @param string $eventId
     * @param bool $fallbackToCreate
     * @return Event
     * @throws EventNotFoundException
     *   If the event can not be found by the CDBXML service implementation.
     */
    private function update($eventId, $fallbackToCreate = true)
    {
        try {
            $event = $this->loadEvent($eventId);
        } catch (AggregateNotFoundException $e) {
            if ($fallbackToCreate) {
                $this->logger->notice(
                    "Could not update event because it does not exist yet on UDB3, will attempt to create the event instead",
                    [
                        'eventId' => $eventId
                    ]
                );

                return $this->create($eventId, false);
            } else {
                $this->logger->error(
                    "Could not update event because it does not exist yet on UDB3",
                    [
                        'eventId' => $eventId
                    ]
                );

                return;
            }
        }

        $eventXml = $this->getCdbXmlOfEvent($eventId);

        $this->importDependencies($eventXml);

        $updated = false;
        do {
            try {
                $event->updateWithCdbXml(
                    $eventXml,
                    $this->cdbXmlService->getCdbXmlNamespaceUri()
                );

                $this->repository->save($event);

                $updated = true;
            } catch (EventStoreException $e) {
                // Collision with another change. Reload the event from the
                // event store and retry.
                $event = $this->loadEvent($eventId);
            }
        } while (!$updated);

        $this->logger->info(
            'Event updated in UDB3',
            [
                'eventId' => $eventId,
            ]
        );

        return $event;
    }

    /**
     * @param string $eventId
     * @return Event
     */
    private function loadEvent($eventId)
    {
        return $this->repository->load($eventId);
    }

    /**
     * @param string $eventId
     * @return string
     * @throws EventNotFoundException
     *   If the event can not be found by the CDBXML service implementation.
     */
    private function getCdbXmlOfEvent($eventId)
    {
        try {
            return $this->cdbXmlService->getCdbXmlOfEvent($eventId);
        } catch (\Exception $e) {
            $this->logger->error(
                $e->getMessage(),
                [
                    'eventId' => $eventId,
                    'exception' => $e,
                ]
            );

            throw $e;
        }
    }

    /**
     * @param string $eventId
     * @param bool $fallbackToUpdate
     * @return Event|null
     * @throws EventNotFoundException
     *   If the event can not be found by the CDBXML service implementation.
     */
    private function create($eventId, $fallbackToUpdate = true)
    {
        $eventXml = $this->getCdbXmlOfEvent($eventId);

        $this->importDependencies($eventXml);

        try {
            $event = Event::importFromUDB2(
                $eventId,
                $eventXml,
                $this->cdbXmlService->getCdbXmlNamespaceUri()
            );

            $this->repository->save($event);

            $this->logger->info(
                'Event created in UDB3',
                [
                    'eventId' => $eventId,
                ]
            );
        } catch (\Exception $e) {
            if ($fallbackToUpdate) {
                $this->logger->notice(
                    "Event creation in UDB3 failed with an exception, will attempt to update the event instead",
                    [
                        'exception' => $e,
                        'eventId' => $eventId
                    ]
                );
                // @todo Differentiate between event exists locally already
                // (same event arriving twice, event created on UDB3 first)
                // and a real error while saving.
                return $this->update($eventId, false);
            } else {
                $this->logger->error(
                    "Event creation in UDB3 failed with an exception",
                    [
                        'exception' => $e,
                        'eventId' => $eventId
                    ]
                );
                return;
            }
        }

        return $event;
    }

    /**
     * @inheritdoc
     */
    public function createEventFromUDB2($eventId)
    {
        return $this->create($eventId);
    }

    /**
     * @param string $eventXml
     * @throws EntityNotFoundException
     * @throws \Exception
     */
    private function importDependencies($eventXml)
    {
        $udb2Event = EventItemFactory::createEventFromCdbXml(
            $this->cdbXmlService->getCdbXmlNamespaceUri(),
            $eventXml
        );

        try {
            $location = $udb2Event->getLocation();
            if ($location && $location->getCdbid()) {
                // Loading the place will implicitly import it, or throw an error
                // if the place is not known.
                $this->placeService->getEntity($location->getCdbid());
            }
        } catch (EntityNotFoundException $e) {
            $this->logger->error(
                "Unable to retrieve location with ID {$location->getCdbid(
                )}, of event {$udb2Event->getCdbId()}."
            );
        }

        try {
            $organizer = $udb2Event->getOrganiser();
            if ($organizer && $organizer->getCdbid()) {
                // Loading the organizer will implicitly import it, or throw an error
                // if the organizer is not known.
                $this->organizerService->getEntity($organizer->getCdbid());
            }
        } catch (EntityNotFoundException $e) {
            $this->logger->error(
                "Unable to retrieve organizer with ID {$organizer->getCdbid(
                )}, of event {$udb2Event->getCdbId()}."
            );
        }
    }
}
