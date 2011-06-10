<?php
/**
 * Processing all public pages of the site.
 *
 * @copyright (C) 2009-2011 Roman Parpalak, partially based on code (C) 2008-2009 PunBB
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */


list($usec, $sec) = explode(' ', microtime());
$s2_start = ((float)$usec + (float)$sec);

define('S2_ROOT', './');
require S2_ROOT.'_include/common.php';

($hook = s2_hook('idx_start')) ? eval($hook) : null;

header('X-Powered-By: S2/'.S2_VERSION);

// We create our own request URI with the path removed and only the parts to rewrite included
if (isset($_SERVER['PATH_INFO']) && S2_URL_PREFIX != '')
	$request_uri = $_SERVER['PATH_INFO'];
else
{
	$request_uri = substr(urldecode($_SERVER['REQUEST_URI']), strlen(S2_URL_PREFIX.S2_PATH));
	if (strpos($request_uri, '?') !== false)
		$request_uri = substr($request_uri, 0, strpos($request_uri, '?'));
}

($hook = s2_hook('idx_pre_redirect')) ? eval($hook) : null;

//
// Redirect to the admin page
//
if (substr($request_uri, -3) == '---')
{
	header('Location: '.S2_BASE_URL.'/_admin/index.php?path='.urlencode(substr($request_uri, 0, -3)));
	die;
}

($hook = s2_hook('idx_pre_rss')) ? eval($hook) : null;

//
// Process RSS request (last articles)
//
if ($request_uri == '/rss.xml')
{
	if (!defined('S2_ARTICLES_FUNCTIONS_LOADED'))
		require S2_ROOT.'_include/articles.php';
	if (!defined('S2_RSS_FUNCTIONS_LOADED'))
		require S2_ROOT.'_include/rss.php';
	s2_no_cache(false);
	s2_do_rss();
	die;
}

if (!defined('S2_COMMENTS_FUNCTIONS_LOADED'))
	require S2_ROOT.'_include/comments.php';

//
// Obtaining the content (array $page) and the template ($template string)
// These variables must be set from now
//
$return = ($hook = s2_hook('idx_get_content')) ? eval($hook) : null;
if (!$return)
{
	if (!defined('S2_ARTICLES_FUNCTIONS_LOADED'))
		require S2_ROOT.'_include/articles.php';
	s2_parse_page_url($request_uri);
}

s2_no_cache(false);

//
// Preparing the content for inserting to the template
//

// HTML head
$replace['<!-- s2_head_title -->'] = empty($page['head_title']) ?
	(!empty($page['title']) ? $page['title'].' - ' : '').S2_SITE_NAME :
	$page['head_title'];

// Meta tags processing
$meta_tags = array(
	'<meta name="Generator" content="S2 '.S2_VERSION.'" />',
);
if (!empty($page['meta_keywords']))
	$meta_tags[] = '<meta name="keywords" content="'.s2_htmlencode($page['meta_keywords']).'" />';
if (!empty($page['meta_description']))
	$meta_tags[] = '<meta name="description" content="'.s2_htmlencode($page['meta_description']).'" />';

($hook = s2_hook('idx_pre_meta_merge')) ? eval($hook) : null;
$replace['<!-- s2_meta -->'] = implode("\n", $meta_tags);

$replace['<!-- s2_rss_link -->'] = '<link rel="alternate" type="application/rss+xml" title="'.$lang_common['RSS link title'].'" href="'.S2_BASE_URL.S2_URL_PREFIX.'/rss.xml" />';

// Including the style
ob_start();
include S2_ROOT.'_styles/'.S2_STYLE.'/'.S2_STYLE.'.php';
$replace['<!-- s2_styles -->'] = ob_get_clean();

// Content
$replace['<!-- s2_site_title -->'] = S2_SITE_NAME;

