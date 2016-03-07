<?php

namespace CultuurNet\UDB3\UDB2\Media;

use CultureFeed_Cdb_Data_Media;
use CultureFeed_Cdb_Item_Base;
use CultuurNet\UDB3\Cdb\EventItemFactory;
use CultuurNet\UDB3\Media\Image;
use CultuurNet\UDB3\Media\Properties\MIMEType;
use CultuurNet\UDB3\Offer\Commands\OfferCommandFactoryInterface;
use ValueObjects\Identity\UUID;
use ValueObjects\String\String as StringLiteral;
use ValueObjects\Web\Url;

trait EditImageTestTrait
{
    /**
     * @var OfferCommandFactoryInterface
     */
    protected $commandFactory;

    /**
     * @test
     */
    public function it_adds_a_media_file_when_adding_an_image()
    {
        $id = 'd53c2bc9-8f0e-4c9a-8457-77e8b3cab3d1';
        $item = $this->createItem($id, 'eventrepositorytest_event.xml');
        $this->repository->save($item);

        $image = new Image(
            new UUID('de305d54-75b4-431b-adb2-eb6b9e546014'),
            new MIMEType('image/png'),
            new StringLiteral('sexy ladies without clothes'),
            new StringLiteral('Bart Ramakers'),
            Url::fromNative('http://foo.bar/media/de305d54-75b4-431b-adb2-eb6b9e546014.png')
        );

        $udb2Event = EventItemFactory::createEventFromCdbXml(
            self::NS,
            file_get_contents(__DIR__ . '/../samples/event.xml')
        );

        $this->entryAPI->expects($this->once())
            ->method('getEvent')
            ->with($id)
            ->willReturn($udb2Event);

        $this->entryAPI->expects($this->once())
            ->method('updateEvent')
            ->with($this->callback(function ($cdbItem) {
                $media = $this->getCdbItemMedia($cdbItem);

                $newFiles = [];
                foreach ($media as $key => $file) {
                    if ($file->getHLink() === 'http://foo.bar/media/de305d54-75b4-431b-adb2-eb6b9e546014.png') {
                        $newFiles[] = $file;
                    }
                };

                return !empty($newFiles);
            }));

        $item->addImage($image);
        $this->repository->syncBackOn();
        $this->repository->save($item);
    }

    /**
     * @test
     */
    public function it_should_make_an_image_main_when_added_to_an_item_without_existing_ones()
    {
        $id = 'd53c2bc9-8f0e-4c9a-8457-77e8b3cab3d1';
        $item = $this->createItem($id, 'eventrepositorytest_event.xml');
        $this->repository->save($item);

        $image = new Image(
            new UUID('de305d54-75b4-431b-adb2-eb6b9e546014'),
            new MIMEType('image/png'),
            new StringLiteral('sexy ladies without clothes'),
            new StringLiteral('Bart Ramakers'),
            Url::fromNative('http://foo.bar/media/de305d54-75b4-431b-adb2-eb6b9e546014.png')
        );

        $udb2Event = EventItemFactory::createEventFromCdbXml(
            self::NS,
            file_get_contents(__DIR__ . '/../samples/event-without-images.xml')
        );

        $this->entryAPI->expects($this->once())
            ->method('getEvent')
            ->with($id)
            ->willReturn($udb2Event);

        $this->entryAPI->expects($this->once())
            ->method('updateEvent')
            ->with($this->callback(function ($cdbItem) {
                $media = $this->getCdbItemMedia($cdbItem);

                $newFiles = [];
                foreach ($media as $key => $file) {
                    if ($file->getHLink() === 'http://foo.bar/media/de305d54-75b4-431b-adb2-eb6b9e546014.png') {
                        $newFiles[] = $file;
                        $this->assertEquals(
                            \CultureFeed_Cdb_Data_File::MEDIA_TYPE_PHOTO,
                            $file->getMediaType()
                        );
                    }
                };

                return !empty($newFiles);
            }));

        $item->addImage($image);
        $this->repository->syncBackOn();
        $this->repository->save($item);
    }

