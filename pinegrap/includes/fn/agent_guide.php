<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The guide an AI agent reads before it works on the site: a registry of
 * topics, each built from code by its own file (agent_guide_<topic>.php), and
 * the text, the topic list, the digest and the size limits every door reads -
 * GET /guide (includes/api/resources/guide.php) and the MCP server's
 * resource, tool and opening instructions.
 *
 * A topic's lines name tools as {tool:route.id}; the engine writes each as
 * the tool's name, and tests/agent_guide_test.php fails on a route id that is
 * no offered route. The guide is English, as tool descriptions are: its
 * reader is a model.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
	exit;
}

// The limits the tests hold the guide to, in bytes: the topic list, one
// topic's text, and the MCP server's opening instructions. A subject that
// needs more than a topic holds is a second topic.
function pg_agent_guide_sizes() {

	return array(
		'list'         => 1536,
		'topic'        => 4096,
		'instructions' => 3072
	);

}

// The topics, in the order they are listed. Each one:
//   title    the heading of its text
//   when     one sentence: when to read it
//   module   the module that must be on ('' for none): store, workspace, erp
//   tools    route id prefixes; beside start (and a topic with none), a topic
//            is listed only to a caller holding a tool that starts with one,
//            and those tools' descriptions end with "Guide: <topic>."
//   file     the builder, includes/fn/agent_guide_<id>.php, which declares
//            pg_agent_guide_<id>_sections(): a list of array('id' =>
//            [a-z_], 'heading' => text, 'lines' => list). A line is text, or
//            array('text' => ..., 'module' => ...) for a line kept only while
//            that module is on. A topic whose file is not there is not listed.
function pg_agent_guide_registry() {

	$topics = array(
		'start' => array(
			'title'  => 'Start here',
			'when'   => 'First: what the site is made of, which tools do which job, the rules of every call.',
			'module' => '',
			'tools'  => array()
		),
		'designer' => array(
			'title'  => 'Visual Page Editor',
			'when'   => 'Before reading or changing a page\'s layout, look or text in a design.',
			'module' => '',
			'tools'  => array('design.')
		),
		'content' => array(
			'title'  => 'Pages, folders and files',
			'when'   => 'Before changing pages\' settings, folders or files.',
			'module' => '',
			'tools'  => array('pages.', 'folders.', 'files.')
		),
		'forms' => array(
			'title'  => 'Forms and collections',
			'when'   => 'Before writing a form submission or a collection record.',
			'module' => '',
			'tools'  => array('forms.')
		),
		'store' => array(
			'title'  => 'Store',
			'when'   => 'Before working on products, orders or the store\'s settings.',
			'module' => 'store',
			'tools'  => array('products.', 'product_groups.', 'inventory.', 'orders.', 'store.')
		),
		'workspace' => array(
			'title'  => 'Workspace',
			'when'   => 'Before writing in channels, tasks or canvases.',
			'module' => 'workspace',
			'tools'  => array('workspace.')
		),
		'erp' => array(
			'title'  => 'Accounting',
			'when'   => 'Before working on accounts, invoices, stock or other accounting records.',
			'module' => 'erp',
			'tools'  => array('erp.')
		),
		'panel' => array(
			'title'  => 'The panel in a browser',
			'when'   => 'When working in the person\'s signed-in browser rather than through tools.',
			'module' => '',
			'tools'  => array()
		)
	);

	foreach ($topics as $id => $topic) {

		$topics[$id]['file'] = PG_FUNCTIONS_DIR . '/includes/fn/agent_guide_' . $id . '.php';

	}

	return $topics;

}

// Whether the site runs a module the registry names.
function pg_agent_guide_module_on($module) {

	switch ($module) {

		case '':
			return true;

		case 'store':
			return defined('ECOMMERCE') && (ECOMMERCE === true);

		case 'workspace':
			return defined('WORKSPACE_ENABLED') && (WORKSPACE_ENABLED == true);

		case 'erp':
			return defined('ERP_ENABLED') && (ERP_ENABLED == true);

	}

	return false;

}

// The modules on, from the list the registry names.
function pg_agent_guide_modules_on() {

	$on = array();

	foreach (array('store', 'workspace', 'erp') as $module) {

		if (pg_agent_guide_module_on($module)) {

			$on[] = $module;

		}

	}

	return $on;

}

// The ids of the topics listed for a caller holding the routes $route_ids,
// on a site running $modules: a topic's builder file is there, its module is
// on, and - for a topic that names tool prefixes - the caller holds one of
// those tools. $registry is pg_agent_guide_registry().
function pg_agent_guide_listed($registry, $route_ids, $modules) {

	$listed = array();

	foreach ($registry as $id => $topic) {

		if (!is_file($topic['file'])) {

			continue;

		}

		if (($topic['module'] !== '') && !in_array($topic['module'], $modules, true)) {

			continue;

		}

		if (!empty($topic['tools']) && !pg_agent_guide_holds($topic['tools'], $route_ids)) {

			continue;

		}

		$listed[] = $id;

	}

	return $listed;

}

