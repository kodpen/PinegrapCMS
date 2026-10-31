<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Wording that says what really happened: the Translations Update log line
 * of a language translated by hand (a scan, nothing sent), the scan-only
 * tooltip of its Update button, the Import ZIP rename log line and the
 * Variant Sets enable/disable log line and notice read right at one.
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

function pg_tzv_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

function pg_tzv_assert_tr($key)
{
	$map = pg_test_language_map('tr');
	pg_assert_true(isset($map[$key]) && $map[$key] !== '', 'Turkish value for: ' . $key);
}

function test_translations_update_logs_a_send_only_with_a_job()
{
	$src = pg_tzv_source('translations_action.php');

	pg_assert_contains("if (!empty(\$result['job_id']))", $src, 'the sent line waits for a job');
	pg_assert_contains("'the texts of {var:1} were scanned ({var:2} text(s) pending)'", $src, 'a scan without a job logs a scan line');
	pg_tzv_assert_tr('the texts of {var:1} were scanned ({var:2} text(s) pending)');
}

function test_translations_update_tooltip_by_hand_is_scan_only()
{
	$src = pg_tzv_source('translations.php');

	foreach (array(
		'Scan this page and list its pending texts here',
		'Scan these texts and list the pending ones here',
		'Scan every page and list the pending texts here',
	) as $key) {
		pg_assert_contains("lang('" . $key . "')", $src, $key);
		pg_tzv_assert_tr($key);
	}
	pg_assert_contains("(\$engine_key === 'manual')", $src, 'the manual engine picks the scan-only tooltips');
}

function test_import_zip_rename_line_has_its_own_key()
{
	$src = pg_tzv_source('import_zip.php');

	pg_assert_false(strpos($src, 'during import my site') !== false, 'no Import My Site wording');
	pg_assert_contains("'file was renamed during the ZIP file import ({var:1} -> {var:2})'", $src, 'own ZIP key');
	pg_tzv_assert_tr('file was renamed during the ZIP file import ({var:1} -> {var:2})');
}

function test_variant_set_status_line_reads_right_at_one()
{
	$src = pg_tzv_source('variant_set_action.php');

	pg_assert_false(strpos($src, '{var:1} variant set{suffix:1} were updated') !== false, 'no "1 variant set were updated"');
	foreach (array('{var:1} variant set was updated', '{var:1} variant sets were updated') as $key) {
		pg_assert_contains("lang(array('string' => '" . $key . "'", $src, $key);
		pg_tzv_assert_tr($key);
	}
	pg_assert_contains("log_activity(\$updated_text,", $src, 'the log line uses the counted text');
	pg_assert_contains("add_notice(\$updated_text)", $src, 'the notice uses the counted text');
}
