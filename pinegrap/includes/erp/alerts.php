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
 *   - Quotes about to run out: the open quotes whose date comes within the
 *     days the store chose, from the same job (8.58).
 *   - An order that was due its automatic invoice and did not get one, with
 *     the reason (erp_auto_invoice_for_order()).
 *
 * The first two are off until switched on in the ERP settings (4.91); the
 * quote notice is on until switched off; the invoice notice comes with the
 * automatic invoice itself. Nothing here
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
 * The store's choices, or the defaults (all off) before 4.91. The quote
 * notice is off until its columns are there (8.58).
 *
 * @return array  collections (bool), collection_min (kurus), low_stock (bool),
 *                quote_expiry (bool), quote_expiry_days (int, 1-30)
 */
function erp_alert_settings()
{
    static $settings = null;

    if ($settings === null) {
        $settings = array('collections' => false, 'collection_min' => 0, 'low_stock' => false, 'quote_expiry' => false, 'quote_expiry_days' => 3);

        if (function_exists('waf_table_has_column') && waf_table_has_column('config', 'erp_notify_collections')) {
            $row = db_item("SELECT erp_notify_collections, erp_notify_collection_min, erp_notify_low_stock FROM config LIMIT 1");

            if (is_array($row)) {
                $settings['collections'] = ((int) $row['erp_notify_collections'] === 1);
                $settings['collection_min'] = max(0, (int) $row['erp_notify_collection_min']);
                $settings['low_stock'] = ((int) $row['erp_notify_low_stock'] === 1);
            }
        }

        if (function_exists('waf_table_has_column') && waf_table_has_column('config', 'erp_notify_quote_expiry')) {
            $row = db_item("SELECT erp_notify_quote_expiry, erp_notify_quote_expiry_days FROM config LIMIT 1");

            if (is_array($row)) {
                $settings['quote_expiry'] = ((int) $row['erp_notify_quote_expiry'] === 1);
                $settings['quote_expiry_days'] = min(30, max(1, (int) $row['erp_notify_quote_expiry_days']));
            }
        }
    }

    return $settings;
}

/**
 * Who caused a notice: the person signed in on the panel. A call through the
 * external API or the MCP server loads no panel user, so there it is the
 * application's owner - the person signed in on a device or through OAuth,
 * the owner of an application key - the name the Workspace's bell rows carry
 * (ws_bell_user()). A scheduled job has neither.
 *
 * @param int|string $user_id   USER_ID
 * @param string     $username  USER_USERNAME
 * @param array|null $app       api_current_app()
 * @return array  id (0 for nobody), username ('' for nobody)
 */
function erp_alert_actor($user_id, $username, $app)
{
    if (((int) $user_id > 0) && ((string) $username !== '')) {
        return array('id' => (int) $user_id, 'username' => (string) $username);
    }

    $owner = (is_array($app) && isset($app['owner']) && is_array($app['owner'])) ? $app['owner'] : array();

    return array('id' => (int) ($owner['id'] ?? 0), 'username' => (string) ($owner['username'] ?? ''));
}

/**
 * erp_alert_actor() for this request.
 *
 * @return array  id, username
 */
function erp_alert_current_actor()
{
    return erp_alert_actor(
        defined('USER_ID') ? USER_ID : 0,
        defined('USER_USERNAME') ? USER_USERNAME : '',
        function_exists('api_current_app') ? api_current_app() : null
    );
}

/**
 * Write the bell row and queue its device banners.
 *
 * @param array $properties  create_notification()'s
 * @param bool  $skip_actor  true when the person who caused it
 *                           (erp_alert_current_actor()) is not to be told
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
    $actor = $skip_actor ? erp_alert_current_actor()['id'] : 0;

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
    $actor = erp_alert_current_actor()['username'];

    // The row carries what the bell says the way an order row does: title is
    // the account, form_id the receipt and order_total the amount as it was
    // received (includes/notifications.php builds the sentence).
    return erp_alert_create(array(
        'action' => 'erp_money',
        'type' => 'success',
        'title' => mb_substr(($title !== '') ? $title : lang('Walk-in sale'), 0, 200),
        'form_id' => (int) ($payload['id'] ?? 0),
        'order_total' => erp_money_out_currency((int) ($payload['amount'] ?? 0), (string) ($payload['currency'] ?? '')),
        'user' => ($actor !== '') ? $actor : 'system',
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

/**
 * The open quotes that run out within the days the store chose, today
 * included, in one notice.
 *
 * A quote is announced once (erp_quotes.expiry_notified_at); opening it
 * again, or changing the date of an open one, clears the stamp
 * (includes/erp/quotes.php), so it is announced again when its new date
 * comes near. Run from the ERP's hourly job.
 *
 * @return array ['ran' => bool, 'count' => int, 'notification_id' => int]
 */
