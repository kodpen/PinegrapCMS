<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A module of functions.php: Custom forms on pages (pg_cf_*): fields, settings, bindings, submission.
//
// Loaded by functions.php through require_once, never on its own. The
// functions were moved here from functions.php as they were; PG_FUNCTIONS_DIR
// is the software directory (what dirname(__FILE__) was in functions.php),
// see the note at the top of functions.php.
if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * A designed form's fields ARE its controls.
 *
 * A custom_form widget drawn in the visual editor keeps no field list of its
 * own. Every form control inside the widget tree - <input>, <select>,
 * <textarea>, whatever put it there - is one field of the page's form, and the
 * control carries the whole definition:
 *
 *   name          the `name` attribute, which is also the field's name
 *   type          the tag, and for <input> its `type` attribute
 *   required      the `required` attribute
 *   label         the <label for> that names the control (a radio / checkbox
 *                 group: the <legend> of the enclosing <fieldset>)
 *   options       the <option> children, or the group's controls
 *   default       the `value` attribute / the textarea text
 *   regex         the `pattern` attribute
 *
 * What markup cannot say sits on the node as `props._cf`:
 *
 *   ids             field row id per page ({page_id: form_fields.id}), so a
 *                   renamed control keeps its row and its submissions
 *   label           the label when the designer removed the <label>
 *   rss_field       title / description / media / category role
 *   contact_field   the contact column the field fills and updates
 *   office_use_only staff see and fill it; the visitor never does
 *   validation_message, upload_folder_id, quiz_question, quiz_answer
 *
 * On every page save the server reads the widget trees and writes the
 * `form_fields` rows to match (pg_cf_reconcile_page_form). A control with a
 * row id keeps its row whatever its name is now; a control without one is
 * matched by name, then created; a row with no control left is deleted and
 * the caller is told how many submitted values went with it. The editor has
 * nothing to send but the form-level settings.
 */

// The `form_fields`.`type` enum, in the order the field editor offers them.
function pg_cf_field_types()
{
    return array(
        'text box', 'text area', 'pick list', 'radio button', 'check box',
        'file upload', 'date', 'date and time', 'time', 'email address',
        'information',
    );
}

// Types whose choices live in `form_field_options`.
function pg_cf_type_has_options($type)
{
    return in_array($type, array('pick list', 'radio button', 'check box'), true);
}

// Values `rss_field` accepts. Mirrors select_rss_field(); kept as data here
// because the editor needs the list as JSON, not as <option> HTML.
function pg_cf_rss_fields()
{
    return array(
        ''            => lang('None'),
        'category'    => lang('Category'),
        'title'       => lang('Title'),
        'description' => lang('Description'),
        'media'       => lang('Media'),
    );
}

// Values `contact_field` accepts, read out of the same list the legacy field
// screen uses so the two screens can never offer different sets.
function pg_cf_contact_fields()
{
    $out = array('' => lang('None'));
    if (!function_exists('select_contact_field')) return $out;
    // select_contact_field() returns <option> HTML; parsing it is ugly but it
    // is one list in one place, and a second hand-written copy here would be
    // the thing that drifts.
    if (preg_match_all('/<option value="([^"]*)"[^>]*>(.*?)<\/option>/i',
                       select_contact_field(), $m, PREG_SET_ORDER)) {
        foreach ($m as $hit) {
            $value = html_entity_decode($hit[1], ENT_QUOTES, 'UTF-8');
            if ($value === '') continue;
            $out[$value] = html_entity_decode(strip_tags($hit[2]), ENT_QUOTES, 'UTF-8');
        }
    }
    return $out;
}

// A field name is written into an HTML attribute and, on form list views, into
// a ^^name^^ token. add_field.php refuses the characters that break either;
// the same set is refused here so a form drawn in the editor and one drawn on
// the legacy screen cannot disagree about what a legal name is.
function pg_cf_clean_field_name($name)
{
    $name = str_replace(array('^', '&', '[', ']', '<', '>', '/', '"', "'", "\\"), '', (string)$name);
    $name = preg_replace('/\s+/u', ' ', $name);
    return mb_substr(trim($name), 0, 100);
}

// True when this installation can store a designed form. Same defensive shape
// as pg_page_noindex_ready(): take the code without the database and the
// feature disappears quietly instead of erroring.
function pg_cf_page_form_ready()
{
    static $ready = null;
    if ($ready !== null) return $ready;
    $ready = false;
    if (!db::$con) return $ready;
    $need = array('custom_form_pages', 'form_fields', 'form_field_options');
    foreach ($need as $t) {
        $r = @mysqli_query(db::$con, "SHOW TABLES LIKE '" . e($t) . "'");
        if (!$r || mysqli_num_rows($r) === 0) return $ready;
    }
    $ready = true;
    return $ready;
}

/**
 * A page's whole form, shaped for the editor.
 *
 * Every column the field screen can set comes back, plus the options. The
 * save reply carries it so the editor learns the row ids; the API answers
 * with it when a widget is set to show an existing form and the editor
 * rebuilds its controls from that form's fields.
 */
