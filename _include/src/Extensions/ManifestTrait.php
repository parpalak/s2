<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license MIT
 * @package S2
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
    public function isAdminAffected(): bool
    {
        return false;
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
