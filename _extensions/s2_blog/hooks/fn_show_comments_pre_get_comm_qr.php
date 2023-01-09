<?php
/**
 * Hook fn_show_comments_pre_get_comm_qr
 *
 * @copyright (C) 2023 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_blog
 */

 if (!defined('S2_ROOT')) {
     die;
}

if (strpos($mode, 's2_blog') === 0)
{
	$query = array(
		'SELECT'	=> 'p.title, c.post_id AS article_id, c.id, c.time, c.nick, c.email, c.show_email, c.subscribed, c.text, c.shown, c.sent, c.good, c.ip',
		'FROM'		=> 's2_blog_comments AS c',
		'JOINS'		=> array(
			array(
				'INNER JOIN'	=> 's2_blog_posts AS p',
				'ON'			=> 'p.id = c.post_id'
			)
		),
		'WHERE'		=> 'c.post_id = '.$id,
		'ORDER BY'	=> 'time'
	);

	$output = '';
	if ($mode == 's2_blog_hidden')
	{
		// Show all hidden commetns
		$query['WHERE'] = 'shown = 0';
		$output = '<h2>'.Lang::get('Blog hidden comments', 's2_blog').'</h2>';
	}
	elseif ($mode == 's2_blog_new')
	{
		// Show unverified commetns
		$query['WHERE'] = 'shown = 0 AND sent = 0';
		$output = '<h2>'.Lang::get('Blog new comments', 's2_blog').'</h2>';
	}
	elseif ($mode == 's2_blog_last')
	{
		// Show last 20 commetns
		unset($query['WHERE']);
		$query['ORDER BY'] = 'time DESC';
		$query['LIMIT'] = '20';
		$output = '<h2>'.Lang::get('Blog last comments', 's2_blog').'</h2>';
	}
}
