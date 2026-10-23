<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the submitter_name standard field of the form widgets
 * (includes/fn/widgets.php) and for the author lines of the shipped design
 * templates (includes/design_templates/): a public page prints the
 * submission's contact name, never the login username `submitter` holds.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The field reads the contact joined on forms.contact_id and nothing of the
// user table: first and last name, else the nickname, '' without either.
function test_designer_submitter_name_field_reads_the_contact()
{
	$fields = pg_sw_standard_fields();

	pg_assert_true(isset($fields['submitter_name']), 'submitter_name is a standard field');
	pg_assert_same(array('submitter_contact'), $fields['submitter_name']['join'], 'it joins the contact only');
	pg_assert_same('', $fields['submitter_name']['type'], 'a plain text value, escaped like other text');
	pg_assert_same(
		"IFNULL(NULLIF(TRIM(CONCAT_WS(' ', NULLIF(TRIM(submitter_contact.first_name), ''), NULLIF(TRIM(submitter_contact.last_name), ''))), ''),"
		. " IFNULL(TRIM(submitter_contact.nickname), ''))",
		$fields['submitter_name']['sql'],
		'first and last name, else the nickname, else empty'
	);
	pg_assert_false(strpos($fields['submitter_name']['sql'], 'user') !== false, 'no user column is read');

	// The username field itself is unchanged: staff screens and the classic
	// pages keep printing it.
	pg_assert_same('submitter.user_username', $fields['submitter']['sql'], 'submitter stays the username');
}

// The alias is the only input of the SQL builder; anything but a plain
// identifier is dropped from it.
function test_designer_submitter_name_sql_alias_is_an_identifier()
{
	$sql = pg_sw_submitter_name_sql("c`; DROP TABLE forms; --");
	pg_assert_false(strpos($sql, '`') !== false || strpos($sql, ';') !== false, 'no quote or statement break');
	pg_assert_contains('cdroptableforms.first_name', $sql, 'the alias keeps its identifier characters');
}

// A design naming the field joins the contact on the record's contact id
// (never on id 0) and leaves the user table out.
function test_designer_submitter_name_standard_sql_joins_contacts()
{
	$sql = pg_sw_standard_sql(array('submitter_name' => true), 0);

	pg_assert_contains(' AS `submitter_name`', $sql['select'], 'the value is selected under its name');
	pg_assert_same(
		' LEFT JOIN contacts AS submitter_contact ON (forms.contact_id = submitter_contact.id) AND (forms.contact_id > 0)',
		$sql['join'],
		'the contact is the only join'
	);
	pg_assert_false(strpos($sql['select'] . $sql['join'], 'user_username') !== false, 'the username is not read');
	pg_assert_false(preg_match('/\bJOIN\s+user\b/i', $sql['join']) === 1, 'the user table is not joined');

	// A filter on the field reads the same expression, so the join is needed
	// there as well.
	$both = pg_sw_standard_sql(array('submitter' => true, 'submitter_name' => true), 0);
	pg_assert_contains('LEFT JOIN user AS submitter', $both['join'], 'submitter keeps its own join');
	pg_assert_contains('LEFT JOIN contacts AS submitter_contact', $both['join'], 'beside the contact join');
}

// A form that already has its own field named submitter_name keeps printing
// and filtering that field: the widget-only name yields to it. The classic
// registry's names do not (submitter beats a form field of that name).
function test_designer_submitter_name_yields_to_a_form_field()
{
	pg_assert_true(pg_sw_form_field_wins('submitter_name'), 'submitter_name yields');
	pg_assert_true(pg_sw_form_field_wins('Submitter_Name'), 'whatever its case');
	pg_assert_false(pg_sw_form_field_wins('submitter'), 'submitter does not');
	pg_assert_false(pg_sw_form_field_wins('reference_code'), 'nor does a classic name');

	$row = array('submitter_name' => 'Jane Contact', 'submitter' => 'jane.login');
	$custom = array(
		'submitter_name' => array('data' => 'Pen <Name>', 'type' => 'text box', 'wysiwyg' => 0),
		'submitter'      => array('data' => 'Typed', 'type' => 'text box', 'wysiwyg' => 0),
	);
	$html = pg_sw_apply_tokens('[^^submitter_name^^][^^submitter^^]', $row, $custom);
	pg_assert_contains('[Pen &lt;Name&gt;]', $html, 'the form field wins, escaped');
	pg_assert_false(strpos($html, 'Jane Contact') !== false, 'the contact name is not printed over it');
	pg_assert_false(strpos($html, 'Typed') !== false, 'submitter still beats a same-named form field');

	$html = pg_sw_apply_tokens('[^^submitter_name^^]', array('submitter_name' => 'Jane <C>'), array());
	pg_assert_same('[Jane &lt;C&gt;]', $html, 'without such a field the contact name is printed, escaped');
}

