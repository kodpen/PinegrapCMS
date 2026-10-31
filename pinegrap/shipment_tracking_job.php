<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Scheduled job: follows the parcels a carrier label service opened. The
 * service of each open parcel is asked where it stands, the answer is
 * written back onto the parcel through the shipment write path, and an
 * arrival is recorded once, with order.delivered, the order_delivered e-mail
 * and the text message (pg_shipment_tracking_run(),
 * includes/fn/shipment_tracking.php).
 *
 * Runs from the job dispatcher, from a crontab line, or by hand from the
 * panel by somebody who manages the orders.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

require('init.php');

// A background run (crontab, or the general job's dispatcher) has no user.
// Every other request is a web request and must come from a signed-in user
// who may manage the orders, the gate of the order screens.
if (!pg_cron_is_background_run()) {
    $user = validate_user();
    validate_ecommerce_access($user);
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/shipment_tracking.php');

// The run starts no question after its budget; this leaves room for the
// last answer and the writes after it.
if (function_exists('set_time_limit')) {
    @set_time_limit(PG_SHIPMENT_TRACKING_SECONDS + 120);
}

$result = array('asked' => 0, 'changed' => 0, 'delivered' => 0, 'failed' => 0);

// E-commerce switched off is not a failure: the run is recorded so the
// scheduled-task health indicator stays green, and nothing else happens.
if (defined('ECOMMERCE') && (ECOMMERCE === true)) {
    $result = pg_shipment_tracking_run();
}

if (function_exists('pg_cron_ran')) {
    pg_cron_ran('shipment_tracking_job');
}

// The summary is for a person running the script by hand; under the general
// job's dispatcher printed text goes to the site log (pg_cron_dispatch_output()).
if ((php_sapi_name() === 'cli') && !defined('PG_CRON_DISPATCH')) {
    print 'shipment tracking: ' . (int) $result['asked'] . ' asked, ' . (int) $result['changed'] . ' changed, '
        . (int) $result['delivered'] . ' delivered, ' . (int) $result['failed'] . ' failed' . "\n";
} elseif (!pg_cron_is_background_run()) {
    // Started by hand from the panel: back to the orders.
    go(PATH . SOFTWARE_DIRECTORY . '/view_orders.php');
}
