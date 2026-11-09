<?php
/**
 * @copyright 2026 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace integration;

use IntegrationTester;
use Codeception\Example;
use Psr\Log\NullLogger;
use S2\Cms\Config\DynamicConfigProvider;
use S2\Cms\Helper\StringHelper;
use S2\Cms\Mail\CommentMailer;
use S2\Cms\Model\User\UserProvider;
use S2\Cms\Comment\SpamDetectorReport;
use S2\Cms\Comment\SpamDigestNotifier;
use S2\Cms\Pdo\DbLayer;
use S2\Cms\Queue\QueueConsumer;
use S2\Cms\Queue\QueuePublisher;
use S2\Cms\Template\Viewer;
use s2_extensions\s2_blog\BlogUrlBuilder;

/**
 * @group spam
 */
class SpamDigestCest
{
    public function _before(IntegrationTester $I): void
    {
        $I->setConfigValue('S2_PREMODERATION', '1');
        $I->grabService(DbLayer::class)->update('users')->set('hide_comments', '0')
            ->where('email <> :email')->setParameter('email', 'admin@example.com')->execute();
    }

    public function testFirstNotificationAndSixHourlyDigest(IntegrationTester $I): void
    {
        $text = "Привет [b]мир[/b] &lt;i&gt;текст&lt;/i&gt; www.spam.example spam.example/path name@spam.example https&#58;//spam.example \n" . str_repeat('длинный текст ', 30);
        $submittedAt = time();
        $this->submit($I, $text);
        $queuedPayload = json_decode($I->grabService(DbLayer::class)->select('payload')->from('queue')->execute()->result(), true, 512, JSON_THROW_ON_ERROR);
        $I->assertGreaterThanOrEqual($submittedAt, $queuedPayload['sample']['received_at']);
        $I->assertLessThanOrEqual(time(), $queuedPayload['sample']['received_at']);
        $beforeDelivery = time();
        $I->assertEmpty($I->grabModeratorMails());
        $this->drain($I);

        $mails = $I->grabModeratorMails();
        $I->assertCount(1, $mails);
        $I->assertStringContainsString('Received: ' . $I->grabService(Viewer::class)->dateAndTime($queuedPayload['sample']['received_at']), $mails[0]['text']);
        $state = $I->grabService(DbLayer::class)->select('*')->from('spam_notifications')->execute()->fetchAssoc();
        $I->assertGreaterThanOrEqual($beforeDelivery + 6 * 3600, (int)$state['next_send_at']);
        $I->assertLessThanOrEqual(time() + 6 * 3600, (int)$state['next_send_at']);
        $I->assertEquals($state['next_send_at'], $I->grabService(DbLayer::class)->select('available_at')->from('queue')->where('code = :code')->setParameter('code', SpamDigestNotifier::DIGEST_CODE)->execute()->result());
        $I->assertStringContainsString('Привет мир текст', $mails[0]['text']);
        $I->assertStringNotContainsString('spam.example', $mails[0]['text']);
        $I->assertStringNotContainsString('[b]', $mails[0]['text']);
        $I->assertStringNotContainsString('<i>', $mails[0]['text']);
        $I->assertStringNotContainsString($text, $mails[0]['text']);
        $I->assertStringContainsString('http://s2.localhost/_admin/index.php', $mails[0]['text']);
        $I->assertArrayNotHasKey('Sender', $mails[0]['headers']);
        $I->assertStringNotContainsString('spammer@example.com', json_encode($mails));
        $I->assertSame('Comments held by the spam filter', base64_decode(substr($mails[0]['subject'], 10, -2)));

        $db = $I->grabService(DbLayer::class);
        $comment = $db->select('text, shown, spam_check_status')->from('art_comments')->execute()->fetchAssoc();
        $I->assertSame(trim($text), $comment['text']);
        $I->assertEquals(0, $comment['shown']);
        $I->assertSame('spam', $comment['spam_check_status']);

        for ($i = 0; $i < 3; ++$i) {
            $this->submit($I, 'Repeated spam');
            $this->drain($I);
        }
        for ($i = 0; $i < 6; ++$i) {
            $this->submit($I, "Different spam $i " . str_repeat('цитата ', 50));
            $this->drain($I);
        }

        $I->assertCount(1, $I->grabModeratorMails());
        $state = $db->select('*')->from('spam_notifications')->execute()->fetchAssoc();
        $I->assertEquals(9, $state['pending_count']);
        $samples = json_decode($state['samples'], true, 512, JSON_THROW_ON_ERROR);
        $I->assertCount(5, $samples);
        $I->assertSame(3, $samples[0]['count']);
        foreach ($samples as $sample) {
            $I->assertLessThanOrEqual(100, mb_strlen($sample['quote']));
        }

        // The scheduled job sends the accumulated digest without another comment.
        $this->makeDue($I);
        $this->drain($I);
        $mails = $I->grabModeratorMails();
        $I->assertCount(2, $mails);
        $I->assertStringContainsString('held 9 comments since the previous notification.', $mails[1]['text']);
        $I->assertStringContainsString('(3 comments)', $mails[1]['text']);
        $I->assertEquals(0, $db->select('pending_count')->from('spam_notifications')->execute()->result());

        // An empty interval produces neither mail nor an endless chain of jobs.
        $this->makeDue($I);
        $this->drain($I);
        $I->assertCount(2, $I->grabModeratorMails());
        $I->assertEquals(0, $db->select('COUNT(*)')->from('queue')->execute()->result());

        $this->submit($I, 'After a quiet interval');
        $this->drain($I);
        $I->assertCount(3, $I->grabModeratorMails());
    }

