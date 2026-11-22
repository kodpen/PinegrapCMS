<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the booking widget's service rows (pg_booking_widget_rows(),
 * includes/fn/widgets_booking.php): what the widget prints for each service.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_booking_widget_rows_fill_the_service_length()
{
	$rows = pg_booking_widget_rows(array(
		array('id' => 3, 'name' => 'Haircut', 'description' => '', 'length_minutes' => 45, 'price' => 0),
	));

	pg_assert_same('3', $rows[0]['__service_id']);
	pg_assert_same(false, strpos($rows[0]['__service_length'], '{var:1}'), 'the placeholder is filled, not printed');
	pg_assert_same(true, strpos($rows[0]['__service_length'], '45') !== false, 'the length is in the text');
}
