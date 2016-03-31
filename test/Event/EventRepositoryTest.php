<?php

namespace CultuurNet\UDB3\UDB2\Event;

use Broadway\Domain\Metadata;
use Broadway\EventSourcing\EventSourcedAggregateRoot;
use Broadway\EventSourcing\MetadataEnrichment\MetadataEnrichingEventStreamDecorator;
use Broadway\Repository\RepositoryInterface;
use CultuurNet\Auth\TokenCredentials;
use CultuurNet\Entry\EntityType;
use CultuurNet\Entry\EntryAPI;
use CultuurNet\UDB3\BookingInfo;
use CultuurNet\UDB3\Cdb\EventItemFactory;
use CultuurNet\UDB3\CollaborationData;
use CultuurNet\UDB3\Event\Commands\EventCommandFactory;
use CultuurNet\UDB3\Event\Event;
use CultuurNet\UDB3\EventSourcing\ExecutionContextMetadataEnricher;
use CultuurNet\UDB3\EventXmlString;
use CultuurNet\UDB3\Label;
use CultuurNet\UDB3\Language;
use CultuurNet\UDB3\LabelCollection;
use CultuurNet\UDB3\OrganizerService;
use CultuurNet\UDB3\PlaceService;
use CultuurNet\UDB3\UDB2\EntryAPIImprovedFactoryInterface;
use CultuurNet\UDB3\UDB2\EventImporterInterface;
use CultuurNet\UDB3\UDB2\EventRepository;
use CultuurNet\UDB3\UDB2\Media\EditImageTestTrait;
use PHPUnit_Framework_TestCase;
use CultureFeed_Cdb_Data_ContactInfo;
use ValueObjects\String\String;
use ValueObjects\Web\Url;

class EventRepositoryTest extends PHPUnit_Framework_TestCase
{
    use EditImageTestTrait;
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
        $this->decoratedRepository->expects($this->any())
            ->method('save')
            ->willReturnCallback(
                function (EventSourcedAggregateRoot $aggregateRoot) {
                    // Clear the uncommitted events.
                    $aggregateRoot->getUncommittedEvents();
                }
            );

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

        $this->commandFactory = new EventCommandFactory();
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

        $cdbXml = file_get_contents(__DIR__ . '/../samples/event.xml');

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
                file_get_contents(__DIR__ . '/../samples/contactinfo.xml'),
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

        $cdbXml = file_get_contents(__DIR__ . '/../samples/event.xml');

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
                file_get_contents(__DIR__ . '/../samples/contactinfo-emptied.xml'),
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
        $expectedXmlStringArgument = file_get_contents(__DIR__ . '/../samples/eventrepositorytest_event_with_cdbid.xml');

        $this->entryAPI->expects($this->once())
            ->method('createEventFromRawXml')
            ->with($expectedXmlStringArgument);

        $event = $this->createItem(
            'd53c2bc9-8f0e-4c9a-8457-77e8b3cab3d1',
            'eventrepositorytest_event.xml'
        );

