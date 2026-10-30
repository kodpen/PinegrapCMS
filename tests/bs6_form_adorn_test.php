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

	$cases = array(
		// The gift card amount (create) and balance (edit), drawn by the shared form.
		array($root . 'includes/forms/gift_card_form.php', '\'id\' => $field', '<label for="\' . $field . \'"', 'form-adorn-end', ''),
		array($root . 'workspace_template.php', 'id="ws_tpl_icon"', 'for="ws_tpl_icon"', '', 'id="ws_tpl_icon_preview"'),
		array($root . 'includes/workspace/mcp_catalogue.php', 'id="ws_mcp_probe_alias"', 'for="ws_mcp_probe_alias"', 'form-control-sm', '>@</span>'),
		array($root . 'includes/workspace/mcp.php', 'id="\' . $prefix . \'alias"', 'for="\' . $prefix . \'alias"', 'form-control-sm', '>@</span>'),
		array($root . 'includes/forms/ad_region_form.php', "'id'=>'transition_duration'", '<label class="form-label" for="transition_duration"', 'form-adorn-end', "lang('milliseconds')"),
		array($root . 'includes/forms/ad_region_form.php', "'id'=>'slideshow_interval'", '<label class="form-label" for="slideshow_interval"', 'form-adorn-end', "lang('seconds')"),
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
		array($root . 'includes/settings/screen.php', 'id="pg_settings_filter"', '', 'form-control-sm', 'bi bi-search'),
		array($root . 'view_orders.php', 'id="tax_rate_for_parasut"', '<label class="form-label" for="tax_rate_for_parasut"', 'form-adorn-end', '>%</span>'),
		array($root . 'includes/local_sale.php', 'id="pg_sale_received"', 'for="pg_sale_received"', 'form-control-lg', 'BASE_CURRENCY_SYMBOL'),
		array($root . 'includes/designer_screen.php', 'id="sdh-filter"', '', 'form-control-sm form-adorn rounded-pill', 'bi bi-search'),
		// Offer tier fields: no <label for>, so each input names itself.
		array($root . 'assets/js/offer_editor.js', 'data-on="tier-min"', '', 'form-control-sm form-adorn form-adorn-end', 'esc(C.currency)'),
		array($root . 'assets/js/offer_editor.js', 'data-on="tier-percent"', '', 'form-control-sm form-adorn form-adorn-end', '>%</span>'),
	);

	// Settings cards: every single add-on field keeps its <label for> above the wrapper.
	foreach (pg_test_form_adorn_settings_fields() as $file => $ids) {
		foreach ($ids as $id => $add_on) {
			$cases[] = array($root . 'includes/settings/' . $file, 'id="' . $id . '"', 'for="' . $id . '"', 'form-adorn-end', $add_on);
		}
	}

	return $cases;
}

/**
 * The settings card fields drawn as form-adorn, by file: input id => add-on.
 */
