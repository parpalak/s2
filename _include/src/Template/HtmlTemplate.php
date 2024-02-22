<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license MIT
 * @package S2
 */

declare(strict_types=1);

namespace S2\Cms\Template;

use Symfony\Component\HttpFoundation\Response;

class HtmlTemplate
{
    protected array $page = [];
    protected array $breadCrumbs = [];

    public function __construct(
        protected string $template,
        protected \Viewer $viewer
    ) {
    }

    public function putInPlaceholder(string $placeholder, mixed $content): static
    {
        $this->page[$placeholder] = $content;

        return $this;
    }

    public function addBreadCrumb(string $title, ?string $link = null): static
    {
        $this->breadCrumbs[] = ['title' => $title, 'link' => $link];

        return $this;
    }

    public function toHttpResponse(): Response
    {
        global $s2_start;

        $template = $this->template;

        // HTML head
        $replace['<!-- s2_head_title -->'] = empty($this->page['head_title']) ?
            (!empty($this->page['title']) ? $this->page['title'] . ' - ' : '') . S2_SITE_NAME :
            $this->page['head_title'];

        // Meta tags processing
        $meta_tags = [
            '<meta name="Generator" content="S2 ' . S2_VERSION . '" />',
        ];

        if (!empty($this->page['meta_keywords'])) {
            $meta_tags[] = '<meta name="keywords" content="' . s2_htmlencode($this->page['meta_keywords']) . '" />';
        }
        if (!empty($this->page['meta_description'])) {
            $meta_tags[] = '<meta name="description" content="' . s2_htmlencode($this->page['meta_description']) . '" />';
        }
        if (!empty($this->page['canonical_path']) && \defined('S2_CANONICAL_URL')) {
            $meta_tags[] = '<link rel="canonical" href="' . S2_CANONICAL_URL . s2_htmlencode($this->page['canonical_path']) . '" />';
        }

        $replace['<!-- s2_meta -->'] = implode("\n", $meta_tags);

        if (empty($this->page['rss_link'])) {
            $this->page['rss_link'][] = '<link rel="alternate" type="application/rss+xml" title="' . \Lang::get('RSS link title') . '" href="' . s2_link('/rss.xml') . '" />';
        }
        $replace['<!-- s2_rss_link -->'] = implode("\n", $this->page['rss_link']);

        // Content
        $replace['<!-- s2_site_title -->']      = S2_SITE_NAME;
        $replace['<!-- s2_navigation_link -->'] = '';
        if (isset($this->page['link_navigation'])) {
            $link_navigation = [];
            foreach ($this->page['link_navigation'] as $link_rel => $link_href) {
                $link_navigation[] = '<link rel="' . $link_rel . '" href="' . $link_href . '" />';
            }

            $replace['<!-- s2_navigation_link -->'] = implode("\n", $link_navigation);
        }

        $replace['<!-- s2_author -->']      = !empty($this->page['author']) ? '<div class="author">' . $this->page['author'] . '</div>' : '';
        $replace['<!-- s2_title -->']       = !empty($this->page['title']) ? $this->viewer->render('title', array_intersect_key($this->page, ['title' => 1, 'favorite' => 1])) : '';
        $replace['<!-- s2_date -->']        = !empty($this->page['date']) ? '<div class="date">' . s2_date($this->page['date']) . '</div>' : '';
        $replace['<!-- s2_crumbs -->']      = \count($this->breadCrumbs) > 0 ? $this->viewer->render('breadcrumbs', ['breadcrumbs' => $this->breadCrumbs]) : '';
        $replace['<!-- s2_subarticles -->'] = $this->page['subcontent'] ?? '';

        foreach ($this->simplePlaceholders() as $placeholderName) {
            $replace['<!-- s2_' . $placeholderName . ' -->'] = $this->page[$placeholderName] ?? '';
        }

        if (S2_ENABLED_COMMENTS && !empty($this->page['commented'])) {
            $comment_array = [
                'id' => $this->page['id'] . '.' . ($this->page['class'] ?? '')
            ];

            if (!empty($this->page['comment_form']) && \is_array($this->page['comment_form'])) {
                $comment_array += $this->page['comment_form'];
            }

            $replace['<!-- s2_comment_form -->'] = $this->viewer->render('comment_form', $comment_array);
        } else {
            $replace['<!-- s2_comment_form -->'] = '';
        }

        $replace['<!-- s2_back_forward -->'] = !empty($this->page['back_forward']) ? $this->viewer->render('back_forward', ['links' => $this->page['back_forward']]) : '';

        if (str_contains($template, '<!-- s2_last_comments -->') && \count($last_comments = \Placeholder::last_article_comments())) {
            $replace['<!-- s2_last_comments -->'] = $this->viewer->render('menu_comments', [
                'title' => \Lang::get('Last comments'),
                'menu'  => $last_comments,
            ]);
        }

        if (str_contains($template, '<!-- s2_last_discussions -->') && \count($last_discussions = \Placeholder::last_discussions())) {
            $replace['<!-- s2_last_discussions -->'] = $this->viewer->render('menu_block', [
                'title' => \Lang::get('Last discussions'),
                'menu'  => $last_discussions,
            ]);
        }

        if (str_contains($template, '<!-- s2_last_articles -->')) {
            $replace['<!-- s2_last_articles -->'] = \Placeholder::last_articles($this->viewer, 5);
        }

        if (str_contains($template, '<!-- s2_tags_list -->')) {
            $replace['<!-- s2_tags_list -->'] = !\count($tags_list = \Placeholder::tags_list()) ? '' : $this->viewer->render('tags_list', [
                'tags' => $tags_list,
            ]);
        }

        // Footer
        $replace['<!-- s2_copyright -->'] = s2_build_copyright();

        ($hook = s2_hook('idx_pre_get_queries')) ? eval($hook) : null;

        // Queries
        /** @var ?\S2\Cms\Pdo\PDO $pdo */
        $pdo = \Container::getIfInstantiated(\PDO::class);
        if (defined('S2_SHOW_QUERIES')) {
            $pdoLogs                      = $pdo ? $pdo->cleanLogs() : [];
            $replace['<!-- s2_debug -->'] = defined('S2_SHOW_QUERIES') ? $this->viewer->render('debug_queries', [
                'saved_queries' => $pdoLogs,
            ]) : '';
        }

        $etag = md5($template);
        // Add here placeholders to be excluded from the ETag calculation
        $etag_skip = array('<!-- s2_comment_form -->');

        ($hook = s2_hook('idx_template_pre_replace')) ? eval($hook) : null;

        // Replacing placeholders and calculating hash for ETag header
        foreach ($replace as $what => $to) {
            if (defined('S2_DEBUG_VIEW') && $to && !in_array($what, array('<!-- s2_head_title -->', '<!-- s2_navigation_link -->', '<!-- s2_rss_link -->', '<!-- s2_meta -->', '<!-- s2_styles -->'))) {

                $title = '<pre style="color: red; font-size: 12px; opacity: 0.6; margin: 0 -100% 0 0; width: 100%; text-align: center; line-height: 1; position: relative; float: left; z-index: 1000; pointer-events: none;">' . s2_htmlencode($what) . '</pre>';
                $to    = '<div style="border: 1px solid rgba(255, 0, 0, 0.4); margin: 1px;">' .
                    $title . $to .
                    '</div>';
            }

            if (!\in_array($what, $etag_skip, true)) {
                $etag .= md5($to);
            }

            $template = str_replace($what, $to, $template);
        }

        ($hook = s2_hook('idx_template_after_replace')) ? eval($hook) : null;

        // Execution time
        if (defined('S2_DEBUG') || defined('S2_SHOW_TIME')) {
            $time_placeholder = 't = ' . \Lang::number_format(microtime(true) - $s2_start, true, 3) . '; q = ' . ($pdo ? (isset($pdoLogs) ? \count($pdoLogs) : $pdo->getQueryCount()) : 0);
            $template         = str_replace('<!-- s2_querytime -->', $time_placeholder, $template);
            $etag             .= md5($time_placeholder);
        }

        $response = new Response($template);
        $response->setEtag(md5($etag));

        return $response;
    }

    protected function simplePlaceholders(): array
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

    public function inTemplate(string $placeholder): bool
    {
        return str_contains($this->template, $placeholder);
    }
}
