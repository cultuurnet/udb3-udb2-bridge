<?php

namespace CultuurNet\UDB3\UDB2;

use CultuurNet\Auth\ConsumerCredentials;
use CultuurNet\Auth\Guzzle\HttpClientFactory;
use Guzzle\Http\Client;
use Guzzle\Http\Message\RequestInterface;
use Guzzle\Http\Message\Response;
use ValueObjects\String\String as StringLiteral;

class ActorCdbXmlFromEntryAPITest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ActorCdbXmlFromEntryAPI
     */
    private $service;

    /**
     * @var HttpClientFactory|\PHPUnit_Framework_MockObject_MockObject
     */
    private $clientFactory;

    protected function setUp()
    {
        $this->service = new ActorCdbXmlFromEntryAPI(
            'http://example.com/uitid/rest',
            new ConsumerCredentials(
                'foo',
                'bar'
            ),
            new StringLiteral('user-xyz')
        );

        $this->clientFactory = $this->getMock(HttpClientFactory::class);

        $this->service->setHttpClientFactory($this->clientFactory);
    }

    /**
     * @test
     */
    public function it_retrieves_cdbxml_of_an_actor()
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
                    return $request->getUrl() == '/actor/061C13AC-A15F-F419-D8993D68C9E94548?uid=user-xyz';
                }
            ))
            ->willReturn(new Response(200, null, file_get_contents(__DIR__ . '/samples/entry-api-actor-response.xml')));

        $actorXml = $this->service->getCdbXmlOfActor('061C13AC-A15F-F419-D8993D68C9E94548');

        $this->assertXmlStringEqualsXmlFile(
            __DIR__ . '/samples/search-results-single-actor.xml',
            $actorXml
        );
    }

    /**
     * @test
     */
    public function it_throws_an_error_when_actor_id_is_not_a_string()
    {
        $this->setExpectedException(
            \InvalidArgumentException::class,
            'Expected $actorId to be a string, received value of type double'
        );

        $this->service->getCdbXmlOfActor(12.5);
    }

    /**
     * @test
     */
    public function it_throws_an_error_if_actor_id_is_empty()
    {
        $this->setExpectedException(\InvalidArgumentException::class, '$actorId should not be empty');

        $this->service->getCdbXmlOfActor('');
    }

    /**
     * @test
     */
    public function it_throws_an_error_if_the_actor_cannot_be_found_via_the_entry_api()
    {
        $this->setExpectedException(
            \RuntimeException::class,
            "Actor with cdbid '061C13AC-A15F-F419-D8993D68C9E94548' could not be found in the Entry API response body."
        );

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
                    return $request->getUrl() == '/actor/061C13AC-A15F-F419-D8993D68C9E94548?uid=user-xyz';
                }
            ))
            ->willReturn(
                new Response(404, null, file_get_contents(__DIR__ . '/samples/entry-api-actor-not-found-response.xml'))
            );

        $actorXml = $this->service->getCdbXmlOfActor('061C13AC-A15F-F419-D8993D68C9E94548');
    }
}
