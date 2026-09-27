<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The second half of an installation made with a design template.
 *
 * The installer (install/index.php) builds the site from a starter site the
 * usual way and leaves a note in data/temp/: the template, the language of
 * the starter site, the layouts of the widgets only the editor can build
 * (made in the installer's browser by StyleDesigner.starterTree()), the
 * administrator the design is written as, and the hash of a key it hands
 * the installer's screen. The screen then sends the key here, once the
 * installation has finished.
 *
 * This request runs as the site does (init.php): writing a design needs the
 * settings the site defines from its config row, which the installer never
 * defines. It publishes the template with its home page as the site's home
 * page, then takes away the starter site's pages and page styles and what
 * belonged only to them. The template is written first, with the old page
 * names moved aside so the template's pages get their own names: when it
 * cannot be written, the names go back and the starter site stays exactly as
 * it was installed.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_INSTALL_DESIGN_TEMPLATE')) {
    exit;
}

// Where the installer leaves the note. data/temp/ is not configurable, and
// the installer writes its progress files there too.
function pg_idt_marker_path()
{
    return dirname(__FILE__) . '/../data/temp/install_design_template.json';
}

// The answer, as JSON, and the end of the request. Anything a function
// printed along the way (a PHP notice) is thrown away so the screen can read
// the answer.
function pg_idt_answer($answer)
{
    pg_idt_session_name(false);
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
    }
    echo json_encode($answer, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

// The page and design writers log under the signed-in user's name, the way
// the editor's save runs; this request has no sign-in, so the administrator's
// name stands in while it runs ($on) and is taken out of the session again
// before the answer ($on = false). A name that was already there is left
// alone.
function pg_idt_session_name($on, $name = '')
{
    static $set = false;
    if ($on) {
        if (!isset($_SESSION['sessionusername']) || $_SESSION['sessionusername'] === '') {
            $_SESSION['sessionusername'] = (string)$name;
            $set = true;
        }
    } elseif ($set) {
        unset($_SESSION['sessionusername']);
        $set = false;
    }
}

// The tables and columns this database has, for the clean-up below: a
// starter site from an older package may lack a table a newer one has, and
// a failed query ends the request (db() -> output_error()).
function pg_idt_columns()
{
    static $columns = null;
    if ($columns !== null) return $columns;
    $columns = array();
    foreach ((array)db_items(
        "SELECT TABLE_NAME AS t, COLUMN_NAME AS c FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()") as $row) {
        $columns[strtolower($row['t'])][strtolower($row['c'])] = true;
    }
    return $columns;
}

function pg_idt_has($table, $column)
{
    $columns = pg_idt_columns();
    return isset($columns[strtolower($table)][strtolower($column)]);
}

// What belonged only to the starter site's pages and page styles, and the
// pages and styles themselves. Settings elsewhere that named one of those
// pages are cleared (0 is "none chosen" for every one of them); the folders
// and the administrator's start page are pointed at the new design. Menus,
// common regions, products, contacts, calendars, folders and files stay.
function pg_idt_remove_starter($page_ids, $style_ids, $style_id, $start_page_id)
{
    $removed = array('pages' => 0, 'styles' => 0);
    $pages  = implode(',', array_map('intval', $page_ids));
    $styles = implode(',', array_map('intval', $style_ids));

    if ($pages !== '') {
        // The layouts the starter site's pages had of their own (one file
        // per page, named by its id).
        if (defined('LAYOUT_DIRECTORY_PATH')) {
            foreach ($page_ids as $pid) {
                $file = LAYOUT_DIRECTORY_PATH . '/' . (int)$pid . '.php';
                if (is_file($file)) @unlink($file);
            }
        }

        db("DELETE FROM page WHERE page_id IN ($pages)");
        $removed['pages'] = (int)mysqli_affected_rows(db::$con);

        // What hangs on a page goes with it: every row below whose page is
        // not there any more, which also takes the rows the starter data
        // carries for pages it never had. 0 is no page (a product form's
        // fields), so those stay.
        $gone = "> 0 AND %s NOT IN (SELECT page_id FROM page)";

        // The submissions of the old forms, before the rows they hang on.
        if (pg_idt_has('form_data', 'form_id') && pg_idt_has('forms', 'page_id')) {
            db("DELETE form_data FROM form_data INNER JOIN forms ON form_data.form_id = forms.id WHERE forms.page_id " . sprintf($gone, 'forms.page_id'));
        }
        $by_page = array(
            'forms'                                      => 'page_id',
            'pregion'                                    => 'pregion_page',
            'affiliate_sign_up_form_pages'               => 'page_id',
            'billing_information_pages'                  => 'page_id',
            'calendar_event_view_pages'                  => 'page_id',
            'calendar_view_pages'                        => 'page_id',
            'catalog_detail_pages'                       => 'page_id',
            'catalog_pages'                              => 'page_id',
            'custom_form_confirmation_pages'             => 'page_id',
            'custom_form_pages'                          => 'page_id',
            'email_a_friend_pages'                       => 'page_id',
            'express_order_pages'                        => 'page_id',
            'folder_view_pages'                          => 'page_id',
            'form_item_view_pages'                       => 'page_id',
            'form_list_view_pages'                       => 'page_id',
            'form_view_directory_pages'                  => 'page_id',
            'order_form_pages'                           => 'page_id',
            'order_preview_pages'                        => 'page_id',
            'order_receipt_pages'                        => 'page_id',
            'photo_gallery_pages'                        => 'page_id',
            'search_results_pages'                       => 'page_id',
            'shipping_address_and_arrival_pages'         => 'page_id',
            'shipping_method_pages'                      => 'page_id',
            'shopping_cart_pages'                        => 'page_id',
            'update_address_book_pages'                  => 'page_id',
            'calendar_event_views_calendars_xref'        => 'page_id',
            'calendar_views_calendars_xref'              => 'page_id',
            'form_list_view_browse_fields'               => 'page_id',
            'form_list_view_filters'                     => 'page_id',
            'form_field_options'                         => 'page_id',
            'form_fields'                                => 'page_id',
            'form_signatures'                            => 'page_id',
            'submitted_form_info'                        => 'page_id',
            'submitted_form_views'                       => 'page_id',
            'submitted_form_view_stats'                  => 'page_id',
            'comments'                                   => 'page_id',
            'allow_new_comments_for_items'               => 'page_id',
            'watchers'                                   => 'page_id',
            'tag_cloud_keywords_xref'                    => 'search_results_page_id',
            'search_items'                               => 'page_id',
            'target_options'                             => 'page_id',
            'short_links'                                => 'page_id',
            'preview_styles'                             => 'page_id',
        );
        foreach ($by_page as $table => $column) {
            if (pg_idt_has($table, $column)) db("DELETE FROM `$table` WHERE `$column` " . sprintf($gone, "`$column`"));
        }
        foreach (array('form_view_directory_page_id', 'form_list_view_page_id') as $column) {
            if (pg_idt_has('form_view_directories_form_list_views_xref', $column)) {
                db("DELETE FROM form_view_directories_form_list_views_xref WHERE `$column` " . sprintf($gone, "`$column`"));
            }
        }
        if (pg_idt_has('seo_issue', 'entity_id')) db("DELETE FROM seo_issue WHERE entity_type = 'page' AND entity_id " . sprintf($gone, 'entity_id'));
        if (pg_idt_has('seo_link', 'from_id')) db("DELETE FROM seo_link WHERE from_type = 'page' AND from_id " . sprintf($gone, 'from_id'));
        if (pg_idt_has('recycle_bin', 'item_id')) db("DELETE FROM recycle_bin WHERE item_type = 'page' AND item_id " . sprintf($gone, 'item_id'));

        $page_settings = array(
            'config'                  => array('ecommerce_retrieve_order_next_page_id', 'membership_expiration_warning_email_page_id', 'ecommerce_reward_program_email_page_id'),
            'products'                => array('send_to_page', 'email_page', 'recurring_profile_disabled_email_page_id', 'submit_form_custom_form_page_id', 'add_comment_page_id', 'gift_card_email_page_id'),
            'offers'                  => array('upsell_action_page_id'),
            'email_campaign_profiles' => array('page_id'),
            'calendar_events'         => array('next_page_id'),
        );
        foreach ($page_settings as $table => $list) {
            foreach ($list as $column) {
                if (pg_idt_has($table, $column)) db("UPDATE `$table` SET `$column` = 0 WHERE `$column` IN ($pages)");
            }
        }
        if (pg_idt_has('user', 'user_home')) {
            db("UPDATE user SET user_home = '" . (int)$start_page_id . "' WHERE user_home IN ($pages)");
        }
    }

    if ($styles !== '') {
        foreach (array('system_style_cells', 'preview_styles') as $table) {
            if (pg_idt_has($table, 'style_id')) db("DELETE FROM `$table` WHERE style_id IN ($styles)");
        }
        // The root folder is what the site's pages fall back to, so it takes
        // the new design; any other folder falls back to its parent again.
        db("UPDATE folder SET folder_style = '" . (int)$style_id . "' WHERE folder_parent = '0' AND folder_style IN ($styles)");
        db("UPDATE folder SET folder_style = 0 WHERE folder_style IN ($styles)");
        if (pg_idt_has('folder', 'mobile_style_id')) db("UPDATE folder SET mobile_style_id = 0 WHERE mobile_style_id IN ($styles)");
        db("DELETE FROM style WHERE style_id IN ($styles)");
        $removed['styles'] = (int)mysqli_affected_rows(db::$con);
    }

    return $removed;
}

// ── The request ─────────────────────────────────────────────────────────────

ob_start();

// The note and the key, before anything else runs. Until the key matches,
// nobody learns anything but that there is nothing to do here.
$idt_key = isset($_POST['key']) ? (string)$_POST['key'] : '';
if (preg_match('/^[a-f0-9]{32}$/', $idt_key) !== 1) {
    pg_idt_answer(array('ok' => false, 'code' => 'key'));
}
$idt_path = pg_idt_marker_path();
$idt_note = is_file($idt_path) ? json_decode((string)@file_get_contents($idt_path), true) : null;
if (!is_array($idt_note)) {
    pg_idt_answer(array('ok' => false, 'code' => (is_file($idt_path . '.running') ? 'busy' : 'missing')));
}
if (!isset($idt_note['key']) || !hash_equals((string)$idt_note['key'], hash('sha256', $idt_key))) {
    pg_idt_answer(array('ok' => false, 'code' => 'key'));
}
// A day is plenty for an installation screen to send its second request.
if ((int)(isset($idt_note['created']) ? $idt_note['created'] : 0) < time() - 86400) {
    @unlink($idt_path);
    pg_idt_answer(array('ok' => false, 'code' => 'expired'));
}
$idt_language = (isset($idt_note['language']) && in_array($idt_note['language'], array('en', 'tr'), true)) ? $idt_note['language'] : 'en';

// Taken by this request. A second one (a double click, a retry while this
// runs) finds no note; a failure below puts it back so the screen can try
// again.
if (!@rename($idt_path, $idt_path . '.running')) {
    pg_idt_answer(array('ok' => false, 'code' => 'busy'));
}
$idt_state = array('done' => false, 'parked' => array());
register_shutdown_function(function () use (&$idt_state, $idt_path) {
    pg_idt_session_name(false);
    if ($idt_state['done']) return;
    // Ended before the template was written (a failed query ends the request
    // on its own): the old names go back and the note too.
    if ($idt_state['parked'] && isset(db::$con) && db::$con) {
        foreach ($idt_state['parked'] as $pid => $name) {
            @mysqli_query(db::$con, "UPDATE page SET page_name = '" . mysqli_real_escape_string(db::$con, $name) . "' WHERE page_id = '" . (int)$pid . "'");
        }
    }
    if (is_file($idt_path . '.running')) @rename($idt_path . '.running', $idt_path);
});

// The site, in the language of the starter site: the template's pages are
// written in the language the rest of its content is in. The software's own
// index.php is not the site's, so init.php works the path out as for any
// other script under the software directory.
define('SOFTWARE_LANGUAGE', $idt_language);
define('NON_ROOT_INDEX', true);
require(dirname(__FILE__) . '/../init.php');

$idt_user = pg_load_user_row(isset($idt_note['user_id']) ? (int)$idt_note['user_id'] : 0);
if (!is_array($idt_user) || (int)$idt_user['role'] !== 0) {
    pg_idt_answer(array('ok' => false, 'error' => lang('The administrator of the installation could not be found.')));
}
pg_idt_session_name(true, $idt_user['username']);
$idt_template = pg_design_template(isset($idt_note['template']) ? (string)$idt_note['template'] : '');
if (!$idt_template) {
    pg_idt_answer(array('ok' => false, 'error' => lang('The template could not be found.')));
}

// Everything the starter site brought, and its page names out of the way.
$idt_page_ids  = array_map('intval', (array)db_values("SELECT page_id FROM page"));
$idt_style_ids = array_map('intval', (array)db_values("SELECT style_id FROM style"));
foreach ((array)db_items("SELECT page_id, page_name FROM page") as $idt_row) {
    $idt_state['parked'][(int)$idt_row['page_id']] = (string)$idt_row['page_name'];
}
if ($idt_page_ids) {
    db("UPDATE page SET page_name = CONCAT('pg-starter-', page_id) WHERE page_id IN (" . implode(',', $idt_page_ids) . ")");
}

$idt_result = pg_design_template_install($idt_template['id'], $idt_user, array(
    'starters' => (isset($idt_note['starters']) && is_array($idt_note['starters'])) ? $idt_note['starters'] : array(),
    'home'     => true,
    'look'     => isset($idt_note['look']) ? (string)$idt_note['look'] : '',
    'palette'  => isset($idt_note['palette']) ? (string)$idt_note['palette'] : '',
    // Nobody is visiting: the SEO job scores the pages (see the function).
    'seo'      => false,
));

if (empty($idt_result['ok'])) {
    // The shutdown function puts the names and the note back.
    pg_idt_answer(array('ok' => false, 'error' => isset($idt_result['error']) ? $idt_result['error'] : lang('An error occurred')));
}
$idt_state['done'] = true;
$idt_state['parked'] = array();

$idt_start_page = isset($idt_result['pages']['staff']) ? (int)$idt_result['pages']['staff'] : 0;
$idt_removed = pg_idt_remove_starter($idt_page_ids, $idt_style_ids, (int)$idt_result['style_id'], $idt_start_page);

@unlink($idt_path . '.running');

$idt_home_name = ($idt_result['home_page_id'] > 0)
    ? (string)db_value("SELECT page_name FROM page WHERE page_id = '" . (int)$idt_result['home_page_id'] . "' LIMIT 1")
    : '';

log_activity(lang(array('string' => 'The design template "{var:1}" was installed', 'vars' => array($idt_result['style_name']))), (string)$idt_user['username']);

$idt_palettes = pg_design_palettes();
$idt_palette = isset($idt_note['palette']) ? (string)$idt_note['palette'] : '';

pg_idt_answer(array(
    'ok'       => true,
    'design'   => $idt_result['style_name'],
    'look'     => pg_design_look_label(isset($idt_note['look']) ? (string)$idt_note['look'] : ''),
    'palette'  => isset($idt_palettes[$idt_palette]) ? $idt_palettes[$idt_palette]['name'] : '',
    'pages'    => count($idt_result['pages']),
    'home'     => $idt_home_name,
    'removed'  => $idt_removed,
    'warnings' => array_values(array_unique($idt_result['warnings'])),
));
