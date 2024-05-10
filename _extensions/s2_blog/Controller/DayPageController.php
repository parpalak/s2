<?php
/**
 * Blog posts for a day.
 *
 * @copyright 2007-2024 Roman Parpalak
 * @license MIT
 * @package s2_blog
 */

declare(strict_types=1);

namespace s2_extensions\s2_blog\Controller;

use Lang;
use S2\Cms\Template\HtmlTemplate;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DayPageController extends BlogController
{
    public function body(Request $request, HtmlTemplate $template): ?Response
    {
        $year  = (int)($textYear = $request->attributes->get('year'));
        $month = (int)($textMonth = $request->attributes->get('month'));
        $day   = (int)($textDay = $request->attributes->get('day'));

        if ($template->hasPlaceholder('<!-- s2_blog_calendar -->')) {
            $template->registerPlaceholder('<!-- s2_blog_calendar -->', $this->calendarBuilder->calendar($year, $month, $day));
        }

        $template->putInPlaceholder('title', '');

        $startTime = mktime(0, 0, 0, $month, $day, $year);
        $endTime   = $startTime + 60 * 60 * 24;

        $output = $this->getPosts([
            'WHERE' => 'p.create_time < ' . $endTime . ' AND p.create_time >= ' . $startTime
        ]);

        if ($output === '') {
            $template->markAsNotFound();
            $output = '<p>' . Lang::get('Not found', 's2_blog') . '</p>';
        }

        $template
            ->putInPlaceholder('text', $output)
            ->setLink('up', $this->blogUrlBuilder->monthFromTimestamp($startTime))
            ->putInPlaceholder('head_title', s2_date($startTime))
        ;

        $template->addBreadCrumb($this->articleProvider->mainPageTitle(), $this->urlBuilder->link('/'));
        if (!$this->blogUrlBuilder->blogIsOnTheSiteRoot()) {
            $template->addBreadCrumb(Lang::get('Blog', 's2_blog'), $this->blogUrlBuilder->main());
        }
        $template
            ->addBreadCrumb($textYear, $this->blogUrlBuilder->year($year))
            ->addBreadCrumb($textMonth, $this->blogUrlBuilder->month($year, $month))
            ->addBreadCrumb($textDay)
        ;

        return null;
    }
}
