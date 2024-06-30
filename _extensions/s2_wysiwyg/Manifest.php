<?php
/**
 * WYSIWYG
 *
 * Adds TinyMCE WYSIWYG editor to the article edit form.
 *
 * @copyright 2007-2024 Roman Parpalak
 * @license MIT
 * @package s2_wysiwyg
 */

declare(strict_types=1);

namespace s2_extensions\s2_wysiwyg;

use S2\Cms\Extensions\ManifestInterface;
use S2\Cms\Extensions\ManifestTrait;
use S2\Cms\Framework\Container;
use S2\Cms\Pdo\DbLayer;

class Manifest implements ManifestInterface
{
    use ManifestTrait;

    public function getTitle(): string
    {
        return 'WYSIWYG';
    }

    public function getAuthor(): string
    {
        return 'Roman Parpalak';
    }

    public function getDescription(): string
    {
        return 'Adds TinyMCE WYSIWYG editor to the admin panel.';
    }

    public function getVersion(): string
    {
        return '2.0dev';
    }

    public function isAdminAffected(): bool
    {
        return true;
    }

    public function install(DbLayer $dbLayer, Container $container, ?string $currentVersion): void
    {
        foreach ([
                     'S2_WYSIWYG_TYPE' => '0',
                 ] as $name => $value) {
            if (\defined($name)) {
                // TODO insert ignore
                continue;
            }

            $query = [
                'INSERT' => 'name, value',
                'INTO'   => 'config',
                'VALUES' => '\'' . $name . '\', \'' . $value . '\''
            ];

            $dbLayer->buildAndQuery($query);
        }
    }

    public function uninstall(DbLayer $dbLayer, Container $container): void
    {
        $query = [
            'DELETE' => 'config',
            'WHERE'  => 'name in (\'S2_WYSIWYG_TYPE\')',
        ];
        $dbLayer->buildAndQuery($query);
    }
}
