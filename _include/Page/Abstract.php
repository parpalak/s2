<?php
/**
 * Abstract page render class.
 *
 * @copyright (C) 2014 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */

abstract class Page_Abstract
{
	protected $template_id = 'site.php';
	protected $error_template_id = 'error404.php';
	protected $template = null;
	protected $class = '';
	protected $page = array();
	protected $etag = null;
	protected $rss_link = array();

	abstract public function __construct (array $params = array());


	public function obtainTemplate ($path = false)
	{
		try {
			$this->template = s2_get_template($this->template_id, $path);
		}
		catch (Exception $e) {
			error($e->getMessage());
		}
	}

	public function getTemplate ()
	{
		if ($this->template === null)
			$this->obtainTemplate();

		return $this->template;
	}

	protected function error_404 ()
	{
		global $lang_common;

		header($_SERVER['SERVER_PROTOCOL'].' 404 Not Found');
		$this->template = s2_get_template($this->error_template_id);

		$this->page = array(
			'head_title'	=> $lang_common['Error 404'],
			'title'			=> '<h1>'.$lang_common['Error 404'].'</h1>',
			'text'			=> sprintf($lang_common['Error 404 text'], s2_link('/')),
		);

		$this->render();

		die();
	}

	/**
	 * Outputs content to browser
	 */
	public function process_template ()
	{
		global $s2_start, $s2_db, $lang_common;
		//
		// Preparing the content for inserting to the template
		//

		if ($this->template === null)
			$this->obtainTemplate();

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

		if (empty($this->rss_link))
			$this->rss_link[] = '<link rel="alternate" type="application/rss+xml" title="'.$lang_common['RSS link title'].'" href="'.s2_link('/rss.xml').'" />';
		$replace['<!-- s2_rss_link -->'] = implode("\n", $this->rss_link);

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
		$replace['<!-- s2_title -->'] = !empty($page['title']) ? '<h1'.(!empty($page['favorite']) ? ' class="favorite-title"' : '').'>'.(!empty($page['title_prefix']) ? implode('', $page['title_prefix']) : '').$page['title'].'</h1>' : '';
		$replace['<!-- s2_date -->'] = !empty($page['date']) ? '<div class="date">'.s2_date($page['date']).'</div>' : '';
		$replace['<!-- s2_crumbs -->'] = isset($page['path']) ? $page['path'] : '';
		$replace['<!-- s2_section_link -->'] = isset($page['section_link']) ? $page['section_link'] : '';
		$replace['<!-- s2_excerpt -->'] = isset($page['excerpt']) ? $page['excerpt'] : '';
		$replace['<!-- s2_text -->'] = isset($page['text']) ? $page['text'] : '';
		$replace['<!-- s2_subarticles -->'] = isset($page['subcontent']) ? $page['subcontent'] : '';
		$replace['<!-- s2_tags -->'] = !empty($page['tags']) ? $page['tags'] : '';
		$replace['<!-- s2_comments -->'] = isset($page['comments']) ? $page['comments'] : '';

		if (S2_ENABLED_COMMENTS && !empty($page['commented']))
			$replace['<!-- s2_comment_form -->'] = '<h2 class="comment form">'.$lang_common['Post a comment'].'</h2>'."\n".s2_comment_form($page['id'].'.'.$this->class);
		else
			$replace['<!-- s2_comment_form -->'] = '';

		$replace['<!-- s2_back_forward -->'] = !empty($page['back_forward']) ? $page['back_forward'] : '';

		// Aside
		$replace['<!-- s2_menu -->'] = !empty($page['menu']) ? implode("\n", $page['menu']) : '';
		$replace['<!-- s2_article_tags -->'] = !empty($page['article_tags']) ? $page['article_tags'] : '';

		if (strpos($template, '<!-- s2_last_comments -->') !== false && ($last_comments = Placeholder::last_article_comments()))
			$replace['<!-- s2_last_comments -->'] = '<div class="header">'.$lang_common['Last comments'].'</div>'.$last_comments;

		if (strpos($template, '<!-- s2_last_discussions -->') !== false && ($last_discussions = Placeholder::last_discussions()))
			$replace['<!-- s2_last_discussions -->'] = '<div class="header">'.$lang_common['Last discussions'].'</div>'.$last_discussions;

		if (strpos($template, '<!-- s2_last_articles -->') !== false)
			$replace['<!-- s2_last_articles -->'] = Placeholder::last_articles(5);

		if (strpos($template, '<!-- s2_tags_list -->') !== false)
			$replace['<!-- s2_tags_list -->'] = Placeholder::tags_list();

		// Footer
		$replace['<!-- s2_copyright -->'] = s2_build_copyright();

		($hook = s2_hook('idx_pre_get_queries')) ? eval($hook) : null;

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
			$time_placeholder = 't = '.s2_number_format(microtime(true) - $s2_start, true, 3).'; q = '.$s2_db->get_num_queries();
			$template = str_replace('<!-- s2_querytime -->', $time_placeholder, $template);
			$etag .= md5($time_placeholder);
		}

		$s2_db->close();

		$etag = '"'.md5($etag).'"';

		$this->etag = $etag;

		if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] == $etag)
		{
			header($_SERVER['SERVER_PROTOCOL'].' 304 Not Modified');
			exit;
		}
	}

	/**
	 * Outputs content to browser
	 */
	public function render ()
	{
		$this->process_template();

		ob_start();
		if (S2_COMPRESS)
			ob_start('ob_gzhandler');

		echo $this->template;

		if (S2_COMPRESS)
			ob_end_flush();

		if (!empty($this->etag))
			header('ETag: '.$this->etag);
		header('Content-Length: '.ob_get_length());
		header('Content-Type: text/html; charset=utf-8');

		ob_end_flush();
	}
}
