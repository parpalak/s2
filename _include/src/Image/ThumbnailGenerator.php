<?php declare(strict_types=1);
/**
 * @copyright (C) 2023 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */

namespace S2\Cms\Image;

use S2\Cms\Queue\QueueHandlerInterface;
use S2\Cms\Queue\QueuePublisher;
use S2\Cms\Rose\CustomExtractor;

class ThumbnailGenerator implements QueueHandlerInterface
{
    private const CACHE_SUBDIRECTORY = '/cache/';

    private QueuePublisher $publisher;
    private string $cacheUrlPrefix;
    private string $cacheFilesystemPrefix;

    public function __construct(QueuePublisher $publisher, string $cacheUrlPrefix, string $cacheFilesystemPrefix)
    {
        $this->publisher             = $publisher;
        $this->cacheUrlPrefix        = $cacheUrlPrefix;
        $this->cacheFilesystemPrefix = $cacheFilesystemPrefix;
    }

    /**
     * @param string $src URL of the image
     * @param string $originalWidth Attr content (may not be valid)
     * @param string $originalHeight Attr content (may not be valid)
     * @param int    $maxWidth Limit for the thumbnail
     * @param int    $maxHeight Limit for the thumbnail
     *
     * @return string HTML Markup
     */
    public function getThumbnailHtml(string $src, string $originalWidth, string $originalHeight, int $maxWidth, int $maxHeight): string
    {
        if (strpos($src, CustomExtractor::YOUTUBE_PROTOCOL) === 0) {
            $src = 'https://img.youtube.com/vi/' . substr($src, \strlen(CustomExtractor::YOUTUBE_PROTOCOL)) . '/mqdefault.jpg';

            $sizeArray = $this->reduceSize('320', '180', $maxWidth, $maxHeight);

            return sprintf('<span class="video-thumbnail"><img src="%s" width="%s" height="%s"></span>', $src, ...$sizeArray);
        }

        try {
            [$newWidth, $newHeight] = $this->reduceSize($originalWidth, $originalHeight, $maxWidth, $maxHeight);
            $src = $this->getThumbnailSrc($src, 2*$newWidth, 2*$newHeight); // 2 for retina

            return sprintf('<img src="%s" width="%s" height="%s" alt="">', $src, $newWidth, $newHeight);
        } catch (\InvalidArgumentException $e) {
            return sprintf('<img src="%s" alt="">', $src);
        }
    }

    /**
     * Get slightly reduced image for recommendations.
     */
    public function getReducedImg(ImgDto $img): ImgDto
    {
        $src = $img->getSrc();
        if (strpos($src, CustomExtractor::YOUTUBE_PROTOCOL) === 0) {
            return (new ImgDto(
                'https://img.youtube.com/vi/' . substr($src, \strlen(CustomExtractor::YOUTUBE_PROTOCOL)) . '/hq720.jpg',
                640,
                360,
                $img->getClass()
            ))/*->addSrc('https://img.youtube.com/vi/' . substr($src, \strlen(CustomExtractor::YOUTUBE_PROTOCOL)) . '/hq720.jpg')*/ ;
        }

        return $img;
    }

    /**
     * {@inheritdoc}
     */
    public function handle(string $id, string $code, array $payload): bool
    {
        if ($code !== 'thumbnail') {
            return false;
        }

        [$src, $width, $height] = $payload;

        // Check if $src file is in the pictures dir
        $canBeHandled = (strpos($src, $this->cacheUrlPrefix . '/') === 0);
        if ($canBeHandled) {
            $filename = $this->cacheFilesystemPrefix . self::getCachedFilename($id);
            $dirname  = \dirname($filename);
            if (!is_dir($dirname)) {
                if (!mkdir($dirname, 0777, true) && !is_dir($dirname)) {
                    throw new \RuntimeException(sprintf('Directory "%s" was not created', $dirname));
                }
                chmod($dirname, 0777);
            }
            self::makeThumbnail(
                $this->cacheFilesystemPrefix . substr($src, \strlen($this->cacheUrlPrefix)),
                $filename,
                $width,
                $height
            );
        }

        return $canBeHandled;
    }

    protected function reduceSize(string $width, string $height, int $maxWidth, int $maxHeight, float $zoom = 1.0): array
    {
        if (!is_numeric($height) || !is_numeric($width)) {
            throw new \InvalidArgumentException();
        }

        if ($maxWidth * $height > $maxHeight * $width) {
            $ratio = $zoom * $maxHeight / $height;
        } else {
            $ratio = $zoom * $maxWidth / $width;
        }
        if ($ratio > 1) {
            $ratio = 1;
        }

        return [(int)($width * $ratio), (int)($height * $ratio)];
    }

    private function getThumbnailSrc(string $src, int $newWidth, int $newHeight): string
    {
        $args = \func_get_args();
        $hash = md5(serialize($args));
        if (file_exists($this->cacheFilesystemPrefix . self::getCachedFilename($hash))) {
            return $this->cacheUrlPrefix . self::getCachedFilename($hash);
        }

        // No cache. Add a job to queue and fallback to original image
        $this->publisher->publish($hash, 'thumbnail', $args);

        return $src;
    }

    private static function makeThumbnail(string $inputFilename, string $outputFilename, int $width, int $height): void
    {
        $imageInfo = getimagesize($inputFilename);

        switch ($imageInfo['mime']) {
            case 'image/gif':
                if (imagetypes() & IMG_GIF) {
                    $image = imagecreatefromgif($inputFilename);
                } else {
                    throw new \RuntimeException('GIF images are not supported');
                }
                break;
            case 'image/jpeg':
                if (imagetypes() & IMG_JPG) {
                    $image = imagecreatefromjpeg($inputFilename);
                } else {
                    throw new \RuntimeException('JPEG images are not supported');
                }
                break;
            case 'image/png':
                if (imagetypes() & IMG_PNG) {
                    $image = imagecreatefrompng($inputFilename);
                } else {
                    throw new \RuntimeException('PNG images are not supported');
                }
                break;
            case 'image/wbmp':
                if (imagetypes() & IMG_WBMP) {
                    $image = imagecreatefromwbmp($inputFilename);
                } else {
                    throw new \RuntimeException('WBMP images are not supported');
                }
                break;
            default:
                throw new \RuntimeException($imageInfo['mime'] . ' images are not supported');
        }

        $inputWidth  = imagesx($image);
        $inputHeight = imagesy($image);
        $thumbnail   = imagecreatetruecolor($width, $height);

        $white = imagecolorallocate($thumbnail, 255, 255, 255);
        imagefilledrectangle($thumbnail, 0, 0, $width, $height, $white);
        imagecolortransparent($thumbnail, $white);

        imagecopyresampled($thumbnail, $image, 0, 0, 0, 0, $width, $height, $inputWidth, $inputHeight);

        imagejpeg($thumbnail, $outputFilename, 90);

        imagedestroy($image);
        imagedestroy($thumbnail);
    }

    private static function getCachedFilename(string $hash): string
    {
        return self::CACHE_SUBDIRECTORY . substr($hash, 0, 2) . '/' . substr($hash, 2, 2) . '/' . substr($hash, 4) . '.jpg';
    }
}
