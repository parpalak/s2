<?php
/**
 * General blog page.
 *
 * @copyright 2007-2024 Roman Parpalak
 * @license MIT
 * @package s2_blog
 */

namespace s2_extensions\s2_blog\Controller;

use Lang;
use S2\Cms\Framework\ControllerInterface;
use S2\Cms\Pdo\DbLayer;
use S2\Cms\Template\HtmlTemplate;
use S2\Cms\Template\HtmlTemplateProvider;
use S2\Cms\Template\Viewer;
use s2_extensions\s2_blog\Lib;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

abstract class BlogController implements ControllerInterface
{
    protected string $template_id = 'blog.php';
    protected string $blogPath;
    protected string $blogTagsPath;

    abstract public function body(Request $request, HtmlTemplate $template): ?Response;

    public function __construct(
        protected DbLayer              $dbLayer,
        protected HtmlTemplateProvider $templateProvider,
        protected Viewer               $viewer,
        protected string               $tagsUrl,
        protected string               $blogUrl, // S2_BLOG_URL
        protected string               $blogTitle, // S2_BLOG_TITLE
    )
    {
        $this->blogPath     = s2_link(str_replace(urlencode('/'), '/', urlencode($this->blogUrl)) . '/'); // S2_BLOG_PATH
        $this->blogTagsPath = $this->blogPath . urlencode($this->tagsUrl) . '/'; // S2_BLOG_TAGS_PATH

        Lang::load('s2_blog', function () {
            if (file_exists(__DIR__ . '/../lang/' . S2_LANGUAGE . '.php'))
                return require __DIR__ . '/../lang/' . S2_LANGUAGE . '.php';
            else
                return require __DIR__ . '/../lang/English.php';
        });
    }

    public function handle(Request $request): Response
    {
        $template = $this->templateProvider->getTemplate($this->template_id, 's2_blog');

        $template
            ->putInPlaceholder('commented', 0)
            ->putInPlaceholder('class', 's2_blog')
            ->putInPlaceholder('rss_link', ['<link rel="alternate" type="application/rss+xml" title="' .
                s2_htmlencode(Lang::get('RSS link title', 's2_blog')) . '" href="' .
                s2_link(str_replace(urlencode('/'), '/', urlencode($this->blogUrl)) . '/rss.xml') . '" />'])
        ;

        if ($template->hasPlaceholder('<!-- s2_blog_navigation -->')) {
            $template->registerPlaceholder('<!-- s2_blog_navigation -->', $this->blog_navigation($request));
        }

        $result = $this->body($request, $template);
        if ($result !== null) {
            return $result;
        }

        $headTitle = $template->getFromPlaceholder('head_title');
        $template->putInPlaceholder('head_title', $headTitle === null ? $this->blogTitle : $headTitle . ' - ' . $this->blogTitle);

        return $template->toHttpResponse();
    }

    public function getPosts(array $additionalQueryParts, bool $sortAsc = true, string $sortField = 'create_time'): string
    {
        // Obtaining posts
        $sub_query         = [
            'SELECT' => 'count(*)',
            'FROM'   => 's2_blog_comments AS c',
            'WHERE'  => 'c.post_id = p.id AND shown = 1',
        ];
        $raw_query_comment = $this->dbLayer->build($sub_query);

        $sub_query      = [
            'SELECT' => 'u.name',
            'FROM'   => 'users AS u',
            'WHERE'  => 'u.id = p.user_id',
        ];
        $raw_query_user = $this->dbLayer->build($sub_query);

        $query = [
            'SELECT' => 'p.create_time, p.title, p.text, p.url, p.id, p.commented, p.favorite, (' . $raw_query_comment . ') AS comment_num, (' . $raw_query_user . ') AS author, p.label',
            'FROM'   => 's2_blog_posts AS p',
            'WHERE'  => 'p.published = 1' . (isset($additionalQueryParts['WHERE']) ? ' AND ' . $additionalQueryParts['WHERE'] : '')
        ];
        if (isset($additionalQueryParts['JOINS'])) {
            $query['JOINS'] = $additionalQueryParts['JOINS'];
        }

        if (isset($additionalQueryParts['SELECT'])) {
            $query['SELECT'] .= ', ' . $additionalQueryParts['SELECT'];
        }

        $result = $this->dbLayer->buildAndQuery($query);

        $posts = $merge_labels = $labels = $ids = $sort_array = [];
        while ($row = $this->dbLayer->fetchAssoc($result)) {
            $posts[$row['id']]  = $row;
            $ids[]              = $row['id'];
            $sort_array[]       = $row[$sortField];
            $labels[$row['id']] = $row['label'];
            if ($row['label']) {
                $merge_labels[$row['label']] = 1;
            }
        }
        if (\count($posts) === 0) {
            return '';
        }

        $see_also = $tags = [];
        Lib::posts_links($ids, $merge_labels, $see_also, $tags);

        array_multisort($sort_array, $sortAsc ? SORT_ASC : SORT_DESC, $ids);

        $output = '';
        foreach ($ids as $id) {
            $post               = &$posts[$id];
            $link               = $this->blogPath . date('Y/m/d/', $post['create_time']) . urlencode($post['url']);
            $post['link']       = $link;
            $post['title_link'] = $link;
            $post['time']       = s2_date_time($post['create_time']);
            $post['tags']       = $tags[$id] ?? [];

            $post['see_also'] = [];
            if (!empty($labels[$id]) && isset($see_also[$labels[$id]])) {
                $label_copy = $see_also[$labels[$id]];
                if (isset($label_copy[$id])) {
                    unset($label_copy[$id]);
                }
                $post['see_also'] = $label_copy;
            }

            $output .= $this->viewer->render('post', $post, 's2_blog');
        }

        return $output;
    }

