<?php
/**
 * @copyright 2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   s2
 */

declare(strict_types=1);

namespace unit\Cms\Helper;

use Codeception\Test\Unit;
use S2\Cms\Helper\StringHelper;

class StringHelperTest extends Unit
{
    /**
     * @dataProvider jsMailToDataProvider
     */
    public function testJsMailTo(string $name, string $email, string $expectedOutput): void
    {
        $result = StringHelper::jsMailTo($name, $email);
        $this->assertEquals($expectedOutput, $result);
    }

    private function jsMailToDataProvider(): array
    {
        return [
            'valid email' => [
                'John Doe',
                'john@example.com',
                '<script type="text/javascript">var mailto="john"+"%40"+"example.com";' .
                'document.write(\'<a href="mailto:\'+mailto+\'">John Doe</a>\');</script>' .
                '<noscript>John Doe, <small>[john at example.com]</small></noscript>'
            ],
            'email with single quote in name' => [
                "John O'Reilly",
                'john@example.com',
                '<script type="text/javascript">var mailto="john"+"%40"+"example.com";' .
                'document.write(\'<a href="mailto:\'+mailto+\'">John O\\\'Reilly</a>\');</script>' .
                '<noscript>John O\'Reilly, <small>[john at example.com]</small></noscript>'
            ],
            'invalid email - no @' => [
                'John Doe',
                'invalid-email',
                'John Doe'
            ],
            'invalid email - multiple @' => [
                'John Doe',
                'john@doe@example.com',
                'John Doe'
            ],
        ];
    }

    /**
     * @dataProvider isValidEmailDataProvider
     */
    public function testIsValidEmail(string $email, bool $expectedResult): void
    {
        $result = StringHelper::isValidEmail($email);
        $this->assertEquals($expectedResult, $result);
    }

    private function isValidEmailDataProvider(): array
    {
        return [
            'valid email' => ['test@example.com', true],
            'valid email with subdomain' => ['test@sub.example.com', true],
            'valid email with plus' => ['test+filter@example.com', true],
            'valid email with quotes' => ['"test"@example.com', true],
            'valid email with ip' => ['test@[127.0.0.1]', true],
            'invalid email - no @' => ['test.example.com', false],
            'invalid email - no domain' => ['test@', false],
            'invalid email - no local part' => ['@example.com', false],
            'invalid email - space' => ['test @example.com', false],
            'invalid email - too long' => [str_repeat('a', 70) . '@example.com', false],
            'invalid email - multiple @' => ['test@@example.com', false],
            'invalid email - special chars' => ['test<@example.com', false],
        ];
    }

    /**
     * @dataProvider pagingDataProvider
     */
    public function testPaging(int $page, int $totalPages, string $url, array $expectedLinks, string $expectedOutput): void
    {
        $linksForNavigation = [];
        $result = StringHelper::paging($page, $totalPages, $url, $linksForNavigation);

        $this->assertEquals($expectedLinks, $linksForNavigation);
        $this->assertStringContainsString($expectedOutput, $result);
    }

    private function pagingDataProvider(): array
    {
        return [
            'first page of many' => [
                1,
                5,
                'http://example.com/page?num=%d',
                ['next' => 'http://example.com/page?num=2'],
                '<span class="current digit">1</span>'
            ],
            'middle page' => [
                3,
                5,
                'http://example.com/page?num=%d',
                ['prev' => 'http://example.com/page?num=2', 'next' => 'http://example.com/page?num=4'],
                '<span class="current digit">3</span>'
            ],
            'last page' => [
                5,
                5,
                'http://example.com/page?num=%d',
                ['prev' => 'http://example.com/page?num=4'],
                '<span class="current digit">5</span>'
            ],
            'single page' => [
                1,
                1,
                'http://example.com/page?num=%d',
                [],
                '<span class="current digit">1</span>'
            ],
            'invalid page' => [
                0,
                5,
                'http://example.com/page?num=%d',
                ['next' => 'http://example.com/page?num=1'],
                '<span class="arrow left">&larr;</span>'
            ],
        ];
    }

    public function testBbcodeToHtml(): void
    {
        $input = '[B]bold[/B] [I]italic[/I] [Q=Author]quote[/Q] http://example.com';
        $expected = '<strong>bold</strong> <em>italic</em><blockquote><strong>Author</strong> wrote:<br/><br/><em>quote</em></blockquote><noindex><a href="http://example.com" rel="nofollow">http://example.com</a></noindex>';

        $result = StringHelper::bbcodeToHtml($input, 'wrote:');
        $this->assertEquals($expected, $result);
    }

    public function testUtf8Wordwrap(): void
    {
        $input = "Это длинная строка на русском языке которая должна быть разбита на несколько строк";
        $expected = "Это длинная строка на русском языке которая \nдолжна быть разбита на несколько строк ";

        $result = StringHelper::utf8Wordwrap($input, 50);
        $this->assertEquals($expected, $result);
    }

    public function testBbcodeToMail(): void
    {
        $input = "[B]bold[/B] [I]italic[/I] [Q=Author]quote\nline2[/Q]";
        $expected = "*bold* _italic_ \n\n> quote \n> line2";

        $result = StringHelper::bbcodeToMail($input);
        $this->assertEquals($expected, $result);
    }
}
