<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The All Gift Cards list (view_gift_cards.php) and its quick edit: the
 * values the gift card screen (edit_gift_card.php) saves, the balance, the
 * expiration date and the notes, are fields, checked by that screen's own
 * check and written by its own save (includes/forms/gift_card_form.php).
 * Store credit and notes written on more lines change only on the screen.
 *
 * Loaded where it is needed (require_once), not by functions.php; it needs
 * includes/forms/gift_card_form.php.
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
 * The quick edit fields, keyed by the list's column: the values the gift
 * card screen saves. The balance is read as the screen reads it (12,50,
 * 1.234,56 or 1,234.56); the expiration date is typed in the site's date
 * order, blank for none. Pure but for lang().
 *
 * @return array field => definition
 */
function pg_gift_card_edit_columns()
{
    return array(
        'balance' => array('type' => 'money', 'label' => lang('Balance')),
        'expiration_date' => array('type' => 'text', 'label' => lang('Expiration Date'), 'max' => 20),
        'notes' => array('type' => 'text', 'label' => lang('Notes')),
    );
}

/**
 * Whether a gift card's notes can change on the list. The list's box holds
 * one line and reads a line break as a space, so notes written on more lines
 * (on the gift card's screen, or the line a cancelled order adds) change only
 * on the gift card's screen, where the lines are kept.
 *
 * @param string $notes
 * @return bool
 */
function pg_gift_card_notes_fit_list($notes)
{
    return strpbrk((string) $notes, "\r\n") === false;
}

/**
 * Whether a gift card's cell changes on the list. Store credit (a card bound
 * to a customer, credit_user_id > 0) changes only on the gift card's screen,
 * which records a balance change in the audit trail; notes on more lines
 * only there too. Pure.
 *
 * @param array  $gift_card a row of pg_gift_card_list_sql()
 * @param string $field
 * @return bool
 */
function pg_gift_card_quick_cell_editable($gift_card, $field)
{
    if (!array_key_exists($field, pg_gift_card_edit_columns()) || ((int) ($gift_card['credit_user_id'] ?? 0) > 0)) {
        return false;
    }

    return ($field !== 'notes') || pg_gift_card_notes_fit_list($gift_card['notes'] ?? '');
}

/**
 * The value a field opens with, as the gift card screen shows it: the
 * balance in the panel language's separators, the expiration date in the
 * site's date order (blank when it never expires).
 *
 * @param array  $gift_card a gift_cards row
 * @param string $field
 * @return string
 */
function pg_gift_card_quick_value($gift_card, $field)
{
    $stored = $gift_card[$field] ?? '';

    switch ($field) {
        case 'balance':
            return pg_format_input_amount(((int) $stored) / 100);

        case 'expiration_date':
            return (((string) $stored === '') || ((string) $stored === '0000-00-00')) ? '' : (string) prepare_form_data_for_output((string) $stored, 'date', false);
    }

    return (string) $stored;
}

/**
 * The fields of the gift card screen's form for one quick edit: the stored
 * card as the screen opens it, with the one change laid over it.
 *
 * @param array  $gift_card a gift_cards row
 * @param string $field     a key of pg_gift_card_edit_columns()
 * @param mixed  $value     as pg_list_edit_parse() read it
 * @return array form field => value
 */
function pg_gift_card_quick_form_values($gift_card, $field, $value)
{
    $values = array();

    foreach (array_keys(pg_gift_card_edit_columns()) as $key) {
        $values[$key] = pg_gift_card_quick_value($gift_card, $key);
    }

    if (array_key_exists($field, $values)) {
        $values[$field] = (string) $value;
    }

    return $values;
}

/**
 * The list's query, without its ORDER BY.
 *
 * @param int $id one gift card only, 0 for all
 * @return string
 */
