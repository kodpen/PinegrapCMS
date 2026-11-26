<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Code fields of Site Settings keep the editor's translated texts: the
 * search panel, the line prompt and the fold markers of a code field
 * (pg_code_editor_includes(), printed once per request with the first
 * pg_code_editor_attach()) reach every category that draws one, in the
 * settings dialog and on the full-page screen.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function settings_code_editor_texts_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/' . $file);
}

// The source without its comments, which may name the functions they explain.
function settings_code_editor_texts_code($file)
{
	$code = '';

	foreach (token_get_all(settings_code_editor_texts_source($file)) as $token) {
		if (is_array($token) && (($token[0] === T_COMMENT) || ($token[0] === T_DOC_COMMENT))) {
			continue;
		}

		$code .= is_array($token) ? $token[1] : $token;
	}

	return $code;
}

// prep.php is read before the cards of every category but the Workspace
// ones, and what it builds is printed only by the card that uses it. The
// first code field of a request carries the editor's texts, so an attach in
// prep.php would spend them on markup that only the SEO category prints, and
// the code fields of Maintenance, Appearance and Features would open with
// English texts.
function test_settings_code_editor_texts_prep_draws_no_code_field()
{
	$source = settings_code_editor_texts_code('prep.php');

	pg_assert_true(strpos($source, 'pg_code_editor_attach(') === false, 'prep.php attaches no code editor');
	pg_assert_true(strpos($source, 'pg_code_editor_includes(') === false, 'prep.php prints none of the editor texts');
}

// The site-wide JSON-LD field keeps its editor: the Structured Data card,
// which prints the field, attaches it.
function test_settings_code_editor_texts_jsonld_field_attached_by_its_card()
{
	$prep = settings_code_editor_texts_source('prep.php');
	$seo = settings_code_editor_texts_source('seo.php');

	pg_assert_contains('id="custom_jsonld"', $prep, 'prep.php still builds the JSON-LD field');
	pg_assert_true(preg_match("/\\\$seo_card_id === 'pgset-structured'[^\\n]*\\n[^\\n]*pg_code_editor_attach\\(array\\('id' => 'custom_jsonld', 'code_type' => 'application\\/json'\\)\\)/", $seo) === 1, 'the Structured Data card attaches the JSON-LD field');
}

// Each category that draws a code field attaches it in its own module, so
// the first field of the pane brings the texts with it.
function test_settings_code_editor_texts_each_category_attaches_its_fields()
{
	foreach (array('appearance.php', 'features.php', 'seo.php') as $file) {
		pg_assert_contains('pg_code_editor_attach(', settings_code_editor_texts_source($file), $file . ' attaches its code fields');
	}
}
