<?php

// Language definitions used in all admin files
$lang_admin_ext = array(

'Install extension'				=>	'Установить расширение',
'Upgrade extension'				=>	'Обновить расширение',
'Extensions available'			=>	'Расширения для установки или обновления',
'Hotfixes available'			=>	'Hotfixes available for install',
'Installed extensions'			=>	'Установленные расширения',
'Version'						=>	' v%s',
'Installed extensions warn'		=>	'<strong>Внимание!</strong> При удалении расширения все связанные с ним данные навсегда удаляются из базы данных. Их нельзя будет восстановить повторной установкой расширения. Лучше отключите расширение, особенно если хотите сохранить данные.',
'Uninstall'						=>	'Удалить',
'Enable'						=>	'Включить',
'Disable'						=>	'Отключить',
'Refresh hooks'  				=>	'Обновить хуки',
'Extension loading error'		=>	'Не удалось загрузить расширение «%s».',
'Illegal ID'					=>	'Идентификатор расширения должен совпадать с названием папки расширения и состоять только из строчных английских букв или цифр (a-z, 0-9) или символа подчеркивания (_).',
'Missing manifest'				=>	'Отсутствует файл Manifest.php.',
'Manifest class not found'      =>	'Класс Manifest не найден в Manifest.php.',
'ManifestInterface is not implemented'			=>	'Класс Manifest не реализует ManifestInterface.',

'No installed extensions'		=>	'Нет установленных расширений.',
'No available extensions'		=>	'Нет расширений, готовых к установке или обновлению.',
'Invalid extensions'			=>	'<strong>Внимание!</strong> В папке «_extensions» были найдены следующие расширения. Однако их невозможно установить по указанным ниже причинам.',
'Extension by'					=>	'Автор: %s.',

'Missing dependency'			=>	'Расширение «%1$s» нельзя установить, пока не установлены и не включены следующие расширения: %2$s.',
'Uninstall dependency'			=>	'Расширение «%1$s» нельзя удалить, пока установлены следующие расширения: %2$s.',
'Disable dependency'			=>	'Расширение «%1$s» нельзя отключить, пока включены следующие расширения: %2$s.',
'Disabled dependency'			=>	'Расширение «%1$s» нельзя включить, пока отключены следующие расширения: %2$s.',

);
