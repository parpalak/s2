<?php
/**
 * Displays tags list page.
 *
 * @copyright 2007-2024 Roman Parpalak
 * @license MIT
 * @package S2
 */

use Symfony\Component\HttpFoundation\Request;

class Page_Tags extends Page_HTML implements Page_Routable
{
    public function render(Request $request): void
    {
        if ($request->attributes->get('slash') !== '/') {
            s2_permanent_redirect($request->getPathInfo() . '/');
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

        parent::render($request);
    }
}
