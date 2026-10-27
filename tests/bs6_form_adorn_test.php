<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source tests for panel fields drawn as Bootstrap 6 form-adorn: the add-on
 * inside the wrapper label and hidden from the accessible name, the input a
 * borderless form-ghost that keeps its own <label for>, no input-group left
 * around it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

/**
 * The markup from the form-adorn wrapper that holds the input with $id to
 * the end of that input's line, or '' when the input is not in one.
 */
function pg_test_form_adorn_block($source, $id)
{
	$at = strpos($source, $id);

	if ($at === false) {
		return '';
	}

	$start = strrpos(substr($source, 0, $at), '<label class="form-control');
	$group = strrpos(substr($source, 0, $at), 'input-group');

	if ($start === false || ($group !== false && $group > $start)) {
		return '';
	}

	$end = strpos($source, "\n", $at);

	return substr($source, $start, (($end === false) ? strlen($source) : $end) - $start);
}

function pg_test_form_adorn_cases()
{
	$root = dirname(__DIR__) . '/pinegrap/';

	return array(
		array($root . 'add_gift_card.php', "'id' => 'amount'", '<label for="amount"', 'form-adorn-end', ''),
		array($root . 'edit_gift_card.php', "'id' => 'balance'", '<label for="balance"', 'form-adorn-end', ''),
		array($root . 'workspace_template.php', 'id="ws_tpl_icon"', 'for="ws_tpl_icon"', '', 'id="ws_tpl_icon_preview"'),
		array($root . 'includes/workspace/mcp_catalogue.php', 'id="ws_mcp_probe_alias"', 'for="ws_mcp_probe_alias"', 'form-control-sm', '>@</span>'),
		array($root . 'includes/workspace/mcp.php', 'id="\' . $prefix . \'alias"', 'for="\' . $prefix . \'alias"', 'form-control-sm', '>@</span>'),
		array($root . 'add_ad_region.php', "'id'=>'transition_duration'", '<label class="form-label" for="transition_duration"', 'form-adorn-end', "lang('milliseconds')"),
		array($root . 'edit_ad_region.php', "'id'=>'transition_duration'", '<label class="form-label" for="transition_duration"', 'form-adorn-end', "lang('milliseconds')"),
		array($root . 'add_ad_region.php', "'id'=>'slideshow_interval'", '<label class="form-label" for="slideshow_interval"', 'form-adorn-end', "lang('seconds')"),
		array($root . 'edit_ad_region.php', "'id'=>'slideshow_interval'", '<label class="form-label" for="slideshow_interval"', 'form-adorn-end', "lang('seconds')"),
		// Toolbar search fields: the input names itself with aria-label.
		array($root . 'view_folders.php', 'id="filter_input"', '', 'form-control-sm', 'bi bi-search'),
		array($root . 'view_folders.php', 'id="transfer_filter"', '', 'form-control-sm', 'bi bi-search'),
		array($root . 'view_folders.php', 'id="ws_send_filter"', '', 'form-control-sm', 'bi bi-search'),
		array($root . 'view_audit_log.php', 'name="q"', '', 'form-control-sm', 'bi bi-search'),
		array($root . 'view_not_found_log.php', 'id="pg_404_search"', '', 'form-control-sm', 'bi bi-search'),
		array($root . 'translations.php', 'name="q"', '', 'form-control-sm', 'bi bi-search'),
		array($root . 'erp_cheques.php', 'name="search"', '', 'form-control-sm', 'bi bi-search'),
		array($root . 'erp_quotes.php', 'name="search"', '', 'form-control-sm', 'bi bi-search'),
		array($root . 'erp_purchase_orders.php', 'name="search"', '', 'form-control-sm', 'bi bi-search'),
		array($root . 'erp_stock_minimums.php', 'name="search"', '', 'form-control-sm', 'bi bi-search'),
		array($root . 'includes/forms/contact_form.php', 'id="affiliate_commission_rate" class', '<label for="affiliate_commission_rate" class="form-label"', 'form-adorn-end', '>%</span>'),
	);
}

