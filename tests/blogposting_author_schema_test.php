<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the 2026.11.0 step that gives a custom form its Schema author
 * choice (upgrade_2026_11_0_blogposting_author(), 11.0.51): it is called
 * once under its own label, adds custom_form_pages.schema_author through
 * the idempotent helper with contact as the default, writes no ALTER of its
 * own and ends with a note for the operator. The step itself needs a
 * database; its source is checked here.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function blogposting_author_schema_test_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/migrations/2026.11.0.php');
}

// The body of the step function, from its signature to the closing brace at
// the start of a line.
function blogposting_author_schema_test_step()
{
	if (!preg_match('/^function upgrade_2026_11_0_blogposting_author\(\) \{\n(.*?)\n\}\n/ms', blogposting_author_schema_test_source(), $match)) {
		return '';
	}

	return $match[1];
}

function test_blogposting_author_schema_step_is_called_once_under_a_label_of_its_own()
{
	$source = blogposting_author_schema_test_source();

	pg_assert_same(1, preg_match_all('#^\tupgrade_2026_11_0_blogposting_author\(\); +// 11\.0\.51$#m', $source), 'called once from upgrade_to_2026_11_0() under 11.0.51');

	preg_match('/function upgrade_to_2026_11_0\(\) \{(.*?)\n\}\n/s', $source, $entry);
	preg_match_all('#// (11\.0\.\d+)\n#', isset($entry[1]) ? $entry[1] : '', $labels);

	pg_assert_same(1, count(array_keys($labels[1], '11.0.51', true)), 'no other step carries 11.0.51');
}

function test_blogposting_author_schema_step_adds_the_column_through_the_helper()
{
	$step = blogposting_author_schema_test_step();

	pg_assert_true($step !== '', 'the step function is found');
	pg_assert_contains("install_add_column('custom_form_pages', 'schema_author', \"VARCHAR(64) NOT NULL DEFAULT 'contact'\");", $step, 'one word per form, contact by default so running sites keep their output');
	pg_assert_true(stripos($step, 'ALTER TABLE') === false, 'no ALTER of its own');
	pg_assert_true(strpos($step, 'db(') === false, 'no raw statement');
	pg_assert_true(preg_match("/install_note\\('(?:[^'\\\\]|\\\\.)+'\\);\\s*\$/", $step) === 1, 'ends with a note for the operator');
}

// The settings screen draws and writes the choice only once the column is
// there: every key of the posted properties becomes a column of the save.
function test_blogposting_author_schema_settings_wait_for_the_column()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/forms/page_form.php');

	pg_assert_same(2, substr_count($source, 'if (pg_blogposting_author_setting_ready()) {'), 'the rows and the posted value each wait for the column');
	pg_assert_contains("\$properties['schema_author'] = pg_blogposting_author_setting_value(", $source, 'the posted choice is stored as one word');
}
