<?php

class s2_search_stemmer
{
	protected static $Stem_Caching = 0;
	protected static $Stem_Cache = array();
	const VOWEL = '/аеиоуыэюя/u';
	const PERFECTIVEGROUND = '/((ив|ивши|ившись|ыв|ывши|ывшись)|((?<=[ая])(в|вши|вшись)))$/u';
	const REFLEXIVE = '/(с[яь])$/u';
	const ADJECTIVE = '/(ее|ие|ые|ое|ими|ыми|ей|ий|ый|ой|ем|им|ым|ом|его|ого|их|ых|еых|ую|юю|ая|яя|ою|ею)$/u';
	const PARTICIPLE = '/((ивш|ывш|ующ)|((?<=[ая])(ем|нн|вш|ющ|щ)))$/u';
	const VERB = '/((ила|ыла|ена|ейте|уйте|ите|или|ыли|ей|уй|ил|ыл|им|ым|ены|ить|ыть|ишь|ую|ю)|((?<=[ая])(ла|на|ете|йте|ли|й|л|ем|н|ло|но|ет|ют|ны|ть|ешь|нно)))$/u';
	const NOUN = '/(а|ев|ов|ие|ье|е|иями|ями|ами|еи|ии|и|ией|ей|ой|ий|й|и|ы|ь|у|ию|ью|ю|ия|ья|я|ах)$/u';
	const RVRE = '/^(.*?[аеиоуыэюя])(.*)$/u';
	const DERIVATIONAL = '/[^аеиоуыэюя][аеиоуыэюя]+[^аеиоуыэюя]+[аеиоуыэюя].*(?<=о)сть?$/u';

	protected static $fixed_words = array(
		'в'			=> '',
		'и'			=> '',
		'или'		=> '',
		'когда'		=> '',
		'если'		=> '',
		'тире'		=> '',
		'после'		=> '',
		'перед'		=> '',
		'менее'		=> '',
		'ему'		=> 'он',
		'им'		=> 'они',

		'шея'		=> '',
		'шее'		=> 'шея',
		'шеи'		=> 'шея',
		'шеей'		=> 'шея',
		'шей'		=> 'шея',
		'шеями'		=> 'шея',
		'шеях'		=> 'шея',

		'имя'		=> '',
		'имени'		=> 'имя',
		'именем'	=> 'имя',
		'имена'		=> 'имя',
		'именам'	=> 'имя',
		'именами'	=> 'имя',
		'именах'	=> 'имя',

		'её'		=> 'она',
		'ее'		=> 'она',
		'ей'		=> 'она',
		'ней'		=> 'она',

		'иван'		=> '',
		'ивана'		=> 'иван',
		'иваны'		=> 'иван',
		'иванам'	=> 'иван',

		'ересь'		=> 'ерес',
		'ереси'		=> 'ерес',
		'ересью'	=> 'ерес',
		'ересью'	=> 'ерес',

		'ищу'		=> 'иска',
		'ищешь'		=> 'иска',
		'ищет'		=> 'иска',
		'ищем'		=> 'иска',
		'ищете'		=> 'иска',
		'ищут'		=> 'иска',

		'чай'		=> '',
		'чаю'		=> 'чай',

		'ива'		=> '',
		'ивы'		=> 'ива',
		'ивами'		=> 'ива',
	);

	protected static function s(&$s, $re, $to)
	{
		$orig = $s;
		$s = preg_replace($re, $to, $s);
		return $orig !== $s;
	}

	protected static function m($s, $re)
	{
		return preg_match($re, $s);
	}

	public static function stem_word($word) 
	{
		$word = utf8_strtolower($word);
		if (isset(self::$fixed_words[$word]))
			return self::$fixed_words[$word] ? self::$fixed_words[$word] : $word;
		$word = str_replace('ё', 'е', $word);
		# Check against cache of stemmed words
		if (self::$Stem_Caching && isset(self::$Stem_Cache[$word])) {
			return self::$Stem_Cache[$word];
		}
		$stem = $word;
		do {
			if (!preg_match(self::RVRE, $word, $p)) break;
			$start = $p[1];
			$RV = $p[2];
			if (!$RV) break;

			# Step 1
			if (!self::s($RV, self::PERFECTIVEGROUND, '')) {
				self::s($RV, self::REFLEXIVE, '');

				if (self::s($RV, self::ADJECTIVE, '')) {
					self::s($RV, self::PARTICIPLE, '');
				} else {
					if (!self::s($RV, self::VERB, ''))
						self::s($RV, self::NOUN, '');
				}
			}

			# Step 2
			self::s($RV, '/и$/u', '');

			# Step 3
			if (self::m($RV, self::DERIVATIONAL))
				self::s($RV, '/ость?$/u', '');

			# Step 4
			if (!self::s($RV, '/ь$/u', '')) {
				self::s($RV, '/ейше?/u', '');
				self::s($RV, '/нн$/u', 'н'); 
			}

			$stem = $start.$RV;
		} while(false);
		if (self::$Stem_Caching)
			self::$Stem_Cache[$word] = $stem;
		return $stem;
	}

	public static function stem_caching($caching_level) 
	{
		self::$Stem_Caching = $caching_level;
	}

	public static function clear_stem_cache() 
	{
		self::$Stem_Cache = array();
	}
}
