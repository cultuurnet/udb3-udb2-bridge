<?php
/**
 * @file
 */

namespace CultuurNet\UDB3\UDB2\Event;

use CultuurNet\UDB3\UDB2\EventCdbXmlServiceInterface;
use CultuurNet\UDB3\UDB2\EventNotFoundException;

class SpecificationDecoratedEventCdbXmlTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \CultuurNet\UDB3\UDB2\Event\SpecificationDecoratedEventCdbXml
     */
    private $specificationDecoratedEventCdbXml;

    /**
     * @var \CultuurNet\UDB3\UDB2\EventCdbXmlServiceInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $wrapped;

    /**
     * @var \CultuurNet\UDB3\Cdb\Event\SpecificationInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $specification;

    /**
     * @var string
     */
    private $xml;

    public function setUp()
    {
        $this->specification = $this->getMock(
            \CultuurNet\UDB3\Cdb\Event\SpecificationInterface::class
        );

        $this->wrapped = $this->getMock(
            EventCdbXmlServiceInterface::class
        );

        $this->specificationDecoratedEventCdbXml = new \CultuurNet\UDB3\UDB2\Event\SpecificationDecoratedEventCdbXml(
            $this->wrapped,
            $this->specification
        );

        $this->xml = file_get_contents(__DIR__ . '/../samples/event.xml');
    }

    /**
     * @test
     */
    public function it_delegates_retrieval_of_cdbxml_to_the_wrapped_service()
    {
        $this->wrapped->expects($this->any())
            ->method('getCdbXmlNamespaceUri')
            ->willReturn('http://www.cultuurdatabank.com/XMLSchema/CdbXSD/3.3/FINAL');

        $this->assertEquals(
            'http://www.cultuurdatabank.com/XMLSchema/CdbXSD/3.3/FINAL',
            $this->specificationDecoratedEventCdbXml->getCdbXmlNamespaceUri()
        );

        $this->wrapped->expects($this->once())
            ->method('getCdbXmlOfEvent')
            ->with('foo')
            ->willReturn($this->xml);

        $this->specification->expects($this->once())
            ->method('isSatisfiedByEvent')
            ->willReturn(true);

        $this->assertEquals(
            $this->xml,
            $this->specificationDecoratedEventCdbXml->getCdbXmlOfEvent('foo')
        );
    }

    /**
     * @test
     */
    public function it_ensures_the_retrieved_cdbxml_satisfies_the_spec()
    {
        $this->setExpectedException(EventNotFoundException::class);

        $this->wrapped->expects($this->once())
            ->method('getCdbXmlOfEvent')
            ->with('foo')
            ->willReturn($this->xml);

        $this->specification->expects($this->once())
            ->method('isSatisfiedByEvent')
            ->willReturn(false);

        $this->specificationDecoratedEventCdbXml->getCdbXmlOfEvent('foo');
    }
}