    /**
     * @dataProvider digestLanguageProvider
     */
    public function testDigestNumberAgreement(IntegrationTester $I, Example $example): void
    {
        $I->setConfigValue('S2_LANGUAGE', $example['language']);
        $I->grabService(CommentMailer::class)->mailSpamDigest('admin', 'admin@example.com', $example['count'], [[
            'title' => 'Page',
            'quote' => 'Spam excerpt',
            'count' => $example['count'],
            'received_at' => mktime(12, 34, 0, 11, 14, 2023),
        ]], 'http://s2.localhost/_admin/index.php');
        $mail = $I->grabModeratorMails()[0]['text'];
        $I->assertStringContainsString($example['summary'], $mail);
        $I->assertStringContainsString('(' . $example['form'] . '):', $mail);
        $I->assertStringContainsString($example['date'], $mail);
        $I->assertStringContainsString($example['interval'], $mail);
        $I->assertStringNotContainsString('%count%', $mail);
    }

    protected function digestLanguageProvider(): array
    {
        $examples = [];
        foreach ([1 => 'комментарий', 2 => 'комментария', 5 => 'комментариев', 11 => 'комментариев', 21 => 'комментарий', 22 => 'комментария', 25 => 'комментариев', 111 => 'комментариев'] as $count => $word) {
            $examples[] = [
                'date' => '14 ноября 2023 года, 12:34',
                'language' => 'Russian', 'count' => $count, 'form' => "$count $word",
                'summary' => "антиспам задержал $count $word.", 'interval' => 'не чаще раза в 6 часов.',
            ];
        }
        foreach ([1 => 'comment', 2 => 'comments'] as $count => $word) {
            $examples[] = [
                'date' => 'November 14, 2023. 12:34 PM',
                'language' => 'English', 'count' => $count, 'form' => "$count $word",
                'summary' => "held $count $word since the previous notification.", 'interval' => 'at most once every 6 hours.',
            ];
        }
        return $examples;
    }

