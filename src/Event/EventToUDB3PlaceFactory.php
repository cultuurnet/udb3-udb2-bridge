<?php

namespace CultuurNet\UDB3\UDB2\Event;

use CultuurNet\UDB3\Place\Place;
use ValueObjects\String\String as StringLiteral;

/**
 * Creates UDB3 place entities based on UDB2 event cdb xml.
 */
class EventToUDB3PlaceFactory implements EventToUDB3AggregateFactoryInterface
{
    /**
     * @inheritdoc
     */
    public function createFromCdbXml(
        StringLiteral $id,
        StringLiteral $cdbXml,
        StringLiteral $cdbXmlNamespaceUri
    ) {
        return Place::importFromUDB2Event(
            (string)$id,
            (string)$cdbXml,
            (string)$cdbXmlNamespaceUri
        );
    }
}
