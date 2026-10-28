<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the schema type of a custom form's record pages
 * (includes/fn/seo.php): the stored word is read as BlogPosting, Article,
 * NewsArticle or none, the builder writes the chosen @type, the record page
 * writes no record block for none, and the 2026.11.0 step (11.0.55) adds
 * custom_form_pages.schema_type through the idempotent helper.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Anything that is not one of the four words, '' from a row older than the
// column included, reads as BlogPosting: the type every record page had.
function test_record_schema_type_is_read_from_the_stored_word()
{
	pg_assert_same('BlogPosting', pg_record_schema_type(''), 'empty');
	pg_assert_same('BlogPosting', pg_record_schema_type(null), 'null');
	pg_assert_same('BlogPosting', pg_record_schema_type('WebApplication'), 'a type not offered');
	pg_assert_same('Article', pg_record_schema_type('Article'), 'Article');
	pg_assert_same('NewsArticle', pg_record_schema_type(' newsarticle '), 'case and spaces');
	pg_assert_same('none', pg_record_schema_type('None'), 'none');
	pg_assert_same(array('BlogPosting', 'Article', 'NewsArticle', 'none'), pg_record_schema_types(), 'the offered words');
}

function test_record_schema_type_sets_the_type_of_the_block()
{
	$base = array('headline' => 'Post', 'url' => 'https://example.com/post');

	$data = pg_build_blogposting_jsonld($base);
	pg_assert_same('BlogPosting', $data['@type'], 'no type given');

	$data = pg_build_blogposting_jsonld($base + array('type' => 'NewsArticle'));
	pg_assert_same('NewsArticle', $data['@type'], 'NewsArticle');
	pg_assert_same('Post', $data['headline'], 'same properties');

	$data = pg_build_blogposting_jsonld($base + array('type' => 'Article'));
	pg_assert_same('Article', $data['@type'], 'Article');
}

// The record page reads the form's type and writes no record block for none.
function test_record_schema_type_none_writes_no_record_block()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/get_page_content.php');

	pg_assert_contains("\$pg_blog_schema_type = pg_form_page_schema_type(\$pg_blog_form['page_id']);", $source, 'the type of the record\'s form is read');
	pg_assert_contains("if (!empty(\$pg_blog_form_id) && ((\$pg_blog_schema_type ?? 'BlogPosting') !== 'none')) {", $source, 'none writes no record block');
	pg_assert_contains("'type'        => \$pg_blog_schema_type ?? 'BlogPosting',", $source, 'the builder is given the type');
}

function test_record_schema_type_step_adds_the_column_through_the_helper()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/migrations/2026.11.0.php');

	pg_assert_same(1, preg_match_all('#^\tupgrade_2026_11_0_record_schema_type\(\); +// 11\.0\.55$#m', $source), 'called once under 11.0.55');

	preg_match('/function upgrade_to_2026_11_0\(\) \{(.*?)\n\}\n/s', $source, $entry);
	preg_match_all('#// (11\.0\.\d+)\n#', isset($entry[1]) ? $entry[1] : '', $labels);
	pg_assert_same(1, count(array_keys($labels[1], '11.0.55', true)), 'no other step carries 11.0.55');

	pg_assert_true(preg_match('/^function upgrade_2026_11_0_record_schema_type\(\) \{\n(.*?)\n\}\n/ms', $source, $step) === 1, 'the step function is found');
	pg_assert_contains("install_add_column('custom_form_pages', 'schema_type', \"VARCHAR(32) NOT NULL DEFAULT 'BlogPosting'\");", $step[1], 'BlogPosting by default so running sites keep their output');
	pg_assert_true(stripos($step[1], 'ALTER TABLE') === false, 'no ALTER of its own');
	pg_assert_true(strpos($step[1], 'db(') === false, 'no raw statement');
}

// Both settings screens read and write the type only once the column is
// there: every key of the posted properties becomes a column of the save.
function test_record_schema_type_settings_wait_for_the_column()
{
	$page_form = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/forms/page_form.php');
	pg_assert_same(2, substr_count($page_form, 'if (pg_record_schema_type_setting_ready()) {'), 'the classic row and the posted value each wait for the column');
	pg_assert_contains("\$properties['schema_type'] = pg_record_schema_type(\$field('custom_form_schema_type'));", $page_form, 'the posted type is stored as one of the words');

	$custom_form = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/custom_form.php');
	pg_assert_contains("(pg_record_schema_type_setting_ready() ? ', schema_type' : '')", $custom_form, 'the designer reads the column once it is there');
	pg_assert_contains("if (array_key_exists('schema_type', \$settings) && pg_record_schema_type_setting_ready()) {", $custom_form, 'the designer writes it once it is there');
}
