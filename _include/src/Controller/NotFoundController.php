<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license MIT
 * @package S2
 */

declare(strict_types=1);

namespace S2\Cms\Controller;

use S2\Cms\Framework\ControllerInterface;
use S2\Cms\Model\ArticleProvider;
use S2\Cms\Model\UrlBuilder;
use S2\Cms\Template\HtmlTemplateProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

readonly class NotFoundController implements ControllerInterface
{
    public function __construct(
        private ArticleProvider      $articleProvider,
        private UrlBuilder           $urlBuilder,
        private HtmlTemplateProvider $htmlTemplateProvider,
    ) {
    }

    public function handle(Request $request): Response
    {
        $template = $this->htmlTemplateProvider->getTemplate('error404.php');

        $template
            ->markAsNotFound()
            ->putInPlaceholder('head_title', \Lang::get('Error 404'))
            ->putInPlaceholder('title', '<h1 class="error404-header">' . \Lang::get('Error 404') . '</h1>')
            ->putInPlaceholder('text', sprintf(\Lang::get('Error 404 text'), $this->urlBuilder->link('/')))
            ->addBreadCrumb($this->articleProvider->mainPageTitle(), $this->urlBuilder->link('/'))
        ;

        return $template->toHttpResponse();
    }
}
