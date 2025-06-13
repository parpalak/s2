<?php
/**
 * @copyright 2024-2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Model;

use S2\AdminYard\Config\FieldConfig;
use S2\AdminYard\Form\FormParams;
use S2\AdminYard\SettingStorage\SettingStorageInterface;
use S2\Cms\Framework\Exception\AccessDeniedException;
use S2\Cms\Framework\Exception\NotFoundException;
use S2\Cms\Pdo\DbLayer;
use S2\Cms\Pdo\DbLayerException;

readonly class ArticleManager
{
    public function __construct(
        private DbLayer                 $dbLayer,
        private SettingStorageInterface $settingStorage,
        private PermissionChecker       $permissionChecker,
        private bool                    $newPositionOnTop,
        private bool                    $useHierarchy,
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

        $commentNumQuery = $this->dbLayer
            ->select('COUNT(*)')
            ->from('art_comments AS c')
            ->where('a.id = c.article_id')
            ->getSql()
        ;

        $qb = $this->dbLayer
            ->select('title, id, create_time, priority, published, (' . $commentNumQuery . ') as comment_num, parent_id')
            ->from('articles AS a')
            ->where('parent_id = :id')->setParameter('id', $id)
            ->orderBy('priority')
        ;

        if ($search !== null) {
            // This function also can search through the site :)
            $condition  = [];
            $paramIndex = 0;
            foreach (explode(' ', $search) as $word) {
                if ($word === '') {
                    continue;
                }
                if ($word[0] === ':' && \strlen($word) > 1) {
                    $condition[] = '(' . $this->dbLayer
                            ->select('COUNT(*)')
                            ->from('article_tag AS at')
                            ->innerJoin('tags AS t', 't.id = at.tag_id')
                            ->where('a.id = at.article_id')
                            ->andWhere('t.name LIKE :param' . $paramIndex)
                            ->getSql()
                        . ')';
                    $qb->setParameter('param' . $paramIndex, '%' . substr($word, 1) . '%');
                    $paramIndex++;
                } else {
                    $condition[] = \sprintf("(title LIKE :param%s OR pagetext LIKE :param%s)", $paramIndex, $paramIndex + 1);
                    $qb->setParameter('param' . $paramIndex, '%' . $word . '%');
                    $paramIndex++;
                    $qb->setParameter('param' . $paramIndex, '%' . $word . '%');
                    $paramIndex++;
                }
            }

            if (\count($condition) > 0) {
                $qb
                    ->addSelect('(' . implode(' AND ', $condition) . ') AS found')
                    ->addSelect('(' . $this->dbLayer
                            ->select('COUNT(*)')
                            ->from('articles AS a2')
                            ->where('a2.parent_id = a.id')
                            ->getSql()
                        . ') AS child_num')
                ;
            }
        }
        $result = $qb->execute();

        $output = [];
        while ($article = $result->fetchAssoc()) {
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
     * @throws NotFoundException
     */
    public function createArticle(int $parentId, string $title): int
    {
        $result = $this->dbLayer
            ->select('1')
            ->from('articles')
            ->where('id = :id')->setParameter('id', $parentId)
            ->execute()
        ;

        if (!$result->fetchAssoc()) {
            // parent_id must be an existing article. E.g. it's impossible to create another root with parent_id = 0.
            throw new NotFoundException('Item not found!');
        }

        $this->dbLayer->startTransaction();

        if ($this->newPositionOnTop) {
            $this->dbLayer
                ->update('articles')
                ->set('priority', 'priority + 1')
                ->where('parent_id = :id')->setParameter('id', $parentId)
                ->execute()
            ;
            $newPriority = 0;

        } else {
            $result      = $this->dbLayer
                ->select('MAX(priority + 1)')
                ->from('articles')
                ->where('parent_id = :id')->setParameter('id', $parentId)
                ->execute()
            ;
            $newPriority = (int)$result->result();
        }

        $this->dbLayer
            ->insert('articles')
            ->setValue('parent_id', ':parent_id')->setParameter('parent_id', $parentId)
            ->setValue('title', ':title')->setParameter('title', $title)
            ->setValue('priority', ':priority')->setParameter('priority', $newPriority)
            ->setValue('url', ':url')->setParameter('url', 'new')
            ->setValue('user_id', ':user_id')->setParameter('user_id', $this->permissionChecker->getUserId())
            ->setValue('template', ':template')->setParameter('template', $this->useHierarchy ? '' : 'site.php')
            ->setValue('excerpt', ':excerpt')->setParameter('excerpt', '')
            ->setValue('pagetext', ':pagetext')->setParameter('pagetext', '')
            ->execute()
        ;
        $insertId = (int)$this->dbLayer->insertId();

        $this->dbLayer->endTransaction();

        return $insertId;
    }

    /**
     * @throws DbLayerException
     * @throws AccessDeniedException
     * @throws NotFoundException
     */
    public function renameArticle(int $id, string $title, string $csrfToken): void
    {
        if (!$this->permissionChecker->isGrantedAny(PermissionChecker::PERMISSION_CREATE_ARTICLES, PermissionChecker::PERMISSION_EDIT_SITE)) {
            throw new AccessDeniedException('Permission denied.');
        }

        if ($csrfToken !== $this->getCsrfToken($id)) {
            throw new AccessDeniedException('Invalid CSRF token!');
        }

        $result = $this->dbLayer
            ->select('user_id')
            ->from('articles')
            ->where('id = :id')->setParameter('id', $id)
            ->execute()
        ;

        if ($row = $result->fetchRow()) {
            [$userId] = $row;
        } else {
            throw new NotFoundException('Item not found!');
        }

        if (!$this->permissionChecker->isGranted(PermissionChecker::PERMISSION_EDIT_SITE) && $userId !== $this->permissionChecker->getUserId()) {
            throw new AccessDeniedException('You do not have permission to edit this article!');
        }

        $this->dbLayer
            ->update('articles')
            ->set('title', ':title')->setParameter('title', $title)
            ->where('id = :id')->setParameter('id', $id)
            ->execute()
        ;
    }

    /**
     * @throws DbLayerException
     * @throws AccessDeniedException
     * @throws NotFoundException
     */
    public function moveBranch(int $sourceId, int $destinationId, int $position, string $csrfToken): void
    {
        if (!$this->permissionChecker->isGrantedAny(PermissionChecker::PERMISSION_CREATE_ARTICLES, PermissionChecker::PERMISSION_EDIT_SITE)) {
            throw new AccessDeniedException('Permission denied.');
        }

        if ($csrfToken !== $this->getCsrfToken($sourceId)) {
            throw new AccessDeniedException('Invalid CSRF token!');
        }

        $result = $this->dbLayer
            ->select('priority, parent_id, user_id, id')
            ->from('articles')
            ->where('id IN (:source_id, :destination_id)')
            ->setParameter('source_id', $sourceId)
            ->setParameter('destination_id', $destinationId)
            ->execute()
        ;

        $rows = $result->fetchAssocAll();
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

        $this->dbLayer->startTransaction();

        $this->dbLayer
            ->update('articles')
            ->set('priority', 'priority + 1')
            ->where('priority >= :priority')->setParameter('priority', $position)
            ->andWhere('parent_id = :parent_id')->setParameter('parent_id', $destinationId)
            ->execute()
        ;

        $this->dbLayer
            ->update('articles')
            ->set('priority', ':priority')->setParameter('priority', $position)
            ->set('parent_id', ':parent_id')->setParameter('parent_id', $destinationId)
            ->where('id = :id')->setParameter('id', $sourceId)
            ->execute()
        ;

        $this->dbLayer
            ->update('articles')
            ->set('priority', 'priority - 1')
            ->where('parent_id = :parent_id')->setParameter('parent_id', $sourceParentId)
            ->andWhere('priority > :priority')->setParameter('priority', $sourcePriority)
            ->execute()
        ;

        $this->dbLayer->endTransaction();
    }

    /**
     * @throws DbLayerException
     * @throws AccessDeniedException
     * @throws NotFoundException
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

        $result = $this->dbLayer
            ->select('priority, parent_id, user_id')
            ->from('articles')
            ->where('id = :id')->setParameter('id', $id)
            ->execute()
        ;

        if ($row = $result->fetchRow()) {
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

        $this->dbLayer->startTransaction();

        $this->dbLayer
            ->update('articles')
            ->set('priority', 'priority - 1')
            ->where('parent_id = :parent_id')->setParameter('parent_id', $parentId)
            ->andWhere('priority > :priority')->setParameter('priority', $priority)
            ->execute()
        ;

        $this->deleteItemAndChildren($id);

        $this->dbLayer->endTransaction();
    }

    public function getCsrfToken(int $id): string
    {
        // This token is used for every action in the tree management actions.
        // I chose to use ACTION_DELETE since then it would be compatible with the AdminYard delete token.
        $formParams = new FormParams('Article', [], $this->settingStorage, FieldConfig::ACTION_DELETE, ['id' => (string)$id]);

        return $formParams->getCsrfToken();
    }

    /**
     * @throws DbLayerException
     */
    private function deleteItemAndChildren(int $id): void
    {
        $result = $this->dbLayer
            ->select('id')
            ->from('articles')
            ->where('parent_id = :id')->setParameter('id', $id)
            ->execute()
        ;

        while ($row = $result->fetchRow()) {
            $this->deleteItemAndChildren($row[0]);
        }

        $this->dbLayer
            ->delete('articles')
            ->where('id  = :id')->setParameter('id', $id)
            ->execute()
        ;

        $this->dbLayer
            ->delete('article_tag')
            ->where('article_id = :id')->setParameter('id', $id)
            ->execute()
        ;

        $this->dbLayer
            ->delete('art_comments')
            ->where('article_id = :id')->setParameter('id', $id)
            ->execute()
        ;
    }
}
