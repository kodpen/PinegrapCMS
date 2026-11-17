<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Workspace screen "My meeting link"
 * (includes/fn/booking_link_screen.php, includes/workspace/nav.php): the
 * services the screen lists and why one is not offered, the site path a
 * short address points at, and the screen's place behind More.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

function _booking_link_screen_test_member($overrides = array())
{
	return $overrides + array(
		'id'          => 7,
		'user_id'     => 42,
		'enabled'     => 1,
		'user_role'   => 2,
		'service_ids' => '[1,2]',
	);
}

function _booking_link_screen_test_services()
{
	return array(
		array('id' => 1, 'name' => 'Consultation', 'length_minutes' => 30, 'price' => 0, 'deposit' => 0, 'deposit_product_id' => 0),
		array('id' => 2, 'name' => 'Session', 'length_minutes' => 60, 'price' => 10000, 'deposit' => 2000, 'deposit_product_id' => 9),
		array('id' => 3, 'name' => 'Not mine', 'length_minutes' => 45, 'price' => 0, 'deposit' => 0, 'deposit_product_id' => 0),
	);
}

function test_booking_link_screen_services_lists_the_members_own_with_the_deposit_marked()
{
	$rows = pg_booking_link_screen_services(_booking_link_screen_test_services(), _booking_link_screen_test_member());

	pg_assert_same(array(1, 2), array_map(function ($row) {
		return (int) $row['id'];
	}, $rows), 'only the services the member takes, in the list order');
	pg_assert_same(true, $rows[0]['offered'], 'a service without a deposit is on the link');
	pg_assert_same('', $rows[0]['reason']);
	pg_assert_same(false, $rows[1]['offered'], 'a service with a deposit is not');
	pg_assert_same('deposit', $rows[1]['reason']);
}

function test_booking_link_screen_services_of_a_member_with_none()
{
	pg_assert_same(array(), pg_booking_link_screen_services(_booking_link_screen_test_services(), _booking_link_screen_test_member(array('service_ids' => '[]'))));
	pg_assert_same(array(), pg_booking_link_screen_services(array(), _booking_link_screen_test_member()));
}

function test_booking_link_screen_short_target_is_a_path_on_the_site()
{
	$key = str_repeat('k', 32);
	$member = _booking_link_screen_test_member();

	pg_assert_same('/site/book_with.php?c=' . pg_booking_link_code($member, $key), pg_booking_link_short_target($member, $key, '/site/'), 'the link as a path, so a new host name keeps it');
	pg_assert_same('', pg_booking_link_short_target($member, '', '/'), 'no key, no target');
	pg_assert_same('', pg_booking_link_short_target(_booking_link_screen_test_member(array('user_id' => 0)), $key, '/'), 'no user, no target');
}

function test_booking_link_screen_is_behind_more_for_booking_staff_only()
{
	$flags = array(
		'base'          => '/pg/',
		'calendar_feed' => true,
		'mcp'           => true,
	);

	$keys = function ($groups) {
		return array_map(function ($item) {
			return $item['key'];
		}, $groups['more']);
	};

	$staff = ws_nav_groups(array('role' => 2, 'settings' => false), $flags + array('meeting_link' => true));

	pg_assert_same(array('mcp', 'calendar_feed', 'meeting_link', 'archived', 'audit'), $keys($staff), 'after the calendar subscription');
	pg_assert_same(array('mcp', 'calendar_feed', 'archived', 'audit'), $keys(ws_nav_groups(array('role' => 2, 'settings' => false), $flags + array('meeting_link' => false))), 'not for a user who is no booking staff');

	$entry = null;

	foreach ($staff['more'] as $item) {
		if ($item['key'] === 'meeting_link') {
			$entry = $item;
		}
	}

	pg_assert_same('My meeting link', $entry['label'] ?? '');
	pg_assert_same('/pg/workspace_meeting_link.php', $entry['url'] ?? '');
	pg_assert_same('meeting_link', ws_side_screen('/pg/workspace_meeting_link.php'), 'the list marks the screen');
}
