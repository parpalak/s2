<?php
/**
 * @copyright 2024-2025  Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   s2_blog
 */

declare(strict_types=1);

namespace s2_extensions\s2_blog\Admin;

use S2\AdminYard\Config\FieldConfig;
use S2\Cms\Config\StringProxy;
use S2\Cms\Pdo\DbLayer;
use S2\Cms\Pdo\DbLayerException;

readonly class PathToAdminEntityConverter
{
    public function __construct(
        private DbLayer    $dbLayer,
        private StringProxy $blogUrl,
    ) {
    }

    /**
     * @throws DbLayerException
     */
    public function getQueryParams(string $path): ?array
    {
        $blogUrl = $this->blogUrl->get();
        if (!str_starts_with($path, $blogUrl)) {
            return null;
        }

        $path      = substr($path, \strlen($blogUrl));
        $pathArray = explode('/', $path);   //   []/[2006]/[12]/[31]/[newyear]
        if (\count($pathArray) < 5) {
            return ['entity' => 'BlogPost', 'action' => FieldConfig::ACTION_LIST];
        }

        $start_time = mktime(0, 0, 0, (int)$pathArray[2], (int)$pathArray[3], (int)$pathArray[1]);
        $end_time   = mktime(0, 0, 0, (int)$pathArray[2], (int)$pathArray[3] + 1, (int)$pathArray[1]);

        $result = $this->dbLayer
            ->select('id')
            ->from('s2_blog_posts')
            ->where('create_time < :end_time')->setParameter('end_time', $end_time)
            ->andWhere('create_time >= :start_time')->setParameter('start_time', $start_time)
            ->andWhere('url = :url')->setParameter('url', $pathArray[4])
            ->execute()
        ;

        if ($row = $result->fetchAssoc()) {
            return ['entity' => 'BlogPost', 'action' => FieldConfig::ACTION_EDIT, 'id' => $row['id']];
        }

        return ['entity' => 'BlogPost', 'action' => FieldConfig::ACTION_LIST];
    }
}
