<?php

namespace CultuurNet\UDB3\UDB2\Place;

use CultuurNet\UDB3\Place\Place;
use CultuurNet\UDB3\UDB2\Actor\ActorFactoryInterface;

/**
 * Creates UDB3 place entities based on UDB2 event cdb xml.
 */
class PlaceFromActorFactory implements ActorFactoryInterface
{
    /**
     * @inheritdoc
     */
    public function createFromCdbXml($id, $cdbXml, $cdbXmlNamespaceUri)
    {
        return Place::importFromUDB2Actor(
            (string) $id,
            (string) $cdbXml,
            (string) $cdbXmlNamespaceUri
        );
    }
}
