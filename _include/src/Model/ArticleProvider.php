<?php
/**
 * @copyright 2007-2024 Roman Parpalak
 * @license MIT
 * @package S2
 */

declare(strict_types=1);

namespace S2\Cms\Model;

use S2\Cms\Pdo\DbLayer;
use S2\Cms\Template\Viewer;

readonly class ArticleProvider
{
    public function __construct(
        private DbLayer    $dbLayer,
        private UrlBuilder $urlBuilder,
        private Viewer     $viewer,
        private bool       $useHierarchy,
    ) {
    }

    /**
     * Fetching last articles info (for template placeholders and RSS)
     *
     * @throws \S2\Cms\Pdo\DbLayerException
     */
    public function lastArticlesList(?int $limit = 5): array
    {
        $raw_query_child_num = $this->dbLayer->build([
            'SELECT' => '1',
            'FROM'   => 'articles AS a2',
            'WHERE'  => 'a2.parent_id = a.id AND a2.published = 1',
            'LIMIT'  => '1'
        ]);

        $raw_query_user = $this->dbLayer->build([
            'SELECT' => 'u.name',
            'FROM'   => 'users AS u',
            'WHERE'  => 'u.id = a.user_id'
        ]);

        $query = [
            'SELECT'   => 'a.id, a.title, a.create_time, a.modify_time, a.excerpt, a.favorite, a.url, a.parent_id, a1.title AS parent_title, a1.url AS p_url, (' . $raw_query_user . ') AS author',
            'FROM'     => 'articles AS a',
            'JOINS'    => [
                [
                    'INNER JOIN' => 'articles AS a1',
                    'ON'         => 'a1.id = a.parent_id'
                ]
            ],
            'ORDER BY' => 'a.create_time DESC',
            'WHERE'    => '(' . $raw_query_child_num . ') IS NULL AND (a.create_time <> 0 OR a.modify_time <> 0) AND a.published = 1',
        ];

        if ($limit !== null) {
            $query['LIMIT'] = (string)$limit;
        }

        $result = $this->dbLayer->buildAndQuery($query);

        $last = $urls = $parent_ids = [];
        for ($i = 0; $row = $this->dbLayer->fetchAssoc($result); $i++) {
            $urls[$i]       = urlencode($row['url']);
            $parent_ids[$i] = $row['parent_id'];

            $last[$i]['title']        = $row['title'];
            $last[$i]['parent_title'] = $row['parent_title'];
            $last[$i]['p_url']        = $row['p_url'];
            $last[$i]['time']         = $row['create_time'];
            $last[$i]['modify_time']  = $row['modify_time'];
            $last[$i]['favorite']     = $row['favorite'];
            $last[$i]['text']         = $row['excerpt'];
            $last[$i]['author']       = $row['author'] ?? '';
        }

        $urls = Model::get_group_url($parent_ids, $urls);

        foreach ($last as $k => $v) {
            if (isset($urls[$k])) {
                $last[$k]['rel_path'] = $urls[$k];
            } else {
                unset($last[$k]);
            }
        }

        return $last;
    }

    /**
     * Formatting last articles (for template placeholders)
     *
     * @throws \S2\Cms\Pdo\DbLayerException
     */
    public function lastArticlesPlaceholder(int $limit): string
    {
        $articles = $this->lastArticlesList($limit);

        $output = '';
        foreach ($articles as &$item) {
            $parentPath          = $this->useHierarchy ? preg_replace('#/\\K[^/]*$#', '', $item['rel_path']) : '/' . $item['p_url'];
            $item['date']        = s2_date($item['time']);
            $item['link']        = $this->urlBuilder->link($item['rel_path']);
            $item['parent_link'] = $this->urlBuilder->link($parentPath);

            $output .= $this->viewer->render('last_articles_item', $item);
        }
        unset($item);

        return $output;
    }
}
