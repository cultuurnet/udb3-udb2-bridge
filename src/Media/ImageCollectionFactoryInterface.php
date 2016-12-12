<?php

namespace CultuurNet\UDB3\UDB2\Media;

use CultureFeed_Cdb_Data_Media;
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
        CopyrightHolder $fallbackCopyright
    );
}
