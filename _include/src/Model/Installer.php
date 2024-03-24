<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license MIT
 * @package S2
 */

declare(strict_types=1);

namespace S2\Cms\Model;

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
        $schema = array(
            'FIELDS'      => array(
                'name'  => array(
                    'datatype'   => 'VARCHAR(255)',
                    'allow_null' => false,
                    'default'    => '\'\''
                ),
                'value' => array(
                    'datatype'   => 'TEXT',
                    'allow_null' => true
                )
            ),
            'PRIMARY KEY' => array('name')
        );

        $this->dbLayer->createTable('config', $schema);


        $schema = array(
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
        );

        $this->dbLayer->createTable('extensions', $schema);

        $schema = array(
            'FIELDS'      => array(
                'id'          => array(
                    'datatype'   => 'SERIAL',
                    'allow_null' => false
                ),
                'parent_id'   => array(
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
                    'allow_null' => false,
                    'default'    => '0'
                )
            ),
            'PRIMARY KEY' => array('id'),
            'INDEXES'     => array(
                'url_idx'         => array('url'),
                'create_time_idx' => array('create_time'),
                'parent_id_idx'   => array('parent_id'),
                'children_idx'    => array('parent_id', 'published'),
                'template_idx'    => array('template')
            )
        );

        $this->dbLayer->createTable('articles', $schema);


        $schema = array(
            'FIELDS'      => array(
                'id'         => array(
                    'datatype'   => 'SERIAL',
                    'allow_null' => false
                ),
                'article_id' => array(
                    'datatype'   => 'INT(10) UNSIGNED',
                    'allow_null' => false,
                    'default'    => '0'
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
            'PRIMARY KEY' => array('id'),
            'INDEXES'     => array(
                'article_id_idx' => array('article_id'),
                'sort_idx'       => array('article_id', 'time', 'shown'),
                'time_idx'       => array('time')
            )
        );

        $this->dbLayer->createTable('art_comments', $schema);


        $schema = array(
            'FIELDS'      => array(
                'tag_id'      => array(
                    'datatype'   => 'SERIAL',
                    'allow_null' => false
                ),
                'name'        => array(
                    'datatype'   => 'VARCHAR(255)',
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
                    'datatype'   => 'VARCHAR(255)',
                    'allow_null' => false,
                    'default'    => '\'\''
                ),
            ),
            'PRIMARY KEY' => array('tag_id'),
            'UNIQUE KEYS' => array(
                'name_idx' => array('name'),
                'url_idx'  => array('url')
            )
        );

        $this->dbLayer->createTable('tags', $schema);


        $schema = array(
            'FIELDS'      => array(
                'id'         => array(
                    'datatype'   => 'SERIAL',
                    'allow_null' => false
                ),
                'article_id' => array(
                    'datatype'   => 'INT(10) UNSIGNED',
                    'allow_null' => false,
                    'default'    => '0'
                ),
                'tag_id'     => array(
                    'datatype'   => 'INT(10) UNSIGNED',
                    'allow_null' => false,
                    'default'    => '0'
                ),
            ),
            'PRIMARY KEY' => array('id'),
            'INDEXES'     => array(
                'article_id_idx' => array('article_id'),
                'tag_id_idx'     => array('tag_id'),
            ),
        );

        $this->dbLayer->createTable('article_tag', $schema);


        $schema = array(
            'FIELDS'  => array(
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
                    'datatype'   => 'VARCHAR(200)',
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
            'INDEXES' => array(
                'challenge_idx' => array('challenge'),
                'login_idx'     => array('login'),
            ),
        );

        $this->dbLayer->createTable('users_online', $schema);


        $schema = array(
            'FIELDS'      => array(
                'id'              => array(
                    'datatype'   => 'SERIAL',
                    'allow_null' => false
                ),
                'login'           => array(
                    'datatype'   => 'VARCHAR(200)',
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
        );

        $this->dbLayer->createTable('users', $schema);

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
}
