<?php
/**
 * Created by PhpStorm.
 * User: Roman
 * Date: 23.10.2014
 * Time: 23:01
 */

class Lang
{
	// Returns a month defined in lang files
	public static function month ($month)
	{
		global $lang_month_big;

		return $lang_month_big[$month - 1];
	}

	public static function friendly_filesize ($size)
	{
		global $lang_common, $lang_filesize;

		$i = 0;
		while (($size/1024) > 1)
		{
			$size /= 1024;
			$i++;
		}

		return sprintf($lang_common['Filesize format'], Lang::number_format($size), $lang_filesize[$i]);
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
		global $lang_common;

		$result = number_format($number, $decimal_count === false ? $lang_common['Decimal count'] : $decimal_count, $lang_common['Decimal point'], $lang_common['Thousands separator']);
		if (!$trailing_zero)
			$result = preg_replace('#'.preg_quote($lang_common['Decimal point'], '#').'?0*$#', '', $result);

		return $result;
	}

}
