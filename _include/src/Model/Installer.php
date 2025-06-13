<?php
/**
 * @copyright 2024-2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Model;

use S2\Cms\AdminYard\UserSettingStorage;
use S2\Cms\Pdo\DbLayer;
use S2\Cms\Pdo\DbLayerException;
use S2\Cms\Pdo\SchemaBuilderInterface;

readonly class Installer
{
    public function __construct(private DbLayer $dbLayer)
    {
    }

    /**
     * @throws DbLayerException
     */
    public function createTables(): void
    {
        // Create all tables
        $this->dbLayer->createTable('config', function (SchemaBuilderInterface $table) {
            $table
                ->addString('name', 191)
                ->addText('value', nullable: false)
                ->setPrimaryKey(['name'])
            ;
        });

        $this->dbLayer->createTable('extensions', function (SchemaBuilderInterface $table) {
            $table
                ->addString('id', 150)
                ->addString('title', 255)
                ->addString('version', 25)
                ->addText('description')
                ->addString('author', 50)
                ->addText('uninstall_note')
                ->addBoolean('disabled')
                ->addString('dependencies', 255)
                ->setPrimaryKey(['id'])
            ;
        });

        $this->dbLayer->createTable('users', function (SchemaBuilderInterface $table) {
            $table
                ->addIdColumn()
                ->addString('login', 191)
                ->addString('password', 40)
                ->addString('email', 80)
                ->addString('name', 80)
                ->addBoolean('view')
                ->addBoolean('view_hidden')
                ->addBoolean('hide_comments')
                ->addBoolean('edit_comments')
                ->addBoolean('create_articles')
                ->addBoolean('edit_site')
                ->addBoolean('edit_users')
                ->addUniqueIndex('login_idx', ['login'])
            ;
        });

        $this->dbLayer->createTable('articles', function (SchemaBuilderInterface $table) {
            $table
                ->addIdColumn()
                ->addInteger('parent_id', true) // NOTE think about adding a foreign key here. What value must be set in parent_id for root article? Null? Now it is 0.
                ->addString('meta_keys', 255)
                ->addString('meta_desc', 255)
                ->addString('title', 255)
                ->addText('excerpt', nullable: false)
                ->addLongText('pagetext', nullable: false)
                ->addInteger('create_time', true)
                ->addInteger('modify_time', true)
                ->addInteger('revision', true, false, 1)
                ->addInteger('priority', true)
                ->addBoolean('published')
                ->addBoolean('favorite')
                ->addBoolean('commented', false, true)
                ->addString('url', 255)
                ->addString('template', 30)
                ->addInteger('user_id', true, nullable: true, default: null)
                ->addForeignKey(
                    'fk_user',
                    ['user_id'],
                    'users',
                    ['id'],
                    'SET NULL',
                )
                ->addIndex('url_idx', ['url'])
                ->addIndex('create_time_idx', ['create_time'])
                ->addIndex('children_idx', ['parent_id', 'published'])
                ->addIndex('template_idx', ['template'])
            ;
        });

        $this->dbLayer->createTable('art_comments', function (SchemaBuilderInterface $table) {
            $table
                ->addIdColumn()
                ->addInteger('article_id', true, false, null)
                ->addInteger('time', true)
                ->addString('ip', 39)
                ->addString('nick', 50)
                ->addString('email', 80)
                ->addBoolean('show_email')
                ->addBoolean('subscribed')
                ->addBoolean('shown', false, true)
                ->addBoolean('sent', false, true)
                ->addBoolean('good')
                ->addText('text', nullable: false)
                ->addForeignKey(
                    'fk_article',
                    ['article_id'],
                    'articles',
                    ['id'],
                    'CASCADE'
                )
                ->addIndex('sort_idx', ['article_id', 'time', 'shown'])
                ->addIndex('time_idx', ['time'])
            ;
        });

        $this->dbLayer->createTable('tags', function (SchemaBuilderInterface $table) {
            $table
                ->addIdColumn()
                ->addString('name', 191)
                ->addText('description', nullable: false)
                ->addInteger('modify_time', true)
                ->addString('url', 191)
                ->addUniqueIndex('name_idx', ['name'])
                ->addUniqueIndex('url_idx', ['url'])
            ;
        });

        $this->dbLayer->createTable('article_tag', function (SchemaBuilderInterface $table) {
            $table
                ->addIdColumn()
                ->addInteger('article_id', true, false, null)
                ->addInteger('tag_id', true, false, null)
                ->addForeignKey(
                    'fk_article',
                    ['article_id'],
                    'articles',
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
                ->addIndex('article_id_idx', ['article_id'])
                ->addIndex('tag_id_idx', ['tag_id'])
            ;
        });

        $this->dbLayer->createTable('users_online', function (SchemaBuilderInterface $table) {
            $table->addString('challenge', 32)
                ->addString('salt', 32)
                ->addInteger('time', true)
                ->addString('login', 191, true, null)
                ->addString('ip', 39)
                ->addString('ua', 200)
                ->addString('comment_cookie', 32)
                ->addForeignKey(
                    'fk_user',
                    ['login'],
                    'users',
                    ['login'],
                    'CASCADE',
                )
                ->addIndex('login_idx', ['login'])
                ->addUniqueIndex('challenge_idx', ['challenge'])
            ;
        });

        $this->dbLayer->createTable(UserSettingStorage::TABLE_NAME, function (SchemaBuilderInterface $table) {
            $table
                ->addInteger('user_id', true, default: null)
                ->addString('name', 191, default: null)
                ->addText('value', nullable: false)
                ->setPrimaryKey(['user_id', 'name'])
                ->addForeignKey(
                    'fk_user',
                    ['user_id'],
                    'users',
                    ['id'],
                    'CASCADE',
                )
            ;
        });

        $this->dbLayer->createTable('queue', function (SchemaBuilderInterface $table) {
            $table
                ->addString('id', 80, default: null)
                ->addString('code', 80, default: null)
                ->addText('payload', nullable: false)
                ->setPrimaryKey(['id', 'code'])
            ;
        });
    }

    /**
     * @throws DbLayerException
     */
    public function dropTables(): void
    {
        $this->dbLayer->dropTable('queue');
        $this->dbLayer->dropTable('article_tag');
        $this->dbLayer->dropTable('tags');
        $this->dbLayer->dropTable('art_comments');
        $this->dbLayer->dropTable('articles');
        $this->dbLayer->dropTable('extensions');
        $this->dbLayer->dropTable('config');
        $this->dbLayer->dropTable(UserSettingStorage::TABLE_NAME);
        $this->dbLayer->dropTable('users_online');
        $this->dbLayer->dropTable('users');
    }

    /**
     * @throws DbLayerException
     */
    public function insertConfigData(string $siteName, string $email, string $defaultLanguage, int $dbRevision): void
    {
        // Insert config data
        $config = [
            'S2_SITE_NAME'        => $siteName,
            'S2_WEBMASTER'        => '',
            'S2_WEBMASTER_EMAIL'  => $email,
            'S2_START_YEAR'       => date('Y'),
            'S2_USE_HIERARCHY'    => '1',
            'S2_MAX_ITEMS'        => '0',
            'S2_FAVORITE_URL'     => 'favorite',
            'S2_TAGS_URL'         => 'tags',
            'S2_COMPRESS'         => '0',
            'S2_STYLE'            => 'zeta',
            'S2_LANGUAGE'         => $defaultLanguage,
            'S2_SHOW_COMMENTS'    => '1',
            'S2_ENABLED_COMMENTS' => '1',
            'S2_PREMODERATION'    => '0',
            'S2_AKISMET_KEY'      => '',
            'S2_ADMIN_COLOR'      => '#eeeeee',
            'S2_ADMIN_NEW_POS'    => '0',
            'S2_ADMIN_CUT'        => '0',
            'S2_LOGIN_TIMEOUT'    => '60',
            'S2_DB_REVISION'      => (string)$dbRevision,
        ];

        foreach ($config as $conf_name => $conf_value) {
            $this->dbLayer
                ->insert('config')
                ->setValue('name', ':name')->setParameter('name', $conf_name)
                ->setValue('value', ':value')->setParameter('value', $conf_value)
                ->execute()
            ;
        }
    }

    /**
     * @throws DbLayerException
     */
    public function insertMainPage(string $title, int $time): void
    {
        $this->dbLayer
            ->insert('articles')
            ->setValue('parent_id', ':parent_id')->setParameter('parent_id', ArticleProvider::ROOT_ID)
            ->setValue('title', ':title')->setParameter('title', $title)
            ->setValue('create_time', '0')
            ->setValue('modify_time', ':modify_time')->setParameter('modify_time', $time)
            ->setValue('published', '1')
            ->setValue('template', ':template')->setParameter('template', 'mainpage.php')
            ->setValue('excerpt', "''")
            ->setValue('pagetext', "''")
            ->execute()
        ;
    }
}
