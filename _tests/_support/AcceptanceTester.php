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

    public function install(string $username = 'admin', string $userpass = ''): void
    {
        $I = $this;
        $I->amOnPage('/');
        $I->seeLink('install S2', '/_admin/install.php');
        $I->amOnPage('/_admin/install.php');
        $I->seeResponseCodeIs(200);
        $I->see('S2 2.0dev', 'h1');

        $I->fillField('req_db_name', 's2_test');
        $I->fillField('db_username', 'root');
        $I->fillField('req_username', $username);
        $I->fillField('req_password', $userpass);
        $I->click('start');
        $I->canSeeResponseCodeIs(200);
        $I->see('S2 is completely installed!');
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
}
