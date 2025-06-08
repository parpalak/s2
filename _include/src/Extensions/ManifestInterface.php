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

interface ManifestInterface
{
    public function getTitle(): string;

    public function getAuthor(): string;

    public function getDescription(): string;

    public function getVersion(): string;

    /**
     * The list of extension IDs that this extension depends on
     * @return string[]
     */
    public function getDependencies(): array;

    public function getInstallationNote(): ?string;

    public function install(DbLayer $dbLayer, Container $container, ?string $currentVersion): void;

    public function getUninstallationNote(): ?string;

    public function uninstall(DbLayer $dbLayer, Container $container): void;
}
