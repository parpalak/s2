<?php
/**
 * Hook fn_s2_parse_page_url_end
 *
 * @copyright 2023-2024 Roman Parpalak
 * @license MIT
 * @package s2_blog
 *
 * @var int                           $articleId
 * @var \S2\Cms\Controller\PageCommon $this
 * @var \S2\Cms\Template\HtmlTemplate $template
 */

if (!defined('S2_ROOT')) {
    die;
}

if ($template->hasPlaceholder('<!-- s2_blog_tags -->')) {
    Lang::load('s2_blog', static function () {
        if (file_exists(S2_ROOT . '/_extensions/s2_blog' . '/lang/' . S2_LANGUAGE . '.php')) {
            return require S2_ROOT . '/_extensions/s2_blog' . '/lang/' . S2_LANGUAGE . '.php';
        }

        return require S2_ROOT . '/_extensions/s2_blog' . '/lang/English.php';
    });

    $s2_blog_tags = s2_extensions\s2_blog\Placeholder::blog_tags($articleId);
    $template->registerPlaceholder('<!-- s2_blog_tags -->', empty($s2_blog_tags) ? '' : $this->viewer->render('menu_block', [
        'title' => Lang::get('See in blog', 's2_blog'),
        'menu'  => $s2_blog_tags,
        'class' => 's2_blog_tags',
    ]));
}
