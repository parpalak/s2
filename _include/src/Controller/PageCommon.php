<?php
/**
 * Displays a page stored in DB.
 *
 * @copyright 2007-2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Controller;

use S2\Cms\Framework\ControllerInterface;
use S2\Cms\Framework\Exception\ConfigurationException;
use S2\Cms\Framework\Exception\NotFoundException;
use S2\Cms\Helper\StringHelper;
use S2\Cms\Model\Article\ArticleRenderedEvent;
use S2\Cms\Model\ArticleProvider;
use S2\Cms\Model\UrlBuilder;
use S2\Cms\Pdo\DbLayer;
use S2\Cms\Pdo\DbLayerException;
use S2\Cms\Template\HtmlTemplateProvider;
use S2\Cms\Template\Viewer;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;


readonly class PageCommon implements ControllerInterface
{
    public function __construct(
        private DbLayer                  $dbLayer,
        private ArticleProvider          $articleProvider,
        private EventDispatcherInterface $eventDispatcher,
        private UrlBuilder               $urlBuilder,
        private TranslatorInterface      $translator,
        private HtmlTemplateProvider     $htmlTemplateProvider,
        private Viewer                   $viewer,
        private bool                     $useHierarchy,
        private bool                     $showComments,
        private string                   $tagsUrl,
        private string                   $favoriteUrl,
        private int                      $maxItems,
        private bool                     $debug,
    ) {
    }

    /**
     * @throws DbLayerException
     * @throws NotFoundException
     * @throws ConfigurationException
     * @throws BadRequestException
     */
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

            $result = $this->dbLayer->select('id, parent_id, title, template')
                ->from('articles')
                ->where('url IN (' . implode(',', array_fill(0, \count($urls), '?')) . ')')
                ->andWhere('published=1')
                ->execute($urls)
            ;

            $nodes = $result->fetchAssocAll();

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
                    if ($node['parent_id'] === $parent_id) {
                        $cur_node = $node;
                        $found_node_num++;
                    }
                }

                if ($found_node_num === 0) {
                    throw new NotFoundException();
                }
                if ($found_node_num > 1) {
                    throw new ConfigurationException(
                        $this->translator->trans('DB repeat items') . ($this->debug ? ' (parent_id=' . $parent_id . ', url="' . s2_htmlencode($request_array[$i]) . '")' : ''),
                        $this->translator->trans('Error encountered')
                    );
                }

                $parent_id = $cur_node['id'];
                if ($cur_node['template'] !== '') {
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

        $raw_query_children = $this->dbLayer
            ->select('1')
            ->from('articles AS a1')
            ->where('a1.parent_id = a.id')
            ->andWhere('a1.published = 1')
            ->limit(1)
            ->getSql()
        ;

        $raw_query_author = $this->dbLayer
            ->select('u.name')
            ->from('users AS u')
            ->where('u.id = a.user_id')
            ->getSql()
        ;

        $qb = $this->dbLayer
            ->select('a.id, a.title, a.meta_keys as meta_keywords, a.meta_desc as meta_description')
            ->addSelect('a.excerpt as excerpt, a.pagetext as text, a.create_time as date')
            ->addSelect('favorite, commented, template')
            ->addSelect('(' . $raw_query_children . ') IS NOT NULL AS children_exist, (' . $raw_query_author . ') AS author')
            ->from('articles AS a')
            ->where('url = :url')->setParameter('url', $request_array[$i])
            ->andWhere('published = 1')
        ;
        if ($this->useHierarchy) {
            $qb->andWhere('parent_id = :parent_id')->setParameter('parent_id', $parent_id);
        }

        $result = $qb->execute();
        $page   = $result->fetchAssoc();

        // Error handling
        if (!$page) {
            throw new NotFoundException();
        }

        if ($result->fetchAssoc()) {
            throw new ConfigurationException(
                $this->translator->trans('DB repeat items') . ($this->debug ? ' (parent_id=' . $parent_id . ', url="' . s2_htmlencode($request_array[$i]) . '")' : ''),
                $this->translator->trans('Error encountered')
            );
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

                $errorMessage = \sprintf($this->translator->trans('Error no template'), implode('<br />', array_map(static function ($a) {
                    return '<a href="' . $a['link'] . '">' . s2_htmlencode($a['title']) . '</a>';
                }, $bread_crumbs)));
            } else {
                $errorMessage = $this->translator->trans('Error no template flat');
            }

            throw new ConfigurationException(
                $errorMessage,
                $this->translator->trans('Error encountered')
            );
        }

        if ($this->useHierarchy && $parent_num && $was_end_slash !== (bool)$page['children_exist']) {
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
            ->putInPlaceholder('favorite', $page['favorite'])
            ->putInPlaceholder('commented', $page['commented'])
            ->putInPlaceholder('author', $page['author'])
            ->putInPlaceholder('canonical_path', $current_path . ($was_end_slash ? '/' : ''))
        ;

        if ($page['favorite'] === 1) {
            $template->putInPlaceholder('favorite_link', $this->urlBuilder->link('/' . rawurlencode($this->favoriteUrl) . '/'));
        }

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
            $raw_query1 = $this->dbLayer
                ->select('a1.id')
                ->from('articles AS a1')
                ->where('a1.parent_id = a.id')
                ->andWhere('published = 1')
                ->limit(1)
                ->getSql()
            ;

            $sort_order = SORT_DESC;

            $result = $this->dbLayer
                ->select('title, url, (' . $raw_query1 . ') IS NOT NULL AS children_exist, id, excerpt, favorite, create_time, parent_id')
                ->from('articles AS a')
                ->where('parent_id = :parent_id')->setParameter('parent_id', $articleId)
                ->andWhere('published = 1')
                ->orderBy('priority')
                ->execute()
            ;

            $subarticles = $subsections = $sort_array = [];
            while ($row = $result->fetchAssoc()) {
                if ($row['children_exist']) {
                    // The child is a subsection
                    $item = [
                        'id'            => $row['id'],
                        'title'         => $row['title'],
                        'link'          => $this->urlBuilder->link($current_path . '/' . rawurlencode($row['url']) . '/'),
                        'favorite_link' => $this->urlBuilder->link('/' . rawurlencode($this->favoriteUrl) . '/'),
                        'date'          => $this->viewer->date($row['create_time']),
                        'excerpt'       => $row['excerpt'],
                        'favorite'      => $row['favorite'],
                    ];

                    $subsections[] = $item;
                } else {
                    // The child is an article
                    $item       = array(
                        'id'            => $row['id'],
                        'title'         => $row['title'],
                        'link'          => $this->urlBuilder->link($current_path . '/' . rawurlencode($row['url'])),
                        'favorite_link' => $this->urlBuilder->link('/' . rawurlencode($this->favoriteUrl) . '/'),
                        'date'          => $this->viewer->date($row['create_time']),
                        'excerpt'       => $row['excerpt'],
                        'favorite'      => $row['favorite'],
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
                    'title' => $this->translator->trans('Subsections'),
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
                    'title' => $this->translator->trans('In this section'),
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

                    $total_pages = (int)ceil(1.0 * \count($subarticles) / $this->maxItems);

                    $link_nav = [];
                    $paging   = StringHelper::paging($page_num + 1, $total_pages, $this->urlBuilder->link(str_replace('%', '%%', $current_path . '/'), ['p=%d']), $link_nav) . "\n";
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
            $raw_query_child_num = $this->dbLayer
                ->select('1')
                ->from('articles AS a2')
                ->where('a2.parent_id = a.id')
                ->andWhere('a2.published = 1')
                ->limit(1)
                ->getSql()
            ;

            $result = $this->dbLayer
                ->select('title, url, id, excerpt, create_time, parent_id')
                ->from('articles AS a')
                ->where('parent_id = :parent_id')->setParameter('parent_id', $parent_id)
                ->andWhere('published = 1')
                ->andWhere('(' . $raw_query_child_num . ') IS NULL')
                ->orderBy('priority')
                ->execute()
            ;

            $neighbour_urls = $menu_articles = [];

            $i         = 0;
            $curr_item = -1;
            while ($row = $result->fetchAssoc()) {
                // A neighbor
                $url = $this->urlBuilder->link($parent_path . rawurlencode($row['url']));

                $menu_articles[] = [
                    'title'      => $row['title'],
                    'link'       => $url,
                    'is_current' => $articleId === $row['id'],
                ];

                if ($articleId === $row['id']) {
                    $curr_item = $i;
                }

                $neighbour_urls[] = $url;

                $i++;
            }

            if (\count($bread_crumbs) > 1) {
                $template->putInPlaceholder('menu_siblings', $this->viewer->render('menu_block', [
                    'title' => \sprintf($this->translator->trans('More in this section'), '<a href="' . $this->urlBuilder->link($parent_path) . '">' . $bread_crumbs[\count($bread_crumbs) - 2]['title'] . '</a>'),
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

        // Comments
        if ($page['commented'] && $this->showComments && $template->hasPlaceholder('<!-- s2_comments -->')) {
            $result = $this->dbLayer
                ->select('nick, time, email, show_email, good, text')
                ->from('art_comments')
                ->where('article_id = :article_id')->setParameter('article_id', $articleId)
                ->andWhere('shown = 1')
                ->orderBy('time')
                ->execute()
            ;

            $comments = '';
            for ($i = 1; $row = $result->fetchAssoc(); $i++) {
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

    /**
     * @throws DbLayerException
     */
    private function tagged_articles(int $articleId): string
    {
        $result = $this->dbLayer
            ->select('t.id AS tag_id, name, t.url as url')
            ->from('tags AS t')
            ->innerJoin('article_tag AS atg', 'atg.tag_id = t.id')
            ->where('atg.article_id = :article_id')->setParameter('article_id', $articleId)
            ->execute()
        ;

        $tag_names = $tag_urls = [];
        while ($row = $result->fetchAssoc()) {
            $tag_names[$row['tag_id']] = $row['name'];
            $tag_urls[$row['tag_id']]  = $row['url'];
        }

        if (\count($tag_urls) === 0) {
            return '';
        }

        $raw_query1 = $this->dbLayer
            ->select('1')
            ->from('articles AS a1')
            ->where('a1.parent_id = atg.article_id')
            ->andWhere('a1.published = 1')
            ->limit(1)
            ->getSql()
        ;

        $result = $this->dbLayer
            ->select('title, tag_id, parent_id, url, a.id AS id, (' . $raw_query1 . ') IS NOT NULL AS children_exist')
            ->from('articles AS a')
            ->innerJoin('article_tag AS atg', 'atg.article_id = a.id')
            ->where('atg.tag_id IN (' . implode(', ', array_fill(0, \count($tag_names), '?')) . ')')
            ->andWhere('a.published = 1')
            // ->orderBy('create_time') // no temp table is created but order by ID is almost the same
            ->execute(array_keys($tag_names))
        ;

        // Build article lists that have the same tags as our article

        $hasArticlesInList = false;

        $titles = $parent_ids = $urls = $tag_ids = $original_ids = [];
        while ($row = $result->fetchAssoc()) {
            if ($articleId !== $row['id']) {
                $hasArticlesInList = true;
            }
            $titles[]       = $row['title'];
            $parent_ids[]   = $row['parent_id'];
            $urls[]         = rawurlencode($row['url']) . ($this->useHierarchy && $row['children_exist'] ? '/' : '');
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
                'is_current' => $original_ids[$k] === $articleId,
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
                'title' => \sprintf($this->translator->trans('With this tag'), '<a href="' . $this->urlBuilder->link('/' . rawurlencode($this->tagsUrl) . '/' . rawurlencode($tag_urls[$tag_id]) . '/') . '">' . $tag_names[$tag_id] . '</a>'),
                'menu'  => $articles,
                'class' => 'article_tags',
            ));
        }

        return implode("\n", $output);
    }

    /**
     * @throws DbLayerException
     */
    private function get_tags(int $articleId): string
    {
        $result = $this->dbLayer
            ->select('name, url')
            ->from('tags AS t')
            ->innerJoin('article_tag AS at', 'at.tag_id = t.id')
            ->where('at.article_id = :article_id')->setParameter('article_id', $articleId)
            ->execute()
        ;

        $tags = [];
        while ($row = $result->fetchAssoc()) {
            $tags[] = array(
                'title' => $row['name'],
                'link'  => $this->urlBuilder->link('/' . rawurlencode($this->tagsUrl) . '/' . rawurlencode($row['url']) . '/'),
            );
        }

        if (\count($tags) === 0) {
            return '';
        }

        return $this->viewer->render('tags', [
            'title' => $this->translator->trans('Tags'),
            'tags'  => $tags,
        ]);
    }
}
