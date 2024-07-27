<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license   http://opensource.org/licenses/MIT MIT
 * @package   S2
 */

declare(strict_types=1);

namespace s2_extensions\s2_blog\Admin;

use S2\AdminYard\Config\FieldConfig;
use S2\Cms\Pdo\DbLayer;
use S2\Cms\Pdo\DbLayerException;

readonly class PathToAdminEntityConverter
{
    public function __construct(
        private DbLayer $dbLayer,
        private string  $blogUrl,
    ) {
    }

    /**
     * @throws DbLayerException
     */
    public function getQueryParams(string $path): ?array
    {
        if (!str_starts_with($path, $this->blogUrl)) {
            return null;
        }

        $path      = substr($path, \strlen($this->blogUrl));
        $pathArray = explode('/', $path);   //   []/[2006]/[12]/[31]/[newyear]
        if (\count($pathArray) < 5) {
            return ['entity' => 'BlogPost', 'action' => FieldConfig::ACTION_LIST];
        }

        $start_time = mktime(0, 0, 0, (int)$pathArray[2], (int)$pathArray[3], (int)$pathArray[1]);
        $end_time   = mktime(0, 0, 0, (int)$pathArray[2], (int)$pathArray[3] + 1, (int)$pathArray[1]);

        $result = $this->dbLayer->buildAndQuery([
            'SELECT' => 'id',
            'FROM'   => 's2_blog_posts',
            'WHERE'  => 'create_time < :end_time AND create_time >= :start_time AND url=:url'
        ], [
            'start_time' => $start_time,
            'end_time'   => $end_time,
            'url'        => $pathArray[4]
        ]);

        if ($row = $this->dbLayer->fetchAssoc($result)) {
            return ['entity' => 'BlogPost', 'action' => FieldConfig::ACTION_EDIT, 'id' => $row['id']];
        }

        return ['entity' => 'BlogPost', 'action' => FieldConfig::ACTION_LIST];
    }
}
