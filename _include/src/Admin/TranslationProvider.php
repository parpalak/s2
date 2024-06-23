<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license   http://opensource.org/licenses/MIT MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Admin;

use S2\Cms\Translation\TranslationProviderInterface;

readonly class TranslationProvider implements TranslationProviderInterface
{
    public function __construct(private string $rootDir)
    {
    }

    public function getTranslations(string $language, string $locale): array
    {
        require $this->rootDir . '_admin/lang/ru/admin.php';
        $translationsAY = require $this->rootDir . '_vendor/s2/admin-yard/translations/ru.php';

        return array_merge($lang_admin, $translationsAY);
    }
}
