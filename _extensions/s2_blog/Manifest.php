<?php
/**
 * Blog
 *
 * Allows to add a blog to your S2 site
 *
 * @copyright 2007-2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   s2_blog
 */

declare(strict_types=1);

namespace s2_extensions\s2_blog;

use S2\Cms\Extensions\ManifestInterface;
use S2\Cms\Extensions\ManifestTrait;
use S2\Cms\Framework\Container;
use S2\Cms\Pdo\DbLayer;
use S2\Cms\Pdo\DbLayerException;
use S2\Cms\Pdo\SchemaBuilderInterface;

class Manifest implements ManifestInterface
{
    use ManifestTrait;

    public function getTitle(): string
    {
        return 'Blog';
    }

    public function getAuthor(): string
    {
        return 'Roman Parpalak';
    }

    public function getDescription(): string
    {
        return 'Adds a blog to your site.';
    }

    public function getVersion(): string
    {
        return '2.0a2';
    }

    public function getUninstallationNote(): ?string
    {
        return 'Warning! All your posts and user comments will be deleted during the uninstall process. It is strongly recommended you to disable \'Blog\' extension instead or to upgrade it without uninstalling.';
    }

    /**
     * @throws DbLayerException
     */
    public function install(DbLayer $dbLayer, Container $container, ?string $currentVersion): void
    {
        // Setup posts table
        if (!$dbLayer->tableExists('s2_blog_posts')) {
            $dbLayer->createTable('s2_blog_posts', function (SchemaBuilderInterface $table) {
                $table
                    ->addIdColumn()
                    ->addInteger('create_time', true)
                    ->addInteger('modify_time', true)
                    ->addInteger('revision', true, default: 1)
                    ->addString('title', 255)
                    ->addLongText('text', nullable: false)
                    ->addBoolean('published')
                    ->addBoolean('favorite')
                    ->addBoolean('commented', default: true)
                    ->addString('label', 255)
                    ->addString('url', 255)
                    ->addInteger('user_id', true, nullable: true, default: null)
                    ->addForeignKey(
                        'fk_user',
                        ['user_id'],
                        'users',
                        ['id'],
                        'SET NULL',
                    )
                    ->addIndex('url_idx', ['url'])
                    ->addIndex('create_time_published_idx', ['create_time', 'published'])
                    ->addIndex('id_published_idx', ['id', 'published'])
                    ->addIndex('favorite_idx', ['favorite'])
                    ->addIndex('label_idx', ['label'])
                ;
            });
        } else {
            $dbLayer->addField('s2_blog_posts', 'revision', SchemaBuilderInterface::TYPE_UNSIGNED_INTEGER, null, false, '1', 'modify_time');
            $dbLayer->addField('s2_blog_posts', 'user_id', SchemaBuilderInterface::TYPE_UNSIGNED_INTEGER, null, false, '0', 'url');
        }

        // For old installations
        $dbLayer->addIndex('s2_blog_posts', 'create_time_published_idx', array('create_time', 'published'));
        $dbLayer->addIndex('s2_blog_posts', 'id_published_idx', array('id', 'published'));
        $dbLayer->addIndex('s2_blog_posts', 'favorite_idx', array('favorite'));

        // Setup blog comments table
        if (!$dbLayer->tableExists('s2_blog_comments')) {
            $dbLayer->createTable('s2_blog_comments', function (SchemaBuilderInterface $table) {
                $table
                    ->addIdColumn()
                    ->addInteger('post_id', true, default: null)
                    ->addInteger('time', true)
                    ->addString('ip', 39)
                    ->addString('nick', 50)
                    ->addString('email', 80)
                    ->addBoolean('show_email')
                    ->addBoolean('subscribed')
                    ->addBoolean('shown', default: true)
                    ->addBoolean('sent', default: true)
                    ->addBoolean('good')
                    ->addText('text', nullable: false)
                    ->addForeignKey(
                        'fk_post',
                        ['post_id'],
                        's2_blog_posts',
                        ['id'],
                        'CASCADE',
                    )
                    ->addIndex('sort_idx', ['post_id', 'time', 'shown'])
                    ->addIndex('time_idx', ['time'])
                ;
            });
        }

        // For old installations
        $dbLayer->addIndex('s2_blog_comments', 'sort_idx', array('post_id', 'time', 'shown'));

        // Setup table to link posts and tags
        if (!$dbLayer->tableExists('s2_blog_post_tag')) {
            $dbLayer->createTable('s2_blog_post_tag', function (SchemaBuilderInterface $table) {
                $table
                    ->addIdColumn()
                    ->addInteger('post_id', true, default: null)
                    ->addInteger('tag_id', true, default: null)
                    ->addForeignKey(
                        'fk_post',
                        ['post_id'],
                        's2_blog_posts',
                        ['id'],
                        'CASCADE',
                    )
                    ->addForeignKey(
                        'fk_tag',
                        ['tag_id'],
                        'tags',
                        ['id'],
                        'CASCADE',
                    )
                    ->addIndex('post_id_idx', ['post_id'])
                    ->addIndex('tag_id_idx', ['tag_id'])
                ;
            });
        }

        // Add extension options to the config table
        $config = [
            'S2_BLOG_URL'   => '/blog',
            'S2_BLOG_TITLE' => 'My blog',
        ];

        foreach ($config as $confName => $confValue) {
            $dbLayer->insert('config')
                ->setValue('name', ':name')->setParameter('name', $confName)
                ->setValue('value', ':value')->setParameter('value', $confValue)
                ->onConflictDoNothing('name')
                ->execute()
            ;
        }

        // User permissions
        if ($dbLayer->fieldExists('users', 'edit_s2_blog')) {
            $dbLayer->dropField('users', 'edit_s2_blog');
        }

        // A field in tags table for important tags displaying
        $dbLayer->addField('tags', 's2_blog_important', SchemaBuilderInterface::TYPE_BOOLEAN, null, false, 0);

        $dbLayer->addIndex('tags', 's2_blog_important_idx', array('s2_blog_important'));

        if ($currentVersion !== null && version_compare($currentVersion, '2.0a1', '<')) {
            $dbLayer->alterField('s2_blog_posts', 'user_id', SchemaBuilderInterface::TYPE_UNSIGNED_INTEGER, null, true);
            $dbLayer->update('s2_blog_posts')
                ->set('user_id', 'NULL')
                ->where('user_id = 0')
                ->execute()
            ;
        }

        if ($currentVersion !== null && version_compare($currentVersion, '2.0a2', '<')) {
            $dbLayer->dropIndex('s2_blog_posts', 'create_time_idx');
            $existingUsers = $dbLayer->select('id')->from('users')->execute()->fetchColumn();
            $dbLayer->update('s2_blog_posts')
                ->set('user_id', 'NULL')
                ->where('user_id NOT IN (' . implode(',', array_fill(0, \count($existingUsers), '?')) . ')')
                ->execute($existingUsers)
            ;
            $dbLayer->addForeignKey('s2_blog_posts', 'fk_user', ['user_id'], 'users', ['id'], 'SET NULL');

            $dbLayer->alterField('s2_blog_comments', 'post_id', SchemaBuilderInterface::TYPE_UNSIGNED_INTEGER, null, false);
            $dbLayer->addForeignKey('s2_blog_comments', 'fk_post', ['post_id'], 's2_blog_posts', ['id'], 'CASCADE');
            $dbLayer->dropIndex('s2_blog_comments', 'post_id_idx');

            $dbLayer->query('DELETE FROM ' . $dbLayer->getPrefix() . 's2_blog_post_tag WHERE post_id NOT IN (SELECT id FROM ' . $dbLayer->getPrefix() . 's2_blog_posts)');
            $dbLayer->query('DELETE FROM ' . $dbLayer->getPrefix() . 's2_blog_post_tag WHERE tag_id NOT IN (SELECT id FROM ' . $dbLayer->getPrefix() . 'tags)');

            $dbLayer->alterField('s2_blog_post_tag', 'post_id', SchemaBuilderInterface::TYPE_UNSIGNED_INTEGER, null, false);
            $dbLayer->alterField('s2_blog_post_tag', 'tag_id', SchemaBuilderInterface::TYPE_UNSIGNED_INTEGER, null, false);
            $dbLayer->addForeignKey('s2_blog_post_tag', 'fk_post', ['post_id'], 's2_blog_posts', ['id'], 'CASCADE');
            $dbLayer->addForeignKey('s2_blog_post_tag', 'fk_tag', ['tag_id'], 'tags', ['id'], 'CASCADE');
        }
    }

    /**
     * @throws DbLayerException
     */
    public function uninstall(DbLayer $dbLayer, Container $container): void
    {
        if ($dbLayer->tableExists('config')) {
            $dbLayer
                ->delete('config')
                ->where('name in (\'S2_BLOG_URL\', \'S2_BLOG_TITLE\')')
                ->execute()
            ;
        }

        $dbLayer->dropTable('s2_blog_post_tag');
        $dbLayer->dropTable('s2_blog_comments');
        $dbLayer->dropTable('s2_blog_posts');

        if ($dbLayer->tableExists('tags')) {
            $dbLayer->dropIndex('tags', 's2_blog_important_idx');
        }
        $dbLayer->dropField('tags', 's2_blog_important');
    }
}
