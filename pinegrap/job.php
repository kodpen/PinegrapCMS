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
        item_type
    FROM comments
    WHERE
        (publish_date_and_time != '0000-00-00 00:00:00')
        AND (publish_date_and_time <= NOW())
        AND (published = 0)
    ORDER BY publish_date_and_time");

// Loop through the comments in order to publish them.
foreach ($comments as $comment) {
    // Publish the comment and clear the scheduled info.
    // We update the created timestamp so that the comment appears in logical order.
    db(
        "UPDATE comments
        SET
            published = '1',
            publish_date_and_time = '',
            publish_cancel = '0',
            created_timestamp = UNIX_TIMESTAMP()
        WHERE id = '" . $comment['id'] . "'");

    // If this comment is for a submitted form on a form item view,
    // then update submitted form info.
    if ($comment['item_type'] == 'submitted_form') {

        // Get the number of views so we do not lose that data when we delete record below.
        $number_of_views = db_value(
            "SELECT number_of_views
            FROM submitted_form_info
            WHERE
                (submitted_form_id = '" . $comment['item_id'] . "')
                AND (page_id = '" . $comment['page_id'] . "')");

        // Get the number of published comments for this submitted form and page.
        $number_of_comments = db_value(
            "SELECT COUNT(*)
            FROM comments
            WHERE
                (page_id = '" . $comment['page_id'] . "')
                AND (item_id = '" . $comment['item_id'] . "')
                AND (item_type = 'submitted_form')
                AND (published = '1')");
        
        // Delete the current record if one exists.
        db(
            "DELETE FROM submitted_form_info
            WHERE
                (submitted_form_id = '" . $comment['item_id'] . "')
                AND (page_id = '" . $comment['page_id'] . "')");
        
        // Insert new record.
        db(
            "INSERT INTO submitted_form_info (
                submitted_form_id,
                page_id,
                number_of_views,
                number_of_comments,
                newest_comment_id)
             VALUES (
                '" . $comment['item_id'] . "',
                '" . $comment['page_id'] . "',
                '$number_of_views',
                '$number_of_comments',
                '" . $comment['id'] . "')");
    }

    send_comment_email_to_administrators($comment['id']);

    // If this comment is for a submitted form on a form item view,
    // then send comment email to custom form submitter.
    if ($comment['item_type'] == 'submitted_form') {
        send_comment_email_to_custom_form_submitter($comment['id']);
    }

    send_comment_email_to_watchers($comment['id']);
}

$number_of_comments = count($comments);

// If at least one comment was published, then log activity.
if ($number_of_comments) {

    log_activity(lang(array('string' => 'general job published {var:1} scheduled comment(s)', 'vars' => array(pg_format_number($number_of_comments, 0)))), 'UNKNOWN');

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

// Queued text messages (pg_sms_queue_run() in includes/fn/sms.php). A request
// only queues a message, since the provider's answer is not worth a visitor's
// wait, so apart from the queue screen's Run now this pass is what sends
// them, and it depends on no setting. An empty queue, which is every site
// without SMS, costs one read. Fewer rows and less time than the mail pass:
// each message is a paid call that waits on the provider.
if (pg_sms_queue_ready()) {

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
// gave another (includes/workspace/scheduled.php).
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
        ws_scheduled_run();
    }

}

// The visitor location database (includes/fn/geo.php): this month's list,
// fetched while a setting reads the visitor's location. The look is a file
// read; the fetch happens about once a month, and a failed one is tried again
// twelve hours later, not on every tick.
if (function_exists('pg_geo_db_due') && pg_geo_db_due()) {

    pg_geo_db_refresh();

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
