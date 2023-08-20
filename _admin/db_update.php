<?php
/**
 * Database update script.
 *
 * @copyright (C) 2011-2013 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */


use S2\Cms\Pdo\DbLayer;

if (!defined('S2_DB_LAST_REVISION'))
	die;

/** @var DbLayer $s2_db */
$s2_db = \Container::get(DbLayer::class);

if (S2_DB_REVISION < 2)
{
	$query = array(
		'INSERT'	=> 'name, value',
		'INTO'		=> 'config',
		'VALUES'	=> '\'S2_MAX_ITEMS\', \'0\''
	);

	$s2_db->buildAndQuery($query);

	define('S2_MAX_ITEMS', 0);
}

if (S2_DB_REVISION < 3)
{
	$query = array(
		'INSERT'	=> 'name, value',
		'INTO'		=> 'config',
		'VALUES'	=> '\'S2_ADMIN_COLOR\', \'#eeeeee\''
	);

	$s2_db->buildAndQuery($query);

	define('S2_ADMIN_COLOR', '#eeeeee');
}

if (S2_DB_REVISION < 4)
{
	$s2_db->addIndex('articles', 'children_idx', array('parent_id', 'published'));
	$s2_db->addIndex('art_comments', 'sort_idx', array('article_id', 'time', 'shown'));
	$s2_db->addIndex('tags', 'url_idx', array('url'));
}

if (S2_DB_REVISION < 5)
{
	$s2_db->addField('articles', 'user_id', 'INT(10) UNSIGNED', false, '0', 'template');
	$s2_db->addField('articles', 'revision', 'INT(10) UNSIGNED', false, '1', 'modify_time');
	$s2_db->addField('users', 'create_articles', 'INT(10) UNSIGNED', false, '0', 'edit_comments');

	$query = array(
		'UPDATE'	=> 'users',
		'SET'		=> 'create_articles = edit_site'
	);

	$s2_db->buildAndQuery($query);
}

if (S2_DB_REVISION < 6)
{
	$s2_db->addField('users', 'name', 'VARCHAR(80)', false, '', 'password');
}

if (S2_DB_REVISION < 7)
{
	$s2_db->dropField('articles', 'children_preview');
}

if (S2_DB_REVISION < 8)
{
	$s2_db->addField('users_online', 'ua', 'VARCHAR(200)', false, '', 'login');
	$s2_db->addField('users_online', 'ip', 'VARCHAR(39)', false, '', 'login');
	$s2_db->addIndex('users_online', 'login_idx', array('login'));
}

if (S2_DB_REVISION < 9)
{
	$query = array(
		'INSERT'	=> 'name, value',
		'INTO'		=> 'config',
		'VALUES'	=> '\'S2_ADMIN_NEW_POS\', \'0\''
	);

	$s2_db->buildAndQuery($query);

	define('S2_ADMIN_NEW_POS', '0');
}

if (S2_DB_REVISION < 10)
{
	$check_for_updates = (function_exists('curl_init') || function_exists('fsockopen') || in_array(strtolower(@ini_get('allow_url_fopen')), array('on', 'true', '1'))) ? '1' : '0';

	$query = array(
		'INSERT'	=> 'name, value',
		'INTO'		=> 'config',
		'VALUES'	=> '\'S2_ADMIN_UPDATES\', \''.$check_for_updates.'\''
	);

	$s2_db->buildAndQuery($query);

	define('S2_ADMIN_UPDATES', $check_for_updates);
}

if (S2_DB_REVISION < 11)
{
	$s2_db->addField('extensions', 'admin_affected', 'TINYINT(1) UNSIGNED', false, '0', 'author');
}

if (S2_DB_REVISION < 12)
{
	$query = array(
		'INSERT'	=> 'name, value',
		'INTO'		=> 'config',
		'VALUES'	=> '\'S2_ADMIN_CUT\', \'0\''
	);

	$s2_db->buildAndQuery($query);

	define('S2_ADMIN_CUT', '0');
}

if (S2_DB_REVISION < 13)
{
	$s2_db->addField('users_online', 'comment_cookie', 'VARCHAR(32)', false, '', 'ua');
}

if (S2_DB_REVISION < 14)
{
	$query = array(
		'INSERT'	=> 'name, value',
		'INTO'		=> 'config',
		'VALUES'	=> '\'S2_USE_HIERARCHY\', \'1\''
	);

	$s2_db->buildAndQuery($query);

	define('S2_USE_HIERARCHY', '1');
}

if (S2_DB_REVISION < 15) {
    $s2_db->createTable('queue', array(
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

if (S2_DB_REVISION < 16 && $db_type === 'mysql') {
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
        $s2_db->query(sprintf('ALTER TABLE `%s` ENGINE=InnoDB;', $s2_db->getPrefix() . $table));
        $s2_db->query(sprintf('ALTER TABLE `%s` CONVERT TO CHARACTER SET utf8mb4;', $s2_db->getPrefix() . $table));
    }
    foreach ([
                 's2_blog_comments',
                 's2_blog_post_tag',
                 's2_blog_posts',
             ] as $table) {
        if ($s2_db->tableExists($table)) {
            $s2_db->query(sprintf('ALTER TABLE `%s` ENGINE=InnoDB;', $s2_db->getPrefix() . $table));
            $s2_db->query(sprintf('ALTER TABLE `%s` CONVERT TO CHARACTER SET utf8mb4;', $s2_db->getPrefix() . $table));
        }
    }
}

$query = array(
	'UPDATE'	=> 'config',
	'SET'		=> 'value = \''.S2_DB_LAST_REVISION.'\'',
	'WHERE'		=> 'name = \'S2_DB_REVISION\''
);

$s2_db->buildAndQuery($query);

S2Cache::generate_config();
