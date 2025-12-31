<?php
/**
 * @copyright 2007-2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Admin\Picture;

use S2\AdminYard\Translator;
use S2\Cms\Model\PermissionChecker;

readonly class PictureFileNameHelper
{
    public function __construct(
        private Translator        $translator,
        private PermissionChecker $permissionChecker,
        private string            $allowedExtensions,
    ) {
    }

    public function normalizeFileName(string $filename): string
    {
        $filename = mb_strtolower(self::baseName($filename));
        $filename = str_replace("\0", '', $filename);
        while (str_contains($filename, '..')) {
            $filename = str_replace('..', '', $filename);
        }

        return $filename;
    }

    public function assertAllowedExtension(string $filename): void
    {
        $extension = '';
        if (($dotPos = strrpos($filename, '.')) !== false) {
            $extension = substr($filename, $dotPos + 1);
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
    }

    public function incrementCopySuffix(string $filename): string
    {
        return preg_replace_callback('#(?:|_copy|_copy\((\d+)\))(?=(?:\.[^\.]*)?$)#', static function ($match) {
            if ($match[0] === '') {
                return '_copy';
            }

            if ($match[0] === '_copy') {
                return '_copy(2)';
            }

            return '_copy(' . ($match[1] + 1) . ')';
        }, $filename, 1);
    }

    private static function baseName(string $dir): string
    {
        return false !== ($pos = strrpos($dir, '/')) ? substr($dir, $pos + 1) : $dir;
    }
}
