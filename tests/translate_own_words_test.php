<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for what a page in another language counts toward its robots
 * verdict under the "reviewed" index policy: only its own words. The
 * counters and their scope (pg_tr_render_counter(), pg_tr_render_scope(),
 * pg_tr_render_count_key()), the verdict and the cached-body flag made from
 * them (pg_tr_render_noindex(), pg_tr_render_counts_reviewed()) and the
 * custom HTML block counted at its node (pg_tr_render_custom_html()) in
 * includes/translate/render.php; the record view's records counted as the
 * page's own (pg_sw_list_publish()); the translation job a record save opens
 * (pg_tr_records_editor(), pg_tr_scope_owners()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/translate/render.php');
require_once(PG_FUNCTIONS_DIR . '/includes/translate/content.php');
require_once(PG_FUNCTIONS_DIR . '/includes/translate/jobs.php');

// Counts with nothing drawn, for the verdict tests.
function _translate_own_words_counts($set = array())
{
	return array_merge(array('total' => 0, 'hits' => 0, 'machine' => 0, 'google' => 0, 'data_total' => 0, 'data_hits' => 0, 'data_machine' => 0), $set);
}

// The page's own words count where the verdict reads them; a list's
// records, the catalog and other database values count apart. Google output
// is counted in either, as the attribution hangs on it.
function test_translate_own_words_counter_splits_by_scope()
{
	pg_tr_render_counter('reset');

	pg_tr_render_counter('total');
	pg_tr_render_counter('hits');

	$before = pg_tr_render_scope('data');
	pg_tr_render_counter('total');
	pg_tr_render_counter('total');
	pg_tr_render_counter('hits');
	pg_tr_render_counter('machine');
	pg_tr_render_counter('google');
	$inner = pg_tr_render_scope($before);

	$counts = pg_tr_render_counter('get');

	pg_assert_same('own', $before, 'own words by default');
	pg_assert_same('data', $inner, 'the scope set');
	pg_assert_same('own', pg_tr_render_scope(), 'restored');
	pg_assert_same(1, $counts['total'], 'own total');
	pg_assert_same(1, $counts['hits'], 'own hits');
	pg_assert_same(0, $counts['machine'], 'own machine');
	pg_assert_same(2, $counts['data_total'], 'data total');
	pg_assert_same(1, $counts['data_hits'], 'data hits');
	pg_assert_same(1, $counts['data_machine'], 'data machine');
	pg_assert_same(1, $counts['google'], 'google in either scope');

	pg_tr_render_counter('reset');
}

// The key a counter name goes to in a scope.
function test_translate_own_words_count_key()
{
	pg_assert_same('total', pg_tr_render_count_key('total', 'own'), 'own total');
	pg_assert_same('data_machine', pg_tr_render_count_key('machine', 'data'), 'data machine');
	pg_assert_same('google', pg_tr_render_count_key('google', 'data'), 'google stays one count');
	pg_assert_same('', pg_tr_render_count_key('reset', 'own'), 'not a counter');
	pg_assert_same('', pg_tr_render_count_key('data_total', 'own'), 'a bucket is not a counter name');
	pg_assert_same('own', pg_tr_render_scope('nonsense'), 'an unknown scope is not set');
	pg_assert_same('own', pg_tr_render_scope(), 'still own');
}

// Untranslated or machine-translated list records, products and database
// texts never turn the page noindex under the reviewed policy.
function test_translate_own_words_noindex_ignores_database_texts()
{
	$counts = _translate_own_words_counts(array('total' => 12, 'hits' => 12, 'data_total' => 40, 'data_hits' => 3, 'data_machine' => 2, 'google' => 1));

	pg_assert_false(pg_tr_render_noindex('reviewed', 1, $counts), 'new blog posts on the list');
	pg_assert_same(1, pg_tr_render_counts_reviewed($counts), 'the cached body flag');
}

// The page's own words still keep it out until each is translated and
// reviewed; the flag of a cached body and the other policies as before.
function test_translate_own_words_noindex_follows_own_words()
{
	pg_assert_true(pg_tr_render_noindex('reviewed', 1, _translate_own_words_counts(array('total' => 3, 'hits' => 2))), 'an own text missing');
	pg_assert_true(pg_tr_render_noindex('reviewed', 1, _translate_own_words_counts(array('total' => 3, 'hits' => 3, 'machine' => 1))), 'an own text machine translated');
	pg_assert_true(pg_tr_render_noindex('reviewed', 0, _translate_own_words_counts()), 'the cached body said not reviewed');
	pg_assert_false(pg_tr_render_noindex('reviewed', 1, _translate_own_words_counts()), 'nothing drawn');
	pg_assert_true(pg_tr_render_noindex('none', 1, _translate_own_words_counts()), 'none');
	pg_assert_false(pg_tr_render_noindex('all', 1, _translate_own_words_counts(array('total' => 3))), 'all');
	pg_assert_same(0, pg_tr_render_counts_reviewed(_translate_own_words_counts(array('total' => 2, 'hits' => 1))), 'flag with an own text missing');
	pg_assert_same(0, pg_tr_render_counts_reviewed(_translate_own_words_counts(array('total' => 2, 'hits' => 2, 'machine' => 1))), 'flag with own machine output');
}

// A custom HTML block's texts are the page's own words, though the
// finished-page pass draws them: told apart by its node.
function test_translate_own_words_custom_html_node()
{
	pg_assert_true(pg_tr_render_custom_html('content', array('contentType' => 'custom_html', 'html' => '<p>Hi</p>')), 'a custom HTML block');
	pg_assert_false(pg_tr_render_custom_html('content', array('contentType' => 'paragraph', 'text' => 'Hi')), 'a paragraph');
	pg_assert_false(pg_tr_render_custom_html('content', array('contentType' => 'custom_html')), 'no HTML');
	pg_assert_false(pg_tr_render_custom_html('semantic', array('contentType' => 'custom_html', 'html' => '<p>Hi</p>')), 'another node type');
}

// The finished-page pass counts what it swaps apart from the verdict.
function test_translate_own_words_database_pass_is_data()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/translate/content.php');
	$start = strpos($source, 'function pg_tr_render_db_texts(');
	$body = substr($source, $start, strpos($source, "\n}", $start) - $start);

	pg_assert_contains("pg_tr_render_scope('data')", $body, 'the pass sets the data scope');
	pg_assert_contains('pg_tr_render_scope($scope)', $body, 'and restores it');
}

