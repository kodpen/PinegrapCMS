<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the back-in-stock form of the catalog item view: the field and
 * the button bound to it, the can_notify flag and its variant switch, the
 * hidden form printed after the widget, the variant script, and what the
 * designer offers and starts with.
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

// The attributes of a node as name => value.
function stock_notify_item_view_test_attrs($node)
{
	$out = array();

	foreach ((isset($node['props']['_attrs']) && is_array($node['props']['_attrs'])) ? $node['props']['_attrs'] : array() as $a) {
		$out[$a['name']] = $a['value'];
	}

	return $out;
}

// A product page block: the address field, a component button, a <button>
// with words, a link bound like a button, and the add-to-cart button.
function stock_notify_item_view_test_tree($field_attrs = array(), $field_flag = '')
{
	$field_bindings = array('value' => 'stock_notify_email');

	if ($field_flag !== '') {
		$field_bindings['eo_visible_if'] = $field_flag;
	}

	return array('type' => 'root', 'props' => array(), 'children' => array(
		array('type' => 'semantic', 'props' => array('tag' => 'div', 'cssClass' => 'input-group'), 'children' => array(
			array('type' => 'semantic', 'props' => array('tag' => 'input', 'cssClass' => 'form-control',
				'_attrs' => array_merge(array(array('name' => 'placeholder', 'value' => 'Your e-mail address'), array('name' => 'type', 'value' => 'text')), $field_attrs),
				'_bindings' => $field_bindings), 'children' => array()),
			array('type' => 'component', 'props' => array('componentType' => 'btn', 'btnElement' => 'a', 'href' => '#',
				'text' => 'Button', '_bindings' => array('action' => 'stock_notify')), 'children' => array()),
			array('type' => 'semantic', 'props' => array('tag' => 'button', 'text' => 'Tell me',
				'_bindings' => array('action' => 'stock_notify')), 'children' => array()),
			array('type' => 'semantic', 'props' => array('tag' => 'a', 'text' => 'Link',
				'_bindings' => array('action' => 'stock_notify')), 'children' => array()),
			array('type' => 'component', 'props' => array('componentType' => 'btn', 'text' => 'Add to Cart',
				'_bindings' => array('action' => 'add_to_cart')), 'children' => array()),
		)),
	));
}

// The controls post the form named after the widget, a field takes the
// signed-in visitor's address, and both take can_notify unless they carry a
// flag of their own.
function test_stock_notify_item_view_binding()
{
	$tree = stock_notify_item_view_test_tree();
	_apply_catalog_item_view_bindings($tree, array('stock_notify_form' => 'pg-stock-notify-1', 'stock_notify_email' => 'ayse@example.com'));

	$group = $tree['children'][0]['children'];

	pg_assert_same(4, count($group), 'the link is dropped, the rest stay');

	$field = stock_notify_item_view_test_attrs($group[0]);
	pg_assert_same('email', $field['type'], 'an address field');
	pg_assert_same('email_address', $field['name'], 'the name stock_notify.php reads');
	pg_assert_same('pg-stock-notify-1', $field['form'], 'belongs to the form after the widget');
	pg_assert_same('ayse@example.com', $field['value'], "the account's address");
	pg_assert_same('100', $field['maxlength'], 'the column');
	pg_assert_same('Your e-mail address', $field['placeholder'], "the designer's own attribute stays");
	pg_assert_same('can_notify', $group[0]['props']['_bindings']['eo_visible_if'], 'shown only while it can be asked for');

	$button = $group[1];
	pg_assert_same('button', $button['props']['btnElement']);
	pg_assert_same('submit', $button['props']['btnType']);
	pg_assert_same('', $button['props']['href']);
	pg_assert_same('pg-stock-notify-1', stock_notify_item_view_test_attrs($button)['form']);
	pg_assert_same(lang('Notify me'), $button['props']['text'], 'a boilerplate caption becomes the action');
	pg_assert_same('can_notify', $button['props']['_bindings']['eo_visible_if']);

	$plain = stock_notify_item_view_test_attrs($group[2]);
	pg_assert_same('submit', $plain['type'], 'a <button> submits');
	pg_assert_same('pg-stock-notify-1', $plain['form']);
	pg_assert_same('Tell me', $group[2]['props']['text'], 'its words stay');

	pg_assert_same('add_to_cart', $group[3]['props']['_bindings']['action'], 'add to cart is untouched');
	pg_assert_false(isset(stock_notify_item_view_test_attrs($group[3])['form']), 'add to cart posts its own form');
}

