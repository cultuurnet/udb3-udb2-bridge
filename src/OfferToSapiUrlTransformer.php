<?php

namespace CultuurNet\UDB3\UDB2;

use ValueObjects\Web\Url;

class OfferToSapiUrlTransformer implements UrlTransformerInterface
{
    /**
     * @var string
     *  The UDB2 offer type: event or actor.
     */
    private $offerType;

    /**
     * OfferToSapiUrlTransformer constructor.
     * @param string $offerType
     */
    public function __construct($offerType)
    {
        $this->offerType = $offerType;
    }

    public function transform(Url $url)
    {
        $lastSlashPosition = strrpos($url, '/') + 1;
        $cdbid = substr($url, $lastSlashPosition, strlen($url) - $lastSlashPosition);

        return  Url::fromNative(
            'http://search-prod.lodgon.com/search/rest/detail/' . $this->offerType . '/' . $cdbid . '?noauth=true&version=3.3'
        );
    }
}
