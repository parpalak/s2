<?php /** @noinspection PhpExpressionResultUnusedInspection */

/**
 * Displays a page with search results
 *
 * @copyright (C) 2010-2023 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_search
 */

namespace s2_extensions\s2_search;

use Lang;
use S2\Rose\Entity\ExternalId;
use S2\Rose\Entity\Query;
use S2\Rose\Finder;
use S2\Rose\Helper\ProfileHelper;
use S2\Rose\Stemmer\StemmerInterface;
use S2\Rose\Storage\Exception\EmptyIndexException;

class Page extends \Page_HTML implements \Page_Routable
{
    protected $template_id = 'service.php';
    private int $page_num;
    private StemmerInterface $stemmer;

    public function __construct(array $params = array())
    {
        $query          = $_GET['q'] ?? '';
        $this->page_num = isset($_GET['p']) ? (int)$_GET['p'] : 1;

        Lang::load('s2_search', static function () {
            /** @noinspection PhpUndefinedConstantInspection */
            if (file_exists(__DIR__ . '/../lang/' . S2_LANGUAGE . '.php')) {
                /** @noinspection PhpUndefinedConstantInspection */
                return require __DIR__ . '/../lang/' . S2_LANGUAGE . '.php';
            }
            return require __DIR__ . '/../lang/English.php';
        });

        $this->viewer = new \Viewer($this);
        /** @noinspection PhpFieldAssignmentTypeMismatchInspection */
        $this->stemmer = \Container::get(StemmerInterface::class);

        parent::__construct($params);

        $this->build_page($query);
    }

    private function build_page($query): void
    {
        $content['query'] = $query;

        if ($query !== '') {
            /** @var Finder $finder */
            $finder = \Container::get(Finder::class);

            $items_per_page = S2_MAX_ITEMS ?: 10.0;
            $queryObj       = new Query($query);
            $queryObj
                ->setLimit($items_per_page)
                ->setOffset(($this->page_num - 1) * $items_per_page) // TODO Может быть за пределами
            ;
            try {
                $resultSet = $finder->find($queryObj, defined('S2_DEBUG_VIEW'));
                $content   += ['num' => $resultSet->getTotalCount()];
            } catch (EmptyIndexException $e) {
                $content += ['num' => 0,];
            }

            $content += ['tags' => $this->findInTags($queryObj)];

            ($hook = s2_hook('s2_search_pre_results')) ? eval($hook) : null;

            if ($content['num'] > 0) {
                // Feel free to suggest the code for other languages.
                /** @noinspection PhpUndefinedConstantInspection */
                /** @noinspection SubStrUsedAsStrPosInspection */
                if (substr(S2_LANGUAGE, 0, 7) === 'Russian') {
                    $content['num_info'] = sprintf(s2_russian_plural((int)$content['num'], 'Нашлось %d страниц.', 'Нашлась %d страница.', 'Нашлось %d страницы.'), $content['num']);
                } else {
                    $content['num_info'] = sprintf(Lang::get('Found', 's2_search'), $content['num']);
                }

                $total_pages = ceil(1.0 * $content['num'] / $items_per_page);
                if ($this->page_num < 1 || $this->page_num > $total_pages) {
                    $this->page_num = 1;
                }

                $content['profile'] = array_map(static fn($p) => ProfileHelper::formatProfilePoint($p), $resultSet->getProfilePoints());
                $content['trace']   = $resultSet->getTrace();

                $content['output'] = '';
                foreach ($resultSet->getItems() as $item) {
                    $content['output'] .= $this->renderPartial('search_result', [
                        'title'  => $item->getHighlightedTitle($this->stemmer),
                        'url'    => $item->getUrl(),
                        'descr'  => $item->getSnippet(),
                        'time'   => $item->getDate() ? $item->getDate()->getTimestamp() : null,
                        'images' => $item->getImageCollection(),
                        'debug'  => ($content['trace'][(new ExternalId($item->getId()))->toString()]),
                    ]);
                }

                $link_nav          = array();
                $content['paging'] = s2_paging($this->page_num, $total_pages, s2_link('/search', array('q=' . str_replace('%', '%%', urlencode($query)), 'p=%d')), $link_nav);
                foreach ($link_nav as $rel => $href) {
                    $this->page['link_navigation'][$rel] = $href;
                }
            }
        }

        $this->page['text']  = $this->renderPartial('search', $content);
        $this->page['title'] = Lang::get('Search', 's2_search');
        $this->page['path']  = array(
            array(
                'title' => \Model::main_page_title(),
                'link'  => s2_link('/'),
            ),
            array(
                'title' => Lang::get('Search', 's2_search'),
            ),
        );
    }

