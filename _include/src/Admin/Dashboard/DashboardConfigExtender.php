<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license   http://opensource.org/licenses/MIT MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Admin\Dashboard;

use S2\AdminYard\Config\AdminConfig;
use S2\AdminYard\TemplateRenderer;
use S2\Cms\Admin\AdminConfigExtenderInterface;
use S2\Cms\Model\PermissionChecker;

readonly class DashboardConfigExtender implements AdminConfigExtenderInterface
{
    public function __construct(
        private array             $dashboardStatProviders,
        private array             $dashboardBlockProviders,
        private PermissionChecker $permissionChecker,
        private TemplateRenderer  $templateRenderer
    ) {
    }

    public function extend(AdminConfig $adminConfig): void
    {
        if (!$this->permissionChecker->isGranted(PermissionChecker::PERMISSION_VIEW_HIDDEN)) {
            return;
        }

        $adminConfig
            ->setServicePage('Dashboard', function () {
                return $this->templateRenderer->render(
                    '_admin/templates/dashboard/dashboard.php.inc',
                    [
                        'dashboardStatProviders'  => $this->dashboardStatProviders,
                        'dashboardBlockProviders' => $this->dashboardBlockProviders,
                    ]
                );
            }, 30)
        ;
    }
}
