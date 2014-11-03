<?php
/**
 * General blog page.
 *
 * @copyright (C) 2007-2014 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_blog
 */

namespace s2_extensions\s2_blog;
use \Lang;


abstract class Page_HTML extends \Page_HTML
{
	public $template_id = 'blog.php';

	abstract public function body (array $params);

	public function __construct (array $params = array())
	{
		Lang::load('s2_blog', function ()
		{
			if (file_exists(__DIR__ . '/../../lang/' . S2_LANGUAGE . '.php'))
				return require __DIR__ . '/../../lang/' . S2_LANGUAGE . '.php';
			else
				return require __DIR__ . '/../../lang/English.php';
		});

		$this->viewer = new \Viewer($this);

		$this->page['commented'] = 0;
		$this->page['class'] = 's2_blog';
		$this->page['rss_link'][] = '<link rel="alternate" type="application/rss+xml" title="'.s2_htmlencode(Lang::get('RSS link title', 's2_blog')).'" href="'.s2_link(str_replace(urlencode('/'), '/', urlencode(S2_BLOG_URL)).'/rss.xml').'" />';

		$this->body($params);

		$this->page['meta_description'] = S2_BLOG_TITLE;
		$this->page['head_title'] = empty($this->page['head_title']) ? S2_BLOG_TITLE : $this->page['head_title'] . ' - ' . S2_BLOG_TITLE;

		if ($this->inTemplate('<!-- s2_menu -->'))
			$this->page['menu']['s2_blog_navigation'] = $this->blog_navigation();
	}

	public function get_posts ($query_add, $sort_asc = true, $sort_field = 'create_time')
	{
		global $s2_db;

		// Obtaining posts

		$sub_query = array(
			'SELECT' => 'count(*)',
			'FROM'   => 's2_blog_comments AS c',
			'WHERE'  => 'c.post_id = p.id AND shown = 1',
		);
		$raw_query_comment = $s2_db->query_build($sub_query, true);

		$sub_query = array(
			'SELECT' => 'u.name',
			'FROM'   => 'users AS u',
			'WHERE'  => 'u.id = p.user_id',
		);
		$raw_query_user = $s2_db->query_build($sub_query, true);

		$query = array(
			'SELECT' => 'p.create_time, p.title, p.text, p.url, p.id, p.commented, p.favorite, (' . $raw_query_comment . ') AS comment_num, (' . $raw_query_user . ') AS author, p.label',
			'FROM'   => 's2_blog_posts AS p',
			'WHERE'  => 'p.published = 1' . (!empty($query_add['WHERE']) ? ' AND ' . $query_add['WHERE'] : '')
		);
		if (!empty($query_add['JOINS']))
			$query['JOINS'] = $query_add['JOINS'];

		if (!empty($query_add['SELECT']))
			$query['SELECT'] .= ', '.$query_add['SELECT'];

		($hook = s2_hook('fn_s2_blog_get_posts_pre_get_posts_qr')) ? eval($hook) : null;
		$result = $s2_db->query_build($query);

		$posts = $merge_labels = $labels = $ids = $sort_array = array();
		while ($row = $s2_db->fetch_assoc($result))
		{
			$posts[$row['id']] = $row;
			$ids[] = $row['id'];
			$sort_array[] = $row[$sort_field];
			$labels[$row['id']] = $row['label'];
			if ($row['label'])
				$merge_labels[$row['label']] = 1;
		}
		if (empty($posts))
			return '';

		$see_also = $tags = array();
		Lib::posts_links($ids, $merge_labels, $see_also, $tags);

		array_multisort($sort_array, $sort_asc ? SORT_ASC : SORT_DESC, $ids);

		$output = '';
		foreach ($ids as $id)
		{
			$post = &$posts[$id];
			$link = S2_BLOG_PATH . date('Y/m/d/', $post['create_time']) . urlencode($post['url']);
			$post['link'] = $link;
			$post['title_link'] = $link;
			$post['time'] = s2_date_time($post['create_time']);
			$post['tags'] = isset($tags[$id]) ? $tags[$id] : array();

			$post['see_also'] = array();
			if (!empty($labels[$id]) && isset($see_also[$labels[$id]]))
			{
				$label_copy = $see_also[$labels[$id]];
				if (isset($label_copy[$id]))
					unset($label_copy[$id]);
				$post['see_also'] = $label_copy;
			}


			$output .= $this->renderPartial('post', $post);
		}

		return $output;
	}

