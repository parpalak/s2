<?php
/**
 * @copyright 2023-2024 Roman Parpalak
 * @license   http://opensource.org/licenses/MIT MIT
 * @package   S2
 */

declare(strict_types=1);

namespace acceptance;

use AcceptanceTester;
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
     * @throws \JsonException
     */
    public function runTest(AcceptanceTester $I): void
    {
        $dbType = getenv('APP_DB_TYPE');
        foreach ($this->configProvider() as $config) {
            if (!\is_string($dbType) || $dbType === $config['db_type']) {
                $file = __DIR__ . '/../../config.test.php';
                if (file_exists($file)) {
                    unlink($file);
                }

                $this->tryToTest($I, new Example($config));
            }
        }
    }

    /**
     * @throws \JsonException
     */
    protected function tryToTest(AcceptanceTester $I, Example $example): void
    {
        if (file_exists('config.test.php')) {
            throw new \Exception('config.test.php must not exist for test run');
        }

        $I->install('admin', 'passwd', $example['db_type'], $example['db_user'], $example['db_password']);

        $I->amOnPage('/');
        $I->see('Site powered by S2');
        $I->click(['link' => 'Page 1']);
        $I->see('If you see this text, the install of S2 has been successfully completed.');
        $I->canWriteComment();

        $this->testHierarchyRedirects($I);
        $this->testAdminLogin($I);
        $this->testAdminEditAndTagsAdded($I);
        $this->testTagsPage($I);
        $this->testFavoritePage($I);
        $this->testBlogExtension($I);
        $this->testBlogRssAndSitemap($I);
        $this->testSearchExtension($I);
        $this->testAdminAddArticles($I);
        $this->testRssAndSitemap($I);
        $this->testAdminTagListAndEdit($I);
        $this->testAdminCommentManagement($I);
    }

    private function testHierarchyRedirects(AcceptanceTester $I): void
    {
        $I->stopFollowingRedirects();
        $I->amOnPage('/section1');
        $I->seeResponseCodeIs(301);
        $I->followRedirect();
        $I->seeCurrentUrlEquals('/index.php?/section1/');
        $I->amOnPage('/section1/page1/');
        $I->seeResponseCodeIs(301);
        $I->followRedirect();
        $I->seeCurrentUrlEquals('/index.php?/section1/page1');
        $I->startFollowingRedirects();
    }

    private function testAdminLogin(AcceptanceTester $I): void
    {
        $I->login('admin', 'no-pass');
        $I->seeResponseCodeIs(401);
        $I->see('You have entered incorrect username or password.');

        $I->login('admin', 'passwd');
        $I->seeResponseCodeIs(200);
        $I->dontSee('You have entered incorrect username or password.');

        $I->amOnPage('/---');
        $I->see('👤 admin');
    }

    /**
     * @throws \JsonException
     */
    private function testAdminEditAndTagsAdded(AcceptanceTester $I): void
    {
        $I->amOnPage('/tags/tag1');
        $I->seeResponseCodeIsClientError();
        $I->amOnPage('/tags/another tag');
        $I->seeResponseCodeIsClientError();
        $I->amOnPage('/_admin/index.php?entity=Tag&action=list');
        $I->dontSee('another tag');

        $I->amOnPage('/_admin/index.php?entity=Article&action=edit&id=3');
        $I->assertStringContainsString('If you see this text, the install of S2 has been successfully completed.', $I->grabValueFrom('textarea[name=pagetext]'));

        $dataProvider = static function (string $csrfToken) {
            return [
                '__csrf_token' => $csrfToken,
                'title'        => 'New Page Title',
                'meta_keys'    => 'New Meta Keywords',
                'meta_desc'    => 'New Meta Description',
                'excerpt'      => 'New Excerpt',
                'tags'         => 'tag1, another tag',
                'create_time'  => '2023-08-10T11:32',
                'modify_time'  => '2023-08-11T12:15',
                'pagetext'     => '<p>Some new page text</p>',
                'revision'     => '1',
                'user_id'      => '1',
                'template'     => 'site.php',
                'url'          => 'new_page1',
                'favorite'     => '1',
                'published'    => '1',
                'commented'    => '1',
            ];
        };
        $csrfToken    = $I->grabValueFrom('input[name=__csrf_token]');
        $I->sendAjaxPostRequest('/_admin/index.php?entity=Article&action=edit&id=333', $dataProvider($csrfToken));
        $this->assertJsonResponseContains($I, ['errors', 0], 'Unable to confirm security token.');

        for ($i = 0; $i < 2; $i++) {
            // 2-nd iteration checks that consequent saving of the same entity works fine
            $I->sendAjaxPostRequest('/_admin/index.php?entity=Article&action=edit&id=3', $dataProvider($csrfToken));
            $I->seeResponseCodeIsSuccessful();
            $I->dontSee('Warning! An error occurred during page saving. Copy the content to a text editor and save into a file out of caution.');
            $I->see('"success":true');
            $I->see('"urlStatus":"ok"');
            $I->see('"urlTitle":""');
            $I->see('"revision":"2"');
        }

        $I->sendAjaxGetRequest('/_admin/ajax.php?action=load_tree');
        $I->see('{"success":false,"message":"Parameter \u0022id\u0022 is required."}');
        $I->sendAjaxGetRequest('/_admin/ajax.php?action=load_tree&id=0&search=');
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
        $I->amOnPage('/_admin/index.php?entity=Tag&action=list');
        $I->see('another tag');
    }

    private function testAdminAddArticles(AcceptanceTester $I): void
    {
        foreach ([4, 5] as $newId) {
            $I->sendAjaxGetRequest('/_admin/ajax.php?action=create&id=2&title=New+page+' . $newId);
            $data = json_decode($I->grabPageSource(), true, 512, JSON_THROW_ON_ERROR);
            $I->assertArrayHasKey('success', $data);
            $I->assertTrue($data['success']);
            $I->assertArrayHasKey('id', $data);
            $I->assertEquals($newId, $data['id']);

            $I->amOnPage('/_admin/index.php?entity=Article&action=edit&id=' . $newId);
            $csrfToken = $I->grabValueFrom('input[name=__csrf_token]');

            $dataProvider = static function (string $id, string $csrfToken) {
                return [
                    '__csrf_token' => $csrfToken,
                    'title'        => 'New Page ' . $id,
                    'meta_keys'    => 'New Meta Keywords',
                    'meta_desc'    => 'New Meta Description',
                    'excerpt'      => 'New Excerpt',
                    'tags'         => 'tag1, another tag',
                    'create_time'  => '2023-08-10T11:32',
                    'modify_time'  => '2023-08-12T12:15',
                    'pagetext'     => '<p>Some new page text</p>',
                    'revision'     => '1',
                    'user_id'      => '1',
                    'template'     => 'site.php',
                    'url'          => 'new_page' . $id,
                    'favorite'     => '1',
                    'published'    => '1',
                    'commented'    => '1',
                ];
            };

            $I->sendAjaxPostRequest('/_admin/index.php?entity=Article&action=edit&id=' . $newId, $dataProvider((string)$newId, $csrfToken));
            $I->seeResponseCodeIsSuccessful();
            $I->see('"success":true');
            $I->see('"urlStatus":"ok"');
            $I->see('"urlTitle":""');
            $I->see('"revision":"2"');
        }

        // Links to related pages in section and by tags
        $I->amOnPage('/section1/new_page4');
        $I->see('New Page 4', 'h1');
        $I->see('Some new page text', '#content');

        $I->see('More in the section “Section 1”', '.header.menu_siblings');
        $I->see('New Page Title', '.menu_siblings a');
        $I->see('New Page 4', '.menu_siblings span');

        $I->see('On the subject “tag1”', '.header.article_tags');
        $I->see('New Page Title', '.article_tags a');
        $I->see('New Page 4', '.article_tags span');

        $I->see('See in blog', '.header.s2_blog_tags');
        $I->see('tag1', '.s2_blog_tags a');

        // Links to sub-pages
        $I->amOnPage('/section1/');
        $I->see('In this section', '.header.menu_children');
        $I->see('New Page Title', '.menu_children a');
        $I->see('New Page 4', '.menu_children a');

        $I->see('New Page Title', 'h3.subsection');
        $I->see('New Excerpt', 'p.subsection');
    }

    private function testTagsPage(AcceptanceTester $I): void
    {
        $I->stopFollowingRedirects();
        $I->amOnPage('/tags');
        $I->seeResponseCodeIs(301);
        $I->startFollowingRedirects();
        $I->amOnPage('/tags/');
        $I->see('tag1');
        $I->see('another tag');
    }

    private function testFavoritePage(AcceptanceTester $I): void
    {
        $I->stopFollowingRedirects();
        $I->amOnPage('/favorite');
        $I->seeResponseCodeIs(301);
        $I->startFollowingRedirects();
        $I->amOnPage('/favorite/');
        $I->see('New Excerpt');
    }

    private function testRssAndSitemap(AcceptanceTester $I): void
    {
        $I->amOnPage('/index.php?/rss.xml'); // Other URL scheme because the built-in PHP server looks for a file rss.xml
        $I->seeResponseCodeIsSuccessful();
        $I->canSee('Site powered by S2');
        $I->canSee('New Page Title');
        $I->canSee('New Page 4');
        $I->canSee('New Page 5');
        $I->canSee('/section1/new_page1');
        $I->canSee(gmdate('D, d M Y H:i:s', strtotime('2023-08-10 11:32:00')) . ' GMT');
        $I->see('New Excerpt');

        $I->haveHttpHeader('If-Modified-Since', 'Sat, 12 Aug 2023 00:00:00 GMT');
        $I->amOnPage('/index.php?/rss.xml');
        $I->dontSee('New Page Title'); // Modified before this date, skip in output
        $I->see('New Page 4');
        $I->see('New Page 5');


        $I->amOnPage('/index.php?/sitemap.xml'); // Same as above
        $I->seeResponseCodeIsSuccessful();
        $I->see('/section1/new_page1');
        $I->see(gmdate('c', strtotime('2023-08-11 12:15')));
    }

    /**
     * @throws \JsonException
     */
    private function testBlogExtension(AcceptanceTester $I): void
    {
        $I->installExtension('s2_blog');

        $I->amOnPage('/blog/tags/blog tag');
        $I->seeResponseCodeIsClientError();

        $I->amOnPage('/_admin/index.php?entity=BlogPost&action=new');
        $I->submitForm('form', [
            'title' => 'New Blog Post Title',
            'text'  => '<p>Start text</p>',
        ]);
        $I->seeResponseCodeIsSuccessful();
        $postId    = $I->grabFromCurrentUrl('~id=(\d+)~');
        $csrfToken = $I->grabValueFrom('input[name=__csrf_token]');

        $dataProvider = static function (string $csrfToken) {
            return [
                '__csrf_token' => $csrfToken,
                'title'        => 'New Blog Post Title',
                'tags'         => 'tag1, blog tag',
                'create_time'  => '2023-08-12T11:32',
                'modify_time'  => '2023-08-12T12:15',
                'text'         => '<p>New blog post</p>',
                'user_id'      => '1',
                'label'        => '',
                'revision'     => '1',
                'template'     => 'site.php',
                'url'          => 'new_post1',

                'commented' => '1',
                'published' => '1',
            ];
        };
        $I->sendAjaxPostRequest('/_admin/index.php?entity=BlogPost&action=edit&id=333', $dataProvider($csrfToken));
        $this->assertJsonResponseContains($I, ['errors', 0], 'Unable to confirm security token.');

        $I->sendAjaxPostRequest('/_admin/index.php?entity=BlogPost&action=edit&id=' . $postId, $dataProvider($csrfToken));
        $I->seeResponseCodeIsSuccessful();
        $I->see('"success":true');
        $I->see('"urlStatus":"ok"');
        $I->see('"urlTitle":""');
        $I->see('"revision":"2"');

        $I->amOnPage('/_admin/index.php?entity=BlogPost&action=edit&id=' . $postId);
        $postText = $I->grabValueFrom('textarea[name=text]');
        $I->assertStringContainsString('New blog post', $postText);

        $I->amOnPage('/_admin/index.php?entity=BlogPost&action=list');
        $I->canSee('2023-08-12');
        $I->see('New Blog Post Title');

        foreach (['/blog/2023/08/12/', '/blog/2023/08/'] as $url) {
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

        $I->stopFollowingRedirects();

        $I->amOnPage('/blog/tags/blog tag');
        $I->seeResponseCodeIs(301);
        $I->followRedirect();
        $I->seeCurrentUrlEquals('/index.php?/blog/tags/blog+tag/');
        $I->seeResponseCodeIsSuccessful();

        $I->amOnPage('/blog');
        $I->seeResponseCodeIs(301);
        $I->followRedirect();
        $I->seeCurrentUrlEquals('/index.php?/blog/');
        $I->seeResponseCodeIsSuccessful();
        $I->see('New Blog Post Title');
        $I->see('New blog post');
        $I->see('August 12, 2023');

        $I->startFollowingRedirects();
    }

    private function testBlogRssAndSitemap(AcceptanceTester $I): void
    {
        $I->amOnPage('/index.php?/blog/rss.xml'); // Other URL scheme because the built-in PHP server looks for a file rss.xml
        $I->seeResponseCodeIsSuccessful();
        $I->canSee('My blog');
        $I->canSee('New Blog Post Title');
        $I->canSee('/blog/2023/08/12/new_post1');
        $I->canSee(gmdate('D, d M Y H:i:s', strtotime('2023-08-12 11:32:00')) . ' GMT');
        $I->see('New blog post');

        $I->amOnPage('/index.php?/blog/sitemap.xml'); // Same as above
        $I->seeResponseCodeIsSuccessful();
        $I->see('/blog/2023/08/12/new_post1');
        $I->see(gmdate('c', strtotime('2023-08-12 12:15')));
    }

    private function testSearchExtension(AcceptanceTester $I): void
    {
        $I->amOnPage('/?search=1&q=new');
        $I->dontSee('Search', 'h1');

        $I->installExtension('s2_search');

        $I->amOnPage('/section1/new_page1');
        $I->submitForm('.s2_search_form', ['q' => 'new']);
        $I->seeCurrentUrlEquals('/index.php?search=1&q=new');
        $I->see('Search', 'h1');
        $I->dontSee('New Blog Post Title');
        $I->dontSee('New Page Title');

        $I->sendAjaxGetRequest('/_admin/ajax.php?action=s2_search_makeindex');
        $I->see('go_20');
        $I->sendAjaxGetRequest('/_admin/ajax.php?action=s2_search_makeindex');
        $I->see('stop');

        $I->amOnPage('/blog/2023/08/12/new_post1');
        $I->submitForm('.s2_search_form', ['q' => 'new']);
        $I->seeCurrentUrlEquals('/index.php?search=1&q=new');
        $I->see('Search', 'h1');
        $I->see('New Blog Post Title');
        $I->see('New Page Title');

        // todo save and check indexing
        // todo recommendations
    }

    /**
     * @throws \JsonException
     */
    private function testAdminTagListAndEdit(AcceptanceTester $I): void
    {
        $I->amOnPage('/tags/tag1');
        $I->seeResponseCodeIsSuccessful();

        $I->amOnPage('/_admin/index.php?entity=Tag&action=list');
        $I->see('Tag');
        $I->see('another tag');

        $tagId = '1';
        $I->amOnPage('/_admin/index.php?entity=Tag&action=edit&tag_id=' . $tagId);
        $dataProvider = static function (string $csrfToken) {
            return [
                '__csrf_token' => $csrfToken,
                'name'         => 'New Tag Name',
                'modify_time'  => '2023-08-12T12:15',
                'description'  => 'New tag description text',
                'url'          => 'new_tag_url1',

                'commented' => '1',
                'published' => '1',
            ];
        };
        $csrfToken    = $I->grabValueFrom('input[name=__csrf_token]');
        $I->sendAjaxPostRequest('/_admin/index.php?entity=Tag&action=edit&tag_id=1111' . $tagId, $dataProvider($csrfToken));
        $this->assertJsonResponseContains($I, ['errors', 0], 'Unable to confirm security token.');

        $I->sendAjaxPostRequest('/_admin/index.php?entity=Tag&action=edit&tag_id=' . $tagId, $dataProvider($csrfToken));
        $I->see('{"success":true}');

        $I->amOnPage('/tags/tag1');
        $I->seeResponseCodeIsClientError();

        $I->amOnPage('/tags/new_tag_url1');
        $I->seeResponseCodeIsSuccessful();
        $I->see('New tag description text');
    }

    private function testAdminCommentManagement(AcceptanceTester $I): void
    {
        $I->sendAjaxPostRequest('/_admin/index.php?entity=Comment&action=list');
        $I->see('This is my first comment!');

        $I->changeSetting('S2_PREMODERATION', true);
        $I->changeSetting('S2_WEBMASTER_EMAIL', 'webmaster@example.com');
        $I->changeSetting('S2_WEBMASTER', 'Webmaster Name');

        $I->amOnPage('/_admin/index.php?entity=User&action=list');
        $I->seeResponseCodeIsSuccessful();
        $I->submitForm('form[action="?entity=User&action=patch&field=email&id=' . 1 . '"]', [
            'email' => 'admin@example.com',
        ]);
        $I->seeResponseCodeIsSuccessful();
        $I->see('{"success":true}');

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

        // Disable comments
        $I->changeSetting('S2_SHOW_COMMENTS', false);
        $I->changeSetting('S2_ENABLED_COMMENTS', false);

        // Test <!-- s2_last_comments --> and <!-- s2_last_discussions --> placeholders when comments are disabled
        $I->amOnPage('/index.php?/');
        $I->seeResponseCodeIsSuccessful();

        // Check conditional get when the comment form is disabled. Otherwise, there are some random tokens.
        // Last comments must be also hidden.
        $I->amOnPage('/index.php?/section1/new_page1');
        $headers = $I->grabHeaders();
        $I->haveHttpHeader('If-None-Match', $headers['ETag'][0]);
        $I->amOnPage('/index.php?/section1/new_page1');
        $I->seeResponseCodeIs(304);
    }

    private function assertJsonResponseContains(AcceptanceTester $I, array $path, string $needle): void
    {
        $response = json_decode($I->grabPageSource(), true, 512, JSON_THROW_ON_ERROR);
        foreach ($path as $value) {
            $I->assertArrayHasKey($value, $response);
            $response = $response[$value];
        }
        $I->assertStringContainsString($needle, $response);
    }
}
