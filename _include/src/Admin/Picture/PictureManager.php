<?php
/**
 * @copyright 2007-2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Admin\Picture;

use S2\AdminYard\Config\FieldConfig;
use S2\AdminYard\Form\FormParams;
use S2\AdminYard\SettingStorage\SettingStorageInterface;
use S2\AdminYard\Translator;
use S2\Cms\AdminYard\CustomTemplateRenderer;
use S2\Cms\Framework\Exception\AccessDeniedException;
use S2\Cms\Image\ThumbnailGenerator;
use S2\Cms\Model\PermissionChecker;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

class PictureManager
{
    private const EXTENSIONS_FOR_PREVIEW = ['gif', 'bmp', 'jpg', 'jpeg', 'png'];

    public function __construct(
        private readonly Translator              $translator,
        private readonly CustomTemplateRenderer  $customTemplateRenderer,
        private readonly PermissionChecker       $permissionChecker,
        private readonly SettingStorageInterface $settingStorage,
        private readonly string                  $basePath,
        private string                           $imageDir, // filesystem
        private readonly string                  $allowedExtensions,
    ) {
        $this->imageDir = rtrim($imageDir, '/');
    }

    public function getThumbnailResponse(string $file, $maxSize = 100, $maxZoom = 2.0): Response
    {
        $filename = $this->imageDir . $file;

        $image = ThumbnailGenerator::createImageFromFile($filename);
        $sx    = imagesx($image);
        $sy    = imagesy($image);

        $originalSize = max($sx, $sy);
        if ($originalSize < 1) {
            throw new \RuntimeException('Image size is 0');
        }

        $zoom = min(1.0 * $maxSize / $originalSize, $maxZoom);

        $thumbnail = imagecreatetruecolor($maxSize, $maxSize);

        imagealphablending($thumbnail, false);
        imagesavealpha($thumbnail, true);
        $white = imagecolorallocatealpha($thumbnail, 255, 255, 255, 127);
        imagefilledrectangle($thumbnail, 0, 0, $maxSize, $maxSize, $white);
        imagecolortransparent($thumbnail, $white);

        $dst_width  = (int)($sx * $zoom);
        $dst_height = (int)($sy * $zoom);
        $dst_x      = (int)(max(0, $maxSize - $dst_width) / 2);
        $dst_y      = max(0, $maxSize - $dst_height);
        // TODO chess-like background for transparent images
        // imagefilledrectangle($thumbnail, $dst_x, $dst_y, $dst_x + $dst_width, $dst_y + $dst_height, imagecolorallocatealpha($thumbnail, 255, 0, 0, 0));
        // imagealphablending($thumbnail, true);
        imagecopyresampled($thumbnail, $image, $dst_x, $dst_y, 0, 0, $dst_width, $dst_height, $sx, $sy);

        ob_start();
        if (\function_exists('imageavif')) {
            $contentType = 'image/avif';
            imageavif($thumbnail);
        } else {
            $contentType = 'image/jpeg';
            imagejpeg($thumbnail);
        }
        $content = ob_get_clean();

        imagedestroy($image);
        imagedestroy($thumbnail);

        return new Response($content, Response::HTTP_OK, ['Content-Type' => $contentType]);
    }

    public function getDirContentRecursive(string $dir): array
    {
        if (!($dirHandle = opendir($this->imageDir . $dir))) {
            throw new \RuntimeException($this->translator->trans('Directory not open', ['{{ dir }}' => $this->imageDir . $dir]), Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $output = [];
        $dirs   = [];

        while (($item = readdir($dirHandle)) !== false) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            if (is_dir($this->imageDir . $dir . '/' . $item)) {
                $dirs[] = $item;
            }
        }

        closedir($dirHandle);

        sort($dirs);

        foreach ($dirs as $item) {
            $output[] = [
                'data'     => $item,
                'attr'     => [
                    'data-path'       => $dir . '/' . $item,
                    'data-csrf-token' => $this->getFolderCsrfToken($dir . '/' . $item),
                ],
                'children' => $this->getDirContentRecursive($dir . '/' . $item)
            ];
        }

        if ($dir === '') {
            $output = [
                'data'     => $this->translator->trans('Pictures'),
                'attr'     => [
                    'id'              => 'node_1',
                    'data-path'       => '',
                    'data-csrf-token' => $this->getFolderCsrfToken(''),
                ],
                'children' => $output,
            ];
        }

        return $output;
    }


    public function createSubfolder(string $path, string $name): string
    {
        if (file_exists($this->imageDir . $path . '/' . $name)) {
            $i = 1;
            while (file_exists($this->imageDir . $path . '/' . $name . $i)) {
                $i++;
            }
            $name .= $i;
        }

        if (!mkdir($concurrentDirectory = $this->imageDir . $path . '/' . $name) && !is_dir($concurrentDirectory)) {
            throw new \RuntimeException($this->translator->trans('Error creating folder', ['{{ dir }}' => $this->imageDir . $path . '/' . $name]), Response::HTTP_SERVICE_UNAVAILABLE);
        }

        chmod($this->imageDir . $path . '/' . $name, 0777);

        return $name;
    }

    public function deleteFolder(string $dir, bool $deleteRoot = true): void
    {
        $fullDir = $this->imageDir . $dir;
        if (!$dirHandle = @opendir($fullDir)) {
            return;
        }

        while (false !== ($item = readdir($dirHandle))) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            if (is_dir($fullDir . '/' . $item) || !@unlink($fullDir . '/' . $item)) {
                $this->deleteFolder($dir . '/' . $item);
            }
        }

        closedir($dirHandle);

        if ($deleteRoot) {
            @rmdir($fullDir);
        }
    }

    public function deleteFile(string $path): void
    {
        if (file_exists($this->imageDir . $path)) {
            @unlink($this->imageDir . $path);
        }
    }

    public function renameFolder(string $path, string $newName): string
    {
        $parentPath = self::s2_dirname($path);

        $newFullName = $this->imageDir . $parentPath . '/' . $newName;
        if (file_exists($newFullName)) {
            throw new \RuntimeException($this->translator->trans('Rename file exists', ['{{ dir }}' => $newName]), Response::HTTP_CONFLICT);
        }

        $oldFullName = $this->imageDir . $path;
        if (!is_dir($oldFullName)) {
            throw new \RuntimeException($this->translator->trans('Directory not found', ['{{ dir }}' => $oldFullName]), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (!rename($oldFullName, $newFullName)) {
            throw new \RuntimeException($this->translator->trans('Rename error'), Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return $parentPath . '/' . $newName;
    }

    public function renameFile(string $path, string $newName): string
    {
        $parentPath = self::s2_dirname($path);

        $newFullPath = $this->imageDir . $parentPath . '/' . $newName;
        if (file_exists($newFullPath)) {
            throw new \RuntimeException($this->translator->trans('Rename file exists', ['{{ dir }}' => $newName]), Response::HTTP_CONFLICT);
        }

        $oldFullPath = $this->imageDir . $path;
        // TODO check if $oldFullPath exists
        if (!rename($oldFullPath, $newFullPath)) {
            throw new \RuntimeException($this->translator->trans('Rename error'), Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return $newName;
    }

    public function moveFolder(string $sourcePath, string $destPath): string
    {
        $fullSourcePath = $this->imageDir . $sourcePath;
        $fullDestPath   = $this->imageDir . $destPath . '/' . self::s2_basename($sourcePath);

        if (file_exists($fullDestPath)) {
            throw new \RuntimeException($this->translator->trans('Move file exists', ['{{ dir }}' => $fullDestPath]), Response::HTTP_CONFLICT);
        }

        // TODO check if $fullSourcePath exists
        if (!rename($fullSourcePath, $fullDestPath)) {
            throw new \RuntimeException($this->translator->trans('Move error'), Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return $destPath . '/' . self::s2_basename($sourcePath);
    }

    public function moveFiles(string $sourcePath, string $destPath, array $fileNames): void
    {
        $skippedFiles = [];
        foreach ($fileNames as $fileName) {
            $fileName       = self::s2_basename($fileName);
            $fullSourcePath = $this->imageDir . $sourcePath . '/' . $fileName;
            $fullDestPath   = $this->imageDir . $destPath . '/' . $fileName;

            if (file_exists($fullDestPath)) {
                $skippedFiles[] = $fileName;
                continue;
            }

            if (!rename($fullSourcePath, $fullDestPath)) {
                throw new \RuntimeException($this->translator->trans('Move error'), Response::HTTP_SERVICE_UNAVAILABLE);
            }
        }

        if (\count($skippedFiles) > 0) {
            throw new \RuntimeException($this->translator->trans('Move file exists', ['{{ dir }}' => implode(', ', $skippedFiles)]), Response::HTTP_CONFLICT);
        }
    }

    public function getFiles(string $dir): array
    {
        $displayPreview = \function_exists('imagetypes');

        clearstatcache();

        if (!is_dir($this->imageDir . $dir)) {
            return ['message' => 'Invalid directory'];
        }

        if (!($dirHandle = opendir($this->imageDir . $dir))) {
            return ['message' => '<p>' . $this->translator->trans('Directory not open', ['{{ dir }}' => $this->imageDir . $dir]) . '</p>'];
        }

        $files = [];
        while (($item = readdir($dirHandle)) !== false) {
            if ($item === '.' || $item === '..' || is_dir($this->imageDir . $dir . '/' . $item)) {
                continue;
            }
            $files[] = $item;
        }

        closedir($dirHandle);

        sort($files);

        $output = [];
        foreach ($files as $item) {
            $bits = $dimensions = '';

            if (str_contains($item, '.') && \in_array(pathinfo($item, PATHINFO_EXTENSION), self::EXTENSIONS_FOR_PREVIEW, true)) {
                $imageInfo = getImageSize($this->imageDir . $dir . '/' . $item);
                if ($imageInfo !== false) {
                    $dimensions = $imageInfo[0] . '*' . $imageInfo[1];
                    $bits       = ($imageInfo['bits'] ?? 0) * ($imageInfo['channels'] ?? 1);
                }
            }

            $output[] = [
                'data' => [
                    'title' => $item,
                    'icon'  => $displayPreview && $dimensions ? $this->basePath . '/_admin/ajax.php?action=preview&file=' . urlencode($dir . '/' . $item) . '&nocache=' . filemtime($this->imageDir . $dir . '/' . $item) : 'no-preview'
                ],
                'attr' => [
                    'data-fname' => $item,
                    'data-dim'   => $dimensions,
                    'data-bits'  => $bits,
                    'data-fsize' => $this->customTemplateRenderer->friendlyFilesize(filesize($this->imageDir . $dir . '/' . $item))
                ]
            ];
        }

        return \count($output) > 0 ? $output : ['message' => $this->translator->trans('Empty directory')];
    }

    public function processUploadedFile(UploadedFile $uploadedFile, string $path, bool $createDir): string
    {
        $filename = $uploadedFile->getClientOriginalName();
        if ($uploadedFile->getError() !== UPLOAD_ERR_OK) {
            $errorMessage = $this->translator->trans('Upload error ' . $uploadedFile->getError());
            $error        = $filename ? sprintf($this->translator->trans('Upload file error'), $filename, $errorMessage) : $errorMessage;
            throw new \RuntimeException($error);
        }

        if (!$uploadedFile->isValid()) {
            $errorMessage = $this->translator->trans('Is upload file error');
            $errors       = $filename ? sprintf($this->translator->trans('Upload file error'), $filename, $errorMessage) : $errorMessage;
            throw new \RuntimeException($errors);
        }

        $filename = mb_strtolower(self::s2_basename($filename));
        $filename = str_replace("\0", '', $filename);
        while (str_contains($filename, '..')) {
            $filename = str_replace('..', '', $filename);
        }

        $extension = '';
        if (($ext_pos = strrpos($filename, '.')) !== false) {
            $extension = substr($filename, $ext_pos + 1);
        }

        if (
            $this->allowedExtensions !== ''
            && $extension !== ''
            && !$this->permissionChecker->isGranted(PermissionChecker::PERMISSION_EDIT_USERS)
            && !str_contains(' ' . $this->allowedExtensions . ' ', ' ' . $extension . ' ')
        ) {
            $errorMessage = $this->translator->trans('Forbidden extension', ['{{ ext }}' => $extension]);
            $error        = $filename ? \sprintf($this->translator->trans('Upload file error'), $filename, $errorMessage) : $errorMessage;
            throw new \RuntimeException($error);
        }

        // Processing name collisions
        while (is_file($this->imageDir . $path . '/' . $filename)) {
            $filename = preg_replace_callback('#(?:|_copy|_copy\((\d+)\))(?=(?:\.[^\.]*)?$)#', static function ($match) {
                if ($match[0] === '') {
                    return '_copy';
                }

                if ($match[0] === '_copy') {
                    return '_copy(2)';
                }

                return '_copy(' . ($match[1] + 1) . ')';
            }, $filename, 1);
        }

        if ($createDir && !is_dir($this->imageDir . $path)) {
            if (!mkdir($this->imageDir . $path, 0777, true) && !is_dir($this->imageDir . $path)) {
                throw new \RuntimeException(\sprintf('Directory "%s" was not created', $this->imageDir . $path));
            }
            chmod($this->imageDir . $path, 0777);
        }
        $uploadedFile->move($this->imageDir . $path, $filename);
        chmod($this->imageDir . $path . '/' . $filename, 0644);

        return $path . '/' . $filename;
    }


    public function getImageInfo(string $fileName): array
    {
        return \function_exists('getimagesize') ? (getimagesize($this->imageDir . $fileName) ?: []) : [];
    }

    public function getFolderCsrfToken(string $path): string
    {
        $formParams = new FormParams(
            'PictureManager',
            [],
            $this->settingStorage,
            FieldConfig::ACTION_DELETE,
            ['scope' => 'folder', 'path' => $this->getFolderTokenKey($path)],
        );

        return $formParams->getCsrfToken();
    }

    public function assertFolderCsrfToken(string $path, string $csrfToken): void
    {
        if ($csrfToken === '' || !hash_equals($this->getFolderCsrfToken($path), $csrfToken)) {
            throw new AccessDeniedException('Invalid CSRF token!');
        }
    }

    public function assertFileCsrfToken(string $filePath, string $csrfToken): void
    {
        $this->assertFolderCsrfToken(self::s2_dirname($filePath), $csrfToken);
    }

    private function getFolderTokenKey(string $path): string
    {
        $fullPath = $this->imageDir . $path;
        clearstatcache(false, $fullPath);

        $realPath = realpath($fullPath);
        if ($realPath === false) {
            $realPath = $fullPath;
        }

        $inode = @fileinode($fullPath);
        if ($inode === false) {
            return $realPath;
        }

        return 'inode:' . $inode;
    }

    private static function s2_basename($dir)
    {
        return false !== ($pos = strrpos($dir, '/')) ? substr($dir, $pos + 1) : $dir;
    }

    private static function s2_dirname(string $dir): string
    {
        return preg_replace('#/[^/]*$#', '', $dir);
    }
}
