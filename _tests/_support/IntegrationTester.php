<?php

declare(strict_types=1);


/**
 * Inherited Methods
 * @method void wantTo($text)
 * @method void wantToTest($text)
 * @method void execute($callable)
 * @method void expectTo($prediction)
 * @method void expect($prediction)
 * @method void amGoingTo($argumentation)
 * @method void am($role)
 * @method void lookForwardTo($achieveValue)
 * @method void comment($description)
 * @method void pause($vars = [])
 *
 * @SuppressWarnings(PHPMD)
 */
class IntegrationTester extends \Codeception\Actor
{
    use _generated\IntegrationTesterActions;

    public function login(string $username = 'admin', string $userpass = ''): void
    {
        $I = $this;
        $I->amOnPage('https://localhost/_admin/index.php');
        $I->canSee('Username');
        $I->canSee('Password');

        $challenge = $I->grabValueFrom('input[name=challenge]');
        $I->sendPost('https://localhost/_admin/index.php?action=login', [
            'login'     => $username,
            'challenge' => $challenge,
            'key'       => md5(md5($userpass . 'Life is not so easy :-)') . ';-)' . $I->grabAttributeFrom('.loginform', 'data-salt')),
        ]);
    }

    public function logout(): void
    {
        $this->amOnPage('https://localhost/_admin/index.php?action=logout');
    }

    public function assertJsonSubResponseContains(string $needle, array $path): void
    {
        $I        = $this;
        $response = $I->grabJson();
        foreach ($path as $value) {
            $I->assertArrayHasKey($value, $response);
            $response = $response[$value];
        }
        $I->assertStringContainsString($needle, $response);
    }

    public function assertJsonResponseHasNoKey(array $path): void
    {
        $I        = $this;
        $response = $I->grabJson();
        $total    = count($path);
        foreach (array_values($path) as $index => $value) {
            if ($index === $total - 1) {
                $I->assertArrayNotHasKey($value, $response);
            } else {
                $I->assertArrayHasKey($value, $response);
                $response = $response[$value];
            }
        }
    }

    public function assertJsonSubResponseEquals(mixed $needle, array $path): void
    {
        $I        = $this;
        $response = $I->grabJson();
        foreach ($path as $value) {
            $I->assertArrayHasKey($value, $response);
            $response = $response[$value];
        }
        $I->assertEquals($needle, $response);
    }
}
