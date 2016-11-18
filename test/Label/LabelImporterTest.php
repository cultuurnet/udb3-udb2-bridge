<?php

namespace CultuurNet\UDB3\UDB2\Label;

use Broadway\CommandHandling\CommandBusInterface;
use CultuurNet\UDB3\Event\Commands\SyncLabels as SyncLabelsOnEvent;
use CultuurNet\UDB3\Event\Events\EventImportedFromUDB2;
use CultuurNet\UDB3\Event\Events\EventUpdatedFromUDB2;
use CultuurNet\UDB3\Label;
use CultuurNet\UDB3\Label\LabelServiceInterface;
use CultuurNet\UDB3\Label\ValueObjects\LabelName;
use CultuurNet\UDB3\LabelCollection;
use CultuurNet\UDB3\Place\Commands\SyncLabels as SyncLabelsOnPlace;
use CultuurNet\UDB3\Place\Events\PlaceImportedFromUDB2;
use CultuurNet\UDB3\Place\Events\PlaceUpdatedFromUDB2;

class LabelImporterTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var LabelServiceInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $labelService;

    /**
     * @var LabelImporter
     */
    private $labelImporter;

    protected function setUp()
    {
        $this->labelService = $this->getMock(LabelServiceInterface::class);

        $this->labelImporter = new LabelImporter($this->labelService);

        $this->labelService->expects($this->at(0))
            ->method('createLabelAggregateIfNew')
            ->with(new LabelName('2dotstwice'), true);

        $this->labelService->expects($this->at(1))
            ->method('createLabelAggregateIfNew')
            ->with(new LabelName('cultuurnet'), false);
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

        $this->labelImporter->applyPlaceUpdatedFromUDB2($placeUpdatedFromUDB2);
    }
}
