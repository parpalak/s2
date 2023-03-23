<?php declare(strict_types=1);

class InstallCest
{
    public function _before(AcceptanceTester $I)
    {
    }

    // tests
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

        $this->testComment($I);
        $this->testAdminLogin($I);
        $this->testAdminCommentList($I);
    }

    private function testComment(AcceptanceTester $I): void
    {
        $I->fillField('name', 'Roman');
        $I->fillField('email', 'roman@example.com');
        $I->fillField('text', 'This is my first comment!');
        $text = $I->grabTextFrom('p#qsp');
        preg_match('#(\d\d)\+(\d)#', $text, $matches);
        $I->fillField('question', $matches[1] + $matches[2]);
        $I->click('submit');

        $I->seeResponseCodeIs(200);
        $I->see('Roman wrote:');
        $I->see('This is my first comment!');
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
        $I->amOnPage('/_admin/site_ajax.php?action=load_tags');
        $I->see('Tag');
        $I->see('Modified');
    }
}
