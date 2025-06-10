<?php
/**
 * @copyright 2024-2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace s2_extensions\s2_blog\Model;

use S2\Cms\Pdo\DbLayer;
use S2\Cms\Pdo\DbLayerException;
use S2\Cms\Template\Viewer;
use s2_extensions\s2_blog\BlogUrlBuilder;

readonly class PostProvider
{
    public function __construct(
        private DbLayer        $dbLayer,
        private BlogUrlBuilder $blogUrlBuilder,
        private Viewer         $viewer,
    ) {
    }

    /**
     * Returns an array containing info about last N posts
     *
     * @throws DbLayerException
     */
    public function lastPostsArray(int $postsNum = 10, int $skip = 0, bool $fakeLastPost = false): array
    {
        if ($fakeLastPost) {
            $postsNum++;
        }

        // Obtaining last posts
        $rawQueryCount = $this->dbLayer
            ->select('count(*)')
            ->from('s2_blog_comments AS c')
            ->where('c.post_id = p.id')
            ->andWhere('c.shown = 1')
            ->getSql()
        ;

        $rawQueryUser = $this->dbLayer
            ->select('u.name')
            ->from('users AS u')
            ->where('u.id = p.user_id')
            ->getSql()
        ;

        $result = $this->dbLayer
            ->select('p.create_time, p.title, p.text, p.url, p.id, p.commented, p.modify_time, p.favorite')
            ->addSelect('(' . $rawQueryCount . ') AS comment_num')
            ->addSelect('(' . $rawQueryUser . ') AS author, p.label')
            ->from('s2_blog_posts AS p')
            ->where('p.published = 1')
            ->orderBy('p.create_time DESC')
            ->limit($postsNum)
            ->offset($skip)
            ->execute()
        ;

        $posts = $mergeLabels = $labels = $ids = [];
        $i     = 0;
        while ($row = $result->fetchAssoc()) {
            $i++;
            $posts[$row['id']] = $row;

            if ($i >= $postsNum && $fakeLastPost) {
                continue;
            }

            $ids[]              = $row['id'];
            $labels[$row['id']] = $row['label'];
            if ($row['label']) {
                $mergeLabels[$row['label']] = 1;
            }
        }
        if (!$i) {
            return [];
        }

        $seeAlso = $tags = [];
        $this->postsLinks($ids, $mergeLabels, $seeAlso, $tags);

        foreach ($posts as $i => &$post) {
            $posts[$i]['see_also'] = [];
            if (!empty($labels[$i]) && isset($seeAlso[$labels[$i]])) {
                $labelCopy = $seeAlso[$labels[$i]];
                if (isset($labelCopy[$i])) {
                    unset($labelCopy[$i]);
                }
                $posts[$i]['see_also'] = $labelCopy;
            }

            $post['tags'] = $tags[$i] ?? [];
            if (!isset($post['author'])) {
                $post['author'] = '';
            }

            $link               = $this->blogUrlBuilder->postFromTimestamp($post['create_time'], $post['url']);
            $post['title_link'] = $link;
            $post['link']       = $link;
            $post['time']       = $this->viewer->dateAndTime($post['create_time']);
        }

        return $posts;
    }

    /**
     * Fetching tags and labels for posts
     *
     * @param array $ids    Post ids, e.g. array (10, 15, 20)
     * @param array $labels Label flags, e.g. array ('label1' => 1, 'label2' => 1, 'label3' => 1)
     * @param       $see_also
     * @param       $tags
     *
     * @throws DbLayerException
     */
    public function postsLinks(array $ids, array $labels, &$see_also, &$tags): void
    {
        // Processing labels
        if (\count($labels) > 0) {
            $result = $this->dbLayer
                ->select('p.id, p.label, p.title, p.create_time, p.url')
                ->from('s2_blog_posts AS p')
                ->where('p.label IN (' . implode(',', array_fill(0, \count($labels), '?')) . ')')
                ->andWhere('p.published = 1')
                ->execute(array_keys($labels))
            ;

            $rows = $sortArray = [];
            while ($row = $result->fetchAssoc()) {
                $rows[]      = $row;
                $sortArray[] = $row['create_time'];
            }

            array_multisort($sortArray, SORT_DESC, $rows);

            foreach ($rows as $row) {
                $see_also[$row['label']][$row['id']] = [
                    'title' => $row['title'],
                    'link'  => $this->blogUrlBuilder->postFromTimestamp($row['create_time'], $row['url']),
                ];
            }
        }

        // Obtaining tags
        $result2 = $this->dbLayer
            ->select('pt.post_id', 't.name', 't.url', 'pt.id AS pt_id')
            ->from('tags AS t')
            ->innerJoin('s2_blog_post_tag AS pt', 'pt.tag_id = t.id')
            ->where('pt.post_id IN (' . implode(',', array_fill(0, \count($ids), '?')) . ')')
            ->execute($ids)
        ;

        $rows = $sortArray = [];
        while ($row = $result2->fetchAssoc()) {
            $rows[]      = $row;
            $sortArray[] = $row['pt_id'];
        }

        array_multisort($sortArray, $rows);

        $tags = [];
        foreach ($rows as $row) {
            $tags[$row['post_id']][] = array(
                'title' => $row['name'],
                'link'  => $this->blogUrlBuilder->tag($row['url']),
            );
        }
    }

    /**
     * @throws DbLayerException
     */
    public function checkUrlStatus(int $createTime, string $url): string
    {
        if ($url === '') {
            return 'empty';
        }

        $startTime = strtotime('midnight', $createTime);
        $endTime   = $startTime + 86400;

        $result = $this->dbLayer
            ->select('COUNT(*)')
            ->from('s2_blog_posts')
            ->where('url = :url')
            ->setParameter('url', $url)
            ->andWhere('create_time < :end_time')
            ->setParameter('end_time', $endTime)
            ->andWhere('create_time >= :start_time')
            ->setParameter('start_time', $startTime)
            ->execute()
        ;

        if ($result->result() !== 1) {
            return 'not_unique';
        }

        return 'ok';
    }

    /**
     * @throws DbLayerException
     */
    public function getAllLabels(): array
    {
        $result = $this->dbLayer
            ->select('label')
            ->from('s2_blog_posts')
            ->groupBy('label')
            ->orderBy('count(label) DESC')
            ->execute()
        ;
        $labels = $result->fetchColumn();

        return $labels;
    }

    /**
     * @throws DbLayerException
     */
    public function getCommentNum(int $postId, bool $includeHidden): int
    {
        $qb = $this->dbLayer
            ->select('COUNT(*)')
            ->from('s2_blog_comments')
            ->where('post_id = :post_id')
            ->setParameter('post_id', $postId)
        ;

        if (!$includeHidden) {
            $qb->andWhere('shown = 1');
        }

        return (int)$qb->execute()->result();
    }
}
