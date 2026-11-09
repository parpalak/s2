<?php
/**
 * @copyright 2026 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Helper;

use Symfony\Contracts\Translation\TranslatorInterface;

class DateHelper
{
    public static function format(int $time, string $format, TranslatorInterface $translator): string
    {
        if (!$time) {
            return '';
        }

        $date = date($format, $time);
        if (str_contains($format, 'F')) {
            $month = date('F', $time);
            $date = str_replace($month, $translator->trans($month . ' genitive'), $date);
        }

        return $date;
    }
}
