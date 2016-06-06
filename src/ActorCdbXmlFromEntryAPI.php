<?php

namespace CultuurNet\UDB3\UDB2;

use CultuurNet\Auth\ConsumerCredentials;
use CultuurNet\Auth\Guzzle\OAuthProtectedService;
use Guzzle\Http\Exception\ClientErrorResponseException;
use ValueObjects\String\String as StringLiteral;

/**
 * Service to retrieve actor CDBXML, from the Entry API.
 *
 * This uses the 'Light UiTID' authentication, as described in
 * https://docs.google.com/document/d/14vteMLuhDbUbn_49WMoGxHXtGJIpMaD7fqoR7VyQDwI/edit#.
 */
class ActorCdbXmlFromEntryAPI extends OAuthProtectedService implements ActorCdbXmlServiceInterface
{
    /**
     * @var String
     */
    private $userId;

    /**
     * @var string
     */
    private $cdbXmlNamespaceUri;

    /**
     * @param string $baseUrl
     * @param ConsumerCredentials $consumerCredentials
     * @param StringLiteral $userId
     * @param string $cdbXmlNamespaceUri
     */
    public function __construct(
        $baseUrl,
        ConsumerCredentials $consumerCredentials,
        StringLiteral $userId,
        $cdbXmlNamespaceUri = 'http://www.cultuurdatabank.com/XMLSchema/CdbXSD/3.2/FINAL'
    ) {
        parent::__construct(
            $baseUrl,
            $consumerCredentials
        );

        $this->userId = $userId;
        $this->cdbXmlNamespaceUri = $cdbXmlNamespaceUri;
    }

    /**
     * @inheritdoc
     */
    protected function getClient(array $additionalOAuthParameters = array())
    {
        $client = parent::getClient($additionalOAuthParameters);
        $client->setDefaultOption(
            'headers',
            [
                'Accept' => 'text/xml'
            ]
        );
        $client->setDefaultOption(
            'query',
            [
                'uid' => (string) $this->userId
            ]
        );

        return $client;
    }

    /**
     * @inheritdoc
     */
    public function getCdbXmlOfActor($actorId)
    {
        $this->guardActorId($actorId);

        $request = $this->getClient()->get('actor/' . $actorId);

        try {
            $response = $request->send();
        } catch (ClientErrorResponseException $e) {
            if ($e->getResponse()->getStatusCode() == '404') {
                throw new ActorNotFoundException(
                    "Actor with cdbid '{$actorId}' could not be found via Entry API."
                );
            }

            throw $e;
        }

        // @todo verify response Content-Type

        $cdbXml = $response->getBody(true);
        return $this->extractActorElement($cdbXml, $actorId);
    }

    /**
     * @param string $cdbXml
     * @param string $actorId
     * @return string
     * @throws \RuntimeException
     */
    private function extractActorElement($cdbXml, $actorId)
    {
        $reader = new \XMLReader();
        $reader->xml($cdbXml);

        while ($reader->read()) {
            switch ($reader->nodeType) {
                case ($reader::ELEMENT):
                    if ($reader->localName == "actor" &&
                        $reader->getAttribute('cdbid') == $actorId
                    ) {
                        $node = $reader->expand();
                        $dom = new \DomDocument('1.0');
                        $n = $dom->importNode($node, true);
                        $dom->appendChild($n);
                        return $dom->saveXML();
                    }
            }
        }

        throw new \RuntimeException(
            "Actor with cdbid '{$actorId}' could not be found in the Entry API response body."
        );
    }

    private function guardActorId($actorId)
    {
        if (!is_string($actorId)) {
            throw new \InvalidArgumentException(
                'Expected $actorId to be a string, received value of type ' . gettype($actorId)
            );
        }

        if ('' == trim($actorId)) {
            throw new \InvalidArgumentException('$actorId should not be empty');
        }
    }

    /**
     * @inheritdoc
     */
    public function getCdbXmlNamespaceUri()
    {
        return $this->cdbXmlNamespaceUri;
    }
}
