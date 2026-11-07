<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the visual designer's Publish sheet (assets/js/
 * pg_designer_publish.js): which pages come checked when it opens, and what
 * "Publish selected" does with the checked ones — the drafts go on the
 * site, the unchecked changed pages on the site are held out of the save.
 * The script runs under Node; on a machine without Node the runs are
 * skipped.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The decoded JSON `$expr` prints after the sheet script is loaded; null
// without Node.
function designer_publish_sheet_test_eval($expr)
{
	if (!function_exists('exec')) {
		return null;
	}

	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgsdpub');

	if ($file === false) {
		return null;
	}

	$rows = 'var rows = ['
		. '{ key: "a", name: "Home", state: "live", changed: false, home: true },'
		. '{ key: "b", name: "About", state: "live", changed: true },'
		. '{ key: "c", name: "New", state: "draft", changed: true },'
		. '{ key: "d", name: "Spring", state: "scheduled", changed: false },'
		. '{ key: "e", name: "Variant", state: "held", changed: true },'
		. '{ key: "f", name: "Team", state: "live", changed: true, locked: true },'
		. '{ key: "g", name: "Old draft", state: "draft", changed: false }'
		. '];';

	file_put_contents($file, file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_designer_publish.js')
		. "\n" . $rows . "\nconsole.log(JSON.stringify(" . $expr . '));');
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	pg_assert_same(0, $code, implode("\n", $out));

	return json_decode(implode("\n", $out), true);
}

// Drafts and changed pages on the site come checked; a page waiting for its
// publish time, an experiment's variant and a locked page do not.
function test_designer_publish_sheet_defaults()
{
	$got = designer_publish_sheet_test_eval('PgDesignerPublish.defaults(rows)');

	if ($got === null) {
		return;
	}

	pg_assert_same(array('b', 'c', 'g'), $got, 'the changed live page and the two drafts');
}

// The defaults publish both drafts and hold nothing back.
function test_designer_publish_sheet_plan_defaults()
{
	$got = designer_publish_sheet_test_eval('PgDesignerPublish.plan(rows, PgDesignerPublish.defaults(rows))');

	if ($got === null) {
		return;
	}

	pg_assert_same(array('publish' => array('c', 'g'), 'hold' => array(), 'save' => true), $got, 'the default selection');
}

// An unchecked changed page on the site is held out of the save; a checked
// scheduled page is published now; the variant and the locked page are
// never touched, checked or not.
function test_designer_publish_sheet_plan_choices()
{
	$got = designer_publish_sheet_test_eval('['
		. 'PgDesignerPublish.plan(rows, ["d", "e", "f"]),'
		. 'PgDesignerPublish.plan(rows, []),'
		. 'PgDesignerPublish.plan(rows, ["a"])'
		. ']');

	if ($got === null) {
		return;
	}

	pg_assert_same(array('publish' => array('d'), 'hold' => array('b'), 'save' => true), $got[0], 'a scheduled page checked, the changed page unchecked');
	pg_assert_same(array('publish' => array(), 'hold' => array('b'), 'save' => false), $got[1], 'nothing checked: nothing to do');
	pg_assert_same(array('publish' => array(), 'hold' => array('b'), 'save' => true), $got[2], 'an unchanged live page checked saves the design');
}
