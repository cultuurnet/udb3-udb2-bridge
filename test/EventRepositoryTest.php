<?php
/**
 * @file
 */

namespace CultuurNet\UDB3\UDB2;

use Broadway\Domain\Metadata;
use Broadway\EventSourcing\MetadataEnrichment\MetadataEnrichingEventStreamDecorator;
use Broadway\Repository\RepositoryInterface;
use CultuurNet\Auth\TokenCredentials;
use CultuurNet\Entry\EntryAPI;
use CultuurNet\UDB3\BookingInfo;
use CultuurNet\UDB3\Cdb\EventItemFactory;
use CultuurNet\UDB3\EntityServiceInterface;
use CultuurNet\UDB3\Event\Event;
use CultuurNet\UDB3\Event\ReadModel\JSONLD\OrganizerServiceInterface;
use CultuurNet\UDB3\Event\ReadModel\JSONLD\PlaceServiceInterface;
use CultuurNet\UDB3\EventSourcing\ExecutionContextMetadataEnricher;
use CultuurNet\UDB3\EventXmlString;
use CultuurNet\UDB3\Label;
use CultuurNet\UDB3\Language;
use CultuurNet\UDB3\LabelCollection;
use CultuurNet\UDB3\OrganizerService;
use CultuurNet\UDB3\PlaceService;
use PHPUnit_Framework_TestCase;
use CultureFeed_Cdb_Data_ContactInfo;
use ValueObjects\String\String;

class EventRepositoryTest extends PHPUnit_Framework_TestCase
{

    const NS = 'http://www.cultuurdatabank.com/XMLSchema/CdbXSD/3.3/FINAL';

    /**
     * @var EventRepository
     */
    private $repository;

    /**
     * @var RepositoryInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $decoratedRepository;

    /**
     * @var PlaceService|\PHPUnit_Framework_MockObject_MockObject
     */
    private $placeService;

    /**
     * @var OrganizerService|\PHPUnit_Framework_MockObject_MockObject
     */
    private $organizerService;

    /**
     * @var EventImporterInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $eventImporter;

    /**
     * @var EntryAPIImprovedFactoryInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $entryAPIImprovedFactory;

    /**
     * @var EntryAPI|\PHPUnit_Framework_MockObject_MockObject
     */
    private $entryAPI;