// A guest's field stays empty; a value or flag the designer set is kept.
function test_stock_notify_item_view_keeps_the_designers_choices()
{
	$tree = stock_notify_item_view_test_tree(array(array('name' => 'value', 'value' => 'shop@example.com')), 'has_rating');
	_apply_catalog_item_view_bindings($tree, array('stock_notify_form' => 'pg-stock-notify-2', 'stock_notify_email' => 'ayse@example.com'));

	$field = $tree['children'][0]['children'][0];
	pg_assert_same('shop@example.com', stock_notify_item_view_test_attrs($field)['value'], 'a value of the design');
	pg_assert_same('has_rating', $field['props']['_bindings']['eo_visible_if'], 'a flag of its own');

	$guest = stock_notify_item_view_test_tree();
	_apply_catalog_item_view_bindings($guest, array('stock_notify_form' => 'pg-stock-notify-3', 'stock_notify_email' => ''));
	pg_assert_false(isset(stock_notify_item_view_test_attrs($guest['children'][0]['children'][0])['value']), 'no address for a guest');
}

// Without the requests' table there is no form: every control goes.
function test_stock_notify_item_view_not_ready_draws_nothing()
{
	$tree = stock_notify_item_view_test_tree();
	_apply_catalog_item_view_bindings($tree, array('stock_notify_form' => ''));

	$group = $tree['children'][0]['children'];
	pg_assert_same(1, count($group), 'only add to cart is left');
	pg_assert_same('add_to_cart', $group[0]['props']['_bindings']['action']);

	$tree = stock_notify_item_view_test_tree();
	_apply_catalog_item_view_bindings($tree, array());
	pg_assert_same(1, count($tree['children'][0]['children']), 'no form in the context');
}

// A select group keeps both can_notify states only when its variants differ
// in stock.
function test_stock_notify_item_view_stock_states_differ()
{
	$out = array('id' => 1, 'out_of_stock' => '1');
	$buyable = array('id' => 2, 'out_of_stock' => '0');

	pg_assert_true(_pg_catalog_stock_states_differ(array($out, $buyable)), 'one out, one buyable');
	pg_assert_true(_pg_catalog_stock_states_differ(array($buyable, $buyable, $out)), 'in any order');
	pg_assert_false(_pg_catalog_stock_states_differ(array($out, $out)), 'all out');
	pg_assert_false(_pg_catalog_stock_states_differ(array($buyable)), 'one variant');
	pg_assert_false(_pg_catalog_stock_states_differ(array()), 'no variants');
	pg_assert_true(_pg_catalog_stock_states_differ(array($out, array('id' => 3))), 'a variant without the field can be bought');
}

// can_notify switched by the variant script: both states kept and marked;
// a flag not switched is still kept or cut.
function test_stock_notify_item_view_flag_switch()
{
	$span = function ($flag, $n, $inner) {
		return '<!--pg-vis:' . $flag . ':' . $n . '-->' . $inner . '<!--/pg-vis:' . $n . '-->';
	};
	$html = $span('can_notify', 1, '<div class="mt-3">Tell me</div>') . $span('has_rating', 2, '<p>4,6</p>');

	pg_assert_same(
		'<div data-pg-variant-flag="can_notify" hidden class="mt-3">Tell me</div>',
		_pg_catalog_variant_flags_keep($html, array('can_notify' => false, 'has_rating' => false), array('can_notify')),
		'kept hidden for a variant that can be bought; the rating cut'
	);
	pg_assert_same(
		'<div data-pg-variant-flag="can_notify" class="mt-3">Tell me</div><p>4,6</p>',
		_pg_catalog_variant_flags_keep($html, array('can_notify' => true, 'has_rating' => true), array('can_notify')),
		'shown for one that is out; the rating kept as it is'
	);
	pg_assert_same(
		'<p data-pg-variant-flag="has_rating">4,6</p>',
		_pg_catalog_variant_flags_keep($html, array('has_rating' => true)),
		'the rating alone by default'
	);
}

// The renderer: the flag from the product's stock, the switch, the form after
// the widget's own, and the readiness of the requests.
function test_stock_notify_item_view_renderer()
{
	$view = pg_test_function_source('_render_system_widget_catalog_item_view');

	pg_assert_contains("_pg_sw_mark_visibility(\$tree_decoded, array('has_rating', 'no_rating', 'can_notify', 'is_bundle', 'has_bundle_saving'));", $view, 'can_notify is a flag of the product page');
	pg_assert_contains("'can_notify' => (\$_civ_notify_form !== '') && \$p && pg_stock_notify_product_out(", $view, 'from the product, while the requests can be kept');
	pg_assert_contains("_pg_catalog_stock_states_differ(\$variant_data['products'])", $view, 'switched only when the variants differ');
	pg_assert_contains("\$_civ_switched[] = 'can_notify';", $view);
	pg_assert_contains("pg_stock_notify_ready() ? 'pg-stock-notify-'", $view, 'no form without the table');
	pg_assert_contains("'stock_notify_form'      => \$_civ_notify_form,", $view, 'the binding pass knows the form');
	pg_assert_contains("'/stock_notify.php') . '\" method=\"post\"", $view, 'the form posts stock_notify.php');
	pg_assert_contains('. get_token_field()', substr($view, (int) strpos($view, '$_civ_notify_html =')), 'with the form token');
	pg_assert_contains('name="send_to"', $view, 'back to the product page');
	pg_assert_contains("strpos(\$_civ_html, 'form=\"' . \$_civ_notify_form . '\"')", $view, 'only when a control is drawn');
	pg_assert_contains("data-pg-stock-notify=\"' . h(\$_civ_notify_form)", $view, 'the widget form names it for the variant script');
	pg_assert_contains("\$_civ_v_v   = '8';", $view, 'the variant script is fetched again');

	$close = strpos($view, ". '</form>'\n         . \$_civ_notify_html");
	pg_assert_true($close !== false, 'printed after the add-to-cart form, never inside it');
}

