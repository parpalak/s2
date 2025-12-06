<?php
/**
 * Blog posts for a year.
 *
 * @copyright 2007-2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   s2_blog
 */

namespace s2_extensions\s2_blog\Controller;

use S2\Cms\Config\BoolProxy;
use S2\Cms\Config\IntProxy;
use S2\Cms\Config\StringProxy;
use S2\Cms\Model\ArticleProvider;
use S2\Cms\Model\UrlBuilder;
use S2\Cms\Pdo\DbLayer;
use S2\Cms\Pdo\DbLayerException;
use S2\Cms\Template\HtmlTemplate;
use S2\Cms\Template\HtmlTemplateProvider;
use S2\Cms\Template\Viewer;
use s2_extensions\s2_blog\BlogUrlBuilder;
use s2_extensions\s2_blog\CalendarBuilder;
use s2_extensions\s2_blog\Model\PostProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

class YearPageController extends BlogController
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
            $enabledComments,
        );
    }

    /**
     * @throws DbLayerException
     */
    public function body(Request $request, HtmlTemplate $template): ?Response
    {
        $year = $request->attributes->getInt('year');

        if ($template->hasPlaceholder('<!-- s2_blog_calendar -->')) {
            $template->registerPlaceholder('<!-- s2_blog_calendar -->', '');
        }

        $start_time = mktime(0, 0, 0, 1, 1, $year);
        $end_time   = mktime(0, 0, 0, 1, 1, $year + 1);

        $title = \sprintf($this->translator->trans('Year'), $year);
        $template->putInPlaceholder('head_title', $title);
        $pageTitle = $title;

        $template->setLink('up', $this->blogUrlBuilder->main());
        $startYear = $this->startYear->get();
        if ($year > $startYear) {
            $pageTitle = '<a href="' . $this->blogUrlBuilder->year($year - 1) . '">&larr;</a> ' . $pageTitle;
            $template->setLink('prev', $this->blogUrlBuilder->year($year - 1));
        }
        if ($year < date('Y')) {
            $pageTitle .= ' <a href="' . $this->blogUrlBuilder->year($year + 1) . '">&rarr;</a>';
            $template->setLink('next', $this->blogUrlBuilder->year($year + 1));
        }
        $template->putInPlaceholder('title', $pageTitle);

        $result = $this->dbLayer
            ->select('create_time, url')
            ->from('s2_blog_posts')
            ->where('create_time < :end_time')->setParameter('end_time', $end_time)
            ->andWhere('create_time >= :start_time')->setParameter('start_time', $start_time)
            ->andWhere('published = 1')
            ->execute()
        ;

        $dayUrlsArray = array_fill(1, 12, []);
        while ($row = $result->fetchRow()) {
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
            $template->addBreadCrumb($this->translator->trans('Blog'), $this->blogUrlBuilder->main());
        }
        $template->addBreadCrumb($year);

        return null;
    }
}
