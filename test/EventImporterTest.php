<?php
/**
 * @file
 */

namespace CultuurNet\UDB3\UDB2;

use Broadway\EventHandling\EventBusInterface;
use Broadway\EventStore\InMemoryEventStore;
use Broadway\EventStore\TraceableEventStore;
use CultuurNet\UDB3\Address\Address;
use CultuurNet\UDB3\Address\Locality;
use CultuurNet\UDB3\Address\PostalCode;
use CultuurNet\UDB3\Address\Street;
use CultuurNet\UDB3\Calendar;
use CultuurNet\UDB3\CalendarType;
use CultuurNet\UDB3\Event\Event;
use CultuurNet\UDB3\Event\EventRepository;
use CultuurNet\UDB3\Event\Events\EventUpdatedFromUDB2;
use CultuurNet\UDB3\Event\EventType;
use CultuurNet\UDB3\Event\ReadModel\JSONLD\OrganizerServiceInterface;
use CultuurNet\UDB3\Event\ReadModel\JSONLD\PlaceServiceInterface;
use CultuurNet\UDB3\Location\Location;
use CultuurNet\UDB3\OrganizerService;
use CultuurNet\UDB3\PlaceService;
use CultuurNet\UDB3\Title;
use CultuurNet\UDB3\Cdb\CdbId\EventRelatedCdbIdExtractor;
use CultuurNet\UDB3\Cdb\ExternalId\ArrayMappingService;
use ValueObjects\Geography\Country;
use ValueObjects\String\String as StringLiteral;

class EventImporterTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var EventImporter
     */
    protected $importer;

    /**
     * @var EventRepository
     */
    protected $repository;

    /**
     * @var TraceableEventStore
     */
    protected $store;

    /**
     * @var EventCdbXmlServiceInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $eventCdbXmlService;

    /**
     * @var PlaceServiceInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $placeService;

    /**
     * @var OrganizerServiceInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $organizerService;

    /**
     * @var EventRelatedCdbIdExtractor
     */
    protected $cdbIdExtractor;

    public function setUp()
    {
        $this->store = new TraceableEventStore(
            new InMemoryEventStore()
        );

        /** @var EventBusInterface $eventBus */
        $eventBus = $this->getMock(
            EventBusInterface::class
        );

        $this->repository = new EventRepository(
            $this->store,
            $eventBus,
            []
        );

        $this->eventCdbXmlService = $this->getMock(
            EventCdbXmlServiceInterface::class
        );

        $this->eventCdbXmlService->expects($this->any())
            ->method('getCdbXmlNamespaceUri')
            ->willReturn('http://www.cultuurdatabank.com/XMLSchema/CdbXSD/3.3/FINAL');

        $this->placeService = $this->getMock(
            PlaceService::class,
            [],
            [],
            '',
            false
        );

        $this->organizerService = $this->getMock(
            OrganizerService::class,
            [],
            [],
            '',
            false
        );

        $this->cdbIdExtractor = new EventRelatedCdbIdExtractor(
            new ArrayMappingService([])
        );

        $this->importer = new EventImporter(
            $this->eventCdbXmlService,
            $this->repository,
            $this->placeService,
            $this->organizerService,
            $this->cdbIdExtractor
        );
    }

    /**
     * @test
     */
    public function it_does_not_create_an_event_from_cdbxml_with_an_external_url()
    {
        $this->store->trace();

        $cdbId = '7914ed2d-9f28-4946-b9bd-ae8f7a4aea11';

        $cdbXml = file_get_contents(__DIR__ . '/samples/event-with-external-url.xml');

        $this->eventCdbXmlService
            ->expects($this->once())
            ->method('getCdbXmlOfEvent')
            ->with($cdbId)
            ->willReturn($cdbXml);

        $event = $this->importer->createEventFromUDB2($cdbId);

        $this->assertNull($event);

        $this->assertTracedEvents([]);
    }

    /**
     * @test
     */
    public function it_updates_an_existing_event_with_cdbxml()
    {
        if (!class_exists(Title::class)) {
            $this->markTestSkipped('Required class Title not available');
        }

        $cdbId = '7914ed2d-9f28-4946-b9bd-ae8f7a4aea11';

        $event = Event::create(
            $cdbId,
            new Title('Infodag Sint-Lukas Brussel'),
            new EventType('0.12.0.0.0', 'Opendeurdag'),
            new Location(
                'd0cd4e9d-3cf1-4324-9835-2bfba63ac015',
                new StringLiteral('Repeteerkot'),
                new Address(
                    new Street('Kerkstraat 69'),
                    new PostalCode('9630'),
                    new Locality('Zottegem'),
                    Country::fromNative('BE')
                )
            ),
            new Calendar(
                CalendarType::SINGLE(),
                \DateTime::createFromFormat(\DateTime::ATOM, '2015-01-26T13:25:21+01:00')
            )
        );
        $this->repository->save($event);

        $eventXml = file_get_contents(
            __DIR__ . '/samples/event.xml'
        );

        $this->eventCdbXmlService
            ->expects($this->once())
            ->method('getCdbXmlOfEvent')
            ->with($cdbId)
            ->willReturn($eventXml);

        $this->store->trace();

        $this->importer->updateEventFromUDB2($cdbId);

        $this->assertTracedEvents(
            [
                new EventUpdatedFromUDB2(
                    $cdbId,
                    $eventXml,
                    $this->eventCdbXmlService->getCdbXmlNamespaceUri()
                ),
            ]
        );
    }

    /**
     * @param object[] $expectedEvents
     */
    protected function assertTracedEvents($expectedEvents)
    {
        $events = $this->store->getEvents();

        $this->assertEquals(
            $expectedEvents,
            $events
        );
    }
}
