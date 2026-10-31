<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for where View Order sends the operator: Save and Delete fall back
 * to All Orders (never the site's front page), the back arrow follows
 * send_to like Save, and the Paraşüt and carrier label reloads keep
 * send_to. All Orders for Contact goes back to Edit Contact.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The source of a panel screen.
function order_screen_destinations_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

function test_view_order_save_and_delete_fall_back_to_all_orders()
{
	$source = order_screen_destinations_source('view_order.php');

	pg_assert_same(2, substr_count($source, "\$target = pg_safe_redirect_path(\$liveform->get_field_value('send_to'), PATH . SOFTWARE_DIRECTORY . '/view_orders.php');"), 'Save and Delete resolve the target with the All Orders fallback');
	pg_assert_same(2, substr_count($source, "header('Location: ' . URL_SCHEME . HOSTNAME . \$target);"), 'Save and Delete redirect to the resolved target');
	pg_assert_false(strpos($source, "pg_safe_redirect_path(\$liveform->get_field_value('send_to')))") !== false, 'no redirect falls back to the site front page');
	pg_assert_same(2, substr_count($source, "basename((string) parse_url(\$target, PHP_URL_PATH)) === 'view_orders.php'"), 'the notice is prepared for the resolved list');
}

function test_view_order_back_arrow_follows_send_to()
{
	$source = order_screen_destinations_source('view_order.php');

	pg_assert_contains("'cancel'=>array('enable'=>'true','url'=>pg_send_to_url('view_orders.php'))", $source, 'the arrow goes where Save goes');
	pg_assert_contains("array('label' => lang('All Orders'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_orders.php')", $source, 'the breadcrumb stays on All Orders');
}

function test_view_order_reloads_keep_send_to()
{
	$source = order_screen_destinations_source('view_order.php');

	pg_assert_same(3, substr_count($source, '<input type="hidden" name="send_to" value="\' . h(pg_send_to_url(\'\')) . \'" />'), 'both Paraşüt mini forms and the label form carry send_to');
	pg_assert_contains("\$redirect = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_order.php?id=' . \$order_id . '&send_to=' . urlencode(pg_send_to_url(''));", $source, 'a Paraşüt refusal reloads with send_to');
	pg_assert_contains("go(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_order.php?id=' . \$order_id . '&send_to=' . urlencode(pg_send_to_url('')));", $source, 'a Paraşüt result reloads with send_to');

	$label = order_screen_destinations_source('shipment_label.php');

	pg_assert_contains("'/view_order.php?id=' . \$order_id . '&send_to=' . urlencode(pg_send_to_url('')) : '/view_orders.php'", $label, 'a pickup booked from the order reloads with send_to');
	pg_assert_contains("go(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_order.php?id=' . \$order_id . '&send_to=' . urlencode(pg_send_to_url('')));", $label, 'a label made from the order reloads with send_to');
}

function test_orders_for_contact_returns_to_edit_contact()
{
	$source = order_screen_destinations_source('view_orders_for_contact.php');

	pg_assert_contains("'cancel'=>array('enable'=>'true','url'=>'edit_contact.php?id=' . (int) \$id)", $source, 'the arrow goes to the opener, Edit Contact');
	pg_assert_contains("\$output_link_url = 'view_order.php?id=' . \$row['id'] . '&send_to=' . urlencode(REQUEST_URL);", $source, 'the row link brings the order back here');
	pg_assert_contains("h(escape_javascript(\$output_link_url))", $source, 'the onclick url is escaped');
}
