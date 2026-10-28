<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the name a signed-in member's comment is stored and shown under:
 * pg_contact_display_name() (includes/fn/contacts.php), and
 * pg_comment_member_name() and pg_comment_name_choices()
 * (includes/fn/comments.php). On a page of a visual design it is the
 * contact's display name, else "Member"; elsewhere the login username. A
 * name_type the form does not offer the member counts as no choice.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A contact row as pg_comment_member_contact() returns it.
function comment_member_name_test_contact($first_name = '', $last_name = '', $nickname = '')
{
	return array('first_name' => $first_name, 'last_name' => $last_name, 'nickname' => $nickname);
}

// First and last name, else the nickname, else ''. The halves are trimmed, so
// a name with one part has no stray space; NULL halves (a LEFT JOIN without a
// contact) read as ''.
function test_comment_member_name_contact_display_name()
{
	pg_assert_same('Jane Doe', pg_contact_display_name('Jane', 'Doe', ''), 'first and last name');
	pg_assert_same('Jane Doe', pg_contact_display_name(' Jane ', ' Doe ', ''), 'trimmed halves');
	pg_assert_same('Jane', pg_contact_display_name('Jane', '', ''), 'first name only');
	pg_assert_same('Doe', pg_contact_display_name('', 'Doe', ''), 'last name only');
	pg_assert_same('jd42', pg_contact_display_name('', '', 'jd42'), 'the nickname without a name');
	pg_assert_same('jd42', pg_contact_display_name('  ', '  ', ' jd42 '), 'blank names give the trimmed nickname');
	pg_assert_same('', pg_contact_display_name(null, null, null), 'no contact');
	pg_assert_same('Şule Öztürk', pg_contact_display_name('Şule', 'Öztürk', 'x'), 'a name beats the nickname');
	pg_assert_same('Jane', pg_contact_display_name('Jane', null), 'the nickname is optional');
}

// Select off on a designed page: the display name, else Member; never the
// login username.
function test_comment_member_name_designed_page()
{
	pg_assert_same('Jane Doe', pg_comment_member_name('jane', comment_member_name_test_contact('Jane', 'Doe', 'jd'), true), 'the display name');
	pg_assert_same('jd42', pg_comment_member_name('jane', comment_member_name_test_contact('', '', 'jd42'), true), 'the nickname');
	pg_assert_same(lang('Member'), pg_comment_member_name('jane', comment_member_name_test_contact(), true), 'no name gives Member');
	pg_assert_false(pg_comment_member_name('jane', array(), true) === 'jane', 'never the username');

	$long = pg_comment_member_name('jane', comment_member_name_test_contact(str_repeat('ş', 60), str_repeat('ö', 60)), true);
	pg_assert_same(100, mb_strlen($long, 'UTF-8'), 'cut to the size of comments.name');
	pg_assert_same(str_repeat('ş', 60) . ' ' . str_repeat('ö', 39), $long, 'cut by characters, not bytes');

	foreach (array(null, false, '', 'Jane') as $contact) {
		pg_assert_same(lang('Member'), pg_comment_member_name('jane', $contact, true), 'a contact that is not an array: ' . var_export($contact, true));
	}
}

// Select off on a legacy page: the username, whatever the contact holds.
function test_comment_member_name_legacy_page()
{
	pg_assert_same('jane', pg_comment_member_name('jane', comment_member_name_test_contact('Jane', 'Doe', 'jd'), false), 'with a name');
	pg_assert_same('jane', pg_comment_member_name('jane', comment_member_name_test_contact(), false), 'without a name');
	pg_assert_same('jane', pg_comment_member_name('jane', null, false), 'without a contact');
}

// The select's options, in the form's order, under the form's offer
// conditions, with the legacy formulas.
function test_comment_member_name_choices()
{
	$choices = pg_comment_name_choices('jane', comment_member_name_test_contact('Jane', 'Doe', 'jd'), false);

	pg_assert_same(array('username', 'first_name', 'first_name_last_initial', 'first_initial_last_name', 'first_initial_last_initial', 'full_name', 'anonymous'), array_keys($choices), 'both names: every option, in order');
	pg_assert_same(array('jane', 'Jane', 'Jane D.', 'J. Doe', 'J. D.', 'Jane Doe', 'Anonymous'), array_values($choices), 'both names: the stored names');

	$choices = pg_comment_name_choices('sule', comment_member_name_test_contact('Şule', 'Öztürk'), false);
	pg_assert_same('Ş. Ö.', $choices['first_initial_last_initial'], 'initials by characters');
	pg_assert_same('Şule Ö.', $choices['first_name_last_initial'], 'last initial by characters');

	$choices = pg_comment_name_choices('jane', comment_member_name_test_contact('Jane'), false);
	pg_assert_same(array('username', 'first_name', 'anonymous'), array_keys($choices), 'first name only');

	$choices = pg_comment_name_choices('jane', comment_member_name_test_contact('', 'Doe'), false);
	pg_assert_same(array('username', 'anonymous'), array_keys($choices), 'last name only');

	$choices = pg_comment_name_choices('jane', comment_member_name_test_contact(), false);
	pg_assert_same(array('username', 'anonymous'), array_keys($choices), 'no names');

	// The username option is the select-off name of the page kind.
	pg_assert_same('jane', pg_comment_name_choices('jane', comment_member_name_test_contact('Jane', 'Doe'), false)['username'], 'legacy: the username');
	pg_assert_same('Jane Doe', pg_comment_name_choices('jane', comment_member_name_test_contact('Jane', 'Doe'), true)['username'], 'designed: the display name');
	pg_assert_same('jd42', pg_comment_name_choices('jane', comment_member_name_test_contact('', '', 'jd42'), true)['username'], 'designed: the nickname');
	pg_assert_same(lang('Member'), pg_comment_name_choices('jane', comment_member_name_test_contact(), true)['username'], 'designed: Member');
	pg_assert_same(array('username', 'anonymous'), array_keys(pg_comment_name_choices('jane', null, true)), 'a contact that is not an array');

	foreach (pg_comment_name_choices('jane', comment_member_name_test_contact(str_repeat('a', 50), str_repeat('b', 50)), true) as $value => $name) {
		pg_assert_true(mb_strlen($name, 'UTF-8') <= 100, 'cut to 100: ' . $value);
	}
}

