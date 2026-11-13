<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Store credit on the customer's screen (edit_user.php, 2026.11.0): the
 * balance, the cards bound to the customer, staff issuing credit (kind
 * 'manual', a reason, an optional last day) and voiding a card with a
 * reason. The writes are the store credit model's (pg_credit_issue(),
 * pg_credit_void()), which keep the audit trail.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** Most cards the customer's screen lists, newest first. */
define('PG_CREDIT_STAFF_CARDS', 50);

/** Largest amount staff may issue at once, in minor units. */
define('PG_CREDIT_STAFF_MAX', 100000000);

/**
 * Whether a person may see and change a customer's store credit: the same
 * commerce rights as the gift card screens.
 *
 * @param array $user validate_user()'s row
 * @return bool
 */
function pg_credit_staff_allowed(array $user)
{
    return ((int) ($user['role'] ?? 3) < 3) || !empty($user['manage_ecommerce']);
}

/**
 * The issue form read and checked (pure). The amount is read as
 * pg_parse_amount() reads amounts and rounded to minor units; the last day
 * is a Y-m-d date, '' for never: the card stops at the start of the day
 * after it, in the site's time zone.
 *
 * @param mixed $amount_raw
 * @param mixed $last_day_raw
 * @param mixed $reason_raw
 * @param int   $now
 * @return array amount (minor units), expires (unix time, 0 = never),
 *               reason, error ('' or amount, date, reason)
 */
function pg_credit_staff_issue_input($amount_raw, $last_day_raw, $reason_raw, $now)
{
    $out = array('amount' => 0, 'expires' => 0, 'reason' => '', 'error' => '');

    $amount = is_scalar($amount_raw) ? (int) round(pg_parse_amount($amount_raw) * 100) : 0;

    if (($amount <= 0) || ($amount > PG_CREDIT_STAFF_MAX)) {
        $out['error'] = 'amount';

        return $out;
    }

    $out['amount'] = $amount;

    $expires = pg_credit_last_day_expires($last_day_raw, $now);

    if ($expires < 0) {
        $out['error'] = 'date';

        return $out;
    }

    $out['expires'] = $expires;

    $reason = is_scalar($reason_raw) ? trim((string) $reason_raw) : '';

    if ($reason === '') {
        $out['error'] = 'reason';

        return $out;
    }

    $out['reason'] = mb_substr($reason, 0, PG_CREDIT_REASON_LENGTH);

    return $out;
}

/**
 * The moment a card given a last day stops (pure): the start of the next
 * day, so the card is valid through the day itself. Shared by the staff
 * screen and POST /customers/{id}/credit.
 *
 * @param mixed $last_day_raw  Y-m-d, or empty for never
 * @param int   $now
 * @return int  unix time, 0 for never, -1 for an impossible or past day
 */
function pg_credit_last_day_expires($last_day_raw, $now)
{
    $last_day = is_scalar($last_day_raw) ? trim((string) $last_day_raw) : '';

    if ($last_day === '') {
        return 0;
    }

    $date = DateTime::createFromFormat('!Y-m-d', $last_day);

    if (!$date || ($date->format('Y-m-d') !== $last_day)) {
        return -1;
    }

    $date->modify('+1 day');
    $expires = (int) $date->getTimestamp();

    return ($expires <= (int) $now) ? -1 : $expires;
}

/**
 * The sentence for an issue form error code.
 *
 * @param string $code pg_credit_staff_issue_input()'s error
 * @return string
 */
function pg_credit_staff_issue_error($code)
{
    switch ((string) $code) {
        case 'amount':
            return lang('Enter the amount of store credit, such as 25 or 25.50.');
        case 'date':
            return lang('The last day must be a date in the future, or left empty.');
        case 'reason':
            return lang('Enter the reason for this store credit.');
    }

    return lang('The store credit could not be written.');
}

/**
 * The issue form's field an error code is about (pure).
 *
 * @param string $code pg_credit_staff_issue_input()'s error
 * @return string the field's name, '' for none
 */
function pg_credit_staff_issue_field($code)
{
    $fields = array('amount' => 'credit_amount', 'date' => 'credit_last_day', 'reason' => 'credit_reason');

    return $fields[(string) $code] ?? '';
}

/**
 * Where a bound card stands (pure): open (spendable), voided (closed by
 * staff), used (balance 0: spent) or expired.
 *
 * @param array $card gift_cards row (balance, expires_at, expiration_date)
 *                    and void_reason (pg_credit_staff_cards(): the reason of
 *                    its void line, null when it was never voided)
 * @param int   $now
 * @return string open, voided, used or expired
 */
