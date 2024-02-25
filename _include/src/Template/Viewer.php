<?php
/**
 * Renders views.
 *
 * @copyright 2014-2024 Roman Parpalak
 * @license MIT
 * @package S2
 */

namespace S2\Cms\Template;


class Viewer
{
    private string $styleViewDir;
    private string $systemViewDir;

    public function __construct(string $rootDir, string $style, private readonly bool $debug)
    {
        $this->styleViewDir = $rootDir . '_styles/' . $style . '/views/';
        $this->systemViewDir = S2_ROOT . '_include/views/';
    }

    /**
     * @throws \JsonException
     */
    private static function jsonFormat($vars, int $level = 0): string
    {
        if (\is_array($vars) && \count(array_filter(array_keys($vars), '\is_int')) < \count($vars)) {
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
                $s .= sprintf("%s%s<span style='color:grey'>%s</span>\n",
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

    public function render(string $name, array $vars, string ...$extraDirs): string
    {
        $name     = preg_replace('#[^0-9a-zA-Z._\-]#', '', $name);
        $filename = $name . '.php';

        $foundFile = null;
        $dirs      = [$this->styleViewDir, ...$extraDirs, $this->systemViewDir];
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

    private function includeFile(string $_found_file, array $_vars): void
    {
        extract($_vars, EXTR_OVERWRITE);
        include $_found_file;
    }
}
