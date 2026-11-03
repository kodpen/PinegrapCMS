<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a refused Connect or Save on the marketplace account screen
 * (marketplace_settings.php): the browser asks for the name and the text
 * credentials first, and a refusal the server still makes draws the form
 * again from what was posted, opened, without echoing a secret back.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function marketplace_settings_refused_test_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/marketplace_settings.php');
}

function marketplace_settings_refused_test_credential_field()
{
	$source = marketplace_settings_refused_test_source();
	$start = strpos($source, 'function mp_settings_credential_field(');

	pg_assert_true($start !== false, 'the credential field helper is there');

	return substr($source, $start, (int) strpos($source, "\n}\n", $start) - $start);
}

function test_marketplace_settings_name_boxes_are_required()
{
	$source = marketplace_settings_refused_test_source();

	pg_assert_true((bool) preg_match('/id="new_name" name="name"[^>]*\brequired\b/', $source), 'the Connect name is required');
	pg_assert_true((bool) preg_match('/id="edit_name" name="name"[^>]*\brequired\b/', $source), 'the drawer name is required');
}

// A text credential is required on Connect only: in the drawer a blank box
// keeps what is stored. A secret is never drawn back into its box.
function test_marketplace_settings_credential_boxes()
{
	$helper = marketplace_settings_refused_test_credential_field();

	pg_assert_true(strpos($helper, "(!\$editing ? ' required' : '')") !== false, 'a text credential is required on Connect only');
	pg_assert_true(strpos($helper, "autocomplete=\"' . (!empty(\$meta['secret']) ? 'new-password' : 'off')") !== false, 'secret boxes keep new-password');
	pg_assert_true((bool) preg_match('/!\$editing && empty\(\$meta\[\'secret\'\]\)[^;]*value="/', $helper), 'only a non-secret box on Connect draws a value');
}

function test_marketplace_settings_refused_connect_is_drawn_from_the_post()
{
	$source = marketplace_settings_refused_test_source();

	pg_assert_true(strpos($source, "\$mp_refused = 'create';") !== false, 'a refused Connect is recognised');
	pg_assert_true(strpos($source, "(\$key === \$new_provider) ? ' selected' : ''") !== false, 'the posted marketplace is selected');
	pg_assert_true((bool) preg_match('/id="new_name" name="name" value="\' \. h\(\$new_name\)/', $source), 'the posted name is drawn');
	pg_assert_true(strpos($source, "\$new_push_price = (\$mp_refused === 'create') ? !empty(\$_POST['push_price']) : true;") !== false, 'Send prices is drawn as posted');
	pg_assert_true(strpos($source, 'name="push_price" value="1" checked>') === false, 'Send prices is no longer always on');
}

function test_marketplace_settings_refused_save_keeps_its_account()
{
	$source = marketplace_settings_refused_test_source();

	pg_assert_true(strpos($source, "\$mp_refused = 'update';") !== false, 'a refused Save is recognised');
	pg_assert_true((bool) preg_match('/if \(\$mp_refused === \'update\'\) \{\s*\$selected_id = \$account_id;/', $source), 'the account is the posted account_id');
	pg_assert_true(strpos($source, "\$edit_name = (\$mp_refused === 'update')") !== false, 'the drawer name is drawn from the post');
	pg_assert_true(strpos($source, "pgUI.ready(function () { pgUI[kind].show(el); });") !== false, 'the refused form opens again through pgUI');
	pg_assert_true(strpos($source, 'bootstrap.Dialog') === false, 'no direct Bootstrap call');
}

// The name and cargo template columns hold 100 characters: the boxes say so
// and the server refuses a longer value instead of letting MySQL cut it.
function test_marketplace_settings_lengths_are_refused_not_cut()
{
	$source = marketplace_settings_refused_test_source();

	pg_assert_true((bool) preg_match('/id="new_name" name="name"[^>]*\bmaxlength="100"/', $source), 'the Connect name has a maxlength');
	pg_assert_true((bool) preg_match('/id="edit_name" name="name"[^>]*\bmaxlength="100"/', $source), 'the drawer name has a maxlength');
	pg_assert_true((bool) preg_match('/id="edit_shipment_template" name="shipment_template"[^>]*\bmaxlength="100"/', $source), 'the cargo template has a maxlength');
	pg_assert_true(substr_count($source, "} elseif (mb_strlen(\$name) > 100) {") === 2, 'a long name is refused on Connect and Save');
	pg_assert_true(strpos($source, "mb_substr(isset(\$_POST['shipment_template'])") === false, 'the cargo template is no longer cut');
}

// An emptied Days to prepare keeps the stored value instead of becoming 1.
function test_marketplace_settings_empty_preparing_day_keeps_the_stored_value()
{
	$source = marketplace_settings_refused_test_source();

	pg_assert_true(strpos($source, "? (int)\$_POST['preparing_day'] : (int)\$account['preparing_day'];") !== false, 'the save falls back to the stored value');
	pg_assert_true(strpos($source, "? (int)\$_POST['preparing_day'] : (int)\$selected['preparing_day']));") !== false, 'the redraw falls back to the stored value');
	pg_assert_true((bool) preg_match('/id="edit_preparing_day" name="preparing_day"[^>]*\bstep="1" required/', $source), 'the box asks for a whole number');
}

// The messages of a refused Connect or Save are drawn inside the dialog or
// drawer it opens again, not under its backdrop, and name their field.
function test_marketplace_settings_refusal_is_shown_in_its_form()
{
	$source = marketplace_settings_refused_test_source();

	pg_assert_true(strpos($source, "' . (\$mp_refused ? '' : \$liveform->output_errors()) . ") !== false, 'not printed at the top of a refused post');
	pg_assert_true((bool) preg_match('/<div class="dialog-body">\s*\' \. \(\(\$mp_refused === \'create\'\) \? \$mp_form_errors : \'\'\)/', $source), 'printed in the Connect dialog');
	pg_assert_true((bool) preg_match('/<div class="drawer-body">\s*\' \. \(\(\$mp_refused === \'update\'\) \? \$mp_form_errors : \'\'\)/', $source), 'printed in the drawer');
	pg_assert_true(strpos($source, "\$liveform->mark_error(\$field, lang('This is required.'));") === false, 'a credential message names its field');
	pg_assert_true(strpos(marketplace_settings_refused_test_credential_field(), "(\$invalid ? ' is-invalid\" aria-invalid=\"true' : '')") !== false, 'a refused credential box is marked');
}
