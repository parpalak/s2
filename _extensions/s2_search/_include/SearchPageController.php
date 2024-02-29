<?php
/**
 * Displays a page with search results
 *
 * @copyright 2010-2024 Roman Parpalak
 * @license MIT
 * @package s2_search
 */

declare(strict_types=1);

namespace s2_extensions\s2_search;

use Lang;
use S2\Cms\Controller\ControllerInterface;
use S2\Cms\Pdo\DbLayer;
use S2\Cms\Template\HtmlTemplateProvider;
use S2\Cms\Template\Viewer;
use S2\Rose\Entity\ExternalId;
use S2\Rose\Entity\Query;
use S2\Rose\Finder;
use S2\Rose\Helper\ProfileHelper;
use S2\Rose\Stemmer\StemmerInterface;
use S2\Rose\Storage\Exception\EmptyIndexException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class SearchPageController implements ControllerInterface
{
    public function __construct(
        private Finder               $finder,
        private StemmerInterface     $stemmer,
        private DbLayer              $dbLayer,
        private HtmlTemplateProvider $templateProvider,
        private Viewer               $viewer,
        private bool                 $debugView,
        private string               $tagsUrl,
    ) {
        Lang::load('s2_search', static function () {
            /** @noinspection PhpUndefinedConstantInspection */
            if (file_exists(__DIR__ . '/../lang/' . S2_LANGUAGE . '.php')) {
                /** @noinspection PhpUndefinedConstantInspection */
                return require __DIR__ . '/../lang/' . S2_LANGUAGE . '.php';
            }
            return require __DIR__ . '/../lang/English.php';
        });
    }

    public function handle(Request $request): Response
    {
        $query   = $request->query->get('q', '');
        $pageNum = (int)$request->query->get('p', 1);

        $content['query'] = $query;

        $template = $this->templateProvider->getTemplate('service.php');

        if ($query !== '') {
            $items_per_page = S2_MAX_ITEMS ?: 10.0;
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
                // Feel free to suggest the code for other languages.
                /** @noinspection PhpUndefinedConstantInspection */
                if (str_starts_with(S2_LANGUAGE, 'Russian')) {
                    $content['num_info'] = sprintf(s2_russian_plural((int)$content['num'], 'Нашлось %d страниц.', 'Нашлась %d страница.', 'Нашлось %d страницы.'), $content['num']);
                } else {
                    $content['num_info'] = sprintf(Lang::get('Found', 's2_search'), $content['num']);
                }

                $totalPages = ceil(1.0 * $content['num'] / $items_per_page);
                if ($pageNum < 1 || $pageNum > $totalPages) {
                    $pageNum = 1;
                }

                $content['profile'] = array_map(static fn($p) => ProfileHelper::formatProfilePoint($p), $resultSet->getProfilePoints());
                $content['trace']   = $resultSet->getTrace();

                $content['output'] = '';
                foreach ($resultSet->getItems() as $item) {
                    $content['output'] .= $this->viewer->render('search_result', [
                        'title'  => $item->getHighlightedTitle($this->stemmer),
                        'url'    => $item->getUrl(),
                        'descr'  => $item->getFormattedSnippet(),
                        'time'   => $item->getDate()?->getTimestamp(),
                        'images' => $item->getImageCollection(),
                        'debug'  => ($content['trace'][(new ExternalId($item->getId()))->toString()]),
                    ], 's2_search');
                }

                $link_nav          = [];
                $content['paging'] = s2_paging($pageNum, $totalPages, s2_link('/search', ['q=' . str_replace('%', '%%', urlencode($query)), 'p=%d']), $link_nav);
                foreach ($link_nav as $rel => $href) {
                    $template->setLink($rel, $href);
                }
            }
        }

        $template->putInPlaceholder('text', $this->viewer->render('search', $content, 's2_search'));
        $template->putInPlaceholder('title', Lang::get('Search', 's2_search'));
        $template->registerPlaceholder('<!-- s2_search_field -->', '');

        $template->addBreadCrumb(\Model::main_page_title(), s2_link('/'));
        $template->addBreadCrumb(Lang::get('Search', 's2_search'));

        return $template->toHttpResponse();
    }

    private function findInTags(Query $query): string
    {
        $return = '';

        $words = $query->valueToArray();
        if (\count($words) === 0) {
            return $return;
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
            if ($this->tagIsSimilarToWords($row['name'], $words)) {
                $tags[] = '<a href="' . s2_link('/' . $this->tagsUrl . '/' . urlencode($row['url']) . '/') . '">' . $row['name'] . '</a>';
            }
        }

        ($hook = s2_hook('s2_search_find_tags_pre_mrg')) ? eval($hook) : null;

        if (!empty($tags)) {
            $return .= '<p class="s2_search_found_tags">' . sprintf(Lang::get('Found tags', 's2_search'), implode(', ', $tags)) . '</p>';
        }

        return $return;
    }

    private function tagIsSimilarToWords(string $name, array $words): bool
    {
        $foundWordsInTags = array_filter(explode(' ', $name), static fn($word) => mb_strlen($word) > 2);
        $foundStemsInTags = array_map(fn(string $keyPart) => $this->stemmer->stemWord($keyPart), $foundWordsInTags);

        foreach ($foundStemsInTags as $foundStemInTags) {
            foreach ($words as $word) {
                if ($word === $foundStemInTags || (str_starts_with($foundStemInTags, $word) && mb_strlen($word) >= 5)) {
                    return true;
                }
            }
        }

        return false;
    }
}
