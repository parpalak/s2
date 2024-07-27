<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license   http://opensource.org/licenses/MIT MIT
 * @package   S2
 */

declare(strict_types=1);

namespace s2_extensions\s2_blog\Model;

use S2\Cms\Pdo\DbLayer;
use S2\Cms\Pdo\DbLayerException;

readonly class PostProvider
{
    public function __construct(
        private DbLayer $dbLayer,
    ) {
    }

    public function checkUrlStatus(int $createTime, string $url): string
    {
        $s2_db = $this->dbLayer;

        if ($url === '') {
            return 'empty';
        }

        $startTime = strtotime('midnight', $createTime);
        $endTime   = $startTime + 86400;

        $query  = [
            'SELECT' => 'COUNT(*)',
            'FROM'   => 's2_blog_posts',
            'WHERE'  => 'url = :url AND create_time < :end_time AND create_time >= :start_time',
        ];
        $result = $s2_db->buildAndQuery($query, [
            'start_time' => $startTime,
            'end_time'   => $endTime,
            'url'        => $url
        ]);

        if ($s2_db->result($result) !== 1) {
            return 'not_unique';
        }

        return 'ok';
    }

    public function getAllLabels(): array
    {
        $query  = [
            'SELECT'   => 'label',
            'FROM'     => 's2_blog_posts',
            'GROUP BY' => 'label',
            'ORDER BY' => 'count(label) DESC'
        ];
        $result = $this->dbLayer->buildAndQuery($query);

        $labels = $this->dbLayer->fetchColumn($result);

        return $labels;
    }

    /**
     * @throws DbLayerException
     */
    public function getCommentNum(int $postId, bool $includeHidden): int
    {
        $result = $this->dbLayer->buildAndQuery([
            'SELECT' => 'COUNT(*)',
            'FROM'   => 's2_blog_comments',
            'WHERE'  => 'post_id = :post_id' . ($includeHidden ? '' : ' AND shown = 0'),
        ], ['post_id' => $postId]);

        return (int)$this->dbLayer->result($result);
    }
}
