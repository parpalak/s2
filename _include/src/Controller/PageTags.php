<?php
/**
 * Displays tags list page.
 *
 * @copyright 2007-2024 Roman Parpalak
 * @license MIT
 * @package S2
 */

declare(strict_types=1);

namespace S2\Cms\Controller;

use S2\Cms\Framework\ControllerInterface;
use S2\Cms\Model\ArticleProvider;
use S2\Cms\Model\TagsProvider;
use S2\Cms\Model\UrlBuilder;
use S2\Cms\Template\HtmlTemplateProvider;
use S2\Cms\Template\Viewer;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

readonly class PageTags implements ControllerInterface
{
    public function __construct(
        private TagsProvider         $tagsProvider,
        private ArticleProvider      $articleProvider,
        private UrlBuilder           $urlBuilder,
        private HtmlTemplateProvider $htmlTemplateProvider,
        private Viewer               $viewer
    ) {
    }

    public function handle(Request $request): Response
    {
        if ($request->attributes->get('slash') !== '/') {
            return new RedirectResponse($this->urlBuilder->link($request->getPathInfo() . '/'), Response::HTTP_MOVED_PERMANENTLY);
        }

        $template = $this->htmlTemplateProvider->getTemplate('site.php');

        $template
            ->addBreadCrumb($this->articleProvider->mainPageTitle(), $this->urlBuilder->link('/'))
            ->addBreadCrumb(\Lang::get('Tags'))
            ->putInPlaceholder('title', \Lang::get('Tags'))
            ->putInPlaceholder('date', '')
            ->putInPlaceholder('text', $this->viewer->render('tags_list', [
                'tags' => $this->tagsProvider->tagsList(),
            ]))
        ;

        return $template->toHttpResponse();
    }
}
