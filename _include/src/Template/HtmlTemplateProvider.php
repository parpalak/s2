<?php
/**
 * Provides the content of templates.
 * Replaces styles and scripts placeholders.
 *
 * @copyright 2009-2025 Roman Parpalak
 * @license   MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Template;

use S2\Cms\Asset\AssetMerge;
use S2\Cms\Asset\AssetPack;
use S2\Cms\Config\DynamicConfigProvider;
use S2\Cms\HttpClient\HttpClient;
use S2\Cms\Model\UrlBuilder;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class HtmlTemplateProvider
{
    private string $styleName;

    public function __construct(
        private RequestStack             $requestStack,
        private UrlBuilder               $urlBuilder,
        private TranslatorInterface      $translator,
        private Viewer                   $viewer,
        private HttpClient               $httpClient,
        private EventDispatcherInterface $dispatcher,
        private DynamicConfigProvider    $dynamicConfigProvider,
        private bool                     $debug,
        private bool                     $debugView,
        private string                   $rootDir,
        private string                   $cacheDir,
        private bool                     $disableCache,
        private string                   $basePath,
    ) {
        $this->styleName = $this->dynamicConfigProvider->get('S2_STYLE');
    }

    public function getTemplate(string $templateId, ?string $extraDir = null): HtmlTemplate
    {
        $templateContent = $this->getRawTemplateContent($templateId, $extraDir);
        $templateContent = $this->replaceCurrentLinks($templateContent);

        $htmlTemplate = new HtmlTemplate(
            $templateContent,
            $this->requestStack,
            $this->urlBuilder,
            $this->translator,
            $this->viewer,
            $this->dispatcher,
            $this->dynamicConfigProvider,
            $this->debugView,
        );

        $this->dispatcher->dispatch(new TemplateEvent($htmlTemplate), TemplateEvent::EVENT_CREATED);

        return $htmlTemplate;
    }


    /**
     * Searches for a template file (in the style or 'template' directory)
     */
    public function getRawTemplateContent(string $templateId, ?string $extraDir): string
    {
        $path            = null;
        $cleanTemplateId = preg_replace('#[^0-9a-zA-Z._\-]#', '', $templateId);

        $buildEvent = new TemplateBuildEvent($this->styleName, $cleanTemplateId, $path);
        $this->dispatcher->dispatch($buildEvent, TemplateBuildEvent::EVENT_START);

        if ($path === null) { // Can be not null via event
            $path = $this->getTemplateFullFilename($extraDir, $cleanTemplateId);
        }

        ob_start();
        include $path;
        $template = ob_get_clean();

        $styleFilename = '_styles/' . $this->styleName . '/' . $this->styleName . '.php';
        $assetPack     = require $this->rootDir . $styleFilename;

        if (!($assetPack instanceof AssetPack)) {
            throw new \LogicException(\sprintf(
                'Style "%s" is broken (file "%s" must return an AssetPack object). Choose another style.',
                $this->styleName,
                $styleFilename
            ));
        }

        $this->dispatcher->dispatch(new TemplateAssetEvent($assetPack));

        $styles  = $assetPack->getStyles(
            $this->basePath . '/_styles/' . $this->styleName . '/',
            $this->disableCache ? null : new AssetMerge(
                $this->httpClient,
                $this->cacheDir,
                $this->basePath . '/_cache/',
                $this->styleName . '_styles',
                AssetMerge::TYPE_CSS,
                $this->debug
            )
        );
        $scripts = $assetPack->getScripts(
            $this->basePath . '/_styles/' . $this->styleName . '/',
            $this->disableCache ? null : new AssetMerge(
                $this->httpClient,
                $this->cacheDir,
                $this->basePath . '/_cache/',
                $this->styleName . '_scripts',
                AssetMerge::TYPE_JS,
                $this->debug
            )
        );

        $template = str_replace(['<!-- s2_styles -->', '<!-- s2_scripts -->'], [$styles, $scripts], $template);

        $this->dispatcher->dispatch($buildEvent, TemplateBuildEvent::EVENT_END);

        return $template;
    }

    private function replaceCurrentLinks(string $templateContent): string
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null || !str_contains($templateContent, '</a>')) {
            return $templateContent;
        }

        $requestUri = $request->getPathInfo();

        $templateContent = preg_replace_callback('#<a href="([^"]*)">([^<]*)</a>#',
            function ($matches) use ($requestUri) {
                $real_request_uri = $this->urlBuilder->link($requestUri);

                [, $url, $text] = $matches;

                if ($url === $real_request_uri) {
                    return '<span>' . $text . '</span>';
                }

                if ($url !== '' && str_starts_with($real_request_uri, $url)) {
                    return '<a class="current" href="' . $url . '">' . $text . '</a>';
                }

                return '<a href="' . $url . '">' . $text . '</a>';
            },
            $templateContent
        );

        return $templateContent;
    }

    private function getTemplateFullFilename(?string $extraDir, string $cleanTemplateId): string
    {
        $pathInStyles = $this->rootDir . '_styles/' . $this->styleName . '/templates/' . $cleanTemplateId;
        if (file_exists($pathInStyles)) {
            return $pathInStyles;
        }

        if ($extraDir !== null) {
            $path = $this->rootDir . '_extensions/' . $extraDir . '/templates/' . $cleanTemplateId;
        } else {
            $path = $this->rootDir . '_include/templates/' . $cleanTemplateId;
        }

        if (file_exists($path)) {
            return $path;
        }

        throw new \RuntimeException(\sprintf($this->translator->trans('Template not found'), $path));
    }
}
