<?php
/**
 * List of blog tags.
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


class TagsPageController extends BlogController
{
    public function body (Request $request, HtmlTemplate $template): ?Response
    {
        if ($request->attributes->get('slash') !== '/') {
            return new RedirectResponse(s2_link($request->getPathInfo() . '/'), Response::HTTP_MOVED_PERMANENTLY);
        }

        $template->registerPlaceholder('<!-- s2_blog_navigation -->', '');

		if ($template->hasPlaceholder('<!-- s2_blog_calendar -->')) {
            $template->registerPlaceholder('<!-- s2_blog_calendar -->', Lib::calendar(date('Y'), date('m'), '0'));
        }

        $query = [
            'SELECT'	=> 'tag_id, name, url',
            'FROM'		=> 'tags'
        ];
        $result = $this->dbLayer->buildAndQuery($query);

        $tag_name = $tag_url = $tag_count = [];
        while ($row = $this->dbLayer->fetchAssoc($result)) {
            $tag_name[$row['tag_id']] = $row['name'];
            $tag_url[$row['tag_id']] = $row['url'];
            $tag_count[$row['tag_id']] = 0;
        }

        $query = [
            'SELECT'	=> 'pt.tag_id',
            'FROM'		=> 's2_blog_post_tag AS pt',
            'JOINS'		=> [
                [
                    'INNER JOIN'	=> 's2_blog_posts AS p',
                    'ON'			=> 'p.id = pt.post_id'
                ]
            ],
            'WHERE'		=> 'p.published = 1'
        ];
        $result = $this->dbLayer->buildAndQuery($query);

        while ($row = $this->dbLayer->fetchRow($result)) {
            $tag_count[$row[0]] = 1 + ($tag_count[$row[0]] ?? 0);
        }

        arsort($tag_count);

        $tags = [];
        foreach ($tag_count as $id => $num) {
            if ($num) {
                $tags[] = [
                    'title' => $tag_name[$id],
                    'link'  => S2_BLOG_TAGS_PATH . urlencode($tag_url[$id]) . '/',
                    'num'   => $num,
                ];
            }
        }

        $template->putInPlaceholder('text', $this->viewer->render('tags_list', ['tags' => $tags]));

        $template->addBreadCrumb(\Model::main_page_title(), s2_link('/'));
        if ($this->blogUrl !== '') {
            $template->addBreadCrumb(Lang::get('Blog', 's2_blog'), $this->blogPath);
        }
        $template->addBreadCrumb(Lang::get('Tags'));

        $template
            ->putInPlaceholder('head_title', Lang::get('Tags'))
            ->putInPlaceholder('title', Lang::get('Tags'))
            ->setLink('up', $this->blogPath)
        ;

        return null;
	}
}
