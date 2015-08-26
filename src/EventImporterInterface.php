<?php
/**
 * @file
 */

namespace CultuurNet\UDB3\UDB2;

use CultuurNet\UDB3\Event\Event;

/**
 * Imports cultural events from UDB2 into UDB3.
 */
interface EventImporterInterface
{
    /**
     * @param string $eventId
     * @return Event
     * @throws EventNotFoundException
     *   If the event can not be found in UDB2.
     */
    public function updateEventFromUDB2($eventId);

    /**
     * @param string $eventId
     * @return Event
     * @throws EventNotFoundException
     *   If the event can not be found in UDB2.
     */
    public function createEventFromUDB2($eventId);
}
