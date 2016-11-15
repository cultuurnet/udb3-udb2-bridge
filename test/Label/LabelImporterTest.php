<?php

namespace CultuurNet\UDB3\UDB2\Label;

use Broadway\CommandHandling\CommandBusInterface;

class LabelImporterTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var LabelImporter
     */
    private $labelImporter;

    protected function setUp()
    {
        $commandBus = $this->getMock(CommandBusInterface::class);

        $this->labelImporter = new LabelImporter($commandBus);
    }

    /**
     * @test
     */
    public function it_dispatches_label_added_commands_when_applying_event_imported_from_udb2()
    {
        $this->assertTrue(false);
    }

    /**
     * @test
     */
    public function it_does_not_dispatch_label_added_commands_when_event_imported_from_udb2_has_no_labels()
    {
        $this->assertTrue(false);
    }

    /**
     * @test
     */
    public function it_dispatches_label_added_commands_when_applying_place_imported_from_udb2()
    {
        $this->assertTrue(false);
    }

    /**
     * @test
     */
    public function it_does_not_dispatch_label_added_commands_when_place_imported_from_udb2_has_no_labels()
    {
        $this->assertTrue(false);
    }
}
