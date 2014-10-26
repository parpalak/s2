<?php
/**
 * Created by PhpStorm.
 * User: Roman
 * Date: 23.10.2014
 * Time: 23:01
 */

class Lang
{
	private static $data = array();

	/**
	 * @param string $namespace
	 * @param array|callable|null $values
	 */
	public static function load($namespace, $values = null)
	{
		if (empty($values))
		{
			$language = defined('S2_LANGUAGE') ? S2_LANGUAGE : 'English';
			$values = require S2_ROOT.'_lang/'.$language.'/'.$namespace.'.php';
		}

		if (is_callable($values))
		{
			if (isset(self::$data[$namespace]))
				return;

			$values = $values();
		}

		self::$data[$namespace] = $values;
	}

	public static function get($key, $namespace = 'common')
	{
		if (!isset(self::$data[$namespace]))
			self::load($namespace);

		return self::$data[$namespace][$key];
	}

	public static function admin_code ()
	{
		$lang_code = self::get('Lang Code');
		return $lang_code ? $lang_code : 'en';
	}

	public static function month ($month)
	{
		$lang_month_big = self::get('Months');
		return $lang_month_big[$month - 1];
	}

	public static function friendly_filesize ($size)
	{
		$i = 0;
		while (($size/1024) > 1)
		{
			$size /= 1024;
			$i++;
		}

		$lang_filesize = self::get('File size units');
		return sprintf(self::get('File size format'), self::number_format($size), $lang_filesize[$i]);
	}

	/**
	 * Outputs integers using current language settings
	 *
	 * @param float $number
	 * @param bool $trailing_zero
	 * @param bool $decimal_count
	 * @return string
	 */
	public static function number_format ($number, $trailing_zero = false, $decimal_count = false)
	{
		$result = number_format($number, $decimal_count === false ? self::get('Decimal count') : $decimal_count, self::get('Decimal point'), self::get('Thousands separator'));
		if (!$trailing_zero)
			$result = preg_replace('#'.preg_quote(self::get('Decimal point'), '#').'?0*$#', '', $result);

		return $result;
	}

}
