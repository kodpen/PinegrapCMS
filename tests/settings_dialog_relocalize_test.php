<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a Site Settings dialog save that changes how the panel looks:
 * a new software language reloads the page so the panel speaks it at once
 * (pg_software_language_changed(), includes/fn/lang.php), and
 * the Theme card puts the acrylic effect on or off the page in place.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/lang.php');

function test_settings_language_after_save_names_a_new_language()
{
	pg_assert_same('tr', pg_software_language_changed('en', '', 'tr', ''), 'stored language changed');
	pg_assert_same('', pg_software_language_changed('tr', '', 'tr', 'en'), 'stored language the same');
	pg_assert_same('', pg_software_language_changed('de', 'de', 'tr', ''), 'an enforced language never changes');
	pg_assert_same('en', pg_software_language_changed('tr', '', 'undefined', 'en'), 'none stored: the default');
	pg_assert_same('', pg_software_language_changed('en', '', '', ''), 'nothing set at all speaks English');
	pg_assert_same('en', pg_software_language_changed('tr', '', '', ''), 'cleared to nothing: English');
}

function test_settings_pane_answers_relocalize()
{
	$pane = (string) file_get_contents(PG_FUNCTIONS_DIR . '/settings_pane.php');

	pg_assert_contains('pg_software_language_changed(', $pane);
	pg_assert_contains("'relocalize'", $pane, 'the save answer carries the flag');
	pg_assert_contains("'language' => \$relocalize", $pane, 'the saved notice speaks the new language');
}

function test_settings_dialog_reloads_on_relocalize()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/backend.src.js');
	$at = strpos($js, 'if (response.relocalize)');

	pg_assert_true($at !== false, 'the dialog reads the flag');

	$branch = substr($js, (int) $at, 700);

	pg_assert_contains("history.replaceState(history.state, '', '#settings/' + pane)", $branch, 'the hash reopens the pane without a hashchange');
	pg_assert_contains('window.location.reload()', $branch);
	pg_assert_contains('dirty = false', $branch);
}

function test_settings_theme_card_applies_acrylic_in_place()
{
	$card = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/appearance.php');

	pg_assert_contains('data-pg-advanced-visuals="', $card, 'the card carries the stored value');
	pg_assert_contains('classList.toggle("advanced-visuals"', $card, 'the script sets the body class');
}
