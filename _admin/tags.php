<?php
/**
 * Tag management
 *
 * @copyright 2007-2024 Roman Parpalak
 * @license MIT
 * @package S2
 */


use S2\Cms\Pdo\DbLayer;

if (!defined('S2_ROOT'))
	die;

function s2_load_tag ($id)
{
    /** @var DbLayer $s2_db */
    $s2_db = \Container::get(DbLayer::class);

	$query = array(
		'SELECT'	=> 'tag_id as id, name, description, url, modify_time',
		'FROM'		=> 'tags',
		'WHERE'		=> 'tag_id = '.$id
	);
	($hook = s2_hook('fn_load_tag_pre_qr')) ? eval($hook) : null;
	$result = $s2_db->buildAndQuery($query);

	$tag = $s2_db->fetchAssoc($result);

	return $tag;
}

function s2_save_tag ($tag)
{
    /** @var DbLayer $s2_db */
    $s2_db = \Container::get(DbLayer::class);

	$id = isset($tag['id']) ? (int) $tag['id'] : 0;
	$tag_name = isset($tag['name']) ? $s2_db->escape($tag['name']) : '';
	$tag_url = isset($tag['url']) ? $s2_db->escape($tag['url']) : '';
	$tag_description = isset($tag['description']) ? $s2_db->escape($tag['description']) : '';

	$modify_time = !empty($tag['modify_time']) ? s2_timestamp_from_form_time($tag['modify_time']) : time();

	($hook = s2_hook('fn_save_tag_pre_id_check')) ? eval($hook) : null;

	if (!$id)
	{
		$query = array(
			'SELECT'	=> 'tag_id',
			'FROM'		=> 'tags',
			'WHERE'		=> 'name = \''.$tag_name.'\''
		);
		($hook = s2_hook('fn_save_tag_pre_get_id_qr')) ? eval($hook) : null;
		$result = $s2_db->buildAndQuery($query);
		if ($row = $s2_db->fetchAssoc($result))
			$id = $row['tag_id'];
	}

	if ($id)
	{
		$query = array(
			'UPDATE'	=> 'tags',
			'SET'		=> 'name = \''.$tag_name.'\', url = \''.$tag_url.'\', description = \''.$tag_description.'\', modify_time = '.$modify_time,
			'WHERE'		=> 'tag_id = '.$id
		);
		($hook = s2_hook('fn_save_tag_pre_upd_qr')) ? eval($hook) : null;
		$s2_db->buildAndQuery($query);
	}
	else
	{
		$query = array(
			'INSERT'	=> 'name, description, modify_time, url',
			'INTO'		=> 'tags',
			'VALUES'	=> '\''.$tag_name.'\', \''.$tag_description.'\', \''.$modify_time.'\', \''.$tag_url.'\''
		);
		($hook = s2_hook('fn_save_tag_pre_ins_qr')) ? eval($hook) : null;
		$s2_db->buildAndQuery($query);

		$id = $s2_db->insertId();
	}

	return $id;
}

function s2_delete_tag ($id)
{
    /** @var DbLayer $s2_db */
    $s2_db = \Container::get(DbLayer::class);

	$query = array(
		'DELETE'	=> 'tags',
		'WHERE'		=> 'tag_id = '.$id,
		'LIMIT'		=> '1'
	);
	($hook = s2_hook('fn_delete_tag_pre_del_tag_qr')) ? eval($hook) : null;
	$s2_db->buildAndQuery($query);

	$query = array(
		'DELETE'	=> 'article_tag',
		'WHERE'		=> 'tag_id = '.$id,
	);
	($hook = s2_hook('fn_delete_tag_pre_del_links_qr')) ? eval($hook) : null;
	$s2_db->buildAndQuery($query);

	($hook = s2_hook('fn_delete_tag_end')) ? eval($hook) : null;
}

