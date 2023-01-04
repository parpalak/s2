<?php
/**
 * Renders views.
 *
 * @copyright (C) 2014 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */

class Viewer
{
    private $dirs = array();
    private $debug = false;

    public function __construct($that = null)
    {
        /** @noinspection PhpUndefinedConstantInspection */
        $this->dirs[] = S2_ROOT . '_styles/' . S2_STYLE . '/views/';

        $ext_dir = s2_ext_dir_from_ns(get_class($this));
        if ($ext_dir) {
            $this->dirs[] = $ext_dir . '/views/';
        }

        if ($that instanceof Page_Abstract) {
            $ext_dir = s2_ext_dir_from_ns(get_class($that));
            if ($ext_dir) {
                $this->dirs[] = $ext_dir . '/views/';
            }
        } elseif (is_string($that) && $that) {
            $ext_dir = s2_ext_dir_from_ns($that);
            if ($ext_dir) {
                $this->dirs[] = $ext_dir . '/views/';
            }
        }

        $this->dirs[] = S2_ROOT . '_include/views/';

        if (defined('S2_DEBUG_VIEW') && ($that === null || $that instanceof Page_HTML)) {
            $this->debug = true;
        }
    }

    /**
     * @throws JsonException
     */
    private static function jsonFormat($vars, int $level = 0): string
    {
        if (is_array($vars) && count(array_filter(array_keys($vars), 'is_int')) < count($vars)) {
            $s = "<span style='color:grey'>{</span>\n";
            $i = count($vars);
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
        if (is_array($vars)) {
            $s = "<span style='color:grey'>[</span>\n";
            $i = count($vars);
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

        $str = s2_htmlencode(json_encode($vars, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | (is_array($vars) && count($vars) > 1 ? JSON_PRETTY_PRINT : 0)));
        $str = str_replace(["\r", "\n"], ['', "\n" . str_pad(' ', $level * 4)], $str);

        return $str;
    }

    /**
     * @param       $name
     * @param array $vars
     * @returns string
     */
    public function render($name, array $vars)
    {
        $name     = preg_replace('#[^0-9a-zA-Z._\-]#', '', $name);
        $filename = $name . '.php';

        $found_file = '';
        foreach ($this->dirs as $dir) {
            if (file_exists($dir . $filename)) {
                $found_file = $dir . $filename;
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

        if ($found_file) {
            $this->include_file($found_file, $vars);
        } elseif ($this->debug) {
            echo 'View file not found in ', s2_htmlencode(var_export($this->dirs, true));
        }

        if ($this->debug) {
            echo '</div>';
        }

        return ob_get_clean();
    }

    private function include_file($_found_file, $_vars): void
    {
        extract($_vars, EXTR_OVERWRITE);
        include $_found_file;
    }
}
