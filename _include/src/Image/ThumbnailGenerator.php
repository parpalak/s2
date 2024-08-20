<?php /** @noinspection HtmlUnknownTarget */
/**
 * @copyright 2023-2024 Roman Parpalak
 * @license   https://opensource.org/license/MIT MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Image;

use S2\Cms\Queue\QueueHandlerInterface;
use S2\Cms\Queue\QueuePublisher;
use s2_extensions\s2_search\Rose\CustomExtractor;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class ThumbnailGenerator implements QueueHandlerInterface
{
    private const CACHE_SUBDIRECTORY = '/cache/';

    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly QueuePublisher           $publisher,
        private readonly string                   $cacheUrlPrefix,
        private string                            $cacheFilesystemPrefix
    ) {
        $this->cacheFilesystemPrefix = rtrim($cacheFilesystemPrefix, '/');
    }

    /**
     * @param string $src            URL of the image
     * @param string $originalWidth  Attr content (may not be valid)
     * @param string $originalHeight Attr content (may not be valid)
     * @param int    $maxWidth       Limit for the thumbnail
     * @param int    $maxHeight      Limit for the thumbnail
     *
     * @return string HTML Markup
     */
    public function getThumbnailHtml(string $src, string $originalWidth, string $originalHeight, int $maxWidth, int $maxHeight): string
    {
        $event = new ThumbnailGenerateEvent($src, $originalWidth, $originalHeight, $maxWidth, $maxHeight);
        $this->eventDispatcher->dispatch($event);
        if (($result = $event->getResult()) !== null) {
            return $result;
        }

        try {
            [$newWidth, $newHeight] = self::reduceSize($originalWidth, $originalHeight, $maxWidth, $maxHeight);
            $src = $this->getThumbnailSrc($src, 2 * $newWidth, 2 * $newHeight); // 2 for retina

            return sprintf('<img src="%s" width="%s" height="%s" alt="">', $src, $newWidth, $newHeight);
        } catch (\InvalidArgumentException $e) {
            return sprintf('<img src="%s" alt="">', $src);
        }
    }

    /**
     * TODO move to a separate class like ImageReducer
     * Get slightly reduced image for recommendations.
     */
    public function getReducedImg(ImgDto $img): ImgDto
    {
        $src = $img->getSrc();
        if (str_starts_with($src, CustomExtractor::YOUTUBE_PROTOCOL)) {
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
        $canBeHandled = str_starts_with($src, $this->cacheUrlPrefix . '/');
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

    public static function createImageFromFile(string $inputFilename): \GdImage
    {
        $imageInfo = getimagesize($inputFilename);

        switch ($imageInfo['mime']) {
            case 'image/gif':
                if (imagetypes() & IMG_GIF) {
                    return imagecreatefromgif($inputFilename);
                }
                throw new \RuntimeException('GIF images are not supported');

            case 'image/jpeg':
                if (imagetypes() & IMG_JPG) {
                    return imagecreatefromjpeg($inputFilename);
                }
                throw new \RuntimeException('JPEG images are not supported');

            case 'image/png':
                if (imagetypes() & IMG_PNG) {
                    return imagecreatefrompng($inputFilename);
                }
                throw new \RuntimeException('PNG images are not supported');

            case 'image/wbmp':
                if (imagetypes() & IMG_WBMP) {
                    return imagecreatefromwbmp($inputFilename);
                }
                throw new \RuntimeException('WBMP images are not supported');
        }

        throw new \RuntimeException($imageInfo['mime'] . ' images are not supported');
    }

    public static function reduceSize(string $width, string $height, int $maxWidth, int $maxHeight, float $zoom = 1.0): array
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
        $image = self::createImageFromFile($inputFilename);

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
