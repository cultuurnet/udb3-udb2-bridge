<?php

namespace CultuurNet\UDB3\UDB2\Event;

use Broadway\Domain\AggregateRoot;
use Broadway\Domain\DomainMessage;
use CultuurNet\UDB3\Place\Events\PlaceImportedFromUDB2Event;
use CultuurNet\UDB3\Place\Place;
use ValueObjects\String\String;

class EventToUDB3PlaceFactoryTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @test
     */
    public function it_creates_a_place_entity_based_on_cdbxml()
    {
        $factory = new EventToUDB3PlaceFactory();

        $id = '404EE8DE-E828-9C07-FE7D12DC4EB24480';
        $cdbXml = file_get_contents(__DIR__ . '/samples/event.xml');
        $cdbXmlNamespaceUri = 'http://www.cultuurdatabank.com/XMLSchema/CdbXSD/3.3/FINAL';

        $place = $factory->createFromCdbXml(
            new String($id),
            new String($cdbXml),
            new String($cdbXmlNamespaceUri)
        );

        $this->assertInstanceOf(Place::class, $place);
        $this->assertEvents(
            [
                new PlaceImportedFromUDB2Event(
                    $id,
                    $cdbXml,
                    $cdbXmlNamespaceUri
                ),
            ],
            $place
        );
    }

    private function assertEvents(array $expectedEvents, AggregateRoot $place)
    {
        $domainMessages = iterator_to_array(
            $place->getUncommittedEvents()->getIterator()
        );

        $payloads = array_map(
            function (DomainMessage $item) {
                return $item->getPayload();
            },
            $domainMessages
        );

        $this->assertEquals(
            $expectedEvents,
            $payloads
        );
    }
}