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
use S2\Cms\Pdo\DbLayer;
use S2\Cms\Template\HtmlTemplate;
use S2\Cms\Template\HtmlTemplateProvider;
use S2\Cms\Template\Viewer;
use s2_extensions\s2_blog\Lib;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;


class MonthPageController extends BlogController
{
    public function __construct(
        DbLayer                 $dbLayer,
        HtmlTemplateProvider    $templateProvider,
        Viewer                  $viewer,
        string                  $tagsUrl,
        string                  $blogUrl,
        string                  $blogTitle,
        private readonly string $startYear,
    ) {
        parent::__construct($dbLayer, $templateProvider, $viewer, $tagsUrl, $blogUrl, $blogTitle);
    }

    public function body(Request $request, HtmlTemplate $template): ?Response
    {
        $params = $request->attributes->all();

        $year  = $params['year'];
        $month = $params['month'];

        if ($template->hasPlaceholder('<!-- s2_blog_calendar -->')) {
            $template->registerPlaceholder('<!-- s2_blog_calendar -->', Lib::calendar($year, $month, 0));
        }

        $template->putInPlaceholder('title', '');

        $startTime = mktime(0, 0, 0, $month, 1, $year);
        $endTime   = mktime(0, 0, 0, $month + 1, 1, $year);
        $prevTime  = mktime(0, 0, 0, $month - 1, 1, $year);

        $template->setLink('up', $this->blogPath . date('Y/', $startTime));

        $paging = '';
        if ($prevTime >= mktime(0, 0, 0, 1, 1, $this->startYear)) {
            $prevLink = $this->blogPath . date('Y/m/', $prevTime);
            $template->setLink('prev', $prevLink);
            $paging = '<a href="' . $prevLink . '">' . Lang::get('Here') . '</a> ';
        }
        if ($endTime < time()) {
            $nextLink = $this->blogPath . date('Y/m/', $endTime);
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

        $template->addBreadCrumb(\S2\Cms\Model\Model::main_page_title(), s2_link('/'));
        if ($this->blogUrl !== '') {
            $template->addBreadCrumb(Lang::get('Blog', 's2_blog'), $this->blogPath);
        }
        $template
            ->addBreadCrumb($year, $this->blogPath . $year . '/')
            ->addBreadCrumb($month)
        ;

        return null;
    }
}
