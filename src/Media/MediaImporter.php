<?php

namespace CultuurNet\UDB3\UDB2\Media;

use Broadway\EventHandling\EventListenerInterface;
use CultureFeed_Cdb_Data_File;
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
use League\Uri\Modifiers\AbstractUriModifier;
use League\Uri\Modifiers\Normalize;
use League\Uri\Modifiers\Pipeline;
use League\Uri\Schemes\Http;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Rhumsaa\Uuid\Uuid as BaseUuid;
use ValueObjects\Identity\UUID;
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
     * @var AbstractUriModifier
     */
    private $uriNormalizer;

    /**
     * @param MediaManagerInterface $mediaManager
     */
    public function __construct(
        MediaManagerInterface $mediaManager
    ) {
        $this->mediaManager = $mediaManager;
        $this->uriNormalizer = new Pipeline([
            new Normalize(),
            new UriSchemeNormalize(),
        ]);
        $this->logger = new NullLogger();
    }

    public function getNormalizer()
    {
        return $this->uriNormalizer;
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
            $originalUri = Http::createFromString($picture->getHLink());
            $normalizedUri = $this->uriNormalizer->__invoke($originalUri);
            $namespace = BaseUuid::uuid5('00000000-0000-0000-0000-000000000000', $normalizedUri->getHost());
            $description = $picture->getDescription();

            $this->mediaManager->create(
                UUID::fromNative((string) BaseUuid::uuid5($namespace, (string) $normalizedUri)),
                MIMEType::fromSubtype($picture->getFileType()),
                new String($description ? $description : 'no description'),
                new String($picture->getCopyright()),
                Url::fromNative($normalizedUri)
            );
        }
    }
}
