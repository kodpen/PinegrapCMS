<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - a record's fields changed by a person, right away, with no
 * assistant in between. The record drawer (and the chip menu) offer the
 * fields of the catalogue in changes.php; a save goes through the same
 * typing, rights, per-kind rules and writers a proposed change goes through
 * when it is applied, is written in the person's own name, and - when it
 * was made from a channel - may be kept there as a locked decision, like
 * every other change to a record the workspace makes.
 *
 * What is written is also kept in ws_ai_changes as an applied row with no
 * request (request_id 0), so the record's history reads the same whoever
 * made the change: an assistant's proposal that was applied, or a person.
 *
 * A person also makes a new product or product group here
 * (ws_quick_create_*): the drawer's new mode, opened from the search box or
 * a /product card, and the /product command. Off sale unless ticked, it is
 * written and kept the same way, as an applied create.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * The kinds of record a person edits from the drawer: the catalogue's kinds
 * that have a screen of their own outside the workspace. The workspace's own
 * things (a channel's summary, a task, a note) are edited where they live,
 * a user's role on the user screen, and a page's or a file's words in the
 * file manager.
 *
 * @return string[]
 */
function ws_quick_edit_types()
{
    return array('product', 'product_group', 'order', 'contact', 'erp_account', 'offer', 'calendar_event');
}

/**
 * The stock of a product is its own kind of change (the stock writer turns
 * tracking on with it); the drawer shows it as one more field of the product.
 */
define('WS_QUICK_EDIT_STOCK_FIELD', 'quantity');

/**
 * A field of the catalogue as the drawer draws it: its kind, label, limits
 * and, for an enum, the values with their labels. Nothing here reads the
 * database.
 *
 * @param string $type  the kind of record
 * @param string $name  the field's name
 * @param array  $field the catalogue's [kind, limit, label, column]
 * @return array name, kind, label, limit, values, labels, column
 */
function ws_quick_edit_field_meta($type, $name, $field)
{
    $kind = (string) $field[0];
    $limit = null;
    $values = array();
    $labels = array();

    if (in_array($kind, array('enum', 'role'), true)) {
        $values = array_values((array) $field[1]);

        foreach ($values as $value) {
            $labels[$value] = ws_change_enum_label($type, $value);
        }
    } elseif ($kind === 'int') {
        $limit = array('min' => (int) $field[1][0], 'max' => (int) $field[1][1]);
    } elseif ($kind === 'decimal') {
        $limit = array('min' => $field[1][0], 'max' => $field[1][1], 'decimals' => (int) $field[1][2], 'may_be_empty' => !empty($field[1][3]));
    } elseif ($kind === 'lines') {
        $limit = array('max_items' => (int) $field[1][0], 'max_length' => (int) $field[1][1]);
    } elseif ($kind === 'money') {
        $limit = ((int) $field[1] > 0) ? array('max' => (int) $field[1]) : null;
    } elseif (is_numeric($field[1]) && ((int) $field[1] > 0)) {
        $limit = array('max_length' => (int) $field[1]);
    }

    return array(
        'name'   => (string) $name,
        'kind'   => $kind,
        'label'  => (string) $field[2],
        'limit'  => $limit,
        'values' => $values,
        'labels' => $labels,
        // A field with no column goes with another (an order's reason for
        // cancelling); the drawer shows it only when that one asks for it.
        'column' => ((string) $field[3] !== ''),
    );
}

/**
 * Has the record moved since the drawer read it? The drawer sends back the
 * values it was given; one that no longer matches what the record holds now
 * means somebody else wrote meanwhile, and their change is not written over.
 *
 * @param array $field    the catalogue's field
 * @param mixed $sent     the value the drawer was given, as it came back
 * @param mixed $current  what the record holds now, in the field's shape
 * @return bool true when they differ
 */
function ws_quick_edit_moved($field, $sent, $current)
{
    $sent = ws_change_value($field, $sent);

    // A value the field cannot hold at all did not come from this record.
    if ($sent === null) {
        return $current !== null;
    }

    // Both in the field's own shape: what the record holds may carry more
    // than the field keeps (the seconds of a time).
    $held = ws_change_value($field, $current);

    return $sent !== (($held === null) ? $current : $held);
}

/**
 * The fields the drawer shows first for a kind of record: the ones changed
 * most often. The rest wait behind "More fields". A kind not named here
 * shows every field.
 *
 * @param string $type
 * @return string[]
 */
function ws_quick_edit_short_list($type)
{
    $lists = array(
        'product'        => array('short_description', 'price', 'enabled', WS_QUICK_EDIT_STOCK_FIELD, 'minimum_quantity', 'maximum_quantity', 'brand', 'keywords'),
        'product_group'  => array('name', 'enabled', 'title'),
        'order'          => array('status', 'cancellation_reason', 'notes', 'tracking_numbers'),
        'contact'        => array('first_name', 'last_name', 'company', 'email', 'phone'),
        'erp_account'    => array('title', 'email', 'phone', 'payment_days', 'credit_limit', 'status'),
        'offer'          => array('status', 'start_date', 'end_date', 'description'),
        'calendar_event' => array('name', 'start_time', 'end_time', 'all_day', 'location', 'published'),
    );

    return $lists[$type] ?? array();
}

/**
 * The words the drawer's fields section uses (ws_js_strings()).
 *
 * @return array
 */
function ws_quick_edit_js_strings()
{
    return array(
        'qe_title'       => lang('Fields'),
        'qe_edit'        => lang('Edit fields'),
        'qe_more'        => lang('More fields'),
        'qe_fewer'       => lang('Fewer fields'),
        'qe_save'        => lang('Save'),
        'qe_saved'       => lang('Saved.'),
        'qe_nothing'     => lang('Nothing changed.'),
        'qe_stale'       => lang('The record changed meanwhile, so nothing was written.'),
        'qe_reload'      => lang('Load it again'),
        'qe_decision'    => lang('Keep it in this channel as a decision'),
        'qe_stock_off'   => lang('Stock is not tracked for this product; saving a quantity turns tracking on.'),
        'qe_lines_help'  => lang('One per line'),
        'qe_keywords_help' => lang('Separated by commas'),
        'qe_decimal_help'  => lang('A number, like 0.5'),
        // The card /price and /stock answer with (commands.php).
        'qe_command_title' => lang('The change that will be written'),
        'qe_record'        => lang('Record'),
        // The drawer's new mode and the card /product answers with.
        'qc_new_product'   => lang('New product'),
        'qc_new_group'     => lang('New product group'),
        'qc_create'        => lang('Create'),
        'qc_created'       => lang('Created.'),
        'qc_command_title' => lang('The product that will be created'),
        'qc_all_fields'    => lang('All fields'),
        'qc_unticked'      => lang('Left unticked, nobody sees it in the store yet.'),
        'qc_name_help'     => lang('The product\'s own code (SKU): no two products share one.'),
        // A quantity turns tracking on; so does "Track Inventory" under More
        // fields with no quantity, which tracks the stock from 0.
        'qc_quantity_help' => lang(array('string' => 'Left empty, the stock is not tracked, unless {var:1} is set to Yes: then it is tracked from 0.', 'vars' => array(lang('Track Inventory')))),
    );
}

/**
 * Why this person cannot edit this record from the drawer, or '' when they
 * can, with the record when they can.
 *
 * @param array  $viewer
 * @param string $type
 * @param int    $id
 * @return array ok, error, code (rights, gone, rule, request), record
 */
function ws_quick_edit_check($viewer, $type, $id)
{
    $fail = function ($error, $code = 'request') {
        return array('ok' => false, 'error' => $error, 'code' => $code, 'record' => null);
    };

    if (!function_exists('ws_changes_ready') || !ws_changes_ready()) {
        return $fail(lang('This site cannot take proposed changes yet: its database has to be upgraded.'));
    }

    if (!in_array($type, ws_quick_edit_types(), true) || ((int) $id <= 0)) {
        return $fail(lang('Invalid request.'));
    }

    if (!ws_change_allowed($viewer, $type, (int) $id)) {
        return $fail(lang('You may not change this record.'), 'rights');
    }

    $record = ws_change_record($type, (int) $id);

    if ($record === null) {
        return $fail(lang('The record is gone.'), 'gone');
    }

    // A record the kind's own rules refuse to write (one in the Recycle Bin).
    $check = ws_change_check($viewer, $type, 'update', $record, array());

    if ($check !== '') {
        return $fail($check, 'rule');
    }

    return array('ok' => true, 'error' => '', 'code' => '', 'record' => $record);
}

/**
 * The fields of a record as the drawer draws them, with what the record
 * holds now in each.
 *
 * @param array  $viewer
 * @param string $type
 * @param int    $id
 * @return array ok, error, code, name, tag, label, fields (list of meta +
 *               value + shown), short (the names shown first), stock (the
 *               product's quantity, when it may be set)
 */
function ws_quick_edit_fields($viewer, $type, $id)
{
    $check = ws_quick_edit_check($viewer, $type, $id);

    if (!$check['ok']) {
        return array('ok' => false, 'error' => $check['error'], 'code' => $check['code']);
    }

    $types = ws_change_types();
    $record = $check['record'];
    $fields = array();

    foreach (ws_change_fields_for($types[$type], 'update') as $name => $field) {
        $meta = ws_quick_edit_field_meta($type, $name, $field);
        $meta['value'] = ((string) $field[3] !== '') ? ws_change_current_value($field, $record) : null;
        $meta['shown'] = ((string) $field[3] !== '') ? (string) ws_change_show($type, $name, $meta['value'], 120) : '';
        $fields[] = $meta;
    }

    $out = array(
        'ok'     => true,
        'error'  => '',
        'code'   => '',
        'name'   => ws_change_record_name($type, $record),
        'tag'    => '<#' . $types[$type]['tag'] . ':' . (int) $id . '>',
        'label'  => (string) $types[$type]['label'],
        'fields' => $fields,
        'short'  => ws_quick_edit_short_list($type),
        'stock'  => null,
    );

    // The product's stock, one more field the drawer draws after the others.
    if (($type === 'product') && isset($types['stock']) && ws_change_allowed($viewer, 'stock', (int) $id)) {
        $field = $types['stock']['fields'][WS_QUICK_EDIT_STOCK_FIELD];
        $meta = ws_quick_edit_field_meta('stock', WS_QUICK_EDIT_STOCK_FIELD, $field);
        $meta['value'] = ws_change_current_value($field, $record);
        $meta['shown'] = (string) ws_change_show('stock', WS_QUICK_EDIT_STOCK_FIELD, $meta['value'], 120);
        $meta['tracked'] = ((int) ($record['inventory'] ?? 0) === 1);
        $out['stock'] = $meta;
    }

    return $out;
}

/**
 * Writes the fields a person changed in the drawer.
 *
 * @param array  $viewer
 * @param string $type
 * @param int    $id
 * @param array  $fields     name => new value
 * @param array  $from       name => the value the drawer was given, for the
 *                           fields it sends back; a field left out is not
 *                           checked against the record
 * @param int    $channel_id the channel the drawer was opened from, or 0
 * @param bool   $decision   keep the change in that channel as a decision
 * @return array ok, error, stale, lines (what changed, in words), decision_id
 */
function ws_quick_edit_save($viewer, $type, $id, $fields, $from = array(), $channel_id = 0, $decision = false)
{
    $fail = function ($error, $stale = false) {
        return array('ok' => false, 'error' => $error, 'stale' => $stale, 'lines' => array(), 'decision_id' => 0);
    };

    $check = ws_quick_edit_check($viewer, $type, $id);

    if (!$check['ok']) {
        return $fail($check['error']);
    }

    $fields = is_array($fields) ? $fields : array();
    $from = is_array($from) ? $from : array();

    if (empty($fields)) {
        return $fail(lang('Nothing in it differs from what the record holds now.'));
    }

    // The stock of a product goes to its own writer.
    $changes = array();
    $stock = null;

    if (($type === 'product') && array_key_exists(WS_QUICK_EDIT_STOCK_FIELD, $fields)) {
        $stock = $fields[WS_QUICK_EDIT_STOCK_FIELD];
        unset($fields[WS_QUICK_EDIT_STOCK_FIELD]);
    }

    if (!empty($fields)) {
        $changes[] = array('type' => $type, 'action' => 'update', 'id' => (int) $id, 'fields' => $fields);
    }

    if ($stock !== null) {
        if (!ws_change_allowed($viewer, 'stock', (int) $id)) {
            return $fail(lang('You may not change this record.'));
        }

        $changes[] = array('type' => 'stock', 'action' => 'update', 'id' => (int) $id, 'fields' => array(WS_QUICK_EDIT_STOCK_FIELD => $stock));
    }

    // Typed, bounded, checked by the kind's rules and laid against the record
    // as it is now; a field that already holds the value is left out, so a
    // save that changes nothing is refused here.
    $input = ws_changes_input($changes, (int) $viewer['id'], (int) $channel_id);

    if (!$input['ok'] || empty($input['rows'])) {
        return $fail(preg_replace('/^changes\[[0-9]+\]: /', '', (string) $input['error']));
    }

    $types = ws_change_types();

    // What the drawer was given must still be what the record holds.
    foreach ($input['rows'] as $row) {
        foreach ($row['fields'] as $item) {
            $field = ws_change_field($types[$row['type']], (string) $item['name']);

            if (($field === null) || ((string) $field[3] === '') || !array_key_exists($item['name'], $from)) {
                continue;
            }

            if (ws_quick_edit_moved($field, $from[$item['name']], $item['from'])) {
                return $fail(lang('The record changed meanwhile, so nothing was written. Open it again to see what it holds now.'), true);
            }
        }
    }

    $lines = array();
    $person = ws_person_name($viewer['id']);
    $now = time();

    foreach ($input['rows'] as $row) {
        $values = array();

        foreach ($row['fields'] as $item) {
            $values[$item['name']] = $item['to'];
        }

        // The writers' log lines and sentences name whoever made the change;
        // this one logs its own line below.
        $GLOBALS['ws_change_log_quiet'] = true;
        ws_change_source(array('kind' => 'person', 'name' => $person));
        $done = ws_change_write($viewer, $row['type'], 'update', $check['record'], $values, false);
        ws_change_source(false);
        $GLOBALS['ws_change_log_quiet'] = false;

        if (!$done['ok']) {
            return $fail($done['error']);
        }

        foreach ($row['fields'] as $item) {
            $field = ws_change_field($types[$row['type']], (string) $item['name']);

            if ($field !== null) {
                $lines[] = ((string) $field[3] === '')
                    ? $field[2] . ': “' . ws_change_show($row['type'], $item['name'], $item['to'], 120) . '”'
                    : $field[2] . ': “' . ws_change_show($row['type'], $item['name'], $item['from'], 120) . '” → “' . ws_change_show($row['type'], $item['name'], $item['to'], 120) . '”';
            }
        }

        // Kept with the proposals that were applied, as a change with no
        // request behind it.
        db("INSERT INTO ws_ai_changes (request_id, channel_id, message_id, requested_by, record_type, action, record_id, fields, reason, status, decided_by, decided_at, created_at)
            VALUES ('0', '" . (int) $channel_id . "', '0', '" . (int) $viewer['id'] . "', '" . e($row['type']) . "', 'update', '" . (int) $id . "',
                '" . e(json_encode($row['fields'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . "', '', 'applied',
                '" . (int) $viewer['id'] . "', '" . $now . "', '" . $now . "')");
    }

    $name = ws_change_record_name($type, $check['record']);
    $tag = '<#' . $types[$type]['tag'] . ':' . (int) $id . '>';

    log_activity(lang(array('string' => '{var:1} ({var:2}) was changed in the Workspace: {var:3}', 'vars' => array($types[$type]['label'], $name, implode(', ', $lines)))), ws_quick_edit_log_name($viewer));

    // Kept in the channel the drawer was opened from, as a locked decision.
    $decision_id = 0;
    $channel = ((int) $channel_id > 0) ? ws_channel((int) $channel_id) : null;

    if ($decision && $channel && ws_can_post_channel($viewer, $channel)) {
        $body = lang(array('string' => '{var:1} was changed. {var:2}', 'vars' => array($tag, implode(' · ', $lines))));
        $sent = ws_message_send($viewer, $channel, mb_substr($body, 0, WS_MESSAGE_MAX), array('kind' => 'decision'));

        if ($sent['ok']) {
            $decision_id = (int) $sent['message_id'];
            db("UPDATE ws_messages SET locked = 1 WHERE id = '" . $decision_id . "'");

            if (function_exists('ws_thread_copy_sync')) {
                ws_thread_copy_sync($decision_id);
            }
        }
    }

    return array('ok' => true, 'error' => '', 'stale' => false, 'lines' => $lines, 'decision_id' => $decision_id);
}

/**
 * The name the activity log files a quick edit under: the session's
 * username, or the person's name when there is no session (the API).
 *
 * @param array $viewer
 * @return string
 */
function ws_quick_edit_log_name($viewer)
{
    $user = (string) ($_SESSION['sessionusername'] ?? '');

    return ($user !== '') ? $user : ws_person_name($viewer['id']);
}

/* ---------------------------------------------------------------------------
   A new record, made by a person: the drawer's new mode (opened from the
   search box or a /product card) and the /product command (commands.php).
   It goes through changes.php as an assistant's create does when it is
   applied - typed, bounded, checked (a name already taken), written by the
   kind's writer through ws_change_write() - in the person's name, and is
   kept in ws_ai_changes as an applied create with no request.
   --------------------------------------------------------------------------- */

/**
 * The kinds of record a person makes from the Workspace: the catalogue's
 * creates. Each starts off sale (a group switched off) unless the person
 * ticks it.
 *
 * @return string[]
 */
function ws_quick_create_types()
{
    return array('product', 'product_group');
}

/**
 * The fields of a kind's create the drawer does not draw: a group's
 * products are put in from each product (its groups) or with a proposal,
 * not picked one by one in an empty group's form.
 *
 * @param string $type
 * @return string[]
 */
function ws_quick_create_left_out($type)
{
    return ($type === 'product_group') ? array('product_ids') : array();
}

/**
 * The fields the drawer's new mode shows first; the rest wait behind "More
 * fields".
 *
 * @param string $type
 * @return string[]
 */
function ws_quick_create_short_list($type)
{
    $lists = array(
        'product'       => array('name', 'short_description', 'price', 'enabled', WS_QUICK_EDIT_STOCK_FIELD, 'group_ids'),
        'product_group' => array('name', 'short_description', 'enabled', 'parent_id'),
    );

    return $lists[$type] ?? array();
}

/**
 * What a field of a new record holds when the person leaves it alone: what
 * the kind's writer starts the row with. ws_change_write_product() makes a
 * product taxable and shippable and leaves the rest off; a group is
 * switched off unless the values say otherwise (ws_change_group_create_data());
 * a text, an amount or a number is empty, a list of records holds none. The
 * drawer sends only what differs from it.
 *
 * @param string $type
 * @param string $name
 * @param array  $field the catalogue's [kind, limit, label, column]
 * @return mixed
 */
function ws_quick_create_default($type, $name, $field)
{
    if ((string) $field[0] === 'bool') {
        return ($type === 'product') && in_array((string) $name, array('taxable', 'shippable'), true);
    }

    return ((string) $field[0] === 'ids') ? array() : '';
}

/**
 * The create form of a kind as the drawer's new mode draws it: each field as
 * ws_quick_edit_field_meta() describes it, with the value it starts with
 * (ws_quick_create_default()) and, for a list of records, the tag its picker
 * searches. Reads no database.
 *
 * @param string $type
 * @param array  $kind the kind of the catalogue (ws_change_types()[$type])
 * @return array[]
 */
function ws_quick_create_form($type, $kind)
{
    $out = array();

    foreach (ws_change_fields_for($kind, 'create') as $name => $field) {
        if (in_array($name, ws_quick_create_left_out($type), true)) {
            continue;
        }

        $meta = ws_quick_edit_field_meta($type, $name, $field);
        $meta['value'] = ws_quick_create_default($type, $name, $field);

        if ((string) $field[0] === 'ids') {
            $meta['tag'] = (string) $field[1];
        }

        $out[] = $meta;
    }

    return $out;
}

/**
 * The change a save hands changes.php: a create of the kind, off sale
 * (switched off) unless the person ticked it. A field left empty is no
 * field, as a new record has nothing to empty.
 *
 * @param string $type
 * @param mixed  $fields name => value
 * @return array type, action, id, fields
 */
function ws_quick_create_change($type, $fields)
{
    $out = array();

    foreach ((is_array($fields) ? $fields : array()) as $name => $value) {
        if (($value === null) || ($value === '') || ($value === array())) {
            continue;
        }

        $out[(string) $name] = $value;
    }

    if (!array_key_exists('enabled', $out)) {
        $out['enabled'] = false;
    }

    return array('type' => (string) $type, 'action' => 'create', 'id' => 0, 'fields' => $out);
}

/**
 * The drawer's title for a new record of a kind.
 *
 * @param string $type
 * @return string
 */
function ws_quick_create_title($type)
{
    return ($type === 'product_group') ? lang('New product group') : lang('New product');
}

/**
 * Why this person cannot make a record of this kind, or ok.
 *
 * @param array  $viewer
 * @param string $type
 * @return array ok, error, code (rights, request)
 */
function ws_quick_create_check($viewer, $type)
{
    if (!function_exists('ws_changes_ready') || !ws_changes_ready()) {
        return array('ok' => false, 'error' => lang('This site cannot take proposed changes yet: its database has to be upgraded.'), 'code' => 'request');
    }

    $types = ws_change_types();

    if (!in_array($type, ws_quick_create_types(), true) || !isset($types[$type]) || !in_array('create', $types[$type]['actions'], true)) {
        return array('ok' => false, 'error' => lang('Invalid request.'), 'code' => 'request');
    }

    // The e-commerce right, as add_product.php asks it.
    if (!ws_change_allowed($viewer, $type, 0)) {
        return array('ok' => false, 'error' => lang('You may not create this kind of record.'), 'code' => 'rights');
    }

    return array('ok' => true, 'error' => '', 'code' => '');
}

/**
 * The empty form of a new record, as the drawer's new mode draws it.
 *
 * @param array  $viewer
 * @param string $type
 * @return array ok, error, code, title, label, tag, fields, short, required
 */
function ws_quick_create_fields($viewer, $type)
{
    $check = ws_quick_create_check($viewer, $type);

    if (!$check['ok']) {
        return $check;
    }

    $types = ws_change_types();

    return array(
        'ok'       => true,
        'error'    => '',
        'code'     => '',
        'title'    => ws_quick_create_title($type),
        'label'    => (string) $types[$type]['label'],
        'tag'      => (string) $types[$type]['tag'],
        'fields'   => ws_quick_create_form($type, $types[$type]),
        'short'    => ws_quick_create_short_list($type),
        'required' => array_values((array) ($types[$type]['required'] ?? array())),
    );
}

/**
 * A new record's fields checked as a save checks them, nothing written: the
 * person's right, a required field named by its label, then changes.php's
 * typing, bounds and rules (ws_changes_input(): a name already taken, a
 * group that is gone or in the Recycle Bin). The /product card asks it
 * before it is drawn.
 *
 * @param array  $viewer
 * @param string $type
 * @param mixed  $fields     name => value
 * @param int    $channel_id the channel it is made from, or 0
 * @return array ok, error, row (ws_changes_input()'s), values (name => value)
 */
function ws_quick_create_input($viewer, $type, $fields, $channel_id = 0)
{
    $fail = function ($error) {
        return array('ok' => false, 'error' => $error, 'row' => null, 'values' => array());
    };

    $check = ws_quick_create_check($viewer, $type);

    if (!$check['ok']) {
        return $fail($check['error']);
    }

    $types = ws_change_types();
    $change = ws_quick_create_change($type, $fields);

    foreach ((array) ($types[$type]['required'] ?? array()) as $name) {
        if (!isset($change['fields'][$name]) || !is_scalar($change['fields'][$name]) || (trim((string) $change['fields'][$name]) === '')) {
            $field = ws_change_field($types[$type], $name);

            return $fail(lang(array('string' => '{var:1} is required', 'vars' => array(($field !== null) ? (string) $field[2] : $name))));
        }
    }

    $input = ws_changes_input(array($change), (int) $viewer['id'], (int) $channel_id);

    if (!$input['ok'] || empty($input['rows'])) {
        return $fail(preg_replace('/^changes\[[0-9]+\]: /', '', (string) $input['error']));
    }

    $row = $input['rows'][0];
    $values = array();

    foreach ($row['fields'] as $item) {
        $values[$item['name']] = $item['to'];
    }

    return array('ok' => true, 'error' => '', 'row' => $row, 'values' => $values);
}

/**
 * Makes a new product or product group from what a person filled in: the
 * drawer's new mode or a confirmed /product card.
 *
 * Off sale (switched off) unless "On sale" ("Enabled") was ticked. Written
 * by the kind's writer through ws_change_write() - a product by
 * ws_change_write_product() (scored for SEO, product.created), a group by
 * pg_catalog_group_create() (scored, product_group.created) - with the
 * sentences in the person's name and one activity log line of its own. Kept
 * in ws_ai_changes as an applied create with no request (request_id 0,
 * message_id 0), as a drawer edit is kept. With $decision and a channel the
 * person may post in, a locked decision "{tag} was added. …" goes there. It
 * is not linked as the row's decision_message_id: the decision timeline
 * would show it as an assistant's proposal.
 *
 * @param array  $viewer
 * @param string $type       product | product_group
 * @param array  $fields     name => value; what is left out keeps the
 *                           writer's starting value
 * @param int    $channel_id the channel it was made from, or 0
 * @param bool   $decision   keep it in that channel as a decision
 * @return array ok, error, id, name, tag, lines (what it holds, in words),
 *               decision_id
 */
function ws_quick_create_save($viewer, $type, $fields, $channel_id = 0, $decision = false)
{
    $fail = function ($error) {
        return array('ok' => false, 'error' => $error, 'id' => 0, 'name' => '', 'tag' => '', 'lines' => array(), 'decision_id' => 0);
    };

    $input = ws_quick_create_input($viewer, $type, $fields, $channel_id);

    if (!$input['ok']) {
        return $fail($input['error']);
    }

    $types = ws_change_types();
    $values = $input['values'];
    $person = ws_person_name($viewer['id']);

    // The writer's log line and sentences would name an assistant; this
    // logs its own line below.
    $GLOBALS['ws_change_log_quiet'] = true;
    ws_change_source(array('kind' => 'person', 'name' => $person));
    $done = ws_change_write($viewer, $type, 'create', null, $values, false);
    ws_change_source(false);
    $GLOBALS['ws_change_log_quiet'] = false;

    $id = (int) ($done['id'] ?? 0);

    if (empty($done['ok']) || ($id <= 0)) {
        return $fail(!empty($done['error']) ? (string) $done['error'] : lang('Invalid request.'));
    }

    // What it holds, in words: the decision tags the records a list names
    // (drawn as chips), the activity log gives their numbers.
    $lines = array();
    $logged = array();

    foreach ($input['row']['fields'] as $item) {
        $field = ws_change_field($types[$type], (string) $item['name']);

        if ($field === null) {
            continue;
        }

        $shown = $field[2] . ': “' . ws_change_show($type, $item['name'], $item['to'], 120) . '”';
        $lines[] = ((string) $field[0] === 'ids') ? $field[2] . ': ' . ws_change_ids_tokens($field, $item['to']) : $shown;
        $logged[] = $shown;
    }

    $now = time();

    // Kept with the proposals that were applied, as a create with no request
    // behind it.
    db("INSERT INTO ws_ai_changes (request_id, channel_id, message_id, requested_by, record_type, action, record_id, fields, reason, status, decided_by, decided_at, created_at)
        VALUES ('0', '" . (int) $channel_id . "', '0', '" . (int) $viewer['id'] . "', '" . e($type) . "', 'create', '" . $id . "',
            '" . e(json_encode($input['row']['fields'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . "', '', 'applied',
            '" . (int) $viewer['id'] . "', '" . $now . "', '" . $now . "')");

    $name = ws_change_record_name($type, $values);
    $tag = '<#' . $types[$type]['tag'] . ':' . $id . '>';

    log_activity(lang(array('string' => '{var:1} ({var:2}) was added in the Workspace: {var:3}', 'vars' => array($types[$type]['label'], $name, implode(', ', $logged)))), ws_quick_edit_log_name($viewer));

    $decision_id = 0;
    $channel = ((int) $channel_id > 0) ? ws_channel((int) $channel_id) : null;

    if ($decision && $channel && ws_can_post_channel($viewer, $channel)) {
        $body = lang(array('string' => '{var:1} was added. {var:2}', 'vars' => array($tag, implode(' · ', $lines))));
        $sent = ws_message_send($viewer, $channel, mb_substr($body, 0, WS_MESSAGE_MAX), array('kind' => 'decision'));

        if ($sent['ok']) {
            $decision_id = (int) $sent['message_id'];
            db("UPDATE ws_messages SET locked = 1 WHERE id = '" . $decision_id . "'");

            if (function_exists('ws_thread_copy_sync')) {
                ws_thread_copy_sync($decision_id);
            }
        }
    }

    return array('ok' => true, 'error' => '', 'id' => $id, 'name' => $name, 'tag' => $tag, 'lines' => $lines, 'decision_id' => $decision_id);
}
