<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license   http://opensource.org/licenses/MIT MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Admin\Dashboard;

use S2\AdminYard\TemplateRenderer;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class DashboardEnvironmentProvider implements DashboardStatProviderInterface
{
    public function __construct(
        private TranslatorInterface $translator,
        private TemplateRenderer    $templateRenderer,
        private string              $rootDir
    ) {
    }

    public function getHtml(): string
    {
        $serverLoad = $this->detectLoadAverages() ?? $this->translator->trans('N/A');

        $environment = [
            sprintf($this->translator->trans('OS'), PHP_OS),
            '<a href="site_ajax.php?action=phpinfo" title="' . $this->translator->trans('PHP info') . '" target="_blank">PHP: ' . PHP_VERSION . ' &uarr;</a>',
            sprintf($this->translator->trans('Server load'), $serverLoad),
        ];

        return $this->templateRenderer->render($this->rootDir . '_admin/templates/dashboard/stat-item.php.inc', [
            'title'  => $this->translator->trans('Environment'),
            'output' => implode('<br>', $environment),
        ]);
    }

    /**
     * Get the server load averages (if possible)
     */
    private function detectLoadAverages(): ?string
    {
        if (\function_exists('sys_getloadavg')) {
            $loadAverages = sys_getloadavg();
            if (\is_array($loadAverages)) {
                $loadAverages = array_map(static fn($value) => round($value, 3), $loadAverages);
                return $loadAverages[0] . ' ' . $loadAverages[1] . ' ' . $loadAverages[2];
            }
        }

        if (@is_readable('/proc/loadavg')) {
            $loadAverages = @file_get_contents('/proc/loadavg');
            if ($loadAverages !== false) {
                $loadAverages = explode(' ', $loadAverages);
                if (isset($loadAverages[2])) {
                    return $loadAverages[0] . ' ' . $loadAverages[1] . ' ' . $loadAverages[2];
                }
            }
        }

        if (PHP_OS_FAMILY === 'Windows') {
            return null;
        }

        $uptime = shell_exec('uptime');
        if ($uptime !== null && preg_match('/averages?:\s+([\d.]+),\s+([\d.]+),\s+([\d.]+)/i', $uptime, $matches)) {
            return $matches[1] . ' ' . $matches[2] . ' ' . $matches[3];
        }

        if (PHP_OS_FAMILY === 'BSD' || PHP_OS_FAMILY === 'Darwin') {
            $load = shell_exec('sysctl -n vm.loadavg');
            if ($load !== null) {
                $load = str_replace(['{ ', ' }'], '', $load);
                $loadAverages = explode(' ', $load);
                if (isset($loadAverages[2])) {
                    return $loadAverages[0] . ' ' . $loadAverages[1] . ' ' . $loadAverages[2];
                }
            }
        }

        return null;
    }
}
