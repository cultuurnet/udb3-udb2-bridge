<?php
/**
 * @file
 */

namespace CultuurNet\UDB3\UDB2\Organizer;

use Broadway\Repository\RepositoryInterface;
use CultuurNet\UDB3\Cdb\ActorItemFactory;
use CultuurNet\UDB3\Organizer\Organizer;
use CultuurNet\UDB3\UDB2\ActorCdbXmlServiceInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

/**
 * Imports organizers from UDB2 into UDB3 based on cdbxml.
 *
 * @deprecated
 *   Used in CultuurNet\UDB3\UDB2\Organizer\OrganizerRepository to import an
 *   organizer from UDB2 if it's not found in the local repository. In the
 *   future Organizers will be only imported from the UDB2 domain messages
 *   on an AMQP exchange when the repository decorator is dropped.
 */
class OrganizerCdbXmlImporter implements OrganizerImporterInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @var ActorCdbXmlServiceInterface
     */
    protected $cdbXmlService;

    /**
     * @var RepositoryInterface
     */
    protected $repository;

    /**
     * @param ActorCdbXmlServiceInterface $cdbXmlService
     * @param RepositoryInterface $repository
     */
    public function __construct(
        ActorCdbXmlServiceInterface $cdbXmlService,
        RepositoryInterface $repository
    ) {
        $this->cdbXmlService = $cdbXmlService;
        $this->repository = $repository;
    }

    /**
     * @inheritdoc
     */
    public function updateOrganizerFromUDB2($organizerId)
    {

    }

    /**
     * @inheritdoc
     */
    public function createOrganizerFromUDB2($organizerId)
    {
        try {
            $organizerXml = $this->cdbXmlService->getCdbXmlOfActor($organizerId);

            $cfActor = ActorItemFactory::createActorFromCdbXml(
                $this->cdbXmlService->getCdbXmlNamespaceUri(),
                $organizerXml
            );

            if (!empty($cfActor->getExternalUrl())) {
                // Do not import an actor that has an external url, which would
                // mean that it already exists on another udb3 system.
                return null;
            }

            $organizer = Organizer::importFromUDB2(
                $organizerId,
                $organizerXml,
                $this->cdbXmlService->getCdbXmlNamespaceUri()
            );

            $this->repository->save($organizer);

            return $organizer;
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->notice(
                    "Organizer creation in UDB3 failed with an exception",
                    [
                        'exception' => $e,
                        'organizerId' => $organizerId
                    ]
                );
            }
        }
    }
}
