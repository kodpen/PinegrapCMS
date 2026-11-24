<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Security Scan's rules on accounts and settings: a new Administrator or
 * Designer account, a new application key, a core folder anyone on the
 * server may write to, risky PHP settings and the PHP security fixes
 * (PHP2Shell) that System Status checks. Each rule turns what it reads into
 * sightings of the finding model (includes/fn/security_scan.php); the scan
 * merges them and only reads, so nothing here changes an account, a key, a
 * folder or a setting.
 *
 * The rules are pure functions of the rows and the environment they are
 * given; pg_security_accounts_scan() reads both and is what the scan runs.
 * A finding's detail is JSON facts, never a sentence: the reason is drawn
 * in the reader's language by pg_security_accounts_reason().
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// An account or a key is new for this long after it was made. A finding
// nobody acted on turns gone after it; Ignore keeps it out of the way sooner.
define('PG_SECURITY_NEW_WINDOW', 30 * 86400);

/**
 * The kinds this module reports.
 *
 * @return array
 */
function pg_security_accounts_kinds()
{
    return array(
        'account_new_admin',
        'account_new_designer',
        'api_key_new',
        'core_folder_writable',
        'php_allow_url_include',
        'php_display_errors',
        'php_security_fixes',
    );
}

/**
 * The place of a server setting: "setting:<name>". The software folder
 * itself is the setting "software_folder", since a path place cannot name it.
 *
 * @param string $name
 * @return string
 */
function pg_security_place_setting($name)
{
    if (!is_string($name) || (preg_match('/^[a-z][a-z0-9_]{0,39}$/', $name) !== 1)) {
        return '';
    }

    return 'setting:' . $name;
}

/**
 * Sightings for new staff accounts and new application keys.
 *
 * $users are user rows (user_id, user_role, user_created); an Administrator
 * (0) or Designer (1) made inside the window is a finding. An account whose
 * creation time is unknown (0, made before the column) is not new.
 * $apps are api_apps rows (id, name, kind, status, created_timestamp); an
 * active 'server' application, the kind that carries a key and a secret,
 * made inside the window is a finding. Device and OAuth applications are
 * made by a person's own sign-in and are listed on their own screens.
 *
 * @param array $users
 * @param array $apps
 * @param int $now
 * @return array Sightings
 */
function pg_security_accounts_sightings(array $users, array $apps, $now)
{
    $now = (int) $now;
    $sightings = array();

    foreach ($users as $user) {
        $created = isset($user['user_created']) ? (int) $user['user_created'] : 0;
        $role = isset($user['user_role']) ? (string) $user['user_role'] : '';

        if (($created <= 0) || ($created < $now - PG_SECURITY_NEW_WINDOW) || (($role !== '0') && ($role !== '1'))) {
            continue;
        }

        $sighting = pg_security_sighting(
            ($role === '0') ? 'account_new_admin' : 'account_new_designer',
            pg_security_place_record('user', isset($user['user_id']) ? (string) $user['user_id'] : ''),
            ($role === '0') ? PG_SECURITY_SEVERITY_HIGH : PG_SECURITY_SEVERITY_MEDIUM,
            json_encode(array('created' => $created))
        );

        if ($sighting !== null) {
            $sightings[] = $sighting;
        }
    }

    foreach ($apps as $app) {
        $created = isset($app['created_timestamp']) ? (int) $app['created_timestamp'] : 0;

        if (($created <= 0) || ($created < $now - PG_SECURITY_NEW_WINDOW)
            || !isset($app['kind'], $app['status']) || ($app['kind'] !== 'server') || ($app['status'] !== 'active')) {
            continue;
        }

        $sighting = pg_security_sighting(
            'api_key_new',
            pg_security_place_record('api_app', isset($app['id']) ? (string) $app['id'] : ''),
            PG_SECURITY_SEVERITY_MEDIUM,
            json_encode(array('created' => $created, 'name' => isset($app['name']) ? (string) $app['name'] : ''))
        );

        if ($sighting !== null) {
            $sightings[] = $sighting;
        }
    }

    return $sightings;
}

/**
 * Is an ini value on? ini_get() answers '1', 'On', 'yes' or 'true' in any
 * case for on, and false for a setting PHP does not know.
 *
 * @param mixed $value
 * @return bool
 */
