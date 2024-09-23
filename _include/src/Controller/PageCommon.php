<?php
/**
 * Displays a page stored in DB.
 *
 * @copyright 2007-2024 Roman Parpalak
 * @license   MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Controller;

use S2\Cms\Framework\ControllerInterface;
use S2\Cms\Framework\Exception\NotFoundException;
use S2\Cms\Model\Article\ArticleRenderedEvent;
use S2\Cms\Model\ArticleProvider;
use S2\Cms\Model\UrlBuilder;
use S2\Cms\Pdo\DbLayer;
use S2\Cms\Recommendation\RecommendationProvider;
use S2\Cms\Template\HtmlTemplateProvider;
use S2\Cms\Template\Viewer;
use S2\Rose\Entity\ExternalId;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;


readonly class PageCommon implements ControllerInterface
{
    public function __construct(
        private DbLayer                  $dbLayer,
        private ArticleProvider          $articleProvider,
        private EventDispatcherInterface $eventDispatcher,
        private UrlBuilder               $urlBuilder,
        private HtmlTemplateProvider     $htmlTemplateProvider,
        private RecommendationProvider   $recommendationProvider,
        private Viewer                   $viewer,
        private bool                     $useHierarchy,
        private bool                     $showComments,
        private string                   $tagsUrl,
        private int                      $maxItems,
        private bool                     $debug,
    ) {
    }

    public function handle(Request $request): Response
    {
        $request_uri = $request->getPathInfo();

        $request_array = explode('/', $request_uri);   //   []/[dir1]/[dir2]/[dir3]/[file1]
        $request_array = array_map('rawurldecode', $request_array);

        // Correcting trailing slash and the rest of URL
        if (!$this->useHierarchy && \count($request_array) > 2) {
            return new RedirectResponse($this->urlBuilder->link('/' . $request_array[1]), Response::HTTP_MOVED_PERMANENTLY);
        }

        $was_end_slash = str_ends_with($request_uri, '/');

        $bread_crumbs = [];

        $parent_path = '';
        $parent_id   = ArticleProvider::ROOT_ID;
        $parent_num  = \count($request_array) - 1 - (int)$was_end_slash;

        $template_id = '';

        if ($this->useHierarchy) {
            $urls = array_unique($request_array);
            $urls = array_map([$this->dbLayer, 'escape'], $urls);

            $query  = [
                'SELECT' => 'id, parent_id, title, template',
                'FROM'   => 'articles',
                'WHERE'  => 'url IN (\'' . implode('\', \'', $urls) . '\') AND published=1'
            ];
            $result = $this->dbLayer->buildAndQuery($query);

            $nodes = $this->dbLayer->fetchAssocAll($result);

            /**
             * Walking through the page parents
             * 1. We ensure all of them are published
             * 2. We build "bread crumbs"
             * 3. We determine the template of the page
             */
            for ($i = 0; $i < $parent_num; $i++) {
                $parent_path .= rawurlencode($request_array[$i]) . '/';

                $cur_node       = [];
                $found_node_num = 0;
                foreach ($nodes as $node) {
                    if ($node['parent_id'] == $parent_id) {
                        $cur_node = $node;
                        $found_node_num++;
                    }
                }

                if ($found_node_num === 0) {
                    throw new NotFoundException();
                }
                if ($found_node_num > 1) {
                    error(\Lang::get('DB repeat items') . ($this->debug ? ' (parent_id=' . $parent_id . ', url="' . s2_htmlencode($request_array[$i]) . '")' : ''));
                }

                $parent_id = $cur_node['id'];
                if ($cur_node['template'] != '') {
                    $template_id = $cur_node['template'];
                }

                $bread_crumbs[] = [
                    'link'  => $this->urlBuilder->link($parent_path),
                    'title' => $cur_node['title']
                ];
            }
        } else {
            $parent_path = '/';
            $i           = 1;
        }
        // Path to the requested page (without trailing slash)
        $current_path = $parent_path . rawurlencode($request_array[$i]);

        $subquery           = [
            'SELECT' => '1',
            'FROM'   => 'articles AS a1',
            'WHERE'  => 'a1.parent_id = a.id AND a1.published = 1',
            'LIMIT'  => '1'
        ];
        $raw_query_children = $this->dbLayer->build($subquery);

        $subquery         = [
            'SELECT' => 'u.name',
            'FROM'   => 'users AS u',
            'WHERE'  => 'u.id = a.user_id'
        ];
        $raw_query_author = $this->dbLayer->build($subquery);

        $query  = [
            'SELECT' => 'a.id, a.title, a.meta_keys as meta_keywords, a.meta_desc as meta_description, a.excerpt as excerpt, a.pagetext as text, a.create_time as date, favorite, commented, template, (' . $raw_query_children . ') IS NOT NULL AS children_exist, (' . $raw_query_author . ') AS author',
            'FROM'   => 'articles AS a',
            'WHERE'  => 'url=\'' . $this->dbLayer->escape($request_array[$i]) . '\'' . ($this->useHierarchy ? ' AND parent_id=' . $parent_id : '') . ' AND published=1'
        ];
        $result = $this->dbLayer->buildAndQuery($query);

        $page = $this->dbLayer->fetchAssoc($result);

        // Error handling
        if (!$page) {
            throw new NotFoundException();
        }

        if ($this->dbLayer->fetchAssoc($result)) {
            error(\Lang::get('DB repeat items') . ($this->debug ? ' (parent_id=' . $parent_id . ', url="' . $request_array[$i] . '")' : ''));
        }

        if ($page['template']) {
            $template_id = $page['template'];
        }

        if ($template_id === '') {
            if ($this->useHierarchy) {
                $bread_crumbs[] = [
                    'link'  => $this->urlBuilder->link($parent_path),
                    'title' => $page['title'],
                ];

                error(sprintf(\Lang::get('Error no template'), implode('<br />', array_map(static function ($a) {
                    return '<a href="' . $a['link'] . '">' . s2_htmlencode($a['title']) . '</a>';
                }, $bread_crumbs))));
            } else {
                error(\Lang::get('Error no template flat'));
            }
        }

        if ($this->useHierarchy && $parent_num && $page['children_exist'] != $was_end_slash) {
            return new RedirectResponse($this->urlBuilder->link($current_path . (!$was_end_slash ? '/' : '')), Response::HTTP_MOVED_PERMANENTLY);
        }

        $articleId = (int)$page['id'];
        $template  = $this->htmlTemplateProvider->getTemplate($template_id);
        $template
            ->putInPlaceholder('id', md5('article_' . $articleId)) // for comments form
            ->putInPlaceholder('meta_keywords', $page['meta_keywords'])
            ->putInPlaceholder('meta_description', $page['meta_description'])
            ->putInPlaceholder('excerpt', $page['excerpt'])
            ->putInPlaceholder('text', $page['text'])
            ->putInPlaceholder('date', $page['date'])
            ->putInPlaceholder('commented', $page['commented'])
            ->putInPlaceholder('author', $page['author'])
            ->putInPlaceholder('canonical_path', $current_path . ($was_end_slash ? '/' : ''))
        ;

        $bread_crumbs[] = [
            'title' => $page['title']
        ];
        $template->putInPlaceholder('title', s2_htmlencode($page['title']));

        if (!empty($page['author'])) {
            $template->putInPlaceholder('author', s2_htmlencode($page['author']));
        }

        if ($this->useHierarchy) {
            foreach ($bread_crumbs as $crumb) {
                $template->addBreadCrumb($crumb['title'], $crumb['link'] ?? null);
            }
            $template->setLink('top', $this->urlBuilder->link('/'));

            if (\count($bread_crumbs) > 1) {
                $template->setLink('up', $this->urlBuilder->link($parent_path));
                $template->putInPlaceholder(
                    'section_link',
                    '<a href="' . $this->urlBuilder->link($parent_path) . '">' . $bread_crumbs[\count($bread_crumbs) - 2]['title'] . '</a>'
                );
            }
        }

        // Dealing with sections, subsections, neighbours
        if (
            $this->useHierarchy
            && $page['children_exist']
            && (
                $template->hasPlaceholder('<!-- s2_subarticles -->')
                || $template->hasPlaceholder('<!-- s2_menu_children -->')
                || $template->hasPlaceholder('<!-- s2_menu_subsections -->')
                || $template->hasPlaceholder('<!-- s2_navigation_link -->')
            )
        ) {
            // It's a section. We have to fetch subsections and articles.

            // Fetching children
            $subquery   = [
                'SELECT' => 'a1.id',
                'FROM'   => 'articles AS a1',
                'WHERE'  => 'a1.parent_id = a.id AND a1.published = 1',
                'LIMIT'  => '1'
            ];
            $raw_query1 = $this->dbLayer->build($subquery);

            $sort_order = SORT_DESC;
            $query      = [
                'SELECT'   => 'title, url, (' . $raw_query1 . ') IS NOT NULL AS children_exist, id, excerpt, favorite, create_time, parent_id',
                'FROM'     => 'articles AS a',
                'WHERE'    => 'parent_id = ' . $articleId . ' AND published = 1',
                'ORDER BY' => 'priority'
            ];
            $result     = $this->dbLayer->buildAndQuery($query);

            $subarticles = $subsections = $sort_array = [];
            while ($row = $this->dbLayer->fetchAssoc($result)) {
                if ($row['children_exist']) {
                    // The child is a subsection
                    $item = [
                        'id'       => $row['id'],
                        'title'    => $row['title'],
                        'link'     => $this->urlBuilder->link($current_path . '/' . rawurlencode($row['url']) . '/'),
                        'date'     => s2_date($row['create_time']),
                        'excerpt'  => $row['excerpt'],
                        'favorite' => $row['favorite'],
                    ];

                    $subsections[] = $item;
                } else {
                    // The child is an article
                    $item       = array(
                        'id'       => $row['id'],
                        'title'    => $row['title'],
                        'link'     => $this->urlBuilder->link($current_path . '/' . rawurlencode($row['url'])),
                        'date'     => s2_date($row['create_time']),
                        'excerpt'  => $row['excerpt'],
                        'favorite' => $row['favorite'],
                    );
                    $sort_field = $row['create_time'];

                    $subarticles[] = $item;
                    $sort_array[]  = $sort_field;
                }
            }

            $sections_text = '';
            if (\count($subsections) > 0) {
                // There are subsections in the section
                $template->putInPlaceholder('menu_subsections', $this->viewer->render('menu_block', [
                    'title' => \Lang::get('Subsections'),
                    'menu'  => $subsections,
                    'class' => 'menu_subsections',
                ]));

                foreach ($subsections as $item) {
                    $sections_text .= $this->viewer->render('subarticles_item', $item);
                }
            }

            $articles_text = '';
            if (\count($subarticles) > 0) {
                // There are articles in the section
                $template->putInPlaceholder('menu_children', $this->viewer->render('menu_block', [
                    'title' => \Lang::get('In this section'),
                    'menu'  => $subarticles,
                    'class' => 'menu_children',
                ]));

                ($sort_order == SORT_DESC) ? arsort($sort_array) : asort($sort_array);

                if ($this->maxItems > 0) {
                    // Paging navigation
                    $page_num = $request->query->get('p', 1) - 1;
                    if ($page_num < 0) {
                        $page_num = 0;
                    }

                    $start = $page_num * $this->maxItems;
                    if ($start >= \count($subarticles)) {
                        $page_num = $start = 0;
                    }

                    $total_pages = ceil(1.0 * \count($subarticles) / $this->maxItems);

                    $link_nav = [];
                    $paging   = s2_paging($page_num + 1, $total_pages, $this->urlBuilder->link(str_replace('%', '%%', $current_path . '/'), ['p=%d']), $link_nav) . "\n";
                    foreach ($link_nav as $rel => $href) {
                        $template->setLink($rel, $href);
                    }

                    $sort_array = \array_slice($sort_array, $start, $this->maxItems, true);
                }

                foreach ($sort_array as $index => $value) {
                    $articles_text .= $this->viewer->render('subarticles_item', $subarticles[$index]);
                }

                if ($this->maxItems) {
                    $articles_text .= $paging;
                }
            }

            $template->putInPlaceholder('subcontent', $this->viewer->render('subarticles', [
                'articles' => $articles_text,
                'sections' => $sections_text,
            ]));
        }

        if (
            $this->useHierarchy
            && !$page['children_exist']
            && (
                $template->hasPlaceholder('<!-- s2_menu_siblings -->')
                || $template->hasPlaceholder('<!-- s2_back_forward -->')
                || $template->hasPlaceholder('<!-- s2_navigation_link -->')
            )
        ) {
            // It's an article. We have to fetch other articles in the parent section

            // Fetching "siblings"
            $subquery            = [
                'SELECT' => '1',
                'FROM'   => 'articles AS a2',
                'WHERE'  => 'a2.parent_id = a.id AND a2.published = 1',
                'LIMIT'  => '1'
            ];
            $raw_query_child_num = $this->dbLayer->build($subquery);

            $query  = [
                'SELECT'   => 'title, url, id, excerpt, create_time, parent_id',
                'FROM'     => 'articles AS a',
                'WHERE'    => 'parent_id = ' . $parent_id . ' AND published=1 AND (' . $raw_query_child_num . ') IS NULL',
                'ORDER BY' => 'priority'
            ];
            $result = $this->dbLayer->buildAndQuery($query);

            $neighbour_urls = $menu_articles = [];

            $i         = 0;
            $curr_item = -1;
            while ($row = $this->dbLayer->fetchAssoc($result)) {
                // A neighbour
                $url = $this->urlBuilder->link($parent_path . rawurlencode($row['url']));

                $menu_articles[] = [
                    'title'      => $row['title'],
                    'link'       => $url,
                    'is_current' => $articleId == $row['id'],
                ];

                if ($articleId == $row['id'])
                    $curr_item = $i;

                $neighbour_urls[] = $url;

                $i++;
            }

            if (\count($bread_crumbs) > 1) {
                $template->putInPlaceholder('menu_siblings', $this->viewer->render('menu_block', [
                    'title' => sprintf(\Lang::get('More in this section'), '<a href="' . $this->urlBuilder->link($parent_path) . '">' . $bread_crumbs[\count($bread_crumbs) - 2]['title'] . '</a>'),
                    'menu'  => $menu_articles,
                    'class' => 'menu_siblings',
                ]));
            }

            if ($curr_item !== -1) {
                if (isset($neighbour_urls[$curr_item - 1])) {
                    $template->setLink('prev', $neighbour_urls[$curr_item - 1]);
                }
                if (isset($neighbour_urls[$curr_item + 1])) {
                    $template->setLink('next', $neighbour_urls[$curr_item + 1]);
                }

                $template->putInPlaceholder('back_forward', [
                    'up'      => \count($bread_crumbs) <= 1 ? null : [
                        'title' => $bread_crumbs[\count($bread_crumbs) - 2]['title'],
                        'link'  => $this->urlBuilder->link($parent_path),
                    ],
                    'back'    => empty($menu_articles[$curr_item - 1]) ? null : [
                        'title' => $menu_articles[$curr_item - 1]['title'],
                        'link'  => $menu_articles[$curr_item - 1]['link'],
                    ],
                    'forward' => empty($menu_articles[$curr_item + 1]) ? null : [
                        'title' => $menu_articles[$curr_item + 1]['title'],
                        'link'  => $menu_articles[$curr_item + 1]['link'],
                    ],
                ]);
            }
        }

        // Tags
        if ($template->hasPlaceholder('<!-- s2_article_tags -->')) {
            $template->putInPlaceholder('article_tags', $this->tagged_articles($articleId));
        }

        if ($template->hasPlaceholder('<!-- s2_tags -->')) {
            $template->putInPlaceholder('tags', $this->get_tags($articleId));
        }

        // Recommendations
        if ($template->hasPlaceholder('<!-- s2_recommendations -->')) {
            [$recommendations, $log, $rawRecommendations] = $this->recommendationProvider->getRecommendations($request_uri, new ExternalId($articleId));
            $template->putInPlaceholder('recommendations', $this->viewer->render('recommendations', [
                'raw'     => $rawRecommendations,
                'content' => $recommendations,
                'log'     => $log,
            ]));
        }

        // Comments
        if ($page['commented'] && $this->showComments && $template->hasPlaceholder('<!-- s2_comments -->')) {
            $query  = [
                'SELECT'   => 'nick, time, email, show_email, good, text',
                'FROM'     => 'art_comments',
                'WHERE'    => 'article_id = ' . $articleId . ' AND shown = 1',
                'ORDER BY' => 'time'
            ];
            $result = $this->dbLayer->buildAndQuery($query);

            $comments = '';
            for ($i = 1; $row = $this->dbLayer->fetchAssoc($result); $i++) {
                $row['i'] = $i;
                $comments .= $this->viewer->render('comment', $row);
            }

            if ($comments !== '') {
                $template->putInPlaceholder('comments', $this->viewer->render('comments', ['comments' => $comments]));
            }
        }

        $this->eventDispatcher->dispatch(new ArticleRenderedEvent($template, $articleId));

        return $template->toHttpResponse();
    }

    private function tagged_articles(int $articleId): string
    {
        $query  = [
            'SELECT' => 't.id AS tag_id, name, t.url as url',
            'FROM'   => 'tags AS t',
            'JOINS'  => [
                [
                    'INNER JOIN' => 'article_tag AS atg',
                    'ON'         => 'atg.tag_id = t.id'
                ]
            ],
            'WHERE'  => 'atg.article_id = ' . $articleId
        ];
        $result = $this->dbLayer->buildAndQuery($query);

        $tag_names = $tag_urls = [];
        while ($row = $this->dbLayer->fetchAssoc($result)) {
            $tag_names[$row['tag_id']] = $row['name'];
            $tag_urls[$row['tag_id']]  = $row['url'];
        }

        if (\count($tag_urls) === 0) {
            return '';
        }

        $subquery   = [
            'SELECT' => '1',
            'FROM'   => 'articles AS a1',
            'WHERE'  => 'a1.parent_id = atg.article_id AND a1.published = 1',
            'LIMIT'  => '1'
        ];
        $raw_query1 = $this->dbLayer->build($subquery);

        $query  = [
            'SELECT' => 'title, tag_id, parent_id, url, a.id AS id, (' . $raw_query1 . ') IS NOT NULL AS children_exist',
            'FROM'   => 'articles AS a',
            'JOINS'  => [
                [
                    'INNER JOIN' => 'article_tag AS atg',
                    'ON'         => 'a.id = atg.article_id'
                ],
            ],
            'WHERE'  => 'atg.tag_id IN (' . implode(', ', array_keys($tag_names)) . ') AND a.published = 1'
//		'ORDER BY'	=> 'create_time'  // no temp table is created but order by ID is almost the same
        ];
        $result = $this->dbLayer->buildAndQuery($query);

        // Build article lists that have the same tags as our article

        $hasArticlesInList = false;

        $titles = $parent_ids = $urls = $tag_ids = $original_ids = [];
        while ($row = $this->dbLayer->fetchAssoc($result)) {
            if ($articleId <> $row['id']) {
                $hasArticlesInList = true;
            }
            $titles[]       = $row['title'];
            $parent_ids[]   = $row['parent_id'];
            $urls[]         = rawurlencode($row['url']) . (S2_USE_HIERARCHY && $row['children_exist'] ? '/' : '');
            $tag_ids[]      = $row['tag_id'];
            $original_ids[] = $row['id'];
        }

        if (\count($urls) === 0) {
            return '';
        }

        if ($hasArticlesInList) {
            $urls = $this->articleProvider->getFullUrlsForArticles($parent_ids, $urls);
        }

        // Sorting all obtained article links into groups by each tag
        $art_by_tags = [];

        foreach ($urls as $k => $url) {
            $art_by_tags[$tag_ids[$k]][] = [
                'title'      => $titles[$k],
                'link'       => $url,
                'is_current' => $original_ids[$k] == $articleId,
            ];
        }

        // Remove tags that have only one article
        foreach ($art_by_tags as $tag_id => $title_array) {
            if (\count($title_array) <= 1) {
                unset($art_by_tags[$tag_id]);
            }
        }

        $output = [];
        foreach ($art_by_tags as $tag_id => $articles) {
            $output[] = $this->viewer->render('menu_block', array(
                'title' => sprintf(\Lang::get('With this tag'), '<a href="' . $this->urlBuilder->link('/' . rawurlencode($this->tagsUrl) . '/' . rawurlencode($tag_urls[$tag_id]) . '/') . '">' . $tag_names[$tag_id] . '</a>'),
                'menu'  => $articles,
                'class' => 'article_tags',
            ));
        }

        return implode("\n", $output);
    }


    private function get_tags(int $articleId): string
    {
        $query  = [
            'SELECT' => 'name, url',
            'FROM'   => 'tags AS t',
            'JOINS'  => [
                [
                    'INNER JOIN' => 'article_tag AS at',
                    'ON'         => 'at.tag_id = t.id'
                ]
            ],
            'WHERE'  => 'at.article_id = ' . $articleId
        ];
        $result = $this->dbLayer->buildAndQuery($query);

        $tags = [];
        while ($row = $this->dbLayer->fetchAssoc($result)) {
            $tags[] = array(
                'title' => $row['name'],
                'link'  => $this->urlBuilder->link('/' . rawurlencode($this->tagsUrl) . '/' . rawurlencode($row['url']) . '/'),
            );
        }

        if (\count($tags) === 0) {
            return '';
        }

        return $this->viewer->render('tags', [
            'title' => \Lang::get('Tags'),
            'tags'  => $tags,
        ]);
    }
}
