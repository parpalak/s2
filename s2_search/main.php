<?php
/**
 * Search results page
 *
 * @copyright (C) 2011 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_search
 */


$s2_search_query = isset($_GET['q']) ? $_GET['q'] : '';
$s2_search_page = isset($_GET['p']) ? (int) $_GET['p'] : 1;

$template = s2_get_template('service.php');

require $ext_info['path'].'/stemmer.class.php';
require $ext_info['path'].'/finder.class.php';

ob_start();

?>
<div class="search-results">
	<form method="get" action="<?php echo S2_PATH.S2_URL_PREFIX; ?>/search">
		<div class="button">
			<input type="submit" name="search" value="<?php echo $lang_s2_search['Search button']; ?>" />
		</div>
		<div class="wrap">
			<input id="s2_search_input_ext" type="text" name="q" value="<?php echo $s2_search_query; ?>" />
		</div>
	</form>
<?php

if ($s2_search_query !== '')
	s2_search_finder::find($s2_search_query, $s2_search_page);

?>
</div>
<?php

$page['text'] = ob_get_clean();
$page['title'] = $lang_s2_search['Search'];

$s2_search_queryuery = array(
	'SELECT'	=> 'title',
	'FROM'		=> 'articles',
	'WHERE'		=> 'parent_id = '.S2_ROOT_ID,
);
($hook = s2_hook('s2_search_pre_crumbs_fetch_qr')) ? eval($hook) : null;
$s2_search_result = $s2_db->query_build($s2_search_queryuery) or error(__FILE__, __LINE__);
list($s2_search_main) = $s2_db->fetch_row($s2_search_result);
$page['path'] = sprintf($lang_s2_search['Crumbs'], $s2_search_main, S2_PATH.'/', $lang_s2_search['Search']);
