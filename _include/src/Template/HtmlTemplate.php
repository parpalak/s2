<?php
/**
 * @copyright 2009-2024 Roman Parpalak
 * @license   http://opensource.org/licenses/MIT MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Template;

use Psr\Cache\InvalidArgumentException;
use S2\Cms\Config\DynamicConfigProvider;
use S2\Cms\Model\UrlBuilder;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class HtmlTemplate
{
    protected array $page = [];
    protected array $breadCrumbs = [];
    private array $navLinks = [];
    private array $replace = [];
    private bool $notFound = false;

    public function __construct(
        private readonly string                   $template,
        private readonly RequestStack             $requestStack,
        private readonly UrlBuilder               $urlBuilder,
        private readonly TranslatorInterface      $translator,
        private readonly Viewer                   $viewer,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly DynamicConfigProvider    $dynamicConfigProvider,
        private readonly bool                     $debugView,
    ) {
    }

    public function putInPlaceholder(string $placeholder, mixed $content): static
    {
        $this->page[$placeholder] = $content;

        return $this;
    }

    public function getFromPlaceholder(string $placeholder): mixed
    {
        return $this->page[$placeholder] ?? null;
    }

    public function addBreadCrumb(string $title, ?string $link = null): static
    {
        $this->breadCrumbs[] = ['title' => $title, 'link' => $link];

        return $this;
    }

    public function toHttpResponse(): Response
    {
        $template = $this->template;

        $replace = [];

        // HTML head
        $replace['<!-- s2_head_title -->'] = empty($this->page['head_title']) ?
            (!empty($this->page['title']) ? $this->page['title'] . ' &mdash; ' : '') . $this->dynamicConfigProvider->get('S2_SITE_NAME') :
            $this->page['head_title'];

        // Meta tags processing
        $meta_tags = [
            '<meta name="Generator" content="S2" />',
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
            $this->page['rss_link'][] = '<link rel="alternate" type="application/rss+xml" title="' . $this->translator->trans('RSS link title') . '" href="' . $this->urlBuilder->link('/rss.xml') . '" />';
        }
        $replace['<!-- s2_rss_link -->'] = implode("\n", $this->page['rss_link']);

        // Content
        $replace['<!-- s2_site_title -->'] = $this->dynamicConfigProvider->get('S2_SITE_NAME');

        $link_navigation = [];
        foreach ($this->navLinks as $link_rel => $link_href) {
            $link_navigation[] = '<link rel="' . $link_rel . '" href="' . $link_href . '" />';
        }

        $replace['<!-- s2_navigation_link -->'] = implode("\n", $link_navigation);

        $replace['<!-- s2_author -->']      = !empty($this->page['author']) ? '<div class="author">' . $this->page['author'] . '</div>' : '';
        $replace['<!-- s2_title -->']       = !empty($this->page['title']) ? $this->viewer->render('title', array_intersect_key($this->page, ['title' => 1, 'favorite' => 1])) : '';
        $replace['<!-- s2_date -->']        = !empty($this->page['date']) ? '<div class="date">' . s2_date($this->page['date']) . '</div>' : '';
        $replace['<!-- s2_crumbs -->']      = \count($this->breadCrumbs) > 0 ? $this->viewer->render('breadcrumbs', ['breadcrumbs' => $this->breadCrumbs]) : '';
        $replace['<!-- s2_subarticles -->'] = $this->page['subcontent'] ?? '';

        foreach ($this->simplePlaceholders() as $placeholderName) {
            $replace['<!-- s2_' . $placeholderName . ' -->'] = $this->page[$placeholderName] ?? '';
        }

        if (!empty($this->page['commented']) && $this->dynamicConfigProvider->get('S2_ENABLED_COMMENTS') === '1') {
            $comment_array = [
                'id' => $this->page['id'] . '.' . ($this->page['class'] ?? '')
            ];

            if (!empty($this->page['comment_form']) && \is_array($this->page['comment_form'])) {
                $comment_array += $this->page['comment_form'];
            }

            $event = new TemplatePreCommentRenderEvent([$this->translator->trans('Comment syntax info')]);
            $this->eventDispatcher->dispatch($event);
            $replace['<!-- s2_comment_form -->'] = $this->viewer->render('comment_form', [
                ...$comment_array,
                'syntaxHelpItems' => $event->syntaxHelpItems,
                'action'          => $this->urlBuilder->linkToFile('/comment.php'),
            ]);
        } else {
            $replace['<!-- s2_comment_form -->'] = '';
        }

        $replace['<!-- s2_back_forward -->'] = !empty($this->page['back_forward']) ? $this->viewer->render('back_forward', ['links' => $this->page['back_forward']]) : '';

        // Footer
        $replace['<!-- s2_copyright -->'] = $this->s2_build_copyright();

        $this->eventDispatcher->dispatch(new TemplateEvent($this), TemplateEvent::EVENT_PRE_REPLACE);

        $replace = array_merge($replace, $this->replace);

        $etag = md5($template);
        // Add here placeholders to be excluded from the ETag calculation
        $etag_skip = array('<!-- s2_comment_form -->');

        // Replacing placeholders and calculating hash for ETag header
        foreach ($replace as $what => $to) {
            if ($this->debugView && $to && !in_array($what, array('<!-- s2_head_title -->', '<!-- s2_navigation_link -->', '<!-- s2_rss_link -->', '<!-- s2_meta -->', '<!-- s2_styles -->'))) {

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

        $finalReplaceEvent = new TemplateFinalReplaceEvent($template);
        $this->eventDispatcher->dispatch($finalReplaceEvent);
        $etag .= $finalReplaceEvent->getHash();

        $response = new Response($template);
        $response->setEtag(md5($etag));
        if ($this->notFound) {
            $response->setStatusCode(Response::HTTP_NOT_FOUND);
        }

        return $response;
    }

    public function hasPlaceholder(string $placeholder): bool
    {
        return str_contains($this->template, $placeholder);
    }

    public function setLink(string $rel, string $link): static
    {
        $this->navLinks[$rel] = $link;

        return $this;
    }

    /**
     * Register a new placeholder that is not known to this class.
     * Supposed to be used in extensions for their own custom placeholders.
     */
    public function registerPlaceholder(string $placeholder, string $value): static
    {
        $this->replace[$placeholder] = $value;

        return $this;
    }

    public function isNotFound(): bool
    {
        return $this->notFound;
    }

    public function markAsNotFound(): static
    {
        $this->notFound = true;

        return $this;
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

    /**
     * @throws InvalidArgumentException
     */
    private function s2_build_copyright(): string
    {
        $request_uri = $this->requestStack->getCurrentRequest()?->getPathInfo();

        $webmaster = $this->dynamicConfigProvider->get('S2_WEBMASTER');
        $email     = $this->dynamicConfigProvider->get('S2_WEBMASTER_EMAIL');
        $startYear = $this->dynamicConfigProvider->get('S2_START_YEAR');

        $author    = $webmaster ?: $this->dynamicConfigProvider->get('S2_SITE_NAME');
        $copyright = $webmaster && $email ? s2_js_mailto($author, $email) : ($request_uri !== '/' ? '<a href="' . $this->urlBuilder->link('/') . '">' . $author . '</a>' : $author);

        return ($startYear !== date('Y') ?
                sprintf($this->translator->trans('Copyright 2'), $copyright, $startYear, date('Y')) :
                sprintf($this->translator->trans('Copyright 1'), $copyright, date('Y'))) . ' ' .
            sprintf($this->translator->trans('Powered by'), '<a href="http://s2cms.ru/">S2</a>');
    }
}
