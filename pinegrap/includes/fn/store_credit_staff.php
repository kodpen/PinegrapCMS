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
 * Where a bound card stands (pure): open (spendable), used (balance 0: spent
 * or voided) or expired.
 *
 * @param array $card gift_cards row (balance, expires_at, expiration_date)
 * @param int   $now
 * @return string open, used or expired
 */
function pg_credit_staff_card_state(array $card, $now)
{
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
        "SELECT id, code, amount, balance, kind, reason, issued_by, expires_at, expiration_date, created_timestamp
        FROM gift_cards
        WHERE user_id = '" . (int) $user_id . "'
        ORDER BY id DESC
        LIMIT " . PG_CREDIT_STAFF_CARDS);

    return is_array($rows) ? $rows : array();
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
 * @return array ok, message
 */
function pg_credit_staff_post($user_id, array $post, $staff_id)
{
    if (!pg_credit_ready()) {
        return array('ok' => false, 'message' => lang('Store credit is not installed yet: the database has to be updated first.'));
    }

    $action = isset($post['pg_credit_action']) && is_string($post['pg_credit_action']) ? $post['pg_credit_action'] : '';

    if ($action === 'issue') {
        $input = pg_credit_staff_issue_input(
            $post['credit_amount'] ?? '', $post['credit_last_day'] ?? '', $post['credit_reason'] ?? '', time());

        if ($input['error'] !== '') {
            return array('ok' => false, 'message' => pg_credit_staff_issue_error($input['error']));
        }

        $card_id = pg_credit_issue((int) $user_id, $input['amount'], 'manual', $input['reason'], $input['expires'], (int) $staff_id);

        if ($card_id <= 0) {
            return array('ok' => false, 'message' => lang('The store credit could not be written.'));
        }

        log_activity(lang(array(
            'string' => 'Store credit of {var:1} was issued to user #{var:2}: {var:3}',
            'vars'   => array(pg_order_log_money($input['amount']), (int) $user_id, $input['reason']))));

        return array('ok' => true, 'message' => lang(array(
            'string' => 'Store credit of {var:1} was issued.',
            'vars'   => array(pg_order_log_money($input['amount'])))));
    }

    if ($action === 'void') {
        $card_id = (isset($post['credit_card_id']) && is_scalar($post['credit_card_id'])) ? (int) $post['credit_card_id'] : 0;
        $reason = (isset($post['credit_void_reason']) && is_scalar($post['credit_void_reason'])) ? trim((string) $post['credit_void_reason']) : '';

        // Only a card of this customer: the id comes from the page.
        $owner = ($card_id > 0) ? (int) db_value("SELECT user_id FROM gift_cards WHERE id = '" . $card_id . "'") : 0;

        if (($owner <= 0) || ($owner !== (int) $user_id)) {
            return array('ok' => false, 'message' => lang('The store credit card could not be found.'));
        }

        if ($reason === '') {
            return array('ok' => false, 'message' => lang('Enter the reason for voiding this store credit.'));
        }

        if (!pg_credit_void($card_id, $reason)) {
            return array('ok' => false, 'message' => lang('The store credit card could not be voided: it may have been used meanwhile. Check it and try again.'));
        }

        log_activity(lang(array(
            'string' => 'Store credit card #{var:1} of user #{var:2} was voided: {var:3}',
            'vars'   => array($card_id, (int) $user_id, $reason))));

        return array('ok' => true, 'message' => lang('The store credit card was voided.'));
    }

    return array('ok' => false, 'message' => lang('Invalid request.'));
}

/**
 * The Store Credit card of the customer's screen: the balance, the cards and
 * the issue form. It is its own form, so it sits outside the screen's main
 * form.
 *
 * @param int    $user_id
 * @param string $send_to
 * @param int    $now
 * @return string HTML, '' before the store credit columns exist
 */
function pg_credit_staff_panel_html($user_id, $send_to, $now)
{
    if (!pg_credit_ready()) {
        return '';
    }

    $money = function ($cents) {
        return pg_credit_staff_money($cents, BASE_CURRENCY_SYMBOL);
    };
    $keys = get_token_field()
        . '<input type="hidden" name="id" value="' . (int) $user_id . '"/>'
        . '<input type="hidden" name="send_to" value="' . h((string) $send_to) . '"/>';

    $states = array(
        'open'    => array(lang('Open'), 'theme-success'),
        'used'    => array(lang('Used'), 'theme-secondary'),
        'expired' => array(lang('Expired'), 'theme-warning'),
    );

    $rows = '';

    foreach (pg_credit_staff_cards($user_id) as $card) {
        $state = pg_credit_staff_card_state($card, $now);
        $expires = (int) $card['expires_at'];

        $rows .= '
                        <tr>
                            <td><a href="edit_gift_card.php?id=' . (int) $card['id'] . '">#' . (int) $card['id'] . '</a></td>
                            <td>' . h(pg_credit_staff_kind_label($card['kind'])) . (((string) $card['reason'] !== '') ? '<div class="small fg-secondary text-break">' . h((string) $card['reason']) . '</div>' : '') . '</td>
                            <td class="text-end">' . $money($card['amount']) . '</td>
                            <td class="text-end fw-semibold">' . $money($card['balance']) . '</td>
                            <td>' . (($expires > 0) ? h(pg_credit_expiration_date($expires)) : h(lang('Never'))) . '</td>
                            <td><span class="badge ' . $states[$state][1] . '">' . h($states[$state][0]) . '</span></td>
                            <td class="text-end">' . (($state === 'open') ? '
                                <form method="post" action="edit_user.php" class="d-flex gap-3 justify-content-end disable_shortcut" style="margin:0">' . $keys . '
                                    <input type="hidden" name="pg_credit_action" value="void"/>
                                    <input type="hidden" name="credit_card_id" value="' . (int) $card['id'] . '"/>
                                    <input type="text" name="credit_void_reason" class="form-control form-control-sm" maxlength="255" required placeholder="' . h(lang('Reason')) . '" aria-label="' . h(lang('Reason for voiding')) . '" style="max-width:12rem"/>
                                    <button type="submit" class="btn btn-sm btn-outline theme-danger" data-confirm-content="' . h(lang('The card\'s balance becomes 0 and the customer can no longer spend it.')) . '">' . h(lang('Void')) . '</button>
                                </form>' : '') . '</td>
                        </tr>';
    }

    return '
                <div class="card pg-card mb-5" id="store_credit">
                    <div class="card-header bg-reset border-0 pg-title-section d-flex justify-content-between align-items-center">
                        <span>' . h(lang('Store Credit')) . '</span>
                        <span class="small fw-normal fg-secondary">' . h(lang('Balance')) . ': <strong>' . $money(pg_credit_balance($user_id, $now)) . '</strong></span>
                    </div>
                    <div class="card-body">
                        ' . (($rows !== '') ? '<div class="table-responsive mb-5">
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
                        <form method="post" action="edit_user.php" class="disable_shortcut">' . $keys . '
                            <input type="hidden" name="pg_credit_action" value="issue"/>
                            <div class="row g-5 align-items-end">
                                <div class="col-12 md:col-3">
                                    <label for="credit_amount" class="form-label">' . h(lang('Amount')) . '</label>
                                    <label class="form-control form-adorn form-adorn-end">
                                        <span class="form-adorn-text" aria-hidden="true">' . h(BASE_CURRENCY_CODE) . '</span>
                                        <input type="text" name="credit_amount" id="credit_amount" class="form-ghost" maxlength="14" inputmode="decimal" required style="text-align: right;"/>
                                    </label>
                                </div>
                                <div class="col-12 md:col-3">
                                    <label for="credit_last_day" class="form-label">' . h(lang('Last day')) . ' <span class="fg-secondary fw-normal">(' . h(lang('optional')) . ')</span></label>
                                    <input type="date" data-pg-date name="credit_last_day" id="credit_last_day" class="form-control"/>' . get_date_picker_format() . pg_fields_script() . '
                                </div>
                                <div class="col-12 md:col-4">
                                    <label for="credit_reason" class="form-label">' . h(lang('Reason')) . '</label>
                                    <input type="text" name="credit_reason" id="credit_reason" class="form-control" maxlength="255" required/>
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
