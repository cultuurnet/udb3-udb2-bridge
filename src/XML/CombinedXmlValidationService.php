<?php

namespace CultuurNet\UDB3\UDB2\XML;

class CombinedXmlValidationService implements XMLValidationServiceInterface
{
    /**
     * @var XMLValidationServiceCollection
     */
    private $xmlValidationServiceCollection;

    /**
     * CombinedXmlValidationService constructor.
     * @param XMLValidationServiceCollection $xmlValidationServiceCollection
     */
    public function __construct(XMLValidationServiceCollection $xmlValidationServiceCollection)
    {
        $this->xmlValidationServiceCollection = $xmlValidationServiceCollection;
    }

    /**
     * @inheritdoc
     */
    public function validate($xml)
    {
        foreach ($this->xmlValidationServiceCollection->getXmlValidationServices() as $xmlValidationService) {
            $xmlValidationService->validate($xml);
        }
    }
}
