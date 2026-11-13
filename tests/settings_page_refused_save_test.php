<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a settings page whose save was refused: what was posted is kept
 * by control name (pg_settings_typed_values()), secrets are left out of what
 * is printed back (pg_settings_typed_restorable()), and the page puts the
 * values back and marks the refused fields (pgSettingsRestore()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_SETTINGS_MENU')) {
	define('PG_SETTINGS_MENU', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/settings/registry.php');

function test_settings_typed_values_by_control_name()
{
	$post = array(
		'token'                  => 'abc',
		'fake_user'              => array('name' => 'No Autofill'),
		'submitted_button_field' => 'submit',
		'submit_save'            => 'Save',
		'organization_city'      => 'pg-test Şehir',
		'support_email'          => 'bad-address',
		'allowed_ips'            => array('1.1.1.1', '2.2.2.2'),
		'languages'              => array('en' => array('og_locale' => 'english', 'served' => '1')),
		'count'                  => 5,
	);

	pg_assert_same(array(
		'organization_city'        => array('pg-test Şehir'),
		'support_email'            => array('bad-address'),
		'allowed_ips[]'            => array('1.1.1.1', '2.2.2.2'),
		'allowed_ips[0]'           => array('1.1.1.1'),
		'allowed_ips[1]'           => array('2.2.2.2'),
		'languages[en][og_locale]' => array('english'),
		'languages[en][served]'    => array('1'),
		'count'                    => array('5'),
	), pg_settings_typed_values($post), 'every value by control name, token, decoy and button left out');

	pg_assert_same(array(), pg_settings_typed_values(array()), 'nothing posted');
	pg_assert_same(array(), pg_settings_typed_values(null), 'no post at all');
}

function test_settings_typed_restorable_drops_secrets()
{
	$typed = array(
		'organization_city' => array('Izmir'),
		'smtp_password'     => array('secret'),
		'backup[key]'       => array('k'),
		'logo'              => array('x.png'),
	);

	$html = '<input type="text" name="organization_city" value="" />'
		. '<input name="smtp_password" type="password" value="" />'
		. '<input type=password name="backup[key]" />'
		. '<input type="file" name="logo" />';

	pg_assert_same(array('organization_city' => array('Izmir')), pg_settings_typed_restorable($typed, $html), 'password and file controls left out');
	pg_assert_same($typed, pg_settings_typed_restorable($typed, ''), 'no markup, nothing left out');
	pg_assert_same(array(), pg_settings_typed_restorable(null, $html), 'no typed values');
}

function test_settings_page_puts_typed_values_back()
{
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/screen.php');

	pg_assert_contains("\$_SESSION['software']['pg_settings_typed'][\$pg_settings_key] = pg_settings_typed_values(\$_POST);", $screen, 'a refused save keeps what was typed');
	pg_assert_contains('pgSettingsRestore(', $screen, 'the redrawn page puts it back');
	pg_assert_contains('pg_settings_typed_restorable(', $screen, 'secrets are not printed back');
	pg_assert_contains('JSON_HEX_TAG', $screen, 'the values cannot close the script element');

	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/backend.src.js');

	pg_assert_contains('window.pgSettingsRestore = function (form, data)', $js);

	// A card's drawer (a mailbox, a department) sits outside the form and
	// joins its fields by their form attribute: they are marked through
	// form.elements, and the shut drawer holding the first one opens.
	$start = strpos($js, 'window.pgSettingsRestore = function (form, data)');
	$restore = substr($js, $start, strpos($js, "\n};\n", $start) - $start);

	pg_assert_contains('var controls = Array.prototype.filter.call(form.elements,', $restore, 'marks reach a drawer\'s fields');
	pg_assert_true(strpos($restore, 'form.querySelectorAll(') === false, 'not only the form\'s descendants');
	pg_assert_contains("control.closest('dialog.drawer')", $restore);
	pg_assert_contains('window.pgUI.drawer.show(drawer).then(function () {', $restore, 'the drawer opens, then the field is focused');
	pg_assert_contains("\$(document).on('change input', '[data-pg-set-marked]'", $restore, 'a drawer field loses its mark once edited');
}
