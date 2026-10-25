<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the watcher part of a form list's viewer filter: which page's
 * watchers a viewer filtered list counts (pg_sw_list_watcher_page_decision()),
 * the form a record view's comments and watchers belong to
 * (pg_sw_record_view_form_page_id()), and that the list renderer and the
 * comment context both read them.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/helpers/source_reader.php');

// A detail page row as pg_sw_list_watcher_page_id() reads it.
function pg_test_list_watcher_page($page_type, $comments = '1', $watcher_email_page_id = '30')
{
	return array(
		'page_id' => '7',
		'page_type' => $page_type,
		'comments' => $comments,
		'comments_watcher_email_page_id' => $watcher_email_page_id,
	);
}

// A classic form item view page counts its watchers as before: whatever form
// a record view widget on it would show, it is not asked.
function test_form_list_watcher_classic_page()
{
	$page = pg_test_list_watcher_page('form item view');

	pg_assert_same(7, pg_sw_list_watcher_page_decision($page, 12, 0), 'no widget');
	pg_assert_same(7, pg_sw_list_watcher_page_decision($page, 12, 12), 'a widget of the same form');
	pg_assert_same(7, pg_sw_list_watcher_page_decision($page, 12, 99), 'a widget of another form is not asked');
}

// The symptom: a designed record page (page type 'standard') never matched,
// so "Topics you follow" showed nothing. Its record view shows the list's
// form, so its watchers count.
function test_form_list_watcher_designed_page()
{
	foreach (array('standard', 'catalog detail', 'calendar event view') as $type) {
		pg_assert_same(7, pg_sw_list_watcher_page_decision(pg_test_list_watcher_page($type), 12, 12), $type . ' with a record view of the list\'s form');
	}
}

// No watcher of another form or page: a designed page without a record
// view, or whose record view shows another form, gives no page.
function test_form_list_watcher_designed_page_other_form()
{
	$page = pg_test_list_watcher_page('standard');

	pg_assert_same(0, pg_sw_list_watcher_page_decision($page, 12, 0), 'no record view widget');
	pg_assert_same(0, pg_sw_list_watcher_page_decision($page, 12, 99), 'a record view of another form');
	pg_assert_same(0, pg_sw_list_watcher_page_decision($page, 0, 0), 'no list form, no widget');
}

// Comments off or watching off: no watcher counts, classic or designed, as
// add_or_remove_watcher.php refuses both on any page.
function test_form_list_watcher_comments_and_watching_off()
{
	foreach (array('form item view', 'standard') as $type) {
		pg_assert_same(0, pg_sw_list_watcher_page_decision(pg_test_list_watcher_page($type, '0'), 12, 12), $type . ': comments off');
		pg_assert_same(0, pg_sw_list_watcher_page_decision(pg_test_list_watcher_page($type, '1', '0'), 12, 12), $type . ': watching off');
		pg_assert_same(0, pg_sw_list_watcher_page_decision(pg_test_list_watcher_page($type, '1', ''), 12, 12), $type . ': watching unset');
	}
}

// No page row (missing page, failed query) or a malformed one.
function test_form_list_watcher_no_page()
{
	pg_assert_same(0, pg_sw_list_watcher_page_decision(null, 12, 12), 'null');
	pg_assert_same(0, pg_sw_list_watcher_page_decision(false, 12, 12), 'false');
	pg_assert_same(0, pg_sw_list_watcher_page_decision(array(), 12, 12), 'empty row');

	$page = pg_test_list_watcher_page('form item view');
	$page['page_id'] = '0';

	pg_assert_same(0, pg_sw_list_watcher_page_decision($page, 12, 12), 'page id 0');

	$page = pg_test_list_watcher_page(array('form item view'));

	pg_assert_same(0, pg_sw_list_watcher_page_decision($page, 12, 0), 'a page type that is not text');
}

// Without a detail page or a form there is nothing to read, and no query
// is run.
function test_form_list_watcher_page_id_without_ids()
{
	pg_assert_same(0, pg_sw_list_watcher_page_id(0, 12), 'no detail page');
	pg_assert_same(0, pg_sw_list_watcher_page_id(-3, 12), 'a negative detail page');
	pg_assert_same(0, pg_sw_list_watcher_page_id(7, 0), 'no list form');
}

// The form a record view's comments and watchers belong to: the one
// pg_sw_record_comment_context() reads, custom_form_page_id before the older
// form_page_id. The original source_page name is not looked up there.
function test_form_list_watcher_record_view_form()
{
	pg_assert_same(12, pg_sw_record_view_form_page_id(array('custom_form_page_id' => '12')));
	pg_assert_same(12, pg_sw_record_view_form_page_id(array('custom_form_page_id' => 12, 'form_page_id' => 99)), 'the current key wins');
	pg_assert_same(99, pg_sw_record_view_form_page_id(array('custom_form_page_id' => '', 'form_page_id' => '99')), 'the older key');
	pg_assert_same(99, pg_sw_record_view_form_page_id(array('custom_form_page_id' => 0, 'form_page_id' => 99)), 'the older key past a zero');
	pg_assert_same(0, pg_sw_record_view_form_page_id(array('source_page' => 'topics')), 'a page name');
	pg_assert_same(0, pg_sw_record_view_form_page_id(array()), 'nothing');
	pg_assert_same(0, pg_sw_record_view_form_page_id(null), 'not a config');
}

// The list renderer reads the watcher page from the helper, no longer by
// page type alone; submitter and editor clauses keep their columns.
function test_form_list_watcher_renderer_wiring()
{
	$list = pg_test_function_source('_render_system_widget_form_list');

	pg_assert_contains("pg_sw_list_watcher_page_id(isset(\$cfg['detail_page_id']) ? \$cfg['detail_page_id'] : 0, \$custom_form_page_id)", $list, 'the list asks the helper');
	pg_assert_false(strpos($list, "page_type = 'form item view'") !== false, 'no page type test of its own');
	pg_assert_contains("watchers.page_id = '\" . (int)\$watcher_page_id . \"'", $list, 'watchers of that page only');
	pg_assert_contains("watchers.item_type = 'submitted_form'", $list, 'of a record');
	pg_assert_contains("(forms.user_id = '\" . e(\$uid) . \"')", $list, 'submitter clause');
	pg_assert_contains("(forms.form_editor_user_id = '\" . e(\$uid) . \"')", $list, 'editor clause');

	$helper = pg_test_function_source('pg_sw_list_watcher_page_id');

	pg_assert_contains("pg_sw_page_widget(\$detail_page_id, 'form_item_view')", $helper, 'the page\'s first record view, as the comment context reads it');
	pg_assert_contains('pg_sw_record_view_form_page_id(', $helper, 'its form by the same rule');

	$context = pg_test_function_source('pg_sw_record_comment_context');

	pg_assert_contains('pg_sw_record_view_form_page_id($cfg)', $context, 'the comment context reads the form by the same rule');
}
