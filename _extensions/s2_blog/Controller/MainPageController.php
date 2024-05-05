<?php
/**
 * Main blog page with last posts.
 *
 * @copyright 2007-2024 Roman Parpalak
 * @license MIT
 * @package s2_blog
 */

namespace s2_extensions\s2_blog\Controller;

use Lang;
use S2\Cms\Model\ArticleProvider;
use S2\Cms\Model\UrlBuilder;
use S2\Cms\Pdo\DbLayer;
use S2\Cms\Template\HtmlTemplate;
use S2\Cms\Template\HtmlTemplateProvider;
use S2\Cms\Template\Viewer;
use s2_extensions\s2_blog\Lib;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class MainPageController extends BlogController
{
    public function __construct(
        DbLayer              $dbLayer,
        ArticleProvider      $articleProvider,
        UrlBuilder           $urlBuilder,
        HtmlTemplateProvider $templateProvider,
        Viewer               $viewer,
        string               $tagsUrl,
        string               $blogUrl,
        string               $blogTitle,
        private readonly int $itemsPerPage,
    ) {
        parent::__construct($dbLayer, $articleProvider, $urlBuilder, $templateProvider, $viewer, $tagsUrl, $blogUrl, $blogTitle);
    }

    public function handle(Request $request): Response
    {
        $s2_blog_skip      = (int)$request->attributes->get('page', 0);
        $this->template_id = $s2_blog_skip > 0 ? 'blog.php' : 'blog_main.php';

        return parent::handle($request);
    }

    public function body(Request $request, HtmlTemplate $template): ?Response
    {
        if ($request->attributes->get('slash') !== '/') {
            return new RedirectResponse($this->urlBuilder->link($request->getPathInfo() . '/'), Response::HTTP_MOVED_PERMANENTLY);
        }

        $skipLastPostsNum = (int)$request->attributes->get('page', 0);
        if ($skipLastPostsNum < 0) {
            $skipLastPostsNum = 0;
        }

        if ($template->hasPlaceholder('<!-- s2_blog_calendar -->')) {
            $template->registerPlaceholder('<!-- s2_blog_calendar -->', Lib::calendar(date('Y'), date('m'), '0'));
        }

        $postsPerPage = $this->itemsPerPage ?: 10;
        $posts        = Lib::last_posts_array($postsPerPage, $skipLastPostsNum, true);

        $output = '';
        $i      = 0;
        foreach ($posts as $post) {
            $i++;
            if ($i > $postsPerPage) {
                break;
            }

            $output .= $this->viewer->render('post', $post, 's2_blog');
        }

        $paging = '';
        if ($skipLastPostsNum > 0) {
            $prevLink = $this->blogPath . ($skipLastPostsNum > $postsPerPage ? 'skip/' . ($skipLastPostsNum - $postsPerPage) : '');
            $template->setLink('prev', $prevLink);
            $paging = '<a href="' . $prevLink . '">' . Lang::get('Here') . '</a> ';
            // TODO think about back_forward
        }
        if ($i > $postsPerPage) {
            $nextLink = S2_BLOG_PATH . 'skip/' . ($skipLastPostsNum + $postsPerPage);
            $template->setLink('next', $nextLink);
            $paging .= '<a href="' . $nextLink . '">' . Lang::get('There') . '</a>';
        }

        if ($paging !== '') {
            $output .= '<p class="s2_blog_pages">' . $paging . '</p>';
        }

        $template->putInPlaceholder('text', $output);

        $template->addBreadCrumb($this->articleProvider->mainPageTitle(), $this->urlBuilder->link('/'));
        if ($this->blogUrl !== '') {
            $template->addBreadCrumb(Lang::get('Blog', 's2_blog'), $skipLastPostsNum > 0 ? $this->blogPath : null);
        }

        if ($skipLastPostsNum > 0) {
            $template->setLink('up', $this->blogPath);
        } else {
            $template->putInPlaceholder('meta_description', $this->blogTitle);
            if ($this->blogUrl !== '') {
                $template->setLink('up', $this->urlBuilder->link('/'));
            }
        }

        return null;
    }
}
