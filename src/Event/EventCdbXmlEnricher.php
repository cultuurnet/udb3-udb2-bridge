<?php
/**
 * @file
 */

namespace CultuurNet\UDB3\UDB2\Event;

use Broadway\Domain\DateTime;
use Broadway\Domain\DomainEventStream;
use Broadway\Domain\DomainMessage;
use Broadway\Domain\Metadata;
use Broadway\EventHandling\EventBusInterface;
use Broadway\EventHandling\EventListenerInterface;
use CultureFeed_Cdb_Xml;
use CultuurNet\UDB2DomainEvents\EventCreated;
use CultuurNet\UDB2DomainEvents\EventUpdated;
use CultuurNet\UDB3\EventHandling\DelegateEventHandlingToSpecificMethodTrait;
use CultuurNet\UDB3\UDB2\Event\Events\EventCreatedEnrichedWithCdbXml;
use CultuurNet\UDB3\UDB2\Event\Events\EventUpdatedEnrichedWithCdbXml;
use CultuurNet\UDB3\UDB2\EventNotFoundException;
use DomDocument;
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
 * Republishes incoming UDB2 events enriched with their cdbxml.
 */
class EventCdbXmlEnricher implements EventListenerInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;
    use DelegateEventHandlingToSpecificMethodTrait;

    /**
     * @var EventBusInterface
     */
    protected $eventBus;

    /**
     * @var HttpClient
     */
    protected $httpClient;

    /**
     * @var StringLiteral
     */
    protected $cdbXmlNamespaceUri;

    /**
     * @param EventBusInterface $eventBus
     */
    public function __construct(
        EventBusInterface $eventBus,
        HttpClient $httpClient
    ) {
        $this->eventBus = $eventBus;
        $this->httpClient = $httpClient;
        $this->cdbXmlNamespaceUri = new StringLiteral(
            CultureFeed_Cdb_Xml::namespaceUriForVersion('3.3')
        );
        $this->logger = new NullLogger();
    }

    protected function applyEventUpdated(
        EventUpdated $eventUpdated,
        DomainMessage $message
    ) {
        $this->logger->debug(
            'UDB2 event updated, with url ' . $eventUpdated->getUrl()
        );

        $xml = $this->retrieveXml($eventUpdated->getUrl());

        $enrichedEventUpdated = EventUpdatedEnrichedWithCdbXml::fromEventUpdated(
            $eventUpdated,
            $xml,
            $this->cdbXmlNamespaceUri
        );

        $this->publish(
            $enrichedEventUpdated,
            $message->getMetadata()
        );
    }

    protected function applyEventCreated(
        EventCreated $eventCreated,
        DomainMessage $message
    ) {
        $this->logger->debug(
            'UDB2 event created, with url ' . $eventCreated->getUrl()
        );

        $xml = $this->retrieveXml($eventCreated->getUrl());

        $enrichedEventCreated = EventCreatedEnrichedWithCdbXml::fromEventCreated(
            $eventCreated,
            $xml,
            $this->cdbXmlNamespaceUri
        );

        $this->publish(
            $enrichedEventCreated,
            $message->getMetadata()
        );
    }

    /**
     * @param Url $url
     *
     * @return StringLiteral
     */
    private function retrieveXml(Url $url)
    {
        $lastSlashPosition = strrpos($url, '/') + 1;
        $cdbid = substr($url, $lastSlashPosition, strlen($url) - $lastSlashPosition);

        $url = Url::fromNative('http://search-prod.lodgon.com/search/rest/detail/event/' . $cdbid . '?noauth=true&version=3.3');

        $this->logger->debug('retrieving cdbxml from ' . (string)$url);

        $request = new Request(
            'GET',
            (string)$url,
            [
                'Accept' => 'application/xml',
            ]
        );

        $startTime = microtime(true);

        $response = $this->httpClient->sendRequest($request);

        $delta = round(microtime(true) - $startTime, 3) * 1000;
        $this->logger->debug('sendRequest took ' . $delta . ' ms.');
        
        if (200 !== $response->getStatusCode()) {
            $this->logger->error(
                'Unable to retrieve cdbxml, server responded with ' .
                $response->getStatusCode() . ' ' . $response->getReasonPhrase(),
                [
                    'url' => (string)$url
                ]
            );

            throw new EventNotFoundException(
                'Unable to retrieve event from ' . (string)$url
            );
        }

        $xml = $response->getBody()->getContents();

        $eventXml = $this->extractEventElement($xml);

        return new StringLiteral($eventXml);
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
     * @param string $cdbXml
     * @param string $eventId
     * @return string
     * @throws \RuntimeException
     */
    private function extractEventElement($cdbXml)
    {
        $reader = new XMLReader();
        $reader->xml($cdbXml);

        while ($reader->read()) {
            switch ($reader->nodeType) {
                case ($reader::ELEMENT):
                    if ($reader->localName === 'event') {
                        $node = $reader->expand();
                        $dom = new DomDocument('1.0');
                        $n = $dom->importNode($node, true);
                        $dom->appendChild($n);
                        return $dom->saveXML();
                    }
            }
        }

        throw new \RuntimeException(
            "Event could not be found in the Entry API response body."
        );
    }
}
