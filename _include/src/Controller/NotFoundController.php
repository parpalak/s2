<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license MIT
 * @package S2
 */

declare(strict_types=1);

namespace S2\Cms\Controller;

use S2\Cms\Template\HtmlTemplateProvider;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

readonly class NotFoundController implements ControllerInterface
{
    public function __construct(private HtmlTemplateProvider $htmlTemplateProvider, private array $redirectMap)
    {
    }

    protected function checkRedirect(Request $request): ?RedirectResponse
    {
        if (empty($this->redirectMap)) {
            return null;
        }

        $requestUri = $request->getPathInfo();
        $newUrl     = preg_replace(array_keys($this->redirectMap), array_values($this->redirectMap), $requestUri);
        if ($newUrl === $requestUri) {
            return null;
        }

        $url = (str_starts_with($newUrl, 'http://') || str_starts_with($newUrl, 'https://')) ? $newUrl : s2_link($newUrl);

        return new RedirectResponse($url, Response::HTTP_MOVED_PERMANENTLY);
    }

    public function handle(Request $request): Response
    {
        if (null !== ($response = $this->checkRedirect($request))) {
            return $response;
        }

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
