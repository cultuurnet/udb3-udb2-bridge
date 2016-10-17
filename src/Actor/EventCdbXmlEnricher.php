<?php

namespace CultuurNet\UDB3\UDB2\Actor;

use Broadway\Domain\DateTime;
use Broadway\Domain\DomainEventStream;
use Broadway\Domain\DomainMessage;
use Broadway\Domain\Metadata;
use Broadway\EventHandling\EventBusInterface;
use Broadway\EventHandling\EventListenerInterface;
use CultuurNet\UDB2DomainEvents\ActorCreated;
use CultuurNet\UDB2DomainEvents\ActorUpdated;
use CultuurNet\UDB3\Cdb\ActorItemFactory;
use CultuurNet\UDB3\EventHandling\DelegateEventHandlingToSpecificMethodTrait;
use CultuurNet\UDB3\UDB2\Actor\Events\ActorCreatedEnrichedWithCdbXml;
use CultuurNet\UDB3\UDB2\Actor\Events\ActorUpdatedEnrichedWithCdbXml;
use CultuurNet\UDB3\UDB2\ActorNotFoundException;
use DOMDocument;
use GuzzleHttp\Psr7\Request;
use Http\Client\HttpClient;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use ValueObjects\Identity\UUID;
use ValueObjects\String\String as StringLiteral;
use ValueObjects\Web\Url;
use XMLReader;

/**
 * Creates new event messages based on incoming UDB2 events, enriching them with
 * cdb xml so other components do not need to take care of that themselves.
 */
class EventCdbXmlEnricher implements EventListenerInterface, LoggerAwareInterface
{
    use DelegateEventHandlingToSpecificMethodTrait;
    use LoggerAwareTrait;

    /**
     * @var HttpClient
     */
    protected $httpClient;

    /**
     * @var EventBusInterface
     */
    protected $eventBus;

    /**
     * @var array
     */
    protected $logContext;

    /**
     * @param EventBusInterface $eventBus
     * @param HttpClient $httpClient
     */
    public function __construct(
        EventBusInterface $eventBus,
        HttpClient $httpClient
    ) {
        $this->eventBus = $eventBus;
        $this->httpClient = $httpClient;
        $this->logger = new NullLogger();
    }

    /**
     * @param DomainMessage $domainMessage
     */
    private function setLogContextFromDomainMessage(
        DomainMessage $domainMessage
    ) {
        $this->logContext = [];

        $metadata = $domainMessage->getMetadata()->serialize();
        if (isset($metadata['correlation_id'])) {
            $this->logContext['correlation_id'] = $metadata['correlation_id'];
        }
    }

    /**
     * @param ActorCreated $actorCreated
     * @param DomainMessage $message
     */
    private function applyActorCreated(
        ActorCreated $actorCreated,
        DomainMessage $message
    ) {
        $this->setLogContextFromDomainMessage($message);

        $xml = $this->getActorXml($actorCreated->getUrl());

        $enrichedActorCreated = ActorCreatedEnrichedWithCdbXml::fromActorCreated(
            $actorCreated,
            $xml,
            new StringLiteral(
                \CultureFeed_Cdb_Xml::namespaceUriForVersion('3.3')
            )
        );

        $this->publish(
            $enrichedActorCreated,
            $message->getMetadata()
        );
    }

    /**
     * @param ActorUpdated $actorUpdated
     * @param DomainMessage $message
     */
    private function applyActorUpdated(
        ActorUpdated $actorUpdated,
        DomainMessage $message
    ) {
        $this->setLogContextFromDomainMessage($message);

        $xml = $this->getActorXml($actorUpdated->getUrl());

        $enrichedActorUpdated = ActorUpdatedEnrichedWithCdbXml::fromActorUpdated(
            $actorUpdated,
            $xml,
            new StringLiteral(
                \CultureFeed_Cdb_Xml::namespaceUriForVersion('3.3')
            )
        );

        $this->publish(
            $enrichedActorUpdated,
            $message->getMetadata()
        );
    }

    /**
     * @param object $payload
     * @param Metadata $metadata
     */
    private function publish($payload, Metadata $metadata)
    {
        $message = new DomainMessage(
            UUID::generateAsString(),
            1,
            $metadata,
            $payload,
            DateTime::now()
        );

        $domainEventStream = new DomainEventStream([$message]);
        $this->eventBus->publish($domainEventStream);
    }

    /**
     * @param Url $url
     * @return StringLiteral
     *
     * @throws ActorNotFoundException
     */
    private function getActorXml(Url $url)
    {
        $lastSlashPosition = strrpos($url, '/') + 1;
        $cdbid = substr($url, $lastSlashPosition, strlen($url) - $lastSlashPosition);

        $url = Url::fromNative('http://search-prod.lodgon.com/search/rest/detail/actor/' . $cdbid . '?noauth=true&version=3.3');

        $this->logger->debug('retrieving cdbxml from ' . (string)$url);

        $request = new Request(
            'GET',
            (string)$url,
            [
                'Accept' => 'application/xml',
            ]
        );

        $response = $this->httpClient->sendRequest($request);

        if (200 !== $response->getStatusCode()) {
            $this->logger->error(
                'Unable to retrieve cdbxml, server responded with ' .
                $response->getStatusCode() . ' ' . $response->getReasonPhrase(),
                [
                    'url' => (string)$url
                ]
            );

            throw new ActorNotFoundException(
                'Unable to retrieve actor from ' . (string)$url
            );
        }

        $xml = $response->getBody()->getContents();

        $eventXml = $this->extractActorElement($xml);

        return new StringLiteral($eventXml);
    }

    /**
     * @param string $cdbXml
     * @return string
     * @throws \RuntimeException
     */
    private function extractActorElement($cdbXml)
    {
        $reader = new XMLReader();
        $reader->xml($cdbXml);

        while ($reader->read()) {
            switch ($reader->nodeType) {
                case ($reader::ELEMENT):
                    if ($reader->localName === 'actor') {
                        $node = $reader->expand();
                        $dom = new DomDocument('1.0');
                        $n = $dom->importNode($node, true);
                        $dom->appendChild($n);
                        return $dom->saveXML();
                    }
            }
        }

        throw new \RuntimeException(
            "Actor could not be found in the Entry API response body."
        );
    }
}
