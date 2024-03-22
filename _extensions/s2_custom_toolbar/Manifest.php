<?php
/**
 * Custom Editor Toolbar
 *
 * @copyright 2013-2024 Roman Parpalak
 * @license MIT
 * @package s2_custom_toolbar
 */

declare(strict_types=1);

namespace s2_extensions\s2_custom_toolbar;

use S2\Cms\Extensions\ManifestInterface;
use S2\Cms\Extensions\ManifestTrait;

class Manifest implements ManifestInterface
{
    use ManifestTrait;

    public function getTitle(): string
    {
        return 'Custom Editor Toolbar';
    }

    public function getAuthor(): string
    {
        return 'Roman Parpalak';
    }

    public function getDescription(): string
    {
        return 'Adds buttons to the editor toolbar.';
    }

    public function getVersion(): string
    {
        return '1.0a';
    }

    public function isAdminAffected(): bool
    {
        return true;
    }
}
