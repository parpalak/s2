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
use S2\Cms\Framework\Exception\AccessDeniedException;
use S2\Cms\Framework\Exception\NotFoundException;
use S2\Cms\Pdo\DbLayer;
use S2\Cms\Pdo\DbLayerException;
use Symfony\Component\HttpFoundation\RequestStack;

readonly class ArticleManager
{
    public function __construct(
        private DbLayer           $dbLayer,
        private RequestStack      $requestStack,
        private PermissionChecker $permissionChecker,
        private bool              $newPositionOnTop,
        private bool              $useHierarchy,
    ) {
    }

    /**
     * Builds HTML tree for the admin panel
     *
     * @throws DbLayerException
     */
    public function getChildBranches(int $id, ?string $search = null): array
    {
        // TODO add published=1 if there is no view_hidden permission

        $subquery        = [
            'SELECT' => 'COUNT(*)',
            'FROM'   => 'art_comments AS c',
            'WHERE'  => 'a.id = c.article_id'
        ];
        $commentNumQuery = $this->dbLayer->build($subquery);

        $query = [
            'SELECT'   => 'title, id, create_time, priority, published, (' . $commentNumQuery . ') as comment_num, parent_id',
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
                                    'ON'         => 't.id = at.tag_id'
                                ]
                            ],
                            // TODO do not use escaping, use parameters instead
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
                    'data-csrf-token' => $this->getCsrfToken($article['id']),
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

    /**
     * @throws DbLayerException
     */
    public function createArticle(int $parentId, string $title): int
    {
        $result = $this->dbLayer->buildAndQuery([
            'SELECT' => '1',
            'FROM'   => 'articles',
            'WHERE'  => 'id = :id'
        ], ['id' => $parentId]);

        if (!$this->dbLayer->fetchAssoc($result)) {
            // parent_id must be an existing article. E.g. it's impossible to create another root with parent_id = 0.
            throw new NotFoundException('Item not found!');
        }

        if ($this->newPositionOnTop) {
            $this->dbLayer->buildAndQuery([
                'UPDATE' => 'articles',
                'SET'    => 'priority = priority + 1',
                'WHERE'  => 'parent_id = :id'
            ], ['id' => $parentId]);
            $newPriority = 0;

        } else {
            $result      = $this->dbLayer->buildAndQuery([
                'SELECT' => 'MAX(priority + 1)',
                'FROM'   => 'articles',
                'WHERE'  => 'parent_id = :id'
            ], ['id' => $parentId]);
            $newPriority = (int)$this->dbLayer->result($result);
        }

        $query = [
            'INSERT' => 'parent_id, title, priority, url, user_id, template, excerpt, pagetext',
            'INTO'   => 'articles',
            'VALUES' => ':parent_id, :title, :priority, :url, :user_id, :template, :excerpt, :pagetext'
        ];
        $this->dbLayer->buildAndQuery($query, [
            'parent_id' => $parentId,
            'title'     => $title,
            'priority'  => $newPriority,
            'url'       => 'new',
            'user_id'   => $this->permissionChecker->getUserId(),
            'template'  => $this->useHierarchy ? '' : 'site.php',
            'excerpt'   => '',
            'pagetext'  => '',
        ]);

        return (int)$this->dbLayer->insertId();
    }

    /**
     * @throws DbLayerException
     */
    public function renameArticle(int $id, string $title, string $csrfToken): void
    {
        if (!$this->permissionChecker->isGrantedAny(PermissionChecker::PERMISSION_CREATE_ARTICLES, PermissionChecker::PERMISSION_EDIT_SITE)) {
            throw new AccessDeniedException('Permission denied.');
        }

        if ($csrfToken !== $this->getCsrfToken($id)) {
            throw new AccessDeniedException('Invalid CSRF token!');
        }

        $result = $this->dbLayer->buildAndQuery([
            'SELECT' => 'user_id',
            'FROM'   => 'articles',
            'WHERE'  => 'id = :id',
        ], ['id' => $id]);

        if ($row = $this->dbLayer->fetchRow($result)) {
            [$userId] = $row;
        } else {
            throw new NotFoundException('Item not found!');
        }

        if (!$this->permissionChecker->isGranted(PermissionChecker::PERMISSION_EDIT_SITE) && $userId !== $this->permissionChecker->getUserId()) {
            throw new AccessDeniedException('You do not have permission to edit this article!');
        }

        $this->dbLayer->buildAndQuery([
            'UPDATE' => 'articles',
            'SET'    => 'title = :title',
            'WHERE'  => 'id = :id',
        ], ['id' => $id, 'title' => $title]);
    }

    /**
     * @throws DbLayerException
     */
    public function moveBranch(int $sourceId, int $destinationId, int $position, string $csrfToken): void
    {
        if (!$this->permissionChecker->isGrantedAny(PermissionChecker::PERMISSION_CREATE_ARTICLES, PermissionChecker::PERMISSION_EDIT_SITE)) {
            throw new AccessDeniedException('Permission denied.');
        }

        if ($csrfToken !== $this->getCsrfToken($sourceId)) {
            throw new AccessDeniedException('Invalid CSRF token!');
        }

        $result = $this->dbLayer->buildAndQuery([
            'SELECT' => 'priority, parent_id, user_id, id',
            'FROM'   => 'articles',
            'WHERE'  => 'id IN (:source_id, :destination_id)',
        ], ['source_id' => $sourceId, 'destination_id' => $destinationId]);

        $rows = $this->dbLayer->fetchAssocAll($result);
        if (\count($rows) !== 2) {
            throw new NotFoundException('Item not found!');
        }
        if ($rows[0]['id'] === $sourceId) {
            $sourcePriority = $rows[0]['priority'];
            $sourceParentId = $rows[0]['parent_id'];
            $sourceUserId   = $rows[0]['user_id'];
        } else {
            $sourcePriority = $rows[1]['priority'];
            $sourceParentId = $rows[1]['parent_id'];
            $sourceUserId   = $rows[1]['user_id'];
        }

        if (!$this->permissionChecker->isGranted(PermissionChecker::PERMISSION_EDIT_SITE) && $sourceUserId !== $this->permissionChecker->getUserId()) {
            throw new AccessDeniedException('You don\'t have permissions to move this article!');
        }

        $this->dbLayer->buildAndQuery([
            'UPDATE' => 'articles',
            'SET'    => 'priority = priority + 1',
            'WHERE'  => 'priority >= :priority AND parent_id = :parent_id'
        ], ['priority' => $position, 'parent_id' => $destinationId]);

        $this->dbLayer->buildAndQuery([
            'UPDATE' => 'articles',
            'SET'    => 'priority = :priority, parent_id = :parent_id',
            'WHERE'  => 'id = :id'
        ], [
            'priority'  => $position,
            'parent_id' => $destinationId,
            'id'        => $sourceId
        ]);

        $query = [
            'UPDATE' => 'articles',
            'SET'    => 'priority = priority - 1',
            'WHERE'  => 'parent_id = :parent_id AND priority > :priority'
        ];
        $this->dbLayer->buildAndQuery($query, [
            'priority'  => $sourcePriority,
            'parent_id' => $sourceParentId
        ]);
    }

    /**
     * @throws DbLayerException
     */
    public function deleteBranch(int $id, string $csrfToken): void
    {
        if (!$this->permissionChecker->isGrantedAny(
            PermissionChecker::PERMISSION_CREATE_ARTICLES,
            PermissionChecker::PERMISSION_EDIT_SITE)
        ) {
            throw new AccessDeniedException('Permission denied.');
        }

        if ($csrfToken !== $this->getCsrfToken($id)) {
            throw new AccessDeniedException('Invalid CSRF token!');
        }

        $result = $this->dbLayer->buildAndQuery([
            'SELECT' => 'priority, parent_id, user_id',
            'FROM'   => 'articles',
            'WHERE'  => 'id = :id',
        ], ['id' => $id]);

        if ($row = $this->dbLayer->fetchRow($result)) {
            [$priority, $parentId, $userId] = $row;
        } else {
            throw new NotFoundException('Item not found!');
        }

        if ($parentId === ArticleProvider::ROOT_ID) {
            throw new AccessDeniedException('Can\'t delete root item!');
        }

        if (!$this->permissionChecker->isGranted(PermissionChecker::PERMISSION_EDIT_SITE) && $userId !== $this->permissionChecker->getUserId()) {
            throw new AccessDeniedException('You don\'t have permissions to delete this article!');
        }

        $this->dbLayer->buildAndQuery([
            'UPDATE' => 'articles',
            'SET'    => 'priority = priority - 1',
            'WHERE'  => 'parent_id = :parent_id AND  priority > :priority',
        ], [
            'priority'  => $priority,
            'parent_id' => $parentId
        ]);

        $this->deleteItemAndChildren($id);
    }

    public function getCsrfToken(int $id): string
    {
        // This token is used for every action in the tree management actions.
        // I chose to use ACTION_DELETE since then it would be compatible with the AdminYard delete token.
        $formParams = new FormParams('Article', [], $this->requestStack->getMainRequest(), FieldConfig::ACTION_DELETE, ['id' => (string)$id]);

        return $formParams->getCsrfToken();
    }

    /**
     * @throws DbLayerException
     */
    private function deleteItemAndChildren(int $id): void
    {
        $result = $this->dbLayer->buildAndQuery([
            'SELECT' => 'id',
            'FROM'   => 'articles',
            'WHERE'  => 'parent_id = :id'
        ], ['id' => $id]);

        while ($row = $this->dbLayer->fetchRow($result)) {
            $this->deleteItemAndChildren($row[0]);
        }

        $this->dbLayer->buildAndQuery([
            'DELETE' => 'articles',
            'WHERE'  => 'id = :id'
        ], ['id' => $id]);

        $this->dbLayer->buildAndQuery([
            'DELETE' => 'article_tag',
            'WHERE'  => 'article_id = :id',
        ], ['id' => $id]);

        $this->dbLayer->buildAndQuery([
            'DELETE' => 'art_comments',
            'WHERE'  => 'article_id = :id',
        ], ['id' => $id]);
    }
}
