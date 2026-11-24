<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for includes/fn/page_base_version.php: the hash of the stored tree a
 * Page Design Studio tab started from, and when a save is refused because
 * somebody else wrote the page since.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function page_base_version_test_tree($text, $props = array())
{
	return json_encode(array(
		'id'       => 'root',
		'type'     => 'body',
		'props'    => (object)array(),
		'children' => array(array(
			'id'       => 'n1',
			'type'     => 'text',
			'props'    => (object)array_merge(array('text' => $text), $props),
			'children' => array(),
		)),
	));
}

// The same tree hashes the same, whatever escaping or empty-slot form it
// was stored with.
function test_page_base_hash_ignores_encoding()
{
	$a = '{"id":"root","props":{},"children":[{"id":"n1","props":{"text":"Café / bar"}}]}';
	$b = '{"id":"root","props":[],"children":[{"id":"n1","props":{"text":"Café \/ bar"}}]}';

	pg_assert_same(pg_page_base_hash($a), pg_page_base_hash($b));
	pg_assert_same(40, strlen(pg_page_base_hash($a)));
}

// A layout change is a new version.
function test_page_base_hash_sees_layout_change()
{
	pg_assert_true(pg_page_base_hash(page_base_version_test_tree('Old')) !== pg_page_base_hash(page_base_version_test_tree('New')));
}

// A node note written by designer/note_save is not a new version.
function test_page_base_hash_ignores_notes()
{
	$plain = page_base_version_test_tree('Hello');
	$noted = page_base_version_test_tree('Hello', array('_notes' => 'Check this', '_notes_by' => 'ayse', '_notes_at' => 1790000000));

	pg_assert_same(pg_page_base_hash($plain), pg_page_base_hash($noted));
}

// Notes inside nested slots are left out too, not only under children.
function test_page_base_hash_ignores_nested_notes()
{
	$a = '{"id":"w","props":{"slots":{"head":{"id":"h","props":{"text":"x"}}}}}';
	$b = '{"id":"w","props":{"slots":{"head":{"id":"h","props":{"text":"x","_notes":"see"}}}}}';

	pg_assert_same(pg_page_base_hash($a), pg_page_base_hash($b));
}

// No tree and a tree that is not JSON still give a hash to compare.
function test_page_base_hash_empty_and_broken()
{
	pg_assert_same(sha1(''), pg_page_base_hash(''));
	pg_assert_same(sha1('{broken'), pg_page_base_hash('{broken'));
}

// A page written since the tab loaded it is stale; one still as loaded is not.
function test_page_base_is_stale()
{
	$loaded = page_base_version_test_tree('Mine');
	$base   = pg_page_base_hash($loaded);

	pg_assert_false(pg_page_base_is_stale(12, $base, $loaded));
	pg_assert_false(pg_page_base_is_stale(12, strtoupper($base), $loaded));
	pg_assert_true(pg_page_base_is_stale(12, $base, page_base_version_test_tree('Theirs')));
	// A note added meanwhile does not refuse the save.
	pg_assert_false(pg_page_base_is_stale(12, $base, page_base_version_test_tree('Mine', array('_notes' => 'hi'))));
}

// No base sent (an older editor tab, the API), a malformed one or a new page:
// nothing to compare.
function test_page_base_is_stale_without_base()
{
	$stored = page_base_version_test_tree('Theirs');

	pg_assert_false(pg_page_base_is_stale(12, null, $stored));
	pg_assert_false(pg_page_base_is_stale(12, 'not-a-hash', $stored));
	pg_assert_false(pg_page_base_is_stale(0, pg_page_base_hash(''), $stored));
}
