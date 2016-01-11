<?php
/**
 * @file
 */

namespace CultuurNet\UDB3\UDB2\Place;

use Broadway\Repository\RepositoryInterface;
use CultuurNet\UDB3\Place\Place;
use CultuurNet\UDB3\UDB2\ActorCdbXmlServiceInterface;
use CultuurNet\UDB3\UDB2\EventCdbXmlServiceInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

/**
 * Imports places from UDB2 into UDB3 based on cdbxml.
 */
class PlaceCdbXmlImporter implements PlaceImporterInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @var ActorCdbXmlServiceInterface
     */
    protected $cdbXmlService;

    /**
     * @var EventCdbXmlServiceInterface
     */
    protected $eventCdbXmlService;

    /**
     * @var RepositoryInterface
     */
    protected $repository;

    /**
     * @param ActorCdbXmlServiceInterface $cdbXmlService
     * @param RepositoryInterface         $repository
     * @param EventCdbXmlServiceInterface $eventCdbXmlService
     */
    public function __construct(
        ActorCdbXmlServiceInterface $cdbXmlService,
        RepositoryInterface $repository,
        EventCdbXmlServiceInterface $eventCdbXmlService
    ) {
        $this->cdbXmlService = $cdbXmlService;
        $this->repository = $repository;
        $this->eventCdbXmlService = $eventCdbXmlService;
    }

    /**
     * @param string $placeId
     * @return Place|null
     */
    public function updatePlaceFromUDB2($placeId)
    {

    }

    private function createPlaceFromActor($placeId)
    {
        $placeXml = $this->cdbXmlService->getCdbXmlOfActor($placeId);

        $place = Place::importFromUDB2Actor(
            $placeId,
            $placeXml,
            $this->cdbXmlService->getCdbXmlNamespaceUri()
        );

        return $place;
    }

    private function createPlaceFromEvent($placeId)
    {
        $placeXml = $this->eventCdbXmlService->getCdbXmlOfEvent($placeId);

        $place = Place::importFromUDB2Event(
            $placeId,
            $placeXml,
            $this->eventCdbXmlService->getCdbXmlNamespaceUri()
        );

        return $place;
    }

    /**
     * @param string $placeId
     * @return Place|null
     */
    public function createPlaceFromUDB2($placeId)
    {
        $sources = [
            'Actor' => array($this, 'createPlaceFromActor'),
            'Event' => array($this, 'createPlaceFromEvent')
        ];

        $place = null;
        foreach ($sources as $type => $source) {
            try {
                $place = call_user_func($source, $placeId);

                if ($place) {
                    break;
                }
            } catch (\Exception $e) {
                if ($this->logger) {
                    $this->logger->notice(
                        "Place creation in UDB3 failed with an exception",
                        [
                            'exception' => $e,
                            'placeId' => $placeId
                        ]
                    );
                }
            }
        }

        try {
            if ($place) {
                $this->repository->save($place);
            }
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->notice(
                    "Place creation in UDB3 failed with an exception",
                    [
                        'exception' => $e,
                        'placeId' => $placeId
                    ]
                );
            }
        }

        return $place;
    }
}
