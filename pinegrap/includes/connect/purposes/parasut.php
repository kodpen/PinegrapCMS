<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The site's Paraşüt company (includes/fn/parasut.php): e-invoices, e-archive
 * invoices and e-dispatch notes of the ERP, and the order screen's Create
 * Invoice and E-Shipment.
 *
 * Paraşüt has no scope of its own for the API: openid and offline_access,
 * the provider's identity scopes, give the token every company of the
 * person who signs in may use.
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
    'label'       => 'Paraşüt',
    'description' => lang('Sends the site\'s invoices and dispatch notes to its Paraşüt company.'),
    'owner'       => 'site',
    'scopes'      => array(
        'parasut' => array(),
    ),
);
