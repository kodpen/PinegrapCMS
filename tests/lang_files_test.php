<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the language file helpers (includes/fn/lang_files.php) and
 * pg_lang_read_folder() (includes/fn/core.php): the shard a key belongs to,
 * the exact text of a language file, splitting, adding keys, the three-way
 * merge tools/tr_resolve.php uses, the checks tools/check_lang.php runs on a
 * shard, and reading a language kept as shards.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Letters of either case go to their own shard, digits to 0, the rest to _.
function test_lang_files_shard_of()
{
	$cases = array(
		'Save' => 's',
		'save changes' => 's',
		'Zip code' => 'z',
		'404 page' => '0',
		'10001' => '0',
		'{var:1} items' => '_',
		'"Quoted"' => '_',
		'Ürün' => '_',
		' leading space' => '_',
		'' => '_',
	);

	foreach ($cases as $key => $shard) {
		pg_assert_same($shard, pg_lang_shard_of($key), 'shard of "' . $key . '"');
	}
}

// Sorted in strcmp order (capitals before small letters), four-space indent,
// raw Unicode and slashes, an object even for a numeric key, a final newline.
function test_lang_files_encode_sorted()
{
	$text = pg_lang_file_encode(array('save' => 'kaydet', 'Save' => 'Kaydet', 'Path a/b' => 'Yol ı/ğ', 'Bold' => 'Kalın'));

	pg_assert_same("{\n    \"Bold\": \"Kalın\",\n    \"Path a/b\": \"Yol ı/ğ\",\n    \"Save\": \"Kaydet\",\n    \"save\": \"kaydet\"\n}\n", $text);

	pg_assert_same("{\n    \"10001\": \"10001\"\n}\n", pg_lang_file_encode(array(10001 => '10001')), 'a numeric key stays an object');
	pg_assert_same("{}\n", pg_lang_file_encode(array()), 'an empty shard');
	pg_assert_same("{\n    \"Say \\\"hi\\\"\": \"Selam \\\\ de\"\n}\n", pg_lang_file_encode(array('Say "hi"' => 'Selam \\ de')), 'quotes and backslashes escaped');
}

// Not sorted: the map's own order, which keeps the single file's lines.
function test_lang_files_encode_keeps_order()
{
	pg_assert_same("{\n    \"Save\": \"Kaydet\",\n    \"Bold\": \"Kalın\"\n}\n", pg_lang_file_encode(array('Save' => 'Kaydet', 'Bold' => 'Kalın'), false));
}

// The real tr.json is in the exact form pg_lang_file_encode() writes, so
// tools/tr_add.php can append to it without moving any other line.
function test_lang_files_single_file_round_trip()
{
	$text = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/local/tr.json');
	$map = json_decode($text, true);

	pg_assert_true(is_array($map), 'tr.json decodes');
	pg_assert_true($text === pg_lang_file_encode($map, false), 'tr.json is in its exact form');
}

function test_lang_files_split()
{
	$shards = pg_lang_split(array('save' => 'b', 'Apple' => 'a', '{x}' => 'c', 'Save' => 'B', '7 days' => 'd'));

	pg_assert_same(array('0', '_', 'a', 's'), array_map('strval', array_keys($shards)), 'shards in name order (PHP keeps the name 0 as an integer key)');
	pg_assert_same(array('Save' => 'B', 'save' => 'b'), $shards['s'], 'keys sorted inside a shard');
	pg_assert_same(array('7 days' => 'd'), $shards['0']);
}

function test_lang_files_add()
{
	$result = pg_lang_add(array('Save' => 'Kaydet', 'Bold' => 'Kalın'), array('Cancel' => 'İptal', 'Save' => 'Kaydet', 'Bold' => 'Koyu'));

	pg_assert_same(array('Save' => 'Kaydet', 'Bold' => 'Kalın', 'Cancel' => 'İptal'), $result['map'], 'a new key at the end; a different value refused');
	pg_assert_same(array('Cancel'), $result['added']);
	pg_assert_same(array('Save'), $result['skipped']);
	pg_assert_same(array('Bold'), $result['refused']);
	pg_assert_same(array(), $result['replaced']);

	$replaced = pg_lang_add(array('Bold' => 'Kalın'), array('Bold' => 'Koyu'), true);

	pg_assert_same(array('Bold' => 'Koyu'), $replaced['map'], '--replace changes the value');
	pg_assert_same(array('Bold'), $replaced['replaced']);
}

