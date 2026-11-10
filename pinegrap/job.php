<?php
/**
 * PineGrap - Enterprise Website Platform
 *
 * Originally developed as LiveSite by Camelback Web Architects.
 * Since 2017, maintained and evolved by Erdal Güral (Kodpen) under the name PineGrap.
 * The final LiveSite update (2019) has been integrated into PineGrap.
 * LiveSite remains available as a separate downloadable legacy version.
 *
 * @author      Camelback Web Architects
 *              Erdal Güral (Kodpen)
 * @link        https://livesite.com
 *              https://kodpen.com
 * @copyright   2001–2019 Camelback Consulting, Inc.
 *              2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// This is the general job that handles misc. things that need to run often.
// It should be run by a cron job.  Currently it does the following:
//
// - Publishing scheduled comments
// - Putting visual-designer pages on the site, and taking them off it, at
//     their scheduled times
// - Creating order abandoned auto campaigns
// - Unpublishing old calendar events so they don't appear on the calendar
//     or in search results.

require('init.php');

// Start and time limit of this process (JOB_TIME_LIMIT, else
// max_execution_time): a dispatched pass caps each tick by what is left of it
// (pg_cron_time_left()), so it keeps its place before a runner stops it.
pg_cron_clock(microtime(true));

// A background run (crontab, or the general job's dispatcher) has no user.
// Every other request is a web request and must come from a signed-in
// manager, the same gate the scheduled jobs settings sit behind; without it
// anybody could start the job from a browser.
if (!pg_cron_is_background_run()) {
    $user = validate_user();
    validate_area_access($user, 'manager');
}

// Get all scheduled comments that need to be published.
$comments = db_items(
    "SELECT
        id,
        page_id,
        item_id,
        item_type,
        created_user_id,
        publish_date_and_time
    FROM comments
    WHERE
        (publish_date_and_time != '0000-00-00 00:00:00')
        AND (publish_date_and_time <= NOW())
        AND (published = 0)
    ORDER BY publish_date_and_time");

$comments_published = 0;

// Loop through the comments in order to publish them.
foreach ($comments as $comment) {
    // Publish the comment and clear the scheduled info. The claim changes the
    // row only while it is still unpublished at the time read above: a run
    // that overlaps this one and read the same comment changes nothing and
    // sends no e-mail, so nobody is mailed about one comment twice.
    db(pg_comment_schedule_claim_sql($comment['id'], $comment['publish_date_and_time']));

    if (mysqli_affected_rows(db::$con) !== 1) {
        continue;
    }

    $comments_published++;

    // Rebuild the record's counters before the e-mails, whose subjects read
    // them; a comment that is not about a form record runs no query.
    pg_comment_refresh_record_info($comment['page_id'], $comment['item_type'], $comment['item_id']);

    send_comment_email_to_administrators($comment['id']);

    // The record's submitter is mailed only when someone else wrote the
    // comment, as on Edit Comment and the API.
    $record_id = pg_comment_record_id($comment['item_type'], $comment['item_id']);

    if ($record_id > 0) {
        $submitter = db_item(
            "SELECT
                page.comments_submitter_email_page_id,
                forms.user_id AS submitter_user_id
            FROM page
            LEFT JOIN forms ON forms.id = '" . $record_id . "'
            WHERE page.page_id = '" . (int) $comment['page_id'] . "'");

        if (
            $submitter
            && pg_comment_submitter_mail_due($comment['item_type'], $comment['item_id'], $submitter['comments_submitter_email_page_id'], (int) $comment['created_user_id'], (int) $submitter['submitter_user_id'])
        ) {
            send_comment_email_to_custom_form_submitter($comment['id']);
        }
    }

    send_comment_email_to_watchers($comment['id']);
}

// If at least one comment was published, then log activity.
if ($comments_published) {

    log_activity(lang(array('string' => 'general job published {var:1} scheduled comment(s)', 'vars' => array(pg_format_number($comments_published, 0)))), 'UNKNOWN');

}

// The rating of the products whose reviews were just published, and once a
// day the rating of every product from its reviews (includes/fn/reviews.php):
// that pass catches what no write path reported, such as a page deleted with
// its comments. A tick between passes reads one small file.
$reviewed_products = array();

foreach ($comments as $comment) {
    if ($comment['item_type'] == 'product') {
        $reviewed_products[] = (int) $comment['item_id'];
    }
}

pg_review_refresh_products($reviewed_products);

$rerated_products = pg_review_refresh_daily();

if ($rerated_products > 0) {

    log_activity(lang(array('string' => 'general job corrected the rating of {var:1} product(s) from their reviews', 'vars' => array(pg_format_number($rerated_products, 0)))), 'UNKNOWN');

}

pg_wishlist_trim_daily();

pg_stock_notify_trim_daily();

// Pre-orders whose date has come or whose stock is in end (includes/fn/
// preorder.php): the flag is cleared, the back order stays. Once an hour.
$preorders_ended = pg_preorder_end_hourly();

if ($preorders_ended > 0) {

    log_activity(lang(array('string' => 'general job ended the pre-order of {var:1} product(s)', 'vars' => array(pg_format_number($preorders_ended, 0)))), 'UNKNOWN');

}

// Contact groups filled by rules (includes/fn/audiences.php): each dynamic
// group recomputed at most once an hour, the one waiting longest first, at
// most PG_AUDIENCE_TICK_SECONDS of this tick; a big group goes on from its
// cursor next tick. A site with no dynamic group pays one small read.
$audiences = pg_audience_job_run();

if (($audiences['added'] + $audiences['removed']) > 0) {

    log_activity(lang(array('string' => 'general job recomputed {var:1} contact group(s) filled by rules: {var:2} added, {var:3} removed', 'vars' => array(pg_format_number($audiences['groups'], 0), pg_format_number($audiences['added'], 0), pg_format_number($audiences['removed'], 0)))), 'UNKNOWN');

}

// Shipping label documents whose time is up (includes/fn/shipment_labels.php):
// 30 days after delivery or cancellation, 90 days after creation at the
// latest. Once a day; the rows stay with their orders.
$shipment_labels_removed = pg_shipment_label_sweep_daily();

if ($shipment_labels_removed > 0) {

    log_activity(lang(array('string' => 'general job removed {var:1} kept shipping label document(s)', 'vars' => array(pg_format_number($shipment_labels_removed, 0)))), 'UNKNOWN');

}

// Labels of paid orders, made by the carrier label services whose automatic
// label is switched on (includes/fn/shipment_label_auto.php); never in the
// checkout request. A failure is logged by the run itself.
if (ECOMMERCE) {

    require_once(PG_FUNCTIONS_DIR . '/includes/fn/shipment_label_auto.php');

    $shipment_labels_auto = pg_shipment_label_auto_run();

    if ($shipment_labels_auto['created'] > 0) {

        log_activity(lang(array('string' => 'general job created {var:1} shipping label(s) for paid orders', 'vars' => array(pg_format_number($shipment_labels_auto['created'], 0)))), 'UNKNOWN');

    }

}

// Card data of processed orders (includes/fn/ecommerce.php): an order that
// left complete through any path loses its card number, and no security code
// stays stored. Once an hour; a tick between passes reads one small file.
$card_data_cleared = pg_order_card_data_sweep();

if ($card_data_cleared > 0) {

    log_activity(lang(array('string' => 'Card data was removed from {var:1} order(s).', 'vars' => array(pg_format_number($card_data_cleared, 0)))), 'UNKNOWN');

}

// If commerce is enabled and there is at least one enabled campaign profile
// that creates auto campaigns for abandoned orders, then check if any orders
// have been abandoned.

if (
    ECOMMERCE
    and db_value(
        "SELECT id
        FROM email_campaign_profiles
        WHERE (enabled = '1') AND (action = 'order_abandoned')
        LIMIT 1")
) {

    $six_hours_ago_timestamp = time() - 21600;

    $week_ago_timestamp = time() - 604800;

    // Get all incomplete orders that have a contact that are at least 6 hours old,
    // but not more than a week old, and where auto campaigns have not already
    // been added for it, and where the order has at least one enabled order item.
    // The week old check makes sure that we don't create a ton of campaigns for very old
    // orders the moment after an abandoned order profile is created.

    $orders = db_items(
        "SELECT
            orders.id,
            orders.contact_id,
            contacts.email_address AS contact_email_address,
            orders.order_date
        FROM orders
        LEFT JOIN contacts ON orders.contact_id = contacts.id
        WHERE
            (orders.status = 'incomplete')
            AND (orders.contact_id != '0')
            AND (orders.order_date <= '$six_hours_ago_timestamp')
            AND (orders.order_date >= '$week_ago_timestamp')
            AND (contacts.id IS NOT NULL)
            AND (contacts.email_address != '')
            AND NOT EXISTS (
                SELECT 1
                FROM email_campaigns
                WHERE email_campaigns.order_id = orders.id
                LIMIT 1)
            AND EXISTS (
                SELECT 1
                FROM order_items
                LEFT JOIN products ON order_items.product_id = products.id
                WHERE
                    (order_items.order_id = orders.id)
                    AND (products.enabled = '1')
                LIMIT 1)");

    foreach ($orders as $order) {

        // If an order abandoned auto campaign has already been created in the last
        // week for this same customer (for a different order), then don't create
        // a campaign for this order, because we don't want to annoy the customer.
        // We check for both a matching contact id and email address, because
        // the customer might have created multiple orders without being logged in,
        // which would result in multiple contacts being created.
        if (
            db_value(
                "SELECT email_campaigns.id
                FROM email_campaigns
                LEFT JOIN email_recipients ON email_campaigns.id = email_recipients.email_campaign_id
                WHERE
                    (email_campaigns.action = 'order_abandoned')
                    AND (email_campaigns.created_timestamp >= '$week_ago_timestamp')
                    AND (email_campaigns.status != 'cancelled')
                    AND
                    (
                        (email_recipients.contact_id = '" . $order['contact_id'] . "')
                        OR (email_recipients.email_address = '" . e($order['contact_email_address']) . "')
                    )
                LIMIT 1")
        ) {
            continue;
        }

        // If this customer has completed a different order after this order,
        // was created, then don't create a campaign for this order,
        // because the other order that was completed was likely related/a duplicate
        // of this order, and we don't want to annoy the customer.
        if (
            db_value(
                "SELECT orders.id
                FROM orders
                LEFT JOIN contacts ON orders.contact_id = contacts.id
                WHERE
                    (orders.status != 'incomplete')
                    AND (orders.order_date > '" . $order['order_date'] . "')
                    AND
                    (
                        (orders.contact_id = '" . $order['contact_id'] . "')
                        OR (contacts.email_address = '" . e($order['contact_email_address']) . "')
                    )
                LIMIT 1")
        ) {
            continue;
        }

        create_auto_email_campaigns(array(
            'action' => 'order_abandoned',
            'order_id' => $order['id'],
            'contact_id' => $order['contact_id']));

    }

}


// Bank transfer orders nobody paid for. An offline order is complete the
// moment it is placed, so without this the ones that never get paid sit in
// the list until somebody deletes them by hand. The setting gives the number
// of days to wait; 0 turns the sweep off, and so does a schema that has not
// been upgraded to carry paid_at.
//
// Two kinds of order are left alone: those that already carry an ERP invoice,
// because cancelling them would leave a document with no order behind it and
// the accountant decides those, and those that have shipped, because a
// dispatched parcel means the operator saw the money somewhere this software
// did not. The shipped check is done here because the admin flag passed to
// process_order_cancellation() bypasses its own shipment guard.
//
// Idempotent: a cancelled order leaves the WHERE clause, and
// process_order_cancellation() refuses an order that is already cancelled, so
// two runs that overlap do no harm. The batch is capped so one tick of the
// job never spends its whole budget here; the rest is picked up next time.

if (
    ECOMMERCE
    and defined('ECOMMERCE_OFFLINE_PAYMENT_CANCEL_DAYS')
    and (ECOMMERCE_OFFLINE_PAYMENT_CANCEL_DAYS > 0)
    and pg_schema_has('orders', 'paid_at')
) {

    $cancel_days = (int) ECOMMERCE_OFFLINE_PAYMENT_CANCEL_DAYS;

    $cancel_before_timestamp = time() - ($cancel_days * 86400);

    // An order put on the customer's account (ERP, 10.60) is an offline
    // payment too, but no transfer is coming: it is paid by the account's
    // due date, once it is invoiced. It is never cancelled here.
    $sql_not_on_account = pg_schema_has('orders', 'erp_on_account')
        ? 'AND (erp_on_account = 0)'
        : '';

    $unpaid_orders = db_items(
        "SELECT id, order_number
        FROM orders
        WHERE
            (payment_method = 'Offline Payment')
            AND (paid_at = 0)
            AND (status = 'complete')
            AND (order_date < '" . $cancel_before_timestamp . "')
            AND (COALESCE(erp_invoice_id, 0) = 0)
            " . $sql_not_on_account . "
        ORDER BY order_date ASC
        LIMIT 50");

    $cancel_reason = lang(array('string' => 'Cancelled automatically: bank transfer not received within {var:1} days.', 'vars' => array($cancel_days)));

    foreach ((array) $unpaid_orders as $unpaid_order) {

        if (_order_has_shipped($unpaid_order['id'])) {
            continue;
        }

        // Admin context so the cancellation is logged as the shop's decision,
        // no user because nobody clicked, and no refund attempt because no
        // money was ever taken.
        $response = process_order_cancellation($unpaid_order['id'], $cancel_reason, true, 0, false);

        if (($response['status'] ?? '') != 'success') {
            continue;
        }

        log_activity(lang(array('string' => 'general job cancelled unpaid bank transfer order #{var:1} after {var:2} day(s)', 'vars' => array($unpaid_order['order_number'], $cancel_days))), 'UNKNOWN');

    }

}

// Check to see if there are any old calendar events that need to be unpublished,
// so that they do not appear on the calendar anymore or in the search.

unpublish_events();

function unpublish_events() {

    // If calendars is disabled in the site settings, then abort.
    if (!CALENDARS) {
        return;
    }

    // Get the last time that this check was run so we can determine if we do it now.
    // We only unpublish events once per day even though the general job
    // might run more often, like once every 5 minutes, so that the cron job
    // do not cause unnecessary load on the server.

    $unpublish_event_timestamp = db_value("SELECT unpublish_event_timestamp FROM config");

    $current_timestamp = time();

    // If we have already performed this check in the last day, then abort.
    if ($unpublish_event_timestamp > ($current_timestamp - 86400)) {
        return;
    }

    // Get all events that are currently published and that might need to be unpublished.
    $events = db_items(
        "SELECT id
        FROM calendar_events
        WHERE
            (published = '1')
            AND (unpublish_days != '0')");

    foreach ($events as $event) {

        $event = get_calendar_event($event['id'], 0);

        // If the event is a recurring event, then get info for the last recurrence.
        // because we need to figure out if the last recurrence is old enough.
        if ($event['recurrence']) {
            $event = get_calendar_event($event['id'], $event['total_recurrence_number']);
        }

        // Calculate when the event should be allowed to be unpublished.
        $unpublish_timestamp = strtotime($event['end_date_and_time']) + ($event['unpublish_days'] * 86400);

        // If the event is old enough, then unpublish it.
        if ($current_timestamp >= $unpublish_timestamp) {

            db(
                "UPDATE calendar_events
                SET
                    published = '0',
                    last_modified_user_id = '" . USER_ID . "',
                    last_modified_timestamp = UNIX_TIMESTAMP()
                WHERE id = '" . $event['id'] . "'");

            log_activity(lang(array('string' => '{var:1} ({var:2}) was unpublished by general job because event was old', 'vars' => array(lang('calendar event'), $event['name']))));

        }

    }

    // Remember that we just checked if events needed to be unpublished,
    // so that we don't check again for 24 hours.
    db("UPDATE config SET unpublish_event_timestamp = UNIX_TIMESTAMP()");
}

// If MailChimp and commerce is enabled, then sync product groups, products, and orders with
// MailChimp.
if (MAILCHIMP and ECOMMERCE) {
    require_once(dirname(__FILE__) . '/mailchimp.php');
    mailchimp_sync();
}

// API housekeeping: age out the request log, the rate buckets and the stored
// idempotent answers. Throttled to once a day inside the function, so running
// it on every tick costs one small SELECT.
require_once(dirname(__FILE__) . '/includes/api/maintenance.php');
api_maintenance_purge();

// Visitor records past the retention period of the SEO settings
// (includes/fn/visitor_retention.php): each day added up into the daily
// summary, then its rows removed a chunk at a time, then the IP address
// taken out of the visits older than its own, shorter period
// (includes/fn/visitor_ip.php). Once an hour when the table is down to its
// period, every tick while rows are still waiting; a look that finds nothing
// to do is one config read and one file read.
//
// The budget keeps a table of millions from holding up the rest of this tick:
// the backlog of a large site goes over the next ticks, a few hundred
// thousand rows each.
if (pg_visitor_cleanup_due()) {

    $visitor_cleanup = pg_visitor_cleanup_run(15);

    if ($visitor_cleanup['deleted'] > 0) {
        log_activity(lang(array(
            'string' => 'general job removed {var:1} visitor record(s) older than {var:2} days',
            'vars'   => array(pg_format_number($visitor_cleanup['deleted'], 0), $visitor_cleanup['days']),
        )), 'UNKNOWN');
    }

    if ($visitor_cleanup['ip_cleared'] > 0) {
        log_activity(lang(array(
            'string' => 'general job took the IP address out of {var:1} visitor record(s) older than {var:2} days',
            'vars'   => array(pg_format_number($visitor_cleanup['ip_cleared'], 0), $visitor_cleanup['ip_days']),
        )), 'UNKNOWN');
    }

}

// Image derivatives (includes/image_engine.php) of files that were deleted
// or have changed since, and a cache the purge set aside
// (pg_image_derivative_sweep_run()): once a day, ten seconds a tick until
// the pass is through. A tick with nothing due reads one small file.
if (pg_image_derivative_sweep_due(pg_image_derivative_directory())) {

    $image_sweep = pg_image_derivative_sweep_run(pg_image_derivative_directory(), 10);

    if ($image_sweep['files'] > 0) {
        log_activity(lang(array(
            'string' => 'general job removed {var:1} image derivative file(s) of deleted or changed files ({var:2})',
            'vars'   => array(pg_format_number($image_sweep['files'], 0), convert_bytes_to_string($image_sweep['bytes'], 1)),
        )), 'UNKNOWN');
    }
}

// WebP at a picture's own address behind a CDN or proxy
// (includes/fn/image_vary_check.php): the token-free self-test through the
// site's own address, once after the switch or the confirmation changes,
// then daily while the switch is on, confirmed or not. It sets the
// confirmation only when both copies come back out of the edge's cache,
// takes it back when they do not, and sends the original when the edge keeps
// even private answers. A tick with nothing due reads one small file.
require_once(PG_FUNCTIONS_DIR . '/includes/fn/image_vary_check.php');
pg_image_vary_test_daily();

// Uploaded files to the object store (includes/fn/file_storage.php): while
// the switch is on, the newest local files first, twenty files or twenty
// seconds a tick, each checked by its size in the store before its bytes
// leave the disk. A file that fails three passes stays local and is named in
// the Site Log. "Bring back" on the Storage card turns the walk round: the
// stored files are fetched home the same way. Off or paused, this is one
// settings read.
$file_move = pg_file_move_run(20, 20);

if ($file_move['moved'] > 0) {
    $file_move_vars = array(pg_format_number($file_move['moved'], 0), convert_bytes_to_string($file_move['bytes'], 1));

    log_activity(($file_move['direction'] === 'back')
        ? lang(array('string' => 'general job brought {var:1} file(s) back from the object store ({var:2})', 'vars' => $file_move_vars))
        : lang(array('string' => 'general job moved {var:1} file(s) to the object store ({var:2})', 'vars' => $file_move_vars)), 'UNKNOWN');
}

// A stored file whose name has bytes on the disk again was replaced: its
// row goes back to local and the pass above sends the new bytes later
// (pg_file_storage_reconcile_run(), 500 rows a tick). Once a day the objects
// no row names any more go: every delete door removes only the row
// (pg_file_storage_orphan_sweep()).
pg_file_storage_reconcile_run(500);

$file_orphans = pg_file_storage_orphan_sweep();

if ($file_orphans > 0) {
    log_activity(lang(array(
        'string' => 'general job deleted {var:1} object(s) of deleted or replaced files from the object store',
        'vars'   => array(pg_format_number($file_orphans, 0)),
    )), 'UNKNOWN');
}

// Copies of stored files fetched for readers that need a path
// (pg_file_local_copy()): once an hour, those unused for a week and then the
// least recently used above 1 GB go.
pg_file_cache_sweep();

// Workspace previews used to be drawn into data/temp/workspace_previews;
// they come from the image derivative cache now (ws_file_preview()). What an
// earlier version left there goes, a few hundred files a tick.
$ws_old_previews = PG_FUNCTIONS_DIR . '/data/temp/workspace_previews';

if (is_dir($ws_old_previews)) {

    foreach (array_slice((array) @glob($ws_old_previews . '/*'), 0, 500) as $ws_old_preview) {
        if (is_file($ws_old_preview)) {
            @unlink($ws_old_preview);
        }
    }

    @rmdir($ws_old_previews);
}

// The 320 px WebP copies panel lists draw their thumbnails from
// (pg_image_panel_thumb_url()), newest files first, five seconds a tick,
// while the server writes WebP and it is offered; once through, a new pass
// after a day.
$image_thumbs_directory = pg_image_derivative_directory();

if (($image_thumbs_directory !== '') && pg_image_warmup_due(pg_image_warmup_state($image_thumbs_directory, 'thumbs'), time())) {

    $image_thumbs_formats = pg_image_delivery_formats();

    if (!empty($image_thumbs_formats['webp'])) {

        $image_thumbs = pg_image_warmup_run($image_thumbs_directory, 5, null, null, null, 'thumbs');

        if ($image_thumbs['made'] > 0) {
            log_activity(lang(array(
                'string' => 'general job made {var:1} panel thumbnails ahead of the File Manager',
                'vars'   => array(pg_format_number($image_thumbs['made'], 0)),
            )), 'UNKNOWN');
        }
    }
}

// WebP copies of JPEG and PNG files served at their own address, made ahead
// of the first visitor (pg_image_warmup_run()): largest first, ten seconds a
// tick, while the setting is on and the server writes WebP; once through, a
// new pass after a day. A tick with nothing due reads one small file.
if (pg_image_warmup_wanted(pg_image_derivative_formats_bits())) {

    $image_warmup_directory = pg_image_derivative_directory();

    if (($image_warmup_directory !== '') && pg_image_warmup_due(pg_image_warmup_state($image_warmup_directory), time())) {

        $image_delivery_formats = pg_image_delivery_formats();

        if (!empty($image_delivery_formats['webp'])) {

            $image_warmup = pg_image_warmup_run($image_warmup_directory, 10);

            if ($image_warmup['made'] > 0) {
                log_activity(lang(array(
                    'string' => 'general job made {var:1} WebP image copies ahead of visitors',
                    'vars'   => array(pg_format_number($image_warmup['made'], 0)),
                )), 'UNKNOWN');
            }
        }
    }
}

// The page cache (includes/fn/page_cache.php): the folders of old
// generations and of a purge, and copies past twice their lifetime; at most
// every ten minutes, ten seconds a tick. With nothing to do it reads one small
// file.
if (function_exists('pg_page_cache_sweep_run')) {

    $page_cache_sweep = pg_page_cache_sweep_run(10);

    if ($page_cache_sweep['files'] > 0) {
        log_activity(lang(array(
            'string' => 'general job removed {var:1} page cache file(s)',
            'vars'   => array(pg_format_number($page_cache_sweep['files'], 0)),
        )), 'UNKNOWN');
    }
}

// Scheduled pages (includes/fn/page_schedule.php): a draft whose publish time
// has come goes on the site, a page whose time to come off has come goes back
// to the drafts, and the time is cleared. Every tick, so a page goes live
// within one tick of its time; with nothing due it is two indexed reads.
if (function_exists('pg_page_schedule_run')) {

    $page_schedule = pg_page_schedule_run();

    foreach ($page_schedule['published'] as $page_schedule_name) {
        log_activity(lang(array('string' => 'page ({var:1}) was published at its scheduled time', 'vars' => array($page_schedule_name))), 'UNKNOWN');
    }

    foreach ($page_schedule['unpublished'] as $page_schedule_name) {
        log_activity(lang(array('string' => 'page ({var:1}) was taken off the site at its scheduled time', 'vars' => array($page_schedule_name))), 'UNKNOWN');
    }

    foreach ($page_schedule['home'] as $page_schedule_name) {
        log_activity(lang(array('string' => 'page ({var:1}) is the home page, so it stayed on the site at its scheduled time', 'vars' => array($page_schedule_name))), 'UNKNOWN');
    }
}

// Content imports (includes/fn/importers.php, loaded only here and on
// import.php): a running import is worked for a short budget every tick, so
// it carries on with the screen closed, and what imports finished more than
// 30 days ago made can no longer be undone. With no import it is one read of
// a small table.
require_once(PG_FUNCTIONS_DIR . '/includes/fn/importers.php');

$import_tick = pg_import_job_tick(pg_cron_time_left(20));

if ($import_tick['swept'] > 0) {
    log_activity(lang(array(
        'string' => 'general job ended the undo of {var:1} imported item(s) older than 30 days',
        'vars'   => array(pg_format_number($import_tick['swept'], 0)),
    )), 'UNKNOWN');
}

// The bulk move to the visual designer (includes/fn/legacy_to_designer_run.php):
// a move started on its screen is worked for a short budget every tick, so it
// carries on with the screen closed. With none it is one missing file, and the
// converters are not loaded (the path is pg_ltd_state_path()'s).
if (is_file(PG_FUNCTIONS_DIR . '/data/temp/legacy_to_designer.json')) {
    require_once(PG_FUNCTIONS_DIR . '/includes/fn/legacy_to_designer_run.php');
    pg_ltd_job_tick(pg_cron_time_left(20));
}

// Page revisions (includes/fn/page_revisions.php): once a day the rows of
// deleted pages go, and every page holding more revisions than the count rule
// keeps is trimmed by the age rule. A tick with nothing due reads one small
// file.
if (function_exists('pg_page_revisions_sweep_due') && pg_page_revisions_sweep_due()) {

    $revision_sweep = pg_page_revisions_sweep();

    if (($revision_sweep['orphans'] + $revision_sweep['trimmed']) > 0) {
        log_activity(lang(array(
            'string' => 'general job removed {var:1} page revision(s): {var:2} of deleted pages, {var:3} past the retention period',
            'vars'   => array(
                pg_format_number($revision_sweep['orphans'] + $revision_sweep['trimmed'], 0),
                pg_format_number($revision_sweep['orphans'], 0),
                pg_format_number($revision_sweep['trimmed'], 0)),
        )), 'UNKNOWN');
    }
}

// Collection item revisions (includes/fn/record_revisions.php): once a day
// the rows of deleted items and forms go, and every item holding more
// revisions than the count rule keeps is trimmed by the age rule. A tick
// with nothing due reads one small file.
if (function_exists('pg_record_revisions_sweep_due') && pg_record_revisions_sweep_due()) {

    $record_revision_sweep = pg_record_revisions_sweep();

    if (($record_revision_sweep['orphans'] + $record_revision_sweep['trimmed']) > 0) {
        log_activity(lang(array(
            'string' => 'general job removed {var:1} item revision(s): {var:2} of deleted items, {var:3} past the retention period',
            'vars'   => array(
                pg_format_number($record_revision_sweep['orphans'] + $record_revision_sweep['trimmed'], 0),
                pg_format_number($record_revision_sweep['orphans'], 0),
                pg_format_number($record_revision_sweep['trimmed'], 0)),
        )), 'UNKNOWN');
    }
}

// The CA store (includes/fn/update.php): once a day the server's own
// certificate store is probed with one short request, so that outbound
// connections move to the bundled data/cacert.pem when that store fails and
// back when it heals. With the CURL_CA_BUNDLE override set nothing runs.
if (function_exists('pg_ca_bundle_probe_daily')) {
    pg_ca_bundle_probe_daily();
}

// The 404 log (includes/fn/not_found_log.php): once a day the addresses not
// asked for in 90 days go, then the row cap. A tick with nothing due reads
// one small file.
if (function_exists('pg_not_found_log_sweep_due') && pg_not_found_log_sweep_due()) {

    $not_found_removed = pg_not_found_log_sweep();

    if ($not_found_removed > 0) {
        log_activity(lang(array(
            'string' => 'general job removed {var:1} address(es) from the 404 log',
            'vars'   => array(pg_format_number($not_found_removed, 0)),
        )), 'UNKNOWN');
    }
}

// The site search rollup (includes/fn/site_search_stats.php): once a day the
// days older than the retention period go.
if (function_exists('pg_site_search_sweep_due') && pg_site_search_sweep_due()) {

    $site_search_removed = pg_site_search_sweep();

    if ($site_search_removed > 0) {
        log_activity(lang(array(
            'string' => 'general job removed {var:1} row(s) from the site search statistics',
            'vars'   => array(pg_format_number($site_search_removed, 0)),
        )), 'UNKNOWN');
    }
}

// The page speed rollup (includes/fn/page_vitals.php): once a day the days
// older than the retention period go.
if (function_exists('pg_vitals_sweep_due') && pg_vitals_sweep_due()) {

    $page_vitals_removed = pg_vitals_sweep();

    if ($page_vitals_removed > 0) {
        log_activity(lang(array(
            'string' => 'general job removed {var:1} row(s) from the page speed statistics',
            'vars'   => array(pg_format_number($page_vitals_removed, 0)),
        )), 'UNKNOWN');
    }
}

// The AI fetch rollup (includes/fn/ai_visibility.php): once a day the days
// older than the retention period go.
if (function_exists('pg_ai_fetch_sweep_due') && pg_ai_fetch_sweep_due()) {

    $ai_fetch_removed = pg_ai_fetch_sweep();

    if ($ai_fetch_removed > 0) {
        log_activity(lang(array(
            'string' => 'general job removed {var:1} row(s) from the AI fetch statistics',
            'vars'   => array(pg_format_number($ai_fetch_removed, 0)),
        )), 'UNKNOWN');
    }
}

// Image versions (includes/fn/image_editor.php): once a day the versions of
// files deleted where no direct call took them along. A tick with nothing
// due reads one small file.
if (function_exists('pg_file_versions_sweep_due') && pg_file_versions_sweep_due()) {

    $file_versions_removed = pg_file_versions_sweep();

    if ($file_versions_removed > 0) {
        log_activity(lang(array(
            'string' => 'general job removed {var:1} image version(s) of deleted files',
            'vars'   => array(pg_format_number($file_versions_removed, 0)),
        )), 'UNKNOWN');
    }
}

// The audit trail past the years of the Security screen and the consent
// log's history past three years (includes/fn/privacy_retention.php): once a
// day, five seconds a tick until the pass is through. A tick with nothing
// due reads one small file.
if (pg_privacy_retention_due()) {

    $privacy_retention = pg_privacy_retention_run(5);

    if (($privacy_retention['audit'] + $privacy_retention['consent']) > 0) {
        log_activity(lang(array(
            'string' => 'general job removed {var:1} audit trail line(s) and {var:2} consent log line(s) past their retention period',
            'vars'   => array(pg_format_number($privacy_retention['audit'], 0), pg_format_number($privacy_retention['consent'], 0)),
        )), 'UNKNOWN');
    }
}

// Cancelled appointment bookings past 400 days since their last change
// (includes/fn/booking_retention.php): once a day, five seconds a tick until
// the pass is through. Nothing to do on a site without the booking tables; a
// tick with nothing due reads one small file.
if (pg_booking_retention_due()) {

    $booking_retention = pg_booking_retention_run(5);

    if ($booking_retention['removed'] > 0) {
        log_activity(lang(array(
            'string' => 'general job removed {var:1} cancelled booking(s) older than {var:2} days',
            'vars'   => array(pg_format_number($booking_retention['removed'], 0), pg_booking_retention_limits()['days']),
        )), 'UNKNOWN');
    }
}

// The price visitors are shown under the offers, into the product price
// history (pg_price_history_offer_pass_run()): once an hour, ten seconds a
// tick until the pass is through. A tick with nothing due reads one small
// file; a shop with no offer running reads its offers once an hour.
if (pg_price_history_offer_pass_due()) {
    pg_price_history_offer_pass_run(PG_PRICE_HISTORY_OFFER_BUDGET);
}

// Product price history rows older than thirteen months
// (pg_price_history_trim_run()): once a day, five seconds a tick until the
// pass is through. Each product and channel keeps the newest row before that
// point and the newest row a writer wrote. A tick with nothing due reads one
// small file.
if (pg_price_history_trim_due()) {

    $price_history_trim = pg_price_history_trim_run(PG_PRICE_HISTORY_TRIM_BUDGET);

    if ($price_history_trim['removed'] > 0) {
        log_activity(lang(array(
            'string' => 'general job removed {var:1} product price history row(s) older than {var:2} days',
            'vars'   => array(pg_format_number($price_history_trim['removed'], 0), PG_PRICE_HISTORY_KEEP_DAYS),
        )), 'UNKNOWN');
    }
}

// A visitors table still on MyISAM is moved to InnoDB once the cleanup has
// brought it down to its period: an InnoDB copy built next to it a chunk at a
// time, put in its place with one RENAME (pg_visitor_move_run()). Not in a
// tick whose cleanup still has rows waiting, which keeps the tick short. A
// tick with nothing under way reads one file; the look itself, a handful of
// small reads, comes once an hour (once a day when the table is not on
// MyISAM).
if (pg_visitor_move_due() && (!isset($visitor_cleanup) || ($visitor_cleanup['state'] !== 'more'))) {

    $visitor_move = pg_visitor_move_run(15);

    if ($visitor_move['text'] !== '') {
        log_activity($visitor_move['text'], 'UNKNOWN');
    }

}

// A visitors table split into months gets the coming months ahead of time
// (pg_visitor_partition_open_run()), so the visits of a new month never land
// in the partition for later visits. Once a day; a look is one read of the
// table's partitions, and opening a month a moment.
if (pg_visitor_partition_due()) {

    $visitor_months = pg_visitor_partition_open_run();

    if ($visitor_months['text'] !== '') {
        log_activity($visitor_months['text'], 'UNKNOWN');
    }

}

// Scheduled-task health: record that this job finished. See pg_cron_ran().
pg_cron_ran('job');

// Webhook deliveries, when the operator has switched them on.
//
// Run here rather than through the dispatcher below: that hands out one job per
// tick, so an event would wait its turn behind every other due job in its lane,
// and a notification that arrives late is most of the way to one that did not
// arrive.
// The cost when nothing is queued is one indexed read, which is why it can
// afford a turn on every tick.
//
// The time budget is deliberately small. This is the general job; a receiver
// that has stopped answering must not be allowed to spend the whole tick here.
if (pg_cron_job_is_enabled('api_webhook_job')) {

    require_once(dirname(__FILE__) . '/includes/api/outbound/webhooks.php');

    api_webhook_dispatch(15);

    pg_cron_ran('api_webhook_job');

}

// Device notifications, when the operator has switched them on. Same shape and
// same reasoning as the webhook pass above: the queue is empty on a site where
// nobody subscribed a device, which costs one indexed read.
if (pg_cron_job_is_enabled('push_job')) {

    require_once(dirname(__FILE__) . '/includes/push.php');

    pg_push_queue_run(10);

    pg_cron_ran('push_job');

}

// Booking reminders (pg_booking_reminders_run() in
// includes/fn/booking_messages.php): the confirmed bookings whose reminder
// moment fell since the last pass get their mail and text. Before the mail
// and SMS passes, so a reminder made now goes out in this run. Nothing to do
// on a site without the booking tables; otherwise one indexed read.
if (function_exists('pg_booking_reminders_run')) {

    pg_booking_reminders_run(time());

}

// Store credit (pg_credit_mails_run() in includes/fn/store_credit_mails.php):
// the credit_expiring notices whose moment fell since the last pass, then the
// customer cards whose end has come are closed. Before the mail pass, so a
// notice made now goes out in this run.
if (function_exists('pg_credit_mails_run')) {

    pg_credit_mails_run(time());

}

// Queued e-mail (includes/fn/mail_queue.php): password resets, order receipts,
// form and comment notifications that email() handed over instead of sending
// inside the visitor's request.
//
// Unconditional, unlike the two passes above: email() queues a message only
// while this job keeps finishing, so this pass is the only thing that sends
// those rows and must not depend on a setting. With nothing due it is one
// indexed read. The budget is kept small for the same reason as the webhook
// pass: an SMTP server that does not answer costs its full timeout per
// message, and what is left over waits for the next tick.
if (pg_mail_queue_ready()) {

    pg_mail_queue_run(25, 20);

    pg_cron_ran('mail_job');

}

// The Fediverse outbox (includes/fn/activitypub_outbox.php): what the site
// publishes is compared with what its followers were sent, the difference
// queued as Create, Update or Delete, and the deliveries that are due are
// signed and posted, retried on the mail queue's ladder. Only on a site that
// switched the feature on; a small budget, as the mail pass, because a
// follower's server that does not answer costs its full timeout.
if (pg_config_text('ap_settings') !== '') {

    require_once(PG_FUNCTIONS_DIR . '/includes/fn/activitypub_outbox.php');

    pg_ap_outbox_run(25);

}

// Queued text messages (pg_sms_queue_run() in includes/fn/sms.php). A request
// only queues a message, since the provider's answer is not worth a visitor's
// wait, so apart from the queue screen's Run now this pass is what sends
// them, and it depends on no setting. An empty queue, which is every site
// without SMS, costs one read. Fewer rows and less time than the mail pass:
// each message is a paid call that waits on the provider.
if (pg_sms_queue_ready()) {

    // A due SMS campaign is queued first, so one that starts now goes out
    // in this run (includes/fn/sms_campaigns.php).
    require_once(PG_FUNCTIONS_DIR . '/includes/fn/sms_campaigns.php');
    pg_sms_campaigns_run_due();

    pg_sms_queue_run(10, 15);

}

// Workspace task reminders, and the notice that a task went overdue
// (includes/workspace/reminders.php). Each look is one read; the workspace is
// loaded only when one of them is due, so the general job stays as light as
// it was on every other tick.
if (is_file(dirname(__FILE__) . '/includes/workspace/reminders.php')) {

    require_once(dirname(__FILE__) . '/includes/workspace/reminders.php');

    $workspace_overdue_due = defined('WORKSPACE_ENABLED') && WORKSPACE_ENABLED && ws_task_overdue_due();

    if (ws_task_reminders_waiting() || $workspace_overdue_due) {
        require_once(dirname(__FILE__) . '/includes/workspace/bootstrap.php');
        ws_task_reminders_run();

        if ($workspace_overdue_due) {
            ws_task_overdue_run();
        }
    }

}

// Workspace scheduled actions whose time has come, and the starts one action
// gave another (includes/workspace/scheduled.php), and the boxes of the
// flows' runs that are due, with a job's longer time budget
// (includes/workspace/flow_runs.php).
//
// Run on every tick rather than left to the workspace's own scheduled job:
// that one waits for its turn in the dispatcher's rotation below, so an action
// set for a given minute could run the better part of an hour late. With the
// general job scheduled every minute, an action now runs within a minute of
// its time even when nobody has the workspace open.
//
// The module is loaded only while it is switched on, and the look is two
// indexed reads. Each action is claimed before it runs, so two ticks that
// overlap cannot carry out the same one twice; a backlog is worked off a few
// actions per tick (WS_SCHEDULED_BATCH).
if (
    defined('WORKSPACE_ENABLED') && WORKSPACE_ENABLED
    && is_file(dirname(__FILE__) . '/includes/workspace/bootstrap.php')
) {

    require_once(dirname(__FILE__) . '/includes/workspace/bootstrap.php');

    if (ws_enabled() && function_exists('ws_scheduled_due') && ws_scheduled_due()) {
        ws_scheduled_run(WS_SCHEDULED_BATCH, WS_FLOW_BUDGET_JOB);
    }

    // The agents' scheduled wakes (includes/workspace/agent_wake.php) on
    // every tick, for the same reason: "every 30m" or "at 21:00" must not
    // wait for the dispatcher's rotation. One small read when none is due.
    if (ws_enabled() && function_exists('ws_agent_wakes_due') && ws_agent_wakes_due()) {
        ws_agent_wakes_run();
    }

    // The tags of the notes and workflow table rows saved before ws_refs
    // took them (includes/workspace/refs.php), a few seconds a tick until
    // they are through; then one small read of the marker.
    if (ws_enabled()) {
        ws_ref_sources_backfill_run();
    }

}

// Workspace mailboxes read over IMAP (includes/workspace/mail_fetch.php), on
// every tick for the same reason as the scheduled actions above: the
// dispatcher's rotation would leave a customer's mail waiting its turn. One
// small read on a site with no mailbox that has a server; the reader and
// its IMAP client are loaded only when one exists. The budget keeps the
// general job short: a box needs fifteen seconds at most, and the boxes left
// over are read on the next tick, longest waiting first.
if (
    defined('WORKSPACE_ENABLED') && WORKSPACE_ENABLED
    && function_exists('ws_mail_ready') && ws_enabled() && ws_mail_ready()
    && db_value("SELECT id FROM ws_mail_boxes WHERE enabled = 1 AND transport = 'imap' AND settings LIKE '%\"host\":\"_%' LIMIT 1")
) {

    require_once(dirname(__FILE__) . '/includes/workspace/mail_fetch.php');

    ws_mail_fetch_run(20);

    pg_cron_ran('workspace_mail_job');

}

// The visitor location database (includes/fn/geo.php): this month's list,
// fetched while a setting reads the visitor's location. The look is a file
// read; the fetch happens about once a month, and a failed one is tried again
// twelve hours later, not on every tick.
// Google reviews (includes/fn/google_reviews.php): the kept answers past
// their fresh hour are read again; nothing happens without a key and a
// Place ID.
if (function_exists('pg_grev_job_run')) {

    pg_grev_job_run();

}

if (function_exists('pg_geo_db_due') && pg_geo_db_due()) {

    pg_geo_db_refresh();

}

// The firewall's pre-database ban mirror (waf_ban_shield_refresh() in
// waf.php). A web request refreshes it only when it is not banned, and the
// firewall does not run on the command line, so on a quiet site a lifted ban
// stayed in the file for hours; the job rewrites it on every tick.
if (function_exists('waf_ban_shield_refresh')) {

    waf_ban_shield_refresh(true);

}

// Operations alerts (includes/fn/ops_alerts.php): a backup gone stale, a
// scheduled task that stopped, software files that no longer match the
// release - to the bell, the subscribed devices and the alert recipients, at
// most once a day per problem. The checks run at most every ten minutes and
// skip a tick that overlaps a slow pass; with alerts off, or between passes,
// this is one constant read and one small file read. The alert mail is
// queued, so the next tick's mail pass sends it.
pg_ops_alerts_run();

// The file of an InnoDB visitors table keeps the room of the rows the cleanup
// removed. Once a large share of it is free, the table is rebuilt online in
// the quietest hour of the day, which writes a file holding only the rows that
// are left (pg_visitor_shrink_run()). Here, after the passes that deliver mail
// and webhooks, because the rebuild is one statement that can take minutes on
// a large table. A tick reads one file; the look itself comes once an hour and
// is one small read outside the quietest hour.
if (pg_visitor_shrink_due()) {

    $visitor_shrink = pg_visitor_shrink_run(false);

    if ($visitor_shrink['text'] !== '') {
        log_activity($visitor_shrink['text'], 'UNKNOWN');
    }

}

// Optional dispatcher: at most one other scheduled job per tick, and only as
// the very last thing this script does. Several of those scripts call exit()
// from inside their own control flow, which ends this process too - harmless
// here, because there is nothing left to run and the completion above is
// already recorded. That is also why a tick never includes a second job.
// Nothing happens at all until an operator selects jobs on the settings
// screen.
//
// The lock is the chosen job's own, and jobs share two lanes: while a heavy
// job such as the backup holds its lane, the next ticks still hand out the
// light jobs, and the same job is never started twice. See
// pg_cron_dispatch_next().
//
// The include is at global scope on purpose. Done from inside a function, the
// job's top-level code would run in that function's local scope, and every
// variable it set would be invisible to the functions it calls.
// Migrate a batch of any remaining legacy-MD5 passwords to wrapped hashes. Config
// gated, so it is one tiny query once the table is done. See pg_password_wrap_run().
pg_password_wrap_run();

$dispatch_script = pg_cron_dispatch_next();

if ($dispatch_script !== '') {

    // Registered before the include so the job's lock is released even when
    // the job exits from the middle of its own flow: shutdown handlers still
    // run. Only a process killed outright skips it, and the lock then expires
    // on its own.
    register_shutdown_function('pg_cron_dispatch_finished');

    // Each of these scripts is written to be a whole request, and some print an
    // error page and stop when their own settings are missing. From here that
    // would land in the general job's output - cron mail every five minutes on
    // a misconfigured site, or markup on the page when someone opens job.php in
    // a browser to trigger it by hand. The handler discards it and files it in
    // the site log instead.
    ob_start('pg_cron_dispatch_output');

    // The dispatched script checks this constant through
    // pg_cron_is_background_run(), so it does not demand a signed-in user
    // when the general job is the one running it.
    define('PG_CRON_DISPATCH', true);

    include($dispatch_script);
}
