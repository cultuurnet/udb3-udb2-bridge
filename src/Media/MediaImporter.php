<?php

namespace CultuurNet\UDB3\UDB2\Media;

use Broadway\EventHandling\EventListenerInterface;
use CultureFeed_Cdb_Data_File;
use CultuurNet\UDB3\Actor\ActorImportedFromUDB2;
use CultuurNet\UDB3\Cdb\ActorItemFactory;
use CultuurNet\UDB3\Cdb\EventItemFactory;
use CultuurNet\UDB3\Event\Events\EventImportedFromUDB2;
use CultuurNet\UDB3\Event\Events\EventUpdatedFromUDB2;
use CultuurNet\UDB3\EventHandling\DelegateEventHandlingToSpecificMethodTrait;
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

class MediaImporter implements EventListenerInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;
    use DelegateEventHandlingToSpecificMethodTrait;

    /**
     * @var MediaManagerInterface
     */
    private $mediaManager;

    /**
     * @param MediaManagerInterface $mediaManager
     */
    public function __construct(
        MediaManagerInterface $mediaManager
    ) {
        $this->mediaManager = $mediaManager;
        $this->logger = new NullLogger();
    }

    /**
     * @param EventImportedFromUDB2 $eventImportedFromUDB2
     */
    public function applyEventImportedFromUDB2(
        EventImportedFromUDB2 $eventImportedFromUDB2
    ) {
        $event = EventItemFactory::createEventFromCdbXml(
            $eventImportedFromUDB2->getCdbXmlNamespaceUri(),
            $eventImportedFromUDB2->getCdbXml()
        );

        $this->createMediaObjectsFromCdbItem($event);
    }

    /**
     * @param EventUpdatedFromUDB2 $eventUpdatedFromUDB2
     */
    public function applyEventUpdatedFromUDB2(
        EventUpdatedFromUDB2 $eventUpdatedFromUDB2
    ) {
        $event = EventItemFactory::createEventFromCdbXml(
            $eventUpdatedFromUDB2->getCdbXmlNamespaceUri(),
            $eventUpdatedFromUDB2->getCdbXml()
        );

        $this->createMediaObjectsFromCdbItem($event);
    }

    /**
     * @param PlaceImportedFromUDB2 $placeImportedFromUDB2
     */
    public function applyPlaceImportedFromUDB2(
        PlaceImportedFromUDB2 $placeImportedFromUDB2
    ) {
        $this->createMediaObjectsFromActorImportEvent($placeImportedFromUDB2);
    }

    /**
     * @param PlaceUpdatedFromUDB2 $placeUpdatedFromUDB2
     */
    public function applyPlaceUpdatedFromUDB2(
        PlaceUpdatedFromUDB2 $placeUpdatedFromUDB2
    ) {
        $this->createMediaObjectsFromActorImportEvent($placeUpdatedFromUDB2);
    }

    /**
     * @param OrganizerImportedFromUDB2 $organizerImportedFromUDB2
     */
    public function applyOrganizerImportedFromUDB2(
        OrganizerImportedFromUDB2 $organizerImportedFromUDB2
    ) {
        $this->createMediaObjectsFromActorImportEvent($organizerImportedFromUDB2);
    }

    /**
     * @param OrganizerUpdatedFromUDB2 $organizerUpdatedFromUDB2
     */
    public function applyOrganizerUpdatedFromUDB2(
        OrganizerUpdatedFromUDB2 $organizerUpdatedFromUDB2
    ) {
        $this->createMediaObjectsFromActorImportEvent($organizerUpdatedFromUDB2);
    }

    /**
     * @param ActorImportedFromUDB2 $importEvent
     */
    private function createMediaObjectsFromActorImportEvent(ActorImportedFromUDB2 $importEvent)
    {
        $actor = ActorItemFactory::createActorFromCdbXml(
            $importEvent->getCdbXmlNamespaceUri(),
            $importEvent->getCdbXml()
        );

        $this->createMediaObjectsFromCdbItem($actor);
    }

    /**
     * @param \CultureFeed_Cdb_Item_Base $cdbItem
     */
    private function createMediaObjectsFromCdbItem(\CultureFeed_Cdb_Item_Base $cdbItem)
    {
        /* @var \CultureFeed_Cdb_Data_Detail $someDetail */
        $someDetail = $cdbItem->getDetails()->current();
        $media = $someDetail->getMedia();

        /**
         * @var CultureFeed_Cdb_Data_File[] $pictures
         */
        $pictures = $media->byMediaTypes(
            [
                CultureFeed_Cdb_Data_File::MEDIA_TYPE_PHOTO,
                CultureFeed_Cdb_Data_File::MEDIA_TYPE_IMAGEWEB
            ]
        );

        foreach ($pictures as $picture) {
            $udb2Media = new Media($picture);
            $description = $picture->getDescription();

            $this->mediaManager->create(
                $udb2Media->identify(),
                MIMEType::fromSubtype($picture->getFileType()),
                new String($description ? $description : 'no description'),
                new String($picture->getCopyright()),
                Url::fromNative($udb2Media->normalizeUri())
            );
        }
    }
}
