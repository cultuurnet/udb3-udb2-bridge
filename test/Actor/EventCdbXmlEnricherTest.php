<?php
/**
 * @file
 */

namespace CultuurNet\UDB3\UDB2\Actor;

use Broadway\Domain\DomainMessage;
use Broadway\Domain\Metadata;
use Broadway\EventHandling\SimpleEventBus;
use Broadway\EventHandling\TraceableEventBus;
use CultuurNet\UDB2DomainEvents\ActorCreated;
use CultuurNet\UDB2DomainEvents\ActorUpdated;
use CultuurNet\UDB3\UDB2\Actor\Events\ActorCreatedEnrichedWithCdbXml;
use CultuurNet\UDB3\UDB2\Actor\Events\ActorUpdatedEnrichedWithCdbXml;
use CultuurNet\UDB3\UDB2\ActorCdbXmlServiceInterface;
use CultuurNet\UDB3\UDB2\OutdatedXmlRepresentationException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Http\Client\HttpClient;
use ValueObjects\Identity\UUID;
use ValueObjects\String\String;
use ValueObjects\Web\Url;

class EventCdbXmlEnricherTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var TraceableEventBus
     */
    private $eventBus;

    /**
     * @var HttpClient|\PHPUnit_Framework_MockObject_MockObject
     */
    private $httpClient;

    /**
     * @var EventCdbXmlEnricher
     */
    private $enricher;

    public function setUp()
    {
        $this->eventBus = new TraceableEventBus(
            new SimpleEventBus()
        );

        $this->eventBus->trace();

        $this->httpClient = $this->getMock(
            HttpClient::class
        );

        $this->enricher = new EventCdbXmlEnricher(
            $this->eventBus,
            $this->httpClient
        );
    }

    private function expectHttpClientToReturnCdbXmlFromUrl($url)
    {
        $request = new Request(
            'GET',
            (string)$url,
            [
                'Accept' => 'application/xml',
            ]
        );

        $response = new Response(
            200,
            [],
            $this->cdbXml()
        );

        $this->httpClient->expects($this->once())
            ->method('sendRequest')
            ->with($request)
            ->willReturn($response);
    }

    private function cdbXml()
    {
        return file_get_contents(__DIR__ . '/Events/actor.xml');
    }

    private function cdbXmlNamespaceUri()
    {
        return 'http://www.cultuurdatabank.com/XMLSchema/CdbXSD/3.3/FINAL';
    }

    /**
     * Data provider with for each incoming message a corresponding expected new
     * message.
     */
    public function messagesProvider()
    {
        $actorCreated = $this->newActorCreated(
            new \DateTimeImmutable(
                '2013-07-18T09:04:37',
                new \DateTimeZone('Europe/Brussels')
            )
        );

        $actorUpdated = $this->newActorUpdated(
            new \DateTimeImmutable(
                '2013-07-18T09:04:37',
                new \DateTimeZone('Europe/Brussels')
            )
        );

        return [
            [
                $actorCreated,
                new ActorCreatedEnrichedWithCdbXml(
                    $actorCreated->getActorId(),
                    $actorCreated->getTime(),
                    $actorCreated->getAuthor(),
                    $actorCreated->getUrl(),
                    new String($this->cdbXml()),
                    new String($this->cdbXmlNamespaceUri())
                )
            ],
            [
                $actorUpdated,
                new ActorUpdatedEnrichedWithCdbXml(
                    $actorUpdated->getActorId(),
                    $actorUpdated->getTime(),
                    $actorUpdated->getAuthor(),
                    $actorCreated->getUrl(),
                    new String($this->cdbXml()),
                    new String($this->cdbXmlNamespaceUri())
                )
            ]
        ];
    }

    private function publish($payload)
    {
        $this->enricher->handle(
            DomainMessage::recordNow(
                UUID::generateAsString(),
                0,
                new Metadata(),
                $payload
            )
        );
    }

    private function newActorCreated(\DateTimeImmutable $time)
    {
        $actorId = new String('foo');
        $author = new String('me@example.com');
        $url = Url::fromNative('http://www.some.url');

        return new ActorCreated(
            $actorId,
            $time,
            $author,
            $url
        );
    }

    private function newActorUpdated(\DateTimeImmutable $time)
    {
        $actorId = new String('foo');
        $author = new String('me@example.com');
        $url = Url::fromNative('http://www.some.url');

        return new ActorUpdated(
            $actorId,
            $time,
            $author,
            $url
        );
    }

    /**
     * @dataProvider messagesProvider
     * @test
     * @param ActorUpdated|ActorCreated $incomingEvent
     * @param ActorUpdatedEnrichedWithCdbXml|ActorCreatedEnrichedWithCdbXml $newEvent
     */
    public function it_publishes_a_new_message_enriched_with_xml(
        $incomingEvent,
        $newEvent
    ) {
        $this->expectHttpClientToReturnCdbXmlFromUrl(
            $incomingEvent->getUrl()
        );

        $this->publish($incomingEvent);

        $this->assertTracedEvents(
            [
                $newEvent
            ]
        );
    }

    /**
     * @param object[] $expectedEvents
     */
    protected function assertTracedEvents($expectedEvents)
    {
        $events = $this->eventBus->getEvents();

        $this->assertEquals(
            $expectedEvents,
            $events
        );
    }
}
