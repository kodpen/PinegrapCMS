<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the parts of tools/tr_resolve.php that need no git: reading the
 * unmerged entries git lists, telling a shard from a single language file,
 * what to do with each conflicted file (merge it, move a single file's edits
 * onto the shards, or leave it to be settled by hand) and putting a single
 * file's edits onto the shards.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The tool returns before its command line part when PG_TEST_RUNNER is set.
require_once(dirname(__DIR__) . '/tools/tr_resolve.php');

// `git ls-files -u -z`: one record per stage, NUL-terminated, the path
// unquoted (spaces and all); a missing stage is a side that deleted the file.
function test_tr_resolve_unmerged()
{
	$base = str_repeat('a', 40);
	$ours = str_repeat('b', 40);
	$theirs = str_repeat('c', 40);

	$output = "100644 $base 1\tpinegrap/includes/local/tr/s.json\0"
		. "100644 $ours 2\tpinegrap/includes/local/tr/s.json\0"
		. "100644 $theirs 3\tpinegrap/includes/local/tr/s.json\0"
		. "100644 $base 1\tpinegrap/includes/local/tr.json\0"
		. "100644 $theirs 3\tpinegrap/includes/local/tr.json\0"
		. "100644 $ours 2\tdocs/a file.md\0";

	pg_assert_same(array(
		'pinegrap/includes/local/tr/s.json' => array(1 => $base, 2 => $ours, 3 => $theirs),
		'pinegrap/includes/local/tr.json' => array(1 => $base, 3 => $theirs),
		'docs/a file.md' => array(2 => $ours),
	), tr_resolve_unmerged($output));

	pg_assert_same(array(), tr_resolve_unmerged(''), 'no conflict');
	pg_assert_same(array(), tr_resolve_unmerged("fatal: not a git repository\n"), 'an error text is no entry');
}

function test_tr_resolve_language_path()
{
	pg_assert_same(array('code' => 'tr', 'shard' => 's'), tr_resolve_language_path('pinegrap/includes/local/tr/s.json'));
	pg_assert_same(array('code' => 'tr', 'shard' => '_'), tr_resolve_language_path('pinegrap/includes/local/tr/_.json'));
	pg_assert_same(array('code' => 'pt-BR', 'shard' => '0'), tr_resolve_language_path('pinegrap/includes/local/pt-BR/0.json'));
	pg_assert_same(array('code' => 'tr', 'shard' => null), tr_resolve_language_path('pinegrap/includes/local/tr.json'), 'the single file');
	pg_assert_same(array('code' => 'en', 'shard' => null), tr_resolve_language_path('pinegrap/includes/local/en.json'));

	pg_assert_same(null, tr_resolve_language_path('pinegrap/includes/local/tr/sub/s.json'), 'no deeper folder');
	pg_assert_same(null, tr_resolve_language_path('pinegrap/includes/local/notes.json'), 'not a language code');
	pg_assert_same(null, tr_resolve_language_path('pinegrap/assets/google_taxonomy/google_taxonomy_tr.json'));
	pg_assert_same(null, tr_resolve_language_path('docs/memory/core/lang-files.md'));
}

// A shard or a single file without shards is merged when both sides have it;
// the single file beside the shards has its edits moved onto them.
function test_tr_resolve_plan()
{
	$all = array(1 => 'x', 2 => 'y', 3 => 'z');

	pg_assert_same(array('action' => 'merge'), tr_resolve_plan($all, true, true), 'a shard both sides edited');
	pg_assert_same(array('action' => 'merge'), tr_resolve_plan(array(2 => 'y', 3 => 'z'), true, true), 'a shard both sides added');
	pg_assert_same('skip', tr_resolve_plan(array(1 => 'x', 2 => 'y'), true, true)['action'], 'a shard theirs deleted');
	pg_assert_same('skip', tr_resolve_plan(array(1 => 'x', 3 => 'z'), true, true)['action'], 'a shard ours deleted');

	pg_assert_same(array('action' => 'merge'), tr_resolve_plan($all, false, false), 'a language with no shards');
	pg_assert_same('skip', tr_resolve_plan(array(1 => 'x', 3 => 'z'), false, false)['action'], 'a single file deleted with no shards to take its edits');

	pg_assert_same(array('action' => 'move', 'side' => 3), tr_resolve_plan(array(1 => 'x', 3 => 'z'), false, true), 'theirs still edits the single file');
	pg_assert_same(array('action' => 'move', 'side' => 2), tr_resolve_plan(array(1 => 'x', 2 => 'y'), false, true), 'ours still edits the single file');
	pg_assert_same('skip', tr_resolve_plan($all, false, true)['action'], 'both sides still have the single file');
	pg_assert_same('skip', tr_resolve_plan(array(3 => 'z'), false, true)['action'], 'no merge base version');
}

