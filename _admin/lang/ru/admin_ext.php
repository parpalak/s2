<?php

// Language definitions used in all admin files
$lang_admin_ext = array(

'Install extension'				=>	'Установить расширение',
'Upgrade extension'				=>	'Обновить расширение',
'Extensions available'			=>	'Расширения для установки или обновления',
'Hotfixes available'			=>	'Hotfixes available for install',
'Installed extensions'			=>	'Установленные расширения',
'Version'						=>	' v%s',
'Hotfix'						=>	'Hotfix',
'Installed hotfixes'			=>	'Installed hotfixes',
'Installed extensions warn'		=>	'<strong>Внимание!</strong> При удалении расширения все связанные с ним данные навсегда удаляются из базы данных. Их нельзя будет восстановить повторной установкой расширения. Лучше отключите расширение, особенно если хотите сохранить данные.',
'Uninstall hotfix'				=>	'Uninstall hotfix',
'Uninstall'						=>	'Удалить',
'Enable'						=>	'Включить',
'Disable'						=>	'Отключить',
'Refresh hooks'  				=>	'Обновить хуки',
'Extension loading error'		=>	'Не удалось загрузить расширение «%s».',
'Illegal ID'					=>	'Идентификатор расширения должен совпадать с названием папки расширения и состоять только из строчных английских букв или цифр (a-z, 0-9) или символа подчеркивания (_).',
'Maxtestedon warning'			=>	'Это расширение не тестировалось на данной версии S2. Может оказаться так, что оно не совместимо с ней.',
'Missing manifest'				=>	'Отсутствует файл manifest.xml.',
'Failed parse manifest'			=>	'Не могу разобрать файл manifest.xml.',

'extension root error'			=>	'Корневой элемент «extension» отсутствует или имеет неправильный формат.',
'extension/engine error'		=>	'Отсутствует атрибут «for» у корневого элемента. Это расширение не для S2.',
'extension/engine error2'		=>	'Атрибут «for» корневого элемента имеет неправильное значение. Это расширение не для S2.',
'extension/engine error3'		=>	'Отсутствует атрибут «engine» у корневого элемента.',
'extension/engine error4'		=>	'Версия формата расширения не поддерживается.',
'extension/id error'			=>	'Элемент extension/id отсутствует или имеет неправильный формат.',
'extension/id error2'			=>	'Элемент extension/id не соответствует имени папки.',
'extension/title error'			=>	'Элемент extension/title отсутствует или имеет неправильный формат.',
'extension/version error'		=>	'Элемент extension/version отсутствует или имеет неправильный формат.',
'extension/description error'	=>	'Элемент extension/description отсутствует или имеет неправильный формат.',
'extension/author error'		=>	'Элемент extension/author отсутствует или имеет неправильный формат.',
'extension/minversion error'	=>	'Элемент extension/minversion отсутствует или имеет неправильный формат.',
'extension/minversion error2'	=>	'Для этого расширения нужен S2 версии %s и выше.',
'extension/maxtestedon error'	=>	'Элемент extension/maxtestedon отсутствует или имеет неправильный формат.',
'extension/note error'			=>	'Элемент extension/note имеет неправильный формат.',
'extension/note error2'			=>	'У элемента extension/note отсутствует атрибут «type».',
'extension/hooks/hook error'	=>	'Элемент extension/hooks/hook отсутствует или имеет неправильный формат.',
'extension/hooks/hook error2'	=>	'У элемента extension/hooks/hook отсутствует атрибут «id».',
'extension/hooks/hook error3'	=>	'У элемента extension/hooks/hook недопустимое значение атрибута «priority».',
'extension/hooks/hook error4'	=>	'Содержимое элемента extension/hooks/hook заканчивается не в режиме PHP.',
'No XML support'				=>	'У PHP на этом сервере нет встроенной поддержки XML (функция xml_parser_create). Без нее не могут работать расширения S2. Обратитесь в техническую поддержку вашего хостинга.',
'No installed extensions'		=>	'Нет установленных расширений.',
'No installed hotfixes'			=>	'There are no installed hotfixes.',
'No available extensions'		=>	'Нет расширений, готовых к установке или обновлению.',
'No available hotfixes'			=>	'There are no hotfixes available for install.',
'Invalid extensions'			=>	'<strong>Внимание!</strong> В папке «_extensions» были найдены следующие расширения. Однако их невозможно установить по указанным ниже причинам.',
'Hotfix installed'				=>	'Hotfix installed.',
'Hotfix uninstalled'			=>	'Hotfix uninstalled.',
'Hotfix download failed'		=>	'Не удалось скачать заплатку. Повторите попытку через некоторое время.',
'Hotfix disabled'				=>	'Hotfix disabled.',
'Hotfix enabled'				=>	'Hotfix enabled.',
'Extension by'					=>	'Автор: %s.',
'Hotfix description'			=>	'This hotfix for your S2 installation was detected by automatic update.',
'Install hotfix'				=>	'Install hotfix',

'Missing dependency'			=>	'Расширение «%1$s» нельзя установить, пока не установлены и не включены следующие расширения: %2$s.',
'Uninstall dependency'			=>	'Расширение «%1$s» нельзя удалить, пока установлены следующие расширения: %2$s.',
'Disable dependency'			=>	'Расширение «%1$s» нельзя отключить, пока включены следующие расширения: %2$s.',
'Disabled dependency'			=>	'Расширение «%1$s» нельзя включить, пока отключены следующие расширения: %2$s.',

);
