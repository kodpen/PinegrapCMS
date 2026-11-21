<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Customer rhythm: what the nightly pass tells. Each stage move of a
 * customer with a contact is announced as customer.rhythm_stage_changed;
 * a customer with products in their reorder window as customer.reorder_due,
 * at most once in the brake's days (7 by default). Both go through
 * pg_announce(), so webhooks and flows hear them; the plan sends no message
 * itself.
 *
 * A product is named once per purchase: only when its window opened after
 * the customer's last reminder, and only until the brake's days after its
 * due day, so a purchase long past its due day is never told. With the
 * comparison share on, one due customer in ten is held back: the reminder
 * is kept on the row as held back and nothing is announced.
 *
 * A reorder event carries the signed link that fills a cart with the
 * products named (customer_rhythm_link.php, reorder.php).
 *
 * The payload names the contact, never a name, e-mail or address. A
 * customer without a contact, or whose contact is gone, is counted in the
 * pass and never announced.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// Days between two reorder events of one customer.
define('PG_CUSTOMER_RHYTHM_BRAKE_DAYS', 7);

// One due customer in this many is held back while the comparison share is on.
define('PG_CUSTOMER_RHYTHM_SHARE_EVERY', 10);

// Months of reminder counts the product summary keeps.
define('PG_CUSTOMER_RHYTHM_MONTHS_KEPT', 24);

/**
 * The settings from their stored text (config_text
 * 'customer_rhythm_settings'), each with its default: on, the brake's
 * days, the days' lead of the reorder window and the comparison share
 * (off). Pure.
 *
 * @param string|array|null $stored
 * @return array  on (bool), brake_days, lead_days, share (bool)
 */
function pg_customer_rhythm_settings_normalize($stored)
{
    if (is_string($stored)) {
        $stored = json_decode($stored, true);
    }

    if (!is_array($stored)) {
        $stored = array();
    }

    $number = function ($key, $default, $low, $high) use ($stored) {
        return (isset($stored[$key]) && is_numeric($stored[$key])) ? (int) min($high, max($low, (int) $stored[$key])) : $default;
    };

    return array(
        'on' => !isset($stored['on']) || !empty($stored['on']),
        'brake_days' => $number('brake_days', PG_CUSTOMER_RHYTHM_BRAKE_DAYS, 1, 90),
        'lead_days' => $number('lead_days', PG_CUSTOMER_RHYTHM_LEAD_DAYS, 0, 30),
        'share' => !empty($stored['share']),
    );
}

/**
 * The site's settings.
 *
 * @return array  pg_customer_rhythm_settings_normalize()
 */
function pg_customer_rhythm_settings()
{
    return pg_customer_rhythm_settings_normalize(function_exists('pg_config_text') ? pg_config_text('customer_rhythm_settings') : '');
}

/**
 * The products to name in a reorder event, or none (H8): nothing while the
 * brake runs since the last reminder; else each due product whose window
 * opened after that reminder and whose due day is at most the brake's days
 * ago. Pure.
 *
 * @param array $due          pg_customer_rhythm_due_products()
 * @param int   $reminded_at  the last reminder, 0 for none
 * @param int   $now
 * @param array $settings     pg_customer_rhythm_settings_normalize()
 * @return array  the due products to name
 */
function pg_customer_rhythm_reorder_pick($due, $reminded_at, $now, $settings)
{
    $reminded_at = (int) $reminded_at;
    $now = (int) $now;
    $brake = (int) $settings['brake_days'] * 86400;

    if (($reminded_at > 0) && (($now - $reminded_at) < $brake)) {
        return array();
    }

    $out = array();

    foreach ((array) $due as $product) {
        $opened = (int) $product['due'] - ((int) $settings['lead_days'] * 86400);

        if (($opened > $reminded_at) && ($now >= $opened) && ($now <= ((int) $product['due'] + $brake))) {
            $out[] = $product;
        }
    }

    return $out;
}

/**
 * Whether a due customer is held back by the comparison share (H12): with
 * the share on, one in ten, chosen afresh each day from the customer key.
 * Pure.
 *
 * @param string $key
 * @param int    $now
 * @param array  $settings
 * @return bool
 */
