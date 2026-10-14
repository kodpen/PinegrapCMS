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

header('Location: ' . URL_SCHEME . HOSTNAME . PATH . encode_url_path(get_page_name($_GET['current_page_id'])) . '/' . encode_url_path(get_catalog_item_address_name_from_id($_GET['product_group_id'], 'product group')) . '?previous_url_id=' . $_GET['previous_url_id']);
exit();
?>