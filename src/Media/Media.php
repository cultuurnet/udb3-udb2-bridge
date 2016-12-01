<?php

namespace CultuurNet\UDB3\UDB2\Media;

use CultureFeed_Cdb_Data_File;
use League\Uri\Modifiers\AbstractUriModifier;
use League\Uri\Modifiers\Normalize;
use League\Uri\Modifiers\Pipeline;
use League\Uri\Schemes\Http;
use Psr\Http\Message\UriInterface;
use ValueObjects\Identity\UUID;
use Rhumsaa\Uuid\Uuid as BaseUuid;

class Media
{
    /**
     * @var AbstractUriModifier
     */
    protected $uriNormalizer;

    /**
     * @var CultureFeed_Cdb_Data_File
     */
    protected $file;

    /**
     * Media constructor.
     * @param CultureFeed_Cdb_Data_File $file
     */
    public function __construct(CultureFeed_Cdb_Data_File $file)
    {
        $this->uriNormalizer = new Pipeline([
            new Normalize(),
            new NormalizeUriScheme(),
        ]);

        $this->file = $file;
    }

    /**
     * @return UUID
     */
    public function identify()
    {
        $normalizedUri = $this->normalizeUri();
        $namespace = BaseUuid::uuid5('00000000-0000-0000-0000-000000000000', $normalizedUri->getHost());
        return UUID::fromNative((string) BaseUuid::uuid5($namespace, (string) $normalizedUri));
    }

    /**
     * @return UriInterface
     */
    public function normalizeUri()
    {
        $originalUri = Http::createFromString($this->file->getHLink());
        return $this->uriNormalizer->__invoke($originalUri);
    }
}