    public function testRepeatedSpamKeepsReceiptRangeAndLegacySamplesStillWork(IntegrationTester $I): void
    {
        $this->submit($I, 'First spam');
        $this->drain($I);
        $this->submit($I, 'Repeated spam');
        $this->drain($I);
        $db = $I->grabService(DbLayer::class);
        $state = $db->select('samples')->from('spam_notifications')->execute()->result();
        $samples = json_decode($state, true, 512, JSON_THROW_ON_ERROR);
        $samples[0]['received_at'] = 1700000000;
        $db->update('spam_notifications')->set('samples', ':samples')
            ->setParameter('samples', json_encode($samples, JSON_THROW_ON_ERROR))->execute();
        $this->submit($I, 'Repeated spam');
        $payload = json_decode($db->select('payload')->from('queue')->where('code = :code')
            ->setParameter('code', SpamDigestNotifier::COMMENT_CODE)->execute()->result(), true, 512, JSON_THROW_ON_ERROR);
        $this->drain($I);
        $samples = json_decode($db->select('samples')->from('spam_notifications')->execute()->result(), true, 512, JSON_THROW_ON_ERROR);
        $I->assertSame(1700000000, $samples[0]['received_at']);
        $I->assertSame($payload['sample']['received_at'], $samples[0]['last_received_at']);
        $this->makeDue($I);
        $this->drain($I);
        $mail = $I->grabModeratorMails()[1]['text'];
        $I->assertStringContainsString($I->grabService(Viewer::class)->dateAndTime(1700000000) . ' – ' . $I->grabService(Viewer::class)->dateAndTime($samples[0]['last_received_at']), $mail);

        // Samples already persisted before the update have no receipt timestamp.
        unset($samples[0]['received_at'], $samples[0]['last_received_at']);
        $I->grabService(CommentMailer::class)->mailSpamDigest('admin', 'admin@example.com', 2, $samples, 'http://s2.localhost/_admin/index.php');
        $mail = $I->grabModeratorMails()[2]['text'];
        $I->assertStringContainsString('(2 comments)', $mail);
        $I->assertStringContainsString('Repeated spam', $mail);
        $I->assertStringNotContainsString('Received:', $mail);
    }

    public function testSpamWithModeratorEmailDoesNotBypassDigest(IntegrationTester $I): void
    {
        $location = $this->submit($I, 'Spam using moderator email', 'admin@example.com');
        $I->amOnPage($location);
        $I->seeResponseCodeIs(200);
        $I->amOnPage($location);
        $I->assertEmpty($I->grabModeratorMails());
        $this->drain($I);
        $I->assertCount(1, $I->grabModeratorMails());

        $location = $this->submit($I, 'More spam using moderator email', 'admin@example.com');
        $I->amOnPage($location);
        $this->drain($I);
        $I->assertCount(1, $I->grabModeratorMails());
        $I->assertEquals(0, $I->grabService(DbLayer::class)->select('SUM(shown)')->from('art_comments')->execute()->result());
    }

    public function testAuthenticatedModeratorCanStillPublishOwnComment(IntegrationTester $I): void
    {
        $I->login('admin', 'admin');
        $location = $this->submit($I, 'My comment classified as spam', 'admin@example.com', 'https://localhost/');
        $I->amOnPage('https://localhost' . $location);
        $I->seeResponseCodeIs(302);
        $I->assertEquals(1, $I->grabService(DbLayer::class)->select('shown')->from('art_comments')->execute()->result());
    }

    public function testRecipientsHaveIndependentLimits(IntegrationTester $I): void
    {
        $this->submit($I, 'First spam');
        $this->drain($I);
        $db = $I->grabService(DbLayer::class);
        $db->update('users')->set('hide_comments', '1')->where("login = 'moderator'")->execute();
        $this->submit($I, 'Second spam');
        $this->drain($I);

        $mails = $I->grabModeratorMails();
        $I->assertCount(2, $mails);
        $I->assertSame('admin@example.com', $mails[0]['moderatorEmail']);
        $I->assertSame('moderator@example.com', $mails[1]['moderatorEmail']);
        $I->assertStringContainsString('Second spam', $mails[1]['text']);
    }

