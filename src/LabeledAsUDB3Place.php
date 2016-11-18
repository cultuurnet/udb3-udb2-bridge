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

        $labels = $this->labels($keywords);

        $UDB3PlaceLabel = new Label('UDB3 place');

        foreach ($labels as $label) {
            if ($label->equals($UDB3PlaceLabel)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param \CultureFeed_Cdb_Data_Keyword[] $keywords
     * @return Label[] $labels
     */
    private function labels($keywords)
    {
        $labels = [];

        foreach ($keywords as $keyword) {
            try {
                $label = new Label($keyword->getValue());
                $labels[] = $label;
            } catch (\InvalidArgumentException $e) {
                continue;
            }
        }

        return $labels;
    }
}
