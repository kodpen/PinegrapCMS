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

// The E-mail sending buttons' icons are decorative.
function test_system_screens_smtp_ligatures_hidden()
{
	$source = system_screens_names_source('includes/settings/mail_sending.php');

	pg_assert_same(3, preg_match_all('/<i class="bi bi-[a-z-]+" aria-hidden="true"><\/i><span class="pg-btn-label">/', $source), 'each button icon is aria-hidden');
	pg_assert_false(strpos($source, 'material-icons') !== false, 'no ligature icon left');
}

// The DKIM record to publish is a named, read-only box on its card; the
// notice after generating says where it is instead of repeating it.
function test_system_screens_dkim_record_named()
{
	$source = system_screens_names_source('includes/settings/mail_sending.php');

	pg_assert_contains('<label for="textrecord" class="pg-field-label">', $source, 'record label for');
	pg_assert_contains('<textarea id="textrecord" class="pg-input font-monospace" rows="4" readonly>', $source, 'record textarea id');
	pg_assert_contains("lang('Add the TXT record shown on the DKIM TXT Record card to your DNS.')", system_screens_names_source('includes/fn/mail_settings.php'), 'the notice points at the card');
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
