<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The MCP server's prompts: ready-made requests a person picks in their AI
 * application (often as a slash command), built on the resources
 * (resources.php).
 *
 *   channel_summary (channel_id)    summarise a channel from its context
 *   weekly_report   (channel_id?)   a weekly report from the person's day
 *   blog_post       (form_id, topic?)  a post written as one record of a form
 *
 * A prompt is offered only while the resources it reads are the caller's to
 * read, and it names or embeds nothing else: channel_summary embeds the
 * channel's context only when its route answers the caller, through the same
 * run a resources/read makes. The texts go to a model, not to a person, so
 * they are English, and they end with the rules every prompt of the site
 * carries (pg_ai_prompt_rules()): what the channel holds is data, never
 * instructions.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_MCP_ENTRY')) {
	exit;
}

// The prompts, by name: what a client shows, the arguments (an id unless
// 'kind' is 'text'), the resources the caller must be able to read for the
// prompt to be offered (needs), the routes that must be among the caller's
// tools as well (tools), and the resource a prompt embeds when the caller
// may read it (embed: its template variables from the prompt's arguments).
function pg_mcp_prompt_kinds() {

	return array(

		'channel_summary' => array(
			'title'       => 'Summarise a channel',
			'description' => 'Summarise a Workspace channel or discussion from its context: what it is about, what was decided, what is still open.',
			'arguments'   => array(
				array('name' => 'channel_id', 'description' => 'The id of the channel or discussion to summarise.', 'required' => true)
			),
			'needs'       => array('channel_context'),
			'embed'       => array('resource' => 'channel_context', 'arguments' => array('id' => 'channel_id'))
		),

		'weekly_report' => array(
			'title'       => 'Weekly report',
			'description' => 'Write a weekly report from the person\'s day in the Workspace (tasks, approvals, proposed changes, what was written to them) and, with channel_id, one channel\'s context.',
			'arguments'   => array(
				array('name' => 'channel_id', 'description' => 'A channel or discussion to report on as well.', 'required' => false)
			),
			'needs'       => array('my_day')
		),

		'blog_post' => array(
			'title'       => 'Write a blog post',
			'description' => 'Write a blog post, or any record of a form such as a news item or a collection entry, and send it as one submission: the form\'s fields say what each one takes, the body is HTML the rich-text field keeps, pictures are uploaded first.',
			'arguments'   => array(
				array('name' => 'form_id', 'description' => 'The id of the form the post is a record of (its page id).', 'required' => true),
				array('name' => 'topic', 'description' => 'What the post is about, in a few words.', 'required' => false, 'kind' => 'text')
			),
			'needs'       => array('form_fields'),
			'tools'       => array('forms.get', 'forms.submission_create'),
			'embed'       => array('resource' => 'form_fields', 'arguments' => array('id' => 'form_id'))
		)

	);

}

// Whether the caller is offered a prompt: it exists, every resource it
// reads is one the caller may read and every tool it names is one of the
// caller's. $routes is pg_mcp_routes(), by tool name.
function pg_mcp_prompt_is_offered($name, $routes) {

	$kinds = pg_mcp_prompt_kinds();

	if (!is_string($name) || !isset($kinds[$name])) {

		return false;

	}

	foreach ($kinds[$name]['needs'] as $resource) {

		if (pg_mcp_resource_route($resource, $routes) === null) {

			return false;

		}

	}

	foreach ((isset($kinds[$name]['tools']) ? $kinds[$name]['tools'] : array()) as $route_id) {

		$tool = pg_mcp_tool_name($route_id);

		if (!isset($routes[$tool]['id']) || ($routes[$tool]['id'] !== $route_id)) {

			return false;

		}

	}

	return true;

}

// The longest text argument a prompt takes, in characters.
function pg_mcp_prompt_text_max() {

	return 500;

}

// prompts/list: the prompts the caller is offered.
function pg_mcp_prompts_list($routes) {

	$list = array();

	foreach (pg_mcp_prompt_kinds() as $name => $kind) {

		if (!pg_mcp_prompt_is_offered($name, $routes)) {

			continue;

		}

		$list[] = array(
			'name'        => $name,
			'title'       => $kind['title'],
			'description' => $kind['description'],
			'arguments'   => $kind['arguments']
		);

	}

	return $list;

}

