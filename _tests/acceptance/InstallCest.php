<?php

declare(strict_types=1);

use Codeception\Example;

class InstallCest
{
    protected function configProvider(): array
    {
        return [
            ['db_type' => 'mysql', 'db_user' => 'root', 'db_password' => ''],
            ['db_type' => 'sqlite', 'db_user' => '', 'db_password' => ''],
            ['db_type' => 'pgsql', 'db_user' => 'postgres', 'db_password' => '12345'],
        ];
    }

    /**
     * @throws JsonException
     */
    public function runTest(AcceptanceTester $I): void
    {
        $dbType = getenv('APP_DB_TYPE');
        foreach ($this->configProvider() as $config) {
            if (!is_string($dbType) || $dbType === $config['db_type']) {
                $file = __DIR__ . '/../../config.test.php';
                if (file_exists($file)) {
                    unlink($file);
                }

                $this->tryToTest($I, new Example($config));
            }
        }
    }

    /**
     * @throws JsonException
     */
    protected function tryToTest(AcceptanceTester $I, Example $example): void
    {
        if (file_exists('config.test.php')) {
            throw new Exception('config.test.php must not exist for test run');
        }

        $I->install('admin', 'passwd', $example['db_type'], $example['db_user'], $example['db_password']);

        $I->amOnPage('/');
        $I->see('Site powered by S2');
        $I->click(['link' => 'Page 1']);
        $I->see('If you see this text, the install of S2 has been successfully completed.');
        $I->canWriteComment();

        $this->testAdminLogin($I);
        $this->testAdminEditAndTagsAdded($I);
        $this->testBlogExtension($I);
        $this->testSearchExtension($I);
        $this->testAdminTagListAndEdit($I);
        $this->testAdminCommentManagement($I);
    }

    private function testAdminLogin(AcceptanceTester $I): void
    {
        $I->login('admin', 'no-pass');
        $I->see('You have entered incorrect username or password.');

        $I->login('admin', 'passwd');
        $I->dontSee('You have entered incorrect username or password.');

        $I->amOnPage('/---');
        $I->see('👤 admin');
    }

    /**
     * @throws JsonException
     */
    private function testAdminEditAndTagsAdded(AcceptanceTester $I): void
    {
        $I->amOnPage('/tags/tag1');
        $I->seeResponseCodeIsClientError();
        $I->amOnPage('/tags/another tag');
        $I->seeResponseCodeIsClientError();
        $I->amOnPage('/_admin/site_ajax.php?action=load_tags');
        $I->dontSee('another tag');

        $I->sendAjaxGetRequest('/_admin/site_ajax.php?action=load&id=3');
        $data = json_decode($I->grabPageSource(), true, 512, JSON_THROW_ON_ERROR);
        $I->assertArrayHasKey('form', $data);
        $I->assertStringContainsString('If you see this text, the install of S2 has been successfully completed.', $data['form']);

        $dataProvider = static function (string $id) {
            return [
                'page'  => [
                    'title'       => 'New Page Title',
                    'meta_keys'   => 'New Meta Keywords',
                    'meta_desc'   => 'New Meta Description',
                    'excerpt'     => 'New Excerpt',
                    'tags'        => 'tag1, another tag',
                    'create_time' => [
                        'hour' => '11',
                        'min'  => '32',
                        'day'  => '10',
                        'mon'  => '08',
                        'year' => '2023',
                    ],
                    'modify_time' => [
                        'hour' => '12',
                        'min'  => '15',
                        'day'  => '11',
                        'mon'  => '08',
                        'year' => '2023',
                    ],
                    'text'        => '<p>Some new page text</p>',
                    'id'          => $id,
                    'revision'    => '1',
                    'user_id'     => '0',
                    'template'    => 'site.php',
                    'url'         => 'new_page1',
                ],
                'flags' => [
                    'favorite'  => '1',
                    'published' => '1',
                    'commented' => '1',
                ],
            ];
        };
        $I->sendAjaxPostRequest('/_admin/site_ajax.php?action=save', $dataProvider('333'));
        $I->see('Item not found!');

        for ($i = 0; $i < 2; $i++) {
            // 2-nd iteration checks that consequent saving of the same entity works fine
            $I->sendAjaxPostRequest('/_admin/site_ajax.php?action=save', $dataProvider('3'));
            $I->seeResponseCodeIsSuccessful();
            $I->dontSee('Warning! An error occurred during page saving. Copy the content to a text editor and save into a file out of caution.');
            $I->see('{"revision":2,"status":"ok","url_status":"ok"}');
        }

        $I->sendAjaxGetRequest('/_admin/site_ajax.php?action=load_tree');
        $I->see('Error in GET parameters.');
        $I->sendAjaxGetRequest('/_admin/site_ajax.php?action=load_tree&id=0&search=');
        $I->assertStringContainsString('New Page Title', $I->grabPageSource());

        $I->amOnPage('/section1/page1');
        $I->seeResponseCodeIsClientError();

        $I->amOnPage('/section1/new_page1');
        $I->see('Some new page text');
        $I->see('August 10, 2023');

        $I->amOnPage('/section1/');
        $I->see('New Excerpt');

        $I->amOnPage('/tags/tag1');
        $I->seeResponseCodeIsSuccessful();
        $I->amOnPage('/tags/another tag');
        $I->seeResponseCodeIsSuccessful();
        $I->amOnPage('/_admin/site_ajax.php?action=load_tags');
        $I->see('another tag');
    }