// Development's file plus the branch's own edits.
function test_lang_files_merge3()
{
	$base = array('A' => 'a', 'B' => 'b', 'C' => 'c', 'D' => 'd');
	$dev = array('A' => 'a', 'B' => 'b2', 'C' => 'c', 'D' => 'd', 'E' => 'e');
	$branch = array('A' => 'a1', 'B' => 'b3', 'D' => 'd', 'F' => 'f');

	$merged = pg_lang_merge3($base, $branch, $dev);

	pg_assert_same(array('A' => 'a1', 'B' => 'b3', 'D' => 'd', 'E' => 'e', 'F' => 'f'), $merged);
	pg_assert_same(array('A', 'B', 'D', 'E', 'F'), array_keys($merged), "development's order, the branch's new keys at the end");

	pg_assert_same(array('C' => 'c2'), pg_lang_merge3(array('C' => 'c'), array(), array('C' => 'c2')), 'a key the branch removed but development changed stays');
	pg_assert_same(array('X' => 'x'), pg_lang_merge3(array(), array('X' => 'x'), array('X' => 'x')), 'both added the same key once');
	pg_assert_same(array('X' => 'branch'), pg_lang_merge3(array(), array('X' => 'branch'), array('X' => 'dev')), 'both added one key: the branch value wins');
	pg_assert_same(array('Y' => 'y'), pg_lang_merge3(array(), array(), array('Y' => 'y')), 'development alone added a key');
}

function test_lang_files_shard_problems()
{
	$good = pg_lang_file_encode(array('Save' => 'Kaydet', 'save' => 'kaydet'));

	pg_assert_same(array(), pg_lang_shard_problems('s', $good), 'a shard in its exact form');

	$wrong = pg_lang_shard_problems('a', $good);
	pg_assert_true(count($wrong) === 2, 'both keys belong in s.json');
	pg_assert_true(strpos($wrong[0], 'key belongs in s.json: Save') === 0);

	$unsorted = "{\n    \"save\": \"kaydet\",\n    \"Save\": \"Kaydet\"\n}\n";
	pg_assert_same(1, count(pg_lang_shard_problems('s', $unsorted)), 'out of order');

	$twice = "{\n    \"Save\": \"Kaydet\",\n    \"Save\": \"Kaydet\"\n}\n";
	pg_assert_same(1, count(pg_lang_shard_problems('s', $twice)), 'a key written twice');

	pg_assert_same(1, count(pg_lang_shard_problems('s', rtrim($good))), 'no final newline');
	pg_assert_same(1, count(pg_lang_shard_problems('s', '{"Save": ')), 'not valid JSON');
	pg_assert_same(1, count(pg_lang_shard_problems('s', "[]\n")), 'a list, not an object');

	$number = pg_lang_shard_problems('s', "{\n    \"Save\": 5\n}\n");
	pg_assert_true(in_array('value is not a string: Save', $number, true), 'a value that is not a string');
}

function test_lang_files_read_folder()
{
	$dir = sys_get_temp_dir() . '/pg_lang_folder_' . getmypid();
	@mkdir($dir);

	pg_assert_same(null, pg_lang_read_folder($dir), 'a folder with no shard');
	pg_assert_same(null, pg_lang_read_folder($dir . '/missing'), 'no folder');

	file_put_contents($dir . '/s.json', pg_lang_file_encode(array('Save' => 'Kaydet')));
	file_put_contents($dir . '/a.json', pg_lang_file_encode(array('Apple' => 'Elma')));
	file_put_contents($dir . '/b.json', '{broken');
	file_put_contents($dir . '/notes.txt', 'not a shard');

	pg_assert_same(array('Apple' => 'Elma', 'Save' => 'Kaydet'), pg_lang_read_folder($dir), 'shards in name order; a broken shard skipped');
	pg_assert_same(array('Apple' => 'Elma', 'Save' => 'Kaydet'), pg_lang_read_folder($dir . '/'), 'a trailing slash');

	foreach (array('s.json', 'a.json', 'b.json', 'notes.txt') as $name) {
		@unlink($dir . '/' . $name);
	}
	@rmdir($dir);
}