function test_tr_resolve_map()
{
	pg_assert_same(array('Save' => 'Kaydet'), tr_resolve_map("{\n    \"Save\": \"Kaydet\"\n}\n"));
	pg_assert_same(array(), tr_resolve_map("{}\n"));
	pg_assert_same(null, tr_resolve_map("<<<<<<< HEAD\n{}\n"), 'conflict markers');
	pg_assert_same(null, tr_resolve_map("[\"Save\"]\n"), 'a list');
	pg_assert_same(null, tr_resolve_map(''), 'empty text');
}

// The single file's own edits since the merge base go onto the shards; the
// shards' edits stay; only the shards that change are written.
function test_tr_resolve_onto_shards()
{
	$base = array('Apple' => 'Elma', 'Save' => 'Kaydet', 'Zebra' => 'Zebra', 'Bold' => 'Kalın');
	$side = array('Apple' => 'Elma', 'Save' => 'Kaydet!', 'Bold' => 'Kalın', 'Cancel' => 'İptal', '7 days' => '7 gün');

	$texts = array(
		'a' => pg_lang_file_encode(array('Apple' => 'Elma')),
		'b' => pg_lang_file_encode(array('Bold' => 'Koyu')),
		's' => pg_lang_file_encode(array('Save' => 'Kaydet', 'Search' => 'Ara')),
		'z' => pg_lang_file_encode(array('Zebra' => 'Zebra')),
	);

	$result = tr_resolve_onto_shards($base, $side, $texts);

	pg_assert_same(array('0', 'c', 's'), array_map('strval', array_keys($result['write'])), 'new shards 0 and c, a changed s; a and b as they were');
	pg_assert_same(pg_lang_file_encode(array('Save' => 'Kaydet!', 'Search' => 'Ara')), $result['write']['s'], "the side's value; the shards' own key stays");
	pg_assert_same(pg_lang_file_encode(array('Cancel' => 'İptal')), $result['write']['c']);
	pg_assert_same(pg_lang_file_encode(array('7 days' => '7 gün')), $result['write']['0']);
	pg_assert_same(array('z'), $result['remove'], 'the key the side removed was the only one in z.json');
	pg_assert_same(array(2, 1, 1), array($result['added'], $result['changed'], $result['removed']), 'Cancel and 7 days added, Save changed, Zebra removed');
	pg_assert_false(isset($result['write']['b']), "the shards' own change of Bold stays");
}

// Never writes over a shard it cannot read, and never leaves no key.
function test_tr_resolve_onto_shards_refuses()
{
	$conflicted = array('s' => "{\n<<<<<<< HEAD\n    \"Save\": \"Kaydet\"\n=======\n    \"Save\": \"Kaydet!\"\n>>>>>>> branch\n}\n");
	$result = tr_resolve_onto_shards(array(), array('Save' => 'Kaydet'), $conflicted);

	pg_assert_true(isset($result['error']), 'a shard still in conflict');
	pg_assert_contains('s.json', $result['error']);

	$emptied = tr_resolve_onto_shards(array('Save' => 'Kaydet'), array(), array('s' => pg_lang_file_encode(array('Save' => 'Kaydet'))));
	pg_assert_same(array('error' => 'no key would be left'), $emptied);
}