// Whether one of $route_ids starts with one of $prefixes.
function pg_agent_guide_holds($prefixes, $route_ids) {

	foreach ($route_ids as $route_id) {

		foreach ($prefixes as $prefix) {

			if (strpos((string)$route_id, $prefix) === 0) {

				return true;

			}

		}

	}

	return false;

}

// The topic whose tools a route belongs to, among the $listed ids, or ''.
// pg_mcp_tool_guidance() ends such a tool's description with "Guide: <id>."
function pg_agent_guide_topic_of_route($registry, $listed, $route_id) {

	foreach ($listed as $id) {

		if (isset($registry[$id]) && !empty($registry[$id]['tools']) && pg_agent_guide_holds($registry[$id]['tools'], array($route_id))) {

			return $id;

		}

	}

	return '';

}

// A topic's sections as its builder declares them, or null when its file is
// not there. Each builder is loaded once, on first use.
function pg_agent_guide_sections($registry, $id) {

	if (!isset($registry[$id]) || !is_file($registry[$id]['file'])) {

		return null;

	}

	require_once($registry[$id]['file']);

	$builder = 'pg_agent_guide_' . $id . '_sections';

	return function_exists($builder) ? call_user_func($builder) : null;

}

// The name a tool goes by over MCP: the route id with its dots as
// underscores, as pg_mcp_tool_name() writes it.
function pg_agent_guide_tool_name($route_id) {

	return substr((string)preg_replace('/[^A-Za-z0-9_\-]/', '_', (string)$route_id), 0, 64);

}

// A line with its {tool:route.id} marks written as tool names.
function pg_agent_guide_line($text) {

	return (string)preg_replace_callback('/\{tool:([a-z0-9_.]+)\}/', function ($found) {

		return pg_agent_guide_tool_name($found[1]);

	}, (string)$text);

}

// The route ids the lines of $sections name, each once.
function pg_agent_guide_named_routes($sections) {

	$named = array();

	foreach ((array)$sections as $section) {

		foreach ($section['lines'] as $line) {

			$text = is_array($line) ? $line['text'] : $line;

			if (preg_match_all('/\{tool:([a-z0-9_.]+)\}/', (string)$text, $found)) {

				foreach ($found[1] as $route_id) {

					$named[$route_id] = true;

				}

			}

		}

	}

	return array_keys($named);

}

// One section as Markdown: its heading with its id, then a line each. A
// line kept for a module that is off is left out.
function pg_agent_guide_section_text($section, $modules) {

	$text = '## ' . $section['heading'] . ' (#' . $section['id'] . ")\n";

	foreach ($section['lines'] as $line) {

		if (is_array($line)) {

			if (!empty($line['module']) && !in_array($line['module'], $modules, true)) {

				continue;

			}

			$line = $line['text'];

		}

		$text .= '- ' . pg_agent_guide_line($line) . "\n";

	}

	return $text;

}

// A topic's whole text, or one section of it ($section_id), or null when the
// topic has no sections or the section is not one of them.
function pg_agent_guide_topic_text($registry, $id, $modules, $section_id = '') {

	$sections = pg_agent_guide_sections($registry, $id);

	if (!is_array($sections)) {

		return null;

	}

	$parts = array();

	foreach ($sections as $section) {

		if (($section_id === '') || ($section['id'] === $section_id)) {

			$parts[] = pg_agent_guide_section_text($section, $modules);

		}

	}

	if (empty($parts)) {

		return null;

	}

	$head = ($section_id === '') ? '# ' . $registry[$id]['title'] . "\n" . $registry[$id]['when'] . "\n\n" : '';

	return $head . implode("\n", $parts);

}

// The topic list as Markdown: a line each with the id to ask for, the title
// and when to read it.
function pg_agent_guide_list_text($registry, $listed) {

	$text = "# Guide topics\nRead start first, then an area's topic before the first work in it.\n\n";

	foreach ($listed as $id) {

		$text .= '- ' . $id . ': ' . $registry[$id]['title'] . '. ' . $registry[$id]['when'] . "\n";

	}

	return $text;

}

// The digest of a text: it changes whenever the text does, so a reader that
// kept an answer knows whether it is still the one.
function pg_agent_guide_digest($text) {

	return substr(hash('sha256', (string)$text), 0, 16);

}

// What a door answers: the list without $topic, a topic, or one section.
// array('status' => 200, 'body' => ...) or array('status' => 404, 'what' =>
// 'topic' or 'section') for a topic that is not listed or a section it does
// not have.
function pg_agent_guide_answer($registry, $listed, $modules, $topic = '', $section = '') {

	if ($topic === '') {

		$text = pg_agent_guide_list_text($registry, $listed);

	} else {

		$text = in_array($topic, $listed, true) ? pg_agent_guide_topic_text($registry, $topic, $modules, $section) : null;

		if ($text === null) {

			return array('status' => 404, 'what' => (($section !== '') && in_array($topic, $listed, true)) ? 'section' : 'topic');

		}

	}

	return array('status' => 200, 'body' => array(
		'version' => defined('VERSION') ? (string)VERSION : '',
		'digest'  => pg_agent_guide_digest($text),
		'topic'   => ($topic === '') ? null : $topic,
		'section' => ($section === '') ? null : $section,
		'topics'  => $listed,
		'text'    => $text
	));

}
