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
	 * Call this if there is nothing to display
	 * and you want to show custom page.
	 */
	protected function s2_404_header ()
	{
		$return = ($hook = s2_hook('fn_404_header_start')) ? eval($hook) : null;
		if ($return != null)
			return;

		$this->checkRedirect();

		header($_SERVER['SERVER_PROTOCOL'].' 404 Not Found');
		s2_no_cache();
	}

	/**
	 * Call this if there is nothing to display
	 * and you want to show standard 404 page.
	 */
	protected function error_404 ()
	{
		$this->checkRedirect();

		header($_SERVER['SERVER_PROTOCOL'].' 404 Not Found');
		try {
			$this->template = s2_get_template($this->error_template_id);
		}
		catch (Exception $e) {
			error($e->getMessage());
		}

		$this->page = array(
			'head_title' => Lang::get('Error 404'),
			'title'      => '<h1 class="error404-header">' . Lang::get('Error 404') . '</h1>',
			'text'       => sprintf(Lang::get('Error 404 text'), s2_link('/')),
		);

		$this->page['path'][] = array(
			'title' => \Model::main_page_title(),
			'link'  => s2_link('/'),
		);

		$this->render();
		die();
	}

	protected function checkRedirect ()
	{
		global $request_uri, $s2_redirect;

		if (empty($s2_redirect))
			return;

		$new_url = preg_replace(array_keys($s2_redirect), array_values($s2_redirect), $request_uri);
		if ($new_url != $request_uri)
		{
			$is_external = (substr($new_url, 0, 7) === 'http://' || substr($new_url, 0, 8) === 'https://');
			s2_permanent_redirect($new_url, $is_external);
		}
	}

    protected function simple_placeholders(): array
    {
        return [
            'section_link',
            'excerpt',
            'text',
            'tags',
            'recommendations',
            'comments',
            'menu_siblings',
            'menu_children',
            'menu_subsections',
            'article_tags'
        ];
    }

	/**
	 * Prepares the content and inserts into the template
	 */
	public function process_template ()
	{
		global $s2_start;

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
		if (!empty($page['canonical_path']) && defined('S2_CANONICAL_URL'))
			$meta_tags[] = '<link rel="canonical" href="' . S2_CANONICAL_URL . s2_htmlencode($page['canonical_path']) . '" />';

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
		$replace['<!-- s2_subarticles -->'] = isset($page['subcontent']) ? $page['subcontent'] : '';

		foreach ($this->simple_placeholders() as $page_index)
		{
			$replace['<!-- s2_' . $page_index . ' -->'] = $page[$page_index] ?? '';
		}


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
        /** @var ?\S2\Cms\Pdo\PDO $pdo */
        $pdo = \Container::getIfInstantiated(\PDO::class);
        /** @var ?\DBLayer_Abstract $s2_db */
        $s2_db = \Container::getIfInstantiated('db');
        if (defined('S2_SHOW_QUERIES')) {
            $pdoLogs                      = $pdo ? $pdo->cleanLogs() : [];
            $replace['<!-- s2_debug -->'] = defined('S2_SHOW_QUERIES') ? $this->viewer->render('debug_queries', [
                'saved_queries'  => $s2_db !== null ? $s2_db->get_saved_queries() : [],
                'saved_queries2' => $pdoLogs,
            ]) : '';
        }

		$etag = md5($template);
		// Add here placeholders to be excluded from the ETag calculation
		$etag_skip = array('<!-- s2_comment_form -->');

		($hook = s2_hook('idx_template_pre_replace')) ? eval($hook) : null;

		// Replacing placeholders and calculating hash for ETag header
		foreach ($replace as $what => $to)
		{
			if (defined('S2_DEBUG_VIEW') && $to && !in_array($what, array('<!-- s2_head_title -->', '<!-- s2_navigation_link -->', '<!-- s2_rss_link -->', '<!-- s2_meta -->', '<!-- s2_styles -->')))
			{

				$title = '<pre style="color: red; font-size: 12px; opacity: 0.6; margin: 0; width: 100%; text-align: center; line-height: 1; position: absolute; left: 0; right: 0; z-index: 1000; top: 0;">' . s2_htmlencode($what) . '</pre>';
				$to = '<div style="border: 1px solid rgba(255, 0, 0, 0.4); margin: 1px; position: relative;">'.
					$title . $to .
					'</div>';
			}

			if (!in_array($what, $etag_skip))
				$etag .= md5($to);

			$template = str_replace($what, $to, $template);
		}

		($hook = s2_hook('idx_template_after_replace')) ? eval($hook) : null;

		// Execution time
		if (defined('S2_DEBUG') || defined('S2_SHOW_TIME'))
		{
			$time_placeholder = 't = '.Lang::number_format(microtime(true) - $s2_start, true, 3).'; q = '.(($s2_db !== null ? $s2_db->get_num_queries() : 0) + ($pdo ? (isset($pdoLogs) ? count($pdoLogs) : $pdo->getQueryCount()) : 0));
			$template = str_replace('<!-- s2_querytime -->', $time_placeholder, $template);
			$etag .= md5($time_placeholder);
		}

		$this->etag = '"'.md5($etag).'"';
	}
}
