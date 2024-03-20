<?php
/**
 * Provides the content of templates.
 * Replaces styles and scripts placeholders.
 *
 * @copyright 2009-2024 Roman Parpalak
 * @license MIT
 * @package S2
 */

declare(strict_types=1);

namespace S2\Cms\Template;

use S2\Cms\Asset\AssetMerge;
use S2\Cms\Asset\AssetPack;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class HtmlTemplateProvider
{
    public function __construct(
        private readonly RequestStack             $requestStack,
        private readonly Viewer                   $viewer,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly bool                     $debug,
        private readonly bool                     $debugView,
        private readonly string                   $rootDir,
        private readonly string                   $cacheDir,
        private readonly string                   $styleName,
    ) {
    }

    public function getTemplate(string $templateId): HtmlTemplate
    {
        $templateContent = $this->getRawTemplateContent($templateId);
        $templateContent = $this->replaceCurrentLinks($templateContent);

        $htmlTemplate = new HtmlTemplate($templateContent, $this->dispatcher, $this->viewer, $this->debugView);

        $this->dispatcher->dispatch(new TemplateEvent($htmlTemplate), TemplateEvent::EVENT_CREATED);

        return $htmlTemplate;
    }


    /**
     * Searches for a template file (in the style or 'template' directory)
     */
    public function getRawTemplateContent(string $templateId): string
    {
        $defaultPath = $this->rootDir . '_include/templates/';

        $path            = null;
        $cleanTemplateId = preg_replace('#[^0-9a-zA-Z._\-]#', '', $templateId);

        $buildEvent = new TemplateBuildEvent($this->styleName, $cleanTemplateId, $path);
        $this->dispatcher->dispatch($buildEvent, TemplateBuildEvent::EVENT_START);

        if ($path === null) { // Can be modified via event
            $templatePathInStyles = $this->rootDir . '_styles/' . $this->styleName . '/templates/' . $cleanTemplateId;
            if (file_exists($templatePathInStyles)) {
                $path = $templatePathInStyles;
            } elseif (file_exists($defaultPath . $cleanTemplateId)) {
                $path = $defaultPath . $cleanTemplateId;
            } else {
                throw new \RuntimeException(sprintf(\Lang::get('Template not found'), $defaultPath . $cleanTemplateId));
            }
        }

        ob_start();
        include $path;
        $template = ob_get_clean();

        $styleFilename = '_styles/' . $this->styleName . '/' . $this->styleName . '.php';
        $assetPack     = require $this->rootDir . $styleFilename;

        if (!($assetPack instanceof AssetPack)) {
            throw new \LogicException(sprintf(
                'Style "%s" is broken (file "%s" must return an AssetPack object). Choose another style.',
                $this->styleName,
                $styleFilename
            ));
        }

        $this->dispatcher->dispatch(new TemplateAssetEvent($assetPack));

        $styles  = $assetPack->getStyles(
            S2_PATH . '/_styles/' . $this->styleName . '/',
            new AssetMerge($this->cacheDir, '/_cache/', $this->styleName . '_styles', AssetMerge::TYPE_CSS, $this->debug)
        );
        $scripts = $assetPack->getScripts(
            S2_PATH . '/_styles/' . $this->styleName . '/',
            new AssetMerge($this->cacheDir, '/_cache/', $this->styleName . '_scripts', AssetMerge::TYPE_JS, $this->debug)
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
            static function ($matches) use ($requestUri) {
                $real_request_uri = s2_link($requestUri);

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
}