function pg_test_form_adorn_settings_fields()
{
	return array(
		'api.php' => array('api_log_retention_days' => "lang('day(s)')"),
		'appearance.php' => array('dashboard_refresh' => "lang('seconds')"),
		'commerce.php' => array(
			'ecommerce_gift_card_validity_days' => "lang('day(s)')",
			'ecommerce_reward_program_points' => "lang('point(s)')",
			'ecommerce_reward_program_membership_days' => "lang('day(s)')",
			'ecommerce_surcharge_percentage' => '>%</span>',
			'affiliate_default_commission_rate' => '>%</span>',
		),
		'contact.php' => array('mailchimp_sync_days' => "lang('days in the past')"),
		'features.php' => array(
			'image_quality_webp' => '>%</span>',
			'image_quality_avif' => '>%</span>',
			'image_product_max_dimension' => "lang('pixels')",
			'image_product_min_dimension' => "lang('pixels')",
			'image_file_resize_trigger' => "lang('pixels')",
			'image_file_max_dimension' => "lang('pixels')",
			'page_revisions_days' => "lang('days')",
			'page_cache_ttl' => "lang('seconds')",
		),
		'firewall.php' => array(
			'waf_log_retention_days' => "lang('day(s)')",
			'waf_log_max_rows' => "lang('row(s)')",
			'waf_rate_limit_requests' => "lang('per minute')",
			'waf_rate_limit_sensitive' => "lang('per minute')",
			'waf_rate_limit_api' => "lang('per minute')",
			'waf_inflight_limit' => "lang('requests')",
			'waf_auto_ban_threshold' => "lang('events / 10 min')",
			'waf_auto_ban_minutes' => "lang('minute(s)')",
			'waf_auto_ban_max_minutes' => "lang('minute(s)')",
		),
		'prep.php' => array(
			'login_throttle_minutes' => "lang('minutes')",
			'login_throttle_lockout' => "lang('minutes')",
			'login_throttle_captcha_after' => "lang('failures')",
		),
		'security.php' => array(
			'\' . $form_limit_input[0] . \'' => 'h($form_limit_input[3])',
			'audit_log_retention_years' => "lang('years')",
		),
		'seo.php' => array(
			'tracking_code_duration' => "lang('day(s)')",
			'visitor_retention_days' => "lang('day(s)')",
			'visitor_ip_days' => "lang('day(s)')",
		),
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
	$gift = (string) file_get_contents($root . 'includes/forms/gift_card_form.php');
	$mcp = (string) file_get_contents($root . 'includes/workspace/mcp.php');
	$catalogue = (string) file_get_contents($root . 'includes/workspace/mcp_catalogue.php');

	pg_assert_contains("'class' => 'form-ghost text-end', 'maxlength'=>'12', 'inputmode'=>'decimal', 'required'=>'required') + pg_money_mask_attributes()", $gift);
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

function test_bs6_form_adorn_settings_cards_keep_no_single_add_on_group()
{
	$root = dirname(__DIR__) . '/pinegrap/includes/settings/';

	// The groups left are the ones form-adorn cannot hold: two add-ons
	// (language address prefix), a hint on the add-on (membership warning) and
	// buttons (SEO, the Paraşüt connect code).
	$left = array('commerce.php' => 1, 'languages.php' => 1, 'security.php' => 1, 'seo.php' => 2);

	foreach (array('api.php', 'appearance.php', 'commerce.php', 'contact.php', 'features.php', 'firewall.php', 'languages.php', 'prep.php', 'screen.php', 'security.php', 'seo.php') as $file) {
		$source = (string) file_get_contents($root . $file);

		pg_assert_same(isset($left[$file]) ? $left[$file] : 0, preg_match_all('/class="input-group[ "]/', $source), $file . ': input-groups left');
	}
}

function test_bs6_form_adorn_commerce_add_ons_name_their_own_field()
{
	$source = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/includes/settings/commerce.php');

	// The surcharge add-on was a <label> pointing at the affiliate rate; the
	// installment label pointed at the secret key.
	pg_assert_same(1, substr_count($source, 'for="affiliate_default_commission_rate"'), 'only the affiliate rate label names the affiliate rate');
	pg_assert_same(1, substr_count($source, 'for="ecommerce_iyzipay_secret_key"'), 'only the secret key label names the secret key');
	pg_assert_contains('<label class="form-label" for="ecommerce_iyzipay_installment">', $source);
}

function test_bs6_form_adorn_order_screens_keep_only_compound_groups()
{
	$root = dirname(__DIR__) . '/pinegrap/';

	// The input-groups left on the panel order screens hold buttons or
	// selects: the shipped quantity +/- controls, the start and stop
	// day/year selects, the shipping report status select.
	$left = array('view_order.php' => 1, 'view_orders.php' => 2, 'view_order_report.php' => 0, 'view_shipping_report.php' => 1);

	foreach ($left as $file => $count) {
		$source = (string) file_get_contents($root . $file);

		pg_assert_same($count, preg_match_all('/class="input-group[ "]/', $source), $file . ': input-groups left');
	}

	$order = (string) file_get_contents($root . 'view_order.php');
	pg_assert_contains('<div class="input-group number-controls">', $order);
}

function test_bs6_form_adorn_settings_row_rules_name_the_wrapper()
{
	$css = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/assets/css/backend.src.css');

	// The settings row shape test and the control placement know the wrapper,
	// or a converted field drops out of the two-column row.
	pg_assert_contains('.pg-set-card .row > [class]:has(> .form-label):has(> input.form-control:not(.tagin), > :is(select.form-control), > .input-group, > .form-adorn, > .form-range) > .form-adorn,', $css);
	pg_assert_true(strpos($css, '> .input-group, > .form-range)') === false, 'every settings row shape test names .form-adorn');

	// The add-on keeps the input-group add-on type, so a converted row stays 38px tall.
	pg_assert_contains(".pg-set-card .form-adorn > .form-adorn-text {\n  font-size: var(--bs-btn-input-font-size);", $css);
}

function test_bs6_form_adorn_designer_home_search_width_on_the_wrapper()
{
	$root = dirname(__DIR__) . '/pinegrap/';
	$css = (string) file_get_contents($root . 'assets/css/designer_home.css');

	// The search box width and its full-width narrow screen rule follow the wrapper.
	pg_assert_false(strpos($css, '.sdh-head-tools .input-group') !== false, 'designer_home.css: a width rule still names .input-group');
	pg_assert_same(2, substr_count($css, '.sdh-head-tools .form-adorn'), 'designer_home.css: both width rules name .form-adorn');
}

function test_bs6_form_adorn_offer_tier_fields_have_names()
{
	$root = dirname(__DIR__) . '/pinegrap/';
	$offer = (string) file_get_contents($root . 'edit_offer.php');
	$js = (string) file_get_contents($root . 'assets/js/offer_editor.js');

	foreach (array('tierMinLabel', 'tierPercentLabel') as $key) {
		pg_assert_contains("'" . $key . "'", $offer, 'edit_offer.php: ' . $key);
		pg_assert_contains('aria-label="\' + esc(L.' . $key . ')', $js, 'offer_editor.js: ' . $key);
	}
}

/**
 * The offer editor's condition and action fields: their captions are sibling
 * text, not labels, so each field carries an aria-label from the labels the
 * screen already hands to the script.
 */
function test_bs6_form_adorn_offer_condition_and_action_fields_have_names()
{
	$root = dirname(__DIR__) . '/pinegrap/';
	$offer = (string) file_get_contents($root . 'edit_offer.php');
	$js = (string) file_get_contents($root . 'assets/js/offer_editor.js');

	foreach (array('quantity', 'usageTotalLabel', 'usageCustomerLabel', 'days', 'discount', 'addGroup', 'selectGroupPlaceholder', 'addMethod') as $key) {
		pg_assert_contains("'" . $key . "'", $offer, 'edit_offer.php: ' . $key);
		pg_assert_contains('aria-label="\' + esc(L.' . $key . ')', $js, 'offer_editor.js: ' . $key);
	}
	pg_assert_contains('aria-label="\' + esc(t.label) + \'" data-on="cond-amount"', $js, 'offer_editor.js: subtotal amount');
	pg_assert_contains('aria-label="\' + esc(t.label) + \'" data-on="cond-mode"', $js, 'offer_editor.js: new customer mode');
	pg_assert_contains('aria-label="\' + esc(placeholder) + \'" data-on="\' + esc(onchange)', $js, 'offer_editor.js: product picker');

	// Every text, number and select field the rows draw has a name.
	preg_match_all('/<(input|select)\b[^>]*data-on="(cond-|act-|tier-)[^>]*>/', $js, $m);
	pg_assert_true(count($m[0]) >= 14, 'offer_editor.js: row fields found');
	foreach ($m[0] as $tag) {
		pg_assert_contains('aria-label=', $tag, 'offer_editor.js: ' . $tag);
	}
}
