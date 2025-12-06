<?php
/**
 * Blog posts for a month.
 *
 * @copyright 2007-2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   s2_blog
 */

namespace s2_extensions\s2_blog\Controller;

use S2\Cms\Config\BoolProxy;
use S2\Cms\Config\IntProxy;
use S2\Cms\Config\StringProxy;
use S2\Cms\Framework\Exception\NotFoundException;
use S2\Cms\Model\ArticleProvider;
use S2\Cms\Model\UrlBuilder;
use S2\Cms\Pdo\DbLayer;
use S2\Cms\Pdo\DbLayerException;
use S2\Cms\Pdo\QueryBuilder\SelectBuilder;
use S2\Cms\Template\HtmlTemplate;
use S2\Cms\Template\HtmlTemplateProvider;
use S2\Cms\Template\Viewer;
use s2_extensions\s2_blog\BlogUrlBuilder;
use s2_extensions\s2_blog\CalendarBuilder;
use s2_extensions\s2_blog\Model\PostProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

class MonthPageController extends BlogController
{
    public function __construct(
        DbLayer              $dbLayer,
        CalendarBuilder      $calendarBuilder,
        BlogUrlBuilder       $blogUrlBuilder,
        ArticleProvider      $articleProvider,
        PostProvider         $postProvider,
        UrlBuilder           $urlBuilder,
        TranslatorInterface  $translator,
        HtmlTemplateProvider $templateProvider,
        Viewer               $viewer,
        StringProxy          $blogTitle,
        BoolProxy            $showComments,
        BoolProxy            $enabledComments,
        private readonly IntProxy $startYear,
    ) {
        parent::__construct(
            $dbLayer,
            $calendarBuilder,
            $blogUrlBuilder,
            $articleProvider,
            $postProvider,
            $urlBuilder,
            $translator,
            $templateProvider,
            $viewer,
            $blogTitle,
            $showComments,
            $enabledComments
        );
    }

    /**
     * @throws DbLayerException
     */
    public function body(Request $request, HtmlTemplate $template): ?Response
    {
        $year  = $request->attributes->get('year');
        // Here is a leading zero for months from 01 to 09, cannot be cast to int
        $month = $request->attributes->get('month');

        if ((int)$month < 1 || (int)$month > 12) {
            throw new NotFoundException();
        }

        if ($template->hasPlaceholder('<!-- s2_blog_calendar -->')) {
            $template->registerPlaceholder('<!-- s2_blog_calendar -->', $this->calendarBuilder->calendar((int)$year, (int)$month));
        }

        $template->putInPlaceholder('title', '');

        $startTime = mktime(0, 0, 0, (int)$month, 1, (int)$year);
        $endTime   = mktime(0, 0, 0, (int)$month + 1, 1, (int)$year);
        $prevTime  = mktime(0, 0, 0, (int)$month - 1, 1, (int)$year);

        $template->setLink('up', $this->blogUrlBuilder->year((int)$year));

        $paging = '';
        $startYear = $this->startYear->get();
        if ($prevTime >= mktime(0, 0, 0, 1, 1, $startYear)) {
            $prevLink = $this->blogUrlBuilder->monthFromTimestamp($prevTime);
            $template->setLink('prev', $prevLink);
            $paging = '<a href="' . $prevLink . '">' . $this->translator->trans('Here') . '</a> ';
        }
        if ($endTime < time()) {
            $nextLink = $this->blogUrlBuilder->monthFromTimestamp($endTime);
            $template->setLink('next', $nextLink);
            $paging .= '<a href="' . $nextLink . '">' . $this->translator->trans('There') . '</a>';
            // TODO think about back_forward template
        }

        if ($paging !== '') {
            $paging = '<p class="s2_blog_pages">' . $paging . '</p>';
        }

        $output = $this->getPosts(
            fn (SelectBuilder $qb) => $qb
                ->andWhere('p.create_time < ' . $endTime)
                ->andWhere('p.create_time >= ' . $startTime)
        );

        if ($output === '') {
            $template->markAsNotFound();
            $output = '<p>' . $this->translator->trans('Not found') . '</p>';
        }

        $template
            ->putInPlaceholder('text', $output . $paging)
            ->putInPlaceholder('head_title', $this->calendarBuilder->month($month) . ', ' . $year)
        ;

        $template->addBreadCrumb($this->articleProvider->mainPageTitle(), $this->urlBuilder->link('/'));
        if (!$this->blogUrlBuilder->blogIsOnTheSiteRoot()) {
            $template->addBreadCrumb($this->translator->trans('Blog'), $this->blogUrlBuilder->main());
        }
        $template
            ->addBreadCrumb($year, $this->blogUrlBuilder->year((int)$year))
            ->addBreadCrumb($month)
        ;

        return null;
    }
}
