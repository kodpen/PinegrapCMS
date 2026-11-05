<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The All Offers list (view_offers.php) and its quick edit: every list value
 * the offer editor (edit_offer.php) changes is a field - the code, the
 * message, how the offer applies, whether it is enabled, its start and end -
 * checked and written by the editor's own code (_pg_offer_validate(),
 * _pg_offer_save()). The flow (conditions and results) and the last change
 * stay text.
 *
 * Loaded where it is needed (require_once), not by functions.php; it needs
 * edit_offer_f.php.
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
 * The quick edit fields. Dates are typed in the site's date format, as the
 * other lists take them; an empty end date makes the offer open-ended.
 *
 * @return array field => definition
 */
function pg_offer_edit_columns()
{
    return array(
        'code'         => array('type' => 'text', 'label' => lang('Offer Code'), 'max' => 50, 'required' => true),
        'description'  => array('type' => 'text', 'label' => lang('Message'), 'max' => 255),
        'require_code' => array('type' => 'select', 'label' => lang('How'), 'options' => array('1' => lang('Code'), '0' => lang('Automatic'))),
        'enabled'      => array('type' => 'select', 'label' => lang('Status'), 'options' => array('1' => lang('Enabled'), '0' => lang('Disabled'))),
        'start_date'   => array('type' => 'text', 'label' => lang('Start Date'), 'required' => true),
        'end_date'     => array('type' => 'text', 'label' => lang('End Date')),
    );
}

/**
 * One offer as the list draws it, read through the editor's own view of the
 * offer (_pg_offer_load()) so the chips and the "incomplete" mark read it
 * the way the editor does.
 *
 * @param array  $row              an offers row with last_modified_username
 * @param array  $products         _pg_offer_products()
 * @param array  $shipping_methods _pg_offer_shipping_methods()
 * @return array
 */
function pg_offer_list_entry($row, $products, $shipping_methods)
{
    $state = _pg_offer_load($row['id']);
    $status = _pg_offer_status($row);
    $condition_chips = array();
    foreach ($state['conditions'] as $condition) {
        $condition_chips[] = _pg_offer_condition_chip($condition, $products);
    }
    $action_chips = array();
    foreach ($state['actions'] as $action) {
        $action_chips[] = array(
            'icon' => _pg_offer_action_icon($action['type']),
            'text' => _pg_offer_action_chip($action, $products));
    }

    return array(
        'id'              => (int) $row['id'],
        'code'            => (string) $row['code'],
        'description'     => (string) $row['description'],
        'status'          => $status,
        'status_label'    => _pg_offer_status_label($status),
        'enabled'         => ($row['status'] == 'enabled'),
        'require_code'    => (int) $row['require_code'],
        'incomplete'      => _pg_offer_is_incomplete($state),
        'condition_chips' => $condition_chips,
        'action_chips'    => $action_chips,
        'upsell'          => ((int) $row['upsell'] == 1) && $state['conditions'],
        'start_date'      => (string) $row['start_date'],
        'end_date'        => (string) $row['end_date'],
        'sentence'        => _pg_offer_sentence($state, $products, $shipping_methods),
        'modified'        => get_relative_time(array('timestamp' => $row['timestamp'])),
        'modified_by'     => (string) $row['last_modified_username']);
}

/**
 * The value a field's input opens with: the stored value, a date in the
 * site's format, no end date for an open-ended offer.
 *
 * @param array  $offer pg_offer_list_entry()
 * @param string $field a key of pg_offer_edit_columns()
 * @return string
 */
function pg_offer_quick_value($offer, $field)
{
    switch ($field) {
        case 'require_code':
            return $offer['require_code'] ? '1' : '0';
        case 'enabled':
            return $offer['enabled'] ? '1' : '0';
        case 'start_date':
            return (string) prepare_form_data_for_output($offer['start_date'], 'date', false);
        case 'end_date':
            return ($offer['end_date'] == PG_OFFER_OPEN_END_DATE) ? '' : (string) prepare_form_data_for_output($offer['end_date'], 'date', false);
    }

    return (string) $offer[$field];
}

/**
 * The list cells a quick edit can change, drawn the one way the list and
 * the answer to an edit both use: name => array('html', 'class'), and
 * 'sort' (Y-m-d) for a date, which the table sorts by instead of the text.
 *
 * @param array $offer pg_offer_list_entry()
 * @return array
 */