// The rows of the lists inside a record carry whether the record is the
// page's own (a record view) or one of a list's.
function test_translate_own_words_record_lists_carry_the_scope()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets.php');

	pg_assert_contains("array('translate' => !empty(\$cfg['translate_records']), 'own' => true)", $source, 'the record view publishes its record as its own');
	pg_assert_contains("\$custom = pg_tr_record_fields(\$custom, true);", $source, 'the record view counts its fields as its own');
	pg_assert_contains("array_map('pg_tr_record_fields', \$by_form)", $source, 'the list leaves them out');
}

// Only someone who may edit the form's records opens a job by saving one:
// a manager or above, or a user with the forms right and edit access to
// the form page's folder.
function test_translate_own_words_records_editor()
{
	pg_assert_true(pg_tr_records_editor(0, 0, false), 'administrator');
	pg_assert_true(pg_tr_records_editor(1, 0, false), 'designer');
	pg_assert_true(pg_tr_records_editor('2', 0, false), 'manager');
	pg_assert_true(pg_tr_records_editor(3, 1, true), 'user with the forms right and folder access');
	pg_assert_false(pg_tr_records_editor(3, 1, false), 'no folder access');
	pg_assert_false(pg_tr_records_editor(3, 0, true), 'no forms right');
	pg_assert_false(pg_tr_records_editor(null, 0, false), 'nobody signed in');
}

// A record save's job covers the form's records.
function test_translate_own_words_records_scope()
{
	pg_assert_same(array(array('form_records', 14)), pg_tr_scope_owners('records:14'), 'the records of one form');
}
