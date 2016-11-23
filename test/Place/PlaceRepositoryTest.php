<?php

namespace CultuurNet\UDB3\UDB2\Place;

use Broadway\Domain\Metadata;
use Broadway\EventSourcing\EventSourcedAggregateRoot;
use Broadway\EventSourcing\MetadataEnrichment\MetadataEnrichingEventStreamDecorator;
use Broadway\Repository\AggregateNotFoundException;
use Broadway\Repository\RepositoryInterface;
use CultuurNet\Auth\TokenCredentials;
use CultuurNet\Entry\EntryAPI;
use CultuurNet\UDB3\EntityServiceInterface;
use CultuurNet\UDB3\EventSourcing\ExecutionContextMetadataEnricher;
use CultuurNet\UDB3\Label;
use CultuurNet\UDB3\Language;
use CultuurNet\UDB3\OrganizerService;
use CultuurNet\UDB3\Place\Commands\PlaceCommandFactory;
use CultuurNet\UDB3\Place\Place;
use CultuurNet\UDB3\UDB2\EntryAPIImprovedFactoryInterface;
use CultuurNet\UDB3\UDB2\Media\EditImageTestTrait;
use ValueObjects\String\String;

class PlaceRepositoryTest extends \PHPUnit_Framework_TestCase
{
    use EditImageTestTrait;
    const NS = 'http://www.cultuurdatabank.com/XMLSchema/CdbXSD/3.3/FINAL';

    /**
     * @var PlaceRepository
     */
    private $repository;

    /**
     * @var RepositoryInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $innerRepository;

    /**
     * @var PlaceImporterInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $placeImporter;

    /**
     * @var OrganizerService
     */
    private $organizerService;

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
        $this->innerRepository = $this->getMock(RepositoryInterface::class);
        $this->innerRepository->expects($this->any())
            ->method('save')
            ->willReturnCallback(
                function (EventSourcedAggregateRoot $aggregateRoot) {
                    // Clear the uncommitted events.
                    $aggregateRoot->getUncommittedEvents();
                }
            );

        $this->placeImporter = $this->getMock(PlaceImporterInterface::class);
        $this->organizerService = $this->getMock(EntityServiceInterface::class);
        $this->entryAPIImprovedFactory = $this->getMock(
            EntryAPIImprovedFactoryInterface::class
        );

        $contextEnricher = new ExecutionContextMetadataEnricher();
        $contextEnricher->setContext(
            new Metadata([
                'uitid_token_credentials' => new TokenCredentials(
                    'token string',
                    'token secret'
                )
            ])
        );

        $this->repository = new PlaceRepository(
            $this->innerRepository,
            $this->entryAPIImprovedFactory,
            $this->placeImporter,
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

        $this->commandFactory = new PlaceCommandFactory();
    }

    /**
     * @test
     */
    public function it_calls_the_importer_if_the_place_is_missing_in_the_decorated_repository()
    {
        $id = 'foo';

        $this->innerRepository->expects($this->once())
            ->method('load')
            ->with($id)
            ->willThrowException(
                new AggregateNotFoundException()
            );

        $this->placeImporter->expects($this->once())
            ->method('createPlaceFromUDB2')
            ->with($id)
            ->willReturn(new Place());

        $this->repository->load($id);
    }

    /**
     * @test
     */
    public function it_reports_an_exception_if_the_importer_does_not_succeed()
    {
        $id = 'foo';

        $this->innerRepository->expects($this->once())
            ->method('load')
            ->with($id)
            ->willThrowException(
                new AggregateNotFoundException()
            );

        $this->placeImporter->expects($this->once())
            ->method('createPlaceFromUDB2')
            ->with($id);

        $this->setExpectedException(AggregateNotFoundException::class);

        $this->repository->load($id);
    }

    /**
     * @test
     */
    public function it_adds_a_label()
    {
        $id = 'd53c2bc9-8f0e-4c9a-8457-77e8b3cab3d1';
        $place = $this->createItem($id, 'place.xml');

        $this->innerRepository->save($place);

        $expectedKeyword = [
            new Label('Keyword B', true),
        ];

        $place->addLabel(
            new Label('Keyword B')
        );

        $this->entryAPI->expects($this->once())
            ->method('addKeywords')
            ->with(
                $id,
                $expectedKeyword
            );

        $this->repository->syncBackOn();
        $this->repository->save($place);
    }

    /**
     * @test
     */
    public function it_removes_a_label()
    {
        $id = 'd53c2bc9-8f0e-4c9a-8457-77e8b3cab3d1';
        $place = $this->createItem($id, 'place.xml');

        $this->innerRepository->save($place);

        $place->addLabel(
            new Label('Keyword B')
        );

        $expectedKeyword = new Label('Keyword B');

        $place->removeLabel(
            new Label('Keyword B')
        );

        $this->entryAPI->expects($this->once())
            ->method('deleteKeyword')
            ->with(
                $id,
                $expectedKeyword
            );

        $this->repository->syncBackOn();
        $this->repository->save($place);
    }

    /**
     * @test
     */
    public function it_does_not_remove_a_label_that_does_not_exist()
    {
        $id = 'd53c2bc9-8f0e-4c9a-8457-77e8b3cab3d1';
        $place = $this->createItem($id, 'place.xml');

        $this->innerRepository->save($place);

        $place->removeLabel(
            new Label('Keyword B')
        );

        $this->entryAPI->expects($this->never())
            ->method('deleteKeyword');

        $this->repository->syncBackOn();
        $this->repository->save($place);
    }

    /**
     * @test
     */
    public function it_can_translate_the_title()
    {
        $id = 'd53c2bc9-8f0e-4c9a-8457-77e8b3cab3d1';
        $place = $this->createItem($id, 'place.xml');

        $this->innerRepository->save($place);

        $expectedLanguage = new Language('en');
        $expectedTitle = new String('English title');

        $place->translateTitle(
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
        $this->repository->save($place);
    }

    /**
     * @test
     */
    public function it_can_translate_the_decription()
    {
        $id = 'd53c2bc9-8f0e-4c9a-8457-77e8b3cab3d1';
        $place = $this->createItem($id, 'place.xml');

        $this->innerRepository->save($place);

        $expectedLanguage = new Language('en');
        $expectedDescription = new String('English description');

        $place->translateDescription(
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
        $this->repository->save($place);
    }

    private function createItem(
        $id,
        $xmlFile,
        $ns = self::NS
    ) {
        $cdbXml = file_get_contents(__DIR__ . '/../samples/' . $xmlFile);

        return Place::importFromUDB2Event(
            $id,
            $cdbXml,
            $ns
        );
    }
}
