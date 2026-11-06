<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Visit Details (view_visitor.php) and the submitted-form screens print their
 * values as text, not as headings, so a screen reader's heading list keeps
 * only the section headings; the site search terms are escaped once; the
 * long referral links wrap at phone width; the ERP quarter pick lists are
 * worded in the panel language.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_visit_details_values_are_not_headings()
{
	foreach (array('view_visitor.php', 'edit_submitted_form.php', 'add_submitted_form.php') as $file) {
		$source = file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
		pg_assert_false((bool) preg_match('/<div class="form-text">[^\n]*<\/div>\s*<h[1-6][\s>]/', $source), $file . ': no heading right under a form-text caption');
	}

	$source = file_get_contents(PG_FUNCTIONS_DIR . '/view_visitor.php');
	pg_assert_contains("\$output_fact(lang('IP Address'), h(\$ip_address_text))", $source, 'view_visitor.php: IP address as a summary row');
	pg_assert_contains('<dd class="mb-0 text-end text-break">', $source, 'view_visitor.php: summary values as text, not headings');
	pg_assert_contains("\$output_tile(lang('Page Views'), pg_format_number(\$page_views, 0), 'h4')", $source, 'view_visitor.php: page views keep their size');
	pg_assert_contains('<div class="\' . $size . \' text-break">', $source, 'view_visitor.php: tile values as text, not headings');
}

function test_visit_details_search_terms_escaped_once()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/view_visitor.php');

	pg_assert_false(strpos($source, "h(\$site_search_terms))") !== false, 'view_visitor.php: no inner h() on the search terms');
	pg_assert_contains("h(\$site_search_terms ? str_replace('|', ', ', \$site_search_terms) : '-')", $source, 'view_visitor.php: search terms escaped once');
}

function test_visit_details_referral_links_wrap()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/view_visitor.php');

	pg_assert_contains('<a class="pg-link-secondary text-break" href="\' . h(escape_url($http_referer))', $source, 'view_visitor.php: referrer URL wraps');
	pg_assert_contains('<a class="pg-link-secondary text-break" href="http://\' . h($referring_host_name)', $source, 'view_visitor.php: host name wraps');
	pg_assert_contains('<a class="pg-link-secondary text-break" href="\' . OUTPUT_PATH . h($landing_page_name)', $source, 'view_visitor.php: landing page wraps');
	pg_assert_false((bool) preg_match('/<a class="pg-link-secondary" /', $source), 'view_visitor.php: every secondary link wraps');
}

function test_visit_details_erp_quarter_translated()
{
	foreach (array('erp_profit.php', 'erp_vat_report.php') as $file) {
		$source = file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
		pg_assert_false(strpos($source, "' / Q'") !== false, $file . ': no English quarter abbreviation outside lang()');
		pg_assert_contains('erp_profit_period_options($period)', $source, $file . ': quarter options from the shared builder');
	}

	// The shared builder of the period picker names a quarter through lang().
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/erp/profit.php');
	pg_assert_contains("lang(array('string' => '{var:1} / Q{var:2}', 'vars' => array(substr(\$value, 0, 4), substr(\$value, 6, 1))))", $source, 'profit.php: quarter option through lang()');

	pg_assert_same('{var:1} / {var:2}. Çeyrek', pg_lang_map('tr')['{var:1} / Q{var:2}'] ?? null, 'tr: quarter wording');
}
