<?php
/**
 * Renders views.
 *
 * @copyright 2014-2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

namespace S2\Cms\Template;

use S2\Cms\Model\UrlBuilder;
use Symfony\Contracts\Translation\TranslatorInterface;

class Viewer
{
    private string $styleViewDir;
    private string $extensionDirPattern;
    private string $systemViewDir;

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly UrlBuilder          $urlBuilder,
        string                               $rootDir,
        string                               $style,
        private readonly bool                $debug
    ) {
        $this->styleViewDir        = $rootDir . '_styles/' . $style . '/views/';
        $this->extensionDirPattern = $rootDir . '_extensions/%s/views/';
        $this->systemViewDir       = $rootDir . '_include/views/';
    }

    public function render(string $name, array $vars, string ...$extraDirs): string
    {
        $name     = preg_replace('#[^0-9a-zA-Z._\-]#', '', $name);
        $filename = $name . '.php';

        $foundFile = null;
        $dirs      = [
            $this->styleViewDir,
            ...array_map(fn(string $dir) => \sprintf($this->extensionDirPattern, $dir), $extraDirs),
            $this->systemViewDir
        ];
        foreach ($dirs as $dir) {
            if (file_exists($dir . $filename)) {
                $foundFile = $dir . $filename;
                break;
            }
        }

        ob_start();

        if ($this->debug) {
            echo '<div style="border: 1px solid rgba(0, 0, 0, 0.15); margin: 1px; position: relative;">',
            '<pre style="opacity: 0.4; background: darkgray; color: white; position: absolute; z-index: 10000; right: 0; cursor: pointer; text-decoration: underline; padding: 0.1em 0.65em;" onclick="this.nextSibling.style.display = this.nextSibling.style.display === \'block\' ? \'none\' : \'block\'; ">', $name, '</pre>',
            '<pre style="display: none; font-size: 12px; line-height: 1.3; color: #9e9; background: #003;">';
            echo self::jsonFormat($vars);
            echo '</pre>';
        }

        if ($foundFile !== null) {
            $this->includeFile($foundFile, $vars);
        } elseif ($this->debug) {
            echo 'View file not found in ', s2_htmlencode(var_export($dirs, true));
        }

        if ($this->debug) {
            echo '</div>';
        }

        return ob_get_clean();
    }


    /**
     * Puts the date into a string
     */
    public function date(int $time): string
    {
        if (!$time) {
            return '';
        }

        $format = $this->translator->trans('Date format');
        $date   = date($format, $time);
        if (str_contains($format, 'F')) {
            $date = str_replace(date('F', $time), $this->translator->trans(date('F', $time) . ' genitive'), $date);
        }

        return $date;
    }

    /**
     * Puts the date and time into a string
     */
    public function dateAndTime(int $time): string
    {
        if (!$time) {
            return '';
        }

        $format = $this->translator->trans('Time format');
        $date   = date($format, $time);
        if (str_contains($format, 'F')) {
            $date = str_replace(date('F', $time), $this->translator->trans(date('F', $time)), $date);
        }

        return $date;
    }

    /**
     * Outputs integers using current language settings
     */
    public function numberFormat(float $number, bool $trailingZeros = false, ?int $decimalCount = null): string
    {
        $decimalPoint = $this->translator->trans('Decimal point');
        $result       = number_format(
            $number,
            $decimalCount ?? (int)$this->translator->trans('Decimal count'),
            $decimalPoint,
            $this->translator->trans('Thousands separator')
        );
        if (!$trailingZeros) {
            $result = preg_replace('#' . preg_quote($decimalPoint, '#') . '?0*$#', '', $result);
        }

        return $result;
    }

    /**
     * @throws \JsonException
     */
    private static function jsonFormat($vars, int $level = 0): string
    {
        if (\is_array($vars) && !array_is_list($vars)) {
            $s = "<span style='color:grey'>{</span>\n";
            $i = \count($vars);
            foreach ($vars as $k => $v) {
                $i--;
                $s .= sprintf("%s<span style='color:grey'>\"</span>%s<span style='color:grey'>\":</span> %s<span style='color:grey'>%s</span>\n",
                    str_pad(' ', ($level + 1) * 4),
                    s2_htmlencode($k),
                    self::jsonFormat($v, $level + 1),
                    $i > 0 ? ',' : ''
                );
            }
            $s .= str_pad(' ', $level * 4) . '<span style="color:grey">}</span>';

            return $s;
        }
        if (\is_array($vars)) {
            $s = "<span style='color:grey'>[</span>\n";
            $i = \count($vars);
            foreach ($vars as $k => $v) {
                $i--;
                $s .= \sprintf("%s%s<span style='color:grey'>%s</span>\n",
                    str_pad(' ', ($level + 1) * 4),
                    self::jsonFormat($v, $level + 1),
                    $i > 0 ? ',' : ''
                );
            }
            $s .= str_pad(' ', $level * 4) . '<span style="color:grey">]</span>';

            return $s;
        }

        $str = s2_htmlencode(json_encode($vars, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | (\is_array($vars) && \count($vars) > 1 ? JSON_PRETTY_PRINT : 0)));
        $str = str_replace(["\r", "\n"], ['', "\n" . str_pad(' ', $level * 4)], $str);

        return $str;
    }

    private function includeFile(string $_found_file, array $_vars): void
    {
        $trans        = $this->translator->trans(...);
        $makeLink     = $this->urlBuilder->link(...);
        $date         = $this->date(...);
        $dateAndTime  = $this->dateAndTime(...);
        $numberFormat = $this->numberFormat(...);

        extract($_vars, EXTR_OVERWRITE);
        include $_found_file;
    }
}
