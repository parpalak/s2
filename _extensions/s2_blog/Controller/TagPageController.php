<?php
/**
 * Blog posts for a specified tag.
 *
 * @copyright 2007-2024 Roman Parpalak
 * @license MIT
 * @package s2_blog
 */

namespace s2_extensions\s2_blog\Controller;

use Lang;
use S2\Cms\Framework\Exception\NotFoundException;
use S2\Cms\Pdo\DbLayer;
use S2\Cms\Template\HtmlTemplate;
use S2\Cms\Template\HtmlTemplateProvider;
use S2\Cms\Template\Viewer;
use s2_extensions\s2_blog\Lib;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;


class TagPageController extends BlogController
{
    public function __construct(
        DbLayer               $dbLayer,
        HtmlTemplateProvider  $templateProvider,
        Viewer                $viewer,
        string                $tagsUrl,
        string                $blogUrl,
        string                $blogTitle,
        private readonly bool $useHierarchy
    ) {
        parent::__construct($dbLayer, $templateProvider, $viewer, $tagsUrl, $blogUrl, $blogTitle);
    }

    public function body(Request $request, HtmlTemplate $template): ?Response
    {
        $params = $request->attributes->all();

        if ($template->hasPlaceholder('<!-- s2_blog_calendar -->')) {
            $template->putInPlaceholder('s2_blog_calendar', Lib::calendar(date('Y'), date('m'), '0'));
        }

        $tag = $params['tag'];

        $query  = [
            'SELECT' => 'tag_id, description, name, url',
            'FROM'   => 'tags',
            'WHERE'  => 'url = \'' . $this->dbLayer->escape($tag) . '\''
        ];
        $result = $this->dbLayer->buildAndQuery($query);

        if (!($row = $this->dbLayer->fetchRow($result))) {
            throw new NotFoundException();
        }

        [$tagId, $tagDescription, $tagName, $tagUrl] = $row;

        if ($params['slash'] !== '/') {
            return new RedirectResponse($this->blogTagsPath . urlencode($tagUrl) . '/', Response::HTTP_MOVED_PERMANENTLY);
        }

        $art_links = $this->articles_by_tag($tagId);
        if (\count($art_links) > 0) {
            $tagDescription .= '<p>' . Lang::get('Articles by tag', 's2_blog') . '<br />' . implode('<br />', $art_links) . '</p>';
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

        $template->addBreadCrumb(\Model::main_page_title(), s2_link('/'));
        if ($this->blogUrl !== '') {
            $template->addBreadCrumb(Lang::get('Blog', 's2_blog'), $this->blogPath);
        }
        $template->addBreadCrumb(Lang::get('Tags'), $this->blogTagsPath);
        $template->addBreadCrumb($tagName);

        $template
            ->putInPlaceholder('head_title', s2_htmlencode($tagName))
            ->putInPlaceholder('title', $this->viewer->render('tag_title', ['title' => $tagName]))
            ->putInPlaceholder('text', $tagDescription . $output)
        ;

        $template->setLink('up', $this->blogTagsPath);

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
        $urls = \Model::get_group_url($parentIds, $urls);

        foreach ($urls as $k => $v) {
            $urls[$k] = '<a href="' . s2_link($v) . '">' . $title[$k] . '</a>';
        }

        return $urls;
    }
}
