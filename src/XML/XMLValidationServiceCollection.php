<?php

namespace CultuurNet\UDB3\UDB2\XML;

class XMLValidationServiceCollection
{
    /**
     * @var XMLValidationServiceInterface[]
     */
    private $xmlValidationServices;

    /**
     * XMLValidationServiceCollection constructor.
     * @param XMLValidationServiceInterface[] $xmlValidationServices
     */
    public function __construct(XMLValidationServiceInterface... $xmlValidationServices)
    {
        $this->xmlValidationServices = $xmlValidationServices;
    }

    /**
     * @return XMLValidationServiceInterface[]
     */
    public function getXmlValidationServices()
    {
        return $this->xmlValidationServices;
    }
}
