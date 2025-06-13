<?php
/**
 * Single blog post.
 *
 * @copyright 2007-2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   s2_blog
 */

namespace s2_extensions\s2_blog\Controller;

use Psr\Cache\InvalidArgumentException;
use S2\Cms\Model\ArticleProvider;
use S2\Cms\Model\UrlBuilder;
use S2\Cms\Pdo\DbLayer;
use S2\Cms\Pdo\DbLayerException;
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

    /**
     * @throws DbLayerException
     * @throws InvalidArgumentException
     */
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

    /**
     * @throws InvalidArgumentException
     * @throws DbLayerException
     */
    private function get_post(Request $request, HtmlTemplate $template, int $year, int $month, int $day, string $url): ?Response
    {
        $startTime = mktime(0, 0, 0, $month, $day, $year);
        $endTime   = $startTime + 86400;

        $template->setLink('up', $this->blogUrlBuilder->day($year, $month, $day));

        $result = $this->dbLayer
            ->select(
                'create_time, title, text, id, commented, label, favorite',
                '(' . $this->dbLayer
                    ->select('u.name')
                    ->from('users AS u')
                    ->where('u.id = p.user_id')
                    ->getSql() . ') AS author',
                'url'
            )
            ->from('s2_blog_posts AS p')
            ->where('create_time < :end_time')->setParameter('end_time', $endTime)
            ->andWhere('create_time >= :start_time')->setParameter('start_time', $startTime)
            ->andWhere('url = :url')->setParameter('url', $url)
            ->andWhere('published = 1')
            ->execute()
        ;

        if (!$row = $result->fetchAssoc()) {
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

        $queries = $params = [];
        if ($label) {
            // Getting posts that have the same label
            $queries[]         = $this->dbLayer->select('title, create_time, url, "label" AS type')
                ->from('s2_blog_posts')
                ->where('label = :label')
                ->andWhere('id <> :post_id')
                ->andWhere('published = 1')
                ->orderBy('create_time DESC')
                ->getSql()
            ;
            $params['label']   = $label;
            $params['post_id'] = $post_id;
        }

        if ($is_back_forward) {
            $queries[] = $this->dbLayer->select('title, create_time, url, "next" AS type')
                ->from('s2_blog_posts')
                ->where('create_time > :time_next')
                ->andWhere('published = 1')
                ->orderBy('create_time ASC')
                ->limit(1)
                ->getSql()
            ;

            $params['time_next'] = (int)$row['create_time'];

            $queries[] = $this->dbLayer->select('title, create_time, url, "prev" AS type')
                ->from('s2_blog_posts')
                ->where('create_time < :time_prev')
                ->setParameter('time_prev', (int)$row['create_time'], \PDO::PARAM_INT)
                ->andWhere('published = 1')
                ->orderBy('create_time DESC')
                ->limit(1)
                ->getSql()
            ;

            $params['time_prev'] = (int)$row['create_time'];
        }

        $result = !empty($queries) ? $this->dbLayer->query('(' . implode(') UNION (', $queries) . ')', $params) : null;

        $back_forward = [];
        while ($result !== null && $row1 = $result->fetchAssoc()) {
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
        $result = $this->dbLayer
            ->select('name, url')
            ->from('tags AS t')
            ->innerJoin('s2_blog_post_tag AS pt', 'pt.tag_id = t.id')
            ->where('post_id = :post_id')
            ->setParameter('post_id', $post_id)
            ->orderBy('pt.id')
            ->execute()
        ;

        $tags = [];
        while ($tag = $result->fetchAssoc()) {
            $tags[] = [
                'title' => $tag['name'],
                'link'  => $this->blogUrlBuilder->tag($tag['url']),
            ];
        }

        $template->putInPlaceholder('commented', $row['commented']);
        if ($row['commented'] && $this->showComments && $template->hasPlaceholder('<!-- s2_comments -->')) {
            $template->putInPlaceholder('comments', $this->getComments($post_id));
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

    /**
     * @throws DbLayerException
     */
    private function getComments(int $id): string
    {
        $comments = '';

        $statement = $this->dbLayer
            ->select('nick, time, email, show_email, good, text')
            ->from('s2_blog_comments')
            ->where('post_id = :post_id')
            ->setParameter('post_id', $id)
            ->andWhere('shown = 1')
            ->orderBy('time')
            ->execute()
        ;

        for ($i = 1; $row = $statement->fetchAssoc(); $i++) {
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
