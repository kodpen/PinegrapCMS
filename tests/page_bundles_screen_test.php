<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Bundles screen (page_bundles.php and
 * includes/fn/page_bundles_screen.php): the status labels and themes, the
 * choices left out when an open bundle has them, the publish time read from
 * the form, and the screen's gates read from its source. No database is
 * used.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _page_bundles_screen_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/page_bundles.php');
}

function test_page_bundle_status_label_and_theme()
{
	foreach (pg_page_bundle_statuses() as $status) {
		pg_assert_true(pg_page_bundle_status_label($status) !== '', $status . ' has a label');
	}

	pg_assert_same('', pg_page_bundle_status_theme('open'), 'an open bundle is the neutral badge');
	pg_assert_same('info', pg_page_bundle_status_theme('scheduled'), 'scheduled');
	pg_assert_same('success', pg_page_bundle_status_theme('published'), 'published');
	pg_assert_same('danger', pg_page_bundle_status_theme('partial'), 'partly published asks for a look');
	pg_assert_same('secondary', pg_page_bundle_status_theme('reverted'), 'taken back');
	pg_assert_same('', pg_page_bundle_status_theme('gone'), 'an unknown status');
}

function test_page_bundle_free_choices()
{
	$rows = array(
		array('page_id' => '3', 'page_name' => 'About'),
		array('page_id' => '5', 'page_name' => 'Home'),
		array('page_id' => '10', 'page_name' => 'Spring offer'),
	);
	$open = array(
		array('bundle_id' => 1, 'bundle_name' => 'Spring', 'draft_page_id' => 10, 'live_page_id' => 0),
		array('bundle_id' => 1, 'bundle_name' => 'Spring', 'draft_page_id' => 11, 'live_page_id' => 3),
	);

	pg_assert_same(array(
		array('page_id' => 5, 'page_name' => 'Home'),
		array('page_id' => 10, 'page_name' => 'Spring offer'),
	), pg_page_bundle_free_choices($rows, $open, 'live'), 'a live page with a replacement waiting is left out');

	pg_assert_same(array(
		array('page_id' => 3, 'page_name' => 'About'),
		array('page_id' => 5, 'page_name' => 'Home'),
	), pg_page_bundle_free_choices($rows, $open, 'draft'), 'a draft in an open bundle is left out');

	pg_assert_same(3, count(pg_page_bundle_free_choices($rows, array(), 'draft')), 'nothing open');
}

function test_page_bundle_time_from_form()
{
	pg_assert_same(0, pg_page_bundle_time_from_form('  '), 'empty is no time');
	pg_assert_same(-1, pg_page_bundle_time_from_form('soon'), 'not a time');
	pg_assert_same(strtotime('2026-12-01 09:30'), pg_page_bundle_time_from_form('2026-12-01 09:30'), 'ISO');
	pg_assert_same(strtotime('2026-12-01 09:30'), pg_page_bundle_time_from_form('2026-12-01T09:30'), 'as a datetime-local field posts it');
}

function test_page_bundles_screen_gates()
{
	$source = _page_bundles_screen_source();

	pg_assert_contains("validate_area_access(\$user, 'designer')", $source, 'designers and administrators');
	pg_assert_contains('validate_token_field()', $source, 'every post checks its token');
	pg_assert_contains('pg_page_bundles_ready()', $source, 'nothing before the upgrade');
	pg_assert_true(strpos($source, 'validate_token_field()') < strpos($source, "pg_page_bundle_create("), 'the token before the first write');
	pg_assert_true(!preg_match('/<tr[^>]*>\s*<td[^>]*>\s*<form/', $source), 'no form inside a table row');
	pg_assert_contains('form="page_bundle_remove"', $source, 'Remove reaches its form through the form attribute');
	pg_assert_contains("\$liveform->remove_form();\n", $source, 'the notices are shown once');
}

function test_page_bundles_screen_says_what_add_a_draft_cleared()
{
	$source = _page_bundles_screen_source();

	pg_assert_contains("\$done['time_cleared']", $source, 'the notice reads the cleared time');
	pg_assert_contains('Its own publish time was cleared', $source, 'and says so');
	pg_assert_contains('Changed since', $source, 'a replacement changed since its copy is marked');
}

