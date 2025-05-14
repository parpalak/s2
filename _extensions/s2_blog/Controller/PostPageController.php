<?php
/**
 * Single blog post.
 *
 * @copyright 2007-2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   s2_blog
 */

namespace s2_extensions\s2_blog\Controller;

use S2\Cms\Model\ArticleProvider;
use S2\Cms\Model\UrlBuilder;
use S2\Cms\Pdo\DbLayer;
use S2\Cms\Template\HtmlTemplate;
use S2\Cms\Template\HtmlTemplateProvider;
use S2\Cms\Template\Viewer;
use S2\Rose\Entity\ExternalId;
use s2_extensions\s2_blog\BlogUrlBuilder;
use s2_extensions\s2_blog\CalendarBuilder;
use s2_extensions\s2_blog\Model\PostProvider;
use s2_extensions\s2_search\Service\RecommendationProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

class PostPageController extends BlogController
{
    public function __construct(
        DbLayer                                  $dbLayer,
        CalendarBuilder                          $calendarBuilder,
        BlogUrlBuilder                           $blogUrlBuilder,
        ArticleProvider                          $articleProvider,
        PostProvider                             $postProvider,
        UrlBuilder                               $urlBuilder,
        private readonly ?RecommendationProvider $recommendationProvider,
        TranslatorInterface                      $translator,
        HtmlTemplateProvider                     $templateProvider,
        Viewer                                   $viewer,
        string                                   $blogTitle,
        bool                                     $showComments,
        bool                                     $enabledComments,
    ) {
        parent::__construct(
            $dbLayer,
            $calendarBuilder,
            $blogUrlBuilder,
            $articleProvider,
            $postProvider,
            $urlBuilder,
            $translator,
            $templateProvider,
            $viewer,
            $blogTitle,
            $showComments,
            $enabledComments
        );
    }

    public function body(Request $request, HtmlTemplate $template): ?Response
    {
        $year  = (int)($textYear = $request->attributes->get('year'));
        $month = (int)($textMonth = $request->attributes->get('month')); // Note: "01" is not parsed with getInt() correctly
        $day   = (int)($textDay = $request->attributes->get('day'));
        $url   = $request->attributes->get('url');

        if ($template->hasPlaceholder('<!-- s2_blog_calendar -->')) {
            $template->registerPlaceholder('<!-- s2_blog_calendar -->', $this->calendarBuilder->calendar($year, $month, $day, $url));
        }

        $template->putInPlaceholder('title', '');

        if (($result = $this->get_post($request, $template, $year, $month, $day, $url)) !== null) {
            return $result;
        }

        $template->addBreadCrumb($this->articleProvider->mainPageTitle(), $this->urlBuilder->link('/'));
        if (!$this->blogUrlBuilder->blogIsOnTheSiteRoot()) {
            $template->addBreadCrumb($this->translator->trans('Blog'), $this->blogUrlBuilder->main());
        }
        $template
            ->addBreadCrumb($textYear, $this->blogUrlBuilder->year($year))
            ->addBreadCrumb($textMonth, $this->blogUrlBuilder->month($year, $month))
            ->addBreadCrumb($textDay, $this->blogUrlBuilder->day($year, $month, $day))
        ;

        return null;
    }