$replace['<!-- s2_title -->'] = !empty($page['title']) ? '<h1>'.$page['title'].'</h1>' : '';
$replace['<!-- s2_date -->'] = !empty($page['date']) ? '<div class="date">'.s2_date($page['date']).'</div>' : '';
$replace['<!-- s2_crumbs -->'] = isset($page['path']) ? $page['path'] : '';
$replace['<!-- s2_text -->'] = isset($page['text']) ? $page['text'] : '';
$replace['<!-- s2_subarticles -->'] = isset($page['subcontent']) ? $page['subcontent'] : '';
$replace['<!-- s2_tags -->'] = !empty($page['tags_list']) ? $page['tags_list'] : '';
$replace['<!-- s2_comments -->'] = isset($page['comments']) ? $page['comments'] : '';

if (S2_ENABLED_COMMENTS && isset($page['commented']) && $page['commented'])
	$replace['<!-- s2_comment_form -->'] = '<h2 class="comment form">'.$lang_common['Post a comment'].'</h2>'."\n".s2_comment_form($page['id']);
else
	$replace['<!-- s2_comment_form -->'] = '';

$replace['<!-- s2_back_forward -->'] = !empty($page['back_forward']) ? $page['back_forward'] : '';

// Aside
$replace['<!-- s2_menu -->'] = !empty($page['menu']) ? implode("\n", $page['menu']) : '';
$replace['<!-- s2_article_tags -->'] = !empty($page['article_tags']) ? $page['article_tags'] : '';

if (strpos($template, '<!-- s2_last_comments -->') !== false && ($last_comments = s2_last_artilce_comments()))
	$replace['<!-- s2_last_comments -->'] = '<div class="header">'.$lang_common['Last comments'].'</div>'.$last_comments;

if (strpos($template, '<!-- s2_last_discussions -->') !== false && ($last_discussions = s2_last_discussions()))
	$replace['<!-- s2_last_discussions -->'] = '<div class="header">'.$lang_common['Last discussions'].'</div>'.$last_discussions;

if (strpos($template, '<!-- s2_last_articles -->') !== false)
	$replace['<!-- s2_last_articles -->'] = s2_last_articles();

// Footer
$author = S2_WEBMASTER ? S2_WEBMASTER : S2_SITE_NAME;
$link = S2_WEBMASTER_EMAIL ? s2_js_mailto($author, S2_WEBMASTER_EMAIL) : '<a href="'.S2_BASE_URL.'/">'.$author.'</a>';

$replace['<!-- s2_copyright -->'] = (S2_START_YEAR != date('Y') ?
	sprintf($lang_common['Copyright 2'], $link, S2_START_YEAR, date('Y')) :
	sprintf($lang_common['Copyright 1'], $link, date('Y'))).' '.
	sprintf($lang_common['Powered by'], '<a href="http://s2cms.ru/">S2</a>');

// Queries
$replace['<!-- s2_debug -->'] = defined('S2_SHOW_QUERIES') ? s2_get_saved_queries() : '';

$etag = md5($template);
// Add here placeholders to be excluded from the ETag calculation
$etag_skip = array('<!-- s2_comment_form -->');

($hook = s2_hook('idx_template_pre_replace')) ? eval($hook) : null;

// Replacing placeholders and calculating hash for ETag header
foreach ($replace as $what => $to)
{
	if (!in_array($what, $etag_skip))
		$etag .= md5($to);
	$template = str_replace($what, $to, $template);
}

($hook = s2_hook('idx_template_after_replace')) ? eval($hook) : null;

// Execution time
if (defined('S2_DEBUG'))
{
	list($usec, $sec) = explode(' ', microtime());
	$page['generate_time'] = 't = '.s2_number_format(((float)$usec + (float)$sec - $s2_start), true, 3).'; q = '.$s2_db->get_num_queries();
	$template = str_replace('<!-- s2_querytime -->', $page['generate_time'], $template);
	$etag .= md5($page['generate_time']);
}

$etag = '"'.md5($etag).'"';

if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] == $etag)
{
	header('HTTP/1.1 304 Not Modified');
	exit;
}

//
// Page output
//
ob_start();
if (S2_COMPRESS)
	ob_start('ob_gzhandler');

echo $template;

if (S2_COMPRESS)
	ob_end_flush();

header('ETag: '.$etag);
header('Content-Length: '.ob_get_length());
header('Content-Type: text/html; charset=utf-8');

ob_end_flush();