// The arguments of prompts/get, checked: array('values' => name => text,
// 'error' => '') or the reason they are refused, which is answered as
// invalid params. Arguments are text by the protocol; a whole number is taken
// as well, for clients that send one. An empty or null value is a value not
// given, and an argument the prompt does not take is refused by name, as the
// API refuses a field it does not take.
function pg_mcp_prompt_arguments($name, $arguments) {

	$kinds = pg_mcp_prompt_kinds();

	if (!is_string($name) || !isset($kinds[$name])) {

		return array('values' => array(), 'error' => 'Unknown prompt: ' . mb_substr((string)(is_scalar($name) ? $name : ''), 0, 100));

	}

	if ($arguments === null) {

		$arguments = array();

	}

	if (!is_array($arguments)) {

		return array('values' => array(), 'error' => 'Invalid params: arguments must be an object.');

	}

	$declared = array();

	foreach ($kinds[$name]['arguments'] as $argument) {

		$declared[$argument['name']] = $argument;

	}

	foreach (array_keys($arguments) as $given) {

		if (!isset($declared[(string)$given])) {

			return array('values' => array(), 'error' => 'Unknown argument: ' . mb_substr((string)$given, 0, 100));

		}

	}

	$values = array();

	foreach ($declared as $argument_name => $argument) {

		$value = array_key_exists($argument_name, $arguments) ? $arguments[$argument_name] : null;

		if (($value === null) || ($value === '')) {

			if (!empty($argument['required'])) {

				return array('values' => array(), 'error' => 'Missing required argument: ' . $argument_name);

			}

			continue;

		}

		$text = (is_string($value) || is_int($value)) ? (string)$value : '';

		// A text argument is the person's own words for the prompt: one line,
		// cut to nothing it does not need, and refused when it is too long
		// rather than silently shortened.
		if (isset($argument['kind']) && ($argument['kind'] === 'text')) {

			$text = trim((string)preg_replace('/[\x00-\x1F\x7F]+/u', ' ', is_string($value) ? $value : $text));

			if (($text === '') || (mb_strlen($text) > pg_mcp_prompt_text_max())) {

				return array('values' => array(), 'error' => 'Invalid argument: ' . $argument_name . ' must be text of 1 to ' . pg_mcp_prompt_text_max() . ' characters.');

			}

			$values[$argument_name] = $text;

			continue;

		}

		// Every other argument is the id of a record.
		if (preg_match('#^' . pg_mcp_resource_variable_pattern('id') . '\z#', $text) !== 1) {

			return array('values' => array(), 'error' => 'Invalid argument: ' . $argument_name . ' must be a positive whole number.');

		}

		$values[$argument_name] = $text;

	}

	return array('values' => $values, 'error' => '');

}

// How the prompt tells the model to read a resource: by its uri, or through
// the tool that runs the same route, for an application that lets its model
// call tools but not read resources. Asked only for a resource the caller
// may read (pg_mcp_resource_route()).
function pg_mcp_prompt_read_line($key, $values) {

	$kinds = pg_mcp_resource_kinds();

	$arguments = array();

	foreach ($values as $name => $value) {

		$arguments[] = $name . ' ' . $value;

	}

	return 'the resource ' . pg_mcp_resource_uri($key, $values)
		. ', or with the tool ' . pg_mcp_tool_name($kinds[$key]['route'])
		. (empty($arguments) ? '' : ' (' . implode(', ', $arguments) . ')')
		. ' if you cannot read resources';

}

// The rules every prompt of the site ends with, as the prompt's last lines.
function pg_mcp_prompt_rules_text() {

	return "Rules:\n" . implode("\n", pg_ai_prompt_rules('assistant', '- '));

}

