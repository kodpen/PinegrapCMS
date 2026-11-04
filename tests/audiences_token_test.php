<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the audience group token over groups filled by rules
 * (includes/fn/page_audience.php): the editor's group list carries each
 * group's kind, and the token reads a membership whatever wrote it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_audiences_token_group_options_carry_kind()
{
	pg_assert_same(
		array(
			array('id' => 4, 'name' => 'Buyers', 'kind' => 'dynamic'),
			array('id' => 2, 'name' => 'Newsletter', 'kind' => 'static'),
		),
		pg_audience_group_options(array(
			array('id' => '4', 'name' => 'Buyers', 'kind' => 'dynamic'),
			array('id' => '2', 'name' => 'Newsletter', 'kind' => 'static'),
		))
	);
}

function test_audiences_token_group_options_before_the_upgrade_are_static()
{
	pg_assert_same(
		array(array('id' => 3, 'name' => 'VIP', 'kind' => 'static')),
		pg_audience_group_options(array(array('id' => 3, 'name' => 'VIP')))
	);
}

function test_audiences_token_group_options_drop_bad_rows()
{
	pg_assert_same(
		array(array('id' => 5, 'name' => 'A', 'kind' => 'static')),
		pg_audience_group_options(array(
			array('id' => 0, 'name' => 'Zero'),
			'not a row',
			array('name' => 'No id'),
			array('id' => 5, 'name' => 'A', 'kind' => 'other'),
		))
	);
}

// A group filled by rules has `rule` rows in the xref: the token must read
// every row of the contact, never only the hand-made ones.
function test_audiences_token_group_reads_every_source()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/page_audience.php');
	pg_assert_true(preg_match('/case \'group\':(.*?)break;/s', $source, $m) === 1, 'the group branch is there');
	pg_assert_true(strpos($m[1], 'contacts_contact_groups_xref') !== false, 'it reads the membership table');
	pg_assert_true(strpos($m[1], 'source') === false, 'no source filter');
	pg_assert_true(strpos($m[1], 'kind') === false, 'no kind filter');
}

// The editor's list is built by the helper, so the kind reaches the designer.
function test_audiences_token_editor_list_uses_helper()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/designer_screen.php');
	pg_assert_true(strpos($source, "'contactGroups' => pg_audience_group_options(") !== false, 'formMeta.contactGroups comes from pg_audience_group_options()');
}
