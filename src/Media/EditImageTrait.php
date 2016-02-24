<?php

namespace CultuurNet\UDB3\UDB2\Media;

use Broadway\Domain\DomainMessage;
use CultureFeed_Cdb_Data_EventDetail;
use CultureFeed_Cdb_Data_Media;
use CultureFeed_Cdb_Item_Base;
use CultureFeed_Cdb_Data_File;
use CultuurNet\UDB3\Media\Image;
use CultuurNet\UDB3\Offer\Events\Image\AbstractImageAdded;
use CultuurNet\UDB3\Offer\Events\Image\AbstractImageRemoved;
use CultuurNet\UDB3\Offer\Events\Image\AbstractImageUpdated;
use ValueObjects\Identity\UUID;
use ValueObjects\String\String as StringLiteral;

trait EditImageTrait
{
    /**
     * Delete a given index on the cdb item.
     *
     * @param CultureFeed_Cdb_Item_Base $cdbItem
     * @param Image $image
     */
    protected function removeImageFromCdbItem(
        CultureFeed_Cdb_Item_Base $cdbItem,
        Image $image
    ) {
        $media = $this->getCdbItemMedia($cdbItem);

        foreach ($media as $key => $file) {
            if ($this->fileMatchesMediaObject($file, $image->getMediaObjectId())) {
                $media->remove($key);
            }
        }
    }

    /**
     * Update an existing image on the cdb item.
     *
     * @param \CultureFeed_Cdb_Item_Base $cdbItem
     * @param UUID $mediaObjectId
     * @param StringLiteral $description
     * @param StringLiteral $copyrightHolder
     */
    protected function updateImageOnCdbItem(
        CultureFeed_Cdb_Item_Base $cdbItem,
        UUID $mediaObjectId,
        StringLiteral $description,
        StringLiteral $copyrightHolder
    ) {
        $media = $this->getCdbItemMedia($cdbItem);

        foreach ($media as $file) {
            if ($this->fileMatchesMediaObject($file, $mediaObjectId)) {
                $file->setTitle((string) $description);
                $file->setCopyright((string) $copyrightHolder);
            }
        }
    }


    /**
     * Add an image to the cdb item.
     *
     * @param CultureFeed_Cdb_Item_Base $cdbItem
     * @param Image $image
     */
    protected function addImageToCdbItem(
        CultureFeed_Cdb_Item_Base $cdbItem,
        Image $image
    ) {
        $sourceUri = (string) $image->getSourceLocation();
        $uriParts = explode('/', $sourceUri);
        $media = $this->getCdbItemMedia($cdbItem);

        $file = new CultureFeed_Cdb_Data_File();
        $file->setMain();
        $file->setHLink($sourceUri);

        // If there are no existing images the newly added one should be main.
        $imageTypes = [
            CultureFeed_Cdb_Data_File::MEDIA_TYPE_PHOTO,
            CultureFeed_Cdb_Data_File::MEDIA_TYPE_IMAGEWEB
        ];
        if ($media->byMediaTypes($imageTypes)->count() === 0) {
            $file->setMediaType(CultureFeed_Cdb_Data_File::MEDIA_TYPE_PHOTO);
        } else {
            $file->setMediaType(CultureFeed_Cdb_Data_File::MEDIA_TYPE_IMAGEWEB);
        }

        $filename = end($uriParts);
        $fileparts = explode('.', $filename);
        $extension = strtolower(end($fileparts));
        if ($extension === 'jpg') {
            $extension = 'jpeg';
        }

        $file->setFileType($extension);
        $file->setFileName($filename);

        $file->setCopyright((string) $image->getCopyrightHolder());
        $file->setTitle((string) $image->getDescription());

        $media->add($file);
    }

    /**
     * Get the media for a CDB item.
     *
     * If the items does not have any detials, one will be created.
     *
     * @param \CultureFeed_Cdb_Item_Base $cdbItem
     *
     * @return CultureFeed_Cdb_Data_Media
     */
    protected function getCdbItemMedia(CultureFeed_Cdb_Item_Base $cdbItem)
    {
        $details = $cdbItem->getDetails();
        $details->rewind();

        // Get the first detail.
        $detail = null;
        foreach ($details as $languageDetail) {
            if (!$detail) {
                $detail = $languageDetail;
            }
        }

        // Make sure a detail exists.
        if (empty($detail)) {
            $detail = new CultureFeed_Cdb_Data_EventDetail();
            $details->add($detail);
        }

        $media = $detail->getMedia();
        $media->rewind();
        return $media;
    }

    /**
     * @param CultureFeed_Cdb_Data_File $file
     * @param UUID $mediaObjectId
     * @return bool
     */
    protected function fileMatchesMediaObject(
        CultureFeed_Cdb_Data_File $file,
        UUID $mediaObjectId
    ) {
        // Matching against the CDBID in the name of the image because
        // that's the only reference in UDB2 we have.
        return !!strpos($file->getHLink(), (string) $mediaObjectId);
    }

    /**
     * Apply the imageAdded event to udb2.
     * @param AbstractImageAdded $domainEvent
     * @param DomainMessage $domainMessage
     */
    protected function applyImageAdded(
        AbstractImageAdded $domainEvent,
        DomainMessage $domainMessage
    ) {
        $entryApi = $this->createEntryAPI($domainMessage);
        $udb2Event = $entryApi->getEvent($domainEvent->getItemId());

        $this->addImageToCdbItem($udb2Event, $domainEvent->getImage());
        $entryApi->updateEvent($udb2Event);
    }

    /**
     * Apply the imageUpdated event to udb2.
     * @param AbstractImageUpdated $domainEvent
     * @param DomainMessage $domainMessage
     */
    protected function applyImageUpdated(
        AbstractImageUpdated $domainEvent,
        DomainMessage $domainMessage
    ) {
        $entryApi = $this->createEntryAPI($domainMessage);
        $udb2Event = $entryApi->getEvent($domainEvent->getItemId());

        $this->updateImageOnCdbItem(
            $udb2Event,
            $domainEvent->getMediaObjectId(),
            $domainEvent->getDescription(),
            $domainEvent->getCopyrightHolder()
        );
        $entryApi->updateEvent($udb2Event);
    }

    /**
     * @param AbstractImageRemoved $domainEvent
     * @param DomainMessage $domainMessage
     */
    protected function applyImageRemoved(
        AbstractImageRemoved $domainEvent,
        DomainMessage $domainMessage
    ) {
        $entryApi = $this->createEntryAPI($domainMessage);
        $udb2Event = $entryApi->getEvent($domainEvent->getItemId());

        $this->removeImageFromCdbItem($udb2Event, $domainEvent->getImage());
        $entryApi->updateEvent($udb2Event);
    }
}
