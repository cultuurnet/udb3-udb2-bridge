<?php
/**
 * @file
 */

namespace CultuurNet\UDB3\UDB2\Actor\Events;

use CultuurNet\UDB2DomainEvents\ActorUpdated;
use CultuurNet\UDB3\Cdb\CdbXmlContainerInterface;
use CultuurNet\UDB3\HasCdbXmlTrait;
use ValueObjects\String\String;

class ActorUpdatedEnrichedWithCdbXml extends ActorUpdated implements CdbXmlContainerInterface
{
    use HasCdbXmlTrait;

    public function __construct(
        String $actorId,
        \DateTimeImmutable $time,
        String $author,
        String $cdbXml,
        String $cdbXmlNamespaceUri
    ) {
        parent::__construct(
            $actorId,
            $time,
            $author
        );

        $this->setCdbXml((string)$cdbXml);
        $this->setCdbXmlNamespaceUri((string)$cdbXmlNamespaceUri);
    }

    public static function fromActorUpdated(
        ActorUpdated $actorUpdated,
        String $cdbXml,
        String $cdbXmlNamespaceUri
    ) {
        return new self(
            $actorUpdated->getActorId(),
            $actorUpdated->getTime(),
            $actorUpdated->getAuthor(),
            $cdbXml,
            $cdbXmlNamespaceUri
        );
    }
}