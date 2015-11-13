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

    public function invalidKeywordsProvider()
    {
        return [
            [
                [''],
                false,
            ],
            [
                [';'],
                false,
            ],
            [
                ['', 'udb3 place'],
                true,
            ],
        ];
    }

    /**
     * @test
     * @dataProvider invalidKeywordsProvider
     */
    public function it_gracefully_handles_invalid_keywords(
        $keywords,
        $satisfied
    )
    {
        $event = $this->eventWith(
            function (Event $e) use ($keywords) {
                foreach ($keywords as $keyword) {
                    $e->addKeyword($keyword);
                }
            }
        );

        $this->assertEquals(
            $satisfied,
            $this->spec->isSatisfiedByEvent($event)
        );
    }
}
