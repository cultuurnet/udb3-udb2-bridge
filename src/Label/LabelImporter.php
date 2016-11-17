<?php

namespace CultuurNet\UDB3\UDB2\Label;

use Broadway\CommandHandling\CommandBusInterface;
use Broadway\EventHandling\EventListenerInterface;
use CultuurNet\UDB3\Cdb\ActorItemFactory;
use CultuurNet\UDB3\Cdb\EventItemFactory;
use CultuurNet\UDB3\Event\Commands\SyncLabels as SyncLabelsOnEvent;
use CultuurNet\UDB3\Event\Events\EventImportedFromUDB2;
use CultuurNet\UDB3\Event\Events\EventUpdatedFromUDB2;
use CultuurNet\UDB3\EventHandling\DelegateEventHandlingToSpecificMethodTrait;
use CultuurNet\UDB3\LabelCollection;
use CultuurNet\UDB3\Offer\Commands\AbstractSyncLabels;
use CultuurNet\UDB3\Place\Commands\SyncLabels as SyncLabelsOnPlace;
use CultuurNet\UDB3\Place\Events\PlaceImportedFromUDB2;
use CultuurNet\UDB3\Place\Events\PlaceUpdatedFromUDB2;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;

class LabelImporter implements EventListenerInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;
    use DelegateEventHandlingToSpecificMethodTrait;

    /**
     * @var CommandBusInterface
     */
    private $commandBus;

    /**
     * LabelImporter constructor.
     * @param CommandBusInterface $commandBus
     */
    public function __construct(
        CommandBusInterface $commandBus
    ) {
        $this->commandBus = $commandBus;

        $this->logger = new NullLogger();
    }

    /**
     * @param EventImportedFromUDB2 $eventImportedFromUDB2
     */
    public function applyEventImportedFromUDB2(
        EventImportedFromUDB2 $eventImportedFromUDB2
    ) {
        $event = EventItemFactory::createEventFromCdbXml(
            $eventImportedFromUDB2->getCdbXmlNamespaceUri(),
            $eventImportedFromUDB2->getCdbXml()
        );

        $this->dispatch(new SyncLabelsOnEvent(
            $eventImportedFromUDB2->getEventId(),
            LabelCollection::fromStrings($event->getKeywords())
        ));
    }

    /**
     * @param PlaceImportedFromUDB2 $placeImportedFromUDB2
     */
    public function applyPlaceImportedFromUDB2(
        PlaceImportedFromUDB2 $placeImportedFromUDB2
    ) {
        $place = ActorItemFactory::createActorFromCdbXml(
            $placeImportedFromUDB2->getCdbXmlNamespaceUri(),
            $placeImportedFromUDB2->getCdbXml()
        );

        $this->dispatch(new SyncLabelsOnPlace(
            $placeImportedFromUDB2->getActorId(),
            LabelCollection::fromStrings($place->getKeywords())
        ));
    }

    /**
     * @param EventUpdatedFromUDB2 $eventUpdatedFromUDB2
     */
    public function applyEventUpdatedFromUDB2(
        EventUpdatedFromUDB2 $eventUpdatedFromUDB2
    ) {
        $event = EventItemFactory::createEventFromCdbXml(
            $eventUpdatedFromUDB2->getCdbXmlNamespaceUri(),
            $eventUpdatedFromUDB2->getCdbXml()
        );

        $this->dispatch(new SyncLabelsOnEvent(
            $eventUpdatedFromUDB2->getEventId(),
            LabelCollection::fromStrings($event->getKeywords())
        ));
    }

    /**
     * @param PlaceUpdatedFromUDB2 $placeUpdatedFromUDB2
     */
    public function applyPlaceUpdatedFromUDB2(
        PlaceUpdatedFromUDB2 $placeUpdatedFromUDB2
    ) {
        $place = ActorItemFactory::createActorFromCdbXml(
            $placeUpdatedFromUDB2->getCdbXmlNamespaceUri(),
            $placeUpdatedFromUDB2->getCdbXml()
        );

        $this->dispatch(new SyncLabelsOnPlace(
            $placeUpdatedFromUDB2->getActorId(),
            LabelCollection::fromStrings($place->getKeywords())
        ));
    }

    private function dispatch(AbstractSyncLabels $syncLabels)
    {
        $this->logger->info(
            'Dispatching SyncLabels with label collection: '
            . join(', ', $syncLabels->getLabelCollection()->toStrings())
        );

        $this->commandBus->dispatch($syncLabels);
    }
}