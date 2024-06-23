<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license   http://opensource.org/licenses/MIT MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Extensions;

use S2\AdminYard\Config\AdminConfig;
use S2\AdminYard\TemplateRenderer;
use S2\AdminYard\Translator;
use S2\Cms\Admin\AdminConfigExtenderInterface;
use S2\Cms\Pdo\DbLayer;

readonly class ExtensionManager implements AdminConfigExtenderInterface
{
    public function __construct(
        private DbLayer          $dbLayer,
        private Translator       $translator,
        private TemplateRenderer $templateRenderer,
        private string           $rootDir,
    ) {
    }

    public function extend(AdminConfig $adminConfig): void
    {
        $adminConfig
            ->setServicePage('Extension', function () {
                return $this->getExtensionList();
            }, 60)
        ;
    }

    public function getExtensionList(): string
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
                    'error'   => sprintf($this->translator->trans('Extension loading error'), $entry),
                    'message' => $this->translator->trans('Illegal ID')
                ];
                continue;
            }

            if (!file_exists($this->rootDir . '_extensions/' . $entry . '/Manifest.php')) {
                $failedExtensions[] = [
                    'entry'   => $entry,
                    'error'   => sprintf($this->translator->trans('Extension loading error'), $entry),
                    'message' => $this->translator->trans('Missing manifest')
                ];
                continue;
            }

            $extensionClass = '\\s2_extensions\\' . $entry . '\\Manifest';
            if (!class_exists($extensionClass)) {
                $failedExtensions[] = [
                    'entry'   => $entry,
                    'error'   => sprintf($this->translator->trans('Extension loading error'), $entry),
                    'message' => $this->translator->trans('Manifest class not found')
                ];
                continue;
            }

            $extensionManifest = new $extensionClass();
            if (!$extensionManifest instanceof ManifestInterface) {
                $failedExtensions[] = [
                    'entry'   => $entry,
                    'error'   => sprintf($this->translator->trans('Extension loading error'), $entry),
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

        return $this->templateRenderer->render('templates/extension/extension.php.inc', [
            'extensionNum'        => $extensionNum,
            'availableExtensions' => $availableExtensions,
            'failedExtensions'    => $failedExtensions,
            'installedExtensions' => $installedExtensions,
        ]);
    }
}
