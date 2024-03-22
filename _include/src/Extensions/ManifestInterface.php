<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license MIT
 * @package S2
 */

declare(strict_types=1);

namespace S2\Cms\Extensions;

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

    public function isAdminAffected(): bool;

    public function install(DbLayer $dbLayer, ?string $currentVersion): void;

    public function getUninstallationNote(): ?string;

    public function uninstall(DbLayer $dbLayer): void;
}
