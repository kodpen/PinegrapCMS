<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the start box "a customer has not ordered for N days". Its
 * anchor (flow_anchors.php) runs every hour; each run looks for customers
 * whose last order is N days old, and starts one run of the flow for each,
 * within the starts the flow has left in the hour. The run's start key names
 * the customer and their last order, so a customer starts the flow once per
 * last order: a new order makes a new key.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The days a new box waits for, and the most it may wait.
define('WS_FLOW_NO_ORDER_DAYS', 30);
define('WS_FLOW_NO_ORDER_DAYS_MAX', 3650);
