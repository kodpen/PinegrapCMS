<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that the offer editor and the offers list keep keyboard focus when
 * they redraw: render() names the focused control and focuses its match in
 * the new markup, a removed row or tag hands focus to its neighbour, a new
 * row or a template focuses the first field it filled, deleting the key
 * codes focuses Generate, and a toggle filtered out of the list hands focus
 * to the next row or the active chip.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function offer_editor_focus_js()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/offer_editor.js');
}

function test_offer_editor_render_restores_focus()
{
	$js = offer_editor_focus_js();

	pg_assert_contains("var FOCUS_ATTRS = ['data-tier', 'data-id', 'data-unit', 'data-target', 'data-mode', 'data-type'];", $js, 'the focus key reads the attributes that tell siblings apart');
	pg_assert_contains('function render(keys) {', $js, 'render() takes the controls to focus');
	pg_assert_contains('var focused = focusedControl();', $js, 'without a list render() keys on the focused control');
	pg_assert_contains("if (keys.length) { restoreFocus(keys, force); }", $js, 'render() focuses the match after the rebuild');
	pg_assert_contains("var box = el.closest('.pg-combobox-menu') || (el.classList.contains('pg-combobox') ? el : null);", $js, 'a combobox toggle or menu stands for its select');
	pg_assert_contains('return el.pgComboboxToggle || el;', $js, 'a combobox select is reached through its toggle');
}

function test_offer_editor_removals_hand_focus_on()
{
	$js = offer_editor_focus_js();

	pg_assert_contains("render(afterRowRemove('condition', i))", $js, 'a removed condition hands focus on');
	pg_assert_contains("render(afterRowRemove('action', i))", $js, 'a removed result hands focus on');
	pg_assert_contains('return [rowRemoveKey(kind, index), rowRemoveKey(kind, index - 1), addButton(kind)];', $js, 'the row now in place, the one before, the Add button');
	pg_assert_contains("render(afterTagRemove(el, 'cond-product-add'))", $js, 'a removed product tag hands focus on');
	pg_assert_contains("render(afterTagRemove(el, 'cond-group-add'))", $js, 'a removed group tag hands focus on');
	pg_assert_contains("render(afterTagRemove(el, 'act-method-add'))", $js, 'a removed method tag hands focus on');
	pg_assert_contains("'[data-on=\"tier-add\"][data-index=\"' + i + '\"]'", $js, 'a removed last tier falls back to Add tier');
}

function test_offer_editor_new_rows_take_focus()
{
	$js = offer_editor_focus_js();

	pg_assert_contains("render([firstField('condition', S.conditions.length - 1), addButton('condition')]);", $js, 'a condition from the menu focuses its row');
	pg_assert_contains("render([firstField('action', S.actions.length - 1), addButton('action')]);", $js, 'a result from the menu focuses its row');
	pg_assert_contains("render([firstField('condition', 0), firstField('action', 0), '#pg-if-code']);", $js, 'a template focuses the first row it filled');
	pg_assert_contains("'[data-on=\"tier-min\"][data-index=\"' + i + '\"][data-tier=\"' + (S.actions[i].tiers.length - 1) + '\"]'", $js, 'Add tier focuses the new tier');
	pg_assert_contains('var same = [focusKey(el), rowRemoveKey(', $js, 'a pick in a row select keeps focus on it');
}

function test_offer_editor_key_code_delete_focuses_generate()
{
	$js = offer_editor_focus_js();

	pg_assert_contains("if (lost) { restoreFocus(['[data-on=\"keycodes-generate\"]', '#pg-if-keycodes-quantity'], true); }", $js, 'Generate takes focus after the codes are deleted');
}

function test_offers_list_toggle_hands_focus_on()
{
	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/templates/view_offers.php');

	pg_assert_contains("if (focused && !\$.contains(document.documentElement, btn[0])) {", $src, 'a toggle filtered out of the list hands focus on');
	pg_assert_contains("(next.length ? next : \$('#pg-offers-toolbar [data-filter].active')).first().trigger('focus');", $src, 'the next row toggle, else the active chip');
}
