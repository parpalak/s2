<?php
/**
 * Single blog post.
 *
 * @copyright (C) 2007-2014 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_blog
 */

namespace s2_extensions\s2_blog;


class Page_Post extends Page_Abstract
{
	public function body (array $params = array())
	{
		global $lang_s2_blog;

		$this->obtainTemplate(__DIR__.'/../../templates/');

		if (strpos($this->template, '<!-- s2_blog_calendar -->') !== false)
			$this->page['s2_blog_calendar'] = Lib::calendar($params['year'], $params['month'], $params['day'], $params['url']);

		$this->page['title'] = '';

		$this->page = self::get_post($params['year'], $params['month'], $params['day'], $params['url']) + $this->page;

		// Bread crumbs
		$this->page['path'][] = array(
			'title' => \Model::main_page_title(),
			'link'  => s2_link('/'),
		);
		if (S2_BLOG_URL)
		{
			$this->page['path'][] = array(
				'title' => $lang_s2_blog['Blog'],
				'link' => S2_BLOG_PATH,
			);
		}

		$this->page['path'][] = array(
			'title' => $params['year'],
			'link'  => S2_BLOG_PATH.$params['year'].'/',
		);
		$this->page['path'][] = array(
			'title' => $params['month'],
			'link'  => S2_BLOG_PATH.$params['year'].'/'.$params['month'].'/',
		);
		$this->page['path'][] = array(
			'title' => $params['day'],
			'link'  => S2_BLOG_PATH.$params['year'].'/'.$params['month'].'/'.$params['day'],
		);
	}

	private static function get_post ($year, $month, $day, $url)
	{
		global $s2_db, $lang_s2_blog;

		$start_time = mktime(0, 0, 0, $month, $day, $year);
		$end_time = mktime(0, 0, 0, $month, $day + 1, $year);

		$sub_query = array(
			'SELECT'	=> 'u.name',
			'FROM'		=> 'users AS u',
			'WHERE'		=> 'u.id = p.user_id',
		);
		$raw_query_user = $s2_db->query_build($sub_query, true) or error(__FILE__, __LINE__);

		$query = array(
			'SELECT'	=> 'create_time, title, text, id, commented, label, favorite, ('.$raw_query_user.') AS author',
			'FROM'		=> 's2_blog_posts AS p',
			'WHERE'		=> 'create_time < '.$end_time.' AND create_time >= '.$start_time.' AND url = \''.$s2_db->escape($url).'\' AND published = 1'
		);
		($hook = s2_hook('fn_s2_blog_get_post_pre_get_post_qr')) ? eval($hook) : null;
		$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

		if (!$row = $s2_db->fetch_assoc($result))
		{
			s2_404_header();
			return array(
				'text'				=> '<p>'.$lang_s2_blog['Not found'].'</p>',
				'head_title'		=> $lang_s2_blog['Not found'],
				'link_navigation'	=> array('up' => S2_BLOG_PATH.date('Y/m/d/', $start_time))
			);
		}

		$post_id = $row['id'];
		$label = $row['label'];

		if ($label)
		{
			// Getting posts that have the same label
			$query = array(
				'SELECT'	=> 'title, create_time, url',
				'FROM'		=> 's2_blog_posts',
				'WHERE'		=> 'label = \''.$s2_db->escape($label).'\' AND id <> '.$post_id.' AND published = 1',
				'ORDER BY'	=> 'create_time DESC'
			);
			($hook = s2_hook('fn_s2_blog_get_post_pre_get_labelled_posts_qr')) ? eval($hook) : null;
			$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

			$links = array();
			while ($row1 = $s2_db->fetch_assoc($result))
				$links[] = '<a href="'.S2_BLOG_PATH.date('Y/m/d/', $row1['create_time']).urlencode($row1['url']).'">'.s2_htmlencode($row1['title']).'</a>';

			if (!empty($links))
				$row['text'] .= Lib::format_see_also($links);
		}

		// Getting tags
		$query = array(
			'SELECT'	=> 'name, url',
			'FROM'		=> 'tags AS t',
			'JOINS'		=> array(
				array(
					'INNER JOIN'	=> 's2_blog_post_tag AS pt',
					'ON'			=> 'pt.tag_id = t.tag_id'
				)
			),
			'WHERE'		=> 'post_id = '.$post_id,
			'ORDER BY'	=> 'pt.id'
		);
		($hook = s2_hook('fn_s2_blog_get_post_pre_get_labelled_posts_qr')) ? eval($hook) : null;
		$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

		$tags = array();
		while ($row1 = $s2_db->fetch_assoc($result))
			$tags[] = '<a href="'.S2_BLOG_TAGS_PATH.urlencode($row1['url']).'/">'.$row1['name'].'</a>';

		$output = Lib::format_post(
			isset($row['author']) ? s2_htmlencode($row['author']) : '',
			s2_htmlencode($row['title']),
			s2_date($row['create_time']),
			s2_date_time($row['create_time']),
			$row['text'],
			implode(', ', $tags),
			'',
			$row['favorite']
		);
		$output .= '<a name="comment"></a>';
		if ($row['commented'] && S2_SHOW_COMMENTS)
			$output .= self::get_comments($post_id);

		return array(
			'text'				=> $output,
			'head_title'		=> s2_htmlencode($row['title']),
			'commented'			=> $row['commented'],
			'id'				=> $post_id,
			'link_navigation'	=> array('up' => S2_BLOG_PATH.date('Y/m/d/', $start_time))
		);
	}

	public static function get_comments ($id)
	{
		global $s2_db, $lang_common;

		$comments = '';
		$html_comment = '<div class="reply_info"><a name="%1$s" href="#%1$s">#%1$s</a>. %2$s</div>'."\n".
			'<div class="reply%3$s">%4$s</div>'."\n";

		$query = array(
			'SELECT'	=> 'nick, time, email, show_email, good, text',
			'FROM'		=> 's2_blog_comments',
			'WHERE'		=> 'post_id = '.$id.' AND shown = 1',
			'ORDER BY'	=> 'time'
		);
		($hook = s2_hook('fn_s2_blog_get_comments_pre_qr')) ? eval($hook) : null;
		$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

		for ($i = 1; $row = $s2_db->fetch_assoc($result); $i++)
		{
			$nick = s2_htmlencode($row['nick']);
			$name = '<strong>'.($row['show_email'] ? s2_js_mailto($nick, $row['email']) : $nick).'</strong>';

			($hook = s2_hook('fn_s2_blog_get_comments_pre_comment_merge')) ? eval($hook) : null;

			$comments .= sprintf($html_comment,
				$i,
				sprintf($lang_common['Comment info format'], s2_date_time($row['time']), $name),
				($row['good'] ? ' good' : ''),
				s2_bbcode_to_html(s2_htmlencode($row['text']))
			);
		}

		return $comments ? '<h2 class="comment">'.$lang_common['Comments'].'</h2>'."\n".$comments : '';
	}

}
