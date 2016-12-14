<?php

namespace CultuurNet\UDB3\UDB2\Media;

use CultureFeed_Cdb_Data_File;
use CultuurNet\UDB3\Media\Image;
use CultuurNet\UDB3\Media\ImageCollection;
use CultuurNet\UDB3\Media\Properties\CopyrightHolder;
use CultuurNet\UDB3\Media\Properties\Description;
use CultuurNet\UDB3\Media\Properties\MIMEType;
use League\Uri\Modifiers\AbstractUriModifier;
use League\Uri\Modifiers\Normalize;
use League\Uri\Schemes\Http;
use Psr\Http\Message\UriInterface;
use ValueObjects\Identity\UUID;
use Rhumsaa\Uuid\Uuid as BaseUuid;
use ValueObjects\Web\Url;

class ImageCollectionFactory implements ImageCollectionFactoryInterface
{
    const SUPPORTED_UDB2_MEDIA_TYPES = [
        CultureFeed_Cdb_Data_File::MEDIA_TYPE_PHOTO,
        CultureFeed_Cdb_Data_File::MEDIA_TYPE_IMAGEWEB
    ];

    /**
     * @var AbstractUriModifier
     */
    protected $uriNormalizer;

    /**
     * @var string|null
     */
    protected $uuidRegex;

    public function __construct()
    {
        $this->uriNormalizer = new Normalize();
    }

    public function withUuidRegex($mediaIdentifierRegex)
    {
        $c = clone $this;
        $c->uuidRegex = $mediaIdentifierRegex;

        return $c;
    }

    /**
     * @inheritdoc
     */
    public function fromUdb2Media(
        \CultureFeed_Cdb_Data_Media $media,
        Description $fallbackDescription,
        CopyrightHolder $fallbackCopyright
    ) {
        $udb2ImageFiles = $media->byMediaTypes(self::SUPPORTED_UDB2_MEDIA_TYPES);

        return array_reduce(
            iterator_to_array($udb2ImageFiles),
            function (
                ImageCollection $images,
                CultureFeed_Cdb_Data_File $file
            ) use (
                $fallbackDescription,
                $fallbackCopyright
            ) {
                $udb2Description = $file->getDescription();
                $udb2Copyright = $file->getCopyright();
                $normalizedUri = $this->normalize($file->getHLink());
                $image = new Image(
                    $this->identify($normalizedUri),
                    MIMEType::fromSubtype($file->getFileType()),
                    empty($udb2Description) ? $fallbackDescription : new Description($udb2Description),
                    empty($udb2Copyright) ? $fallbackCopyright : new CopyrightHolder($udb2Copyright),
                    Url::fromNative((string) $normalizedUri)
                );

                return !$images->getMain() && $file->isMain()
                    ? $images->withMain($image)
                    : $images->with($image);

            },
            new ImageCollection()
        );
    }

    /**
     * @param UriInterface $httpUri
     * @return UUID
     */
    private function identify(Http $httpUri)
    {
        if (isset($this->uuidRegex) && \preg_match('/'.$this->uuidRegex.'/', (string) $httpUri, $matches)) {
            return UUID::fromNative($matches['uuid']);
        }

        $namespace = BaseUuid::uuid5(BaseUuid::NAMESPACE_DNS, $httpUri->getHost());
        return UUID::fromNative((string) BaseUuid::uuid5($namespace, (string) $httpUri));
    }

    /**
     * @param string $link
     * @return UriInterface
     */
    public function normalize($link)
    {
        $originalUri = Http::createFromString($link)->withScheme('http');
        return $this->uriNormalizer->__invoke($originalUri);
    }
}