function pg_credit_staff_card_state(array $card, $now)
{
    // A void writes the balance to 0 and leaves the reason in the notes; it
    // stays the card's story after its last day has gone by too.
    if (((int) ($card['balance'] ?? 0) <= 0) && isset($card['void_reason'])) {
        return 'voided';
    }

    // The expiry pass closes an ended card by writing its balance to 0, so a
    // card past its end reads expired whatever is left on it.
    $expires = (int) ($card['expires_at'] ?? 0);

    if (($expires > 0) && ($expires <= (int) $now)) {
        return 'expired';
    }

    if ((int) ($card['balance'] ?? 0) <= 0) {
        return 'used';
    }

    return pg_credit_card_open($card, (int) $now) ? 'open' : 'expired';
}

/**
 * The Store Credit card of the gift card screen's status column (pure):
 * whose credit a card bound to a customer is, where it came from, why and
 * its last day. Without it the screen called such a card "created manually"
 * and named no customer.
 *
 * @param array  $card   gift_cards row: kind, reason, expires_at
 * @param string $owner  the customer as HTML, a link when the viewer may
 *                       open the account
 * @return string HTML
 */
function pg_credit_staff_gift_card_info_html(array $card, $owner)
{
    $expires = (int) ($card['expires_at'] ?? 0);
    $reason = (string) ($card['reason'] ?? '');

    $rows = array(
        array(lang('Customer'), (string) $owner),
        array(lang('Source'), h(pg_credit_staff_kind_label($card['kind'] ?? ''))),
    );

    if ($reason !== '') {
        $rows[] = array(lang('Reason'), nl2br(h($reason)));
    }

    $rows[] = array(lang('Last day'), ($expires > 0) ? h(pg_credit_expiration_date($expires)) : h(lang('Never')));

    $output_rows = '';

    foreach ($rows as $row) {
        $output_rows .= '
                        <div class="row">
                            <span class="col fg-secondary">' . h($row[0]) . ':</span>
                            <span class="col text-end text-break">' . $row[1] . '</span>
                        </div>';
    }

    return '<div class="card mb-7">
                    <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                        ' . h(lang('Store Credit')) . '
                    </h2>
                    <div class="card-body">' . $output_rows . '
                    </div>
                </div>';
}

/**
 * The customer's bound cards, newest first, open or not.
 *
 * @param int $user_id
 * @return array gift_cards rows
 */
function pg_credit_staff_cards($user_id)
{
    if (((int) $user_id <= 0) || !pg_credit_ready()) {
        return array();
    }

    $rows = db_items(
        "SELECT id, code, amount, balance, kind, reason, issued_by, expires_at, expiration_date, notes, created_timestamp
        FROM gift_cards
        WHERE user_id = '" . (int) $user_id . "'
        ORDER BY id DESC
        LIMIT " . PG_CREDIT_STAFF_CARDS);

    if (!is_array($rows)) {
        return array();
    }

    $lines = pg_credit_staff_void_lines();

    foreach ($rows as $key => $row) {
        $rows[$key]['void_reason'] = pg_credit_staff_void_reason((string) $row['notes'], $lines);
    }

    return $rows;
}

/**
 * The forms the line pg_credit_void() adds to a card's notes can have: in
 * English and in the panel's language, the one it was written in.
 *
 * @return array line templates with {var:1} (the date) and {var:2} (the reason)
 */
function pg_credit_staff_void_lines()
{
    $line = 'Store credit voided on {var:1}: {var:2}';

    return array_values(array_unique(array($line, (string) lang($line))));
}

/**
 * The reason a card was voided for, read from its notes (pure): the last
 * line in one of the void line's forms. A card has no column of its own for
 * it; pg_credit_void() writes the balance to 0 and adds the line, which is
 * also how the staff screen tells a voided card from a spent one.
 *
 * @param string $notes gift_cards.notes
 * @param array  $lines pg_credit_staff_void_lines()
 * @return string|null the reason ('' when none was given), null when the
 *                     notes hold no void line
 */
function pg_credit_staff_void_reason($notes, array $lines)
{
    $patterns = array();
    $date = function ($part) {
        return str_replace(preg_quote('{var:1}', '/'), '\\d{4}-\\d{2}-\\d{2}', preg_quote($part, '/'));
    };

    foreach ($lines as $line) {
        $line = (string) $line;

        if ((strpos($line, '{var:1}') === false) || (strpos($line, '{var:2}') === false)) {
            continue;
        }

        // The notes are trimmed when a line is added, so the space before
        // an empty reason may be gone.
        list($head, $tail) = explode('{var:2}', $line, 2);
        $patterns[] = '/^' . $date(rtrim($head)) . '\\s*(.*?)' . $date($tail) . '$/u';
    }

    $reason = null;

    foreach (preg_split('/\r\n|\r|\n/', (string) $notes) as $note) {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, trim($note), $match)) {
                $reason = trim($match[1]);
                break;
            }
        }
    }

    return $reason;
}

