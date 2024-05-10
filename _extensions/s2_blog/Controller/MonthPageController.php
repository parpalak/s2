<?php
/**
 * Blog posts for a month.
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
use s2_extensions\s2_blog\BlogUrlBuilder;
use s2_extensions\s2_blog\CalendarBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class MonthPageController extends BlogController
{
    public function __construct(
        DbLayer              $dbLayer,
        CalendarBuilder      $calendarBuilder,
        BlogUrlBuilder       $blogUrlBuilder,
        ArticleProvider      $articleProvider,
        UrlBuilder           $urlBuilder,
        HtmlTemplateProvider $templateProvider,
        Viewer               $viewer,
        string               $blogTitle,
        private readonly int $startYear,
    ) {
        parent::__construct($dbLayer, $calendarBuilder, $blogUrlBuilder, $articleProvider, $urlBuilder, $templateProvider, $viewer, $blogTitle);
    }

    public function body(Request $request, HtmlTemplate $template): ?Response
    {
        $year  = $request->attributes->get('year');
        $month = $request->attributes->get('month');

        if ($template->hasPlaceholder('<!-- s2_blog_calendar -->')) {
            $template->registerPlaceholder('<!-- s2_blog_calendar -->', $this->calendarBuilder->calendar((int)$year, (int)$month, 0));
        }

        $template->putInPlaceholder('title', '');

        $startTime = mktime(0, 0, 0, (int)$month, 1, (int)$year);
        $endTime   = mktime(0, 0, 0, (int)$month + 1, 1, (int)$year);
        $prevTime  = mktime(0, 0, 0, (int)$month - 1, 1, (int)$year);

        $template->setLink('up', $this->blogUrlBuilder->year((int)$year));

        $paging = '';
        if ($prevTime >= mktime(0, 0, 0, 1, 1, $this->startYear)) {
            $prevLink = $this->blogUrlBuilder->monthFromTimestamp($prevTime);
            $template->setLink('prev', $prevLink);
            $paging = '<a href="' . $prevLink . '">' . Lang::get('Here') . '</a> ';
        }
        if ($endTime < time()) {
            $nextLink = $this->blogUrlBuilder->monthFromTimestamp($endTime);
            $template->setLink('next', $nextLink);
            $paging .= '<a href="' . $nextLink . '">' . Lang::get('There') . '</a>';
            // TODO think about back_forward template
        }

        if ($paging !== '') {
            $paging = '<p class="s2_blog_pages">' . $paging . '</p>';
        }

        $output = $this->getPosts([
            'WHERE' => 'p.create_time < ' . $endTime . ' AND p.create_time >= ' . $startTime
        ]);

        if ($output === '') {
            $template->markAsNotFound();
            $output = '<p>' . Lang::get('Not found', 's2_blog') . '</p>';
        }

        $template
            ->putInPlaceholder('text', $output . $paging)
            ->putInPlaceholder('head_title', \Lang::month($month) . ', ' . $year)
        ;

        $template->addBreadCrumb($this->articleProvider->mainPageTitle(), $this->urlBuilder->link('/'));
        if (!$this->blogUrlBuilder->blogIsOnTheSiteRoot()) {
            $template->addBreadCrumb(Lang::get('Blog', 's2_blog'), $this->blogUrlBuilder->main());
        }
        $template
            ->addBreadCrumb($year, $this->blogUrlBuilder->year((int)$year))
            ->addBreadCrumb($month)
        ;

        return null;
    }
}
