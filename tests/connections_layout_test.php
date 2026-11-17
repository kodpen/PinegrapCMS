<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Connections screen (connections.php): one list of groups
 * with the attention block on top and the state filter, each provider's
 * panel keeping its accounts and application, and the application fields as
 * form-field boxes.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function connections_layout_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/connections.php');
}

function test_connections_is_one_list_of_groups()
{
	$source = connections_layout_source();

	pg_assert_contains("'heading'             => lang('Connections')", $source, 'the screen is called Connections');
	pg_assert_contains('pg_connections_screen_order($shown', $source, 'the groups are read in the screen\'s order');
	pg_assert_contains('pg_connections_screen_may_see($service[\'place\']', $source, 'a row is shown only to a user who may open its place');
	pg_assert_contains('class="pg-card connections-attention', $source, 'the attention block is on top');
	pg_assert_true(strpos($source, 'connections-attention') < strpos($source, '\' . $groups_html . \''), 'the attention block before the groups');
	pg_assert_contains('class="pg-seg pg-list-seg"', $source, 'the state filter is the segmented control');
	pg_assert_true(strpos($source, 'pg_connect_api(') === false && strpos($source, 'pg_connect_check($account);') < strpos($source, 'Reads'), 'no outside call while the list is drawn: only the Check write calls the provider');
}

function test_connections_provider_drawer_keeps_what_the_screen_did()
{
	$source = connections_layout_source();

	pg_assert_contains('<dialog class="drawer drawer-end connections-drawer" id="connections-\' . h($provider_key) . \'"', $source, 'each provider has its drawer, under the id old links jump to');
	pg_assert_contains('data-drawer="connections-\' . h($provider_key) . \'"', $source, 'the provider row opens its drawer');

	foreach (array('data-action="check"', 'data-action="disconnect"', 'connections-connect', 'name="connections_action" value="save_app"', 'data-action="remove_app"') as $part) {
		pg_assert_contains($part, $source, $part . ' stays on the screen');
	}
}

function test_connections_service_drawer_repeats_no_field()
{
	$source = connections_layout_source();
	$detail = substr($source, (int) strpos($source, 'function connections_service_detail('), 6000);

	pg_assert_contains('<dialog class="drawer drawer-end connections-drawer" id="connections-detail"', $source, 'one drawer for the services');
	pg_assert_contains('<template id="connections-detail-', $detail, 'each service\'s drawer content is drawn on the server');
	pg_assert_true(strpos($detail, '<input') === false && strpos($detail, '<form') === false, 'no field of a setting is repeated in it');
	pg_assert_contains('pg_connections_screen_last_error(', $detail, 'the last error on file');
	pg_assert_contains("lang('Open its settings')", $detail, 'one button opens the setting\'s one place');
}

function test_connections_other_side_leads_to_application_access()
{
	$source = connections_layout_source();

	pg_assert_contains('id="connections-other-side"', $source, 'the other side\'s block');
	pg_assert_contains('href="api_settings.php"', $source, 'it leads to Application Access');
	pg_assert_contains('pg_connections_screen_other_side()', $source, 'with its counts');
	pg_assert_contains('<div class="col-12 lg:col-4 2xl:col-3 order-2">', $source, 'in the side column');
}

function test_connections_application_fields_are_form_field_boxes()
{
	$source = connections_layout_source();
	$form = substr($source, (int) strpos($source, '$app_form = \''), 6000);

	foreach (array('redirect_', 'client_id_', 'client_secret_') as $id) {
		pg_assert_true(preg_match('~<div class="form-field[^"]*">\s*<label class="form-label" for="' . preg_quote($id, '~') . '~', $form) === 1, $id . ' sits in a form-field box');
	}

	pg_assert_true(strpos($form, 'form-control-sm') === false, 'the fields use the regular control size');
	pg_assert_true(preg_match('~</div>\s*<div class="form-text mb-0 connections-copy-status"~', $form) === 1, 'the copy status still follows the input group the script reads');
}