/**
 * The name of a card kind for the screen.
 *
 * @param string $kind
 * @return string
 */
function pg_credit_staff_kind_label($kind)
{
    switch ((string) $kind) {
        case 'points':
            return lang('Reward points');
        case 'refund':
            return lang('Refund');
        case 'manual':
            return lang('Issued by staff');
    }

    return lang('Gift card');
}

/**
 * An amount of store credit for the staff card, escaped for HTML. The
 * currencies table keeps the symbol as HTML (&#8378;), so it becomes its
 * character before h(): the card shows it once, and a symbol typed as
 * markup shows as text.
 *
 * @param int|string $cents   minor units of the base currency
 * @param string     $symbol  the currency symbol as stored
 * @return string HTML
 */
function pg_credit_staff_money($cents, $symbol)
{
    return h(pg_money_text(((int) $cents) / 100, (string) $symbol));
}

/**
 * Issue or void from the customer's screen. The caller has checked the
 * token, the person's right to open this user and pg_credit_staff_allowed().
 *
 * @param int   $user_id  the customer
 * @param array $post     the posted fields: pg_credit_action (issue or
 *                        void), credit_amount, credit_last_day,
 *                        credit_reason, credit_card_id, credit_void_reason
 * @param int   $staff_id
 * @return array ok, message, field (the posted field a refusal is about,
 *               '' for none)
 */
function pg_credit_staff_post($user_id, array $post, $staff_id)
{
    if (!pg_credit_ready()) {
        return array('ok' => false, 'message' => lang('Store credit is not installed yet: the database has to be updated first.'), 'field' => '');
    }

    $action = isset($post['pg_credit_action']) && is_string($post['pg_credit_action']) ? $post['pg_credit_action'] : '';

    if ($action === 'issue') {
        $input = pg_credit_staff_issue_input(
            $post['credit_amount'] ?? '', $post['credit_last_day'] ?? '', $post['credit_reason'] ?? '', time());

        if ($input['error'] !== '') {
            return array('ok' => false, 'message' => pg_credit_staff_issue_error($input['error']), 'field' => pg_credit_staff_issue_field($input['error']));
        }

        $card_id = pg_credit_issue((int) $user_id, $input['amount'], 'manual', $input['reason'], $input['expires'], (int) $staff_id);

        if ($card_id <= 0) {
            return array('ok' => false, 'message' => lang('The store credit could not be written.'), 'field' => '');
        }

        log_activity(lang(array(
            'string' => 'Store credit of {var:1} was issued to user #{var:2}: {var:3}',
            'vars'   => array(pg_order_log_money($input['amount']), (int) $user_id, $input['reason']))));

        return array('ok' => true, 'message' => lang(array(
            'string' => 'Store credit of {var:1} was issued.',
            'vars'   => array(pg_order_log_money($input['amount'])))), 'field' => '');
    }

    if ($action === 'void') {
        $card_id = (isset($post['credit_card_id']) && is_scalar($post['credit_card_id'])) ? (int) $post['credit_card_id'] : 0;
        $reason = (isset($post['credit_void_reason']) && is_scalar($post['credit_void_reason'])) ? trim((string) $post['credit_void_reason']) : '';

        // Only a card of this customer: the id comes from the page.
        $owner = ($card_id > 0) ? (int) db_value("SELECT user_id FROM gift_cards WHERE id = '" . $card_id . "'") : 0;

        if (($owner <= 0) || ($owner !== (int) $user_id)) {
            return array('ok' => false, 'message' => lang('The store credit card could not be found.'), 'field' => '');
        }

        if ($reason === '') {
            return array('ok' => false, 'message' => lang('Enter the reason for voiding this store credit.'), 'field' => 'credit_void_reason');
        }

        if (!pg_credit_void($card_id, $reason)) {
            return array('ok' => false, 'message' => lang('The store credit card could not be voided: it may have been used meanwhile. Check it and try again.'), 'field' => '');
        }

        log_activity(lang(array(
            'string' => 'Store credit card #{var:1} of user #{var:2} was voided: {var:3}',
            'vars'   => array($card_id, (int) $user_id, $reason))));

        return array('ok' => true, 'message' => lang('The store credit card was voided.'), 'field' => '');
    }

    return array('ok' => false, 'message' => lang('Invalid request.'), 'field' => '');
}

