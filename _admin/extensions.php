<?php
/**
 * Extension management.
 *
 * Allows administrators to control the extensions and hotfixes installed in the site.
 *
 * @copyright (C) 2009-2024 Roman Parpalak, based on code (C) 2008-2009 PunBB
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */


use S2\Cms\Extensions\ManifestInterface;
use S2\Cms\Model\ExtensionCache;
use S2\Cms\Pdo\DbLayer;

if (!defined('S2_ROOT')) {
    die;
}

require S2_ROOT . '_admin/lang/' . Lang::admin_code() . '/admin_ext.php';

function s2_extension_list()
{
    global $lang_admin_ext;

    /** @var DbLayer $s2_db */
    $s2_db = \Container::get(DbLayer::class);

    $installedExtensions = [];
    $query               = [
        'SELECT'   => 'e.*',
        'FROM'     => 'extensions AS e',
        'ORDER BY' => 'e.title'
    ];

    $result = $s2_db->buildAndQuery($query);
    while ($cur_ext = $s2_db->fetchAssoc($result)) {
        $installedExtensions[$cur_ext['id']] = $cur_ext;
    }

    $extensionNum = 0;
    $failedNum    = 0;
    $itemNum      = 1;
    $ext_item     = array();
    $ext_error    = array();

    $d = dir(S2_ROOT . '_extensions');
    while (($entry = $d->read()) !== false) {
        if ($entry[0] === '.' || !is_dir(S2_ROOT . '_extensions/' . $entry)) {
            continue;
        }

        if (preg_match('/[^0-9a-z_]/', $entry)) {
            $ext_error[] = '<div class="extension error db' . ++$itemNum . '">' .
                '<h3>' . sprintf($lang_admin_ext['Extension loading error'], s2_htmlencode($entry)) . '</h3>' .
                '<p>' . $lang_admin_ext['Illegal ID'] . '</p>' .
                '</div>';
            ++$failedNum;
            continue;
        }

        if (!file_exists(S2_ROOT . '_extensions/' . $entry . '/Manifest.php')) {
            $ext_error[] = '<div class="extension error db' . ++$itemNum . '">' .
                '<h3>' . sprintf($lang_admin_ext['Extension loading error'], s2_htmlencode($entry)) . '</h3>' .
                '<p>' . $lang_admin_ext['Missing manifest'] . '</p>' .
                '</div>';
            ++$failedNum;
            continue;
        }

        $extensionClass = '\\s2_extensions\\' . $entry . '\\Manifest';
        if (!class_exists($extensionClass)) {
            $ext_error[] = '<div class="extension error db' . ++$itemNum . '">' .
                '<h3>' . sprintf($lang_admin_ext['Extension loading error'], s2_htmlencode($entry)) . '</h3>' .
                '<p>' . $lang_admin_ext['Manifest class not found'] . '</p>' .
                '</div>';
            ++$failedNum;
            continue;
        }

        $extensionManifest = new $extensionClass();
        if (!$extensionManifest instanceof ManifestInterface) {
            $ext_error[] = '<div class="extension error db' . ++$itemNum . '">' .
                '<h3>' . sprintf($lang_admin_ext['Extension loading error'], s2_htmlencode($entry)) . '</h3>' .
                '<p>' . $lang_admin_ext['ManifestInterface is not implemented'] . '</p>' .
                '</div>';
            ++$failedNum;
            continue;
        }

        if (!array_key_exists($entry, $installedExtensions) || version_compare($installedExtensions[$entry]['version'], $extensionManifest->getVersion(), '!=')) {
            $install_notes = array();
            if ($extensionManifest->getInstallationNote()) {
                $install_notes[] = s2_htmlencode(addslashes($extensionManifest->getInstallationNote()));
            }

            if (count($install_notes) > 1) {
                foreach ($install_notes as $index => $cur_note) {
                    $install_notes[$index] = ($index + 1) . '. ' . $cur_note;
                }
            }

            $admin_affected     = $extensionManifest->isAdminAffected();
            $buttons['install'] = '<button class="bitbtn ' . (isset($installedExtensions[$entry]['version']) ? 'upgr_ext' : 'inst_ext') . '" onclick="return changeExtension(\'install_extension\', \'' . s2_htmlencode(addslashes($entry)) . '\', \'' . implode('\\n', $install_notes) . '\', ' . $admin_affected . ');">' . (isset($installedExtensions[$entry]['version']) ? $lang_admin_ext['Upgrade extension'] : $lang_admin_ext['Install extension']) . '</button>';

            $ext_item[] = '<div class="extension available">' .
                '<div class="info"><h3 title="' . s2_htmlencode($entry) . '">' . s2_htmlencode($extensionManifest->getTitle()) . sprintf($lang_admin_ext['Version'], $extensionManifest->getVersion()) . '</h3>' .
                '<p>' . sprintf($lang_admin_ext['Extension by'], s2_htmlencode($extensionManifest->getAuthor())) . '</p></div>' .
                (($extensionManifest->getDescription() !== '') ? '<p class="description">' . s2_htmlencode($extensionManifest->getDescription()) . '</p>' : '') .
                '<div class="options">' . implode('<br />', $buttons) . '</div></div>';
            ++$extensionNum;
        }
    }
    $d->close();

    ob_start();

    echo '<h2>' . $lang_admin_ext['Extensions available'] . '</h2>';

    if ($extensionNum) {
        echo '<div class="extensions">' . implode('', $ext_item) . '</div>';
    } else {
        echo '<div class="info-box"><p>' . $lang_admin_ext['No available extensions'] . '</p></div>';
    }

    // If any of the extensions had errors
    if ($failedNum) {
        echo '<div class="info-box"><p class="important">' . $lang_admin_ext['Invalid extensions'] . '</p></div>';
        echo '<div class="extensions">' . implode('', $ext_error) . '</div>';
    }

    $installed_count = 0;
    $ext_item        = array();
    foreach ($installedExtensions as $id => $ext) {
        $buttons = array(
            'flip'      => '<button class="bitbtn flip_ext" onclick="return changeExtension(\'flip_extension\', \'' . s2_htmlencode(addslashes($id)) . '\', \'\', ' . $ext['admin_affected'] . ');">' . ($ext['disabled'] != '1' ? $lang_admin_ext['Disable'] : $lang_admin_ext['Enable']) . '</button>',
            'uninstall' => '<button class="bitbtn uninst_ext" onclick="return changeExtension(\'uninstall_extension\', \'' . s2_htmlencode(addslashes($id)) . '\', \'' . s2_htmlencode(addslashes($ext['uninstall_note'] ?? '')) . '\', ' . $ext['admin_affected'] . ');">' . $lang_admin_ext['Uninstall'] . '</button>'
        );

        $extra_info = '';

        $ext_item[] = '<div class="extension ' . ($ext['disabled'] == '1' ? 'disabled' : 'enabled') . '">' .
            '<div class="info"><h3 title="' . s2_htmlencode($id) . '">' . s2_htmlencode($ext['title']) . sprintf($lang_admin_ext['Version'], $ext['version']) . '</h3>' .
            '<p>' . sprintf($lang_admin_ext['Extension by'], s2_htmlencode($ext['author'])) . '</p>' . $extra_info . '</div>' .
            (($ext['description'] != '') ? '<p class="description">' . s2_htmlencode($ext['description']) . '</p>' : '') .
            '<div class="options">' . implode(' ', $buttons) . '</div></div>';

        $installed_count++;
    }

    if ($installed_count > 0) {
        echo '<button class="bitbtn refresh-hooks" style="position: relative; float:right; top:1em;" onclick="return RefreshHooks();">' . $lang_admin_ext['Refresh hooks'] . '</button>';
    }

    echo '<h2>' . $lang_admin_ext['Installed extensions'] . '</h2>';
    if ($installed_count > 0) {
        echo '<div class="info-box"><p class="important">' . $lang_admin_ext['Installed extensions warn'] . '</p></div>';
        echo '<div class="extensions">' . implode('', $ext_item) . '</div>';
    } else {
        echo '<div class="info-box"><p>' . $lang_admin_ext['No installed extensions'] . '</p></div>';
    }

    return ob_get_clean();
}

