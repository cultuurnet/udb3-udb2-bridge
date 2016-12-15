<?php

namespace CultuurNet\UDB3\UDB2\Media;

use CultureFeed_Cdb_Data_Media;
use CultureFeed_Cdb_Item_Base;
use CultuurNet\UDB3\Media\Image;
use CultuurNet\UDB3\Media\ImageCollection;
use CultuurNet\UDB3\Media\MediaManagerInterface;
use CultuurNet\UDB3\Media\Properties\CopyrightHolder;
use CultuurNet\UDB3\Media\Properties\Description;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use ValueObjects\String\String as StringLiteral;

class MediaImporter implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @var ImageCollectionFactoryInterface
     */
    private $imageCollectionFactory;

    /**
     * @var MediaManagerInterface
     */
    private $mediaManager;

    /**
     * @param MediaManagerInterface $mediaManager
     */
    public function __construct(
        MediaManagerInterface $mediaManager,
        ImageCollectionFactoryInterface $imageCollectionFactory
    ) {
        $this->mediaManager = $mediaManager;
        $this->imageCollectionFactory = $imageCollectionFactory;
        $this->logger = new NullLogger();
    }

    /**
     * @param CultureFeed_Cdb_Item_Base $cdbItem
     * @return ImageCollection
     */
    public function importImages(CultureFeed_Cdb_Item_Base $cdbItem)
    {
        $title = $this->getTitle($cdbItem);

        $imageCollection = $this
            ->imageCollectionFactory
            ->fromUdb2Media(
                $this->getMedia($cdbItem),
                new Description($title),
                new CopyrightHolder($title)
            );

        $imageArray = $imageCollection->toArray();
        array_walk($imageArray, [$this, 'importImage']);

        return $imageCollection;
    }

    /**
     * @param Image $image
     */
    private function importImage(Image $image)
    {
        $this->mediaManager->create(
            $image->getMediaObjectId(),
            $image->getMimeType(),
            $image->getDescription(),
            $image->getCopyrightHolder(),
            $image->getSourceLocation()
        );
    }

    /**
     * @param CultureFeed_Cdb_Item_Base $cdbItem
     * @return CultureFeed_Cdb_Data_Media
     */
    protected function getMedia(CultureFeed_Cdb_Item_Base $cdbItem)
    {
        $details = $cdbItem->getDetails();
        $details->rewind();

        return $details
            ->current()
            ->getMedia();
    }

    /**
     * @param CultureFeed_Cdb_Item_Base $cdbItem
     * @return string
     */
    protected function getTitle(CultureFeed_Cdb_Item_Base $cdbItem)
    {
        $details = $cdbItem->getDetails();
        $details->rewind();

        return $details->current()->getTitle();
    }
}
