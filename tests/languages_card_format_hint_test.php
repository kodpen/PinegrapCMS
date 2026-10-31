<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Markup pins for the format boxes of a Site Settings Languages row: one
 * hint states what Number format, Date format and og:locale take, the three
 * boxes point at it, and an empty box shows that language's own default as
 * its example instead of a fixed one.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function languages_card_format_hint_source()
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/includes/settings/languages.php');
}

function test_languages_card_format_hint_is_printed()
{
	$source = languages_card_format_hint_source();

	pg_assert_true((bool) preg_match('/<div class="form-text" id="tr_formats_hint_\' \. h\(\$translation_code\) \. \'">/', $source), 'each row prints a format hint with its own id');
	pg_assert_contains("lang('Number: how 1234.56 is written in this language", $source, 'hint states the number rule');
	pg_assert_contains('PHP date letters', $source, 'hint states the date rule');
	pg_assert_contains('language_COUNTRY', $source, 'hint states the og:locale shape');
}

function test_languages_card_format_boxes_point_at_hint()
{
	$source = languages_card_format_hint_source();

	foreach (array('number', 'date', 'og') as $box) {
		pg_assert_true((bool) preg_match('/id="tr_' . $box . '_[^"]*"[^>]*aria-describedby="tr_formats_hint_\' \. h\(\$translation_code\) \. \'"/', $source), 'tr_' . $box . ' points at the hint');
	}
}

function test_languages_card_format_placeholders_follow_language()
{
	$source = languages_card_format_hint_source();

	foreach (array('1,234.56', 'j/n/Y', 'en_US') as $fixed) {
		pg_assert_false(strpos($source, 'placeholder="' . $fixed . '"') !== false, 'no fixed placeholder ' . $fixed);
	}

	foreach (array('number' => 3, 'date' => 4, 'og' => 2) as $box => $index) {
		pg_assert_true((bool) preg_match('/\$translation_known\[\$translation_code\]\[' . $index . '\]/', $source), $box . ' example comes from the known list');
	}
}
