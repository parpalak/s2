<?php
/**
 * Blog posts for a year.
 *
 * @copyright 2007-2014 Roman Parpalak
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


class YearPageController extends BlogController
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
        $year = $request->attributes->getInt('year');

        if ($template->hasPlaceholder('<!-- s2_blog_calendar -->')) {
            $template->registerPlaceholder('<!-- s2_blog_calendar -->', '');
        }

        $start_time = mktime(0, 0, 0, 1, 1, $year);
        $end_time   = mktime(0, 0, 0, 1, 1, $year + 1);

        $title = sprintf(Lang::get('Year', 's2_blog'), $year);
        $template->putInPlaceholder('head_title', $title);
        $pageTitle = $title;

        $template->setLink('up', $this->blogUrlBuilder->main());
        if ($year > $this->startYear) {
            $pageTitle = '<a href="' . $this->blogUrlBuilder->year($year - 1) . '/">&larr;</a> ' . $pageTitle;
            $template->setLink('prev', $this->blogUrlBuilder->year($year - 1) . '/');
        }
        if ($year < date('Y')) {
            $pageTitle .= ' <a href="' . $this->blogUrlBuilder->year($year + 1) . '/">&rarr;</a>';
            $template->setLink('next', $this->blogUrlBuilder->year($year + 1) . '/');
        }
        $template->putInPlaceholder('title', $pageTitle);

        $query  = [
            'SELECT' => 'create_time, url',
            'FROM'   => 's2_blog_posts',
            'WHERE'  => 'create_time < ' . $end_time . ' AND create_time >= ' . $start_time . ' AND published = 1'
        ];
        $result = $this->dbLayer->buildAndQuery($query);

        $dayUrlsArray = array_fill(1, 12, []);
        while ($row = $this->dbLayer->fetchRow($result)) {
            $dayUrlsArray[(int)date('m', $row[0])][(int)date('j', $row[0])][] = $row[1];
        }

        $content = [];
        for ($i = 1; $i <= 12; $i++) {
            $content[] = $this->calendarBuilder->calendar($year, $i, null, '', $dayUrlsArray[$i]);
        }

        $template->putInPlaceholder('text', $this->viewer->render('year', [
            'content' => $content
        ], 's2_blog'));

        $template->addBreadCrumb($this->articleProvider->mainPageTitle(), $this->urlBuilder->link('/'));
        if (!$this->blogUrlBuilder->blogIsOnTheSiteRoot()) {
            $template->addBreadCrumb(Lang::get('Blog', 's2_blog'), $this->blogUrlBuilder->main());
        }
        $template->addBreadCrumb($year);

        return null;
    }
}
