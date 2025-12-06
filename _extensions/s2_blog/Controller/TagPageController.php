<?php
/**
 * Blog posts for a specified tag.
 *
 * @copyright 2007-2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   s2_blog
 */

namespace s2_extensions\s2_blog\Controller;

use S2\Cms\Config\BoolProxy;
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
        StringProxy           $blogTitle,
        BoolProxy             $showComments,
        BoolProxy             $enabledComments,
        private readonly BoolProxy $useHierarchy
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
        $params = $request->attributes->all();

        if ($template->hasPlaceholder('<!-- s2_blog_calendar -->')) {
            $template->registerPlaceholder('<!-- s2_blog_calendar -->', $this->calendarBuilder->calendar());
        }

        $tag = $params['tag'];

        $result = $this->dbLayer
            ->select('id AS tag_id, description, name, url')
            ->from('tags')
            ->where('url = :url')
            ->setParameter('url', $tag)
            ->execute()
        ;

        if (!($row = $result->fetchRow())) {
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

        $output = $this->getPosts(
            fn(SelectBuilder $qb) => $qb
                ->innerJoin('s2_blog_post_tag AS pt', 'p.id = pt.post_id')
                ->andWhere('pt.tag_id = :tag_id')
                ->setParameter('tag_id', $tagId),
            false
        );

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
     * @throws DbLayerException
     */
    private function articles_by_tag(int $tag_id): array
    {
        $rawQuery = $this->dbLayer
            ->select('1')
            ->from('articles AS a1')
            ->where('a1.parent_id = a.id')
            ->andWhere('a1.published = 1')
            ->limit(1)
            ->getSql()
        ;

        $result = $this->dbLayer
            ->select('a.id, a.url, a.title, a.parent_id')
            ->addSelect('(' . $rawQuery . ') IS NOT NULL AS children_exist')
            ->from('articles AS a')
            ->innerJoin('article_tag AS atg', 'atg.article_id = a.id')
            ->where('atg.tag_id = :tag_id')
            ->setParameter('tag_id', $tag_id)
            ->andWhere('a.published = 1')
            ->execute()
        ;

        $title = $urls = $parentIds = [];
        $useHierarchy = $this->useHierarchy->get();

        while ($row = $result->fetchAssoc()) {
            $urls[]      = urlencode($row['url']) . ($useHierarchy && $row['children_exist'] ? '/' : '');
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
