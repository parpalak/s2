<?php
/**
 * Updates search index when a visible article has been changed.
 *
 * @copyright 2007-2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Model;

use S2\Cms\Pdo\DbLayer;
use S2\Cms\Pdo\DbLayerException;
use S2\Cms\Template\Viewer;

readonly class ArticleProvider
{
    public const ROOT_ID = 0;

    public function __construct(
        private DbLayer    $dbLayer,
        private UrlBuilder $urlBuilder,
        private Viewer     $viewer,
        private string     $favoriteUrl,
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
     * @throws DbLayerException
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
            $idsToSelect = array_unique($parentIds);
            $result      = $this->dbLayer
                ->select('id, parent_id, url')
                ->from('articles')
                ->where('id IN (' . implode(', ', array_fill(0, \count($idsToSelect), '?')) . ')')
                ->andWhere('published = 1')
                ->execute($idsToSelect)
            ;

            while ($row = $result->fetchAssoc()) {
                // Well, the loop may seem not pretty much.
                // But $parent_ids values don't have to be unique, we have to process all duplicates.
                foreach ($parentIds as $k => $parentId) {
                    // Note: check for && !$parentsAreFound[$k] seems to be useless after adding array_unique in query before
                    if ($parentId === $row['id'] && !$parentsAreFound[$k]) {
                        $parentIds[$k]       = $row['parent_id'];
                        $urls[$k]            = rawurlencode($row['url']) . '/' . $urls[$k];
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
     * @throws DbLayerException
     */
    public function mainPageTitle(): string
    {
        // TODO cache?
        $result = $this->dbLayer
            ->select('title')
            ->from('articles')
            ->where('parent_id = ' . self::ROOT_ID)
            ->execute()
        ;

        return $result->result();
    }

    /**
     * Fetching last articles info (for template placeholders and RSS)
     *
     * @throws DbLayerException
     */
    public function lastArticlesList(?int $limit = 5): array
    {
        $raw_query_child_num = $this->dbLayer
            ->select('1')
            ->from('articles AS a2')
            ->where('a2.parent_id = a.id')
            ->andWhere('a2.published = 1')
            ->limit(1)
            ->getSql()
        ;

        $raw_query_user = $this->dbLayer
            ->select('u.name')
            ->from('users AS u')
            ->where('u.id = a.user_id')
            ->getSql()
        ;

        $qb = $this->dbLayer
            ->select('a.id, a.title, a.create_time, a.modify_time, a.excerpt, a.favorite, a.url')
            ->addSelect('a.parent_id, a1.title AS parent_title, a1.url AS p_url, (' . $raw_query_user . ') AS author')
            ->from('articles AS a')
            ->innerJoin('articles AS a1', 'a1.id = a.parent_id')
            ->where('(' . $raw_query_child_num . ') IS NULL')
            ->andWhere('a.create_time <> 0 OR a.modify_time <> 0')
            ->andWhere('a.published = 1')
            ->orderBy('a.create_time DESC')
        ;

        if ($limit !== null) {
            $qb->limit($limit);
        }

        $result = $qb->execute();

        $last = $urls = $parentIds = [];
        for ($i = 0; $row = $result->fetchAssoc(); $i++) {
            $urls[$i]      = rawurlencode($row['url']);
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
     * @throws DbLayerException
     */
    public function lastArticlesPlaceholder(int $limit): string
    {
        $articles = $this->lastArticlesList($limit);

        $output = '';
        if (\count($articles) > 0) {
            $favoriteLink = $this->urlBuilder->link('/' . rawurlencode($this->favoriteUrl) . '/');
            foreach ($articles as &$item) {
                $parentPath            = $this->useHierarchy ? preg_replace('#/\\K[^/]*$#', '', $item['rel_path']) : '/' . $item['p_url'];
                $item['date']          = $this->viewer->date($item['time']);
                $item['link']          = $this->urlBuilder->link($item['rel_path']);
                $item['parent_link']   = $this->urlBuilder->link($parentPath);
                $item['favorite_link'] = $favoriteLink;

                $output .= $this->viewer->render('last_articles_item', $item);
            }
            unset($item);
        }

        return $output;
    }

    /**
     * @throws DbLayerException
     */
    public function getTemplateList(): array
    {
        $result        = $this->dbLayer
            ->select('DISTINCT a.template')
            ->from('articles AS a')
            ->execute()
        ;
        $usedTemplates = $result->fetchColumn();

        return array_values(array_unique(array_merge([
            '',
            'site.php',
            'mainpage.php',
            'back_forward.php',
        ], $usedTemplates)));
    }

    /**
     * @throws DbLayerException
     */
    public function pathFromId(int $id, bool $visibleForAll = false): ?string
    {
        if ($id < 0) {
            return null;
        }

        if ($id === self::ROOT_ID) {
            return '';
        }

        $tablePrefix = $this->dbLayer->getPrefix();

        $sql = "
            WITH RECURSIVE path_cte AS (
                SELECT id, url, parent_id, 1 AS level
                FROM {$tablePrefix}articles
                WHERE id = :id" . ($visibleForAll ? " AND published = 1" : "") . "

                UNION ALL

                SELECT a.id, a.url, a.parent_id, p.level + 1
                FROM {$tablePrefix}articles a
                INNER JOIN path_cte p ON a.id = p.parent_id " . ($visibleForAll ? " AND a.published = 1" : "") . "
            )
            SELECT url, parent_id FROM path_cte ORDER BY level DESC
        ";

        $result = $this->dbLayer->query($sql, [
            ':id' => $id,
        ]);

        $urls = [];

        $rootIsFound = false;
        while ($row = $this->dbLayer->fetchAssoc($result)) {
            $urls[] = rawurlencode($row['url']);
            if ($row['parent_id'] === self::ROOT_ID) {
                $rootIsFound = true;
            }
        }

        if (!$rootIsFound) {
            return null;
        }

        $path = implode('/', $urls);
        return $path === '' ? '/' : $path;
    }

    /**
     * @throws DbLayerException
     */
    public function articleFromPath(string $path, bool $publishedOnly): ?array
    {
        $pathArray = explode('/', $path);   // e.g. []/[dir1]/[dir2]/[dir3]/[file1]
        $pathArray = array_map('rawurldecode', $pathArray);

        // Remove the last empty element
        if ($pathArray[\count($pathArray) - 1] === '') {
            unset($pathArray[\count($pathArray) - 1]);
        }

        if (!$this->useHierarchy) {
            $pathArray = \count($pathArray) > 0 ? [$pathArray[1]] : [''];
        }

        $id        = self::ROOT_ID;
        $title     = null;
        $commented = null;

        // Walking through page parents
        foreach ($pathArray as $pathItem) {
            $qb = $this->dbLayer
                ->select('a.id, a.title, a.commented')
                ->from('articles AS a')
                ->where('url = :url')->setParameter('url', $pathItem)
            ;

            if ($this->useHierarchy) {
                $qb->andWhere('parent_id = :id')->setParameter('id', $id);
            }

            if ($publishedOnly) {
                $qb->andWhere('published = 1');
            }

            $row = $qb->execute()->fetchRow();
            if (!\is_array($row)) {
                return null;
            }

            [$id, $title, $commented] = $row;
        }

        return ['id' => $id, 'title' => $title, 'commented' => $commented];
    }

    /**
     * @throws DbLayerException
     */
    public function checkUrlAndTemplateStatus(int $id): array
    {
        $result = $this->dbLayer
            ->select('parent_id, url, template')
            ->from('articles')
            ->where('id = :id')->setParameter('id', $id)
            ->execute()
        ;

        [$parentId, $url, $template] = $result->fetchRow();

        $templateStatus = (!$this->useHierarchy || $template !== '') ? 'ok' : 'empty';

        if ($parentId === self::ROOT_ID) {
            return ['mainpage', $templateStatus];
        }

        if ($url === '') {
            return ['empty', $templateStatus];
        }

        $qb = $this->dbLayer
            ->select('COUNT(*)')
            ->from('articles AS a')
            ->where('a.url = :url')->setParameter('url', $url)
        ;

        if ($this->useHierarchy) {
            $qb->andWhere('a.parent_id = :id')->setParameter('id', $parentId);
        }

        if ($qb->execute()->result() !== 1) {
            // NOTE: seems that this condition must be also checked above in if ($parentId === self::ROOT_ID) {} for root items.
            // However, somewhere must be a more strict constraint that allows only one root item.
            return ['not_unique', $templateStatus];
        }

        return ['ok', $templateStatus];
    }

    /**
     * @throws DbLayerException
     */
    public function getCommentNum(int $id, bool $includeHidden): int
    {
        $qb = $this->dbLayer
            ->select('COUNT(*)')
            ->from('art_comments')
            ->where('article_id = :article_id')->setParameter('article_id', $id)
        ;

        if (!$includeHidden) {
            $qb->andWhere('shown = 1');
        }

        return (int)$qb->execute()->result();
    }
}
