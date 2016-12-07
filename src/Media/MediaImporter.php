<?php

namespace CultuurNet\UDB3\UDB2\Media;

use Broadway\EventHandling\EventListenerInterface;
use CultureFeed_Cdb_Data_File;
use CultureFeed_Cdb_Data_Media;
use CultureFeed_Cdb_Item_Base;
use CultuurNet\UDB3\Actor\ActorImportedFromUDB2;
use CultuurNet\UDB3\Cdb\ActorItemFactory;
use CultuurNet\UDB3\Cdb\CdbXmlContainerInterface;
use CultuurNet\UDB3\Cdb\EventItemFactory;
use CultuurNet\UDB3\Event\Events\EventImportedFromUDB2;
use CultuurNet\UDB3\Event\Events\EventUpdatedFromUDB2;
use CultuurNet\UDB3\EventHandling\DelegateEventHandlingToSpecificMethodTrait;
use CultuurNet\UDB3\Media\Image;
use CultuurNet\UDB3\Media\ImageCollection;
use CultuurNet\UDB3\Media\MediaManagerInterface;
use CultuurNet\UDB3\Media\Properties\MIMEType;
use CultuurNet\UDB3\Organizer\Events\OrganizerImportedFromUDB2;
use CultuurNet\UDB3\Organizer\Events\OrganizerUpdatedFromUDB2;
use CultuurNet\UDB3\Place\Events\PlaceImportedFromUDB2;
use CultuurNet\UDB3\Place\Events\PlaceUpdatedFromUDB2;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use ValueObjects\String\String;
use ValueObjects\Web\Url;

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
     * @param CdbXmlContainerInterface $cdbxml
     * @return ImageCollection
     */
    public function importImages(CdbXmlContainerInterface $cdbxml)
    {
        $udb2Event = EventItemFactory::createEventFromCdbXml(
            $cdbxml->getCdbXmlNamespaceUri(),
            $cdbxml->getCdbXml()
        );

        $imageCollection = $this
            ->imageCollectionFactory
            ->fromUdb2Media($this->getMedia($udb2Event));

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
}
