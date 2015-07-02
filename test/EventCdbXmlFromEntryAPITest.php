<?php
/**
 * @file
 */

namespace CultuurNet\UDB3\UDB2;

use CultuurNet\Auth\ConsumerCredentials;
use CultuurNet\Auth\Guzzle\HttpClientFactory;
use Guzzle\Http\Client;
use Guzzle\Http\Message\Request;
use Guzzle\Http\Message\RequestInterface;
use Guzzle\Http\Message\Response;
use Guzzle\Http\QueryString;
use ValueObjects\String\String;

class EventCdbXmlFromEntryAPITest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var EventCdbXmlFromEntryAPI
     */
    private $service;

    /**
     * @var HttpClientFactory|\PHPUnit_Framework_MockObject_MockObject
     */
    private $clientFactory;

    protected function setUp()
    {
        $this->service = new EventCdbXmlFromEntryAPI(
            'http://example.com/uitid/rest',
            new ConsumerCredentials(
                'foo',
                'bar'
            ),
            new String('user-xyz')
        );

        $this->clientFactory = $this->getMock(HttpClientFactory::class);

        $this->service->setHttpClientFactory($this->clientFactory);
    }

    /**
     * @test
     */
    public function it_retrieves_cdbxml_of_an_event()
    {
        /** @var \PHPUnit_Framework_MockObject_MockObject $client */
        $client = $this->getMock(Client::class, ['send']);

        $this->clientFactory->expects($this->once())
            ->method('createClient')
            ->with(
                'http://example.com/uitid/rest',
                new ConsumerCredentials(
                    'foo',
                    'bar'
                )
            )
            ->willReturn(
                $client
            );

        $client->expects($this->once())
            ->method('send')
            ->with($this->callback(
                function (RequestInterface $request) {
                    return $request->getUrl() == '/event/7914ed2d-9f28-4946-b9bd-ae8f7a4aea11?uid=user-xyz';
                }
            ))
            ->willReturn(new Response(200, null, file_get_contents(__DIR__ . '/entry-api-event-response.xml')));

        $eventXml = $this->service->getCdbXmlOfEvent('7914ed2d-9f28-4946-b9bd-ae8f7a4aea11');

        $this->assertXmlStringEqualsXmlFile(
            __DIR__ . '/search-results-single-event.xml',
            $eventXml
        );
    }
}