    private function get_post(Request $request, HtmlTemplate $template, int $year, int $month, int $day, string $url): ?Response
    {
        $start_time = mktime(0, 0, 0, $month, $day, $year);
        $end_time   = $start_time + 86400;

        $template->setLink('up', $this->blogUrlBuilder->day($year, $month, $day));

        $sub_query      = [
            'SELECT' => 'u.name',
            'FROM'   => 'users AS u',
            'WHERE'  => 'u.id = p.user_id',
        ];
        $raw_query_user = $this->dbLayer->build($sub_query);

        $query  = [
            'SELECT' => 'create_time, title, text, id, commented, label, favorite, (' . $raw_query_user . ') AS author, url',
            'FROM'   => 's2_blog_posts AS p',
            'WHERE'  => 'create_time < ' . $end_time . ' AND create_time >= ' . $start_time . ' AND url = \'' . $this->dbLayer->escape($url) . '\' AND published = 1',
        ];
        $result = $this->dbLayer->buildAndQuery($query);

        if (!$row = $this->dbLayer->fetchAssoc($result)) {
            $template
                ->putInPlaceholder('head_title', $this->translator->trans('Not found'))
                ->putInPlaceholder('text', '<p>' . $this->translator->trans('Not found') . '</p>')
            ;

            return $template->toHttpResponse()->setStatusCode(Response::HTTP_NOT_FOUND);
        }

        $post_id = $row['id'];
        $label   = $row['label'];

        $template->putInPlaceholder('canonical_path', $this->blogUrlBuilder->postFromTimestamp((int)$row['create_time'], $row['url']));

        $is_back_forward = $template->hasPlaceholder('<!-- s2_blog_back_forward -->');

        $queries = [];
        if ($label) {
            // Getting posts that have the same label
            $query     = [
                'SELECT'   => 'title, create_time, url, "label" AS type',
                'FROM'     => 's2_blog_posts',
                'WHERE'    => 'label = \'' . $this->dbLayer->escape($label) . '\' AND id <> ' . $post_id . ' AND published = 1',
                'ORDER BY' => 'create_time DESC',
            ];
            $queries[] = $this->dbLayer->build($query);
        }

        if ($is_back_forward) {
            $query     = [
                'SELECT'   => 'title, create_time, url, "next" AS type',
                'FROM'     => 's2_blog_posts',
                'WHERE'    => ' create_time > ' . (int)$row['create_time'] . ' AND published = 1',
                'ORDER BY' => 'create_time ASC',
                'LIMIT'    => '1',
            ];
            $queries[] = $this->dbLayer->build($query);

            $query     = [
                'SELECT'   => 'title, create_time, url, "prev" AS type',
                'FROM'     => 's2_blog_posts',
                'WHERE'    => ' create_time < ' . (int)$row['create_time'] . ' AND published = 1',
                'ORDER BY' => 'create_time DESC',
                'LIMIT'    => '1',
            ];
            $queries[] = $this->dbLayer->build($query);
        }

        $result = !empty($queries) ? $this->dbLayer->query('(' . implode(') UNION (', $queries) . ')') : null;

        $back_forward = [];
        while ($result && $row1 = $this->dbLayer->fetchAssoc($result)) {
            $post_info = [
                'title' => $row1['title'],
                'link'  => $this->blogUrlBuilder->postFromTimestamp((int)$row1['create_time'], $row1['url']),
            ];

            if ($row1['type'] === 'label') {
                $row['see_also'][] = $post_info;
            } elseif ($row1['type'] === 'next') {
                $template->setLink('next', $post_info['link']);
                $back_forward['forward'] = $post_info;
            } elseif ($row1['type'] === 'prev') {
                $template->setLink('prev', $post_info['link']);
                $back_forward['back'] = $post_info;
            }
        }

        if (!empty($back_forward)) {
            $template->registerPlaceholder('<!-- s2_blog_back_forward -->', $this->viewer->render('back_forward_post', $back_forward, 's2_blog'));
        }

        // Getting tags
        $query  = [
            'SELECT'   => 'name, url',
            'FROM'     => 'tags AS t',
            'JOINS'    => [
                [
                    'INNER JOIN' => 's2_blog_post_tag AS pt',
                    'ON'         => 'pt.tag_id = t.id',
                ],
            ],
            'WHERE'    => 'post_id = ' . $post_id,
            'ORDER BY' => 'pt.id',
        ];
        $result = $this->dbLayer->buildAndQuery($query);

        $tags = [];
        while ($tag = $this->dbLayer->fetchAssoc($result)) {
            $tags[] = [
                'title' => $tag['name'],
                'link'  => $this->blogUrlBuilder->tag($tag['url']),
            ];
        }

        $template->putInPlaceholder('commented', $row['commented']);
        if ($row['commented'] && $this->showComments && $template->hasPlaceholder('<!-- s2_comments -->')) {
            $template->putInPlaceholder('comments', $this->get_comments($post_id));
        }

        $row['time']             = $this->viewer->dateAndTime($row['create_time']);
        $row['commented']        = 0; // for template
        $row['tags']             = $tags;
        $row['favoritePostsUrl'] = $this->blogUrlBuilder->favorite();
        $row['showComments']     = $this->showComments;
        $row['enabledComments']  = $this->enabledComments;

        $template
            ->putInPlaceholder('meta_description', self::extractMetaDescriptions($row['text']))
            ->putInPlaceholder('text', $this->viewer->render('post', $row, 's2_blog'))
            ->putInPlaceholder('id', md5('s2_blog_post_' . $post_id))
            ->putInPlaceholder('head_title', s2_htmlencode($row['title']))
        ;

        if ($this->recommendationProvider !== null && $template->hasPlaceholder('<!-- s2_recommendations -->')) {
            $request_uri = $request->getPathInfo();
            [$recommendations, $log, $rawRecommendations] = $this->recommendationProvider->getRecommendations($request_uri, new ExternalId('s2_blog_' . $post_id));
            $template->putInPlaceholder('recommendations', $this->viewer->render('recommendations', [
                'raw'     => $rawRecommendations,
                'content' => $recommendations,
                'log'     => $log,
            ], 's2_search'));
        }

        return null;
    }

    private function get_comments(int $id): string
    {
        $comments = '';

        $query  = [
            'SELECT'   => 'nick, time, email, show_email, good, text',
            'FROM'     => 's2_blog_comments',
            'WHERE'    => 'post_id = ' . $id . ' AND shown = 1',
            'ORDER BY' => 'time',
        ];
        $result = $this->dbLayer->buildAndQuery($query);

        for ($i = 1; $row = $this->dbLayer->fetchAssoc($result); $i++) {
            $row['i'] = $i;
            $comments .= $this->viewer->render('comment', $row);
        }

        return $comments ? $this->viewer->render('comments', ['comments' => $comments]) : '';
    }

    private static function extractMetaDescriptions(string $text): string
    {
        $replace_what = ["\r", '&nbsp;', '&mdash;', '&ndash;', '&laquo;', '&raquo;'];
        $replace_to   = ['', ' ', '—', '–', '«', '»',];
        foreach (['<br>', '<br />', '<h1>', '<h2>', '<h3>', '<h4>', '<p>', '<pre>', '<blockquote>', '<li>'] as $tag) {
            $replace_what[] = $tag;
            $replace_to[]   = $tag . "\r";
        }
        $text = str_replace($replace_what, $replace_to, $text);
        $text = strip_tags($text);
        $text = preg_replace('#(?<=[.?!;])[ \n\t]+#S', "\r", $text);
        $text = trim($text);

        $start = 0;
        while (($pos = mb_strpos($text, "\r", $start)) !== false) {
            if ($pos > 160 && $start <= 160) {
                $text = mb_substr($text, 0, $start);
                break;
            }
            $start = $pos + 1;
        }

        $text = str_replace("\r", ' ', $text);

        return $text;
    }
}
