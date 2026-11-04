<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Store credit mails and expiry (2026.11.0): the campaign profile actions
 * credit_issued (sent when a card is written for a customer) and
 * credit_expiring (sent once, PG_CREDIT_EXPIRING_DAYS before a card ends),
 * their mail-merge fields, and the general job's passes: the notices whose
 * moment fell since the last pass, and the cards whose end has come, which
 * are closed with a note line and an audit line. No column: the notice pass
 * keeps its last pass time in config_text credit_mails_at.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** Days before a card ends that its customer is told. */
define('PG_CREDIT_EXPIRING_DAYS', 14);

/** Cards one expiry pass closes at most; the next pass takes the rest. */
define('PG_CREDIT_EXPIRE_BATCH', 200);

/**
 * The campaign profile actions of store credit, in the order the form lists
 * them.
 *
 * @return string[]
 */
function pg_credit_mail_actions()
{
    return array('credit_issued', 'credit_expiring');
}

/**
 * Whether the upgrade has added the actions to both action columns: an
 * ENUM stores a value it does not list as ''.
 *
 * @return bool
 */
function pg_credit_mail_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = pg_credit_ready();

        foreach (array('email_campaign_profiles', 'email_campaigns') as $table) {
            $column = $ready ? db_item("SHOW COLUMNS FROM " . $table . " WHERE Field = 'action'") : null;
            $ready = $ready && is_array($column) && (strpos((string) ($column['Type'] ?? ''), "'credit_expiring'") !== false);
        }
    }

    return $ready;
}

/**
 * The mail-merge fields a store credit profile fills in, as the profile
 * form lists them.
 *
 * @return array field name => what it holds
 */
function pg_credit_mail_field_meanings()
{
    return array(
        'credit_amount'      => lang('the amount of the card'),
        'credit_left'        => lang('what is left on the card'),
        'credit_balance'     => lang('the customer\'s whole store credit balance'),
        'credit_source'      => lang('where the credit came from (reward points, refund, issued by staff)'),
        'credit_reason'      => lang('the reason given for the credit'),
        'credit_last_day'    => lang('the last day the card can be used, empty when it never ends'),
        'credit_name'        => lang('the customer\'s name'),
        'credit_account_url' => lang('a link to the customer\'s account page'),
    );
}

/**
 * The mail-merge fields of a card (pure), every one of
 * pg_credit_mail_field_meanings() in the shape replace_variables() takes.
 *
 * @param array $values  amount, left, balance, source, reason, last_day,
 *                       name, account_url (text)
 * @return array
 */
function pg_credit_mail_fields(array $values)
{
    $fields = array();

    foreach (array_keys(pg_credit_mail_field_meanings()) as $name) {
        $fields[] = array(
            'name' => $name,
            'data' => (string) ($values[substr($name, strlen('credit_'))] ?? ''),
            'type' => '',
        );
    }

    return $fields;
}

/**
 * The moment a card's expiring notice is due (pure): PG_CREDIT_EXPIRING_DAYS
 * before its end, 0 when it never ends.
 *
 * @param int $expires_at
 * @return int
 */
function pg_credit_expiring_moment($expires_at)
{
    $expires_at = (int) $expires_at;

    return ($expires_at > 0) ? $expires_at - PG_CREDIT_EXPIRING_DAYS * 86400 : 0;
}

/**
 * Whether a card's expiring notice is due in this pass (pure): its moment
 * fell in (last, now], the card has not ended, has something left and was
 * written before the moment (a card given with less than the notice period
 * left is told nothing more: the issued mail named its last day).
 *
 * @param array $card  balance, expires_at, created_timestamp
 * @param int   $last  the previous pass
 * @param int   $now
 * @return bool
 */
function pg_credit_expiring_due(array $card, $last, $now)
{
    $expires = (int) ($card['expires_at'] ?? 0);
    $moment = pg_credit_expiring_moment($expires);

    return ($moment > 0)
        && ($moment > (int) $last)
        && ($moment <= (int) $now)
        && ($expires > (int) $now)
        && ((int) ($card['balance'] ?? 0) > 0)
        && ((int) ($card['created_timestamp'] ?? 0) <= $moment);
}

