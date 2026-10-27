<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the warnings the search index and SEO structure jobs wrote when
 * the general job ran them from the command line. The progress page header
 * read the Host header, which a command line run does not have; the first
 * custom form page ended the search index run on the missing REQUEST_URL; a
 * <system> region naming a page that is gone (deleted, or parked by the
 * Recycle Bin) read the properties of a page that was not found; and the
 * pages the index renders without a signed-in user or a requested page name
 * read the session user, the page name and a form's description field
 * without checking. A form view directory linking a private form list view
 * sent the run to the login screen, which ended it. The progress page is no
 * longer filed in the site log after every dispatched run.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function cli_job_warnings_test_source($file)
{
	return file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

// The source between two markers, so a check reads one function only.
function cli_job_warnings_test_between($source, $start, $end)
{
	$from = strpos($source, $start);

	if ($from === false) {
		return '';
	}

	$to = strpos($source, $end, $from + strlen($start));

	return ($to === false) ? substr($source, $from) : substr($source, $from, $to - $from);
}

// The panel title takes the host from HOSTNAME, which init.php sets to the
// site's hostname setting when there is no Host header.
function test_cli_job_warnings_header_title_host()
{
	$header = cli_job_warnings_test_between(cli_job_warnings_test_source('includes/fn/output.php'), 'function output_header_secure(', "\nfunction ");

	pg_assert_true($header !== '', 'output_header_secure() found');
	pg_assert_contains("\$output_host = defined('HOSTNAME') ? HOSTNAME : (\$_SERVER['HTTP_HOST'] ?? '');", $header, 'host fallback');
	pg_assert_contains("h(\$output_host)", $header, 'title uses the fallback');
	pg_assert_same(0, substr_count($header, "h(\$_SERVER['HTTP_HOST'])"), 'raw Host header read');

	$init = cli_job_warnings_test_source('init.php');

	pg_assert_contains("define('HOSTNAME', \$row['hostname']);", $init, 'command line HOSTNAME is the hostname setting');
}

// REQUEST_URL is given a value before any page is rendered, and the progress
// page is buffered and thrown away only in a dispatched run.
function test_cli_job_warnings_search_index_request_url_and_progress_page()
{
	$source = cli_job_warnings_test_source('update_search_index.php');

	$define = strpos($source, "define('REQUEST_URL', defined('PATH') ? PATH : '/');");
	$render = strpos($source, '$content = get_page_content(');

	pg_assert_true($define !== false, 'REQUEST_URL fallback');
	pg_assert_true($render !== false, 'page render found');
	pg_assert_true($define < $render, 'defined before the first render');

	$buffer_start = strpos($source, "if (defined('PG_CRON_DISPATCH')) {\n    ob_start();\n}");
	$header = strpos($source, "output_header_secure(array(\n    'title' => lang('Update Search Index'),");
	$buffer_end = strpos($source, "if (defined('PG_CRON_DISPATCH')) {\n    ob_end_clean();\n}");
	$footer = strpos($source, "</main>' . output_footer_secure();");

	pg_assert_true(($buffer_start !== false) && ($buffer_start < $header), 'buffer starts before the progress page');
	pg_assert_true(($buffer_end !== false) && ($buffer_end > $footer), 'buffer is discarded after the footer');
	pg_assert_true(strpos($source, "pg_cron_ran('update_search_index');") < $buffer_end, 'run recorded before the buffer is discarded');
}

// A form without a description field neither has a description field row nor
// selects a description column.
function test_cli_job_warnings_search_index_form_description()
{
	$source = cli_job_warnings_test_source('update_search_index.php');

	pg_assert_contains("if ((\$description_field['id'] ?? '') != '') {", $source, 'missing description field row');
	pg_assert_contains("\$description = trim(\$submitted_form['description'] ?? '');", $source, 'description column not selected');
	pg_assert_same(0, substr_count($source, "if (\$description_field['id'] != '') {"), 'unguarded description field row');
	pg_assert_contains("\$username = \$_SESSION['sessionusername'] ?? '';", $source, 'anonymous web request');
}

// A <system> region naming a page that is not there is emptied and skipped
// before the missing page's properties are read.
function test_cli_job_warnings_missing_system_region_page()
{
	$source = cli_job_warnings_test_source('get_page_content.php');

	$query = strpos($source, "db_q(\$query, array((string) \$system_page_name))");
	$skip = strpos($source, "if (!\$row) {\n                \$content = preg_replace('/<system>' . \$system_page_name . '<\\/system>/i', '', \$content);\n                continue;\n            }", (int) $query);
	$assign = strpos($source, "\$system_region_properties = \$row;", (int) $query);
	$read = strpos($source, "if (\$system_region_properties['page_id'] == \$page_id) {", (int) $query);

	pg_assert_true($query !== false, 'system page query found');
	pg_assert_true($skip !== false, 'missing page skipped');
	pg_assert_true(($skip < $assign) && ($assign < $read), 'skipped before the properties are read');
}

// The search index run is not sent to login/register by a form view
// directory that links a private form list view.
function test_cli_job_warnings_form_view_directory_private_list()
{
	$source = cli_job_warnings_test_source('get_form_view_directory_screen_content.php');

	$skip = strpos($source, "if ((USER_LOGGED_IN == false) && defined('UPDATE_SEARCH_INDEX') && (UPDATE_SEARCH_INDEX === true)) {\n                    unset(\$form_list_views[\$key]);\n                    continue;\n                }");
	$redirect = strpos($source, "go(PATH . SOFTWARE_DIRECTORY . '/registration_entrance.php?send_to='");

	pg_assert_true($skip !== false, 'index run leaves the list out');
	pg_assert_true(($redirect !== false) && ($skip < $redirect), 'before the redirect');
}

// Pages rendered without a signed-in user or a requested page name.
function test_cli_job_warnings_unsigned_renders()
{
	foreach (array('get_my_account_profile.php', 'get_affiliate_sign_up_form_screen_content.php') as $file) {
		$source = cli_job_warnings_test_source($file);

		pg_assert_contains("escape(\$_SESSION['sessionusername'] ?? '')", $source, $file . ': no session user');
		pg_assert_same(0, substr_count($source, "escape(\$_SESSION['sessionusername'])"), $file . ': unguarded session user');
		pg_assert_contains("if (is_array(\$row) == false) {\n        \$row = array(", $source, $file . ': no user row');
	}

	$catalog = cli_job_warnings_test_source('get_catalog.php');

	pg_assert_same(2, substr_count($catalog, "if (isset(\$_GET['page']) && (mb_strpos(\$_GET['page'], '/') !== FALSE)) {"), 'catalog page name checked in both layouts');
	pg_assert_same(0, substr_count($catalog, "            if (mb_strpos(\$_GET['page'], '/') !== FALSE) {"), 'unguarded catalog page name');
}
