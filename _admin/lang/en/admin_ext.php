<?php

// Language definitions used in all admin files
$lang_admin_ext = array(

'Install extension'				=>	'Install extension',
'Upgrade extension'				=>	'Upgrade extension',
'Extensions available'			=>	'Extensions available for install or upgrade',
'Hotfixes available'			=>	'Hotfixes available for install',
'Installed extensions'			=>	'Installed extensions',
'Version'						=>	' v%s',
'Installed extensions warn'		=>	'<strong>WARNING!</strong> If you uninstall an extension, any data associated with that extension will be permanently deleted from the database and cannot be restored by re-installing the extension. If you wish to retain the data then you should disable the extension instead.',
'Uninstall'						=>	'Uninstall',
'Enable'						=>	'Enable',
'Disable'						=>	'Disable',
'Refresh hooks'  				=>	'Refresh hooks',
'Extension loading error'		=>	'Loading of extension “%s” failed.',
'Illegal ID'					=>	'The ID must contain only lowercase alphanumeric characters (a-z and 0-9) and the underscore character (_).',
'Missing manifest'				=>	'Missing Manifest.php.',
'Manifest class not found'      =>	'Class Manifest not found in Manifest.php.',
'ManifestInterface is not implemented'			=>	'Class Manifest does not implement ManifestInterface.',

'No installed extensions'		=>	'There are no installed extensions.',
'No available extensions'		=>	'There are no extensions available for install or upgrade.',
'Invalid extensions'			=>	'<strong>Warning!</strong> The extensions listed below were found in the extensions folder but are not available for install or upgrade because the errors displayed below were detected.',
'Extension by'					=>	'Created by %s.',

'Missing dependency'			=>	'The extension “%1$s” cannot be installed unless the following extensions are installed and enabled: %2$s.',
'Uninstall dependency'			=>	'The extension “%1$s” cannot be uninstalled before the following extensions are installed: %2$s',
'Disable dependency'			=>	'The extension “%1$s” cannot be disabled while the following extensions are enabled: %2$s.',
'Disabled dependency'			=>	'The extension “%1$s” cannot be enabled while the following extensiond are disabled: %2$s.',

);
