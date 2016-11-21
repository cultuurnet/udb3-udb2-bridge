<?php

namespace CultuurNet\UDB3\UDB2\Actor;

use Broadway\Domain\AggregateRoot;
use Broadway\EventHandling\EventListenerInterface;
use Broadway\Repository\AggregateNotFoundException;
use Broadway\Repository\RepositoryInterface;
use CultuurNet\UDB3\Cdb\ActorItemFactory;
use CultuurNet\UDB3\Cdb\CdbXmlContainerInterface;
use CultuurNet\UDB3\Cdb\UpdateableWithCdbXmlInterface;
use CultuurNet\UDB3\EventHandling\DelegateEventHandlingToSpecificMethodTrait;
use CultuurNet\UDB3\UDB2\Actor\Events\ActorCreatedEnrichedWithCdbXml;
use CultuurNet\UDB3\UDB2\Actor\Events\ActorUpdatedEnrichedWithCdbXml;
use CultuurNet\UDB3\UDB2\Actor\Specification\ActorSpecificationInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use ValueObjects\String\String as StringLiteral;

/**
 * Applies incoming UDB2 actor events enriched with cdb xml on UDB3 organizers.
 *
 * Wether the UDB2 actor event should be processed is defined by an
 * implementation of ActorSpecificationInterface.
 *
 * Instantiation of new entities is delegated to an implementation of
 * ActorToUDB3AggregateFactoryInterface.
 *
 * Entities targeted by the ActorEventApplier need to implement
 * UpdateableWithCdbXmlInterface.
 */
class ActorEventApplier implements EventListenerInterface, LoggerAwareInterface
{
    use DelegateEventHandlingToSpecificMethodTrait;
    use LoggerAwareTrait;

    /**
     * @var RepositoryInterface
     */
    protected $repository;

    /**
     * @var ActorSpecificationInterface
     */
    protected $actorSpecification;

    /**
     * @var ActorToUDB3AggregateFactoryInterface
     */
    protected $actorFactory;

    /**
     * @param RepositoryInterface $repository
     * @param ActorToUDB3AggregateFactoryInterface $actorFactory
     * @param ActorSpecificationInterface $actorSpecification
     */
    public function __construct(
        RepositoryInterface $repository,
        ActorToUDB3AggregateFactoryInterface $actorFactory,
        ActorSpecificationInterface $actorSpecification
    ) {
        $this->repository = $repository;

        $this->actorSpecification = $actorSpecification;

        $this->actorFactory = $actorFactory;
    }

    /**
     * @param ActorCreatedEnrichedWithCdbXml $actorCreated
     */
    private function applyActorCreatedEnrichedWithCdbXml(
        ActorCreatedEnrichedWithCdbXml $actorCreated
    ) {
        if (!$this->isSatisfiedBy($actorCreated)) {
            return;
        }

        $this->createWithUpdateFallback(
            $actorCreated->getActorId(),
            $actorCreated
        );
    }

    /**
     * @param ActorUpdatedEnrichedWithCdbXml $actorUpdated
     */
    private function applyActorUpdatedEnrichedWithCdbXml(
        ActorUpdatedEnrichedWithCdbXml $actorUpdated
    ) {
        if (!$this->isSatisfiedBy($actorUpdated)) {
            return;
        }

        $this->updateWithCreateFallback(
            $actorUpdated->getActorId(),
            $actorUpdated
        );
    }

    /**
     * @param CdbXmlContainerInterface $actorCdbXml
     * @return bool
     */
    private function isSatisfiedBy(CdbXmlContainerInterface $actorCdbXml)
    {
        $actor = ActorItemFactory::createActorFromCdbXml(
            $actorCdbXml->getCdbXmlNamespaceUri(),
            $actorCdbXml->getCdbXml()
        );

        $satisfied = $this->actorSpecification->isSatisfiedBy($actor);

        if (!$satisfied && $this->logger) {
            $this->logger->debug(
                'The specification of which actors need to be processed is ' .
                'not satisfied by UDB2 actor with cdbid: ' . $actor->getCdbId()
            );
        }

        return $satisfied;
    }

    /**
     * @param StringLiteral $entityId
     * @param CdbXmlContainerInterface $cdbXml
     */
    private function updateWithCreateFallback(
        StringLiteral $entityId,
        CdbXmlContainerInterface $cdbXml
    ) {
        try {
            $this->update($entityId, $cdbXml);

            $this->debug(
                'Actor succesfully updated.'
            );
        } catch (AggregateNotFoundException $e) {
            $this->debug(
                'Update failed because entity did not exist yet, trying to create it as a fallback.'
            );

            $this->create($entityId, $cdbXml);

            $this->debug(
                'Actor succesfully created.'
            );
        }
    }

    private function debug($message)
    {
        if ($this->logger) {
            $this->logger->debug($message);
        }
    }

    /**
     * @param StringLiteral $entityId
     * @param CdbXmlContainerInterface $cdbXml
     */
    private function createWithUpdateFallback(
        StringLiteral $entityId,
        CdbXmlContainerInterface $cdbXml
    ) {
        try {
            $this->create($entityId, $cdbXml);

            $this->debug(
                'Actor succesfully created.'
            );
        } catch (\Exception $e) {
            $this->debug(
                'Creation failed, trying to update as a fallback.'
            );

            $this->update($entityId, $cdbXml);

            $this->debug(
                'Actor succesfully updated.'
            );
        }
    }

    /**
     * @param StringLiteral $entityId
     * @param CdbXmlContainerInterface $cdbXml
     */
    private function update(
        StringLiteral $entityId,
        CdbXmlContainerInterface $cdbXml
    ) {
        $entityId = (string) $entityId;

        /** @var UpdateableWithCdbXmlInterface|AggregateRoot $entity */
        $entity = $this->repository->load($entityId);

        $entity->updateWithCdbXml(
            $cdbXml->getCdbXml(),
            $cdbXml->getCdbXmlNamespaceUri()
        );

        $this->repository->save($entity);
    }

    /**
     * @param StringLiteral $id
     * @param CdbXmlContainerInterface $cdbXml
     */
    private function create(
        StringLiteral $id,
        CdbXmlContainerInterface $cdbXml
    ) {
        $entity = $this->actorFactory->createFromCdbXml(
            (string) $id,
            $cdbXml->getCdbXml(),
            $cdbXml->getCdbXmlNamespaceUri()
        );

        $this->repository->save($entity);
    }
}
