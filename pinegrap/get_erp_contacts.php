<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - contacts for the account form's search box, as JSON.
 *
 * ?q= is what was typed; name, company and e-mail address are searched. Each
 * hit says which account it is already linked to, so the form can grey out a
 * contact that belongs to another card.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
$user = validate_user();
if (!validate_erp_access($user)) {
    exit();
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/bootstrap.php');

header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex');
header('Cache-Control: private, no-store');

$results = erp_contact_search((string) ($_GET['q'] ?? ''), 15);

echo json_encode(array('results' => $results), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