/**
 * The start of the window a pass reads (pure): the stored pass time, no
 * further back than two days after a long stop; a first pass looks back one
 * job tick.
 *
 * @param string $stored  config_text credit_mails_at
 * @param int    $now
 * @return int
 */
function pg_credit_mail_window_start($stored, $now)
{
    $now = (int) $now;

    return ((string) $stored !== '') ? max((int) $stored, $now - 2 * 86400) : $now - 900;
}

/**
 * Mails a card's customer for one action. Quiet: no profile of the action,
 * no address, or the actions not there yet sends nothing and stops nothing.
 *
 * @param int    $card_id
 * @param string $action  credit_issued or credit_expiring
 * @return bool  whether the campaigns were asked for
 */
function pg_credit_mail_send($card_id, $action)
{
    if (!in_array($action, pg_credit_mail_actions(), true) || !pg_credit_mail_ready()) {
        return false;
    }

    $card = db_item(
        "SELECT
            gift_cards.id,
            gift_cards.user_id,
            gift_cards.kind,
            gift_cards.reason,
            gift_cards.amount,
            gift_cards.balance,
            gift_cards.expires_at,
            gift_cards.expiration_date,
            user.user_email,
            user.user_contact,
            contacts.first_name,
            contacts.last_name
        FROM gift_cards
        INNER JOIN user ON user.user_id = gift_cards.user_id
        LEFT JOIN contacts ON contacts.id = user.user_contact
        WHERE gift_cards.id = '" . (int) $card_id . "' AND gift_cards.user_id > 0");

    if (!is_array($card) || ((string) $card['user_email'] === '')) {
        return false;
    }

    $rows = pg_credit_account_cards(array($card), array(), time());
    $kinds = pg_credit_kinds();
    $money = function ($cents) {
        return html_entity_decode(pg_format_money((int) $cents / 100, BASE_CURRENCY_SYMBOL), ENT_QUOTES, 'UTF-8');
    };
    $account = get_page_type_url('my account');

    $values = array(
        'amount'      => $money($card['amount']),
        'left'        => $money($card['balance']),
        'balance'     => $money(pg_credit_balance((int) $card['user_id'])),
        'source'      => isset($kinds[$card['kind']]) ? $kinds[$card['kind']] : '',
        'reason'      => (string) $card['reason'],
        'last_day'    => ($rows[0]['last_day'] !== '')
            ? get_absolute_time(array('timestamp' => strtotime($rows[0]['last_day']), 'type' => 'date', 'format' => 'plain_text'))
            : '',
        'name'        => trim((string) $card['first_name'] . ' ' . (string) $card['last_name']),
        'account_url' => ($account && defined('URL_SCHEME')) ? URL_SCHEME . HOSTNAME_SETTING . $account : '',
    );

    create_auto_email_campaigns(array(
        'action'           => $action,
        'campaign_item_id' => (int) $card['id'],
        'contact_id'       => (int) $card['user_contact'],
        'email_address'    => (string) $card['user_email'],
        'fields'           => pg_credit_mail_fields($values),
        'data'             => array('store_credit' => $card),
    ));

    return true;
}

/**
 * The credit_issued mail of a card just written (pg_credit_issue()). A
 * throw is logged and stops nothing: the card stands.
 *
 * @param int $card_id
 * @return void
 */
function pg_credit_mail_issued($card_id)
{
    try {
        pg_credit_mail_send((int) $card_id, 'credit_issued');
    } catch (\Throwable $exception) {
        log_activity(pg_log_lang(array(
            'string' => 'The store credit mail of card {var:1} could not be prepared: {var:2}',
            'vars'   => array((int) $card_id, $exception->getMessage()),
        )), 'UNKNOWN');
    }
}

/**
 * Close a customer's card whose end has come: its balance becomes 0 over
 * the one read (an order spending it meanwhile wins), a note line keeps what
 * was left, and the audit trail gets a revoke line.
 *
 * @param array $card  id, code, user_id, kind, amount, balance, reason,
 *                     issued_by, expires_at, notes
 * @param int   $now
 * @return bool
 */
function pg_credit_expire_card(array $card, $now)
{
    $left = (int) $card['balance'];

    if (($left <= 0) || ((int) $card['user_id'] <= 0)) {
        return false;
    }

    $line = lang(array('string' => 'Store credit expired on {var:1} with {var:2} unused.', 'vars' => array(
        date('Y-m-d', (int) $now),
        html_entity_decode(pg_format_money($left / 100, BASE_CURRENCY_SYMBOL), ENT_QUOTES, 'UTF-8'),
    )));
    $notes = trim((string) $card['notes']);
    $notes = ($notes === '') ? $line : $notes . "\n" . $line;

    $written = db_q(
        "UPDATE gift_cards SET balance = 0, notes = ?, last_modified_user_id = 0, last_modified_timestamp = ? WHERE id = ? AND balance = ?",
        array($notes, (int) $now, (int) $card['id'], $left));

    if (!$written || (mysqli_affected_rows(db::$con) !== 1)) {
        return false;
    }

    $after = $card;
    $after['balance'] = 0;
    $after['void_reason'] = 'expired';

    pg_audit('revoke', 'store_credit', (int) $card['id'], pg_credit_audit_fields($card), pg_credit_audit_fields($after), pg_credit_audit_label($card['code']));

    return true;
}

/**
 * The general job's store credit passes: the expiring notices whose moment
 * fell since the last pass, then the cards whose end has come. The pass
 * time is written before anything is sent, so a pass that dies loses its
 * notices rather than sending them twice.
 *
 * @param int $now
 * @return array  notified, expired
 */
function pg_credit_mails_run($now)
{
    $now = (int) $now;
    $out = array('notified' => 0, 'expired' => 0);

    if (!pg_credit_ready() || !function_exists('pg_config_text_save')) {
        return $out;
    }

    $last = pg_credit_mail_window_start(pg_config_text('credit_mails_at'), $now);

    if (pg_config_text_save('credit_mails_at', (string) $now)) {
        $days = PG_CREDIT_EXPIRING_DAYS * 86400;
        $rows = db_items(
            "SELECT id, balance, expires_at, created_timestamp FROM gift_cards
            WHERE
                user_id > 0
                AND balance > 0
                AND expires_at > '" . $now . "'
                AND expires_at > '" . ($last + $days) . "'
                AND expires_at <= '" . ($now + $days) . "'
            ORDER BY expires_at, id");

        foreach ((array) $rows as $card) {
            if (!pg_credit_expiring_due($card, $last, $now)) {
                continue;
            }

            try {
                if (pg_credit_mail_send((int) $card['id'], 'credit_expiring')) {
                    $out['notified']++;
                }
            } catch (\Throwable $exception) {
                log_activity(pg_log_lang(array(
                    'string' => 'The store credit mail of card {var:1} could not be prepared: {var:2}',
                    'vars'   => array((int) $card['id'], $exception->getMessage()),
                )), 'UNKNOWN');
            }
        }
    }

    $ended = db_items(
        "SELECT id, code, user_id, kind, amount, balance, reason, issued_by, expires_at, notes FROM gift_cards
        WHERE user_id > 0 AND balance > 0 AND expires_at > 0 AND expires_at <= '" . $now . "'
        ORDER BY expires_at, id
        LIMIT " . PG_CREDIT_EXPIRE_BATCH);

    foreach ((array) $ended as $card) {
        if (pg_credit_expire_card($card, $now)) {
            $out['expired']++;
        }
    }

    if ($out['expired'] > 0) {
        log_activity(pg_log_lang(array('string' => 'Store credit: {var:1} expired card(s) closed.', 'vars' => array($out['expired']))), 'UNKNOWN');
    }

    return $out;
}
