<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Markup pins for the Site Settings Languages card and the Translations
 * screen: the Remove help matches what the save does, the hints are
 * readable without a mouse, and the translation boxes, the review toggle
 * and the suspicious mark carry their own names.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function translations_screen_names_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

// The save purges a removed language's translations; no visible text may
// say they are kept.
function test_translations_screen_names_remove_help_is_true()
{
	$source = translations_screen_names_source('includes/settings/languages.php');

	pg_assert_false(strpos($source, 'the translations already made are kept') !== false, 'stale "kept" sentence gone');
	pg_assert_contains('id="tr_remove_hint"', $source, 'remove help has an id');
	pg_assert_true((bool) preg_match('/id="tr_remove_[^"]*"[^>]*aria-describedby="tr_remove_hint"/', $source), 'Remove points at the help');
	pg_assert_contains('id="tr_auto_hint"', $source, 'translate-on-save help has an id');
	pg_assert_true((bool) preg_match('/id="tr_auto_[^"]*"[^>]*aria-describedby="tr_auto_hint"/', $source), 'Translate on save points at the help');
	// The hints were hover-only titles on the wrapping div.
	pg_assert_false((bool) preg_match('/<div class="form-field[^"]*"\s+title=/', $source), 'no title on a form-field div');
}

function test_translations_screen_names_review_toggle_follows_state()
{
	$source = translations_screen_names_source('translations.php');

	pg_assert_true((bool) preg_match('/<button[^\n]*pg-tr-review[^\n]*/', $source, $match), 'review button found');
	pg_assert_contains('no-popover', $match[0], 'no popover copies a stale title into aria-label');
	pg_assert_contains('aria-label="', $match[0], 'own aria-label');
	pg_assert_contains('aria-label="\' . h($review_label) . \'"', $match[0], 'named by the state label');
	pg_assert_contains('$review_label = ($state === \'reviewed\') ? lang(\'Mark as not reviewed\') : lang(\'Mark as reviewed\');', $source, 'reviewed rows offer the reverse');

	$script = translations_screen_names_source('assets/js/translations.js');
	pg_assert_contains("review.setAttribute('aria-label'", $script, 'rowState renames the button');
}

function test_translations_screen_names_boxes_and_marks()
{
	$source = translations_screen_names_source('translations.php');

	pg_assert_true((bool) preg_match('/<textarea class="form-control pg-tr-target"[^>]*>/', $source, $match), 'translation box found');
	pg_assert_contains("aria-label=\"' . h(lang('Translation')) . '\"", $match[0], 'translation box named');

	// One markup for the shown mark and the hidden copy a save reveals.
	pg_assert_same(1, preg_match_all('/class="badge theme-danger pg-tr-suspicious/', $source), 'one suspicious mark markup');
	pg_assert_true((bool) preg_match('/pg-tr-suspicious[^\n]*<span class="visually-hidden">\' \. h\(\$suspicious_text\)/', $source), 'suspicious mark carries text');
}
