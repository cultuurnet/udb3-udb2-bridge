<?php
/**
 * @file
 */

namespace CultuurNet\UDB3\UDB2\Event;

use CultuurNet\UDB3\Cdb\Event\SpecificationInterface;
use CultuurNet\UDB3\Cdb\EventItemFactory;
use CultuurNet\UDB3\Event\Events\EventCdbXMLInterface;
use CultuurNet\UDB3\UDB2\EventCdbXmlServiceInterface;
use CultuurNet\UDB3\UDB2\EventNotFoundException;

class SpecificationDecoratedEventCdbXml implements EventCdbXmlServiceInterface
{
    /**
     * @var EventCdbXmlServiceInterface
     */
    private $wrapped;

    /**
     * @var SpecificationInterface
     */
    private $specification;

    public function __construct(
        EventCdbXmlServiceInterface $wrapped,
        SpecificationInterface $specification)
    {
        $this->wrapped = $wrapped;
        $this->specification = $specification;
    }

    /**
     * @inheritdoc
     */
    public function getCdbXmlOfEvent($eventId)
    {
        $eventCdbXml = $this->wrapped->getCdbXmlOfEvent($eventId);

        $udb2Event = EventItemFactory::createEventFromCdbXml(
            $this->wrapped->getCdbXmlNamespaceUri(),
            $eventCdbXml
        );

        $this->guardSpecification($udb2Event);
    }

    private function guardSpecification(\CultureFeed_Cdb_Item_Event $event)
    {
        if ($this->specification->isSatisfiedByEvent($event)) {
            return;
        }

        throw new EventNotFoundException(
            'CDBXML was found, but it does not qualify as an event.'
        );
    }

    /**
     * @return string
     */
    public function getCdbXmlNamespaceUri()
    {
        return $this->wrapped->getCdbXmlNamespaceUri();
    }
}
