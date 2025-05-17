<?php
/**
 * @copyright 2024-2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Admin;

readonly class TranslationProvider implements TranslationProviderInterface
{
    public function __construct(private string $rootDir)
    {
    }

    public function getTranslations(string $language, string $locale): array
    {
        $translationsS2 = require $this->rootDir . '_admin/lang/' . $locale . '/admin.php';
        $translationsAY = require $this->rootDir . '_vendor/s2/admin-yard/translations/' . $locale . '.php';

        return array_merge($translationsS2, $translationsAY);
    }
}
