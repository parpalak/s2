<?php
/**
 * Blog
 *
 * Allows to add a blog to your S2 site
 *
 * @copyright 2007-2024 Roman Parpalak
 * @license MIT
 * @package s2_blog
 */

declare(strict_types=1);

namespace s2_extensions\s2_blog;

use S2\Cms\Extensions\ManifestInterface;
use S2\Cms\Extensions\ManifestTrait;
use S2\Cms\Framework\Container;
use S2\Cms\Pdo\DbLayer;
use S2\Cms\Pdo\DbLayerException;

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
        return '2.0dev';
    }

    public function isAdminAffected(): bool
    {
        return true;
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
            $schema = [
                'FIELDS'      => [
                    'id'          => [
                        'datatype'   => 'SERIAL',
                        'allow_null' => false
                    ],
                    'create_time' => [
                        'datatype'   => 'INT(10) UNSIGNED',
                        'allow_null' => false,
                        'default'    => '0'
                    ],
                    'modify_time' => [
                        'datatype'   => 'INT(10) UNSIGNED',
                        'allow_null' => false,
                        'default'    => '0'
                    ],
                    'revision'    => [
                        'datatype'   => 'INT(10) UNSIGNED',
                        'allow_null' => false,
                        'default'    => '1'
                    ],
                    'title'       => [
                        'datatype'   => 'VARCHAR(255)',
                        'allow_null' => false,
                        'default'    => '\'\''
                    ],
                    'text'        => [
                        'datatype'   => 'LONGTEXT',
                        'allow_null' => true
                    ],
                    'published'   => [
                        'datatype'   => 'TINYINT(1)',
                        'allow_null' => false,
                        'default'    => '0'
                    ],
                    'favorite'    => [
                        'datatype'   => 'TINYINT(1)',
                        'allow_null' => false,
                        'default'    => '0'
                    ],
                    'commented'   => [
                        'datatype'   => 'TINYINT(1)',
                        'allow_null' => false,
                        'default'    => '1'
                    ],
                    'label'       => [
                        'datatype'   => 'VARCHAR(255)',
                        'allow_null' => false,
                        'default'    => '\'\''
                    ],
                    'url'         => [
                        'datatype'   => 'VARCHAR(255)',
                        'allow_null' => false,
                        'default'    => '\'\''
                    ],
                    'user_id'     => [
                        'datatype'   => 'INT(10) UNSIGNED',
                        'allow_null' => false,
                        'default'    => '0'
                    ]
                ],
                'PRIMARY KEY' => ['id'],
                'INDEXES'     => [
                    'url_idx'                   => ['url'],
                    'create_time_idx'           => ['create_time'],
                    'create_time_published_idx' => ['create_time', 'published'],
                    'id_published_idx'          => ['id', 'published'],
                    'favorite_idx'              => ['favorite'],
                    'label_idx'                 => ['label']
                ]
            ];

            $dbLayer->createTable('s2_blog_posts', $schema);
        } else {
            $dbLayer->addField('s2_blog_posts', 'revision', 'INT(10) UNSIGNED', false, '1', 'modify_time');
            $dbLayer->addField('s2_blog_posts', 'user_id', 'INT(10) UNSIGNED', false, '0', 'url');
        }

        // For old installations
        $dbLayer->addIndex('s2_blog_posts', 'create_time_published_idx', array('create_time', 'published'));
        $dbLayer->addIndex('s2_blog_posts', 'id_published_idx', array('id', 'published'));
        $dbLayer->addIndex('s2_blog_posts', 'favorite_idx', array('favorite'));

        // Setup blog comments table
        if (!$dbLayer->tableExists('s2_blog_comments')) {
            $schema = [
                'FIELDS'      => [
                    'id'         => [
                        'datatype'   => 'SERIAL',
                        'allow_null' => false
                    ],
                    'post_id'    => [
                        'datatype'   => 'INT(10) UNSIGNED',
                        'allow_null' => false,
                        'default'    => '0'
                    ],
                    'time'       => [
                        'datatype'   => 'INT(10) UNSIGNED',
                        'allow_null' => false,
                        'default'    => '0'
                    ],
                    'ip'         => [
                        'datatype'   => 'VARCHAR(39)',
                        'allow_null' => false,
                        'default'    => '\'\''
                    ],
                    'nick'       => [
                        'datatype'   => 'VARCHAR(50)',
                        'allow_null' => false,
                        'default'    => '\'\''
                    ],
                    'email'      => [
                        'datatype'   => 'VARCHAR(80)',
                        'allow_null' => false,
                        'default'    => '\'\''
                    ],
                    'show_email' => [
                        'datatype'   => 'TINYINT(1)',
                        'allow_null' => false,
                        'default'    => '0'
                    ],
                    'subscribed' => [
                        'datatype'   => 'TINYINT(1)',
                        'allow_null' => false,
                        'default'    => '0'
                    ],
                    'shown'      => [
                        'datatype'   => 'TINYINT(1)',
                        'allow_null' => false,
                        'default'    => '1'
                    ],
                    'sent'       => [
                        'datatype'   => 'TINYINT(1)',
                        'allow_null' => false,
                        'default'    => '1'
                    ],
                    'good'       => [
                        'datatype'   => 'TINYINT(1)',
                        'allow_null' => false,
                        'default'    => '0'
                    ],
                    'text'       => [
                        'datatype'   => 'TEXT',
                        'allow_null' => true
                    ],
                ],
                'PRIMARY KEY' => ['id'],
                'INDEXES'     => [
                    'post_id_idx' => ['post_id'],
                    'sort_idx'    => ['post_id', 'time', 'shown'],
                    'time_idx'    => ['time']
                ]
            ];

            $dbLayer->createTable('s2_blog_comments', $schema);
        }

        // For old installations
        $dbLayer->addIndex('s2_blog_comments', 'sort_idx', array('post_id', 'time', 'shown'));

        // Setup table to link posts and tags
        if (!$dbLayer->tableExists('s2_blog_post_tag')) {
            $schema = [
                'FIELDS'      => [
                    'id'      => [
                        'datatype'   => 'SERIAL',
                        'allow_null' => false
                    ],
                    'post_id' => [
                        'datatype'   => 'INT(10) UNSIGNED',
                        'allow_null' => false,
                        'default'    => '0'
                    ],
                    'tag_id'  => [
                        'datatype'   => 'INT(10) UNSIGNED',
                        'allow_null' => false,
                        'default'    => '0'
                    ],
                ],
                'PRIMARY KEY' => ['id'],
                'INDEXES'     => [
                    'post_id_idx' => ['post_id'],
                    'tag_id_idx'  => ['tag_id'],
                ],
            ];

            $dbLayer->createTable('s2_blog_post_tag', $schema);
        }

        // Add extension options to the config table
        $s2_blog_config = [
            'S2_BLOG_URL'   => '/blog',
            'S2_BLOG_TITLE' => 'My blog',
        ];

        foreach ($s2_blog_config as $conf_name => $conf_value) {
            if (\defined($conf_name)) {
                // TODO implement insert ignore
                continue;
            }

            $query = [
                'INSERT' => 'name, value',
                'INTO'   => 'config',
                'VALUES' => '\'' . $conf_name . '\', \'' . $conf_value . '\''
            ];

            $dbLayer->buildAndQuery($query);
        }

        // User permissions
        if ($dbLayer->fieldExists('users', 'edit_s2_blog')) {
            $dbLayer->dropField('users', 'edit_s2_blog');
        }

        // A field in tags table for important tags displaying
        $dbLayer->addField('tags', 's2_blog_important', 'INT(1)', false, '0');

        $dbLayer->addIndex('tags', 's2_blog_important_idx', array('s2_blog_important'));
    }

    public function uninstall(DbLayer $dbLayer, Container $container): void
    {
        $query = [
            'DELETE' => 'config',
            'WHERE'  => 'name in (\'S2_BLOG_URL\', \'S2_BLOG_TITLE\')',
        ];
        $dbLayer->buildAndQuery($query);

        $dbLayer->dropTable('s2_blog_posts');
        $dbLayer->dropTable('s2_blog_post_tag');
        $dbLayer->dropTable('s2_blog_comments');

        $dbLayer->dropField('tags', 's2_blog_important');
    }
}
