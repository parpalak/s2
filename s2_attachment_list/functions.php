<?php
/**
 * Functions for the Attachments list extension
 *
 * @copyright (C) 2011 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_attachment_list
 */


function s2_attachment_paging ($page, $total_pages, $current_path)
{
	$current_path .= '/';
	$str = '';
	for ($i = 1; $i <= $total_pages; $i++)
		$str .= ($i == $page ? ' <span class="current">'.$i.'</span>' : ' <a href="'.S2_PATH.S2_URL_PREFIX.$current_path.(S2_URL_PREFIX ? '?&amp;' : '?').'p='.$i.'">'.$i.'</a>');

	$str = ($page <= 1 || $page > $total_pages ? '<span class="nav">&larr;</span>' : '<a href="'.S2_PATH.S2_URL_PREFIX.$current_path.(S2_URL_PREFIX ? '?&amp;' : '?').'p='.($page - 1).'">&larr;</a>').$str.($page == $total_pages ? ' <span class="nav">&rarr;</span>' : ' <a href="'.S2_PATH.S2_URL_PREFIX.$current_path.(S2_URL_PREFIX ? '?&amp;' : '?').'p='.($page + 1).'">&rarr;</a>');
	return '<div class="paging">'.$str.'</div>';
}

function s2_attachment_list ($id, $current_path, $config, $page_limit)
{
	global $s2_db, $lang_s2_attachment;

	if (!$config)
	{
		$config = array(
			'title'		=> 'Page',
			'files'		=> 'Attached files',
		);
	}

	$subquery = array(
		'SELECT'	=> 'a1.id',
		'FROM'		=> 'articles AS a1',
		'WHERE'		=> 'a1.parent_id = a.id AND a1.published = 1',
		'LIMIT'		=> '1'
	);
	$raw_query1 = $s2_db->query_build($subquery, true) or error(__FILE__, __LINE__);

	$subquery = array(
		'SELECT'	=> 'a.id',
		'FROM'		=> 's2_attachment_files AS f',
		'WHERE'		=> 'f.article_id = a.id',
		'LIMIT'		=> '1'
	);
	$raw_query2 = $s2_db->query_build($subquery, true) or error(__FILE__, __LINE__);

	$query = array(
		'SELECT'	=> '*, ('.$raw_query1.') IS NOT NULL AS children_exist',
		'FROM'		=> 'articles AS a',
		'WHERE'		=> 'a.parent_id = '.$id.' AND ('.$raw_query2.') IS NOT NULL AND a.published = 1',
		'ORDER BY'	=> 'create_time DESC'
	);
	if ($page_limit)
	{
		$query2 = array(
			'SELECT'	=> 'count(id)',
			'FROM'		=> 'articles AS a',
			'WHERE'		=> 'a.parent_id = '.$id.' AND ('.$raw_query2.') IS NOT NULL AND a.published = 1',
		);
		$result = $s2_db->query_build($query2) or error(__FILE__, __LINE__);
		$item_num = $s2_db->result($result);

		$total_pages = ceil(1.0 * $item_num / $page_limit);

		$page = isset($_GET['p']) ? (int) $_GET['p'] : 1;
		if ($page < 1 || $page > $total_pages)
			$page = 1;

		$query['LIMIT'] = $page_limit.' OFFSET '.($page_limit*($page - 1));
	}
	($hook = s2_hook('fn_s2_attachment_list_pre_get_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

	$rows = $ids = array();
	while ($row = $s2_db->fetch_assoc($result))
	{
		$rows[] = $row;
		$ids[] = $row['id'];
	}

	// Fetching files
	$files = array();
	if (count($ids))
	{
		$query = array(
			'SELECT'	=> 'name, filename, time, size, article_id',
			'FROM'		=> 's2_attachment_files',
			'WHERE'		=> 'article_id IN ('.implode(', ', $ids).')',
			'ORDER BY'	=> 'time'
		);
		($hook = s2_hook('fn_s2_attachment_list_pre_get_files_qr')) ? eval($hook) : null;
		$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

		while ($row = $s2_db->fetch_assoc($result))
			$files[$row['article_id']][] = '<a href="'.S2_PATH.'/'.S2_IMG_DIR.'/'.date('Y', $row['time']).'/'.$row['article_id'].'/'.$row['filename'].'">'.s2_htmlencode($row['name'] ? $row['name'] : $row['filename']).'</a>';
	}

	$output = '';
	foreach ($rows as $row)
	{
		$tr = '';
		foreach ($config as $conf_name => $conf_value)
		{
			if ($conf_name == 'files')
				$item = isset($files[$row['id']]) ? implode('<br />', $files[$row['id']]) : '';
			elseif ($conf_name == 'title')
				$item = '<a href="'.S2_PATH.S2_URL_PREFIX.$current_path.'/'.urlencode($row['url']).($row['children_exist'] ? '/' : '').'">'.$row['title'].'</a>';
			elseif ($conf_name == 'create_time' || $conf_name == 'modify_time')
				$item = $row[$conf_name] ? '<nobr>'.s2_date($row[$conf_name]).'</nobr>' : '';
			else
				$item = $row[$conf_name];
			$tr .= '<td>'.$item.'</td>';
		}

		$output .= '<tr>'.$tr.'</tr>';
	}

	if ($output)
	{
		$head = '';
		foreach ($config as $conf_name => $conf_value)
			$head .= '<th>'.$conf_value.'</th>';
		$output = '<table class="s2_attachment_list"><tr>'.$head.'</tr>'.$output.'</table>';

		if ($page_limit)
			$output .= s2_attachment_paging ($page, $total_pages, $current_path);
	}

	return $output;
}

define('S2_ATTACHMENT_LIST_FUNCTIONS_LOADED', 1);