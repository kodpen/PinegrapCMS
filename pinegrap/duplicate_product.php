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
 *              2016–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
$user = validate_user();
validate_ecommerce_access($user);

validate_token_field();

$new_product_id = duplicate_product($_GET['id']);

$old_product_name = db_value("SELECT name FROM products WHERE id = '" . escape($_GET['id']) . "'");

log_activity(lang(array('string'=>'{var:1} ({var:2}) was duplicated','vars'=>array(lang('product'), $old_product_name) )), $_SESSION['sessionusername']);

include_once('liveform.class.php');
$liveform_edit_product = new liveform('edit_product');
$liveform_edit_product->add_notice(lang(array('string'=>'The {var:1} has been duplicated, and you are now editing the duplicate.','vars'=>lang('product') )) );

go(PATH . SOFTWARE_DIRECTORY . '/edit_product.php?id=' . $new_product_id);
?>