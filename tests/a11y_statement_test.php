<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the accessibility statement (includes/fn/widgets_accessibility.php):
 * the conformance claimed from fixed finding sets, the known limitations
 * order, the settings made safe, and escaping in the printable statement.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/a11y.php');

// Three checks of the registry's severities, without reading it.
function _a11y_statement_test_rules()
{
	return array(
		'a11y_img_alt'      => array('severity' => 'error', 'criterion' => '1.1.1', 'level' => 'A'),
		'a11y_contrast'     => array('severity' => 'warning', 'criterion' => '1.4.3', 'level' => 'AA'),
		'a11y_target_size'  => array('severity' => 'notice', 'criterion' => '2.5.8', 'level' => 'AA'),
	);
}

function test_a11y_statement_conformance_verdict()
{
	$rules = _a11y_statement_test_rules();
	$none = array();
	$warn = array('a11y_contrast' => array('pages' => 3, 'occurrences' => 9));
	$err = array('a11y_img_alt' => array('pages' => 2, 'occurrences' => 2)) + $warn;

	pg_assert_same('unchecked', pg_a11y_conformance($none, $rules, 0, 10, 0), 'no full pass: no claim, never "full"');
	pg_assert_same('unchecked', pg_a11y_conformance($err, $rules, 2, 10, 0), 'findings but no full pass yet');
	pg_assert_same('full', pg_a11y_conformance($none, $rules, 0, 10, 1700000000), 'a finished pass with no finding');
	pg_assert_same('partial', pg_a11y_conformance($warn, $rules, 0, 10, 1700000000), 'warnings only');
	pg_assert_same('partial', pg_a11y_conformance($err, $rules, 4, 10, 1700000000), 'errors on fewer than half the pages');
	pg_assert_same('none', pg_a11y_conformance($err, $rules, 5, 10, 1700000000), 'errors on half the pages');
	pg_assert_same('full', pg_a11y_conformance(array('a11y_retired' => array('pages' => 4)), $rules, 0, 10, 1700000000), 'a code no rule names is not a finding');
	pg_assert_same('full', pg_a11y_conformance(array('a11y_contrast' => array('pages' => 0)), $rules, 0, 10, 1700000000), 'a row with no page is not a finding');
}

function test_a11y_statement_limits_order()
{
	$limits = pg_a11y_statement_limits(array(
		'a11y_target_size' => array('pages' => 9, 'occurrences' => 30),
		'a11y_contrast'    => array('pages' => 4, 'occurrences' => 5),
		'a11y_img_alt'     => array('pages' => 1, 'occurrences' => 1),
	), _a11y_statement_test_rules());

	pg_assert_same(array('a11y_img_alt', 'a11y_contrast', 'a11y_target_size'), array_column($limits, 'code'), 'errors first, then warnings, then notices');
	pg_assert_same(4, $limits[1]['pages'], 'the page count');
	pg_assert_same('AA', $limits[1]['level'], 'the level');
	pg_assert_false(isset($limits[0]['rank']), 'no sort key left in the rows');
}

function test_a11y_statement_clean_settings()
{
	$clean = pg_a11y_statement_clean(array('organisation' => "  Kodpen  ", 'date' => '2026-02-30', 'scope' => array('x'), 'extra' => 'y'));

	pg_assert_same('Kodpen', $clean['organisation'], 'trimmed');
	pg_assert_same('', $clean['date'], 'an impossible date is dropped');
	pg_assert_same('', $clean['scope'], 'a non-text value is dropped');
	pg_assert_false(isset($clean['extra']), 'only the card fields are kept');
	pg_assert_same('2026-11-02', pg_a11y_statement_clean(array('date' => '2026-11-02'))['date'], 'a real date is kept');
	pg_assert_same(200, mb_strlen(pg_a11y_statement_clean(array('organisation' => str_repeat('ş', 300)))['organisation'], 'UTF-8'), 'cut at the limit in characters');
	pg_assert_same(array_keys(pg_a11y_statement_fields()), array_keys(pg_a11y_statement_clean('not an array')), 'every field, even from nothing');
}

function test_a11y_statement_print_view_escapes()
{
	$settings = pg_a11y_statement_clean(array('organisation' => '<b>Org</b>', 'contact' => 'a11y@example.com', 'date' => '2026-11-02'));
	$data = array('summary' => array('a11y_img_alt' => array('pages' => 2, 'occurrences' => 3)), 'error_pages' => 2, 'pages' => 10, 'last_run' => 1700000000);
	$parts = pg_a11y_statement_parts($settings, $data, _a11y_statement_test_rules(), 'example.com');
	$html = pg_a11y_statement_html($parts);

	pg_assert_same('partial', $parts['verdict'], 'the verdict travels with the parts');
	pg_assert_same(1, count($parts['rows']), 'one limitation row');
	pg_assert_false(strpos($html, '<b>Org</b>'), 'the organisation is escaped');
	pg_assert_true(strpos($html, '&lt;b&gt;Org&lt;/b&gt;') !== false, 'and shown');
	pg_assert_true(strpos($html, 'a11y@example.com') !== false, 'the contact is shown');
	pg_assert_false(strpos($html, 'Enforcement procedure'), 'no enforcement section without its text');

	$flags = pg_a11y_statement_flags(pg_a11y_statement_parts(pg_a11y_statement_clean(array()), array('summary' => array(), 'error_pages' => 0, 'pages' => 0, 'last_run' => 0), array(), 'example.com'));
	pg_assert_false($flags['a11y_assessed'], 'not assessed before the first pass');
	pg_assert_true($flags['no_limitations'], 'no limitation rows');
}

function test_a11y_statement_registered()
{
	$labels = pg_sw_type_labels();
	pg_assert_true(isset($labels['accessibility_statement']), 'panel label');
	$designer = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/designer.php');
	pg_assert_contains("_render_system_widget_accessibility_statement(", $designer, 'dispatched by _expand_system_widgets()');
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	pg_assert_contains("{ type: 'accessibility_statement'", $js, 'in SW_TYPES');
	pg_assert_contains("case 'accessibility_statement': {", $js, 'has a starter tree');
	pg_assert_contains("accessibility_statement: SW_ACCESSIBILITY_STATEMENT_TOKEN_GROUPS", $js, 'has a token palette');
	pg_assert_contains("accessibility_statement: 1, tag_cloud_region: 1", $js, 'draws no Messages block');
}
