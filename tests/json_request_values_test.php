<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the endpoints that read a JSON body: the image editor's API, the
 * translations screen's calls, the Workspace canvas save and the notes
 * import. A key read as one value that arrives as a list or an object counts
 * as not given, where (string) logged "Array to string conversion" and (int)
 * read it as id 1.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function json_request_values_test_source($file)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $file);
}

// The keys a source casts to text or a number: (string) $request['x'],
// trim((string) $request['x']), (int) ($request['x'] ?? 0) and the like.
function json_request_values_test_cast_keys($source)
{
	preg_match_all('/\((?:string|int|float)\)\s*\(?\s*\$request\[\'([a-z_]+)\'\]/', $source, $matches);

	return array_values(array_unique($matches[1]));
}

// A list or an object in a one-value key is left out; numbers, yes/no and
// the parts that are lists or objects stay as they came.
function test_image_editor_request_values_drop_lists()
{
	if (!function_exists('pg_image_editor_request_values')) {
		pg_assert_true(false, 'pg_image_editor_request_values() exists');

		return;
	}

	$request = array(
		'token'   => 'abc',
		'action'  => array('open'),
		'mode'    => array(),
		'file_id' => array(5),
		'to'      => array('a' => 7),
		'base'    => array('a' => 1),
		'name'    => array('x'),
		'weight'  => 400,
		'italic'  => true,
		'doc'     => array('layers' => array()),
		'masks'   => array(array('id' => 1)),
		'export'  => array('format' => 'png'),
	);

	pg_assert_same(array(
		'token'  => 'abc',
		'weight' => 400,
		'italic' => true,
		'doc'    => array('layers' => array()),
		'masks'  => array(array('id' => 1)),
		'export' => array('format' => 'png'),
	), pg_image_editor_request_values($request), 'only the one-value keys holding a list are left out');

	pg_assert_same(array('file_id' => '12', 'action' => 'open'), pg_image_editor_request_values(array('file_id' => '12', 'action' => 'open')), 'text and numbers stay');
}

// Every key the editor's actions cast, or hand to a helper that reads it as
// an id or as text, is one the API drops when it holds a list; and the API
// reads its request through the drop.
function test_image_editor_single_keys_cover_reads()
{
	if (!function_exists('pg_image_editor_single_keys')) {
		pg_assert_true(false, 'pg_image_editor_single_keys() exists');

		return;
	}

	$keys = pg_image_editor_single_keys();
	$source = json_request_values_test_source('includes/fn/image_editor.php');
	$all = $source . json_request_values_test_source('includes/fn/image_editor_data.php') . json_request_values_test_source('includes/fn/picture_translation.php');

	// Handed as they came to pg_image_editor_file(), pg_file_versions_row(),
	// pg_image_editor_animation_file(), pg_image_editor_font_face(),
	// pg_image_editor_take_png() and pg_image_editor_take_picture().
	$handed = array('file_id', 'version_id', 'to', 'target', 'upload', 'family', 'weight', 'overlay', 'colour', 'picture', 'svg');

	foreach (array_merge(json_request_values_test_cast_keys($all), $handed) as $key) {
		// Set by pg_image_editor_api() from the bytes after the request line,
		// never from the body.
		if ($key === 'binary') {
			continue;
		}

		pg_assert_true(in_array($key, $keys, true), 'one-value key ' . $key);
	}

	foreach (array('doc', 'export', 'extend', 'redact', 'masks', 'pictures', 'motion') as $key) {
		pg_assert_false(in_array($key, $keys, true), $key . ' is a list or an object');
	}

	pg_assert_contains('$request = pg_image_editor_request_values(pg_image_editor_arrays($objects));', $source, 'the API reads the request through the drop');
}

// The three root endpoints leave out a list or an object in each one-value
// key right after the body is decoded, before the first read.
function test_json_endpoints_drop_lists_before_reads()
{
	foreach (array('translations_action.php', 'workspace_file_save.php', 'workspace_notes_import.php') as $file) {
		$source = json_request_values_test_source($file);

		if (!preg_match('/foreach \(array\(([^)]*)\) as \$([a-z_]+)\) \{\s*if \(isset\(\$request\[\$\2\]\) && !is_scalar\(\$request\[\$\2\]\)\) \{\s*unset\(\$request\[\$\2\]\);/', $source, $match, PREG_OFFSET_CAPTURE)) {
			pg_assert_true(false, $file . ' leaves out lists in its one-value keys');

			continue;
		}

		preg_match_all('/\'([a-z_]+)\'/', $match[1][0], $names);

		foreach (json_request_values_test_cast_keys($source) as $key) {
			pg_assert_true(in_array($key, $names[1], true), $file . ' leaves out a list in ' . $key);
		}

		$first_read = strpos($source, '$request[\'');

		pg_assert_true(($first_read === false) || ($first_read > $match[0][1]), $file . ' leaves them out before the first read');
	}
}
