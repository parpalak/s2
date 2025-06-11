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
     * @throws NotFoundException
     */
    public function handle(Request $request): Response
    {
        $name     = $request->attributes->get('name');
        $hasSlash = (!empty($request->attributes->get('slash')));

        // Tag preview
        $result = $this->dbLayer
            ->select('id AS tag_id, description, name, url')
            ->from('tags')
            ->where('url = :url')->setParameter('url', $name)
            ->execute()
        ;

        if (!($row = $result->fetchRow())) {
            throw new NotFoundException();
        }

        [$tagId, $tagDescription, $tagName, $tagUrl] = $row;

        if (!$hasSlash) {
            return new RedirectResponse(
                $this->urlBuilder->link('/' . rawurlencode($this->tagsUrlFragment) . '/' . rawurlencode($tagUrl) . '/'),
                Response::HTTP_MOVED_PERMANENTLY
            );
        }

        $rawQuery = $this->dbLayer
            ->select('1')
            ->from('articles AS a1')
            ->where('a1.parent_id = a.id')
            ->andWhere('a1.published = 1')
            ->limit(1)
            ->getSql()
        ;

        $sort_order = SORT_DESC; // SORT_ASC is also possible
        $result = $this->dbLayer
            ->select('a.title, a.url, (' . $rawQuery . ') IS NOT NULL AS children_exist, a.id, a.excerpt, a.favorite, a.create_time, a.parent_id')
            ->from('article_tag AS at')
            ->innerJoin('articles AS a', 'a.id = at.article_id')
            ->where('at.tag_id = :tag_id')->setParameter('tag_id', $tagId)
            ->andWhere('a.published = 1')
            // NOTE: leads to "Using temporary; Using filesort"
            // ->orderBy('a.create_time DESC')
            ->execute()
        ;

        $urls = $parentIds = $rows = [];
        while ($row = $result->fetchAssoc()) {
            $rows[]      = $row;
            $urls[]      = rawurlencode($row['url']);
            $parentIds[] = $row['parent_id'];
        }

        $urls = $this->articleProvider->getFullUrlsForArticles($parentIds, $urls);

        $sections = $articles = $sortingValuesForArticles = $sortingValuesForSections = [];
        if (\count($urls) > 0) {
            $favoriteLink = $this->urlBuilder->link('/' . rawurlencode($this->favoriteUrl) . '/');
            foreach ($urls as $k => $url) {
                $row  = $rows[$k];
                $item = [
                    'id'            => $row['id'],
                    'title'         => $row['title'],
                    'link'          => $this->urlBuilder->link($url . ($this->useHierarchy && $row['children_exist'] ? '/' : '')),
                    'favorite_link' => $favoriteLink,
                    'date'          => $this->viewer->date($row['create_time']),
                    'excerpt'       => $row['excerpt'],
                    'favorite'      => $row['favorite'],
                ];
                if ($row['children_exist']) {
                    $sections[]                 = $item;
                    $sortingValuesForSections[] = $row['create_time'];
                } else {
                    $articles[]                 = $item;
                    $sortingValuesForArticles[] = $row['create_time'];
                }
            }
        }

        $sectionText = '';
        if (\count($sections) > 0) {
            // There are sections having the tag
            array_multisort($sortingValuesForSections, $sort_order, $sections);
            foreach ($sections as $item) {
                $sectionText .= $this->viewer->render('subarticles_item', $item);
            }
        }

        $articleText = '';
        if (\count($articles) > 0) {
            // There are articles having the tag
            array_multisort($sortingValuesForArticles, $sort_order, $articles);
            foreach ($articles as $item) {
                $articleText .= $this->viewer->render('subarticles_item', $item);
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
                'articles'    => $articleText,
                'sections'    => $sectionText,
            ]))
        ;

        return $template->toHttpResponse();
    }
}
