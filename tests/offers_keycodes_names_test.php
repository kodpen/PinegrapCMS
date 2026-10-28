<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the names screen readers get on the offers and key codes lists
 * and the key code form: the offer toggle's name follows its state, the
 * up-sell badge and the key code marks read as text, the Report radios have
 * a group name and the landmarks are translated.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function offers_keycodes_names_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/templates/' . $file);
}

function test_offers_toggle_name_follows_its_state()
{
	$src = offers_keycodes_names_source('view_offers.php');

	pg_assert_contains('aria-label="<?=$offer[\'enabled\'] ? h(lang(\'Disable offer\')) : h(lang(\'Enable offer\'))?>"', $src, 'the toggle prints its name beside its title');
	pg_assert_contains(".attr('aria-label', text)", $src, 'relabel() renames the toggle after a click');
}

function test_offers_upsell_badge_has_a_name()
{
	$src = offers_keycodes_names_source('view_offers.php');

	pg_assert_contains('<i class="bi bi-chat-square-text" aria-hidden="true"></i><span class="visually-hidden"><?=lang(\'Up-sell message\')?></span>', $src, 'the up-sell badge reads its name');
}

function test_key_code_marks_read_as_text()
{
	$src = offers_keycodes_names_source('view_key_codes.php');

	pg_assert_contains('<span class="material-icons" aria-hidden="true">task_alt</span><span class="visually-hidden"><?=lang(\'Yes\')?></span>', $src, 'the Single-Use mark reads Yes');
	pg_assert_false(strpos($src, 'aria-label="Button Bar"') !== false, 'the toolbar landmark is translated');
	pg_assert_contains('<span class="material-icons me-3" aria-hidden="true">delete_forever</span>', $src, 'the delete ligature is not read');

	$cells = pg_key_code_list_cells(array(
		'enabled' => '1', 'expiration_date' => '0000-00-00',
		'offer_status' => 'enabled', 'offer_start_date' => '2000-01-01', 'offer_end_date' => '2099-12-31',
		'last_modified_timestamp' => time(), 'last_modified_username' => '',
	), date('Y-m-d'));

	pg_assert_contains('<span class="material-icons" aria-hidden="true">task_alt</span>', $cells['enabled']['html'], 'the Enabled ligature is hidden');
	pg_assert_contains('<span class="visually-hidden">' . h(lang('Yes')) . '</span>', $cells['enabled']['html'], 'the Enabled mark reads Yes');
}

function test_key_code_report_radios_have_a_group_name()
{
	$src = offers_keycodes_names_source('edit_key_code.php');

	pg_assert_false(strpos($src, 'for=""') !== false, 'no label points at nothing');
	pg_assert_contains('<fieldset class="col-12 my-3">', $src, 'the Report radios sit in a fieldset');
	pg_assert_contains('<legend class="form-label fs-md"><?=lang(\'Report\')?></legend>', $src, 'the legend names the group');
	pg_assert_false(strpos($src, 'aria-label="data edit buttons ') !== false, 'the save bar landmark is translated');
	pg_assert_false((bool) preg_match('/<span class="material-icons me-3">/', $src), 'button ligatures are not read');
}
