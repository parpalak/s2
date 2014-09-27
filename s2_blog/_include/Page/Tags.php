<?php
/**
 * List of blog tags.
 *
 * @copyright (C) 2007-2014 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_blog
 */

namespace s2_extensions\s2_blog;


class Page_Tags extends Page_Abstract
{
	public function body (array $params = array())
	{
		global $lang_s2_blog;

		$this->obtainTemplate(__DIR__.'/../../templates/');

		if (strpos($this->template, '<!-- s2_blog_calendar -->') !== false)
			$this->page['s2_blog_calendar'] = Lib::calendar(date('Y'), date('m'), '0');

		// The list of tags
		$this->page['text'] = self::all_tags();

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
			'title' => $lang_s2_blog['Tags'],
		);

		$this->page['head_title'] = $this->page['title'] = $lang_s2_blog['Tags'];
		$this->page['link_navigation']['up'] = S2_BLOG_PATH;
	}

	private static function all_tags ()
	{
		global $s2_db;

		$query = array(
			'SELECT'	=> 'tag_id, name, url',
			'FROM'		=> 'tags'
		);
		($hook = s2_hook('fn_s2_blog_all_tags_pre_get_tags_qr')) ? eval($hook) : null;
		$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

		while ($row = $s2_db->fetch_assoc($result))
		{
			$tag_name[$row['tag_id']] = $row['name'];
			$tag_url[$row['tag_id']] = $row['url'];
			$tag_count[$row['tag_id']] = 0;
		}

		$query = array(
			'SELECT'	=> 'pt.tag_id',
			'FROM'		=> 's2_blog_post_tag AS pt',
			'JOINS'		=> array(
				array(
					'INNER JOIN'	=> 's2_blog_posts AS p',
					'ON'			=> 'p.id = pt.post_id'
				)
			),
			'WHERE'		=> 'p.published = 1'
		);
		($hook = s2_hook('fn_s2_blog_all_tags_pre_get_posts_qr')) ? eval($hook) : null;
		$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

		while ($row = $s2_db->fetch_row($result))
			$tag_count[$row[0]]++;

		arsort($tag_count);

		$tags = array();
		foreach ($tag_count as $id => $num)
			if ($num)
				$tags[] = '<a href="'.S2_BLOG_TAGS_PATH.urlencode($tag_url[$id]).'/">'.$tag_name[$id].'</a> ('.$num.')';

		($hook = s2_hook('fn_s2_blog_all_tags_end')) ? eval($hook) : null;
		return '<div class="tags_list">'.implode('<br />', $tags).'</div>';
	}

}