// Select on: an offered value gives that choice on both page kinds.
function test_comment_member_name_select_offered()
{
	$contact = comment_member_name_test_contact('Jane', 'Doe', 'jd');

	foreach (array(false, true) as $designed) {
		foreach (pg_comment_name_choices('jane', $contact, $designed) as $value => $name) {
			pg_assert_same($name, pg_comment_member_name('jane', $contact, $designed, true, $value), ($designed ? 'designed: ' : 'legacy: ') . $value);
		}
	}

	pg_assert_same('Anonymous', pg_comment_member_name('jane', $contact, true, true, 'anonymous'), 'anonymous is the stored literal');
	pg_assert_same('J. D.', pg_comment_member_name('jane', $contact, true, true, 'first_initial_last_initial'), 'initials');
}

// Select on: a value the form does not offer this member gives the
// select-off name, never a half-built name and never a warning.
function test_comment_member_name_select_forged()
{
	$first_only = comment_member_name_test_contact('Jane');

	foreach (array('', 'bogus', array('x'), null, 'first_name_last_initial', 'full_name', 'first_initial_last_initial') as $forged) {
		$label = var_export($forged, true);

		pg_assert_same('Jane', pg_comment_member_name('jane', $first_only, true, true, $forged), 'designed: ' . $label);
		pg_assert_same('jane', pg_comment_member_name('jane', $first_only, false, true, $forged), 'legacy: ' . $label);
	}

	pg_assert_same(lang('Member'), pg_comment_member_name('jane', comment_member_name_test_contact(), true, true, 'first_name'), 'designed, no name: first_name is not offered');
	pg_assert_same('jane', pg_comment_member_name('jane', comment_member_name_test_contact(), false, true, 'full_name'), 'legacy, no name: full_name is not offered');
}

// add_comment.php and the comment form in get_page_content.php ask the same
// functions about the comment's page, and neither keeps the old username
// rule or the old option formulas of its own.
function test_comment_member_name_callers()
{
	$add = (string) file_get_contents(PG_FUNCTIONS_DIR . '/add_comment.php');

	pg_assert_contains('pg_comment_member_name(', $add, 'add_comment.php asks pg_comment_member_name()');
	pg_assert_contains('pg_sw_page_is_visual($page_id)', $add, 'about the posted page');
	pg_assert_false(strpos($add, "\$name = \$_SESSION['sessionusername'];") !== false, 'add_comment.php no longer stores the username itself');
	pg_assert_false(strpos($add, "case 'first_initial_last_name':") !== false, 'add_comment.php no longer builds the options itself');

	$page = (string) file_get_contents(PG_FUNCTIONS_DIR . '/get_page_content.php');

	pg_assert_contains('pg_comment_name_choices(', $page, 'the form builds the options from pg_comment_name_choices()');
	pg_assert_contains('pg_comment_member_name(', $page, 'the form shows pg_comment_member_name()');
	pg_assert_contains("pg_sw_page_is_visual(\$system_region_properties['page_id'])", $page, 'about the comment\'s page');
	pg_assert_false(strpos($page, "h(\$user['username'])") !== false, 'the form no longer prints the username itself');

	$seo = new ReflectionFunction('pg_submitted_form_author_name');
	$lines = array_slice(file($seo->getFileName()), $seo->getStartLine() - 1, $seo->getEndLine() - $seo->getStartLine() + 1);
	pg_assert_contains('pg_contact_display_name(', implode('', $lines), 'the BlogPosting author follows the same rule');
}

// The stored 'Anonymous' and an empty name read in the panel language.
function test_comment_display_name()
{
	pg_assert_same(lang('Anonymous'), pg_comment_display_name('Anonymous'));
	pg_assert_same(lang('Anonymous'), pg_comment_display_name(''));
	pg_assert_same(lang('Anonymous'), pg_comment_display_name(null));
	pg_assert_same('Ayşe K.', pg_comment_display_name('Ayşe K.'));
}
