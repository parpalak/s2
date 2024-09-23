<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license   http://opensource.org/licenses/MIT MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Admin;

use S2\AdminYard\Config\FieldConfig;
use S2\Cms\Model\ArticleProvider;
use S2\Cms\Pdo\DbLayerException;

readonly class PathToAdminEntityConverter
{
    public function __construct(
        private ArticleProvider $articleProvider,
    ) {
    }

    /**
     * @throws DbLayerException
     */
    public function getQueryParams(string $path): ?array
    {
        if ($path === '/') {
            return null;
        }

        $data = $this->articleProvider->articleFromPath($path, false);
        if ($data === null) {
            return null;
        }

        return ['entity' => 'Article', 'action' => FieldConfig::ACTION_EDIT, 'id' => $data['id']];
    }
}
