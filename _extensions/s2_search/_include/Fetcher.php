<?php
/**
 * Provides data for building the search index
 *
 * @copyright (C) 2010-2023 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_search
 */

namespace s2_extensions\s2_search;

use S2\Rose\Entity\Indexable;

class Fetcher implements GenericFetcher
{
    private function crawl($parent_id, $url): \Generator
    {
        global $s2_db;

        $subQuery        = array(
            'SELECT' => 'count(*)',
            'FROM'   => 'articles AS a2',
            'WHERE'  => 'a2.parent_id = a.id',
            'LIMIT'  => '1'
        );
        $child_num_query = $s2_db->query_build($subQuery, true);

        $query = array(
            'SELECT' => 'title, id, create_time, url, (' . $child_num_query . ') as is_children, parent_id, meta_keys, meta_desc, pagetext',
            'FROM'   => 'articles AS a',
            'WHERE'  => 'parent_id = ' . $parent_id . ' AND published = 1',
        );
        ($hook = s2_hook('s2_search_fetcer_crawl_pre_qr')) ? eval($hook) : null;
        $result = $s2_db->query_build($query);

        while ($article = $s2_db->fetch_assoc($result)) {
            $indexable = new Indexable($article['id'], $article['title'], $article['pagetext']);
            $indexable
                ->setKeywords($article['meta_keys'])
                ->setDescription($article['meta_desc'])
                ->setDate($article['create_time'] > 0 ? new \DateTime('@' . $article['create_time']) : null)
                ->setUrl($url . urlencode($article['url']) . ($article['is_children'] ? '/' : ''))
            ;

            yield $indexable;

            $article['pagetext'] = '';

            yield from $this->crawl($article['id'], $url . urlencode($article['url']) . '/');
        }

        ($hook = s2_hook('s2_search_fetcher_crawl_end')) ? eval($hook) : null;
    }

    public function process(): \Generator
    {
        yield from $this->crawl(0, '');

        ($hook = s2_hook('s2_search_fetcher_process_end')) ? yield from (eval($hook))() : null;
    }

    public function chapter(string $id): ?Indexable
    {
        global $s2_db;

        $data = ($hook = s2_hook('s2_search_fetcher_chapter_start')) ? eval($hook) : null;
        if ($data instanceof Indexable) {
            return $data;
        }

        $subQuery        = array(
            'SELECT' => 'count(*)',
            'FROM'   => 'articles AS a2',
            'WHERE'  => 'a2.parent_id = a.id',
            'LIMIT'  => '1'
        );
        $child_num_query = $s2_db->query_build($subQuery, true);

        $query = array(
            'SELECT' => 'title, id, create_time, url, (' . $child_num_query . ') as is_children, parent_id, meta_keys, meta_desc, pagetext',
            'FROM'   => 'articles AS a',
            'WHERE'  => 'id = \'' . $s2_db->escape($id) . '\' AND published = 1',
        );
        ($hook = s2_hook('s2_search_fetcher_chapter_pre_qr')) ? eval($hook) : null;
        $result = $s2_db->query_build($query);

        $article = $s2_db->fetch_assoc($result);
        if (!$article) {
            return null;
        }

        $parent_path = \Model::path_from_id($article['parent_id'], true);
        if ($parent_path === false) {
            return null;
        }

        $indexable = new Indexable($article['id'], $article['title'], $article['pagetext']);
        $indexable
            ->setKeywords($article['meta_keys'])
            ->setDescription($article['meta_desc'])
            ->setDate($article['create_time'] > 0 ? new \DateTime('@' . $article['create_time']) : null)
            ->setUrl($parent_path . '/' . urlencode($article['url']) . ($article['url'] && $article['is_children'] ? '/' : ''))
        ;

        return $indexable;
    }

    public function texts(array $ids): array
    {
        global $s2_db;

        $articles = array();

        $result = ($hook = s2_hook('s2_search_fetcher_texts_start')) ? eval($hook) : null;
        if ($result) {
            return $articles;
        }

        foreach ($ids as $k => $v) {
            $ids[$k] = (int)$v;
        }

        if (count($ids) > 0) {
            // Obtaining articles text
            $query = array(
                'SELECT' => 'id, pagetext',
                'FROM'   => 'articles AS a',
                'WHERE'  => 'id IN (' . implode(', ', $ids) . ') AND published = 1',
            );
            ($hook = s2_hook('s2_search_fetcher_texts_pre_qr')) ? eval($hook) : null;
            $result = $s2_db->query_build($query);

            while ($article = $s2_db->fetch_assoc($result)) {
                $articles[$article['id']] = $article['pagetext'];
            }
        }

        return $articles;
    }
}
