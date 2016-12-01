<?php

namespace CultuurNet\UDB3\UDB2\Media;

use CultureFeed_Cdb_Data_File;

class MediaTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @test
     */
    public function it_should_normalize_uris()
    {
        $file = new CultureFeed_Cdb_Data_File();
        $file->setHLink('http://스타벅스코리아.com/to/the/sky/');

        $similarFile = new CultureFeed_Cdb_Data_File();
        $similarFile->setHLink('https://xn--oy2b35ckwhba574atvuzkc.com/path/../to/the/./sky/');

        $media = new Media($file);
        $similarMedia =  new Media($file);

        $this->assertEquals($media->normalizeUri(), $similarMedia->normalizeUri());
    }
}
