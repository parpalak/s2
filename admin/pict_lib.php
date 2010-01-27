<?php
/**
 * Some functions maintaining picture displaying and management
 *
 * @copyright (C) 2007-2010 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */

$allowed_extensions = array ('gif', 'bmp', 'jpg', 'jpeg', 'png');

function s2_dirname ($dir)
{
	return preg_replace('#/[^/]*$#', '', $dir);
}

// Removes a folder with all subfolders and files
function s2_unlink_recursive($dir, $delete_root = true)
{
	if (!$dir_handle = @opendir($dir))
		return;

	while (false !== ($item = readdir($dir_handle)))
	{
		if ($item == '.' || $item == '..')
			continue;
		if (!@unlink($dir.'/'.$item))
			s2_unlink_recursive($dir.'/'.$item);
	}

	closedir($dir_handle);
	if ($delete_root)
		@rmdir($dir);

	return;
}

//
// Functions below outputs files tree to HTML
//

function s2_walk_dir ($dir, $created_name = false)
{
	global $lang_pictures;

	($hook = s2_hook('fn_walk_dir_start')) ? eval($hook) : null;

	if (!($dir_handle = opendir(S2_IMG_PATH.$dir)))
	{
		printf('<p>'.$lang_pictures['Directory not open'].'</p>', S2_IMG_PATH.$dir);
		return '';
	}

	$spans = $subdirs = array();
	$item_count = 0;

	while (($item = readdir($dir_handle)) !== false)
	{
		($hook = s2_hook('fn_walk_dir_loop_start')) ? eval($hook) : null;

		if ($item == '.' || $item == '..')
			continue;
		if (is_dir(S2_IMG_PATH.$dir.'/'.$item))
		{
			($hook = s2_hook('fn_walk_dir_format_item_start')) ? eval($hook) : null;
			$item_count++;
			$spans[] = '<span path="'.$dir.'/'.$item.'"'.($created_name !== false && $created_name == $item ? ' selected="selected"' : '').'>'.$item .'</span>';
			$subdirs[] = s2_walk_dir($dir.'/'.$item);
		}
	}

	$output = '';
	for ($i = 0; $i < $item_count; $i++)
	{
		($hook = s2_hook('fn_walk_dir_pre_item_merge')) ? eval($hook) : null;
		$subdir = $subdirs[$i] ? '<ul'.($i == $item_count - 1 ? ' class="l"' : '').'>'.$subdirs[$i].'</ul>' : '';
		$expand = $subdir ? '<a href="#" class="sc" onclick="return UnHide(this)"><img src="i/p.gif" alt="" /></a>' : '';
		$output .= '<li class="cl"><div><p>'.$expand.$spans[$i].'</p></div>'.$subdir.'</li>';
	}

	($hook = s2_hook('fn_walk_dir_end')) ? eval($hook) : null;
	return $output;
}

