<?php
/**
 * @file
 */

namespace CultuurNet\UDB3\UDB2\Event;

use Broadway\Domain\AggregateRoot;
use CultuurNet\UDB3\Cdb\UpdateableWithCdbXmlInterface;
use CultuurNet\UDB3\Event\Event;
use CultuurNet\UDB3\UDB2\Offer\OfferFactoryInterface;
use ValueObjects\String\String as StringLiteral;

class EventFactory implements OfferFactoryInterface
{
    public function createFromCdbXml(
        StringLiteral $id,
        StringLiteral $cdbXml,
        StringLiteral $cdbXmlNamespaceUri
    ) {
        return Event::importFromUDB2(
            (string)$id,
            (string)$cdbXml,
            (string)$cdbXmlNamespaceUri
        );
    }

}