    /**
     * @throws JsonException
     */
    private function testBlogExtension(AcceptanceTester $I): void
    {
        $I->installExtension('s2_blog');
        $I->sendAjaxGetRequest('/_admin/site_ajax.php?action=create_blog_post');
        $I->seeResponseCodeIsSuccessful();
        $postId = $I->grabPageSource();
        $I->assertEquals(1, $postId, 'If postId is empty probably hooks are not applied due to lack of opcache invalidation.');

        $I->amOnPage('/tags/blog tag');
        $I->seeResponseCodeIsClientError();

        $I->sendAjaxGetRequest('/_admin/site_ajax.php?action=edit_blog_post&id=' . $postId);
        $data = json_decode($I->grabPageSource(), true, 512, JSON_THROW_ON_ERROR);
        $I->assertArrayHasKey('form', $data);

        $dataProvider = static function (string $id) {
            return [
                'page'  => [
                    'title'       => 'New Blog Post Title',
                    'tags'        => 'tag1, blog tag',
                    'create_time' => [
                        'hour' => '11',
                        'min'  => '32',
                        'day'  => '12',
                        'mon'  => '08',
                        'year' => '2023',
                    ],
                    'modify_time' => [
                        'hour' => '12',
                        'min'  => '15',
                        'day'  => '12',
                        'mon'  => '08',
                        'year' => '2023',
                    ],
                    'text'        => '<p>New blog post</p>',
                    'user_id'     => '0',
                    'label'       => '',
                    'id'          => $id,
                    'revision'    => '1',
                    'template'    => 'site.php',
                    'url'         => 'new_post1',
                ],
                'flags' => [
                    'commented' => '1',
                    'published' => '1',
                ],
            ];
        };
        $I->sendAjaxPostRequest('/_admin/site_ajax.php?action=save_blog', $dataProvider($postId . '0'));
        $I->see('Item not found!');

        $I->sendAjaxPostRequest('/_admin/site_ajax.php?action=save_blog', $dataProvider($postId));
        $I->seeResponseCodeIsSuccessful();
        $I->dontSee('Warning! An error occurred during page saving. Copy the content to a text editor and save into a file out of caution.');
        $I->see('{"revision":2,"status":"ok","url_status":"ok"}');

        $I->sendAjaxGetRequest('/_admin/site_ajax.php?action=edit_blog_post&id=' . $postId);
        $data = json_decode($I->grabPageSource(), true, 512, JSON_THROW_ON_ERROR);
        $I->assertArrayHasKey('form', $data);
        $I->assertStringContainsString('New blog post', $data['form']);

        $I->sendAjaxPostRequest('/_admin/site_ajax.php?action=load_blog_posts', [
            'posts' => [
                'start_time' => '',
                'text'       => '',
                'end_time'   => '',
                'key'        => '',
                'author'     => '',
                'hidden'     => '',
            ]
        ]);
        $I->canSee('2023-08-12');
        $I->see('New Blog Post Title');

        foreach (['/blog/2023/08/12/', '/blog/2023/08/', '/blog/'] as $url) {
            $I->amOnPage($url);
            $I->see('New Blog Post Title');
            $I->see('New blog post');
            $I->see('August 12, 2023');
        }

        $I->amOnPage('/blog/2023/08/12/new_post1');
        $I->see('New Blog Post Title');
        $I->see('New blog post');
        $I->see('August 12, 2023');
        $I->canWriteComment();

        $I->amOnPage('/tags/blog tag');
        $I->seeResponseCodeIsSuccessful();
    }

