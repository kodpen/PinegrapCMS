<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The source language of front-end translation as resolved from its stored
 * setting and the panel language (pg_tr_effective_source_language()), and
 * the Languages save telling a changed source language from an unchanged
 * one by that resolved language: on a site that never chose one the setting
 * is empty while the screen posts the panel language, and a save that
 * counted that as a change took every page's kept index answer away.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function translate_source_language_helper_ready()
{
	return pg_assert_true(function_exists('pg_tr_effective_source_language'), 'pg_tr_effective_source_language() exists');
}

// languages.save.php without its comments, which may quote the lines they
// explain.
function translate_source_language_save_code()
{
	$code = '';

	foreach (token_get_all((string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/languages.save.php')) as $token) {
		if (is_array($token) && (($token[0] === T_COMMENT) || ($token[0] === T_DOC_COMMENT))) {
			continue;
		}

		$code .= is_array($token) ? $token[1] : $token;
	}

	return $code;
}

function test_translate_source_language_setting_comes_first()
{
	if (!translate_source_language_helper_ready()) {
		return;
	}

	pg_assert_same('de', pg_tr_effective_source_language('de', 'tr'), 'a chosen source language');
	pg_assert_same('pt-BR', pg_tr_effective_source_language('pt-BR', ''), 'a chosen source language without a panel language');
}

// An empty setting means the panel language, read the way init.php reads it
// for TRANSLATION_SOURCE_LANGUAGE: an empty or 'undefined' panel language
// means English.
function test_translate_source_language_empty_setting_follows_the_panel()
{
	if (!translate_source_language_helper_ready()) {
		return;
	}

	pg_assert_same('tr', pg_tr_effective_source_language('', 'tr'), 'the panel language');
	pg_assert_same('en', pg_tr_effective_source_language('', ''), 'no panel language');
	pg_assert_same('en', pg_tr_effective_source_language('', 'undefined'), 'an undefined panel language');
}

// The save compares the source language as it was and as it is saved, both
// resolved, so that saving the screen as it was keeps the pages' kept index
// answers on a site whose setting is empty.
function test_translate_source_language_save_compares_resolved_languages()
{
	$code = translate_source_language_save_code();

	pg_assert_true((bool) preg_match('/\$translation_source_before\s*=\s*pg_tr_effective_source_language\(/', $code), 'the source language before the save is resolved');
	pg_assert_true((bool) preg_match('/\$translation_effective_source\s*=\s*pg_tr_effective_source_language\(\$translation_source,/', $code), 'the saved source language is resolved');
	pg_assert_true((bool) preg_match('/pg_tr_invalidate_pages\(\s*\'\'\s*,\s*0\s*,\s*\(\$translation_effective_source\s*!==\s*\$translation_source_before\)/', $code), 'the kept answers go only when the resolved language changed');
	pg_assert_true(strpos($code, 'pg_tr_prefixes_rewrite($translation_effective_source)') !== false, 'the prefixes skip the resolved source language');
}
