<?php
/**
 * @copyright 2007-2024 Roman Parpalak
 * @license MIT
 * @package S2
 */

declare(strict_types=1);

namespace S2\Cms\Model;

use S2\Cms\Pdo\DbLayer;
use S2\Cms\Pdo\DbLayerException;

readonly class CommentProvider
{
    public function __construct(
        private DbLayer         $dbLayer,
        private ArticleProvider $articleProvider,
        private UrlBuilder      $urlBuilder,
        private bool            $showComments
    ) {
    }

    /**
     * Fetching last comments (for template placeholders)
     *
     * @throws \S2\Cms\Pdo\DbLayerException
     */
    public function lastArticleComments(): array
    {
        if (!$this->showComments) {
            return [];
        }

        // row_number() alternative. why not to use it?
        $countSubQuery = [
            'SELECT' => 'count(*) + 1',
            'FROM'   => 'art_comments AS c1',
            'WHERE'  => 'shown = 1 AND c1.article_id = c.article_id AND c1.time < c.time'
        ];
        $countRawQuery = $this->dbLayer->build($countSubQuery);

        $query  = [
            'SELECT'   => 'c.time, a.url, a.title, c.nick, a.parent_id, (' . $countRawQuery . ') AS count',
            'FROM'     => 'articles AS a',
            'JOINS'    => [
                [
                    'INNER JOIN' => 'art_comments AS c',
                    'ON'         => 'c.article_id = a.id'
                ],
            ],
            'WHERE'    => 'published = 1 AND commented = 1 AND shown = 1',
            'ORDER BY' => 'time DESC',
            'LIMIT'    => '5'
        ];
        $result = $this->dbLayer->buildAndQuery($query);

        $nickNames = $titles = $parentIds = $urls = $counts = [];
        while ($row = $this->dbLayer->fetchAssoc($result)) {
            $nickNames[] = $row['nick'];
            $titles[]    = $row['title'];
            $parentIds[] = $row['parent_id'];
            $urls[]      = urlencode($row['url']);
            $counts[]    = $row['count'];
        }

        $urls = $this->articleProvider->getFullUrlsForArticles($parentIds, $urls);

        $output = [];
        foreach ($urls as $k => $url) {
            $output[] = array(
                'title'  => $titles[$k],
                'link'   => $this->urlBuilder->link($url) . '#' . $counts[$k],
                'author' => $nickNames[$k],
            );
        }

        return $output;
    }

    /**
     * Displaying last discussions (for template placeholders).
     *
     * Last discussions are the articles with the highest number of comments that were created in the last month.
     *
     * @return array
     * @throws \S2\Cms\Pdo\DbLayerException
     */
    public function lastDiscussions(): array
    {
        if (!$this->showComments) {
            return [];
        }

        $activeArticlesSubQuery = [
            'SELECT'   => 'c.article_id AS article_id, count(c.article_id) AS comment_num, max(c.id) AS max_id',
            'FROM'     => 'art_comments AS c',
            'WHERE'    => 'c.shown = 1 AND c.time > ' . strtotime('-1 month midnight'),
            'GROUP BY' => 'c.article_id',
            'ORDER BY' => 'comment_num DESC',
        ];
        $activeArticlesRawQuery = $this->dbLayer->build($activeArticlesSubQuery);

        $query  = [
            'SELECT' => 'a.url, a.title, a.parent_id, c2.nick, c2.time',
            'FROM'   => 'articles AS a, (' . $activeArticlesRawQuery . ') AS c1',
            'JOINS'  => [
                [
                    'INNER JOIN' => 'art_comments AS c2',
                    'ON'         => 'c2.id = c1.max_id'
                ],
            ],
            'WHERE'  => 'c1.article_id = a.id AND a.commented = 1 AND a.published = 1',
            'LIMIT'  => '10',
        ];
        $result = $this->dbLayer->buildAndQuery($query);

        $titles = $parent_ids = $urls = $nicks = $time = array();
        while ($row = $this->dbLayer->fetchAssoc($result)) {
            $titles[]     = $row['title'];
            $parent_ids[] = $row['parent_id'];
            $urls[]       = urlencode($row['url']);
            $nicks[]      = $row['nick'];
            $time[]       = $row['time'];
        }

        $urls = $this->articleProvider->getFullUrlsForArticles($parent_ids, $urls);

        $output = [];
        foreach ($urls as $k => $url) {
            $output[] = [
                'title' => $titles[$k],
                'link'  => $this->urlBuilder->link($url),
                'hint'  => $nicks[$k] . ' (' . s2_date_time($time[$k]) . ')',
            ];
        }

        return $output;
    }

    /**
     * @throws DbLayerException
     */
    public function getPendingCommentsCount(): int
    {
        $result = $this->dbLayer->buildAndQuery([
            'SELECT' => 'COUNT(*)',
            'FROM'   => 'art_comments',
            'WHERE'  => 'shown = 0 AND sent = 0',
        ]);

        return $this->dbLayer->result($result);
    }
}