function s2_output_tag_form ($tag, $modify_time)
{
    global $lang_admin;
    /** @var DbLayer $s2_db */
    $s2_db = \Container::get(DbLayer::class);

    ($hook = s2_hook('fn_output_tag_form_pre_get_tags')) ? eval($hook) : null;

    $subquery = array(
        'SELECT'	=> 'count(*)',
        'FROM'		=> 'article_tag AS at',
        'WHERE'		=> 't.tag_id = at.tag_id'
    );
    $raw_query = $s2_db->build($subquery);

    $query = array(
        'SELECT'	=> 'tag_id AS id, name, ('.$raw_query.') AS art_count',
        'FROM'		=> 'tags AS t',
        'ORDER BY'	=> 'name'
    );
    ($hook = s2_hook('fn_output_tag_form_pre_get_tags_qr')) ? eval($hook) : null;
    $result = $s2_db->buildAndQuery($query);

    $tag_names = array();
    $rows = [];
    while ($row = $s2_db->fetchAssoc($result))
    {
        $rows[] = $row;
        $tag_names[$row['id']] = $row['name'];
    }


    ($hook = s2_hook('fn_output_tag_form_start')) ? eval($hook) : null;

?>
<form class="full_tab_form" name="tagform" action="" onsubmit="return SaveTag();">
	<div class="main-column vert-flex">
		<table class="fields">
<?php ($hook = s2_hook('fn_output_tag_form_pre_name')) ? eval($hook) : null; ?>
			<tr>
				<td class="label"><?php echo $lang_admin['Tag']; ?></td>
				<td><input type="text" name="tag[name]" size="50" maxlength="191" value="<?php echo s2_htmlencode($tag['name']); ?>" /></td>
			</tr>
<?php ($hook = s2_hook('fn_output_tag_form_pre_time')) ? eval($hook) : null; ?>
			<tr>
				<td class="label"><?php echo $lang_admin['Modify time']; ?></td>
				<td>
					<?php echo s2_get_time_input('tag[modify_time]', $modify_time); ?>
					<a href="#" class="js" onclick="return SetTime(document.forms['tagform'], 'tag[modify_time]');"><?php echo $lang_admin['Now']; ?></a>
				</td>
			</tr>
<?php ($hook = s2_hook('fn_output_tag_form_after_time')) ? eval($hook) : null; ?>
		</table>
<?php ($hook = s2_hook('fn_output_tag_form_pre_text')) ? eval($hook) : null; ?>
		<div class="text_wrapper">
			<textarea id="tagtext" class="full_textarea" name="tag[description]"><?php echo s2_htmlencode($tag['description'])?></textarea>
		</div>
	</div>
    <div class="aside-column">
        <?php ($hook = s2_hook('fn_output_tag_form_pre_url')) ? eval($hook) : null; ?>
        <?php echo $lang_admin['URL part']; ?>
        <br />
        <input type="text" name="tag[url]" size="18" maxlength="191" value="<?php echo $tag['url']; ?>" />
        <br />
        <?php ($hook = s2_hook('fn_output_tag_form_pre_replace_tag')) ? eval($hook) : null; ?>
        <?php echo $lang_admin['Replace tag']; ?>
        <br />
        <select name="tag[id]">
            <option value="0"><?php echo $lang_admin['New']; ?></option>
            <?php

            ($hook = s2_hook('fn_output_tag_form_pre_loop_replace_tag')) ? eval($hook) : null;

            foreach ($tag_names as $k => $v)
                echo "\t\t\t".'<option value="'.$k.'"'.($k == $tag['id'] ? 'selected="selected"' : '').'>'.s2_htmlencode($v).'</option>'."\n";

            ?>
        </select>
        <?php ($hook = s2_hook('fn_output_tag_form_after_replace_tag')) ? eval($hook) : null; ?>
        <hr />
        <?php ($hook = s2_hook('fn_output_tag_form_pre_submit')) ? eval($hook) : null; ?>
        <input class="bitbtn savetag" name="button" type="submit" title="<?php echo $lang_admin['Save info']; ?>" value="<?php echo $lang_admin['Save']; ?>" />
        <?php ($hook = s2_hook('fn_output_tag_form_after_submit')) ? eval($hook) : null; ?>
        <hr />
        <?php

        ($hook = s2_hook('fn_output_tag_form_pre_delete')) ? eval($hook) : null;

        if ($tag['id'])
        {

            ?>
            <input class="bitbtn deltag" type="button" title="<?php printf($lang_admin['Delete tag'], s2_htmlencode($tag['name'])); ?>" value="<?php echo $lang_admin['Delete']; ?>" onclick="return DeleteTag(<?php echo $tag['id'], ', \'', s2_htmlencode(addslashes($tag['name'])), '\''; ?>);" />
            <?php ($hook = s2_hook('fn_output_tag_form_after_delete')) ? eval($hook) : null; ?>
            <br />
            <br />
            <a title="<?php echo $lang_admin['Preview published']; ?>" target="_blank" href="<?php echo s2_link('/'.S2_TAGS_URL.'/'.urlencode($tag['url'])); ?>"><?php echo $lang_admin['Preview ready']; ?></a>
            <?php

        }

        ($hook = s2_hook('fn_output_tag_form_after_preview')) ? eval($hook) : null;

        ?>
    </div>
    <div class="aside-column vert-flex" title="<?php echo $lang_admin['Click tag']; ?>">
        <?php echo $lang_admin['Tags:']; ?>
        <hr />
        <div class="tags_list">
            <?php

            foreach ($rows as $row) {
                $info = $row['art_count'];
                ($hook = s2_hook('fn_output_tag_form_loop_get_tags_qr')) ? eval($hook) : null;
                echo '<a href="#" class="js'.($row['id'] == $tag['id'] ? ' cur_link' : '').'" onclick="return LoadTag(\''.$row['id'].'\');">'.s2_htmlencode($row['name']).' ('.$info.')</a><br />'."\n";
            }

            ?>
        </div>
    </div>
</form>
<?php

}
