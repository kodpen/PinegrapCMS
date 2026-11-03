<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the contact groups list (view_contact_groups.php): the type and
 * the description sort on the value the list shows, and the form's fixed
 * required message stays hidden beside a server message.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/contact_list.php');

function test_contact_group_list_sorts_on_shown_values()
{
	$map = pg_contact_group_list_order_map();

	pg_assert_same(array('name', 'subscription', 'type', 'description', 'created', 'last_modified'), array_keys($map), 'one entry per sort code');
	pg_assert_same("IF(contact_groups.email_subscription = 1, contact_groups.email_subscription_type, '')", $map['type'], 'the type of a group without a subscription sorts as blank');
	pg_assert_same("IF(contact_groups.email_subscription = 1, contact_groups.description, '')", $map['description'], 'the description of a group without a subscription sorts as blank');
	pg_assert_same('contact_groups.name', $map['name'], 'the name sorts as stored');

	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_contact_groups.php');
	pg_assert_contains('pg_list_order_by($sort_levels, pg_contact_group_list_order_map())', $screen, 'the screen sorts by the map');
}

function test_contact_group_form_required_message_hidden_beside_server_message()
{
	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/backend.src.css');

	pg_assert_contains(".pg-inline-feedback ~ .invalid-feedback:not(.pg-inline-feedback) {\n  display: none;\n}", $css, 'a fixed message after a liveform message is hidden');
}
