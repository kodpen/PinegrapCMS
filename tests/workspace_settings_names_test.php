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
	$source = workspace_settings_names_source('workspace_settings.php');

	foreach (array('Job title', 'Hours a day', 'Not in the team', 'Team lead', 'Own department') as $label) {
		pg_assert_true(strpos($source, "aria-label=\"' . h(lang('" . $label . "'))") === false, $label . ': row field named without its person');
		pg_assert_true(strpos($source, "'vars' => array(lang('" . $label . "'), \$person['name'])") !== false, $label . ': row field does not name its person');
	}

	pg_assert_true(strpos($source, "function ws_settings_days_html(\$name, \$mask, \$id_prefix, \$label = '')") !== false, 'working days group takes no label');
	pg_assert_true(strpos($source, "'vars' => array(lang('Working days'), \$person['name'])") !== false, 'working days group does not name its person');
}

function test_workspace_settings_names_department_buttons()
{
	$source = workspace_settings_names_source('workspace_settings.php');

	pg_assert_true(strpos($source, "'vars' => array(lang('Edit'), \$department['name'])") !== false, 'department Edit button does not name its department');
	pg_assert_true(strpos($source, "'vars' => array(\$archive_label, \$department['name'])") !== false, 'department archive button has no name');
}

function test_workspace_settings_names_hours_separator()
{
	$source = workspace_settings_names_source('workspace_settings.php');

	pg_assert_true(strpos($source, "return rtrim(rtrim(number_format(\$hours, 2, '.', ''), '0'), '.');") === false, 'hours written with a fixed point');
	pg_assert_true(strpos($source, "return str_replace('.', pg_number_separators()['decimal'], \$text);") !== false, 'hours not written with the panel separator');
}

function test_workspace_settings_names_claude_copy_fields()
{
	$guide = workspace_settings_names_source('includes/workspace/claude_setup.php');
	$card = workspace_settings_names_source('includes/workspace/claude.php');

	pg_assert_true(strpos($card, '$copy = function ($id, $value, $rows = 1, $label = \'\')') !== false, 'copy closure takes no label');
	pg_assert_true(strpos($guide, "\$copy('ws-claude-prompt', \$prompt, 8, lang('The prompt for the routine'))") !== false, 'guide prompt field has no name');
	pg_assert_true(strpos($guide, "\$copy('ws-claude-host', \$host, 1, lang('Domain'))") !== false, 'guide domain field has no name');
}
