<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the messages the visual designer screen hands to its own toasts
 * (pg_designer_screen_messages() in includes/fn/designer.php) instead of
 * printing the panel's alert boxes above the full-height editor.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_designer_screen_messages_order_and_types()
{
	$out = pg_designer_screen_messages(array('Bad name'), array('Old markup'), array('Opened'));
	pg_assert_same(array(
		array('type' => 'error', 'message' => 'Bad name'),
		array('type' => 'warning', 'message' => 'Old markup'),
		array('type' => 'success', 'message' => 'Opened'),
	), $out);
}

function test_designer_screen_messages_skips_empty()
{
	pg_assert_same(array(), pg_designer_screen_messages(array(), array(), array()));
	pg_assert_same(array(), pg_designer_screen_messages(array('', '  '), null, array(null)));
	$out = pg_designer_screen_messages(array(), array('One', 'Two'), array());
	pg_assert_same(2, count($out));
	pg_assert_same('Two', $out[1]['message']);
}
