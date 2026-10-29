<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Markup pins for the Translations screen: the source and target texts,
 * the glossary rows and fields and the language names carry the language
 * they are written in, and a right-to-left language is shown and typed
 * right to left.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function translations_screen_language_source()
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/translations.php');
}

// The helper gives lang, and dir="rtl" by the same flag the site uses.
function test_translations_screen_language_helper()
{
	$source = translations_screen_language_source();

	pg_assert_contains('$text_lang = function ($code) {', $source, 'local helper');
	pg_assert_contains('$known[$code][5] === 1', $source, 'right-to-left flag of pg_tr_known_languages()');
	pg_assert_contains('\' dir="rtl"\'', $source, 'writes dir="rtl"');
	pg_assert_contains('\' lang="\' . h($code) . \'"\'', $source, 'escaped lang');
}

function test_translations_screen_language_texts()
{
	$source = translations_screen_language_source();

	pg_assert_true((bool) preg_match('/<div class="pg-tr-source[^\n]*\$text_lang\(\$source\)/', $source), 'source text in the source language');
	pg_assert_true((bool) preg_match('/<textarea class="form-control pg-tr-target"[^\n]*\$text_lang\(\$language\)/', $source), 'target box in the target language');
	pg_assert_true((bool) preg_match('/<textarea class="form-control pg-tr-target"[^\n]*aria-label=/', $source), 'target box keeps its name');
	pg_assert_false((bool) preg_match('/pg-tr-target"[^\n]*lang="\' \. h\(\$language\)/', $source), 'no second lang on the target box');
}

function test_translations_screen_language_glossary()
{
	$source = translations_screen_language_source();

	pg_assert_contains('<span class="fw-semibold"\' . $text_lang($source) . \'>\' . h($term[\'term\'])', $source, 'glossary term in the source language');
	pg_assert_contains('<span\' . $text_lang($language) . \'>\' . h($term[\'translation\'])', $source, 'glossary translation in the target language');
	pg_assert_true((bool) preg_match('/id="pg_tr_term"[^\n]*\$text_lang\(\$source\)/', $source), 'Term field in the source language');
	pg_assert_true((bool) preg_match('/id="pg_tr_term_translation"[^\n]*\$text_lang\(\$language\)/', $source), 'Translation to use field in the target language');
}

function test_translations_screen_language_names()
{
	$source = translations_screen_language_source();

	pg_assert_contains('<span\' . $text_lang($code) . \'>\' . h(pg_tr_language_label($code)) . \'</span>', $source, 'switcher names in their own language');
	pg_assert_contains('<span\' . $text_lang($language) . \'>\' . h(pg_tr_language_label($language)) . \'</span>', $source, 'current-language button name in its own language');
}
