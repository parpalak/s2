<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license   http://opensource.org/licenses/MIT MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Admin;

use S2\AdminYard\Config\FieldConfig;
use S2\Cms\Model\ArticleProvider;
use S2\Cms\Pdo\DbLayer;

readonly class PathToAdminEntityConverter
{
    public function __construct(
        private DbLayer $dbLayer,
        private bool    $useHierarchy,
    ) {
    }

    public function getQueryParams(string $path): ?array
    {
        if ($path === '/') {
            return null;
        }

        $pathArray = explode('/', $path);   // e.g. []/[dir1]/[dir2]/[dir3]/[file1]

        // Remove last empty element
        if ($pathArray[\count($pathArray) - 1] === '') {
            unset($pathArray[\count($pathArray) - 1]);
        }

        if (!$this->useHierarchy) {
            $pathArray = [$pathArray[1]];
        }

        $id = ArticleProvider::ROOT_ID;

        // Walking through page parents
        foreach ($pathArray as $pathItem) {
            $query  = [
                'SELECT' => 'a.id',
                'FROM'   => 'articles AS a',
                'WHERE'  => 'url = :url' . ($this->useHierarchy ? ' AND parent_id = :id' : '')
            ];
            $result = $this->dbLayer->buildAndQuery($query, [
                'url' => $pathItem,
                ...$this->useHierarchy ? ['id' => $id] : []
            ]);

            $id = $this->dbLayer->result($result);
            if (!$id) {
                return null;
            }
        }

        return ['entity' => 'Article', 'action' => FieldConfig::ACTION_EDIT, 'id' => $id];
    }
}
