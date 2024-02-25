<?php
/**
 * Displays the list of pages and excerpts for a specified tag.
 *
 * @copyright 2007-2024 Roman Parpalak
 * @license MIT
 * @package S2
 */

declare(strict_types=1);

namespace S2\Cms\Controller;

use S2\Cms\Framework\Exception\NotFoundException;
use S2\Cms\Pdo\DbLayer;
use S2\Cms\Template\HtmlTemplateProvider;
use S2\Cms\Template\Viewer;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

readonly class PageTag implements ControllerInterface
{
    public function __construct(
        private DbLayer              $dbLayer,
        private HtmlTemplateProvider $htmlTemplateProvider,
        private Viewer               $viewer,
        private string               $tagsUrlFragment,
    ) {
    }

    public function handle(Request $request): Response
    {
        $name     = $request->attributes->get('name');
        $hasSlash = (!empty($request->attributes->get('slash')));

        // Tag preview
        $query  = [
            'SELECT' => 'tag_id, description, name, url',
            'FROM'   => 'tags',
            'WHERE'  => 'url = \'' . $this->dbLayer->escape($name) . '\''
        ];
        $result = $this->dbLayer->buildAndQuery($query);

        if (!($row = $this->dbLayer->fetchRow($result))) {
            throw new NotFoundException();
        }

        [$tagId, $tagDescription, $tagName, $tagUrl] = $row;

        if (!$hasSlash) {
            return new RedirectResponse(s2_link('/' . $this->tagsUrlFragment . '/' . urlencode($tagUrl) . '/'), Response::HTTP_MOVED_PERMANENTLY);
        }

        $subquery   = [
            'SELECT' => '1',
            'FROM'   => 'articles AS a1',
            'WHERE'  => 'a1.parent_id = a.id AND a1.published = 1',
            'LIMIT'  => '1'
        ];
        $raw_query1 = $this->dbLayer->build($subquery);

        $sort_order = SORT_DESC; // SORT_ASC is also possible
        $query      = [
            'SELECT' => 'a.title, a.url, (' . $raw_query1 . ') IS NOT NULL AS children_exist, a.id, a.excerpt, a.favorite, a.create_time, a.parent_id',
            'FROM'   => 'article_tag AS at',
            'JOINS'  => [
                [
                    'INNER JOIN' => 'articles AS a',
                    'ON'         => 'a.id = at.article_id'
                ],
            ],
            'WHERE'  => 'at.tag_id = ' . $tagId . ' AND a.published = 1'
        ];
        $result     = $this->dbLayer->buildAndQuery($query);

        $urls = $parent_ids = $rows = [];
        while ($row = $this->dbLayer->fetchAssoc($result)) {
            $rows[]       = $row;
            $urls[]       = urlencode($row['url']);
            $parent_ids[] = $row['parent_id'];
        }

        $urls = \Model::get_group_url($parent_ids, $urls);

        $sections = $articles = $articles_sort_array = $sections_sort_array = [];
        foreach ($urls as $k => $url) {
            $row = $rows[$k];
            if ($row['children_exist']) {
                $item       = [
                    'id'       => $row['id'],
                    'title'    => $row['title'],
                    'link'     => s2_link($url . (S2_USE_HIERARCHY ? '/' : '')),
                    'date'     => s2_date($row['create_time']),
                    'excerpt'  => $row['excerpt'],
                    'favorite' => $row['favorite'],
                ];
                $sort_field = $row['create_time'];

                $sections[]            = $item;
                $sections_sort_array[] = $sort_field;
            } else {
                $item       = [
                    'id'       => $row['id'],
                    'title'    => $row['title'],
                    'link'     => s2_link($url),
                    'date'     => s2_date($row['create_time']),
                    'excerpt'  => $row['excerpt'],
                    'favorite' => $row['favorite'],
                ];
                $sort_field = $row['create_time'];

                $articles[]            = $item;
                $articles_sort_array[] = $sort_field;
            }
        }

        $section_text = '';
        if (\count($sections) > 0) {
            // There are sections having the tag
            array_multisort($sections_sort_array, $sort_order, $sections);
            foreach ($sections as $item) {
                $section_text .= $this->viewer->render('subarticles_item', $item);
            }
        }

        $article_text = '';
        if (\count($articles) > 0) {
            // There are favorite articles
            array_multisort($articles_sort_array, $sort_order, $articles);
            foreach ($articles as $item) {
                $article_text .= $this->viewer->render('subarticles_item', $item);
            }
        }

        $template = $this->htmlTemplateProvider->getTemplate('site.php');

        $template
            ->addBreadCrumb(\Model::main_page_title(), s2_link('/'))
            ->addBreadCrumb(\Lang::get('Tags'), s2_link('/' . $this->tagsUrlFragment . '/'))
            ->addBreadCrumb($tagName)
            ->putInPlaceholder('title', $this->viewer->render('tag_title', ['title' => $tagName]))
            ->putInPlaceholder('date', '')
            ->putInPlaceholder('text', $this->viewer->render('list_text', [
                'description' => $tagDescription,
                'articles'    => $article_text,
                'sections'    => $section_text,
            ]))
        ;

        return $template->toHttpResponse();
    }
}
