<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The gift card recipient check of the order preview: each card a gift card
 * line issues (pg_order_gift_card_count()) is issued from its own
 * order_item_gift_cards row, so every unit needs that row with a valid
 * recipient e-mail address before the order is placed. Loaded by
 * order_preview.php.
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
 * How many cards of the given gift card lines have no recipient: no row for
 * the unit, or a row whose e-mail address is empty or not valid. Only the
 * units the line issues cards for are asked for. Pure.
 *
 * @param array $lines order item id => quantity, gift card lines only
 * @param array $rows  order_item_gift_cards rows: order_item_id,
 *                     quantity_number, recipient_email_address
 * @return int
 */
function pg_gift_card_recipients_missing(array $lines, array $rows)
{
    $recipients = array();

    foreach ($rows as $row) {
        $recipients[(int) $row['order_item_id']][(int) $row['quantity_number']] = trim((string) $row['recipient_email_address']);
    }

    $missing = 0;

    foreach ($lines as $order_item_id => $quantity) {
        $cards = pg_order_gift_card_count($quantity);

        for ($quantity_number = 1; $quantity_number <= $cards; $quantity_number++) {
            $email = $recipients[(int) $order_item_id][$quantity_number] ?? '';

            if (($email === '') || !validate_email_address($email)) {
                $missing++;
            }
        }
    }

    return $missing;
}

/**
 * How many cards the order's active gift card lines would issue without a
 * recipient (pg_gift_card_recipients_missing()).
 *
 * @param int|string $order_id
 * @return int
 */
function pg_order_gift_card_recipients_missing($order_id)
{
    $order_id = (int) $order_id;

    if ($order_id <= 0) {
        return 0;
    }

    $lines = array();

    foreach (db_items(
        "SELECT order_items.id, order_items.quantity
        FROM order_items
        JOIN products ON products.id = order_items.product_id
        WHERE
            (order_items.order_id = '" . $order_id . "')
            AND (products.gift_card = '1')"
            . pg_order_item_active_sql()) as $line) {
        $lines[(int) $line['id']] = (int) $line['quantity'];
    }

    if (!$lines) {
        return 0;
    }

    $rows = db_items(
        "SELECT order_item_id, quantity_number, recipient_email_address
        FROM order_item_gift_cards
        WHERE order_item_id IN (" . implode(', ', array_keys($lines)) . ")");

    return pg_gift_card_recipients_missing($lines, is_array($rows) ? $rows : array());
}

/**
 * The error the order preview shows when a gift card has no recipient.
 *
 * @return string
 */
function pg_gift_card_recipients_missing_error()
{
    return lang('Please enter a valid recipient email address for every gift card in your cart.');
}
