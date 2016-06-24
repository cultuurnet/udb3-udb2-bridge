<?php
/**
 * @file
 */

namespace CultuurNet\UDB3\UDB2\Place;

use Broadway\EventHandling\EventBusInterface;
use Broadway\EventStore\InMemoryEventStore;
use Broadway\EventStore\TraceableEventStore;
use CultuurNet\UDB3\Place\Events\PlaceImportedFromUDB2;
use CultuurNet\UDB3\Place\Events\PlaceImportedFromUDB2Event;
use CultuurNet\UDB3\Place\Place;
use CultuurNet\UDB3\Place\PlaceRepository;
use CultuurNet\UDB3\UDB2\ActorCdbXmlServiceInterface;
use CultuurNet\UDB3\UDB2\ActorNotFoundException;
use CultuurNet\UDB3\UDB2\EventCdbXmlServiceInterface;
use CultuurNet\UDB3\UDB2\EventNotFoundException;
use Psr\Log\LoggerInterface;

class PlaceCdbXmlImporterTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var PlaceCdbXmlImporter
     */
    private $importer;

    /**
     * @var PlaceRepository
     */
    private $repository;

    /**
     * @var ActorCdbXmlServiceInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $actorCdbXmlService;

    /**
     * @var EventCdbXmlServiceInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $eventCdbXmlService;

    /**
     * @var TraceableEventStore
     */
    private $store;

    public function setUp()
    {
        $this->store = new TraceableEventStore(
            new InMemoryEventStore()
        );

        /** @var EventBusInterface $eventBus */
        $eventBus = $this->getMock(
            EventBusInterface::class
        );

        $this->repository = new PlaceRepository(
            $this->store,
            $eventBus,
            []
        );

        $this->actorCdbXmlService = $this->getMock(ActorCdbXmlServiceInterface::class);
        $this->eventCdbXmlService = $this->getMock(EventCdbXmlServiceInterface::class);

        $this->importer = new PlaceCdbXmlImporter(
            $this->repository,
            $this->actorCdbXmlService,
            $this->eventCdbXmlService
        );

    }

    /**
     * @test
     */
    public function it_creates_a_place_from_cdbxml()
    {
        $this->store->trace();

        $placeId = '404EE8DE-E828-9C07-FE7D12DC4EB24480';

        $cdbXml = file_get_contents(__DIR__ . '/../samples/actor.xml');
        $cdbXmlNamespaceUri = 'http://www.cultuurdatabank.com/XMLSchema/CdbXSD/3.3/FINAL';

        $this->actorCdbXmlService->expects($this->once())
            ->method('getCdbXmlOfActor')
            ->willReturn($cdbXml);

        $this->actorCdbXmlService->expects($this->atLeastOnce())
            ->method('getCdbXmlNamespaceUri')
            ->willReturn($cdbXmlNamespaceUri);

        $place = $this->importer->createPlaceFromUDB2($placeId);

        $this->assertInstanceOf(Place::class, $place);

        $this->assertTracedEvents(
            [
                new PlaceImportedFromUDB2(
                    $placeId,
                    $cdbXml,
                    $cdbXmlNamespaceUri
                ),
            ]
        );
    }

    /**
     * @test
     */
    public function it_does_not_create_a_place_from_cdbxml_with_an_external_url()
    {
        $this->store->trace();

        $placeId = '404EE8DE-E828-9C07-FE7D12DC4EB24480';

        $cdbXml = file_get_contents(__DIR__ . '/../samples/place-actor-with-externalurl.xml');
        $cdbXmlNamespaceUri = 'http://www.cultuurdatabank.com/XMLSchema/CdbXSD/3.3/FINAL';

        $this->actorCdbXmlService->expects($this->once())
            ->method('getCdbXmlOfActor')
            ->willReturn($cdbXml);

        $this->actorCdbXmlService->expects($this->atLeastOnce())
            ->method('getCdbXmlNamespaceUri')
            ->willReturn($cdbXmlNamespaceUri);

        $place = $this->importer->createPlaceFromUDB2($placeId);

        $this->assertNull($place);

        $this->assertTracedEvents([]);
    }

    /**
     * @test
     */
    public function it_creates_a_place_from_cdbxml_event()
    {
        $this->store->trace();

        $placeId = 'bf5fee06-4f1a-410b-97d8-b8d48351419c';

        $cdbXml = file_get_contents(__DIR__ . '/../samples/place-event-without-externalurl.xml');
        $cdbXmlNamespaceUri = 'http://www.cultuurdatabank.com/XMLSchema/CdbXSD/3.3/FINAL';

        $this->actorCdbXmlService->expects($this->once())
            ->method('getCdbXmlOfActor')
            ->with($placeId)
            ->willThrowException(new ActorNotFoundException());

        $this->eventCdbXmlService->expects($this->once())
            ->method('getCdbXmlOfEvent')
            ->with($placeId)
            ->willReturn($cdbXml);

        $this->eventCdbXmlService->expects($this->atLeastOnce())
            ->method('getCdbXmlNamespaceUri')
            ->willReturn($cdbXmlNamespaceUri);

        $place = $this->importer->createPlaceFromUDB2($placeId);

        $this->assertInstanceOf(Place::class, $place);

        $this->assertTracedEvents(
            [
                new PlaceImportedFromUDB2Event(
                    $placeId,
                    $cdbXml,
                    $cdbXmlNamespaceUri
                ),
            ]
        );
    }

    /**
     * @test
     */
    public function it_does_not_create_a_place_from_an_event_with_an_external_url()
    {
        $this->store->trace();

        $placeId = 'bf5fee06-4f1a-410b-97d8-b8d48351419c';

        $cdbXml = file_get_contents(__DIR__ . '/../samples/place-event-with-externalurl.xml');
        $cdbXmlNamespaceUri = 'http://www.cultuurdatabank.com/XMLSchema/CdbXSD/3.3/FINAL';

        $this->actorCdbXmlService->expects($this->once())
            ->method('getCdbXmlOfActor')
            ->willThrowException(new ActorNotFoundException());

        $this->eventCdbXmlService->expects($this->once())
            ->method('getCdbXmlOfEvent')
            ->willReturn($cdbXml);

        $this->eventCdbXmlService->expects($this->atLeastOnce())
            ->method('getCdbXmlNamespaceUri')
            ->willReturn($cdbXmlNamespaceUri);

        $place = $this->importer->createPlaceFromUDB2($placeId);

        $this->assertNull($place);

        $this->assertTracedEvents([]);
    }

    /**
     * @test
     */
    public function it_returns_nothing_if_creation_failed()
    {
        $this->actorCdbXmlService->expects($this->once())
            ->method('getCdbXmlOfActor')
            ->willThrowException(new ActorNotFoundException());

        $this->eventCdbXmlService->expects($this->once())
            ->method('getCdbXmlOfEvent')
            ->willThrowException(new EventNotFoundException());

        $place = $this->importer->createPlaceFromUDB2('foo');

        $this->assertNull($place);
    }

    /**
     * @test
     */
    public function it_logs_creation_failures()
    {
        $actorException = new ActorNotFoundException();
        $eventException = new EventNotFoundException();

        $this->actorCdbXmlService->expects($this->once())
            ->method('getCdbXmlOfActor')
            ->willThrowException($actorException);

        $this->eventCdbXmlService->expects($this->once())
            ->method('getCdbXmlOfEvent')
            ->willThrowException($eventException);

        $logger = $this->getMock(LoggerInterface::class);
        $this->importer->setLogger($logger);

        $logger->expects($this->exactly(2))
            ->method('notice')
            ->withConsecutive(
                [
                    'Place creation in UDB3 failed with an exception',
                    [
                        'exception' => $actorException,
                        'placeId' => 'foo',
                    ]
                ],
                [
                    'Place creation in UDB3 failed with an exception',
                    [
                        'exception' => $eventException,
                        'placeId' => 'foo',
                    ]
                ]
            );

        $this->importer->createPlaceFromUDB2('foo');
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
