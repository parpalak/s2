<?php
/**
 * Provides the content of templates.
 * Replaces styles and scripts placeholders.
 *
 * @copyright 2009-2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Template;

use S2\Cms\Asset\AssetMerge;
use S2\Cms\Asset\AssetMergeFactory;
use S2\Cms\Asset\AssetPack;
use S2\Cms\Config\DynamicConfigProvider;
use S2\Cms\Framework\StatefulServiceInterface;
use S2\Cms\Model\UrlBuilder;
use S2\Cms\Pdo\DbLayerException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class HtmlTemplateProvider implements StatefulServiceInterface
{
    private ?string $styleName = null;

    /** @noinspection PhpPropertyOnlyWrittenInspection */
    public function __construct(
        private readonly RequestStack             $requestStack,
        private readonly UrlBuilder               $urlBuilder,
        private readonly TranslatorInterface      $translator,
        private readonly Viewer                   $viewer,
        private readonly AssetMergeFactory        $assetMergeFactory,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly DynamicConfigProvider    $dynamicConfigProvider,
        private readonly bool                     $debugView,
        private readonly string                   $rootDir,
        private readonly string                   $basePath,
        private readonly string                   $baseUrl, // to be used in templates
        private readonly ?string                  $canonicalUrl,
    ) {
    }

    /**
     * @throws DbLayerException
     */
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
            $this->canonicalUrl,
        );

        $this->dispatcher->dispatch(new TemplateEvent($htmlTemplate), TemplateEvent::EVENT_CREATED);

        return $htmlTemplate;
    }


    /**
     * Searches for a template file (in the style or 'template' directory)
     * @throws DbLayerException
     */
    public function getRawTemplateContent(string $templateId, ?string $extraDir): string
    {
        $path            = null;
        $cleanTemplateId = preg_replace('#[^0-9a-zA-Z._\-]#', '', $templateId);

        $buildEvent = new TemplateBuildEvent($this->getStyleName(), $cleanTemplateId, $path);
        $this->dispatcher->dispatch($buildEvent, TemplateBuildEvent::EVENT_START);

        if ($path === null) { // Can be not null via event
            $path = $this->getTemplateFullFilename($extraDir, $cleanTemplateId);
        }

        ob_start();
        include $path;
        $template = ob_get_clean();

        $styleFilename = '_styles/' . $this->getStyleName() . '/' . $this->getStyleName() . '.php';
        $assetPack     = require $this->rootDir . $styleFilename;

        if (!($assetPack instanceof AssetPack)) {
            throw new \LogicException(\sprintf(
                'Style "%s" is broken (file "%s" must return an AssetPack object). Choose another style.',
                $this->getStyleName(),
                $styleFilename
            ));
        }

        $this->dispatcher->dispatch(new TemplateAssetEvent($assetPack));

        $styles  = $assetPack->getStyles(
            $this->basePath . '/_styles/' . $this->getStyleName() . '/',
            $this->assetMergeFactory->create($this->getStyleName() . '_styles', AssetMerge::TYPE_CSS)
        );
        $scripts = $assetPack->getScripts(
            $this->basePath . '/_styles/' . $this->getStyleName() . '/',
            $this->assetMergeFactory->create($this->getStyleName() . '_scripts', AssetMerge::TYPE_JS)
        );

        $template = str_replace(['<!-- s2_styles -->', '<!-- s2_scripts -->'], [$styles, $scripts], $template);

        $this->dispatcher->dispatch($buildEvent, TemplateBuildEvent::EVENT_END);

        return $template;
    }

    /**
     * {@inheritdoc}
     */
    public function clearState(): void
    {
        $this->styleName = null;
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

    /**
     * @throws DbLayerException
     */
    private function getTemplateFullFilename(?string $extraDir, string $cleanTemplateId): string
    {
        $pathInStyles = $this->rootDir . '_styles/' . $this->getStyleName() . '/templates/' . $cleanTemplateId;
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

    /**
     * @throws DbLayerException
     */
    private function getStyleName(): string
    {
        return $this->styleName ?? $this->styleName = $this->dynamicConfigProvider->get('S2_STYLE');
    }
}