function s2_install_extension($id)
{
    global $lang_admin_ext;
    /** @var DbLayer $s2_db */
    $s2_db = \Container::get(DbLayer::class);

    $id       = preg_replace('/[^0-9a-z_]/', '', $id);
    $messages = [];

    if (!file_exists(S2_ROOT . '_extensions/' . $id . '/Manifest.php')) {
        $messages[] = $lang_admin_ext['Missing manifest'];
        return [sprintf($lang_admin_ext['Extension loading error'], $id)] + $messages;
    }

    $extensionClass = '\\s2_extensions\\' . $id . '\\Manifest';
    if (!class_exists($extensionClass)) {
        $messages[] = $lang_admin_ext['Manifest class not found'];
        return [sprintf($lang_admin_ext['Extension loading error'], $id)] + $messages;
    }

    $extensionManifest = new $extensionClass();
    if (!$extensionManifest instanceof ManifestInterface) {
        $messages[] = $lang_admin_ext['ManifestInterface is not implemented'];
        return [sprintf($lang_admin_ext['Extension loading error'], $id)] + $messages;
    }

    $query = [
        'SELECT' => 'e.id',
        'FROM'   => 'extensions AS e',
        'WHERE'  => 'e.disabled=0'
    ];

    $result = $s2_db->buildAndQuery($query);

    $installed_ext = [];
    while ($row = $s2_db->fetchAssoc($result)) {
        $installed_ext[] = $row['id'];
    }

    $broken_dependencies = [];
    foreach ($extensionManifest->getDependencies() as $dependency) {
        if (!in_array($dependency, $installed_ext, true)) {
            $broken_dependencies[] = $dependency;
        }
    }

    if (!empty($broken_dependencies)) {
        return $messages + array(sprintf($lang_admin_ext['Missing dependency'], $id, implode(', ', $broken_dependencies)));
    }

    // Is there some uninstall code to store in the db?
    $uninstall_code = 'NULL';

    // Is there an uninstall note to store in the db?
    $uninstall_note = $extensionManifest->getUninstallationNote() !== null ? '\'' . $s2_db->escape(trim($extensionManifest->getUninstallationNote())) . '\'' : 'NULL';

    // Is this a fresh install or an upgrade?
    $query = [
        'SELECT' => 'e.version',
        'FROM'   => 'extensions AS e',
        'WHERE'  => 'e.id=\'' . $s2_db->escape($id) . '\''
    ];

    $result = $s2_db->buildAndQuery($query);
    if ($curr_version = $s2_db->result($result)) {
        // Run the author supplied installation code
        $extensionManifest->install($s2_db, $curr_version);

        // Update the existing extension
        $query = [
            'UPDATE' => 'extensions',
            'SET'    => 'title=\'' . $s2_db->escape($extensionManifest->getTitle()) . '\', version=\'' . $s2_db->escape($extensionManifest->getVersion()) . '\', description=\'' . $s2_db->escape($extensionManifest->getDescription()) . '\', author=\'' . $s2_db->escape($extensionManifest->getAuthor()) . '\', admin_affected=\'' . ($extensionManifest->isAdminAffected() ? '1' : '0') . '\', uninstall=' . $uninstall_code . ', uninstall_note=' . $uninstall_note . ', dependencies=\'|' . implode('|', $extensionManifest->getDependencies()) . '|\'',
            'WHERE'  => 'id=\'' . $s2_db->escape($id) . '\''
        ];

        $s2_db->buildAndQuery($query);
    } else {
        // Run the author supplied installation code
        $extensionManifest->install($s2_db, null);

        // Add the new extension
        $query = [
            'INSERT' => 'id, title, version, description, author, admin_affected, uninstall, uninstall_note, dependencies',
            'INTO'   => 'extensions',
            'VALUES' => '\'' . $s2_db->escape($id) . '\', \'' . $s2_db->escape($extensionManifest->getTitle()) . '\', \'' . $s2_db->escape($extensionManifest->getVersion()) . '\', \'' . $s2_db->escape($extensionManifest->getDescription()) . '\', \'' . $s2_db->escape($extensionManifest->getAuthor()) . '\', \'' . ($extensionManifest->isAdminAffected() ? '1' : '0') . '\', ' . $uninstall_code . ', ' . $uninstall_note . ', \'|' . implode('|', $extensionManifest->getDependencies()) . '|\'',
        ];

        $s2_db->buildAndQuery($query);
    }

    // Regenerate the hooks cache
    /** @var ExtensionCache $cache */
    $cache = \Container::get(ExtensionCache::class);
    $cache->clear(); // TODO also clear DynamicConfigProvider cache
    $cache->generateHooks();

    return $messages;
}

