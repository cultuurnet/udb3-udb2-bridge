<?php

namespace CultuurNet\UDB3\UDB2\Media;

use CultureFeed_Cdb_Data_Media;
use CultureFeed_Cdb_Item_Base;
use CultuurNet\UDB3\Language;
use CultuurNet\UDB3\Media\ImageCollection;
use CultuurNet\UDB3\Media\Properties\CopyrightHolder;
use CultuurNet\UDB3\Media\Properties\Description;

interface ImageCollectionFactoryInterface
{
    /**
     * @param CultureFeed_Cdb_Data_Media $media
     * @param Description $fallbackDescription,
     * @param CopyrightHolder $fallbackCopyright
     * @return ImageCollection
     */
    public function fromUdb2Media(
        CultureFeed_Cdb_Data_Media $media,
        Description $fallbackDescription,
        CopyrightHolder $fallbackCopyright,
        Language $language
    );

    /**
     * @param CultureFeed_Cdb_Item_Base $item
     * @return ImageCollection
     */
    public function fromUdb2Item(CultureFeed_Cdb_Item_Base $item);
}