    /**
     * @test
     */
    public function it_deletes_a_media_file_when_removing_an_image()
    {
        $itemId = 'd53c2bc9-8f0e-4c9a-8457-77e8b3cab3d1';
        $item = $this->createItem($itemId, 'eventrepositorytest_event.xml');
        $image = new Image(
            new UUID('9554d6f6-bed1-4303-8d42-3fcec4601e0e'),
            new MIMEType('image/jpg'),
            new StringLiteral('duckfaceplant'),
            new StringLiteral('Karbido Ensemble'),
            Url::fromNative('http://foo.bar/media/9554d6f6-bed1-4303-8d42-3fcec4601e0e.jpg')
        );
        $item->addImage($image);
        $this->repository->save($item);

        $udb2Event = EventItemFactory::createEventFromCdbXml(
            self::NS,
            file_get_contents(__DIR__ . '/../samples/event.xml')
        );

        $this->entryAPI->expects($this->once())
            ->method('getEvent')
            ->with($itemId)
            ->willReturn($udb2Event);

        $this->entryAPI
            ->expects($this->once())
            ->method('updateEvent')
            ->with($this->callback(function ($cdbItem) {
                $media = $this->getCdbItemMedia($cdbItem);

                $removedFiles = [];
                foreach ($media as $key => $file) {
                    if ($file->getHLink() === 'http://85.255.197.172/images/20140108/9554d6f6-bed1-4303-8d42-3fcec4601e0e.jpg') {
                        $removedFiles[] = $file;
                    }
                };

                return empty($removedFiles);
            }));

        $item->removeImage($image);
        $this->repository->syncBackOn();
        $this->repository->save($item);
    }

    /**
     * @test
     */
    public function it_should_make_the_oldest_image_main_when_deleting_the_current_main_image()
    {
        $itemId = 'd53c2bc9-8f0e-4c9a-8457-77e8b3cab3d1';
        $newImage = new Image(
            new UUID('9554d6f6-bed1-4303-8d42-3fcec4eee000'),
            new MIMEType('image/jpg'),
            new StringLiteral('duckfaceplant'),
            new StringLiteral('Karbido Ensemble'),
            Url::fromNative('http://foo.bar/media/9554d6f6-bed1-4303-8d42-3fcec4eee000.jpg')
        );
        $olderImage = new Image(
            new UUID('9554d6f6-bed1-4303-8d42-3fcec4601e0e'),
            new MIMEType('image/jpg'),
            new StringLiteral('Beep Boop'),
            new StringLiteral('Noo Idee'),
            Url::fromNative('http://foo.bar/media/9554d6f6-bed1-4303-8d42-3fcec4601e0e.jpg')
        );

        $udb2Event = EventItemFactory::createEventFromCdbXml(
            self::NS,
            file_get_contents(__DIR__ . '/../samples/event-with-existing-images.xml')
        );

        $item = $this->createItem($itemId, 'eventrepositorytest_event.xml');
        $item->addImage($olderImage);
        $item->addImage($newImage);
        $this->repository->save($item);

        $this->entryAPI->expects($this->once())
            ->method('getEvent')
            ->with($itemId)
            ->willReturn($udb2Event);

        $this->entryAPI
            ->expects($this->any())
            ->method('updateEvent')
            ->with($this->callback(function ($cdbItem) {
                $media = $this->getCdbItemMedia($cdbItem);

                $newMainImage = false;
                foreach ($media as $key => $file) {
                    if ($file->getHLink() === 'http://foo.bar/media/9554d6f6-bed1-4303-8d42-3fcec4eee000.jpg') {
                        $newMainImage = ($file->getMediaType() === \CultureFeed_Cdb_Data_File::MEDIA_TYPE_PHOTO);
                    }
                };

                return $newMainImage;
            }));

        $item->removeImage($olderImage);
        $this->repository->syncBackOn();
        $this->repository->save($item);
    }