function pg_cf_load_page_fields($page_id)
{
    $page_id = (int)$page_id;
    if ($page_id <= 0 || !pg_cf_page_form_ready()) return array();

    $rows = db_items(
        "SELECT id, name, label, type, required, information, default_value,
                use_folder_name_for_default_value, size, maxlength, wysiwyg,
                `rows`, cols, multiple, spacing_above, spacing_below,
                contact_field, office_use_only, rss_field, quiz_question,
                quiz_answer, sort_order
         FROM form_fields
         WHERE page_id = '$page_id' AND form_type = 'custom'
         ORDER BY sort_order ASC, id ASC");
    if (!is_array($rows) || empty($rows)) return array();

    // Optional in older schemas — probed, never assumed.
    $has_upload_folder = pg_cf_form_fields_has_column('upload_folder_id');
    $has_validation    = pg_cf_form_fields_has_column('validation_regex');
    $validation_by_field = array();
    if ($has_validation) {
        foreach ((array)db_items(
            "SELECT id, validation_regex, validation_message FROM form_fields
             WHERE page_id = '$page_id' AND form_type = 'custom'") as $v) {
            $validation_by_field[(int)$v['id']] = array(
                'regex'   => (string)$v['validation_regex'],
                'message' => (string)$v['validation_message'],
            );
        }
    }
    $upload_by_field   = array();
    if ($has_upload_folder) {
        foreach ((array)db_items(
            "SELECT id, upload_folder_id FROM form_fields
             WHERE page_id = '$page_id' AND form_type = 'custom'") as $u) {
            $upload_by_field[(int)$u['id']] = (int)$u['upload_folder_id'];
        }
    }

    // Options in one query rather than one per field.
    $ids = array();
    foreach ($rows as $r) $ids[] = (int)$r['id'];
    $options_by_field = array();
    if (!empty($ids)) {
        foreach ((array)db_items(
            "SELECT id, form_field_id, label, value, email_address, default_selected,
                    upload_folder_id, target_form_field_id, sort_order
             FROM form_field_options
             WHERE form_field_id IN (" . implode(',', $ids) . ")
             ORDER BY sort_order ASC, id ASC") as $o) {
            $options_by_field[(int)$o['form_field_id']][] = array(
                'id'            => (int)$o['id'],
                'label'         => (string)$o['label'],
                'value'         => (string)$o['value'],
                'email_address' => (string)$o['email_address'],
                'default'       => ((int)$o['default_selected'] === 1),
                'upload_folder' => (int)$o['upload_folder_id'],
                'trigger'       => pg_cf_option_trigger_text($o),
            );
        }
    }

    $out = array();
    foreach ($rows as $r) {
        $id = (int)$r['id'];
        $out[] = array(
            'id'              => $id,
            'name'            => (string)$r['name'],
            'label'           => (string)$r['label'],
            'type'            => (string)$r['type'],
            'required'        => ((int)$r['required'] === 1),
            'multiple'        => ((int)$r['multiple'] === 1),
            'office_use_only' => ((int)$r['office_use_only'] === 1),
            'wysiwyg'         => ((int)$r['wysiwyg'] === 1),
            'rss_field'       => (string)$r['rss_field'],
            'contact_field'   => (string)$r['contact_field'],
            'default_value'   => (string)$r['default_value'],
            'folder_default'  => ((int)$r['use_folder_name_for_default_value'] === 1),
            'size'            => (int)$r['size'],
            'maxlength'       => (int)$r['maxlength'],
            'rows'            => (int)$r['rows'],
            'cols'            => (int)$r['cols'],
            'spacing_above'   => ((int)$r['spacing_above'] === 1),
            'spacing_below'   => ((int)$r['spacing_below'] === 1),
            'upload_folder'   => isset($upload_by_field[$id]) ? $upload_by_field[$id] : 0,
            'quiz_question'   => ((int)$r['quiz_question'] === 1),
            'quiz_answer'     => (string)$r['quiz_answer'],
            'information'     => (string)$r['information'],
            'regex'           => isset($validation_by_field[$id]) ? $validation_by_field[$id]['regex'] : '',
            'regex_message'   => isset($validation_by_field[$id]) ? $validation_by_field[$id]['message'] : '',
            'options'         => isset($options_by_field[$id]) ? $options_by_field[$id] : array(),
        );
    }
    return $out;
}

/**
 * Folders the operator may upload into, flat, for the field editor's pickers.
 *
 * Only folders they can actually edit: an upload folder they cannot manage is
 * not a folder they may send visitors' files to, which is the check
 * add_field.php makes at write time. Doing it here as well means the editor
 * never offers a choice the save would silently drop.
 */
function pg_cf_folder_options()
{
    $out  = array();
    $rows = db_items("SELECT folder_id, folder_name, folder_parent FROM folder ORDER BY folder_name ASC");
    if (!is_array($rows)) return $out;
    foreach ($rows as $r) {
        $id = (int)$r['folder_id'];
        if (function_exists('check_edit_access') && !check_edit_access($id)) continue;
        $out[] = array('id' => $id, 'name' => (string)$r['folder_name']);
    }
    return $out;
}

/**
 * Is this form's own page gone?
 *
 * A form belongs to a page: `custom_form.php` needs that page to check access
 * and to build the confirmation redirect, and the legacy form screens edit it
 * from there. So a form whose page has been deleted - or sent to the recycle
 * bin - is a form nothing renders and nobody can edit. THAT is the form a
 * widget may adopt.
 *
 * A form whose page is alive is off limits: adding or removing a field here
 * would change what the other page renders and validates, and that page would
 * start refusing submissions for a field it never draws. The editor shows it
 * read-only instead.
 */
function pg_cf_form_is_orphaned($page_id)
{
    $page_id = (int)$page_id;
    if ($page_id <= 0 || !pg_cf_page_form_ready()) return false;

    $row = db_item("SELECT page_id, page_folder FROM page WHERE page_id = '$page_id' LIMIT 1");
    if (!is_array($row)) return true;                       // page deleted outright

    if (function_exists('pg_recycle_bin_folder_ids')) {
        $binned = pg_recycle_bin_folder_ids();
        if (is_array($binned) && in_array((int)$row['page_folder'], array_map('intval', $binned), true)) {
            return true;                                    // page is in the recycle bin
        }
    }
    return false;
}

/**
 * Move an orphaned form onto a page that now renders it.
 *
 * Everything moves together - the settings row, the fields, their options and
 * the submissions - because they are one record addressed by page_id. Moving
 * only the fields would leave the submissions pointing at a page that no
 * longer has a form, and the submissions list would go blank.
 *
 * Refused when the source page is alive: see pg_cf_form_is_orphaned().
 */
function pg_cf_adopt_form($from_page_id, $to_page_id, $user)
{
    $from = (int)$from_page_id;
    $to   = (int)$to_page_id;
    if ($from <= 0 || $to <= 0 || $from === $to) return false;
    if (!pg_cf_page_form_ready() || !pg_cf_form_is_orphaned($from)) return false;

    $target = db_item("SELECT page_id, page_folder, page_name, page_title FROM page WHERE page_id = '$to' LIMIT 1");
    if (!is_array($target)) return false;
    if (function_exists('check_edit_access') && !check_edit_access($target['page_folder'])) return false;

    // The destination must not already have a form of its own — merging two
    // field sets by name would silently join two histories of submissions.
    if (db_value("SELECT COUNT(*) FROM form_fields WHERE page_id = '$to'") > 0) return false;

    $name = trim((string)$target['page_title']);
    if ($name === '') $name = (string)$target['page_name'];

    if (db_value("SELECT COUNT(*) FROM custom_form_pages WHERE page_id = '$to'") > 0) {
        db("DELETE FROM custom_form_pages WHERE page_id = '$to'");
    }
    db("UPDATE custom_form_pages   SET page_id = '$to' WHERE page_id = '$from'");
    db("UPDATE form_fields         SET page_id = '$to' WHERE page_id = '$from'");
    db("UPDATE form_field_options  SET page_id = '$to' WHERE page_id = '$from'");
    db("UPDATE target_options      SET page_id = '$to' WHERE page_id = '$from'");
    db("UPDATE forms               SET page_id = '$to' WHERE page_id = '$from'");
    pg_cf_ensure_form_page($to, $name);

    log_activity(lang(array(
        'string' => 'orphaned form was moved to page ({var:1})',
        'vars'   => array($target['page_name']))), isset($_SESSION['sessionusername']) ? $_SESSION['sessionusername'] : '');
    return true;
}

/**
 * The form-level settings of a page, for the editor.
 *
 * Read from `custom_form_pages` rather than kept on the widget: the form
 * belongs to the PAGE, and the same widget placed on two pages must not make
 * them share one notification address.
 */
function pg_cf_load_page_form_settings($page_id)
{
    $page_id  = (int)$page_id;
    $defaults = array(
        'exists' => false, 'orphan' => false, 'form_name' => '', 'enabled' => 1, 'quiz' => 0,
        'notify_email' => '', 'notify_subject' => '',
        'confirm_email' => 0, 'confirm_subject' => '',
        'confirmation_message' => '', 'confirmation_page_id' => 0, 'contact_group_id' => 0,
        'membership' => 0, 'membership_days' => 0, 'auto_registration' => 0,
    );
    if ($page_id <= 0 || !pg_cf_page_form_ready()) return $defaults;

    $row = db_item(
        "SELECT form_name, enabled, quiz, save, auto_registration,
                administrator_email, administrator_email_to_email_address, administrator_email_subject,
                submitter_email, submitter_email_subject,
                confirmation_type, confirmation_message, confirmation_page_id, contact_group_id,
                membership, membership_days
         FROM custom_form_pages WHERE page_id = '$page_id' LIMIT 1");
    if (!is_array($row)) return $defaults;

    return array(
        'exists'               => true,
        // An orphan may be adopted by the page that renders it; a form whose
        // own page is alive may only be looked at from here.
        'orphan'               => pg_cf_form_is_orphaned($page_id),
        'form_name'            => (string)$row['form_name'],
        'enabled'              => (int)$row['enabled'] === 1 ? 1 : 0,
        'quiz'                 => (int)$row['quiz'] === 1 ? 1 : 0,
        'notify_email'         => ((int)$row['administrator_email'] === 1)
                                      ? (string)$row['administrator_email_to_email_address'] : '',
        'notify_subject'       => (string)$row['administrator_email_subject'],
        'confirm_email'        => (int)$row['submitter_email'] === 1 ? 1 : 0,
        'confirm_subject'      => (string)$row['submitter_email_subject'],
        'confirmation_message' => (string)$row['confirmation_message'],
        // "Next page": where the visitor lands after submitting. Zero means
        // the message above is shown instead.
        'confirmation_page_id' => ((string)$row['confirmation_type'] === 'page')
                                      ? (int)$row['confirmation_page_id'] : 0,
        'contact_group_id'     => (int)$row['contact_group_id'],
        'membership'           => (int)$row['membership'] === 1 ? 1 : 0,
        'membership_days'      => (int)$row['membership_days'],
        'auto_registration'    => (int)$row['auto_registration'] === 1 ? 1 : 0,
    );
}

// `form_fields` grew columns over twenty years and not every installation has
// all of them. Probed once per column per request, same shape as
// waf_table_has_column().
function pg_cf_form_fields_has_column($column)
{
    static $seen = array();
    if (isset($seen[$column])) return $seen[$column];
    $seen[$column] = false;
    if (!db::$con) return false;
    $r = @mysqli_query(db::$con,
        "SHOW COLUMNS FROM form_fields WHERE Field = '" . e($column) . "'");
    $seen[$column] = ($r && mysqli_num_rows($r) > 0);
    return $seen[$column];
}

// The conditional-display rule an option carries, rebuilt in the "field=a,b"
// text the legacy screen accepts so the editor can show and re-save it
// without a second UI for `target_options`.
function pg_cf_option_trigger_text($option_row)
{
    $target = (int)(isset($option_row['target_form_field_id']) ? $option_row['target_form_field_id'] : 0);
    if ($target <= 0) return '';
    $name = (string)db_value("SELECT name FROM form_fields WHERE id = '$target' LIMIT 1");
    if ($name === '') return '';
    $values = db_values("SELECT value FROM target_options
                         WHERE trigger_option_id = '" . (int)$option_row['id'] . "'
                         ORDER BY id ASC");
    if (!is_array($values) || empty($values)) return '';
    return $name . '=' . implode(',', $values);
}

// Make sure `custom_form_pages` has a row for this page, and that the form is
// enabled - custom_form.php refuses a submission when it is not, and a form
// the operator just drew is a form they want to receive.
//
// The column list is read from the table rather than written out: this table
// has grown for twenty years and carries columns some installations do not,
// and a NOT NULL column with no default would otherwise fail the INSERT under
// strict mode. Only the three columns we actually mean are given values; the
// rest get a type-appropriate empty so the row can exist.
function pg_cf_ensure_form_page($page_id, $form_name)
{
    $page_id = (int)$page_id;
    if ($page_id <= 0) return false;

    $existing = db_value("SELECT page_id FROM custom_form_pages WHERE page_id = '$page_id' LIMIT 1");
    if ($existing !== '' && $existing !== null) {
        // Never overwrite a form_name the operator typed on the legacy screen.
        db("UPDATE custom_form_pages SET enabled = '1' WHERE page_id = '$page_id' AND enabled = '0'");
        return true;
    }

    // Form names are treated as unique across the site (the form screens
    // number duplicates the same way), and the name is what an operator picks
    // the form by on every other screen. Two forms called "İletişim" is a
    // support call waiting to happen.
    $form_name = trim($form_name);
    if ($form_name === '') $form_name = 'Form';
    $base = mb_substr($form_name, 0, 90);
    $try  = $base;
    for ($n = 2; $n < 100; $n++) {
        if (!db_value("SELECT id FROM custom_form_pages WHERE form_name = '" . e($try) . "' LIMIT 1")) break;
        $try = $base . '[' . $n . ']';
    }
    $form_name = $try;

    $cols = db_items("SHOW COLUMNS FROM custom_form_pages");
    if (!is_array($cols) || empty($cols)) return false;

    $names  = array();
    $values = array();
    foreach ($cols as $c) {
        $field = isset($c['Field']) ? (string)$c['Field'] : '';
        if ($field === '' || $field === 'id') continue;                 // auto-increment
        if (strpos((string)$c['Extra'], 'auto_increment') !== false) continue;

        // Values we actually mean. The confirmation defaults matter: with an
        // empty confirmation_type custom_form.php has nothing to show the
        // visitor after a successful submission, and a form that thanks
        // nobody looks broken even when it worked.
        $meant = array(
            'page_id'                    => (string)$page_id,
            'form_name'                  => $form_name,
            'enabled'                    => '1',
            'confirmation_type'          => 'message',
            'confirmation_message'       => lang('Thank you. Your message has been received.'),
            'return_type'                => 'message',
            'submitter_email_format'     => 'plain_text',
            'administrator_email_format' => 'plain_text',
            'submit_button_label'        => lang('Submit'),
        );
        if (array_key_exists($field, $meant)) {
            $names[]  = $field;
            $values[] = "'" . e($meant[$field]) . "'";
            continue;
        }

        // Nullable or defaulted columns can be left out entirely.
        if (strtoupper((string)$c['Null']) === 'YES') continue;
        if ($c['Default'] !== null) continue;

        $type = strtolower((string)$c['Type']);
        $names[] = $field;
        if (strpos($type, 'int') === 0 || strpos($type, 'tinyint') === 0
            || strpos($type, 'smallint') === 0 || strpos($type, 'mediumint') === 0
            || strpos($type, 'bigint') === 0 || strpos($type, 'decimal') === 0
            || strpos($type, 'float') === 0 || strpos($type, 'double') === 0) {
            $values[] = "'0'";
        } elseif (strpos($type, 'datetime') === 0 || strpos($type, 'timestamp') === 0) {
            $values[] = "'0000-00-00 00:00:00'";
        } elseif (strpos($type, 'date') === 0) {
            $values[] = "'0000-00-00'";
        } else {
            $values[] = "''";
        }
    }
    if (empty($names)) return false;

    db("INSERT INTO custom_form_pages (" . implode(', ', $names) . ") VALUES (" . implode(', ', $values) . ")");
    return true;
}

/**
 * The notification e-mail body a form gets when nobody has written one.
 *
 * `get_variable_submitted_form_data_for_content()` replaces ^^field_name^^
 * with the submitted value, and does nothing at all to an empty body - so a
 * form with no body sends a blank e-mail. Listing the fields is the only
 * default that is actually useful, and it is exactly what the operator would
 * have typed.
 */
function pg_cf_default_email_body($fields)
{
    $lines = array();
    foreach ((array)$fields as $f) {
        $name  = isset($f['name'])  ? (string)$f['name']  : '';
        $label = isset($f['label']) ? (string)$f['label'] : $name;
        if ($name === '') continue;
        $lines[] = $label . ': ^^' . $name . '^^';
    }
    return implode("\n", $lines);
}

/**
 * Settings the editor owns for a page's own form.
 *
 * Only keys the widget actually carries are written, and the e-mail bodies are
 * only rewritten while they are still exactly what we generated last time -
 * the moment an operator edits one, it is theirs and we stop touching it.
 * $old_fields is the field set as it was BEFORE this save, which is what makes
 * that comparison possible.
 */
function pg_cf_write_form_settings($page_id, $settings, $old_fields, $new_fields)
{
    $page_id = (int)$page_id;
    if ($page_id <= 0 || !is_array($settings)) return;

    $row = db_item(
        "SELECT administrator_email_body, submitter_email_body
         FROM custom_form_pages WHERE page_id = '$page_id' LIMIT 1");
    if (!is_array($row)) return;

    $set = array();

    if (array_key_exists('notify_email', $settings)) {
        $to = trim((string)$settings['notify_email']);
        // One address or a comma-separated list, same as the form screen.
        $set['administrator_email_to_email_address'] = $to;
        $set['administrator_email'] = ($to !== '') ? '1' : '0';
    }
    if (array_key_exists('notify_subject', $settings)) {
        $set['administrator_email_subject'] = mb_substr(trim((string)$settings['notify_subject']), 0, 255);
    }
    if (array_key_exists('confirm_email', $settings)) {
        $set['submitter_email'] = !empty($settings['confirm_email']) ? '1' : '0';
    }
    if (array_key_exists('confirm_subject', $settings)) {
        $set['submitter_email_subject'] = mb_substr(trim((string)$settings['confirm_subject']), 0, 255);
    }
    if (array_key_exists('contact_group_id', $settings)) {
        $set['contact_group_id'] = (int)$settings['contact_group_id'];
    }
    if (array_key_exists('form_name', $settings)) {
        $fn = mb_substr(trim((string)$settings['form_name']), 0, 100);
        // Never blank it: form_name is how every other screen names this form.
        if ($fn !== '') $set['form_name'] = $fn;
    }
    if (array_key_exists('enabled', $settings)) {
        $set['enabled'] = !empty($settings['enabled']) ? '1' : '0';
    }
    if (array_key_exists('quiz', $settings)) {
        $set['quiz'] = !empty($settings['quiz']) ? '1' : '0';
    }
    // Membership is what turns a contact form into a sign-up. It only works
    // with a day count, and without auto-registration it demands the visitor
    // already be signed in — so the two travel together and a membership of
    // zero days is no membership at all.
    if (array_key_exists('membership', $settings)) {
        $days = max(0, (int)(isset($settings['membership_days']) ? $settings['membership_days'] : 0));
        $on   = (!empty($settings['membership']) && $days > 0);
        $set['membership']      = $on ? '1' : '0';
        $set['membership_days'] = $on ? (string)$days : '0';
    }
    if (array_key_exists('auto_registration', $settings)) {
        $set['auto_registration'] = !empty($settings['auto_registration']) ? '1' : '0';
    }
    if (array_key_exists('confirmation_message', $settings)) {
        $msg = trim((string)$settings['confirmation_message']);
        if ($msg !== '') $set['confirmation_message'] = $msg;
    }
    // Where the visitor lands after submitting: a page, or the message.
    // custom_form.php reads confirmation_type; the page id alone is not
    // enough, and a page that no longer exists falls back to the message.
    if (array_key_exists('confirmation_page_id', $settings)) {
        $next = (int)$settings['confirmation_page_id'];
        if ($next > 0 && !db_value("SELECT COUNT(*) FROM page WHERE page_id = '$next'")) $next = 0;
        $set['confirmation_page_id'] = $next;
        $set['confirmation_type']    = ($next > 0) ? 'page' : 'message';
    }

    // Bodies: still auto-generated → regenerate for the new field list.
    $was = pg_cf_default_email_body($old_fields);
    $now = pg_cf_default_email_body($new_fields);
    if ((string)$row['administrator_email_body'] === '' || (string)$row['administrator_email_body'] === $was) {
        $set['administrator_email_body'] = $now;
    }
    if ((string)$row['submitter_email_body'] === '' || (string)$row['submitter_email_body'] === $was) {
        $set['submitter_email_body'] = $now;
    }

    if (empty($set)) return;
    $sql = array();
    foreach ($set as $col => $val) $sql[] = $col . " = '" . e($val) . "'";
    db("UPDATE custom_form_pages SET " . implode(', ', $sql) . " WHERE page_id = '$page_id'");
}

/**
 * Write a page's form: `custom_form_pages` + `form_fields` + `form_field_options`.
 *
 * The list is AUTHORITATIVE - pg_cf_reconcile_page_form() derives it from the
 * controls the page's widgets draw - so this writes exactly it: rows with an
 * id are updated in place, rows without one are created, and rows that are
 * gone from the list are deleted.
 *
 * Updating in place is what keeps submissions attached: `form_data` points at
 * `form_fields.id`, so renaming a field or changing its type must not become a
 * delete plus an insert. That is why the control carries the id and this
 * function trusts it over the name.
 *
 * Deleting is never silent: the count of fields removed - and of submissions
 * that lose a value with them - comes back so the caller can say so.
 *
 * Every field is an array with the keys pg_cf_load_page_fields() returns.
 * Returns array('written', 'created', 'deleted', 'orphaned', 'lost_values').
 */
function pg_cf_sync_page_form($page_id, $fields, $form_name, $user_id, $settings = array())
{
    $page_id = (int)$page_id;
    $result  = array('written' => 0, 'created' => 0, 'deleted' => 0,
                     'orphaned' => 0, 'lost_values' => 0);
    if ($page_id <= 0 || !is_array($fields) || !pg_cf_page_form_ready()) return $result;

    $form_name = trim((string)$form_name);
    if ($form_name === '') {
        $form_name = (string)db_value("SELECT page_name FROM page WHERE page_id = '$page_id' LIMIT 1");
    }
    if (!pg_cf_ensure_form_page($page_id, $form_name)) return $result;

    // Guard rail, on the server where it counts: a form that belongs to a
    // LIVE page of its own is only rendered here, never rewritten from here.
    // Adding or removing a field would change what that page draws and what
    // it validates, and it would start rejecting submissions for a field it
    // does not show. The editor greys the manager out; this is the half that
    // a hand-made POST cannot get past.
    $result['read_only'] = false;
    if (isset($settings['__foreign']) && $settings['__foreign'] && !pg_cf_form_is_orphaned($page_id)) {
        $result['read_only'] = true;
        return $result;
    }

    $valid_types    = pg_cf_field_types();
    $valid_rss      = pg_cf_rss_fields();
    $valid_contact  = pg_cf_contact_fields();
    $has_upload_col = pg_cf_form_fields_has_column('upload_folder_id');
    $has_validation = pg_cf_form_fields_has_column('validation_regex');
    $user_id        = (int)$user_id;

    // What is on the page's form right now, so we can tell an update from an
    // insert and find what the operator removed.
    $existing = array();
    $old_list = array();
    foreach ((array)db_items(
        "SELECT id, name, label FROM form_fields
         WHERE page_id = '$page_id' AND form_type = 'custom'
         ORDER BY sort_order ASC, id ASC") as $row) {
        $existing[(int)$row['id']] = $row;
        $old_list[] = array('name' => $row['name'], 'label' => $row['label']);
    }

    $kept         = array();
    $seen_names   = array();
    $written_list = array();
    $order        = 0;

    foreach ($fields as $f) {
        if (!is_array($f)) continue;

        $type = isset($f['type']) ? (string)$f['type'] : '';
        if (!in_array($type, $valid_types, true)) $type = 'text box';

        // Information blocks are rich text, not inputs; they still need a name
        // because every other screen addresses fields by one.
        $name = pg_cf_clean_field_name(isset($f['name']) ? $f['name'] : '');
        if ($name === '') continue;
        // Two fields with one name would make ^^name^^ ambiguous on every
        // list view. First wins, same rule the legacy screen enforces.
        $key = mb_strtolower($name, 'UTF-8');
        if (isset($seen_names[$key])) continue;
        $seen_names[$key] = true;

        $label = trim((string)(isset($f['label']) ? $f['label'] : ''));
        if ($label === '') $label = $name;

        $id = isset($f['id']) ? (int)$f['id'] : 0;
        if ($id > 0 && !isset($existing[$id])) $id = 0;   // not ours: treat as new
        $order++;

        $set = array(
            'name'                              => $name,
            'label'                             => mb_substr($label, 0, 255),
            'type'                              => $type,
            'required'                          => !empty($f['required']) ? 1 : 0,
            'multiple'                          => !empty($f['multiple']) ? 1 : 0,
            'office_use_only'                   => !empty($f['office_use_only']) ? 1 : 0,
            'wysiwyg'                           => !empty($f['wysiwyg']) ? 1 : 0,
            'rss_field'                         => (isset($f['rss_field']) && isset($valid_rss[(string)$f['rss_field']]))
                                                       ? (string)$f['rss_field'] : '',
            'contact_field'                     => (isset($f['contact_field']) && isset($valid_contact[(string)$f['contact_field']]))
                                                       ? (string)$f['contact_field'] : '',
            'default_value'                     => mb_substr((string)(isset($f['default_value']) ? $f['default_value'] : ''), 0, 255),
            'use_folder_name_for_default_value' => !empty($f['folder_default']) ? 1 : 0,
            'size'                              => max(0, (int)(isset($f['size']) ? $f['size'] : 0)),
            'maxlength'                         => max(0, (int)(isset($f['maxlength']) ? $f['maxlength'] : 0)),
            'rows'                              => max(0, min(200, (int)(isset($f['rows']) ? $f['rows'] : 0))),
            'cols'                              => max(0, min(500, (int)(isset($f['cols']) ? $f['cols'] : 0))),
            'spacing_above'                     => !empty($f['spacing_above']) ? 1 : 0,
            'spacing_below'                     => !empty($f['spacing_below']) ? 1 : 0,
            'quiz_question'                     => !empty($f['quiz_question']) ? 1 : 0,
            'quiz_answer'                       => mb_substr((string)(isset($f['quiz_answer']) ? $f['quiz_answer'] : ''), 0, 255),
            'information'                       => (string)(isset($f['information']) ? $f['information'] : ''),
            'sort_order'                        => $order,
        );
        if ($has_validation) {
            // Stored verbatim. It is an HTML `pattern`, which is anchored by
            // definition, and the server compiles it with delimiters of its
            // own — so nothing here has to guess at the operator's intent.
            $set['validation_regex']   = mb_substr((string)(isset($f['regex']) ? $f['regex'] : ''), 0, 255);
            $set['validation_message'] = mb_substr((string)(isset($f['regex_message']) ? $f['regex_message'] : ''), 0, 255);
        }

        // A folder the operator cannot edit is not a folder they may upload
        // into - the same check add_field.php makes.
        if ($has_upload_col) {
            $folder = (int)(isset($f['upload_folder']) ? $f['upload_folder'] : 0);
            if ($folder > 0 && (!db_value("SELECT COUNT(*) FROM folder WHERE folder_id = '$folder'")
                                || (function_exists('check_edit_access') && !check_edit_access($folder)))) {
                $folder = 0;
            }
            $set['upload_folder_id'] = $folder;
        }

        if ($id > 0) {
            $pairs = array();
            foreach ($set as $col => $val) {
                $pairs[] = '`' . $col . "` = '" . e($val) . "'";
            }
            $pairs[] = "user = '$user_id'";
            $pairs[] = 'timestamp = UNIX_TIMESTAMP()';
            db("UPDATE form_fields SET " . implode(', ', $pairs) . " WHERE id = '$id'");
        } else {
            $set['form_type'] = 'custom';
            $set['page_id']   = $page_id;
            $cols = array();
            $vals = array();
            foreach ($set as $col => $val) { $cols[] = '`' . $col . '`'; $vals[] = "'" . e($val) . "'"; }
            $cols[] = '`user`';      $vals[] = "'$user_id'";
            $cols[] = '`timestamp`'; $vals[] = 'UNIX_TIMESTAMP()';
            db("INSERT INTO form_fields (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $vals) . ")");
            $id = (int)mysqli_insert_id(db::$con);
            if ($id > 0) $result['created']++;
        }
        if ($id <= 0) continue;

        $kept[$id] = true;
        $result['written']++;
        $written_list[] = array('name' => $name, 'label' => $label);

        pg_cf_write_field_options($page_id, $id, $type,
            isset($f['options']) ? $f['options'] : array());
    }

    // Removed by the operator. Counted first so the caller can report how much
    // submitted data goes with them - a field deleted is a column heading gone
    // from every past submission, and that has to be said out loud.
    foreach ($existing as $id => $row) {
        if (isset($kept[$id])) continue;
        $result['deleted']++;
        $result['lost_values'] += (int)db_value(
            "SELECT COUNT(*) FROM form_data WHERE form_field_id = '" . (int)$id . "'");
        db("DELETE FROM form_field_options WHERE form_field_id = '" . (int)$id . "'");
        db("DELETE FROM target_options WHERE trigger_form_field_id = '" . (int)$id . "'");
        db("DELETE FROM form_data WHERE form_field_id = '" . (int)$id . "'");
        db("DELETE FROM form_fields WHERE id = '" . (int)$id . "'");
    }

    pg_cf_write_form_settings($page_id, $settings, $old_list, $written_list);

    return $result;
}

/**
 * Rewrite one field's choices.
 *
 * Wholesale, because `form_data` stores the submitted VALUE and a copy of the
 * field name, never an option id - no submission points at the rows being
 * replaced. `target_options` does point at them, so it is rebuilt in step.
 */
function pg_cf_write_field_options($page_id, $field_id, $type, $options)
{
    $page_id  = (int)$page_id;
    $field_id = (int)$field_id;
    if ($field_id <= 0) return;

    // Old rows go whatever the new type is: a field that stopped being a pick
    // list must not keep choices nothing reads.
    foreach ((array)db_values("SELECT id FROM form_field_options WHERE form_field_id = '$field_id'") as $old_id) {
        db("DELETE FROM target_options WHERE trigger_option_id = '" . (int)$old_id . "'");
    }
    db("DELETE FROM form_field_options WHERE form_field_id = '$field_id'");
    if (!pg_cf_type_has_options($type) || !is_array($options)) return;

    $order = 0;
    foreach ($options as $opt) {
        if (!is_array($opt)) continue;
        $label = mb_substr(trim((string)(isset($opt['label']) ? $opt['label'] : '')), 0, 255);
        $value = mb_substr(trim((string)(isset($opt['value']) ? $opt['value'] : '')), 0, 255);
        if ($label === '' && $value === '') continue;
        if ($value === '') $value = $label;
        if ($label === '') $label = $value;

        // Conditional admin notification addresses, comma separated, each
        // validated exactly as the legacy screen validates them.
        $emails = array();
        foreach (explode(',', (string)(isset($opt['email_address']) ? $opt['email_address'] : '')) as $address) {
            $address = trim($address);
            if ($address === '') continue;
            if (function_exists('validate_email_address') && !validate_email_address($address)) continue;
            $emails[] = $address;
        }

        $folder = (int)(isset($opt['upload_folder']) ? $opt['upload_folder'] : 0);
        if ($folder > 0 && (!db_value("SELECT COUNT(*) FROM folder WHERE folder_id = '$folder'")
                            || (function_exists('check_edit_access') && !check_edit_access($folder)))) {
            $folder = 0;
        }

        // "otherField=a,b" — show otherField's options a and b when this
        // choice is picked. Resolved to ids here, same as add_field.php.
        $target_field_id = 0;
        $target_values   = array();
        $trigger = trim((string)(isset($opt['trigger']) ? $opt['trigger'] : ''));
        if ($trigger !== '' && strpos($trigger, '=') !== false) {
            list($target_name, $target_list) = explode('=', $trigger, 2);
            $target_name = pg_cf_clean_field_name($target_name);
            if ($target_name !== '') {
                $target_field_id = (int)db_value(
                    "SELECT id FROM form_fields
                     WHERE page_id = '$page_id' AND form_type = 'custom'
                       AND name = '" . e($target_name) . "' AND type = 'pick list' LIMIT 1");
                foreach (explode(',', $target_list) as $tv) {
                    $tv = trim($tv);
                    if ($tv !== '') $target_values[] = $tv;
                }
            }
            if ($target_field_id <= 0 || empty($target_values)) {
                $target_field_id = 0;
                $target_values   = array();
            }
        }

        $order++;
        db("INSERT INTO form_field_options
                (page_id, form_field_id, label, value, email_address, default_selected,
                 sort_order, target_form_field_id, upload_folder_id)
            VALUES
                ('$page_id', '$field_id', '" . e($label) . "', '" . e($value) . "',
                 '" . e(implode(', ', $emails)) . "', '" . (!empty($opt['default']) ? 1 : 0) . "',
                 '$order', '$target_field_id', '$folder')");
        $option_id = (int)mysqli_insert_id(db::$con);

        foreach ($target_values as $tv) {
            db("INSERT INTO target_options (page_id, trigger_form_field_id, trigger_option_id, value)
                VALUES ('$page_id', '$field_id', '$option_id', '" . e($tv) . "')");
        }
    }
}

/**
 * Field rows of a designed form, keyed by lower-cased name, for the render
 * walker. Static per request: a page can carry the same widget more than once.
 */
function pg_cf_field_map($page_id)
{
    static $cache = array();
    $page_id = (int)$page_id;
    if ($page_id <= 0) return array();
    if (isset($cache[$page_id])) return $cache[$page_id];

    $map = array();
    foreach ((array)db_items(
        "SELECT id, name, label, type, required, multiple, maxlength, size,
                `rows`, cols, default_value, use_folder_name_for_default_value,
                contact_field, office_use_only, wysiwyg, information, rss_field"
         . (pg_cf_form_fields_has_column('validation_regex')
                ? ", validation_regex, validation_message" : ", '' AS validation_regex, '' AS validation_message") . "
         FROM form_fields
         WHERE page_id = '$page_id' AND form_type = 'custom'
         ORDER BY sort_order ASC, id ASC") as $row) {
        $map[mb_strtolower((string)$row['name'], 'UTF-8')] = $row;
    }
    $cache[$page_id] = $map;
    return $map;
}

/**
 * The submitter's contact record, when there is one to pre-fill from.
 *
 * A field wired to `contact_field` is filled from the signed-in visitor's
 * contact and writes back to it on submit - that is the whole point of the
 * wiring, and it is why a returning member does not retype their address.
 * Resolved once per request; an anonymous visitor gets an empty array and
 * every contact-wired field simply falls through to its own default.
 */
function pg_cf_submitter_contact()
{
    static $contact = null;
    if ($contact !== null) return $contact;
    $contact = array();
    if (defined('USER_LOGGED_IN') && USER_LOGGED_IN && defined('USER_ID')) {
        $row = db_item(
            "SELECT contacts.*
             FROM user
             LEFT JOIN contacts ON user.user_contact = contacts.id
             WHERE user.user_id = '" . e(USER_ID) . "' LIMIT 1");
        if (is_array($row)) $contact = $row;
    }
    return $contact;
}

/**
 * What a bound control should start out showing, in the order the legacy form
 * screen resolves it. The order matters and is not arbitrary:
 *
 *   1. what the visitor typed before a failed submission - never lose that
 *   2. ?value_<id> in the URL - a link that pre-fills the form on purpose
 *   3. the visitor's own contact record - the reason contact_field exists
 *   4. the folder name, when the field is set to use it
 *   5. the field's default value
 */
function pg_cf_field_initial_value($field, $lf, $folder_id = 0)
{
    $id = (int)$field['id'];

    if ($lf) {
        $stored = $lf->get_field_value($id);
        if (is_array($stored) || ($stored !== '' && $stored !== null)) return $stored;
    }
    if (isset($_GET['value_' . $id]) && trim((string)$_GET['value_' . $id]) !== '') {
        return trim((string)$_GET['value_' . $id]);
    }
    if (!empty($field['contact_field'])) {
        $contact = pg_cf_submitter_contact();
        $key     = (string)$field['contact_field'];
        if (isset($contact[$key]) && $contact[$key] !== '') return (string)$contact[$key];
    }
    if (!empty($field['use_folder_name_for_default_value']) && (int)$folder_id > 0) {
        $name = (string)db_value("SELECT folder_name FROM folder WHERE folder_id = '" . (int)$folder_id . "' LIMIT 1");
        if ($name !== '') return $name;
    }
    if (isset($field['default_value']) && (string)$field['default_value'] !== '') {
        return (string)$field['default_value'];
    }
    return '';
}

/**
 * Give every label the control it names.
 *
 * A <label> without `for` only reaches its control by wrapping it, which the
 * Bootstrap block does not do - label and control are siblings. So a label
 * drawn by hand names nothing: clicking it does not focus the field, and a
 * screen reader reads the field as unlabelled. The ids are known only after
 * pg_cf_apply_field_bindings() has run (it invents one for a control that had
 * none), so this is a pass of its own, after it.
 *
 * A wrapper holding several controls is left alone - which of them the label
 * belongs to is a guess, and a wrong `for` is worse than a missing one.
 */
function pg_cf_link_labels(&$tree)
{
    if (!is_array($tree)) return;

    $tag_of = function ($n) {
        return (is_array($n) && isset($n['type']) && $n['type'] === 'semantic' && isset($n['props']['tag']))
            ? strtolower((string)$n['props']['tag']) : '';
    };
    $attr_of = function ($n, $name) {
        if (!is_array($n) || empty($n['props']['_attrs']) || !is_array($n['props']['_attrs'])) return null;
        foreach ($n['props']['_attrs'] as $a) {
            if (is_array($a) && isset($a['name']) && $a['name'] === $name) {
                return isset($a['value']) ? (string)$a['value'] : '';
            }
        }
        return null;
    };
    $id_of = function ($n) use ($attr_of) {
        if (is_array($n) && isset($n['props']['id']) && trim((string)$n['props']['id']) !== '') {
            return trim((string)$n['props']['id']);
        }
        $a = $attr_of($n, 'id');
        return ($a === null) ? '' : trim($a);
    };

    // Every id in this tree, so a `for` pointing at something that is no
    // longer here can be told apart from one that is correct. A dangling
    // `for` is worse than a missing one: the label reaches nothing AND the
    // renderer would leave it alone, so nothing on screen says it is broken.
    // It happens the ordinary way - the control's id was edited and the
    // label was written before that.
    $ids = array();
    $collect_ids = function ($node) use (&$collect_ids, &$ids, $id_of) {
        if (!is_array($node)) return;
        $id = $id_of($node);
        if ($id !== '') $ids[$id] = true;
        if (!empty($node['children']) && is_array($node['children'])) {
            foreach ($node['children'] as $c) { $collect_ids($c); }
        }
    };
    $collect_ids($tree);

    $walk = function (&$node) use (&$walk, $tag_of, $attr_of, $id_of, $ids) {
        if (!is_array($node) || empty($node['children']) || !is_array($node['children'])) return;

        $labels   = array();
        $controls = array();
        foreach ($node['children'] as $i => $child) {
            $t = $tag_of($child);
            if ($t === 'label') {
                $for = $attr_of($child, 'for');
                $for = ($for === null) ? '' : trim($for);
                if ($for === '' || !isset($ids[$for])) $labels[] = $i;
            } elseif (in_array($t, array('input', 'select', 'textarea'), true)) {
                $type = strtolower((string)$attr_of($child, 'type'));
                // A hidden field has nothing to label.
                if ($type !== 'hidden') $controls[] = $i;
            }
        }
        if (count($labels) === 1 && count($controls) === 1) {
            $target = $id_of($node['children'][$controls[0]]);
            if ($target !== '') {
                $li =& $node['children'][$labels[0]];
                if (!isset($li['props']['_attrs']) || !is_array($li['props']['_attrs'])) {
                    $li['props']['_attrs'] = array();
                }
                // Replace, not append: a dangling `for` is still in the list
                // and two `for` attributes on one element is undefined.
                $li['props']['_attrs'] = array_values(array_filter($li['props']['_attrs'],
                    function ($a) { return !(is_array($a) && isset($a['name']) && $a['name'] === 'for'); }));
                $li['props']['_attrs'][] = array('name' => 'for', 'value' => $target);
                unset($li);
            }
        }

        foreach ($node['children'] as &$child) { $walk($child); }
        unset($child);
    };
    $walk($tree);
}

/**
 * Resolve every control of a designed form against the page's field rows.
 *
 * custom_form.php addresses fields by their numeric row id ($_POST[<id>]), so
 * the id is what has to reach the browser - but the designer only ever wrote
 * a name. This walker looks the name up in the page's rows at render time and
 * rewrites `name` to the id. A control whose name has no row (the page has not
 * been saved since it was drawn) is left as it is: it posts under its own
 * name, custom_form.php ignores it, and nothing breaks.
 *
 * Office-use-only controls are kept only for $staff (the page in edit mode,
 * viewed by someone who may edit it); for a visitor the control goes, its
 * label goes with it, and a wrapper left empty goes after them. That is the
 * legacy rule: custom_form.php reads such a field from the POST only when the
 * form says `office_use_only=true`, and otherwise stores its default value.
 *
 * $lf is the page's liveform, used to re-fill what the visitor typed when the
 * submission came back with an error.
 */
function pg_cf_apply_field_bindings(&$tree, $map, $lf = null, $cf_folder_id = 0, $staff = false)
{
    if (!is_array($tree)) return;

    $tag_lc = (isset($tree['type']) && $tree['type'] === 'semantic' && isset($tree['props']['tag']))
        ? strtolower((string)$tree['props']['tag']) : '';

    if (in_array($tag_lc, array('input', 'select', 'textarea'), true)) {
        if (!isset($tree['props']['_attrs']) || !is_array($tree['props']['_attrs'])) {
            $tree['props']['_attrs'] = array();
        }
        $attrs      = _pg_cf_node_attrs($tree);
        $input_type = ($tag_lc === 'input') ? strtolower((string)(isset($attrs['type']) ? $attrs['type'] : 'text')) : '';
        $name       = pg_cf_clean_field_name(isset($attrs['name']) ? $attrs['name'] : '');
        $key        = mb_strtolower($name, 'UTF-8');

        if ($name !== '' && isset($map[$key])
            && !in_array($input_type, array('submit', 'reset', 'button', 'image'), true)) {

            $row      = $map[$key];
            $field_id = (int)$row['id'];

            $set_attr = function (&$list, $attr, $value) {
                $list = array_values(array_filter($list, function ($a) use ($attr) {
                    return !(is_array($a) && isset($a['name']) && $a['name'] === $attr);
                }));
                if ($value !== null) $list[] = array('name' => $attr, 'value' => $value);
            };

            // `id` is left exactly as drawn: the designer's <label for="…">
            // points at it. One is only INVENTED for a control that has none.
            $existing_id = isset($attrs['id']) ? trim((string)$attrs['id']) : '';
            $set_attr($tree['props']['_attrs'], 'id', null);
            if ($existing_id !== '') {
                $tree['props']['id'] = $existing_id;
            } else {
                static $cf_seq = array();
                $cf_seq[$field_id] = isset($cf_seq[$field_id]) ? $cf_seq[$field_id] + 1 : 0;
                $tree['props']['id'] = 'cf_' . $field_id . ($cf_seq[$field_id] > 0 ? '_' . $cf_seq[$field_id] : '');
            }

            // A multi-select and a checkbox group post an array.
            $array_post = ($tag_lc === 'select' && !empty($row['multiple']))
                       || ($input_type === 'checkbox' && !empty($row['multiple']));
            $set_attr($tree['props']['_attrs'], 'name', $field_id . ($array_post ? '[]' : ''));

            // `required` on every box of a checkbox GROUP would make the
            // browser demand all of them ticked. The server checks that one
            // is; the browser is told nothing. A lone consent box keeps it.
            if ($input_type === 'checkbox' && array_key_exists('required', $attrs)) {
                static $cf_option_count = array();
                if (!isset($cf_option_count[$field_id])) {
                    $cf_option_count[$field_id] = (int)db_value(
                        "SELECT COUNT(*) FROM form_field_options WHERE form_field_id = '$field_id'");
                }
                if ($cf_option_count[$field_id] > 1) $set_attr($tree['props']['_attrs'], 'required', null);
            }

            // The message the browser shows when `pattern` refuses the value.
            // The pattern itself is the designer's own attribute.
            $cf_msg = isset($row['validation_message']) ? trim((string)$row['validation_message']) : '';
            if ($cf_msg !== '' && isset($attrs['pattern']) && trim((string)$attrs['pattern']) !== '') {
                $set_attr($tree['props']['_attrs'], 'title', $cf_msg);
            }

            // A <select> shows the FIELD's choices. They were written from
            // this very markup on save, so normally the two are identical -
            // but a list edited on the legacy field screen has to reach the
            // visitor without anybody re-drawing the control. The designer's
            // empty-valued prompt row is kept.
            if ($tag_lc === 'select') {
                $opts = db_items(
                    "SELECT label, value, default_selected FROM form_field_options
                     WHERE form_field_id = '$field_id' ORDER BY sort_order ASC, id ASC");
                if (is_array($opts) && !empty($opts)) {
                    $kept = array();
                    foreach ((array)(isset($tree['children']) ? $tree['children'] : array()) as $child) {
                        if (!is_array($child) || !isset($child['props']['tag'])) continue;
                        if (strtolower((string)$child['props']['tag']) !== 'option') continue;
                        $ca = _pg_cf_node_attrs($child);
                        if (isset($ca['value']) && $ca['value'] === '') { $kept[] = $child; break; }
                    }
                    foreach ($opts as $o) {
                        $oattrs = array(array('name' => 'value', 'value' => (string)$o['value']));
                        if ((int)$o['default_selected'] === 1) $oattrs[] = array('name' => 'selected', 'value' => '');
                        $kept[] = array(
                            'type'     => 'semantic',
                            'props'    => array('tag' => 'option', 'text' => (string)$o['label'], '_attrs' => $oattrs),
                            'children' => array(),
                        );
                    }
                    $tree['children'] = $kept;
                }
            }

            // Starting value: what the visitor typed before a failed
            // submission, a pre-fill link, their own contact record, the
            // folder name, the field default - in that order.
            $stored = pg_cf_field_initial_value($row, $lf, $cf_folder_id);
            if ($tag_lc === 'textarea') {
                if (!is_array($stored) && $stored !== '') $tree['props']['text'] = (string)$stored;
            } elseif ($input_type === 'checkbox' || $input_type === 'radio') {
                $own = isset($attrs['value']) ? (string)$attrs['value'] : '';
                $hit = is_array($stored) ? in_array($own, $stored, true) : ((string)$stored === $own && $own !== '');
                if ($hit) $set_attr($tree['props']['_attrs'], 'checked', '');
                elseif ($stored !== '' && $stored !== null) $set_attr($tree['props']['_attrs'], 'checked', null);
            } elseif ($tag_lc === 'select') {
                pg_cf_select_stored_option($tree, $stored);
            } elseif ($input_type !== 'file' && $input_type !== 'password') {
                if (!is_array($stored) && $stored !== '') {
                    $set_attr($tree['props']['_attrs'], 'value', (string)$stored);
                }
            }
        }
    }

    if (isset($tree['children']) && is_array($tree['children'])) {
        // Office-use-only fields never appear on the public form. Dropping
        // just the input would leave its label and an empty Bootstrap block
        // behind, so the label pointing at it goes too, and a wrapper left
        // with nothing in it goes after them.
        if (!$staff) {
            $drop_ids = array();
            foreach ($tree['children'] as $child) {
                if (!is_array($child)) continue;
                $ct = (isset($child['type']) && $child['type'] === 'semantic' && isset($child['props']['tag']))
                    ? strtolower((string)$child['props']['tag']) : '';
                if (!in_array($ct, array('input', 'select', 'textarea'), true)) continue;
                $ca = _pg_cf_node_attrs($child);
                $ck = mb_strtolower(pg_cf_clean_field_name(isset($ca['name']) ? $ca['name'] : ''), 'UTF-8');
                if ($ck === '' || !isset($map[$ck]) || empty($map[$ck]['office_use_only'])) continue;
                $drop_ids[] = array('node' => $child, 'id' => isset($ca['id']) ? (string)$ca['id'] : '');
            }
            if (!empty($drop_ids)) {
                $labels_for = array();
                foreach ($drop_ids as $d) if ($d['id'] !== '') $labels_for[$d['id']] = true;
                $kept = array();
                foreach ($tree['children'] as $child) {
                    if (!is_array($child)) { $kept[] = $child; continue; }
                    $skip = false;
                    foreach ($drop_ids as $d) { if ($child === $d['node']) { $skip = true; break; } }
                    if (!$skip && isset($child['props']['tag'])
                        && strtolower((string)$child['props']['tag']) === 'label') {
                        $la = _pg_cf_node_attrs($child);
                        if (isset($la['for']) && isset($labels_for[(string)$la['for']])) $skip = true;
                    }
                    if (!$skip) $kept[] = $child;
                }
                $tree['children'] = $kept;
                if (empty($tree['children']) && isset($tree['type']) && $tree['type'] === 'semantic') {
                    $tree['props']['_pg_cf_empty'] = true;   // parent prunes it below
                }
            }
        }

        foreach ($tree['children'] as &$child) {
            pg_cf_apply_field_bindings($child, $map, $lf, $cf_folder_id, $staff);
        }
        unset($child);

        $tree['children'] = array_values(array_filter($tree['children'], function ($c) {
            return !(is_array($c) && !empty($c['props']['_pg_cf_empty']));
        }));
    }
}

/**
 * Bootstrap's validation switch-on, once per page.
 *
 * Bootstrap ships the CSS for :invalid feedback but leaves the "only after
 * they try to submit" behaviour to the site — without it every field is red
 * before the visitor has typed anything, which reads as "you already got it
 * wrong". This is Bootstrap's own documented snippet, scoped to the forms
 * this widget renders.
 *
 * Emitted once even when a page carries two forms: it binds by class.
 */
function pg_cf_validation_script()
{
    static $emitted = false;
    if ($emitted) return '';
    $emitted = true;
    // Emitted once, inside the first form on the page; the forms below it are
    // not in the document yet at that point, so the binding waits for
    // DOMContentLoaded and then takes every .pg-cf-form on the page (custom
    // forms and the member widgets' forms alike).
    return '<script>(function(){'
         . 'var bind=function(){var f=document.querySelectorAll(".pg-cf-form");'
         . 'Array.prototype.forEach.call(f,function(form){'
         . 'if(form.getAttribute("data-pg-validated"))return;form.setAttribute("data-pg-validated","1");'
         . 'form.addEventListener("submit",function(e){'
         . 'if(!form.checkValidity()){e.preventDefault();e.stopPropagation();'
         . 'var bad=form.querySelector(":invalid");if(bad&&bad.focus)bad.focus();}'
         . 'form.classList.add("was-validated");},false);});};'
         . 'if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",bind);}else{bind();}'
         . '})();</script>';
}

/**
 * Fill containers the designer bound to a server-built section.
 *
 * Unlike the express-order walker this one DROPS the container when the
 * section comes back empty. The only section today is the CAPTCHA, which is
 * absent for a signed-in visitor and when the setting is off - and a heading
 * with an empty box under it reads as something that failed to load.
 */
function pg_cf_apply_section_bindings(&$tree, $sections_html)
{
    if (!is_array($tree)) return;

    if (!empty($tree['children']) && is_array($tree['children'])) {
        $kept = array();
        foreach ($tree['children'] as $child) {
            if (!is_array($child)) { $kept[] = $child; continue; }
            $sec = isset($child['props']['_bindings']['section'])
                 ? (string)$child['props']['_bindings']['section'] : '';
            if ($sec !== '' && array_key_exists($sec, $sections_html)) {
                $html = (string)$sections_html[$sec];
                if ($html === '') continue;                 // nothing to show → no container
                $child['children'] = array(array(
                    'type'     => 'content',
                    'props'    => array('contentType' => 'custom_html', 'html' => $html),
                    'children' => array(),
                ));
                $kept[] = $child;
                continue;                                   // don't recurse into what we replaced
            }
            pg_cf_apply_section_bindings($child, $sections_html);
            $kept[] = $child;
        }
        $tree['children'] = $kept;
    }
}

// Mark the <option> matching what the visitor picked. Only touches `selected`,
// so an option the designer marked selected in the tree stays that way until
// there is a submitted value to honour instead.
function pg_cf_select_stored_option(&$select_node, $stored)
{
    if ($stored === '' || $stored === null) return;
    $wanted = is_array($stored) ? array_map('strval', $stored) : array((string)$stored);
    if (!isset($select_node['children']) || !is_array($select_node['children'])) return;

    foreach ($select_node['children'] as &$opt) {
        if (!is_array($opt) || !isset($opt['props']['tag'])) continue;
        if (strtolower((string)$opt['props']['tag']) !== 'option') continue;
        if (!isset($opt['props']['_attrs']) || !is_array($opt['props']['_attrs'])) $opt['props']['_attrs'] = array();
        $val = null;
        foreach ($opt['props']['_attrs'] as $a) {
            if (is_array($a) && isset($a['name']) && $a['name'] === 'value') { $val = (string)$a['value']; break; }
        }
        if ($val === null) $val = isset($opt['props']['text']) ? (string)$opt['props']['text'] : '';
        $opt['props']['_attrs'] = array_values(array_filter($opt['props']['_attrs'],
            function ($a) { return !(is_array($a) && isset($a['name']) && $a['name'] === 'selected'); }));
        if (in_array($val, $wanted, true)) {
            $opt['props']['_attrs'][] = array('name' => 'selected', 'value' => '');
        }
    }
    unset($opt);
}

/**
 * SQL that says "this page has a form", for the table alias given.
 *
 * Two kinds of page collect submissions. The legacy custom form page is a
 * page TYPE (page_type = 'custom form'). A visual-designer page that draws a
 * custom_form widget of its own keeps page_type = 'standard' — the editor
 * never changes a page's type — and until this predicate existed every screen
 * that enumerates forms tested the type alone: a submission arrived, was
 * stored, and could not be found on the Submitted Forms screen because the
 * form it belonged to was not in the list.
 *
 * The designer-owned form is recognised by its custom_form_pages row, which
 * pg_cf_ensure_form_page() creates for it. The row alone is not enough: a
 * legacy page whose type was changed away from 'custom form' keeps its row
 * (nothing deletes it), and making that page a form again would change what
 * sites have shown for years. So the page must ALSO be a visual design — its
 * own layout tree, or a style the editor made.
 */
function pg_form_page_sql($alias = 'page')
{
    $a = preg_replace('/[^A-Za-z0-9_]/', '', (string)$alias);
    if ($a === '') $a = 'page';

    $visual = (function_exists('pg_multi_page_design_ready') && pg_multi_page_design_ready())
        ? "($a.page_tree_json IS NOT NULL AND $a.page_tree_json <> '')"
        : "0";
    $visual_style = "$a.page_style IN (SELECT s.style_id FROM style s WHERE s.style_layout = 'visual_designer')";

    return "(($a.page_type = 'custom form')
             OR ($a.page_id IN (SELECT cfp.page_id FROM custom_form_pages cfp)
                 AND ($visual OR $visual_style)))";
}

/**
 * Does this custom_form widget render the form of the page it sits on?
 *
 * Twin of _cfWidgetOwnsPageForm() in style_designer.js. 'page' is the
 * editor's default; 'existing' points at a form built on the form screens; a
 * config written before the setting existed has no form_source, and is
 * 'existing' exactly when it carries a form id.
 */
function _pg_cf_widget_owns_page_form($cfg)
{
    if (!is_array($cfg)) return false;
    if (!isset($cfg['regionType']) || $cfg['regionType'] !== 'custom_form') return false;
    $source = isset($cfg['form_source']) ? (string)$cfg['form_source'] : '';
    if ($source === 'page')     return true;
    if ($source === 'existing') return false;
    $fid = (int)(isset($cfg['form_id']) ? $cfg['form_id'] : 0);
    if ($fid <= 0) $fid = (int)(isset($cfg['custom_form_page_id']) ? $cfg['custom_form_page_id'] : 0);
    return $fid <= 0;
}

/**
 * The visible text of a node and everything under it.
 */
function _pg_cf_node_text($node)
{
    $out = array();
    $walk = function ($n) use (&$walk, &$out) {
        if (!is_array($n)) return;
        if (isset($n['props']) && is_array($n['props'])) {
            if (!empty($n['props']['text']) && is_string($n['props']['text'])) $out[] = $n['props']['text'];
            elseif (!empty($n['props']['html']) && is_string($n['props']['html'])) $out[] = strip_tags($n['props']['html']);
        }
        if (!empty($n['children']) && is_array($n['children'])) foreach ($n['children'] as $c) $walk($c);
    };
    $walk($node);
    return trim(preg_replace('/\s+/u', ' ', implode(' ', $out)));
}

function _pg_cf_node_attrs($node)
{
    $attrs = array();
    if (!is_array($node) || !isset($node['props']) || !is_array($node['props'])) return $attrs;
    if (!empty($node['props']['id'])) $attrs['id'] = (string)$node['props']['id'];
    if (!empty($node['props']['_attrs']) && is_array($node['props']['_attrs'])) {
        foreach ($node['props']['_attrs'] as $a) {
            if (is_array($a) && isset($a['name']) && $a['name'] !== '') {
                $attrs[strtolower((string)$a['name'])] = isset($a['value']) ? (string)$a['value'] : '';
            }
        }
    }
    return $attrs;
}

// form_fields.type for an <input type>. Anything not listed is a text box:
// the storage does not distinguish `tel` from `text`, and inventing enum
// values here would give custom_form.php types it cannot validate.
function _pg_cf_type_by_input()
{
    return array(
        'email' => 'email address', 'date' => 'date', 'month' => 'date', 'week' => 'date',
        'time' => 'time', 'datetime-local' => 'date and time', 'file' => 'file upload',
        'checkbox' => 'check box', 'radio' => 'radio button',
    );
}

// The `_cf` record a control carries, with every key present.
function _pg_cf_node_record($node)
{
    $cf = (is_array($node) && isset($node['props']['_cf']) && is_array($node['props']['_cf']))
        ? $node['props']['_cf'] : array();
    return array(
        'ids'                => (isset($cf['ids']) && is_array($cf['ids'])) ? $cf['ids'] : array(),
        'label'              => isset($cf['label']) ? trim((string)$cf['label']) : '',
        'rss_field'          => isset($cf['rss_field']) ? (string)$cf['rss_field'] : '',
        'contact_field'      => isset($cf['contact_field']) ? (string)$cf['contact_field'] : '',
        'office_use_only'    => !empty($cf['office_use_only']),
        'validation_message' => isset($cf['validation_message']) ? trim((string)$cf['validation_message']) : '',
        'upload_folder_id'   => (int)(isset($cf['upload_folder_id']) ? $cf['upload_folder_id'] : 0),
        'quiz_question'      => !empty($cf['quiz_question']),
        'quiz_answer'        => isset($cf['quiz_answer']) ? (string)$cf['quiz_answer'] : '',
    );
}

/**
 * Every field a widget tree defines, keyed by lower-cased name, in document
 * order, with everything the writer needs.
 *
 * Twin of the editor's reading of the same markup (_cfControls in
 * style_designer.js): the label rule, the type rule and the grouping rule are
 * the same on both sides, so what the panel shows is what gets stored.
 *
 * A radio / checkbox group shares one name and comes back as ONE field whose
 * options are the group's controls; a second text control with a name already
 * taken is ignored (the first wins). A role (rss_field) taken by an earlier
 * field is cleared on a later one - one title per form.
 *
 * $page_id selects which row id the control's `_cf.ids` map carries for this
 * page; a shared widget drawn on two pages has a row on each.
 */
function _pg_cf_controls($widget_tree, $page_id = 0)
{
    $page_id  = (int)$page_id;
    $by_input = _pg_cf_type_by_input();
    $skip     = array('submit' => 1, 'reset' => 1, 'button' => 1, 'image' => 1);
    $tag_of   = function ($n) {
        return (is_array($n) && isset($n['type']) && $n['type'] === 'semantic' && isset($n['props']['tag']))
            ? strtolower((string)$n['props']['tag']) : '';
    };

    // <label for> texts, so a field gets the word the designer wrote beside it.
    $labels = array();
    $collect_labels = function ($n) use (&$collect_labels, &$labels, $tag_of) {
        if (!is_array($n)) return;
        if ($tag_of($n) === 'label') {
            $a = _pg_cf_node_attrs($n);
            if (!empty($a['for'])) $labels[(string)$a['for']] = _pg_cf_node_text($n);
        }
        if (!empty($n['children']) && is_array($n['children'])) foreach ($n['children'] as $c) $collect_labels($c);
    };
    $collect_labels($widget_tree);

    $out = array();
    $roles_taken = array();
    $walk = function ($n, $legend) use (&$walk, &$out, &$roles_taken, $labels, $by_input, $skip, $tag_of, $page_id) {
        if (!is_array($n)) return;
        $tag = $tag_of($n);

        // The nearest enclosing <fieldset>'s <legend> names a radio /
        // checkbox group. Bootstrap's own group markup, and the one thing a
        // per-option <label> cannot say.
        if ($tag === 'fieldset') {
            $legend = '';
            foreach ((array)(isset($n['children']) ? $n['children'] : array()) as $c) {
                if ($tag_of($c) === 'legend') { $legend = _pg_cf_node_text($c); break; }
            }
        }

        if (in_array($tag, array('input', 'select', 'textarea'), true)) {
            $a    = _pg_cf_node_attrs($n);
            $it   = ($tag === 'input') ? strtolower((string)(isset($a['type']) ? $a['type'] : 'text')) : '';
            $name = pg_cf_clean_field_name(isset($a['name']) ? $a['name'] : '');
            if ($name !== '' && ($tag !== 'input' || !isset($skip[$it]))) {
                $key   = mb_strtolower($name, 'UTF-8');
                $cf    = _pg_cf_node_record($n);
                $id    = isset($a['id']) ? (string)$a['id'] : '';
                $check = ($it === 'checkbox' || $it === 'radio');

                $label = '';
                if ($check) {
                    if ($legend !== '')                         $label = $legend;
                    elseif ($cf['label'] !== '')                $label = $cf['label'];
                    elseif ($id !== '' && isset($labels[$id]))  $label = $labels[$id];
                } else {
                    if ($id !== '' && isset($labels[$id]))      $label = $labels[$id];
                    elseif ($cf['label'] !== '')                $label = $cf['label'];
                    elseif (!empty($a['placeholder']))          $label = (string)$a['placeholder'];
                    elseif (!empty($a['aria-label']))           $label = (string)$a['aria-label'];
                }
                if ($label === '') $label = $name;

                $type = ($tag === 'textarea') ? 'text area'
                      : (($tag === 'select') ? 'pick list'
                      : (isset($by_input[$it]) ? $by_input[$it] : 'text box'));

                if (!isset($out[$key])) {
                    $rss = $cf['rss_field'];
                    if ($rss !== '' && isset($roles_taken[$rss])) $rss = '';
                    if ($rss !== '') $roles_taken[$rss] = true;

                    $row_id = (isset($cf['ids'][(string)$page_id])) ? (int)$cf['ids'][(string)$page_id] : 0;
                    if ($row_id <= 0 && isset($cf['ids'][$page_id])) $row_id = (int)$cf['ids'][$page_id];

                    $default = '';
                    if ($tag === 'textarea')      $default = isset($n['props']['text']) && is_string($n['props']['text']) ? $n['props']['text'] : '';
                    elseif ($tag === 'input' && !$check && $it !== 'file' && $it !== 'password') {
                        $default = isset($a['value']) ? (string)$a['value'] : '';
                    }

                    $pattern = ($tag === 'input' && !$check
                                && !in_array($it, array('file', 'range', 'color', 'hidden'), true)
                                && isset($a['pattern'])) ? trim((string)$a['pattern']) : '';

                    $out[$key] = array(
                        'id'              => $row_id,
                        'name'            => $name,
                        'label'           => $label,
                        'type'            => $type,
                        'required'        => array_key_exists('required', $a),
                        'multiple'        => ($tag === 'select' && array_key_exists('multiple', $a)) || $it === 'checkbox',
                        'office_use_only' => $cf['office_use_only'],
                        'wysiwyg'         => false,
                        'rss_field'       => $rss,
                        'contact_field'   => $cf['contact_field'],
                        'default_value'   => $default,
                        'folder_default'  => false,
                        'size'            => (int)(isset($a['size']) ? $a['size'] : 0),
                        'maxlength'       => (int)(isset($a['maxlength']) ? $a['maxlength'] : 0),
                        'rows'            => ($tag === 'textarea') ? (int)(isset($a['rows']) ? $a['rows'] : 0) : 0,
                        'cols'            => ($tag === 'textarea') ? (int)(isset($a['cols']) ? $a['cols'] : 0) : 0,
                        'spacing_above'   => false,
                        'spacing_below'   => false,
                        'upload_folder'   => ($it === 'file') ? $cf['upload_folder_id'] : 0,
                        'quiz_question'   => $cf['quiz_question'],
                        'quiz_answer'     => $cf['quiz_answer'],
                        'information'     => '',
                        'regex'           => $pattern,
                        'regex_message'   => $cf['validation_message'],
                        'options'         => array(),
                    );
                    // A <select> carries its answers as <option> children.
                    if ($tag === 'select' && !empty($n['children']) && is_array($n['children'])) {
                        foreach ($n['children'] as $o) {
                            if ($tag_of($o) !== 'option') continue;
                            $oa = _pg_cf_node_attrs($o);
                            $ot = _pg_cf_node_text($o);
                            $ov = isset($oa['value']) ? (string)$oa['value'] : $ot;
                            if ($ov === '') continue;               // the "Choose…" prompt is not a choice
                            $out[$key]['options'][] = array('label' => ($ot !== '' ? $ot : $ov), 'value' => $ov,
                                'default' => array_key_exists('selected', $oa), 'email_address' => '', 'upload_folder' => 0, 'trigger' => '');
                        }
                    }
                }
                // Each radio / checkbox of the group is one answer of the field,
                // labelled by its own <label for>.
                if ($check) {
                    $ov = isset($a['value']) ? (string)$a['value'] : '';
                    if ($ov !== '') {
                        $dup = false;
                        foreach ($out[$key]['options'] as $ex) if ($ex['value'] === $ov) { $dup = true; break; }
                        if (!$dup) {
                            $ol = ($id !== '' && isset($labels[$id])) ? $labels[$id] : $ov;
                            $out[$key]['options'][] = array('label' => $ol, 'value' => $ov,
                                'default' => array_key_exists('checked', $a), 'email_address' => '', 'upload_folder' => 0, 'trigger' => '');
                        }
                    }
                }
            }
        }
        if (!empty($n['children']) && is_array($n['children'])) foreach ($n['children'] as $c) $walk($c, $legend);
    };
    $walk($widget_tree, '');
    return $out;
}

/**
 * The custom_form widgets on a page that own the page's form: rows of
 * shared_components (id, tree_json, decoded tree), in page order.
 *
 * A widget set to "existing form" that points at this very page is the
 * page's own form as well - the controls on the canvas are its fields.
 */
function _pg_cf_page_form_widgets($page_id, $tree = null)
{
    $page_id = (int)$page_id;
    if ($tree === null) $tree = json_decode((string)pg_page_tree_json($page_id), true);
    if (!is_array($tree)) return array();

    $sids = array();
    $find = function ($n) use (&$find, &$sids) {
        if (!is_array($n)) return;
        if (isset($n['type']) && $n['type'] === 'shared_ref') {
            $sid = (int)(isset($n['props']['sharedId']) ? $n['props']['sharedId'] : 0);
            if ($sid > 0 && !in_array($sid, $sids, true)) $sids[] = $sid;
            return;
        }
        if (!empty($n['children']) && is_array($n['children'])) foreach ($n['children'] as $c) $find($c);
    };
    $find($tree);
    if (empty($sids)) return array();

    $rows = db_items(
        "SELECT id, tree_json, system_region_config FROM shared_components
         WHERE id IN (" . implode(',', array_map('intval', $sids)) . ")");
    $by_id = array();
    foreach ((array)$rows as $row) $by_id[(int)$row['id']] = $row;

    $out = array();
    foreach ($sids as $sid) {
        if (!isset($by_id[$sid])) continue;
        $row = $by_id[$sid];
        $cfg = json_decode((string)$row['system_region_config'], true);
        if (!is_array($cfg) || !isset($cfg['regionType']) || $cfg['regionType'] !== 'custom_form') continue;
        $owns = _pg_cf_widget_owns_page_form($cfg);
        if (!$owns) {
            $fid = (int)(isset($cfg['form_id']) ? $cfg['form_id'] : 0);
            if ($fid <= 0) $fid = (int)(isset($cfg['custom_form_page_id']) ? $cfg['custom_form_page_id'] : 0);
            $owns = ($fid > 0 && $fid === $page_id);
        }
        if (!$owns) continue;
        $wt = json_decode((string)$row['tree_json'], true);
        if (!is_array($wt)) continue;
        $out[] = array('id' => $sid, 'tree' => $wt, 'tree_json' => (string)$row['tree_json']);
    }
    return $out;
}

/**
 * Write the row ids the save handed out back onto the controls that own
 * them, in the widget's stored tree.
 *
 * The id is what keeps a renamed control attached to its row and its
 * submissions on the NEXT save; without it the rename would read as delete
 * plus create. Keyed by page, because a shared widget drawn on two pages has
 * a row on each. Only touched when something actually changes.
 */
function _pg_cf_stamp_ids($widget, $page_id, $ids_by_name)
{
    $page_id = (int)$page_id;
    if ($page_id <= 0 || !is_array($widget) || empty($widget['tree']) || empty($ids_by_name)) return false;
    $tree    = $widget['tree'];
    $changed = false;
    $skip    = array('submit' => 1, 'reset' => 1, 'button' => 1, 'image' => 1);

    $walk = function (&$n) use (&$walk, &$changed, $ids_by_name, $page_id, $skip) {
        if (!is_array($n)) return;
        if (isset($n['type']) && $n['type'] === 'semantic' && isset($n['props']['tag'])) {
            $tag = strtolower((string)$n['props']['tag']);
            if (in_array($tag, array('input', 'select', 'textarea'), true)) {
                $a    = _pg_cf_node_attrs($n);
                $it   = ($tag === 'input') ? strtolower((string)(isset($a['type']) ? $a['type'] : 'text')) : '';
                $name = mb_strtolower(pg_cf_clean_field_name(isset($a['name']) ? $a['name'] : ''), 'UTF-8');
                if ($name !== '' && ($tag !== 'input' || !isset($skip[$it])) && isset($ids_by_name[$name])) {
                    $want = (int)$ids_by_name[$name];
                    if (!isset($n['props']['_cf']) || !is_array($n['props']['_cf'])) $n['props']['_cf'] = array();
                    if (!isset($n['props']['_cf']['ids']) || !is_array($n['props']['_cf']['ids'])) $n['props']['_cf']['ids'] = array();
                    $have = isset($n['props']['_cf']['ids'][(string)$page_id]) ? (int)$n['props']['_cf']['ids'][(string)$page_id] : 0;
                    if ($have !== $want) {
                        $n['props']['_cf']['ids'][(string)$page_id] = $want;
                        $changed = true;
                    }
                }
            }
        }
        if (!empty($n['children']) && is_array($n['children'])) {
            foreach ($n['children'] as &$c) $walk($c);
            unset($c);
        }
    };
    $walk($tree);
    if (!$changed) return false;

    db("UPDATE shared_components
        SET tree_json = '" . e(pg_designer_tree_encode($tree)) . "',
            updated_at = " . time() . "
        WHERE id = '" . (int)$widget['id'] . "' LIMIT 1");
    return true;
}

/**
 * Make the page's form_fields rows match what its custom_form widgets draw.
 *
 * Run on every page save, after the page tree is written. The field list is
 * READ FROM THE WIDGET TREES - there is no other list. What the markup names
 * is written: a control with a row id keeps that row (renamed, retyped, it
 * does not matter), a control without one takes the row of the same name if
 * there is one and is created otherwise, and a row no control draws any more
 * is deleted along with the submitted values under it - counted, and
 * reported back, never silent.
 *
 * $settings are the form-level settings the editor sent for this page (name,
 * notification address, confirmation...); an empty array writes none.
 *
 * A page with no form widget of its own is left untouched - the form it may
 * have had is not this function's to delete. Returns the writer's result plus
 * 'synced' (whether anything was written at all).
 */
function pg_cf_reconcile_page_form($page_id, $user_id, $settings = array())
{
    $page_id = (int)$page_id;
    $none    = array('synced' => false, 'written' => 0, 'created' => 0, 'deleted' => 0, 'orphaned' => 0, 'lost_values' => 0);
    if ($page_id <= 0 || !pg_cf_page_form_ready()) return $none;

    $widgets = _pg_cf_page_form_widgets($page_id);
    if (empty($widgets)) return $none;

    // One list across every owning widget on the page; the first control to
    // claim a name defines the field.
    $wanted = array();
    foreach ($widgets as $w) {
        foreach (_pg_cf_controls($w['tree'], $page_id) as $key => $field) {
            if (!isset($wanted[$key])) $wanted[$key] = $field;
        }
    }

    // Resolve row ids: a stamped id that is really this page's row wins; a
    // control with no id takes the row of the same name.
    $existing = pg_cf_load_page_fields($page_id);
    $by_id = array();
    $by_name = array();
    foreach ($existing as $f) {
        $by_id[(int)$f['id']] = $f;
        $by_name[mb_strtolower((string)$f['name'], 'UTF-8')] = (int)$f['id'];
    }
    $claimed = array();
    foreach ($wanted as $key => &$field) {
        $id = (int)$field['id'];
        if ($id > 0 && (!isset($by_id[$id]) || isset($claimed[$id]))) $id = 0;
        if ($id <= 0 && isset($by_name[$key]) && !isset($claimed[$by_name[$key]])) $id = $by_name[$key];
        $field['id'] = $id;
        if ($id > 0) $claimed[$id] = true;
    }
    unset($field);

    $form_name = trim((string)(isset($settings['form_name']) ? $settings['form_name'] : ''));
    if ($form_name === '') {
        $form_name = (string)db_value("SELECT form_name FROM custom_form_pages WHERE page_id = '$page_id' LIMIT 1");
    }
    if ($form_name === '') {
        $form_name = trim((string)db_value("SELECT page_title FROM page WHERE page_id = '$page_id' LIMIT 1"));
        if ($form_name === '') $form_name = (string)db_value("SELECT page_name FROM page WHERE page_id = '$page_id' LIMIT 1");
    }

    $res = pg_cf_sync_page_form($page_id, array_values($wanted), $form_name, (int)$user_id,
                                is_array($settings) ? $settings : array());
    $res['synced'] = true;

    // Hand the ids back to the controls, so the next save recognises them
    // by row and not by name.
    $ids_by_name = array();
    foreach (pg_cf_load_page_fields($page_id) as $f) {
        $ids_by_name[mb_strtolower((string)$f['name'], 'UTF-8')] = (int)$f['id'];
    }
    foreach ($widgets as $w) _pg_cf_stamp_ids($w, $page_id, $ids_by_name);

    return $res;
}
// Prepares custom shipping & billing form fields for custom layouts on express order, shipping
// address, and billing info pages.  It prefills data, sets attributes for fields, and returns
// info about the form.
function prepare_custom_form($properties)
{
    // liveform object so we can update fields
    $form = $properties['form'];
    $page_id = $properties['page_id'];
    $page_type = $properties['page_type'];
    // Form type is used by express order to specify whether custom shipping or billing fields
    // should be dealt with
    $form_type = $properties['form_type'];
    $ship_to_id = $properties['ship_to_id'];
    // Prefix is used on express order for custom shipping fields in order to make the field names
    // unique for each recipient, because fields might appear multiple times for multiple recipients
    // Only the express order screen passes a prefix.
    $prefix = isset($properties['prefix']) ? $properties['prefix'] : '';
    // Used to set the default value for fields that use the folder id for default value feature
    $folder_id = $properties['folder_id'];
    // Is edit mode on or off.
    $edit = $properties['edit'];
    $form_name = $properties['form_name'];
    $order_id = ($_SESSION['ecommerce']['order_id'] ?? '');
    // If the necessary ids were passed, then get values that visitor has submitted in the past,
    // that are stored in the db, and prefill.
    if (($form_type == 'shipping' and $ship_to_id) or ($form_type == 'billing' and $order_id)) {
        $form_type_filter = "";
        switch ($form_type) {
            case 'shipping':
                $form_type_filter = "form_data.ship_to_id = '" . e($ship_to_id) . "'";
                break;
            case 'billing':
                $form_type_filter = "(form_data.order_id = '" . e($order_id) . "')

                    AND (form_data.ship_to_id = '0')

                    AND (form_data.order_item_id = '0')";
                break;
        }
        // Get values from db that visitor has submitted
        $fields = db_items("SELECT

                form_data.form_field_id,

                form_data.data,

                count(*) as number_of_values,

                form_fields.type

            FROM form_data

            LEFT JOIN form_fields ON form_data.form_field_id = form_fields.id

            WHERE $form_type_filter

            GROUP BY form_data.form_field_id");
        // Loop through all field data in order to prefill fields.
        foreach ($fields as $field) {
            // If there is more than one value, get all values.
            if ($field['number_of_values'] > 1) {
                $field['data'] = db_values("SELECT data

                    FROM form_data

                    WHERE

                        $form_type_filter

                        AND (form_field_id = '" . $field['form_field_id'] . "')

                    ORDER BY id");
            }
            $html_name = $prefix . 'field_' . $field['form_field_id'];
            $form->set($html_name, prepare_form_data_for_output($field['data'], $field['type'], $prepare_for_html = false));
        }
    }
    $date_fields = array();
    $date_and_time_fields = array();
    $wysiwyg_fields = array();
    $form_type_filter = "";
    if ($form_type) {
        $form_type_filter = " AND (form_type = '" . e($form_type) . "')";
    }
    $fields = db_items("SELECT

            id,

            name,

            label,

            type,

            default_value,

            use_folder_name_for_default_value,

            multiple,

            required,

            size,

            maxlength,

            wysiwyg,

            `rows`, # Backticks for reserved word.

            cols,

            information

        FROM form_fields

        WHERE

            (page_id = '" . e($page_id) . "')

            $form_type_filter

        ORDER BY sort_order", 'id');
    foreach ($fields as $field) {
        $html_name = $prefix . 'field_' . $field['id'];
        // If this is a radio, check box group, or pick list,
        // then get options for the field, because we will need them below.
        if (($field['type'] == 'radio button') || ($field['type'] == 'check box') || ($field['type'] == 'pick list')) {
            $field['options'] = db_items("SELECT

                    label,

                    value,

                    default_selected

                FROM form_field_options

                WHERE form_field_id = '" . $field['id'] . "'

                ORDER BY sort_order");
        }
        // If the value for this field has not been set yet,
        // (e.g. the form has not been submitted by the customer),
        // then set default value.
        if (!$form->field_in_session($html_name)) {
            $value_from_query_string = trim(isset($_GET['value_' . $field['id']]) ? $_GET['value_' . $field['id']] : '');
            // If a default value was passed in the query string, then use that.
            if ($value_from_query_string != '') {
                $form->set($html_name, $value_from_query_string);
                // Otherwise, if field is set to use folder name for default value,
                // then use it.
            } else if ($field['use_folder_name_for_default_value']) {
                $default_value = db_value("SELECT folder_name

                    FROM folder

                    WHERE folder_id = '" . e($folder_id) . "'");
                $form->set($html_name, $default_value);
                // Otherwise if there is a default value, then use that.
            } else if ($field['default_value'] != '') {
                $form->set($html_name, $field['default_value']);
                // Otherwise if this is a check box group or pick list,
                // then use on/off values (default_selected) from choices.
            } else if (($field['type'] == 'check box') || ($field['type'] == 'pick list')) {
                $values = array();
                foreach ($field['options'] as $option) {
                    if ($option['default_selected']) {
                        $values[] = $option['value'];
                    }
                }
                // If there is at least one default-selected value, then continue to set it.
                if ($values) {
                    // If there is only one value, then set it.
                    if (count($values) == 1) {
                        $form->set($html_name, $values[0]);
                        // Otherwise, there is more than one value, so set all of them.
                    } else {
                        $form->set($html_name, $values);
                    }
                }
            }
        }
        switch ($field['type']) {
            case 'date':
                $date_fields[] = $html_name;
                break;
            case 'date and time':
                $date_and_time_fields[] = $html_name;
                break;
            case 'pick list':
                $field['pick_list_options'] = array();
                foreach ($field['options'] as $option) {
                    $field['pick_list_options'][$option['label']] = array(
                        'value' => $option['value'],
                        'default_selected' => $option['default_selected']
                    );
                }
                $form->set($html_name, 'options', $field['pick_list_options']);
                break;
            case 'text area':
                // If field is a rich-text editor, then remember that,
                // so we can prepare JS later.
                if ($field['wysiwyg']) {
                    $wysiwyg_fields[] = $html_name;
                }
                if ($field['rows']) {
                    $form->set($html_name, 'rows', $field['rows']);
                }
                if ($field['cols']) {
                    $form->set($html_name, 'cols', $field['cols']);
                }
                break;
        }
        if ($field['size'] and (($field['type'] == 'text box') or ($field['type'] == 'pick list') or ($field['type'] == 'file upload') or ($field['type'] == 'date') or ($field['type'] == 'date and time') or ($field['type'] == 'email address') or ($field['type'] == 'time'))) {
            $form->set($html_name, 'size', $field['size']);
        }
        if ($field['type'] == 'date') {
            $form->set($html_name, 'maxlength', 10);
        } else if ($field['type'] == 'date and time') {
            $form->set($html_name, 'maxlength', 22);
        } else if ($field['type'] == 'time') {
            $form->set($html_name, 'maxlength', 11);
        } else if ($field['maxlength'] and (($field['type'] == 'text box') or ($field['type'] == 'text area') or ($field['type'] == 'file upload') or ($field['type'] == 'email address'))) {
            $form->set($html_name, 'maxlength', $field['maxlength']);
        }
        // If this field is required, and it is not a check box,
        // or it is a check box and there is just one check box option,
        // then add required attribute.  We don't add the required attribute,
        // when there are multiple check box options, because it would require
        // that all of them be checked.
        if ($field['required'] and (($field['type'] != 'check box') or (count($field['options']) == 1))) {
            $form->set($html_name, 'required', true);
        }
    }
    $edit_start = '';
    $edit_end = '';
    // If edit mode is enabled, then prepare start and end HTML code to create edit grid around form
    if ($edit) {
        $url_form_type = '';
        // If this is an express order page, then determine if we should forward to shipping
        // or billing form.
        if ($page_type == 'express order') {
            $url_form_type = '&amp;form_type=';
            if ($form_type == 'shipping') {
                $url_form_type .= 'shipping';
            } else {
                $url_form_type .= 'billing';
            }
        }
        if ($form_type == 'shipping') {
            $title = lang('Custom Shipping Form');
        } else {
            $title = lang('Custom Billing Form');
        }
        // If there is a form name, then add to the end of the title
        if ($form_name) {
            $title .= ': ' . h($form_name);
        }
        $edit_start = '<div class="edit_mode" style="position: relative; outline: 1px dashed #4780C5; margin: -1px;"><a href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_fields.php?page_id=' . h($page_id) . $url_form_type . '&from=pages&send_to=' . h(urlencode(REQUEST_URL)) . '" style="background: #4780C5; position: absolute; display: block;  text-decoration: none; z-index: 10; line-height: normal; padding: 5px 8px; margin: -1px 25px 0 -1px; border: none; -webkit-border-bottom-right-radius: 6px; -moz-border-bottom-right-radius: 6px; border-bottom-right-radius: 6px;" title="' . $title . '">' . lang('Edit') . '</a>';
        $edit_end = '</div>';
    }
    return array(
        'fields' => $fields,
        'date_fields' => $date_fields,
        'date_and_time_fields' => $date_and_time_fields,
        'wysiwyg_fields' => $wysiwyg_fields,
        'edit_start' => $edit_start,
        'edit_end' => $edit_end
    );
}
// This function is used by custom shipping and billing forms to process the custom form that was
// submitted by the visitor.  This function is not used by custom form pages or product forms,
// because those areas require different code.
function submit_custom_form($properties)
{
    // liveform object so we can update fields
    $form = $properties['form'];
    $page_id = $properties['page_id'];
    $page_type = $properties['page_type'];
    $form_type = $properties['form_type'];
    $ship_to_id = $properties['ship_to_id'];
    // Prefix is used on express order for custom shipping fields in order to make the field names
    // unique for each recipient, because fields might appear multiple times for multiple recipients
    // Only the express order screen passes a prefix.
    $prefix = isset($properties['prefix']) ? $properties['prefix'] : '';
    // Require is used in order to determine if we should require fields or not
    if (isset($properties['require']) == true) {
        $require = $properties['require'];
    } else {
        $require = true;
    }
    $order_id = ($_SESSION['ecommerce']['order_id'] ?? '');
    $sql_ship_to_id_name = "";
    $sql_ship_to_id_value = "";
    // Delete existing form data and set SQL ship to id values differently,
    // based on the form type.
    switch ($form_type) {
        case 'shipping':
            db("DELETE FROM form_data

                WHERE

                    (ship_to_id = '" . e($ship_to_id) . "')

                    AND (ship_to_id != '0')");
            $sql_ship_to_id_name = "ship_to_id,";
            $sql_ship_to_id_value = "'" . e($ship_to_id) . "',";
            break;
        case 'billing':
            db("DELETE FROM form_data

                WHERE

                    (order_id = '" . e($order_id) . "')

                    AND (order_id != '0')

                    AND (ship_to_id = '0')

                    AND (order_item_id = '0')");
            break;
    }
    // Prepare sql filter in order to get correct fields
    $form_type_filter = "";
    // If the page type is express order then we need to add an extra filter for the form type
    if ($page_type == 'express order') {
        $form_type_filter .= " AND (form_type = '" . e($form_type) . "')";
    }
    // Get fields for this form.
    $fields = db_items("SELECT

            id,

            name,

            label,

            type,

            required,

            wysiwyg

        FROM form_fields

        WHERE

            (page_id = '" . e($page_id) . "')

            $form_type_filter

            AND (type != 'information')

        ORDER BY sort_order");
    // Loop through all form fields, so that we can save form data.
    foreach ($fields as $field) {
        $html_field_name = $prefix . 'field_' . $field['id'];
        // If validation for required fields should be completed and field is required, then validate field.
        if (($require == true) && ($field['required'] == 1)) {
            $error_message = '';
            // If there is a field label, then prepare error message.
            if ($field['label']) {
                $error_message = $field['label'] . ' is required.';
            }
            $form->validate_required_field($html_field_name, $error_message);
        }
        // Validate data differently depending on field type.
        switch ($field['type']) {
            case 'date':
                // If value is not blank and date is not valid, then mark error.
                if (($form->get_field_value($html_field_name) != '') && (validate_date($form->get_field_value($html_field_name)) == false)) {
                    $form->mark_error($html_field_name, lang(array('string' => 'Please enter a valid date for {var:1}', 'vars' => $field['label'])));
                }
                break;
            case 'date and time':
                // If value is not blank and date and time is not valid, then mark error.
                if (($form->get_field_value($html_field_name) != '') && (validate_date_and_time($form->get_field_value($html_field_name)) == false)) {
                    $form->mark_error($html_field_name, lang(array('string' => 'Please enter a valid date & time for {var:1}', 'vars' => $field['label'])));
                }
                break;
            case 'email address':
                // If value is not blank and e-mail address is not valid, then mark error.
                if (($form->get_field_value($html_field_name) != '') && (validate_email_address($form->get_field_value($html_field_name)) == false)) {
                    $form->mark_error($html_field_name, lang(array('string' => 'Please enter a valid e-mail address for {var:1}', 'vars' => $field['label'])));
                }
                break;
            case 'time':
                // If value is not blank and time is not valid, then mark error.
                if (($form->get_field_value($html_field_name) != '') && (validate_time($form->get_field_value($html_field_name)) == false)) {
                    $form->mark_error($html_field_name, lang(array('string' => 'Please enter a valid time for {var:1}', 'vars' => $field['label'])));
                }
                break;
        }
        // If the field does not have an error, then store data for field.
        if ($form->check_field_error($html_field_name) == false) {
            // Assume that the form data type is standard until we find out otherwise.
            $form_data_type = 'standard';
            // If the form field's type is date, date and time, or time, then set form data type to the form field type.
            if (($field['type'] == 'date') || ($field['type'] == 'date and time') || ($field['type'] == 'time')) {
                $form_data_type = $field['type'];
                // Otherwise if the form field is a wysiwyg text area, then set type to html and prepare content for input.
            } elseif (($field['type'] == 'text area') && ($field['wysiwyg'] == 1)) {
                $form_data_type = 'html';
                $form->assign_field_value($html_field_name, prepare_rich_text_editor_content_for_input(pg_sanitize_rich_text($form->get_field_value($html_field_name))));
            }
            // If this field has multiple values (i.e. check box group or pick list),
            // then loop through values in order to add them to database.
            if (is_array($form->get_field_value($html_field_name)) == true) {
                foreach ($form->get_field_value($html_field_name) as $value) {
                    db("INSERT INTO form_data (

                            order_id,

                            $sql_ship_to_id_name

                            form_field_id,

                            data,

                            name,

                            type)

                        VALUES (

                            '" . e($order_id) . "',

                            $sql_ship_to_id_value

                            '" . $field['id'] . "',

                            '" . e(prepare_form_data_for_input($value, $field['type'])) . "',

                            '" . e($field['name']) . "',

                            '$form_data_type')");
                }
                // Otherwise this field does not have multiple values,
                // so just insert one value in database.
            } else {
                db("INSERT INTO form_data (

                        order_id,

                        $sql_ship_to_id_name

                        form_field_id,

                        data,

                        name,

                        type)

                    VALUES (

                        '" . e($order_id) . "',

                        $sql_ship_to_id_value

                        '" . $field['id'] . "',

                        '" . e(prepare_form_data_for_input($form->get_field_value($html_field_name), $field['type'])) . "',

                        '" . e($field['name']) . "',

                        '$form_data_type')");
            }
        }
    }
}
