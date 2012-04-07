<?php
/**
 * Some functions maintaining picture displaying and management
 *
 * @copyright (C) 2007-2012 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */

$allowed_extensions = array ('gif', 'bmp', 'jpg', 'jpeg', 'png');

function s2_dirname ($dir)
{
	return preg_replace('#/[^/]*$#', '', $dir);
}

function s2_basename ($dir)
{
	return false !== ($pos = strrpos($dir, '/')) ? substr($dir, $pos + 1) : $dir;
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

	$output = array();

	while (($item = readdir($dir_handle)) !== false)
	{
		($hook = s2_hook('fn_walk_dir_loop_start')) ? eval($hook) : null;

		if ($item == '.' || $item == '..')
			continue;
		if (is_dir(S2_IMG_PATH.$dir.'/'.$item))
		{
			($hook = s2_hook('fn_walk_dir_format_item_start')) ? eval($hook) : null;

			$output[] = array(
				'data'		=> $item,
				'attr'		=> array('data-path' => $dir.'/'.$item),
				'children'	=> s2_walk_dir($dir.'/'.$item)
			);
		}
	}

	closedir($dir_handle);

	($hook = s2_hook('fn_walk_dir_end')) ? eval($hook) : null;

	return $output;
}

function s2_get_files ($dir)
{
	global $allowed_extensions, $lang_pictures;

	$display_preview = function_exists('imagetypes');

	($hook = s2_hook('fn_get_files_start')) ? eval($hook) : null;

	clearstatcache();

	if (!is_dir(S2_IMG_PATH.$dir))
		return array('message' => 'Invalid directory');

	if (!($dir_handle = opendir(S2_IMG_PATH.$dir)))
		return array('message' => sprintf('<p>'.$lang_pictures['Directory not open'].'</p>', S2_IMG_PATH.$dir));

	$output = array();
	while (($item = readdir($dir_handle)) !== false)
	{
		($hook = s2_hook('fn_get_files_loop_start')) ? eval($hook) : null;

		if ($item == '.' || $item == '..' || is_dir(S2_IMG_PATH.$dir.'/'.$item))
			continue;

		$dim = '';

		if (strpos($item, '.') !== false && in_array(end(explode('.', $item)), $allowed_extensions))
		{
			$image_info = getImageSize(S2_IMG_PATH.$dir.'/'.$item);
			$dim = $image_info[0].'*'.$image_info[1];
		}

		($hook = s2_hook('fn_get_files_pre_output_item_merge')) ? eval($hook) : null;

		$output[] = array(
			'data'		=> array(
				'title'		=> $item,
				'icon'		=> $display_preview && $dim ? S2_PATH.'/_admin/pict_ajax.php?action=preview&file='.rawurlencode($dir.'/'.$item).'&nocache='.filemtime(S2_IMG_PATH.$dir.'/'.$item) : 'no-preview'
			),
			'attr'		=> array(
				'data-fname'	=> $item,
				'data-dim'		=> $dim,
				'data-fsize'	=> s2_frendly_filesize(filesize(S2_IMG_PATH.$dir.'/'.$item))
			)
		);
	}

	closedir($dir_handle);

	($hook = s2_hook('fn_get_files_end')) ? eval($hook) : null;

	return count($output) ? $output : array('message' => $lang_pictures['Empty directory']);
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

function s2_upload_form ()
{
	global $session_id, $lang_pictures;

	$return = ($hook = s2_hook('fn_upload_form_start')) ? eval($hook) : null;
	if ($return)
		return;

?>
				<form target="submit_result" enctype="multipart/form-data" action="<?php echo S2_PATH; ?>/_admin/pict_ajax.php?action=upload" method="post" onsubmit="UploadSubmit(this);">
					<?php echo $lang_pictures['Upload']; ?> <?php echo $lang_pictures['Upload to']; ?> <span id="fold_name"><strong><?php echo $lang_pictures['Pictures']; ?></strong></span>
					<input name="pictures[]" multiple="true" min="1" max="999" size="20" type="file" onchange="UploadChange(this);" /><br />
					<?php printf($lang_pictures['Upload limit'], s2_frendly_filesize(s2_return_bytes(ini_get('upload_max_filesize'))), s2_frendly_filesize(s2_return_bytes(ini_get('post_max_size'))))?><br />
					<input type="hidden" name="dir" value="" />
				</form>
				<iframe name="submit_result" id="submit_result" src="" width="0" height="0" frameborder="0" align="left" onload="FileUploaded();"></iframe>
<?php

}