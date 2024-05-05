<?php
/**
 * Blog posts for a day.
 *
 * @copyright 2007-2024 Roman Parpalak
 * @license MIT
 * @package s2_blog
 */

namespace s2_extensions\s2_blog\Controller;

use Lang;
use S2\Cms\Template\HtmlTemplate;
use s2_extensions\s2_blog\Lib;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DayPageController extends BlogController
{
    public function body(Request $request, HtmlTemplate $template): ?Response
    {
        $params = $request->attributes->all();

        $year  = $params['year'];
        $month = $params['month'];
        $day   = $params['day'];

        if ($template->hasPlaceholder('<!-- s2_blog_calendar -->')) {
            $template->registerPlaceholder('<!-- s2_blog_calendar -->', Lib::calendar($year, $month, $day));
        }

        $template->putInPlaceholder('title', '');

        $startTime = mktime(0, 0, 0, $month, $day, $year);
        $endTime   = mktime(0, 0, 0, $month, $day + 1, $year);

        $output = $this->getPosts([
            'WHERE' => 'p.create_time < ' . $endTime . ' AND p.create_time >= ' . $startTime
        ]);

        if ($output === '') {
            $template->markAsNotFound();
            $output = '<p>' . Lang::get('Not found', 's2_blog') . '</p>';
        }

        $template
            ->putInPlaceholder('text', $output)
            ->setLink('up', $this->blogPath . date('Y/m/', $startTime))
            ->putInPlaceholder('head_title', s2_date($startTime))
        ;

        $template->addBreadCrumb($this->articleProvider->mainPageTitle(), $this->urlBuilder->link('/'));
        if ($this->blogUrl !== '') {
            $template->addBreadCrumb(Lang::get('Blog', 's2_blog'), $this->blogPath);
        }
        $template
            ->addBreadCrumb($year, $this->blogPath . $year . '/')
            ->addBreadCrumb($month, $this->blogPath . $year . '/' . $month . '/')
            ->addBreadCrumb($day)
        ;

        return null;
    }
}
