<?php

namespace CultuurNet\UDB3\UDB2\XML;

class CombinedXmlValidationServiceTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @test
     */
    public function it_calls_validate_on_all_xml_validation_services_of_the_collection()
    {
        $xmlValidationService1 = $this->createMock(XMLValidationServiceInterface::class);
        $xmlValidationService2 = $this->createMock(XMLValidationServiceInterface::class);

        $combinedXmlValidationService = new CombinedXmlValidationService(
            new XMLValidationServiceCollection(
                $xmlValidationService1,
                $xmlValidationService2
            )
        );
        
        $xmlValidationService1->expects($this->once())
            ->method('validate')
            ->with('xml');

        $xmlValidationService2->expects($this->once())
            ->method('validate')
            ->with('xml');

        $combinedXmlValidationService->validate('xml');
    }
}
