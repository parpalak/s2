<?php

use S2\Cms\Pdo\DbLayer;

/**
 * Abstract page controller class. Renders content for the browser
 *
 * @copyright (C) 2014-2024 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */
abstract class Page_Abstract
{
    protected string $template_id = 'site.php';
    protected ?string $template = null;
    /**
     * @deprecated Come up with a DTO for page
     * @note Somewhere this var can hold non-array values like false
     */
    protected $page = array();
    protected ?string $etag = null;
    protected Viewer $viewer;

    public function __construct(array $params = [])
    {
        if (empty($this->viewer)) {
            $this->viewer = new Viewer();
        } // Might already be overridden in child classes.
    }

    protected function renderPartial(string $name, array $vars): string
    {
        return $this->viewer->render($name, $vars);
    }

    public function ensureTemplateIsLoaded(): void
    {
        if ($this->template !== null) {
            return;
        }

        $ext_dir = s2_ext_dir_from_ns(get_class($this));
        $path    = $ext_dir ? $ext_dir . '/templates/' : false;

        try {
            $this->template = s2_get_template($this->template_id, $path);
        } catch (Exception $e) {
            error($e->getMessage());
        }
    }

    public function inTemplate($placeholder): bool
    {
        $this->ensureTemplateIsLoaded();

        return str_contains($this->template, $placeholder);
    }

    /**
     * Outputs content to browser
     */
    public function render(): void
    {
        $this->ensureTemplateIsLoaded();

        if ($this instanceof Page_HTML) {
            $this->process_template();
        }

        /** @var ?DbLayer $s2_db */
        $s2_db = Container::getIfInstantiated(DbLayer::class);
        $s2_db?->close();

        if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] === $this->etag) {
            header($_SERVER['SERVER_PROTOCOL'] . ' 304 Not Modified');
            exit;
        }

        ob_start();
        if (S2_COMPRESS) {
            ob_start('ob_gzhandler');
        }

        echo $this->template;

        if (S2_COMPRESS) {
            ob_end_flush();
        }

        if ($this->etag !== null) {
            header('ETag: ' . $this->etag);
        }
        header('Content-Length: ' . ob_get_length());
        header('Content-Type: text/html; charset=utf-8');

        ob_end_flush();
    }
}