function pg_security_ini_on($value)
{
    return is_string($value) && in_array(strtolower(trim($value)), array('1', 'on', 'yes', 'true'), true);
}

/**
 * May anyone on the server write to a folder with this mode (the "others"
 * write bit)? false for a mode that could not be read.
 *
 * @param mixed $mode
 * @return bool
 */
function pg_security_mode_world_writable($mode)
{
    return is_int($mode) && (($mode & 0002) === 0002);
}

/**
 * Sightings for the server's settings, from the environment
 * pg_security_settings_env() reads:
 * - 'windows': no mode bits to read, so the folder rule is skipped.
 * - 'folders': core folder path ('' for the software folder) => its mode, or
 *   false when unreadable. A folder others may write to lets any account or
 *   site on the server change the code. The owner's own write access is not
 *   a finding: the update needs it.
 * - 'allow_url_include': on lets an include run code from another host.
 * - 'display_errors': on (not 'stderr') shows paths and queries to visitors.
 * - 'php_version', 'php2shell' and 'php2shell_fixed_in': pg_php2shell_status()
 *   read as System Status reads it; missing is critical, unknown medium, and
 *   likely_fixed passes as it does there.
 *
 * @param array $env
 * @return array Sightings
 */
function pg_security_settings_sightings(array $env)
{
    $sightings = array();

    if (empty($env['windows']) && isset($env['folders']) && is_array($env['folders'])) {
        foreach ($env['folders'] as $folder => $mode) {
            if (!pg_security_mode_world_writable($mode)) {
                continue;
            }

            $folder = (string) $folder;
            $sightings[] = pg_security_sighting(
                'core_folder_writable',
                ($folder === '') ? pg_security_place_setting('software_folder') : pg_security_place_path($folder),
                PG_SECURITY_SEVERITY_HIGH,
                json_encode(array('mode' => sprintf('%04o', $mode & 07777)))
            );
        }
    }

    if (isset($env['allow_url_include']) && pg_security_ini_on($env['allow_url_include'])) {
        $sightings[] = pg_security_sighting('php_allow_url_include', pg_security_place_setting('allow_url_include'), PG_SECURITY_SEVERITY_HIGH, '{}');
    }

    if (isset($env['display_errors']) && pg_security_ini_on($env['display_errors'])) {
        $sightings[] = pg_security_sighting('php_display_errors', pg_security_place_setting('display_errors'), PG_SECURITY_SEVERITY_LOW, '{}');
    }

    $state = isset($env['php2shell']) ? (string) $env['php2shell'] : '';

    if (($state === 'missing') || ($state === 'unknown')) {
        $sightings[] = pg_security_sighting(
            'php_security_fixes',
            pg_security_place_setting('php_version'),
            ($state === 'missing') ? PG_SECURITY_SEVERITY_CRITICAL : PG_SECURITY_SEVERITY_MEDIUM,
            json_encode(array(
                'version' => isset($env['php_version']) ? (string) $env['php_version'] : '',
                'state' => $state,
                'fixed_in' => isset($env['php2shell_fixed_in']) ? (string) $env['php2shell_fixed_in'] : '',
            ))
        );
    }

    return array_values(array_filter($sightings));
}

/**
 * The core folders the folder rule looks at, relative to the software
 * folder: the software folder itself ('') and the code folders below it.
 * The upload folders and data/ must be writable and are not code.
 *
 * @return array
 */
function pg_security_core_folders()
{
    return array('', 'includes', 'includes/fn', 'includes/api', 'includes/migrations', 'install', 'assets');
}

/**
 * The environment the settings rule reads, from this server.
 *
 * @return array
 */
function pg_security_settings_env()
{
    $folders = array();

    foreach (pg_security_core_folders() as $folder) {
        $path = rtrim(PG_FUNCTIONS_DIR . '/' . $folder, '/');
        $mode = is_dir($path) ? @fileperms($path) : false;
        $folders[$folder] = is_int($mode) ? $mode : false;
    }

    $status = pg_php2shell_status(PHP_VERSION, pg_php_build_time());

    return array(
        'windows' => (DIRECTORY_SEPARATOR === '\\'),
        'folders' => $folders,
        'allow_url_include' => ini_get('allow_url_include'),
        'display_errors' => ini_get('display_errors'),
        'php_version' => PHP_VERSION,
        'php2shell' => $status['state'],
        'php2shell_fixed_in' => $status['fixed_in'],
    );
}

