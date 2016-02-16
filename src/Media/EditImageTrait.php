<?php

namespace CultuurNet\UDB3\UDB2\Media;

use CultureFeed_Cdb_Item_Base;
use CultureFeed_Cdb_Data_File;
use CultuurNet\UDB3\Media\Image;
use ValueObjects\Identity\UUID;
use ValueObjects\String\String;

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

        $details = $cdbItem->getDetails();
        $details->rewind();

        // No detail = nothing to delete.
        if (!$details->valid()) {
            return;
        }

        $media = $details->current()->getMedia();
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
     * @param \ValueObjects\String\String $description
     * @param \ValueObjects\String\String $copytightHolder
     */
    protected function updateImageOnCdbItem(
        CultureFeed_Cdb_Item_Base $cdbItem,
        UUID $mediaObjectId,
        String $description,
        String $copyrightHolder
    ) {
        $media = $this->getCdbItemMedia($cdbItem);

        foreach ($media as $file) {
            if ($file->getMediatype() === CultureFeed_Cdb_Data_File::MEDIA_TYPE_IMAGEWEB) {
                $file->setMain();

                if ($this->fileMatchesMediaObject($file, $mediaObjectId)) {
                    $file->setTitle((string) $description);
                    $file->setCopyright((string) $copyrightHolder);
                }
            }
        }
    }

    /**
     * Add an image to the cdb item.
     */
    protected function addImageToCdbItem(
        CultureFeed_Cdb_Item_Base $cdbItem,
        Image $image
    ) {
        $sourceUri = (string) $image->getSourceLocation();
        $uriParts = explode('/', $sourceUri);

        $file = new CultureFeed_Cdb_Data_File();
        $file->setMediaType(CultureFeed_Cdb_Data_File::MEDIA_TYPE_IMAGEWEB);
        $file->setMain();
        $file->setHLink($sourceUri);

        $filename = end($uriParts);
        $fileparts = explode('.', $filename);
        $extension = strtolower(end($fileparts));
        if ($extension === 'jpg') {
            $extension = 'jpeg';
        }

        $file->setFileType($extension);
        $file->setFileName($filename);

        $file->setCopyright($image->getCopyrightHolder());
        $file->setTitle($image->getDescription());

        $this->getCdbItemMedia($cdbItem)->add($file);
    }

    /**
     * Get the media for a CDB item.
     *
     * If the items does not have any detials, one will be created.
     *
     * @param \CultureFeed_Cdb_Item_Base $cdbItem
     */
    private function getCdbItemMedia(CultureFeed_Cdb_Item_Base $cdbItem)
    {
        $details = $cdbItem->getDetails();

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

        return $detail->getMedia();
    }

    /**
     * @param CultureFeed_Cdb_Data_File $file
     * @param UUID $mediaObjectId
     * @return bool
     */
    private function fileMatchesMediaObject(
        CultureFeed_Cdb_Data_File $file,
        UUID $mediaObjectId
    ) {
        // Matching against the CDBID in the name of the image because
        // that's the only reference in UDB2 we have.
        return !!strpos($file->getHLink(), (string) $mediaObjectId);
    }
}