    private function testSearchExtension(AcceptanceTester $I): void
    {
        $I->amOnPage('/?search=1&q=new');
        $I->dontSee('Search', 'h1');

        $I->installExtension('s2_search');

        $I->amOnPage('/?search=1&q=new');
        $I->see('Search', 'h1');
        $I->dontSee('New Blog Post Title');
        $I->dontSee('New Page Title');

        $I->sendAjaxGetRequest('/_admin/site_ajax.php?action=s2_search_makeindex');
        $I->see('go_20');
        $I->sendAjaxGetRequest('/_admin/site_ajax.php?action=s2_search_makeindex');
        $I->see('stop');

        $I->amOnPage('/?search&q=new');
        $I->see('Search', 'h1');
        $I->see('New Blog Post Title');
        $I->see('New Page Title');

        // todo save and check indexing
        // todo recommendations
    }

    private function testAdminTagListAndEdit(AcceptanceTester $I): void
    {
        $I->amOnPage('/tags/tag1');
        $I->seeResponseCodeIsSuccessful();

        $I->amOnPage('/_admin/site_ajax.php?action=load_tags');
        $I->see('Tag');
        $I->see('Modified');
        $I->see('another tag');

        $tagId = '1';
        $I->amOnPage('/_admin/site_ajax.php?action=load_tag&id=' . $tagId);
        $dataProvider = static function (string $id) {
            return [
                'tag'   => [
                    'name'        => 'New Tag Name',
                    'modify_time' => [
                        'hour' => '12',
                        'min'  => '15',
                        'day'  => '12',
                        'mon'  => '08',
                        'year' => '2023',
                    ],
                    'description' => 'New tag description text',
                    'url'         => 'new_tag_url1',
                    'id'          => $id,
                ],
                'flags' => [
                    'commented' => '1',
                    'published' => '1',
                ],
            ];
        };
        $I->sendAjaxPostRequest('/_admin/site_ajax.php?action=save_tag', $dataProvider($tagId . '000'));
        $I->see('Item not found!');
        $I->sendAjaxPostRequest('/_admin/site_ajax.php?action=save_tag', $dataProvider($tagId));
        $I->see('New tag description text');

        $I->amOnPage('/tags/tag1');
        $I->seeResponseCodeIsClientError();

        $I->amOnPage('/tags/new_tag_url1');
        $I->seeResponseCodeIsSuccessful();
        $I->see('New tag description text');
    }

