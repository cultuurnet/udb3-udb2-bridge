<?php

namespace CultuurNet\UDB3\UDB2\XML;

class XMLValidationServiceCollectionTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @test
     */
    public function it_stores_an_array_of_xml_validation_services()
    {
        $xmlValidationService1 = $this->createMock(XMLValidationServiceInterface::class);
        $xmlValidationService2 = $this->createMock(XMLValidationServiceInterface::class);

        $xmlValidationServiceCollection = new XMLValidationServiceCollection(
            $xmlValidationService1,
            $xmlValidationService2
        );

        $this->assertEquals(
            [
                $xmlValidationService1,
                $xmlValidationService2
            ],
            $xmlValidationServiceCollection->getXmlValidationServices()
        );
    }

    /**
     * @test
     */
    public function it_requires_xml_validation_services_interfaces()
    {
        $xmlValidationService = $this->createMock(XMLValidationServiceInterface::class);
        $somethingElse = $this->createMock(\Exception::class);

        if (interface_exists(\Throwable::class)) {
            $this->expectException(\Throwable::class);
        } else {
            $this->expectException(\PHPUnit_Framework_Error::class);
        }

        new XMLValidationServiceCollection(
            $xmlValidationService,
            $somethingElse
        );
    }
}
