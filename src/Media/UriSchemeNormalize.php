<?php

namespace CultuurNet\UDB3\UDB2\Media;

use League\Uri\Interfaces\Uri;
use League\Uri\Modifiers\AbstractUriModifier;
use Psr\Http\Message\UriInterface;

class UriSchemeNormalize extends AbstractUriModifier
{
    /**
     * Return a Uri object modified according to the modifier
     *
     * @param Uri|UriInterface $uri
     *
     * @return Uri|UriInterface
     */
    public function __invoke($uri)
    {
        return $uri->withScheme('http');
    }
}
