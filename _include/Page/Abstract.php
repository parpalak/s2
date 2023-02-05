<?php
/**
 * Abstract page controller class. Renders content for the browser
 *
 * @copyright (C) 2014 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */

abstract class Page_Abstract
{
	protected $template_id = 'site.php';
	protected $template = null;
	protected $page = array();
	protected $etag = null;
	protected $viewer = null;

	public function __construct (array $params = array())
	{
		if (empty($this->viewer))
			$this->viewer = new Viewer();
	}

	protected function renderPartial($name, $vars)
	{
		return $this->viewer->render($name, $vars);
	}

	public function obtainTemplate ()
	{
		if ($this->template !== null)
			return;

		$ext_dir = s2_ext_dir_from_ns(get_class($this));
		$path = $ext_dir ? $ext_dir . '/templates/' : false;

		try {
			$this->template = s2_get_template($this->template_id, $path);
		}
		catch (Exception $e) {
			error($e->getMessage());
		}
	}

	public function inTemplate ($placeholder)
	{
		$this->obtainTemplate();
		return strpos($this->template, $placeholder) !== false;
	}

	/**
	 * Outputs content to browser
	 */
	public function render ()
	{
        /** @var ?DBLayer_Abstract $s2_db */
        $s2_db = \Container::getIfInstantiated('db');

		$this->obtainTemplate();

		if ($this instanceof Page_HTML) {
            $this->process_template();
        }

        if ($s2_db !== null) {
            $s2_db->close();
        }

		if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] == $this->etag)
		{
			header($_SERVER['SERVER_PROTOCOL'].' 304 Not Modified');
			exit;
		}

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
