<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for SMTP Settings, System Status and the Performance Log:
 * icon ligatures kept out of button names, a named DKIM record box, the
 * e-Fatura date and share in the panel's own format and the sort state of
 * the active Performance Log column.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function system_screens_names_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

function test_system_screens_smtp_ligatures_hidden()
{
	$source = system_screens_names_source('smtp_settings.php');

	foreach (array('forward_to_inbox', 'vpn_key', 'verified', 'save') as $ligature) {
		pg_assert_true(
			(bool) preg_match('/<span class="material-icons[^"]*" aria-hidden="true">' . $ligature . '<\/span>/', $source),
			$ligature . ' ligature aria-hidden'
		);
		pg_assert_true(
			!preg_match('/<span class="material-icons[^"]*">' . $ligature . '<\/span>/', $source),
			$ligature . ' ligature without aria-hidden'
		);
	}
}

function test_system_screens_dkim_notice_record_named()
{
	$source = system_screens_names_source('smtp_settings.php');

	pg_assert_contains('<label for="dkim_notice_record" class="form-label mt-3">', $source, 'notice label for');
	pg_assert_contains('<textarea id="dkim_notice_record" class="form-control" rows="4" readonly>', $source, 'notice textarea id');
}

function test_system_screens_efatura_date_and_share()
{
	$source = system_screens_names_source('includes/fn/system_status.php');

	pg_assert_true(strpos($source, "date('d.m.Y', strtotime(\$efatura_deadline))") === false, 'fixed d.m.Y date');
	pg_assert_contains("date(get_date_format_code() . '/Y', strtotime(\$efatura_deadline))", $source, 'panel date order');
	pg_assert_true(strpos($source, "\$efatura_row['share'] . '%'") === false, 'fixed percent order');
	pg_assert_contains("lang(array('string' => '{var:1}%', 'vars' => array(\$efatura_row['share'])))", $source, 'translated percent');
}

function test_system_screens_performance_log_aria_sort()
{
	$source = system_screens_names_source('view_performance_log.php');

	pg_assert_contains("\$aria_sort = ' aria-sort=\"ascending\"';", $source, 'ascending');
	pg_assert_contains("\$aria_sort = ' aria-sort=\"descending\"';", $source, 'descending');
	pg_assert_contains('<th class="\' . $th_class . \'"\' . $aria_sort . \'>', $source, 'th carries aria-sort');
}