// The form decides, not the record: a form with its own field for the name
// (by name or by label) keeps the standard column out of the query, so a
// record that has no value for that field prints nothing rather than the
// contact's name, as before the standard name existed.
function test_designer_submitter_name_yield_is_decided_per_form()
{
	$ids = pg_sw_form_field_identifiers(array(
		array('name' => 'pen-name', 'label' => 'Yazar Adı'),
		array('name' => '', 'label' => 'Submitter Name'),
	));
	pg_assert_true(isset($ids['pen-name']) && isset($ids['penname']), 'the name, with and without hyphens');
	pg_assert_true(isset($ids['yazar adı']) && isset($ids['yazar_adi']), 'the label and its slug');
	pg_assert_true(isset($ids['submitter_name']), 'a label slug can match the standard name');

	$tokens = array('submitter_name' => true, 'submitter' => true, 'title' => true);
	pg_assert_same(array('submitter_name'), pg_sw_yielded_standard_names($tokens, $ids), 'only the yielding name');
	pg_assert_same(array(), pg_sw_yielded_standard_names($tokens, array('submitter' => true)), 'submitter never yields');
	pg_assert_same(array(), pg_sw_yielded_standard_names($tokens, array('title' => true)), 'no such field: nothing yields');
	pg_assert_same(array(), pg_sw_yielded_standard_names(array('title' => true), $ids), 'not named: nothing yields');

	// The renderers pass the yielded names to the SQL builder as skipped.
	$sql = pg_sw_standard_sql($tokens, 0, array_merge(array('reference_code'), pg_sw_yielded_standard_names($tokens, $ids)));
	pg_assert_false(strpos($sql['select'], '`submitter_name`') !== false, 'the contact name is not selected');
	pg_assert_false(strpos($sql['join'], 'submitter_contact') !== false, 'the contact is not joined');
	pg_assert_contains('`submitter`', $sql['select'], 'submitter is still selected');

	// A record of that form with no value for the field: the row has no
	// such column, the record no such value, so the token prints nothing.
	$html = pg_sw_sweep_tokens(pg_sw_apply_tokens('[^^submitter_name^^]', array('submitter' => 'jane.login'), array()));
	pg_assert_same('[]', $html, 'a record without the value prints nothing');
}

// Every shipped template file, as the template loader reads it: id => array.
function _test_dsn_templates()
{
	$out = array();
	$files = glob(PG_FUNCTIONS_DIR . '/includes/design_templates/*.php');
	foreach ($files ? $files : array() as $file) {
		$out[basename($file, '.php')] = _pg_tpl_include($file);
	}
	return $out;
}

// The trees a template page draws: its own, and every widget and shared part
// it places, followed through parts placed inside parts.
function _test_dsn_page_trees($tpl, $page)
{
	$trees = array();
	$queue = array(isset($page['tree']) ? $page['tree'] : array());
	$seen = array();
	while ($queue) {
		$tree = array_shift($queue);
		if (!is_array($tree)) {
			continue;
		}
		$trees[] = $tree;
		foreach (_test_dsn_refs($tree) as $ref) {
			if (isset($seen[$ref[0] . ':' . $ref[1]])) {
				continue;
			}
			$seen[$ref[0] . ':' . $ref[1]] = true;
			$set = ($ref[0] === 'widget') ? 'widgets' : 'shared';
			if (isset($tpl[$set][$ref[1]]['tree'])) {
				$queue[] = $tpl[$set][$ref[1]]['tree'];
			}
		}
	}
	return $trees;
}

