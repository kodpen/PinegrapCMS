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

// The compiled cache of a language sits in data/temp as lang_<code>.php; a
// code that is not a plain name builds no path.
function test_lang_files_cache_path()
{
	pg_assert_same('/x/temp/lang_tr.php', pg_lang_cache_path('tr', '/x/temp'));
	pg_assert_same('/x/temp/lang_pt-BR.php', pg_lang_cache_path('pt-BR', '/x/temp/'), 'a trailing slash');
	pg_assert_same(PG_FUNCTIONS_DIR . '/data/temp/lang_en.php', pg_lang_cache_path('en'), 'data/temp by default');

	foreach (array('', '../tr', 'tr/x', 'tr.php', "tr\0", str_repeat('a', 33)) as $code) {
		pg_assert_same(null, pg_lang_cache_path($code, '/x/temp'), 'no path from "' . $code . '"');
	}
}

// The signature changes with any source file's name, time or size, and with
// a file added or gone; the order the files are listed in does not matter.
function test_lang_files_cache_signature()
{
	$stats = array('a.json' => array(1000, 10), 's.json' => array(2000, 20));
	$signature = pg_lang_cache_signature($stats);

	pg_assert_same($signature, pg_lang_cache_signature(array('s.json' => array(2000, 20), 'a.json' => array(1000, 10))), 'order does not count');
	pg_assert_true($signature !== pg_lang_cache_signature(array('a.json' => array(1000, 10), 's.json' => array(2001, 20))), 'a newer shard');
	pg_assert_true($signature !== pg_lang_cache_signature(array('a.json' => array(999, 10), 's.json' => array(2000, 20))), 'an older shard (a copy keeping old times)');
	pg_assert_true($signature !== pg_lang_cache_signature(array('a.json' => array(1000, 11), 's.json' => array(2000, 20))), 'another size');
	pg_assert_true($signature !== pg_lang_cache_signature(array('a.json' => array(1000, 10))), 'a shard gone');
	pg_assert_true($signature !== pg_lang_cache_signature($stats + array('b.json' => array(1000, 10))), 'a shard added');
	pg_assert_true($signature !== pg_lang_cache_signature(array('b.json' => array(1000, 10), 's.json' => array(2000, 20))), 'a shard renamed');
}

// The cache file: the Pinegrap header, a gate so it cannot run as a page,
// and an array that gives back every key and text exactly.
function test_lang_files_cache_text()
{
	$map = array(
		'Save' => 'Kaydet',
		"It's" => 'Bu \' ve \\ ve "',
		'End ?> <?php' => '?>',
		'10001' => '10001',
		"Line\nbreak" => "Satır\r\nsonu\0",
		'Ürün' => 'Ürün ı ğ ş',
	);

	$text = pg_lang_cache_text('sig1', $map);

	pg_assert_same(0, strpos($text, "<?php\n/**\n * Pinegrap - Enterprise Website Platform\n"), 'the Pinegrap header');
	pg_assert_true(strpos($text, "if (!defined('PG_FUNCTIONS_DIR')) {\n    exit;\n}\n") !== false, 'the gate');

	$file = sys_get_temp_dir() . '/pg_lang_cache_text_' . getmypid() . '.php';
	file_put_contents($file, $text);
	$data = include $file;
	@unlink($file);

	pg_assert_same(array('signature' => 'sig1', 'map' => $map), $data, 'every key and text back');
}

// pg_lang_map() with a cache folder: the first read writes the compiled
// file, the next reads it, a changed shard rebuilds it, a folder it cannot
// write to only costs the cache.
function test_lang_files_map_cache()
{
	$local = sys_get_temp_dir() . '/pg_lang_cache_local_' . getmypid();
	$temp = sys_get_temp_dir() . '/pg_lang_cache_temp_' . getmypid();
	@mkdir($local);
	@mkdir($local . '/xx');
	@mkdir($temp);

	file_put_contents($local . '/xx/s.json', pg_lang_file_encode(array('Save' => 'Kaydet')));
	touch($local . '/xx/s.json', 1000000000);

	$cache = $temp . '/lang_xx.php';

	pg_assert_same(array('Save' => 'Kaydet'), pg_lang_map('xx', $local, $temp), 'read from the shards');
	pg_assert_true(is_file($cache), 'the cache is written');
	pg_assert_same(array(), glob($temp . '/*.tmp-*'), 'no temporary file left');

	// A cache whose signature matches is read instead of the shards.
	$data = include $cache;
	file_put_contents($cache, pg_lang_cache_text($data['signature'], array('Save' => 'From cache')));
	pg_assert_same(array('Save' => 'From cache'), pg_lang_map('xx', $local, $temp), 'read from the cache');

	// A shard edited, even to an older time, rebuilds it.
	file_put_contents($local . '/xx/s.json', pg_lang_file_encode(array('Save' => 'Kaydet!')));
	touch($local . '/xx/s.json', 999999999);
	pg_assert_same(array('Save' => 'Kaydet!'), pg_lang_map('xx', $local, $temp), 'a changed shard');
	$data = include $cache;
	pg_assert_same(array('Save' => 'Kaydet!'), $data['map'], 'the cache rebuilt');

	// A shard added.
	file_put_contents($local . '/xx/a.json', pg_lang_file_encode(array('Apple' => 'Elma')));
	pg_assert_same(array('Apple' => 'Elma', 'Save' => 'Kaydet!'), pg_lang_map('xx', $local, $temp), 'a new shard');

	// A cache that does not parse is rebuilt.
	file_put_contents($cache, "<?php\nreturn array(");
	pg_assert_same(array('Apple' => 'Elma', 'Save' => 'Kaydet!'), pg_lang_map('xx', $local, $temp), 'a broken cache');
	$data = include $cache;
	pg_assert_same(array('Apple' => 'Elma', 'Save' => 'Kaydet!'), $data['map'], 'the broken cache rewritten');

	// The single file is cached the same way.
	file_put_contents($local . '/yy.json', '{"Save": "Tek"}');
	pg_assert_same(array('Save' => 'Tek'), pg_lang_map('yy', $local, $temp), 'a single file');
	pg_assert_true(is_file($temp . '/lang_yy.php'), 'a single file cached');

	// No language, or a folder the cache cannot be written to.
	pg_assert_same(array(), pg_lang_map('zz', $local, $temp), 'no language');
	pg_assert_true(!is_file($temp . '/lang_zz.php'), 'nothing cached for no language');
	pg_assert_same(array('Save' => 'Tek'), pg_lang_map('yy', $local, $temp . '/missing'), 'no cache folder');

	foreach (array($local . '/xx/s.json', $local . '/xx/a.json', $local . '/yy.json', $cache, $temp . '/lang_yy.php') as $file) {
		@unlink($file);
	}
	@rmdir($local . '/xx');
	@rmdir($local);
	@rmdir($temp);
}