// The variant script switches can_notify and moves the request's product.
function test_stock_notify_item_view_variant_script()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_civ_variants.js');
	$apply = substr($js, (int) strpos($js, 'function applyVariant('));

	pg_assert_contains("flag === 'can_notify' && ('out_of_stock' in p)", $apply, 'from the chosen variant');
	pg_assert_contains("el.hidden = String(p.out_of_stock) !== '1'", $apply, 'shown only for one out of stock');
	pg_assert_contains("form.getAttribute('data-pg-stock-notify')", $apply, 'finds the request form');
	pg_assert_contains('notifyPid.value = String(p.id)', $apply, 'and gives it the variant');
}

// The designer offers the flag and the two bindings, and its starter carries
// the block.
function test_stock_notify_item_view_designer()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');

	pg_assert_contains("['can_notify', _sdT('The product is out of stock (visitors can ask to be told)')]", $js, 'the flag');
	pg_assert_contains("if (isRealButton) aOpts.push(['stock_notify', _sdT('Notify me when back in stock')]);", $js, 'the button, on a real button only');
	pg_assert_contains("vOpts.push(['stock_notify_email',", $js, 'the field');
	pg_assert_contains("vOpts.push(['stock_notify_phone',", $js, 'the number field');

	$start = strpos($js, "case 'catalog_item_view': {");
	$starter = substr($js, (int) $start, 20000);

	pg_assert_true($start !== false, 'the starter is there');
	pg_assert_contains("_bindings: { eo_visible_if: 'can_notify' }", $starter, 'shown only while it can be asked for');
	pg_assert_contains("_bindings: { value: 'stock_notify_email' }", $starter, 'the field');
	pg_assert_contains("_bindings: { action: 'stock_notify' }", $starter, 'the button');
	pg_assert_contains("_bindings: { value: 'stock_notify_phone' }", $starter, 'the number field');
	pg_assert_contains("_sdT('We will send you one message when it is back in stock.')", $starter, 'one message is promised');
}

// The number field: a tel input of the request form, never required, drawn
// only while the notice can go out as a text message.
function test_stock_notify_item_view_phone_field()
{
	$phone = array('type' => 'semantic', 'props' => array('tag' => 'input',
		'_attrs' => array(array('name' => 'type', 'value' => 'text'), array('name' => 'required', 'value' => 'required')),
		'_bindings' => array('value' => 'stock_notify_phone')), 'children' => array());
	$link = array('type' => 'semantic', 'props' => array('tag' => 'a', '_bindings' => array('value' => 'stock_notify_phone')), 'children' => array());

	$tree = array('type' => 'root', 'props' => array(), 'children' => array($phone, $link));
	_apply_catalog_item_view_bindings($tree, array('stock_notify_form' => 'pg-stock-notify-4', 'stock_notify_sms' => true));

	pg_assert_same(1, count($tree['children']), 'a link cannot be the field');
	$attrs = stock_notify_item_view_test_attrs($tree['children'][0]);
	pg_assert_same('tel', $attrs['type']);
	pg_assert_same('phone', $attrs['name'], 'the name stock_notify.php reads');
	pg_assert_same('tel', $attrs['autocomplete']);
	pg_assert_same('50', $attrs['maxlength'], 'the column');
	pg_assert_same('pg-stock-notify-4', $attrs['form']);
	pg_assert_false(isset($attrs['required']), 'optional');
	pg_assert_same('can_notify', $tree['children'][0]['props']['_bindings']['eo_visible_if']);

	$off = array('type' => 'root', 'props' => array(), 'children' => array($phone));
	_apply_catalog_item_view_bindings($off, array('stock_notify_form' => 'pg-stock-notify-5', 'stock_notify_sms' => false));
	pg_assert_same(0, count($off['children']), 'no text messages, no number field');

	pg_assert_contains("'stock_notify_sms'       => (\$_civ_notify_form !== '') && pg_stock_notify_sms_on()", (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets_catalog.php'), 'the renderer asks');
}
