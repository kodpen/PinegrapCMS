<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The site's Xero organisation (includes/fn/xero.php): the chart of
 * accounts read on Xero's page of Connections, and the site's sales,
 * customers and payments sent to the books.
 *
 * Xero's granular scopes, which applications made since 2026-03-02 use:
 * accounting.settings (accounts, tax rates, items), accounting.contacts,
 * accounting.invoices (invoices and credit notes) and accounting.payments.
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
    'label'       => 'Xero',
    'description' => lang('Sends the site\'s sales, customers and payments to its Xero organisation.'),
    'owner'       => 'site',
    'scopes'      => array(
        'xero' => array('accounting.settings', 'accounting.contacts', 'accounting.invoices', 'accounting.payments'),
    ),
);
