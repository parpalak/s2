<?php

use Codeception\Actor;


/**
 * Inherited Methods
 * @method void wantToTest($text)
 * @method void wantTo($text)
 * @method void execute($callable)
 * @method void expectTo($prediction)
 * @method void expect($prediction)
 * @method void amGoingTo($argumentation)
 * @method void am($role)
 * @method void lookForwardTo($achieveValue)
 * @method void comment($description)
 * @method void pause()
 *
 * @SuppressWarnings(PHPMD)
 */
class AcceptanceTester extends Actor
{
    use _generated\AcceptanceTesterActions;

    /**
     * Define custom actions here
     */

    public function install(string $userName, string $userPass, string $dbType, string $dbUser, string $dbPassword): void
    {
        $I = $this;
        $I->amOnPage('/');
        $I->seeLink('install S2', '/_admin/install.php');
        $I->amOnPage('/_admin/install.php');
        $I->seeResponseCodeIs(200);
        $I->see('S2 2.0dev', 'h1');

        $I->selectOption('req_db_type', $dbType);
        $I->fillField('req_db_name', 's2_test');
        $I->fillField('db_username', $dbUser);
        $I->fillField('db_password', $dbPassword);
        $I->fillField('req_username', $userName);
        $I->fillField('req_password', $userPass);
        $I->click('start');
        $I->canSeeResponseCodeIs(200);
        $I->see('S2 is completely installed!');
    }

    public function canWriteComment(bool $premoderation = false): void
    {
        $I = $this;

        $name = 'Roman 🌞';
        $I->fillField('name', $name);
        $I->fillField('email', 'roman@example.com');
        $I->fillField('text', 'This is my first comment! 👪🐶');
        $text = $I->grabTextFrom('p#qsp');
        preg_match('#(\d\d)\+(\d)#', $text, $matches);
        $I->fillField('question', (int)$matches[1] + (int)$matches[2]);
        $I->click('submit');

        $I->seeResponseCodeIs(200);
        if ($premoderation) {
            $I->see('Your comment has been successfully sent. It will be published after the verification.');
        } else {
            $I->see($name . ' wrote:');
            $I->see('This is my first comment!');
        }
    }

    public function login(string $username = 'admin', string $userpass = ''): void
    {
        $I = $this;
        $I->amOnPage('/---');
        $I->canSee('Username');
        $I->canSee('Password');

        $challenge = $I->grabValueFrom('input[name=challenge]');
        $I->sendAjaxPostRequest('/_admin/site_ajax.php?action=login', [
            'login'     => $username,
            'challenge' => $challenge,
            'key'       => md5(md5($userpass . 'Life is not so easy :-)') . ';-)' . $I->grabAttributeFrom('.loginform', 'data-salt')),
        ]);
        $I->seeResponseCodeIs(200);
    }

    public function installExtension(string $extensionId): void
    {
        $I = $this;
        $I->amOnPage('/_admin/site_ajax.php?action=load_extensions');
        $I->seeResponseCodeIsSuccessful();
        $I->seeElement('.extension.available [title=' . $extensionId . ']');
        $I->dontSeeElement('.extension.enabled [title=' . $extensionId . ']');

        $I->amOnPage('/_admin/site_ajax.php?action=install_extension&id=' . $extensionId);
        $I->seeResponseCodeIsSuccessful();
        $I->dontSeeElement('.extension.available [title=' . $extensionId . ']');
        $I->seeElement('.extension.enabled [title=' . $extensionId . ']');
    }

    public function clearEmail(): void
    {
        $fi = new FilesystemIterator($this->getEmailDir(), FilesystemIterator::SKIP_DOTS);
        foreach ($fi as $f) {
            unlink($f);
        }
    }

    public function getEmails(): array
    {
        $result = [];
        $fi = new FilesystemIterator($this->getEmailDir(), FilesystemIterator::SKIP_DOTS);
        foreach ($fi as $f) {
            $result[] = file_get_contents($f);
        }

        return $result;
    }

    private function getEmailDir(): string
    {
        return '_tests/_output/email';
    }
}
