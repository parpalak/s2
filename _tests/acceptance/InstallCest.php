<?php

declare(strict_types=1);

class InstallCest
{
    /**
     * @throws Exception
     */
    public function _before(AcceptanceTester $I)
    {
    }

    /**
     * @throws JsonException
     */
    public function tryToTest(AcceptanceTester $I): void
    {
        if (file_exists('config.php')) {
            throw new Exception('config.php must not exist for test run');
        }

        $I->install('admin', 'passwd');

        $I->amOnPage('/');
        $I->see('Site powered by S2');
        $I->click(['link' => 'Page 1']);
        $I->see('If you see this text, the install of S2 has been successfully completed.');
        $I->canWriteComment();

        $this->testAdminLogin($I);
        $this->testAdminCommentList($I);
        $this->testAdminEditAndTagsAdded($I);
        $this->testBlogExtension($I);
        $this->testSearchExtension($I);
        $this->testAdminTagListAndEdit($I);
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

    private function testAdminCommentList(AcceptanceTester $I): void
    {
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
                ],
            ];
        };
        $I->sendAjaxPostRequest('/_admin/site_ajax.php?action=save', $dataProvider('333'));
        $I->see('Item not found!');

        $I->sendAjaxPostRequest('/_admin/site_ajax.php?action=save', $dataProvider('3'));
        $I->seeResponseCodeIsSuccessful();
        $I->dontSee('Warning! An error occurred during page saving. Copy the content to a text editor and save into a file out of caution.');
        $I->see('{"revision":2,"status":"ok","url_status":"ok"}');

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
                'tag'  => [
                    'name'       => 'New Tag Name',
                    'modify_time' => [
                        'hour' => '12',
                        'min'  => '15',
                        'day'  => '12',
                        'mon'  => '08',
                        'year' => '2023',
                    ],
                    'description'        => 'New tag description text',
                    'url'         => 'new_tag_url1',
                    'id'         => $id,
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
}
