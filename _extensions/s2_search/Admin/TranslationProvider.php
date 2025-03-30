<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license   http://opensource.org/licenses/MIT MIT
 * @package   S2
 */

declare(strict_types=1);

namespace s2_extensions\s2_search\Admin;

use S2\Cms\Admin\TranslationProviderInterface;

class TranslationProvider implements TranslationProviderInterface
{
    public function getTranslations(string $language, string $locale): array
    {
        return match ($locale) {
            'ru' => [
                'Search config'                        => 'Поиск',
                'S2_SEARCH_QUICK'                      => 'Быстрый поиск',
                'S2_SEARCH_QUICK_help'                 => 'По мере набора поискового запроса показывать подсказки с совпадающими заголовками.',
                'S2_SEARCH_RECOMMENDATIONS_LIMIT'      => 'Объем рекомендаций',
                'S2_SEARCH_RECOMMENDATIONS_LIMIT_help' => 'Максимальное количество рекомендаций. 0 отключает рекомендации совсем.',
                'Search index'                         => 'Поисковый индекс',
                'Reindex'                              => 'Проиндексировать заново',
                'Reindex title'                        => 'Долгая операция. Если соединение с сервером прервется, можно запустить повторно.',
                'Indexing required'                    => 'Чтобы на сайте заработал поиск, нужно проиндексировать данные.',
            ],
            'en' => [
                'Search config'                        => 'Search',
                'S2_SEARCH_QUICK'                      => 'Quick search',
                'S2_SEARCH_QUICK_help'                 => 'Show suggestions based on the search over titles while typing a search query.',
                'S2_SEARCH_RECOMMENDATIONS_LIMIT'      => 'Recommendations size',
                'S2_SEARCH_RECOMMENDATIONS_LIMIT_help' => 'Maximum number of recommendations. Set 0 to disable recommendations.',
                'Search index'                         => 'Search index',
                'Reindex'                              => 'Reindex',
                'Reindex title'                        => 'May be time-consuming. You can run it again in case of connection loss.',
                'Indexing required'                    => 'In order to enable the site search, indexing is required.',
            ],
        };
    }
}
