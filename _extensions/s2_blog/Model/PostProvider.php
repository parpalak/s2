<?php
/**
 * @copyright 2024-2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace s2_extensions\s2_blog\Model;

use S2\Cms\Pdo\DbLayer;
use S2\Cms\Pdo\DbLayerException;
use S2\Cms\Template\Viewer;
use s2_extensions\s2_blog\BlogUrlBuilder;

readonly class PostProvider
{
    public function __construct(
        private DbLayer        $dbLayer,
        private BlogUrlBuilder $blogUrlBuilder,
        private Viewer         $viewer,
    ) {
    }

    /**
     * Returns an array containing info about last N posts
     *
     * @throws DbLayerException
     */
    public function lastPostsArray(int $postsNum = 10, int $skip = 0, bool $fakeLastPost = false): array
    {
        if ($fakeLastPost) {
            $postsNum++;
        }

        // Obtaining last posts
        $raw_query_comment = $this->dbLayer->build([
            'SELECT' => 'count(*)',
            'FROM'   => 's2_blog_comments AS c',
            'WHERE'  => 'c.post_id = p.id AND shown = 1',
        ]);

        $raw_query_user = $this->dbLayer->build([
            'SELECT' => 'u.name',
            'FROM'   => 'users AS u',
            'WHERE'  => 'u.id = p.user_id',
        ]);

        $query  = [
            'SELECT'   => 'p.create_time, p.title, p.text, p.url, p.id, p.commented, p.modify_time, p.favorite, (' . $raw_query_comment . ') AS comment_num, (' . $raw_query_user . ') AS author, p.label',
            'FROM'     => 's2_blog_posts AS p',
            'WHERE'    => 'published = 1',
            'ORDER BY' => 'create_time DESC',
            'LIMIT'    => ':limit OFFSET :skip',
        ];
        $result = $this->dbLayer->buildAndQuery(
            $query,
            [
                'limit' => $postsNum,
                'skip'  => $skip,
            ],
            [
                'limit' => \PDO::PARAM_INT,
                'skip'  => \PDO::PARAM_INT
            ]
        );

        $posts = $mergeLabels = $labels = $ids = array();
        $i     = 0;
        while ($row = $this->dbLayer->fetchAssoc($result)) {
            $i++;
            $posts[$row['id']] = $row;

            if ($i >= $postsNum && $fakeLastPost) {
                continue;
            }

            $ids[]              = $row['id'];
            $labels[$row['id']] = $row['label'];
            if ($row['label']) {
                $mergeLabels[$row['label']] = 1;
            }
        }
        if (!$i) {
            return [];
        }

        $seeAlso = $tags = [];
        $this->postsLinks($ids, $mergeLabels, $seeAlso, $tags);

        foreach ($posts as $i => &$post) {
            $posts[$i]['see_also'] = [];
            if (!empty($labels[$i]) && isset($seeAlso[$labels[$i]])) {
                $labelCopy = $seeAlso[$labels[$i]];
                if (isset($labelCopy[$i])) {
                    unset($labelCopy[$i]);
                }
                $posts[$i]['see_also'] = $labelCopy;
            }

            $post['tags'] = $tags[$i] ?? [];
            if (!isset($post['author'])) {
                $post['author'] = '';
            }

            $link               = $this->blogUrlBuilder->postFromTimestamp($post['create_time'], $post['url']);
            $post['title_link'] = $link;
            $post['link']       = $link;
            $post['time']       = $this->viewer->dateAndTime($post['create_time']);
        }

        return $posts;
    }

    /**
     * Fetching tags and labels for posts
     *
     * @param array $ids    Post ids, e.g. array (10, 15, 20)
     * @param array $labels Label flags, e.g. array ('label1' => 1, 'label2' => 1, 'label3' => 1)
     * @param       $see_also
     * @param       $tags
     *
     * @throws DbLayerException
     */
    public function postsLinks(array $ids, array $labels, &$see_also, &$tags): void
    {
        // Processing labels
        if (\count($labels) > 0) {
            $query  = [
                'SELECT' => 'p.id, p.label, p.title, p.create_time, p.url',
                'FROM'   => 's2_blog_posts AS p',
                'WHERE'  => 'p.label IN (\'' . implode('\', \'', array_keys($labels)) . '\') AND p.published = 1'
            ];
            $result = $this->dbLayer->buildAndQuery($query);

            $rows = $sortArray = [];
            while ($row = $this->dbLayer->fetchAssoc($result)) {
                $rows[]      = $row;
                $sortArray[] = $row['create_time'];
            }

            array_multisort($sortArray, SORT_DESC, $rows);

            foreach ($rows as $row) {
                $see_also[$row['label']][$row['id']] = [
                    'title' => $row['title'],
                    'link'  => $this->blogUrlBuilder->postFromTimestamp($row['create_time'], $row['url']),
                ];
            }
        }

        // Obtaining tags
        $query  = [
            'SELECT' => 'pt.post_id, t.name, t.url, pt.id AS pt_id',
            'FROM'   => 'tags AS t',
            'JOINS'  => [
                [
                    'INNER JOIN' => 's2_blog_post_tag AS pt',
                    'ON'         => 'pt.tag_id = t.id'
                ]
            ],
            'WHERE'  => 'pt.post_id IN (' . implode(', ', $ids) . ')'
        ];
        $result = $this->dbLayer->buildAndQuery($query);

        $rows = $sortArray = [];
        while ($row = $this->dbLayer->fetchAssoc($result)) {
            $rows[]      = $row;
            $sortArray[] = $row['pt_id'];
        }

        array_multisort($sortArray, $rows);

        $tags = [];
        foreach ($rows as $row) {
            $tags[$row['post_id']][] = array(
                'title' => $row['name'],
                'link'  => $this->blogUrlBuilder->tag($row['url']),
            );
        }
    }

    /**
     * @throws DbLayerException
     */
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

    /**
     * @throws DbLayerException
     */
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
            'WHERE'  => 'post_id = :post_id' . ($includeHidden ? '' : ' AND shown = 1'),
        ], ['post_id' => $postId]);

        return (int)$this->dbLayer->result($result);
    }
}
