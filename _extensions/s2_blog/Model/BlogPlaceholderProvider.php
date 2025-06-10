<?php
/**
 * Content for blog placeholders.
 *
 * @copyright 2007-2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   s2_blog
 */

namespace s2_extensions\s2_blog\Model;

use Psr\Cache\InvalidArgumentException;
use S2\Cms\Pdo\DbLayer;
use S2\Cms\Pdo\DbLayerException;
use S2\Cms\Template\Viewer;
use s2_extensions\s2_blog\BlogUrlBuilder;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class BlogPlaceholderProvider
{
    private const CACHE_KEY_NAVIGATION = 's2_blog_navigation';

    public function __construct(
        private DbLayer             $dbLayer,
        private BlogUrlBuilder      $blogUrlBuilder,
        private TranslatorInterface $translator,
        private Viewer              $viewer,
        private RequestStack        $requestStack,
        private CacheInterface      $cache,
        private bool                $showComments,
        private int                 $maxItems,
        private string              $urlPrefix,
    ) {
    }

    /**
     * @throws InvalidArgumentException
     */
    public function getBlogNavigationData(): array
    {
        $request_uri = $this->urlPrefix . $this->requestStack->getCurrentRequest()?->getPathInfo();

        $result = $this->cache->get(self::CACHE_KEY_NAVIGATION, function (ItemInterface $item) {
            $item->expiresAfter(900);

            $blogNavigation = ['title' => $this->translator->trans('Navigation')];

            // Last posts on the blog main page
            $blogNavigation['last'] = [
                'title' => \sprintf($this->translator->trans('Nav last'), $this->maxItems ?: 10),
                'link'  => $this->blogUrlBuilder->main(),
            ];

            // Check for favorite posts
            $result = $this->dbLayer->select('1')
                ->from('s2_blog_posts')
                ->where('published = 1')
                ->andWhere('favorite = 1')
                ->limit(1)
                ->execute()
            ;

            if ($result->fetchRow()) {
                $blogNavigation['favorite'] = [
                    'title' => $this->translator->trans('Nav favorite'),
                    'link'  => $this->blogUrlBuilder->favorite(),
                ];
            }

            // Fetch important tags
            $blogNavigation['tags_header'] = [
                'title' => $this->translator->trans('Nav tags'),
                'link'  => $this->blogUrlBuilder->tags(),
            ];

            $result = $this->dbLayer->select('t.name, t.url, count(t.id)')
                ->from('tags AS t')
                ->innerJoin('s2_blog_post_tag AS pt', 't.id = pt.tag_id')
                ->innerJoin('s2_blog_posts AS p', 'p.id = pt.post_id')
                ->where('t.s2_blog_important = 1')
                ->andWhere('p.published = 1')
                ->groupBy('t.id')
                ->orderBy('3 DESC')
                ->execute()
            ;

            $tags = [];
            while ($tag = $result->fetchAssoc()) {
                $tags[] = [
                    'title' => $tag['name'],
                    'link'  => $this->blogUrlBuilder->tag($tag['url']),
                ];
            }

            $blogNavigation['tags'] = $tags;

            return $blogNavigation;
        });

        foreach ($result as &$item) {
            if (\is_array($item)) {
                if (isset($item['link'])) {
                    $item['is_current'] = $item['link'] === $request_uri;
                } else {
                    foreach ($item as &$sub_item) {
                        if (\is_array($sub_item) && isset($sub_item['link'])) {
                            $sub_item['is_current'] = $sub_item['link'] === $request_uri;
                        }
                    }
                    unset($sub_item);
                }
            }
        }
        unset($item);

        return $result;
    }

    /**
     * @throws DbLayerException
     */
    public function getRecentComments(): array
    {
        if (!$this->showComments) {
            return [];
        }

        $raw_query1 = $this->dbLayer
            ->select('count(*) + 1')
            ->from('s2_blog_comments AS c1')
            ->where('shown = 1')
            ->andWhere('c1.post_id = c.post_id')
            ->andWhere('c1.time < c.time')
            ->getSql()
        ;

        $result = $this->dbLayer
            ->select('time, url, title, nick, create_time, (' . $raw_query1 . ') AS count')
            ->from('s2_blog_comments AS c')
            ->innerJoin('s2_blog_posts AS p', 'c.post_id = p.id')
            ->where('commented = 1')
            ->andWhere('published = 1')
            ->andWhere('shown = 1')
            ->orderBy('time DESC')
            ->limit(5)
            ->execute()
        ;

        $output      = [];
        $request_uri = $this->urlPrefix . $this->requestStack->getCurrentRequest()?->getPathInfo();
        while ($row = $result->fetchAssoc()) {
            $cur_url  = $this->blogUrlBuilder->postFromTimestamp($row['create_time'], $row['url']);
            $output[] = [
                'title'      => $row['title'],
                'link'       => $cur_url . '#' . $row['count'],
                'author'     => $row['nick'],
                'is_current' => $request_uri === $cur_url,
            ];
        }

        return $output;
    }

    /**
     * @throws DbLayerException
     */
    public function getRecentDiscussions(): array
    {
        if (!$this->showComments) {
            return [];
        }

        $rawQuery = $this->dbLayer
            ->select('c.post_id AS post_id, COUNT(c.post_id) AS comment_num,  MAX(c.id) AS max_id')
            ->from('s2_blog_comments AS c')
            ->where('c.shown = 1')
            ->andWhere('c.time > :time')
            ->setParameter('time', strtotime('-1 month midnight'))
            ->groupBy('c.post_id')
            ->orderBy('comment_num DESC')
            ->getSql()
        ;

        $result = $this->dbLayer
            ->select('p.create_time, p.url, p.title, c1.comment_num AS comment_num, c2.nick, c2.time')
            ->from('s2_blog_posts AS p, (' . $rawQuery . ') AS c1')
            ->innerJoin('s2_blog_comments AS c2', 'c2.id = c1.max_id')
            ->where('c1.post_id = p.id')
            ->andWhere('p.commented = 1')
            ->andWhere('p.published = 1')
            ->setParameter('time', strtotime('-1 month midnight'))
            ->limit(10)
            ->execute()
        ;

        $output      = [];
        $request_uri = $this->urlPrefix . $this->requestStack->getCurrentRequest()?->getPathInfo();
        while ($row = $result->fetchAssoc()) {
            $cur_url  = $this->blogUrlBuilder->postFromTimestamp($row['create_time'], $row['url']);
            $output[] = array(
                'title'      => $row['title'],
                'link'       => $cur_url,
                'hint'       => $row['nick'] . ' (' . $this->viewer->dateAndTime($row['time']) . ')',
                'is_current' => $request_uri === $cur_url,
            );
        }

        return $output;
    }

    /**
     * @throws DbLayerException
     */
    public function getBlogTagsForArticle(int $articleId): array
    {
        $rawQuery = $this->dbLayer
            ->select('p.id')
            ->from('s2_blog_posts AS p')
            ->innerJoin('s2_blog_post_tag AS pt', 'p.id = pt.post_id')
            ->where('p.published = 1')
            ->andWhere('pt.tag_id = atg.tag_id')
            ->limit(1)
            ->getSql()
        ;

        $result = $this->dbLayer->select('t.name, t.url as url')
            ->from('tags AS t')
            ->innerJoin('article_tag AS atg', 'atg.tag_id = t.id')
            ->where('atg.article_id = :id')
            ->setParameter('id', $articleId)
            ->andWhere('(' . $rawQuery . ') IS NOT NULL')
            ->execute()
        ;

        $links = [];
        while ($row = $result->fetchAssoc()) {
            $links[] = [
                'title' => $row['name'],
                'link'  => $this->blogUrlBuilder->tag($row['url']),
            ];
        }

        return $links;
    }
}
