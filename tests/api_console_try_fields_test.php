<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the API console's Try it fields: every field fieldsFor() builds
 * gets an id from a counter kept across panels, and its caption is a label
 * pointing at that id.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function api_console_try_fields_source()
{
	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/api/console_view.php');
	$start = strpos($src, 'function fieldsFor(endpoint)');
	$end = strpos($src, 'function parseList(text)');
	return array($src, ($start !== false && $end !== false) ? substr($src, $start, $end - $start) : '');
}

function test_api_console_fields_get_ids_from_a_shared_counter()
{
	list($src, $fields) = api_console_try_fields_source();

	pg_assert_true($fields !== '', 'fieldsFor() is found');
	pg_assert_true(strpos($src, 'var fieldSeq = 0;') !== false && strpos($src, 'var fieldSeq = 0;') < strpos($src, 'function fieldsFor(endpoint)'), 'the counter lives outside fieldsFor()');
	pg_assert_contains('var id = "doc_field_" + (++fieldSeq);', $fields, 'each row takes the next id');
	pg_assert_contains("<label for=\\'\" + id + \"\\' class=\\'form-label small mb-1\\'>", $fields, 'the caption points at the field');
	pg_assert_contains("<span class=\\'fg-2\\'>\" + esc(T.where[where] || where) + \"</span></label>", $fields, 'the translated where text stays inside the label');
	pg_assert_contains("var attributes = \" id=\\'\" + id + \"\\'", $fields, 'the shared attributes start with the id');
	pg_assert_true(substr_count($fields, 'doc-field\\\'" + attributes') === 5, 'both textareas, both selects and the input carry the attributes');
	pg_assert_true(strpos($fields, "<label class=\\'form-label") === false, 'no caption without for');
}

function test_api_console_object_fields_are_sent_as_objects()
{
	list($src, $fields) = api_console_try_fields_source();

	pg_assert_contains('} else if (type === "object") {', $fields, 'an object parameter gets its own field');
	pg_assert_contains('placeholder=\\\'{&quot;key&quot;: &quot;value&quot;}\\\'', $fields, 'the placeholder shows a JSON object');
	pg_assert_contains('} else if (field.dataset.type === "object") {', $src, 'send() parses object fields');
	pg_assert_contains('problem = T.objectUnreadable.replace("%NAME%", name);', $src, 'an unreadable object names the field');
	pg_assert_contains('if (parsed !== null && typeof parsed === "object" && !Array.isArray(parsed)) { return parsed; }', $src, 'only a JSON object is taken');
}
