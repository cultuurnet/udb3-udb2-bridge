<?php

namespace CultuurNet\UDB3\UDB2\Label;

use Broadway\CommandHandling\CommandBusInterface;
use CultuurNet\UDB3\Event\Commands\SyncLabels as SyncLabelsOnEvent;
use CultuurNet\UDB3\Event\Events\EventImportedFromUDB2;
use CultuurNet\UDB3\Event\Events\EventUpdatedFromUDB2;
use CultuurNet\UDB3\Label;
use CultuurNet\UDB3\LabelCollection;
use CultuurNet\UDB3\Place\Commands\SyncLabels as SyncLabelsOnPlace;
use CultuurNet\UDB3\Place\Events\PlaceImportedFromUDB2;
use CultuurNet\UDB3\Place\Events\PlaceUpdatedFromUDB2;

class LabelImporterTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var CommandBusInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $commandBus;

    /**
     * @var LabelImporter
     */
    private $labelImporter;

    /**
     * @var LabelCollection
     */
    private $labelCollection;

    protected function setUp()
    {
        $this->commandBus = $this->getMock(CommandBusInterface::class);

        $this->labelImporter = new LabelImporter($this->commandBus);

        $this->labelCollection = new LabelCollection(
            [
                new Label('2dotstwice'),
                new Label('cultuurnet', false),
            ]
        );
    }

    /**
     * @test
     */
    public function it_dispatches_sync_labels_commands_when_applying_event_imported_from_udb2()
    {
        $cdbXml = file_get_contents(__DIR__ . '/Samples/event.xml');
        $cdbXmlNamespaceUri = \CultureFeed_Cdb_Xml::namespaceUriForVersion('3.3');

        $eventImportedFromUDB2 = new EventImportedFromUDB2(
            'd53c2bc9-8f0e-4c9a-8457-77e8b3cab3d1',
            $cdbXml,
            $cdbXmlNamespaceUri
        );

        $this->commandBus->expects($this->once())
            ->method('dispatch')
            ->with(new SyncLabelsOnEvent(
                $eventImportedFromUDB2->getEventId(),
                $this->labelCollection
            ));

        $this->labelImporter->applyEventImportedFromUDB2($eventImportedFromUDB2);
    }

    /**
     * @test
     */
    public function it_dispatches_label_added_commands_when_applying_place_imported_from_udb2()
    {
        $cdbXml = file_get_contents(__DIR__ . '/Samples/place.xml');
        $cdbXmlNamespaceUri = \CultureFeed_Cdb_Xml::namespaceUriForVersion('3.3');

        $placeImportedFromUDB2 = new PlaceImportedFromUDB2(
            '764066ab-826f-48c2-897d-a329ebce953f',
            $cdbXml,
            $cdbXmlNamespaceUri
        );

        $this->commandBus->expects($this->once())
            ->method('dispatch')
            ->with(new SyncLabelsOnPlace(
                $placeImportedFromUDB2->getActorId(),
                $this->labelCollection
            ));

        $this->labelImporter->applyPlaceImportedFromUDB2($placeImportedFromUDB2);
    }

    /**
     * @test
     */
    public function it_dispatches_sync_labels_commands_when_applying_event_updated_from_udb2()
    {
        $cdbXml = file_get_contents(__DIR__ . '/Samples/event.xml');
        $cdbXmlNamespaceUri = \CultureFeed_Cdb_Xml::namespaceUriForVersion('3.3');

        $eventUpdatedFromUDB2 = new EventUpdatedFromUDB2(
            'd53c2bc9-8f0e-4c9a-8457-77e8b3cab3d1',
            $cdbXml,
            $cdbXmlNamespaceUri
        );

        $this->commandBus->expects($this->once())
            ->method('dispatch')
            ->with(new SyncLabelsOnEvent(
                $eventUpdatedFromUDB2->getEventId(),
                $this->labelCollection
            ));

        $this->labelImporter->applyEventUpdatedFromUDB2($eventUpdatedFromUDB2);
    }

    /**
     * @test
     */
    public function it_dispatches_label_added_commands_when_applying_place_updated_from_udb2()
    {
        $cdbXml = file_get_contents(__DIR__ . '/Samples/place.xml');
        $cdbXmlNamespaceUri = \CultureFeed_Cdb_Xml::namespaceUriForVersion('3.3');

        $placeUpdatedFromUDB2 = new PlaceUpdatedFromUDB2(
            '764066ab-826f-48c2-897d-a329ebce953f',
            $cdbXml,
            $cdbXmlNamespaceUri
        );

        $this->commandBus->expects($this->once())
            ->method('dispatch')
            ->with(new SyncLabelsOnPlace(
                $placeUpdatedFromUDB2->getActorId(),
                $this->labelCollection
            ));

        $this->labelImporter->applyPlaceUpdatedFromUDB2($placeUpdatedFromUDB2);
    }
}