function pg_customer_rhythm_held_back($key, $now, $settings)
{
    if (empty($settings['share'])) {
        return false;
    }

    $hash = sprintf('%u', crc32((string) $key . '|' . (int) floor((int) $now / 86400)));

    return ((int) substr($hash, -6) % PG_CUSTOMER_RHYTHM_SHARE_EVERY) === 0;
}

/**
 * The data of customer.rhythm_stage_changed, and the start of
 * customer.reorder_due's. Pure.
 *
 * @param array  $row           a customer_rhythm row as the pass writes it
 * @param string $order_number  the last order's number, '' when not found
 * @return array
 */
function pg_customer_rhythm_event_payload($row, $order_number)
{
    $before = pg_customer_rhythm_stage_name(isset($row['stage_before']) ? $row['stage_before'] : 0);
    $last = (int) $row['last_order_at'];

    return array(
        'id' => (int) $row['contact_id'],
        'stage' => pg_customer_rhythm_stage_name($row['stage']),
        'previous_stage' => ($before === '') ? null : $before,
        'interval_days' => (int) $row['interval_days'],
        'days_since_last_order' => $last ? (int) max(0, floor(((int) $row['updated_at'] - $last) / 86400)) : 0,
        'chance' => (int) $row['chance'],
        'expected_value' => (int) $row['expected_value'],
        'last_order_id' => (int) $row['last_order_id'],
        'last_order_number' => (string) $order_number,
    );
}

/**
 * The data of customer.reorder_due: the customer's, each product named
 * with its last quantity and due day, and the reorder link that puts them
 * into a cart (pg_customer_rhythm_link_url()). Pure.
 *
 * @param array  $row
 * @param string $order_number
 * @param array  $products  pg_customer_rhythm_reorder_pick()
 * @param array  $names     product_id => array(name, short_description)
 * @param string $url       the reorder link, '' when the site has no key
 * @return array
 */
function pg_customer_rhythm_reorder_payload($row, $order_number, $products, $names, $url = '')
{
    $list = array();
    $text = array();

    foreach ((array) $products as $product) {
        $id = (int) $product['product_id'];
        $name = isset($names[$id][0]) ? (string) $names[$id][0] : '';
        $description = isset($names[$id][1]) ? (string) $names[$id][1] : '';
        $list[] = array(
            'id' => $id,
            'name' => $name,
            'short_description' => $description,
            'quantity' => (int) $product['quantity'],
            'due_at' => gmdate('Y-m-d\TH:i:s\Z', (int) $product['due']),
        );
        $text[] = (int) $product['quantity'] . ' × ' . (($description !== '') ? $description : $name);
    }

    return pg_customer_rhythm_event_payload($row, $order_number) + array(
        'products' => $list,
        'products_text' => implode(', ', $text),
        'reorder_url' => (string) $url,
    );
}

/**
 * Counts reminders into the month they were made in: told, held back.
 * Pure.
 *
 * @param array $months  'YYYY-MM' => array(told, held back)
 * @param int   $time
 * @param bool  $held
 * @param int   $count
 * @return void
 */
function pg_customer_rhythm_reminder_count(&$months, $time, $held, $count = 1)
{
    $month = gmdate('Y-m', (int) $time);

    if (!isset($months[$month])) {
        $months[$month] = array(0, 0);
    }

    $months[$month][$held ? 1 : 0] += (int) $count;
}

/**
 * The reminder counts kept by the summary: the earlier ones and this pass's
 * added up, the last 24 months. Pure.
 *
 * @param array|null $old
 * @param array      $new
 * @return array  'YYYY-MM' => array(told, held back), oldest first
 */
function pg_customer_rhythm_reminders_merge($old, $new)
{
    $months = array();

    foreach (array((array) $old, (array) $new) as $part) {
        foreach ($part as $month => $pair) {
            if (!preg_match('/^\d{4}-\d{2}$/', (string) $month) || !is_array($pair)) {
                continue;
            }

            if (!isset($months[$month])) {
                $months[$month] = array(0, 0);
            }

            $months[$month][0] += (int) (isset($pair[0]) ? $pair[0] : 0);
            $months[$month][1] += (int) (isset($pair[1]) ? $pair[1] : 0);
        }
    }

    ksort($months);

    return array_slice($months, -PG_CUSTOMER_RHYTHM_MONTHS_KEPT, null, true);
}

