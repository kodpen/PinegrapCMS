<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - the module's entry point.
 *
 * Every erp_*.php screen includes this once, after init.php and after the user
 * has been validated. It defines the gate the module's files check for and
 * loads them in dependency order.
 *
 * The module lives here and not in functions.php: that file was split into
 * includes/fn/ in 2026-09 to get it back under control, and an accounting
 * module is exactly the kind of thing that would undo the split.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The gate every file under includes/erp/ checks, so none of them can be
// requested directly over the web.
if (!defined('PG_ERP_ENTRY')) {
    define('PG_ERP_ENTRY', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/money.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/fx.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/ledger.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/cash.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/accounts.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/numbering.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/order_bridge.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/invoice_manual.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/settlement.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/returns.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/document.php');
