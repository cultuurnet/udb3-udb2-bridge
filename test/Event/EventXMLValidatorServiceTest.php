<?php

namespace CultuurNet\UDB3\UDB2\Event;

use CultuurNet\UDB3\UDB2\XML\XMLValidationException;
use ValueObjects\StringLiteral\StringLiteral;

class EventXMLValidatorServiceTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @test
     */
    public function it_throws_for_same_location_and_organizer_cdbid()
    {
        $eventXmlValidatorService = new EventXMLValidatorService(
            new StringLiteral('http://www.cultuurdatabank.com/XMLSchema/CdbXSD/3.3/FINAL')
        );

        $this->expectException(XMLValidationException::class);

        $eventXml = file_get_contents(__DIR__ . '/samples/event_same_cdbid_location_and_organizer.xml');
        $eventXmlValidatorService->validate($eventXml);
    }
}
