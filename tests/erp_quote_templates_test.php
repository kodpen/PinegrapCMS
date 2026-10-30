<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for sales quote templates (includes/erp/quote_templates.php) that
 * need no database: who sees a template, who may write one, what form_data
 * keeps and gives back, and the migration step that brings the table.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/quote_templates.php');
require_once(__DIR__ . '/helpers/source_reader.php');

// One stored line as erp_invoice_form_read() gives it.
function erp_quote_templates_test_line($extra = array())
{
	return array_merge(array(
		'product_id'          => 12,
		'description'         => 'Installation',
		'quantity'            => 2.0,
		'unit_code'           => 'C62',
		'unit_price'          => 150000,
		'discount_rate'       => 5.0,
		'tax_rate'            => 20.0,
		'tax2_rate'           => 0.0,
		'withholding_code'    => '',
		'withholding_rate'    => 0.0,
	), $extra);
}

// A personal template is its owner's alone; a site template is everyone's.
function test_erp_quote_template_visibility()
{
	$personal = array('scope' => 'personal', 'user_id' => 7);
	$site = array('scope' => 'site', 'user_id' => 7);

	pg_assert_true(erp_quote_template_visible($personal, 7), 'the owner sees a personal template');
	pg_assert_false(erp_quote_template_visible($personal, 8), 'nobody else does');
	pg_assert_false(erp_quote_template_visible(array('scope' => 'personal', 'user_id' => 0), 0), 'no owner, no reader');
	pg_assert_true(erp_quote_template_visible($site, 8), 'a site template for every ERP user');
	pg_assert_true(erp_quote_template_visible($site, 7), 'its maker too');
	pg_assert_false(erp_quote_template_visible(array('scope' => 'team', 'user_id' => 7), 7), 'an unknown scope for nobody');
}

// The list query reads the same rule.
function test_erp_quote_templates_list_reads_the_rule()
{
	$source = pg_test_function_source('erp_quote_templates_for_user');

	pg_assert_contains("scope = 'site'", $source, 'site templates');
	pg_assert_contains("scope = 'personal' AND user_id = ", $source, 'personal ones by owner');
}

// Writing needs the right to edit a quote; a personal template is changed
// only by its owner, a site one by anyone who may write quotes.
function test_erp_quote_template_write_refusal()
{
	pg_assert_same('', erp_quote_template_write_refusal('personal', 7, 7, false), 'the owner writes a personal template');
	pg_assert_same('', erp_quote_template_write_refusal('site', 7, 8, false), 'anyone with the right writes a site template');
	pg_assert_true(erp_quote_template_write_refusal('personal', 7, 8, false) !== '', 'not someone else\'s personal template');
	pg_assert_true(erp_quote_template_write_refusal('site', 0, 8, true) !== '', 'the accountant\'s read-only right writes none');
	pg_assert_true(erp_quote_template_write_refusal('personal', 8, 8, true) !== '', 'not even a personal one');
	pg_assert_true(erp_quote_template_write_refusal('team', 8, 8, false) !== '', 'an unknown scope');
	pg_assert_true(erp_quote_template_write_refusal('personal', 0, 0, false) !== '', 'nobody signed in');
}

// form_data keeps the lines and nothing else, and gives back only lines.
function test_erp_quote_template_form_data_round_trip()
{
	$line = erp_quote_templates_test_line();
	$blank = erp_quote_templates_test_line(array('product_id' => 0, 'description' => '  '));
	$extra = erp_quote_templates_test_line(array('account_id' => 3, 'issue_date' => '2026-10-30', 'full_number' => 'TS1', 'source_line' => 'x', 'offer_id' => 4, 'offer_discount_rate' => 10.0));

	$json = erp_quote_template_encode(array($line, $blank, $extra));
	$decoded = json_decode($json, true);

	pg_assert_same(array('lines'), array_keys($decoded), 'only lines are kept');
	pg_assert_same(2, count($decoded['lines']), 'an empty row is not kept');

	$lines = erp_quote_template_lines($json);

	pg_assert_same($line, $lines[0], 'a line comes back as it was saved');
	pg_assert_same($line, $lines[1], 'the account, dates, number, source line and offer are dropped');

	$foreign = json_encode(array(
		'account_id'  => 3,
		'issue_date'  => '2026-10-30',
		'due_date'    => '2026-11-30',
		'full_number' => 'TS2026000001',
		'lines'       => array($extra),
	));

	pg_assert_same(array($line), erp_quote_template_lines($foreign), 'a row written elsewhere gives lines only');
	pg_assert_same(array(), erp_quote_template_lines('not json'), 'broken data gives no lines');
	pg_assert_same(array(), erp_quote_template_lines(null), 'no data gives no lines');
}

// "From template" on the new quote screen fills the lines and nothing else.
function test_erp_quote_template_fills_only_the_lines()
{
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/add_erp_quote.php');
	$start = strpos($screen, "if (\$template_action === 'apply')");

	pg_assert_true($start !== false, 'the screen fills from a template');

	$branch = substr($screen, $start, strpos($screen, "} elseif (\$template_action === 'save')", $start) - $start);

	pg_assert_contains("assign_field_value('lines'", $branch, 'the lines are filled');
	foreach (array('account_id', 'issue_date', 'due_date', 'currency', 'notes') as $field) {
		pg_assert_false(strpos($branch, "assign_field_value('" . $field . "'") !== false, $field . ' stays the operator\'s');
	}
}

// One migration step brings the table and the follow-up column.
function test_erp_quote_templates_migration_step()
{
	$file = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/migrations/2026.11.0.php');
	$start = strpos($file, 'function upgrade_2026_11_0_erp_quote_templates()');

	pg_assert_true($start !== false, 'the step exists');
	pg_assert_contains("upgrade_2026_11_0_erp_quote_templates();    // 11.0.63", $file, 'called on its ERP label');

	$step = substr($file, $start, strpos($file, "\n}\n", $start) - $start);

	pg_assert_contains("install_create_table('erp_quote_templates'", $step, 'the table');
	foreach (array(
		'id INT UNSIGNED NOT NULL AUTO_INCREMENT',
		"name VARCHAR(100) NOT NULL DEFAULT ''",
		"scope VARCHAR(16) NOT NULL DEFAULT 'personal'",
		'user_id INT UNSIGNED NOT NULL DEFAULT 0',
		'form_data MEDIUMTEXT NULL',
		'created_at INT UNSIGNED NOT NULL DEFAULT 0',
		'updated_at INT UNSIGNED NOT NULL DEFAULT 0',
		'KEY idx_scope_user (scope, user_id)',
	) as $column) {
		pg_assert_contains($column, $step, $column);
	}
	pg_assert_false(stripos($step, 'ENUM') !== false, 'scope is no ENUM');
	pg_assert_contains("install_add_column('config', 'erp_quote_expiry_followup', \"TINYINT(1) NOT NULL DEFAULT 0\")", $step, 'the follow-up column');
	pg_assert_false(stripos($step, 'ALTER TABLE') !== false, 'no bare ALTER');
	pg_assert_false(strpos($step, 'db(') !== false, 'no direct query');
}

// A reinstall drops it with the other tables.
function test_erp_quote_templates_in_get_tables()
{
	pg_assert_contains("\t\t'erp_quote_templates',\n", (string) file_get_contents(PG_FUNCTIONS_DIR . '/install/index.php'), 'listed in get_tables()');
}
