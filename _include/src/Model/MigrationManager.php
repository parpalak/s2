<?php
/**
 * @copyright 2011-2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Model;

use S2\Cms\Pdo\DbLayer;
use S2\Cms\Pdo\DbLayerException;

class MigrationManager
{
    private const S2_DB_LAST_REVISION = 22;

    public function __construct(
        private readonly DbLayer $dbLayer,
        private readonly string $dbType,
    ) {
    }

    /**
     * @throws DbLayerException
     */
    public function migrate(int $currentRevision, int $lastRevision): void
    {
        if ($lastRevision !== self::S2_DB_LAST_REVISION) {
            throw new \LogicException('Invalid last revision.');
        }

        if ($currentRevision < 2) {
            $query = array(
                'INSERT' => 'name, value',
                'INTO'   => 'config',
                'VALUES' => '\'S2_MAX_ITEMS\', \'0\''
            );

            $this->dbLayer->buildAndQuery($query);
        }

        if ($currentRevision < 3) {
            $query = array(
                'INSERT' => 'name, value',
                'INTO'   => 'config',
                'VALUES' => '\'S2_ADMIN_COLOR\', \'#eeeeee\''
            );

            $this->dbLayer->buildAndQuery($query);
        }

        if ($currentRevision < 4) {
            $this->dbLayer->addIndex('articles', 'children_idx', array('parent_id', 'published'));
            $this->dbLayer->addIndex('art_comments', 'sort_idx', array('article_id', 'time', 'shown'));
            $this->dbLayer->addIndex('tags', 'url_idx', array('url'));
        }

        if ($currentRevision < 5) {
            $this->dbLayer->addField('articles', 'user_id', 'INT(10) UNSIGNED', false, '0', 'template');
            $this->dbLayer->addField('articles', 'revision', 'INT(10) UNSIGNED', false, '1', 'modify_time');
            $this->dbLayer->addField('users', 'create_articles', 'INT(10) UNSIGNED', false, '0', 'edit_comments');

            $query = array(
                'UPDATE' => 'users',
                'SET'    => 'create_articles = edit_site'
            );

            $this->dbLayer->buildAndQuery($query);
        }

        if ($currentRevision < 6) {
            $this->dbLayer->addField('users', 'name', 'VARCHAR(80)', false, '', 'password');
        }

        if ($currentRevision < 7) {
            $this->dbLayer->dropField('articles', 'children_preview');
        }

        if ($currentRevision < 8) {
            $this->dbLayer->addField('users_online', 'ua', 'VARCHAR(200)', false, '', 'login');
            $this->dbLayer->addField('users_online', 'ip', 'VARCHAR(39)', false, '', 'login');
            $this->dbLayer->addIndex('users_online', 'login_idx', array('login'));
        }

        if ($currentRevision < 9) {
            $query = array(
                'INSERT' => 'name, value',
                'INTO'   => 'config',
                'VALUES' => '\'S2_ADMIN_NEW_POS\', \'0\''
            );

            $this->dbLayer->buildAndQuery($query);
        }

        if ($currentRevision < 10) {
            $check_for_updates = (\function_exists('curl_init') || \function_exists('fsockopen') || \in_array(strtolower(@\ini_get('allow_url_fopen')), array('on', 'true', '1'))) ? '1' : '0';

            $query = array(
                'INSERT' => 'name, value',
                'INTO'   => 'config',
                'VALUES' => '\'S2_ADMIN_UPDATES\', \'' . $check_for_updates . '\''
            );

            $this->dbLayer->buildAndQuery($query);
        }

        if ($currentRevision < 11) {
            $this->dbLayer->addField('extensions', 'admin_affected', 'TINYINT(1) UNSIGNED', false, '0', 'author');
        }

        if ($currentRevision < 12) {
            $query = array(
                'INSERT' => 'name, value',
                'INTO'   => 'config',
                'VALUES' => '\'S2_ADMIN_CUT\', \'0\''
            );

            $this->dbLayer->buildAndQuery($query);
        }

        if ($currentRevision < 13) {
            $this->dbLayer->addField('users_online', 'comment_cookie', 'VARCHAR(32)', false, '', 'ua');
        }

        if ($currentRevision < 14) {
            $query = array(
                'INSERT' => 'name, value',
                'INTO'   => 'config',
                'VALUES' => '\'S2_USE_HIERARCHY\', \'1\''
            );

            $this->dbLayer->buildAndQuery($query);
        }

        if ($currentRevision < 15) {
            $this->dbLayer->createTable('queue', array(
                'FIELDS'      => array(
                    'id'      => array(
                        'datatype'   => 'VARCHAR(80)',
                        'allow_null' => false,
                    ),
                    'code'    => array(
                        'datatype'   => 'VARCHAR(80)',
                        'allow_null' => false
                    ),
                    'payload' => array(
                        'datatype'   => 'TEXT',
                        'allow_null' => false
                    ),
                ),
                'PRIMARY KEY' => array('id', 'code')
            ));
        }

        if ($currentRevision < 16 && $this->dbType === 'mysql') {
            foreach ([
                         'art_comments',
                         'article_tag',
                         'articles',
                         'config',
                         'extension_hooks',
                         'extensions',
                         'queue',
                         'tags',
                         'users',
                         'users_online',
                     ] as $table) {
                $this->dbLayer->query(\sprintf('ALTER TABLE `%s` ENGINE=InnoDB;', $this->dbLayer->getPrefix() . $table));
                $this->dbLayer->query(\sprintf('ALTER TABLE `%s` CONVERT TO CHARACTER SET utf8mb4;', $this->dbLayer->getPrefix() . $table));
            }
            foreach ([
                         's2_blog_comments',
                         's2_blog_post_tag',
                         's2_blog_posts',
                     ] as $table) {
                if ($this->dbLayer->tableExists($table)) {
                    $this->dbLayer->query(\sprintf('ALTER TABLE `%s` ENGINE=InnoDB;', $this->dbLayer->getPrefix() . $table));
                    $this->dbLayer->query(\sprintf('ALTER TABLE `%s` CONVERT TO CHARACTER SET utf8mb4;', $this->dbLayer->getPrefix() . $table));
                }
            }
        }

        if ($currentRevision < 17) {
            $this->dbLayer->buildAndQuery([
                'DELETE' => 'config',
                'WHERE'  => 'name = \'S2_ADMIN_UPDATES\'',
            ]);
        }

        if ($currentRevision < 18) {
            $this->dbLayer->dropTable('extension_hooks');
            $this->dbLayer->dropField('extensions', 'uninstall');
        }

        if ($currentRevision < 19) {
            $this->dbLayer->alterField('articles', 'user_id', 'INT(10) UNSIGNED', true);
            $this->dbLayer->buildAndQuery([
                'UPDATE' => 'articles',
                'SET'    => 'user_id = NULL',
                'WHERE'  => 'user_id = 0'
            ]);
        }

        if ($currentRevision < 20) {
            if ($this->dbLayer->fieldExists('tags', 'tag_id')) {
                $this->dbLayer->renameField('tags', 'tag_id', 'id');
            }
            $this->dbLayer->dropIndex('tags', 'url_idx');
            $this->dbLayer->addIndex('tags', 'url_idx', ['url'], true);

            $this->dbLayer->addIndex('articles', 'template_idx', ['template']);
            $this->dbLayer->dropIndex('articles', 'parent_id_idx');
            $result        = $this->dbLayer->buildAndQuery([
                'SELECT' => 'id',
                'FROM'   => 'users',
            ]);
            $existingUsers = $this->dbLayer->fetchColumn($result);
            $this->dbLayer->buildAndQuery([
                'UPDATE' => 'articles',
                'SET'    => 'user_id = NULL',
                'WHERE'  => 'user_id NOT IN (' . implode(',', $existingUsers) . ')',
            ]);
            $this->dbLayer->addForeignKey('articles', 'fk_user', ['user_id'], 'users', ['id'], 'SET NULL');

            $this->dbLayer->dropIndex('art_comments', 'article_id_idx');
            $this->dbLayer->alterField('art_comments', 'article_id', 'INT(10) UNSIGNED', false);
            $this->dbLayer->query('DELETE FROM ' . $this->dbLayer->getPrefix() . 'art_comments WHERE article_id NOT IN (SELECT id FROM ' . $this->dbLayer->getPrefix() . 'articles)');
            $this->dbLayer->addForeignKey('art_comments', 'fk_article', ['article_id'], 'articles', ['id'], 'CASCADE');

            $this->dbLayer->query('DELETE FROM ' . $this->dbLayer->getPrefix() . 'article_tag WHERE article_id NOT IN (SELECT id FROM ' . $this->dbLayer->getPrefix() . 'articles)');
            $this->dbLayer->query('DELETE FROM ' . $this->dbLayer->getPrefix() . 'article_tag WHERE tag_id NOT IN (SELECT id FROM ' . $this->dbLayer->getPrefix() . 'tags)');

            $this->dbLayer->alterField('article_tag', 'article_id', 'INT(10) UNSIGNED', false);
            $this->dbLayer->alterField('article_tag', 'tag_id', 'INT(10) UNSIGNED', false);
            $this->dbLayer->addForeignKey('article_tag', 'fk_article', ['article_id'], 'articles', ['id'], 'CASCADE');
            $this->dbLayer->addForeignKey('article_tag', 'fk_tag', ['tag_id'], 'tags', ['id'], 'CASCADE');

            $result         = $this->dbLayer->buildAndQuery([
                'SELECT' => 'login',
                'FROM'   => 'users',
            ]);
            $existingLogins = $this->dbLayer->fetchColumn($result);
            $this->dbLayer->buildAndQuery([
                'DELETE' => 'users_online',
                'WHERE'  => 'login NOT IN (' . implode(',', array_fill(0, \count($existingLogins), '?')) . ') AND login IS NOT NULL',
            ], $existingLogins);
            $this->dbLayer->addForeignKey('users_online', 'fk_user', ['login'], 'users', ['login'], 'CASCADE');
            $this->dbLayer->dropIndex('users_online', 'challenge_idx');
            $this->dbLayer->addIndex('users_online', 'challenge_idx', ['challenge'], true);
        }

        if ($currentRevision < 21) {
            $this->dbLayer->createTable('user_settings', [
                'FIELDS'       => [
                    'user_id' => [
                        'datatype'   => 'INT(10) UNSIGNED',
                        'allow_null' => false
                    ],
                    'name'    => [
                        'datatype'   => 'VARCHAR(191)',
                        'allow_null' => false
                    ],
                    'value'   => [
                        'datatype'   => 'TEXT',
                        'allow_null' => false
                    ],
                ],
                'PRIMARY KEY'  => ['user_id', 'name'],
                'FOREIGN KEYS' => [
                    'fk_user' => [
                        'columns'           => ['user_id'],
                        'reference_table'   => 'users',
                        'reference_columns' => ['id'],
                        'on_delete'         => 'CASCADE',
                    ],
                ]
            ]);
        }

        if ($currentRevision < 22) {
            $query = array(
                'INSERT' => 'name, value',
                'INTO'   => 'config',
                'VALUES' => '\'S2_AKISMET_KEY\', \'\'',
            );

            $this->dbLayer->buildAndQuery($query);
        }

        $this->dbLayer->buildAndQuery([
            'UPDATE' => 'config',
            'SET'    => 'value = \'' . self::S2_DB_LAST_REVISION . '\'',
            'WHERE'  => 'name = \'S2_DB_REVISION\'',
        ]);
    }
}
