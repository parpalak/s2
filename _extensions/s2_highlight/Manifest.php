<?php
/**
 * HTML code highlighting
 *
 * @copyright 2012-2024 Roman Parpalak
 * @license MIT
 * @package s2_highlight
 */

declare(strict_types=1);

namespace s2_extensions\s2_highlight;

use S2\Cms\Extensions\ManifestInterface;
use S2\Cms\Extensions\ManifestTrait;

class Manifest implements ManifestInterface
{
    use ManifestTrait;

    public function getTitle(): string
    {
        return 'Editor Highlighting';
    }

    public function getAuthor(): string
    {
        return 'Roman Parpalak';
    }

    public function getDescription(): string
    {
        return 'Highlights HTML code in the editor.';
    }

    public function getVersion(): string
    {
        return '2.0dev';
    }

    public function isAdminAffected(): bool
    {
        return true;
    }
}
