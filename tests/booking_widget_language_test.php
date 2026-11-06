<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the booking widget's endpoint on a page in another site
 * language (pg_booking_widget_endpoint(), includes/fn/widgets_booking.php):
 * the widget's script posts with fetch(), which the jQuery prefilter of
 * frontend.src.js never sees, so the address itself carries pg_lang.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_booking_widget_endpoint_source_language()
{
	$url = pg_booking_widget_endpoint('');

	pg_assert_same(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/booking_slots.php', $url, 'the source language adds nothing');
}

function test_booking_widget_endpoint_other_language()
{
	pg_assert_same(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/booking_slots.php?pg_lang=de', pg_booking_widget_endpoint('de'));
	pg_assert_same(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/booking_slots.php?pg_lang=pt-BR', pg_booking_widget_endpoint('pt-BR'), 'a region code as the language row writes it');
}
