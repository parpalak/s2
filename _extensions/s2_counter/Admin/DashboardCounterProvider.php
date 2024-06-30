<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license   http://opensource.org/licenses/MIT MIT
 * @package   s2_counter
 */

declare(strict_types=1);

namespace s2_extensions\s2_counter\Admin;

use S2\AdminYard\TemplateRenderer;
use S2\Cms\Admin\Dashboard\DashboardBlockProviderInterface;

readonly class DashboardCounterProvider implements DashboardBlockProviderInterface
{
    public function __construct(
        private TemplateRenderer $templateRenderer,
        private string           $rootDir
    ) {
    }

    public function getHtml(): string
    {
        return $this->templateRenderer->render(
            $this->rootDir . '_extensions/s2_counter/views/dashboard/diagrams.php.inc',
            [
                'dirIsWritable' => is_writable($this->rootDir . '_extensions/s2_counter/data/'),
            ]
        );
    }
}
