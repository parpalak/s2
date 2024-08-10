<?php
/**
 * Content for blog placeholders.
 *
 * @copyright 2007-2024 Roman Parpalak
 * @license   http://opensource.org/licenses/MIT MIT
 * @package   s2_blog
 */

namespace s2_extensions\s2_blog\Model;

use Psr\Cache\InvalidArgumentException;
use S2\Cms\Pdo\DbLayer;
use S2\Cms\Pdo\DbLayerException;
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
                'title' => sprintf($this->translator->trans('Nav last'), $this->maxItems ?: 10),
                'link'  => $this->blogUrlBuilder->main(),
            ];

            // Check for favorite posts
            $result = $this->dbLayer->buildAndQuery([
                'SELECT' => '1',
                'FROM'   => 's2_blog_posts',
                'WHERE'  => 'published = 1 AND favorite = 1',
                'LIMIT'  => '1'
            ]);

            if ($this->dbLayer->fetchRow($result)) {
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

            $result = $this->dbLayer->buildAndQuery([
                'SELECT'   => 't.name, t.url, count(t.id)',
                'FROM'     => 'tags AS t',
                'JOINS'    => [
                    [
                        'INNER JOIN' => 's2_blog_post_tag AS pt',
                        'ON'         => 't.id = pt.tag_id'
                    ],
                    [
                        'INNER JOIN' => 's2_blog_posts AS p',
                        'ON'         => 'p.id = pt.post_id'
                    ]
                ],
                'WHERE'    => 't.s2_blog_important = 1 AND p.published = 1',
                'GROUP BY' => 't.id',
                'ORDER BY' => '3 DESC',
            ]);

            $tags = [];
            while ($tag = $this->dbLayer->fetchAssoc($result)) {
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

        $raw_query1 = $this->dbLayer->build([
            'SELECT' => 'count(*) + 1',
            'FROM'   => 's2_blog_comments AS c1',
            'WHERE'  => 'shown = 1 AND c1.post_id = c.post_id AND c1.time < c.time'
        ]);

        $result = $this->dbLayer->buildAndQuery([
            'SELECT'   => 'time, url, title, nick, create_time, (' . $raw_query1 . ') AS count',
            'FROM'     => 's2_blog_comments AS c',
            'JOINS'    => [
                [
                    'INNER JOIN' => 's2_blog_posts AS p',
                    'ON'         => 'c.post_id = p.id'
                ]
            ],
            'WHERE'    => 'commented = 1 AND published = 1 AND shown = 1',
            'ORDER BY' => 'time DESC',
            'LIMIT'    => '5'
        ]);

        $output      = [];
        $request_uri = $this->urlPrefix . $this->requestStack->getCurrentRequest()?->getPathInfo();
        while ($row = $this->dbLayer->fetchAssoc($result)) {
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

        $raw_query1 = $this->dbLayer->build([
            'SELECT'   => 'c.post_id AS post_id, count(c.post_id) AS comment_num,  max(c.id) AS max_id',
            'FROM'     => 's2_blog_comments AS c',
            'WHERE'    => 'c.shown = 1 AND c.time > ' . strtotime('-1 month midnight'),
            'GROUP BY' => 'c.post_id',
            'ORDER BY' => 'comment_num DESC',
        ]);

        $query  = [
            'SELECT' => 'p.create_time, p.url, p.title, c1.comment_num AS comment_num, c2.nick, c2.time',
            'FROM'   => 's2_blog_posts AS p, (' . $raw_query1 . ') AS c1',
            'JOINS'  => [
                [
                    'INNER JOIN' => 's2_blog_comments AS c2',
                    'ON'         => 'c2.id = c1.max_id'
                ],
            ],
            'WHERE'  => 'c1.post_id = p.id AND p.commented = 1 AND p.published = 1',
            'LIMIT'  => '10',
        ];
        $result = $this->dbLayer->buildAndQuery($query);

        $output      = [];
        $request_uri = $this->urlPrefix . $this->requestStack->getCurrentRequest()?->getPathInfo();
        while ($row = $this->dbLayer->fetchAssoc($result)) {
            $cur_url  = $this->blogUrlBuilder->postFromTimestamp($row['create_time'], $row['url']);
            $output[] = array(
                'title'      => $row['title'],
                'link'       => $cur_url,
                'hint'       => $row['nick'] . ' (' . s2_date_time($row['time']) . ')',
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
        $raw_query = $this->dbLayer->build([
            'SELECT' => 'p.id',
            'FROM'   => 's2_blog_posts AS p',
            'JOINS'  => [
                [
                    'INNER JOIN' => 's2_blog_post_tag AS pt',
                    'ON'         => 'p.id = pt.post_id AND p.published = 1'
                ]
            ],
            'WHERE'  => 'pt.tag_id = atg.tag_id',
            'LIMIT'  => '1'
        ]);

        $result = $this->dbLayer->buildAndQuery([
            'SELECT' => 't.name, t.url as url',
            'FROM'   => 'tags AS t',
            'JOINS'  => [
                [
                    'INNER JOIN' => 'article_tag AS atg',
                    'ON'         => 'atg.tag_id = t.id'
                ]
            ],
            'WHERE'  => 'atg.article_id = :id AND (' . $raw_query . ') IS NOT NULL',
        ], ['id' => $articleId]);

        $links = [];
        while ($row = $this->dbLayer->fetchAssoc($result)) {
            $links[] = [
                'title' => $row['name'],
                'link'  => $this->blogUrlBuilder->tag($row['url']),
            ];
        }

        return $links;
    }
}
