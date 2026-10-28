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
 *   url_param       a page-address parameter the field starts with
 *                   (?topic=…), read at render time, never stored in a row
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

// A control of a form widget tree with an RSS role (`_cf.rss_field`) on it,
// for code that builds form trees itself - the design templates. The role is
// what names a record: its page title and description, its feed item and its
// share picture. A role outside the ones pg_cf_rss_fields() offers leaves the
// control as it is.
function pg_cf_with_role($node, $role)
{
    $role = (string)$role;
    if (!is_array($node) || !in_array($role, array('category', 'title', 'description', 'media'), true)) return $node;
    if (!isset($node['props']) || !is_array($node['props'])) $node['props'] = array();
    if (!isset($node['props']['_cf']) || !is_array($node['props']['_cf'])) $node['props']['_cf'] = array();
    $node['props']['_cf']['rss_field'] = $role;
    return $node;
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

/**
 * Whether a signed-in person's form submission is about somebody else: the
 * e-mail address the form writes to the contact (a field wired to the
 * contact's email_address) is none of the addresses that person has. Only
 * then can the two be told apart - a form with no such field, or a person
 * with no address on file, is about the person submitting it.
 *
 * @param string $typed the value of the form's contact e-mail field
 * @param array  $known the signed-in person's addresses (account, contact)
 * @return bool
 */
function pg_cf_email_is_someone_else($typed, $known)
{
    $typed = mb_strtolower(trim((string)$typed), 'UTF-8');
    if ($typed === '') return false;

    $mine = array();
    foreach ((array)$known as $address) {
        $address = mb_strtolower(trim((string)$address), 'UTF-8');
        if ($address !== '') $mine[] = $address;
    }

    return !empty($mine) && !in_array($typed, $mine, true);
}

/**
 * custom_form.php: whether the contact this submission fills in is not the
 * signed-in person's own (pg_cf_email_is_someone_else()). False for a
 * visitor who is not signed in, and for a submission that writes nothing to
 * a contact.
 *
 * @param string     $typed       the form's contact e-mail value
 * @param int|string $contact_id  the signed-in person's contact, or ''
 * @param string     $sql_update  the contact columns the form writes
 * @return bool
 */
function pg_cf_contact_is_someone_else($typed, $contact_id, $sql_update)
{
    if (!defined('USER_LOGGED_IN') || !USER_LOGGED_IN || (string)$sql_update === '') return false;

    $known = array(defined('USER_EMAIL_ADDRESS') ? USER_EMAIL_ADDRESS : '');
    if ((int)$contact_id > 0) {
        $known[] = (string)db_value("SELECT email_address FROM contacts WHERE id = '" . (int)$contact_id . "'");
    }

    return pg_cf_email_is_someone_else($typed, $known);
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
        if (!pg_schema_has($t)) return $ready;
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
    $kind_by_field = array();
    if (pg_coll_ready()) {
        foreach ((array)db_items(
            "SELECT id, collection_kind, reference_page_id FROM form_fields
             WHERE page_id = '$page_id' AND form_type = 'custom'") as $k) {
            $kind_by_field[(int)$k['id']] = array((string)$k['collection_kind'], (int)$k['reference_page_id']);
        }
    }
    $translate_by_field = array();
    if (pg_schema_has('form_fields', 'translate_values')) {
        foreach ((array)db_items(
            "SELECT id, translate_values FROM form_fields
             WHERE page_id = '$page_id' AND form_type = 'custom'") as $t) {
            $translate_by_field[(int)$t['id']] = ((int)$t['translate_values'] === 1);
        }
    }
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
            'translate'       => isset($translate_by_field[$id]) ? $translate_by_field[$id] : true,
            'kind'            => isset($kind_by_field[$id]) ? $kind_by_field[$id][0] : '',
            'reference_page_id' => isset($kind_by_field[$id]) ? $kind_by_field[$id][1] : 0,
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
        'notify_email' => '', 'notify_subject' => '', 'notify_page_id' => 0,
        'confirm_email' => 0, 'confirm_subject' => '', 'confirm_page_id' => 0,
        'confirmation_message' => '', 'confirmation_page_id' => 0, 'contact_group_id' => 0,
        'membership' => 0, 'membership_days' => 0, 'auto_registration' => 0,
        'pretty_urls' => 0, 'double_opt_in' => 0, 'collection' => 0,
        'schema_type' => 'BlogPosting', 'schema_author' => 'contact',
    );
    if ($page_id <= 0 || !pg_cf_page_form_ready()) return $defaults;
    // The 11.0.27 column is read once it is there.
    $double_opt_in_ready = pg_fs_ready();

    $row = db_item(
        "SELECT form_name, enabled, quiz, save, auto_registration,
                administrator_email, administrator_email_to_email_address, administrator_email_subject,
                administrator_email_format, administrator_email_page_id,
                submitter_email, submitter_email_subject, submitter_email_format, submitter_email_page_id,
                confirmation_type, confirmation_message, confirmation_page_id, contact_group_id,
                membership, membership_days, pretty_urls" . ($double_opt_in_ready ? ', double_opt_in' : '')
                . (pg_coll_ready() ? ', collection' : '')
                . (pg_record_schema_type_setting_ready() ? ', schema_type' : '')
                . (pg_blogposting_author_setting_ready() ? ', schema_author' : '') . "
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
        // A page sent as the e-mail (HTML); zero is the plain field list.
        'notify_page_id'       => ((string)$row['administrator_email_format'] === 'html')
                                      ? (int)$row['administrator_email_page_id'] : 0,
        'confirm_email'        => (int)$row['submitter_email'] === 1 ? 1 : 0,
        'confirm_subject'      => (string)$row['submitter_email_subject'],
        'confirm_page_id'      => ((string)$row['submitter_email_format'] === 'html')
                                      ? (int)$row['submitter_email_page_id'] : 0,
        'confirmation_message' => (string)$row['confirmation_message'],
        // "Next page": where the visitor lands after submitting. Zero means
        // the message above is shown instead.
        'confirmation_page_id' => ((string)$row['confirmation_type'] === 'page')
                                      ? (int)$row['confirmation_page_id'] : 0,
        'contact_group_id'     => (int)$row['contact_group_id'],
        'membership'           => (int)$row['membership'] === 1 ? 1 : 0,
        'membership_days'      => (int)$row['membership_days'],
        'auto_registration'    => (int)$row['auto_registration'] === 1 ? 1 : 0,
        // Records addressed by their name (/<page>/<title>) rather than by
        // their reference code; the name comes from the Title field.
        'pretty_urls'          => (int)$row['pretty_urls'] === 1 ? 1 : 0,
        // The contact joins the group only through the link mailed to it.
        'double_opt_in'        => ($double_opt_in_ready && (int)$row['double_opt_in'] === 1) ? 1 : 0,
        // A collection: its records are items other forms' records may
        // point at (includes/fn/collections.php).
        'collection'           => (isset($row['collection']) && (int)$row['collection'] === 1) ? 1 : 0,
        // How the structured data of the record pages names them
        // (includes/fn/seo.php); BlogPosting before the 11.0.55 column.
        'schema_type'          => pg_record_schema_type($row['schema_type'] ?? ''),
        // Who the record pages' structured data names as the author, with a
        // field by its name: the editor knows its controls by name, and a
        // field made in this very save has no id yet. The save turns the
        // name back into the id (pg_cf_template_schema_author()).
        'schema_author'        => pg_cf_editor_schema_author($row['schema_author'] ?? '', function ($field_id) use ($page_id) {
            return (string)db_value("SELECT name FROM form_fields
                                     WHERE id = '" . (int)$field_id . "' AND page_id = '$page_id' AND form_type = 'custom' LIMIT 1");
        }),
    );
}

/**
 * custom_form_pages.schema_author as the designer's form settings hold it:
 * 'contact', 'organization' or 'field:<field name>'. A field the form no
 * longer has is the contact, as the record pages read it
 * (pg_blogposting_author_choice()).
 *
 * Pure apart from $name_of.
 *
 * @param string   $stored   custom_form_pages.schema_author
 * @param callable $name_of  function ($field_id): the form field's name, '' when there is none
 */
function pg_cf_editor_schema_author($stored, $name_of)
{
    $choice = pg_blogposting_author_choice($stored);
    if ($choice['source'] !== 'field') return $choice['source'];
    $name = (string)$name_of($choice['field_id']);
    return ($name !== '') ? 'field:' . $name : 'contact';
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
    $r = db_q("SHOW COLUMNS FROM form_fields WHERE Field = ?", array((string)$column));
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
 * The plain text body a submission e-mail goes out with.
 *
 * The mailer refuses an empty message, so an empty body sat in the mail
 * queue until its retries ran out and nobody got the e-mail. A body left
 * empty is replaced by the form's fields, the way the visual editor writes
 * it; a form without a named field still names the submission. Labels come
 * from the legacy field screen too, where they may be empty or end in a
 * colon. $fields: rows with `name`, `label` and `office_use_only`. The
 * submitter's copy ($for_submitter) leaves out the fields marked "Hide This
 * Field from Submitter": the visitor never saw them, and the site fills
 * their values in for the office.
 */
function pg_cf_email_body_fallback($body, $fields, $for_submitter = false)
{
    if (trim((string)$body) !== '') return (string)$body;

    $rows = array();
    foreach ((array)$fields as $f) {
        $name = isset($f['name']) ? (string)$f['name'] : '';
        if ($name === '') continue;
        if ($for_submitter && !empty($f['office_use_only'])) continue;
        $label = rtrim(trim(strip_tags(isset($f['label']) ? (string)$f['label'] : '')), ': ');
        $rows[] = array('name' => $name, 'label' => ($label !== '') ? $label : $name);
    }

    $default = pg_cf_default_email_body($rows);
    if ($default !== '') return $default;

    return lang('Reference Code') . ': ^^reference_code^^' . "\n"
        . lang('Date Submitted') . ': ^^submitted_date_and_time^^';
}

/**
 * pg_cf_email_body_fallback() for a form page; the fields are read only when
 * the body is empty. $for_submitter: the e-mail goes to the submitter.
 */
function pg_cf_email_body_or_default($page_id, $body, $for_submitter = false)
{
    if (trim((string)$body) !== '') return (string)$body;

    $page_id = (int)$page_id;
    $fields  = array();
    if ($page_id > 0) {
        $fields = db_items(
            "SELECT name, label, office_use_only FROM form_fields
             WHERE page_id = '$page_id' AND form_type = 'custom'
                 AND name != '' AND type != 'information'
             ORDER BY sort_order ASC, id ASC");
    }
    return pg_cf_email_body_fallback($body, is_array($fields) ? $fields : array(), $for_submitter);
}

/**
 * Whether a submission ends on the form's confirmation message rather than
 * on its next page.
 *
 * A next page that was never chosen, has been deleted since or is in the
 * Recycle Bin used to send the visitor to the home page, or to a binned page
 * in a private folder, without a word; the message is shown instead.
 * Deleting a page in the panel keeps its row and moves it into the bin
 * (pg_recycle_page()), so the row alone does not prove the page is on the
 * site. The page is looked up only when a next page is chosen.
 */
function pg_cf_confirmation_shows_message($type, $page_id)
{
    if ((string)$type !== 'page') return true;
    $page_id = (int)$page_id;
    if ($page_id <= 0) return true;
    if (!db_value("SELECT COUNT(*) FROM page WHERE page_id = '$page_id'")) return true;
    return function_exists('pg_recycle_page_binned') && pg_recycle_page_binned($page_id);
}

/**
 * The confirmation message as the visitor sees it: an empty one would leave
 * the page blank where the form was, so it says thank you instead.
 */
function pg_cf_confirmation_message_or_default($message)
{
    $message = (string)$message;
    $text = trim(str_replace("\xC2\xA0", ' ', html_entity_decode(strip_tags($message, '<img><iframe><video>'), ENT_QUOTES, 'UTF-8')));
    return ($text !== '') ? $message : lang('Thank you. Your message has been received.');
}

/**
 * What the legacy page screen refuses to save for a custom form: an e-mail
 * that is switched on with nothing to send (plain text without a body, HTML
 * without a page) and a "Next Page" confirmation without a page. Only the
 * 'page' confirmation type needs a page: an empty or missing type is the
 * message, as on submit (pg_cf_confirmation_shows_message()).
 *
 * $settings: the 'custom form' properties of
 * pg_page_form_type_properties_from_post(). $page_exists: callable that
 * answers whether a page id names a page on the site, neither deleted nor in
 * the Recycle Bin. Returns the screen's
 * field id => message, in the order of the screen.
 */
function pg_cf_legacy_settings_errors($settings, $page_exists)
{
    $errors = array();
    $value = function ($key) use ($settings) {
        return isset($settings[$key]) ? (string)$settings[$key] : '';
    };

    foreach (array('submitter' => lang('E-mail Submitter'), 'administrator' => lang('E-mail Administrator')) as $who => $label) {
        if ($value($who . '_email') !== '1') continue;

        // custom_form.php sends plain text only for 'plain_text'; anything else is the page.
        if ($value($who . '_email_format') === 'plain_text') {
            if (trim($value($who . '_email_body')) === '') {
                $errors['custom_form_' . $who . '_email_body'] = lang(array(
                    'string' => '{var:1} is on with the Plain Text format, but its Body is empty. Write the body of the e-mail, or choose HTML and select a page.',
                    'vars' => array($label)));
            }
        } elseif (!call_user_func($page_exists, (int)$value($who . '_email_page_id'))) {
            $errors['custom_form_' . $who . '_email_page_id'] = lang(array(
                'string' => '{var:1} is on with the HTML format, but no page is selected. Select the page to send, or choose Plain Text and write a body.',
                'vars' => array($label)));
        }
    }

    if ($value('confirmation_type') === 'page' && !call_user_func($page_exists, (int)$value('confirmation_page_id'))) {
        $errors['custom_form_confirmation_page_id'] = lang('The Confirmation Type is Next Page, but no page is selected. Select the page the visitor goes to after submitting, or choose Message.');
    }

    return $errors;
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
    // A page to send as the e-mail instead of the plain list of fields: the
    // page is drawn for the submission (its Form Item View widget shows the
    // record just sent). Zero, or a page that no longer exists, goes back to
    // the plain text body.
    foreach (array('notify_page_id' => 'administrator', 'confirm_page_id' => 'submitter') as $key => $who) {
        if (!array_key_exists($key, $settings)) continue;
        $mail_page = (int)$settings[$key];
        if ($mail_page > 0 && !db_value("SELECT COUNT(*) FROM page WHERE page_id = '$mail_page'")) $mail_page = 0;
        $set[$who . '_email_page_id'] = $mail_page;
        $set[$who . '_email_format']  = ($mail_page > 0) ? 'html' : 'plain_text';
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
    // The records' names are written by pg_cf_sync_page_form() once the
    // fields (the Title field among them) are in place.
    if (array_key_exists('pretty_urls', $settings)) {
        $set['pretty_urls'] = !empty($settings['pretty_urls']) ? '1' : '0';
    }
    if (array_key_exists('collection', $settings) && pg_coll_ready()) {
        $set['collection'] = !empty($settings['collection']) ? '1' : '0';
    }
    if (array_key_exists('schema_type', $settings) && pg_record_schema_type_setting_ready()) {
        $set['schema_type'] = pg_record_schema_type($settings['schema_type']);
    }
    if (array_key_exists('double_opt_in', $settings) && pg_fs_ready()) {
        $set['double_opt_in'] = !empty($settings['double_opt_in']) ? '1' : '0';
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
 * The `wysiwyg` (rich-text editor) column a designed field is written with.
 *
 * Only a text area can be rich. $wanted is what the control's `_cf.wysiwyg`
 * says: true or false once the designer's switch has been used, and the
 * control then decides. null means the control does not say - a tree drawn
 * before the switch existed, or a flag set on the classic field screen - and
 * the row keeps the flag it has ($stored, the existing form_fields row with
 * its `type` and `wysiwyg`), so a designer save no longer switches it off.
 */
function pg_cf_rich_text_flag($wanted, $type, $stored = null)
{
    if ((string)$type !== 'text area') return 0;
    if ($wanted === null) {
        return (is_array($stored) && isset($stored['type']) && (string)$stored['type'] === 'text area'
                && !empty($stored['wysiwyg'])) ? 1 : 0;
    }
    return $wanted ? 1 : 0;
}

/**
 * The `translate_values` column a designed field is written with: 1 when the
 * answers given in the field are listed for translation and translated where
 * a widget shows them (pg_tr_extract_form_records(), pg_tr_record_fields()),
 * 0 when they are left as they were typed (icon names, codes, JSON).
 *
 * Only a text box or a text area has its answers translated, so any other
 * field is 1. $wanted is the control's `_cf.translate`: true or false once
 * the designer's switch has been used. null means the control does not say
 * (a tree drawn before the switch existed, a field set on the classic field
 * screen): the stored row ($stored, with `translate_values` when the column
 * was read) keeps its flag, and a new field is included.
 */
function pg_cf_translate_flag($wanted, $type, $stored = null)
{
    if (!in_array((string)$type, array('text box', 'text area'), true)) return 1;
    if ($wanted === null) {
        return (is_array($stored) && isset($stored['translate_values'])
                && (string)$stored['translate_values'] === '0') ? 0 : 1;
    }
    return $wanted ? 1 : 0;
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
    $has_translate  = pg_schema_has('form_fields', 'translate_values');
    $has_coll       = pg_coll_ready();
    $user_id        = (int)$user_id;

    // What is on the page's form right now, so we can tell an update from an
    // insert and find what the operator removed.
    $existing = array();
    $old_list = array();
    $old_title_id = 0;
    foreach ((array)db_items(
        "SELECT id, name, label, type, wysiwyg, rss_field"
         . ($has_translate ? ", translate_values" : "") . " FROM form_fields
         WHERE page_id = '$page_id' AND form_type = 'custom'
         ORDER BY sort_order ASC, id ASC") as $row) {
        $existing[(int)$row['id']] = $row;
        $old_list[] = array('name' => $row['name'], 'label' => $row['label']);
        if ($old_title_id === 0 && (string)$row['rss_field'] === 'title') $old_title_id = (int)$row['id'];
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
            'wysiwyg'                           => pg_cf_rich_text_flag(
                                                       array_key_exists('wysiwyg', $f) ? $f['wysiwyg'] : false, $type,
                                                       ($id > 0 && isset($existing[$id])) ? $existing[$id] : null),
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
        if ($has_translate) {
            // Three-state like `wysiwyg`: a text field whose control does not
            // say keeps the row's flag, so a save never resets it. A field
            // saved as another type is written as 1 and comes back included.
            $set['translate_values'] = pg_cf_translate_flag(
                array_key_exists('translate', $f) ? $f['translate'] : null, $type,
                ($id > 0 && isset($existing[$id])) ? $existing[$id] : null);
        }

        // A collection field's kind, kept only where the field's type can
        // have it, and the form a reference points into, kept only while that
        // page holds a form (includes/fn/collections.php).
        if ($has_coll) {
            $kind = pg_coll_field_kind(isset($f['kind']) ? $f['kind'] : '', $type);
            $set['collection_kind']   = $kind;
            $set['reference_page_id'] = pg_coll_reference_target(
                pg_coll_field_reference($kind, isset($f['reference_page_id']) ? $f['reference_page_id'] : 0));
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
    // An information or signature row has no control a designed form could
    // carry: a classic layout drawn beside the widget still prints it, and a
    // signature's evidence is never deleted from here.
    foreach ($existing as $id => $row) {
        if (isset($kept[$id])) continue;
        if (in_array((string)$row['type'], array('information', 'signature'), true)) continue;
        $result['deleted']++;
        $result['lost_values'] += (int)db_value(
            "SELECT COUNT(*) FROM form_data WHERE form_field_id = '" . (int)$id . "'");
        db("DELETE FROM form_field_options WHERE form_field_id = '" . (int)$id . "'");
        db("DELETE FROM target_options WHERE trigger_form_field_id = '" . (int)$id . "'");
        db("DELETE FROM form_data WHERE form_field_id = '" . (int)$id . "'");
        db("DELETE FROM form_fields WHERE id = '" . (int)$id . "'");
    }

    // The pretty URL state before the settings are written: turning it on,
    // or moving the Title role to another field, renames the records.
    $was_pretty   = check_if_pretty_urls_are_enabled($page_id) == true;
    $title_before = $old_title_id;

    pg_cf_write_form_settings($page_id, $settings, $old_list, $written_list);

    $result['addresses'] = pg_cf_refresh_address_names($page_id, $was_pretty, $title_before);

    return $result;
}

/**
 * Give a form's records the names its pretty URLs need.
 *
 * The name comes from the record's Title field (update_submitted_form_address_name()),
 * and custom_form.php writes it on every new submission while the setting is
 * on. What was there before has none: turning the setting on, or moving the
 * Title role to another field, renames every record, as the classic page and
 * field screens do (edit_page.php, edit_field.php). While the setting stays
 * on, a record that still has no name (written while it was off, or through
 * the API) gets one. Returns how many records were given a name.
 */
function pg_cf_refresh_address_names($page_id, $was_pretty, $title_before)
{
    $page_id = (int)$page_id;
    if ($page_id <= 0 || !pg_cf_page_form_ready()) return 0;
    if (check_if_pretty_urls_are_enabled($page_id) != true) return 0;
    if (function_exists('pg_sw_form_pretty_urls')) pg_sw_form_pretty_urls(0, true);

    $title_now = (int)db_value(
        "SELECT id FROM form_fields
         WHERE page_id = '$page_id' AND rss_field = 'title'
         ORDER BY sort_order ASC LIMIT 1");
    if ($title_now <= 0) return 0;

    // Every record when the names may all be stale; otherwise only the
    // unnamed ones that have a title to be named after, so a save does not
    // walk the records that never will.
    if (!$was_pretty || (int)$title_before !== $title_now) {
        $records = db_items(
            "SELECT id FROM forms
             WHERE page_id = '$page_id'
             ORDER BY submitted_timestamp ASC, id ASC");
    } else {
        $records = db_items(
            "SELECT DISTINCT forms.id, forms.submitted_timestamp
             FROM forms
             INNER JOIN form_data ON form_data.form_id = forms.id AND form_data.data != ''
             INNER JOIN form_fields ON form_fields.id = form_data.form_field_id AND form_fields.rss_field = 'title'
             WHERE forms.page_id = '$page_id' AND forms.address_name = ''
             ORDER BY forms.submitted_timestamp ASC, forms.id ASC");
    }
    $named = 0;
    foreach ((array)$records as $record) {
        if (update_submitted_form_address_name($record['id']) !== '') $named++;
    }
    return $named;
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
                ? ", validation_regex, validation_message" : ", '' AS validation_regex, '' AS validation_message")
         . (pg_coll_ready() ? ", collection_kind, reference_page_id" : ", '' AS collection_kind, 0 AS reference_page_id") . "
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
    if (pg_cf_connect_to_contact_off()) return $contact;
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
 * ?connect_to_contact=false - the legacy switch for filling a form in on
 * someone else's behalf. Nothing is pre-filled from the signed-in visitor's
 * contact, and the form carries the flag to custom_form.php so nothing is
 * written back to it either.
 */
function pg_cf_connect_to_contact_off()
{
    // Only a string can be the switch; connect_to_contact[]= counts as not passed.
    return isset($_GET['connect_to_contact'])
        && is_string($_GET['connect_to_contact'])
        && trim(mb_strtolower($_GET['connect_to_contact'], 'UTF-8')) === 'false';
}

/**
 * The value of page-address parameter $name a designed form control starts
 * with (props._cf.url_param, ?topic=…). The list-filter rule of
 * pg_sw_url_param_value() with a longer cap: a string only (?topic[]=x is no
 * value), trimmed, valid UTF-8 without control or format characters (C0 and
 * C1 controls, zero-width characters, the byte order mark, bidi controls) or
 * line and paragraph separators, cut to $max characters.
 *
 * $multiline (a text area) keeps line breaks and tabs; \r\n and \r become \n.
 * A value holding '^^' is refused: the renderer replaces ^^__…^^ tokens over
 * the drawn form and pg_sw_sweep_tokens() would cut the text between two
 * pairs. '' when there is no usable value.
 *
 * @param array  $request   the query parameters (pg_sw_filter_request())
 * @param string $name      a parameter name pg_sw_url_param_name() accepted
 * @param bool   $multiline whether line breaks and tabs are kept
 * @param int    $max       the longest value kept, in characters
 * @return string
 */
function pg_cf_url_prefill_value($request, $name, $multiline = false, $max = 1000)
{
    if (!is_string($name) || $name === '' || !is_array($request)
        || !isset($request[$name]) || !is_string($request[$name])) return '';

    $value = trim($request[$name]);
    // The encoding is checked before any /u pattern runs: preg_match() gives
    // up on invalid UTF-8 and would let the value through unchecked.
    if ($value === '' || !mb_check_encoding($value, 'UTF-8')) return '';

    if ($multiline) {
        $value = str_replace(array("\r\n", "\r"), "\n", $value);
        $refused = '/(?![\t\n])\p{Cc}|[\p{Cf}\p{Zl}\p{Zp}]/u';
    } else {
        $refused = '/[\p{Cc}\p{Cf}\p{Zl}\p{Zp}]/u';
    }
    if (preg_match($refused, $value) || strpos($value, '^^') !== false) return '';

    return mb_substr($value, 0, max(1, (int)$max), 'UTF-8');
}

/**
 * The option $value names, in the option's own spelling: an exact match
 * first, then one that differs only in upper and lower case (UTF-8). '' when
 * nothing matches. An empty option value never matches, so a "Choose…"
 * prompt is never picked.
 *
 * @param string $value
 * @param array  $option_values the option values the control draws
 * @return string
 */
function pg_cf_match_option($value, array $option_values)
{
    $value = is_scalar($value) ? (string)$value : '';
    if ($value === '') return '';

    foreach ($option_values as $option) {
        if (is_scalar($option) && (string)$option !== '' && (string)$option === $value) return (string)$option;
    }
    $lower = mb_strtolower($value, 'UTF-8');
    foreach ($option_values as $option) {
        if (!is_scalar($option) || (string)$option === '') continue;
        if (mb_strtolower((string)$option, 'UTF-8') === $lower) return (string)$option;
    }
    return '';
}

/**
 * The starting values a designed form takes from the page address, keyed by
 * the lower-cased field names that key $map.
 *
 * A field takes one only when its lead control (the first control with its
 * name, _pg_cf_controls()) names a parameter in `_cf.url_param` that
 * pg_sw_url_param_name() accepts, its row exists, it is not hidden from the
 * visitor (office_use_only on the control or the row: a crafted link must
 * not preset a staff-only field staff might then send unnoticed), it is not
 * a file upload, and it is not a lone check box (fewer than two distinct
 * values: a consent box is never ticked from a link).
 *
 * A radio or check-box group keeps only a value one of its boxes carries
 * (pg_cf_match_option()). A pick list is matched later, in
 * pg_cf_apply_field_bindings(), against the options it actually draws.
 *
 * @param array $controls _pg_cf_controls() of the widget tree, names as drawn
 * @param array $map      pg_cf_field_map() of the form's page
 * @param array $request  the query parameters (pg_sw_filter_request())
 * @return array field key => value
 */
function pg_cf_url_prefill_values($controls, $map, $request)
{
    $out = array();
    if (!is_array($controls) || !is_array($map) || !is_array($request) || empty($request)) return $out;

    foreach ($controls as $key => $control) {
        if (!is_array($control) || !isset($map[$key]) || !is_array($map[$key])) continue;
        $name = pg_sw_url_param_name(isset($control['url_param']) ? $control['url_param'] : '');
        if ($name === '') continue;

        $row = $map[$key];
        if (!empty($control['office_use_only']) || !empty($row['office_use_only'])) continue;

        // The row's type is what custom_form.php validates; the control's is
        // what the page draws until the next save. Both must allow it.
        $types = array(
            isset($row['type']) ? (string)$row['type'] : '',
            isset($control['type']) ? (string)$control['type'] : '',
        );
        if (in_array('file upload', $types, true)) continue;

        // The values the boxes draw, each once: a group that repeats one
        // value is still a single yes/no box.
        $options = array();
        $drawn   = (isset($control['options']) && is_array($control['options'])) ? $control['options'] : array();
        foreach ($drawn as $option) {
            if (!is_array($option) || !isset($option['value'])) continue;
            $option_value = (string)$option['value'];
            if ($option_value !== '' && !in_array($option_value, $options, true)) $options[] = $option_value;
        }
        if (in_array('check box', $types, true) && count($options) < 2) continue;

        $max = 1000;
        $maxlength = isset($row['maxlength']) ? (int)$row['maxlength'] : 0;
        if ($maxlength > 0 && $maxlength < $max) $max = $maxlength;

        $multiline = ($types[0] === 'text area' && $types[1] === 'text area');
        $value = pg_cf_url_prefill_value($request, $name, $multiline, $max);
        if ($value === '') continue;

        if (in_array('radio button', $types, true) || in_array('check box', $types, true)) {
            $value = pg_cf_match_option($value, $options);
            if ($value === '') continue;
        }
        $out[$key] = $value;
    }
    return $out;
}

/**
 * What a bound control should start out showing. The legacy form screen's
 * order, with a designed form's named address parameter after ?value_<id>
 * (a source the legacy screen does not have). The order matters and is not
 * arbitrary:
 *
 *   1. what the visitor typed before a failed submission - never lose that
 *   2. ?value_<id> in the URL - a link that pre-fills the form on purpose
 *   3. $url_value, the control's `_cf.url_param` read from the page address
 *      (pg_cf_url_prefill_values()) - a link that names the field instead
 *      of its row id
 *   4. the visitor's own contact record - the reason contact_field exists
 *   5. the folder name, when the field is set to use it
 *   6. the field's default value
 */
function pg_cf_field_initial_value($field, $lf, $folder_id = 0, $url_value = '')
{
    $id = (int)$field['id'];

    if ($lf) {
        $stored = $lf->get_field_value($id);
        if (is_array($stored) || ($stored !== '' && $stored !== null)) return $stored;
    }
    // An array (value_<id>[]=) is not a value for one control, so it counts as not passed.
    if (isset($_GET['value_' . $id]) && is_string($_GET['value_' . $id]) && trim($_GET['value_' . $id]) !== '') {
        return trim($_GET['value_' . $id]);
    }
    if (is_string($url_value) && $url_value !== '') return $url_value;
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
 *
 * $url_values are the starting values the controls' `_cf.url_param` read from
 * the page address (pg_cf_url_prefill_values(), field key => value). They
 * come after ?value_<id> (pg_cf_field_initial_value()). A pick list keeps
 * one only when an option it draws carries it; otherwise the next source
 * applies.
 *
 * A text area whose row is rich text (`wysiwyg`) gets `data-pg-rich-text`,
 * and the page brings the editor for it (pg_cf_rich_text_inject()). Its
 * `required` moves to `aria-required`, because the editor hides the control
 * the browser would check; the server still refuses an empty answer. Its
 * starting value goes through pg_sanitize_rich_text(), because the editor
 * reads that text as HTML and a pre-fill link can put anything there.
 */
function pg_cf_apply_field_bindings(&$tree, $map, $lf = null, $cf_folder_id = 0, $staff = false, $url_values = array())
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

            // A rich-text text area is marked for the page, which loads one
            // editor for every marked control once the page is drawn in
            // (pg_cf_rich_text_inject()). The editor hides the text area and
            // copies its text back only on submit (TinyMCE) or removes
            // `required` itself (CKEditor), so a browser check on the hidden
            // control would block every submit: `required` becomes
            // aria-required and the server's required check stands.
            $cf_rich = ($tag_lc === 'textarea') && !empty($row['wysiwyg'])
                    && isset($row['type']) && (string)$row['type'] === 'text area';
            if ($cf_rich) {
                $set_attr($tree['props']['_attrs'], 'data-pg-rich-text', '');
                if (array_key_exists('required', $attrs)) {
                    $set_attr($tree['props']['_attrs'], 'required', null);
                    $set_attr($tree['props']['_attrs'], 'aria-required', 'true');
                }
            }

            // `required` on every box of a checkbox GROUP would make the
            // browser demand all of them ticked. The server checks that one
            // is; the browser is told nothing. A lone consent box keeps it.
            if ($input_type === 'checkbox' && array_key_exists('required', $attrs)) {
                static $cf_option_count = array();
                if (!isset($cf_option_count[$field_id])) {
                    $cf_ref_boxes = pg_cf_reference_choices_for($row);
                    $cf_option_count[$field_id] = ($cf_ref_boxes !== null) ? count($cf_ref_boxes) : (int)db_value(
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

            // A field's kind draws its control: a number field takes decimal
            // numbers, an image field offers images; a reference pick list
            // lists the records of the form it points into (a product or
            // page pick list the products or pages), below the
            // designer's empty-valued prompt row (one whose form is gone
            // keeps its drawn choices). The server checks them again
            // (pg_coll_check_field()). A reference radio button or check box
            // group was drawn from the records one level up
            // (pg_cf_reference_choice_nodes()).
            $cf_kind = pg_coll_field_kind(isset($row['collection_kind']) ? $row['collection_kind'] : '', (string)$row['type']);
            if ($tag_lc === 'input') {
                foreach (pg_coll_input_attrs($cf_kind, (string)$row['type']) as $attr => $value) {
                    $set_attr($tree['props']['_attrs'], $attr, $value);
                }
            }
            $cf_ref_options = ($tag_lc === 'select') ? pg_cf_reference_choices_for($row) : null;

            // A <select> shows the FIELD's choices. They were written from
            // this very markup on save, so normally the two are identical -
            // but a list edited on the legacy field screen has to reach the
            // visitor without anybody re-drawing the control. The designer's
            // empty-valued prompt row is kept.
            if ($tag_lc === 'select') {
                if ($cf_ref_options !== null) {
                    $opts = array();
                    foreach ($cf_ref_options as $code => $label) {
                        $opts[] = array('label' => $label, 'value' => $code, 'default_selected' => 0);
                    }
                } else {
                    $opts = db_items(
                        "SELECT label, value, default_selected FROM form_field_options
                         WHERE form_field_id = '$field_id' ORDER BY sort_order ASC, id ASC");
                }
                if ((is_array($opts) && !empty($opts)) || $cf_ref_options !== null) {
                    $kept = array();
                    foreach ((array)(isset($tree['children']) ? $tree['children'] : array()) as $child) {
                        if (!is_array($child) || !isset($child['props']['tag'])) continue;
                        if (strtolower((string)$child['props']['tag']) !== 'option') continue;
                        $ca = _pg_cf_node_attrs($child);
                        if (isset($ca['value']) && $ca['value'] === '') { $kept[] = $child; break; }
                    }
                    foreach ((array)$opts as $o) {
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

            // The value the control's named address parameter carries. A
            // pick list takes it only when one of the options drawn above
            // carries it (in that option's spelling); otherwise it falls
            // through to the contact record, the folder or the default.
            $url_value = (is_array($url_values) && isset($url_values[$key]) && is_string($url_values[$key]))
                ? $url_values[$key] : '';
            if ($tag_lc === 'select' && $url_value !== '') {
                $url_value = pg_cf_match_option($url_value, pg_cf_select_option_values($tree));
            }

            // Starting value: what the visitor typed before a failed
            // submission, a pre-fill link (?value_<id>, then the named
            // address parameter), their own contact record, the folder name,
            // the field default - in that order.
            $stored = pg_cf_field_initial_value($row, $lf, $cf_folder_id, $url_value);
            if ($tag_lc === 'textarea') {
                // The editor parses a rich text area's text as HTML, and the
                // text can come from the address (?value_<id> or the named
                // parameter): it is filtered the way the stored answer will be.
                if (!is_array($stored) && $stored !== '') {
                    $tree['props']['text'] = $cf_rich ? pg_sanitize_rich_text((string)$stored) : (string)$stored;
                }
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
        $tree['children'] = pg_cf_reference_choice_nodes($tree['children'], $map);

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
            pg_cf_apply_field_bindings($child, $map, $lf, $cf_folder_id, $staff, $url_values);
        }
        unset($child);

        $tree['children'] = array_values(array_filter($tree['children'], function ($c) {
            return !(is_array($c) && !empty($c['props']['_pg_cf_empty']));
        }));
    }
}

/**
 * The choices a reference, product or page field's control offers
 * (pg_coll_kind_options()), or null when its choices stay the drawn ones:
 * the field has none of those kinds, or the form a reference points into
 * is gone. The row's reference_options, when given, stand for that read.
 *
 * @param array $row form_fields row (pg_cf_field_map())
 * @return array|null value => label
 */
function pg_cf_reference_choices_for($row)
{
    $kind = pg_coll_field_kind(isset($row['collection_kind']) ? $row['collection_kind'] : '', isset($row['type']) ? (string)$row['type'] : '');
    $field = array('kind' => $kind, 'reference_page_id' => isset($row['reference_page_id']) ? (int)$row['reference_page_id'] : 0);
    if (isset($row['reference_options']) && is_array($row['reference_options'])) $field['options'] = $row['reference_options'];
    return pg_coll_kind_options($field);
}

/**
 * The children of one element with each reference radio button or check
 * box group drawn from the records it may point at: the group's first
 * option (the box, or the wrapper holding only the box and its label, or
 * the box and the label right after it) is repeated once per record, in
 * place of every option the designer drew; the box carries the record's
 * code, its label the record's label, and each box an id of its own the
 * label points at. A group whose form has no records keeps no option: a
 * drawn value is not a record and the server refuses it
 * (pg_coll_check_field()). Name, checked state and the rest are settled
 * by pg_cf_apply_field_bindings() afterwards.
 *
 * @param array $children
 * @param array $map pg_cf_field_map()
 * @return array
 */
function pg_cf_reference_choice_nodes($children, $map)
{
    $any = false;
    foreach ((array)$map as $row) {
        if (in_array(isset($row['type']) ? (string)$row['type'] : '', array('radio button', 'check box'), true)
            && pg_coll_field_kind(isset($row['collection_kind']) ? $row['collection_kind'] : '', (string)$row['type']) === 'reference') {
            $any = true;
            break;
        }
    }
    if (!$any) return $children;

    $tag_of = function ($n) {
        return (is_array($n) && isset($n['type']) && $n['type'] === 'semantic' && isset($n['props']['tag']))
            ? strtolower((string)$n['props']['tag']) : '';
    };
    // The field key of a reference radio button or check box, else ''. A box
    // already drawn from a record is not drawn again one level down.
    $box_key = function ($n) use ($tag_of, $map) {
        if ($tag_of($n) !== 'input' || !empty($n['props']['_pg_cf_ref_drawn'])) return '';
        $a = _pg_cf_node_attrs($n);
        $t = isset($a['type']) ? strtolower($a['type']) : '';
        if ($t !== 'radio' && $t !== 'checkbox') return '';
        $key = mb_strtolower(pg_cf_clean_field_name(isset($a['name']) ? $a['name'] : ''), 'UTF-8');
        return ($key !== '' && isset($map[$key])) ? $key : '';
    };
    $controls = function ($n) use (&$controls, $tag_of) {
        $count = in_array($tag_of($n), array('input', 'select', 'textarea'), true) ? 1 : 0;
        if (is_array($n) && !empty($n['children']) && is_array($n['children'])) {
            foreach ($n['children'] as $c) $count += $controls($c);
        }
        return $count;
    };

    // Every option of a group: field key => list of (first index, last
    // index, kind of unit); the box sits at the unit's 'box' path.
    $units = array();
    $count = count($children);
    $list  = array_values($children);
    for ($i = 0; $i < $count; $i++) {
        $child = $list[$i];
        $key = $box_key($child);
        if ($key !== '') {
            $last = ($i + 1 < $count && $tag_of($list[$i + 1]) === 'label' && $controls($list[$i + 1]) === 0) ? $i + 1 : $i;
            $units[$key][] = array('from' => $i, 'to' => $last, 'wrapped' => false);
            $i = $last;
            continue;
        }
        // A wrapper holds the box and one label, beside inline text only
        // (div.form-check); one holding a legend or the question's own
        // label too is looked into instead.
        if (!is_array($child) || empty($child['children']) || !is_array($child['children']) || $controls($child) !== 1) continue;
        $key = '';
        $labels = 0;
        $plain = true;
        foreach ($child['children'] as $inner) {
            $inner_key = $box_key($inner);
            if ($inner_key !== '') { $key = $inner_key; continue; }
            $inner_tag = $tag_of($inner);
            if ($inner_tag === 'label') $labels++;
            elseif (!in_array($inner_tag, array('span', 'small', 'strong', 'em', 'b', 'i'), true)) $plain = false;
        }
        if ($key !== '' && $plain && $labels <= 1) $units[$key][] = array('from' => $i, 'to' => $i, 'wrapped' => true);
    }

    $replace = array();
    $skip    = array();
    foreach ($units as $key => $found) {
        $choices = pg_cf_reference_choices_for($map[$key]);
        if ($choices === null) continue;
        $first = $found[0];
        $template = array_slice($list, $first['from'], $first['to'] - $first['from'] + 1);
        $new = array();
        $k = 0;
        foreach ($choices as $code => $label) {
            foreach (pg_cf_reference_choice_unit($template, $first['wrapped'], (string)$code, (string)$label, (int)$map[$key]['id'], $k++, $box_key) as $node) {
                $new[] = $node;
            }
        }
        $replace[$first['from']] = $new;
        foreach ($found as $unit) {
            for ($j = $unit['from']; $j <= $unit['to']; $j++) $skip[$j] = true;
        }
    }
    if (!$replace) return $children;

    $out = array();
    foreach ($list as $i => $child) {
        if (isset($replace[$i])) foreach ($replace[$i] as $node) $out[] = $node;
        if (!isset($skip[$i])) $out[] = $child;
    }
    return $out;
}

/**
 * One option of a reference group (pg_cf_reference_choice_nodes()) for one
 * record: the template's box takes the code and an id of its own, without
 * the designer's checked state; its label takes the record's label and
 * points at the box.
 *
 * @param array    $template the option's nodes: the box and its label, or the wrapper
 * @param bool     $wrapped  the template is one wrapper holding both
 * @param string   $code
 * @param string   $label
 * @param int      $field_id
 * @param int      $k        the record's place in the group, from 0
 * @param callable $box_key  the field key of a reference box, else ''
 * @return array
 */
function pg_cf_reference_choice_unit($template, $wrapped, $code, $label, $field_id, $k, $box_key)
{
    $without = function ($attrs, $names) {
        return array_values(array_filter(is_array($attrs) ? $attrs : array(), function ($a) use ($names) {
            return !(is_array($a) && isset($a['name']) && in_array(strtolower((string)$a['name']), $names, true));
        }));
    };
    $nodes = $wrapped ? $template[0]['children'] : $template;

    $id = '';
    foreach ($nodes as $n) {
        if ($box_key($n) === '') continue;
        $a  = _pg_cf_node_attrs($n);
        $id = isset($a['id']) ? trim($a['id']) : '';
        break;
    }
    $id = (($id !== '') ? $id : 'cf_' . $field_id) . ($k > 0 ? '_r' . $k : '');

    foreach ($nodes as &$n) {
        if ($box_key($n) !== '') {
            $n['props']['id'] = $id;
            $n['props']['_attrs'] = $without(isset($n['props']['_attrs']) ? $n['props']['_attrs'] : array(), array('id', 'value', 'checked'));
            $n['props']['_attrs'][] = array('name' => 'value', 'value' => $code);
            $n['props']['_pg_cf_ref_drawn'] = true;
        } elseif (isset($n['props']['tag']) && strtolower((string)$n['props']['tag']) === 'label') {
            $n['props']['text'] = $label;
            unset($n['props']['html']);
            $n['children'] = array();
            $n['props']['_attrs'] = $without(isset($n['props']['_attrs']) ? $n['props']['_attrs'] : array(), array('for'));
            $n['props']['_attrs'][] = array('name' => 'for', 'value' => $id);
        }
    }
    unset($n);

    if (!$wrapped) return $nodes;
    $wrapper = $template[0];
    $wrapper['children'] = $nodes;
    return array($wrapper);
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
         // A button carrying formnovalidate (a Cancel beside a required
         // field) submits without the check, as the browser itself would.
         . 'form.addEventListener("submit",function(e){'
         . 'if(e.submitter&&e.submitter.hasAttribute("formnovalidate"))return;'
         . 'if(!form.checkValidity()){e.preventDefault();e.stopPropagation();'
         . 'var bad=form.querySelector(":invalid");if(bad&&bad.focus)bad.focus();}'
         . 'form.classList.add("was-validated");},false);'
         // An image field (data-pg-image-preview) shows the chosen picture
         // below itself before the form is sent.
         . 'Array.prototype.forEach.call(form.querySelectorAll("input[type=file][data-pg-image-preview]"),function(input){'
         . 'var img=null,url="";input.addEventListener("change",function(){'
         . 'if(url){URL.revokeObjectURL(url);url="";}'
         . 'var file=input.files&&input.files[0];'
         . 'if(!file||!/^image\\//.test(file.type)||!window.URL){if(img)img.hidden=true;return;}'
         . 'if(!img){img=document.createElement("img");img.className="img-thumbnail d-block mt-2";img.alt="";'
         . 'img.style.maxHeight="12rem";input.insertAdjacentElement("afterend",img);}'
         . 'url=URL.createObjectURL(file);img.src=url;img.hidden=false;});});});};'
         . 'if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",bind);}else{bind();}'
         . '})();</script>';
}

/**
 * The ids of the rich-text text areas in a drawn page, in document order,
 * each once: the `<textarea>` tags pg_cf_apply_field_bindings() marked with
 * `data-pg-rich-text`.
 *
 * get_wysiwyg_editor_code() writes every id into a script as it is, so only
 * an id of letters, digits and `_ : . -` that starts with a letter is kept;
 * a text area with any other id gets no editor and stays a plain text area.
 *
 * Both editors attach to the first element in the document with the id, so
 * an id that more than one element of the page carries is left out too: the
 * editor would otherwise take over another control (a plain text area whose
 * starting value never went through pg_sanitize_rich_text()).
 */
function pg_cf_rich_text_ids($html)
{
    $ids = array();
    $html = (string)$html;
    if (strpos($html, 'data-pg-rich-text') === false) return $ids;
    if (!preg_match_all('~<textarea\b[^>]*>~i', $html, $tags)) return $ids;
    foreach ($tags[0] as $tag) {
        if (!preg_match('~\sdata-pg-rich-text(?:\s*=|[\s>/])~i', $tag)) continue;
        // The leading space keeps data-id and the like out.
        if (!preg_match('~\sid\s*=\s*"([^"]*)"~i', $tag, $m)) continue;
        $id = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (!preg_match('~^[A-Za-z][A-Za-z0-9_:.\-]*$~', $id)) continue;
        if (in_array($id, $ids, true)) continue;
        if (pg_cf_id_count($html, $id) + ($m[1] !== $id ? pg_cf_id_count($html, $m[1]) : 0) > 1) continue;
        $ids[] = $id;
    }
    return $ids;
}

/**
 * How many tags in $html carry the id attribute $id, quoted either way or
 * bare. The leading space keeps `data-id` and the like out, and `for=` never
 * matches; the value compares case-sensitively, as getElementById() does.
 */
function pg_cf_id_count($html, $id)
{
    $q = preg_quote((string)$id, '~');
    return (int)preg_match_all('~\s(?i:id)\s*=\s*(?:"' . $q . '"|\'' . $q . '\'|' . $q . '(?=[\s/>]))~', (string)$html);
}

/**
 * Bring the rich-text editor to a drawn page that has rich-text text areas.
 *
 * Asked once the whole page is drawn in (get_page_content.php), so one editor
 * serves every designed form on the page: two forms, and a form inside a
 * shared component, alike. The editor is the site's own setting
 * (`page_editor_version`: CKEditor, or TinyMCE for "previous"), the one the
 * classic form pages load. The code goes before the last `</body>`.
 */
function pg_cf_rich_text_inject($content)
{
    $content = (string)$content;
    if (strpos($content, 'data-pg-rich-text') === false) return $content;
    $ids = pg_cf_rich_text_ids($content);
    if (!$ids) return $content;

    $code = get_wysiwyg_editor_code($ids) . "\n";

    // The last </body>: an earlier one can belong to markup the page shows
    // as text or carries in an attribute.
    $body = strripos($content, '</body>');
    return ($body !== false) ? substr_replace($content, $code, $body, 0) : $content . $code;
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

// The values of a <select> node's <option> children, read the way
// pg_cf_select_stored_option() reads them: the `value` attribute, or the text
// when there is none. Empty values (a "Choose…" prompt) are left out.
function pg_cf_select_option_values($select_node)
{
    $out = array();
    if (!is_array($select_node) || !isset($select_node['children']) || !is_array($select_node['children'])) return $out;

    foreach ($select_node['children'] as $opt) {
        if (!is_array($opt) || !isset($opt['props']['tag'])) continue;
        if (strtolower((string)$opt['props']['tag']) !== 'option') continue;
        $val = null;
        if (isset($opt['props']['_attrs']) && is_array($opt['props']['_attrs'])) {
            foreach ($opt['props']['_attrs'] as $a) {
                if (is_array($a) && isset($a['name']) && $a['name'] === 'value') {
                    $val = isset($a['value']) ? (string)$a['value'] : '';
                    break;
                }
            }
        }
        if ($val === null) $val = isset($opt['props']['text']) ? (string)$opt['props']['text'] : '';
        if ($val !== '') $out[] = $val;
    }
    return $out;
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

// The `_cf` record a control carries, with every key present. `wysiwyg` and
// `translate` are three-state: null when the control does not say (the field
// row keeps the flag it has), otherwise true or false. `url_param` is kept as
// written; pg_sw_url_param_name() decides where it is used.
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
        'wysiwyg'            => array_key_exists('wysiwyg', $cf) ? !empty($cf['wysiwyg']) : null,
        'translate'          => array_key_exists('translate', $cf) ? !empty($cf['translate']) : null,
        'url_param'          => (isset($cf['url_param']) && is_scalar($cf['url_param'])) ? trim((string)$cf['url_param']) : '',
        // A collection field's kind and the form a reference points into
        // (pg_coll_field_kind(), pg_coll_field_reference()).
        'kind'               => (isset($cf['kind']) && is_string($cf['kind'])) ? $cf['kind'] : '',
        'ref'                => (isset($cf['ref']) && (is_int($cf['ref']) || is_string($cf['ref']))) ? $cf['ref'] : 0,
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
 *
 * `wysiwyg` (rich text) is read for a text area only and stays null when
 * the control's `_cf` has no such key: the writer then keeps the row's own
 * flag (pg_cf_rich_text_flag()). The editor's twin is _cfFieldsOf().
 * `translate` (include the answers in translation) is three-state the same
 * way (pg_cf_translate_flag()).
 * `url_param` (the page-address parameter the field starts with) is read
 * from the field's first control, like every `_cf` key, and is never written
 * to a row: the renderer reads it (pg_cf_url_prefill_values()).
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
                    } elseif ($tag === 'select' && !array_key_exists('multiple', $a) && !empty($n['children']) && is_array($n['children'])) {
                        // A single pick list's default is the answer marked
                        // selected: what an office-use-only list stores for
                        // a visitor, who never sees it.
                        foreach ($n['children'] as $o) {
                            if ($tag_of($o) !== 'option') continue;
                            $oa = _pg_cf_node_attrs($o);
                            if (!array_key_exists('selected', $oa)) continue;
                            $default = isset($oa['value']) ? (string)$oa['value'] : _pg_cf_node_text($o);
                            break;
                        }
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
                        'wysiwyg'         => ($tag === 'textarea') ? $cf['wysiwyg'] : false,
                        'translate'       => $cf['translate'],
                        'rss_field'       => $rss,
                        'contact_field'   => $cf['contact_field'],
                        'url_param'       => $cf['url_param'],
                        'kind'            => $cf['kind'],
                        'reference_page_id' => $cf['ref'],
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

    // Whether this save is the one that creates the form (sample records go
    // only into a form made just now).
    $had_form = (bool)db_value("SELECT id FROM custom_form_pages WHERE page_id = '$page_id' LIMIT 1");

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

    // A design template's form can ask for sample records (the setting
    // `sample_records`: the records themselves, or 'blog' for the site's
    // sample blog), so the pages listing it do not open empty. Only into the
    // form this save created.
    if (!$had_form && is_array($settings) && !empty($settings['sample_records'])) {
        $sample = $settings['sample_records'];
        $res['seeded'] = pg_cf_seed_sample_records($page_id, is_array($sample) ? $sample : (string)$sample, (int)$user_id);
    }

    // The BlogPosting author (`schema_author`, 'field:<field name>' for a
    // field): the designer's Schema Author choice, or a template's on the
    // form Publish makes. Written after the fields, so a field added in this
    // save has its id. A form only shown here is never written from here.
    if (is_array($settings) && isset($settings['schema_author']) && empty($res['read_only']) && pg_blogposting_author_setting_ready()) {
        db("UPDATE custom_form_pages
            SET schema_author = '" . e(pg_cf_template_schema_author($settings['schema_author'], $ids_by_name)) . "'
            WHERE page_id = '$page_id' LIMIT 1");
    }

    return $res;
}

/**
 * The custom_form_pages.schema_author value a design template's form setting
 * names: 'contact', 'organization' or 'field:<field name>', the name turned
 * into the id of the form's field ($ids_by_name: lowercased name => id). A
 * field the form does not have, or anything else, is the contact, the
 * column's default (pg_blogposting_author_setting_value(), includes/fn/seo.php).
 */
function pg_cf_template_schema_author($wanted, $ids_by_name)
{
    $wanted = is_scalar($wanted) ? trim((string)$wanted) : '';
    if (strpos($wanted, 'field:') === 0) {
        $name = mb_strtolower(pg_cf_clean_field_name(substr($wanted, 6)), 'UTF-8');
        $id   = (is_array($ids_by_name) && $name !== '' && isset($ids_by_name[$name])) ? (int)$ids_by_name[$name] : 0;
        return pg_blogposting_author_setting_value('field', $id);
    }
    return pg_blogposting_author_setting_value($wanted, 0);
}

/**
 * Copy sample records into a new form.
 *
 * An array: the records a design template brings along, each a list of
 * field name => value (a file field takes the address of a picture). They
 * are written newest first, dated over the last weeks, so the template's
 * blog opens the same on every site - a site installed from a template has
 * no sample data of its own to copy. At most twenty-four (a café's menu);
 * a name the form has no field for is skipped, and a list of values fills a
 * field that holds several (a check box group, a multiple pick list).
 *
 * 'blog': the posts of the sample blog (the form the sample data names
 * BLOG) become posts of this form - title, a short summary, the body as
 * plain text with its paragraphs (as filtered HTML when the form's body is a
 * rich-text text area), and a cover: the post's own picture when
 * it has one in the file manager, otherwise a sample photo. The newest six,
 * dated over the last weeks and signed by the operator who published the
 * page, so the blog looks alive on the first visit and every post can be
 * edited or deleted like any other. Nothing happens when the form already
 * has records or the site has no sample blog. Returns the number copied.
 */
function pg_cf_seed_sample_records($page_id, $source, $user_id)
{
    $page_id = (int)$page_id;
    if ($page_id <= 0) return 0;
    if (is_array($source)) return _pg_cf_seed_given_records($page_id, $source, $user_id);
    if ($source !== 'blog') return 0;
    if ((int)db_value("SELECT COUNT(*) FROM forms WHERE page_id = '$page_id'") > 0) return 0;

    $src_page = (int)db_value(
        "SELECT page_id FROM custom_form_pages
         WHERE UPPER(form_name) = 'BLOG' AND page_id <> '$page_id'
         ORDER BY page_id ASC LIMIT 1");
    if ($src_page <= 0) return 0;

    $fields_of = function ($pid) {
        $out = array();
        foreach ((array)db_items("SELECT id, name, type, wysiwyg FROM form_fields WHERE page_id = '" . (int)$pid . "'") as $f) {
            $out[mb_strtolower((string)$f['name'], 'UTF-8')] = $f;
        }
        return $out;
    };
    $src = $fields_of($src_page);
    $dst = $fields_of($page_id);
    if (!isset($src['title']) || !isset($dst['title'])) return 0;

    // Rich text to the plain text the template's fields hold: paragraphs
    // kept as blank lines, everything else flattened.
    $plain = function ($html) {
        $text = preg_replace('~<(script|style|iframe)\b[^>]*>.*?</\1\s*>~is', ' ', (string)$html);
        $text = preg_replace('~<\s*br\s*/?>~i', "\n", (string)$text);
        $text = preg_replace('~</(p|div|li|h[1-6]|blockquote)\s*>~i', "\n\n", (string)$text);
        $text = html_entity_decode(strip_tags((string)$text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('~[ \t\x{00A0}]+~u', ' ', (string)$text);
        $text = preg_replace('~ *\n *~', "\n", (string)$text);
        return trim((string)preg_replace('~\n{3,}~', "\n\n", (string)$text));
    };
    $short = function ($text, $max) {
        $text = trim(preg_replace('~\s+~u', ' ', (string)$text));
        if (mb_strlen($text, 'UTF-8') <= $max) return $text;
        $cut = mb_substr($text, 0, $max - 1, 'UTF-8');
        $space = mb_strrpos($cut, ' ', 0, 'UTF-8');
        if ($space !== false && $space > $max * 0.6) $cut = mb_substr($cut, 0, $space, 'UTF-8');
        return rtrim($cut, " ,.;:-") . "\u{2026}";
    };

    $records = db_items(
        "SELECT id FROM forms
         WHERE page_id = '$src_page' AND complete = '1'
         ORDER BY submitted_timestamp DESC, id DESC
         LIMIT 6");
    if (!$records) return 0;

    $pretty = (bool)db_value("SELECT pretty_urls FROM custom_form_pages WHERE page_id = '$page_id' LIMIT 1");
    $now = time();
    $copied = 0;
    foreach ($records as $i => $record) {
        $values = array();
        foreach ((array)db_items("SELECT form_field_id, data FROM form_data WHERE form_id = '" . (int)$record['id'] . "'") as $d) {
            foreach ($src as $name => $f) {
                if ((int)$f['id'] === (int)$d['form_field_id']) { $values[$name] = (string)$d['data']; break; }
            }
        }
        $title = trim($plain(isset($values['title']) ? $values['title'] : ''));
        if ($title === '') continue;
        $summary = $plain(isset($values['summary']) && trim(strip_tags($values['summary'])) !== '' ? $values['summary'] : (isset($values['description']) ? $values['description'] : ''));
        $body_html = isset($values['details']) ? $values['details'] : (isset($values['content']) ? $values['content'] : '');
        $body = $plain($body_html);

        // The cover: a picture of the post's media that is in the file
        // manager ("{path}name.jpg" in the sample data), else a sample photo.
        $cover_data = '';
        $cover_file = 0;
        if (isset($values['media']) && preg_match('~<img\b[^>]*\bsrc\s*=\s*["\']([^"\']+)["\']~i', $values['media'], $im)) {
            $src_url = html_entity_decode($im[1], ENT_QUOTES, 'UTF-8');
            $file_name = basename(preg_replace('~^\{path\}~', '', $src_url));
            $cover_file = (int)db_value("SELECT id FROM files WHERE name = '" . e($file_name) . "' LIMIT 1");
            if ($cover_file > 0) $cover_data = $file_name;
        }
        if ($cover_file <= 0) $cover_data = 'https://picsum.photos/seed/pinegrap-post-' . ($i + 1) . '/1200/675';

        $when = $now - ($i * 4 + 1) * 86400;
        db("INSERT INTO forms (page_id, complete, user_id, reference_code, submitted_timestamp, last_modified_user_id, last_modified_timestamp)
            VALUES ('$page_id', '1', '" . (int)$user_id . "', '" . e(generate_form_reference_code()) . "', '$when', '" . (int)$user_id . "', '$when')");
        $form_id = (int)mysqli_insert_id(db::$con);
        if ($form_id <= 0) continue;

        $put = function ($name, $data, $file_id = 0, $type = 'standard') use ($dst, $form_id) {
            if (!isset($dst[$name])) return;
            db("INSERT INTO form_data (form_id, form_field_id, file_id, data, name, type)
                VALUES ('$form_id', '" . (int)$dst[$name]['id'] . "', '" . (int)$file_id . "', '" . e($data) . "', '" . e($dst[$name]['name']) . "', '" . e($type) . "')");
        };
        $put('title', $title);
        $put('summary', $short($summary, 220));
        // A rich-text body keeps the post's own markup, filtered like an
        // editor's answer; a plain one gets the text with its paragraphs.
        if (isset($dst['content']) && (string)$dst['content']['type'] === 'text area' && !empty($dst['content']['wysiwyg'])) {
            $put('content', prepare_rich_text_editor_content_for_input(pg_cf_seed_rich_value($body_html)), 0, 'html');
        } else {
            $put('content', $body);
        }
        $put('cover_image', $cover_data, $cover_file);

        if ($pretty && function_exists('update_submitted_form_address_name')) update_submitted_form_address_name($form_id);
        $copied++;
    }
    return $copied;
}

/**
 * A sample value for a rich-text text area, as the HTML its editor would have
 * stored: text without block markup gets a paragraph per blank-line-separated
 * part and a <br> per single line break (its characters escaped); markup is
 * kept. Either way it is filtered like a visitor's answer.
 *
 * The {path} placeholder is the store's business
 * (prepare_rich_text_editor_content_for_input(), which needs the site path),
 * so the caller adds it when it writes the row.
 */
function pg_cf_seed_rich_value($value)
{
    $value = trim(str_replace(array("\r\n", "\r"), "\n", (string)$value));
    if ($value === '') return '';
    if (!preg_match('~<(p|h[1-6]|ul|ol|blockquote|div|table|pre|figure|hr|br)\b~i', $value)) {
        $html = '';
        foreach (preg_split('~\n\s*\n~', $value) as $para) {
            $para = trim($para);
            if ($para === '') continue;
            $html .= '<p>' . nl2br(h($para), false) . '</p>';
        }
        $value = $html;
    }
    return pg_sanitize_rich_text($value);
}

// The records a template hands pg_cf_seed_sample_records(), written into the
// form of $page_id while it has none.
function _pg_cf_seed_given_records($page_id, $records, $user_id)
{
    $page_id = (int)$page_id;
    if ($page_id <= 0 || !$records) return 0;
    if ((int)db_value("SELECT COUNT(*) FROM forms WHERE page_id = '$page_id'") > 0) return 0;

    $fields = array();
    foreach ((array)db_items("SELECT id, name, type, wysiwyg FROM form_fields WHERE page_id = '$page_id'") as $f) {
        $fields[mb_strtolower((string)$f['name'], 'UTF-8')] = $f;
    }
    if (!$fields) return 0;

    $pretty = (bool)db_value("SELECT pretty_urls FROM custom_form_pages WHERE page_id = '$page_id' LIMIT 1");
    $now = time();
    $copied = 0;
    foreach (array_slice(array_values($records), 0, 24) as $i => $record) {
        $values = _pg_cf_seed_record_values($record, $fields);
        if (!$values) continue;

        $when = $now - ($i * 4 + 1) * 86400;
        db("INSERT INTO forms (page_id, complete, user_id, reference_code, submitted_timestamp, last_modified_user_id, last_modified_timestamp)
            VALUES ('$page_id', '1', '" . (int)$user_id . "', '" . e(generate_form_reference_code()) . "', '$when', '" . (int)$user_id . "', '$when')");
        $form_id = (int)mysqli_insert_id(db::$con);
        if ($form_id <= 0) continue;
        // One row per value: a check box group keeps each ticked answer in
        // a row of its own, which is what its filters and the edit screen read.
        // A rich-text text area holds HTML, stored the way its editor's
        // answer is (type html): the record page then prints paragraphs and
        // the RSS feed reads text, not tags.
        foreach ($values as $key => $list) {
            $rich = (string)$fields[$key]['type'] === 'text area' && !empty($fields[$key]['wysiwyg']);
            foreach ($list as $value) {
                if ($rich) $value = prepare_rich_text_editor_content_for_input(pg_cf_seed_rich_value($value));
                db("INSERT INTO form_data (form_id, form_field_id, file_id, data, name, type)
                    VALUES ('$form_id', '" . (int)$fields[$key]['id'] . "', '0', '" . e($value) . "', '" . e($fields[$key]['name']) . "', '" . ($rich ? 'html' : 'standard') . "')");
            }
        }
        if ($pretty && function_exists('update_submitted_form_address_name')) update_submitted_form_address_name($form_id);
        $copied++;
    }
    return $copied;
}

// The values of one sample record that its form can hold, by the field's
// lowercased name: a list of one value, or of several for a list of values
// (a check box group, a multiple pick list). Names the form has no field
// for, empty values and anything that is not a scalar are left out.
function _pg_cf_seed_record_values($record, $fields)
{
    $out = array();
    if (!is_array($record) || !is_array($fields)) return $out;
    foreach ($record as $name => $value) {
        $key = mb_strtolower((string)$name, 'UTF-8');
        if (!isset($fields[$key])) continue;
        $list = array();
        foreach ((is_array($value) ? array_values($value) : array($value)) as $v) {
            if (!is_scalar($v)) continue;
            $v = trim((string)$v);
            if ($v !== '' && !in_array($v, $list, true)) $list[] = $v;
        }
        if ($list) $out[$key] = $list;
    }
    return $out;
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
    // Only the shipping form has a recipient; the billing form passes none.
    $ship_to_id = $properties['ship_to_id'] ?? '';
    // Prefix is used on express order for custom shipping fields in order to make the field names
    // unique for each recipient, because fields might appear multiple times for multiple recipients
    // Only the express order screen passes a prefix.
    $prefix = isset($properties['prefix']) ? $properties['prefix'] : '';
    // Used to set the default value for fields that use the folder id for default value feature
    $folder_id = $properties['folder_id'];
    // Is edit mode on or off.
    $edit = $properties['edit'];
    // Added to the edit-mode title when a caller names the form.
    $form_name = $properties['form_name'] ?? '';
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
            // An array (value_<id>[]=) is not a value for one field, so it counts as not passed.
            $value_from_query_string = trim((isset($_GET['value_' . $field['id']]) && is_string($_GET['value_' . $field['id']])) ? $_GET['value_' . $field['id']] : '');
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
// What a custom shipping, billing or product form field keeps of the value a visitor posted,
// for submit_custom_form() and the product forms of the shopping cart and the express order.
// A check box group and a multi-select keep their list of strings, any other field one
// string; a list where one value belongs (name[]=x from a hand-made request) counts as not
// given, as it does in a form list's advanced search. The validators and the escaping that
// follow then only ever see strings.
function pg_cf_posted_value($value, $field)
{
    return pg_form_list_search_value($value, ($field['type'] == 'check box') || (($field['type'] == 'pick list') && !empty($field['multiple'])));
}
// Replaces the value a liveform holds for a custom shipping, billing or product form field
// with what pg_cf_posted_value() keeps of it, before the field is validated and stored. A
// value that is kept as posted is left untouched. The field needs its type and multiple
// columns.
function pg_cf_keep_posted_value($liveform, $html_field_name, $field)
{
    $posted_value = $liveform->get_field_value($html_field_name);
    $kept_value = pg_cf_posted_value($posted_value, $field);

    if ($kept_value !== $posted_value) {
        $liveform->assign_field_value($html_field_name, $kept_value);
    }
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
    // Only the shipping form has a recipient; the billing form passes none.
    $ship_to_id = $properties['ship_to_id'] ?? '';
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

            wysiwyg,

            multiple

        FROM form_fields

        WHERE

            (page_id = '" . e($page_id) . "')

            $form_type_filter

            AND (type != 'information')

        ORDER BY sort_order");
    // Loop through all form fields, so that we can save form data.
    foreach ($fields as $field) {
        $html_field_name = $prefix . 'field_' . $field['id'];
        pg_cf_keep_posted_value($form, $html_field_name, $field);
        // If validation for required fields should be completed and field is required, then validate field.
        if (($require == true) && ($field['required'] == 1)) {
            $error_message = '';
            // If there is a field label, then prepare error message.
            if ($field['label']) {
                $error_message = lang(array('string' => '{var:1} is required.', 'vars' => h($field['label'])));
            }
            $form->validate_required_field($html_field_name, $error_message);
        }
        // Validate data differently depending on field type.
        switch ($field['type']) {
            case 'date':
                // If value is not blank and date is not valid, then mark error.
                if (($form->get_field_value($html_field_name) != '') && (validate_date($form->get_field_value($html_field_name)) == false)) {
                    $form->mark_error($html_field_name, lang(array('string' => 'Please enter a valid date for {var:1}', 'vars' => h($field['label']))));
                }
                break;
            case 'date and time':
                // If value is not blank and date and time is not valid, then mark error.
                if (($form->get_field_value($html_field_name) != '') && (validate_date_and_time($form->get_field_value($html_field_name)) == false)) {
                    $form->mark_error($html_field_name, lang(array('string' => 'Please enter a valid date & time for {var:1}', 'vars' => h($field['label']))));
                }
                break;
            case 'email address':
                // If value is not blank and e-mail address is not valid, then mark error.
                if (($form->get_field_value($html_field_name) != '') && (validate_email_address($form->get_field_value($html_field_name)) == false)) {
                    $form->mark_error($html_field_name, lang(array('string' => 'Please enter a valid e-mail address for {var:1}', 'vars' => h($field['label']))));
                }
                break;
            case 'time':
                // If value is not blank and time is not valid, then mark error.
                if (($form->get_field_value($html_field_name) != '') && (validate_time($form->get_field_value($html_field_name)) == false)) {
                    $form->mark_error($html_field_name, lang(array('string' => 'Please enter a valid time for {var:1}', 'vars' => h($field['label']))));
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

/**
 * What a check box variable written as ^^name^^%%Yes|No%% prints.
 *
 * The text before the first | is printed when the box was ticked, the text
 * after it when it was not; either may be empty. A box counts as ticked when
 * any of its values is something other than blank or 0, so a group of boxes
 * prints the first text when at least one of them was ticked. Returns null
 * when the format holds no |: then it is not a yes/no pair and the caller
 * prints the value the usual way.
 */
function pg_cf_checkbox_format_text($data, $format)
{
    $format = (string)$format;
    if (strpos($format, '|') === false) return null;

    list($ticked_text, $empty_text) = explode('|', $format, 2);

    $values = is_array($data) ? $data : array($data);
    foreach ($values as $value) {
        $value = trim((string)$value);
        if ($value !== '' && $value !== '0') return $ticked_text;
    }

    return $empty_text;
}

/**
 * The variables a form item view layout can use, shaped for the screen.
 *
 * $fields are form_fields rows (name, label, type), $standard_fields the
 * list get_standard_fields_for_view() returns. Each entry carries the token
 * (^^name^^), a label, the field type and, for check box and date fields, an
 * example of the format that goes with them ($yes / $no fill the check box
 * one). Information fields and fields without a name print nothing and are
 * left out. 'syntax' holds one sample line per format (conditional, date,
 * checkbox), taken from the first fitting form field. 'mail' lists the links
 * only the e-mail to the person who sent the form fills in
 * (pg_cf_mail_link_names()); $mail_links maps each name to its label.
 */
function pg_cf_variables_help_build($fields, $standard_fields, $yes = 'Yes', $no = 'No', $mail_links = array())
{
    $example = function ($name, $type) use ($yes, $no) {
        switch ($type) {
            case 'check box':     return '^^' . $name . '^^%%' . $yes . '|' . $no . '%%';
            case 'date':          return '^^' . $name . '^^%%d.m.Y%%';
            case 'date and time': return '^^' . $name . '^^%%d.m.Y H:i%%';
        }
        return '';
    };

    $help = array('fields' => array(), 'standard' => array());

    foreach ((array)$fields as $field) {
        $name = (string)($field['name'] ?? '');
        $type = (string)($field['type'] ?? '');
        if ($name === '' || $type === 'information') continue;
        $label = trim((string)($field['label'] ?? ''));
        $help['fields'][] = array(
            'token'   => '^^' . $name . '^^',
            'label'   => ($label !== '') ? $label : $name,
            'type'    => $type,
            'example' => $example($name, $type),
        );
    }

    foreach ((array)$standard_fields as $field) {
        $name = (string)($field['value'] ?? '');
        if ($name === '') continue;
        $type = (string)($field['type'] ?? '');
        $help['standard'][] = array(
            'token'   => '^^' . $name . '^^',
            'label'   => (string)($field['name'] ?? $name),
            'type'    => $type,
            'example' => $example($name, $type),
        );
    }

    // Ready-made lines for the three formats, on the form's own fields when
    // it has a fitting one: a conditional block, a date format, a check box.
    $help['syntax'] = array(
        'conditional' => '[[example: ^^example^^ || -]]',
        'date'        => '^^submitted_date_and_time^^%%d.m.Y H:i%%',
        'checkbox'    => $example('example', 'check box'),
    );
    if (!empty($help['fields'])) {
        $first = $help['fields'][0];
        $help['syntax']['conditional'] = '[[' . $first['label'] . ': ' . $first['token'] . ' || -]]';
    }
    foreach (array_reverse($help['fields']) as $item) {
        if ($item['type'] === 'date' || $item['type'] === 'date and time') $help['syntax']['date'] = $item['example'];
        if ($item['type'] === 'check box') $help['syntax']['checkbox'] = $item['example'];
    }

    $help['mail'] = array();
    foreach (pg_cf_mail_link_names() as $name) {
        $label = trim((string)($mail_links[$name] ?? ''));
        $help['mail'][] = array(
            'token'   => '^^' . $name . '^^',
            'label'   => ($label !== '') ? $label : $name,
            'type'    => '',
            'example' => '',
        );
    }

    return $help;
}

/**
 * The variables of the form on page $page_id plus the standard fields, for
 * the form item view screen (see pg_cf_variables_help_build()). Each entry
 * also gets a translated name for its type (type_label, empty when the type
 * says nothing worth showing).
 */
function pg_cf_variables_help($page_id)
{
    $page_id = (int)$page_id;

    $fields = array();
    if ($page_id > 0) {
        $rows = db_items(
            "SELECT name, label, type
             FROM form_fields
             WHERE page_id = '$page_id' AND type != 'information' AND name != ''
             ORDER BY sort_order ASC, id ASC");
        if (is_array($rows)) $fields = $rows;
    }

    $help = pg_cf_variables_help_build($fields, get_standard_fields_for_view(), lang('Yes'), lang('No'), array(
        'email_preferences_url'    => lang('E-mail preferences link'),
        'confirm_subscription_url' => lang('Subscription confirmation link'),
    ));

    $type_labels = array(
        'text box'      => lang('Text Box'),
        'text area'     => lang('Text Area'),
        'pick list'     => lang('Pick List'),
        'radio button'  => lang('Radio Button'),
        'check box'     => lang('Check Box'),
        'file upload'   => lang('File Upload'),
        'date'          => lang('Date'),
        'date and time' => lang('Date & Time'),
        'time'          => lang('Time'),
        'email address' => lang('E-mail Address'),
        'signature'     => lang('Signature'),
    );
    foreach (array('fields', 'standard', 'mail') as $group) {
        foreach ($help[$group] as $i => $item) {
            $help[$group][$i]['type_label'] = $type_labels[$item['type']] ?? '';
        }
    }

    return $help;
}

/**
 * The mail merge fields that hold a link for the person who sent the form:
 * the signed e-mail preferences page and the subscription confirmation.
 * They are not standard fields (get_standard_fields_for_view()), which list
 * views, the search index and the designer also read: a signed link must
 * only ever reach the mailbox of the address it was signed for.
 *
 * @return array
 */
function pg_cf_mail_link_names()
{
    return array('email_preferences_url', 'confirm_subscription_url');
}

/**
 * Who the links are being drawn for, within the request that sends a form's
 * e-mails. Set it (an array: email_address, contact_id, custom_form_page_id,
 * form_id) just before the copy for the person who sent the form is drawn,
 * clear it (false) right after; pass nothing to ask. A watcher's copy, the
 * administrator's e-mail and the confirmation page are drawn without it, so
 * they get no link.
 *
 * @param array|false|null $context
 * @return array|null
 */
function pg_cf_mail_links_context($context = null)
{
    static $current = null;

    if ($context === false) {
        $current = null;
    } elseif (is_array($context)) {
        $current = $context;
    }

    return $current;
}

/**
 * The values of the link fields for what is being drawn now, each '' when
 * there is no link to give: no context set, or the administrator's e-mail
 * (drawn while pg_tr_suspend() holds the request for the site's own people).
 *
 * @return array name => value
 */
function pg_cf_mail_link_values()
{
    return pg_cf_mail_link_values_build(
        pg_cf_mail_links_context(),
        function_exists('pg_tr_suspend') && pg_tr_suspend(),
        'pg_email_preferences_url',
        'pg_cf_confirm_subscription_url');
}

/**
 * The link field values for $context (pg_cf_mail_links_context()): each ''
 * without a context or while $suspended (the administrator's e-mail), else
 * what the two link builders give for the context's address, form and contact.
 *
 * @param array|null $context
 * @param bool       $suspended
 * @param callable   $preferences_url function (string $email_address): string
 * @param callable   $confirm_url     function (int $form_id, int $contact_id, string $email_address): string
 * @return array name => value
 */
function pg_cf_mail_link_values_build($context, $suspended, $preferences_url, $confirm_url)
{
    $values = array_fill_keys(pg_cf_mail_link_names(), '');

    if (!is_array($context) || $suspended) {
        return $values;
    }

    $values['email_preferences_url'] = (string) call_user_func($preferences_url, (string)($context['email_address'] ?? ''));
    $values['confirm_subscription_url'] = (string) call_user_func($confirm_url, (int)($context['form_id'] ?? 0), (int)($context['contact_id'] ?? 0), (string)($context['email_address'] ?? ''));

    return $values;
}

/**
 * The link that confirms a subscription made with a form: the signed,
 * stateless link pg_fs_hold_submission() made for this submission when the
 * form asks for a double opt-in and held the contact group back
 * (pg_fs_pending_url()). Given only to the contact's own address
 * (pg_cf_confirm_subscription_url_for()); '' for any other submission, a
 * submission without a contact and a site without ENCRYPTION_KEY.
 *
 * @param int    $form_id       the submitted form (forms.id)
 * @param int    $contact_id
 * @param string $email_address where the e-mail being drawn goes
 * @return string
 */
function pg_cf_confirm_subscription_url($form_id, $contact_id, $email_address)
{
    $url = pg_fs_pending_url($form_id);

    if ($url === '') {
        return '';
    }

    return pg_cf_confirm_subscription_url_for($url, $email_address, pg_fs_contact_email($contact_id));
}

/**
 * $url when $email_address is the contact's own address once both are
 * normalised (pg_privacy_normalize_email()), '' otherwise. The link is signed
 * for the contact's address, while the submitter's e-mail can go to another
 * one (the signed-in user's, or an e-mail field not bound to the contact): a
 * confirmation pressed there would not prove the address owner agreed.
 *
 * @param string $url
 * @param string $email_address
 * @param string $contact_address
 * @return string
 */
function pg_cf_confirm_subscription_url_for($url, $email_address, $contact_address)
{
    $address = pg_privacy_normalize_email($email_address);

    if ((string) $url === '' || $address === '' || $address !== pg_privacy_normalize_email($contact_address)) {
        return '';
    }

    return (string) $url;
}

/**
 * Fills the link fields (pg_cf_mail_link_names()) in a template with
 * $values, '' for a missing one: an empty value takes the other half of a
 * [[...||...]] block and never leaves the token as written. In HTML the
 * value is escaped, and the http:// the editor's Link dialog puts in front
 * of href="^^email_preferences_url^^" is taken off first.
 *
 * Only for text that carries no submitted value yet (a subject or plain
 * text body before get_variable_submitted_form_data_for_content(), a form
 * item view's layout): [[...]] blocks are evaluated here, so a value that
 * holds "[[" or "]]" would otherwise open or close one around other text.
 * Text that already holds the submitted values goes through
 * pg_cf_fill_mail_link_tokens().
 *
 * @param string $content
 * @param array  $values name => value (pg_cf_mail_link_values())
 * @param string $format 'html' or 'plain_text'
 * @return string
 */
function pg_cf_fill_mail_links($content, $values, $format)
{
    $content = (string)$content;

    if (strpos($content, '^^') === false) {
        return $content;
    }

    $format = ($format === 'html') ? 'html' : 'plain_text';

    if ($format === 'html') {
        $content = pg_mail_merge_address_hrefs($content, pg_cf_mail_link_names());
    }

    $fields = array();
    foreach (pg_cf_mail_link_names() as $name) {
        $fields[] = array('name' => $name, 'data' => (string)($values[$name] ?? ''), 'type' => '');
    }

    return replace_variables(array('content' => $content, 'fields' => $fields, 'format' => $format));
}

/**
 * Replaces the bare link fields (^^email_preferences_url^^,
 * ^^confirm_subscription_url^^) in text that already holds the submitted
 * values, such as an HTML e-mail drawn by get_page_content(): no [[...]]
 * block is evaluated, so a submitted value can neither hide nor reveal
 * other text. In HTML the value is escaped and the Link dialog's http:// in
 * front of the field is taken off; '' for a missing value.
 *
 * @param string $content
 * @param array  $values name => value (pg_cf_mail_link_values())
 * @param string $format 'html' or 'plain_text'
 * @return string
 */
function pg_cf_fill_mail_link_tokens($content, $values, $format)
{
    $content = (string)$content;

    if (strpos($content, '^^') === false) {
        return $content;
    }

    $html = ($format === 'html');

    if ($html) {
        $content = pg_mail_merge_address_hrefs($content, pg_cf_mail_link_names());
    }

    $replace = array();
    foreach (pg_cf_mail_link_names() as $name) {
        $value = (string)($values[$name] ?? '');
        $replace['^^' . $name . '^^'] = $html ? h($value) : $value;
    }

    return strtr($content, $replace);
}

/**
 * Whether a filled message carries one of the links in $values, so email()
 * keeps its body out of the failure log and the "delivery failed" notice.
 *
 * @param string $text
 * @param array  $values
 * @return bool
 */
function pg_cf_mail_links_in($text, $values)
{
    foreach ((array)$values as $value) {
        if (((string)$value !== '') && (strpos((string)$text, (string)$value) !== false)) {
            return true;
        }
    }

    return false;
}

/**
 * Who the form's output is being drawn for, within the request that handles
 * a submission: true while the copy for the person who sent the form is
 * drawn, false while a watcher's copy is. Pass a bool to set it, nothing to
 * ask. Outside custom_form.php (the confirmation page) it stays true: the
 * page is the visitor's own.
 *
 * @param bool|null $submitter
 * @return bool
 */
function pg_cf_new_account_recipient($submitter = null)
{
    static $is_submitter = true;

    if ($submitter !== null) {
        $is_submitter = (bool) $submitter;
    }

    return $is_submitter;
}

/**
 * Whether the account a form's auto-registration opened may be handed over
 * in what is being drawn now: the copy for the person who sent the form and
 * their confirmation page, never a watcher's copy or the administrator's
 * e-mail (drawn while pg_tr_suspend() holds the request for the site's own
 * people).
 *
 * @return bool
 */
function pg_cf_new_account_for_submitter()
{
    if (function_exists('pg_tr_suspend') && pg_tr_suspend()) {
        return false;
    }

    return pg_cf_new_account_recipient();
}

/**
 * The e-mail address of the account a form's auto-registration opened in
 * this session for a submitted form, or ''.
 *
 * @param int $form_id the submitted form (forms.id)
 * @return string
 */
function pg_cf_new_account_email($form_id)
{
    $entry = $_SESSION['software']['custom_form_auto_registration'][(int) $form_id] ?? null;

    return (is_array($entry) && isset($entry['email_address'])) ? (string) $entry['email_address'] : '';
}

/**
 * The set password link of that account for the person who sent the form,
 * or '' (someone else's copy, no account, or the link was used or expired).
 *
 * @param int $form_id the submitted form (forms.id)
 * @return string
 */
function pg_cf_new_account_link($form_id)
{
    $entry = $_SESSION['software']['custom_form_auto_registration'][(int) $form_id] ?? null;

    if (!is_array($entry) || empty($entry['user_id']) || !pg_cf_new_account_for_submitter()) {
        return '';
    }

    return pg_set_password_link((int) $entry['user_id']);
}

/**
 * The "new account" block a plain-text form e-mail ends with. The person who
 * sent the form gets the address and the link to set a password; anyone else
 * only learns that an account was opened for the address. No password is
 * ever written: the account's password is random and nobody is told it.
 *
 * @param string $email_address
 * @param string $set_password_url '' when there is no live link
 * @param bool   $for_submitter
 * @return string '' without an account
 */
function pg_cf_new_account_plain_text($email_address, $set_password_url, $for_submitter)
{
    $email_address = (string) $email_address;
    $set_password_url = (string) $set_password_url;

    if ($email_address === '') {
        return '';
    }

    $text = "\n\n" . lang('New Account') . ':' . "\n" . '------------' . "\n";

    if (!$for_submitter) {
        return $text . lang(array('string' => 'A new account was created with this submission: {var:1}', 'vars' => array($email_address)));
    }

    $text .=
        lang('We have created a new account for you on our site.') . "\n" .
        "\n" .
        lang('Email') . ': ' . $email_address . "\n";

    if ($set_password_url !== '') {
        return $text . lang(array('string' => 'Set your password: {var:1}', 'vars' => array($set_password_url)));
    }

    return $text . lang('You can set a password with "Forgot password" on the sign-in page.');
}

/**
 * The same block for the built-in (system) layouts of the form item view and
 * the confirmation screen.
 *
 * @param string $email_address
 * @param string $set_password_url
 * @param bool   $for_submitter
 * @return string HTML, '' without an account
 */
function pg_cf_new_account_html($email_address, $set_password_url, $for_submitter)
{
    $email_address = (string) $email_address;
    $set_password_url = (string) $set_password_url;

    if ($email_address === '') {
        return '';
    }

    $html = '<div class="account heading" style="margin-top: 1em">' . lang('New Account') . '</div>' . "\n" . '<div class="account data">' . "\n";

    if (!$for_submitter) {
        return $html . '<p>' . lang(array('string' => 'A new account was created with this submission: {var:1}', 'vars' => array(h($email_address)))) . '</p>' . "\n" . '</div>';
    }

    $html .=
        '<p>' . lang('We have created a new account for you on our site.') . '</p>' . "\n" .
        '<p>' . lang('Email') . ': ' . h($email_address) . '</p>' . "\n";

    if ($set_password_url !== '') {
        $html .= '<p><a href="' . h($set_password_url) . '">' . lang('Set your password') . '</a></p>' . "\n";
    } else {
        $html .= '<p>' . h(lang('You can set a password with "Forgot password" on the sign-in page.')) . '</p>' . "\n";
    }

    return $html . '</div>';
}

/**
 * What a layout's $auto_registration_password (and the designer's
 * __new_account_password token) shows now that no password is handed out.
 * Layouts written before - the ones in data/backups and every layout an
 * operator modified from them - print it as text under a "Password" label,
 * so it carries a sentence with the set password link instead of the secret.
 *
 * @param string $set_password_url
 * @param bool   $for_submitter
 * @return string plain text ('' for anyone but the person who sent the form)
 */
function pg_cf_new_account_password_note($set_password_url, $for_submitter)
{
    if (!$for_submitter) {
        return '';
    }

    if ((string) $set_password_url !== '') {
        return lang(array('string' => 'Set it with this link: {var:1}', 'vars' => array((string) $set_password_url)));
    }

    return lang('You can set a password with "Forgot password" on the sign-in page.');
}

/* Form submission protections: a signed fill time and per address, per
   e-mail and per domain limits. custom_form.php applies them to everyone
   but staff (pg_user_has_panel_access()). */

/**
 * The key the fill time is signed with: the site's encryption key, or ''
 * when the site has none (then the check is skipped).
 *
 * @return string
 */
function pg_cf_issued_key()
{
    return defined('ENCRYPTION_KEY') ? (string) ENCRYPTION_KEY : '';
}

/**
 * The value of the pg_form_issued field: when the form was drawn, and a
 * signature over that second, so a script cannot write a time of its own.
 * Stateless: no session entry, so a reopened page still verifies.
 *
 * @param int    $time Unix time the form is drawn at
 * @param string $key
 * @return string "<time>.<32 hex>", or '' without a key
 */
function pg_cf_issued_token($time, $key)
{
    $key = (string) $key;

    if ($key === '') {
        return '';
    }

    $time = (int) $time;

    return $time . '.' . substr(hash_hmac('sha256', 'pg-form-issued|' . $time, $key), 0, 32);
}

/**
 * Judge a posted pg_form_issued value.
 *
 * @param mixed  $token   The posted value
 * @param int    $now
 * @param string $key
 * @param int    $min     Seconds a person needs at least
 * @param int    $max_age Seconds after which the form counts as expired
 * @return string ok, missing, bad, fast or old; always ok without a key
 */
function pg_cf_issued_verdict($token, $now, $key, $min = 3, $max_age = 86400)
{
    $key = (string) $key;

    if ($key === '') {
        return 'ok';
    }

    if (!is_string($token) || ($token === '')) {
        return 'missing';
    }

    if (!preg_match('/^(\d{1,12})\.([0-9a-f]{32})$/', $token, $match)) {
        return 'bad';
    }

    if (!hash_equals(pg_cf_issued_token((int) $match[1], $key), $token)) {
        return 'bad';
    }

    $age = (int) $now - (int) $match[1];

    // A time ahead of the clock is signed by this site but not reached yet;
    // it counts as too fast rather than as a free pass.
    if ($age < (int) $min) {
        return 'fast';
    }

    if ($age > (int) $max_age) {
        return 'old';
    }

    return 'ok';
}

/**
 * The time a form is signed at. A visitor sent back to the form with an error
 * (custom_form.php keeps when in the session) has spent the time once
 * already, so the form drawn for them is signed as if drawn the minimum time
 * ago: correcting one field and submitting again within seconds is not
 * refused as too fast. A note older than ten minutes is ignored.
 *
 * @param int $now
 * @param int $retry_at When the visitor was last sent back, or 0
 * @param int $min      The minimum fill time pg_cf_issued_verdict() checks
 * @return int
 */
function pg_cf_issued_time($now, $retry_at, $min = 3)
{
    $now      = (int) $now;
    $retry_at = (int) $retry_at;

    if (($retry_at > 0) && ($retry_at <= $now) && ($now - $retry_at <= 600)) {
        return $now - (int) $min;
    }

    return $now;
}

/**
 * The hidden field every form posting to custom_form.php carries. Empty on a
 * site without an encryption key.
 *
 * @return string
 */
function pg_cf_issued_field()
{
    $retry_at = isset($_SESSION['software']['pg_form_retry']) ? (int) $_SESSION['software']['pg_form_retry'] : 0;

    // Used once: the next form drawn is the one the visitor was sent back to.
    if ($retry_at) {
        unset($_SESSION['software']['pg_form_retry']);
    }

    $token = pg_cf_issued_token(pg_cf_issued_time(time(), $retry_at), pg_cf_issued_key());

    if ($token === '') {
        return '';
    }

    return '<input type="hidden" name="pg_form_issued" value="' . h($token) . '">';
}

/**
 * The three limits from the Security settings, each 0..10000; 0 turns one
 * off. The defaults apply until the 2026.11.0 step adds the columns. The
 * domain limit is off by default: its counter is shared by every visitor at
 * that domain, so a few submissions naming gmail.com addresses would refuse
 * every Gmail user for the hour; the per-address and per-visitor limits
 * already stop one inbox from being flooded.
 *
 * @return array ip_hour, recipient_day, domain_hour
 */
function pg_cf_throttle_limits()
{
    $limits = array();

    foreach (array(
        'ip_hour'       => array('form_limit_ip_hour', 10),
        'recipient_day' => array('form_limit_recipient_day', 3),
        'domain_hour'   => array('form_limit_domain_hour', 0),
    ) as $name => $setting) {
        $value = function_exists('waf_setting') ? waf_setting($setting[0], $setting[1]) : $setting[1];

        $limits[$name] = max(0, min(10000, (int) $value));
    }

    return $limits;
}

/**
 * The counters one submission is held against, in waf_rate:
 *
 *   cfi  the visitor's address (its /64 for IPv6), per hour
 *   cfr  the e-mail address the form writes to, per day (waf_rate_long_scopes())
 *   cfd  that address's domain, per hour
 *
 * The address is folded to the inbox it reaches (pg_cf_throttle_mailbox()),
 * so Foo@, foo+1@ and, at Gmail, f.oo@ share one counter; waf_rate_key()
 * hashes every subject, so no address is stored. A limit of 0 drops its row,
 * an empty subject too.
 *
 * @param string $email      The address the form writes to, or ''
 * @param string $ip_subject waf_ip_subject() of the visitor, or ''
 * @param array  $limits     pg_cf_throttle_limits()
 * @return array Rows of subject, scope, window (seconds) and limit
 */
function pg_cf_throttle_subjects($email, $ip_subject, $limits)
{
    $rows = array();

    $ip_subject = (string) $ip_subject;

    if (($ip_subject !== '') && !empty($limits['ip_hour'])) {
        $rows[] = array('subject' => 'i|' . $ip_subject, 'scope' => 'cfi', 'window' => 3600, 'limit' => (int) $limits['ip_hour']);
    }

    $email = pg_cf_throttle_mailbox($email);
    $at    = strrpos($email, '@');

    if (($at === false) || ($at === 0) || ($at === strlen($email) - 1)) {
        return $rows;
    }

    if (!empty($limits['recipient_day'])) {
        $rows[] = array('subject' => 'r|' . $email, 'scope' => 'cfr', 'window' => 86400, 'limit' => (int) $limits['recipient_day']);
    }

    if (!empty($limits['domain_hour'])) {
        $rows[] = array('subject' => 'd|' . substr($email, $at + 1), 'scope' => 'cfd', 'window' => 3600, 'limit' => (int) $limits['domain_hour']);
    }

    return $rows;
}

/**
 * Whether the visitor's address gets a counter of its own. A private or
 * reserved address (10/8, 172.16/12, 192.168/16, fc00::/7, loopback...) is
 * the peer of a reverse proxy or load balancer not listed as a Trusted Proxy,
 * or of a local network, and is shared by every visitor behind it: counting
 * it would hold the whole site to one visitor's allowance.
 *
 * @param string $ip
 * @return bool
 */
function pg_cf_visitor_address_counted($ip)
{
    return filter_var((string) $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
}

/**
 * An e-mail address folded to the inbox it reaches, for the counters only:
 * trimmed and lower-cased, a "+tag" dropped from the name (plus addressing
 * delivers name+anything@ to name@ at every large provider), and at Gmail the
 * dots dropped too, googlemail.com being the same domain. Without this one
 * inbox could be flooded through any number of counters of its own.
 *
 * @param string $email
 * @return string The folded address, or the trimmed text when it is not one
 */
function pg_cf_throttle_mailbox($email)
{
    $email = mb_strtolower(trim((string) $email), 'UTF-8');
    $at    = strrpos($email, '@');

    if (($at === false) || ($at === 0) || ($at === strlen($email) - 1)) {
        return $email;
    }

    $local  = substr($email, 0, $at);
    $domain = substr($email, $at + 1);

    // A quoted name is taken as it is.
    if ($local[0] !== '"') {
        $plus = strpos($local, '+');

        if ($plus) {
            $local = substr($local, 0, $plus);
        }

        if (($domain == 'gmail.com') || ($domain == 'googlemail.com')) {
            $domain = 'gmail.com';
            $bare   = str_replace('.', '', $local);

            if ($bare !== '') {
                $local = $bare;
            }
        }
    }

    return $local . '@' . $domain;
}

/**
 * The submitter's address as custom_form.php settles it after the form row
 * is written, worked out before it so the limits can be checked first: the
 * field connected to the contact's e-mail address, else the signed-in
 * account's address, else the form's only e-mail field. Fields whose value
 * is a list (check box groups, multi-selects) are skipped, as there.
 *
 * @param array  $fields     form_fields rows (id, type, contact_field)
 * @param array  $values     Posted value per field id
 * @param string $user_email USER_EMAIL_ADDRESS, or ''
 * @return string
 */
function pg_cf_submitter_address_guess($fields, $values, $user_email)
{
    $email_fields  = 0;
    $email_value   = '';
    $contact_value = '';

    foreach ($fields as $field) {
        $id    = isset($field['id']) ? $field['id'] : '';
        $value = isset($values[$id]) ? $values[$id] : '';

        if (is_array($value)) {
            continue;
        }

        $value = (string) $value;

        if (isset($field['type']) && ($field['type'] == 'email address')) {
            $email_fields++;
            $email_value = $value;
        }

        if (isset($field['contact_field']) && ($field['contact_field'] == 'email_address')) {
            $contact_value = $value;
        }
    }

    if ($contact_value != '') {
        return $contact_value;
    }

    if ((string) $user_email !== '') {
        return (string) $user_email;
    }

    if ($email_fields == 1) {
        return $email_value;
    }

    return '';
}

/**
 * Whether a form writes to the submitter's address: it mails them, adds
 * them to a contact group or opens an account for them. Only then is the
 * address a recipient the per-address and per-domain limits protect; a
 * form that only stores what was typed has no one to flood.
 *
 * @param array $settings submitter_email, contact_group_id, auto_registration
 * @return bool
 */
function pg_cf_form_writes_to_submitter($settings)
{
    return !empty($settings['submitter_email'])
        || !empty($settings['contact_group_id'])
        || !empty($settings['auto_registration']);
}

/**
 * Count one submission on its counters, in order, and return the first row
 * the count took past its limit; the rows after it are not counted, so a
 * refused submission uses up no one else's allowance. Counting and testing
 * are one atomic step (waf_rate_record() is a single INSERT ... ON DUPLICATE
 * KEY UPDATE), so parallel requests, each with a session of its own, cannot
 * all read room under the limit and all pass. A refused submission is
 * counted too. Fails open: a counter that cannot be written returns 0.
 *
 * Runs before the form's hook code, so a hook that ends the request (go(),
 * exit) cannot skip the count.
 *
 * @param array         $rows   pg_cf_throttle_subjects()
 * @param callable|null $record (subject, scope, window) => new total;
 *                              waf_rate_record() when null
 * @return array The row over its limit, or an empty array
 */
function pg_cf_throttle_take($rows, $record = null)
{
    $live = ($record === null);

    if ($live) {
        if (!function_exists('waf_rate_record')) {
            return array();
        }

        $record = 'waf_rate_record';
    }

    $full = array();

    foreach ($rows as $row) {
        if ((int) call_user_func($record, $row['subject'], $row['scope'], $row['window']) > $row['limit']) {
            $full = $row;
            break;
        }
    }

    // The firewall's sweep runs only while the firewall is on; these counters
    // work without it, so they bring their own, as the sign-in throttle does.
    if ($live && $rows && function_exists('waf_rate_sweep') && (mt_rand(1, 50) === 1)) {
        waf_rate_sweep();
    }

    return $full;
}

/**
 * Log a refusal by a form limit in the firewall log and in data/firewall.log,
 * so a limit never refuses silently.
 *
 * @param array $row The row over its limit, from pg_cf_throttle_take()
 * @return void
 */
function pg_cf_throttle_log($row)
{
    $rule = 'form-' . $row['scope'];

    if (function_exists('waf_log_event') && isset(db::$con) && db::$con) {
        waf_log_event('rate', $rule, 'rate', 5, 'custom_form.php', (int) $row['limit'] . (($row['window'] >= 86400) ? '/day' : '/hour'));
    }

    if (function_exists('waf_text_log') && function_exists('waf_client_ip')) {
        waf_text_log('DENY', waf_client_ip(), array('rule' => $rule));
    }
}
