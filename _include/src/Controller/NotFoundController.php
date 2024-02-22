<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license MIT
 * @package S2
 */

declare(strict_types=1);

namespace S2\Cms\Controller;

use S2\Cms\Template\HtmlTemplateProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

readonly class NotFoundController implements ControllerInterface
{
    public function __construct(private HtmlTemplateProvider $htmlTemplateProvider)
    {
    }

    public function handle(Request $request): Response
    {
        $template = $this->htmlTemplateProvider->getTemplate('error404.php');

        $template
            ->putInPlaceholder('head_title', \Lang::get('Error 404'))
            ->putInPlaceholder('title', '<h1 class="error404-header">' . \Lang::get('Error 404') . '</h1>')
            ->putInPlaceholder('text', sprintf(\Lang::get('Error 404 text'), s2_link('/')))
            ->addBreadCrumb(\Model::main_page_title(), s2_link('/'))
        ;

        return $template->toHttpResponse()->setStatusCode(Response::HTTP_NOT_FOUND);
    }
}
