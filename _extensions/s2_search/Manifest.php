<?php
/**
 * Search
 *
 * Adds full-text search with English and Russian morphology.
 *
 * @copyright 2011-2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   s2_search
 */

declare(strict_types=1);

namespace s2_extensions\s2_search;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use S2\Cms\Extensions\ManifestInterface;
use S2\Cms\Extensions\ManifestTrait;
use S2\Cms\Framework\Container;
use S2\Cms\Pdo\DbLayer;
use S2\Cms\Pdo\DbLayerException;
use S2\Rose\Storage\Database\PdoStorage;

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
        return '2.0a1';
    }

    public function isAdminAffected(): bool
    {
        return true;
    }

    public function getInstallationNote(): ?string
    {
        return 'Do not forget to create search index after extension installation (Admin → Stats page).';
    }

    public function install(DbLayer $dbLayer, Container $container, ?string $currentVersion): void
    {
        $s2_search_config = [
            'S2_SEARCH_QUICK'                 => '0',
            'S2_SEARCH_RECOMMENDATIONS_LIMIT' => '0',
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

        // The extension is not installed yet, so we can't take the storage from the container directly
        $pdoStorage = Extension::PdoStorageFactory($container);
        $pdoStorage->erase();
    }

    /**
     * @throws DbLayerException
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function uninstall(DbLayer $dbLayer, Container $container): void
    {
        if ($dbLayer->tableExists('config')) {
            $dbLayer->buildAndQuery([
                'DELETE' => 'config',
                'WHERE'  => 'name in (\'S2_SEARCH_QUICK\', \'S2_SEARCH_RECOMMENDATIONS_LIMIT\')',
            ]);
        }

        /** @var PdoStorage $pdoStorage */
        $pdoStorage = $container->get(PdoStorage::class);
        $pdoStorage->drop();
    }
}
