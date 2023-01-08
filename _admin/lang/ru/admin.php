<?php

$lang_admin = array(

// Common admin panel
'Welcome'					=> 'Привет, %s.',
'Logout'					=> 'Выйти?',
'Logout info'				=> 'Выход из системы здесь',
'Delete from list'			=> 'Удалить страницу из этого списка',
'New page'					=> 'Новая страница',
'Admin panel'				=> 'Панель управления',
'Expired session'			=> 'Ваш сеанс из-за длительной паузы прерван. Введите пароль для продолжения.',
'Lost session'				=> 'Ваш сеанс завершен. Введите пароль для продолжения.',
'Wrong_IP session'			=> 'IP-адрес вашего компьютера изменился. Введите пароль для продолжения.',
'No permission'				=> 'У вас недостаточно прав для совершения этого действия.',
'Other sessions'			=> 'Вы зашли в панель управления с нескольких браузеров. Вот ваши сеансы: <br />%s',
'Close other sessions'		=> 'Закрыть все сеансы кроме текущего',
'New version message'		=> 'Вышла новая версия <strong>S2 %s</strong>. Ее можно <a target="_blank" href="http://s2cms.ru/download">скачать на сайте движка &uarr;</a>.',

// Login form
'Password'					=> 'Пароль',
'Log in'					=> 'Войти',
'Noscript'					=> 'У вас отключен Javascript.<br />Без него система не работает.<br />Включите его, на дворе XXI век.',
'Error login page'			=> 'Неправильный логин или пароль.<br />Введите, пожалуйста, данные еще раз.',

// List tab
'Site'						=> 'Сайт',
'Expand'					=> 'Раскрыть всё',
'Collapse'					=> 'Закрыть всё',
'Refresh'					=> 'Обновить',
'Search'					=> 'Поиск',
'Create subarticle'			=> 'Создать страницу в этом разделе',

// Editor tab
'Empty editor info'			=> 'Перейдите на вкладку «<a href="#list">Сайт</a>» и выберите страницу для редактирования.',
'Not saved correct'			=> 'Внимание! Возникла ошибка при сохранении страницы. На всякий случай скопируйте ее содержимое в текстовый редактор и сохраните в файл.',
'Editor'					=> 'Редактор',
'Author'					=> 'Автор:',
'Template'					=> 'Шаблон:',
'Title'						=> 'Заголовок',
'Add template info'			=> 'Имя файла с новым шаблоном:',
'Meta keywords'				=> 'Meta-keywords',
'Meta description'			=> 'Meta-description',
'Meta help'					=> 'Мета-информацию могут использовать поисковики. Заполните для улучшения индексации.',
'Excerpt'					=> 'Выдержка',
'Excerpt help'				=> 'Выдержка используется как описание в разделах и попадает в RSS',
'Tags'						=> 'Ключевые слова',
'Tags help'					=> 'Список ключевых слов через запятую. На вкладке ключевых слов можно их отредактировать.',
'Create time'				=> 'Создано',
'Modify time'				=> 'Изменено',
'Modify time help'			=> 'Используется в RSS. Можно обновлять при внесении правок в опубликованную страницу',
'Now'						=> 'сейчас',
'Paragraphs info'			=> '«Умная» расстановка тегов &lt;p&gt; и &lt;br&gt;',
'Published'					=> 'Опубликовано',
'Commented'					=> 'Можно комментировать',
'Commented info'			=> 'Отображать форму ввода комментариев на этой странице.',
'Preview published'			=> 'Опубликованная страница откроется в новом окне',
'Preview ready'				=> 'Просмотреть опубликованное &uarr;',
'Go to comments'			=> 'Перейти к комментариям',
'Preview not found'			=> '<h1>Страница не найдена</h1><p>Страница, которую вы хотите просмотреть, не опубликована или не найдена. Можно просматривать только опубликованные страницы.</p>',
'URL not unique'			=> 'Такой URL уже используется. Подберите другой.',
'URL empty'					=> 'Задайте фрагмент URL, чтобы у этой страницы появился свой адрес.',
'URL on mainpage'			=> 'Фрагмент URL главной страницы изменять нельзя.',

'Bold'						=> 'Жирный шрифт (Ctrl + B)',
'Italic'					=> 'Курсив (Ctrl + I)',
'Strike'					=> 'Зачеркнутый',

'Link'						=> 'Ссылка (Ctrl + K)',
'Quote'						=> 'Цитата (Ctrl + Q)',
'Image'						=> 'Картинки (Ctrl + P)',

'Header 2'					=> 'Заголовок уровня 2',
'Header 3'					=> 'Заголовок уровня 3',
'Header 4'					=> 'Заголовок уровня 4',

'Left'						=> 'Абзац (Ctrl + L)',
'Center'					=> 'Абзац с выравниванием по центру (Ctrl + E)',
'Right'						=> 'Абзац с выравниванием вправо (Ctrl + R)',
'Justify'					=> 'Растянутый абзац (Ctrl + J)',

'UL'						=> 'Список',
'OL'						=> 'Нумерованный список',
'LI'						=> 'Элемент списка',

'SUP'						=> 'Верхний индекс',
'SUB'						=> 'Нижний индекс',

'PRE'						=> 'Сохранить форматирование',
'CODE'						=> 'Код',

'BIG'						=> 'Надпись увеличенным шрифтом',
'SMALL'						=> 'Надпись уменьшенным шрифтом',

'NOBR'						=> 'Запретить перенос строк (Ctrl + O)',

'Cut'						=> 'Обозначить выдержку тегом <cut>',

'Input time format'			=> '[hour]:[minute], [day].[month].[year]', // Determines order of the input fields

// Preview tab
'Preview'					=> 'Предпросмотр',

// Comments tab
'Hide'						=> 'Скрыть',
'Leave hidden'				=> 'Оставить скрытым и не рассылать',
'Show'						=> 'Отобразить',
'Mark comment'				=> 'Отметить комментарий',
'Unmark comment'			=> 'Убрать отметку',
'Delete'					=> 'Удалить',
'Edit'						=> 'Редактировать',
'All comments'				=> 'Все комментарии',
'Go to editor'				=> 'Перейти к редактированию страницы',
'All comments to'			=> 'Все комментарии к «%s»',
'Show hidden comments'		=> 'Скрытые',
'Show last comments'		=> 'Последние',
'Show new comments'			=> 'Непроверенные',
'Hidden comments'			=> 'Скрытые комментарии на сайте',
'Last comments'				=> 'Последние комментарии на сайте',
'New comments'				=> 'Непроверенные комментарии на сайте',
'No comments'				=> 'Нет комментариев',
'Hidden'					=> 'скрыт',
'Subscribed'				=> 'подписан',
'Save'						=> 'Сохранить',
'Save info'					=> 'А еще работает CTRL + S',
'Show email'				=> 'Показывать e-mail',
'Name'						=> 'Имя',
'Date'						=> 'Время',
'IP'						=> 'IP',
'Comment'					=> 'Комментарий',
'Premoderation info'		=> 'Включен режим премодерации. Следующие комментарии ожидают проверки. Вы должны либо опубликовать их, либо удалить.',
'View comments'				=> 'Просмотреть комментарии',
'Unchecked comments'		=> 'Имеются комментарии, ожидающие проверки.',

// Pictures tab
'Pictures'					=> 'Картинки',

// Tags tab
'Tags:'						=> 'Ключевые слова:',
'Tag'						=> 'Ключевое слово',
'New'						=> '— Новое —',
'Replace tag'				=> 'Сохранить вместо:',
'Delete tag'				=> 'Удалить ключевое слово «%s»',
'Click tag'					=> 'Щелкните по слову, чтобы выбрать его',
'URL part'					=> 'Фрагмент URL:',

// Admin tab
'Administrate'				=> 'Администрирование',

// Stat tab
'Already published'			=> 'Сейчас на сайте',
'Articles'					=> 'Статей: %s',
'Comments'					=> 'Комментариев: %s',
'Server load'				=> 'Нагрузка на сервер',
'Stat'						=> 'Статистика',
'S2 version'				=> 'Версия S2',
'Environment'				=> 'Программная среда',
'N/A'						=> 'Информация недоступна',
'OS'						=> 'Операционная система: %s',
'PHP info'					=> 'Сведения о PHP на этом сервере',
'Accelerator'				=> 'Акселератор: %s',
'Database'					=> 'База данных',
'Rows'						=> 'Строк: %s',
'Size'						=> 'Объем данных: %s',

// Options tab
'Options'					=> 'Настройка',

// Users tab
'Users'						=> 'Пользователи',
'No other admin'			=> '<strong>Внимание!</strong> Вы не можете лишить себя прав администратора, потому что больше администраторов не останется.',
'No other admin delete'		=> '<strong>Внимание!</strong> Вы не можете удалить себя, потому что больше администраторов не останется.',
'Set email'					=> 'Задать адрес электронной почты',
'Change email'				=> 'Изменить адрес электронной почты',
'Set name'					=> 'Задать имя',
'Change name'				=> 'Изменить имя',
'Deny'						=> 'запретить',
'Allow'						=> 'разрешить',
'Username exists'			=> 'Учетная запись «%s» уже существует.',
'Password changed'			=> 'Пароль изменен.',
'Password unchanged'		=> 'Пароль совпадает с предыдущим.',
'Change password'			=> 'Изменить пароль',
'Delete user'				=> 'Удалить пользователя',
'Add user'					=> 'Добавить пользователя',
'Login'						=> 'Логин',
'Email'						=> 'Электронная почта',

// Extensions tab
'Extensions'				=> 'Расширения',
);

