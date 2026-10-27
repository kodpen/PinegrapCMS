<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for filling a flow box's texts in one pass: a {{node.*}} value
 * (ws_step_fill_nodes()) or a scheduled placeholder's value
 * (ws_scheduled_fill()) is never read again as a placeholder, so a value a
 * form visitor typed or a webhook returned cannot pull in other data.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/templates.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flows.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_runs.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/steps.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/scheduled.php');

// Both fillers in the order a box runs them.
function ws_flow_fill_test_both($text, $values, $context)
{
	return ws_scheduled_fill(ws_step_fill_nodes($text, $values), $context);
}

// A box's value holding placeholders stays as written.
function test_workspace_flow_fill_node_value_stays_literal()
{
	$values = array('form' => array('text' => 'Hi {{customer}} {{form_fields}} {{date}} {{node.form.secret}} {{count:tasks_open}}', 'secret' => 'hidden'));
	$filled = ws_flow_fill_test_both('Said: {{node.form.text}}', $values, array('row' => array('name' => 'Flow')));

	pg_assert_true(strpos($filled, 'hidden') === false, 'a node value does not pull in another node value');
	pg_assert_true(strpos($filled, date('d.m.Y')) === false, 'a node value does not pull in the date');
	$j = "\u{2060}";
	pg_assert_same("Said: Hi {{$j}{{$j}customer}} {{$j}{{$j}form_fields}} {{$j}{{$j}date}} {{$j}{{$j}node.form.secret}} {{$j}{{$j}count:tasks_open}}", $filled, 'each brace of a value is followed by a word joiner');

	// A brace at either end of a value cannot meet one of the text.
	$values = array('a' => array('text' => '{', 'end' => 'x{'));
	pg_assert_same(0, preg_match('/\{\{date\}\}/', ws_step_fill_nodes('{{node.a.end}}{date}}', $values)), 'a trailing brace');
	pg_assert_same(0, preg_match('/\{\{date\}\}/', ws_step_fill_nodes('{{{node.a.text}}date}}', $values)), 'a lone brace');
}

// A scheduled placeholder's value holding placeholders stays as written.
function test_workspace_flow_fill_scheduled_value_stays_literal()
{
	$context = array('row' => array('name' => '{{date}}'), 'result' => '{{count:tasks_open}} {{node.x.y}}', 'source' => '{{run}}');

	pg_assert_same('{{date}} / {{count:tasks_open}} {{node.x.y}} / {{run}}', ws_scheduled_fill('{{name}} / {{result}} / {{source}}', $context));
	pg_assert_same('{{count:tasks_open}} {{node.x.y}}', ws_flow_fill_test_both('{{result}}', array('x' => array('y' => 'leak')), $context));
}

// Values without braces fill as before.
function test_workspace_flow_fill_normal_values_unchanged()
{
	$values = array('say' => array('text' => 'written in #sales', 'message_id' => '42'));
	$context = array('row' => array('name' => 'Daily', 'run_count' => 4), 'result' => 'ok');

	pg_assert_same('written in #sales (42) Daily 5 ok ' . date('d.m.Y') . ' ', ws_flow_fill_test_both('{{node.say.text}} ({{node.say.message_id}}) {{name}} {{run}} {{result}} {{date}} {{count:tasks_open}}', $values, $context));
	pg_assert_same('a } b { c', ws_step_fill_nodes('{{node.say.x}}a } b { c', $values));
}

// One value with markup, an ampersand, quotes, a line break and a control
// character, as a form visitor or a webhook may give it.
function ws_flow_fill_test_value()
{
	return array('form' => array('text' => "a<b & \"c\" 'd'\nnext\x07end <@user:5> <#order:7> <!channel> @everyone"));
}

