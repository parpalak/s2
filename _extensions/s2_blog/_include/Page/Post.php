<?php
/**
 * Single blog post.
 *
 * @copyright 2007-2024 Roman Parpalak
 * @license MIT
 * @package s2_blog
 */

namespace s2_extensions\s2_blog;

use Lang;
use S2\Cms\Pdo\DbLayer;
use S2\Cms\Recommendation\RecommendationProvider;
use S2\Rose\Entity\ExternalId;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class Page_Post extends Page_HTML implements \Page_Routable
{
    public function body (Request $request): ?Response
    {
        $params = $request->attributes->all();

        if ($this->inTemplate('<!-- s2_blog_calendar -->'))
            $this->page['s2_blog_calendar'] = Lib::calendar($params['year'], $params['month'], $params['day'], $params['url']);

        $this->page['title'] = '';

        $this->get_post($params['year'], $params['month'], $params['day'], $params['url']);

        // Bread crumbs
        $this->page['path'][] = [
            'title' => \Model::main_page_title(),
            'link'  => s2_link('/'),
        ];
        if (S2_BLOG_URL) {
            $this->page['path'][] = [
                'title' => Lang::get('Blog', 's2_blog'),
                'link'  => S2_BLOG_PATH,
            ];
        }

        $this->page['path'][] = [
            'title' => $params['year'],
            'link'  => S2_BLOG_PATH . $params['year'] . '/',
        ];
        $this->page['path'][] = [
            'title' => $params['month'],
            'link'  => S2_BLOG_PATH . $params['year'] . '/' . $params['month'] . '/',
        ];
        $this->page['path'][] = [
            'title' => $params['day'],
            'link'  => S2_BLOG_PATH . $params['year'] . '/' . $params['month'] . '/' . $params['day'] . '/',
        ];

        return null;
    }

    private function get_post($year, $month, $day, $url)
    {
        /** @var DbLayer $s2_db */
        $s2_db = \Container::get(DbLayer::class);

        $start_time = mktime(0, 0, 0, $month, $day, $year);
        $end_time   = mktime(0, 0, 0, $month, $day + 1, $year);

        $this->page['link_navigation'] = ['up' => S2_BLOG_PATH . date('Y/m/d/', $start_time)];

        $sub_query      = [
            'SELECT' => 'u.name',
            'FROM'   => 'users AS u',
            'WHERE'  => 'u.id = p.user_id',
        ];
        $raw_query_user = $s2_db->build($sub_query);

        $query = [
            'SELECT' => 'create_time, title, text, id, commented, label, favorite, (' . $raw_query_user . ') AS author, url',
            'FROM'   => 's2_blog_posts AS p',
            'WHERE'  => 'create_time < ' . $end_time . ' AND create_time >= ' . $start_time . ' AND url = \'' . $s2_db->escape($url) . '\' AND published = 1',
        ];
        ($hook = s2_hook('fn_s2_blog_get_post_pre_get_post_qr')) ? eval($hook) : null;
        $result = $s2_db->buildAndQuery($query);

        if (!$row = $s2_db->fetchAssoc($result)) {
            $this->s2_404_header();
            $this->page['head_title'] = Lang::get('Not found', 's2_blog');
            $this->page['text']       = '<p>' . Lang::get('Not found', 's2_blog') . '</p>';

            return;
        }

        $post_id = $row['id'];
        $label   = $row['label'];

        $this->page['canonical_path'] = S2_BLOG_PATH . date('Y/m/d/', $row['create_time']) . $row['url'];

        $is_back_forward = $this->inTemplate('<!-- s2_blog_back_forward -->');

        $queries = [];
        if ($label) {
            // Getting posts that have the same label
            $query = [
                'SELECT'   => 'title, create_time, url, "label" AS type',
                'FROM'     => 's2_blog_posts',
                'WHERE'    => 'label = \'' . $s2_db->escape($label) . '\' AND id <> ' . $post_id . ' AND published = 1',
                'ORDER BY' => 'create_time DESC',
            ];
            ($hook = s2_hook('fn_s2_blog_get_post_pre_get_labelled_posts_qr')) ? eval($hook) : null;
            $queries[] = $s2_db->build($query);
        }

        if ($is_back_forward) {
            $query = [
                'SELECT'   => 'title, create_time, url, "next" AS type',
                'FROM'     => 's2_blog_posts',
                'WHERE'    => ' create_time > ' . (int)$row['create_time'] . ' AND published = 1',
                'ORDER BY' => 'create_time ASC',
                'LIMIT'    => '1',
            ];
            ($hook = s2_hook('fn_s2_blog_get_post_pre_get_next_posts_qr')) ? eval($hook) : null;
            $queries[] = $s2_db->build($query);

            $query = [
                'SELECT'   => 'title, create_time, url, "prev" AS type',
                'FROM'     => 's2_blog_posts',
                'WHERE'    => ' create_time < ' . (int)$row['create_time'] . ' AND published = 1',
                'ORDER BY' => 'create_time DESC',
                'LIMIT'    => '1',
            ];
            ($hook = s2_hook('fn_s2_blog_get_post_pre_get_prev_posts_qr')) ? eval($hook) : null;
            $queries[] = $s2_db->build($query);
        }

        $result = !empty($queries) ? $s2_db->query('(' . implode(') UNION (', $queries) . ')') : null;

        $back_forward = [];
        while ($result && $row1 = $s2_db->fetchAssoc($result)) {
            $post_info = [
                'title' => $row1['title'],
                'link'  => S2_BLOG_PATH . date('Y/m/d/', $row1['create_time']) . urlencode($row1['url']),
            ];

            if ($row1['type'] == 'label')
                $row['see_also'][] = $post_info;
            elseif ($row1['type'] == 'next') {
                $this->page['link_navigation']['next'] = $post_info['link'];
                $back_forward['forward']               = $post_info;
            } elseif ($row1['type'] == 'prev') {
                $this->page['link_navigation']['prev'] = $post_info['link'];
                $back_forward['back']                  = $post_info;
            }
        }

        if (!empty($back_forward)) {
            $this->page['s2_blog_back_forward'] = $this->renderPartial('back_forward_post', $back_forward);
        }

        // Getting tags
        $query = [
            'SELECT'   => 'name, url',
            'FROM'     => 'tags AS t',
            'JOINS'    => [
                [
                    'INNER JOIN' => 's2_blog_post_tag AS pt',
                    'ON'         => 'pt.tag_id = t.tag_id',
                ],
            ],
            'WHERE'    => 'post_id = ' . $post_id,
            'ORDER BY' => 'pt.id',
        ];
        ($hook = s2_hook('fn_s2_blog_get_post_pre_get_labelled_posts_qr')) ? eval($hook) : null;
        $result = $s2_db->buildAndQuery($query);

        $tags = [];
        while ($tag = $s2_db->fetchAssoc($result))
            $tags[] = [
                'title' => $tag['name'],
                'link'  => S2_BLOG_TAGS_PATH . urlencode($tag['url']) . '/',
            ];

        $this->page['commented'] = $row['commented'];
        if ($row['commented'] && S2_SHOW_COMMENTS && $this->inTemplate('<!-- s2_comments -->'))
            $this->page['comments'] = $this->get_comments($post_id);

        $row['time']      = s2_date_time($row['create_time']);
        $row['commented'] = 0; // for template
        $row['tags']      = $tags;

        $this->page['meta_description'] = self::extractMetaDescriptions($row['text']);

        $this->page['text'] = $this->renderPartial('post', $row);

        if ($this->inTemplate('<!-- s2_recommendations -->')) {
            /** @var RecommendationProvider $recommendationProvider */
            $recommendationProvider = \Container::get(RecommendationProvider::class);
            global $request_uri;

            [$recommendations, $log, $rawRecommendations] = $recommendationProvider->getRecommendations($request_uri, new ExternalId('s2_blog_' . $post_id));
            $this->page['recommendations'] = $this->renderPartial('recommendations', [
                'raw'     => $rawRecommendations,
                'content' => $recommendations,
                'log'     => $log,
            ]);
        }

        $this->page['id']         = $post_id;
        $this->page['head_title'] = s2_htmlencode($row['title']);
    }

    private function get_comments($id)
    {
        /** @var DbLayer $s2_db */
        $s2_db = \Container::get(DbLayer::class);

        $comments = '';

        $query = [
            'SELECT'   => 'nick, time, email, show_email, good, text',
            'FROM'     => 's2_blog_comments',
            'WHERE'    => 'post_id = ' . $id . ' AND shown = 1',
            'ORDER BY' => 'time',
        ];
        ($hook = s2_hook('fn_s2_blog_get_comments_pre_qr')) ? eval($hook) : null;
        $result = $s2_db->buildAndQuery($query);

        for ($i = 1; $row = $s2_db->fetchAssoc($result); $i++) {
            $row['i'] = $i;
            $comments .= $this->renderPartial('comment', $row);
        }

        return $comments ? $this->renderPartial('comments', ['comments' => $comments]) : '';
    }

    private static function extractMetaDescriptions($text)
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
