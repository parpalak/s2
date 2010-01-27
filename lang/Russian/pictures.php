<?php

// Language definitions used in the picture manager
$lang_pictures = array(

'Pictures'						=> 'Картинки',

'Upload file'					=> 'Загрузка файла',
'Upload limit'					=> '<small title="Параметры upload_max_filesize и post_max_size в php.ini">Каждый файл до %1$s, всего до %2$s.</small>',
'Upload'						=> 'Закачать',
'Upload failed'					=> 'При загрузке файлов возникли следующие ошибки:\n\n%s',
'Upload file error'				=> '%1$s: %2$s',
'Move upload file error'		=> '%1$s: Ошибка при перемещении файла. Возможно, не хватает прав для записи файлов.',

'Upload to'						=> 'в',

'Directory not open'			=> 'Папка <strong>%s</strong> недоступна для чтения.',
'Empty directory'				=> 'Эта папка пуста',
'Delete'						=> 'Удалить',

// Error messages on file uploading
UPLOAD_ERR_INI_SIZE				=> 'Размер загружаемого файла больше допустимого (параметр upload_max_filesize в php.ini).',
UPLOAD_ERR_FORM_SIZE			=> 'Размер загружаемого файла больше допустимого (параметр MAX_FILE_SIZE в HTML-коде формы).',
UPLOAD_ERR_PARTIAL				=> 'Файл не был загружен до конца.',
UPLOAD_ERR_NO_FILE				=> 'Файл не был загружен.',
UPLOAD_ERR_NO_TMP_DIR			=> 'Отсутствует временная директория для сохранения файла.',
UPLOAD_ERR_CANT_WRITE			=> 'Невозможно сохранить файл на диск.',
UPLOAD_ERR_EXTENSION			=> 'Загрузка файла была остановлена расширением.',
'Unknown error'					=> 'Во время загрузки файла произошла неизвестная ошибка.',

);
