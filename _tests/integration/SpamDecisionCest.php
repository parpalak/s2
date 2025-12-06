<?php
/**
 * @copyright 2025 Roman Parpalak
 * @license   http://opensource.org/licenses/MIT MIT
 * @package   S2
 */

declare(strict_types=1);

namespace integration;

use Codeception\Example;
use S2\Cms\Comment\SpamDetectorReport;

/**
 * @group spam
 */
class SpamDecisionCest
{
    private const COMMENT_URL = 'http://s2.localhost/';

    /**
     * @dataProvider decisionProvider
     */
    public function testSpamDecision(\IntegrationTester $I, Example $example): void
    {
        $I->setConfigValue('S2_PREMODERATION', $example['premoderation']);
        $I->setSpamResponses([$example['status']]);
        $startCount = $this->commentCount($I);

        $I->sendPost(self::COMMENT_URL, $this->commentData($example['text']));

        if ($example['expectErrorKey'] !== null) {
            /** @var \Symfony\Contracts\Translation\TranslatorInterface $translator */
            $translator = $I->grabService('comments_translator');
            $expectError = $translator->trans($example['expectErrorKey']);

            $I->seeResponseCodeIs(200);
            $I->see($expectError);
            $I->assertEquals($startCount, $this->commentCount($I));
            $I->assertEmpty($I->grabModeratorMails());
            return;
        }

        $I->seeResponseCodeIs(302);
        $location = $I->grabLocation();
        if ($example['expectCommentSent']) {
            $I->assertStringContainsString('comment_sent', $location);
        } else {
            $I->assertStringNotContainsString('comment_sent', $location);
        }

        $this->assertLastComment($I, shown: $example['expectShown'], totalStart: $startCount);

        $mails = $I->grabModeratorMails();
        if ($example['expectMail']) {
            $I->assertNotEmpty($mails);
            $I->assertEquals($example['mailStatus'], $mails[0]['spamReportStatus']);
            $I->assertEquals($example['mailPublished'], $mails[0]['isPublished']);
        } else {
            $I->assertEmpty($mails);
        }
    }

