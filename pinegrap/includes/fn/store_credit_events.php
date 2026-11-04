<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Store credit webhook events: credit.issued, credit.used and credit.expired,
 * announced through pg_announce() by the writers of includes/fn/store_credit.php,
 * the order completion's redemption and the expiry pass. The card's code is
 * never in a payload: it can still pay at a checkout.
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
 * The payload of a store credit event (pure): the card, its account and that
 * account's customer record, money in minor units, moments in ISO-8601 UTC,
 * and what the event adds (order_id and amount_used, expired_amount).
 *
 * @param array $card   gift_cards row: id, user_id, kind, reason, amount,
 *                      balance, expires_at, and customer_id (user.user_contact)
 * @param array $extra  event fields laid over the card's
 * @return array
 */
function pg_credit_event_data(array $card, array $extra = array())
{
    $expires = (int) ($card['expires_at'] ?? 0);
    $customer = (int) ($card['customer_id'] ?? 0);

    $data = array(
        'id'          => (int) ($card['id'] ?? 0),
        'account_id'  => (int) ($card['user_id'] ?? 0),
        'customer_id' => ($customer > 0) ? $customer : null,
        'kind'        => (string) ($card['kind'] ?? ''),
        'reason'      => (string) ($card['reason'] ?? ''),
        'amount'      => (int) ($card['amount'] ?? 0),
        'balance'     => (int) ($card['balance'] ?? 0),
        'expires_at'  => ($expires > 0) ? gmdate('Y-m-d\TH:i:s\Z', $expires) : null,
    );

    foreach ($extra as $name => $value) {
        $data[$name] = is_int($value) ? $value : (is_numeric($value) ? (int) $value : $value);
    }

    return $data;
}

/**
 * Announce a store credit event for a card bound to a customer. A plain
 * gift card (user_id 0) is not store credit and is never announced.
 *
 * @param string $event  credit.issued | credit.used | credit.expired
 * @param int    $card_id
 * @param array  $extra  see pg_credit_event_data()
 */
function pg_credit_announce($event, $card_id, array $extra = array())
{
    if (((int) $card_id <= 0) || !function_exists('pg_announce') || !pg_credit_ready()) {
        return;
    }

    $card = db_item(
        "SELECT gift_cards.id, gift_cards.user_id, gift_cards.kind, gift_cards.reason, gift_cards.amount,
            gift_cards.balance, gift_cards.expires_at, COALESCE(user.user_contact, 0) AS customer_id
        FROM gift_cards
        LEFT JOIN user ON user.user_id = gift_cards.user_id
        WHERE gift_cards.id = '" . (int) $card_id . "'");

    if (!is_array($card) || ((int) $card['user_id'] <= 0)) {
        return;
    }

    pg_announce((string) $event, pg_credit_event_data($card, $extra));
}
