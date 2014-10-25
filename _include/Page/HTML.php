<?php
/**
 * Page controller for processing template placeholders.
 *
 * @copyright (C) 2007-2014 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */


abstract class Page_HTML extends Page_Abstract
{
	protected $error_template_id = 'error404.php';

	/**
	 * Call this if there is nothing to display.
	 */
	protected function error_404 ()
	{
		header($_SERVER['SERVER_PROTOCOL'].' 404 Not Found');
		try {
			$this->template = s2_get_template($this->error_template_id);
		}
		catch (Exception $e) {
			error($e->getMessage());
		}

		$this->page = array(
			'head_title'	=> Lang::get('Error 404'),
			'title'			=> '<h1>'.Lang::get('Error 404').'</h1>',
			'text'			=> sprintf(Lang::get('Error 404 text'), s2_link('/')),
		);

		$this->render();
		die();
	}

	/**
	 * Prepares the content and inserts into the template
	 */
	public function process_template ()
	{
		global $s2_start, $s2_db;

		$template = &$this->template;
		$page = &$this->page;

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

		($hook = s2_hook('proc_tpl_pre_meta_merge')) ? eval($hook) : null;
		$replace['<!-- s2_meta -->'] = implode("\n", $meta_tags);

		if (empty($page['rss_link']))
			$page['rss_link'][] = '<link rel="alternate" type="application/rss+xml" title="'.Lang::get('RSS link title').'" href="'.s2_link('/rss.xml').'" />';
		$replace['<!-- s2_rss_link -->'] = implode("\n", $page['rss_link']);

		// Content
		$replace['<!-- s2_site_title -->'] = S2_SITE_NAME;
		$replace['<!-- s2_navigation_link -->'] = '';
		if (isset($page['link_navigation']))
		{
			$link_navigation = array();
			foreach ($page['link_navigation'] as $link_rel => $link_href)
				$link_navigation[] = '<link rel="'.$link_rel.'" href="'.$link_href.'" />';

			$replace['<!-- s2_navigation_link -->'] = implode("\n", $link_navigation);
		}

		$replace['<!-- s2_author -->'] = !empty($page['author']) ? '<div class="author">'.$page['author'].'</div>' : '';
		$replace['<!-- s2_title -->'] = empty($page['title']) ? '' : $this->viewer->render('title', array_intersect_key($page, array('title' => 1, 'favorite' => 1)));
		$replace['<!-- s2_date -->'] = !empty($page['date']) ? '<div class="date">'.s2_date($page['date']).'</div>' : '';
		$replace['<!-- s2_crumbs -->'] = isset($page['path']) ? $this->viewer->render('breadcrumbs', array('breadcrumbs' => $page['path'])) : '';
		$replace['<!-- s2_section_link -->'] = isset($page['section_link']) ? $page['section_link'] : '';
		$replace['<!-- s2_excerpt -->'] = isset($page['excerpt']) ? $page['excerpt'] : '';
		$replace['<!-- s2_text -->'] = isset($page['text']) ? $page['text'] : '';
		$replace['<!-- s2_subarticles -->'] = isset($page['subcontent']) ? $page['subcontent'] : '';
		$replace['<!-- s2_tags -->'] = !empty($page['tags']) ? $page['tags'] : '';
		$replace['<!-- s2_comments -->'] = isset($page['comments']) ? $page['comments'] : '';

		if (S2_ENABLED_COMMENTS && !empty($page['commented']))
		{
			$comment_array = array(
				'id' => $page['id'].'.'.(isset($page['class']) ? $page['class'] : '')
			);

			if (!empty($page['comment_form']) && is_array($page['comment_form']))
				$comment_array += $page['comment_form'];

			$replace['<!-- s2_comment_form -->'] = $this->renderPartial('comment_form',  $comment_array);
		}
		else
			$replace['<!-- s2_comment_form -->'] = '';

		$replace['<!-- s2_back_forward -->'] = !empty($page['back_forward']) ? $this->viewer->render('back_forward', array('links' => $page['back_forward'])) : '';

		// Aside
		$replace['<!-- s2_menu -->'] = !empty($page['menu']) ? implode("\n", $page['menu']) : '';
		$replace['<!-- s2_article_tags -->'] = !empty($page['article_tags']) ? $page['article_tags'] : '';

		if (strpos($template, '<!-- s2_last_comments -->') !== false && count($last_comments = Placeholder::last_article_comments()))
			$replace['<!-- s2_last_comments -->'] = $this->viewer->render('menu_comments', array(
				'title' => Lang::get('Last comments'),
				'menu'  => $last_comments,
			));

		if (strpos($template, '<!-- s2_last_discussions -->') !== false && count($last_discussions = Placeholder::last_discussions()))
			$replace['<!-- s2_last_discussions -->'] = $this->viewer->render('menu_block', array(
				'title' => Lang::get('Last discussions'),
				'menu'  => $last_discussions,
			));

		if (strpos($template, '<!-- s2_last_articles -->') !== false)
			$replace['<!-- s2_last_articles -->'] = Placeholder::last_articles($this->viewer, 5);

		if (strpos($template, '<!-- s2_tags_list -->') !== false)
			$replace['<!-- s2_tags_list -->'] = !count($tags_list = Placeholder::tags_list()) ? '' : $this->viewer->render('tags_list', array(
				'tags' => $tags_list,
			));

		// Footer
		$replace['<!-- s2_copyright -->'] = s2_build_copyright();

		($hook = s2_hook('idx_pre_get_queries')) ? eval($hook) : null;

		// Queries
		$replace['<!-- s2_debug -->'] = defined('S2_SHOW_QUERIES') ? $this->viewer->render('debug_queries', array('saved_queries' => $s2_db->get_saved_queries())) : '';

		$etag = md5($template);
		// Add here placeholders to be excluded from the ETag calculation
		$etag_skip = array('<!-- s2_comment_form -->');

		($hook = s2_hook('idx_template_pre_replace')) ? eval($hook) : null;

		// Replacing placeholders and calculating hash for ETag header
		foreach ($replace as $what => $to)
		{
			if (defined('S2_DEBUG_VIEW') && $to && !in_array($what, array('<!-- s2_head_title -->', '<!-- s2_navigation_link -->', '<!-- s2_rss_link -->', '<!-- s2_meta -->', '<!-- s2_styles -->')))
				$to = '<pre style="color: red; font-size: 12px; opacity: 0.4; margin: 0; width: 100%; text-align: center; line-height: 1;">' . s2_htmlencode($what) . '</pre>' .
					'<div style="border: 1px solid rgba(255, 0, 0, 0.4); margin: 1px;">'. $to . '</div>';

			if (!in_array($what, $etag_skip))
				$etag .= md5($to);

			$template = str_replace($what, $to, $template);
		}

		($hook = s2_hook('idx_template_after_replace')) ? eval($hook) : null;

		// Execution time
		if (defined('S2_DEBUG'))
		{
			$time_placeholder = 't = '.Lang::number_format(microtime(true) - $s2_start, true, 3).'; q = '.$s2_db->get_num_queries();
			$template = str_replace('<!-- s2_querytime -->', $time_placeholder, $template);
			$etag .= md5($time_placeholder);
		}

		$this->etag = '"'.md5($etag).'"';
	}
}