function s2_get_files ($dir)
{
	global $allowed_extensions, $lang_pictures;

	$max_size = 80;
	$display_preview = function_exists('imagetypes');

	($hook = s2_hook('fn_get_files_start')) ? eval($hook) : null;

	clearstatcache();

	if (!is_dir(S2_IMG_PATH.$dir))
		return;

	if (!($dir_handle = opendir(S2_IMG_PATH.$dir)))
	{
		printf('<p>'.$lang_pictures['Directory not open'].'</p>', S2_IMG_PATH.$dir);
		return;
	}

	$output = '';

	while (($item = readdir($dir_handle)) !== false)
	{
		($hook = s2_hook('fn_get_files_loop_start')) ? eval($hook) : null;

		if ($item == '.' || $item == '..' || is_dir(S2_IMG_PATH.$dir.'/'.$item))
			continue;

		$fsize = '';

		$preview = '<img src="i/file.png" vspace="16" align="center" alt="" />';
		if (strpos($item, '.') !== false && in_array(end(explode('.', $item)), $allowed_extensions))
		{
			($hook = s2_hook('fn_get_files_pre_get_file_info')) ? eval($hook) : null;

			$image_info = getImageSize(S2_IMG_PATH.$dir.'/'.$item);
			$sx = $image_info[0];
			$sy = $image_info[1];
			$fsize = 'fsize="'.$sx.'*'.$sy.'"';
			if ($sx < $sy)
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
			}

			$v = (int)(($max_size - $dy)/2);
			$v = $v > 0 ? ' vspace="'.$v.'"' : '';

			($hook = s2_hook('fn_get_files_pre_view_merge')) ? eval($hook) : null;
			if ($display_preview)
				$preview = '<img src="pict_ajax.php?action=preview&file='.$dir.'/'.$item.'&nocache='.filemtime(S2_IMG_PATH.$dir.'/'.$item).'" align="middle"'.$v.' alt="" />';
		}

		$delete_button = '<img class="del" src="i/delete.png" onclick="DeleteFile(\''.$dir.'/'.$item.'\');" alt="'.$lang_pictures['Delete'].'" />';

		($hook = s2_hook('fn_get_files_pre_output_merge')) ? eval($hook) : null;
		$output .= '<li><span fname="'.$dir.'/'.$item.'"'.$fsize.' fval="'.s2_frendly_filesize(filesize(S2_IMG_PATH.$dir.'/'.$item)).'">'.$delete_button.$preview.'</span>'.$item.'</li>';
	}

	($hook = s2_hook('fn_get_files_end')) ? eval($hook) : null;
	return $output ? '<ul>'.$output.'</ul><br clear="both" />' : '<p>'.$lang_pictures['Empty directory'].'</p>';
}

//
// Outputs thumbnails
//
function s2_make_thumbnail ($filename, $max_size = 100)
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
	if ($sx < $sy)
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
	}

	$thumbnail = imagecreatetruecolor($dx, $dy);

	imagealphablending($thumbnail, false);
	imagesavealpha($thumbnail, true);
	$white = imagecolorallocatealpha($thumbnail, 255, 255, 255, 127);
	imagefilledrectangle($thumbnail, 0, 0, $dx, $dy, $white);
	imagecolortransparent($thumbnail, $white);

	imagecopyresampled($thumbnail, $image, 0, 0, 0, 0, $dx, $dy, $sx, $sy);

	header('Content-Type: image/png');
	imagepng($thumbnail);

	imagedestroy($image);
	imagedestroy($thumbnail);
}

//
// Displaying HTML form for pictures uploading
//

function s2_return_bytes ($val)
{
	$val = trim($val);
	$last = strtolower($val[strlen($val) - 1]);
	switch($last)
	{
		case 'g':
			$val *= 1024;
		case 'm':
			$val *= 1024;
		case 'k':
			$val *= 1024;
	}

	return $val;
}

function s2_upload_form ()
{
	global $session_id, $lang_pictures;

	$return = ($hook = s2_hook('fn_upload_form_start')) ? eval($hook) : null;
	if ($return)
		return;

?>
				<form target="submit_result" enctype="multipart/form-data" action="<?php echo S2_PATH; ?>/admin/pict_ajax.php?action=upload" method="post" onsubmit="UploadSubmit(this);">
					<input name="pictures[]" multiple="true" size="25" type="file" /><br />
					<?php printf($lang_pictures['Upload limit'], s2_frendly_filesize(s2_return_bytes(ini_get('upload_max_filesize'))), s2_frendly_filesize(s2_return_bytes(ini_get('post_max_size'))))?><br />
					<input type="submit" value="<?php echo $lang_pictures['Upload']; ?>" /> <?php echo $lang_pictures['Upload to']; ?> <span id="fold_name"><strong><?php echo $lang_pictures['Pictures']; ?></strong></span>
					<input type="hidden" name="dir" value="" />
				</form>
				<iframe name="submit_result" src="" width="0" height="0" frameborder="0" align="left" ></iframe>
<?php

}