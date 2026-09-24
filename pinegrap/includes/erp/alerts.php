<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - notices for the people who run the store, on the panel bell and on
 * the devices they subscribed (the panel as an app on the home screen).
 *
 *   - Money in: each collection of at least the amount the store chose, as
 *     soon as it is recorded - at the counter, on the receipt screen, through
 *     the API or from an online payment.
 *   - Low stock: the products that have dropped to or below their minimum
 *     since the last notice, from the ERP's scheduled job.
 *
 * Both are off until switched on in the ERP settings (4.91). Nothing here
 * delivers anything itself: the bell row goes through create_notification()
 * and the device banner through the push queue that row feeds, the channels
 * the overdue reminders use (includes/erp/notify.php). The person whose own
 * action caused a notice is not told about it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_ERP_ENTRY')) {
    exit;
}

/**
 * The store's choices, or the defaults (all off) before 4.91.
 *
 * @return array  collections (bool), collection_min (kurus), low_stock (bool)
 */
function erp_alert_settings()
{
    static $settings = null;

    if ($settings === null) {
        $settings = array('collections' => false, 'collection_min' => 0, 'low_stock' => false);

        if (function_exists('waf_table_has_column') && waf_table_has_column('config', 'erp_notify_collections')) {
            $row = db_item("SELECT erp_notify_collections, erp_notify_collection_min, erp_notify_low_stock FROM config LIMIT 1");

            if (is_array($row)) {
                $settings = array(
                    'collections' => ((int) $row['erp_notify_collections'] === 1),
                    'collection_min' => max(0, (int) $row['erp_notify_collection_min']),
                    'low_stock' => ((int) $row['erp_notify_low_stock'] === 1),
                );
            }
        }
    }

    return $settings;
}

/**
 * Write the bell row and queue its device banners.
 *
 * @param array $properties  create_notification()'s
 * @param bool  $skip_actor  true when the person signed in caused it and is
 *                           not to be told
 * @return int  The notification's id, 0 when none was written
 */
function erp_alert_create($properties, $skip_actor = true)
{
    if (!function_exists('create_notification')) {
        return 0;
    }

    $notification_id = (int) create_notification($properties);

    if ($notification_id <= 0) {
        return 0;
    }

    // Read already for whoever caused it: their bell does not show it and
    // the push sender, which asks again at send time, skips their devices.
    $actor = ($skip_actor && defined('USER_ID')) ? (int) USER_ID : 0;

    if ($actor > 0) {
        include_once(PG_FUNCTIONS_DIR . '/includes/notifications.php');

        if (pg_notification_reads_available()) {
            db("INSERT IGNORE INTO notification_reads (notification_id, user_id, timestamp)
                VALUES ('" . $notification_id . "', '" . $actor . "', UNIX_TIMESTAMP())");
        }
    }

    include_once(PG_FUNCTIONS_DIR . '/includes/push.php');

    if (function_exists('pg_push_enqueue_notification')) {
        pg_push_enqueue_notification(array(
            'id' => $notification_id,
            'action' => (string) $properties['action'],
        ));
    }

    return $notification_id;
}

/**
 * The announce door's hook (erp_event()): a collection that deserves a notice.
 *
 * @param string $event
 * @param array  $payload
 * @return int  The notification's id, 0 when none was written
 */
function erp_alert_event($event, $payload)
{
    if (((string) $event !== 'erp.receipt.created') || ((string) ($payload['direction'] ?? '') !== 'collection')) {
        return 0;
    }

    $settings = erp_alert_settings();
    $amount_base = (int) ($payload['amount_base'] ?? 0);

    if (!$settings['collections'] || ($amount_base <= 0) || ($amount_base < $settings['collection_min'])) {
        return 0;
    }

    $title = (string) db_value("SELECT title FROM erp_accounts WHERE id = '" . (int) ($payload['account_id'] ?? 0) . "' LIMIT 1");

    // The row carries what the bell says the way an order row does: title is
    // the account, form_id the receipt and order_total the amount as it was
    // received (includes/notifications.php builds the sentence).
    return erp_alert_create(array(
        'action' => 'erp_money',
        'type' => 'success',
        'title' => mb_substr(($title !== '') ? $title : lang('Walk-in sale'), 0, 200),
        'form_id' => (int) ($payload['id'] ?? 0),
        'order_total' => erp_money_out_currency((int) ($payload['amount'] ?? 0), (string) ($payload['currency'] ?? '')),
        'user' => defined('USER_USERNAME') && (USER_USERNAME !== '') ? USER_USERNAME : 'system',
    ));
}

/**
 * The products newly at or below their minimum, in one notice.
 *
 * A product is announced once when it drops; a product back above its
 * minimum is announced again the next time it drops. Run from the ERP's
 * scheduled job, so a sale anywhere - the shop, the counter, a marketplace,
 * the API - is caught within one run.
 *
 * @return array ['ran' => bool, 'count' => int, 'notification_id' => int]
 */
function erp_alert_low_stock()
{
    $result = array('ran' => false, 'count' => 0, 'notification_id' => 0);

    if (!erp_alert_settings()['low_stock'] || !function_exists('erp_stock_minimums_ready') || !erp_stock_minimums_ready()
        || !waf_table_has_column('erp_stock_minimums', 'notified_at')) {
        return $result;
    }

    $result['ran'] = true;

    // Back above the minimum (or no longer tracked, or off sale): ready to
    // be announced the next time it drops.
    db("UPDATE erp_stock_minimums m
        INNER JOIN products p ON p.id = m.product_id
        SET m.notified_at = 0
        WHERE m.notified_at > 0 AND (p.inventory <> '1' OR p.enabled <> '1' OR p.inventory_quantity > m.min_quantity)");

    $rows = (array) db_items("SELECT p.id AS product_id, p.name AS product_sku, p.short_description AS product_name, p.inventory_quantity, m.min_quantity
        FROM erp_stock_minimums m
        INNER JOIN products p ON p.id = m.product_id
        WHERE m.min_quantity > 0 AND m.notified_at = 0 AND p.inventory = '1' AND p.enabled = '1' AND p.inventory_quantity <= m.min_quantity
        ORDER BY (m.min_quantity - p.inventory_quantity) DESC, p.id ASC
        LIMIT 500");

    if (empty($rows)) {
        return $result;
    }

    $ids = array();
    foreach ($rows as $row) {
        $ids[] = (int) $row['product_id'];
    }

    // title is the number of products; product_id the first of them, the one
    // named when it is the only one.
    $result['count'] = count($rows);
    $result['notification_id'] = erp_alert_create(array(
        'action' => 'erp_low_stock',
        'type' => 'warning',
        'title' => (string) count($rows),
        'product_id' => (int) $rows[0]['product_id'],
        'user' => 'system',
    ), false);

    db("UPDATE erp_stock_minimums SET notified_at = UNIX_TIMESTAMP() WHERE product_id IN (" . implode(', ', $ids) . ")");

    return $result;
}