$lang_user_permissions = array (

'view'					=> 'Просмотр',
'view_hidden'			=> 'Секретный доступ',
'hide_comments'			=> 'Скрывать комментарии',
'edit_comments'			=> 'Редактировать комментарии',
'create_articles'		=> 'Создавать страницы',
'edit_site'				=> 'Редактировать сайт',
'edit_users'			=> 'Управлять пользователями'

);

$lang_user_permissions_help = array (

'view'					=> 'Просмотр опубликованных материалов, комментариев, загруженных картинок.',
'view_hidden'			=> 'Просмотр неопубликованных материалов и скрытых комментариев, IP-адресов и электронной почты комментаторов, содержимого вкладки администрирования. Изменение своих данных: имени, электронной почты и пароля.',
'hide_comments'			=> 'Возможность прятать или показывать комментарии, в том числе и непроверенные (модераторы).',
'edit_comments'			=> 'Возможность редактировать комментарии (модераторы).',
'create_articles'		=> 'Возможность создавать свои материалы и изменять их, загружать картинки, создавать и изменять ключевые слова (авторы).',
'edit_site'				=> 'Возможность изменять и удалять чужие материалы, изменять и удалять картинки, создавать и изменять ключевые слова (редакторы).',
'edit_users'			=> 'Возможность менять настройки сайта, создавать и редактировать учетные записи пользователей, устанавливать расширения (администраторы).'

);

$lang_templates = array(

''					=> '— как у раздела —',
'site.php'			=> 'Обычная страница',
'mainpage.php'		=> 'Главная страница',
'back_forward.php'	=> 'Страница без меню',
'+'					=> '— другой шаблон —',

);
