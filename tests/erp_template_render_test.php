<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the document template engine (erp_template_render() in
 * includes/erp/document.php): a value is printed as it is and never read as
 * template text. A value inside a section was rendered first and then
 * scanned again by the enclosing pass, so an address typed as
 * {{{account.title}}} printed the account title unescaped.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_ERP_ENTRY')) {
	define('PG_ERP_ENTRY', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/document.php');

function test_erp_template_render_value_in_a_section_is_not_read_as_a_placeholder()
{
	$html = erp_template_render('{{#account.address}}<p>{{account.address}}</p>{{/account.address}}', array(
		'account' => array('title' => '<img src=x onerror=alert(1)>', 'address' => '{{{account.title}}}'),
	));

	pg_assert_same('<p>{{{account.title}}}</p>', $html, 'the typed braces are printed, not filled in');
}

function test_erp_template_render_escaped_value_in_a_section_is_not_filled_again()
{
	$html = erp_template_render('{{#lines}}<td>{{description}}</td>{{/lines}}', array(
		'secret' => 'kept',
		'lines' => array(array('description' => 'A {{secret}} B')),
	));

	pg_assert_same('<td>A {{secret}} B</td>', $html, 'a line description is not a placeholder');
}

function test_erp_template_render_raw_value_is_not_filled_again()
{
	$html = erp_template_render('{{{a}}}|{{b}}', array('a' => '{{b}}', 'b' => '<i>'));

	pg_assert_same('{{b}}|&lt;i&gt;', $html, 'a raw value is printed as it is');
}

function test_erp_template_render_sections_and_placeholders_still_work()
{
	$html = erp_template_render('{{title}}:{{#rows}}[{{name}}/{{title}}]{{/rows}}{{^none}}-{{/none}}{{{raw}}}', array(
		'title' => 'T&',
		'rows' => array(array('name' => 'a'), array('name' => 'b<')),
		'none' => array(),
		'raw' => '<b>x</b>',
	));

	pg_assert_same('T&amp;:[a/T&amp;][b&lt;/T&amp;]-<b>x</b>', $html, 'sections, inverted sections, escaped and raw values');
}