    // TODO think about html refactoring
    // TODO rename hooks
    private function findInTags(Query $query)
    {
        /** @var \DBLayer_Abstract $s2_db */
        $s2_db = \Container::get('db');

        $return = '';

        ($hook = s2_hook('s2_search_pre_tags')) ? eval($hook) : null;

        $words = $query->valueToArray();
        if (count($words) === 0) {
            return $return;
        }

        $stemmedWords = array_map(fn($word) => $this->stemmer->stemWord($word), $words);
        $words        = array_unique(array_merge($words, $stemmedWords));

        $sql = array(
            'SELECT' => '1',
            'FROM'   => 'article_tag AS at',
            'JOINS'  => array(
                array(
                    'INNER JOIN' => 'articles AS a',
                    'ON'         => 'a.id = at.article_id'
                )
            ),
            'WHERE'  => 'at.tag_id = t.tag_id AND a.published = 1',
            'LIMIT'  => '1'
        );
        ($hook = s2_hook('s2_search_pre_find_tags_sub_qr')) ? eval($hook) : null;
        $s2_search_sub_sql = $s2_db->query_build($sql, true);

        $where = array_map(static fn($word) => 'name LIKE \'' . $s2_db->escape($word) . '%\' OR name LIKE \'% ' . $s2_db->escape($word) . '%\'', $words);
        $sql   = array(
            'SELECT' => 'tag_id, name, url, (' . $s2_search_sub_sql . ') AS used',
            'FROM'   => 'tags AS t',
            'WHERE'  => implode(' OR ', $where),
        );
        ($hook = s2_hook('s2_search_pre_find_tags_qr')) ? eval($hook) : null;
        $s2_search_result = $s2_db->query_build($sql);

        $tags = array();
        while ($s2_search_row = $s2_db->fetch_assoc($s2_search_result)) {
            ($hook = s2_hook('s2_search_find_tags_get_res')) ? eval($hook) : null;

            if ($s2_search_row['used'] && $this->tagIsSimilarToWords($s2_search_row['name'], $words)) {
                /** @noinspection PhpUndefinedConstantInspection */
                $tags[] = '<a href="' . s2_link('/' . S2_TAGS_URL . '/' . urlencode($s2_search_row['url']) . '/') . '">' . $s2_search_row['name'] . '</a>';
            }
        }

        ($hook = s2_hook('s2_search_find_tags_pre_mrg')) ? eval($hook) : null;

        if (!empty($tags)) {
            $return .= '<p class="s2_search_found_tags">' . sprintf(Lang::get('Found tags', 's2_search'), implode(', ', $tags)) . '</p>';
        }

        ($hook = s2_hook('s2_search_find_tags_end')) ? eval($hook) : null;

        return $return;
    }

    private function tagIsSimilarToWords(string $name, array $words): bool
    {
        $foundWordsInTags = array_filter(explode(' ', $name), static fn($word) => mb_strlen($word) > 2);
        $foundStemsInTags = array_map(fn(string $keyPart) => $this->stemmer->stemWord($keyPart), $foundWordsInTags);

        foreach ($foundStemsInTags as $foundStemInTags) {
            foreach ($words as $word) {
                if ($word === $foundStemInTags || (strpos($foundStemInTags, $word) === 0 && mb_strlen($word) >= 5)) {
                    return true;
                }
            }
        }

        return false;
    }
}
