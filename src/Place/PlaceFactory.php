<?php
/**
 * @file
 */

namespace CultuurNet\UDB3\UDB2\Place;

use CultuurNet\UDB3\Place\Place;
use CultuurNet\UDB3\UDB2\Offer\OfferFactoryInterface;
use ValueObjects\String\String as StringLiteral;

/**
 * Creates UDB3 place entities based on UDB2 event cdb xml.
 */
class PlaceFactory implements OfferFactoryInterface
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
