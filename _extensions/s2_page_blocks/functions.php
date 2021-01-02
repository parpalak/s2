<?php
/**
 * Helper functions for page blocks
 *
 * @copyright (C) 2013 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_page_blocks
 */

function s2_page_blocks_admin_content ($config, $content, $id)
{
	$height_sum = 0.25;
?>
		<table class="fields">
<?php

	foreach ($config as $field)
	{
		if ($field['type'] === 'img')
		{
			$height_sum += 1.916667;
			$s2_page_blocks_text = isset($content[$field['name']]) ? $content[$field['name']] : '';
?>
			<tr>
				<td class="label"><?php echo $field['info']; ?></td>
				<td>
					<input class="s2_page_blocks_img_button" type="image" src="../../_admin/i/1.gif" onclick="s2_page_blocks_choose_file('page[s2_page_blocks_<?php echo s2_htmlencode($field['name']); ?>]'); return false;" />
					<input type="text" name="page[s2_page_blocks_<?php echo s2_htmlencode($field['name']); ?>]" value="<?php echo s2_htmlencode($s2_page_blocks_text); ?>" />
				</td>
			</tr>
<?php
		}
		else
		{
			$height_sum += 0.166666 + $field['type'];
			$s2_page_blocks_text = isset($content[$field['name']]) ? $content[$field['name']] : '';
?>
			<tr>
				<td class="label"><?php echo $field['info']; ?></td>
				<td>
					<textarea style="font-size: 1em; height: <?php echo $field['type']; ?>em;" class="full_textarea" name="page[s2_page_blocks_<?php echo s2_htmlencode($field['name']); ?>]"><?php echo s2_htmlencode($s2_page_blocks_text); ?></textarea>
				</td>
			</tr>
<?php
		}
	}
?>
		</table>
<?php

	return $height_sum;
}