function s2_flip_extension($id)
{
    global $lang_admin_ext;
    /** @var DbLayer $s2_db */
    $s2_db = \Container::get(DbLayer::class);

    $id = preg_replace('/[^0-9a-z_]/', '', $id);

    // Fetch the current status of the extension
    $query = [
        'SELECT' => 'e.disabled',
        'FROM'   => 'extensions AS e',
        'WHERE'  => 'e.id=\'' . $s2_db->escape($id) . '\''
    ];

    $result = $s2_db->buildAndQuery($query);

    if ($row = $s2_db->fetchAssoc($result)) {
        // Are we disabling or enabling?
        $disable = $row['disabled'] == '0';
    } else {
        return sprintf($lang_admin_ext['Extension loading error'], $id);
    }

    // Check dependencies
    if ($disable) {
        $query = [
            'SELECT' => 'e.id',
            'FROM'   => 'extensions AS e',
            'WHERE'  => 'e.disabled=0 AND e.dependencies LIKE \'%|' . $s2_db->escape($id) . '|%\''
        ];

        $result = $s2_db->buildAndQuery($query);

        $dependency_ids = [];
        while ($dependency = $s2_db->fetchAssoc($result)) {
            $dependency_ids[] = $dependency['id'];
        }

        if (!empty($dependency_ids)) {
            return sprintf($lang_admin_ext['Disable dependency'], $id, implode(', ', $dependency_ids));
        }
    } else {
        $query = [
            'SELECT' => 'e.dependencies',
            'FROM'   => 'extensions AS e',
            'WHERE'  => 'e.id=\'' . $s2_db->escape($id) . '\''
        ];

        $result = $s2_db->buildAndQuery($query);

        $dependencies = $s2_db->fetchAssoc($result);
        $dependencies = explode('|', substr($dependencies['dependencies'], 1, -1));

        $query = [
            'SELECT' => 'e.id',
            'FROM'   => 'extensions AS e',
            'WHERE'  => 'e.disabled=0'
        ];

        $result = $s2_db->buildAndQuery($query);

        $installed_ext = [];
        while ($row = $s2_db->fetchAssoc($result)) {
            $installed_ext[] = $row['id'];
        }

        $broken_dependencies = array();
        foreach ($dependencies as $dependency) {
            if (!empty($dependency) && !in_array($dependency, $installed_ext, true)) {
                $broken_dependencies[] = $dependency;
            }
        }

        if (!empty($broken_dependencies)) {
            return sprintf($lang_admin_ext['Disabled dependency'], $id, implode(', ', $broken_dependencies));
        }
    }

    $query = [
        'UPDATE' => 'extensions',
        'SET'    => 'disabled=' . ($disable ? '1' : '0'),
        'WHERE'  => 'id=\'' . $s2_db->escape($id) . '\''
    ];

    $s2_db->buildAndQuery($query);


    // Regenerate the hooks cache
    /** @var ExtensionCache $cache */
    $cache = \Container::get(ExtensionCache::class);
    $cache->clear(); // TODO also clear DynamicConfigProvider cache
    $cache->generateHooks();

    return '';
}

