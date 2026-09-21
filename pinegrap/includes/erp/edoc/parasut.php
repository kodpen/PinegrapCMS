<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP e-document driver: Paraşüt.
 *
 * Paraşüt is reached through the client the site has carried since the
 * Paraşüt repair (includes/fn/parasut.php): OAuth2 password grant, one token
 * cached on disk, credentials AES-encrypted in the config row and entered on
 * the commerce settings card. This driver does not copy any of that; it
 * declares no fields of its own and points the settings screen at that card.
 *
 * What is here: who the provider is, whether it answers, and the taxpayer
 * lookup the account card uses. Sending the invoice itself is Faz 3 of the
 * ERP plan and lands here as erp_edoc_parasut_send_invoice() and friends;
 * until then erp_edoc_supports('send_invoice') says no, and the module keeps
 * printing PDFs.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_ERP_ENTRY')) {
    exit;
}

function erp_edoc_parasut_info()
{
    return array(
        'label' => 'Paraşüt',
        'description' => lang('Cloud pre-accounting by Mikrogrup. e-Invoice, e-Archive and e-Delivery note through the Paraşüt API; the store needs a Paraşüt subscription with API access.'),
        'docs_url' => 'https://apidocs.parasut.com/',
        'capabilities' => array('einvoice', 'earchive', 'ewaybill', 'inbox'),
        'settings_note' => lang('The Paraşüt client id, secret, user name, password and company id are the Paraşüt boxes further down on this card.'),
    );
}

/**
 * No fields: the credentials live in the config row (Faz -1), read by
 * parasut_get_token().
 */
function erp_edoc_parasut_fields()
{
    return array();
}

/**
 * Can we get a token, and is a company named.
 *
 * @param array $credentials  Unused: Paraşüt reads its own
 * @return array ['success' => bool, 'message' => string, 'details' => array]
 */
function erp_edoc_parasut_ping($credentials)
{
    if (!function_exists('parasut_get_token')) {
        return array('success' => false, 'message' => lang('The Paraşüt client is not loaded on this installation.'), 'details' => array());
    }

    $company = defined('PARASUT_COMPANY_ID') ? trim((string) PARASUT_COMPANY_ID) : '';

    if ($company === '') {
        return array('success' => false, 'message' => lang('The Paraşüt company id is empty. Enter it on the Paraşüt card of the commerce settings.'), 'details' => array());
    }

    $token = parasut_get_token();

    if (empty($token['success'])) {
        return array('success' => false, 'message' => (string) $token['error'], 'details' => array());
    }

    return array(
        'success' => true,
        'message' => lang(array('string' => 'Paraşüt answered; company {var:1}.', 'vars' => $company)),
        'details' => array('company_id' => $company),
    );
}

/**
 * Whether a tax number belongs to a registered e-invoice user, and its
 * aliases (the mailbox addresses) when it does.
 *
 * @param string $vkn
 * @return array ['success' => bool, 'is_einvoice_user' => bool, 'aliases' => array, 'error' => string]
 */
function erp_edoc_parasut_check_taxpayer($vkn)
{
    if (!function_exists('parasut_check_einvoice_address')) {
        return array('success' => false, 'is_einvoice_user' => false, 'aliases' => array(), 'error' => lang('The Paraşüt client is not loaded on this installation.'));
    }

    $result = parasut_check_einvoice_address((string) $vkn);

    if (empty($result['success'])) {
        return array('success' => false, 'is_einvoice_user' => false, 'aliases' => array(), 'error' => (string) $result['error']);
    }

    $aliases = array();

    foreach ((array) $result['data'] as $item) {
        $alias = (string) ($item['attributes']['e_invoice_address'] ?? '');

        if ($alias !== '') {
            $aliases[] = $alias;
        }
    }

    return array('success' => true, 'is_einvoice_user' => !empty($aliases), 'aliases' => $aliases, 'error' => '');
}