function pg_offer_list_cells($offer)
{
    $status_class = array('active' => 'theme-success', 'scheduled' => 'theme-info', 'expired' => 'theme-warning');
    $status = '<span class="badge rounded-pill pg-offer-status fw-normal ' . ($status_class[$offer['status']] ?? 'theme-secondary') . '">' . h($offer['status_label']) . '</span>';
    if ($offer['incomplete']) {
        $status .= ' <span class="badge rounded-pill bg-subtle-warning fg-emphasis-warning border border-subtle-warning fw-normal" title="' . h(lang('This offer cannot do anything at checkout yet.')) . '">' . h(lang('Incomplete')) . '</span>';
    }

    $how = $offer['require_code']
        ? '<span class="badge rounded-pill bg-subtle-primary fg-emphasis-primary border border-subtle-primary fw-normal"><i class="bi bi-tag" aria-hidden="true"></i> ' . h(lang('Code')) . '</span>'
        : '<span class="badge rounded-pill bg-subtle-info fg-emphasis-info border border-subtle-info fw-normal"><i class="bi bi-lightning-charge" aria-hidden="true"></i> ' . h(lang('Automatic')) . '</span>';

    $end = ($offer['end_date'] == PG_OFFER_OPEN_END_DATE) ? h(lang('Open-ended')) : h((string) prepare_form_data_for_output($offer['end_date'], 'date', false));

    $modified = $offer['modified'];
    if ($offer['modified_by'] != '') {
        $modified .= ' ' . lang(array('string' => 'by {var:1}', 'vars' => array(h($offer['modified_by']))));
    }

    return array(
        'code'         => array('html' => '<a class="fw-semibold pg-link-body-emphasis text-decoration-none" href="edit_offer.php?id=' . (int) $offer['id'] . '">' . h($offer['code']) . '</a>', 'class' => 'align-middle'),
        'description'  => array('html' => h($offer['description']), 'class' => 'align-middle small fg-secondary'),
        'require_code' => array('html' => $how, 'class' => 'align-middle text-nowrap'),
        'enabled'      => array('html' => $status, 'class' => 'align-middle text-nowrap'),
        'start_date'   => array('html' => h((string) prepare_form_data_for_output($offer['start_date'], 'date', false)), 'class' => 'align-middle text-nowrap fg-secondary', 'sort' => $offer['start_date']),
        'end_date'     => array('html' => $end, 'class' => 'align-middle text-nowrap fg-secondary', 'sort' => $offer['end_date']),
        'modified'     => array('html' => $modified, 'class' => 'align-middle text-nowrap fg-secondary'),
    );
}

/**
 * The editor's request for an offer with one list value changed: the stored
 * offer as the editor loads it (_pg_offer_load()) and the change, so
 * _pg_offer_validate() checks the whole offer the way a save from the
 * editor does. Pure.
 *
 * A date comes as Y-m-d. The editor keeps an open-ended offer's start on or
 * before today; an offer that starts later and has no end is a range up to
 * the open end date, which the list and the editor read as open-ended once
 * it has started.
 *
 * @param array  $state _pg_offer_load()
 * @param string $field a key of pg_offer_edit_columns()
 * @param mixed  $value as pg_list_edit_parse() read it, a date as Y-m-d
 * @param string $today Y-m-d
 * @return array the request for _pg_offer_validate()
 */
function pg_offer_quick_request($state, $field, $value, $today)
{
    $offer = $state['offer'];

    switch ($field) {
        case 'code':
        case 'description':
            $offer[$field] = (string) $value;
            break;

        case 'require_code':
        case 'enabled':
            $offer[$field] = ((string) $value === '1') ? 1 : 0;
            break;

        case 'start_date':
            $offer['start_date'] = (string) $value;
            if ($offer['end_date'] != PG_OFFER_OPEN_END_DATE) {
                $offer['period'] = 'range';
            } elseif ($offer['start_date'] <= $today) {
                $offer['period'] = 'open';
            } else {
                $offer['period'] = 'range';
            }
            break;

        case 'end_date':
            if ((string) $value === '') {
                $offer['end_date'] = PG_OFFER_OPEN_END_DATE;
                $offer['period'] = ($offer['start_date'] <= $today) ? 'open' : 'range';
            } else {
                $offer['end_date'] = (string) $value;
                $offer['period'] = 'range';
            }
            break;
    }

    return array(
        'offer'      => $offer,
        'conditions' => $state['conditions'],
        'actions'    => $state['actions']);
}