function pg_gift_card_list_sql($id = 0)
{
    return "SELECT
            gift_cards.id,
            gift_cards.code,
            gift_cards.amount,
            gift_cards.expiration_date,
            gift_cards.balance,
            gift_cards.notes,
            gift_cards.from_name,
            gift_cards.recipient_email_address,
            gift_cards.delivery_date,
            created_user.user_username AS created_username,
            gift_cards.created_timestamp,
            last_modified_user.user_username AS last_modified_username,
            gift_cards.last_modified_timestamp,
            " . (pg_credit_ready() ? 'gift_cards.user_id' : '0') . " AS credit_user_id
        FROM gift_cards
        LEFT JOIN user AS created_user ON gift_cards.created_user_id = created_user.user_id
        LEFT JOIN user AS last_modified_user ON gift_cards.last_modified_user_id = last_modified_user.user_id"
        . (((int) $id > 0) ? "
        WHERE gift_cards.id = '" . (int) $id . "'
        LIMIT 1" : '');
}

/**
 * The notes and Last Modified cells, drawn the one way the list and the
 * answer to an edit both use.
 *
 * @param array $gift_card a gift_cards row with notes,
 *                         last_modified_timestamp and last_modified_username
 *                         (empty when nobody is recorded)
 * @return array 'notes' and 'last_modified' => array('html', 'class')
 */
function pg_gift_card_list_cells($gift_card)
{
    $username = (string) ($gift_card['last_modified_username'] ?? '');

    return array(
        // Wrapped in a box of its own width: the list's cells do not wrap,
        // so a long note pushed the other columns off the screen.
        'notes'         => array('html' => '<div class="text-wrap text-break" style="max-width:20rem;">' . nl2br(h((string) $gift_card['notes'])) . '</div>', 'class' => 'align-middle text-wrap'),
        'last_modified' => array(
            'html'  => get_relative_time(array('timestamp' => $gift_card['last_modified_timestamp']))
                . (($username !== '') ? ' ' . lang(array('string' => 'by {var:1}', 'vars' => array(h($username)))) : ''),
            'class' => 'align-middle',
        ),
    );
}

/**
 * A gift card's cells on the list, with their quick edit attributes: the
 * code, green while the card has a balance and has not expired, red with the
 * reason under it otherwise, is the link to the gift card.
 *
 * @param array  $gift_card  a row of pg_gift_card_list_sql()
 * @param string $today      Y-m-d
 * @param array  $quick_edit pg_gift_card_edit_columns(), empty without quick edit
 * @return array key => pg_list_cell()
 */
function pg_gift_card_list_row_cells($gift_card, $today, array $quick_edit)
{
    $id = (int) $gift_card['id'];
    $no_expiry = ((string) $gift_card['expiration_date'] === '0000-00-00') || ((string) $gift_card['expiration_date'] === '');
    $expired = !$no_expiry && ((string) $gift_card['expiration_date'] < $today);

    if ($gift_card['balance'] && !$expired) {
        $status_class = 'status_enabled fg-success';
        $state = '';
    } else {
        $status_class = 'status_disabled fg-danger';
        $state = $expired ? pg_list_status_word(lang('Expired')) : pg_list_status_word(lang('No balance'));
    }

    // A card bound to a customer is store credit, not a sold gift card.
    if ((int) ($gift_card['credit_user_id'] ?? 0) > 0) {
        $state .= pg_list_status_word(lang('Store credit'));
    }

    // The code as stored (no dashes), unseen but read by the list's search:
    // the API returns it so, and a customer reads it out so.
    $raw_code = '<span hidden>' . h($gift_card['code']) . '</span>';

    $expires = $no_expiry ? 0 : strtotime((string) $gift_card['expiration_date']);
    $from_name = '';
    $delivery_date = '';
    $delivery = 0;

    // A card with a recipient says who sent it and when it goes out.
    if ((string) $gift_card['recipient_email_address'] !== '') {
        $from_name = ((string) $gift_card['from_name'] !== '') ? h($gift_card['from_name']) : h(lang('Anonymous'));

        if ((string) $gift_card['delivery_date'] === '0000-00-00') {
            $delivery_date = h(lang('Immediate'));
        } else {
            $delivery = strtotime((string) $gift_card['delivery_date']);
            $delivery_date = get_absolute_time(array('timestamp' => $delivery, 'type' => 'date'));
        }
    }

    $attributes = function ($field, array $more = array()) use ($gift_card, $quick_edit, $id) {
        if (!$quick_edit || !pg_gift_card_quick_cell_editable($gift_card, $field)) {
            return $more;
        }

        return $more + pg_list_edit_cell_attributes($quick_edit, $field, $id, pg_gift_card_quick_value($gift_card, $field));
    };

    $cells = pg_gift_card_list_cells($gift_card);

    return array(
        'code' => pg_list_cell(pg_commerce_list_open_link('edit_gift_card.php?id=' . $id, '<span class="badge fw-lighter ' . $status_class . '">' . output_gift_card_code($gift_card['code']) . '</span>') . $raw_code . $state, 'align-middle', array('data-pg-sort-value' => (string) $gift_card['code'])),
        'balance' => pg_list_cell('<span class="badge theme-secondary fw-lighter">' . pg_format_money($gift_card['balance'] / 100, BASE_CURRENCY_SYMBOL) . '</span>', 'align-middle', $attributes('balance', array('data-pg-sort-value' => (string) (int) $gift_card['balance']))),
        'original_amount' => pg_list_cell('<span class="badge theme-primary fw-lighter">' . pg_format_money($gift_card['amount'] / 100, BASE_CURRENCY_SYMBOL) . '</span>', 'align-middle', array('data-pg-sort-value' => (string) (int) $gift_card['amount'])),
        'expiration_date' => pg_list_cell($expires ? get_absolute_time(array('timestamp' => $expires, 'type' => 'date')) : '', 'align-middle', $attributes('expiration_date', array('data-pg-sort-value' => (string) (int) $expires))),
        'notes' => pg_list_cell($cells['notes']['html'], $cells['notes']['class'], $attributes('notes')),
        'from' => pg_list_cell($from_name),
        'recipient' => pg_list_cell(h($gift_card['recipient_email_address'])),
        'delivery_date' => pg_list_cell($delivery_date, 'align-middle', array('data-pg-sort-value' => (string) (int) $delivery)),
        'created' => pg_list_cell(pg_list_when($gift_card['created_timestamp'], (string) $gift_card['created_username']), 'align-middle', array('data-pg-sort-value' => (string) (int) $gift_card['created_timestamp'])),
        'last_modified' => pg_list_cell($cells['last_modified']['html'], $cells['last_modified']['class'], array('data-pg-sort-value' => (string) (int) $gift_card['last_modified_timestamp'])),
    );
}

/**
 * The quick edit answer from a gift card read again: the edited cell and
 * every other cell of the row (the code's colour follows the balance and
 * the expiration date; Last Modified follows every change), each with the
 * value its field opens with.
 *
 * @param array  $cells pg_gift_card_list_row_cells()
 * @param string $field the edited cell's column
 * @return array
 */
function pg_gift_card_quick_saved(array $cells, $field)
{
    $view = function ($cell) {
        $out = array('html' => $cell['html'], 'class' => $cell['class']);

        if (isset($cell['attributes']['data-pg-edit-value'])) {
            $out['value'] = (string) $cell['attributes']['data-pg-edit-value'];
        }

        return $out;
    };

    $answer = array('ok' => true) + $view($cells[$field]);
    $answer['value'] = $answer['value'] ?? '';
    $answer['cells'] = array();

    foreach ($cells as $key => $cell) {
        if ($key !== $field) {
            $answer['cells'][$key] = $view($cell);
        }
    }

    return $answer;
}

/**
 * Writes one value changed on the list through the gift card screen's own
 * check (pg_gift_card_form_check(): a balance 0 or above that fits the
 * column, a real expiration date or blank) and save
 * (pg_gift_card_form_write(): the balance only over the one read, the notes
 * only over the ones read). The same values again write nothing.
 *
 * @param int    $id
 * @param string $field      a key of pg_gift_card_edit_columns()
 * @param mixed  $value      as pg_list_edit_parse() read it
 * @param int    $user_id
 * @param array  $quick_edit pg_gift_card_edit_columns()
 * @return array the answer for pg_list_edit_reply()
 */
function pg_gift_card_quick_update($id, $field, $value, $user_id, array $quick_edit)
{
    $id = (int) $id;

    $refuse = function ($message, $code = 'invalid') {
        return array('ok' => false, 'code' => $code, 'message' => (string) $message);
    };

    $gift_card = db_item(pg_gift_card_list_sql($id));

    if (!$gift_card) {
        return $refuse(lang('Record not found.'), 'not_found');
    }

    if ((int) $gift_card['credit_user_id'] > 0) {
        return $refuse(lang('Store credit changes on the gift card\'s screen, which records it in the audit trail.'), 'locked');
    }

    if (!pg_gift_card_quick_cell_editable($gift_card, $field)) {
        return $refuse(lang('These notes take more than one line. Change them in the gift card.'), 'locked');
    }

    $form = new liveform('gift_card_quick_edit');
    $form->remove_form();

    foreach (pg_gift_card_quick_form_values($gift_card, $field, $value) as $form_field => $form_value) {
        $form->assign_field_value($form_field, $form_value);
    }

    $amount = pg_gift_card_form_check($form, 'edit');

    if ($form->check_form_errors()) {
        $messages = array_values(array_filter($form->field_error_texts(), 'strlen'));
        $form->remove_form();

        return $refuse($messages ? html_entity_decode(strip_tags($messages[0]), ENT_QUOTES, 'UTF-8') : lang('The value could not be saved.'));
    }

    $save = array(
        'balance' => (int) round($amount * 100),
        'balance_was' => (int) $gift_card['balance'],
        'expiration_date' => pg_gift_card_form_expiration($form->get_field_value('expiration_date')),
        'notes' => (string) $form->get_field_value('notes'),
        'notes_was' => (string) $gift_card['notes'],
    );
    $form->remove_form();

    $stored_expiration = ((string) $gift_card['expiration_date'] === '') ? '0000-00-00' : (string) $gift_card['expiration_date'];

    if (($save['balance'] !== (int) $gift_card['balance'])
        || ($save['expiration_date'] !== $stored_expiration)
        || ($save['notes'] !== (string) $gift_card['notes'])) {
        $written = pg_gift_card_form_write($id, $save, $user_id);

        if (!$written['ok']) {
            return ($written['field'] === '')
                ? $refuse(lang('The value could not be saved.'))
                : $refuse(lang('The gift card changed in the meantime. Reload the list and try again.'), 'changed');
        }

        log_activity(
            lang(array(
                'string' => 'gift card ({var:1}) was changed on the gift card list: {var:2} from "{var:3}" to "{var:4}"',
                'vars'   => array(output_gift_card_code($gift_card['code']), (string) ($quick_edit[$field]['label'] ?? $field), pg_gift_card_quick_value($gift_card, $field), (string) $value),
            )),
            $_SESSION['sessionusername'] ?? '');

        $gift_card = db_item(pg_gift_card_list_sql($id));

        if (!$gift_card) {
            return $refuse(lang('Record not found.'), 'not_found');
        }
    }

    return pg_gift_card_quick_saved(pg_gift_card_list_row_cells($gift_card, date('Y-m-d'), $quick_edit), $field);
}