    /**
     * @dataProvider nonSpamProvider
     */
    public function testNonSpamNotificationsKeepFullText(IntegrationTester $I, Example $example): void
    {
        $I->setConfigValue('S2_PREMODERATION', $example['premoderation']);
        $I->setSpamResponses([$example['status']]);
        $text = str_repeat('Ordinary comment ', 20) . '<b>more</b>';
        $I->sendPost('http://s2.localhost/', [
            'name' => 'Reader',
            'email' => 'reader@example.com',
            'text' => $text,
            'key' => '111111111111111111111',
            'question' => '12',
        ]);
        $I->seeResponseCodeIs(302);
        $mails = $I->grabModeratorMails();
        $I->assertCount(1, $mails);
        $I->assertSame(StringHelper::bbcodeToMail($text), $mails[0]['text']);
        $I->assertSame($example['status'], $mails[0]['spamReportStatus']);
        $I->assertSame($example['published'], $mails[0]['isPublished']);
        $this->drain($I);
        $I->assertCount(1, $I->grabModeratorMails());
    }

    protected function nonSpamProvider(): array
    {
        return [
            ['status' => 'ham', 'premoderation' => '0', 'published' => false],
            ['status' => 'ham', 'premoderation' => '1', 'published' => false],
            ['status' => 'disabled', 'premoderation' => '0', 'published' => true],
            ['status' => 'disabled', 'premoderation' => '1', 'published' => false],
            ['status' => 'failed', 'premoderation' => '0', 'published' => true],
            ['status' => 'failed', 'premoderation' => '1', 'published' => false],
        ];
    }

    public function testNoModeratorsCreatesNoDigestJobs(IntegrationTester $I): void
    {
        $db = $I->grabService(DbLayer::class);
        $db->update('users')->set('hide_comments', '0')->execute();
        $this->submit($I, 'Spam without recipients');
        $this->drain($I);
        $I->assertEmpty($I->grabModeratorMails());
        $I->assertEquals(0, $db->select('COUNT(*)')->from('spam_notifications')->execute()->result());
    }

    /**
     * @dataProvider longSpamProvider
     */
    public function testLongMalformedSpamCanStillBeNotified(IntegrationTester $I, Example $example): void
    {
        $this->submit($I, $example['text']);
        $payload = $I->grabService(DbLayer::class)->select('payload')->from('queue')->execute()->result();
        $sample = json_decode($payload, true, 512, JSON_THROW_ON_ERROR)['sample'];
        $I->assertLessThanOrEqual(100, mb_strlen($sample['quote']));
        $this->drain($I);
        $I->assertCount(1, $I->grabModeratorMails());
    }

    protected function longSpamProvider(): array
    {
        return [['text' => str_repeat('a.', 32000)], ['text' => str_repeat('[url=', 12000)]];
    }

    public function testRevokedPermissionStopsQueuedMail(IntegrationTester $I): void
    {
        $this->submit($I, 'First spam');
        $this->drain($I);
        $this->submit($I, 'Pending spam');
        $this->drain($I);
        $db = $I->grabService(DbLayer::class);
        $db->update('users')->set('hide_comments', '0')->execute();
        $this->makeDue($I);
        $this->drain($I);
        $I->assertCount(1, $I->grabModeratorMails());
        $I->assertEquals(0, $db->select('COUNT(*)')->from('spam_notifications')->execute()->result());
    }

    public function testBlogAndArticlesShareDigest(IntegrationTester $I): void
    {
        $this->submit($I, 'Article spam');
        $this->drain($I);
        $db = $I->grabService(DbLayer::class);
        $timestamp = time();
        $db->insert('s2_blog_posts')
            ->setValue('title', "'Blog post'")
            ->setValue('text', "'Text'")
            ->setValue('url', "'digest-test'")
            ->setValue('create_time', ':time')->setParameter('time', $timestamp)
            ->setValue('published', '1')
            ->execute();
        $url = $I->grabService(BlogUrlBuilder::class)->absPostFromTimestamp($timestamp, 'digest-test');
        $location = $this->submit($I, 'Blog spam', 'admin@example.com', $url);
        $I->amOnPage($location);
        $I->seeResponseCodeIs(200);
        $this->drain($I);
        $I->assertCount(1, $I->grabModeratorMails());
        $I->assertSame('spam', $db->select('spam_check_status')->from('s2_blog_comments')->execute()->result());

        $this->makeDue($I);
        $this->drain($I);
        $mails = $I->grabModeratorMails();
        $I->assertCount(2, $mails);
        $I->assertStringContainsString('Blog post', $mails[1]['text']);
        $I->assertStringContainsString('Blog spam', $mails[1]['text']);
    }