function test_page_bundle_screen_actions_by_status()
{
	pg_assert_same(array('publish' => 'Publish', 'take_back' => false), pg_page_bundle_screen_actions('open'), 'an open bundle is published');
	pg_assert_same(array('publish' => 'Publish', 'take_back' => false), pg_page_bundle_screen_actions('scheduled'), 'a scheduled one too, before its time');
	pg_assert_same(array('publish' => 'Publish the rest', 'take_back' => true), pg_page_bundle_screen_actions('partial'), 'a partly published one publishes the rest or is taken back');
	pg_assert_same(array('publish' => '', 'take_back' => true), pg_page_bundle_screen_actions('published'), 'a published one is taken back');
	pg_assert_same(array('publish' => '', 'take_back' => false), pg_page_bundle_screen_actions('reverted'), 'a taken back one is closed');
}

function test_page_bundle_screen_posted_choices()
{
	pg_assert_same(array(4 => 'replace', 7 => 'leave'),
		pg_page_bundle_screen_choices(array('4' => 'replace', '7' => 'leave', '9' => 'drop', 'x' => 'leave', '0' => 'replace', '5' => array('leave'))),
		'only replace or leave for an item id');
	pg_assert_same(array(), pg_page_bundle_screen_choices('replace'), 'not a list');
	pg_assert_same(array(3, 8), pg_page_bundle_screen_kept(array('3', '8', 'x', '0', array(2), '3')), 'the kept item ids, once each');
	pg_assert_same(array(), pg_page_bundle_screen_kept(null), 'none posted');
}

function test_page_bundle_screen_outcome()
{
	pg_assert_same(array('ok' => true, 'text' => 'The bundle was published: its pages are on the site.'),
		pg_page_bundle_screen_outcome('publish', array('ok' => true, 'error' => '')), 'published');
	pg_assert_same(array('ok' => true, 'text' => 'The bundle was taken back.'),
		pg_page_bundle_screen_outcome('take_back', array('ok' => true, 'error' => '')), 'taken back');
	pg_assert_same(array('ok' => false, 'text' => 'Busy.'),
		pg_page_bundle_screen_outcome('publish', array('ok' => false, 'error' => 'Busy.', 'busy' => true)), 'the runner\'s own error');
	pg_assert_same(array('ok' => false, 'text' => 'Moved.'),
		pg_page_bundle_screen_outcome('publish', array('ok' => true, 'error' => 'Moved.')), 'written but the status could not move');
	pg_assert_same(array('ok' => false, 'text' => 'The bundle cannot make that change now.'),
		pg_page_bundle_screen_outcome('take_back', array('ok' => false, 'error' => '')), 'a failure without words');
}

function test_page_bundles_screen_publishes_and_takes_back()
{
	$source = _page_bundles_screen_source();

	pg_assert_contains("\$action === 'publish'", $source, 'the screen handles a publish');
	pg_assert_contains("\$action === 'take_back'", $source, 'and a take-back');
	pg_assert_contains('pg_page_bundle_publish($bundle, $user, ', $source, 'through the locked publish');
	pg_assert_contains('pg_page_bundle_take_back($bundle, $user, ', $source, 'and the locked take-back');
	pg_assert_true(strpos($source, 'validate_token_field()') < strpos($source, 'pg_page_bundle_publish($bundle'), 'the token before the publish');
	pg_assert_true(strpos($source, 'validate_token_field()') < strpos($source, 'pg_page_bundle_take_back($bundle'), 'and before the take-back');
	pg_assert_contains('pg_page_bundle_publish_dialog($bundle, $publish_check', $source, 'the publish dialog is printed');
	pg_assert_contains('pg_page_bundle_take_back_dialog($bundle, pg_page_bundle_take_back_check(', $source, 'the take-back dialog is printed');
	pg_assert_contains('pg_page_bundle_screen_actions($bundle[\'status\'])', $source, 'the buttons follow the status');
	pg_assert_contains('pgUI.dialog.show(', $source, 'the dialogs open through pgUI');
}