function test_bs6_form_adorn_wraps_the_single_add_on_fields()
{
	foreach (pg_test_form_adorn_cases() as $case) {
		list($file, $id, $label, $modifier, $add_on) = $case;
		$source = (string) file_get_contents($file);
		$block = pg_test_form_adorn_block($source, $id);
		$name = basename($file);

		pg_assert_true($block !== '', $name . ': the input sits in a form-adorn label, not an input-group');
		pg_assert_contains('form-adorn', $block, $name);
		pg_assert_contains('form-ghost', $block, $name);
		pg_assert_contains('class="form-adorn-text" aria-hidden="true"', $block, $name);
		pg_assert_false(strpos($block, 'form-control text-end') !== false || strpos($block, 'input class="form-control') !== false, $name . ': the input lost form-control');

		if ($modifier !== '') {
			pg_assert_contains($modifier, $block, $name);
		}

		if ($add_on !== '') {
			pg_assert_contains($add_on, $block, $name);
		}

		// The field keeps its own name: the add-on is hidden from it.
		if ($label !== '') {
			pg_assert_contains($label, substr($source, 0, (int) strpos($source, $block)), $name);
		} else {
			pg_assert_contains('aria-label="', $block, $name . ': a search field without a <label for> names itself');
		}
	}
}

function test_bs6_form_adorn_keeps_the_input_attributes()
{
	$root = dirname(__DIR__) . '/pinegrap/';
	$gift = (string) file_get_contents($root . 'add_gift_card.php');
	$mcp = (string) file_get_contents($root . 'includes/workspace/mcp.php');
	$catalogue = (string) file_get_contents($root . 'includes/workspace/mcp_catalogue.php');

	pg_assert_contains("'class' => 'form-ghost text-end', 'maxlength'=>'12', 'inputmode'=>'numeric', 'required'=>'required') + pg_money_mask_attributes()", $gift);
	pg_assert_contains('pattern="[A-Za-z][A-Za-z0-9_\-]{1,31}" required autocomplete="off"\' . $disabled . \'>', $mcp);
	pg_assert_contains('pattern="[A-Za-z][A-Za-z0-9_\-]{1,31}" required autocomplete="off">', $catalogue);
}

function test_bs6_form_adorn_toolbar_search_keeps_its_toolbar_class()
{
	$root = dirname(__DIR__) . '/pinegrap/';

	$files = array(
		'view_folders.php' => 'id="filter_input"',
		'view_audit_log.php' => 'name="q"',
		'view_not_found_log.php' => 'id="pg_404_search"',
		'translations.php' => 'name="q"',
		'erp_cheques.php' => 'name="search"',
		'erp_quotes.php' => 'name="search"',
		'erp_purchase_orders.php' => 'name="search"',
		'erp_stock_minimums.php' => 'name="search"',
	);

	foreach ($files as $file => $id) {
		$source = (string) file_get_contents($root . $file);
		$at = (int) strpos($source, $id);
		$before = substr($source, max(0, $at - 900), 900);

		// The .pg-toolbar-search width and narrow-screen order stay on the field.
		pg_assert_contains('pg-toolbar-search', $before, $file);
		pg_assert_contains('rounded-pill', $before, $file);
	}
}

function test_bs6_form_adorn_workspace_toolbar_searches()
{
	$root = dirname(__DIR__) . '/pinegrap/assets/js/';
	$cases = array(
		'workspace.js' => array("t('search_tasks')", "t('tl_search')", "t('notes_search')"),
		'workspace_board_channel.js' => array("t('cb_search')"),
	);

	foreach ($cases as $file => $placeholders) {
		$source = (string) file_get_contents($root . $file);

		// No toolbar search is an input-group around a lone input any more.
		pg_assert_false(strpos($source, "'input-group input-group-sm rounded-pill pg-toolbar-search'") !== false, $file . ': a toolbar search is still an input-group');

		foreach ($placeholders as $placeholder) {
			$at = strpos($source, $placeholder);
			pg_assert_true($at !== false, $file . ': ' . $placeholder);
			$before = substr($source, max(0, (int) $at - 700), 700);
			$after = substr($source, (int) $at, 500);

			pg_assert_contains("'form-control form-control-sm form-adorn rounded-pill pg-toolbar-search'", $before, $file . ': ' . $placeholder);
			pg_assert_contains("'form-ghost'", $before, $file . ': ' . $placeholder);
			pg_assert_contains("'form-adorn-text'", $before, $file . ': ' . $placeholder);
			pg_assert_contains("setAttribute('aria-hidden', 'true')", $before, $file . ': ' . $placeholder);
			pg_assert_contains("'bi-search'", $before, $file . ': ' . $placeholder);
			pg_assert_contains("setAttribute('aria-label'", $after, $file . ': ' . $placeholder);
		}
	}
}
