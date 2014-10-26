<?php

return array(
	'Lang Code'              => 'ru',

	// Error messages
	'Error encountered'      => 'Произошла ошибка',
	'DB repeat items'        => 'Ошибка в базе данных: наличие повторяющихся элементов.',
	'Error no template'      => 'Ни у одного из разделов шаблон не найден.<br /><br />Вы должны задать шаблон, отличный от шаблона «как у раздела», хотя бы у одного элемента из перечисленных ниже:<br />%s',
	'Error no template flat' => 'Шаблон страницы не найден.<br /><br />Вы должны у этой страницы задать шаблон, отличный от шаблона «как у раздела».',
	'Template not found'     => 'Отсутствует файл шаблона <strong>%s</strong>. Если эта ошибка будет повторяться, попробуйте переустановить S2.',
	'Error 404'              => 'Ошибка 404',
	'Error 404 text'         => 'Эта страница никогда не&nbsp;существовала или&nbsp;была удалена. Перейдите на&nbsp;<a href="%1$s">главную</a> и&nbsp;найдите нужную страницу самостоятельно, либо&nbsp;напишите автору сайта.',

	// Page content
	'In this section'        => 'В этом разделе',
	'Read in this section'   => 'Читайте в этом разделе',
	'More in this section'   => 'Еще в разделе <nobr>«%s»</nobr>',
	'Subsections'            => 'Подразделы',
	'Tags'                   => 'Ключевые слова',
	'With this tag'          => 'По теме «%s»',
	'Comments'               => 'Комментарии',
	'Copyright 1'            => '© %1$s, %2$s.',
	'Copyright 2'            => '© %1$s, %2$s–%3$s.',
	'Powered by'             => 'Сайт работает на движке %s.',
	'Last comments'          => 'Последние комментарии на&nbsp;сайте',
	'Last discussions'       => 'Обсуждаемое на&nbsp;сайте',
	'Here'                   => '← сюда',
	'There'                  => 'туда →',

	'Favorite'               => 'Избранное',

	// RSS
	'RSS description'        => '%s. Последние статьи.',
	'RSS link title'         => 'Последние статьи на сайте',

	// Comments
	'Wrote'                  => 'пишет:',
	'Comment info format'    => '%1$s. %2$s пишет:',
	'Post a comment'         => 'Оставьте свой комментарий',
	'Your name'              => 'Ваше имя:',
	'Your email'             => 'Электронная почта:',
	'Your comment'           => 'Комментарий:',
	'Show email label'       => 'Показывать адрес посетителям сайта',
	'Show email label title' => '',
	'Subscribe label'        => 'Подписаться на комментарии других посетителей',
	'Subscribe label title'  => 'Комментарии других пользователей будут приходить вам по почте. Сможете отписаться, когда надоест.',
	'Comment syntax info'    => 'Для выделения используйте следующий код: [i]<i>курсив</i>[/i], [b]<b>жирный</b>[/b].<br />Цитату оформляйте так: [q = имя автора]цитата[/q] или [q]еще цитата[/q].<br />Ссылку начните с http://. Других команд или HTML-тегов здесь нет.',
	'Comment question'       => 'Сколько будет %s?',

	'Submit'                 => 'Отправить',
	'Preview'                => 'Предварительный просмотр',
	'Error'                  => 'Oшибка!',

	// Locale settings
	'Date format'            => 'j F Y года', // See http://php.net/manual/en/function.date.php for details
	'Time format'            => 'j F Y года, H:i',

	'Decimal count'          => 2,
	'Decimal point'          => ',',
	'Thousands separator'    => ' ',

	'Months'                 => array(
		'Январь',
		'Февраль',
		'Март',
		'Апрель',
		'Май',
		'Июнь',
		'Июль',
		'Август',
		'Сентябрь',
		'Октябрь',
		'Ноябрь',
		'Декабрь'

	),

	'Inline Months'          => array(
		'January'   => 'января',
		'February'  => 'февраля',
		'March'     => 'марта',
		'April'     => 'апреля',
		'May'       => 'мая',
		'June'      => 'июня',
		'July'      => 'июля',
		'August'    => 'августа',
		'September' => 'сентября',
		'October'   => 'октября',
		'November'  => 'ноября',
		'December'  => 'декабря'

	),

	'File size format'       => '%1$s %2$s', // %1$s = number, %2$s = unit
	'File size units'        => array('Б', 'КБ', 'МБ', 'ГБ', 'ТБ', 'ПБ', 'ЭБ', 'ЗБ', 'ИБ')
);
