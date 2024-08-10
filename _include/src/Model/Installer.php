<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license   http://opensource.org/licenses/MIT MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Model;

use S2\Cms\AdminYard\UserSettingStorage;
use S2\Cms\Pdo\DbLayer;
use S2\Cms\Pdo\DbLayerException;

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
        $this->dbLayer->createTable('config', array(
            'FIELDS'      => array(
                'name'  => array(
                    'datatype'   => 'VARCHAR(191)',
                    'allow_null' => false,
                    'default'    => '\'\''
                ),
                'value' => array(
                    'datatype'   => 'TEXT',
                    'allow_null' => true
                )
            ),
            'PRIMARY KEY' => array('name')
        ));

        $this->dbLayer->createTable('extensions', array(
            'FIELDS'      => array(
                'id'             => array(
                    'datatype'   => 'VARCHAR(150)',
                    'allow_null' => false,
                    'default'    => '\'\''
                ),
                'title'          => array(
                    'datatype'   => 'VARCHAR(255)',
                    'allow_null' => false,
                    'default'    => '\'\''
                ),
                'version'        => array(
                    'datatype'   => 'VARCHAR(25)',
                    'allow_null' => false,
                    'default'    => '\'\''
                ),
                'description'    => array(
                    'datatype'   => 'TEXT',
                    'allow_null' => true
                ),
                'author'         => array(
                    'datatype'   => 'VARCHAR(50)',
                    'allow_null' => false,
                    'default'    => '\'\''
                ),
                'admin_affected' => array(
                    'datatype'   => 'TINYINT(1) UNSIGNED',
                    'allow_null' => false,
                    'default'    => '0'
                ),
                'uninstall_note' => array(
                    'datatype'   => 'TEXT',
                    'allow_null' => true
                ),
                'disabled'       => array(
                    'datatype'   => 'TINYINT(1)',
                    'allow_null' => false,
                    'default'    => '0'
                ),
                'dependencies'   => array(
                    'datatype'   => 'VARCHAR(255)',
                    'allow_null' => false,
                    'default'    => '\'\''
                )
            ),
            'PRIMARY KEY' => array('id')
        ));

        $this->dbLayer->createTable('users', array(
            'FIELDS'      => array(
                'id'              => array(
                    'datatype'   => 'SERIAL',
                    'allow_null' => false
                ),
                'login'           => array(
                    'datatype'   => 'VARCHAR(191)',
                    'allow_null' => false,
                    'default'    => '\'\''
                ),
                'password'        => array(
                    'datatype'   => 'VARCHAR(40)',
                    'allow_null' => false,
                    'default'    => '\'\''
                ),
                'email'           => array(
                    'datatype'   => 'VARCHAR(80)',
                    'allow_null' => false,
                    'default'    => '\'\''
                ),
                'name'            => array(
                    'datatype'   => 'VARCHAR(80)',
                    'allow_null' => false,
                    'default'    => '\'\''
                ),
                'view'            => array(
                    'datatype'   => 'TINYINT(1)',
                    'allow_null' => false,
                    'default'    => '0'
                ),
                'view_hidden'     => array(
                    'datatype'   => 'TINYINT(1)',
                    'allow_null' => false,
                    'default'    => '0'
                ),
                'hide_comments'   => array(
                    'datatype'   => 'TINYINT(1)',
                    'allow_null' => false,
                    'default'    => '0'
                ),
                'edit_comments'   => array(
                    'datatype'   => 'TINYINT(1)',
                    'allow_null' => false,
                    'default'    => '0'
                ),
                'create_articles' => array(
                    'datatype'   => 'INT(10) UNSIGNED',
                    'allow_null' => false,
                    'default'    => '0'
                ),
                'edit_site'       => array(
                    'datatype'   => 'TINYINT(1)',
                    'allow_null' => false,
                    'default'    => '0'
                ),
                'edit_users'      => array(
                    'datatype'   => 'TINYINT(1)',
                    'allow_null' => false,
                    'default'    => '0'
                ),
            ),
            'PRIMARY KEY' => array('id'),
            'UNIQUE KEYS' => array(
                'login_idx' => array('login'),
            )
        ));

        $this->dbLayer->createTable('articles', array(
            'FIELDS'       => array(
                'id'          => array(
                    'datatype'   => 'SERIAL',
                    'allow_null' => false
                ),
                'parent_id'   => array( // NOTE think about adding a foreign key here. What value must be set in parent_id for root article? Null? Now it is 0.
                    'datatype'   => 'INT(10) UNSIGNED',
                    'allow_null' => false,
                    'default'    => '0'
                ),
                'meta_keys'   => array(
                    'datatype'   => 'VARCHAR(255)',
                    'allow_null' => false,
                    'default'    => '\'\''
                ),
                'meta_desc'   => array(
                    'datatype'   => 'VARCHAR(255)',
                    'allow_null' => false,
                    'default'    => '\'\''
                ),
                'title'       => array(
                    'datatype'   => 'VARCHAR(255)',
                    'allow_null' => false,
                    'default'    => '\'\''
                ),
                'excerpt'     => array(
                    'datatype'   => 'TEXT',
                    'allow_null' => true
                ),
                'pagetext'    => array(
                    'datatype'   => 'LONGTEXT',
                    'allow_null' => true
                ),
                'create_time' => array(
                    'datatype'   => 'INT(10) UNSIGNED',
                    'allow_null' => false,
                    'default'    => '0'
                ),
                'modify_time' => array(
                    'datatype'   => 'INT(10) UNSIGNED',
                    'allow_null' => false,
                    'default'    => '0'
                ),
                'revision'    => array(
                    'datatype'   => 'INT(10) UNSIGNED',
                    'allow_null' => false,
                    'default'    => '1'
                ),
                'priority'    => array(
                    'datatype'   => 'INT(10) UNSIGNED',
                    'allow_null' => false,
                    'default'    => '0'
                ),
                'published'   => array(
                    'datatype'   => 'TINYINT(1)',
                    'allow_null' => false,
                    'default'    => '0'
                ),
                'favorite'    => array(
                    'datatype'   => 'TINYINT(1)',
                    'allow_null' => false,
                    'default'    => '0'
                ),
                'commented'   => array(
                    'datatype'   => 'TINYINT(1)',
                    'allow_null' => false,
                    'default'    => '1'
                ),
                'url'         => array(
                    'datatype'   => 'VARCHAR(255)',
                    'allow_null' => false,
                    'default'    => '\'\''
                ),
                'template'    => array(
                    'datatype'   => 'VARCHAR(30)',
                    'allow_null' => false,
                    'default'    => '\'\''
                ),
                'user_id'     => array(
                    'datatype'   => 'INT(10) UNSIGNED',
                    'allow_null' => true,
                )
            ),
            'PRIMARY KEY'  => array('id'),
            'FOREIGN KEYS' => array(
                'fk_user' => array(
                    'columns'           => ['user_id'],
                    'reference_table'   => 'users',
                    'reference_columns' => ['id'],
                    'on_delete'         => 'SET NULL',
                )
            ),
            'INDEXES'      => array(
                'url_idx'         => array('url'),
                'create_time_idx' => array('create_time'),
                'children_idx'    => array('parent_id', 'published'),
                'template_idx'    => array('template')
            )
        ));

        $this->dbLayer->createTable('art_comments', array(
            'FIELDS'       => array(
                'id'         => array(
                    'datatype'   => 'SERIAL',
                    'allow_null' => false
                ),
                'article_id' => array(
                    'datatype'   => 'INT(10) UNSIGNED',
                    'allow_null' => false,
                ),
                'time'       => array(
                    'datatype'   => 'INT(10) UNSIGNED',
                    'allow_null' => false,
                    'default'    => '0'
                ),
                'ip'         => array(
                    'datatype'   => 'VARCHAR(39)',
                    'allow_null' => false,
                    'default'    => '\'\''
                ),
                'nick'       => array(
                    'datatype'   => 'VARCHAR(50)',
                    'allow_null' => false,
                    'default'    => '\'\''
                ),
                'email'      => array(
                    'datatype'   => 'VARCHAR(80)',
                    'allow_null' => false,
                    'default'    => '\'\''
                ),
                'show_email' => array(
                    'datatype'   => 'TINYINT(1)',
                    'allow_null' => false,
                    'default'    => '0'
                ),
                'subscribed' => array(
                    'datatype'   => 'TINYINT(1)',
                    'allow_null' => false,
                    'default'    => '0'
                ),
                'shown'      => array(
                    'datatype'   => 'TINYINT(1)',
                    'allow_null' => false,
                    'default'    => '1'
                ),
                'sent'       => array(
                    'datatype'   => 'TINYINT(1)',
                    'allow_null' => false,
                    'default'    => '1'
                ),
                'good'       => array(
                    'datatype'   => 'TINYINT(1)',
                    'allow_null' => false,
                    'default'    => '0'
                ),
                'text'       => array(
                    'datatype'   => 'TEXT',
                    'allow_null' => true
                ),
            ),
            'PRIMARY KEY'  => array('id'),
            'FOREIGN KEYS' => array(
                'fk_article' => array(
                    'columns'           => ['article_id'],
                    'reference_table'   => 'articles',
                    'reference_columns' => ['id'],
                    'on_delete'         => 'CASCADE',
                )
            ),
            'INDEXES'      => array(
                'sort_idx' => array('article_id', 'time', 'shown'),
                'time_idx' => array('time')
            )
        ));

        $this->dbLayer->createTable('tags', array(
            'FIELDS'      => array(
                'id'          => array(
                    'datatype'   => 'SERIAL',
                    'allow_null' => false
                ),
                'name'        => array(
                    'datatype'   => 'VARCHAR(191)',
                    'allow_null' => false,
                    'default'    => '\'\''
                ),
                'description' => array(
                    'datatype'   => 'TEXT',
                    'allow_null' => true
                ),
                'modify_time' => array(
                    'datatype'   => 'INT(10) UNSIGNED',
                    'allow_null' => false,
                    'default'    => '0'
                ),
                'url'         => array(
                    'datatype'   => 'VARCHAR(191)',
                    'allow_null' => false,
                    'default'    => '\'\''
                ),
            ),
            'PRIMARY KEY' => array('id'),
            'UNIQUE KEYS' => array(
                'name_idx' => array('name'),
                'url_idx'  => array('url')
            )
        ));

        $this->dbLayer->createTable('article_tag', array(
            'FIELDS'       => array(
                'id'         => array(
                    'datatype'   => 'SERIAL',
                    'allow_null' => false
                ),
                'article_id' => array(
                    'datatype'   => 'INT(10) UNSIGNED',
                    'allow_null' => false,
                ),
                'tag_id'     => array(
                    'datatype'   => 'INT(10) UNSIGNED',
                    'allow_null' => false,
                ),
            ),
            'PRIMARY KEY'  => array('id'),
            'FOREIGN KEYS' => array(
                'fk_article' => array(
                    'columns'           => ['article_id'],
                    'reference_table'   => 'articles',
                    'reference_columns' => ['id'],
                    'on_delete'         => 'CASCADE',
                ),
                'fk_tag'     => array(
                    'columns'           => ['tag_id'],
                    'reference_table'   => 'tags',
                    'reference_columns' => ['id'],
                    'on_delete'         => 'CASCADE',
                ),
            ),
            'INDEXES'      => array(
                'article_id_idx' => array('article_id'),
                'tag_id_idx'     => array('tag_id'),
            ),
        ));

        $this->dbLayer->createTable('users_online', array(
            'FIELDS'       => array(
                'challenge'      => array(
                    'datatype'   => 'VARCHAR(32)',
                    'allow_null' => false,
                    'default'    => '\'\''
                ),
                'salt'           => array(
                    'datatype'   => 'VARCHAR(32)',
                    'allow_null' => false,
                    'default'    => '\'\''
                ),
                'time'           => array(
                    'datatype'   => 'INT(10) UNSIGNED',
                    'allow_null' => false,
                    'default'    => '0'
                ),
                'login'          => array(
                    'datatype'   => 'VARCHAR(191)',
                    'allow_null' => true
                ),
                'ip'             => array(
                    'datatype'   => 'VARCHAR(39)',
                    'allow_null' => false,
                    'default'    => '\'\''
                ),
                'ua'             => array(
                    'datatype'   => 'VARCHAR(200)',
                    'allow_null' => false,
                    'default'    => '\'\''
                ),
                'comment_cookie' => array(
                    'datatype'   => 'VARCHAR(32)',
                    'allow_null' => false,
                    'default'    => '\'\''
                ),
            ),
            'FOREIGN KEYS' => array(
                'fk_user' => array(
                    'columns'           => ['login'],
                    'reference_table'   => 'users',
                    'reference_columns' => ['login'],
                    'on_delete'         => 'CASCADE',
                )
            ),
            'INDEXES'      => array(
                'login_idx' => array('login'),
            ),
            'UNIQUE KEYS'  => array(
                'challenge_idx' => array('challenge'),
            )
        ));

        $this->dbLayer->createTable(UserSettingStorage::TABLE_NAME, [
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
            'S2_SITE_NAME'        => "'" . $siteName . "'",
            'S2_WEBMASTER'        => "''",
            'S2_WEBMASTER_EMAIL'  => "'" . $email . "'",
            'S2_START_YEAR'       => "'" . date('Y') . "'",
            'S2_USE_HIERARCHY'    => "'1'",
            'S2_MAX_ITEMS'        => "'0'",
            'S2_FAVORITE_URL'     => "'favorite'",
            'S2_TAGS_URL'         => "'tags'",
            'S2_COMPRESS'         => "'1'",
            'S2_STYLE'            => "'zeta'",
            'S2_LANGUAGE'         => "'" . $this->dbLayer->escape($defaultLanguage) . "'",
            'S2_SHOW_COMMENTS'    => "'1'",
            'S2_ENABLED_COMMENTS' => "'1'",
            'S2_PREMODERATION'    => "'0'",
            'S2_ADMIN_COLOR'      => "'#eeeeee'",
            'S2_ADMIN_NEW_POS'    => "'0'",
            'S2_ADMIN_CUT'        => "'0'",
            'S2_LOGIN_TIMEOUT'    => "'60'",
            'S2_DB_REVISION'      => "'" . $dbRevision . "'",
        ];

        foreach ($config as $conf_name => $conf_value) {
            $this->dbLayer->buildAndQuery([
                'INSERT' => 'name, value',
                'INTO'   => 'config',
                'VALUES' => '\'' . $conf_name . '\', ' . $conf_value . ''
            ]);
        }
    }

    /**
     * @throws DbLayerException
     */
    public function insertMainPage(string $title, int $time): void
    {
        $this->dbLayer->buildAndQuery([
            'INSERT' => 'parent_id, title, create_time, modify_time, published, template',
            'INTO'   => 'articles',
            'VALUES' => '0, \'' . $title . '\', 0, ' . $time . ', 1, \'mainpage.php\''
        ]);
    }
}
