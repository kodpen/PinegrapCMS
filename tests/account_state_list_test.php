<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the state pick list of the account profile and address book
 * widgets: the stored state chosen by code or name (pg_sa_state_pick()),
 * the pick list fitted into a saved tree with only the text box
 * (pg_sa_state_list_fit()), the starters and the renderers' wiring.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function account_state_list_test_control($tag, $name, $id = '')
{
	$props = array('tag' => $tag, '_attrs' => array(array('name' => 'name', 'value' => $name)));
	if ($id !== '') $props['id'] = $id;
	return array('type' => 'semantic', 'props' => $props, 'children' => array());
}

function account_state_list_test_source($relative)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);
}

function test_account_state_list_pick()
{
	$tr = array(array('06', 'Ankara'), array('34', 'İstanbul'));
	pg_assert_same('34', pg_sa_state_pick('34', $tr), 'by code');
	pg_assert_same('34', pg_sa_state_pick(' İstanbul ', $tr), 'by name, spacing ignored');
	pg_assert_same('34', pg_sa_state_pick('istanbul', array(array('34', 'Istanbul'))), 'case ignored');
	pg_assert_same('', pg_sa_state_pick('Bavaria', $tr), 'no match');
	pg_assert_same('', pg_sa_state_pick('', $tr), 'empty');
}

// A stored state typed as a name is chosen in the list.
function test_account_state_list_controls_choose_name()
{
	$tree = array('type' => 'semantic', 'props' => array('tag' => 'div'), 'children' => array(
		account_state_list_test_control('input', 'business_state'),
		account_state_list_test_control('select', 'business_state'),
	));
	_pg_sa_state_controls($tree, 'Ankara', array(array('06', 'Ankara'), array('34', 'İstanbul')), 'business_state');
	$picked = '';
	foreach ($tree['children'][1]['children'] as $o) {
		foreach ($o['props']['_attrs'] as $a) {
			if ($a['name'] === 'selected') $picked = $o['props']['text'];
		}
	}
	pg_assert_same('Ankara', $picked);
}

function test_account_state_list_fit()
{
	$wrap = array('type' => 'semantic', 'props' => array('tag' => 'div'), 'children' => array(
		array('type' => 'semantic', 'props' => array('tag' => 'label', 'text' => 'State'), 'children' => array()),
		account_state_list_test_control('input', 'state', 'f_ab_state'),
	));
	$tree = array('type' => 'semantic', 'props' => array('tag' => 'div'), 'children' => array($wrap));
	pg_assert_true(pg_sa_state_list_fit($tree, 'state'), 'added');
	$kids = $tree['children'][0]['children'];
	pg_assert_same(3, count($kids), 'beside the text box');
	pg_assert_same('select', $kids[2]['props']['tag']);
	pg_assert_same('state', _pg_member_control_name($kids[2]));
	pg_assert_same('f_ab_state_list', $kids[2]['props']['id']);

	pg_assert_false(pg_sa_state_list_fit($tree, 'state'), 'a tree with the list stays');
	pg_assert_same(3, count($tree['children'][0]['children']));
	pg_assert_false(pg_sa_state_list_fit($tree, 'business_state'), 'no text box of that name');
}

function test_account_state_list_wiring()
{
	$php = account_state_list_test_source('includes/fn/widgets_account.php');
	pg_assert_contains("pg_sa_state_list_fit(\$tree, 'business_state');", $php);
	pg_assert_contains("pg_sa_state_list_fit(\$tree, 'state');", $php);
	pg_assert_contains("pg_sa_state_script(\$form_id, \$place['states'], \$place['zip'], 'business_')", $php);
	pg_assert_contains("pg_sa_state_script(\$form_id, \$place['states'], \$place['zip'])", $php);
	$js = account_state_list_test_source('assets/js/style_designer.src.js');
	pg_assert_contains("_swAcctStateField(apId, 'business_state')", $js);
	pg_assert_contains("_swAcctStateField(abId, 'state')", $js);
}
