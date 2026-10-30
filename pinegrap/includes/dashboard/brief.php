<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Dashboard brief - the greeting at the top of the welcome screen.
 *
 * One sentence that weighs the signals the widgets below carry, one thing
 * worth doing, the workspace strip under them and the next meeting under the
 * clock. welcome.php draws it with the page, and api.php's get_dashboard_brief
 * answers it again when the reader asks for another line, so both go through
 * pg_dashboard_brief().
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
 * The brief for one reader, as markup ready to print.
 *
 * Every signal sits behind the same gate as the widget that owns it, and the
 * line remembers what it said in the session so the next load can say
 * something else.
 *
 * @param array $user               the row validate_user() returned
 * @param array $changelog_sections pg_changelog_sections(), for the version check
 * @return array icon (a Bootstrap icon class), greeting (escaped), line
 *               (markup), strip (markup, may be empty), next (markup under
 *               the clock, may be empty), ws (the workspace signals or null)
 */
function pg_dashboard_brief($user, $changelog_sections)
{
    // Which part of the day it is. The old split called 19:00 night, which is when
    // most people are still working; evening now runs to 23:00 and only the small
    // hours are night.
    $time = (int) date('H');
    if ($time < 5) {
        $greeting_part = 'late';
    } elseif ($time < 12) {
        $greeting_part = 'morning';
    } elseif ($time < 18) {
        $greeting_part = 'afternoon';
    } elseif ($time < 23) {
        $greeting_part = 'evening';
    } else {
        $greeting_part = 'late';
    }

    $greeting_icons = array(
        'late'      => 'bi-moon-stars',
        'morning'   => 'bi-sunrise',
        'afternoon' => 'bi-sun',
        'evening'   => 'bi-brightness-alt-high',
    );
    $greeting_labels = array(
        'late'      => lang('Good night'),
        'morning'   => lang('Good morning'),
        'afternoon' => lang('Good afternoon'),
        'evening'   => lang('Good evening'),
    );

    // Once in a while, a lighter greeting instead of the plain one. Every time
    // would be a gimmick and would wear out inside a week; roughly one refresh in
    // four keeps it a small surprise, which is the only thing that makes it worth
    // having. The plain greeting is the default and always the fallback.
    $greeting_playful = array(
        'late' => array(
            lang('Night knight'),
            lang('Still up'),
            lang('The quiet shift'),
        ),
        'morning' => array(
            lang('First coffee'),
            lang('Bright and early'),
            lang('A fresh page'),
        ),
        'afternoon' => array(
            lang('Peak hours'),
            lang('Halfway there'),
            lang('Full swing'),
        ),
        'evening' => array(
            lang('Golden hour'),
            lang('Winding down'),
            lang('The long light'),
        ),
    );

    $greeting_label = $greeting_labels[$greeting_part];

    if ((mt_rand(1, 4) === 1) && !empty($greeting_playful[$greeting_part])) {

        $greeting_playful_last = (string) ($_SESSION['software']['welcome']['greeting_playful'] ?? '');
        $greeting_playful_choices = array_values(
            array_diff($greeting_playful[$greeting_part], array($greeting_playful_last)));

        if (empty($greeting_playful_choices)) {
            $greeting_playful_choices = $greeting_playful[$greeting_part];
        }

        $greeting_label = $greeting_playful_choices[array_rand($greeting_playful_choices)];
        $_SESSION['software']['welcome']['greeting_playful'] = $greeting_label;
    }

    $output_greeting_icon = $greeting_icons[$greeting_part];
    $output_greeting_message_text = $greeting_label . ', ' . h($_SESSION['sessionusername']) . '.';

    // The line under the greeting.
    //
    // It is a read, not a readout. The widgets below already carry every number on
    // this screen; repeating two of them in prose adds nothing anyone would stop
    // for. What is missing between a wall of tiles and an operator is the sentence
    // somebody who had already looked would say -- how it is going, and what to do
    // about it -- so that is what this is:
    //
    //   1. the read     one sentence that weighs several signals against each other
    //                   and says how the day is going
    //   2. the advice   one thing worth doing, and a way straight to it
    //
    // Both halves are drawn from the same signals, gathered once below. Every
    // signal sits behind the same gate as the widget that owns it, so the briefing
    // never weighs a number the operator is not allowed to see -- which is also why
    // each one can be missing, and why nothing here assumes it is there.
    //
    // The read is chosen from whichever readings are true right now, and rotates
    // among them; the advice is not. Advice is the most useful thing to do, and
    // picking that at random would make it worth ignoring.
    //
    // Nothing is invented to sound encouraging. A slow day says the day is young,
    // because that is true; it does not say sales are strong, because that is not.
    $greeting_now = time();

    // "This week" is today and the six days before it from midnight, the
    // window the E-Commerce Summary card counts as its last week, so a sentence
    // and the card beside it never give two numbers for the same week.
    $greeting_week_start = strtotime(date('Y-m-d', $greeting_now)) - (6 * 86400);

    $sig = array(
        'orders_today'    => null,
        'orders_before'   => null,
        'carts_week'      => null,
        'visitors_now'    => null,
        'health'          => null,
        'perf_today'      => null,
        'perf_before'     => null,
        'hits_today'      => null,
        'hits_before'     => null,
        'comments'        => null,
        'out_of_stock'    => null,
        'new_contacts'    => null,
        'log_today'       => null,
        'version_pending' => null,
        'map_lead'        => null,
        'map_new'         => null,
        'orders_week'       => null,
        'orders_prev_week'  => null,
        'pending_shipments' => null,
        'pending_shipments_oldest' => null,
        'refunds_pending'   => null,
        'low_stock'         => null,
        'forms_today'       => null,
        'forms_week'        => null,
        'seo_critical'      => null,
        'campaign_next'     => null,
        'offers_ending'     => null,
        'waf_blocked'       => null,
        'staff_online'      => null,
        'backup_state'      => null,
        'backup_href'       => null,
        'update_state'      => null,
        'update_href'       => null,
        'cron_state'        => null,
        'cron_href'         => null,
        'waf_mode_state'    => null,
        'waf_mode_href'     => null,
        'ws'                => null,
    );

    // ── signals ──────────────────────────────────────────────────────────────────

    // Comment moderation. Same scope marker as view_comments.php: unpublished.
    if ($user['role'] < 3) {
        $sig['comments'] = (int) db_value("SELECT COUNT(*) FROM comments WHERE published = '0'");
    }

    if ((ECOMMERCE == true) && (($user['role'] < 3) || ($user['manage_ecommerce'] == true))) {

        $sig['out_of_stock'] = (int) db_value("SELECT COUNT(*) FROM products WHERE out_of_stock = '1'");

        // Today against the same stretch of yesterday, which is what makes the
        // number mean something: eleven orders is good news or bad news depending
        // entirely on what eleven usually looks like here.
        //
        // Every window in one pass. order_date is a unix timestamp, so the windows
        // are plain arithmetic, and the WHERE keeps the scan to the week they all
        // fall inside rather than the whole table -- this screen is the first thing
        // that loads after a sign in and it is not the place to read an order
        // history.
        $greeting_day_start  = $greeting_now - 86400;
        $greeting_day_before = $greeting_now - 172800;

        // status != 'incomplete' is not a detail, it is the difference between an
        // order and a basket somebody walked away from. Both live in this table,
        // and without the filter the briefing was calling abandoned carts sales --
        // and saying orders were ahead of yesterday on a site that had taken none
        // for two days, three inches above the widget that says otherwise.
        //
        // The rule is copied from the orders widget on this same screen rather than
        // decided here: it draws the orders list from status != 'incomplete' and
        // the basket list from status = 'incomplete'. Two things on one screen
        // counting the same rows differently is worse than either rule being wrong.
        // The baskets come out of the same pass. They are the other half of this
        // table and they are worth a sentence of their own: somebody who filled a
        // basket and left got further than everybody who never started one.
        //
        // A week for those, two days for the orders, and this week against the
        // week before it -- so the scan is two weeks wide and every sum carries
        // both of its own bounds. Without the lower bound the "yesterday" figure
        // would quietly swallow the days behind it, and the basket count would
        // grow to a fortnight the moment the scan did.
        $greeting_prev_week_start = $greeting_week_start - 604800;

        $greeting_orders = db_item(
            "SELECT
                SUM(CASE WHEN status != 'incomplete' AND order_date >= " . (int) $greeting_day_start . " THEN 1 ELSE 0 END) AS orders_today,
                SUM(CASE WHEN status != 'incomplete' AND order_date >= " . (int) $greeting_day_before . " AND order_date < " . (int) $greeting_day_start . " THEN 1 ELSE 0 END) AS orders_before,
                SUM(CASE WHEN status != 'incomplete' AND order_date >= " . (int) $greeting_week_start . " THEN 1 ELSE 0 END) AS orders_week,
                SUM(CASE WHEN status != 'incomplete' AND order_date >= " . (int) $greeting_prev_week_start . " AND order_date < " . (int) $greeting_week_start . " THEN 1 ELSE 0 END) AS orders_prev_week,
                SUM(CASE WHEN status =  'incomplete' AND order_date >= " . (int) $greeting_week_start . " THEN 1 ELSE 0 END) AS carts_week
             FROM orders
             WHERE order_date >= " . (int) $greeting_prev_week_start);

        $sig['orders_today']     = (int) ($greeting_orders['orders_today'] ?? 0);
        $sig['orders_before']    = (int) ($greeting_orders['orders_before'] ?? 0);
        $sig['orders_week']      = (int) ($greeting_orders['orders_week'] ?? 0);
        $sig['orders_prev_week'] = (int) ($greeting_orders['orders_prev_week'] ?? 0);
        $sig['carts_week']       = (int) ($greeting_orders['carts_week'] ?? 0);
    }

    // Where the orders came from, which is the one thing this screen knows and
    // never says in words. Same permission as the Sales Map card -- it is the same
    // reading of the same rows, and a sentence about what the shop earned belongs
    // with the report, not with the order queue.
    //
    // Two windows in one pass over a month: the week just gone, and the three weeks
    // before it. That is what lets the line tell "busiest this week" from "somewhere
    // that had never ordered before", and the second is the more interesting of the
    // two by a distance.
    if ((ECOMMERCE == true) && USER_MANAGE_ECOMMERCE_REPORTS) {

        require_once PG_FUNCTIONS_DIR . '/includes/sales_map.php';

        $greeting_map_week  = $greeting_week_start;
        $greeting_map_month = $greeting_week_start - 1814400;

        $greeting_map_rows = db_items(
            "SELECT
                billing_country,
                billing_state,
                SUM(CASE WHEN order_date >= " . (int) $greeting_map_week . " THEN 1 ELSE 0 END) AS recent,
                SUM(CASE WHEN order_date <  " . (int) $greeting_map_week . " THEN 1 ELSE 0 END) AS earlier
             FROM orders
             WHERE status IN ('complete', 'exported')
             AND order_date >= " . (int) $greeting_map_month . "
             GROUP BY billing_country, billing_state");

        $greeting_map_recent = 0;
        $greeting_map_earlier = 0;
        $greeting_map_lead = null;
        $greeting_map_new = null;

        foreach ($greeting_map_rows as $greeting_map_row) {

            $greeting_map_count = (int) $greeting_map_row['recent'];
            $greeting_map_before = (int) $greeting_map_row['earlier'];

            $greeting_map_recent += $greeting_map_count;
            $greeting_map_earlier += $greeting_map_before;

            if ($greeting_map_count < 1) {
                continue;
            }

            $greeting_map_place = array(
                'name'    => pg_sales_map_label(
                    pg_sales_map_country_code($greeting_map_row['billing_country'], $greeting_map_row['billing_state']),
                    $greeting_map_row['billing_state']),
                'state'   => trim((string) $greeting_map_row['billing_state']),
                'country' => trim((string) $greeting_map_row['billing_country']),
                'count'   => $greeting_map_count,
                // The window this was counted over, carried along so the link under
                // the sentence covers the same days the sentence is about.
                'from'    => $greeting_map_month);

            if (($greeting_map_lead === null) || ($greeting_map_count > $greeting_map_lead['count'])) {
                $greeting_map_lead = $greeting_map_place;
            }

            // First order ever is not what this says -- first in a month is, and
            // that is the honest claim for a month-wide query.
            if (($greeting_map_before === 0)
                && (($greeting_map_new === null) || ($greeting_map_count > $greeting_map_new['count']))
            ) {
                $greeting_map_new = $greeting_map_place;
            }
        }

        // A leader needs somewhere to lead: one order out of two is not a pattern,
        // it is two orders.
        if (($greeting_map_lead !== null) && ($greeting_map_recent >= 3) && ($greeting_map_lead['count'] >= 2)) {
            $sig['map_lead'] = $greeting_map_lead;
        }

        // And "new" only means anything against a month that had orders in it.
        // Without this every place is new on a shop's first week, which is a line
        // that says nothing on the one week somebody is watching closest.
        if (($greeting_map_new !== null) && ($greeting_map_earlier > 0)) {
            $sig['map_new'] = $greeting_map_new;
        }
    }

    if (($user['role'] < 3) || ($user['manage_visitors'] == true)) {
        $sig['visitors_now'] = (int) db_value(
            "SELECT COUNT(*) FROM visitors WHERE stop_timestamp >= " . (int) ($greeting_now - 1200));
    }

    if (($user['role'] < 3) || ($user['manage_contacts'] == true)) {
        $sig['new_contacts'] = (int) db_value(
            "SELECT COUNT(*) FROM contacts WHERE timestamp >= " . (int) $greeting_week_start);
    }

    if ($user['role'] < 3) {
        $sig['log_today'] = (int) db_value(
            "SELECT COUNT(*) FROM log WHERE log_timestamp >= " . (int) ($greeting_now - 86400));
    }

    // Site health, read straight off the cache the system status widget writes and
    // never computed here. The checks behind that score are the expensive thing on
    // this screen -- a cold run walks every table -- and a greeting line is not
    // worth paying for it. So the briefing weighs health when the widget has
    // already been to look, and leaves it out when it has not. After the first load
    // of the dashboard it always has.
    if ($user['role'] < 3) {

        $greeting_health_file = PG_FUNCTIONS_DIR . '/data/temp/system_status_cache.json';

        if (is_file($greeting_health_file)) {

            $greeting_health = json_decode(@file_get_contents($greeting_health_file), true);

            // An hour, not the ten minutes the widget refreshes on. A score from
            // forty minutes ago is still true enough to weigh in a sentence, and
            // insisting on a fresh one would only mean saying nothing most loads.
            if (is_array($greeting_health) && isset($greeting_health['score'], $greeting_health['ts'])
                && (($greeting_now - (int) $greeting_health['ts']) < 3600)
            ) {
                $sig['health'] = (int) $greeting_health['score'];

                // A few of the checks behind the score, by the untranslated key
                // every check carries from shape version 6 on. Their state and the
                // screen that acts on them come out of the same file, so these are
                // free; an older cache simply does not offer them.
                $greeting_health_keys = array(
                    'Last Backup'              => 'backup',
                    'Software Update'          => 'update',
                    'Scheduled Tasks'          => 'cron',
                    'Web Application Firewall' => 'waf_mode',
                );

                if (((int) ($greeting_health['v'] ?? 0) === 6) && !empty($greeting_health['checks'])
                    && is_array($greeting_health['checks'])
                ) {
                    foreach ($greeting_health['checks'] as $greeting_check) {

                        $greeting_check_key = (string) ($greeting_check['key'] ?? '');

                        if (!isset($greeting_health_keys[$greeting_check_key])) {
                            continue;
                        }

                        $greeting_check_name = $greeting_health_keys[$greeting_check_key];

                        $sig[$greeting_check_name . '_state'] = (string) ($greeting_check['state'] ?? '');
                        $sig[$greeting_check_name . '_href']  = (string) ($greeting_check['href'] ?? '');
                    }
                }
            }
        }
    }

    // How fast the site is answering, today against yesterday. perf_stats is the
    // hourly roll-up rather than the raw log -- one row per page per hour, read
    // through idx_area_hour -- so this is a small aggregate over two days and not a
    // walk through a million requests.
    $greeting_perf_on = (!defined('PERF_MONITOR') || PERF_MONITOR !== false)
        && (!defined('PERF_MONITOR_ENABLED') || PERF_MONITOR_ENABLED);

    if (($user['role'] < 3) && $greeting_perf_on) {

        $greeting_perf_start  = $greeting_now - 86400;
        $greeting_perf_before = $greeting_now - 172800;

        $greeting_perf = db_item(
            "SELECT
                SUM(CASE WHEN hour_start >= " . (int) $greeting_perf_start . " THEN hits ELSE 0 END) AS hits_today,
                SUM(CASE WHEN hour_start >= " . (int) $greeting_perf_start . " THEN total_ms ELSE 0 END) AS ms_today,
                SUM(CASE WHEN hour_start <  " . (int) $greeting_perf_start . " THEN hits ELSE 0 END) AS hits_before,
                SUM(CASE WHEN hour_start <  " . (int) $greeting_perf_start . " THEN total_ms ELSE 0 END) AS ms_before
             FROM perf_stats
             WHERE area = 'frontend' AND hour_start >= " . (int) $greeting_perf_before);

        $greeting_hits_today  = (int) ($greeting_perf['hits_today'] ?? 0);
        $greeting_hits_before = (int) ($greeting_perf['hits_before'] ?? 0);

        // Twenty requests is the point where an average stops being one slow page
        // wearing an average as a disguise.
        if ($greeting_hits_today >= 20) {
            $sig['perf_today'] = (int) round(((float) ($greeting_perf['ms_today'] ?? 0)) / $greeting_hits_today);
        }

        if ($greeting_hits_before >= 20) {
            $sig['perf_before'] = (int) round(((float) ($greeting_perf['ms_before'] ?? 0)) / $greeting_hits_before);
        }

        // The request counts come out of the same rows as the timings, so traffic
        // is a signal this screen already paid for. Fifty a day before it is worth
        // comparing -- below that the difference between two days is one crawler.
        if (($greeting_hits_today >= 50) && ($greeting_hits_before >= 50)) {
            $sig['hits_today']  = $greeting_hits_today;
            $sig['hits_before'] = $greeting_hits_before;
        }
    }

    // Where this install stands against the release notes it shipped with. The
    // changelog is already parsed further up this screen for the modal, so this
    // costs nothing -- and the comparison is real: VERSION comes from the database,
    // the newest changelog heading comes from the files, and the two part company
    // exactly when an upgrade has been uploaded but not yet run.
    if (($user['role'] < 3) && (count($changelog_sections) > 0) && (VERSION != 'SETUP')) {

        $greeting_newest_version = (string) $changelog_sections[0]['version'];

        $sig['version_pending'] = version_compare($greeting_newest_version, (string) VERSION, '>')
            ? $greeting_newest_version
            : false;
    }

    // Orders paid for and not yet sent. The same reading as the shipments card
    // (api.php, widget 9) and behind the same gate, cut down to one number: how
    // many orders still have a shippable line with quantity left to ship, over the
    // card's own thirty days from midnight. The reasons for each condition --
    // the ship_to_id marker, complete recipients, online orders only, the signed
    // subtraction -- are written out beside the card's query and hold here as they
    // are.
    if ((ECOMMERCE == true) && defined('ECOMMERCE_SHIPPING') && (ECOMMERCE_SHIPPING == true)
        && (($user['role'] < 3) || ($user['manage_ecommerce'] == true))
    ) {

        $greeting_saved_sql = '';

        if (pg_schema_has('order_items', 'saved_for_later')) {
            $greeting_saved_sql = " AND (order_items.saved_for_later = 0)";
        }

        $greeting_ship_since = strtotime(date('Y-m-d')) - (30 * 86400);

        $greeting_ship = db_item(
            "SELECT COUNT(*) AS pending_count, MIN(order_date) AS oldest FROM (
                SELECT orders.id, MIN(orders.order_date) AS order_date
                FROM orders
                INNER JOIN ship_tos ON (ship_tos.order_id = orders.id) AND (ship_tos.complete = '1')
                INNER JOIN order_items ON order_items.ship_to_id = ship_tos.id
                WHERE
                    (orders.status IN ('complete', 'exported'))
                    AND (orders.type = 'online')
                    AND (orders.order_date >= " . (int) $greeting_ship_since . ")
                    $greeting_saved_sql
                GROUP BY orders.id
                HAVING SUM(GREATEST(CAST(order_items.quantity AS SIGNED) - CAST(order_items.shipped_quantity AS SIGNED), 0)) > 0
            ) AS pending");

        $sig['pending_shipments'] = (int) ($greeting_ship['pending_count'] ?? 0);

        // When the longest waiting of them was placed, so the advice can tell a
        // normal day's packing from a parcel that has been left behind.
        $sig['pending_shipments_oldest'] = (($sig['pending_shipments'] > 0) && !empty($greeting_ship['oldest']))
            ? (int) $greeting_ship['oldest']
            : null;
    }

    if ((ECOMMERCE == true) && (($user['role'] < 3) || ($user['manage_ecommerce'] == true))) {

        // Cancelled orders whose money has not gone back yet: the same three
        // states the orders list files under "Refund Pending". The column came
        // with a later schema step, so it is asked about first.
        if (pg_schema_has('orders', 'refund_status')) {
            $sig['refunds_pending'] = (int) db_value(
                "SELECT COUNT(*) FROM orders WHERE refund_status IN ('manual_required', 'failed', 'pending')");
        }

        // Running low but not out yet -- the out of stock count above already
        // covers zero. Only where the shop has set a threshold of its own; a
        // made-up line would call a shop that keeps two of everything short of
        // stock every single day. Products sold on backorder never run out, and
        // ones that do not track stock have no level to run low on.
        if (defined('ECOMMERCE_LOW_STOCK_THRESHOLD') && ((int) ECOMMERCE_LOW_STOCK_THRESHOLD > 0)) {
            $sig['low_stock'] = (int) db_value(
                "SELECT COUNT(*) FROM products
                 WHERE inventory = '1' AND backorder != '1'
                 AND inventory_quantity BETWEEN 1 AND " . (int) ECOMMERCE_LOW_STOCK_THRESHOLD);
        }

        // Offers that stop within the week. Switched off ones are left out: an
        // offer nobody is running has nothing to end. The open-ended date sits
        // decades out, so the upper bound keeps it out without naming it, and the
        // dates come from PHP so they agree with the offers list on what today is.
        $sig['offers_ending'] = (int) db_value(
            "SELECT COUNT(*) FROM offers
             WHERE status = 'enabled'
             AND end_date > '0000-00-00'
             AND end_date >= '" . e(date('Y-m-d')) . "'
             AND end_date <= '" . e(date('Y-m-d', strtotime('+7 days'))) . "'");
    }

    // Form submissions, today and over the week, in one pass. Roles above User
    // only: for a User with the forms permission the submitted forms screen
    // narrows the list to the folders they may edit, and a site-wide count would
    // promise rows the link does not show.
    if ($user['role'] < 3) {

        $greeting_forms = db_item(
            "SELECT
                SUM(CASE WHEN submitted_timestamp >= " . (int) ($greeting_now - 86400) . " THEN 1 ELSE 0 END) AS forms_today,
                COUNT(*) AS forms_week
             FROM forms
             WHERE complete = '1' AND submitted_timestamp >= " . (int) $greeting_week_start);

        $sig['forms_today'] = (int) ($greeting_forms['forms_today'] ?? 0);
        $sig['forms_week']  = (int) ($greeting_forms['forms_week'] ?? 0);
    }

    if ($user['role'] < 3) {

        // The pages list's own "SEO Score Critical" filter, so the number and the
        // list behind the link are the same rows. A score that is out of date is
        // not counted: the page has changed since it was marked.
        $sig['seo_critical'] = (int) db_value(
            "SELECT COUNT(*) FROM page WHERE seo_score < 30 AND seo_analysis_current = 1");

        // The next campaign due to go out. start_time is a DATETIME written by
        // PHP, so "now" is PHP's too rather than the database server's clock.
        $greeting_campaign = db_item(
            "SELECT subject, start_time FROM email_campaigns
             WHERE type = 'manual' AND status = 'ready'
             AND start_time > '" . e(date('Y-m-d H:i:s')) . "'
             ORDER BY start_time LIMIT 1");

        if (is_array($greeting_campaign) && (strtotime($greeting_campaign['start_time']) > $greeting_now)) {
            $sig['campaign_next'] = array(
                'subject' => (string) $greeting_campaign['subject'],
                'when'    => get_relative_time(array(
                    'timestamp' => strtotime($greeting_campaign['start_time']),
                    'format'    => 'plain_text')),
            );
        }

        // Colleagues signed in in the last twenty minutes, the window the visitor
        // count above uses. Presence is stamped for members browsing the public
        // site as well, so only accounts with a back office role are counted;
        // calling a shopper a colleague would make the line plainly wrong.
        $sig['staff_online'] = (int) db_value(
            "SELECT COUNT(*) FROM user
             WHERE user_online_timestamp >= " . (int) ($greeting_now - 1200) . "
             AND user_role < 3
             AND user_id != '" . (int) $user['id'] . "'");
    }

    // What the firewall turned away over the last day. waf.php is loaded by
    // init.php on every request, so the function test only guards an install
    // where that has changed; nothing is included from here to make it true.
    // SUM(hit_count), because one row is a whole window of repeats.
    if (($user['role'] < 3) && function_exists('waf_mode') && (waf_mode() !== 'off')) {
        $sig['waf_blocked'] = (int) db_value(
            "SELECT COALESCE(SUM(hit_count), 0) FROM waf_log
             WHERE action IN ('block', 'rate', 'ban')
             AND log_timestamp >= " . (int) ($greeting_now - 86400));
    }

    // The workspace: the person's own tasks, what was handed to them, what is on
    // their calendar today.
    //
    // The module is loaded here because the menu loads it inside pg_page_shell(),
    // which runs after this; require_once makes the menu's own load a no-op. The
    // gate is ws_rights() and not USER_MANAGE_WORKSPACE alone, because a profile
    // can take a staff account out of the team and that flag is only read there.
    // With the module off, nothing below this is ever said about it.
    if (defined('WORKSPACE_ENABLED') && WORKSPACE_ENABLED
        && (($user['role'] < 3) || (defined('USER_MANAGE_WORKSPACE') && USER_MANAGE_WORKSPACE))
    ) {

        require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

        if (ws_enabled()) {

            $greeting_ws_rights = ws_rights($user);

            if ($greeting_ws_rights['member']) {
                $sig['ws'] = ws_dashboard_signals($greeting_ws_rights);
            }
        }
    }

    // ── directions ───────────────────────────────────────────────────────────────
    //
    // Where the sentence points. The link goes on the words it is about -- "the 2
    // empty products", "the security checks" -- rather than on a chip parked at the
    // end: the thing being talked about and the thing you click are then the same
    // thing, and the sentence keeps its shape in every language because the anchor
    // is one substitution inside it.
    $greeting_link = function ($href, $text) {
        return '<a class="pg-brief-link" href="' . h($href) . '">' . h($text) . '</a>';
    };

    // ── the advice ───────────────────────────────────────────────────────────────
    //
    // Worked out before the reading, because the reading is chosen to suit it.
    //
    // Two tiers, and only the top occupied one is drawn from: something actually
    // wrong outranks an opportunity, always. Inside a tier it rotates, and each
    // entry has more than one way of saying itself.
    //
    // Strict priority was the first attempt and it was wrong in practice. With two
    // products out of stock it produced the identical sentence on every refresh
    // until somebody restocked them, and a line that never changes stops being
    // read -- which costs more than picking the second most useful thing sometimes.
    $has_orders = ($sig['orders_today'] !== null);
    $has_perf   = ($sig['perf_today'] !== null) && ($sig['perf_before'] !== null);

    $greeting_options = array();

    if (!empty($sig['version_pending'])) {
        $greeting_options[] = array('tier' => 1, 'topic' => 'version', 'text' => lang(array(
            'string' => 'Version {var:1} is sitting in the files unrun, so the release notes are the place to start.',
            'vars' => (string) $sig['version_pending'])));
    }

    if (($sig['health'] !== null) && ($sig['health'] < 70)) {

        $greeting_checks_link = $greeting_link('settings_security.php#pgset-session', lang('the security checks'));

        // Neither of these points back at a score, because the reading above is
        // about something else as often as not, and a sentence that refers to
        // something nobody said is a sentence you have to work out.
        $greeting_options[] = array('tier' => 1, 'topic' => 'health', 'text' => lang(array(
            'string' => 'System health is usually pulled down by {var:1} first, so I would start there.',
            'vars' => $greeting_checks_link)));
        $greeting_options[] = array('tier' => 1, 'topic' => 'health', 'text' => lang(array(
            'string' => 'The shortest way back up that score runs through {var:1}.',
            'vars' => $greeting_checks_link)));
    }

    if (((int) $sig['comments']) > 0) {

        // Two link phrases: Turkish puts the object of "Approving the ..." in
        // the accusative ("5 yorumu") and the subject of "There are ..." in
        // the nominative ("5 onay bekleyen yorum").
        $greeting_comments_one = (((int) $sig['comments']) === 1);
        $greeting_comments_suffix = $greeting_comments_one ? '' : 's';
        $greeting_comments_link = $greeting_link('view_comments.php', lang(array(
            'string' => '{var:1} waiting comment{suffix:1}', 'vars' => pg_format_number($sig['comments'], 0),
            'suffix' => $greeting_comments_suffix)));
        $greeting_comments_there = $greeting_link('view_comments.php', lang(array(
            'string' => '{var:1} comment{suffix:1} waiting for approval', 'vars' => pg_format_number($sig['comments'], 0),
            'suffix' => $greeting_comments_suffix)));

        $greeting_options[] = array('tier' => 1, 'topic' => 'comments', 'text' => $greeting_comments_one
            ? lang(array('string' => 'Approving the {var:1} puts it on the page it belongs to.', 'vars' => $greeting_comments_link))
            : lang(array('string' => 'Approving the {var:1} puts them on the pages they belong to.', 'vars' => $greeting_comments_link)));
        $greeting_options[] = array('tier' => 1, 'topic' => 'comments', 'text' => $greeting_comments_one
            ? lang(array('string' => 'There is {var:1}, and publishing it keeps that page alive.', 'vars' => $greeting_comments_there))
            : lang(array('string' => 'There are {var:1}, and publishing them keeps those pages alive.', 'vars' => $greeting_comments_there)));
    }

    if (((int) $sig['out_of_stock']) > 0) {

        // The filtered list, not the catalogue. Advice that drops you in front of
        // four thousand products has made you do the finding yourself.
        // Accusative and nominative link phrases, as with the comments above.
        $greeting_stock_one = (((int) $sig['out_of_stock']) === 1);
        $greeting_stock_suffix = $greeting_stock_one ? '' : 's';
        $greeting_stock_url = 'view_products.php?filter=out_of_stock_products';
        $greeting_stock_link = $greeting_link($greeting_stock_url, lang(array(
            'string' => '{var:1} empty product{suffix:1}', 'vars' => pg_format_number($sig['out_of_stock'], 0),
            'suffix' => $greeting_stock_suffix)));
        $greeting_stock_there = $greeting_link($greeting_stock_url, lang(array(
            'string' => '{var:1} out-of-stock product{suffix:1}', 'vars' => pg_format_number($sig['out_of_stock'], 0),
            'suffix' => $greeting_stock_suffix)));

        $greeting_options[] = array('tier' => 1, 'topic' => 'stock', 'text' => $greeting_stock_one
            ? lang(array('string' => 'Restocking the {var:1} would keep that page selling.', 'vars' => $greeting_stock_link))
            : lang(array('string' => 'Restocking the {var:1} would keep those pages selling.', 'vars' => $greeting_stock_link)));
        $greeting_options[] = array('tier' => 1, 'topic' => 'stock', 'text' => $greeting_stock_one
            ? lang(array('string' => 'There is {var:1} on the site, and that is a page that cannot sell.', 'vars' => $greeting_stock_there))
            : lang(array('string' => 'There are {var:1} on the site, and each one is a page that cannot sell.', 'vars' => $greeting_stock_there)));
    }

    if ($has_perf && ($sig['perf_today'] > $sig['perf_before']) && ($sig['perf_today'] > 500)) {

        $greeting_perf_link = $greeting_link('view_performance_log.php', lang('the performance log'));

        $greeting_options[] = array('tier' => 2, 'topic' => 'perf', 'text' => lang(array(
            'string' => 'Pages are slower than yesterday, and {var:1} will say which ones.',
            'vars' => $greeting_perf_link)));
        $greeting_options[] = array('tier' => 2, 'topic' => 'perf', 'text' => lang(array(
            'string' => 'That slowdown has been going since yesterday; {var:1} has the page names.',
            'vars' => $greeting_perf_link)));
    }

    if (((int) $sig['carts_week']) > 0) {

        // reset=true first, because this screen keeps its filters in the session
        // and a month-old date range left behind from the last visit would hide
        // the very rows the sentence just promised.
        $greeting_carts_one = (((int) $sig['carts_week']) === 1);
        $greeting_carts_link = $greeting_link('view_orders.php?reset=true&status=incomplete', lang(array(
            'string' => '{var:1} basket{suffix:1}', 'vars' => pg_format_number($sig['carts_week'], 0),
            'suffix' => $greeting_carts_one ? '' : 's')));

        $greeting_options[] = array('tier' => 2, 'topic' => 'carts', 'text' => $greeting_carts_one
            ? lang(array('string' => 'There is {var:1} left half full this week, and it is worth a look at who walked away.', 'vars' => $greeting_carts_link))
            : lang(array('string' => 'There are {var:1} left half full this week, and it is worth a look at who walked away.', 'vars' => $greeting_carts_link)));
        $greeting_options[] = array('tier' => 2, 'topic' => 'carts', 'text' => $greeting_carts_one
            ? lang(array('string' => 'Someone filled {var:1} this week and left it; it is still on the list.', 'vars' => $greeting_carts_link))
            : lang(array('string' => 'Someone filled {var:1} this week and left them; they are all still on the list.', 'vars' => $greeting_carts_link)));
    }

    if (((int) $sig['new_contacts']) > 0) {

        // Not a problem to fix -- an opening to take. Most days the dashboard has
        // nothing wrong with it, and "nothing is wrong" is not the same as "there
        // is nothing worth doing".
        $greeting_people_one = (((int) $sig['new_contacts']) === 1);
        $greeting_people_link = $greeting_link('view_contacts.php', $greeting_people_one
            ? lang(array('string' => '{var:1} person', 'vars' => pg_format_number($sig['new_contacts'], 0)))
            : lang(array('string' => '{var:1} people', 'vars' => pg_format_number($sig['new_contacts'], 0))));

        $greeting_options[] = array('tier' => 2, 'topic' => 'contacts', 'text' => $greeting_people_one
            ? lang(array('string' => 'The {var:1} who joined this week has not heard from you yet.', 'vars' => $greeting_people_link))
            : lang(array('string' => 'The {var:1} who joined this week have not heard from you yet.', 'vars' => $greeting_people_link)));
        $greeting_options[] = array('tier' => 2, 'topic' => 'contacts', 'text' => lang(array(
            'string' => '{var:1} joined this week, and a first hello tends to land well.',
            'vars' => $greeting_people_link)));
    }

    // A place that had never ordered before. Not a problem to fix and not a number
    // to watch -- a door that opened, which is the kind of thing the sales map is
    // on the screen to show and a tile full of totals never will.
    //
    // Only offered with the link, and the link asks for the other commerce
    // permission: view_orders.php wants `manage_ecommerce`, while the figure itself
    // came in under `manage_ecommerce_reports`. Without somewhere to send the
    // reader this is a reading, not advice, and it is already in the pool below.
    if (($sig['map_new'] !== null) && (defined('USER_MANAGE_ECOMMERCE') && USER_MANAGE_ECOMMERCE)) {

        $greeting_map_link = $greeting_link(
            pg_sales_map_orders_url('', $sig['map_new']['state'], $sig['map_new']['from'], 0),
            $sig['map_new']['name']);

        $greeting_options[] = array('tier' => 2, 'topic' => 'map', 'text' => lang(array(
            'string' => '{var:1} ordered for the first time this month, and that order is worth a look.',
            'vars' => $greeting_map_link)));
        $greeting_options[] = array('tier' => 2, 'topic' => 'map', 'text' => lang(array(
            'string' => 'New on the map this month: {var:1}.',
            'vars' => $greeting_map_link)));
    }

    // The checks the system status widget has already run, taken one at a time.
    // Each of these screens is manager and above (validate_area_access 'manager',
    // role <= 2), so the advice is only offered to someone the link will let in.
    if (in_array($sig['backup_state'], array('warn', 'fail'), true) && ($user['role'] <= 2)) {

        $greeting_backup_href = ($sig['backup_href'] !== '') ? $sig['backup_href'] : 'backups.php';

        // 'warn' is a backup that is a few days old; 'fail' is one over a week old
        // or none at all, and "older than it should be" would be untrue of none.
        $greeting_options[] = array('tier' => 1, 'topic' => 'backup', 'text' => ($sig['backup_state'] === 'warn')
            ? lang(array(
                'string' => 'The last backup is older than it should be, and {var:1} is a five minute job.',
                'vars' => $greeting_link($greeting_backup_href, lang('taking a fresh one'))))
            : lang(array(
                'string' => 'There is no backup from the last week, and {var:1} is a five minute job.',
                'vars' => $greeting_link($greeting_backup_href, lang('taking a fresh one')))));
        $greeting_options[] = array('tier' => 1, 'topic' => 'backup', 'text' => lang(array(
            'string' => 'Nothing here has been backed up recently; {var:1} is where to fix that.',
            'vars' => $greeting_link($greeting_backup_href, lang('the backup screen')))));
    }

    // A release waiting to be downloaded. The check also turns amber when the
    // software has simply never asked, and "a newer version is out" would be
    // untrue then, so the flag the check itself reads decides. Kept apart from
    // the version advice above: an upgrade uploaded but not run is the more
    // urgent of the two, and both at once would be one story told twice.
    if (in_array($sig['update_state'], array('warn', 'fail'), true) && empty($sig['version_pending'])
        && defined('SOFTWARE_UPDATE_AVAILABLE') && SOFTWARE_UPDATE_AVAILABLE && ($user['role'] <= 2)
    ) {

        $greeting_update_link = $greeting_link(
            ($sig['update_href'] !== '') ? $sig['update_href'] : 'software_update.php', lang('the update screen'));

        $greeting_options[] = array('tier' => 1, 'topic' => 'update', 'text' => lang(array(
            'string' => 'A newer version is out, and {var:1} will bring it in.',
            'vars' => $greeting_update_link)));
        $greeting_options[] = array('tier' => 1, 'topic' => 'update', 'text' => lang(array(
            'string' => 'There is an update waiting; {var:1} says what it brings.',
            'vars' => $greeting_update_link)));
    }

    // Stalled cron jobs. The check names no screen of its own -- the per-job
    // times are inside the status widget on this page -- so the sentence carries
    // a link only when the check offers one.
    if (($sig['cron_state'] === 'fail') && ($user['role'] <= 2)) {

        $greeting_options[] = array('tier' => 1, 'topic' => 'cron', 'text' => ($sig['cron_href'] !== '')
            ? lang(array(
                'string' => 'The scheduled tasks have stopped running, which quietly stalls backups and mail; {var:1} says why.',
                'vars' => $greeting_link($sig['cron_href'], lang('the status panel'))))
            : h(lang('The scheduled tasks have stopped running, which quietly stalls backups and mail.')));
        $greeting_options[] = array('tier' => 1, 'topic' => 'cron', 'text' => h(lang(
            'Some scheduled tasks have stopped running, and backups and mail wait until they start again.')));
    }

    // Money owed back to customers. reset=true for the same reason as the basket
    // link below: a filter left in the session would hide the rows.
    if (((int) $sig['refunds_pending']) > 0) {

        $greeting_refunds_count = (int) $sig['refunds_pending'];

        $greeting_options[] = array('tier' => 1, 'topic' => 'refunds', 'text' => lang(array(
            'string' => 'Money is still owed on {var:1}; the customers are waiting.',
            'vars' => $greeting_link('view_orders.php?reset=true&status=refund_pending', lang(array(
                'string' => 'the {var:1} cancelled order{suffix:1}',
                'vars'   => pg_format_number($greeting_refunds_count, 0),
                'suffix' => array(($greeting_refunds_count == 1) ? '' : 's')))))));
        $greeting_options[] = array('tier' => 1, 'topic' => 'refunds', 'text' => lang(array(
            'string' => 'Customers are waiting on {var:1}, and it is worth settling before anything new.',
            'vars' => $greeting_link('view_orders.php?reset=true&status=refund_pending', lang(array(
                'string' => '{var:1} refund{suffix:1}',
                'vars'   => pg_format_number($greeting_refunds_count, 0),
                'suffix' => array(($greeting_refunds_count == 1) ? '' : 's')))))));
    }

    // Orders waiting to leave. The orders list has no shipping filter, so the link
    // opens the list itself, cleared of whatever was left in the session.
    //
    // A shop with orders has orders to pack on any given day, and an order that
    // came in this morning and is not boxed yet is the ordinary state of things,
    // not something gone wrong. One that has waited two days is. So this is an
    // opportunity while the oldest waiting order is under 48 hours old, and only
    // outranks the other openings once something has actually been left behind.
    if (((int) $sig['pending_shipments']) > 0) {

        $greeting_ship_count = (int) $sig['pending_shipments'];
        $greeting_ship_tier = (($sig['pending_shipments_oldest'] !== null)
            && ($sig['pending_shipments_oldest'] < ($greeting_now - 172800))) ? 1 : 2;

        $greeting_ship_link = $greeting_link('view_orders.php?reset=true', lang(array(
            'string' => '{var:1} order{suffix:1}',
            'vars'   => pg_format_number($greeting_ship_count, 0),
            'suffix' => array(($greeting_ship_count == 1) ? '' : 's'))));

        $greeting_options[] = array('tier' => $greeting_ship_tier, 'topic' => 'shipments', 'text' => lang(array(
            'string' => 'The customers behind {var:1} are still waiting for a parcel.',
            'vars' => $greeting_ship_link)));
        $greeting_options[] = array('tier' => $greeting_ship_tier, 'topic' => 'shipments', 'text' => lang(array(
            'string' => 'There {suffix:1} {var:1} paid for and not shipped yet.',
            'vars'   => $greeting_ship_link,
            'suffix' => array(($greeting_ship_count == 1) ? 'is' : 'are'))));
    }

    // The workspace, where something was handed to this person or asked of them.
    // Every link here is a workspace screen, and $sig['ws'] is only filled for a
    // member of the team, which is all those screens ask.
    $greeting_ws = $sig['ws'];

    if (($greeting_ws !== null) && ($greeting_ws['new_task'] !== null)) {

        $greeting_new_task = $greeting_ws['new_task'];
        $greeting_new_task_link = $greeting_link(
            'workspace_tasks.php?task=' . (int) $greeting_new_task['id'], $greeting_new_task['title']);

        // The title goes last among the values: it is free text, and lang() fills
        // the placeholders in order.
        if ($greeting_new_task['by'] !== '') {

            $greeting_options[] = array('tier' => 1, 'topic' => 'ws_task', 'text' => lang(array(
                'string' => '{var:1} handed you a new task: {var:2}.',
                'vars' => array(h($greeting_new_task['by']), $greeting_new_task_link))));

            if ($greeting_new_task['due_label'] !== null) {
                $greeting_options[] = array('tier' => 1, 'topic' => 'ws_task', 'text' => lang(array(
                    'string' => 'There is a new task on your list from {var:1}, due {var:2}: {var:3}.',
                    'vars' => array(h($greeting_new_task['by']), h($greeting_new_task['due_label']), $greeting_new_task_link))));
            }

        } else {
            $greeting_options[] = array('tier' => 1, 'topic' => 'ws_task', 'text' => lang(array(
                'string' => 'There is a new task on your list: {var:1}.',
                'vars' => $greeting_new_task_link)));
        }
    }

    if (($greeting_ws !== null) && ($greeting_ws['overdue'] > 0)) {

        $greeting_options[] = array('tier' => 1, 'topic' => 'ws_overdue', 'text' => ($greeting_ws['overdue'] === 1)
            ? lang(array(
                'string' => 'One of {var:1} is past its due date, and it is the one to open first.',
                'vars' => $greeting_link('workspace_tasks.php', lang('your tasks'))))
            : lang(array(
                'string' => '{var:1} are past their due date, and the oldest is the one to open first.',
                'vars' => $greeting_link('workspace_tasks.php', lang(array(
                    'string' => '{var:1} of your tasks',
                    'vars' => pg_format_number($greeting_ws['overdue'], 0)))))));
    }

    if (($greeting_ws !== null) && ($greeting_ws['mentions'] > 0)) {

        $greeting_mention_href = ($greeting_ws['mention_url'] !== null) ? $greeting_ws['mention_url'] : 'workspace.php';

        $greeting_options[] = array('tier' => 1, 'topic' => 'ws_mention', 'text' => lang(array(
            'string' => 'Somebody {var:1} and is waiting on an answer.',
            'vars' => $greeting_link($greeting_mention_href, lang('mentioned you in a channel')))));
        $greeting_options[] = array('tier' => 1, 'topic' => 'ws_mention', 'text' => lang(array(
            'string' => '{var:1} {suffix:1} waiting for you in the channels.',
            'vars' => $greeting_link($greeting_mention_href, lang(array(
                'string' => '{var:1} mention{suffix:1}',
                'vars'   => pg_format_number($greeting_ws['mentions'], 0),
                'suffix' => array(($greeting_ws['mentions'] == 1) ? '' : 's')))),
            'suffix' => array(($greeting_ws['mentions'] == 1) ? 'is' : 'are'))));
    }

    // A request for a decision that only this person can give. The link opens
    // the newest of them in its channel, where the buttons are.
    if (($greeting_ws !== null) && ($greeting_ws['approvals'] > 0)) {

        $greeting_approval_link = $greeting_link(
            ($greeting_ws['approval_url'] !== null) ? $greeting_ws['approval_url'] : 'workspace.php',
            lang(array(
                'string' => '{var:1} approval request{suffix:1}',
                'vars'   => pg_format_number($greeting_ws['approvals'], 0),
                'suffix' => array(($greeting_ws['approvals'] == 1) ? '' : 's'))));

        $greeting_options[] = array('tier' => 1, 'topic' => 'ws_approval', 'text' => lang(array(
            'string' => '{var:1} {suffix:1} waiting on your answer.',
            'vars'   => $greeting_approval_link,
            'suffix' => array(($greeting_ws['approvals'] == 1) ? 'is' : 'are'))));
    }

    // ── opportunities ──

    if (((int) $sig['forms_week']) > 0) {

        $greeting_forms_count = (int) $sig['forms_week'];

        $greeting_forms_link = $greeting_link('view_submitted_forms.php', lang(array(
            'string' => '{var:1} form{suffix:1}',
            'vars'   => pg_format_number($greeting_forms_count, 0),
            'suffix' => array(($greeting_forms_count == 1) ? '' : 's'))));

        $greeting_options[] = array('tier' => 2, 'topic' => 'forms', 'text' => lang(array(
            'string' => '{var:1} came in this week, and the people who filled them in are waiting to hear back.',
            'vars' => $greeting_forms_link)));
        $greeting_options[] = array('tier' => 2, 'topic' => 'forms', 'text' => lang(array(
            'string' => 'Somebody is waiting on an answer to the {var:1} sent in this week.',
            'vars' => $greeting_forms_link)));
    }

    // The pages list's own filter, so the link opens exactly the pages counted.
    if ((((int) $sig['seo_critical']) > 0) && ($user['role'] < 3)) {

        $greeting_seo_count = (int) $sig['seo_critical'];

        $greeting_seo_link = $greeting_link('view_pages.php?filter=seo_critical', lang(array(
            'string' => '{var:1} page{suffix:1}',
            'vars'   => pg_format_number($greeting_seo_count, 0),
            'suffix' => array(($greeting_seo_count == 1) ? '' : 's'))));

        $greeting_options[] = array('tier' => 2, 'topic' => 'seo', 'text' => lang(array(
            'string' => '{var:1} score{suffix:1} under 30 on SEO, and the fix is usually a title and a description.',
            'vars'   => $greeting_seo_link,
            'suffix' => array(($greeting_seo_count == 1) ? 's' : ''))));
        $greeting_options[] = array('tier' => 2, 'topic' => 'seo', 'text' => lang(array(
            'string' => 'The weakest SEO on the site is on {var:1}, all under 30; a title and a description usually lift them.',
            'vars' => $greeting_seo_link)));
    }

    if (((int) $sig['offers_ending']) > 0) {

        $greeting_offers_count = (int) $sig['offers_ending'];

        $greeting_offers_link = $greeting_link('view_offers.php', lang(array(
            'string' => '{var:1} offer{suffix:1}',
            'vars'   => pg_format_number($greeting_offers_count, 0),
            'suffix' => array(($greeting_offers_count == 1) ? '' : 's'))));

        $greeting_options[] = array('tier' => 2, 'topic' => 'offers', 'text' => lang(array(
            'string' => '{var:1} end{suffix:1} within the week; extend or let go, but decide.',
            'vars'   => $greeting_offers_link,
            'suffix' => array(($greeting_offers_count == 1) ? 's' : ''))));
        $greeting_options[] = array('tier' => 2, 'topic' => 'offers', 'text' => lang(array(
            'string' => 'The clock runs out on {var:1} this week.',
            'vars' => $greeting_offers_link)));
    }

    if (($greeting_ws !== null) && ($greeting_ws['due_today'] > 0)) {

        $greeting_options[] = array('tier' => 2, 'topic' => 'ws_today', 'text' => lang(array(
            'string' => '{var:1} on your list {suffix:1} due today.',
            'vars'   => $greeting_link('workspace_tasks.php', lang(array(
                'string' => '{var:1} task{suffix:1}',
                'vars'   => pg_format_number($greeting_ws['due_today'], 0),
                'suffix' => array(($greeting_ws['due_today'] == 1) ? '' : 's')))),
            'suffix' => array(($greeting_ws['due_today'] == 1) ? 'is' : 'are'))));
    }

    // A task under way with a checklist, and how far along it is. "A few more
    // ticks" is only said when it is true.
    if (($greeting_ws !== null) && ($greeting_ws['doing'] !== null) && ($greeting_ws['doing']['progress'] !== null)
        && ($greeting_ws['doing']['progress']['percent'] < 100)
    ) {

        $greeting_doing = $greeting_ws['doing'];
        $greeting_doing_left = $greeting_doing['progress']['total'] - $greeting_doing['progress']['done'];
        $greeting_doing_link = $greeting_link('workspace_tasks.php?task=' . (int) $greeting_doing['id'], $greeting_doing['title']);

        $greeting_options[] = array('tier' => 2, 'topic' => 'ws_doing', 'text' => ($greeting_doing_left <= 3)
            ? lang(array(
                'string' => '{var:1}% of {var:2} is done; a few more ticks and it is finished.',
                'vars' => array($greeting_doing['progress']['percent'], $greeting_doing_link)))
            : lang(array(
                'string' => '{var:1}% of {var:2} is done, with {var:3} items still to tick.',
                'vars' => array($greeting_doing['progress']['percent'], $greeting_doing_link,
                    pg_format_number($greeting_doing_left, 0)))));
    }

    // The next meeting or visit still ahead today.
    if (($greeting_ws !== null) && ($greeting_ws['event_first'] !== null)) {

        $greeting_event = $greeting_ws['event_first'];
        $greeting_event_link = $greeting_link('workspace_calendar.php',
            ($greeting_event['kind'] === 'visit') ? lang('a visit') : lang('a meeting'));

        $greeting_options[] = array('tier' => 2, 'topic' => 'ws_event', 'text' => ($greeting_event['time'] !== null)
            ? lang(array(
                'string' => 'You have {var:1} at {var:2} today: {var:3}.',
                'vars' => array($greeting_event_link, h($greeting_event['time']), h($greeting_event['title']))))
            : lang(array(
                'string' => 'You have {var:1} on the calendar today: {var:2}.',
                'vars' => array($greeting_event_link, h($greeting_event['title'])))));
    }

    // Top occupied tier only.
    $greeting_tier = 0;

    foreach ($greeting_options as $greeting_option) {
        if (($greeting_tier === 0) || ($greeting_option['tier'] < $greeting_tier)) {
            $greeting_tier = $greeting_option['tier'];
        }
    }

    $greeting_advice_pool = array();

    foreach ($greeting_options as $greeting_option) {
        if ($greeting_option['tier'] === $greeting_tier) {
            $greeting_advice_pool[] = $greeting_option['text'];
        }
    }

    // ── what the second half is, this time ───────────────────────────────────────
    //
    // Not always advice. Something is nearly always open on a working site, and a
    // line that turns every single one of them into a suggestion is nagging, not
    // helping -- you stop reading it, which costs the one time it mattered.
    //
    // So the second half is drawn by kind first: usually the advice when there is
    // something genuinely wrong, less often when it is only an opportunity, and the
    // rest of the time a small thing about the software or nothing more than a
    // civil word. A suggestion you can also just not get is a suggestion; one that
    // arrives every time is an instruction.
    $greeting_tips = array_map('h', array(
        lang('Search opens on Ctrl+K from anywhere, if you would rather not reach for the mouse.'),
        lang('Did you know the widgets can be dragged around by their headers?'),
        lang('If you prefer the light theme, it is in the user menu at the top right.'),
    ));

    // The widget theme settings are on the Dashboard card of Site Settings >
    // Appearance, drawn for designers and administrators only, so only those
    // are told to look.
    if ($user['role'] < 2) {
        $greeting_tips[] = h(lang('You will find the widget theme settings in Site Settings, under Appearance.'));
    }

    // Only for someone in the workspace team: a tip about a screen that will turn
    // the reader away is not a tip.
    if ($sig['ws'] !== null) {
        $greeting_tips[] = h(lang('A message in a channel can become a task from its right-click menu.'));
    }

    // What the software gained lately, as opposed to how to work it.
    //
    // Three attempts to get this right, and the first two were the same mistake in
    // different clothes.
    //
    // Scraping the release notes came first and read like a specification, because
    // that is what it is: "the Comment Moderation card: count of comments awaiting
    // approval and the newest five" is a line for somebody upgrading, not a
    // sentence anybody says out loud.
    //
    // Then the notes were rewritten by hand but still announced -- "A recent update
    // brought this: offers are put together in one place now" -- which is a press
    // release with a colon in it. A label and a colon is the shape of a bulletin;
    // nobody talks like that either.
    //
    // So there is no label and no template. Each line is a whole sentence somebody
    // might say while you were both looking at the screen, and the recency lives
    // inside it as an ordinary word -- "now", "these days", "by the way" -- rather
    // than in a heading. No version number anywhere: a line here outlives the
    // release that brought it by a dozen versions, and the changelog is one click
    // away for anyone who wants the full account.
    // Most of them end with an offer to go and look, and the offer is the link --
    // the whole question, not a word inside it. It is what somebody would actually
    // say next, and it means the sentence is never bent around an anchor: a
    // question reads the same in every language, which a phrase spliced into the
    // middle of one does not.
    //
    // It uses the same anchor as everything else in this line. A second link style
    // was written for it first and that was the mistake: two kinds of link in one
    // sentence makes the reader work out whether the difference means anything,
    // and here it does not.
    $greeting_ask = function ($href, $text) use ($greeting_link) {
        return ' ' . $greeting_link($href, $text);
    };

    $greeting_features = array(
        h(lang('By the way, you can build a variant product in one go now.'))
            . $greeting_ask('add_product.php', lang('Want to add one and see?')),

        h(lang('The file manager was rebuilt, by the way. Folders, uploads and previews all on one screen.'))
            . $greeting_ask('view_folders.php', lang('Shall we take a look?')),

        h(lang('You can put an offer together in one place these days, rule and outcome on the same screen.'))
            . $greeting_ask('add_offer.php', lang('Feel like making one now?')),

        h(lang('You can cap how many devices one account stays signed in from, did you know?'))
            . $greeting_ask('settings_security.php#pgset-session', lang('Want to turn it on?')),

        h(lang('Product groups have a management screen of their own these days.'))
            . $greeting_ask('view_product_groups.php', lang('Care to have a look?')),

        h(lang('Your pages and products carry an SEO score now, out of 100.'))
            . $greeting_ask('view_pages.php', lang('Want to see how yours are doing?')),

        // No offer on this one: the widget it is about is further down this very
        // screen, and pointing somebody somewhere they already are is not help.
        h(lang('You can start a deep database check from the system status widget whenever you like.')),

        h(lang('The comments waiting for approval turn up on the dashboard now, newest first.'))
            . $greeting_ask('view_comments.php', lang('Want to go through them?')),
    );

    // Manager and above, which is what settings_pane.php asks for
    // (validate_area_access($user, 'manager'), so role <= 2). Pointing a
    // contributor at a screen that will turn them away is worse than saying nothing.
    //
    // The address comes from pg_settings_link() rather than being written out: the
    // category screens are what it knows about, and the dialog recognises its own
    // addresses and opens over whatever screen the reader is on.
    if ($user['role'] <= 2) {

        $greeting_features[] =
            h(lang('Settings are split into sections these days, and they open over whatever screen you are on.'))
            . $greeting_ask(pg_settings_link(), lang('Want to open them?'));
    }

    if ($sig['ws'] !== null) {

        $greeting_features[] =
            h(lang('The planning board shows how much of each person\'s day is already spoken for.'))
            . $greeting_ask('workspace_board.php', lang('Want to see this week?'));

        $greeting_features[] =
            h(lang('Tasks carry a checklist these days and show how far along they are.'))
            . $greeting_ask('workspace_tasks.php', lang('Want to look at yours?'));
    }

    // Two kinds of closing word, and the difference matters. The first three are
    // claims about the state of the site and are only true when nothing is open --
    // "nothing needs you right now" printed under a line that just said two
    // products are out of stock is the briefing contradicting itself in the space
    // of one breath. The rest are civil words, true on any day.
    $greeting_closers = array_map('h', array(
        lang('Take it easy.'),
        lang('Enjoy the work.'),
        lang('The panel is all yours.'),
    ));

    if (empty($greeting_advice_pool)) {
        $greeting_closers = array_merge($greeting_closers, array_map('h', array(
            lang('Nothing needs you right now.'),
            lang('Everything here looks in good order.'),
            lang('All quiet on this side, so the day is yours.'),
        )));

        // A claim like the three above, and true on the same terms: nothing on
        // the list, nothing handed over unread, nobody waiting on an answer.
        if (($sig['ws'] !== null) && ($sig['ws']['open'] === 0) && ($sig['ws']['new_tasks'] === 0)
            && ($sig['ws']['mentions'] === 0)
        ) {
            $greeting_closers[] = h(lang('Your task list is clear.'));
        }
    }

    // Something about the web itself, which is neither advice nor a manual.
    //
    // It has no use whatsoever, and that is the point: a screen somebody opens
    // every morning for two years can afford to be interesting once in a while, and
    // a small true thing is what makes a line worth reading when there is nothing
    // to report. Nothing here is about this site -- it is about the web the site
    // lives on.
    //
    // The openings are deliberately all different. "Did you know" on all eight
    // would turn a surprise into a format inside a week, which is the same failure
    // as saying the same sentence every refresh, only slower.
    $greeting_trivia = array_map('h', array(
        lang('The world\'s first webcam was pointed at a coffee pot, so nobody in the Cambridge lab would walk down for nothing.'),
        lang('Forty-four of every hundred people clicked the very first banner ad. No advert has come near that since.'),
        lang('The @ in an email address was chosen because it was the one symbol on the keyboard that turned up in nobody\'s name. That was the whole reason.'),
        lang('Did you know the www at the front of an address means nothing technically? It is a habit left over from the early days.'),
        lang('The only tag you are actually required to write in a valid HTML page is the title. Even the html tag is optional, hard as that is to believe.'),
        lang('The little icon on a browser tab came from no standard: in 1999 a browser started asking every site for favicon.ico, never stopped asking, and it became a convention.'),
        lang('The web forgets faster than you would think: roughly 38% of the pages that existed in 2013 were gone a decade later.'),
        lang('The ping command is named after submarine sonar. Its author had the sound going out and coming back in mind.'),
        lang('The first message ever sent between two computers was two letters long. They typed LOGIN, the machine crashed after LO, and that was how the internet opened.'),
        lang('Tim Berners-Lee has said he is sorry about the two slashes in http://. They were never necessary; he just wrote them that way and the world copied him.'),
        lang('There is a real status code reserved for a teapot refusing to make coffee, 418, and some servers still answer with it.'),
        lang('The man who sent the first email cannot remember what it said. Something like QWERTYUIOP, he thinks.'),
        lang('robots.txt exists because one crawler brought a server to its knees in 1994. The fix was a plain text file, and it is still the same file today.'),
        lang('The first web page ever published is still online at CERN, and it is nothing but text and links.'),
    ));

    // Once in a blue moon, none of the above. Nothing to report and no lesson to
    // teach -- just the thing a person says when they have nothing to say. One load
    // in twenty, because the whole worth of it is that it is not expected, and a
    // surprise on a schedule is a feature.
    $greeting_rare = array_map('h', array(
        lang('Nothing comes to mind to report, but I hope you are keeping well. 🙂'),
        lang('Let the numbers rest a minute. How are you doing?'),
        lang('From where I am sitting everything looks fine. ☕'),
        lang('The panel is working, you are working. Good partnership. 🤝'),
        lang('Today I will just wish you an easy one. ✨'),
    ));

    // The weighting, written as a bag because five entries in a bag is easier to
    // read and to change than a chain of thresholds.
    if (empty($greeting_advice_pool)) {
        $greeting_kinds = array('tip', 'feature', 'trivia', 'closer', 'closer');
    } elseif ($greeting_tier === 1) {
        $greeting_kinds = array('advice', 'advice', 'advice', 'tip', 'feature', 'trivia', 'closer');
    } else {
        $greeting_kinds = array('advice', 'advice', 'tip', 'feature', 'trivia', 'closer', 'closer');
    }


    $greeting_kind = $greeting_kinds[array_rand($greeting_kinds)];

    if (mt_rand(1, 20) === 1) {
        $greeting_kind = 'rare';
    }

    $greeting_advice = '';
    $greeting_advice_topic = '';

    if ($greeting_kind === 'advice') {

        $greeting_last_advice = (string) ($_SESSION['software']['welcome']['greeting_advice'] ?? '');
        $greeting_advice_choices = array_values(array_diff($greeting_advice_pool, array($greeting_last_advice)));

        if (empty($greeting_advice_choices)) {
            $greeting_advice_choices = $greeting_advice_pool;
        }

        $greeting_advice = $greeting_advice_choices[array_rand($greeting_advice_choices)];
        $_SESSION['software']['welcome']['greeting_advice'] = $greeting_advice;

        // The reading is kept off whatever the advice is about, so it has to know.
        foreach ($greeting_options as $greeting_option) {
            if ($greeting_option['text'] === $greeting_advice) {
                $greeting_advice_topic = $greeting_option['topic'];
                break;
            }
        }

    } else {

        if ($greeting_kind === 'rare') {
            $greeting_tail_pool = $greeting_rare;
        } elseif ($greeting_kind === 'trivia') {
            $greeting_tail_pool = $greeting_trivia;
        } elseif ($greeting_kind === 'feature') {
            $greeting_tail_pool = $greeting_features;
        } elseif ($greeting_kind === 'tip') {
            $greeting_tail_pool = $greeting_tips;
        } else {
            $greeting_tail_pool = $greeting_closers;
        }

        $greeting_last_tail = (string) ($_SESSION['software']['welcome']['greeting_tail'] ?? '');
        $greeting_tail_choices = array_values(array_diff($greeting_tail_pool, array($greeting_last_tail)));

        if (empty($greeting_tail_choices)) {
            $greeting_tail_choices = $greeting_tail_pool;
        }

        $greeting_tail = $greeting_tail_choices[array_rand($greeting_tail_choices)];
        $_SESSION['software']['welcome']['greeting_tail'] = $greeting_tail;

        // Already escaped where it was written, and the features carry an anchor
        // this file built itself, so it goes out as it is.
        $greeting_advice = $greeting_tail;
    }

    // ── the read ─────────────────────────────────────────────────────────────────
    //
    // Two kinds, and the first kind wins whenever there is one.
    //
    // A reading that holds two signals against each other says something no tile on
    // this screen can: a tile can report traffic and another can report orders, but
    // only a sentence can say that one moved and the other did not. That is the
    // whole reason this line exists, so where such a reading is available it is the
    // only kind offered.
    //
    // The plain readings are the floor -- one signal, still true, still worth a
    // glance -- for the hours when nothing is moving against anything.
    $greeting_smart = array();
    $greeting_plain = array();

    $has_traffic = ($sig['hits_today'] !== null) && ($sig['hits_before'] !== null);

    // Fifteen percent, because two days of a real site are never equal and calling
    // a four percent drift "up" would be the sort of confident nonsense that makes
    // a line like this untrustworthy.
    $traffic_up   = $has_traffic && ($sig['hits_today'] > ($sig['hits_before'] * 1.15));
    $traffic_down = $has_traffic && ($sig['hits_today'] < ($sig['hits_before'] * 0.85));
    $orders_up    = $has_orders && ($sig['orders_today'] > $sig['orders_before']);
    $orders_flat  = $has_orders && ($sig['orders_today'] <= $sig['orders_before']);
    $perf_slower  = $has_perf && ($sig['perf_today'] > ($sig['perf_before'] * 1.15));

    if ($traffic_up && $orders_up) {
        $greeting_smart[] = array('topic' => 'orders',
            'text' => lang('Traffic is up on yesterday and orders have moved up with it.'));
    }

    if ($traffic_up && $orders_flat) {
        $greeting_smart[] = array('topic' => 'orders',
            'text' => lang('More visits than yesterday, and the same orders as yesterday.'));
    }

    if ($traffic_down && $orders_up) {
        $greeting_smart[] = array('topic' => 'orders',
            'text' => lang('Fewer visits than yesterday and more orders, so the people arriving are the right ones.'));
    }

    if ($traffic_up && $perf_slower) {
        $greeting_smart[] = array('topic' => 'perf',
            'text' => lang('Traffic is up and the pages have slowed with it, which is usually one story rather than two.'));
    }

    if ($traffic_up && !$perf_slower && ($sig['perf_today'] !== null)) {
        $greeting_smart[] = array('topic' => 'perf', 'text' => lang(array(
            'string' => 'Traffic is up on yesterday and the pages are holding at {var:1} ms.',
            'vars' => pg_format_number($sig['perf_today'], 0))));
    }

    if ($orders_up && (((int) $sig['out_of_stock']) > 0)) {
        $greeting_smart[] = array('topic' => 'stock', 'text' => lang(array(
            'string' => (((int) $sig['out_of_stock']) === 1)
                ? 'Orders are running ahead of yesterday while {var:1} product sits out of stock.'
                : 'Orders are running ahead of yesterday while {var:1} products sit out of stock.',
            'vars' => pg_format_number($sig['out_of_stock'], 0))));
    }

    // Nobody bought, but somebody nearly did. That is a different day from one
    // where nothing happened at all, and no tile on this screen says it.
    if ($has_orders && ($sig['orders_today'] === 0) && (((int) $sig['carts_week']) > 0)) {
        $greeting_smart[] = array('topic' => 'carts', 'text' => lang(array(
            'string' => (((int) $sig['carts_week']) === 1)
                ? 'No orders today, though {var:1} basket was filled and left this week.'
                : 'No orders today, though {var:1} baskets were filled and left this week.',
            'vars' => pg_format_number($sig['carts_week'], 0))));
    }

    if ((((int) $sig['new_contacts']) > 0) && $has_orders && ($sig['orders_today'] === 0)) {
        $greeting_smart[] = array('topic' => 'contacts',
            'text' => lang('New people are still arriving, though none of them has ordered today.'));
    }

    if (($sig['health'] !== null) && ($sig['health'] < 70) && (((int) $sig['log_today']) > 0)) {
        $greeting_smart[] = array('topic' => 'health', 'text' => lang(array(
            'string' => 'There has been activity today and system health is at {var:1}%, which is worth a look together.',
            'vars' => $sig['health'])));
    }

    // Somewhere ordered that had not ordered in the month before it. This belongs
    // with the combinational readings rather than with the plain ones because that
    // is what it is: this week held against the three weeks behind it.
    if ($sig['map_new'] !== null) {
        $greeting_smart[] = array('topic' => 'map', 'text' => lang(array(
            'string' => '{var:1} ordered for the first time this month.',
            'vars' => $sig['map_new']['name'])));
    }

    // This week against the week before it. Ahead needs a week before it to be
    // ahead of; behind needs one with a few orders in it, or one quiet week
    // against another would be reported as a slide.
    if (($sig['orders_week'] !== null) && ($sig['orders_prev_week'] > 0)
        && ($sig['orders_week'] > $sig['orders_prev_week'])
    ) {
        $greeting_smart[] = array('topic' => 'orders', 'text' => lang(array(
            'string' => 'This week is ahead of last week on orders, {var:1} to {var:2}.',
            'vars' => array(pg_format_number($sig['orders_week'], 0), pg_format_number($sig['orders_prev_week'], 0)))));
    }

    if (($sig['orders_week'] !== null) && ($sig['orders_prev_week'] >= 3)
        && ($sig['orders_week'] < $sig['orders_prev_week'])
    ) {
        $greeting_smart[] = array('topic' => 'orders', 'text' => lang(array(
            'string' => 'Orders are behind last week\'s pace, {var:1} against {var:2}.',
            'vars' => array(pg_format_number($sig['orders_week'], 0), pg_format_number($sig['orders_prev_week'], 0)))));
    }

    // Two readings of the same day side by side, and no more than that. The
    // traffic figure and the firewall log are counted in different places, so
    // nothing here knows whether the blocked requests are inside the rise, and the
    // line does not claim they caused it.
    if ((((int) $sig['waf_blocked']) > 0) && $traffic_up) {
        $greeting_smart[] = array('topic' => 'waf', 'text' => lang(array(
            'string' => 'Traffic is up on yesterday, and the firewall turned away {var:1} request{suffix:1} over the same day.',
            'vars'   => pg_format_number($sig['waf_blocked'], 0),
            'suffix' => array(($sig['waf_blocked'] == 1) ? '' : 's'))));
    }

    if (($sig['ws'] !== null) && ($sig['ws']['overdue'] > 0) && ($sig['ws']['done_week'] > 0)) {
        $greeting_smart[] = array('topic' => 'ws_overdue', 'text' => lang(array(
            'string' => 'You closed {var:1} task{suffix:1} this week, and {var:2} on your list {suffix:2} still past due.',
            'vars'   => array(pg_format_number($sig['ws']['done_week'], 0), pg_format_number($sig['ws']['overdue'], 0)),
            'suffix' => array(($sig['ws']['done_week'] == 1) ? '' : 's', ($sig['ws']['overdue'] == 1) ? 'is' : 'are'))));
    }

    if (($sig['ws'] !== null) && ($sig['ws']['open'] === 0) && ($sig['ws']['new_tasks'] === 0)
        && $has_orders && ($sig['orders_today'] > 0)
    ) {
        $greeting_smart[] = array('topic' => 'ws_open',
            'text' => lang('Orders are coming in and your task list is clear.'));
    }

    // ── the floor ──
    if ($has_orders && ($sig['orders_today'] > $sig['orders_before'])) {
        $greeting_plain[] = array('topic' => 'orders',
            'text' => lang('Orders are running ahead of where they were yesterday.'));
    } elseif ($has_orders && ($sig['orders_today'] === 0) && ($sig['orders_before'] > 0)) {
        $greeting_plain[] = array('topic' => 'orders', 'text' => (((int) $sig['visitors_now']) > 0)
            ? lang('No orders yet today, though people are on the site as we speak.')
            : lang('No orders yet today, and there are hours left in it.'));
    }

    if ($has_perf && ($sig['perf_today'] < $sig['perf_before'])) {
        $greeting_plain[] = array('topic' => 'perf', 'text' => lang(array(
            'string' => 'The site is answering in {var:1} ms, quicker than it managed yesterday.',
            'vars' => pg_format_number($sig['perf_today'], 0))));
    } elseif (($sig['perf_today'] !== null) && ($sig['perf_today'] <= 200)) {
        $greeting_plain[] = array('topic' => 'perf', 'text' => lang(array(
            'string' => 'Pages are coming back in {var:1} ms, which is comfortable.',
            'vars' => pg_format_number($sig['perf_today'], 0))));
    } elseif ($sig['perf_today'] !== null) {
        $greeting_plain[] = array('topic' => 'perf', 'text' => lang(array(
            'string' => 'Pages are averaging {var:1} ms today.',
            'vars' => pg_format_number($sig['perf_today'], 0))));
    }

    if (($sig['health'] !== null) && ($sig['health'] >= 90)) {
        $greeting_plain[] = array('topic' => 'health',
            'text' => lang('System health is high and the infrastructure side is clear.'));
    } elseif (($sig['health'] !== null) && ($sig['health'] >= 70)) {
        $greeting_plain[] = array('topic' => 'health', 'text' => lang(array(
            'string' => 'System health is sitting at a reasonable {var:1}%.',
            'vars' => $sig['health'])));
    } elseif ($sig['health'] !== null) {
        $greeting_plain[] = array('topic' => 'health', 'text' => lang(array(
            'string' => 'Everything is running, though system health has slipped to {var:1}%.',
            'vars' => $sig['health'])));
    }

    if (((int) $sig['visitors_now']) > 0) {
        $greeting_plain[] = array('topic' => 'visitors', 'text' => (((int) $sig['visitors_now']) === 1)
            ? lang('One person is looking around the site right now.')
            : lang(array(
                'string' => '{var:1} people are looking around the site right now.',
                'vars' => pg_format_number($sig['visitors_now'], 0))));
    }

    // Where the week's orders are coming from. The place is named rather than
    // linked because this half of the line is escaped whole -- the advice half is
    // the one that carries an anchor.
    if ($sig['map_lead'] !== null) {

        $greeting_plain[] = array('topic' => 'map', 'text' => lang(array(
            'string' => '{var:1} leads the week with {var:2} order{suffix:2}.',
            'vars'   => array($sig['map_lead']['name'], pg_format_number($sig['map_lead']['count'], 0)),
            'suffix' => array('', ($sig['map_lead']['count'] == 1) ? '' : 's'))));

        $greeting_plain[] = array('topic' => 'map', 'text' => lang(array(
            'string' => 'The busiest place on the map this week: {var:1}.',
            'vars'   => $sig['map_lead']['name'])));
    }

    if (((int) $sig['new_contacts']) > 0) {
        $greeting_plain[] = array('topic' => 'contacts', 'text' => (((int) $sig['new_contacts']) === 1)
            ? lang('One new person joined your contacts this week.')
            : lang(array(
                'string' => '{var:1} new people joined your contacts this week.',
                'vars' => pg_format_number($sig['new_contacts'], 0))));
    }

    if (((int) $sig['low_stock']) > 0) {
        $greeting_plain[] = array('topic' => 'stock', 'text' => lang(array(
            'string' => '{var:1} product{suffix:1} {suffix:2} down to the last few, though not out yet.',
            'vars'   => pg_format_number($sig['low_stock'], 0),
            'suffix' => array(($sig['low_stock'] == 1) ? '' : 's', ($sig['low_stock'] == 1) ? 'is' : 'are'))));
    }

    if (((int) $sig['forms_today']) > 0) {
        $greeting_plain[] = array('topic' => 'forms', 'text' => lang(array(
            'string' => '{var:1} form{suffix:1} came in today.',
            'vars'   => pg_format_number($sig['forms_today'], 0),
            'suffix' => array(($sig['forms_today'] == 1) ? '' : 's'))));
    }

    if (((int) $sig['staff_online']) === 1) {
        $greeting_plain[] = array('topic' => 'staff',
            'text' => lang('One of your colleagues is signed in right now.'));
    } elseif (((int) $sig['staff_online']) > 1) {
        $greeting_plain[] = array('topic' => 'staff', 'text' => lang(array(
            'string' => '{var:1} of your colleagues are signed in right now.',
            'vars' => pg_format_number($sig['staff_online'], 0))));
    }

    if ($sig['campaign_next'] !== null) {
        $greeting_plain[] = array('topic' => 'campaign', 'text' => lang(array(
            'string' => 'A campaign is scheduled to go out {var:1}: “{var:2}”.',
            'vars' => array($sig['campaign_next']['when'], $sig['campaign_next']['subject']))));
    }

    if (((int) $sig['waf_blocked']) > 0) {
        $greeting_plain[] = array('topic' => 'waf', 'text' => lang(array(
            'string' => 'The firewall turned away {var:1} request{suffix:1} in the last day.',
            'vars'   => pg_format_number($sig['waf_blocked'], 0),
            'suffix' => array(($sig['waf_blocked'] == 1) ? '' : 's'))));
    }

    // Monitor mode, from the status check: it records what it would have stopped
    // and stops nothing, which an operator is worth reminding of now and then.
    if ($sig['waf_mode_state'] === 'warn') {
        $greeting_plain[] = array('topic' => 'waf',
            'text' => lang('The firewall is only watching at the moment, not blocking.'));
    }

    if ($sig['ws'] !== null) {

        $greeting_ws_read = $sig['ws'];

        if (($greeting_ws_read['open'] > 0) && ($greeting_ws_read['overdue'] === 0)) {
            $greeting_plain[] = array('topic' => 'ws_open', 'text' => lang(array(
                'string' => '{var:1} task{suffix:1} {suffix:2} open on your list and none of them is late.',
                'vars'   => pg_format_number($greeting_ws_read['open'], 0),
                'suffix' => array(($greeting_ws_read['open'] == 1) ? '' : 's', ($greeting_ws_read['open'] == 1) ? 'is' : 'are'))));
        }

        if ($greeting_ws_read['waiting'] > 0) {
            $greeting_plain[] = array('topic' => 'ws_open', 'text' => lang(array(
                'string' => '{var:1} of your tasks {suffix:1} waiting on somebody else.',
                'vars'   => pg_format_number($greeting_ws_read['waiting'], 0),
                'suffix' => array(($greeting_ws_read['waiting'] == 1) ? 'is' : 'are'))));
        }

        if ($greeting_ws_read['done_week'] > 0) {
            $greeting_plain[] = array('topic' => 'ws_done', 'text' => lang(array(
                'string' => 'You finished {var:1} task{suffix:1} in the last seven days.',
                'vars'   => pg_format_number($greeting_ws_read['done_week'], 0),
                'suffix' => array(($greeting_ws_read['done_week'] == 1) ? '' : 's'))));
        }

        if ($greeting_ws_read['team_done_week'] > 0) {
            $greeting_plain[] = array('topic' => 'ws_done', 'text' => lang(array(
                'string' => 'The team closed {var:1} task{suffix:1} this week.',
                'vars'   => pg_format_number($greeting_ws_read['team_done_week'], 0),
                'suffix' => array(($greeting_ws_read['team_done_week'] == 1) ? '' : 's'))));
        }

        if (($greeting_ws_read['doing'] !== null) && ($greeting_ws_read['doing']['progress'] !== null)) {
            $greeting_plain[] = array('topic' => 'ws_doing', 'text' => lang(array(
                'string' => '{var:1} of {var:2} items are done on {var:3}.',
                'vars'   => array(
                    pg_format_number($greeting_ws_read['doing']['progress']['done'], 0),
                    pg_format_number($greeting_ws_read['doing']['progress']['total'], 0),
                    $greeting_ws_read['doing']['title']))));
        }

        // Counted from the meetings still ahead or under way, so the first of them
        // is the next one to go to.
        if (($greeting_ws_read['events_today'] > 0) && ($greeting_ws_read['event_first'] !== null)) {
            $greeting_event_count = $greeting_ws_read['events_today'];
            $greeting_event_time = $greeting_ws_read['event_first']['time'];

            if (($greeting_event_count === 1) && ($greeting_event_time !== null)) {
                $greeting_event_read = lang(array(
                    'string' => 'There is one meeting on your calendar today, at {var:1}.',
                    'vars' => $greeting_event_time));
            } elseif ($greeting_event_count === 1) {
                $greeting_event_read = lang('There is one meeting on your calendar today.');
            } elseif ($greeting_event_time !== null) {
                $greeting_event_read = lang(array(
                    'string' => 'You have {var:1} meetings on the calendar today, the first at {var:2}.',
                    'vars' => array(pg_format_number($greeting_event_count, 0), $greeting_event_time)));
            } else {
                $greeting_event_read = lang(array(
                    'string' => 'You have {var:1} meetings on the calendar today.',
                    'vars' => pg_format_number($greeting_event_count, 0)));
            }

            $greeting_plain[] = array('topic' => 'ws_event', 'text' => $greeting_event_read);
        }
    }

    // A reading that holds two signals against each other is worth more than one
    // that reports a single number -- but preferring those alone was a mistake and
    // a bad one. On a settled site there is often exactly one combination to be
    // found, and offering only that meant the identical sentence on every single
    // refresh: ten out of ten, which is not a briefing, it is nagging.
    //
    // So both kinds go in the pool always, and the combinational ones simply count
    // twice. They still come up about half the time, and the line always has
    // somewhere else to go.
    $greeting_reads = $greeting_plain;

    foreach ($greeting_smart as $greeting_smart_row) {
        $greeting_reads[] = $greeting_smart_row;
        $greeting_reads[] = $greeting_smart_row;
    }

    // Off the advice's subject where there is anywhere else to go, and back onto it
    // where there is not: a repetitive line still beats an empty one.
    $greeting_read_pool = array();

    foreach ($greeting_reads as $greeting_read_row) {
        if ($greeting_read_row['topic'] !== $greeting_advice_topic) {
            $greeting_read_pool[] = $greeting_read_row['text'];
        }
    }

    if (empty($greeting_read_pool)) {
        foreach ($greeting_reads as $greeting_read_row) {
            $greeting_read_pool[] = $greeting_read_row['text'];
        }
    }

    // A floor under the floor, for an install where every gate is shut.
    if (empty($greeting_read_pool)) {
        $greeting_read_pool[] = lang('Everything here looks in good order.');
    }

    // Two back, not one. Excluding only the last leaves an A B A B flap when there
    // are two candidates, which reads as repetition just as much as A A A does.
    // With a memory two deep, nothing comes round again inside three refreshes
    // wherever there are three things to say.
    $greeting_seen = $_SESSION['software']['welcome']['greeting_seen'] ?? array();

    if (!is_array($greeting_seen)) {
        $greeting_seen = array();
    }

    $greeting_read_choices = array_values(array_diff($greeting_read_pool, $greeting_seen));

    // Give the memory back one entry at a time rather than forgetting all of it:
    // with exactly two things to say, the older one is still the better answer.
    if (empty($greeting_read_choices) && (count($greeting_seen) > 1)) {
        $greeting_read_choices = array_values(
            array_diff($greeting_read_pool, array_slice($greeting_seen, -1)));
    }

    if (empty($greeting_read_choices)) {
        $greeting_read_choices = $greeting_read_pool;
    }

    $greeting_read = $greeting_read_choices[array_rand($greeting_read_choices)];

    $greeting_seen[] = $greeting_read;
    $_SESSION['software']['welcome']['greeting_seen'] = array_slice($greeting_seen, -2);

    // The reading is escaped; the advice carries one anchor this file built itself
    // and is already safe, so it goes out as it is.
    $output_greeting_nudge =
        '<span class="pg-brief-line">' . h($greeting_read) . ' ' . $greeting_advice . '</span>';

    // The workspace strip under the sentence.
    //
    // The sentence rotates; the strip does not. It is the glance -- what was handed
    // over, what is under way, what is late -- and the sentence is the read. They
    // are allowed to touch the same task: the strip is not a chip repeating the
    // sentence's target, it is the same few facts in the same place every time.
    //
    // In order of how much each wants the reader, and three at most: a fourth
    // item turns a glance into a list.
    $output_greeting_ws = '';

    if ($sig['ws'] !== null) {

        $greeting_strip = array();
        $greeting_strip_ws = $sig['ws'];

        $greeting_strip_title = function ($title) {
            return h(mb_strimwidth((string) $title, 0, 48, '…', 'UTF-8'));
        };

        // A running timer leads: it is the one item that goes on counting
        // while nobody looks. The elapsed time is drawn here and kept ticking
        // by the page script from data-pg-elapsed, so it never needs a request.
        if (!empty($greeting_strip_ws['timer'])) {
            $greeting_strip_elapsed = max(0, time() - (int) $greeting_strip_ws['timer']['started_at']);

            $greeting_strip[] =
                '<a class="pg-brief-ws-item hover-lift is-live" href="workspace_tasks.php?task=' . (int) $greeting_strip_ws['timer']['task_id'] . '">'
                . '<i class="bi bi-stopwatch" aria-hidden="true"></i>'
                . '<span class="pg-brief-ws-label">' . h(lang('Timer running')) . '</span>'
                . '<span class="pg-brief-ws-text">' . $greeting_strip_title($greeting_strip_ws['timer']['title']) . '</span>'
                . '<span class="pg-brief-ws-pct" data-pg-elapsed="' . (int) $greeting_strip_ws['timer']['started_at'] . '">'
                . sprintf('%d:%02d', floor($greeting_strip_elapsed / 3600), floor(($greeting_strip_elapsed % 3600) / 60)) . '</span>'
                . '</a>';
        }

        if ($greeting_strip_ws['new_task'] !== null) {
            $greeting_strip[] =
                '<a class="pg-brief-ws-item hover-lift is-new" href="workspace_tasks.php?task=' . (int) $greeting_strip_ws['new_task']['id'] . '">'
                . '<i class="bi bi-check2-square" aria-hidden="true"></i>'
                . '<span class="pg-brief-ws-label">' . h(lang('New task')) . '</span>'
                . '<span class="pg-brief-ws-text">' . $greeting_strip_title($greeting_strip_ws['new_task']['title']) . '</span>'
                . '</a>';
        }

        // Somebody asked for a yes or a no and is waiting on it.
        if (!empty($greeting_strip_ws['approvals'])) {
            $greeting_strip[] =
                '<a class="pg-brief-ws-item hover-lift is-new" href="' . h(($greeting_strip_ws['approval_url'] !== null) ? $greeting_strip_ws['approval_url'] : 'workspace.php') . '">'
                . '<i class="bi bi-patch-question" aria-hidden="true"></i>'
                . '<span class="pg-brief-ws-label">' . h(lang('Awaiting your approval')) . '</span>'
                . '<span class="pg-brief-ws-text">' . h(pg_format_number($greeting_strip_ws['approvals'], 0)) . '</span>'
                . '</a>';
        }

        if (($greeting_strip_ws['doing'] !== null) && ($greeting_strip_ws['doing']['progress'] !== null)) {

            $greeting_strip_percent = (int) $greeting_strip_ws['doing']['progress']['percent'];

            $greeting_strip[] =
                '<a class="pg-brief-ws-item hover-lift" href="workspace_tasks.php?task=' . (int) $greeting_strip_ws['doing']['id'] . '">'
                . '<i class="bi bi-list-check" aria-hidden="true"></i>'
                . '<span class="pg-brief-ws-label">' . h(lang('In progress')) . '</span>'
                . '<span class="pg-brief-ws-text">' . $greeting_strip_title($greeting_strip_ws['doing']['title']) . '</span>'
                . '<span class="pg-brief-ws-bar" aria-hidden="true">'
                . '<span style="width:' . $greeting_strip_percent . '%"></span></span>'
                . '<span class="pg-brief-ws-pct">' . h(lang(array('string' => '{var:1}%', 'vars' => $greeting_strip_percent))) . '</span>'
                . '</a>';
        }

        if ($greeting_strip_ws['overdue'] > 0) {
            $greeting_strip[] =
                '<a class="pg-brief-ws-item hover-lift is-late" href="workspace_tasks.php">'
                . '<i class="bi bi-alarm" aria-hidden="true"></i>'
                . '<span class="pg-brief-ws-label">' . h(lang('Overdue')) . '</span>'
                . '<span class="pg-brief-ws-text">' . h(pg_format_number($greeting_strip_ws['overdue'], 0)) . '</span>'
                . '</a>';
        }

        if ($greeting_strip_ws['mentions'] > 0) {
            $greeting_strip[] =
                '<a class="pg-brief-ws-item hover-lift is-new" href="' . h(($greeting_strip_ws['mention_url'] !== null) ? $greeting_strip_ws['mention_url'] : 'workspace.php') . '">'
                . '<i class="bi bi-at" aria-hidden="true"></i>'
                . '<span class="pg-brief-ws-label">' . h(lang('Mentions')) . '</span>'
                . '<span class="pg-brief-ws-text">' . h(pg_format_number($greeting_strip_ws['mentions'], 0)) . '</span>'
                . '</a>';
        }

        // Due today and the open count only when nothing is late: beside an
        // overdue count they say less than it does.
        if (($greeting_strip_ws['overdue'] === 0) && ($greeting_strip_ws['due_today'] > 0)) {
            $greeting_strip[] =
                '<a class="pg-brief-ws-item hover-lift" href="workspace_tasks.php">'
                . '<i class="bi bi-calendar-check" aria-hidden="true"></i>'
                . '<span class="pg-brief-ws-label">' . h(lang('Due today')) . '</span>'
                . '<span class="pg-brief-ws-text">' . h(pg_format_number($greeting_strip_ws['due_today'], 0)) . '</span>'
                . '</a>';
        } elseif (($greeting_strip_ws['overdue'] === 0) && ($greeting_strip_ws['open'] > 0)) {
            $greeting_strip[] =
                '<a class="pg-brief-ws-item hover-lift" href="workspace_tasks.php">'
                . '<i class="bi bi-inbox" aria-hidden="true"></i>'
                . '<span class="pg-brief-ws-label">' . h(lang('Open tasks')) . '</span>'
                . '<span class="pg-brief-ws-text">' . h(pg_format_number($greeting_strip_ws['open'], 0)) . '</span>'
                . '</a>';
        }

        if (!empty($greeting_strip)) {
            $output_greeting_ws =
                '<div class="pg-brief-ws" role="group" aria-label="' . h(lang('Workspace')) . '">'
                . implode('', array_slice($greeting_strip, 0, 3))
                . '</div>';
        }
    }

    return array(
        'icon'     => $output_greeting_icon,
        'greeting' => $output_greeting_message_text,
        'line'     => $output_greeting_nudge,
        'strip'    => $output_greeting_ws,
        'next'     => ($sig['ws'] !== null) ? pg_dashboard_brief_next($sig['ws']['events_next'], time()) : '',
        'ws'       => $sig['ws'],
    );
}

/**
 * Where an event on today's calendar stands against now.
 *
 * The page script works the same sum every minute from the data attributes
 * pg_dashboard_brief_next() writes, so the two must agree: minutes are rounded
 * up, which keeps "in 1 min" on screen until the minute it starts.
 *
 * @param int  $starts_at unix time
 * @param int  $ends_at   unix time
 * @param bool $all_day   no start time worth counting down to
 * @param int  $now       unix time
 * @return array state ('soon', 'now', 'day' or 'over') and text (plain, may be empty)
 */
function pg_dashboard_brief_until($starts_at, $ends_at, $all_day, $now)
{
    if ((int) $ends_at <= (int) $now) {
        return array('state' => 'over', 'text' => '');
    }

    if ($all_day) {
        return array('state' => 'day', 'text' => '');
    }

    if ((int) $starts_at <= (int) $now) {
        return array('state' => 'now', 'text' => lang('Now'));
    }

    $minutes = (int) ceil(((int) $starts_at - (int) $now) / 60);

    if ($minutes < 60) {
        return array('state' => 'soon', 'text' => lang(array('string' => 'in {var:1} min', 'vars' => array($minutes))));
    }

    $hours = intdiv($minutes, 60);
    $minutes = $minutes % 60;

    return array('state' => 'soon', 'text' => ($minutes === 0)
        ? lang(array('string' => 'in {var:1} h', 'vars' => array($hours)))
        : lang(array('string' => 'in {var:1} h {var:2} min', 'vars' => array($hours, $minutes))));
}

/**
 * The meeting or visit coming up next, as the line under the clock.
 *
 * Every event still to come today is written out and CSS shows the first: when
 * it ends the page script removes it and the next one is already there, with
 * no request. Events with a start time come before the all-day ones, because
 * a countdown is the thing worth the space.
 *
 * @param array $events ws_dashboard_signals() events_next
 * @param int   $now    unix time
 * @return string markup, empty when nothing is left today
 */
function pg_dashboard_brief_next($events, $now)
{
    $timed = array();
    $days = array();

    foreach ((array) $events as $event) {
        $all_day = !empty($event['all_day']);
        $until = pg_dashboard_brief_until((int) $event['starts_at'], (int) $event['ends_at'], $all_day, $now);

        if ($until['state'] === 'over') {
            continue;
        }

        $visit = (($event['kind'] ?? '') === 'visit');
        $kind = $visit ? lang('Customer visit') : lang('Meeting');
        $time = $all_day
            ? lang('All day')
            : get_absolute_time(array(
                'timestamp'     => (int) $event['starts_at'],
                'type'          => 'time',
                'timezone_type' => 'site',
                'format'        => 'plain_text',
            ));

        $item =
            '<a class="pg-next hover-lift' . (($until['state'] === 'now') ? ' is-now' : '') . '" href="workspace_calendar.php"'
            . ' data-pg-starts="' . (int) $event['starts_at'] . '" data-pg-ends="' . (int) $event['ends_at'] . '"'
            . ' data-pg-all-day="' . ($all_day ? '1' : '0') . '" title="' . h($kind . ': ' . $event['title']) . '">'
            . '<i class="bi ' . ($visit ? 'bi-geo-alt' : 'bi-people') . '" aria-hidden="true"></i>'
            . '<span class="visually-hidden">' . h($kind) . ':</span>'
            . '<span class="pg-next-time">' . h($time) . '</span>'
            . '<span class="pg-next-title">' . h(mb_strimwidth((string) $event['title'], 0, 40, '…', 'UTF-8')) . '</span>'
            . '<span class="pg-next-until">' . h($until['text']) . '</span>'
            . '</a>';

        if ($all_day) {
            $days[] = $item;
        } else {
            $timed[] = $item;
        }
    }

    if (empty($timed) && empty($days)) {
        return '';
    }

    return '<div class="pg-clockbar-next" role="group" aria-label="' . h(lang('Next up')) . '">'
        . implode('', array_merge($timed, $days))
        . '</div>';
}
