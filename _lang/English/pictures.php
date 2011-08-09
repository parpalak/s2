<?php

// Language definitions used in the picture manager
$lang_pictures = array(

'Pictures'						=> 'Pictures',

'Upload file'					=> 'Download file',
'Upload limit'					=> '<small title="Parameters upload_max_filesize and post_max_size into php.ini">Every file to&nbsp;%1$s, total to&nbsp;%2$s for&nbsp;times.</small>',
'Upload'						=> 'Download',
'Upload failed'					=> 'Download failed:\n\n%s',
'Upload file error'				=> '%1$s: %2$s',
'Is upload file error'			=> 'Access error. This file was not downloaded.',
'Move upload file error'		=> '%1$s: File removing error. Probably, you have no rights for file record.',
'Empty files'					=> 'Files were not downloaded.',
'No POST data'					=> 'No post data from browser. Probably, you are trying to download too big file.',

'Upload to'						=> 'Download to',

'Directory not open'			=> 'Folder <strong>%s</strong> cannot be opened.',
'Empty directory'				=> 'This folder is empty',
'Delete'						=> 'Delete',

// Error messages on file uploading
UPLOAD_ERR_INI_SIZE				=> 'Non admissible size of downloading file (parameter upload_max_filesize в php.ini).',
UPLOAD_ERR_FORM_SIZE			=> 'Non admissible size of downloading file (parameter MAX_FILE_SIZE in HTML).',
UPLOAD_ERR_PARTIAL				=> 'File was not downloaded up to the end.',
UPLOAD_ERR_NO_FILE				=> 'No file was downloaded.',
UPLOAD_ERR_NO_TMP_DIR			=> 'Temporary folder for file saving is missing.',
UPLOAD_ERR_CANT_WRITE			=> 'It is impossible to save file.',
UPLOAD_ERR_EXTENSION			=> 'File download was stopped with extension.',
'Unknown error'					=> 'During downloading unknown error occured.',

);
