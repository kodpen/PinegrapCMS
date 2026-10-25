<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a product or a product group made by a person from the
 * Workspace (includes/workspace/quick_edit.php, ws_quick_create_*): the parts
 * that need no database - which kinds are offered, the change a save hands
 * changes.php (off sale unless ticked), what a field of a new record holds
 * when it is left alone (the writer's starting row), the form the drawer's
 * new mode draws from the catalogue - and, from the source, how it is wired:
 * the panel action, the write through ws_change_write(), the applied
 * ws_ai_changes row with no request, the drawer, the palette and the
 * /product card.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/helpers/source_reader.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/changes.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/quick_edit.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/commands.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/palette.php');
require_once(PG_FUNCTIONS_DIR . '/includes/panel/actions.php');

// A kind of the change catalogue as ws_change_types() writes it, its labels
// (lang() calls) given as the code that makes them.
function workspace_quick_create_test_kind($type)
{
	$kind = pg_test_ws_change_types()[$type];

	foreach (array('fields', 'create', 'members') as $list) {
		foreach ((array) ($kind[$list] ?? array()) as $name => $field) {
			$kind[$list][$name][2] = ($field[2] instanceof PgTestSourceExpression) ? $field[2]->code : (string) $field[2];
		}
	}

	return $kind;
}

// The body of a function in a source file, up to the next function.
function workspace_quick_create_test_body($source, $function)
{
	$start = strpos($source, "\nfunction " . $function . '(');

	if ($start === false) {
		return '';
	}

	$end = strpos($source, "\nfunction ", $start + 1);

	return substr($source, $start, ($end === false) ? strlen($source) : $end - $start);
}

// A person makes the catalogue's two kinds: products and product groups.
function test_workspace_quick_create_types()
{
	pg_assert_same(array('product', 'product_group'), ws_quick_create_types(), 'products and product groups');

	foreach (ws_quick_create_types() as $type) {
		pg_assert_true(in_array('create', (array) pg_test_ws_change_types()[$type]['actions'], true), $type . ' takes create in the catalogue');
		pg_assert_same('ecommerce', pg_test_ws_change_types()[$type]['right'], $type . ' needs the e-commerce right, as add_product.php');
	}
}

// What a save hands changes.php: a create of the kind, off sale (switched
// off) unless the person ticked it; a field left empty is no field.
function test_workspace_quick_create_change_starts_off_sale()
{
	$change = ws_quick_create_change('product', array('name' => 'KUPA-01', 'price' => 14990));

	pg_assert_same('product', $change['type'], 'the kind');
	pg_assert_same('create', $change['action'], 'a create');
	pg_assert_same(0, $change['id'], 'no record yet');
	pg_assert_same(false, $change['fields']['enabled'], 'off sale when nothing says otherwise');
	pg_assert_same('KUPA-01', $change['fields']['name'], 'the name kept');
	pg_assert_same(14990, $change['fields']['price'], 'the price kept');

	$ticked = ws_quick_create_change('product_group', array('name' => 'Mutfak', 'enabled' => true));
	pg_assert_same(true, $ticked['fields']['enabled'], 'ticked stays ticked');

	$empty = ws_quick_create_change('product', array('name' => 'X', 'brand' => '', 'group_ids' => array(), 'title' => null, 'price' => 0));
	pg_assert_false(array_key_exists('brand', $empty['fields']), 'an empty text is left out');
	pg_assert_false(array_key_exists('group_ids', $empty['fields']), 'an empty list of records is left out');
	pg_assert_false(array_key_exists('title', $empty['fields']), 'nothing is left out');
	pg_assert_same(0, $empty['fields']['price'], 'a price of nothing is still a price');

	pg_assert_same(array('enabled' => false), ws_quick_create_change('product', 'not a list')['fields'], 'no fields at all');
}

// A field the person leaves alone holds what the writer's starting row puts
// there: a product is taxable and shippable, everything else off, empty or
// none; the drawer sends only what differs from it.
function test_workspace_quick_create_defaults_match_the_writer()
{
	$bool = array('bool', 0, 'Label', 'column');

	pg_assert_same(true, ws_quick_create_default('product', 'taxable', $bool), 'taxable');
	pg_assert_same(true, ws_quick_create_default('product', 'shippable', $bool), 'shippable');
	pg_assert_same(false, ws_quick_create_default('product', 'enabled', $bool), 'off sale');
	pg_assert_same(false, ws_quick_create_default('product', 'free_shipping', $bool), 'no free shipping');
	pg_assert_same(false, ws_quick_create_default('product', 'track_stock', $bool), 'no stock tracking');
	pg_assert_same(false, ws_quick_create_default('product_group', 'enabled', $bool), 'a group switched off');
	pg_assert_same(array(), ws_quick_create_default('product', 'group_ids', array('ids', 'product_group', 'Groups', '')), 'no groups');
	pg_assert_same('', ws_quick_create_default('product', 'price', array('money', 2147483647, 'Price', 'price')), 'no price typed');
	pg_assert_same('', ws_quick_create_default('product', 'name', array('text', 100, 'Name', 'name')), 'no name');

	$writer = workspace_quick_create_test_body((string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/changes.php'), 'ws_change_write_product');

	foreach (array("'enabled' => '0'", "'taxable' => '1'", "'shippable' => '1'", "'free_shipping' => '0'", "'inventory' => '0'") as $starting) {
		pg_assert_contains($starting, $writer, 'the product writer starts with ' . $starting);
	}

	pg_assert_contains("'enabled'           => !empty(\$values['enabled'])", (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/changes.php'), 'a group is switched off unless the values say so');
}

// The drawer's form comes from the catalogue: every create field with its
// limit and default, a list of records with the tag its picker searches; a
// group's products are not picked one by one here.
function test_workspace_quick_create_form_from_the_catalogue()
{
	$form = ws_quick_create_form('product', workspace_quick_create_test_kind('product'));
	$by_name = array();

	foreach ($form as $meta) {
		$by_name[$meta['name']] = $meta;
	}

	foreach (array('name', 'short_description', 'price', 'enabled', 'quantity', 'group_ids') as $name) {
		pg_assert_true(isset($by_name[$name]), 'the product form has ' . $name);
	}

	pg_assert_same(array('max_length' => 100), $by_name['name']['limit'], 'the name\'s limit is the catalogue\'s');
	pg_assert_same(array('max' => 2147483647), $by_name['price']['limit'], 'the price\'s ceiling');
	pg_assert_same('product_group', $by_name['group_ids']['tag'], 'groups are picked among product groups');
	pg_assert_same(array(), $by_name['group_ids']['value'], 'no group to start with');
	pg_assert_same(false, $by_name['enabled']['value'], 'off sale to start with');
	pg_assert_same(true, $by_name['taxable']['value'], 'taxable to start with');

	$group = array();

	foreach (ws_quick_create_form('product_group', workspace_quick_create_test_kind('product_group')) as $meta) {
		$group[$meta['name']] = $meta;
	}

	pg_assert_true(isset($group['parent_id']), 'a group picks its parent');
	pg_assert_same('product_group', $group['parent_id']['tag'], 'among product groups');
	pg_assert_false(isset($group['product_ids']), 'a group\'s products are not picked here');
	pg_assert_same(array('max_length' => 2000), $group['short_description']['limit'], 'the group\'s own limits');

	foreach (ws_quick_create_types() as $type) {
		$names = array_map(function ($meta) {
			return $meta['name'];
		}, ws_quick_create_form($type, workspace_quick_create_test_kind($type)));

		foreach (ws_quick_create_short_list($type) as $name) {
			pg_assert_true(in_array($name, $names, true), $type . ' short list names a field of its form: ' . $name);
		}

		pg_assert_same('name', ws_quick_create_short_list($type)[0], $type . ' starts with its name');
	}
}

// The drawer's new mode and the /product card save through the panel action
// every ws_ action is: the workspace handler, the token checked.
function test_workspace_quick_create_panel_action()
{
	$row = pg_panel_action('ws_record_create');

	pg_assert_true(is_array($row), 'ws_record_create is in the table');
	pg_assert_same('pg_panel_ws', $row['handler'] ?? '', 'answered by the workspace');
	pg_assert_true(!empty($row['token']), 'with the token checked');
	pg_assert_true(!empty($row['exempt']), 'the handler checks the session itself');

	$actions = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/actions.php');

	pg_assert_contains("case 'ws_record_create':", $actions, 'the action is answered');
	pg_assert_contains('$created = ws_quick_create_save(', $actions, 'by the create save');
	pg_assert_contains('ws_quick_create_fields($viewer, $type)', $actions, 'the empty form for id 0');
	pg_assert_contains("'on_sale'   => !empty(\$request['on_sale'])", $actions, 'the /product card\'s tick reaches the command');
}

// A create is written as an applied proposal is: through ws_change_write()
// in the person's name, kept in ws_ai_changes as applied with no request.
function test_workspace_quick_create_writes_through_changes_php()
{
	$save = workspace_quick_create_test_body((string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/quick_edit.php'), 'ws_quick_create_save');

	pg_assert_contains("\$done = ws_change_write(\$viewer, \$type, 'create', null, \$values, false);", $save, 'through the wrapper, as the person');
	pg_assert_contains("ws_change_source(array('kind' => 'person', 'name' => \$person));", $save, 'sentences in the person\'s name');
	pg_assert_contains("\$GLOBALS['ws_change_log_quiet'] = true;", $save, 'the writer\'s own log line kept quiet');
	pg_assert_contains("VALUES ('0', '", $save, 'no request behind it');
	pg_assert_contains("'create', '\" . \$id . \"'", $save, 'a create of the new record');
	pg_assert_contains("'applied'", $save, 'kept as applied');
	pg_assert_contains("array('kind' => 'decision')", $save, 'the decision in the channel');
	pg_assert_contains('UPDATE ws_messages SET locked = 1', $save, 'locked, as every decision about a record');
	pg_assert_false(strpos($save, 'decision_message_id') !== false, 'no decision_message_id: the timeline would call it an assistant\'s');

	$input = workspace_quick_create_test_body((string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/quick_edit.php'), 'ws_quick_create_input');

	pg_assert_contains('ws_changes_input(array($change)', $input, 'typed, bounded and checked by changes.php');
}

// The palette offers a create when every word typed starts a word of its
// line (in either language, Turkish letters folded), from two letters on.
function test_workspace_quick_create_palette_words()
{
	$product = 'Yeni ürün New product';
	$group = 'Yeni ürün grubu New product group';

	pg_assert_true(ws_palette_create_matches('yeni ürün', $product), 'as written');
	pg_assert_true(ws_palette_create_matches('urun', $product), 'without the Turkish letters');
	pg_assert_true(ws_palette_create_matches('PROD', $product), 'the start of a word, in capitals');
	pg_assert_true(ws_palette_create_matches('new gr', $group), 'every word starts one');
	pg_assert_true(ws_palette_create_matches('ÜRÜN', $group), 'Turkish capitals');
	pg_assert_false(ws_palette_create_matches('ürün grubu', $product), 'a group\'s words are not a product\'s');
	pg_assert_false(ws_palette_create_matches('kupa', $product), 'another word');
	pg_assert_false(ws_palette_create_matches('', $product), 'nothing typed');
	pg_assert_false(ws_palette_create_matches('n', $product), 'one letter');
}

// The drawer's new mode, the palette's entry and the /product card.
function test_workspace_quick_create_screen_wiring()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');

	pg_assert_contains("api('ws_record_create', {", $js, 'the drawer saves through the action');
	pg_assert_contains('openRecordNew(type, options);', $js, 'openRecord() opens the new mode for no id');
	pg_assert_contains("api('ws_record_fields', { type: type, id: 0 })", $js, 'and loads the empty form');
	pg_assert_contains('openCreate: function (type, options) {', $js, 'the palette is lent the new mode');
	pg_assert_contains("result.command.type === 'create_preview'", $js, 'the /product card is drawn');

	$palette = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_palette.js');

	pg_assert_contains("case 'create':", $palette, 'a create line in the palette');
	pg_assert_contains('k.openCreate(item.kind)', $palette, 'opens the drawer\'s new mode');

	$search = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/palette.php');

	pg_assert_contains('ws_palette_creates($viewer, $query)', $search, 'the palette asks which creates match');
}
