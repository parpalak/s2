<?php
/**
 * General blog page.
 *
 * @copyright 2007-2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   s2_blog
 */

namespace s2_extensions\s2_blog\Controller;

use S2\Cms\Config\BoolProxy;
use S2\Cms\Config\StringProxy;
use S2\Cms\Framework\ControllerInterface;
use S2\Cms\Model\ArticleProvider;
use S2\Cms\Model\UrlBuilder;
use S2\Cms\Pdo\DbLayer;
use S2\Cms\Pdo\DbLayerException;
use S2\Cms\Template\HtmlTemplate;
use S2\Cms\Template\HtmlTemplateProvider;
use S2\Cms\Template\Viewer;
use s2_extensions\s2_blog\BlogUrlBuilder;
use s2_extensions\s2_blog\CalendarBuilder;
use s2_extensions\s2_blog\Model\PostProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

abstract class BlogController implements ControllerInterface
{
    protected string $template_id = 'blog.php';

    public function __construct(
        protected DbLayer              $dbLayer,
        protected CalendarBuilder      $calendarBuilder,
        protected BlogUrlBuilder       $blogUrlBuilder,
        protected ArticleProvider      $articleProvider,
        protected PostProvider         $postProvider,
        protected UrlBuilder           $urlBuilder,
        protected TranslatorInterface  $translator,
        protected HtmlTemplateProvider $templateProvider,
        protected Viewer               $viewer,
        protected StringProxy          $blogTitle,
        protected BoolProxy            $showComments,
        protected BoolProxy            $enabledComments,
    ) {
    }

    abstract public function body(Request $request, HtmlTemplate $template): ?Response;

    /**
     * @throws DbLayerException
     */
    public function handle(Request $request): Response
    {
        $template = $this->templateProvider->getTemplate($this->template_id, 's2_blog');

        /** @noinspection HtmlUnknownTarget */
        $template
            ->putInPlaceholder('commented', 0)
            ->putInPlaceholder('class', 's2_blog')
            ->putInPlaceholder('rss_link', [\sprintf(
                '<link rel="alternate" type="application/rss+xml" title="%s" href="%s" />',
                s2_htmlencode($this->translator->trans('RSS blog link title')),
                $this->blogUrlBuilder->main() . 'rss.xml'
            )])
        ;

        $result = $this->body($request, $template);
        if ($result !== null) {
            return $result;
        }

        $headTitle = $template->getFromPlaceholder('head_title');
        $blogTitle = $this->blogTitle->get();
        $template->putInPlaceholder('head_title', $headTitle === null ? $blogTitle : $headTitle . ' &mdash; ' . $blogTitle);

        return $template->toHttpResponse();
    }

    /**
     * @throws DbLayerException
     */
    public function getPosts(callable $queryModifier, bool $sortAsc = true, string $sortField = 'create_time'): string
    {
        // Obtaining posts
        $qb = $this->dbLayer
            ->select(
                'p.create_time', 'p.title', 'p.text', 'p.url', 'p.id', 'p.commented', 'p.favorite',
                '(' . $this->dbLayer
                    ->select('count(*)')
                    ->from('s2_blog_comments AS c')
                    ->where('c.post_id = p.id')
                    ->andWhere('c.shown = 1')
                    ->getSql() . ') AS comment_num',
                '(' . $this->dbLayer
                    ->select('u.name')
                    ->from('users AS u')
                    ->where('u.id = p.user_id')
                    ->getSql() . ') AS author',
                'p.label'
            )
            ->from('s2_blog_posts AS p')
            ->where('p.published = 1')
        ;

        $queryModifier($qb);

        $result = $qb->execute();

        $posts = $merge_labels = $labels = $ids = $sort_array = [];
        while ($row = $result->fetchAssoc()) {
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
        $this->postProvider->postsLinks($ids, $merge_labels, $see_also, $tags);

        array_multisort($sort_array, $sortAsc ? SORT_ASC : SORT_DESC, $ids);

        $showComments    = $this->showComments->get();
        $enabledComments = $this->enabledComments->get();
        $output = '';
        foreach ($ids as $id) {
            $post               = &$posts[$id];
            $link               = $this->blogUrlBuilder->postFromTimestamp((int)$post['create_time'], $post['url']);
            $post['link']       = $link;
            $post['title_link'] = $link;
            $post['time']       = $this->viewer->dateAndTime($post['create_time']);
            $post['tags']       = $tags[$id] ?? [];

            $post['see_also'] = [];
            if (!empty($labels[$id]) && isset($see_also[$labels[$id]])) {
                $label_copy = $see_also[$labels[$id]];
                if (isset($label_copy[$id])) {
                    unset($label_copy[$id]);
                }
                $post['see_also'] = $label_copy;
            }
            $post['favoritePostsUrl'] = $this->blogUrlBuilder->favorite();
            $post['showComments']     = $showComments;
            $post['enabledComments']  = $enabledComments;

            $output .= $this->viewer->render('post', $post, 's2_blog');
        }

        return $output;
    }
}
