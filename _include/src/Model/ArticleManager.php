<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license   http://opensource.org/licenses/MIT MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Model;

use S2\AdminYard\Config\FieldConfig;
use S2\AdminYard\Form\FormParams;
use S2\Cms\Pdo\DbLayer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

readonly class ArticleManager
{
    public function __construct(
        private DbLayer      $dbLayer,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * Builds HTML tree for the admin panel
     */
    public function getChildBranches(int $id, ?string $search = null): array
    {
        $subquery          = [
            'SELECT' => 'COUNT(*)',
            'FROM'   => 'art_comments AS c',
            'WHERE'  => 'a.id = c.article_id'
        ];
        $comment_num_query = $this->dbLayer->build($subquery);

        $query = [
            'SELECT'   => 'title, id, create_time, priority, published, (' . $comment_num_query . ') as comment_num, parent_id',
            'FROM'     => 'articles AS a',
            'WHERE'    => 'parent_id = ' . $id,
            'ORDER BY' => 'priority'
        ];
        if ($search !== null) {
            // This function also can search through the site :)
            $condition = [];
            foreach (explode(' ', $search) as $word) {
                if ($word !== '') {
                    if ($word[0] === ':' && \strlen($word) > 1) {
                        $subquery    = [
                            'SELECT' => 'count(*)',
                            'FROM'   => 'article_tag AS at',
                            'JOINS'  => [
                                [
                                    'INNER JOIN' => 'tags AS t',
                                    'ON'         => 't.tag_id = at.tag_id'
                                ]
                            ],
                            'WHERE'  => 'a.id = at.article_id AND t.name LIKE \'%' . $this->dbLayer->escape(substr($word, 1)) . '%\'',
                            'LIMIT'  => '1'
                        ];
                        $tagQuery    = $this->dbLayer->build($subquery);
                        $condition[] = '(' . $tagQuery . ')';
                    } else {
                        $condition[] = '(title LIKE \'%' . $this->dbLayer->escape($word) . '%\' OR pagetext LIKE \'%' . $this->dbLayer->escape($word) . '%\')';
                    }
                }
            }

            if (\count($condition) > 0) {
                $query['SELECT'] .= ', (' . implode(' AND ', $condition) . ') AS found';

                $subquery        = [
                    'SELECT' => 'COUNT(*)',
                    'FROM'   => 'articles AS a2',
                    'WHERE'  => 'a2.parent_id = a.id'
                ];
                $child_num_query = $this->dbLayer->build($subquery);

                $query['SELECT'] .= ', (' . $child_num_query . ') as child_num';
            }
        }
        $result = $this->dbLayer->buildAndQuery($query);

        $output = [];
        while ($article = $this->dbLayer->fetchAssoc($result)) {
            $children = (!$search || $article['child_num']) ? $this->getChildBranches($article['id'], $search) : '';

            if ($search && (!$children && !$article['found'])) {
                continue;
            }

            $item = [
                'data' => [
                    'title' => $article['title'],
                ],
                'attr' => [
                    'data-id'         => $article['id'],
                    'data-csrf-token' => $this->getDeleteCsrfToken(['id' => (string)$article['id']], $this->requestStack->getMainRequest()),
                    'id'              => 'node_' . $article['id'],
                ],
            ];

            $classes = [];
            if ($search) {
                $classes[] = 'Search';
                if ($article['found']) {
                    $classes[] = 'Match';
                }
            }
            if (!$article['published']) {
                $classes[] = 'Draft';
            }
            if (\count($classes) > 0) {
                $item['data']['attr']['class'] = implode(' ', $classes);
            }

            if ($article['comment_num']) {
                $item['attr']['data-comments'] = $article['comment_num'];
            }

            if ($children) {
                if ($search) {
                    $item['state'] = 'open';
                }
                $item['children'] = $children;
            }
            $output[] = $item;
        }

        return $output;
    }

    protected function getDeleteCsrfToken(array $primaryKey, Request $request): string
    {
        $formParams = new FormParams('Article', [], $request, FieldConfig::ACTION_DELETE, $primaryKey);

        return $formParams->getCsrfToken();
    }
}
