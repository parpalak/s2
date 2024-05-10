<?php
/**
 * Favorite blog posts.
 *
 * @copyright 2007-2024 Roman Parpalak
 * @license MIT
 * @package s2_blog
 */

namespace s2_extensions\s2_blog\Controller;

use Lang;
use S2\Cms\Template\HtmlTemplate;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class FavoritePageController extends BlogController
{
    public function body(Request $request, HtmlTemplate $template): ?Response
    {
        if ($request->attributes->get('slash') !== '/') {
            return new RedirectResponse($this->urlBuilder->link($request->getPathInfo() . '/'), Response::HTTP_MOVED_PERMANENTLY);
        }

        if ($template->hasPlaceholder('<!-- s2_blog_calendar -->')) {
            $template->registerPlaceholder('<!-- s2_blog_calendar -->', $this->calendarBuilder->calendar());
        }

        $output = $this->getPosts([
            'SELECT' => '2 AS favorite',
            'WHERE'  => 'favorite = 1',
        ], false);

        if ($output === '') {
            // TODO Why 404 in favorite? Where is the message?
            $template->markAsNotFound();
        }

        // Bread crumbs
        $template->addBreadCrumb($this->articleProvider->mainPageTitle(), $this->urlBuilder->link('/'));
        if (!$this->blogUrlBuilder->blogIsOnTheSiteRoot()) {
            $template->addBreadCrumb(Lang::get('Blog', 's2_blog'), $this->blogUrlBuilder->main());
        }
        $template->addBreadCrumb(Lang::get('Favorite'));

        $template
            ->putInPlaceholder('head_title', Lang::get('Favorite'))
            ->putInPlaceholder('title', Lang::get('Favorite'))
            ->putInPlaceholder('text', $output)
        ;

        $template->setLink('up', $this->blogUrlBuilder->main());

        return null;
    }
}
