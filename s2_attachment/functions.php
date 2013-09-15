<?php
/**
 * Functions for the attachment extension
 *
 * @copyright (C) 2010-2013 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_attachment
 */

//
// Interface functions
//
function s2_attachment_items ($id)
{
	global $s2_db, $lang_s2_attachment;

	$query = array(
		'SELECT'	=> 'id, name, filename, size, time, article_id, is_picture',
		'FROM'		=> 's2_attachment_files',
		'WHERE'		=> 'article_id = '.$id,
		'ORDER BY'	=> 'priority',
	);
	($hook = s2_hook('fn_s2_attachment_items_pre_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

	$list_files = $list_pictures = '';
	while ($row = $s2_db->fetch_assoc($result))
	{
		$buttons = array(
			'edit'		=> '<img onclick="return s2_attachment_rename_file('.$row['id'].', \''.$lang_s2_attachment['New filename'].'\', \''.str_replace('\'', '\\\'', rawurlencode($row['name'])).'\');" class="rename" src="i/1.gif" alt="'.$lang_s2_attachment['Rename'].'">', 
			'delete'	=> '<img onclick="return s2_attachment_delete_file('.$row['id'].', \''.str_replace('\'', '\\\'', rawurlencode(sprintf($row['name'] ? $lang_s2_attachment['Confirm delete'] : $lang_s2_attachment['Confirm delete 2'], $row['name'], $row['filename'], s2_frendly_filesize($row['size'])))).'\');" class="delete" src="i/1.gif" alt="'.$lang_s2_attachment['Delete'].'">', 
		);

		$icon = $row['is_picture'] ? '<img class="attach-preview" src="'.S2_PATH.'/'.S2_IMG_DIR.'/'.date('Y', $row['time']).'/'.$row['article_id'].'/micro/'.$row['filename'].'.png" alt="" />' : '';

		$item = '<li data-s2_attachment-id="'.$row['id'].'"><div class="buttons">'.implode('', $buttons).'</div><a href="'.S2_PATH.'/'.S2_IMG_DIR.'/'.date('Y', $row['time']).'/'.$row['article_id'].'/'.$row['filename'].'" target="_blank" title="'.$row['filename'].', '.s2_frendly_filesize($row['size']).'">'.$icon.s2_htmlencode($row['name'] ? $row['name'] : '<'.$row['filename'].'>').'</a></li>';

		if ($row['is_picture'])
			$list_pictures .= $item;
		else
			$list_files .= $item;
	}

	$lists = array();
	if ($list_pictures)
		$lists[] = '<ul class="s2_attachment_listing">'.$list_pictures.'</ul>';
	if ($list_files)
		$lists[] = '<ul class="s2_attachment_listing">'.$list_files.'</ul>';

	return implode('<hr />', $lists);
}

function s2_attachment_add_col ($id)
{
	global $lang_pictures;

?>
	<div class="r-float" id="s2_attachment_col">
		<form target="s2_attachment_result" enctype="multipart/form-data" action="<?php echo S2_PATH; ?>/_admin/pict_ajax.php?action=s2_attachment_upload" method="post" onsubmit="s2_attachment_upload_submit(this);">
			<div id="s2_attachment_file_upload_input">
				<input name="pictures[]" multiple="true" min="1" max="999" size="9" type="file" onchange="s2_attachment_upload_change(this);" />
			</div>
			<?php printf($lang_pictures['Upload limit'], s2_frendly_filesize(s2_return_bytes(ini_get('upload_max_filesize'))), s2_frendly_filesize(s2_return_bytes(ini_get('post_max_size')))); ?>
			<input type="hidden" name="id" value="<?php echo $id; ?>" />
		</form>
		<hr />
		<div class="height_wrap" style="padding-bottom: 6.0em;">
			<div class="tags_list" id="s2_attachment_items"><?php echo s2_attachment_items($id); ?></div>
		</div>
	</div>
<?php

}

//
// Sorting files
//
function s2_attachment_sort_files ($ids)
{
	global $s2_db;

	$ids = explode(',', $ids);

	foreach ($ids as $priority => $id)
	{
		$id = (int) $id;
		if (!$id)
			continue;

		$query = array(
			'UPDATE'	=> 's2_attachment_files',
			'SET'		=> 'priority = '.$priority,
			'WHERE'		=> 'id = '.$id
		);
		($hook = s2_hook('fn_s2_attachment_sort_files_pre_update')) ? eval($hook) : null;
		$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);
	}
}

//
// Saving thumbnails
//
function s2_attachment_save_thumbnail ($filename, $save_to, $max_size = 100)
{
	$image_info = getimagesize($filename);

	switch ($image_info['mime'])
	{
		case 'image/gif':
			if (imagetypes() & IMG_GIF)
				$image = imagecreatefromgif($filename);
			else
				$error = 'GIF images are not supported';
			break;
		case 'image/jpeg':
			if (imagetypes() & IMG_JPG)
				$image = imagecreatefromjpeg($filename);
			else
				$error = 'JPEG images are not supported';
			break;
		case 'image/png':
			if (imagetypes() & IMG_PNG)
				$image = imagecreatefrompng($filename);
			else
				$error = 'PNG images are not supported';
			break;
		case 'image/wbmp':
			if (imagetypes() & IMG_WBMP)
				$image = imagecreatefromwbmp($filename);
			else
				$error = 'WBMP images are not supported';
			break;
		default:
			$error = $image_info['mime'].' images are not supported';
			break;
	}

	if (isset($error))
		return $error;

	$sx = imagesx($image);
	$sy = imagesy($image);
	$dx = $max_size;
	$dy = round($sy * $max_size / $sx);
/* 	if ($sx < $sy)
	{
		if ($sy > $max_size)
		{
			$dy = $max_size;
			$dx = round($dy * $sx / $sy); 
		}
		else
		{
			$dx = $sx;
			$dy = $sy;
		}
	}
	else
	{
		if ($sx > $max_size)
		{
			$dx = $max_size;
			$dy = round($dx * $sy / $sx); 
		}
		else
		{
			$dx = $sx;
			$dy = $sy;
		}
	} */

	$thumbnail = imagecreatetruecolor($dx, $dy);

	imagealphablending($thumbnail, false);
	imagesavealpha($thumbnail, true);
	$white = imagecolorallocatealpha($thumbnail, 255, 255, 255, 127);
	imagefilledrectangle($thumbnail, 0, 0, $dx, $dy, $white);
	imagecolortransparent($thumbnail, $white);

	imagecopyresampled($thumbnail, $image, 0, 0, 0, 0, $dx, $dy, $sx, $sy);

	imagepng($thumbnail, $save_to);

	imagedestroy($image);
	imagedestroy($thumbnail);

	return false;
}

//
// Processes the placeholder
//
function s2_attachment_placeholder_content ($id, $placeholder_limit)
{
	global $s2_db, $lang_s2_attachment;

	$query = array(
		'SELECT'	=> 'id, name, filename, size, time, is_picture',
		'FROM'		=> 's2_attachment_files',
		'WHERE'		=> 'article_id = '.$id,
		'ORDER BY'	=> 'priority'
	);
	($hook = s2_hook('fn_s2_attachment_items_pre_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

	$list_files = '';
	$list_pictures = array();
	foreach ($placeholder_limit as $placeholder => $limit)
		$list_pictures[$placeholder] = '';

	$picture_num = 0;
	while ($row = $s2_db->fetch_assoc($result))
	{
		if ($row['is_picture'])
		{
			$picture_num++;
			foreach ($placeholder_limit as $placeholder => $limit)
			{
				$hidden_style = $limit && $limit < $picture_num ? ' style="display: none;"' : '';
				$list_pictures[$placeholder] .= '<a'.$hidden_style.' href="'.S2_PATH.'/'.S2_IMG_DIR.'/'.date('Y', $row['time']).'/'.$id.'/'.$row['filename'].'" class="highslide" onclick="return hs.expand(this, '.(strpos($placeholder, 'gallery') !== false ? 's2_attachment_gallery' : 's2_attachment_pictures').')"><img src="'.S2_PATH.'/'.S2_IMG_DIR.'/'.date('Y', $row['time']).'/'.$id.'/micro/'.$row['filename'].'.png" alt="" /></a>';
				if ($row['name'])
					$list_pictures[$placeholder] .= '<div'.$hidden_style.' class="highslide-caption">'.s2_htmlencode($row['name']).'</div>';
			}
		}
		else
		{
			$list_files .= '<li><a href="'.S2_PATH.'/'.S2_IMG_DIR.'/'.date('Y', $row['time']).'/'.$id.'/'.$row['filename'].'">'.s2_htmlencode($row['name'] ? $row['name'] : $row['filename']).' ('.s2_frendly_filesize($row['size']).')</a></li>';
		}
	}

	foreach ($list_pictures as $placeholder => $replace)
		if ($replace)
			$list_pictures[$placeholder] = '<div class="highslide-gallery">'.$replace.'</div>';

	if ($list_files)
		$list_files = '<div class="s2_attachment_files"><h2>'.$lang_s2_attachment['Attached files'].'</h2><ul>'.$list_files.'</ul></div>';

	return array($list_files, $list_pictures);
}

define('S2_ATTACHMENT_FUNCTIONS_LOADED', 1);