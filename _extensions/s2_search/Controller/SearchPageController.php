<?php
/**
 * Displays a page with search results
 *
 * @copyright 2010-2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   s2_search
 */

declare(strict_types=1);

namespace s2_extensions\s2_search\Controller;

use S2\Cms\Framework\ControllerInterface;
use S2\Cms\Helper\StringHelper;
use S2\Cms\Image\ThumbnailGenerator;
use S2\Cms\Model\ArticleProvider;
use S2\Cms\Model\UrlBuilder;
use S2\Cms\Pdo\DbLayer;
use S2\Cms\Pdo\DbLayerException;
use S2\Cms\Template\HtmlTemplateProvider;
use S2\Cms\Template\Viewer;
use S2\Rose\Entity\ExternalId;
use S2\Rose\Entity\Query;
use S2\Rose\Exception\ImmutableException;
use S2\Rose\Exception\RuntimeException;
use S2\Rose\Exception\UnknownIdException;
use S2\Rose\Finder;
use S2\Rose\Helper\ProfileHelper;
use S2\Rose\Stemmer\StemmerInterface;
use S2\Rose\Storage\Database\PdoStorage;
use S2\Rose\Storage\Exception\EmptyIndexException;
use S2\Rose\Storage\Exception\InvalidEnvironmentException;
use s2_extensions\s2_search\Event\TagsSearchEvent;
use s2_extensions\s2_search\Service\SimilarWordsDetector;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class SearchPageController implements ControllerInterface
{
    public function __construct(
        private Finder                   $finder,
        private StemmerInterface         $stemmer,
        private PdoStorage               $pdoStorage,
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
     * @throws ImmutableException
     * @throws RuntimeException
     * @throws UnknownIdException
     * @throws BadRequestException
     * @throws \JsonException
     */
    public function handle(Request $request): Response
    {
        if (($titleQuery = $request->query->get('title')) !== null) {
            return $this->searchByTitle($titleQuery);
        }
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

                $totalPages = (int)ceil(1.0 * $content['num'] / $items_per_page);
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
                $content['paging'] = StringHelper::paging($pageNum, $totalPages, $this->urlBuilder->link('/search', ['q=' . str_replace('%', '%%', urlencode($query)), 'p=%d']), $link_nav);
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

        $usedSql = $this->dbLayer
            ->select('1')
            ->from('article_tag AS at')
            ->innerJoin('articles AS a', 'a.id = at.article_id')
            ->where('at.tag_id = t.id')
            ->andWhere('a.published = 1')
            ->limit(1)
            ->getSql()
        ;

        $result = $this->dbLayer
            ->select('id AS tag_id, name, url')
            ->from('tags AS t')
            ->where('EXISTS (' . $usedSql . ')')
            ->andWhere('(' . implode(' OR ', array_fill(0, 2 * \count($words), 'name LIKE ?')) . ')')
            ->execute(array_merge(
                array_map(static fn(string $word) => $word . '%', $words),
                array_map(static fn(string $word) => '% ' . $word . '%', $words),
            ))
        ;

        $tags = [];
        while ($row = $result->fetchAssoc()) {
            if ($this->similarWordsDetector->wordIsSimilarToOtherWords($row['name'], $words)) {
                $tags[] = '<a href="' . $this->urlBuilder->link('/' . rawurlencode($this->tagsUrl) . '/' . rawurlencode($row['url']) . '/') . '">' . s2_htmlencode($row['name']) . '</a>';
            }
        }

        $event = new TagsSearchEvent($words);
        if (\count($tags) > 0) {
            $event->addLine(\sprintf($this->translator->trans('Found tags'), implode(', ', $tags)));
        }
        $this->eventDispatcher->dispatch($event);

        if (($string = $event->getLine()) !== null) {
            return '<p class="s2_search_found_tags">' . $string . '</p>';
        }

        return '';
    }

    /**
     * @throws InvalidEnvironmentException
     * @throws \JsonException
     */
    private function searchByTitle(string $titleQuery): Response
    {
        $pdoStorage = $this->pdoStorage;
        $toc        = $pdoStorage->getTocByTitlePrefix($titleQuery);

        $result = '';
        foreach ($toc as $tocEntryWithExtId) {
            $result .= '<a href="' . $this->urlBuilder->link($tocEntryWithExtId->getTocEntry()->getUrl()) . '">' .
                preg_replace(
                    '#(' . preg_quote($titleQuery, '#') . ')#ui',
                    '<em>\\1</em>'
                    , s2_htmlencode($tocEntryWithExtId->getTocEntry()->getTitle())) .
                '</a>';
        }

        return new Response($result);
    }
}