    public function blog_navigation(Request $request)
    {
        $request_uri = $request->getPathInfo();

        $cur_url = str_replace('%2F', '/', urlencode($request_uri));

        if (file_exists(S2_CACHE_DIR . 's2_blog_navigation.php')) {
            include S2_CACHE_DIR . 's2_blog_navigation.php';
        }

        $now = time();

        if (empty($s2_blog_navigation) || !isset($s2_blog_navigation_time) || $s2_blog_navigation_time < $now - 900) {
            $s2_blog_navigation = array('title' => Lang::get('Navigation', 's2_blog'));

            // Last posts on the blog main page
            $s2_blog_navigation['last'] = array(
                'title' => sprintf(Lang::get('Nav last', 's2_blog'), S2_MAX_ITEMS ? S2_MAX_ITEMS : 10),
                'link'  => S2_BLOG_PATH,
            );

            // Check for favorite posts
            $query = array(
                'SELECT' => '1',
                'FROM'   => 's2_blog_posts',
                'WHERE'  => 'published = 1 AND favorite = 1',
                'LIMIT'  => '1'
            );
            ($hook = s2_hook('fn_s2_blog_navigation_pre_is_favorite_qr')) ? eval($hook) : null;
            $result = $this->dbLayer->buildAndQuery($query);

            if ($this->dbLayer->fetchRow($result))
                $s2_blog_navigation['favorite'] = array(
                    'title' => Lang::get('Nav favorite', 's2_blog'),
                    'link'  => S2_BLOG_PATH . urlencode(S2_FAVORITE_URL) . '/',
                );

            // Fetch important tags
            $s2_blog_navigation['tags_header'] = array(
                'title' => Lang::get('Nav tags', 's2_blog'),
                'link'  => S2_BLOG_TAGS_PATH,
            );

            $query = array(
                'SELECT'   => 't.name, t.url, count(t.tag_id)',
                'FROM'     => 'tags AS t',
                'JOINS'    => array(
                    array(
                        'INNER JOIN' => 's2_blog_post_tag AS pt',
                        'ON'         => 't.tag_id = pt.tag_id'
                    ),
                    array(
                        'INNER JOIN' => 's2_blog_posts AS p',
                        'ON'         => 'p.id = pt.post_id'
                    )
                ),
                'WHERE'    => 't.s2_blog_important = 1 AND p.published = 1',
                'GROUP BY' => 't.tag_id',
                'ORDER BY' => '3 DESC',
            );
            ($hook = s2_hook('fn_s2_blog_navigation_pre_get_tags_qr')) ? eval($hook) : null;
            $result = $this->dbLayer->buildAndQuery($query);

            $tags = array();
            while ($tag = $this->dbLayer->fetchAssoc($result))
                $tags[] = array(
                    'title' => $tag['name'],
                    'link'  => S2_BLOG_TAGS_PATH . urlencode($tag['url']) . '/',
                );

            $s2_blog_navigation['tags'] = $tags;

            // Try to remove very old cache (maybe the file is not writable but removable)
            if (isset($s2_blog_navigation_time) && $s2_blog_navigation_time < $now - 86400)
                @unlink(S2_CACHE_DIR . 's2_blog_navigation.php');

            // Output navigation array as PHP code
            try {
                s2_overwrite_file_skip_locked(
                    S2_CACHE_DIR . 's2_blog_navigation.php',
                    '<?php' . "\n\n" . '$s2_blog_navigation_time = ' . $now . ';' . "\n\n" . '$s2_blog_navigation = ' . var_export($s2_blog_navigation, true) . ';'
                );
            } catch (\RuntimeException $e) {
                // noop
            }
        }

        foreach ($s2_blog_navigation as &$item) {
            if (\is_array($item)) {
                if (isset($item['link'])) {
                    $item['is_current'] = $item['link'] == S2_URL_PREFIX . $cur_url;
                } else {
                    foreach ($item as &$sub_item) {
                        if (\is_array($sub_item) && isset($sub_item['link'])) {
                            $sub_item['is_current'] = $sub_item['link'] == S2_URL_PREFIX . $cur_url;
                        }
                    }
                }
            }
        }

        return $this->viewer->render('navigation', $s2_blog_navigation, 's2_blog');
    }
}
