<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for the Workspace channel Summary cards (Time spent, Customer):
 * their titles are drawn as the overview cards draw theirs, an h3.h6 with an
 * icon, not the uppercase primary form card heading.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_workspace_card_titles_summary_cards()
{
	$files = array(
		'assets/js/workspace_task_time.js' => array("h.t('tt_card')", 'bi-stopwatch'),
		'assets/js/workspace_customer.js' => array("h.t('customer')", 'bi-person-vcard'),
	);

	foreach ($files as $path => $expect) {
		$source = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
		pg_assert_true(strpos($source, 'text-uppercase h5 fg-primary fw-bold') === false, $path . ': form card heading class on a Workspace card');
		pg_assert_contains("h.el('h3', 'h6 mb-0')", $source);
		pg_assert_contains("h.icon('" . $expect[1] . "', 'me-1')", $source);
		pg_assert_contains('document.createTextNode(' . $expect[0] . ')', $source);
	}
}
