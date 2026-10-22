<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the search index items of the records shown on pages built in
 * the visual editor: which lists qualify (pg_sw_search_index_eligible()),
 * the address an item is stored under (pg_sw_search_index_record_url()),
 * its title and description (pg_sw_search_index_record_texts()), the folder
 * it is filed under (pg_sw_search_index_folder()), and source checks on
 * update_search_index.php, the job's lock, the bounded caches, anonymous
 * record access during a run and the caches keyed per record.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _designer_search_index_filter($field, $value, $dynamic)
{
	return array('field' => $field, 'operator' => 'is equal to', 'value' => $value, 'dynamic_value' => $dynamic);
}

function _designer_search_index_source($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

// The body of a top-level PHP function, from its declaration to the next one.
function _designer_search_index_function_body($source, $name)
{
	$start = strpos($source, 'function ' . $name . '(');
	if ($start === false) return '';
	$end = strpos($source, "\nfunction ", $start + 10);
	return ($end === false) ? substr($source, $start) : substr($source, $start, $end - $start);
}

// -- which lists qualify -------------------------------------------------------

// Public access, '' or none set on both widgets.
function test_designer_search_index_eligible_public()
{
	pg_assert_true(pg_sw_search_index_eligible(array(), array()));
	pg_assert_true(pg_sw_search_index_eligible(array('access_control' => 'public'), array('access_control' => '')));
	pg_assert_true(pg_sw_search_index_eligible(array('access_control' => ''), array('regionType' => 'form_item_view')));
}

// Either widget limited to signed-in visitors or the submitter.
function test_designer_search_index_eligible_restricted_access()
{
	pg_assert_false(pg_sw_search_index_eligible(array('access_control' => 'submitter_only'), array()));
	pg_assert_false(pg_sw_search_index_eligible(array('access_control' => 'logged_in'), array()));
	pg_assert_false(pg_sw_search_index_eligible(array(), array('access_control' => 'submitter_only')));
	pg_assert_false(pg_sw_search_index_eligible(array(), array('access_control' => 'logged_in')));
}

// A viewer filtered list shows an anonymous visitor nothing.
function test_designer_search_index_eligible_viewer_filter()
{
	pg_assert_false(pg_sw_search_index_eligible(array('viewer_filter' => true), array()));
	pg_assert_false(pg_sw_search_index_eligible(array('viewer_filter' => 1), array()));
}

// A filter comparing a field with the viewer, on the list or the record view,
// would read '' in the index run and match every anonymous record.
function test_designer_search_index_eligible_viewer_dynamic_value()
{
	$mine = array('filters' => array(_designer_search_index_filter('submitter', '', 'viewer')));
	pg_assert_false(pg_sw_search_index_eligible($mine, array()));

	$by_email = array('filters' => array(_designer_search_index_filter('email', '', 'viewers email address')));
	pg_assert_false(pg_sw_search_index_eligible(array(), $by_email));
}

// A fixed value wins over the dynamic one; other dynamic values qualify; a
// row with no field is blank and ignored.
function test_designer_search_index_eligible_other_filters()
{
	$fixed = array('filters' => array(_designer_search_index_filter('submitter', 'jane', 'viewer')));
	pg_assert_true(pg_sw_search_index_eligible($fixed, array()));

	$dated = array('filters' => array(_designer_search_index_filter('publish-date', '', 'current date')));
	pg_assert_true(pg_sw_search_index_eligible($dated, $dated));

	$blank = array('filters' => array(_designer_search_index_filter('', '', 'viewer')));
	pg_assert_true(pg_sw_search_index_eligible($blank, array()));
}

// -- the item's address ---------------------------------------------------------

// Relative to PATH: no leading slash; the name with pretty URLs on, ?r= otherwise.
function test_designer_search_index_record_url_shapes()
{
	pg_assert_same('blog-yazisi/ilk-yazim', pg_sw_search_index_record_url('blog-yazisi', 'P8TDKZMZGV', 'ilk-yazim', true));
	pg_assert_same('blog-yazisi?r=P8TDKZMZGV', pg_sw_search_index_record_url('blog-yazisi', 'P8TDKZMZGV', 'ilk-yazim', false));
	pg_assert_same('blog-yazisi?r=P8TDKZMZGV', pg_sw_search_index_record_url('blog-yazisi', 'P8TDKZMZGV', '', true));
}

// A pretty address longer than the column falls back to ?r=; no reference
// code or no fitting address gives no item.
function test_designer_search_index_record_url_limits()
{
	pg_assert_same('blog-yazisi?r=P8TDKZMZGV', pg_sw_search_index_record_url('blog-yazisi', 'P8TDKZMZGV', str_repeat('a', 300), true));
	pg_assert_same('', pg_sw_search_index_record_url('blog-yazisi', '', 'ilk-yazim', true));
	pg_assert_same('', pg_sw_search_index_record_url(str_repeat('p', 260), 'P8TDKZMZGV', '', false));
}

// -- the item's title and description -------------------------------------------

function _designer_search_index_rows()
{
	return array(
		array('name' => 'first-name', 'rss_field' => 'title', 'data' => 'Jane', 'type' => ''),
		array('name' => 'last-name', 'rss_field' => 'title', 'data' => 'Cooper', 'type' => ''),
		array('name' => 'headline', 'rss_field' => '', 'data' => '<p>Vertex   <span>launch</span></p>', 'type' => 'html'),
		array('name' => 'summary', 'rss_field' => 'description', 'data' => '<p>A short <span>summary</span>.</p>', 'type' => 'html'),
	);
}

// The RSS title fields, joined in order with spaces.
function test_designer_search_index_texts_rss_title()
{
	$texts = pg_sw_search_index_record_texts(array(), _designer_search_index_rows(), 'P8TDKZMZGV');
	pg_assert_same('Jane Cooper', $texts['title']);
	pg_assert_same('A short summary.', $texts['description']);
}

// The record view's title field wins, its HTML becomes text, and the page's
// title suffix is never appended.
function test_designer_search_index_texts_title_field()
{
	$cfg = array('title_field' => 'headline', 'title_suffix' => '| News');
	$texts = pg_sw_search_index_record_texts($cfg, _designer_search_index_rows(), 'P8TDKZMZGV');
	pg_assert_same('Vertex launch', $texts['title']);

	$texts = pg_sw_search_index_record_texts(array('title_field' => 'reference_code', 'title_suffix' => '| Inbox'), array(), 'P8TDKZMZGV');
	pg_assert_same('P8TDKZMZGV', $texts['title']);
}

// The title fits the column; a long description is cut to 255 characters
// and marked; no description field gives ''.
function test_designer_search_index_texts_limits()
{
	$rows = array(
		array('name' => 'title', 'rss_field' => 'title', 'data' => str_repeat('ş', 300), 'type' => ''),
		array('name' => 'summary', 'rss_field' => 'description', 'data' => str_repeat('ğ', 300), 'type' => ''),
	);
	$texts = pg_sw_search_index_record_texts(array(), $rows, 'X');
	pg_assert_same(255, mb_strlen($texts['title']));
	pg_assert_same(str_repeat('ğ', 255) . '...', $texts['description']);

	$texts = pg_sw_search_index_record_texts(array(), array($rows[0]), 'X');
	pg_assert_same('', $texts['description']);

	$texts = pg_sw_search_index_record_texts(array(), array(), 'X');
	pg_assert_same('', $texts['title']);
}

// -- the item's folder ------------------------------------------------------------

// Site search opens public, registration and guest folders to an anonymous
// visitor (check_view_access($folder, true)); private and membership ones
// stay closed.
function test_designer_search_index_folder_open()
{
	pg_assert_true(pg_sw_search_index_folder_open('public'));
	pg_assert_true(pg_sw_search_index_folder_open('registration'));
	pg_assert_true(pg_sw_search_index_folder_open('guest'));
	pg_assert_false(pg_sw_search_index_folder_open('private'));
	pg_assert_false(pg_sw_search_index_folder_open('membership'));
	pg_assert_false(pg_sw_search_index_folder_open(''));
}

// The detail page's folder when the list's page is open to everyone in site
// search; otherwise the list's page decides who finds the record.
function test_designer_search_index_folder()
{
	pg_assert_same(301, pg_sw_search_index_folder(300, true, 301, true));
	pg_assert_same(303, pg_sw_search_index_folder(303, false, 301, true));
	pg_assert_same(303, pg_sw_search_index_folder(301, true, 303, false));
	pg_assert_same(303, pg_sw_search_index_folder(303, false, 302, false));

	// A private host page over a record page in a registration folder: the
	// registration folder is open in site search, so the host decides.
	pg_assert_same(303, pg_sw_search_index_folder(
		303, pg_sw_search_index_folder_open('private'),
		302, pg_sw_search_index_folder_open('registration')));

	// A draft page over a record page in a membership folder: the members
	// do not find the drafts it lists.
	pg_assert_same(304, pg_sw_search_index_folder(
		304, pg_sw_search_index_folder_open('private'),
		287, pg_sw_search_index_folder_open('membership')));

	// Both on the same members-only folder: that folder.
	pg_assert_same(287, pg_sw_search_index_folder(
		287, pg_sw_search_index_folder_open('membership'),
		287, pg_sw_search_index_folder_open('membership')));
}

// -- source checks -----------------------------------------------------------------

// Bare record pages are skipped, and their host lists collected, before a
// page is drawn as an item.
function test_designer_search_index_page_loop_skips_record_pages()
{
	$source = _designer_search_index_source('update_search_index.php');
	$widgets = strpos($source, "pg_sw_page_widgets(\$page['id'])");
	$skip    = strpos($source, "\$designer_record_page = true;");
	$render  = strpos($source, "\$content = get_page_content(\$page['id']");
	pg_assert_true($widgets !== false && $skip !== false && $render !== false);
	pg_assert_true($widgets < $render && $skip < $render);
	pg_assert_true((bool) preg_match('/if \(\$designer_record_page\) \{\s*continue;/', $source, $continue, PREG_OFFSET_CAPTURE));
	pg_assert_true($skip < $continue[0][1] && $continue[0][1] < $render);
}

// The record branch: the list's and the record view's filters, complete
// records, the gates, the visibility check, ?r=, form_id and the output
// buffer level restored in a finally block, failure isolation; before the
// files and the sweep.
function test_designer_search_index_record_branch()
{
	$source = _designer_search_index_source('update_search_index.php');
	$start  = strpos($source, '$designer_record_urls = array();');
	$files  = strpos($source, '// Get files that should be included in search index.');
	$sweep  = strpos($source, 'DELETE FROM search_items WHERE timestamp');
	pg_assert_true($start !== false && $files !== false && $sweep !== false);
	pg_assert_true($start < $files && $files < $sweep);

	$branch = substr($source, $start, $files - $start);
	foreach (array(
		'pg_sw_list_record_filter_clause(', '(forms.complete = 1)', 'pg_sw_search_index_eligible(',
		"(page.page_search = '1')", 'pg_recycle_bin_folder_ids()', 'pg_sw_search_index_folder(',
		'pg_sw_record_comment_context(', "'form_id' =>", 'pg_error_throws(true)', 'catch (Throwable',
		'finally', "unset(\$_GET['r'])", 'pg_sw_item_missing()', 'pg_sw_search_index_save_item(',
		'pg_sw_search_index_folder_open(', 'while (ob_get_level() > $saved_ob_level)',
	) as $needle) {
		pg_assert_contains($needle, $branch);
	}
	pg_assert_true(strpos($branch, "\$_GET['page']") === false);
}

// The job's lock outlasts a long run.
function test_designer_search_index_job_lock()
{
	$source = _designer_search_index_source('includes/fn/cron.php');
	$start  = strpos($source, "'update_search_index' => array(");
	pg_assert_true($start !== false);
	$next   = strpos($source, ' => array(', $start + 30);
	$block  = substr($source, $start, (($next === false) ? strlen($source) : $next) - $start);
	pg_assert_contains("'lock'", $block);
}

// The per-record caches stay bounded over a whole index run.
function test_designer_search_index_bounded_caches()
{
	$source = _designer_search_index_source('includes/fn/widgets.php');
	pg_assert_contains('count($cache) >=', _designer_search_index_function_body($source, 'pg_sw_record_comment_context'));
	pg_assert_contains('count($cache) >=', _designer_search_index_function_body($source, 'pg_sw_record_passes_filters'));
}

// An index run reads records as an anonymous visitor, even from the panel
// button: a record view only signed-in visitors or the submitter may see
// does not show its record, and a visual record page's comments region
// lists published comments only.
function test_designer_search_index_anonymous_record_access()
{
	$body = _designer_search_index_function_body(_designer_search_index_source('includes/fn/widgets.php'), 'pg_sw_submitted_form_visible');
	$guard = strpos($body, "defined('UPDATE_SEARCH_INDEX') && UPDATE_SEARCH_INDEX === true");
	$signed_in = strpos($body, "if (\$access_control === 'logged_in') return \$signed_in;");
	pg_assert_true($guard !== false && $signed_in !== false && $guard < $signed_in);
	pg_assert_contains("return \$access_control !== 'logged_in' && \$access_control !== 'submitter_only';", $body);

	$source = _designer_search_index_source('get_page_content.php');
	pg_assert_contains("\$comments_for_search_index = (\$comments_record !== null)", $source);
	pg_assert_contains("if ((!\$edit_access && !\$form_editor) || \$comments_for_search_index) {", $source);
}

// Each record drawn on the same detail page in one run gets its own
// canonical address and its own comments: the page item cache and the
// record context asked for without an id are keyed by ?r= as well.
function test_designer_search_index_caches_per_record()
{
	$body = _designer_search_index_function_body(_designer_search_index_source('includes/fn/seo.php'), 'pg_sw_page_item');
	pg_assert_contains("array('page', 'r', 'cat', 'pid')", $body);
	pg_assert_contains('count($cache) >=', $body);
	pg_assert_false(strpos($body, '$cache[$page_id]') !== false);

	$body = _designer_search_index_function_body(_designer_search_index_source('includes/fn/widgets.php'), 'pg_sw_record_comment_context');
	pg_assert_contains("if (\$form_id <= 0) \$key .= ':' . ((isset(\$_GET['r'])", $body);
}
