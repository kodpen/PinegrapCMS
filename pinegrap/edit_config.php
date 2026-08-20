<?php
/**
 * PineGrap - Enterprise Website Platform
 *
 * Originally developed as LiveSite by Camelback Web Architects.
 * Since 2017, maintained and evolved by Erdal Güral (Kodpen) under the name PineGrap.
 * The final LiveSite update (2019) has been integrated into PineGrap.
 * LiveSite remains available as a separate downloadable legacy version.
 *
 * @author      Camelback Web Architects
 *              Erdal Güral (Kodpen)
 * @link        https://livesite.com
 *              https://kodpen.com
 * @copyright   2001–2019 Camelback Consulting, Inc.
 *              2016–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
$user = validate_user();
validate_area_access($user, 'administrator');
include_once('liveform.class.php');
$liveform = new liveform('edit_config');

// ── Config parser: reads only what is physically in config.php ─────────────
function parse_config_file($file_path) {
    $values  = [];
    $content = @file_get_contents($file_path);
    if ($content === false) { return $values; }

    // String defines: define('KEY', 'value');
    preg_match_all(
        "/define\s*\(\s*'([^']+)'\s*,\s*'((?:[^'\\\\]|\\\\.)*)'\s*\);/",
        $content, $m, PREG_SET_ORDER
    );
    foreach ($m as $row) {
        $values[$row[1]] = stripslashes($row[2]);
    }

    // Boolean defines: define('KEY', true); / define('KEY', false);
    preg_match_all(
        "/define\s*\(\s*'([^']+)'\s*,\s*(true|false)\s*\);/i",
        $content, $m, PREG_SET_ORDER
    );
    foreach ($m as $row) {
        $values[$row[1]] = (strtolower($row[2]) === 'true');
    }

    // String-quoted boolean defines: define('KEY', 'true'); / define('KEY', 'false');
    // These are malformed — normalise them to actual PHP booleans so the UI reflects reality.
    preg_match_all(
        "/define\s*\(\s*'([^']+)'\s*,\s*'(true|false)'\s*\);/i",
        $content, $m, PREG_SET_ORDER
    );
    foreach ($m as $row) {
        $values[$row[1]] = (strtolower($row[2]) === 'true');
    }

    // LOCKED_PAGES const array
    if (preg_match('/const LOCKED_PAGES\s*=\s*array\s*\((.*?)\);/si', $content, $m)) {
        preg_match_all('/"([^"]+)"/', $m[1], $pages);
        $values['LOCKED_PAGES'] = $pages[1];
    }

    return $values;
}

// ── Read config.php once; used by both GET and POST ────────────────────────
$config_raw     = @file_get_contents(CONFIG_FILE_PATH);
$config_parsed  = parse_config_file(CONFIG_FILE_PATH);

// ── Update or insert a define() line ──────────────────────────────────────
function update_config_define($content, $key, $value, $type = 'string') {
    $safe_key = preg_quote($key, '/');

    if ($type === 'boolean') {
        $bool_val = ($value === 'true' || $value === true || $value === '1') ? 'true' : 'false';
        // Always remove any malformed string-quoted boolean defines first (e.g. define('KEY', 'false'))
        $content = preg_replace(
            "/[ \t]*define\s*\(\s*'" . $safe_key . "'\s*,\s*'(?:true|false)'\s*\);\r?\n?/i",
            '',
            $content
        );
        // Now update existing proper boolean define, or append a new one
        if (preg_match("/define\s*\(\s*'" . $safe_key . "'\s*,\s*(?:true|false)\s*\);/i", $content)) {
            return preg_replace(
                "/define\s*\(\s*'" . $safe_key . "'\s*,\s*(?:true|false)\s*\);/i",
                "define('" . $key . "', " . $bool_val . ");",
                $content
            );
        }
        return str_replace('?>', "define('" . $key . "', " . $bool_val . ");\r\n?>", $content);
    }

    $safe_value = str_replace("'", "\\'", $value);
    if (preg_match("/define\s*\(\s*'" . $safe_key . "'\s*,\s*'.*?'\s*\);/si", $content)) {
        return preg_replace(
            "/define\s*\(\s*'" . $safe_key . "'\s*,\s*'.*?'\s*\);/si",
            "define('" . $key . "', '" . $safe_value . "');",
            $content
        );
    }
    return str_replace('?>', "define('" . $key . "', '" . $safe_value . "');\r\n?>", $content);
}

// ── Remove a define() line entirely (called when value is empty) ───────────
function remove_config_define($content, $key, $type = 'string') {
    $safe_key = preg_quote($key, '/');
    if ($type === 'boolean') {
        // Remove proper boolean define
        $content = preg_replace(
            "/[ \t]*define\s*\(\s*'" . $safe_key . "'\s*,\s*(?:true|false)\s*\);\r?\n?/i",
            '',
            $content
        );
        // Also remove malformed string-quoted boolean define
        $content = preg_replace(
            "/[ \t]*define\s*\(\s*'" . $safe_key . "'\s*,\s*'(?:true|false)'\s*\);\r?\n?/i",
            '',
            $content
        );
        return $content;
    }
    return preg_replace(
        "/[ \t]*define\s*\(\s*'" . $safe_key . "'\s*,\s*'.*?'\s*\);\r?\n?/si",
        '',
        $content
    );
}

// ── Field group definitions ────────────────────────────────────────────────
// [KEY, input_type, label, hint, readonly, default]
// input_type : 'text' | 'password' | 'boolean' | 'select:opt1,opt2' | 'locked_pages'
// default    : null = bilgi gösterme | '' = Boş | true/false = bool | 'string' = metin
$config_groups = [

    lang('Database Settings') => [
        ['DB_HOST',     'text',     lang('Hostname'),      '', false, 'localhost'],
        ['DB_USERNAME', 'text',     lang('Username'),      '', false, null],
        ['DB_PASSWORD', 'password', lang('Password'),      '', false, null],
        ['DB_DATABASE', 'text',     lang('Database Name'), '', false, null],
        ['DB_LEGACY',   'boolean',  lang('Legacy MySQL (PHP < 7)'),
            lang('Enable only for PHP < 7 compatibility.'), false, false],
    ],

    lang('General') => [
        ['ENCRYPTION_KEY',  'password', lang('Encryption Key'),
            lang('Do not modify. Auto-generated during install.'), true, null],
        ['DYNAMIC_REGIONS', 'boolean', lang('Dynamic Regions'),
            lang('Allow dynamic PHP regions in pages.'), false, true],
        ['PHP_REGIONS',     'boolean', lang('PHP Regions'),
            lang('Allow PHP code in Page Designer.'), false, true],
    ],

    lang('Environment') => [
        ['ENVIRONMENT', 'select:development,production', lang('Environment'), '', false, 'production'],
        ['EDITION',     'text', lang('Edition'),
            lang('Edition name shown in backend footer.'), false, ''],
    ],

    lang('Developer Lock') => [
        ['DEVELOPER_PIN', 'password', lang('Developer PIN'),
            lang('Numeric PIN to lock backend pages.'), false, ''],
        ['LOCKED_PAGES',  'locked_pages', lang('Locked Pages'),
            lang('One filename per line. (e.g. welcome.php)'), false, ''],
    ],

    lang('Language Settings') => [
        ['DEFAULT_SOFTWARE_LANGUAGE',     'text', lang('Default Language'),
            lang("Language code. E.g. 'en' or 'tr'"), false, 'en'],
        ['ENFORCEMENT_SOFTWARE_LANGUAGE', 'text', lang('Enforce Language'),
            lang('Overrides all user settings. Leave empty to disable.'), false, ''],
    ],

    lang('Branding') => [
        ['LOGO_URL',                     'text', lang('Logo URL'),        '', false, ''],
        ['CONTROL_PANEL_STYLESHEET_URL', 'text', lang('Backend CSS URL'), '', false, ''],
    ],

    lang('Maintenance') => [
        ['SOFTWARE_UPDATE_CHECK', 'boolean', lang('Software Update Check'), '', false, true],
        ['SOFTWARE_AUTO_BACKUP',  'boolean', lang('Auto Backup'),           '', false, true],
        ['REQUIRE_SECURE_MODE',   'boolean', lang('Require Secure Mode'),
            lang('Force HTTPS for all requests.'), false, false],
        ['TRUST_PROXY_SSL_HEADERS', 'boolean', lang('Trust Proxy SSL Headers'),
            lang('Required when SSL is terminated by a proxy or CDN before reaching this server (for example Cloudflare Flexible SSL). Only enable it if this server is not reachable directly, because these headers can be forged.'), false, false],
        ['MIG', 'boolean', lang('Migration'),
            lang('Enable migration feature.'), false, true],
    ],

    lang('Cloudflare') => [
        ['CLOUDFLARE_API_TOKEN', 'password', lang('API Token'), '', false, ''],
        ['CLOUDFLARE_ZONE_ID',   'text',     lang('Zone ID'),   '', false, ''],
    ],

    lang('Integrations') => [
        ['UNSPLASH_ACCESS_KEY', 'password', lang('Unsplash Access Key'),
            lang('API access key for Unsplash image search. Leave empty to hide the Unsplash tab in the image picker.'), false, ''],
    ],

    lang('Error Reporting') => [
        ['SET_ERROR_REPORTING', 'boolean', lang('Set Error Reporting'),
            lang('If not selected or true, Pinegrap controls error reporting. Set false to let php.ini take over.'), false, true],
    ],
];


// ── Helper: render a single field ──────────────────────────────────────────
function render_config_field($liveform, $key, $type, $label, $hint, $readonly, $parsed, $default = null) {
    $field_name = strtolower($key);

    // Value only from config.php — null means key doesn't exist in file
    $current    = array_key_exists($key, $parsed) ? $parsed[$key] : null;

    $hint_html  = $hint
        ? '<div class="form-text text-muted small">' . h($hint) . '</div>'
        : '';

    // Varsayılan değer bilgisi (null ise gösterilmez)
    if ($default !== null) {
        if ($default === true)        $dval = 'true';
        elseif ($default === false)   $dval = 'false';
        elseif ($default === '')      $dval = lang('Empty');
        else                          $dval = '"' . h($default) . '"';
        $hint_html .= '<div class="form-text text-secondary small fst-italic">'
            . lang('Default') . ': ' . $dval . '</div>';
    }

    $ro_attr    = $readonly ? ' readonly' : '';
    $ro_class   = $readonly ? ' bg-secondary bg-opacity-10' : '';

    // ── boolean: 3 durumlu select (Not Selected / true / false) ────────
    if ($type === 'boolean') {
        if ($current === true)       { $sel_true = ' selected'; $sel_false = ''; $sel_none = ''; }
        elseif ($current === false)  { $sel_true = ''; $sel_false = ' selected'; $sel_none = ''; }
        else                         { $sel_true = ''; $sel_false = ''; $sel_none = ' selected'; }

        return '
        <div class="col-12 my-2">
            <label for="' . h($field_name) . '" class="form-label">' . h($label) . '</label>
            <select id="' . h($field_name) . '" name="' . h($field_name) . '"
                class="form-select">
                <option value=""'   . $sel_none  . '>' . lang('Not Selected') . '</option>
                <option value="true"'  . $sel_true  . '>true</option>
                <option value="false"' . $sel_false . '>false</option>
            </select>
            ' . $hint_html . '
        </div>';
    }

    // ── locked_pages textarea ─────────────────────────────────────────────
    if ($type === 'locked_pages') {
        $val = (is_array($current)) ? implode("\n", $current) : '';
        return '
        <div class="col-12 my-2">
            <label for="' . h($field_name) . '" class="form-label">' . h($label) . '</label>
            <textarea id="' . h($field_name) . '" name="' . h($field_name) . '"
                class="form-control font-monospace" rows="4"
                placeholder="welcome.php">' . h($val) . '</textarea>
            ' . $hint_html . '
        </div>';
    }

    // ── select ────────────────────────────────────────────────────────────
    if (strpos($type, 'select:') === 0) {
        $options      = explode(',', substr($type, 7));
        $options_html = '<option value="">' . lang('Not Selected') . '</option>';
        foreach ($options as $opt) {
            $sel = ((string)$current === $opt) ? ' selected' : '';
            $options_html .= '<option value="' . h($opt) . '"' . $sel . '>' . h($opt) . '</option>';
        }
        return '
        <div class="col-12 my-2">
            <label for="' . h($field_name) . '" class="form-label">' . h($label) . '</label>
            <select id="' . h($field_name) . '" name="' . h($field_name) . '"
                class="form-select' . $ro_class . '"' . $ro_attr . '>
                ' . $options_html . '
            </select>
            ' . $hint_html . '
        </div>';
    }

    // ── text / password ───────────────────────────────────────────────────
    $input_type  = ($type === 'password') ? 'password' : 'text';
    $display_val = ($current !== null) ? (string)$current : '';
    $eye_btn     = '';
    if ($type === 'password' && !$readonly) {
        $eye_btn = '
            <button class="btn btn-outline-secondary" type="button"
                onclick="var f=this.previousElementSibling;
                         f.type=f.type===\'password\'?\'text\':\'password\'">
                <span class="material-icons" style="font-size:1.1rem">visibility</span>
            </button>';
    }
    $input_html = '<input type="' . $input_type . '"
        id="' . h($field_name) . '"
        name="' . h($field_name) . '"
        class="form-control' . $ro_class . '"
        autocomplete="new-password"
        value="' . h($display_val) . '"' . $ro_attr . '>';

    return '
    <div class="col-12 my-2">
        <label for="' . h($field_name) . '" class="form-label">' . h($label) . '</label>
        ' . ($eye_btn
            ? '<div class="input-group">' . $input_html . $eye_btn . '</div>'
            : $input_html) . '
        ' . $hint_html . '
    </div>';
}

// ── GET: display ───────────────────────────────────────────────────────────
if (!$_POST) {

    $groups_html = '';
    foreach ($config_groups as $group_title => $fields) {
        $fields_html = '';
        foreach ($fields as $field) {
            list($key, $type, $label, $hint, $readonly) = $field;
            $default     = isset($field[5]) ? $field[5] : null;
            $fields_html .= render_config_field(
                $liveform, $key, $type, $label, $hint, $readonly, $config_parsed, $default
            );
        }
        $groups_html .= '
        <div class="col-12 col-lg-6 mb-5">
            <div class="card h-100">
                <div class="card-header bg-reset border-0 text-uppercase h6 text-primary fw-bold">
                    ' . h($group_title) . '
                </div>
                <div class="card-body">
                    <div class="row">' . $fields_html . '</div>
                </div>
            </div>
        </div>';
    }

    $smtp_notice = '
    <div class="col-12 col-lg-6 mb-5">
        <div class="card h-100 border-info">
            <div class="card-header bg-reset border-0 text-uppercase h6 text-info fw-bold">
                ' . lang('SMTP Settings') . '
            </div>
            <div class="card-body d-flex align-items-center">
                <div>
                    <span class="material-icons text-info me-2 align-middle">info</span>
                    ' . lang('SMTP settings are managed on a separate page.') . '
                    <a href="smtp_settings.php" class="ms-2 btn btn-sm btn-outline-info">
                        <span class="material-icons me-1" style="font-size:1rem">open_in_new</span>
                        ' . lang('SMTP Settings') . '
                    </a>
                </div>
            </div>
        </div>
    </div>';

    print pg_page_shell([
        'title'         => lang('Edit Config'),
        'extra classes' => 'setting',
        'icon'          => 'setting',
        'heading'       => lang('Edit Config'),
        'cancel'        => [
            'enable'  => 'true',
            'title'   => lang('Return to Settings'),
            'url'     => 'settings.php'
        ],
        'breadcrumb'    => [
            ['label' => lang('Settings'), 'url' => 'settings.php'],
            ['label' => lang('Edit Config')],
        ],
    ]) . '
        <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors()
                . $liveform->get_warnings()
                . $liveform->output_notices() . '
                <div class="row mb-2 flex-wrap">
                    <div class="col-12 text-center text-md-start">
                        <h2 class="d-inline-block"
                            data-bs-content="' . lang('Update the configuration file settings.') . '"
                            title="' . lang('Edit Config') . '">'
                            . lang('Edit Config') . '</h2>
                    </div>
                </div>
                <form name="form" action="edit_config.php" method="post"
                    autocomplete="off" submitshortcut="submit_save">
                    ' . get_token_field() . '
                    <div class="row">
                        ' . $groups_html . $smtp_notice . '
                    </div>
                    <nav class="buttons navigation text-center position-sticky mb-4"
                        style="bottom:.5rem;">
                        <div class="container">
                            <div class="btn-group flex-wrap justify-content-center">
                                <button type="submit" id="submit_save"
                                    name="submit_save" value="Save"
                                    class="btn my-1 btn-success">
                                    <span class="material-icons me-2">save</span>
                                    <span class="btn-text">' . lang('Save') . '</span>
                                </button>
                            </div>
                        </div>
                    </nav>
                </form>
            </div>
        </div>
    </main>' . output_footer();

    $liveform->remove_form();
    exit;
}

// ── POST: save ─────────────────────────────────────────────────────────────
validate_token_field();

if (!isset($_POST['submit_save']) || ($_POST['submit_save'] ?? '') !== 'Save') {
    header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/edit_config.php');
    exit;
}

if ($config_raw === false) {
    $liveform->add_error(lang('Config file could not be opened.'));
    header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/edit_config.php');
    exit;
}

$config_content = $config_raw;

foreach ($config_groups as $fields) {
    foreach ($fields as $field) {
        list($key, $type, , , $readonly) = $field;
        if ($readonly) { continue; }

        $field_name = strtolower($key);

        // ── LOCKED_PAGES ─────────────────────────────────────────────────
        if ($type === 'locked_pages') {
            $raw   = isset($_POST[$field_name]) ? trim($_POST[$field_name]) : '';
            $pages = array_values(array_filter(array_map('trim', explode("\n", $raw))));
            if (!empty($pages)) {
                $arr_items = '';
                foreach ($pages as $p) {
                    $arr_items .= '  "' . addslashes(basename($p)) . '",' . "\n";
                }
                $new_block = "const LOCKED_PAGES = array(\n" . $arr_items . ");";
                if (preg_match('/const LOCKED_PAGES\s*=\s*array\s*\(.*?\);/si', $config_content)) {
                    $config_content = preg_replace(
                        '/const LOCKED_PAGES\s*=\s*array\s*\(.*?\);/si',
                        $new_block,
                        $config_content
                    );
                } else {
                    $config_content = str_replace('?>', $new_block . "\r\n?>", $config_content);
                }
            } else {
                // Empty textarea → remove the const block
                $config_content = preg_replace(
                    '/[ \t]*const LOCKED_PAGES\s*=\s*array\s*\(.*?\);\r?\n?/si',
                    '',
                    $config_content
                );
            }
            continue;
        }

        // ── Boolean: artık 3 seçenekli select — '' / 'true' / 'false' ──────
        if ($type === 'boolean') {
            $val = isset($_POST[$field_name]) ? trim($_POST[$field_name]) : '';
            if ($val === '') {
                $config_content = remove_config_define($config_content, $key, 'boolean');
            } elseif ($val === 'true' || $val === 'false') {
                $config_content = update_config_define($config_content, $key, $val, 'boolean');
            }
            continue;
        }

        // ── Select / text / password ──────────────────────────────────────
        $val = isset($_POST[$field_name]) ? trim($_POST[$field_name]) : '';

        // Validate select options
        if (strpos($type, 'select:') === 0) {
            $options = explode(',', substr($type, 7));
            if ($val !== '' && !in_array($val, $options)) { continue; }
        }

        if ($val === '') {
            // Empty value → remove define entirely
            $config_content = remove_config_define($config_content, $key, 'string');
        } else {
            $config_content = update_config_define($config_content, $key, $val, 'string');
        }
    }
}

if ($config_content === $config_raw) {
    $liveform->add_notice(lang('No changes detected. Config file was not modified.'));
} else {
    $write_handle = fopen(CONFIG_FILE_PATH, 'w');
    if ($write_handle) {
        fwrite($write_handle, $config_content);
        fclose($write_handle);
        log_activity(lang('Config was modified'), $_SESSION['sessionusername']);
        $liveform->add_notice(lang('The config settings have been saved.'));
    } else {
        $liveform->add_error(lang('Config file could not be opened.'));
    }
}

header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/edit_config.php');
exit;