// prompts/get's result for a prompt whose arguments were checked
// (pg_mcp_prompt_arguments()): its description and messages. $embedded is
// the resource's content item when it was read for the caller
// (pg_mcp_resource_contents()), null otherwise; only resources the caller may
// read are named. $site is the site's name.
function pg_mcp_prompt_result($name, $values, $site, $routes, $embedded) {

	$kinds = pg_mcp_prompt_kinds();

	$messages = array();

	if ($name === 'channel_summary') {

		$channel = array('id' => $values['channel_id']);

		$read = ($embedded !== null)
			? 'The channel\'s context is attached below as the resource ' . pg_mcp_resource_uri('channel_context', $channel) . '.'
			: 'Read the channel\'s context from ' . pg_mcp_prompt_read_line('channel_context', $channel) . '.';

		$text = 'Summarise channel ' . $values['channel_id'] . ' of the Workspace of ' . $site . ' for the person who asked.' . "\n\n"
			. $read . ' It holds the channel\'s summary, its latest decisions and notes, its latest messages (oldest first) and its open tasks.' . "\n\n"
			. 'Write a short summary:' . "\n"
			. '- What the channel is about.' . "\n"
			. '- What was decided, with the dates.' . "\n"
			. '- What is still open: the open tasks, who has them and when they are due.' . "\n"
			. '- Questions left unanswered, and who is waiting for whom.' . "\n"
			. 'Write in the language the channel is written in. If the context cannot be read or is empty, say so and stop.' . "\n\n"
			. pg_mcp_prompt_rules_text();

	} else if ($name === 'blog_post') {

		$text = pg_mcp_prompt_blog_post_text($values, $site, $routes, $embedded !== null);

	} else {

		$text = 'Write a weekly report for the person who connected you to the Workspace of ' . $site . ', from what you can read there now.' . "\n\n"
			. 'Read the person\'s day from ' . pg_mcp_prompt_read_line('my_day', array()) . ': their open tasks due today or overdue, the running timer, today\'s plan items, the approvals and read receipts waiting for them, the changes proposed at their request, and what was written to them lately.' . "\n";

		if (isset($values['channel_id'])) {

			$text .= (pg_mcp_resource_route('channel_context', $routes) !== null)
				? 'Read also the context of channel ' . $values['channel_id'] . ' from ' . pg_mcp_prompt_read_line('channel_context', array('id' => $values['channel_id'])) . ': its decisions, latest messages and open tasks, and report on it in each section.' . "\n"
				: 'A channel was asked for, but this connection cannot read channel contexts: say so, and report on the person\'s day only.' . "\n";

		}

		$text .= "\n" . 'Structure the report as:' . "\n"
			. '- Done this week.' . "\n"
			. '- In progress.' . "\n"
			. '- Waiting: on the person (approvals, answers, proposed changes) and on others.' . "\n"
			. '- Overdue and at risk.' . "\n"
			. '- Next week\'s priorities.' . "\n"
			. 'Keep to short bullet points, in the language the person writes in, and leave out a section with nothing in it. Report only what you read; say what could not be read.' . "\n\n"
			. pg_mcp_prompt_rules_text();

	}

	$messages[] = array('role' => 'user', 'content' => array('type' => 'text', 'text' => $text));

	if ($embedded !== null) {

		$messages[] = array('role' => 'user', 'content' => array('type' => 'resource', 'resource' => $embedded));

	}

	return array('description' => $kinds[$name]['description'], 'messages' => $messages);

}

