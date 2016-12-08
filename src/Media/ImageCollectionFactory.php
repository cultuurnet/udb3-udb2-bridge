<?php

namespace CultuurNet\UDB3\UDB2\Media;

use CultureFeed_Cdb_Data_File;
use CultuurNet\UDB3\Media\Image;
use CultuurNet\UDB3\Media\ImageCollection;
use CultuurNet\UDB3\Media\Properties\MIMEType;
use League\Uri\Modifiers\AbstractUriModifier;
use League\Uri\Modifiers\Normalize;
use League\Uri\Modifiers\Pipeline;
use League\Uri\Schemes\Http;
use Psr\Http\Message\UriInterface;
use ValueObjects\Identity\UUID;
use Rhumsaa\Uuid\Uuid as BaseUuid;
use ValueObjects\String\String as StringLiteral;
use ValueObjects\Web\Url;

class ImageCollectionFactory implements ImageCollectionFactoryInterface
{
    const SUPPORTED_IMAGE_TYPES = [
        CultureFeed_Cdb_Data_File::MEDIA_TYPE_PHOTO,
        CultureFeed_Cdb_Data_File::MEDIA_TYPE_IMAGEWEB
    ];
    const DEFAULT_DESCRIPTION = '¯\_(ツ)_/¯';
    const DEFAULT_COPYRIGHT = '¯\_(ツ)_/¯';

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

        $this->uriNormalizer = new Pipeline([
            new Normalize(),
            new NormalizeUriScheme(),
        ]);
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
    public function fromUdb2Media(\CultureFeed_Cdb_Data_Media $media)
    {
        $udb2ImageFiles = $media->byMediaTypes(self::SUPPORTED_IMAGE_TYPES);

        return array_reduce(
            iterator_to_array($udb2ImageFiles),
            function (ImageCollection $images, CultureFeed_Cdb_Data_File $file) {
                $udb2Description = $file->getDescription();
                $udb2Copyright = $file->getCopyright();
                $normalizedUri = $this->normalize($file->getHLink());
                $image = new Image(
                    $this->identify($normalizedUri),
                    MIMEType::fromSubtype($file->getFileType()),
                    new StringLiteral(empty($udb2Description) ? self::DEFAULT_COPYRIGHT : $udb2Description),
                    new StringLiteral(empty($udb2Copyright) ? self::DEFAULT_DESCRIPTION : $udb2Copyright),
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

        $namespace = BaseUuid::uuid5('00000000-0000-0000-0000-000000000000', $httpUri->getHost());
        return UUID::fromNative((string) BaseUuid::uuid5($namespace, (string) $httpUri));
    }

    /**
     * @param string $link
     * @return UriInterface
     */
    public function normalize($link)
    {
        $originalUri = Http::createFromString($link);
        return $this->uriNormalizer->__invoke($originalUri);
    }
}
