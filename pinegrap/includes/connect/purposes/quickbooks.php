<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The site's QuickBooks Online company (includes/fn/quickbooks.php): the
 * accounts and tax codes read on QuickBooks' page of Connections, and the
 * site's sales, customers and payments sent to the books.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

return array(
    'label'       => 'QuickBooks',
    'description' => lang('Sends the site\'s sales, customers and payments to its QuickBooks company.'),
    'owner'       => 'site',
    'scopes'      => array(
        'quickbooks' => array('com.intuit.quickbooks.accounting'),
    ),
);
