<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license   http://opensource.org/licenses/MIT MIT
 * @package   S2
 */

declare(strict_types=1);

namespace s2_extensions\s2_search\Admin;

use S2\AdminYard\TemplateRenderer;
use S2\Cms\Admin\Dashboard\DashboardStatProviderInterface;
use S2\Rose\Storage\Database\PdoStorage;

readonly class DashboardSearchProvider implements DashboardStatProviderInterface
{
    public function __construct(
        private TemplateRenderer $templateRenderer,
        private PdoStorage       $pdoStorage,
        private string           $rootDir
    ) {
    }

    public function getHtml(): string
    {
        try {
            $stat = $this->pdoStorage->getIndexStat();
        } catch (\Exception $e) {
            $stat = ['rows' => 0, 'bytes' => 0];
        }

        return $this->templateRenderer->render($this->rootDir . '_extensions/s2_search/views/dashboard/search-item.php.inc', $stat);
    }
}
