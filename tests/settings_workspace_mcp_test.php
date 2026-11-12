<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The MCP servers card in Assistants and agents of the Site Settings dialog:
 * it came over from Workspace Settings. Pins the section, its list and the
 * old anchor's new place, the dialog's field and button names read back as
 * the old screen's request, the relay that turns a refusal into a warning
 * and keeps a refused form for the next pane, the sign-in handed over
 * instead of a redirect, and the drawers' forms having no form of their own.
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

if (!defined('PG_SETTINGS_ENTRY')) {
	define('PG_SETTINGS_ENTRY', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/settings/registry.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

function settings_workspace_mcp_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

// A stand-in for the settings liveform: what reached it.
class settings_workspace_mcp_test_form
{
	public $said = array();

	public function add_warning($sentence)
	{
		$this->said[] = array('warning', $sentence);
	}

	public function add_notice($sentence)
	{
		$this->said[] = array('notice', $sentence);
	}

	public function add_error($sentence)
	{
		$this->said[] = array('error', $sentence);
	}
}

function test_settings_workspace_mcp_section_list_and_anchor()
{
	$source = settings_workspace_mcp_test_source('includes/settings/registry.php');
	$from = strpos($source, "        'workspace_assistants' => array(");
	$to = strpos($source, "        'workspace_channels' => array(", (int) $from);
	$entry = substr($source, (int) $from, (int) $to - (int) $from);

	pg_assert_contains("'pgset-ws-mcp'               => lang('MCP servers'),", $entry, 'the card is a section');
	pg_assert_contains("'ws_mcp'   => 3,", $entry, 'a tool switch is ws_mcp[id][tool][name]');
	pg_assert_same(array('workspace_assistants', 'pgset-ws-mcp'), pg_settings_legacy_anchors()['ws-mcp'] ?? null, 'the old anchor lands on the card');
}

function test_settings_workspace_mcp_names()
{
	pg_assert_same('name="mcp_alias"', ws_mcp_settings_name('alias', '3'), 'the old screen keeps its names');
	pg_assert_same('name="ws_mcp[3][alias]" form="f"', ws_mcp_settings_name('alias', '3', 'f'), 'the dialog: by the server, joined to the form');
	pg_assert_same('name="ws_mcp[3][tool][search_repos]" form="f"', ws_mcp_settings_name('tool[search_repos]', '3', 'f'), 'a tool keeps its own pair');
	pg_assert_same('name="ws_mcp[0][tool][a&amp;b]" form="f"', ws_mcp_settings_name('tool[a&b]', '0', 'f'), 'escaped');
}

function test_settings_workspace_mcp_dialog_input_reads_as_the_old_screen()
{
	$post = array('ws_mcp' => array(
		'3'     => array('alias' => 'gh', 'url' => 'https://x.test/mcp', 'tool' => array('a' => '1'), 'agent_tool' => ''),
		'probe' => array('alias' => 'docs', 'auth' => 'none'),
	));

	pg_assert_same(array('mcp_alias' => 'gh', 'mcp_url' => 'https://x.test/mcp', 'mcp_tool' => array('a' => '1'), 'mcp_agent_tool' => '', 'mcp_id' => 3),
		ws_mcp_settings_dialog_input($post, '3'), 'a server\'s fields under the old names');
	pg_assert_same(array('mcp_alias' => 'docs', 'mcp_auth' => 'none', 'mcp_id' => 0), ws_mcp_settings_dialog_input($post, 'probe'), 'the short form is a new server');
	pg_assert_same(array('mcp_id' => 7), ws_mcp_settings_dialog_input($post, '7'), 'a button without fields');
	pg_assert_same(array('mcp_id' => 0), ws_mcp_settings_dialog_input(array('ws_mcp' => 'x'), '0'), 'a hand-made string is no form');
}

function test_settings_workspace_mcp_pressed_button()
{
	pg_assert_same(null, ws_mcp_settings_pressed(array('ws_mcp' => array('3' => array('alias' => 'gh')))), 'a plain Save runs nothing');

	$tools = ws_mcp_settings_pressed(array('ws_mcp_tools' => '3', 'ws_mcp' => array('3' => array('tool' => array('a' => '1')))));
	pg_assert_same('mcp_tools', $tools['action'], 'the tools\' Save');
	pg_assert_same(array('mcp_tool' => array('a' => '1'), 'mcp_id' => 3), $tools['input'], 'with that server\'s tools only');

	$declare = ws_mcp_settings_pressed(array('ws_mcp_declare_all' => '3'));
	pg_assert_same('mcp_tools', $declare['action'], 'declare saves the tools');
	pg_assert_same('1', $declare['input']['mcp_declare_all'], 'and declares the Not said ones');

	$add = ws_mcp_settings_pressed(array('ws_mcp_catalogue_add' => 'deepwiki'));
	pg_assert_same(array('action' => 'mcp_catalogue_add', 'input' => array('mcp_catalogue' => 'deepwiki', 'mcp_id' => 0)), $add, 'a tile\'s Add');

	$check = ws_mcp_settings_pressed(array('ws_mcp_probe' => 'check', 'ws_mcp' => array('check' => array('url' => 'https://y.test'))));
	pg_assert_same(array('action' => 'mcp_probe', 'input' => array('mcp_url' => 'https://y.test', 'mcp_id' => 0)), $check, 'Check the address');

	pg_assert_same(0, ws_mcp_settings_pressed(array('ws_mcp_delete' => '-4'))['input']['mcp_id'], 'no negative id');
	pg_assert_same(null, ws_mcp_settings_pressed(array('ws_mcp_refresh' => array('3'))), 'a list is no button');

	foreach (array('save', 'tools', 'declare_all', 'refresh', 'toggle', 'delete', 'connect', 'disconnect', 'connect_me', 'disconnect_me', 'catalogue_add', 'probe') as $button) {
		pg_assert_true(is_array(ws_mcp_settings_pressed(array('ws_mcp_' . $button => '1'))), 'ws_mcp_' . $button . ' is read');
	}
}

function test_settings_workspace_mcp_relay()
{
	$form = new settings_workspace_mcp_test_form();
	$relay = new ws_mcp_settings_relay($form);

	$relay->add_error('Refused.');
	$relay->add_notice('Saved.');
	$relay->assign_field_value('mcp_kept_for', 'new');

	pg_assert_same(array(array('warning', 'Refused.'), array('notice', 'Saved.')), $form->said, 'a refusal after the save is a warning');
	pg_assert_same(array('mcp_kept_for' => 'new'), $relay->kept, 'kept for the next pane');

	$kept = ws_mcp_settings_kept(new ws_mcp_settings_relay(null, array('mcp_kept_for' => '5', 'mcp_alias' => 'gh', 'mcp_kept_problem' => 'Taken.')));
	pg_assert_same('5', $kept['for'], 'read back by the card');
	pg_assert_same('gh', $kept['values']['mcp_alias'], 'with what was typed');
	pg_assert_same('Taken.', $kept['problem'], 'and why');
	pg_assert_same('', ws_mcp_settings_kept(new ws_mcp_settings_relay())['for'], 'nothing kept');
}

function test_settings_workspace_mcp_sign_in_is_handed_over()
{
	pg_assert_same('workspace_settings.php?mcp_connected=4#ws-mcp-4', ws_mcp_settings_return_url(4), 'the old screen comes back to the server');

	ws_mcp_settings_place(array('return' => 'settings_workspace_assistants.php', 'anchor' => '#pgset-ws-mcp', 'hold' => true));
	pg_assert_same('settings_workspace_assistants.php?mcp_connected=4#pgset-ws-mcp', ws_mcp_settings_return_url(4), 'Site Settings comes back to the card');
	pg_assert_same('settings_workspace_assistants.php?mcp_connected=4#pgset-ws-mcp', pg_connect_return_path(ws_mcp_settings_return_url(4)), 'an address connect.php takes back');

	ws_mcp_settings_go('https://service.test/authorize');
	pg_assert_same('https://service.test/authorize', ws_mcp_settings_place()['go'], 'held, not sent');

	ws_mcp_settings_place(array('return' => 'workspace_settings.php', 'anchor' => '', 'hold' => false, 'go' => ''));

	pg_assert_contains("pg_settings_go()", settings_workspace_mcp_test_source('settings_pane.php'), 'the dialog goes there');
	pg_assert_contains("pg_settings_go()", settings_workspace_mcp_test_source('includes/settings/screen.php'), 'the page goes there');
	pg_assert_contains("pg_settings_go(\$ws_mcp_go)", settings_workspace_mcp_test_source('includes/settings/workspace_assistants.save.php'), 'the save hands it over');
}

function test_settings_workspace_mcp_add_form_has_no_form_of_its_own()
{
	// The whole form names the redirect address, which needs a configured
	// site: its source is read instead.
	$source = settings_workspace_mcp_test_source('includes/workspace/mcp.php');
	$from = strpos($source, 'function ws_mcp_settings_form(');
	$form = substr($source, (int) $from, (int) strpos($source, "\nfunction ", (int) $from + 10) - (int) $from);

	pg_assert_same(1, preg_match_all('/name="mcp_/', $form), 'every field named through ws_mcp_settings_name(), but the old form\'s mcp_id');
	pg_assert_contains("\$required = (\$form === null) ? ' required' : '';", $form, 'nothing blocks the pane\'s Save');
	pg_assert_contains("name=\"ws_mcp_save\" value=\"' . \$id . '\" form=\"' . h(\$form) . '\"", $form, 'a named Save');
	pg_assert_contains(": '<div class=\"ws-dept-form\" data-ws-mcp-form>'", $form, 'no form of its own in the dialog');

	$custom = ws_mcp_settings_custom('', array('for' => 'probe', 'values' => array('mcp_url' => 'https://x.test', 'mcp_auth' => 'bearer', 'mcp_alias' => 'x'), 'problem' => '', 'catalogue' => '', 'state' => 'info', 'text' => 'Found.'), '', 'pg_settings_modal_form');
	pg_assert_false(stripos($custom, '<form') !== false, 'Check the address has no form of its own');
	pg_assert_contains('name="ws_mcp_probe" value="check"', $custom, 'Check the address is a named button');
	pg_assert_contains('name="ws_mcp_save" value="probe"', $custom, 'the short form adds');
	pg_assert_contains('name="ws_mcp[probe][token]"', $custom, 'its token');

	$old = ws_mcp_settings_custom('workspace_settings.php', ws_mcp_settings_kept(null), '');
	pg_assert_contains('<form method="post" action="workspace_settings.php"', $old, 'the old screen keeps its form');
	pg_assert_contains('name="mcp_url"', $old, 'and its names');
}