    protected function decisionProvider(): array
    {
        return [
            // Ham, no markup, publishes immediately
            [
                'text'              => $this->composeText(false, false),
                'status'            => SpamDetectorReport::STATUS_HAM,
                'premoderation'     => '0',
                'expectErrorKey'    => null,
                'expectCommentSent' => false,
                'expectShown'       => 1,
                'expectMail'        => true,
                'mailStatus'        => SpamDetectorReport::STATUS_HAM,
                'mailPublished'     => true,
            ],
            [
                'text'              => $this->composeText(false, false),
                'status'            => SpamDetectorReport::STATUS_HAM,
                'premoderation'     => '1',
                'expectErrorKey'    => null,
                'expectCommentSent' => false,
                'expectShown'       => 1,
                'expectMail'        => true,
                'mailStatus'        => SpamDetectorReport::STATUS_HAM,
                'mailPublished'     => true,
            ],

            // Ham with link → force moderation
            [
                'text'              => $this->composeText(true, false),
                'status'            => SpamDetectorReport::STATUS_HAM,
                'premoderation'     => '0',
                'expectErrorKey'    => null,
                'expectCommentSent' => true,
                'expectShown'       => 0,
                'expectMail'        => true,
                'mailStatus'        => SpamDetectorReport::STATUS_HAM,
                'mailPublished'     => false,
            ],
            [
                'text'              => $this->composeText(true, false),
                'status'            => SpamDetectorReport::STATUS_HAM,
                'premoderation'     => '1',
                'expectErrorKey'    => null,
                'expectCommentSent' => true,
                'expectShown'       => 0,
                'expectMail'        => true,
                'mailStatus'        => SpamDetectorReport::STATUS_HAM,
                'mailPublished'     => false,
            ],

            // Ham with HTML → force moderation
            [
                'text'              => $this->composeText(false, true),
                'status'            => SpamDetectorReport::STATUS_HAM,
                'premoderation'     => '0',
                'expectErrorKey'    => null,
                'expectCommentSent' => true,
                'expectShown'       => 0,
                'expectMail'        => true,
                'mailStatus'        => SpamDetectorReport::STATUS_HAM,
                'mailPublished'     => false,
            ],
            [
                'text'              => $this->composeText(false, true),
                'status'            => SpamDetectorReport::STATUS_HAM,
                'premoderation'     => '1',
                'expectErrorKey'    => null,
                'expectCommentSent' => true,
                'expectShown'       => 0,
                'expectMail'        => true,
                'mailStatus'        => SpamDetectorReport::STATUS_HAM,
                'mailPublished'     => false,
            ],

            // Ham with link + HTML → force moderation
            [
                'text'              => $this->composeText(true, true),
                'status'            => SpamDetectorReport::STATUS_HAM,
                'premoderation'     => '0',
                'expectErrorKey'    => null,
                'expectCommentSent' => true,
                'expectShown'       => 0,
                'expectMail'        => true,
                'mailStatus'        => SpamDetectorReport::STATUS_HAM,
                'mailPublished'     => false,
            ],
            [
                'text'              => $this->composeText(true, true),
                'status'            => SpamDetectorReport::STATUS_HAM,
                'premoderation'     => '1',
                'expectErrorKey'    => null,
                'expectCommentSent' => true,
                'expectShown'       => 0,
                'expectMail'        => true,
                'mailStatus'        => SpamDetectorReport::STATUS_HAM,
                'mailPublished'     => false,
            ],

            // Spam, no link → save but moderate
            [
                'text'              => $this->composeText(false, false),
                'status'            => SpamDetectorReport::STATUS_SPAM,
                'premoderation'     => '0',
                'expectErrorKey'    => null,
                'expectCommentSent' => true,
                'expectShown'       => 0,
                'expectMail'        => true,
                'mailStatus'        => SpamDetectorReport::STATUS_SPAM,
                'mailPublished'     => false,
            ],
            [
                'text'              => $this->composeText(false, false),
                'status'            => SpamDetectorReport::STATUS_SPAM,
                'premoderation'     => '1',
                'expectErrorKey'    => null,
                'expectCommentSent' => true,
                'expectShown'       => 0,
                'expectMail'        => true,
                'mailStatus'        => SpamDetectorReport::STATUS_SPAM,
                'mailPublished'     => false,
            ],

            // Spam with link → reject because of link
            [
                'text'              => $this->composeText(true, false),
                'status'            => SpamDetectorReport::STATUS_SPAM,
                'premoderation'     => '0',
                'expectErrorKey'    => 'links_in_text',
                'expectCommentSent' => false,
                'expectShown'       => 0,
                'expectMail'        => false,
                'mailStatus'        => SpamDetectorReport::STATUS_SPAM,
                'mailPublished'     => false,
            ],
            [
                'text'              => $this->composeText(true, false),
                'status'            => SpamDetectorReport::STATUS_SPAM,
                'premoderation'     => '1',
                'expectErrorKey'    => 'links_in_text',
                'expectCommentSent' => false,
                'expectShown'       => 0,
                'expectMail'        => false,
                'mailStatus'        => SpamDetectorReport::STATUS_SPAM,
                'mailPublished'     => false,
            ],

            // Spam with HTML → moderate (HTML only adds moderation)
            [
                'text'              => $this->composeText(false, true),
                'status'            => SpamDetectorReport::STATUS_SPAM,
                'premoderation'     => '0',
                'expectErrorKey'    => null,
                'expectCommentSent' => true,
                'expectShown'       => 0,
                'expectMail'        => true,
                'mailStatus'        => SpamDetectorReport::STATUS_SPAM,
                'mailPublished'     => false,
            ],
            [
                'text'              => $this->composeText(false, true),
                'status'            => SpamDetectorReport::STATUS_SPAM,
                'premoderation'     => '1',
                'expectErrorKey'    => null,
                'expectCommentSent' => true,
                'expectShown'       => 0,
                'expectMail'        => true,
                'mailStatus'        => SpamDetectorReport::STATUS_SPAM,
                'mailPublished'     => false,
            ],

            // Spam with link + HTML → reject because of link
            [
                'text'              => $this->composeText(true, true),
                'status'            => SpamDetectorReport::STATUS_SPAM,
                'premoderation'     => '0',
                'expectErrorKey'    => 'links_in_text',
                'expectCommentSent' => false,
                'expectShown'       => 0,
                'expectMail'        => false,
                'mailStatus'        => SpamDetectorReport::STATUS_SPAM,
                'mailPublished'     => false,
            ],
            [
                'text'              => $this->composeText(true, true),
                'status'            => SpamDetectorReport::STATUS_SPAM,
                'premoderation'     => '1',
                'expectErrorKey'    => 'links_in_text',
                'expectCommentSent' => false,
                'expectShown'       => 0,
                'expectMail'        => false,
                'mailStatus'        => SpamDetectorReport::STATUS_SPAM,
                'mailPublished'     => false,
            ],

            // Blatant spam, no link → reject as spam
            [
                'text'              => $this->composeText(false, false),
                'status'            => SpamDetectorReport::STATUS_BLATANT,
                'premoderation'     => '0',
                'expectErrorKey'    => 'spam_message_rejected',
                'expectCommentSent' => false,
                'expectShown'       => 0,
                'expectMail'        => false,
                'mailStatus'        => SpamDetectorReport::STATUS_BLATANT,
                'mailPublished'     => false,
            ],
            [
                'text'              => $this->composeText(false, false),
                'status'            => SpamDetectorReport::STATUS_BLATANT,
                'premoderation'     => '1',
                'expectErrorKey'    => 'spam_message_rejected',
                'expectCommentSent' => false,
                'expectShown'       => 0,
                'expectMail'        => false,
                'mailStatus'        => SpamDetectorReport::STATUS_BLATANT,
                'mailPublished'     => false,
            ],

            // Blatant spam with link → reject because of link check
            [
                'text'              => $this->composeText(true, false),
                'status'            => SpamDetectorReport::STATUS_BLATANT,
                'premoderation'     => '0',
                'expectErrorKey'    => 'links_in_text',
                'expectCommentSent' => false,
                'expectShown'       => 0,
                'expectMail'        => false,
                'mailStatus'        => SpamDetectorReport::STATUS_BLATANT,
                'mailPublished'     => false,
            ],
            [
                'text'              => $this->composeText(true, false),
                'status'            => SpamDetectorReport::STATUS_BLATANT,
                'premoderation'     => '1',
                'expectErrorKey'    => 'links_in_text',
                'expectCommentSent' => false,
                'expectShown'       => 0,
                'expectMail'        => false,
                'mailStatus'        => SpamDetectorReport::STATUS_BLATANT,
                'mailPublished'     => false,
            ],

            // Blatant spam with HTML (no link) → reject as spam
            [
                'text'              => $this->composeText(false, true),
                'status'            => SpamDetectorReport::STATUS_BLATANT,
                'premoderation'     => '0',
                'expectErrorKey'    => 'spam_message_rejected',
                'expectCommentSent' => false,
                'expectShown'       => 0,
                'expectMail'        => false,
                'mailStatus'        => SpamDetectorReport::STATUS_BLATANT,
                'mailPublished'     => false,
            ],
            [
                'text'              => $this->composeText(false, true),
                'status'            => SpamDetectorReport::STATUS_BLATANT,
                'premoderation'     => '1',
                'expectErrorKey'    => 'spam_message_rejected',
                'expectCommentSent' => false,
                'expectShown'       => 0,
                'expectMail'        => false,
                'mailStatus'        => SpamDetectorReport::STATUS_BLATANT,
                'mailPublished'     => false,
            ],

            // Blatant spam with link + HTML → reject because of link check
            [
                'text'              => $this->composeText(true, true),
                'status'            => SpamDetectorReport::STATUS_BLATANT,
                'premoderation'     => '0',
                'expectErrorKey'    => 'links_in_text',
                'expectCommentSent' => false,
                'expectShown'       => 0,
                'expectMail'        => false,
                'mailStatus'        => SpamDetectorReport::STATUS_BLATANT,
                'mailPublished'     => false,
            ],
            [
                'text'              => $this->composeText(true, true),
                'status'            => SpamDetectorReport::STATUS_BLATANT,
                'premoderation'     => '1',
                'expectErrorKey'    => 'links_in_text',
                'expectCommentSent' => false,
                'expectShown'       => 0,
                'expectMail'        => false,
                'mailStatus'        => SpamDetectorReport::STATUS_BLATANT,
                'mailPublished'     => false,
            ],
        ];
    }

    private function commentData(string $text): array
    {
        $key = str_repeat('a', 21);
        $key[10] = '1';
        $key[12] = '2';
        $key[20] = '3';

        return [
            'name'     => 'Tester',
            'email'    => 'tester@example.com',
            'text'     => $text,
            'key'      => $key,
            'question' => '15',
        ];
    }

    private function commentCount(\IntegrationTester $I): int
    {
        $pdo   = $I->grabService(\PDO::class);
        $count = $pdo->query('SELECT COUNT(*) FROM art_comments')->fetchColumn();

        return (int)$count;
    }

    private function assertLastComment(\IntegrationTester $I, int $shown, int $totalStart): void
    {
        $pdo    = $I->grabService(\PDO::class);
        $result = $pdo->query('SELECT shown FROM art_comments ORDER BY id DESC LIMIT 1')->fetchColumn();

        $I->assertEquals($totalStart + 1, $this->commentCount($I));
        $I->assertEquals($shown, (int)$result);
    }

    private function composeText(bool $link, bool $html): string
    {
        $textParts = ['Simple text'];
        if ($html) {
            $textParts[] = '<b>bold</b>';
        }
        if ($link) {
            $textParts[] = 'http://example.com';
        }

        return implode(' ', $textParts);
    }
}
