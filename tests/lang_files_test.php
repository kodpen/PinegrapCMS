<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the language file helpers (includes/fn/lang_files.php) and the
 * readers in includes/fn/core.php (pg_lang_read_folder(), pg_lang_source(),
 * pg_lang_map()): the shard a key belongs to, the exact text of a language
 * file, splitting, adding keys, the three-way merge tools/tr_resolve.php
 * uses, the checks tools/check_lang.php runs on a shard, the Turkish shards
 * as shipped, and reading a language from its folder or its single file.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/helpers/language_map.php');

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

// The Turkish wording ships as shards only: every shard in its exact form
// with each key in its own shard, and no single tr.json beside the folder.
function test_lang_files_turkish_shards()
{
	$folder = PG_FUNCTIONS_DIR . '/includes/local/tr';
	$shards = glob($folder . '/*.json');

	pg_assert_true(is_array($shards) && (count($shards) > 20), 'the Turkish shards are there');
	pg_assert_false(is_file(PG_FUNCTIONS_DIR . '/includes/local/tr.json'), 'no single tr.json beside the folder');

	foreach (is_array($shards) ? $shards : array() as $shard) {
		pg_assert_same(array(), pg_lang_shard_problems(basename($shard, '.json'), (string) file_get_contents($shard)), basename($shard));
	}

	pg_assert_same($folder, pg_lang_source('tr'), 'lang() reads the folder');
	pg_assert_same('Kaydet', pg_test_language_map('tr')['Save'] ?? null, 'a key read through the map');
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

// A language's folder of shards wins over its single file, also when an
// update left the old file beside it; an empty folder does not count.
function test_lang_files_source_and_map()
{
	$local = sys_get_temp_dir() . '/pg_lang_local_' . getmypid();
	@mkdir($local);
	@mkdir($local . '/xx');

	pg_assert_same(null, pg_lang_source('xx', $local), 'neither a shard nor a file');
	pg_assert_same(array(), pg_lang_map('xx', $local), 'no language: an empty map');

	file_put_contents($local . '/xx.json', '{"Save": "Old"}');

	pg_assert_same($local . '/xx.json', pg_lang_source('xx', $local), 'an empty folder: the single file');
	pg_assert_same(array('Save' => 'Old'), pg_lang_map('xx', $local));

	file_put_contents($local . '/xx/s.json', pg_lang_file_encode(array('Save' => 'Kaydet')));

	pg_assert_same($local . '/xx', pg_lang_source('xx', $local), 'the folder wins');
	pg_assert_same(array('Save' => 'Kaydet'), pg_lang_map('xx', $local), 'the stale single file is not read');

	pg_assert_same(null, pg_lang_source('../xx', $local), 'no path from the code');
	pg_assert_same(null, pg_lang_source('', $local), 'no empty code');

	file_put_contents($local . '/yy.json', '{broken');
	pg_assert_same(array(), pg_lang_map('yy', $local), 'a single file that does not decode');

	@unlink($local . '/xx/s.json');
	@rmdir($local . '/xx');
	@unlink($local . '/xx.json');
	@unlink($local . '/yy.json');
	@rmdir($local);
}
