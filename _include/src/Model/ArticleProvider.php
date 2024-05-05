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
    public const ROOT_ID = 0;

    public function __construct(
        private DbLayer    $dbLayer,
        private UrlBuilder $urlBuilder,
        private Viewer     $viewer,
        private bool       $useHierarchy,
    ) {
    }

    /**
     * Fetches hierarchical URLs for several articles. This is done by minimal number of SQL queries,
     * as if there were only one article.
     *
     * Returns an array containing full URLs, keys are preserved.
     * If somewhere is a hidden parent, the URL is removed from the returning array.
     *
     * Actually it's one of the best things in S2! :)
     *
     * @throws \S2\Cms\Pdo\DbLayerException
     */
    public function getFullUrlsForArticles(array $parentIds, array $urls): array
    {
        if (!$this->useHierarchy) {
            // Flat urls
            foreach ($urls as $k => $url) {
                $urls[$k] = '/' . $url;
            }

            return $urls;
        }

        /**
         * We build a parent chain for every article. The chain either goes up to the root
         * or stops at an unpublished article.
         *
         * If the chain goes up to the root, parent URLs are added to the $urls elements on each step up.
         * If the chain stops at an unpublished article, the URL is removed from the $urls array.
         */
        while (\count($parentIds) > 0) {
            $parentsAreFound = array_combine(array_keys($parentIds), array_fill(0, \count($parentIds), false));

            // Step to fetch parent articles
            $query  = [
                'SELECT' => 'id, parent_id, url',
                'FROM'   => 'articles',
                'WHERE'  => 'id IN (' . implode(', ', array_unique($parentIds)) . ') AND published = 1'
            ];
            $result = $this->dbLayer->buildAndQuery($query);

            while ($row = $this->dbLayer->fetchAssoc($result)) {
                // Well, the loop may seem not pretty much.
                // But $parent_ids values don't have to be unique, we have to process all duplicates.
                foreach ($parentIds as $k => $parentId) {
                    // Note: check for && !$parentsAreFound[$k] seems to be useless after adding array_unique in query before
                    if ($parentId === $row['id'] && !$parentsAreFound[$k]) {
                        $parentIds[$k]       = $row['parent_id'];
                        $urls[$k]            = urlencode($row['url']) . '/' . $urls[$k];
                        $parentsAreFound[$k] = true;
                        if (self::ROOT_ID === (int)$row['parent_id']) {
                            // The chain is finished - we are at the root.
                            unset($parentIds[$k]);
                        }
                    }
                }
            }

            // Chain was cut (published = 0). Remove the entry from $urls.
            foreach ($parentsAreFound as $k => $parentIsFound) {
                if (!$parentIsFound) {
                    unset($urls[$k], $parentIds[$k]);
                }
            }
        }

        return $urls;
    }

    /**
     * Returns the title of the main page.
     *
     * @throws \S2\Cms\Pdo\DbLayerException
     */
    public function mainPageTitle(): string
    {
        // TODO cache?
        $result = $this->dbLayer->buildAndQuery([
            'SELECT' => 'title',
            'FROM'   => 'articles',
            'WHERE'  => 'parent_id = ' . self::ROOT_ID,
        ]);

        return $this->dbLayer->result($result);
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

        $last = $urls = $parentIds = [];
        for ($i = 0; $row = $this->dbLayer->fetchAssoc($result); $i++) {
            $urls[$i]      = urlencode($row['url']);
            $parentIds[$i] = $row['parent_id'];

            $last[$i]['title']        = $row['title'];
            $last[$i]['parent_title'] = $row['parent_title'];
            $last[$i]['p_url']        = $row['p_url'];
            $last[$i]['time']         = $row['create_time'];
            $last[$i]['modify_time']  = $row['modify_time'];
            $last[$i]['favorite']     = $row['favorite'];
            $last[$i]['text']         = $row['excerpt'];
            $last[$i]['author']       = $row['author'] ?? '';
        }

        $urls = $this->getFullUrlsForArticles($parentIds, $urls);

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
