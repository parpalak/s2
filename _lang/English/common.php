<?php

return array(
	'Lang Code'              => 'en',

	// Error messages
	'Error encountered'      => 'An error was encountered',
	'DB repeat items'        => 'Database error: there are several items supposed to be unique.',
	'Error no template'      => 'No parent section has a template.<br /><br />You have to set a tepmlate differs from “inherited” at least for one item from listed below:<br />%s',
	'Error no template flat' => 'Page has no template.<br /><br />You have to set a tepmlate differs from “inherited” for this page.',
	'Template not found'     => 'The template file <strong>%s</strong> was not found. If this error recur, try to reinstall S2.',
	'Error 404'              => 'Error 404',
	'Error 404 text'         => 'This page never existed or&nbsp;has been removed. Go to&nbsp;<a href="%1$s">the main page</a> and&nbsp;find the necessary information, or&nbsp;write webmaster.',

	// Page content
	'In this section'        => 'In this section',
	'Read in this section'   => 'Read in this section',
	'More in this section'   => 'More in the section <nobr>“%s”</nobr>',
	'Subsections'            => 'Subsections',
	'Tags'                   => 'Tags',
	'With this tag'          => 'On the subject “%s”',
	'Comments'               => 'Comments',
	'Copyright 1'            => '© %2$s %1$s.',
	'Copyright 2'            => '© %2$s–%3$s %1$s.',
	'Powered by'             => 'Powered by %s CMS.',
	'Last comments'          => 'Last comments on&nbsp;the site',
	'Last discussions'       => 'Last discussions on&nbsp;the site',
	'Here'                   => '← here',
	'There'                  => 'there →',

	'Favorite'               => 'Favorite',

	// RSS
	'RSS description'        => '%s. Last articles.',
	'RSS link title'         => 'Last articles on the site',

	// Comments
	'Wrote'                  => 'Wrote:',
	'Comment info format'    => '%1$s. %2$s wrote:',
	'Post a comment'         => 'Post a comment',
	'Your name'              => 'Your name:',
	'Your email'             => 'E-mail:',
	'Your comment'           => 'Comment:',
	'Show email label'       => 'Show to other visitors',
	'Show email label title' => '',
	'Subscribe label'        => 'Subscribe to the other visitors’ comments',
	'Subscribe label title'  => 'The comments of other visitors will be sent to your e-mail. You can unsubscribe later if it bother you.',
	'Comment syntax info'    => 'Use the following code to format your message: [i]<i>italics</i>[/i], [b]<b>bold</b>[/b].<br />Insert quotations: [q = author’s name]a quotation[/q] or [q]another quotation[/q].<br />Start links from http://. There are no other commands or HTML-tags.',
	'Comment question'       => 'How much is %s?',

	'Submit'                 => 'Submit',
	'Preview'                => 'Preview',
	'Error'                  => 'Error!',

	// Locale settings
	'Date format'            => 'F j, Y', // See http://php.net/manual/en/function.date.php for details
	'Time format'            => 'F j, Y. h:i A',

	'Decimal count'          => 2,
	'Decimal point'          => '.',
	'Thousands separator'    => ',',

	'Months'                 => array(

		'January',
		'February',
		'March',
		'April',
		'May',
		'June',
		'July',
		'August',
		'September',
		'October',
		'November',
		'December'

	),
	'Inline Months'          => array(

		'January'   => 'January',
		'February'  => 'February',
		'March'     => 'March',
		'April'     => 'April',
		'May'       => 'May',
		'June'      => 'June',
		'July'      => 'July',
		'August'    => 'August',
		'September' => 'September',
		'October'   => 'October',
		'November'  => 'November',
		'December'  => 'December'

	),

	'File size format'       => '%1$s %2$s', // %1$s = number, %2$s = unit
	'File size units'        => array('B', 'КB', 'MB', 'GB', 'ТB', 'PB', 'EB', 'ZB', 'YB')
);
