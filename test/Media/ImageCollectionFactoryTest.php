<?php

namespace Media;

use CultureFeed_Cdb_Data_Media;
use CultureFeed_Cdb_Item_Base;
use CultuurNet\UDB3\Cdb\EventItemFactory;
use CultuurNet\UDB3\Media\Image;
use CultuurNet\UDB3\Media\ImageCollection;
use CultuurNet\UDB3\Media\Properties\MIMEType;
use CultuurNet\UDB3\UDB2\Media\ImageCollectionFactory;
use ValueObjects\Identity\UUID;
use ValueObjects\String\String as StringLiteral;
use ValueObjects\Web\Url;

class ImageCollectionFactoryTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @test
     */
    public function it_should_return_a_collection_of_images_from_udb2_media()
    {
        $image = new Image(
            UUID::fromNative('f26433f0-97ef-5c07-8ea9-ef00a64dcb59'),
            MIMEType::fromNative('image/jpeg'),
            new StringLiteral('¯\_(ツ)_/¯'),
            new StringLiteral('Zelf gemaakt'),
            Url::fromNative('http://85.255.197.172/images/20140108/9554d6f6-bed1-4303-8d42-3fcec4601e0e.jpg')
        );
        $expectedImages = (new ImageCollection())->with($image);
        $cdbXml = file_get_contents(__DIR__ . '/../Label/Samples/event.xml');
        $cdbXmlNamespaceUri = \CultureFeed_Cdb_Xml::namespaceUriForVersion('3.3');

        $event = EventItemFactory::createEventFromCdbXml($cdbXmlNamespaceUri, $cdbXml);

        $factory = new ImageCollectionFactory();

        $images = $factory->fromUdb2Media($this->getMedia($event));

        $this->assertEquals($expectedImages, $images);
    }
    /**
     * @test
     */
    public function it_should_set_the_first_main_udb2_image_as_main_collection_image()
    {
        $image = new Image(
            UUID::fromNative('bc1dfebe-ca8b-5390-a946-8b43fa9bd609'),
            MIMEType::fromNative('image/jpeg'),
            new StringLiteral('¯\_(ツ)_/¯'),
            new StringLiteral('Karbido Ensemble'),
            Url::fromNative('http://media.uitdatabank.be/20140418/edb05b66-611b-4829-b8f6-bb31c285ec89.jpg')
        );
        $expectedImages = (new ImageCollection())->withMain($image);
        $cdbXml = file_get_contents(__DIR__ . '/samples/event_with_main_imageweb.xml');
        $cdbXmlNamespaceUri = \CultureFeed_Cdb_Xml::namespaceUriForVersion('3.3');

        $event = EventItemFactory::createEventFromCdbXml($cdbXmlNamespaceUri, $cdbXml);

        $factory = new ImageCollectionFactory();

        $images = $factory->fromUdb2Media($this->getMedia($event));

        $this->assertEquals($expectedImages, $images);
    }

    /**
     * @test
     */
    public function it_should_identify_images_using_a_configurable_regex()
    {
        $regex = 'https?:\/\/udb-silex\.dev\/web\/media\/(?<uuid>[0-9A-Fa-f]{8}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{12})\.jpg';

        $image = new Image(
            UUID::fromNative('edb05b66-611b-4829-b8f6-bb31c285ec89'),
            MIMEType::fromNative('image/jpeg'),
            new StringLiteral('my best selfie'),
            new StringLiteral('my dog'),
            Url::fromNative('http://udb-silex.dev/web/media/edb05b66-611b-4829-b8f6-bb31c285ec89.jpg')
        );
        $expectedImages = (new ImageCollection())->withMain($image);
        $cdbXml = file_get_contents(__DIR__ . '/samples/event_with_udb3_image.xml');
        $cdbXmlNamespaceUri = \CultureFeed_Cdb_Xml::namespaceUriForVersion('3.3');

        $event = EventItemFactory::createEventFromCdbXml($cdbXmlNamespaceUri, $cdbXml);

        $factory = (new ImageCollectionFactory())->withUuidRegex($regex);

        $images = $factory->fromUdb2Media($this->getMedia($event));

        $this->assertEquals($expectedImages, $images);
    }

    /**
     * @param CultureFeed_Cdb_Item_Base $cdbItem
     * @return CultureFeed_Cdb_Data_Media
     */
    private function getMedia(CultureFeed_Cdb_Item_Base $cdbItem)
    {
        $details = $cdbItem->getDetails();
        $details->rewind();

        return $details
            ->current()
            ->getMedia();
    }
}
