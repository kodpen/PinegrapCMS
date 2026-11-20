<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// The Privacy centre over the API, read only: the personal data inventory
// with its transfers (includes/fn/privacy_inventory.php) and the request box
// (includes/fn/privacy_requests.php). Every write stays on the panel, where a
// person confirms it.

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL')) {

	exit;

}
