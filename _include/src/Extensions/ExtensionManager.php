<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license   http://opensource.org/licenses/MIT MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Extensions;

use Psr\Cache\InvalidArgumentException;
use S2\AdminYard\Translator;
use S2\Cms\Config\DynamicConfigProvider;
use S2\Cms\Framework\Container;
use S2\Cms\Model\ExtensionCache;
use S2\Cms\Pdo\DbLayer;
use S2\Cms\Pdo\DbLayerException;

readonly class ExtensionManager
{
    public function __construct(
        private DbLayer               $dbLayer,
        private ExtensionCache        $extensionCache,
        private DynamicConfigProvider $dynamicConfigProvider,
        private Translator            $translator,
        private Container             $container, // Note: do not use here as a service locator
        private string                $rootDir,
    ) {
    }

    /**
     * @throws DbLayerException
     */
    public function getExtensionList(): array
    {
        $installedExtensions = [];
        $query               = [
            'SELECT'   => 'e.*',
            'FROM'     => 'extensions AS e',
            'ORDER BY' => 'e.title'
        ];

        $result = $this->dbLayer->buildAndQuery($query);
        while ($currentExtension = $this->dbLayer->fetchAssoc($result)) {
            $installedExtensions[$currentExtension['id']] = $currentExtension;
        }

        $extensionNum        = 0;
        $availableExtensions = [];
        $failedExtensions    = [];

        $d = dir($this->rootDir . '_extensions');
        while (($entry = $d->read()) !== false) {
            if ($entry[0] === '.' || !is_dir($this->rootDir . '_extensions/' . $entry)) {
                continue;
            }

            if (preg_match('/[^0-9a-z_]/', $entry)) {
                $failedExtensions[] = [
                    'entry'   => $entry,
                    'error'   => $this->translator->trans('Extension loading error', ['{{ extension }}' => $entry]),
                    'message' => $this->translator->trans('Illegal ID')
                ];
                continue;
            }

            if (!file_exists($this->rootDir . '_extensions/' . $entry . '/Manifest.php')) {
                $failedExtensions[] = [
                    'entry'   => $entry,
                    'error'   => $this->translator->trans('Extension loading error', ['{{ extension }}' => $entry]),
                    'message' => $this->translator->trans('Missing manifest')
                ];
                continue;
            }

            $extensionClass = '\\s2_extensions\\' . $entry . '\\Manifest';
            if (!class_exists($extensionClass)) {
                $failedExtensions[] = [
                    'entry'   => $entry,
                    'error'   => $this->translator->trans('Extension loading error', ['{{ extension }}' => $entry]),
                    'message' => $this->translator->trans('Manifest class not found')
                ];
                continue;
            }

            $extensionManifest = new $extensionClass();
            if (!$extensionManifest instanceof ManifestInterface) {
                $failedExtensions[] = [
                    'entry'   => $entry,
                    'error'   => $this->translator->trans('Extension loading error', ['{{ extension }}' => $entry]),
                    'message' => $this->translator->trans('ManifestInterface is not implemented')
                ];
                continue;
            }

            if (!\array_key_exists($entry, $installedExtensions) || version_compare($installedExtensions[$entry]['version'], $extensionManifest->getVersion(), '!=')) {
                $installationNotes = [];
                if ($extensionManifest->getInstallationNote()) {
                    $installationNotes[] = $extensionManifest->getInstallationNote();
                }

                $availableExtensions[] = [
                    'entry'             => $entry,
                    'title'             => $extensionManifest->getTitle(),
                    'version'           => $extensionManifest->getVersion(),
                    'author'            => $extensionManifest->getAuthor(),
                    'description'       => $extensionManifest->getDescription(),
                    'install_notes'     => $installationNotes,
                    'installed_version' => $installedExtensions[$entry]['version'] ?? null,
                    'admin_affected'    => $extensionManifest->isAdminAffected()
                ];
                ++$extensionNum;
            }
        }
        $d->close();

        return [
            'extensionNum'        => $extensionNum,
            'availableExtensions' => $availableExtensions,
            'failedExtensions'    => $failedExtensions,
            'installedExtensions' => $installedExtensions,
        ];
    }

    /**
     * @throws DbLayerException
     */
    public function getUpgradableExtensionNum(): int
    {
        $result = $this->dbLayer->buildAndQuery([
            'SELECT' => 'e.*',
            'FROM'   => 'extensions AS e',
        ]);

        $extensionNum = 0;
        while ($currentExtension = $this->dbLayer->fetchAssoc($result)) {
            $manifestClass = '\\s2_extensions\\' . $currentExtension['id'] . '\\Manifest';
            if (!class_exists($manifestClass)) {
                continue;
            }
            $extensionManifest = new $manifestClass;
            if (!$extensionManifest instanceof ManifestInterface) {
                continue;
            }
            if (version_compare($currentExtension['version'], $extensionManifest->getVersion(), '<')) {
                ++$extensionNum;
            }
        }

        return $extensionNum;
    }

    /**
     * @throws DbLayerException
     */
    public function flipExtension(string $id): ?string
    {
        // Fetch the current status of the extension
        $result = $this->dbLayer->buildAndQuery([
            'SELECT' => 'e.disabled',
            'FROM'   => 'extensions AS e',
            'WHERE'  => 'e.id = :id'
        ], ['id' => $id]);

        $row = $this->dbLayer->fetchAssoc($result);
        if (!$row) {
            return $this->translator->trans('Extension loading error', ['{{ extension }}' => $id]);
        }

        // Are we disabling or enabling?
        $disable = $row['disabled'] === 0;

        // Check dependencies
        if ($disable) {
            $result        = $this->dbLayer->buildAndQuery([
                'SELECT' => 'e.id',
                'FROM'   => 'extensions AS e',
                'WHERE'  => 'e.disabled = 0 AND e.dependencies LIKE :id'
            ], ['id' => '%|' . $id . '|%']);
            $dependencyIds = $this->dbLayer->fetchColumn($result);

            if (!empty($dependencyIds)) {
                return $this->translator->trans('Disable dependency', [
                    '{{ extension }}'    => $id,
                    '{{ dependencies }}' => implode(', ', $dependencyIds),
                ]);
            }
        } else {
            $result = $this->dbLayer->buildAndQuery([
                'SELECT' => 'e.dependencies',
                'FROM'   => 'extensions AS e',
                'WHERE'  => 'e.id = :id'
            ], ['id' => $id]);

            $dependencies = $this->dbLayer->result($result);
            $dependencies = explode('|', substr($dependencies, 1, -1));
            $dependencies = array_filter($dependencies, '\strlen');

            $result            = $this->dbLayer->buildAndQuery([
                'SELECT' => 'e.id',
                'FROM'   => 'extensions AS e',
                'WHERE'  => 'e.disabled = 0'
            ]);
            $enabledExtensions = $this->dbLayer->fetchColumn($result);

            $brokenDependencies = array_diff($dependencies, $enabledExtensions);

            if (\count($brokenDependencies) > 0) {
                return $this->translator->trans('Disabled dependency', [
                    '{{ extension }}'    => $id,
                    '{{ dependencies }}' => implode(', ', $brokenDependencies),
                ]);
            }
        }

        $this->dbLayer->buildAndQuery([
            'UPDATE' => 'extensions',
            'SET'    => 'disabled = ' . ($disable ? '1' : '0'),
            'WHERE'  => 'id = :id',
        ], ['id' => $id]);

        // Regenerate the hooks cache
        $this->extensionCache->clear();
        $this->extensionCache->generateHooks();

        return null;
    }

    /**
     * @throws DbLayerException
     * @throws InvalidArgumentException
     */
    public function installExtension(string $id): array
    {
        if (!file_exists($this->rootDir . '_extensions/' . $id . '/Manifest.php')) {
            return [
                $this->translator->trans('Extension loading error', ['{{ extension }}' => $id]),
                $this->translator->trans('Missing manifest'),
            ];
        }

        $extensionClass = '\\s2_extensions\\' . $id . '\\Manifest';
        if (!class_exists($extensionClass)) {
            return [
                $this->translator->trans('Extension loading error', ['{{ extension }}' => $id]),
                $this->translator->trans('Manifest class not found'),
            ];
        }

        $extensionManifest = new $extensionClass();
        if (!$extensionManifest instanceof ManifestInterface) {
            return [
                $this->translator->trans('Extension loading error', ['{{ extension }}' => $id]),
                $this->translator->trans('ManifestInterface is not implemented'),
            ];
        }

        $result            = $this->dbLayer->buildAndQuery([
            'SELECT' => 'e.id',
            'FROM'   => 'extensions AS e',
            'WHERE'  => 'e.disabled=0'
        ]);
        $enabledExtensions = $this->dbLayer->fetchColumn($result);

        $missingDependencies = array_diff($extensionManifest->getDependencies(), $enabledExtensions);

        if (!empty($missingDependencies)) {
            return [
                $this->translator->trans('Missing dependency', [
                    '{{ extension }}'    => $id,
                    '{{ dependencies }}' => implode(', ', $missingDependencies),
                ]),
            ];
        }

        // Is this a fresh install or an upgrade?
        $result = $this->dbLayer->buildAndQuery([
            'SELECT' => 'e.version',
            'FROM'   => 'extensions AS e',
            'WHERE'  => 'e.id = :id'
        ], ['id' => $id]);

        if ($curr_version = $this->dbLayer->result($result)) {
            // Run the author supplied installation code
            $extensionManifest->install($this->dbLayer, $this->container, $curr_version);

            // Update the existing extension
            $query = [
                'UPDATE' => 'extensions',
                'SET'    => 'title = :title, version = :version, description = :description, author = :author, admin_affected = :admin_affected, uninstall_note = :uninstall_note, dependencies = :dependencies',
                'WHERE'  => 'id=:id',
            ];
        } else {
            // Run the author supplied installation code
            $extensionManifest->install($this->dbLayer, $this->container, null);

            // Add the new extension
            $query = [
                'INSERT' => 'id, title, version, description, author, admin_affected, uninstall_note, dependencies',
                'INTO'   => 'extensions',
                'VALUES' => ':id, :title, :version, :description, :author, :admin_affected, :uninstall_note, :dependencies',
            ];
        }
        $this->dbLayer->buildAndQuery($query, [
            'id'             => $id,
            'title'          => $extensionManifest->getTitle(),
            'version'        => $extensionManifest->getVersion(),
            'description'    => $extensionManifest->getDescription(),
            'author'         => $extensionManifest->getAuthor(),
            'admin_affected' => $extensionManifest->isAdminAffected() ? 1 : 0,
            'uninstall_note' => $extensionManifest->getUninstallationNote(),
            'dependencies'   => '|' . implode('|', $extensionManifest->getDependencies()) . '|',
        ]);

        // Extensions may add their own config params
        $this->dynamicConfigProvider->regenerate();

        // Regenerate the hooks cache
        $this->extensionCache->clear();
        $this->extensionCache->generateHooks();

        return [];
    }

    /**
     * @throws DbLayerException
     */
    public function uninstallExtension(string $id): ?string
    {
        // Fetch info about the extension
        $result = $this->dbLayer->buildAndQuery([
            'SELECT' => '1',
            'FROM'   => 'extensions AS e',
            'WHERE'  => 'e.id = :id',
        ], ['id' => $id]);

        $extensionExists = $this->dbLayer->result($result);
        if (!$extensionExists) {
            return $this->translator->trans('Extension loading error', ['{{ extension }}' => $id]);
        }

        // Check dependencies
        $result = $this->dbLayer->buildAndQuery([
            'SELECT' => 'e.id',
            'FROM'   => 'extensions AS e',
            'WHERE'  => 'e.dependencies LIKE :id'
        ], ['id' => '%|' . $id . '|%']);

        $dependencies = $this->dbLayer->fetchColumn($result);

        if (!empty($dependencies)) {
            return $this->translator->trans('Uninstall dependency', [
                '{{ extension }}'    => $id,
                '{{ dependencies }}' => implode(', ', $dependencies),
            ]);
        }

        // Run uninstall code
        $extensionClass = '\\s2_extensions\\' . $id . '\\Manifest';
        /** @var ManifestInterface $extensionManifest */
        $extensionManifest = new $extensionClass();
        $extensionManifest->uninstall($this->dbLayer, $this->container);

        $this->dbLayer->buildAndQuery([
            'DELETE' => 'extensions',
            'WHERE'  => 'id = :id',
        ], ['id' => $id]);

        // Regenerate the hooks cache
        $this->extensionCache->clear();
        $this->extensionCache->generateHooks();

        return null;
    }
}
