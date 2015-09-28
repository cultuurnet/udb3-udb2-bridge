<?php
/**
 * @file
 */

namespace CultuurNet\UDB3\UDB2;

use CultureFeed_Cdb_Item_Event as Event;

class LabeledAsUDB3PlaceTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var LabeledAsUDB3Place
     */
    private $spec;

    public function setUp()
    {
        $this->spec = new LabeledAsUDB3Place();
    }

    /**
     * @param Callable $function
     * @return Event
     */
    private function eventWith($function)
    {
        $c = new Event();

        $function($c);

        return $c;
    }

    public function eventData()
    {
        $data = [];

        $data[] = [
            $this->eventWith(
                function (Event $e) {
                    $e->addKeyword('UdB3 PlAcE');
                }
            ),
            true
        ];

        $data[] = [
            $this->eventWith(
                function (Event $e) {
                    $e->addKeyword('UDB3 place');
                }
            ),
            true
        ];

        $data[] = [
            $this->eventWith(
                function (Event $e) {
                    $e->addKeyword('UDB3');
                }
            ),
            false
        ];

        $data[] = [
            new Event(),
            false
        ];

        return $data;
    }

    /**
     * @test
     * @dataProvider EventData
     */
    public function it_is_satisifed_by_an_event_with_the_udb3_place_keyword(
        Event $event,
        $satisfied
    ) {
        $this->assertEquals(
            $satisfied,
            $this->spec->isSatisfiedByEvent($event)
        );
    }
}
