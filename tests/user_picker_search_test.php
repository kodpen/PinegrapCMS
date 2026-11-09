<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the panel's user pickers (includes/fn/user_picker.php): the text
 * a picker shows, who may search the users, the search condition the shared
 * endpoint user_search_action.php runs, and the screens drawn as searchable
 * comboboxes.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/user_picker.php');

function user_picker_search_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

// "First Last (username)", the username alone without a name.
function test_user_picker_text()
{
	pg_assert_same('Ayşe Yılmaz (ayse)', pg_user_picker_text('Ayşe', 'Yılmaz', 'ayse'), 'name and username');
	pg_assert_same('Ayşe (ayse)', pg_user_picker_text(' Ayşe ', '', 'ayse'), 'first name only, trimmed');
	pg_assert_same('ayse@example.com', pg_user_picker_text('', null, 'ayse@example.com'), 'no name: the username');
	pg_assert_same('Yılmaz', pg_user_picker_text(null, 'Yılmaz', ''), 'no username: the name');
}

// The right of the users list: manager (2) or above.
function test_user_picker_may_search()
{
	foreach (array(0, 1, 2, '2') as $role) {
		pg_assert_true(pg_user_picker_may_search($role), 'role ' . $role . ' may search');
	}

	foreach (array(3, '3', -1, null, '', 'manager', array(2)) as $role) {
		pg_assert_false(pg_user_picker_may_search($role), 'role ' . var_export($role, true) . ' may not search');
	}
}

// No condition for a viewer below manager or for a query without a word.
function test_user_picker_search_where_refuses()
{
	pg_assert_same(null, pg_user_picker_search_where('ali', 3, false, ''), 'a basic user lists nobody');
	pg_assert_same(null, pg_user_picker_search_where('ali', null, false, ''), 'no role lists nobody');
	pg_assert_same(null, pg_user_picker_search_where('   ', 0, false, ''), 'an empty query lists nobody');
	pg_assert_same(null, pg_user_picker_search_where(array('ali'), 0, false, ''), 'a list as the query lists nobody');
}

// Only the accounts the viewer sees on the users list: an administrator
// every one, a designer or manager the roles below their own; staff only
// when asked.
function test_user_picker_search_where_scope()
{
	$admin = pg_user_picker_search_where('ali', 0, false, '');
	$designer = pg_user_picker_search_where('ali', 1, false, '');
	$manager = pg_user_picker_search_where('ali', 2, false, '');
	$staff = pg_user_picker_search_where('ali', 0, true, '');

	pg_assert_false(strpos($admin['sql'], 'user.user_role') !== false, 'an administrator sees every role');
	pg_assert_contains('AND (user.user_role > 1)', $designer['sql'], 'a designer sees the roles below 1');
	pg_assert_contains('AND (user.user_role > 2)', $manager['sql'], 'a manager sees the roles below 2');
	pg_assert_contains('AND (user.user_role < 3)', $staff['sql'], 'staff only');
}

// Every word in one of the username, the e-mail address and the two names;
// % and _ typed stand for themselves; İ, I, ı and i are one letter.
function test_user_picker_search_where_words()
{
	$plain = pg_user_picker_search_where('ali %50', 0, false, '');

	pg_assert_same(1, substr_count($plain['sql'], ') AND ('), 'two words, both required');

	foreach (array('user.user_username LIKE ?', 'user.user_email LIKE ?', 'contacts.first_name LIKE ?', 'contacts.last_name LIKE ?') as $column) {
		pg_assert_contains($column, $plain['sql'], $column);
	}

	pg_assert_same(8, count($plain['params']), 'one value per column and word');
	pg_assert_contains('%\\%50%', implode(' ', $plain['params']), '% typed is plain');

	$turkish = pg_user_picker_search_where('yildiz', 0, false, 'utf8mb4');

	pg_assert_contains("REPLACE(REPLACE(CONVERT(contacts.last_name USING utf8mb4), 'İ', 'i'), 'ı', 'i') LIKE ?", $turkish['sql'], '"yildiz" also finds "Yıldız"');

	$capital = pg_user_picker_search_where('YILDIZ', 0, false, 'utf8mb4');

	pg_assert_contains('COLLATE utf8mb4_turkish_ci LIKE ?', $capital['sql'], '"YILDIZ" under Turkish case rules');
}

