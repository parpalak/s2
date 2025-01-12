<?php

// Language definitions used in install.php
$lang_install = array(

// Install Form
'Install S2'				=>	'Install S2 %s',
'Part 0'					=>	'Change installer language',
'Choose language help'		=>	'You can change the language of this install script if you find it easier to follow the instructions in your own language. Just choose your language from the list of installed ones below.',
'Installer language'		=>	'Installer language',
'Choose language'			=>	'Change language',
'Part1'						=>	'Database setup',
'Part1 intro'				=>	'Please enter the requested information in order to setup your database for S2. Contact your hosting support in case of difficulties.',
'Database error'			=>	'A database error occurred: "%s". Please check your database connection parameters.',
'Database type'				=>	'Database type',
'Database name'				=>	'Database name',
'Database server'			=>	'Database server',
'Database username'			=>	'Database username',
'Database password'			=>	'Database password',
'Table prefix'				=>	'Table prefix',
'Database type N/A'			=>	'(this PHP environment does not have support for it)',
'Database server help'		=>	'The address of the database server.<br />Examples: <em>localhost</em>, <em>mysql1.example.com</em> or <em>208.77.188.166</em>. You can specify a custom port number if your database does not run on the default port (example: <em>localhost:3580</em>). For SQLite support, leave it at “localhost”.',
'Database name help'		=>	'The name of the database that S2 will be installed into.<br />The database must exist. For SQLite, this is the relative path to the database file. If the SQLite database file does not exist, S2 will attempt to create it. You should grant PHP write permissions for this file and for the containing directory.',
'Database username help'	=>	'For database connection. Ignore for SQLite.',
'Database password help'	=>	'For database connection. Ignore for SQLite.',
'Table prefix help'			=>	'Optional database table prefix, e.g. “test_”.<br />By specifying a table prefix you can run multiple copies of S2 in the same database',
'Part2'						=>	'Administrator setup',
'Part2 intro'				=>	'Please enter the requested information in order to setup an administrator account for your S2 installation. You can create more administrators and moderators in the control panel later.',
'Admin username'			=>	'Username',
'Admin password'			=>	'Password',
'Admin e-mail'				=>	'Administrator email',
'E-mail address help'		=>	'An email associated with your account.<br />If you provide the <em>administrator</em> email, you will receive notifications when visitors post comments. This email will never be published but will be displayed in the control panel to users with granted permissions. You can update this address later.<br />During installation, the value of this field will be assigned as the <em>webmaster</em> email. The webmaster email is used in RSS feeds and as the sender email when mailing comments to subscribers. However, it may be accessible to spammers. The webmaster email can be changed later independently of the emails associated with accounts.',
'Part3'						=>	'Site setup',
'Part3 intro'				=>	'Please enter the requested information about the site.',
'Base URL'					=>	'Base URL',
'Base URL help'				=>	' The URL (without trailing slash) of your site (example: <em>http://example.com</em> or <em>http://example.com/~myuser</em>).<br />You must set the correct Base URL or your site will not work properly. Please note that the preset value is just an educated guess by S2.',
'Default language'			=>	'Site language',
'Default language help'		=>	'If you are going to delete the current language pack (English), you must choose another one before deleting.',
'Start install'				=>	'Start installation', // Label for submit button
'Required'					=>	'(Required)',


// Install errors
'No database support'		=>	'This PHP environment does not have support for any of the databases that S2 supports. PHP needs to have support for either MySQL, PostgreSQL or SQLite in order for S2 to be installed.',
'Missing database name'		=>	'You must enter a database name.',
'Username too long'			=>	'Usernames must be no more than 40 characters long.',
'Username too short'		=>	'Usernames must be at least 2 characters long.',
'Password too short'		=>	'Passwords must be at least 4 characters long.',
'Password too long'			=>	'Passwords must be no more than 100 characters long.',
'Invalid email'				=>	'The administrator email address you entered is invalid.',
'Missing base url'			=>	'You must enter a base URL.',
'No such database type'		=>	'“%s” is not a valid database type.',
'Invalid table prefix'		=>	'The table prefix “%s” contains illegal characters. The prefix may contain the letters a to z, any numbers and the underscore character. They must however not start with a number. Please choose a different prefix.',
'Too long table prefix'		=>	'The table prefix “%s” is too long. The maximum length is 40 characters. Please choose a different prefix.',
'SQLite prefix collision'	=>	'The table prefix “sqlite_” is reserved for use by the SQLite engine. Please choose a different prefix.',
'S2 already installed'		=>	'A table called “%1$susers” is already present in the database “%2$s”. This could mean that S2 is already installed or that another piece of software is installed and is occupying one or more of the table names S2 requires.',
'S2 already installed 2'	=>	'If you want to install multiple copies of S2 in the same database, you must choose a different table prefix.',
'S2 already installed 3'	=>	'To connect the current S2 installation to the selected database, simply download the config.php file with the current parameters and upload it to the folder containing the other S2 files.',
'Invalid language'			=>	'The language pack you have chosen does not seem to exist or is corrupt. Please recheck and try again.',

// Used in the install
'Site name'					=>	'Site powered by S2',
'Main Page'					=>	'Main page',
'Section example'			=>	'Section 1',
'Page example'				=>	'Page 1',
'Page text'					=>	'If you see this text, the install of S2 has been successfully completed. Now you can go directly to <script type="text/javascript">document.write(\'<a href="\' + document.location.href + \'---">the control panel</a>\');</script> and configure this site.',


// Installation completed form
'Success description'		=>	'Congratulations! S2 %s is successfully installing.',
'Success welcome'			=>	'Please follow the instructions below to finalize the installation.',
'Final instructions'		=>	'Final instructions',
'No write info 1'			=>	'<strong>Notice!</strong> To finalize the installation, you need to click on the button below to download a file called config.php. You then need to upload this file to the root directory of your S2 installation.',
'No write info 2'			=>	'Once you have uploaded config.php, S2 will be fully installed! You may then %s once config.php has been uploaded.',
'Go to index'				=>	'go to the main page',
'Warning'					=>	'Warning!',
'No cache write'			=>	'<strong>The cache directory is currently not writable!</strong> In order for S2 to function properly, the directory named <em>_cache</em> must be writable by PHP. Use chmod to set the appropriate directory permissions. If in doubt, chmod to 0777.',
'No pictures write'			=>	'<strong>The picture directory is currently not writable!</strong> If you want to upload pictures and other files you must check that the directory named <em>_pictures</em> is writable by PHP. Use chmod to set the appropriate directory permissions. If in doubt, chmod to 0777.',
'File upload alert'			=>	'<strong>File uploads appear to be disallowed on this server!</strong> If you want to upload pictures in the control panel, you have to enable the file_uploads configuration setting in PHP.',
'Download config'			=>	'Download config.php', // Label for submit button
'Write info'				=>	'S2 is completely installed! Now you may %s.',
);
