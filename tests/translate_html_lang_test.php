<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_tr_html_lang() in includes/fn/translate.php: the language a
 * rendered document's <html> tag carries. The designer writes a fixed
 * lang="en" into every page, so "en" is replaced and a missing attribute is
 * added, while a language written into a style by hand stands.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The designer's "en" says nothing about the page: it is replaced, whatever
// its case and quotes.
function test_translate_html_lang_replaces_the_designer_english()
{
	pg_assert_same(
		'<!DOCTYPE html><html lang="tr"><head></head><body></body></html>',
		pg_tr_html_lang('<!DOCTYPE html><html lang="en"><head></head><body></body></html>', 'tr'));

	pg_assert_same('<html lang="tr" class="h-100">', pg_tr_html_lang('<html LANG=\'EN\' class="h-100">', 'tr'), 'any case and quotes');
}

// A tag without the attribute gets it.
function test_translate_html_lang_adds_a_missing_attribute()
{
	pg_assert_same('<html class="h-100" lang="tr">', pg_tr_html_lang('<html class="h-100">', 'tr'));
	pg_assert_same('<html lang="tr">', pg_tr_html_lang('<html>', 'tr'));
}

// A language other than "en" was written on purpose and is kept.
function test_translate_html_lang_keeps_a_language_written_by_hand()
{
	pg_assert_same('<html lang="de">', pg_tr_html_lang('<html lang="de">', 'tr'));
}

// An English site keeps its English.
function test_translate_html_lang_leaves_english_alone()
{
	pg_assert_same('<html lang="en">', pg_tr_html_lang('<html lang="en">', 'en'));
}

// Only the document's own tag: one quoted or repeated in the body is left.
function test_translate_html_lang_touches_only_the_first_tag()
{
	pg_assert_same(
		'<html lang="tr"><body><code>&lt;html lang="en"&gt;</code><html lang="en"></body></html>',
		pg_tr_html_lang('<html lang="en"><body><code>&lt;html lang="en"&gt;</code><html lang="en"></body></html>', 'tr'));
}

// The value is escaped; nothing to set, or nothing to set it on, comes back
// as it was.
function test_translate_html_lang_escapes_the_value_and_skips_an_empty_one()
{
	pg_assert_same('<html lang="tr&quot;">', pg_tr_html_lang('<html lang="en">', 'tr"'));
	pg_assert_same('<html lang="en">', pg_tr_html_lang('<html lang="en">', ''), 'no language');
	pg_assert_same('', pg_tr_html_lang('', 'tr'), 'no document');
	pg_assert_same('<p>no html tag</p>', pg_tr_html_lang('<p>no html tag</p>', 'tr'), 'no tag');
}
