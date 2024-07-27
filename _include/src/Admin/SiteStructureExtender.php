<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license   http://opensource.org/licenses/MIT MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Admin;

use S2\AdminYard\Config\AdminConfig;
use S2\AdminYard\TemplateRenderer;

readonly class SiteStructureExtender implements AdminConfigExtenderInterface
{
    public function __construct(
        private TemplateRenderer $templateRenderer
    ) {
    }

    public function extend(AdminConfig $adminConfig): void
    {
        $adminConfig
            ->setServicePage('Site', function () {
                return $this->templateRenderer->render('_admin/templates/structure/structure.php.inc');
            }, -10)
        ;
    }
}
