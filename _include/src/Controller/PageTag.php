<?php
/**
 * Displays the list of pages and excerpts for a specified tag.
 *
 * @copyright 2007-2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Controller;

use S2\Cms\Framework\ControllerInterface;
use S2\Cms\Framework\Exception\NotFoundException;
use S2\Cms\Model\ArticleProvider;
use S2\Cms\Model\UrlBuilder;
use S2\Cms\Pdo\DbLayer;
use S2\Cms\Pdo\DbLayerException;
use S2\Cms\Template\HtmlTemplateProvider;
use S2\Cms\Template\Viewer;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class PageTag implements ControllerInterface
{
    public function __construct(
        private DbLayer              $dbLayer,
        private ArticleProvider      $articleProvider,
        private UrlBuilder           $urlBuilder,
        private TranslatorInterface  $translator,
        private HtmlTemplateProvider $htmlTemplateProvider,
        private Viewer               $viewer,
        private string               $tagsUrlFragment,
        private string               $favoriteUrl,
        private bool                 $useHierarchy,
    ) {
    }

    /**
     * {@inheritdoc}
     * @throws DbLayerException
     */
    public function handle(Request $request): Response
    {
        $name     = $request->attributes->get('name');
        $hasSlash = (!empty($request->attributes->get('slash')));

        // Tag preview
        $query  = [
            'SELECT' => 'id AS tag_id, description, name, url',
            'FROM'   => 'tags',
            'WHERE'  => 'url = \'' . $this->dbLayer->escape($name) . '\''
        ];
        $result = $this->dbLayer->buildAndQuery($query);

        if (!($row = $this->dbLayer->fetchRow($result))) {
            throw new NotFoundException();
        }

        [$tagId, $tagDescription, $tagName, $tagUrl] = $row;

        if (!$hasSlash) {
            return new RedirectResponse($this->urlBuilder->link('/' . rawurlencode($this->tagsUrlFragment) . '/' . rawurlencode($tagUrl) . '/'), Response::HTTP_MOVED_PERMANENTLY);
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
            $urls[]       = rawurlencode($row['url']);
            $parent_ids[] = $row['parent_id'];
        }

        $urls = $this->articleProvider->getFullUrlsForArticles($parent_ids, $urls);

        $sections = $articles = $articles_sort_array = $sections_sort_array = [];
        foreach ($urls as $k => $url) {
            $row = $rows[$k];
            if ($row['children_exist']) {
                $item       = [
                    'id'            => $row['id'],
                    'title'         => $row['title'],
                    'link'          => $this->urlBuilder->link($url . ($this->useHierarchy ? '/' : '')),
                    'favorite_link' => $this->urlBuilder->link('/' . rawurlencode($this->favoriteUrl) . '/'),
                    'date'          => $this->viewer->date($row['create_time']),
                    'excerpt'       => $row['excerpt'],
                    'favorite'      => $row['favorite'],
                ];
                $sort_field = $row['create_time'];

                $sections[]            = $item;
                $sections_sort_array[] = $sort_field;
            } else {
                $item       = [
                    'id'            => $row['id'],
                    'title'         => $row['title'],
                    'link'          => $this->urlBuilder->link($url),
                    'favorite_link' => $this->urlBuilder->link('/' . rawurlencode($this->favoriteUrl) . '/'),
                    'date'          => $this->viewer->date($row['create_time']),
                    'excerpt'       => $row['excerpt'],
                    'favorite'      => $row['favorite'],
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
            ->addBreadCrumb($this->articleProvider->mainPageTitle(), $this->urlBuilder->link('/'))
            ->addBreadCrumb($this->translator->trans('Tags'), $this->urlBuilder->link('/' . rawurlencode($this->tagsUrlFragment) . '/'))
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
