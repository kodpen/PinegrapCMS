<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for who may load an earlier revision of a page in the visual
 * designer's History panel: loading puts the revision's whole tree on the
 * canvas, and at the content level (manager, user) the save keeps only its
 * texts and image addresses, so a content operator compares and does not
 * load. The designer's _sdRevisionLoadRefusal() is cut out of
 * assets/js/style_designer.src.js and run under Node; on a machine without Node
 * that half is skipped.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function designer_revision_load_test_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
}

// The refusal: another person editing, then the content level; none for a designer.
function test_designer_revision_load_refusal()
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return;
	}

	$source = designer_revision_load_test_source();
	$from = strpos($source, 'function _sdRevisionLoadRefusal(');
	$to = strpos($source, 'function _sdRevisionRestore(', $from);
	pg_assert_true(($from !== false) && ($to !== false), 'the designer has the refusal');

	$script = 'var _collab = { mayEdit: true }; var level = "full";'
		. 'function _sdIsContentLevel() { return level === "content"; }'
		. 'function _sdT(s) { return s; }'
		. substr($source, $from, $to - $from)
		. 'var out = {}; out.full = _sdRevisionLoadRefusal();'
		. 'level = "content"; out.content = _sdRevisionLoadRefusal();'
		. '_collab.mayEdit = false; out.busy = _sdRevisionLoadRefusal();'
		. 'console.log(JSON.stringify(out));';

	$file = tempnam(sys_get_temp_dir(), 'pgrevload');
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);

	pg_assert_same(0, $code, implode("\n", $out));
	$answer = json_decode(implode("\n", $out), true);

	pg_assert_same('', $answer['full'] ?? null, 'a designer loads');
	pg_assert_same('Only a designer can load an earlier revision.', $answer['content'] ?? null, 'a content operator does not');
	pg_assert_same('Another person is editing this page.', $answer['busy'] ?? null, 'nobody while another person edits');
}

// Every way to load asks it: the History row's Load, the comparison's Load
// this revision, and the restore itself.
function test_designer_revision_load_every_way_asks()
{
	$source = designer_revision_load_test_source();

	pg_assert_same(2, substr_count($source, 'var loadRefusal = _sdRevisionLoadRefusal();'), 'the History list and the comparison');
	pg_assert_contains("(loadRefusal ? ' disabled title=\"' + esc(loadRefusal) + '\"' : '') + '>' + esc(_sdT('Load')) + '</button>'", $source, 'the row\'s Load');
	pg_assert_contains("(loadRefusal ? ' disabled title=\"' + esc(loadRefusal) + '\"' : '') + '>' + esc(_sdT('Load this revision'))", $source, 'Load this revision');

	$from = strpos($source, 'function _sdRevisionRestore(');
	pg_assert_contains('var refusal = _sdRevisionLoadRefusal();', substr($source, $from, 200), 'the restore refuses too');
}
