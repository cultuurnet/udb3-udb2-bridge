<?php

namespace CultuurNet\UDB3\UDB2\Media;

use CultuurNet\UDB3\Media\ImageCollection;

interface ImageCollectionFactoryInterface
{
    /**
     * @param \CultureFeed_Cdb_Data_Media $media
     * @return ImageCollection
     */
    public function fromUdb2Media(\CultureFeed_Cdb_Data_Media $media);
}