        $this->repository->syncBackOn();
        $this->repository->save($event);
    }

    /**
     * @test
     */
    public function it_updates_an_event_from_cdbxml()
    {
        $id = 'd53c2bc9-8f0e-4c9a-8457-77e8b3cab3d1';

        $expectedXmlStringArgument = file_get_contents(__DIR__ . '/../samples/eventrepositorytest_event_with_cdbid.xml');

        $this->entryAPI->expects($this->once())
            ->method('updateEventFromRawXml')
            ->with($id, $expectedXmlStringArgument);

        $cdbXml = file_get_contents(__DIR__ . '/../samples/eventrepositorytest_event.xml');

        $event = Event::createFromCdbXml(
            new String($id),
            new EventXmlString($cdbXml),
            new String(self::NS)
        );

        $this->repository->save($event);

        $event->updateFromCdbXml(
            new String($id),
            new EventXmlString($cdbXml),
            new String(self::NS)
        );

        $this->repository->syncBackOn();
        $this->repository->save($event);
    }

    /**
     * @test
     */
    public function it_applies_labels()
    {
        $id = 'd53c2bc9-8f0e-4c9a-8457-77e8b3cab3d1';
        $event = $this->createItem($id, 'eventrepositorytest_event.xml');

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
    public function it_adds_a_label()
    {
        $id = 'd53c2bc9-8f0e-4c9a-8457-77e8b3cab3d1';
        $event = $this->createItem($id, 'eventrepositorytest_event.xml');

        $this->repository->save($event);

        $expectedKeyword = [
            new Label('Keyword B', true),
        ];

        $event->addLabel(
            new Label('Keyword B')
        );

        $this->entryAPI->expects($this->once())
            ->method('addKeywords')
            ->with(
                $id,
                $expectedKeyword
            );

        $this->repository->syncBackOn();
        $this->repository->save($event);
    }

    /**
     * @test
     */
    public function it_deletes_a_label()
    {
        $id = 'd53c2bc9-8f0e-4c9a-8457-77e8b3cab3d1';
        $event = $this->createItem($id, 'eventrepositorytest_event.xml');

        $this->repository->save($event);

        $event->addLabel(
            new Label('Keyword B')
        );

        $expectedKeyword = new Label('Keyword B');

        $event->deleteLabel(
            new Label('Keyword B')
        );

        $this->entryAPI->expects($this->once())
            ->method('deleteKeyword')
            ->with(
                $id,
                $expectedKeyword
            );

        $this->repository->syncBackOn();
        $this->repository->save($event);
    }

    /**
     * @test
     */
    public function it_does_not_delete_a_label_that_does_not_exist()
    {
        $id = 'd53c2bc9-8f0e-4c9a-8457-77e8b3cab3d1';
        $event = $this->createItem($id, 'eventrepositorytest_event.xml');

        $this->repository->save($event);

        $event->deleteLabel(
            new Label('Keyword B')
        );

        $this->entryAPI->expects($this->never())
            ->method('deleteKeyword');

        $this->repository->syncBackOn();
        $this->repository->save($event);
    }

    /**
     * @test
     */
    public function it_can_translate_the_title()
    {
        $id = 'd53c2bc9-8f0e-4c9a-8457-77e8b3cab3d1';
        $event = $this->createItem($id, 'eventrepositorytest_event.xml');

        $this->repository->save($event);

        $expectedLanguage = new Language('en');
        $expectedTitle = new String('English title');

        $event->translateTitle(
            new Language('en'),
            new String('English title')
        );

        $this->entryAPI->expects($this->once())
            ->method('translateEventTitle')
            ->with(
                $id,
                $expectedLanguage,
                $expectedTitle
            );

        $this->repository->syncBackOn();
        $this->repository->save($event);
    }

    /**
     * @test
     */
    public function it_can_translate_the_decription()
    {
        $id = 'd53c2bc9-8f0e-4c9a-8457-77e8b3cab3d1';
        $event = $this->createItem($id, 'eventrepositorytest_event.xml');

        $this->repository->save($event);

        $expectedLanguage = new Language('en');
        $expectedDescription = new String('English description');

        $event->translateDescription(
            new Language('en'),
            new String('English description')
        );

        $this->entryAPI->expects($this->once())
            ->method('translateEventDescription')
            ->with(
                $id,
                $expectedLanguage,
                $expectedDescription
            );

        $this->repository->syncBackOn();
        $this->repository->save($event);
    }

    /**
     * @test
     */
    public function it_applies_a_translation()
    {
        $id = 'd53c2bc9-8f0e-4c9a-8457-77e8b3cab3d1';
        $event = $this->createItem($id, 'eventrepositorytest_event.xml');

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

    /**
     * @test
     */
    public function it_deletes_a_translation()
    {
        $id = 'd53c2bc9-8f0e-4c9a-8457-77e8b3cab3d1';
        $event = $this->createItem($id, 'eventrepositorytest_event.xml');

        $event->applyTranslation(
            new Language('en'),
            new String('Some english translated title')
        );

        $this->repository->save($event);

        $event->deleteTranslation(
            new Language('en')
        );

        $this->entryAPI->expects($this->once())
            ->method('deleteTranslation')
            ->with(
                $id,
                new Language('en')
            );

        $this->repository->syncBackOn();
        $this->repository->save($event);
    }

    /**
     * @test
     */
    public function it_adds_collaboration_data()
    {
        $id = 'd53c2bc9-8f0e-4c9a-8457-77e8b3cab3d1';
        $event = $this->createItem($id, 'eventrepositorytest_event.xml');

        $this->repository->save($event);

        $collaborationData = (new CollaborationData(
            new String('sub-brand-foo'),
            new String('some plain text')
        ))
            ->withTitle(new String('Title'))
            ->withText(new String('Text'))
            ->withArticle(new String('Article'))
            ->withKeyword(new String('Keyword'))
            ->withImage(new String('Image'))
            ->withLink(Url::fromNative('http://google.com'))
            ->withCopyright(new String('Copyright'));

        $event->addCollaborationData(
            new Language('en'),
            $collaborationData
        );

        $expectedDescription = [
            'text' => 'Text',
            'keyword' => 'Keyword',
            'article' => 'Article',
            'image' => 'Image',
        ];

        $this->entryAPI->expects($this->once())
            ->method('createCollaborationLink')
            ->with(
                $id,
                'en',
                'sub-brand-foo',
                json_encode($expectedDescription),
                'some plain text',
                'Title',
                'Copyright',
                'http://google.com'
            );

        $this->repository->syncBackOn();
        $this->repository->save($event);
    }

    /**
     * @test
     */
    public function it_should_remove_the_description_when_updating_with_an_empty_string()
    {
        $id = 'd53c2bc9-8f0e-4c9a-8457-77e8b3cab3d1';
        $event = $this->createItem($id, 'eventrepositorytest_event.xml');

        $event->updateDescription('');

        $this->entryAPI->expects($this->once())
            ->method('deleteDescription')
            ->with(
                $id,
                new EntityType('event'),
                new \CultuurNet\Entry\Language('nl')
            );

        $this->repository->syncBackOn();
        $this->repository->save($event);
    }

    /**
     * @test
     */
    public function it_should_update_the_description()
    {
        $id = 'd53c2bc9-8f0e-4c9a-8457-77e8b3cab3d1';
        $event = $this->createItem($id, 'eventrepositorytest_event.xml');

        $event->updateDescription('beep boop');

        $this->entryAPI->expects($this->once())
            ->method('updateDescription')
            ->with(
                $id,
                new EntityType('event'),
                'beep boop',
                new \CultuurNet\Entry\Language('nl')
            );

        $this->repository->syncBackOn();
        $this->repository->save($event);
    }

    /**
     * @param string $id
     * @param string $xmlFile
     * @param string $ns
     * @return Event
     */
    private function createItem(
        $id,
        $xmlFile,
        $ns = self::NS
    ) {
        $cdbXml = file_get_contents(__DIR__ . '/../samples/' . $xmlFile);

        return Event::createFromCdbXml(
            new String((string) $id),
            new EventXmlString($cdbXml),
            new String((string) $ns)
        );
    }
}