/**
 * Tells what one written batch of the pass has to tell: each stage move,
 * and each customer with products to name in a reorder event. Only for a
 * customer whose contact is there. A reminder is written on the row
 * (reminded_at, held_back, came_back_order_id cleared) before it is
 * announced, so a run that stops between the two never tells it twice.
 *
 * @param array $made      customer key => pg_customer_rhythm_pass_row() result
 * @param array $stored    customer key => stored row (reminded_at)
 * @param array $settings  pg_customer_rhythm_settings()
 * @param int   $now       the pass start
 * @return array  moves, told, held, months (reminder counts)
 */
function pg_customer_rhythm_tell($made, $stored, $settings, $now)
{
    $out = array('moves' => 0, 'told' => 0, 'held' => 0, 'months' => array());

    if (empty($settings['on']) || !$made) {
        return $out;
    }

    $contacts = array();

    foreach ($made as $result) {
        if ((int) $result['row']['contact_id'] > 0) {
            $contacts[(int) $result['row']['contact_id']] = true;
        }
    }

    if (!$contacts) {
        return $out;
    }

    $present = array_flip(array_map('intval', (array) db_values("SELECT id FROM contacts WHERE id IN (" . implode(',', array_keys($contacts)) . ")")));
    $moves = array();
    $told = array();
    $held = array();

    foreach ($made as $key => $result) {
        if (!isset($present[(int) $result['row']['contact_id']])) {
            continue;
        }

        if ($result['moved']) {
            $moves[] = $key;
        }

        $reminded_at = isset($stored[$key]['reminded_at']) ? (int) $stored[$key]['reminded_at'] : 0;
        $products = pg_customer_rhythm_reorder_pick(isset($result['due']) ? $result['due'] : array(), $reminded_at, $now, $settings);

        if (!$products) {
            continue;
        }

        if (pg_customer_rhythm_held_back($key, $now, $settings)) {
            $held[] = (string) $key;
        } else {
            $told[$key] = $products;
        }
    }

    foreach (array(array(array_keys($told), 0), array($held, 1)) as $group) {
        if (!$group[0]) {
            continue;
        }

        $keys = array_map('strval', $group[0]);

        if (db_q("UPDATE customer_rhythm SET reminded_at = ?, held_back = ?, came_back_order_id = 0 WHERE customer_key IN (" . implode(', ', array_fill(0, count($keys), '?')) . ")", array_merge(array((int) $now, $group[1]), $keys)) === false) {
            // Not written, so not told: the next pass tries again.
            if ($group[1]) {
                $held = array();
            } else {
                $told = array();
            }

            continue;
        }

        pg_customer_rhythm_reminder_count($out['months'], $now, (bool) $group[1], count($keys));
    }

    $out['held'] = count($held);

    if (!$moves && !$told) {
        return $out;
    }

    $order_ids = array();
    $product_ids = array();

    foreach (array_merge($moves, array_keys($told)) as $key) {
        if ((int) $made[$key]['row']['last_order_id'] > 0) {
            $order_ids[(int) $made[$key]['row']['last_order_id']] = true;
        }
    }

    foreach ($told as $products) {
        foreach ($products as $product) {
            $product_ids[(int) $product['product_id']] = true;
        }
    }

    $numbers = array();

    if ($order_ids) {
        foreach ((array) db_items("SELECT id, order_number FROM orders WHERE id IN (" . implode(',', array_keys($order_ids)) . ")") as $order) {
            $numbers[(int) $order['id']] = (string) $order['order_number'];
        }
    }

    $names = array();

    if ($product_ids) {
        foreach ((array) db_items("SELECT id, name, short_description FROM products WHERE id IN (" . implode(',', array_keys($product_ids)) . ")") as $product) {
            $names[(int) $product['id']] = array((string) $product['name'], (string) $product['short_description']);
        }
    }

    $number = function ($row) use ($numbers) {
        return isset($numbers[(int) $row['last_order_id']]) ? $numbers[(int) $row['last_order_id']] : '';
    };

    foreach ($moves as $key) {
        pg_announce('customer.rhythm_stage_changed', pg_customer_rhythm_event_payload($made[$key]['row'], $number($made[$key]['row'])));
        $out['moves']++;
    }

    foreach ($told as $key => $products) {
        pg_announce('customer.reorder_due', pg_customer_rhythm_reorder_payload($made[$key]['row'], $number($made[$key]['row']), $products, $names, pg_customer_rhythm_link_url($products, $now)));
        $out['told']++;
    }

    return $out;
}
