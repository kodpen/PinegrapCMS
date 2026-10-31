<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Edit Event Location keeps the name the user typed after a refused rename
 * (blank or already in use): the GET branch loads the stored name only when
 * the liveform holds no typed value.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_calendar_event_location_refused_name_is_kept()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_calendar_event_location.php');

	$guard = strpos($source, "if (!\$liveform->field_in_session('name')) {");
	$assign = strpos($source, "\$liveform->assign_field_value('name', \$row['name']);");
	$select = strpos($source, "FROM calendar_event_locations\n            WHERE id = ?");

	pg_assert_true($guard !== false, 'the GET branch checks the session for a typed name');
	pg_assert_true($assign !== false && $assign > $guard, 'the stored name is assigned inside the session check');
	pg_assert_true($select !== false && $select > $guard, 'the stored name is read only when no typed name waits');
	pg_assert_contains('$liveform->remove_form();', $source, 'the typed values are cleared after they are shown');
}
