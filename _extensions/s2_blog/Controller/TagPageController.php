<?php
/**
 * Blog posts for a specified tag.
 *
 * @copyright 2007-2024 Roman Parpalak
 * @license   http://opensource.org/licenses/MIT MIT
 * @package   s2_blog
 */

namespace s2_extensions\s2_blog\Controller;

use S2\Cms\Framework\Exception\NotFoundException;
use S2\Cms\Model\ArticleProvider;
use S2\Cms\Model\UrlBuilder;
use S2\Cms\Pdo\DbLayer;
use S2\Cms\Template\HtmlTemplate;
use S2\Cms\Template\HtmlTemplateProvider;
use S2\Cms\Template\Viewer;
use s2_extensions\s2_blog\BlogUrlBuilder;
use s2_extensions\s2_blog\CalendarBuilder;
use s2_extensions\s2_blog\Model\PostProvider;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;


class TagPageController extends BlogController
{
    public function __construct(
        DbLayer               $dbLayer,
        CalendarBuilder       $calendarBuilder,
        BlogUrlBuilder        $blogUrlBuilder,
        ArticleProvider       $articleProvider,
        PostProvider          $postProvider,
        UrlBuilder            $urlBuilder,
        TranslatorInterface   $translator,
        HtmlTemplateProvider  $templateProvider,
        Viewer                $viewer,
        string                $blogTitle,
        private readonly bool $useHierarchy
    ) {
        parent::__construct($dbLayer, $calendarBuilder, $blogUrlBuilder, $articleProvider, $postProvider, $urlBuilder, $translator, $templateProvider, $viewer, $blogTitle);
    }

    public function body(Request $request, HtmlTemplate $template): ?Response
    {
        $params = $request->attributes->all();

        if ($template->hasPlaceholder('<!-- s2_blog_calendar -->')) {
            $template->registerPlaceholder('<!-- s2_blog_calendar -->', $this->calendarBuilder->calendar());
        }

        $tag = $params['tag'];

        $query  = [
            'SELECT' => 'id AS tag_id, description, name, url',
            'FROM'   => 'tags',
            'WHERE'  => 'url = \'' . $this->dbLayer->escape($tag) . '\''
        ];
        $result = $this->dbLayer->buildAndQuery($query);

        if (!($row = $this->dbLayer->fetchRow($result))) {
            throw new NotFoundException();
        }

        [$tagId, $tagDescription, $tagName, $tagUrl] = $row;

        if ($params['slash'] !== '/') {
            return new RedirectResponse($this->blogUrlBuilder->tag($tagUrl), Response::HTTP_MOVED_PERMANENTLY);
        }

        $art_links = $this->articles_by_tag($tagId);
        if (\count($art_links) > 0) {
            $tagDescription .= '<p>' . $this->translator->trans('Articles by tag') . '<br />' . implode('<br />', $art_links) . '</p>';
        }

        if ($tagDescription) {
            $tagDescription .= '<hr />';
        }

        $output = $this->getPosts([
            'JOINS' => [
                [
                    'INNER JOIN' => 's2_blog_post_tag AS pt',
                    'ON'         => 'pt.post_id = p.id'
                ]
            ],
            'WHERE' => 'pt.tag_id = ' . $tagId
        ], false);

        if ($output === '') {
            throw new NotFoundException();
        }

        $template->addBreadCrumb($this->articleProvider->mainPageTitle(), $this->urlBuilder->link('/'));
        if (!$this->blogUrlBuilder->blogIsOnTheSiteRoot()) {
            $template->addBreadCrumb($this->translator->trans('Blog'), $this->blogUrlBuilder->main());
        }
        $template->addBreadCrumb($this->translator->trans('Tags'), $this->blogUrlBuilder->tags());
        $template->addBreadCrumb($tagName);

        $template
            ->putInPlaceholder('head_title', s2_htmlencode($tagName))
            ->putInPlaceholder('title', $this->viewer->render('tag_title', ['title' => $tagName]))
            ->putInPlaceholder('text', $tagDescription . $output)
        ;

        $template->setLink('up', $this->blogUrlBuilder->tags());

        return null;
    }


    /**
     * Returns the array of links to the articles with the tag specified
     */
    private function articles_by_tag(int $tag_id): array
    {
        $subquery   = [
            'SELECT' => '1',
            'FROM'   => 'articles AS a1',
            'WHERE'  => 'a1.parent_id = a.id AND a1.published = 1',
            'LIMIT'  => '1'
        ];
        $raw_query1 = $this->dbLayer->build($subquery);

        $query  = [
            'SELECT' => 'a.id, a.url, a.title, a.parent_id, (' . $raw_query1 . ') IS NOT NULL AS children_exist',
            'FROM'   => 'articles AS a',
            'JOINS'  => [
                [
                    'INNER JOIN' => 'article_tag AS atg',
                    'ON'         => 'atg.article_id = a.id'
                ],
            ],
            'WHERE'  => 'atg.tag_id = ' . $tag_id . ' AND a.published = 1',
        ];
        $result = $this->dbLayer->buildAndQuery($query);

        $title = $urls = $parentIds = [];

        while ($row = $this->dbLayer->fetchAssoc($result)) {
            $urls[]      = urlencode($row['url']) . ($this->useHierarchy && $row['children_exist'] ? '/' : '');
            $parentIds[] = $row['parent_id'];
            $title[]     = $row['title'];
        }
        $urls = $this->articleProvider->getFullUrlsForArticles($parentIds, $urls);

        foreach ($urls as $k => $v) {
            $urls[$k] = '<a href="' . $this->urlBuilder->link($v) . '">' . $title[$k] . '</a>';
        }

        return $urls;
    }
}
