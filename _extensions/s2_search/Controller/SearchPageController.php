<?php
/**
 * Displays a page with search results
 *
 * @copyright 2010-2024 Roman Parpalak
 * @license   http://opensource.org/licenses/MIT MIT
 * @package   s2_search
 */

declare(strict_types=1);

namespace s2_extensions\s2_search\Controller;

use S2\Cms\Framework\ControllerInterface;
use S2\Cms\Image\ThumbnailGenerator;
use S2\Cms\Model\ArticleProvider;
use S2\Cms\Model\UrlBuilder;
use S2\Cms\Pdo\DbLayer;
use S2\Cms\Pdo\DbLayerException;
use S2\Cms\Template\HtmlTemplateProvider;
use S2\Cms\Template\Viewer;
use S2\Rose\Entity\ExternalId;
use S2\Rose\Entity\Query;
use S2\Rose\Finder;
use S2\Rose\Helper\ProfileHelper;
use S2\Rose\Stemmer\StemmerInterface;
use S2\Rose\Storage\Exception\EmptyIndexException;
use s2_extensions\s2_search\Event\TagsSearchEvent;
use s2_extensions\s2_search\Service\SimilarWordsDetector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class SearchPageController implements ControllerInterface
{
    public function __construct(
        private Finder                   $finder,
        private StemmerInterface         $stemmer,
        private ThumbnailGenerator       $thumbnailGenerator,
        private SimilarWordsDetector     $similarWordsDetector,
        private DbLayer                  $dbLayer,
        private ArticleProvider          $articleProvider,
        private EventDispatcherInterface $eventDispatcher,
        private TranslatorInterface      $translator,
        private UrlBuilder               $urlBuilder,
        private HtmlTemplateProvider     $templateProvider,
        private Viewer                   $viewer,
        private bool                     $debugView,
        private string                   $tagsUrl,
        private int                      $maxItems,
    ) {
    }

    /**
     * @throws DbLayerException
     */
    public function handle(Request $request): Response
    {
        $query   = $request->query->get('q', '');
        $pageNum = (int)$request->query->get('p', 1);

        $content['query'] = $query;

        $template = $this->templateProvider->getTemplate('service.php');

        if ($query !== '') {
            $items_per_page = $this->maxItems ?: 10.0;
            $queryObj       = new Query($query);
            $queryObj
                ->setLimit($items_per_page)
                ->setOffset(($pageNum - 1) * $items_per_page) // TODO Может быть за пределами
            ;
            try {
                $resultSet = $this->finder->find($queryObj, $this->debugView);
                $content   += ['num' => $resultSet->getTotalCount()];
            } catch (EmptyIndexException $e) {
                $content += ['num' => 0,];
            }

            $content += ['tags' => $this->findInTags($queryObj)];

            if ($content['num'] > 0) {
                $content['num_info'] = $this->translator->trans('Found N pages', ['%count%' => $content['num'], '{{ pages }}' => $content['num']]);

                $totalPages = ceil(1.0 * $content['num'] / $items_per_page);
                if ($pageNum < 1 || $pageNum > $totalPages) {
                    $pageNum = 1;
                }

                $content['profile'] = array_map(static fn($p) => ProfileHelper::formatProfilePoint($p), $resultSet->getProfilePoints());
                $content['trace']   = $resultSet->getTrace();

                $content['output'] = '';
                foreach ($resultSet->getItems() as $item) {
                    $content['output'] .= $this->viewer->render('search_result', [
                        'title'         => $item->getHighlightedTitle($this->stemmer),
                        'url'           => $item->getUrl(),
                        'link'          => $this->urlBuilder->link($item->getUrl()),
                        'descr'         => $item->getFormattedSnippet(),
                        'time'          => $item->getDate()?->getTimestamp(),
                        'images'        => $item->getImageCollection(),
                        'debug'         => ($content['trace'][(new ExternalId($item->getId()))->toString()]),
                        'thumbnailHtml' => $this->thumbnailGenerator->getThumbnailHtml(...),
                    ], 's2_search');
                }

                $link_nav          = [];
                $content['paging'] = s2_paging($pageNum, $totalPages, $this->urlBuilder->link('/search', ['q=' . str_replace('%', '%%', urlencode($query)), 'p=%d']), $link_nav);
                foreach ($link_nav as $rel => $href) {
                    $template->setLink($rel, $href);
                }
            }
        }

        $content['action'] = $this->urlBuilder->link('/search');

        $template->putInPlaceholder('text', $this->viewer->render('search', $content, 's2_search'));
        $template->putInPlaceholder('title', $this->translator->trans('Search'));
        $template->registerPlaceholder('<!-- s2_search_field -->', '');

        $template->addBreadCrumb($this->articleProvider->mainPageTitle(), $this->urlBuilder->link('/'));
        $template->addBreadCrumb($this->translator->trans('Search'));

        return $template->toHttpResponse();
    }

    /**
     * @throws DbLayerException
     */
    private function findInTags(Query $query): string
    {
        $words = $query->valueToArray();
        if (\count($words) === 0) {
            return '';
        }

        $stemmedWords = array_map(fn($word) => $this->stemmer->stemWord($word), $words);
        $words        = array_unique(array_merge($words, $stemmedWords));

        $sql     = [
            'SELECT' => '1',
            'FROM'   => 'article_tag AS at',
            'JOINS'  => [
                [
                    'INNER JOIN' => 'articles AS a',
                    'ON'         => 'a.id = at.article_id'
                ]
            ],
            'WHERE'  => 'at.tag_id = t.tag_id AND a.published = 1',
            'LIMIT'  => '1'
        ];
        $usedSql = $this->dbLayer->build($sql);

        $where = array_map(fn($word) => 'name LIKE \'' . $this->dbLayer->escape($word) . '%\' OR name LIKE \'% ' . $this->dbLayer->escape($word) . '%\'', $words);

        $sql              = [
            'SELECT' => 'tag_id, name, url',
            'FROM'   => 'tags AS t',
            'WHERE'  => '(' . implode(' OR ', $where) . ') AND (' . $usedSql . ') IS NOT NULL',
        ];
        $s2_search_result = $this->dbLayer->buildAndQuery($sql);

        $tags = [];
        while ($row = $this->dbLayer->fetchAssoc($s2_search_result)) {
            if ($this->similarWordsDetector->wordIsSimilarToOtherWords($row['name'], $words)) {
                $tags[] = '<a href="' . $this->urlBuilder->link('/' . $this->tagsUrl . '/' . urlencode($row['url']) . '/') . '">' . $row['name'] . '</a>';
            }
        }
//        $tags[] = '<a href="' . $this->urlBuilder->link('/' . rawurlencode($this->tagsUrl) . '/' . rawurlencode($row['url']) . '/') . '">' . $row['name'] . '</a>';

        $event = new TagsSearchEvent($where, $words);
        if (\count($tags) > 0) {
            $event->addLine(sprintf($this->translator->trans('Found tags'), implode(', ', $tags)));
        }
        $this->eventDispatcher->dispatch($event);

        if (($string = $event->getLine()) !== null) {
            return '<p class="s2_search_found_tags">' . $string . '</p>';
        }

        return '';
    }
}