/**
 * The Store Credit card of the customer's screen: the balance, the cards and
 * the issue form. It is its own form, so it sits outside the screen's main
 * form.
 *
 * What the last issue or void answered is printed at the top of the card,
 * where the screen opens after it (#store_credit), not at the top of the
 * page; after a refusal the typed values are back in their fields and the
 * field the refusal is about is marked.
 *
 * @param int           $user_id
 * @param string        $send_to
 * @param int           $now
 * @param liveform|null $liveform the card's own form (edit_user_store_credit):
 *                                the answer and the posted values
 * @return string HTML, '' before the store credit columns exist
 */
function pg_credit_staff_panel_html($user_id, $send_to, $now, $liveform = null)
{
    if (!pg_credit_ready()) {
        return '';
    }

    $messages = '';
    $kept = function ($field) {
        return '';
    };
    $marked = function ($field) {
        return '';
    };
    $kept_action = '';
    $kept_card = 0;

    if ($liveform) {
        $messages = (string) $liveform->output_errors() . (string) $liveform->output_notices();
        $kept_action = (string) $liveform->get_field_value('pg_credit_action');
        $kept_card = (int) $liveform->get_field_value('credit_card_id');
        $kept = function ($field) use ($liveform) {
            $value = $liveform->get_field_value($field);

            return is_scalar($value) ? (string) $value : '';
        };
        $marked = function ($field) use ($liveform) {
            return $liveform->check_field_error($field) ? ' is-invalid' : '';
        };
    }

    // A refused issue keeps what was typed in the issue form, a refused void
    // what was typed on the row of that card.
    $issue_value = function ($field) use ($kept, $kept_action) {
        return ($kept_action === 'issue') ? h($kept($field)) : '';
    };
    $issue_mark = function ($field) use ($marked, $kept_action) {
        return ($kept_action === 'issue') ? $marked($field) : '';
    };

    $money = function ($cents) {
        return pg_credit_staff_money($cents, BASE_CURRENCY_SYMBOL);
    };
    $keys = get_token_field()
        . '<input type="hidden" name="id" value="' . (int) $user_id . '"/>'
        . '<input type="hidden" name="send_to" value="' . h((string) $send_to) . '"/>';

    $states = array(
        'open'    => array(lang('Open'), 'theme-success'),
        'voided'  => array(lang('Voided'), 'theme-danger'),
        'used'    => array(lang('Used'), 'theme-secondary'),
        'expired' => array(lang('Expired'), 'theme-warning'),
    );

    $rows = '';

    foreach (pg_credit_staff_cards($user_id) as $card) {
        $state = pg_credit_staff_card_state($card, $now);
        $expires = (int) $card['expires_at'];
        $void_row = ($kept_action === 'void') && ($kept_card === (int) $card['id']);
        $void_reason = (($state === 'voided') && ((string) $card['void_reason'] !== ''))
            ? '<div class="small fg-secondary text-break">' . h((string) $card['void_reason']) . '</div>'
            : '';

        $rows .= '
                        <tr>
                            <td><a href="edit_gift_card.php?id=' . (int) $card['id'] . '">#' . (int) $card['id'] . '</a></td>
                            <td>' . h(pg_credit_staff_kind_label($card['kind'])) . (((string) $card['reason'] !== '') ? '<div class="small fg-secondary text-break">' . h((string) $card['reason']) . '</div>' : '') . '</td>
                            <td class="text-end">' . $money($card['amount']) . '</td>
                            <td class="text-end fw-semibold">' . $money($card['balance']) . '</td>
                            <td>' . (($expires > 0) ? h(pg_credit_expiration_date($expires)) : h(lang('Never'))) . '</td>
                            <td><span class="badge ' . $states[$state][1] . '">' . h($states[$state][0]) . '</span>' . $void_reason . '</td>
                            <td class="text-end">' . (($state === 'open') ? '
                                <form method="post" action="edit_user.php" id="store_credit_void_' . (int) $card['id'] . '" class="d-flex gap-3 justify-content-end disable_shortcut" style="margin:0" data-pg-confirm="' . h(lang('The card\'s balance becomes 0 and the customer can no longer spend it.')) . '" data-pg-confirm-title="' . h(lang('Void')) . '" data-pg-confirm-ok="' . h(lang('Void')) . '">' . $keys . '
                                    <input type="hidden" name="pg_credit_action" value="void"/>
                                    <input type="hidden" name="credit_card_id" value="' . (int) $card['id'] . '"/>
                                    <input type="text" name="credit_void_reason" class="form-control form-control-sm' . ($void_row ? $marked('credit_void_reason') : '') . '" maxlength="255" required placeholder="' . h(lang('Reason')) . '" aria-label="' . h(lang('Reason for voiding')) . '" style="max-width:12rem" value="' . ($void_row ? h($kept('credit_void_reason')) : '') . '"/>
                                    <button type="submit" class="btn btn-sm btn-outline theme-danger">' . h(lang('Void')) . '</button>
                                </form>' : '') . '</td>
                        </tr>';
    }

    return '
                <div class="card mb-5" id="store_credit">
                    <div class="card-header bg-reset border-0 d-flex justify-content-between align-items-center">
                        <span class="text-uppercase h5 fg-primary fw-bold mb-0">' . h(lang('Store Credit')) . '</span>
                        <span class="small fg-secondary">' . h(lang('Balance')) . ': <strong>' . $money(pg_credit_balance($user_id, $now)) . '</strong></span>
                    </div>
                    <div class="card-body">
                        ' . $messages . (($rows !== '') ? '<div class="table-responsive mb-5">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th scope="col">' . h(lang('Card')) . '</th>
                                        <th scope="col">' . h(lang('Source')) . '</th>
                                        <th scope="col" class="text-end">' . h(lang('Amount')) . '</th>
                                        <th scope="col" class="text-end">' . h(lang('Balance')) . '</th>
                                        <th scope="col">' . h(lang('Last day')) . '</th>
                                        <th scope="col">' . h(lang('Status')) . '</th>
                                        <th scope="col"><span class="visually-hidden">' . h(lang('Actions')) . '</span></th>
                                    </tr>
                                </thead>
                                <tbody>' . $rows . '
                                </tbody>
                            </table>
                        </div>' : '<p class="small fg-secondary">' . h(lang('This customer has no store credit yet.')) . '</p>') . '
                        <form method="post" action="edit_user.php" id="store_credit_issue" class="disable_shortcut">' . $keys . '
                            <input type="hidden" name="pg_credit_action" value="issue"/>
                            <div class="row g-5 align-items-end">
                                <div class="col-12 md:col-3">
                                    <label for="credit_amount" class="form-label">' . h(lang('Amount')) . '</label>
                                    <label class="form-control form-adorn form-adorn-end">
                                        <span class="form-adorn-text" aria-hidden="true">' . h(BASE_CURRENCY_CODE) . '</span>
                                        <input type="text" name="credit_amount" id="credit_amount" class="form-ghost' . $issue_mark('credit_amount') . '" maxlength="14" inputmode="decimal" required style="text-align: right;" value="' . $issue_value('credit_amount') . '"/>
                                    </label>
                                </div>
                                <div class="col-12 md:col-3">
                                    <label for="credit_last_day" class="form-label">' . h(lang('Last day')) . ' <span class="fg-secondary fw-normal">(' . h(lang('optional')) . ')</span></label>
                                    <input type="date" data-pg-date name="credit_last_day" id="credit_last_day" class="form-control' . $issue_mark('credit_last_day') . '" value="' . $issue_value('credit_last_day') . '"/>' . get_date_picker_format() . pg_fields_script() . '
                                </div>
                                <div class="col-12 md:col-4">
                                    <label for="credit_reason" class="form-label">' . h(lang('Reason')) . '</label>
                                    <input type="text" name="credit_reason" id="credit_reason" class="form-control' . $issue_mark('credit_reason') . '" maxlength="255" required value="' . $issue_value('credit_reason') . '"/>
                                </div>
                                <div class="col-12 md:col-2">
                                    <button type="submit" class="btn btn-solid theme-primary w-100">' . h(lang('Issue Credit')) . '</button>
                                </div>
                            </div>
                            <div class="form-text">' . h(lang('The customer spends store credit at checkout when signed in. Every issue and void is kept in the audit trail.')) . ' ' . h(lang('The customer sees the reason on their account page.')) . '</div>
                        </form>
                    </div>
                </div>';
}
