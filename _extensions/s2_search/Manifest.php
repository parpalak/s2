<?php
/**
 * Search
 *
 * Adds full-text search with English and Russian morphology.
 *
 * @copyright 2011-2024 Roman Parpalak
 * @license MIT
 * @package s2_search
 */

declare(strict_types=1);

namespace s2_extensions\s2_search;

use S2\Cms\Extensions\ManifestInterface;
use S2\Cms\Extensions\ManifestTrait;
use S2\Cms\Pdo\DbLayer;

class Manifest implements ManifestInterface
{
    use ManifestTrait;

    public function getTitle(): string
    {
        return 'Search';
    }

    public function getAuthor(): string
    {
        return 'Roman Parpalak';
    }

    public function getDescription(): string
    {
        return 'Full-text search with English and Russian morphology.';
    }

    public function getVersion(): string
    {
        return '2.0dev';
    }

    public function isAdminAffected(): bool
    {
        return true;
    }

    public function getInstallationNote(): ?string
    {
        return 'Do not forget to create search index after extension installation (Admin → Stats page).';
    }

    public function install(DbLayer $dbLayer, ?string $currentVersion): void
    {
        $s2_search_config = [
            'S2_SEARCH_QUICK' => '0',
        ];

        foreach ($s2_search_config as $conf_name => $conf_value) {
            if (\defined($conf_name)) {
                // TODO implement insert ignore
                continue;
            }

            $query = [
                'INSERT' => 'name, value',
                'INTO'   => 'config',
                'VALUES' => '\'' . $conf_name . '\', \'' . $conf_value . '\''
            ];

            $dbLayer->buildAndQuery($query);
        }
    }

    public function uninstall(DbLayer $dbLayer): void
    {
        $query = [
            'DELETE' => 'config',
            'WHERE'  => 'name in (\'S2_SEARCH_QUICK\')',
        ];
        $dbLayer->buildAndQuery($query);
    }
}
