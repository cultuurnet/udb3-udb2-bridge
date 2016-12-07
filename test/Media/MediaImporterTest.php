<?php

namespace CultuurNet\UDB3\UDB2\Media;

use CultuurNet\UDB3\Event\Events\EventImportedFromUDB2;
use CultuurNet\UDB3\Media\Image;
use CultuurNet\UDB3\Media\ImageCollection;
use CultuurNet\UDB3\Media\MediaManagerInterface;
use CultuurNet\UDB3\Media\Properties\MIMEType;
use ValueObjects\Identity\UUID;
use ValueObjects\String\String as StringLiteral;
use ValueObjects\Web\Url;

class MediaImporterTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var MediaImporter
     */
    private $importer;

    /**
     * @var MediaManagerInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $mediaManager;

    /**
     * @var ImageCollectionFactoryInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $imageCollectionFactory;

    public function setup()
    {
        $this->mediaManager = $this->getMock(MediaManagerInterface::class);
        $this->imageCollectionFactory = $this->getMock(ImageCollectionFactoryInterface::class);
        $this->importer = new MediaImporter($this->mediaManager, $this->imageCollectionFactory);
    }

    /**
     * @test
     */
    public function it_should_create_media_objects_from_event_media_when_importing_from_udb2()
    {
        $cdbXml = file_get_contents(__DIR__ . '/../Label/Samples/event.xml');
        $cdbXmlNamespaceUri = \CultureFeed_Cdb_Xml::namespaceUriForVersion('3.3');

        $eventImportedFromUdb2 = new EventImportedFromUDB2(
            'd53c2bc9-8f0e-4c9a-8457-77e8b3cab3d1',
            $cdbXml,
            $cdbXmlNamespaceUri
        );

        $this->imageCollectionFactory
            ->method('fromUdb2Media')
            ->willReturn(ImageCollection::fromArray([
                new Image(
                    UUID::fromNative('f26433f0-97ef-5c07-8ea9-ef00a64dcb59'),
                    MIMEType::fromNative('image/jpeg'),
                    StringLiteral::fromNative('no description'),
                    StringLiteral::fromNative('Zelf gemaakt'),
                    Url::fromNative('http://85.255.197.172/images/20140108/9554d6f6-bed1-4303-8d42-3fcec4601e0e.jpg')
                )
            ]));

        $this->mediaManager
            ->expects($this->once())
            ->method('create')
            ->with(
                UUID::fromNative('f26433f0-97ef-5c07-8ea9-ef00a64dcb59'),
                MIMEType::fromNative('image/jpeg'),
                StringLiteral::fromNative('no description'),
                StringLiteral::fromNative('Zelf gemaakt'),
                Url::fromNative('http://85.255.197.172/images/20140108/9554d6f6-bed1-4303-8d42-3fcec4601e0e.jpg')
            );

        $this->importer->importImages($eventImportedFromUdb2);
    }
}
