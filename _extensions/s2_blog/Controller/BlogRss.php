<?php
/**
 * RSS feed for blog.
 *
 * @copyright 2007-2024 Roman Parpalak
 * @license MIT
 * @package s2_blog
 */

declare(strict_types=1);

namespace s2_extensions\s2_blog\Controller;

use Lang;
use S2\Cms\Controller\Rss;
use S2\Cms\Template\Viewer;
use s2_extensions\s2_blog\Lib;


readonly class BlogRss extends Rss
{
    public function __construct(
        Viewer         $viewer,
        string         $baseUrl,
        string         $webmaster,
        string         $siteName,
        private string $blogUrl,
        private string $blogTitle,
    )
    {
        Lang::load('s2_blog', function () {
            if (file_exists(__DIR__ . '/../lang/' . S2_LANGUAGE . '.php'))
                return require __DIR__ . '/../lang/' . S2_LANGUAGE . '.php';
            else
                return require __DIR__ . '/../lang/English.php';
        });

        parent::__construct($viewer, $baseUrl, $webmaster, $siteName);
    }

    protected function content(): array
    {
        $posts  = Lib::last_posts_array();
        $viewer = $this->viewer;
        $items  = [];
        foreach ($posts as $post) {
            $items[] = [
                'title'       => $post['title'],
                'text'        => $post['text'] .
                    (empty($post['see_also']) ? '' : $viewer->render('see_also', [
                        'see_also' => $post['see_also']
                    ], 's2_blog')) .
                    (empty($post['tags']) ? '' : $viewer->render('tags', [
                        'title' => Lang::get('Tags'),
                        'tags'  => $post['tags'],
                    ], 's2_blog')),
                'time'        => $post['create_time'],
                'modify_time' => $post['modify_time'],
                'rel_path'    => str_replace(urlencode('/'), '/', urlencode($this->blogUrl)) . date('/Y/m/d/', $post['create_time']) . urlencode($post['url']),
                'author'      => $post['author'],
            ];
        }

        return $items;
    }

    protected function title(): string
    {
        return $this->blogTitle;
    }

    protected function link(): string
    {
        return str_replace(urlencode('/'), '/', urlencode($this->blogUrl)) . '/';
    }

    protected function description(): string
    {
        return sprintf(Lang::get('RSS description', 's2_blog'), $this->blogTitle);
    }
}