    private function testAdminCommentManagement(AcceptanceTester $I): void
    {
        $I->sendAjaxPostRequest('/_admin/site_ajax.php?action=load_last_comments');
        $I->see('This is my first comment!');

        $data = [
            'opt' => [
                'S2_SITE_NAME'        => 'Site Title',
                'S2_WEBMASTER'        => 'Webmaster Name',
                'S2_WEBMASTER_EMAIL'  => 'webmaster@example.com',
                'S2_START_YEAR'       => '2023',
                'S2_COMPRESS'         => '1',
                'S2_FAVORITE_URL'     => 'favorite',
                'S2_TAGS_URL'         => 'keywords',
                'S2_USE_HIERARCHY'    => '1',
                'S2_MAX_ITEMS'        => '0',
                'style'               => 'zeta',
                'lang'                => 'English',
                'S2_BLOG_TITLE'       => 'Blog Title',
                'S2_BLOG_URL'         => '/blog',
                'S2_SHOW_COMMENTS'    => '1',
                'S2_ENABLED_COMMENTS' => '1',
                'S2_PREMODERATION'    => '1',
                'S2_ADMIN_COLOR'      => '#e7e4f4',
                'S2_LOGIN_TIMEOUT'    => '120000',
                'S2_ADMIN_UPDATES'    => '0',
            ]
        ];

        $I->sendAjaxPostRequest('/_admin/site_ajax.php?action=save_options', $data);
        $I->seeResponseCodeIsSuccessful();

        $I->sendAjaxGetRequest('/_admin/site_ajax.php?action=user_set_email&login=admin&email=admin@example.com');
        $I->seeResponseCodeIsSuccessful();
        $I->see('admin@example.com');

        $I->clearEmail();

        $I->amOnPage('/section1/new_page1');
        $I->see('Some new page text');
        $I->canWriteComment(true);

        $emails = $I->getEmails();
        $I->assertCount(1, $emails);

        // Two asserts to skip variable "Date" header
        $I->assertStringContainsString('To: admin@example.com' . "\r\n" .
            'Subject: =?UTF-8?B?Q29tbWVudCB0byBodHRwOi8vbG9jYWxob3N0Ojg4ODEvaW5kZXgucGhwPy9zZWN0aW9uMS9uZXdfcGFnZTE=?=' . "\r\n" .
            'From: =?UTF-8?B?V2VibWFzdGVyIE5hbWU=?= <webmaster@example.com>' . "\r\n" .
            'Sender: =?UTF-8?B?Um9tYW4g8J+Mng==?= <roman@example.com>' . "\r\n" .
            'Date: ', $emails[0]);

        $I->assertStringContainsString(' +0000' . "\r\n" .
            'MIME-Version: 1.0' . "\r\n" .
            'Content-transfer-encoding: 8bit' . "\r\n" .
            'Content-type: text/plain; charset=utf-8' . "\r\n" .
            'X-Mailer: S2 Mailer' . "\r\n" .
            'Reply-To: =?UTF-8?B?Um9tYW4g8J+Mng==?= <roman@example.com>' . "\r\n" .
            '' . "\r\n" .
            'Hello, admin.' . "\r\n" .
            '' . "\r\n" .
            'You have received this e-mail, because you are the moderator.' . "\r\n" .
            'A new comment on' . "\r\n" .
            '“New Page Title”,' . "\r\n" .
            'has been received. You can find it here:' . "\r\n" .
            'http://localhost:8881/index.php?/section1/new_page1' . "\r\n" .
            '' . "\r\n" .
            'Roman 🌞 is the comment author.' . "\r\n" .
            '' . "\r\n" .
            '----------------------------------------------------------------------' . "\r\n" .
            'This is my first comment! 👪🐶' . "\r\n" .
            '----------------------------------------------------------------------' . "\r\n" .
            '' . "\r\n" .
            'This e-mail has been sent automatically. If you reply, the author' . "\r\n" .
            'of the comment will receive your answer.' . "\r\n" .
            '', $emails[0]);

        // TODO check showing and hiding comments
        // TODO check deleting comments
    }
}
