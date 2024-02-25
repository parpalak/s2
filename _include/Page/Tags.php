<?php
/**
 * Displays tags list page.
 *
 * @copyright 2007-2024 Roman Parpalak
 * @license MIT
 * @package S2
 */

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class Page_Tags extends Page_HTML implements Page_Routable
{
    public function handle(Request $request): ?Response
    {
        if ($request->attributes->get('slash') !== '/') {
            return new RedirectResponse(s2_link($request->getPathInfo() . '/'), Response::HTTP_MOVED_PERMANENTLY);
        }

        $this->page = [
            'path'  => [
                [
                    'title' => Model::main_page_title(),
                    'link'  => s2_link('/'),
                ],
                [
                    'title' => Lang::get('Tags'),
                ],
            ],
            'title' => Lang::get('Tags'),
            'date'  => '',
            'text'  => $this->renderPartial('tags_list', ['tags' => Placeholder::tags_list()]),
        ];

        return parent::handle($request);
    }
}
