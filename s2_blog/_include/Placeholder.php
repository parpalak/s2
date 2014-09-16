<?php
/**
 * Content for blog placeholders.
 *
 * @copyright (C) 2007-2014 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_blog
 */

namespace s2_extensions\s2_blog;


class Placeholder
{
	function recent_comments ()
	{
		global $s2_db, $request_uri;

		if (!S2_SHOW_COMMENTS)
			return '';

		$subquery1 = array(
			'SELECT'	=> 'count(*) + 1',
			'FROM'		=> 's2_blog_comments AS c1',
			'WHERE'		=> 'shown = 1 AND c1.post_id = c.post_id AND c1.time < c.time'
		);
		$raw_query1 = $s2_db->query_build($subquery1, true) or error(__FILE__, __LINE__);

		$query = array(
			'SELECT'	=> 'time, url, title, nick, create_time, ('.$raw_query1.') AS count',
			'FROM'		=> 's2_blog_comments AS c',
			'JOINS'		=> array(
				array(
					'INNER JOIN'	=> 's2_blog_posts AS p',
					'ON'			=> 'c.post_id = p.id'
				)
			),
			'WHERE'		=> 'commented = 1 AND published = 1 AND shown = 1',
			'ORDER BY'	=> 'time DESC',
			'LIMIT'		=> '5'
		);
		($hook = s2_hook('fn_s2_blog_recent_comments_pre_qr')) ? eval($hook) : null;
		$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

		$output = '';
		while ($row = $s2_db->fetch_assoc($result))
			$output .= '<li><a href="'.S2_BLOG_PATH.date('Y/m/d/', $row['create_time']).urlencode($row['url']).'#'.$row['count'].'">'.s2_htmlencode($row['title']).'</a>, <em>'.s2_htmlencode($row['nick']).'</em></li>';
		$output = preg_replace('#<a href="'.preg_quote(s2_link($request_uri), '#').'(?:\\#[^"]*)?">(.*?)</a>#', '\\1', $output);
		return $output ? '<ul>'.$output.'</ul>' : '';
	}

	function recent_discussions ()
	{
		global $s2_db, $request_uri;

		if (!S2_SHOW_COMMENTS)
			return '';

		$subquery1 = array(
			'SELECT'	=> 'c.post_id AS post_id, count(c.post_id) AS comment_num,  max(c.id) AS max_id',
			'FROM'		=> 's2_blog_comments AS c',
			'WHERE'		=> 'c.shown = 1 AND c.time > '.strtotime('-1 month midnight'),
			'GROUP BY'	=> 'c.post_id',
			'ORDER BY'	=> 'comment_num DESC',
		);
		$raw_query1 = $s2_db->query_build($subquery1, true) or error(__FILE__, __LINE__);

		$query = array(
			'SELECT'	=> 'p.create_time, p.url, p.title, c1.comment_num AS comment_num, c2.nick, c2.time',
			'FROM'		=> 's2_blog_posts AS p, ('.$raw_query1.') AS c1',
			'JOINS'		=> array(
				array(
					'INNER JOIN'	=> 's2_blog_comments AS c2',
					'ON'			=> 'c2.id = c1.max_id'
				),
			),
			'WHERE'		=> 'c1.post_id = p.id AND p.commented = 1 AND p.published = 1',
			'LIMIT'		=> '10',
		);
		($hook = s2_hook('fn_s2_blog_recent_discussions_pre_qr')) ? eval($hook) : null;
		$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

		$output = '';
		while ($row = $s2_db->fetch_assoc($result))
			$output .= '<li><a href="'.S2_BLOG_PATH.date('Y/m/d/', $row['create_time']).urlencode($row['url']).'" title="'.s2_htmlencode($row['nick'].' ('.s2_date_time($row['time']).')').'">'.s2_htmlencode($row['title']).'</a></li>';
		$output = preg_replace('#<a href="'.preg_quote(s2_link($request_uri), '#').'"[^>]*>(.*?)</a>#', '\\1', $output);
		return $output ? '<ul>'.$output.'</ul>' : '';
	}

	public static function blog_navigation ()
	{
		global $s2_db, $lang_s2_blog;

		if (file_exists(S2_CACHE_DIR.'s2_blog_navigation.php'))
			include S2_CACHE_DIR.'s2_blog_navigation.php';

		$now = time();

		if (empty($s2_blog_navigation) || !isset($s2_blog_navigation_time) || $s2_blog_navigation_time < $now - 900)
		{
			$s2_blog_navigation = array('last' => '<a href="'.S2_BLOG_PATH.'">'.sprintf($lang_s2_blog['Nav last'], S2_MAX_ITEMS ? S2_MAX_ITEMS : 10).'</a>');

			$query = array(
				'SELECT'	=> '1',
				'FROM'		=> 's2_blog_posts',
				'WHERE'		=> 'published = 1 AND favorite = 1',
				'LIMIT'		=> '1'
			);
			($hook = s2_hook('fn_s2_blog_navigation_pre_is_favorite_qr')) ? eval($hook) : null;
			$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

			if ($s2_db->fetch_row($result))
				$s2_blog_navigation['favorite'] = '<a href="'.S2_BLOG_PATH.urlencode(S2_FAVORITE_URL).'/">'.$lang_s2_blog['Nav favorite'].'</a>';

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
			$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

			$tags = '';
			while ($tag = $s2_db->fetch_assoc($result))
				$tags .= '<li><a href="'.S2_BLOG_TAGS_PATH.urlencode($tag['url']).'/">'.$tag['name'].'</a></li>';

			if ($tags != '')
				$s2_blog_navigation['tags'] = sprintf($lang_s2_blog['Nav tags'], S2_BLOG_TAGS_PATH).'<ul>'.$tags.'</ul>';

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

		global $request_uri;
		$cur_url = str_replace('%2F', '/', urlencode($request_uri));
		$output = '<ul><li>'.implode('</li><li>', $s2_blog_navigation).'</li></ul>';
		$output = preg_replace('#<a href="'.preg_quote(s2_link($cur_url), '#').'">(.*?)</a>#', '\\1', $output);
		return $output;
	}

	function last_post ($num_post)
	{
		$posts = Lib::last_posts_array($num_post);
		if (!count($posts))
			return '';

		$html = '<h2 class="preview">%1$s<a href="%2$s">%3$s</a></h2>'."\n".
			'<div class="preview time">%5$s</div>'."\n".
			'<div class="post body">%6$s</div>'."\n";

		($hook = s2_hook('fn_s2_blog_last_post_start')) ? eval($hook) : null;

		$output = '';
		foreach ($posts as $post)
		{
			$link = S2_BLOG_PATH.date('Y/m/d/', $post['create_time']).urlencode($post['url']);
			$tag_prefix = $post['tags'] ? '<small>'.str_replace('<a ', '<a class="preview_section" ', $post['tags']).' &rarr;</small> ' : '';

			($hook = s2_hook('fn_s2_blog_last_post_pre_post_merge')) ? eval($hook) : null;

			$output .= sprintf($html,
				$tag_prefix,
				$link,
				s2_htmlencode($post['title']),
				s2_date($post['create_time']),
				s2_date_time($post['create_time']),
				$post['text']
			);
		}

		return $output;
	}
}
