<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for the current and pressed state of panel controls: a link or
 * menu item printed with .active also carries aria-current, and a toggle
 * button whose chosen state is only a fill (.active, or btn-solid instead of
 * btn-outline) also carries aria-pressed, so a screen reader hears it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function current_state_marks_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

function test_current_state_translations_links()
{
	$source = current_state_marks_source('translations.php');
	pg_assert_contains("(\$code === \$language) ? ' active\" aria-current=\"page' : ''", $source, 'language menu');
	pg_assert_contains("(\$key === \$filter) ? ' active\" aria-current=\"true' : ''", $source, 'filter chips');
	pg_assert_contains("(\$page_id === 0) ? ' active\" aria-current=\"page' : ''", $source, 'all pages');
	pg_assert_contains("(\$pid === \$page_id) ? ' active\" aria-current=\"page' : ''", $source, 'page list');
	pg_assert_contains("(\$group_key === \$group) ? ' active\" aria-current=\"page' : ''", $source, 'group list');
	pg_assert_contains("<a class=\"page-link\"' . ((\$i === \$page_number) ? ' aria-current=\"page\"' : '')", $source, 'pager');
}

function test_current_state_nav_links()
{
	$pins = array(
		'erp_cashflow.php'               => array("(\$active === 'flow') ? ' active\" aria-current=\"page' : ''", "(\$active === 'forecast') ? ' active\" aria-current=\"page' : ''"),
		'erp_settings.php'               => array("((\$key === \$doc) ? ' active\" aria-current=\"page' : '')"),
		'cloudflare.php'                 => array("\$active_24h = (\$period === '24h') ? 'active\" aria-current=\"page' : '';", "\$active_cus = (\$period === 'custom') ? 'active\" aria-current=\"page' : '';"),
		'view_parasut_inbox.php'         => array("\$tab_incoming_active = (\$tab === 'incoming') ? ' active\" aria-current=\"page' : '';", "\$tab_vkn_active      = (\$tab === 'vkn')      ? ' active\" aria-current=\"page' : '';"),
		'includes/settings/screen.php'   => array("(\$pg_settings_other_key === \$pg_settings_key) ? ' active\" aria-current=\"page' : ''"),
		'erp_invoices.php'               => array("((\$edoc_filter === \$edoc_key) ? ' active\" aria-current=\"page' : '')"),
	);

	foreach ($pins as $path => $needles) {
		$source = current_state_marks_source($path);
		foreach ($needles as $needle) {
			pg_assert_contains($needle, $source, $path);
		}
	}
}

function test_current_state_offer_editor_pressed()
{
	$template = current_state_marks_source('includes/templates/edit_offer.php');
	foreach (array('pg-if-period-open', 'pg-if-period-range', 'pg-if-scope-order', 'pg-if-scope-recipient') as $id) {
		pg_assert_contains('id="' . $id . '" aria-pressed="false"', $template, $id);
	}

	$script = current_state_marks_source('assets/js/offer_editor.js');
	pg_assert_contains('function pressed(button, on)', $script);
	pg_assert_contains("pressed(\$('#pg-if-period-open'), S.offer.period === 'open');", $script);
	pg_assert_contains("pressed(\$('#pg-if-period-range'), S.offer.period === 'range');", $script);
	pg_assert_contains("pressed(\$('#pg-if-scope-order'), S.offer.scope !== 'recipient');", $script);
	pg_assert_contains("pressed(\$('#pg-if-scope-recipient'), S.offer.scope === 'recipient');", $script);
	pg_assert_contains('function choiceButton(on)', $script);
	pg_assert_false(strpos($script, "? 'btn-solid theme-secondary' : 'btn-outline theme-secondary')") !== false, 'choice fill without aria-pressed');
	pg_assert_false(strpos($script, "? 'btn-outline theme-secondary' : 'btn-solid theme-secondary')") !== false, 'choice fill without aria-pressed');
}

function test_current_state_site_log_type_filter()
{
	$source = current_state_marks_source('view_log.php');
	pg_assert_contains('active pg-log-type-filter" aria-pressed="true" data-token=""', $source);
	pg_assert_same(3, substr_count($source, 'pg-log-type-filter" aria-pressed="false"'));
	pg_assert_contains('buttons[i].setAttribute("aria-pressed", "false");', $source);
	pg_assert_contains('type_filter.setAttribute("aria-pressed", "true");', $source);
}
