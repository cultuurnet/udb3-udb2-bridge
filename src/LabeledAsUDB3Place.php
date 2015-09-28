<?php
/**
 * @file
 */

namespace CultuurNet\UDB3\UDB2;

use CultureFeed_Cdb_Item_Event;
use CultuurNet\UDB3\Cdb\Event\SpecificationInterface;
use CultuurNet\UDB3\Label;

class LabeledAsUDB3Place implements SpecificationInterface
{
    /**
     * @param CultureFeed_Cdb_Item_Event $event
     * @return bool
     */
    public function isSatisfiedByEvent(CultureFeed_Cdb_Item_Event $event)
    {
        $keywords =  $event->getKeywords(true);

        $UDB3PlaceLabel = new Label('UDB3 place');

        /** @var \CultureFeed_Cdb_Data_Keyword $keyword */
        foreach ($keywords as $keyword) {
            $label = new Label($keyword->getValue());

            if ($label->equals($UDB3PlaceLabel)) {
                return true;
            }
        }

        return false;
    }
}
