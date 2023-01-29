<?php declare(strict_types=1);
/**
 * @copyright (C) 2023 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */

namespace S2\Cms\Image;

use S2\Cms\Rose\CustomExtractor;
use S2\Rose\Entity\Metadata\Img;

class ThumbnailGenerator
{
    public function getImgHtml(Img $image, int $maxWidth, int $maxHeight): string
    {
        $src = $image->getSrc();
        if (strpos($image->getSrc(), CustomExtractor::YOUTUBE_PROTOCOL) === 0) {
            $src = 'https://img.youtube.com/vi/' . substr($src, \strlen(CustomExtractor::YOUTUBE_PROTOCOL)) . '/mqdefault.jpg';

            $sizeArray = $this->detectSize('320', '180', $maxWidth, $maxHeight);

            return sprintf('<span class="video-thumbnail"><img src="%s" width="%s" height="%s"></span>', $src, ...$sizeArray);
        }

        $sizeArray = $this->detectSize($image->getWidth(), $image->getHeight(), $maxWidth, $maxHeight);

        return sprintf('<img src="%s" width="%s" height="%s">', $src, ...$sizeArray);
    }

    protected function detectSize(string $width, string $height, int $maxWidth, int $maxHeight): array
    {
        if (!is_numeric($height) || !is_numeric($width)) {
            return ['', ''];
        }

        if ($maxWidth * $height > $maxHeight * $width) {
            $ratio = $maxHeight / $height;
        } else {
            $ratio = $maxWidth / $width;
        }
        if ($ratio > 1) {
            $ratio = 1;
        }

        return [(int)($width * $ratio), (int)($height * $ratio)];
    }

    public function getImgThumbnail(ImgDto $img): ImgDto
    {
        $src = $img->getSrc();
        if (strpos($src, CustomExtractor::YOUTUBE_PROTOCOL) === 0) {
            return (new ImgDto(
                'https://img.youtube.com/vi/' . substr($src, \strlen(CustomExtractor::YOUTUBE_PROTOCOL)) . '/hq720.jpg',
                640,
                360,
                $img->getClass()
            ))/*->addSrc('https://img.youtube.com/vi/' . substr($src, \strlen(CustomExtractor::YOUTUBE_PROTOCOL)) . '/hq720.jpg')*/;
        }

        return $img;
    }
}