	public function blog_navigation ()
	{
		global $s2_db, $request_uri;

		$cur_url = str_replace('%2F', '/', urlencode($request_uri));

		if (file_exists(S2_CACHE_DIR.'s2_blog_navigation.php'))
			include S2_CACHE_DIR.'s2_blog_navigation.php';

		$now = time();

		if (empty($s2_blog_navigation) || !isset($s2_blog_navigation_time) || $s2_blog_navigation_time < $now - 900)
		{
			$s2_blog_navigation = array('title' => Lang::get('Navigation', 's2_blog'));

			// Last posts on the blog main page
			$s2_blog_navigation['last'] = array(
				'title'      => sprintf(Lang::get('Nav last', 's2_blog'), S2_MAX_ITEMS ? S2_MAX_ITEMS : 10),
				'link'       => S2_BLOG_PATH,
			);

			// Check for favorite posts
			$query = array(
				'SELECT'	=> '1',
				'FROM'		=> 's2_blog_posts',
				'WHERE'		=> 'published = 1 AND favorite = 1',
				'LIMIT'		=> '1'
			);
			($hook = s2_hook('fn_s2_blog_navigation_pre_is_favorite_qr')) ? eval($hook) : null;
			$result = $s2_db->query_build($query);

			if ($s2_db->fetch_row($result))
				$s2_blog_navigation['favorite'] = array(
					'title'      => Lang::get('Nav favorite', 's2_blog'),
					'link'       => S2_BLOG_PATH . urlencode(S2_FAVORITE_URL) . '/',
				);

			// Fetch important tags
			$s2_blog_navigation['tags_header'] = array(
				'title' => Lang::get('Nav tags', 's2_blog'),
				'link'  => S2_BLOG_TAGS_PATH,
			);

			$query = array(
				'SELECT'	=> 't.name, t.url, count(t.tag_id)',
				'FROM'		=> 'tags AS t',
				'JOINS'		=> array(
					array(
						'INNER JOIN'	=> 's2_blog_post_tag AS pt',
						'ON'			=> 't.tag_id = pt.tag_id'
					),
					array(
						'INNER JOIN'	=> 's2_blog_posts AS p',
						'ON'			=> 'p.id = pt.post_id'
					)
				),
				'WHERE'		=> 't.s2_blog_important = 1 AND p.published = 1',
				'GROUP BY'	=> 't.tag_id',
				'ORDER BY'	=> '3 DESC',
			);
			($hook = s2_hook('fn_s2_blog_navigation_pre_get_tags_qr')) ? eval($hook) : null;
			$result = $s2_db->query_build($query);

			$tags = array();
			while ($tag = $s2_db->fetch_assoc($result))
				$tags[] = array(
					'title'      => $tag['name'],
					'link'       => S2_BLOG_TAGS_PATH . urlencode($tag['url']) . '/',
				);

			$s2_blog_navigation['tags'] = $tags;

			// Try to remove very old cache (maybe the file is not writable but removable)
			if (isset($s2_blog_navigation_time) && $s2_blog_navigation_time < $now - 86400)
				@unlink(S2_CACHE_DIR.'s2_blog_navigation.php');

			// Output navigation array as PHP code
			$fh = @fopen(S2_CACHE_DIR.'s2_blog_navigation.php', 'ab+');
			if ($fh)
			{
				if (flock($fh, LOCK_EX | LOCK_NB))
				{
					ftruncate($fh, 0);
					fwrite($fh, '<?php'."\n\n".'$s2_blog_navigation_time = '.$now.';'."\n\n".'$s2_blog_navigation = '.var_export($s2_blog_navigation, true).';');
					fflush($fh);
					fflush($fh);
					flock($fh, LOCK_UN);
				}
				fclose($fh);
			}
		}

		foreach ($s2_blog_navigation as &$item)
			if (is_array($item))
			{
				if (isset($item['link']))
					$item['is_current'] = $item['link'] == $cur_url;
				else
					foreach ($item as &$sub_item)
						if (is_array($sub_item) && isset($sub_item['link']))
							$sub_item['is_current'] = $sub_item['link'] == $cur_url;
			}

		return $this->renderPartial('navigation', $s2_blog_navigation);
	}
}
