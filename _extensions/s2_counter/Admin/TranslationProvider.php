<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license   http://opensource.org/licenses/MIT MIT
 * @package   s2_counter
 */

declare(strict_types=1);

namespace s2_extensions\s2_counter\Admin;

use S2\Cms\Admin\TranslationProviderInterface;

class TranslationProvider implements TranslationProviderInterface
{
    public function getTranslations(string $language, string $locale): array
    {
        return match ($locale) {
            'ru' => [
                'Data folder not writable' => 'У php-скриптов нет доступа на запись в папку «{{ dir }}». Установите нужные права (например, 777), чтобы расширение «s2_counter» смогло работать.',
            ],
            'en' => [
                'Data folder not writable' => 'PHP scripts have no write permissions to the “{{ dir }}” directory. Set write permissions (e. g. 777) for proper functioning of the “s2_counter” extension.',
            ],
        };
    }
}
