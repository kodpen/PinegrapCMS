<?php
/**
 * PineGrap - Enterprise Website Platform
 *
 * Originally developed as LiveSite by Camelback Web Architects.
 * Since 2017, maintained and evolved by Erdal Güral (Kodpen) under the name PineGrap.
 * The final LiveSite update (2019) has been integrated into PineGrap.
 * LiveSite remains available as a separate downloadable legacy version.
 *
 * @author      Camelback Web Architects
 *              Erdal Güral (Kodpen)
 * @link        https://livesite.com
 *              https://kodpen.com
 * @copyright   2001–2019 Camelback Consulting, Inc.
 *              2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// this script is used by the catalog menu to send a visitor to a specific product group that was selected in the catalog menu
include('init.php');

// The page, the product group and the return address are single values; one
// sent as an array (?product_group_id[]=x) counts as not given: it ended in an
// error page.
pg_request_single_values(array('current_page_id', 'product_group_id', 'previous_url_id'));

// The catalog menu form always sends the page, the product group and the
// return address. Opened without the page, or for one that no longer exists
// (stale link, hand-typed URL), there is nowhere to send the visitor: a "not
// found" screen instead of PHP notices and a redirect to a broken address. A
// missing product group or return address leaves its part of the address
// empty, as before, without the notices.
$page_name = get_page_name($_GET['current_page_id'] ?? '');

if ($page_name == '') {
    output_error(lang('Sorry, the page could not be found.'), 404);
}

$product_group_address_name = isset($_GET['product_group_id']) ? get_catalog_item_address_name_from_id($_GET['product_group_id'], 'product group') : '';

header('Location: ' . URL_SCHEME . HOSTNAME . PATH . encode_url_path($page_name) . '/' . encode_url_path($product_group_address_name) . '?previous_url_id=' . ($_GET['previous_url_id'] ?? ''));
exit();
?>