    /**
     * @dataProvider deliveryFailureProvider
     */
    public function testFailedDeliveryIsRetried(IntegrationTester $I, Example $example): void
    {
        $this->submit($I, 'Retry this notification');
        $config = $I->grabService(DynamicConfigProvider::class);
        $mailerClass = $example['mailer'];
        $mailer = new $mailerClass(
            $I->grabService('comments_translator'),
            $config->getStringProxy('S2_WEBMASTER'),
            $config->getStringProxy('S2_WEBMASTER_EMAIL'),
        );
        $logger = new NullLogger();
        $pdo = $I->grabService(\PDO::class);
        $db = $I->grabService(DbLayer::class);
        $notifier = new SpamDigestNotifier($db, $pdo, $I->grabService(QueuePublisher::class), $I->grabService(UserProvider::class), $mailer, 'http://s2.localhost/_admin/index.php', $logger);
        $consumer = new QueueConsumer($pdo, '', $logger, $notifier);
        $I->assertTrue($consumer->runQueue());
        $I->assertFalse($consumer->runQueue());
        $I->assertEmpty($I->grabModeratorMails());
        $I->assertEquals(1, $db->select('pending_count')->from('spam_notifications')->execute()->result());

        $this->makeDue($I);
        $this->drain($I);
        $I->assertCount(1, $I->grabModeratorMails());
        $I->assertEquals(0, $db->select('pending_count')->from('spam_notifications')->execute()->result());
    }

    protected function deliveryFailureProvider(): array
    {
        return [['mailer' => FailedSpamDigestMailer::class], ['mailer' => ThrowingSpamDigestMailer::class]];
    }

    private function submit(IntegrationTester $I, string $text, string $email = 'spammer@example.com', string $url = 'http://s2.localhost/'): string
    {
        $I->setSpamResponses([SpamDetectorReport::STATUS_SPAM]);
        $I->sendPost($url, [
            'name' => 'Spammer',
            'email' => $email,
            'text' => $text,
            'key' => '111111111111111111111',
            'question' => '12',
        ]);
        $I->seeResponseCodeIs(302);
        $location = $I->grabLocation();
        $I->assertStringContainsString('comment_sent', $location);
        return $location;
    }

    private function drain(IntegrationTester $I): void
    {
        $consumer = $I->grabService(QueueConsumer::class);
        for ($i = 0; $i < 100; ++$i) {
            if (!$consumer->runQueue()) {
                return;
            }
        }
        $I->fail('Queue did not drain.');
    }

    private function makeDue(IntegrationTester $I): void
    {
        $db = $I->grabService(DbLayer::class);
        $db->update('spam_notifications')->set('next_send_at', '0')->execute();
        $db->update('queue')->set('available_at', '0')
            ->where('code = :code')->setParameter('code', SpamDigestNotifier::DIGEST_CODE)->execute();
    }
}

readonly class FailedSpamDigestMailer extends CommentMailer
{
    public function mailSpamDigest(string $name, string $email, int $count, array $samples, string $adminUrl): bool
    {
        return false;
    }
}

readonly class ThrowingSpamDigestMailer extends FailedSpamDigestMailer
{
    public function mailSpamDigest(string $name, string $email, int $count, array $samples, string $adminUrl): bool
    {
        throw new \RuntimeException('Mail transport failed.');
    }
}
