<?php
/**
 * @copyright 2024-2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Extensions;

use S2\Cms\Framework\Container;
use S2\Cms\Pdo\DbLayer;

trait ManifestTrait
{
    public function getDependencies(): array
    {
        return [];
    }

    public function getInstallationNote(): ?string
    {
        return null;
    }

    public function install(DbLayer $dbLayer, Container $container, ?string $currentVersion): void
    {
    }

    public function getUninstallationNote(): ?string
    {
        return null;
    }

    public function uninstall(DbLayer $dbLayer, Container $container): void
    {
    }
}
