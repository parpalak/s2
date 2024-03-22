<?php
/**
 * Template editor
 *
 * Allows to edit templates in the admin panel
 *
 * @copyright 2012-2024 Roman Parpalak
 * @license MIT
 * @package s2_tpl_edit
 */

declare(strict_types=1);

namespace s2_extensions\s2_tpl_edit;

use S2\Cms\Extensions\ManifestInterface;
use S2\Cms\Extensions\ManifestTrait;

class Manifest implements ManifestInterface
{
    use ManifestTrait;

    public function getTitle(): string
    {
        return 'Template Editor';
    }

    public function getAuthor(): string
    {
        return 'Roman Parpalak';
    }

    public function getDescription(): string
    {
        return 'Allows to edit templates in the admin panel.';
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
