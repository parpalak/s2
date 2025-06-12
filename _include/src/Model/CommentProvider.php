<?php
/**
 * @copyright 2007-2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Model;

use S2\Cms\Pdo\DbLayer;
use S2\Cms\Pdo\DbLayerException;
use S2\Cms\Template\Viewer;

readonly class CommentProvider
{
    public function __construct(
        private DbLayer         $dbLayer,
        private ArticleProvider $articleProvider,
        private UrlBuilder      $urlBuilder,
        private Viewer          $viewer,
        private bool            $showComments
    ) {
    }

    /**
     * Fetching last comments (for template placeholders)
     *
     * @throws DbLayerException
     */
    public function lastArticleComments(): array
    {
        if (!$this->showComments) {
            return [];
        }

        // Ordinal number of the comment to be selected. Used in the hash of the comment link.
        $countRawQuery = $this->dbLayer
            ->select('COUNT(*) + 1')
            ->from('art_comments AS c1')
            ->where('shown = 1')
            ->andWhere('c1.article_id = c.article_id')
            ->andWhere('c1.time < c.time')
            ->getSql()
        ;

        $result = $this->dbLayer
            ->select('c.time, a.url, a.title, c.nick, a.parent_id, (' . $countRawQuery . ') AS count')
            ->from('articles AS a')
            ->innerJoin('art_comments AS c', 'c.article_id = a.id')
            ->where('published = 1')
            ->andWhere('commented = 1')
            ->andWhere('shown = 1')
            ->orderBy('time DESC')
            ->limit(5)
            ->execute()
        ;

        $nickNames = $titles = $parentIds = $urls = $counts = [];
        while ($row = $result->fetchAssoc()) {
            $nickNames[] = $row['nick'];
            $titles[]    = $row['title'];
            $parentIds[] = $row['parent_id'];
            $urls[]      = rawurlencode($row['url']);
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
     * @throws DbLayerException
     */
    public function lastDiscussions(): array
    {
        if (!$this->showComments) {
            return [];
        }

        $activeArticlesRawQuery = $this->dbLayer
            ->select('c.article_id AS article_id, COUNT(c.article_id) AS comment_num, MAX(c.id) AS max_id')
            ->from('art_comments AS c')
            ->where('c.shown = 1')
            ->andWhere('c.time > :time')
            ->groupBy('c.article_id')
            ->orderBy('comment_num DESC')
            ->getSql()
        ;

        // NOTE: no sorting is specified, random order is used. What order should be used?
        $result = $this->dbLayer
            ->select('a.url, a.title, a.parent_id, c2.nick, c2.time')
            ->from('articles AS a, (' . $activeArticlesRawQuery . ') AS c1')
            ->innerJoin('art_comments AS c2', 'c2.id = c1.max_id')
            ->where('c1.article_id = a.id')
            ->andWhere('a.commented = 1')
            ->andWhere('a.published = 1')
            ->limit(10)
            ->setParameter(':time', strtotime('-1 month midnight'))
            ->execute()
        ;

        $titles = $parent_ids = $urls = $nicks = $time = [];
        while ($row = $result->fetchAssoc()) {
            $titles[]     = $row['title'];
            $parent_ids[] = $row['parent_id'];
            $urls[]       = rawurlencode($row['url']);
            $nicks[]      = $row['nick'];
            $time[]       = $row['time'];
        }

        $urls = $this->articleProvider->getFullUrlsForArticles($parent_ids, $urls);

        $output = [];
        foreach ($urls as $k => $url) {
            $output[] = [
                'title' => $titles[$k],
                'link'  => $this->urlBuilder->link($url),
                'hint'  => $nicks[$k] . ' (' . $this->viewer->dateAndTime($time[$k]) . ')',
            ];
        }

        return $output;
    }

    /**
     * @throws DbLayerException
     */
    public function getPendingCommentsCount(): int
    {
        $result = $this->dbLayer
            ->select('COUNT(*)')
            ->from('art_comments')
            ->where('shown = 0 AND sent = 0')
            ->execute()
        ;

        return $result->result();
    }
}
