<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license   http://opensource.org/licenses/MIT MIT
 * @package   s2_blog
 */

declare(strict_types=1);

namespace s2_extensions\s2_blog\Admin;

use S2\Cms\Translation\TranslationProviderInterface;

class TranslationProvider implements TranslationProviderInterface
{
    public function getTranslations(string $language, string $locale): array
    {
        return match ($locale) {
            'ru' => [
                'Blog config'           => 'Блог',
                'S2_BLOG_TITLE'         => 'Название блога',
                'S2_BLOG_TITLE_help'    => 'Выводится в теге &lt;title&gt;, доступно в шаблонах.',
                'S2_BLOG_URL'           => 'URL блога',
                'S2_BLOG_URL_help'      => 'Префикс URL блога (слеш в&nbsp;начале нужен, в&nbsp;конце&nbsp;— нет). Например, «/blog». Можно оставить пустым, тогда блог будет сразу на главной.',
                'Published in the blog' => 'В блоге опубликовано',
                'Posts num'             => '{{ posts }} пост|{{ posts }} поста|{{ posts }} постов',
                'Blog new comments'     => 'Непроверенные комментарии в блоге',

                // Tags
                'Important tag'         => 'Важное',
                'Important tag info'    => 'Важные теги входят в навигационные ссылки блога',
                'Used in posts'         => 'Используется в постах',
                'Used in posts info'    => 'Количество постов, использующих этот тег, и ссылки на них.',

                'Label'      => 'Метка',
                'Label help' => 'К этой записи будут добавлены ссылки «см. также» на другие записи с такой же меткой.',
            ],
            'en' => [
                'Blog config'           => 'Blog',
                'S2_BLOG_TITLE'         => 'Blog title',
                'S2_BLOG_TITLE_help'    => 'Used in &lt;title&gt; tag, available in templates.',
                'S2_BLOG_URL'           => 'Blog URL',
                'S2_BLOG_URL_help'      => 'Blog URL prefix (it should contain a leading slash and no trailing slash). E.g. “/blog”. Leave it blank to put the blog on the site main page.',
                'Published in the blog' => 'Published in the blog',
                'Posts num'             => '{{ posts }} post|{{ posts }} posts',
                'Blog new comments'     => 'Unverified comments in the blog',

                // Tags
                'Important tag'         => 'Important',
                'Important tag info'    => 'Important tags are used in the blog navigation menu',
                'Used in posts'         => 'Used in posts',
                'Used in posts info'    => 'The number of posts using this tag and links to them.',

                'Label'      => 'Label',
                'Label help' => '“See also” links (to the posts that have the same label) will be appended to this post.',
            ],
        };
    }
}
