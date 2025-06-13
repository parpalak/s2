<?php
/**
 * Displays the list of favorite pages and excerpts.
 *
 * @copyright 2024-2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Controller;

use S2\Cms\Framework\ControllerInterface;
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

readonly class PageFavorite implements ControllerInterface
{
    public function __construct(
        private DbLayer              $dbLayer,
        private ArticleProvider      $articleProvider,
        private UrlBuilder           $urlBuilder,
        private TranslatorInterface  $translator,
        private HtmlTemplateProvider $htmlTemplateProvider,
        private Viewer               $viewer,
        private string               $favoriteUrl,
        private bool                 $useHierarchy,
    ) {
    }

    /**
     * @throws DbLayerException
     */
    public function handle(Request $request): Response
    {
        if ($request->attributes->get('slash') !== '/') {
            return new RedirectResponse(
                $this->urlBuilder->link($request->getPathInfo() . '/'),
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
            ->select('a.title, a.url, (' . $rawQuery . ') IS NOT NULL AS children_exist, a.id, a.excerpt, 2 AS favorite, a.create_time, a.parent_id')
            ->from('articles AS a')
            ->where('a.favorite = 1')
            ->andWhere('a.published = 1')
            // NOTE: leads to "Using filesort". Maybe it's not bad, but in tags there is also "Using temporary".
            // Let's sort in PHP for a common approach.
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
            // There are favorite sections
            array_multisort($sortingValuesForSections, $sort_order, $sections);
            foreach ($sections as $item) {
                $sectionText .= $this->viewer->render('subarticles_item', $item);
            }
        }

        $articleText = '';
        if (\count($articles) > 0) {
            // There are favorite articles
            array_multisort($sortingValuesForArticles, $sort_order, $articles);
            foreach ($articles as $item) {
                $articleText .= $this->viewer->render('subarticles_item', $item);
            }
        }

        $template = $this->htmlTemplateProvider->getTemplate('site.php');

        $template
            ->addBreadCrumb($this->articleProvider->mainPageTitle(), $this->urlBuilder->link('/'))
            ->addBreadCrumb($this->translator->trans('Favorite'))
            ->putInPlaceholder('title', $this->translator->trans('Favorite'))
            ->putInPlaceholder('date', '')
            ->putInPlaceholder('text', $this->viewer->render('list_text', [
                'articles' => $articleText,
                'sections' => $sectionText,
            ]))
        ;

        return $template->toHttpResponse();
    }
}
