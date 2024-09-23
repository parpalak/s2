<?php

return array(

	'Save comment'             => 'Copy your comment somewhere to prevent its loss.',
	'Go back'                  => 'Go <a href="javascript:history.back()">back</a> and&nbsp;fix errors.',
	'Fix error'                => 'Fix errors before sending the comment.',
	'Email subject'            => 'Comment to %s',
	'Comment sent'             => 'Comment has been sent',
	'Comment sent info'        => '<p>Your comment has been successfully sent. It will be published after the verification.</p><p>Meanwhile, you can <a href="%1$s" id="back_to_commented">go back to the article</a> or&nbsp;visit <a href="%2$s">the main page</a>.</p>',

	'Unsubscribed OK'          => 'You have been successfully unsubscribed',
	'Unsubscribed OK info'     => 'You have been successfully unsubscribed from mailing comments.',

	'Unsubscribed failed'      => 'You have not been unsubscribed',
	'Unsubscribed failed info' => 'Probably, you followed an incorrect or outdated link.',

	'Comment preview'          => 'Comment preview',
	'Comment preview info'     => 'Your comment has not been saved yet! Do not forget to press the “Submit” button after editing.',

	'Email pattern'            =>
		'Hello, <name>.

You have received this e-mail, because you have subscribed for the article
“<title>”,
located at the address:
<url>

The author of the new comment is <author>.

----------------------------------------------------------------------
<text>
----------------------------------------------------------------------

This e-mail has been sent automatically. If you reply, the author
of the site will receive your answer. To unsubscribe, follow the link

<unsubscribe>',
	'Email moderator pattern'  =>
		'Hello, <name>.

You have received this e-mail, because you are the moderator.
A new comment on
“<title>”,
has been received. You can find it here:
<url>

<author> is the comment author.

----------------------------------------------------------------------
<text>
----------------------------------------------------------------------

This e-mail has been sent automatically. If you reply, the author
of the comment will receive your answer.',

	// Comment errors
	'Error message'            => 'The following errors must be corrected before your comment can be saved:',
	'missing_text'             => 'You have forgotten to enter the comment text.',
	'missing_nick'             => 'You have forgotten to enter your name.',
	'long_text'                => 'The message cannot be larger than%s bytes.',
    'links_in_text'            => 'Remove http:// or https:// from links. Author will add links to the article if they are valuable.',
	'long_nick'                => 'Is your name length more than 50 symbols? It is something strange...',
	'question'                 => 'You gave the wrong answer to the question. Try again.',
	'email'                    => 'Invalid e-mail. Please enter the correct e-mail, and the author of the site will contact you if it is needed. If you clear the “Show to other visitors” checkbox, your e-mail will not be shown.',
	'disabled'                 => 'Sorry, but&nbsp;you cannot send comments&nbsp;to this site at this moment. Try it later.',
	'no_item'                  => 'Because of an error the destination page cannot be detected. Go to the page you have commented and try again (you can copy and paste the comment text).',

);