/**
 * A typed date as Y-m-d: '' stays '', false when it is not a date in the
 * site's format (or Y-m-d).
 *
 * @param string $typed
 * @return string|false
 */
function pg_offer_quick_date($typed)
{
    $typed = trim((string) $typed);
    if ($typed === '') {
        return '';
    }
    if (!validate_date($typed)) {
        return false;
    }
    $date = prepare_form_data_for_input($typed, 'date');

    return _pg_offer_valid_date($date) ? $date : false;
}

/**
 * The first of the editor's messages for a refused change: the field's own
 * first, then any other (an offer the editor itself could not save as it is
 * stored refuses every change until it is fixed there).
 *
 * @param array  $errors _pg_offer_validate() errors
 * @param string $field  a key of pg_offer_edit_columns()
 * @return string
 */
function pg_offer_quick_error($errors, $field)
{
    $own = array(
        'code'         => 'offer.code',
        'description'  => 'offer.description',
        'start_date'   => 'offer.dates',
        'end_date'     => 'offer.dates');
    if (isset($own[$field], $errors[$own[$field]])) {
        return (string) $errors[$own[$field]];
    }
    $first = reset($errors);

    return ($first === false) ? '' : lang(array('string' => 'The offer cannot be saved as it is: {var:1} Open it to fix this first.', 'vars' => array((string) $first)));
}

/**
 * Write one quick edit from the list the way the offer editor saves: the
 * whole offer through _pg_offer_validate(), the key code check on a new
 * code and _pg_offer_save() (log, audit, offer.updated, key codes moving
 * with a changed code).
 *
 * @param int    $offer_id
 * @param string $field a key of pg_offer_edit_columns()
 * @param mixed  $value as pg_list_edit_parse() read it
 * @param array  $user  validate_user()
 * @return array the answer for pg_list_edit_reply()
 */
function pg_offer_quick_update($offer_id, $field, $value, $user)
{
    $refuse = function ($message) {
        return array('ok' => false, 'code' => 'invalid', 'message' => $message);
    };

    $state = _pg_offer_load((int) $offer_id);
    if (!$state) {
        return $refuse(lang('The offer could not be found.'));
    }

    if (($field == 'start_date') || ($field == 'end_date')) {
        $value = pg_offer_quick_date($value);
        if ($value === false) {
            $columns = pg_offer_edit_columns();

            return $refuse(lang(array('string' => 'Please enter a valid date for {var:1}', 'vars' => array($columns[$field]['label']))) . ' (' . get_date_format_help() . ')');
        }
    }

    $today = date('Y-m-d');
    $checked = _pg_offer_validate(pg_offer_quick_request($state, $field, $value, $today));
    if (!isset($checked['errors']['offer.code'])) {
        $code_error = _pg_offer_code_key_code_error($checked['clean']['offer']['code'], $state['offer']['code']);
        if ($code_error !== '') {
            $checked['errors']['offer.code'] = $code_error;
        }
    }
    if ($checked['errors']) {
        return $refuse(pg_offer_quick_error($checked['errors'], $field));
    }

    if (_pg_offer_save($checked['clean'], $user) <= 0) {
        return $refuse(lang('The offer could not be found.'));
    }

    $row = db_item(
        "SELECT offers.*, last_modified_user.user_username AS last_modified_username
        FROM offers
        LEFT JOIN user AS last_modified_user ON offers.user = last_modified_user.user_id
        WHERE offers.id = '" . e((int) $offer_id) . "'");
    $offer = pg_offer_list_entry($row, _pg_offer_products(), _pg_offer_shipping_methods());

    return pg_offer_quick_answer($offer, $field);
}

/**
 * The answer to a saved quick edit: the cell, every other cell an edit can
 * change, and the row's filter data (status, how, incomplete, enabled) for
 * the list's chips. Pure but for lang() and the date format.
 *
 * @param array  $offer pg_offer_list_entry()
 * @param string $field
 * @return array
 */
function pg_offer_quick_answer($offer, $field)
{
    $cells = pg_offer_list_cells($offer);
    $others = $cells;
    unset($others[$field]);

    return array('ok' => true, 'value' => pg_offer_quick_value($offer, $field))
        + $cells[$field]
        + array(
            'cells' => $others,
            'row'   => array(
                'status'     => $offer['status'],
                'how'        => $offer['require_code'] ? 'code' : 'auto',
                'incomplete' => $offer['incomplete'] ? '1' : '0',
                'enabled'    => $offer['enabled'] ? 1 : 0));
}