function s2_uninstall_extension($id)
{
    global $lang_admin_ext;
    /** @var DbLayer $s2_db */
    $s2_db = \Container::get(DbLayer::class);

    $messages = array();

    $id = preg_replace('/[^0-9a-z_]/', '', $id);

    // Fetch info about the extension
    $query = [
        'SELECT' => 'e.title, e.version, e.description, e.author, e.uninstall, e.uninstall_note',
        'FROM'   => 'extensions AS e',
        'WHERE'  => 'e.id=\'' . $s2_db->escape($id) . '\''
    ];

    $result = $s2_db->buildAndQuery($query);

    $ext_data = $s2_db->fetchAssoc($result);
    if (!$ext_data) {
        return array(sprintf($lang_admin_ext['Extension loading error'], $id)) + $messages;
    }

    // Check dependencies
    $query = [
        'SELECT' => 'e.id',
        'FROM'   => 'extensions AS e',
        'WHERE'  => 'e.dependencies LIKE \'%|' . $s2_db->escape($id) . '|%\''
    ];

    $result = $s2_db->buildAndQuery($query);

    $dependencies = array();
    while ($row = $s2_db->fetchAssoc($result)) {
        $dependencies[] = $row['id'];
    }

    if (!empty($dependencies)) {
        return array(sprintf($lang_admin_ext['Uninstall dependency'], $id, implode(', ', $dependencies))) + $messages;
    }

    // Run uninstall code
    $extensionClass = '\\s2_extensions\\' . $id . '\\Manifest';
    /** @var ManifestInterface $extensionManifest */
    $extensionManifest = new $extensionClass();
    $extensionManifest->uninstall($s2_db);

    $query = [
        'DELETE' => 'extensions',
        'WHERE'  => 'id=\'' . $s2_db->escape($id) . '\''
    ];

    $s2_db->buildAndQuery($query);

    // Regenerate the hooks cache
    /** @var ExtensionCache $cache */
    $cache = \Container::get(ExtensionCache::class);
    $cache->clear(); // TODO also clear DynamicConfigProvider cache
    $cache->generateHooks();

    return $messages;
}
