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
use S2\Cms\Pdo\DbLayer;
use S2\Cms\Template\HtmlTemplateProvider;
use S2\Cms\Template\Viewer;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class PageTags implements ControllerInterface
{
    public function __construct(
        private DbLayer              $dbLayer,
        private HtmlTemplateProvider $htmlTemplateProvider,
        private Viewer               $viewer
    ) {
    }

    public function handle(Request $request): Response
    {
        if ($request->attributes->get('slash') !== '/') {
            return new RedirectResponse(s2_link($request->getPathInfo() . '/'), Response::HTTP_MOVED_PERMANENTLY);
        }

        $template = $this->htmlTemplateProvider->getTemplate('site.php');

        $template
            ->addBreadCrumb(\Model::main_page_title(), s2_link('/'))
            ->addBreadCrumb(\Lang::get('Tags'))
            ->putInPlaceholder('title', \Lang::get('Tags'))
            ->putInPlaceholder('date', '')
            ->putInPlaceholder('text', $this->viewer->render('tags_list', [
                'tags' => \Placeholder::tags_list()
            ]))
        ;

        return $template->toHttpResponse();
    }
}
