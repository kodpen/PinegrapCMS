<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the inline script that hands a product group's variants to the
 * variant chooser (pg_civ_variants_script(), includes/fn/widgets_catalog.php):
 * a description holding "</script>" cannot end the script, and the payload
 * still decodes to the stored text.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_civ_variants_script_keeps_tags_inside_the_script()
{
	$description = '<p>Soft & warm</p><script>alert(1)</script><!-- x -->';
	$data = array('variants' => array('7' => array('description' => $description)));
	$script = pg_civ_variants_script($data);

	pg_assert_same(0, strpos($script, '<script>window.pgCivVariants = window.pgCivVariants || [];window.pgCivVariants.push('), 'opens the array and pushes');
	pg_assert_same(1, substr_count(strtolower($script), '</script'), 'only its own closing tag');
	pg_assert_false(strpos($script, '<!--') !== false, 'no comment opener');

	$prefix = '<script>window.pgCivVariants = window.pgCivVariants || [];window.pgCivVariants.push(';
	$json = substr($script, strlen($prefix), -strlen(');</script>'));
	pg_assert_same($data, json_decode($json, true), 'the payload gives the description back');
}