    public function setUp()
    {
        $this->decoratedRepository = $this->getMock(RepositoryInterface::class);

        $this->entryAPIImprovedFactory = $this->getMock(
            EntryAPIImprovedFactoryInterface::class
        );
        $this->eventImporter = $this->getMock(EventImporterInterface::class);
        $this->placeService = $this->getMockBuilder(PlaceService::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->organizerService = $this->getMockBuilder(OrganizerService::class)
            ->disableOriginalConstructor()
            ->getMock();

        $contextEnricher = new ExecutionContextMetadataEnricher();
        $contextEnricher->setContext(
            new Metadata([
                'uitid_token_credentials' => new TokenCredentials(
                    'token string',
                    'token secret'
                )
            ])
        );

        $this->repository = new EventRepository(
            $this->decoratedRepository,
            $this->entryAPIImprovedFactory,
            $this->eventImporter,
            $this->placeService,
            $this->organizerService,
            [
                new MetadataEnrichingEventStreamDecorator(
                    [$contextEnricher]
                )
            ]
        );

        $this->entryAPI = $this->getMockBuilder(EntryAPI::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->entryAPIImprovedFactory->expects($this->any())
            ->method('withTokenCredentials')
            ->willReturn($this->entryAPI);
    }

    /**
     * @test
     */
    public function it_updates_udb2_reservation_contact_info_based_on_udb3_booking_info()
    {
        if (!class_exists(BookingInfo::class)) {
            $this->markTestSkipped();
        }

        $cdbXmlNamespaceUri = self::NS;

        $id = 'd53c2bc9-8f0e-4c9a-8457-77e8b3cab3d1';

        $cdbXml = file_get_contents(__DIR__ . '/event.xml');

        $event = Event::importFromUDB2(
            $id,
            $cdbXml,
            $cdbXmlNamespaceUri
        );

        $udb2Event = EventItemFactory::createEventFromCdbXml(
            $cdbXmlNamespaceUri,
            $cdbXml
        );

        $this->repository->save($event);

        $bookingInfo = new BookingInfo(
            'http://tickets.example.com',
            'Tickets on Example.com',
            '+32 666 666',
            'tickets@example.com',
            '2007-03-01T13:00:00Z',
            '2007-03-01T13:00:00Z',
            'booking name'
        );
        $event->updateBookingInfo($bookingInfo);

        $expectedContactInfo = CultureFeed_Cdb_Data_ContactInfo::parseFromCdbXml(
            new \SimpleXMLElement(
                file_get_contents(__DIR__ . '/contactinfo.xml'),
                0,
                false,
                $cdbXmlNamespaceUri
            )
        );

        $this->entryAPI->expects($this->once())
            ->method('getEvent')
            ->with($id)
            ->willReturn($udb2Event);

        $this->entryAPI->expects($this->once())
            ->method('updateContactInfo')
            ->with(
                $id,
                'event',
                $expectedContactInfo
            );

        $this->repository->syncBackOn();
        $this->repository->save($event);
    }

    /**
     * @test
     */
    public function it_empties_udb2_reservation_contact_info_based_on_udb3_booking_info()
    {
        if (!class_exists(BookingInfo::class)) {
            $this->markTestSkipped();
        }

        $cdbXmlNamespaceUri = self::NS;

        $id = 'd53c2bc9-8f0e-4c9a-8457-77e8b3cab3d1';

        $cdbXml = file_get_contents(__DIR__ . '/event.xml');

        $event = Event::importFromUDB2(
            $id,
            $cdbXml,
            $cdbXmlNamespaceUri
        );

        $udb2Event = EventItemFactory::createEventFromCdbXml(
            $cdbXmlNamespaceUri,
            $cdbXml
        );

        $this->repository->save($event);

        $bookingInfo = new BookingInfo(
            '',
            '',
            '',
            '',
            '2007-03-01T13:00:00Z',
            '2007-03-01T13:00:00Z',
            'booking name'
        );
        $event->updateBookingInfo($bookingInfo);

        $expectedContactInfo = CultureFeed_Cdb_Data_ContactInfo::parseFromCdbXml(
            new \SimpleXMLElement(
                file_get_contents(__DIR__ . '/contactinfo-emptied.xml'),
                0,
                false,
                $cdbXmlNamespaceUri
            )
        );

        $this->entryAPI->expects($this->once())
            ->method('getEvent')
            ->with($id)
            ->willReturn($udb2Event);

        $this->entryAPI->expects($this->once())
            ->method('updateContactInfo')
            ->with(
                $id,
                'event',
                $expectedContactInfo
            );

        $this->repository->syncBackOn();
        $this->repository->save($event);
    }

    /**
     * @test
     */
    public function it_creates_an_event_from_cdbxml()
    {
        $expectedXmlStringArgument = file_get_contents(__DIR__ . '/eventrepositorytest_event_with_cdbid.xml');

        $this->entryAPI->expects($this->once())
            ->method('createEventFromRawXml')
            ->with($expectedXmlStringArgument);

        $cdbXmlNamespaceUri = new String(self::NS);

        $id = 'd53c2bc9-8f0e-4c9a-8457-77e8b3cab3d1';
        $idString = new String($id);

        $cdbXml = file_get_contents(__DIR__ . '/eventrepositorytest_event.xml');
        $eventXmlString = new EventXmlString($cdbXml);

        $event = Event::createFromCdbXml(
            $idString,
            $eventXmlString,
            $cdbXmlNamespaceUri
        );

        $this->repository->syncBackOn();
        $this->repository->save($event);
    }

    /**
     * @test
     */
    public function it_updates_an_event_from_cdbxml()
    {
        $expectedEventId = 'd53c2bc9-8f0e-4c9a-8457-77e8b3cab3d1';
        $expectedXmlStringArgument = file_get_contents(__DIR__ . '/eventrepositorytest_event_with_cdbid.xml');

        $cdbXmlNamespaceUri = new String(self::NS);

        $id = 'd53c2bc9-8f0e-4c9a-8457-77e8b3cab3d1';
        $idString = new String($id);

        $cdbXml = file_get_contents(__DIR__ . '/eventrepositorytest_event.xml');
        $eventXmlString = new EventXmlString($cdbXml);

        $event = Event::createFromCdbXml(
            $idString,
            $eventXmlString,
            $cdbXmlNamespaceUri
        );

        $this->repository->save($event);

        $this->entryAPI->expects($this->once())
            ->method('updateEventFromRawXml')
            ->with($expectedEventId, $expectedXmlStringArgument);

        $event->updateFromCdbXml(
            $idString,
            $eventXmlString,
            $cdbXmlNamespaceUri
        );

        $this->repository->syncBackOn();
        $this->repository->save($event);
    }

    /**
     * @test
     */
    public function it_applies_labels()
    {
        $cdbXmlNamespaceUri = self::NS;

        $id = 'd53c2bc9-8f0e-4c9a-8457-77e8b3cab3d1';
        $idString = new String($id);

        $cdbXml = file_get_contents(__DIR__ . '/eventrepositorytest_event.xml');
        $eventXmlString = new EventXmlString($cdbXml);

        $event = Event::createFromCdbXml(
            $idString,
            $eventXmlString,
            new String($cdbXmlNamespaceUri)
        );

        $this->repository->save($event);

        $expectedKeywords = [
            new Label('Keyword B', true),
            new Label('Keyword C', false)
        ];

        $event->mergeLabels(
            new LabelCollection($expectedKeywords)
        );

        $this->entryAPI->expects($this->once())
            ->method('addKeywords')
            ->with(
                $id,
                $expectedKeywords
            );

        $this->repository->syncBackOn();
        $this->repository->save($event);
    }

    /**
     * @test
     */
    public function it_applies_a_translation()
    {
        $cdbXmlNamespaceUri = self::NS;

        $id = 'd53c2bc9-8f0e-4c9a-8457-77e8b3cab3d1';
        $idString = new String($id);

        $cdbXml = file_get_contents(__DIR__ . '/eventrepositorytest_event.xml');
        $eventXmlString = new EventXmlString($cdbXml);

        $event = Event::createFromCdbXml(
            $idString,
            $eventXmlString,
            new String($cdbXmlNamespaceUri)
        );

        $this->repository->save($event);

        $event->applyTranslation(
            new Language('en'),
            new String('Title'),
            new String('Short description'),
            new String('Long long long extra long description')
        );

        $expectedFields = [
            'title' => 'Title',
            'shortdescription' => 'Short description',
            'longdescription' => 'Long long long extra long description'
        ];

        $this->entryAPI->expects($this->once())
            ->method('translate')
            ->with(
                $id,
                new Language('en'),
                $expectedFields
            );

        $this->repository->syncBackOn();
        $this->repository->save($event);
    }
}
