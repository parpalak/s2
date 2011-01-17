<?php

// Language definitions used in install.php
$lang_install = array(

// Install Form
'Install S2'				=>	'Установка S2 %s',
'Part 0'					=>	'Выбор языка',
'Choose language help'		=>	'В списке отображены доступные языки. Если вам непонятны инструкции на русском, попробуйте выбрать другой язык.',
'Installer language'		=>	'Язык установки:',
'Choose language legend'	=>	'Язык установки',
'Choose language'			=>	'Изменить язык',
'Part1'						=>	'Настройка базы данных',
'Part1 intro'				=>	'Введите информацию о базе данных, в которую устанавливается S2. Вы должны узнать все параметры, запрашиваемые ниже. Обратитесь в техническую поддержку хостинга в случае каких-либо затруднений.',
'Database type'				=>	'Тип базы данных',
'Database name'				=>	'Название базы данных',
'Database server'			=>	'Сервер базы данных',
'Database username'			=>	'Имя пользователя',
'Database password'			=>	'Пароль',
'Database user pass'		=>	'Имя и пароль пользователя базы данных',
'Table prefix'				=>	'Префикс таблиц',
'Database type info'		=>	'S2 может работать с базами данных MySQL, PostgreSQL и SQLite. В выпадающем списке ниже есть только те базы данных, с которыми будет работать PHP на этом сервере. За подробной информацией о возможности использования конкретных версий баз данных обратитесь к документации.',
'Mysql type info'			=>	'S2 определил, что на этом сервере PHP может обращаться к базе данных MySQL двумя разными способами. Эти способы обозначены ниже как «<em>standard</em>» и «<em>improved</em>». Если вы не знаете, какой из них лучше использовать, попробуйте сначала improved. Если он не заработает, выберите standard.',
'MySQL InnoDB info'			=>	'Возможно, сервер MySQL поддерживает таблицы <a href="http://dev.mysql.com/doc/refman/5.0/en/innodb-overview.html">InnoDB</a>. Если вы планируете сделать большой сайт, рассмотрите возможность использования таблиц этого типа. Но если сомневаетесь, лучше не использовать их.',
'Database server info'		=>	'Введите адрес сервера базы данных (например, <em>localhost</em>, <em>mysql1.example.com</em> или <em>208.77.188.166</em>). Вы можете указать и порт, если сервер базы данных работает на нестандартном порту (например, <em>localhost:3580</em>). For SQLite support, just enter anything or leave it at \'localhost\'.',
'Database name info'		=>	'Укажите название базы данных, в которой S2 будет хранить информацию. Эта база данных должна существовать. For SQLite, this is the relative path to the database file. If the SQLite database file does not exist, S2 will attempt to create it.',
'Database username info'	=>	'Введите имя пользователя и пароль, необходимые для подключения к базе данных. Ignore for SQLite.',
'Table prefix info'			=>	'Вы можете указать строку, с которой будут начинаться названия всех создаваемых таблиц (например, <em>test_</em>). В одну и ту же базу данных можно будет установить несколько копий S2 с разными префиксами таблиц.',
'Part1 legend'				=>	'Информация о базе данных',
'Database type help'		=>	'Выберите тип базы данных.',
'Database server help'		=>	'Адрес сервера баз данных. For SQLite enter anything.',
'Database name help'		=>	'Существующая база данных, в которую будет установлен S2.',
'Database username help'	=>	'Для подключения к базе данных. Ignore for SQLite.',
'Database password help'	=>	'Для подключения к базе данных. Ignore for SQLite.',
'Table prefix help'			=>	'Необязательный префикс таблиц, например, «test_».',
'Part2'						=>	'Учетная запись администратора',
'Part2 legend'				=>	'Параметры учетной записи администратора',
'Part2 intro'				=>	'Во время установки автоматически создается учетная запись администратора сайта. Укажите ее  параметры. Позднее вы сможете создать другие учетные записи и установить нужные права для доступа к панели управления.',
'Admin username'			=>	'Логин',
'Admin username info'		=>	'Выберите логин администратора. Учетная запись будет создана с этим логином и пустым паролем. Вы сможете изменить пароль позднее.',
'Admin e-mail'				=>	'Электронная почта',
'Admin e-mail info'			=>	'Если вы укажете адрес <em>электронной почты администратора</em>, вы будете получать на него уведомления, когда кто-то оставляет комментарий. Адрес электронной почты администратора никогда не отображается на сайте (его могут увидеть в панели управления другие пользователи с достаточными правами доступа). Потом, если понадобится, вы сможете изменить этот адрес. А еще значение этого поля будет присвоено адресу <em>электронной почты автора сайта</em>. Электронная почта автора сайта используется в RSS и в поле «от кого» при рассылке комментариев подписчикам и в принципе может попасть к спамерам. Электронная почта автора сайта может быть изменена позднее независимо от адресов электронной почты, связанных с учетными записями.',
'Username help'				=>	'От 2 до 25 символов.',
'E-mail address help'		=>	'Адрес электронной почты администратора.',
'Part3'						=>	'Настройка сайта',
'Part3 legend'				=>	'Информация о сайте',
'Part3 intro'				=>	'Укажите требуемую информацию о сайте.',
'Base URL'					=>	'URL сайта',
'Base URL info'				=>	'Сюда необходимо ввести адрес будущего сайта без завершающего слеша (например, <em><nobr>http://example.com</nobr></em> или <em><nobr>http://example.com/~myuser</nobr></em>). Вы обязательно должны указать корректное значение, иначе сайт не заработает! Заметьте, сначала в этом поле содержится всего лишь догадка о том, каким должен быть URL сайта.',
'Base URL help'				=>	'URL (без завершающего слеша) устанавливаемого сайта. Прочитайте вышеприведенные инструкции.',
'Default language'			=>	'Язык сайта',
'Default language help'		=>	'Если вы удалите текущий языковой пакет (русский), вы должны будете поменять это значение.',
'Start install'				=>	'Начать установку', // Label for submit button
'Required'					=>	'(обязательно)',


// Install errors
'No database support'		=>	'Кажется, PHP не поддерживает ни одной базы данных из тех, которые может использовать S2. Чтобы S2 установился, PHP должен поддерживать или MySQL, или PostgreSQL, или SQLite.',
'Missing database name'		=>	'Вы должы ввести название базы данных. Вернитесь назад и исправьте ошибку.',
'Username too long'			=>	'Логин администратора длиннее 25 символов. Вернитесь назад и исправьте ошибку.',
'Username too short'		=>	'Логин должен быть не короче двух символов. Вернитесь назад и исправьте ошибку.',
'Invalid email'				=>	'Неправильный адрес электронной почты администратора. Please go back and correct.',
'Missing base url'			=>	'Вы должны ввести URL сайта. Please go back and correct.',
'No such database type'		=>	'\'%s\' - это недопустимый тип базы данных.',
'Invalid MySQL version'		=>	'На сервере установлена база данных MySQL версии %1$s. Для установки S2 требуется, по крайней мере, MySQL %2$s. Вы должны обновить MySQL, чтобы продолжить установку.',
'Invalid table prefix'		=>	'Префикс таблиц \'%s\' содержит недопустимые сочетания символов. Префикс может содержать английские буквы от a до z, цифры и символ подчеркивания. Он не должен начинаться с цифры. Выберите другой префикс.',
'Too long table prefix'		=>	'Префикс таблиц \'%s\' слишком длинный, длиннее 40 символов. Выберите другой префикс.',
'SQLite prefix collision'	=>	'The table prefix \'sqlite_\' is reserved for use by the SQLite engine. Please choose a different prefix.',
'S2 already installed'		=>	'В базе данных «%2$s» уже есть таблица «%1$susers». Возможно, S2 был установлен ранее. Или установлено другое программное обеспечение, создавшее таблицы, которые S2 собирался создать и использовать. Если вы хотите установить несколько копий S2 в одну и ту же базу данных, вы должны выбрать другой префикс таблиц.',
'Invalid language'			=>	'В выбранном вами языковом пакете есть ошибки, или такого пакета вообще не существует. Перепроверьте языковой пакет и попробуйте снова.',

// Used in the install
'Site name'					=>	'Сайт на движке S2',
'Main Page'					=>	'Главная',
'Section example'			=>	'Раздел 1',
'Page example'				=>	'Страница 1',
'Page text'					=>	'Если вы видите этот текст, установка движка S2 завершилась успешно. Теперь вы можете перейти в <a href="./---">панель управления</a> и настроить этот сайт.',


// Installation completed form
'Success description'		=>	'Поздравляем! S2 %s устанавливается успешно.',
'Success welcome'			=>	'Выполните следующую инструкцию, чтобы завершить установку.',
'Final instructions'		=>	'Завершение установки',
'No write info 1'			=>	'<strong>Внимание!</strong> Чтобы завершить установку, нажмите на эту кнопку и скачайте файл config.php. Затем загрузите этот файл на сервер в ту папку, в которую вы закачивали файлы S2.',
'No write info 2'			=>	'После загрузки файла config.php S2 будет установлен окончательно и вы сможете %s.',
'Go to index'				=>	'пройти на главную страницу сайта',
'Warning'					=>	'Предупреждение!',
'No cache write'			=>	'<strong>Отсутствуют права на запись в папку «_cache»!</strong> Во время работы S2 пишет файлы в папку «<em>_cache</em>». Скрипты PHP должны обладать правами для создания файлов в этой папке. Измените разрешения на доступ к папке «_cache» командой chmod. Если не знаете, какие разрешения устанавливать, установите 0777.',
'No pictures write'			=>	'<strong>Отсутствуют права на запись в папку «_pictures»!</strong> S2 сохраняет загруженные картинки и другие файлы в папку «<em>_pictures</em>». Если вы планируете загружать картинки, убедитесь, что скрипты PHP обладают правами для создания файлов в этой папке. При необходимости измените разрешения на доступ к папке «_pictures» командой chmod. Если не знаете, какие разрешения устанавливать, установите 0777.',
'File upload alert'			=>	'<strong>Кажется, загрузка файлов на сервер запрещена!</strong> Если вы хотите загружать картинки через панель управления, вы должны разрешить загрузку файлов (параметр file_uploads в php.ini).',
'Download config'			=>	'Скачать config.php', // Label for submit button
'Write info'				=>	'S2 полностью установлен! Сейчас вы можете %s.',
);