function erp_alert_quote_expiry()
{
    $result = array('ran' => false, 'count' => 0, 'notification_id' => 0);
    $settings = erp_alert_settings();

    if (!$settings['quote_expiry'] || !function_exists('erp_quotes_ready') || !erp_quotes_ready()
        || !waf_table_has_column('erp_quotes', 'expiry_notified_at')) {
        return $result;
    }

    $result['ran'] = true;

    $days = (int) $settings['quote_expiry_days'];
    $today = date('Y-m-d');
    $last = date('Y-m-d', strtotime($today . ' +' . $days . ' days'));

    $rows = (array) db_items("SELECT id FROM erp_quotes
        WHERE status = 'open' AND valid_until BETWEEN '" . escape($today) . "' AND '" . escape($last) . "' AND expiry_notified_at = 0
        ORDER BY valid_until ASC, id ASC
        LIMIT 200");

    if (empty($rows)) {
        return $result;
    }

    $ids = array();
    foreach ($rows as $row) {
        $ids[] = (int) $row['id'];
    }

    // title is the number of quotes, form_id the first of them (named when
    // it is the only one) and order_total the days the notice looked ahead.
    $result['count'] = count($ids);
    $result['notification_id'] = erp_alert_create(array(
        'action' => 'erp_quote_expiry',
        'type' => 'warning',
        'title' => (string) count($ids),
        'form_id' => $ids[0],
        'order_total' => (string) $days,
        'user' => 'system',
    ), false);

    db("UPDATE erp_quotes SET expiry_notified_at = UNIX_TIMESTAMP() WHERE id IN (" . implode(', ', $ids) . ")");

    return $result;
}

/**
 * An order that was due its automatic invoice and did not get one
 * (erp_auto_invoice_for_order()): which order, and the reason the order
 * screen gives. Once per order and reason, so an order shipped in three
 * parcels is not announced three times for the same missing field.
 *
 * Whoever may use the ERP is told, the person whose action it followed as
 * well: they are the one who can put it right, and the screen they are on
 * may not be the order's.
 *
 * @param int    $order_id
 * @param string $order_number
 * @param string $reason
 * @return int  The notification's id, 0 when none was written
 */
function erp_alert_auto_invoice_refused($order_id, $order_number, $reason)
{
    $order_id = (int) $order_id;
    $title = mb_substr(trim((string) $reason), 0, 250);

    if (($order_id <= 0) || ($title === '')) {
        return 0;
    }

    // The look and the write run under a lock of the order's own, so two
    // refusals written at once leave one row. GET_LOCK names belong to the
    // whole server, hence the database in the name. A server that refuses
    // named locks (NULL) still gets its row; one where another request holds
    // the lock past the wait leaves the row to that request.
    $lock = "CONCAT('pg_erp_auto_invoice_alert.', MD5(CONCAT(DATABASE(), '.', '" . $order_id . "')))";
    $locked = db_value("SELECT GET_LOCK(" . $lock . ", 5)");

    if (($locked !== null) && ((int) $locked !== 1)) {
        return 0;
    }

    // title is the reason, order_id the order and order_total its number
    // (includes/notifications.php builds the sentence).
    $seen = db_value("SELECT id FROM notifications
        WHERE action = 'erp_auto_invoice' AND order_id = '" . $order_id . "' AND title = '" . escape($title) . "'
        LIMIT 1");

    $id = 0;

    if ($seen === null) {
        $actor = erp_alert_current_actor()['username'];

        $id = erp_alert_create(array(
            'action' => 'erp_auto_invoice',
            'type' => 'warning',
            'title' => $title,
            'order_id' => $order_id,
            'order_total' => mb_substr((string) $order_number, 0, 100),
            'user' => ($actor !== '') ? $actor : 'system',
        ), false);
    }

    if ($locked !== null) {
        db_value("SELECT RELEASE_LOCK(" . $lock . ")");
    }

    return $id;
}