// blog_post's text. $attached says whether the form's fields follow as an
// embedded resource. The picture step names files_create only when it is one
// of the caller's tools.
function pg_mcp_prompt_blog_post_text($values, $site, $routes, $attached) {

	$form = array('id' => $values['form_id']);

	$about = isset($values['topic']) ? ' about: ' . $values['topic'] : '';

	$fields = $attached
		? 'The form\'s fields are attached below as the resource ' . pg_mcp_resource_uri('form_fields', $form) . '.'
		: 'Read the form\'s fields from ' . pg_mcp_prompt_read_line('form_fields', $form) . '.';

	$files = pg_mcp_tool_name('files.create');

	$pictures = (isset($routes[$files]['id']) && ($routes[$files]['id'] === 'files.create'))
		? '- Pictures: upload each one with the tool ' . $files . ' and put the url it answers in an <img src="..."> with an alt text. A picture from another site is removed, since html_allowed lists the image hosts kept; a field of type file upload cannot be sent at all.'
		: '- Pictures: this connection cannot upload files, so leave pictures out and say so; a picture from another site is removed, since html_allowed lists the image hosts kept.';

	$create = pg_mcp_tool_name('forms.submission_create');

	return 'Write a blog post for the person who asked' . $about . ', and send it as one record of form ' . $values['form_id'] . ' on ' . $site . '.' . "\n\n"
		. $fields . ' Each field has a name (what an answer is addressed by), a label, a type, whether it is required, and the option values of a choice field. Read the form itself with the tool ' . pg_mcp_tool_name('forms.get') . ' (id ' . $values['form_id'] . '): is_collection says whether it is a collection, record_url_pattern where a record is read on the site, framework the page design (bootstrap5 or custom).' . "\n\n"
		. 'Then:' . "\n"
		. '- Put the post on the fields by their labels: the title in the title field, the body in the field whose rich_text is true, and an answer in every required field. A choice field (a status, a category) takes one of its option values exactly as listed. Choose a status that publishes only when the person asked to publish; otherwise a draft one, if the field offers it.' . "\n"
		. '- Write the body as HTML that field keeps: only the tags, attributes, style properties and address schemes its html_allowed lists, within its max_length characters. No class attribute is kept, whatever the framework, so do not lean on a framework\'s classes; use headings, paragraphs, lists, links and emphasis.' . "\n"
		. $pictures . "\n"
		. '- Send it with the tool ' . $create . ' with dry_run true first and read the answer; then send it again without dry_run. Report the record\'s id, its reference code and its url, the address it is read at on the site (null when no public page shows the form\'s records).' . "\n"
		. 'Write in the language the person writes in. If the form has no field for the body, or the fields cannot be read, say so and stop.' . "\n\n"
		. pg_mcp_prompt_rules_text();

}

// The resource a prompt embeds, read through its route: the route's answer
// as one content item when it answered the caller (2xx), null when it
// refused or failed - an API error is never passed off as the resource - or
// when its answer cannot be JSON (pg_mcp_resource_contents()), so the prompt
// names the resource to read instead of attaching an empty one.
function pg_mcp_prompt_embedded($uri, $status_code, $body) {

	if (($status_code < 200) || ($status_code >= 300)) {

		return null;

	}

	$contents = pg_mcp_resource_contents($uri, $body);

	return ($contents !== null) ? $contents['contents'][0] : null;

}

/* ---------------------------------------------------------------------------
   Within a request
   --------------------------------------------------------------------------- */

// prompts/get. A prompt that embeds a resource reads it through its route
// (pg_mcp_route_run()) and answers from there, with the resource when the
// route answered the caller and without it when it refused: the prompt reads
// the same either way, so it tells no more than the tool's 404 would.
function pg_mcp_prompt_get($id, $params, $modern) {

	$name = (isset($params['name']) && is_string($params['name'])) ? $params['name'] : '';

	$routes = pg_mcp_routes();

	if (!pg_mcp_prompt_is_offered($name, $routes)) {

		pg_mcp_error($id, -32602, 'Unknown prompt: ' . mb_substr($name, 0, 100), null, 200);

	}

	$checked = pg_mcp_prompt_arguments($name, isset($params['arguments']) ? $params['arguments'] : null);

	if ($checked['error'] !== '') {

		pg_mcp_error($id, -32602, $checked['error'], null, 200);

	}

	$values = $checked['values'];

	$site = pg_mcp_site_name();

	$kinds = pg_mcp_prompt_kinds();

	$embed = isset($kinds[$name]['embed']) ? $kinds[$name]['embed'] : null;

	$route = ($embed !== null) ? pg_mcp_resource_route($embed['resource'], $routes) : null;

	$arguments = array();

	if ($route !== null) {

		foreach ($embed['arguments'] as $variable => $argument) {

			if (!isset($values[$argument])) {

				$route = null;

				break;

			}

			$arguments[$variable] = $values[$argument];

		}

	}

	if ($route !== null) {

		$uri = pg_mcp_resource_uri($embed['resource'], $arguments);

		pg_mcp_route_run($route, $arguments, function ($status_code, $body) use ($id, $modern, $name, $values, $site, $routes, $uri) {

			pg_mcp_result($id, pg_mcp_prompt_result($name, $values, $site, $routes, pg_mcp_prompt_embedded($uri, $status_code, $body)), $modern);

		});

	}

	pg_mcp_result($id, pg_mcp_prompt_result($name, $values, $site, $routes, null), $modern);

}