// The endpoint: a signed-in manager or above, refused in JSON, the request
// values read as one string each, the answer only ids and texts.
function test_user_picker_endpoint_gate()
{
	$source = user_picker_search_test_source('user_search_action.php');

	pg_assert_contains(' * Pinegrap - Enterprise Website Platform', $source, 'Pinegrap header');
	pg_assert_false(strpos($source, '= validate_user()') !== false, 'no validate_user(): it answers an HTML login page');
	pg_assert_contains("if (!defined('USER_LOGGED_IN') || (USER_LOGGED_IN !== true)) {", $source, 'a signed-in user');
	pg_assert_contains('if (!pg_user_picker_may_search(USER_ROLE)) {', $source, 'the users list right');
	pg_assert_same(2, substr_count($source, 'http_response_code(403);'), 'both refusals answer 403');
	pg_assert_true(strpos($source, 'pg_user_picker_may_search(USER_ROLE)') < strpos($source, 'pg_user_picker_search($q'), 'checked before the search');

	foreach (array('q', 'role', 'value') as $parameter) {
		pg_assert_contains("is_string(\$_GET['" . $parameter . "'] ?? null)", $source, $parameter . ' is one string');
	}

	$module = user_picker_search_test_source('includes/fn/user_picker.php');

	pg_assert_contains('"SELECT user.user_id, user.user_username, contacts.first_name, contacts.last_name', $module, 'the search reads no e-mail address or role back');
	pg_assert_contains("'id' => \$by_username ? (string) \$row['user_username'] : (int) \$row['user_id'],", $module, 'the answer: the stored value');
	pg_assert_contains("'text' => pg_user_picker_text(", $module, 'the answer: the picker text');
	pg_assert_contains("pg_user_enabled_sql('user')", $module, 'disabled accounts are not offered');
	pg_assert_contains("max(1, min(50, (int) (\$options['limit'] ?? 30)))", $module, 'at most 50, 30 by default');
}

// The submitted form's editor is searched on the server by a manager or
// above; the save still checks the username it receives.
function test_user_picker_form_editor_field()
{
	$source = user_picker_search_test_source('edit_submitted_form.php');

	pg_assert_contains("if (pg_user_picker_may_search(\$user['role'])) {", $source, 'only a viewer who may list users gets the search');
	pg_assert_contains('data-pg-combobox data-pg-source="user_search_action.php?value=username" data-pg-min-length="2" data-pg-free-text', $source, 'server search posting the username');
	pg_assert_contains("h(pg_user_picker_label(\$form_editor_value, 'username'))", $source, 'the stored editor is printed as the chosen option');
	pg_assert_contains('$query = "SELECT user_id FROM user WHERE user_username = ?";', $source, 'the save still looks the username up');
	pg_assert_contains("lang('Please enter a valid username for the form editor.')", $source, 'an unknown username is still refused');
}

// The short lists (staff, the team) are searched in the browser.
function test_user_picker_local_lists()
{
	$pins = array(
		'includes/settings/contact.php' => '<select name="chat_operator_user_id" id="chat_operator_user_id" class="form-control" data-pg-combobox>',
		'includes/settings/bookings.php' => '<select name="booking_staff_add" id="booking_staff_add" class="form-control" data-pg-combobox>',
		'edit_booking.php' => '<select class="form-control" id="choice_staff" name="staff_id" data-pg-combobox',
		'view_bookings.php' => '<select class="form-control form-control-sm" id="bookings_staff" name="staff" data-pg-combobox>',
		'workspace_wtables.php' => '<select class="form-control form-control-sm" name="target" id="ws_wt_share_target" required data-pg-combobox>',
	);

	foreach ($pins as $file => $pin) {
		pg_assert_contains($pin, user_picker_search_test_source($file), $file);
	}

	pg_assert_contains("((\$column['type'] === 'person') ? ' data-pg-combobox' : '')", user_picker_search_test_source('workspace_wtables.php'), 'a person column');

	// The settings card and the bookings list bring the field script.
	pg_assert_contains("\$output_chat_operator_options . '</select>' . pg_fields_script()", user_picker_search_test_source('includes/settings/contact.php'), 'live chat card');
	pg_assert_contains("\$bookings_options . '</select>' . pg_fields_script()", user_picker_search_test_source('view_bookings.php'), 'bookings list');

	// The search box has no name and sits in the form: typing in it must
	// not send a form that goes on every change.
	foreach (array('view_bookings.php', 'edit_booking.php') as $file) {
		pg_assert_contains('event.target.closest(".pg-combobox-menu")', user_picker_search_test_source($file), $file . ': the search box sends nothing');
	}
}

// The stored chat operator stays chosen when the account left the staff.
function test_user_picker_chat_operator_kept()
{
	$source = user_picker_search_test_source('includes/settings/prep.php');

	pg_assert_contains('if (($chat_operator_user_id > 0) && !$chat_operator_listed) {', $source, 'an operator outside the list');
	pg_assert_contains('h(pg_user_picker_label($chat_operator_user_id))', $source, 'is printed as the chosen option');
}
