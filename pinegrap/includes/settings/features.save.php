<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings - what the Features screen writes: which parts of the software are switched on, and how images are handled.
 *
 * Runs in the scope of includes/settings/screen.php, after the token check and
 * after post_value() is defined. Only the columns edited by the cards on this
 * screen are written, so a screen that was not submitted cannot have its
 * settings overwritten -- which is what the single screen used to do.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_SETTINGS_ENTRY')) {
    exit;
}



    // ── Performance monitoring ───────────────────────────────────────────
    //
    // Switching it off discards what was collected. Keeping the rows would
    // leave a report that silently ages: the screen still opens, the numbers
    // still look current, and nothing says they stopped being updated weeks
    // ago. Stale data presented as live is worse than no data.
    //
    // Only on the transition, so re-saving the screen while it is already off
    // does not fire the queries again.
    $perf_monitor_new = post_value('perf_monitor') ? 1 : 0;
    $perf_monitor_old = (int) db_value("SELECT perf_monitor FROM config");

    if ($perf_monitor_old === 1 && $perf_monitor_new === 0) {
        if (db_item("SHOW TABLES LIKE 'perf_stats'")) {
            db("TRUNCATE perf_stats");
        }

        if (db_item("SHOW TABLES LIKE 'perf_log'")) {
            db("TRUNCATE perf_log");
        }

        log_activity(lang('performance monitoring was turned off and its records were cleared'), $_SESSION['sessionusername']);
    }

    // ── Image limits ────────────────────────────────────
    // Same fragment pattern as the firewall block below: on an install without
    // the 2026.4.4 columns nothing is written and the save still succeeds.
    //
    // Clamped here as well as in pg_image_settings(). The screen is not the
    // only way into this table — a restored backup or a hand-edited row can
    // carry anything — and a ceiling of zero would scale every product photo
    // to a single pixel.
    $sql_image_settings = '';

    if (pg_image_settings_ready()) {

        $image_product_max = max(200, min(20000, (int) post_value('image_product_max_dimension')));
        $image_product_min = max(0,   min($image_product_max, (int) post_value('image_product_min_dimension')));
        $image_file_max    = max(200, min(20000, (int) post_value('image_file_max_dimension')));

        // The trigger cannot sit below the target: the button it controls
        // would then be offered for images the resize would not change.
        $image_file_trigger = max($image_file_max, min(20000, (int) post_value('image_file_resize_trigger')));

        $image_quality = max(40, min(100, (int) post_value('image_resize_quality')));

        $sql_image_settings =
            "image_product_optimize = '" . escape(post_value('image_product_optimize') ? 1 : 0) . "',
             image_product_max_dimension = " . $image_product_max . ",
             image_product_min_dimension = " . $image_product_min . ",
             image_file_resize_trigger = " . $image_file_trigger . ",
             image_file_max_dimension = " . $image_file_max . ",
             image_resize_quality = " . $image_quality . ",";
    }
    // Only what the cards on this screen edit.
    db("UPDATE config
        SET
            mobile = '" . escape(post_value('mobile')) . "',
            search_type = '" . escape(post_value('search_type')) . "',
            social_networking = '" . escape(post_value('social_networking')) . "',
            social_networking_type = '" . escape(post_value('social_networking_type')) . "',
            social_networking_facebook = '" . escape(post_value('social_networking_facebook')) . "',
            social_networking_twitter = '" . escape(post_value('social_networking_twitter')) . "',
            social_networking_linkedin = '" . escape(post_value('social_networking_linkedin')) . "',
            social_networking_whatsapp = '" . escape(post_value('social_networking_whatsapp')) . "',
            social_networking_telegram = '" . escape(post_value('social_networking_telegram')) . "',
            social_networking_pinterest = '" . escape(post_value('social_networking_pinterest')) . "',
            social_networking_reddit = '" . escape(post_value('social_networking_reddit')) . "',
            social_networking_email = '" . escape(post_value('social_networking_email')) . "',
            social_networking_code = '" . escape(post_value('social_networking_code')) . "',
            auto_dialogs = '" . escape(post_value('auto_dialogs')) . "',
            badge_label = '" . escape(post_value('badge_label')) . "',
            perf_monitor = '" . escape($perf_monitor_new) . "',
            forms = '" . escape(post_value('forms')) . "',
            calendars = '" . escape(post_value('calendars')) . "',
            ads = '" . escape(post_value('ads')) . "',
            signature_tsa_url = '" . escape(trim(post_value('signature_tsa_url'))) . "',
            signature_tsa_auth = '" . escape((post_value('signature_tsa_auth') === 'basic') ? 'basic' : 'none') . "',
            signature_tsa_username = '" . escape(post_value('signature_tsa_username')) . "',
            signature_tsa_password = '" . escape(post_value('signature_tsa_password')) . "',
            " . $sql_image_settings . "
            last_modified_user_id = '" . USER_ID . "',
            last_modified_timestamp = UNIX_TIMESTAMP()");

// The card's own button: the settings are written first, then the authority is
// asked to stamp a throwaway digest. The values are handed over explicitly
// rather than read from the constants, which still hold what this request was
// started with - testing those would answer a question nobody asked.
if (post_value('submit_test_tsa')) {

    $test_url = trim(post_value('signature_tsa_url'));

    if ($test_url === '') {
        $liveform->add_notice(lang('No time stamp authority is set, so nothing was asked.'));

    } else {
        $test_reason = '';
        $test_stamp = pg_signature_request_stamp(
            hash('sha256', 'pinegrap-tsa-test-' . microtime(true)),
            array(
                'url' => $test_url,
                'auth' => post_value('signature_tsa_auth'),
                'username' => post_value('signature_tsa_username'),
                'password' => post_value('signature_tsa_password'),
            ),
            $test_reason);

        if ($test_stamp === false) {
            $liveform->add_error(lang(array(
                'string' => 'The time stamp authority could not be reached: {var:1}',
                'vars' => $test_reason,
            )));

        } else {
            $liveform->add_notice(lang(array(
                'string' => 'Time stamp received. The authority reports {var:1}.',
                'vars' => ($test_stamp['time'] ? (gmdate('Y-m-d H:i:s', $test_stamp['time']) . ' UTC') : lang('no time')),
            )));
        }
    }
}
