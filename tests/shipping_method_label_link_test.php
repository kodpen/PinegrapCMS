<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a shipping method linked to a carrier label service
 * (includes/fn/shipping_methods.php, includes/fn/shipment_labels.php): the
 * link a post saves, the carrier it sets, the services a driver lists, the
 * real-time rate rule, the picker, and the driver and service Create Label
 * takes from the link.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/shipping_method_form.php');

function test_shipping_method_label_link_decide()
{
	$drivers = array(
		'geliver' => array('name' => 'Geliver', 'services' => array('YURTICI_STANDART' => 'Yurtiçi Standart')),
		'sendcloud' => array('name' => 'Sendcloud', 'services' => array()),
	);
	$known = array('geliver', 'sendcloud', 'shippo');

	pg_assert_same(array('label_driver' => '', 'label_service' => ''), pg_shipping_method_label_link_decide('', '', $drivers, $known), 'none');
	pg_assert_same(array('label_driver' => '', 'label_service' => ''), pg_shipping_method_label_link_decide(array('geliver:'), '', $drivers, $known), 'an array is none');
	pg_assert_same(array('label_driver' => 'geliver', 'label_service' => ''), pg_shipping_method_label_link_decide('geliver:', 'X', $drivers, $known), 'the cheapest; a typed code is ignored for a driver with a list');
	pg_assert_same(array('label_driver' => 'geliver', 'label_service' => 'YURTICI_STANDART'), pg_shipping_method_label_link_decide('geliver:YURTICI_STANDART', '', $drivers, $known), 'a listed service');
	pg_assert_same(array('label_driver' => 'sendcloud', 'label_service' => 'postnl:standard'), pg_shipping_method_label_link_decide('sendcloud:', ' postnl:standard ', $drivers, $known), 'a typed code for a driver without a list');
	pg_assert_same(array('label_driver' => 'shippo', 'label_service' => 'usps_priority'), pg_shipping_method_label_link_decide('shippo:usps_priority', '', $drivers, $known), 'a stored link of a driver not set up now is kept');
	pg_assert_same(array('label_driver' => '', 'label_service' => ''), pg_shipping_method_label_link_decide('nosuch:x', '', $drivers, $known), 'an unknown driver is dropped');
	pg_assert_same(64, mb_strlen(pg_shipping_method_label_link_decide('sendcloud:', str_repeat('ş', 80), $drivers, $known)['label_service']), 'the service fits label_service');
}

function test_shipping_method_link_carrier()
{
	$carriers = array('yurtici', 'surat', 'aras', 'mng', 'ptt');

	pg_assert_same('yurtici', pg_shipping_method_link_carrier('geliver', 'YURTICI_STANDART', $carriers, '', ''), 'the carrier the code starts with');
	pg_assert_same('', pg_shipping_method_link_carrier('geliver', '', $carriers, 'aras', 'geliver'), 'the cheapest names no carrier');
	pg_assert_same('', pg_shipping_method_link_carrier('geliver', 'HEPSIJET_STANDART', $carriers, '', ''), 'a carrier the driver does not ship with');
	pg_assert_same('usps', pg_shipping_method_link_carrier('shippo', 'usps_priority', array('usps', 'ups'), '', ''), 'lower case and underscore');
	pg_assert_same('', pg_shipping_method_link_carrier('', '', $carriers, 'aras', 'geliver'), 'a link removed takes its carrier with it');
	pg_assert_same('aras', pg_shipping_method_link_carrier('', '', $carriers, 'aras', ''), 'a carrier set without a link stays');
}

function test_shipping_method_label_services_clean()
{
	pg_assert_same(array(), pg_shipping_method_label_services_clean(array('carriers' => array())), 'no list');
	pg_assert_same(array(), pg_shipping_method_label_services_clean(null), 'no info');
	pg_assert_same(
		array('A' => 'Service A', 'B' => 'B'),
		pg_shipping_method_label_services_clean(array('services' => array('A' => ' Service A ', 'B' => '', '' => 'Empty', str_repeat('x', 65) => 'Long'))),
		'codes kept with a name, the code for a missing one');
}

function test_shipping_method_has_realtime_rate()
{
	pg_assert_true(pg_shipping_method_has_realtime_rate('ups_ground', ''), 'UPS');
	pg_assert_false(pg_shipping_method_has_realtime_rate('fedex_ground', ''), 'FedEx gives none');
	pg_assert_false(pg_shipping_method_has_realtime_rate('', ''), 'nothing');
	pg_assert_true(pg_shipping_method_has_realtime_rate('', 'geliver'), 'a carrier service link');
}

function test_shipping_method_label_field()
{
	pg_assert_same('<input type="hidden" name="label_link" id="label_link" value="" />', pg_shipping_method_label_field(array(), '', ''), 'no service and no link: no empty picker');

	$drivers = array(
		'geliver' => array('name' => 'Geliver', 'services' => array('YURTICI_STANDART' => 'Yurtiçi Standart')),
		'sendcloud' => array('name' => 'Sendcloud', 'services' => array()),
	);

	$html = pg_shipping_method_label_field($drivers, 'geliver', 'YURTICI_STANDART');
	pg_assert_contains('<option value="geliver:YURTICI_STANDART" selected="selected">', $html, 'the stored service is selected');
	pg_assert_contains('<option value="geliver:">', $html, 'the cheapest offer');
	pg_assert_contains('<option value="sendcloud:" data-pg-service-code="1">', $html, 'a driver without a list takes a typed code');

	$html = pg_shipping_method_label_field($drivers, 'sendcloud', 'postnl:standard');
	pg_assert_contains('<option value="sendcloud:" data-pg-service-code="1" selected="selected">', $html, 'the typed service selects its driver');
	pg_assert_contains('value="postnl:standard"', $html, 'and fills the code box');

	$html = pg_shipping_method_label_field($drivers, 'geliver', 'OLD_CODE');
	pg_assert_contains('<option value="geliver:OLD_CODE" selected="selected">', $html, 'a stored service the list lost is offered back');

	$html = pg_shipping_method_label_field(array(), 'shippo', 'usps_priority');
	pg_assert_contains('<option value="shippo:usps_priority" selected="selected">', $html, 'a link of a driver not set up now is offered back');
}

function test_shipment_label_method_driver_and_service()
{
	$drivers = array('geliver' => array('yurtici', 'aras'), 'shippo' => array('usps'));

	pg_assert_same('shippo', pg_shipment_label_method_driver('shippo', 'yurtici', $drivers), 'the linked driver wins over the carrier');
	pg_assert_same('geliver', pg_shipment_label_method_driver('sendcloud', 'aras', $drivers), 'a linked driver not ready: today\'s pick');
	pg_assert_same('geliver', pg_shipment_label_method_driver('', '', $drivers), 'no link, no carrier: the first driver');
	pg_assert_same('', pg_shipment_label_method_driver('', 'dhl', $drivers), 'no link and a carrier no driver ships with');

	$ship_to = array('method_label_driver' => 'geliver', 'method_label_service' => ' YURTICI_STANDART ');
	pg_assert_same('YURTICI_STANDART', pg_shipment_label_method_service($ship_to, 'geliver'), 'the linked service for the linked driver');
	pg_assert_same('', pg_shipment_label_method_service($ship_to, 'shippo'), 'none for another driver');
	pg_assert_same('', pg_shipment_label_method_service(array('id' => 1), 'geliver'), 'none before the columns exist');
}
