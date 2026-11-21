<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for Workspace Settings: every row's fields and buttons name
 * their person or department, the hours boxes are filled with the panel's
 * decimal separator, and the Claude setup guide's copy fields have names.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function workspace_settings_names_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

function test_workspace_settings_names_person_rows()
{
	// The rows and the department form are drawn once, for the old screen and
	// the Site Settings dialog alike.
	$source = workspace_settings_names_source('includes/workspace/team_settings.php');

	foreach (array('Job title', 'Hours a day', 'Not in the team', 'Team lead', 'Own department') as $label) {
		pg_assert_true(strpos($source, "aria-label=\"' . h(lang('" . $label . "'))") === false, $label . ': row field named without its person');
		pg_assert_true(strpos($source, "'vars' => array(lang('" . $label . "'), \$person['name'])") !== false, $label . ': row field does not name its person');
	}

	pg_assert_true(strpos(workspace_settings_names_source('includes/workspace/workdays.php'), "function ws_settings_days_html(\$name, \$mask, \$id_prefix, \$label = ''") !== false, 'working days group takes no label');
	pg_assert_true(strpos($source, "'vars' => array(lang('Working days'), \$person['name'])") !== false, 'working days group does not name its person');
}

function test_workspace_settings_names_hours_separator()
{
	$source = workspace_settings_names_source('includes/workspace/workdays.php');

	pg_assert_true(strpos($source, "return rtrim(rtrim(number_format(\$hours, 2, '.', ''), '0'), '.');") === false, 'hours written with a fixed point');
	pg_assert_true(strpos($source, "return str_replace('.', pg_number_separators()['decimal'], \$text);") !== false, 'hours not written with the panel separator');
}

function test_workspace_settings_names_claude_copy_fields()
{
	$guide = workspace_settings_names_source('includes/workspace/claude_setup.php');

	// The set-up drawer's values to copy: each field is named, by its label
	// or by aria-label.
	pg_assert_true(strpos($guide, "ws_claude_setup_code('ws-claude-prompt', lang('The prompt for the routine'), \$prompt)") !== false, 'guide prompt field has no name');
	pg_assert_true(strpos($guide, "ws_claude_setup_value('ws-claude-host', lang('Domain'), \$host)") !== false, 'guide domain field has no name');
	pg_assert_contains('<label class="pg-value-row-name ws-claude-value-name" for="ws-claude-host">' . h(lang('Domain')) . '</label>', ws_claude_setup_value('ws-claude-host', lang('Domain'), 'example.com'), 'a value row names its field');
	pg_assert_contains('aria-label="' . h(lang('The prompt for the routine')) . '"', ws_claude_setup_code('ws-claude-prompt', lang('The prompt for the routine'), 'x'), 'the code box names its field');
	pg_assert_contains('aria-label="' . h(lang('Copy') . ': ' . lang('Domain')) . '"', ws_claude_setup_value('ws-claude-host', lang('Domain'), 'example.com'), 'each Copy says what it copies');
}
