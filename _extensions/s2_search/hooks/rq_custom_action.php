<?php
/**
 * Hook rq_custom_action
 *
 * @copyright (C) 2023 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_search
 */

 if (!defined('S2_ROOT')) {
     die;
}

if ($action == 's2_search_makeindex')
{
	$is_permission = $s2_user['create_articles'] || $s2_user['edit_site'];
	$save_action   = $_GET['save_action'] ?? '';
	$id            = $_GET['id'] ?? '';

	$save_action = ($save_action == 'save') ? '' : $save_action.'_';
	$chapter     = $id ? $save_action.$id : false;

	($hook = s2_hook('s2_search_action_makeindex')) ? eval($hook) : null;

	s2_test_user_rights($is_permission);

	$fetcher = new \s2_extensions\s2_search\Fetcher();
	$finder  = new \s2_extensions\s2_search\IndexManager(S2_CACHE_DIR, $fetcher, Container::get(\S2\Rose\Indexer::class), Container::get(\S2\Rose\Storage\Database\PdoStorage::class));
	if (!$chapter)
		echo $finder->index();
	else
		$finder->refresh($chapter);
}