// A message (post, task, report, check-in, approval): the value's tags are
// text, not a mention or a record tag; the renderer escapes the HTML.
function test_workspace_flow_fill_message_value_tags_are_text()
{
	$j = "\u{2060}";
	$values = ws_flow_fill_test_value();
	$action = ws_step_fill_action('post', array('type' => 'post', 'channel_id' => 4, 'body' => '<@user:2> said: {{node.form.text}}'), $values);

	pg_assert_same("<@user:2> said: a<{$j}b & \"c\" 'd'\nnext\x07end <{$j}@user:5> <{$j}#order:7> <{$j}!channel> @everyone", $action['body']);
	pg_assert_same(array(array('sigil' => '@', 'type' => 'user', 'id' => 2)), ws_tokens($action['body']), 'only the tag the builder wrote');

	$action = ws_step_fill_action('task_open', array('type' => 'task', 'title' => 'T {{node.form.text}}', 'description' => '{{node.form.text}}'), $values);

	pg_assert_same("T a<{$j}b & \"c\" 'd' next end <{$j}@user:5> <{$j}#order:7> <{$j}!channel> @everyone", $action['title'], 'a title on one line');
	pg_assert_same(array(), ws_tokens($action['description']));

	foreach (array('report' => array('title'), 'checkin' => array('question'), 'approval' => array('title', 'text')) as $kind => $keys) {
		$action = array('type' => $kind);

		foreach ($keys as $key) {
			$action[$key] = '{{node.form.text}}';
		}

		$action = ws_step_fill_action($kind, $action, $values);

		foreach ($keys as $key) {
			pg_assert_same(array(), ws_tokens($action[$key]), $kind . ' ' . $key);
		}
	}
}

// An e-mail: the subject on one line, the plain text body as given, no
// escaping in the fill (the task e-mail escapes its HTML where it writes it).
function test_workspace_flow_fill_email_value_is_plain_text()
{
	$values = ws_flow_fill_test_value();
	$action = ws_step_fill_action('email', array('type' => 'email', 'to' => array('{{node.form.text}}'), 'subject' => 'Re: {{node.form.text}}', 'body' => '{{node.form.text}}'), $values);

	pg_assert_same(array('{{node.form.text}}'), $action['to'], 'an address is never filled');
	pg_assert_same("Re: a<b & \"c\" 'd' next end <@user:5> <#order:7> <!channel> @everyone", $action['subject']);
	pg_assert_same($values['form']['text'], $action['body']);

	$action = ws_step_fill_action('task_digest', array('type' => 'task_digest', 'subject' => '{{node.form.text}}', 'intro' => '{{node.form.text}}'), $values);

	pg_assert_same($values['form']['text'], $action['intro']);
	pg_assert_same(0, strpos(nl2br(h($action['intro']), false), 'a&lt;b &amp; &quot;c&quot; &#039;d&#039;<br>'), 'escaped once, where the HTML is written');
}

// A notice: one line, as the inbox shows it as text.
function test_workspace_flow_fill_notify_value_is_one_line()
{
	$action = ws_step_fill_action('notify', array('type' => 'notify', 'text' => '{{node.form.text}}'), ws_flow_fill_test_value());

	pg_assert_same("a<b & \"c\" 'd' next end <@user:5> <#order:7> <!channel> @everyone", $action['text']);
}

// A webhook: a JSON string in the generic body; a chat service's mention
// and link markup stays text.
function test_workspace_flow_fill_webhook_value_by_format()
{
	$j = "\u{2060}";
	$values = ws_flow_fill_test_value();
	$row = array('id' => 0, 'name' => 'Flow', 'run_count' => 0);

	$action = ws_step_fill_action('webhook', array('type' => 'webhook', 'format' => 'generic', 'url' => 'https://example.com/{{node.form.text}}', 'text' => 'Got: {{node.form.text}}'), $values);
	$body = json_decode(ws_scheduled_webhook_body($row, $action, $action['text'], array()), true);

	pg_assert_same('https://example.com/{{node.form.text}}', $action['url'], 'an address is never filled');
	pg_assert_same('Got: ' . $values['form']['text'], $body['text'], 'the value as one JSON string');

	$action = ws_step_fill_action('webhook', array('type' => 'webhook', 'format' => 'text', 'text' => '<!here> {{node.form.text}}'), $values);
	$body = json_decode(ws_scheduled_webhook_body($row, $action, $action['text'], array()), true);

	pg_assert_same("<!here> a<{$j}b & \"c\" 'd'\nnext\x07end <{$j}@user:5> <{$j}#order:7> <{$j}!channel> @everyone", $body['text']);

	$action = ws_step_fill_action('webhook', array('type' => 'webhook', 'format' => 'discord', 'text' => '@here {{node.form.text}}'), $values);
	$body = json_decode(ws_scheduled_webhook_body($row, $action, $action['text'], array()), true);

	pg_assert_same("@here a<{$j}b & \"c\" 'd'\nnext\x07end <{$j}@user:5> <{$j}#order:7> <{$j}!channel> @{$j}everyone", $body['content']);
}
