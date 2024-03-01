<?php
/**
 * Favorite blog posts.
 *
 * @copyright 2007-2024 Roman Parpalak
 * @license MIT
 * @package s2_blog
 */

namespace s2_extensions\s2_blog\Controller;

use \Lang;
use S2\Cms\Template\HtmlTemplate;
use s2_extensions\s2_blog\Lib;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;


class FavoritePageController extends BlogController
{
    public function body (Request $request, HtmlTemplate $template): ?Response
    {
        if ($request->attributes->get('slash') !== '/') {
            return new RedirectResponse(s2_link($request->getPathInfo() . '/'), Response::HTTP_MOVED_PERMANENTLY);
        }

		if ($template->hasPlaceholder('<!-- s2_blog_calendar -->')) {
            $template->putInPlaceholder('s2_blog_calendar', Lib::calendar(date('Y'), date('m'), '0'));
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
        $template->addBreadCrumb(\Model::main_page_title(), s2_link('/'));
        if ($this->blogUrl !== '') {
            $template->addBreadCrumb(Lang::get('Blog', 's2_blog'), $this->blogPath);
        }
        $template->addBreadCrumb(Lang::get('Favorite'));

        $template
            ->putInPlaceholder('head_title', Lang::get('Favorite'))
            ->putInPlaceholder('title', Lang::get('Favorite'))
            ->putInPlaceholder('text', $output)
        ;

        $template->setLink('up', $this->blogPath);

        return null;
	}
}