    /**
     * @test
     */
    public function it_updates_the_event_media_object_property_when_updating_an_image()
    {
        $itemId = 'd53c2bc9-8f0e-4c9a-8457-77e8b3cab3d1';

        $image = new Image(
            new UUID('9554d6f6-bed1-4303-8d42-3fcec4601e0e'),
            new MIMEType('image/jpg'),
            new StringLiteral('duckfaceplant'),
            new StringLiteral('Karbido Ensemble'),
            Url::fromNative('http://foo.bar/media/9554d6f6-bed1-4303-8d42-3fcec4601e0e.jpg')
        );

        $item = $this->createItem($itemId, 'eventrepositorytest_event.xml');
        $item->addImage($image);
        $this->repository->save($item);

        $existingEvent = EventItemFactory::createEventFromCdbXml(
            self::NS,
            file_get_contents(__DIR__ . '/../samples/event.xml')
        );

        $updateCommand = $this->commandFactory->createUpdateImageCommand(
            $itemId,
            new UUID('9554d6f6-bed1-4303-8d42-3fcec4601e0e'),
            new StringLiteral('nieuwe beschrijving'),
            new StringLiteral('nieuwe auteur')
        );

        $this->entryAPI->expects($this->once())
            ->method('getEvent')
            ->with($itemId)
            ->willReturn($existingEvent);

        $this->entryAPI
            ->expects($this->once())
            ->method('updateEvent')
            ->with($this->callback(function ($cdbItem) {
                $media = $this->getCdbItemMedia($cdbItem);

                $outdatedFiles = [];
                foreach ($media as $key => $file) {
                    if ($file->getHLink() === 'http://85.255.197.172/images/20140108/9554d6f6-bed1-4303-8d42-3fcec4601e0e.jpg') {
                        if ($file->getTitle() !== 'nieuwe beschrijving' ||
                            $file->getCopyright() !== 'nieuwe auteur') {
                            $outdatedFiles[] = $file;
                        }
                    }
                };

                return empty($outdatedFiles);
            }));

        $this->repository->syncBackOn();
        $item->updateImage($updateCommand);
        $this->repository->save($item);
    }

    /**
     * @test
     */
    public function it_should_update_the_image_property_when_selecting_a_main_image()
    {
        $itemId = 'd53c2bc9-8f0e-4c9a-8457-77e8b3cab3d1';

        $newImage = new Image(
            new UUID('9554d6f6-bed1-4303-8d42-3fcec4eee000'),
            new MIMEType('image/jpg'),
            new StringLiteral('duckfaceplant'),
            new StringLiteral('Karbido Ensemble'),
            Url::fromNative('http://foo.bar/media/9554d6f6-bed1-4303-8d42-3fcec4eee000.jpg')
        );
        $image = new Image(
            new UUID('9554d6f6-bed1-4303-8d42-3fcec4601e0e'),
            new MIMEType('image/jpg'),
            new StringLiteral('Beep Boop'),
            new StringLiteral('Noo Idee'),
            Url::fromNative('http://foo.bar/media/9554d6f6-bed1-4303-8d42-3fcec4601e0e.jpg')
        );

        $item = $this->createItem($itemId, 'eventrepositorytest_event.xml');
        $item->addImage($image);
        $item->addImage($newImage);
        $this->repository->save($item);

        $existingEvent = EventItemFactory::createEventFromCdbXml(
            self::NS,
            file_get_contents(__DIR__ . '/../samples/event-with-existing-images.xml')
        );

        $this->entryAPI->expects($this->once())
            ->method('getEvent')
            ->with($itemId)
            ->willReturn($existingEvent);

        $this->entryAPI
            ->expects($this->once())
            ->method('updateEvent')
            ->with($this->callback(function ($cdbItem) {
                $media = $this->getCdbItemMedia($cdbItem);

                $newMainImageSet = false;
                $oldMainImageDowngraded = false;
                foreach ($media as $key => $file) {
                    if ($file->getHLink() === 'http://foo.bar/media/9554d6f6-bed1-4303-8d42-3fcec4eee000.jpg') {
                        $newMainImageSet = ($file->getMediaType() === \CultureFeed_Cdb_Data_File::MEDIA_TYPE_PHOTO);
                    }

                    if ($file->getHLink() === 'http://foo.bar/media/9554d6f6-bed1-4303-8d42-3fcec4601e0e.jpg') {
                        $oldMainImageDowngraded = ($file->getMediaType() === \CultureFeed_Cdb_Data_File::MEDIA_TYPE_IMAGEWEB);
                    }
                };

                return $newMainImageSet && $oldMainImageDowngraded;
            }));

        $this->repository->syncBackOn();
        $item->selectMainImage($newImage);
        $this->repository->save($item);
    }

    /**
     * @param CultureFeed_Cdb_Item_Base $cdbItem
     * @return CultureFeed_Cdb_Data_Media
     */
    private function getCdbItemMedia($cdbItem)
    {
        $details = $cdbItem->getDetails();
        $details->rewind();
        $media = $details->current()->getMedia();
        $media->rewind();

        return $media;
    }
}