/**
 * Every sighting of this module's rules on this site, for a full scan. The
 * accounts and keys are read without any person's data: role and creation
 * time only, and an application's name.
 *
 * @param int $now
 * @return array Sightings
 */
function pg_security_accounts_scan($now)
{
    $users = array();
    $apps = array();

    if (pg_schema_has('user', 'user_created')) {
        $users = db_items("SELECT user_id, user_role, user_created FROM user WHERE (user_role IN ('0', '1')) AND (user_created >= '" . (int) ((int) $now - PG_SECURITY_NEW_WINDOW) . "')");
    }

    if (pg_schema_has('api_apps', 'kind')) {
        $apps = db_items("SELECT id, name, kind, status, created_timestamp FROM api_apps WHERE (kind = 'server') AND (status = 'active') AND (created_timestamp >= '" . (int) ((int) $now - PG_SECURITY_NEW_WINDOW) . "')");
    }

    return array_merge(
        pg_security_accounts_sightings(is_array($users) ? $users : array(), is_array($apps) ? $apps : array(), $now),
        pg_security_settings_sightings(pg_security_settings_env())
    );
}

/**
 * The reason of a finding of this module in the reader's language, as plain
 * text (the screen escapes it). '' for a kind of another module.
 *
 * @param string $kind
 * @param string $detail The finding's JSON detail
 * @return string
 */
function pg_security_accounts_reason($kind, $detail)
{
    $facts = json_decode((string) $detail, true);
    $facts = is_array($facts) ? $facts : array();
    $created = isset($facts['created']) ? date('Y-m-d', (int) $facts['created']) : '';

    switch ($kind) {
        case 'account_new_admin':
            return str_replace('{date}', $created, lang('An Administrator account was created on {date}.'));
        case 'account_new_designer':
            return str_replace('{date}', $created, lang('A Designer account was created on {date}.'));
        case 'api_key_new':
            return str_replace(
                array('{name}', '{date}'),
                array(isset($facts['name']) ? (string) $facts['name'] : '', $created),
                lang('The application key "{name}" was created on {date}.')
            );
        case 'core_folder_writable':
            return str_replace('{mode}', isset($facts['mode']) ? (string) $facts['mode'] : '', lang('Anyone on the server may write to this software folder (permissions {mode}).'));
        case 'php_allow_url_include':
            return lang('PHP\'s allow_url_include is on: an include can run code from another host.');
        case 'php_display_errors':
            return lang('PHP\'s display_errors is on: error messages show paths and queries to visitors.');
        case 'php_security_fixes':
            $text = (isset($facts['state']) && ($facts['state'] === 'missing'))
                ? lang('PHP {version} lacks the PHP2Shell security fixes (in {fixed} and later).')
                : lang('It cannot be told whether PHP {version} has the PHP2Shell security fixes (in {fixed} and later).');

            return str_replace(
                array('{version}', '{fixed}'),
                array(isset($facts['version']) ? (string) $facts['version'] : '', isset($facts['fixed_in']) ? (string) $facts['fixed_in'] : ''),
                $text
            );
    }

    return '';
}

/**
 * What to do about a finding of this module, in the reader's language. ''
 * for a kind of another module.
 *
 * @param string $kind
 * @return string
 */
function pg_security_accounts_advice($kind)
{
    switch ($kind) {
        case 'account_new_admin':
        case 'account_new_designer':
            return lang('If nobody on your team made this account, disable it, change the passwords of the other staff accounts and look at the Site Log.');
        case 'api_key_new':
            return lang('If nobody on your team made this key, revoke it on Application Access.');
        case 'core_folder_writable':
            return lang('Ask your host to remove the write permission for others from this folder (for example 755).');
        case 'php_allow_url_include':
            return lang('Ask your host to turn allow_url_include off.');
        case 'php_display_errors':
            return lang('Ask your host to turn display_errors off; errors still go to the error log.');
        case 'php_security_fixes':
            return lang('Ask your host to update PHP; System Status shows the details under PHP Security Fixes.');
    }

    return '';
}
