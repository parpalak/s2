<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license   http://opensource.org/licenses/MIT MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Admin\Dashboard;

use S2\AdminYard\TemplateRenderer;
use S2\Cms\Pdo\DbLayer;

readonly class DashboardDatabaseProvider implements DashboardStatProviderInterface
{
    public function __construct(
        private TemplateRenderer $templateRenderer,
        private DbLayer          $dbLayer,
        private string           $dbType,
        private string           $dbName,
        private string           $dbPrefix,
        private string           $rootDir
    ) {
    }

    public function getHtml(): string
    {
        $totalSize = $totalRecords = null;

        // Collect some additional info about MySQL
        if ($this->dbType === 'mysql') {
            // Calculate total db size/row count
            // TODO get rid of hardcoded 's2_search_idx_' prefix
            $result = $this->dbLayer->query('SHOW TABLE STATUS FROM `' . $this->dbName . '` WHERE NAME LIKE \'' . $this->dbPrefix . '%\' AND NAME NOT LIKE \'' . $this->dbPrefix . 's2_search_idx_%\'');

            $totalRecords = $totalSize = 0;
            while ($status = $this->dbLayer->fetchAssoc($result)) {
                $totalRecords += $status['Rows'];
                $totalSize    += $status['Data_length'] + $status['Index_length'];
            }
        }

        $versionInfo = $this->dbLayer->getVersion();

        return $this->templateRenderer->render($this->rootDir . '_admin/templates/dashboard/database-item.php.inc', [
            'dbSize'    => $totalSize,
            'dbRecords' => $totalRecords,
            'dbType'    => $versionInfo['name'],
            'dbVersion' => $versionInfo['version'],
        ]);
    }
}