// The widget and shared part keys a tree places: list of array(kind, key).
function _test_dsn_refs($node)
{
	$refs = array();
	if (!is_array($node)) {
		return $refs;
	}
	if (isset($node['props']['templateWidget']) && is_string($node['props']['templateWidget'])) {
		$refs[] = array('widget', $node['props']['templateWidget']);
	}
	if (isset($node['props']['templateShared']) && is_string($node['props']['templateShared'])) {
		$refs[] = array('shared', $node['props']['templateShared']);
	}
	foreach ($node as $child) {
		if (is_array($child)) {
			$refs = array_merge($refs, _test_dsn_refs($child));
		}
	}
	return $refs;
}

// Every binding in a tree (any prop) and every ^^token^^ written into a
// string, as a list of token names.
function _test_dsn_tokens($node)
{
	$found = array();
	if (is_string($node)) {
		if (preg_match_all('/\^\^([a-z0-9_]+)\^\^/i', $node, $m)) {
			$found = $m[1];
		}
		return $found;
	}
	if (!is_array($node)) {
		return $found;
	}
	if (isset($node['props']['_bindings']) && is_array($node['props']['_bindings'])) {
		foreach ($node['props']['_bindings'] as $value) {
			if (is_string($value)) {
				$found[] = $value;
			}
		}
	}
	foreach ($node as $child) {
		$found = array_merge($found, _test_dsn_tokens($child));
	}
	return $found;
}

// Is a node bound to $token inside (or on) an element marked pg-hide-if-empty?
function _test_dsn_bound_hidden_if_empty($node, $token, $inside = false)
{
	if (!is_array($node)) {
		return false;
	}
	$class = isset($node['props']['cssClass']) ? (string)$node['props']['cssClass'] : '';
	$inside = $inside || (preg_match('/(^|\s)pg-hide-if-empty(\s|$)/', $class) === 1);
	if ($inside && isset($node['props']['_bindings']['text']) && $node['props']['_bindings']['text'] === $token) {
		return true;
	}
	foreach ($node as $child) {
		if (is_array($child) && _test_dsn_bound_hidden_if_empty($child, $token, $inside)) {
			return true;
		}
	}
	return false;
}

// No page a visitor or a member can open binds a login username: the
// submitter and last modifier fields are left to the staff pages (private
// folder), where the username is what an editor needs.
function test_designer_submitter_name_templates_public_pages_print_no_username()
{
	$tpls = _test_dsn_templates();
	pg_assert_true(count($tpls) >= 7, 'the built-in templates are found');

	foreach ($tpls as $id => $tpl) {
		$access = array();
		foreach ((isset($tpl['folders']) && is_array($tpl['folders'])) ? $tpl['folders'] : array() as $key => $folder) {
			$access[$key] = isset($folder['access']) ? $folder['access'] : '';
		}
		foreach ((isset($tpl['pages']) && is_array($tpl['pages'])) ? $tpl['pages'] : array() as $page) {
			$folder = isset($page['folder']) ? $page['folder'] : '';
			if (isset($access[$folder]) && $access[$folder] === 'private') {
				continue;
			}
			$tokens = array();
			foreach (_test_dsn_page_trees($tpl, $page) as $tree) {
				$tokens = array_merge($tokens, _test_dsn_tokens($tree));
			}
			$tokens = array_map('strtolower', $tokens);
			pg_assert_false(in_array('submitter', $tokens, true), $id . '/' . $page['key'] . ' binds no submitter');
			pg_assert_false(in_array('last_modifier', $tokens, true), $id . '/' . $page['key'] . ' binds no last_modifier');
		}
	}
}

// Each built-in template's blog post prints the author's name, and the line
// leaves when the record has none (a sample signed by the setup account).
function test_designer_submitter_name_templates_blog_post_byline()
{
	$tpls = _test_dsn_templates();
	foreach (array('hello-pinegrap', 'pinegrap-bookshop', 'pinegrap-boutique', 'pinegrap-cafe', 'pinegrap-clinic', 'pinegrap-playground', 'pinegrap-store') as $id) {
		$found = false;
		foreach ((isset($tpls[$id]['pages']) && is_array($tpls[$id]['pages'])) ? $tpls[$id]['pages'] : array() as $page) {
			if (!isset($page['key']) || $page['key'] !== 'blog_post') {
				continue;
			}
			foreach (_test_dsn_page_trees($tpls[$id], $page) as $tree) {
				if (_test_dsn_bound_hidden_if_empty($tree, 'submitter_name')) {
					$found = true;
				}
			}
		}
		pg_assert_true($found, $id . ' blog post binds submitter_name inside pg-hide-if-empty');
	}
}
