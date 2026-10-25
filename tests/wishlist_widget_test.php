<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Favourites system widget (includes/fn/widgets_wishlist.php):
 * the tokens and flags of a saved product, which products can go to the
 * cart, the widget drawn from a tree and the rows read for it (the remove
 * and add to cart forms, the empty and signed-out states), the renderer's
 * order of calls, the places the kind is registered in, the My Account link
 * and the Favourites pages of the shop templates.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The source of one function, for the checks that read how it is written.
function wishlist_widget_test_function_source($name)
{
	$function = new ReflectionFunction($name);
	$lines = file($function->getFileName());

	return implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1));
}

// The text of style_designer.js from $from to the next $to.
function wishlist_widget_test_js_between($from, $to)
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');
	$start = strpos($js, $from);

	if ($start === false) {
		return '';
	}

	$end = strpos($js, $to, $start + strlen($from));

	return ($end === false) ? '' : substr($js, $start, $end - $start);
}

// A saved product as the renderer reads it.
function wishlist_widget_test_row($id, $more = array())
{
	return array_merge(array(
		'id'                 => $id,
		'name'               => 'SKU-' . $id,
		'short_description'  => 'Product ' . $id,
		'address_name'       => 'product-' . $id,
		'image_name'         => 'product-' . $id . '.jpg',
		'price'              => 2000,
		'inventory'          => 0,
		'inventory_quantity' => 0,
		'backorder'          => 0,
		'added_at'           => 1790000000,
	), $more);
}

// What the renderer hands the drawing.
function wishlist_widget_test_ctx($more = array())
{
	return array_merge(array(
		'signed_in'       => true,
		'self'            => '/favourites',
		'login_url'       => '/login?send_to=%2Ffavourites',
		'shop_url'        => '/shop',
		'wishlist_url'    => '/software/wishlist.php',
		'cart_url'        => '/software/catalog_detail.php',
		'token_field'     => '<input type="hidden" name="token" value="t0k3n">',
		'base_path'       => '/',
		'detail_prefix'   => '/product/',
		'currency_symbol' => '$',
		'discounted'      => array(),
		'date_format'     => 'Y-m-d',
	), $more);
}

function wishlist_widget_test_node($type, $props, $children = array())
{
	return array('type' => $type, 'props' => $props, 'children' => $children);
}

// A layout like the starter: the signed-out note, the empty state, and a
// card per saved product with a link button, an add to cart component
// button, a remove <button> and an out-of-stock badge.
function wishlist_widget_test_tree($more_in_card = array())
{
	$n = 'wishlist_widget_test_node';

	$card = array_merge(array(
		$n('content', array('contentType' => 'image', 'src' => '', 'alt' => '', 'fluid' => true, 'cssClass' => 'card-img-top',
			'_bindings' => array('src' => '__image_url', 'alt' => '__short_description'))),
		$n('content', array('contentType' => 'heading', 'tag' => 'h5', 'text' => 'Product name', '_bindings' => array('text' => '__short_description'))),
		$n('content', array('contentType' => 'paragraph', 'text' => '$0', '_bindings' => array('text' => '__price_formatted'))),
		$n('semantic', array('tag' => 'span', 'cssClass' => 'badge oos-badge', 'text' => 'Out of stock',
			'_bindings' => array('text' => '__out_of_stock_label', 'eo_visible_if' => 'item_out_of_stock'))),
		$n('content', array('contentType' => 'link', 'text' => 'View', 'href' => '#', '_bindings' => array('href' => '__detail_url'))),
		$n('component', array('componentType' => 'btn', 'btnElement' => 'a', 'btnType' => 'button', 'variant' => 'primary', 'size' => 'sm',
			'text' => 'Add to Cart', '_bindings' => array('action' => 'wishlist_add_to_cart'))),
		$n('semantic', array('tag' => 'button', 'cssClass' => 'btn btn-outline-secondary btn-sm', 'text' => 'Remove',
			'_attrs' => array(array('name' => 'type', 'value' => 'button')), '_bindings' => array('action' => 'wishlist_remove'))),
	), $more_in_card);

	return $n('root', array(), array(
		$n('container', array('fluid' => false, 'cssClass' => 'py-4'), array(
			$n('semantic', array('tag' => 'div', 'cssClass' => 'signed-out-note', '_bindings' => array('eo_visible_if' => 'is_signed_out')), array(
				$n('content', array('contentType' => 'paragraph', 'text' => 'Sign in to keep your list on every device.')),
				$n('content', array('contentType' => 'link', 'text' => 'Sign in', 'href' => '#', '_bindings' => array('href' => '__login_url'))),
			)),
			$n('semantic', array('tag' => 'div', 'cssClass' => 'empty-state', '_bindings' => array('eo_visible_if' => 'no_items')), array(
				$n('content', array('contentType' => 'paragraph', 'text' => 'You have not saved any products yet.')),
				$n('content', array('contentType' => 'link', 'text' => 'Go to the shop', 'href' => '#', '_bindings' => array('href' => '__shop_url'))),
			)),
			$n('content', array('contentType' => 'paragraph', 'text' => '0', 'cssClass' => 'count', '_bindings' => array('text' => '__wishlist_count'))),
			$n('row', array('gutter' => '4', '_bindings' => array('eo_visible_if' => 'has_items')), array(
				$n('loop_area', array(), array(
					$n('col', array('xs' => '12', 'md' => '4'), array(
						$n('semantic', array('tag' => 'div', 'cssClass' => 'card h-100'), $card),
					)),
				)),
			)),
		)),
	));
}

// -- one saved product -----------------------------------------------------

// A product's tokens carry the catalog's names and meanings: its id, names,
// the detail page address, the picture, the price as the visitor pays it.
function test_wishlist_widget_row_values_basic()
{
	$v = pg_wishlist_widget_row_values(wishlist_widget_test_row(11), wishlist_widget_test_ctx());

	pg_assert_same('11', $v['__id'], 'id');
	pg_assert_same('SKU-11', $v['__name'], 'name');
	pg_assert_same('Product 11', $v['__short_description'], 'short description');
	pg_assert_same('/product/product-11', $v['__detail_url'], 'detail page address');
	pg_assert_same('/product-11.jpg', $v['__image_url'], 'picture');
	pg_assert_same('SKU-11', $v['__image_alt'], 'picture text, as the catalog writes it');
	pg_assert_same('20.00', $v['__price'], 'price as a number');
	pg_assert_same(pg_visitor_money(20, '$'), $v['__price_formatted'], 'price as the visitor reads it');
	pg_assert_same('', $v['__original_price_formatted'], 'no struck price without an offer');
	pg_assert_same('', $v['__discount_percent'], 'no discount');
	pg_assert_same('', $v['__out_of_stock_label'], 'in stock: no label');
	pg_assert_same(date('Y-m-d', 1790000000), $v['__added_date'], 'saved on');
}

// An offer: the price block strikes the sticker price, as on a catalog card.
function test_wishlist_widget_row_values_discount()
{
	$v = pg_wishlist_widget_row_values(wishlist_widget_test_row(11), wishlist_widget_test_ctx(array('discounted' => array(11 => 1500.4))));

	pg_assert_same('15.00', $v['__price'], 'the offer price, rounded');
	pg_assert_same(_pg_format_price_with_discount(2000, 1500, '$'), $v['__price_formatted'], 'strike-through block');
	pg_assert_same(pg_visitor_money(20, '$'), $v['__original_price_formatted'], 'the sticker price');
	pg_assert_same('25', $v['__discount_percent'], 'percent saved');
}

// No picture, no detail page, no date: empty where the catalog leaves them
// empty, '#' for the address as the catalog writes it.
function test_wishlist_widget_row_values_missing()
{
	$v = pg_wishlist_widget_row_values(
		wishlist_widget_test_row(11, array('image_name' => '', 'added_at' => 0)),
		wishlist_widget_test_ctx(array('detail_prefix' => '')));

	pg_assert_same('', $v['__image_url'], 'no picture');
	pg_assert_same('#', $v['__detail_url'], 'no detail page');
	pg_assert_same('', $v['__added_date'], 'no date');
}

// Out of stock: tracked, none left, no backorder. Backorder keeps it
// orderable; an untracked product always is.
function test_wishlist_widget_can_add()
{
	pg_assert_true(pg_wishlist_widget_can_add(wishlist_widget_test_row(1)), 'stock not tracked');
	pg_assert_true(pg_wishlist_widget_can_add(wishlist_widget_test_row(1, array('inventory' => 1, 'inventory_quantity' => 3))), 'in stock');
	pg_assert_true(pg_wishlist_widget_can_add(wishlist_widget_test_row(1, array('inventory' => 1, 'inventory_quantity' => 0, 'backorder' => 1))), 'backorder');
	pg_assert_false(pg_wishlist_widget_can_add(wishlist_widget_test_row(1, array('inventory' => 1, 'inventory_quantity' => 0))), 'none left');
	pg_assert_false(pg_wishlist_widget_can_add(wishlist_widget_test_row(1, array('inventory' => '1', 'inventory_quantity' => '-2', 'backorder' => '0'))), 'below zero, read from the database');

	$v = pg_wishlist_widget_row_values(wishlist_widget_test_row(1, array('inventory' => 1, 'inventory_quantity' => 0)), wishlist_widget_test_ctx());
	pg_assert_same(lang('Out of stock'), $v['__out_of_stock_label'], 'the label');

	$v = pg_wishlist_widget_row_values(wishlist_widget_test_row(1, array('inventory' => 1, 'inventory_quantity' => 0, 'backorder' => 1)), wishlist_widget_test_ctx());
	pg_assert_same('', $v['__out_of_stock_label'], 'backorder: no label');
}

// The widget's flags and a row's.
function test_wishlist_widget_flags()
{
	pg_assert_same(array('has_items' => true, 'no_items' => false, 'is_signed_in' => true, 'is_signed_out' => false),
		pg_wishlist_widget_flags(3, true), 'a member with a list');
	pg_assert_same(array('has_items' => false, 'no_items' => true, 'is_signed_in' => false, 'is_signed_out' => true),
		pg_wishlist_widget_flags(0, false), 'a visitor without one');

	pg_assert_same(array('item_in_stock' => true, 'item_out_of_stock' => false, 'item_has_discount' => false),
		pg_wishlist_widget_row_flags(wishlist_widget_test_row(1), wishlist_widget_test_ctx()), 'in stock');
	pg_assert_same(array('item_in_stock' => false, 'item_out_of_stock' => true, 'item_has_discount' => true),
		pg_wishlist_widget_row_flags(wishlist_widget_test_row(1, array('inventory' => 1)), wishlist_widget_test_ctx(array('discounted' => array(1 => 100)))),
		'out of stock, on offer');
}

// -- the widget drawn ------------------------------------------------------

// Each saved product is a card; its remove button posts the list's form to
// wishlist.php with the token, the product and the way back.
function test_wishlist_widget_html_remove_forms()
{
	$html = pg_wishlist_widget_html(wishlist_widget_test_tree(), 9,
		array(wishlist_widget_test_row(11), wishlist_widget_test_row(12, array('inventory' => 1))), wishlist_widget_test_ctx());

	pg_assert_contains('Product 11', $html, 'first card');
	pg_assert_contains('Product 12', $html, 'second card');
	pg_assert_true(strpos($html, 'Product 11') < strpos($html, 'Product 12'), 'in list order');

	foreach (array(11, 12) as $pid) {
		$id = 'pgWlRemove_pgw9r' . $pid;
		$form = wishlist_widget_test_form($html, $id);
		pg_assert_contains('method="post"', $form, $pid . ' posts');
		pg_assert_contains('action="/software/wishlist.php"', $form, $pid . ' to the endpoint');
		pg_assert_contains('<input type="hidden" name="token" value="t0k3n">', $form, $pid . ' token');
		pg_assert_contains('<input type="hidden" name="action" value="remove">', $form, $pid . ' action');
		pg_assert_contains('<input type="hidden" name="product_id" value="' . $pid . '">', $form, $pid . ' product');
		pg_assert_contains('<input type="hidden" name="send_to" value="/favourites">', $form, $pid . ' back here');
		pg_assert_same(1, preg_match('/<button[^>]*type="submit"[^>]*form="' . $id . '"[^>]*>Remove<\/button>/', $html), $pid . ' button submits its form');
	}
}

// A product that can go to the cart gets the catalog's add to cart form,
// back to this page with the notice; one out of stock loses the button and
// the form, and shows its badge.
function test_wishlist_widget_html_add_to_cart_forms()
{
	$html = pg_wishlist_widget_html(wishlist_widget_test_tree(), 9,
		array(wishlist_widget_test_row(11), wishlist_widget_test_row(12, array('inventory' => 1))), wishlist_widget_test_ctx());

	$form = wishlist_widget_test_form($html, 'pgWlCart_pgw9r11');
	pg_assert_contains('method="post"', $form, 'posts');
	pg_assert_contains('action="/software/catalog_detail.php"', $form, 'to the add to cart endpoint');
	pg_assert_contains('<input type="hidden" name="token" value="t0k3n">', $form, 'token');
	pg_assert_contains('<input type="hidden" name="product_id" value="11">', $form, 'product');
	pg_assert_contains('<input type="hidden" name="quantity" value="1">', $form, 'one');
	pg_assert_contains('<input type="hidden" name="current_url" value="/favourites">', $form, 'errors come back here');
	pg_assert_contains('<input type="hidden" name="next_url" value="/favourites?cart_added=11">', $form, 'so does the notice');
	pg_assert_same(1, preg_match('/<button[^>]*type="submit"[^>]*form="pgWlCart_pgw9r11"[^>]*>Add to Cart<\/button>/', $html), 'the button submits it');

	pg_assert_same('', wishlist_widget_test_form($html, 'pgWlCart_pgw9r12'), 'out of stock: no form');
	pg_assert_same(1, substr_count($html, '>Add to Cart</button>'), 'out of stock: no button');
	pg_assert_same(1, substr_count($html, 'oos-badge'), 'the badge on the product out of stock only');
	pg_assert_true(strpos($html, 'oos-badge') > strpos($html, 'Product 12'), 'on its own card');
}

// The forms sit after the widget, never inside a card, a list or a table.
function test_wishlist_widget_html_forms_after_the_cards()
{
	$html = pg_wishlist_widget_html(wishlist_widget_test_tree(), 9,
		array(wishlist_widget_test_row(11), wishlist_widget_test_row(12)), wishlist_widget_test_ctx());
	$first_form = strpos($html, '<form');

	pg_assert_true($first_form !== false, 'forms printed');
	pg_assert_true($first_form > strrpos($html, '>Remove</button>'), 'after the last card');
	pg_assert_same(4, substr_count($html, '<form '), 'two per product');
	pg_assert_same(4, substr_count($html, 'class="d-none"'), 'hidden');
}

// The list's own states: products, no products, signed in or not.
function test_wishlist_widget_html_states()
{
	$html = pg_wishlist_widget_html(wishlist_widget_test_tree(), 9, array(wishlist_widget_test_row(11)), wishlist_widget_test_ctx());
	pg_assert_false(strpos($html, 'empty-state') !== false, 'with products: no empty state');
	pg_assert_false(strpos($html, 'signed-out-note') !== false, 'signed in: no note');
	pg_assert_contains('class="row g-4"', $html, 'the cards');
	pg_assert_contains('>1<', $html, 'the count');

	$html = pg_wishlist_widget_html(wishlist_widget_test_tree(), 9, array(), wishlist_widget_test_ctx());
	pg_assert_contains('empty-state', $html, 'empty: the empty state');
	pg_assert_contains('href="/shop"', $html, 'empty: the shop link');
	pg_assert_false(strpos($html, 'class="row g-4"') !== false, 'empty: no cards');
	pg_assert_false(strpos($html, '<form') !== false, 'empty: no forms');

	$html = pg_wishlist_widget_html(wishlist_widget_test_tree(), 9, array(), wishlist_widget_test_ctx(array('signed_in' => false)));
	pg_assert_contains('signed-out-note', $html, 'signed out: the note');
	pg_assert_contains('href="/login?send_to=%2Ffavourites"', $html, 'signed out: the sign-in link');
	pg_assert_contains('empty-state', $html, 'signed out, nothing saved: the empty state');

	$html = pg_wishlist_widget_html(wishlist_widget_test_tree(), 9, array(wishlist_widget_test_row(11)), wishlist_widget_test_ctx(array('signed_in' => false)));
	pg_assert_contains('signed-out-note', $html, 'a visitor with a list: the note');
	pg_assert_contains('pgWlRemove_pgw9r11', $html, 'a visitor with a list: the products and their forms');

	$html = pg_wishlist_widget_html(wishlist_widget_test_tree(), 9, array(), wishlist_widget_test_ctx(array('shop_url' => '')));
	pg_assert_false(strpos($html, 'Go to the shop') !== false, 'no shop page: the link drops');
}

// Token values are escaped once; a raw ^^token^^ never reaches the page.
function test_wishlist_widget_html_escapes()
{
	$html = pg_wishlist_widget_html(wishlist_widget_test_tree(), 9,
		array(wishlist_widget_test_row(11, array('short_description' => 'A "big" <b>deal</b> & more'))), wishlist_widget_test_ctx());

	pg_assert_contains('A &quot;big&quot; &lt;b&gt;deal&lt;/b&gt; &amp; more', $html, 'escaped');
	pg_assert_false(strpos($html, '<b>deal') !== false, 'no markup from the product');
	pg_assert_false(strpos($html, '^^') !== false, 'no token left');
}

// The sign-in link carries ?send_to= this page, so every page gives it
// another address: an action, not a page for crawlers. Every link bound to
// it gets rel="nofollow" from the drawing, printed once, with the words the
// link already had; other links are left alone.
function test_wishlist_widget_html_login_link_nofollow()
{
	$n = 'wishlist_widget_test_node';
	$tree = $n('root', array(), array(
		$n('content', array('contentType' => 'link', 'text' => 'Sign in', 'href' => '#', '_bindings' => array('href' => '__login_url'))),
		$n('semantic', array('tag' => 'a', 'text' => 'Log in here', '_attrs' => array(array('name' => 'rel', 'value' => 'noopener')),
			'_bindings' => array('href' => '__login_url'))),
		$n('component', array('componentType' => 'btn', 'btnElement' => 'a', 'variant' => 'primary', 'text' => 'Log In', 'rel' => 'external nofollow',
			'_bindings' => array('href' => '__login_url'))),
		$n('content', array('contentType' => 'link', 'text' => 'Go to the shop', 'href' => '#', '_bindings' => array('href' => '__shop_url'))),
	));

	$html = pg_wishlist_widget_html($tree, 9, array(), wishlist_widget_test_ctx(array('signed_in' => false)));
	preg_match_all('/<a [^>]*>/', $html, $m);
	pg_assert_same(4, count($m[0]), 'four links');
	list($link, $semantic, $btn, $shop) = $m[0];

	pg_assert_contains('href="/login?send_to=%2Ffavourites"', $link, 'the sign-in address');
	pg_assert_contains('rel="nofollow"', $link, 'a link');
	pg_assert_contains('rel="noopener nofollow"', $semantic, 'an <a>, its own rel kept');
	pg_assert_same(1, substr_count($semantic, 'rel='), 'rel printed once');
	pg_assert_contains('rel="external nofollow"', $btn, 'a button drawn as a link, nofollow not repeated');
	pg_assert_false(strpos($shop, 'rel=') !== false, 'the shop link is a page');

	// The starter-like layout: its signed-out note's link.
	$html = pg_wishlist_widget_html(wishlist_widget_test_tree(), 9, array(), wishlist_widget_test_ctx(array('signed_in' => false)));
	pg_assert_same(1, preg_match('/<a href="\/login\?send_to=%2Ffavourites" rel="nofollow"[^>]*>Sign in<\/a>/', $html), 'the starter note');
}

// A link cannot post: one bound to a list action leaves the page instead of
// sitting there dead.
function test_wishlist_widget_html_link_actions_drop()
{
	$extra = array(wishlist_widget_test_node('content', array('contentType' => 'link', 'text' => 'Remove link', 'href' => '#',
		'_bindings' => array('action' => 'wishlist_remove'))));
	$html = pg_wishlist_widget_html(wishlist_widget_test_tree($extra), 9, array(wishlist_widget_test_row(11)), wishlist_widget_test_ctx());

	pg_assert_false(strpos($html, 'Remove link') !== false, 'dropped');
	pg_assert_contains('pgWlRemove_pgw9r11', $html, 'the button still posts');
}

// The form of $id in $html, '' when there is none.
function wishlist_widget_test_form($html, $id)
{
	return (preg_match('/<form id="' . preg_quote($id, '/') . '"[^>]*>.*?<\/form>/s', $html, $m) === 1) ? $m[0] : '';
}

// -- the renderer ----------------------------------------------------------

// The list belongs to the visitor: the page is never stored for others,
// whatever path the render takes, the empty list included. The cookie list
// moves into the account before the rows are read, and only the owner's
// rows are read.
function test_wishlist_widget_renderer_order()
{
	$source = wishlist_widget_test_function_source('_render_system_widget_wishlist');
	$personal = strpos($source, "pg_page_cache_personal('wishlist');");

	pg_assert_true($personal !== false, 'marks the page personal');
	pg_assert_true($personal < strpos($source, 'return'), 'before any return');
	pg_assert_true(strpos($source, 'pg_wishlist_merge();') > $personal, 'merges');
	pg_assert_true(strpos($source, 'pg_wishlist_owner_sql(pg_wishlist_owner(false))') > strpos($source, 'pg_wishlist_merge();'), 'reads the owner after the merge');
	pg_assert_contains('pg_wishlist_product_live_sql()', $source, 'only products the catalog shows');
	pg_assert_contains("_pg_inject_messages_node(\$tree, 'catalog_detail');", $source, 'add to cart errors land in the widget');
	pg_assert_contains('get_token_field()', $source, 'the session token');
	pg_assert_contains('pg_wishlist_widget_html(', $source, 'draws through the tested function');
}

// -- a page drawn for nobody's visit ---------------------------------------

// What pg_wishlist_widget_background() answers in a PHP process of its own
// set up the way update_search_index.php leaves it: UPDATE_SEARCH_INDEX
// defined, and the manager who started the run still signed in (init.php
// ran first). Its own process, because the constant would change every
// later test. Null when PHP cannot be started again.
function wishlist_widget_test_search_index_process()
{
	if (!function_exists('exec') || !defined('PHP_BINARY') || (PHP_BINARY === '')) {
		return null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgwishlist');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, '<?php' . "\n"
		. "define('PG_TEST_RUNNER', true);\n"
		. "define('UPDATE_SEARCH_INDEX', true);\n"
		. 'require ' . var_export(PG_FUNCTIONS_DIR . '/functions.php', true) . ";\n"
		. "define('USER_LOGGED_IN', true);\n"
		. "define('USER_ID', 7);\n"
		. "echo json_encode(array('background' => pg_wishlist_widget_background(), 'user_id' => pg_wishlist_user_id()));\n");

	$out = array();
	$code = 1;
	@exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	$result = json_decode(implode("\n", $out), true);

	if (($code !== 0) || !is_array($result)) {
		return array('error' => implode("\n", $out));
	}

	return $result;
}

// The search index draws every page inside the request of the manager who
// started it: the manager is still signed in there, and the page is no
// visit of theirs.
function test_wishlist_widget_background_search_index()
{
	$result = wishlist_widget_test_search_index_process();

	if ($result === null) {
		return;
	}

	pg_assert_false(isset($result['error']), isset($result['error']) ? $result['error'] : 'ran');
	pg_assert_same(7, isset($result['user_id']) ? $result['user_id'] : null, 'the manager is signed in there');
	pg_assert_same(true, isset($result['background']) ? $result['background'] : null, 'a background render');
}

// The SEO score and the editor's preview (pg_seo_rendering()) and an e-mail
// body (the render context's 'email') draw the page inside somebody else's
// request too. An ordinary visit is none of them, nor is a page drawn with
// a context that is not an e-mail.
function test_wishlist_widget_background_seo_and_email()
{
	pg_assert_false(pg_wishlist_widget_background(), 'an ordinary visit');

	$seo = pg_seo_rendering();
	pg_seo_rendering(true);
	try {
		$answer = pg_wishlist_widget_background();
	} finally {
		pg_seo_rendering($seo);
	}
	pg_assert_true($answer, 'the SEO score, the preview');

	$previous = pg_sw_render_context(array('email' => true, 'form_id' => 4));
	try {
		$answer = pg_wishlist_widget_background();
	} finally {
		pg_sw_render_context($previous);
	}
	pg_assert_true($answer, 'an e-mail body');

	$previous = pg_sw_render_context(array('email' => false, 'order_id' => 4));
	try {
		$answer = pg_wishlist_widget_background();
	} finally {
		pg_sw_render_context($previous);
	}
	pg_assert_false($answer, 'a page drawn for its visitor');
}

// In such a render the widget belongs to no visitor. The renderer asks
// before anything that reads or moves a list and then draws what a visitor
// without a list sees: no merge, no owner lookup, no rows, signed out, no
// way back to the request's address, no session token.
function test_wishlist_widget_renderer_background()
{
	$source = wishlist_widget_test_function_source('_render_system_widget_wishlist');
	$asked = strpos($source, '$background = pg_wishlist_widget_background();');

	pg_assert_true($asked !== false, 'asks');
	foreach (array('pg_wishlist_merge();', 'pg_wishlist_owner(', 'pg_wishlist_user_id()', 'get_request_uri', 'get_token_field()') as $call) {
		$at = strpos($source, $call);
		pg_assert_true(($at !== false) && ($asked !== false) && ($at > $asked), $call . ' after the question');
	}

	pg_assert_same(1, substr_count($source, 'pg_wishlist_merge();'), 'one merge');
	pg_assert_same(1, preg_match('/if \(!\$background\) \{\s*pg_wishlist_merge\(\);\s*\}/', $source), 'no merge');
	pg_assert_contains('if (!$background && pg_wishlist_ready()) {', $source, 'no owner, no rows');
	pg_assert_same(1, preg_match('/\$self\s*=\s*\$background \? \'\' : _pg_acct_url_without\(/', $source), 'no way back to the request');
	pg_assert_same(1, preg_match("/'signed_in'\\s*=>\\s*!\\\$background && pg_wishlist_user_id\\(\\) > 0,/", $source), 'signed out');
	pg_assert_same(1, preg_match("/'token_field'\\s*=>\\s*\\\$background \\? '' : get_token_field\\(\\),/", $source), 'no session token');
}

// -- registrations ---------------------------------------------------------

function test_wishlist_widget_registered_in_php()
{
	$functions = (string) file_get_contents(PG_FUNCTIONS_DIR . '/functions.php');
	pg_assert_contains("require_once(PG_FUNCTIONS_DIR . '/includes/fn/widgets_wishlist.php');", $functions, 'loaded');

	$designer = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/designer.php');
	pg_assert_same(1, preg_match("/\\\$account_renderers = array\\((?:[^;]*?)'wishlist'\\s*=>\\s*'_render_system_widget_wishlist'/s", $designer), 'dispatched');

	$labels = pg_sw_type_labels();
	pg_assert_true(isset($labels['wishlist']), 'named');
	pg_assert_same(lang('Favourites'), isset($labels['wishlist']) ? $labels['wishlist'] : '', 'as Favourites');
}

function test_wishlist_widget_registered_in_the_editor()
{
	$types = wishlist_widget_test_js_between('var SW_TYPES = [', '];');
	pg_assert_contains("{ type: 'wishlist',", $types, 'SW_TYPES');
	pg_assert_contains("label: _sdT('Favourites')", $types, 'its label');

	pg_assert_contains("wishlist:", wishlist_widget_test_js_between('var _regionTypeLabels2 = {', '};'), 'canvas band label');
	pg_assert_contains("newType === 'wishlist'", wishlist_widget_test_js_between('function _swTypeDefaults(cfg, newType) {', "\n    }\n"), 'defaults');
	pg_assert_contains('wishlist:', wishlist_widget_test_js_between('var SW_ACCOUNT_TOKEN_PALETTES = {', '};'), 'token palette registered');
	pg_assert_contains('wishlist: [', wishlist_widget_test_js_between('var SW_VISIBILITY_FLAGS = {', "\n    };"), 'visibility flags');
	pg_assert_contains("case 'wishlist': {", wishlist_widget_test_js_between('function _buildStarterTree(regionType, opts) {', 'function _ensureMessagesNode('), 'starter layout');
	pg_assert_false(strpos(wishlist_widget_test_js_between('var _SW_NO_MESSAGES = {', '};'), 'wishlist') !== false, 'shows messages');
	pg_assert_contains('wishlist: 1', wishlist_widget_test_js_between('var _SD_PRODUCT_REGIONS = {', '};'), 'product samples on the canvas');

	$actions = wishlist_widget_test_js_between('function _sdBindingActionRows(node) {', 'function propsDataBinding(node) {');
	pg_assert_contains("['wishlist_add_to_cart',", $actions, 'add to cart action offered');
	pg_assert_contains("['wishlist_remove',", $actions, 'remove action offered');

	$palette = wishlist_widget_test_js_between('var SW_WISHLIST_TOKEN_GROUPS = [', "\n    ];");
	foreach (array('__short_description', '__detail_url', '__image_url', '__price_formatted', '__added_date', '__wishlist_count', '__login_url', '__shop_url') as $token) {
		pg_assert_contains("['" . $token . "'", $palette, $token . ' offered');
	}
}

// The starter: cards bound to the list's actions, the empty state with the
// shop link, the signed-out note with the sign-in link; no inline style, no
// script.
function test_wishlist_widget_starter_layout()
{
	$starter = wishlist_widget_test_js_between("case 'wishlist': {", "\n            case '");

	pg_assert_true($starter !== '', 'found');
	foreach (array("action: 'wishlist_add_to_cart'", "action: 'wishlist_remove'", "eo_visible_if: 'has_items'", "eo_visible_if: 'no_items'",
		"eo_visible_if: 'is_signed_out'", "eo_visible_if: 'item_out_of_stock'", "href: '__shop_url'", "href: '__login_url'",
		"href: '__detail_url'", "src: '__image_url'", "text: '__price_formatted'", "createNode('loop_area'") as $piece) {
		pg_assert_contains($piece, $starter, $piece);
	}
	pg_assert_contains("_sdT('You have not saved any products yet.')", $starter, 'empty state words');
	pg_assert_contains("_sdT('Sign in to keep your list on every device.')", $starter, 'signed-out words');
	pg_assert_false(strpos($starter, 'style:') !== false, 'no inline style');
	pg_assert_false(stripos($starter, '<script') !== false, 'no script markup');
	pg_assert_false(strpos($starter, "tag: 'script'") !== false, 'no script element');
	pg_assert_false(strpos($starter, "'custom_html'") !== false, 'no raw markup block');
}

// My Account links to the first page carrying the widget; the link drops
// when there is none.
function test_wishlist_widget_my_account_link()
{
	$extras = wishlist_widget_test_function_source('_pg_my_account_extras');
	pg_assert_contains("'__my_account_wishlist_url' => ''", $extras, 'empty until resolved');
	pg_assert_contains("\$links['__my_account_wishlist_url'] = pg_wishlist_widget_page_url();", $extras, 'resolved from the widget pages');

	$page_url = wishlist_widget_test_function_source('pg_wishlist_widget_page_url');
	pg_assert_contains("_pg_sw_open_widget_page_name('wishlist')", $page_url, 'a page carrying the widget');

	pg_assert_contains("['__my_account_wishlist_url',", wishlist_widget_test_js_between('var SW_MY_ACCOUNT_TOKEN_GROUPS = [', "\n    ];"), 'offered');
	pg_assert_contains("href: '__my_account_wishlist_url'", wishlist_widget_test_js_between("case 'my_account': {", "\n            case '"), 'in the starter links');
}

// -- the shop templates ----------------------------------------------------

// The store, boutique and bookshop templates carry a Favourites page any
// visitor can open, holding the widget, and link it from My Account.
function test_wishlist_widget_templates()
{
	foreach (array('pinegrap-store', 'pinegrap-boutique', 'pinegrap-bookshop') as $id) {
		$tpl = _pg_tpl_include(PG_FUNCTIONS_DIR . '/includes/design_templates/' . $id . '.php');
		pg_assert_true(is_array($tpl), $id . ' loaded');
		if (!is_array($tpl)) {
			continue;
		}

		$page = null;
		foreach ($tpl['pages'] as $p) {
			if (isset($p['key']) && $p['key'] === 'favourites') {
				$page = $p;
			}
		}
		pg_assert_true(is_array($page), $id . ' favourites page');
		if (!is_array($page)) {
			continue;
		}
		pg_assert_same('public', isset($page['folder']) ? $page['folder'] : '', $id . ' open to visitors');
		pg_assert_same('ecommerce', isset($page['requires']) ? $page['requires'] : '', $id . ' a shop page');
		pg_assert_same(false, isset($page['sitemap']) ? $page['sitemap'] : null, $id . ' not in the sitemap');
		// A per-visitor list like the cart: every page's footer links to it,
		// and a crawler would only ever see its empty state.
		pg_assert_same(true, isset($page['noindex']) ? $page['noindex'] : null, $id . ' noindex like the cart');

		$widget = null;
		$widget_key = '';
		foreach ($tpl['widgets'] as $key => $w) {
			if (isset($w['config']['regionType']) && $w['config']['regionType'] === 'wishlist') {
				$widget = $w;
				$widget_key = $key;
			}
		}
		pg_assert_true(is_array($widget), $id . ' wishlist widget');
		if (!is_array($widget)) {
			continue;
		}
		pg_assert_same('favourites', $widget['page'], $id . ' on the page');
		pg_assert_same('starter', $widget['tree'], $id . ' the starter layout');
		pg_assert_same('ecommerce', isset($widget['requires']) ? $widget['requires'] : '', $id . ' widget is a shop widget');
		pg_assert_same('{{tab:login}}', $widget['config']['login_page_id'], $id . ' sign-in page');
		pg_assert_same('{{tab:shop}}', $widget['config']['shop_page_id'], $id . ' shop page');
		pg_assert_contains('"templateWidget":"' . $widget_key . '"', json_encode($page['tree']), $id . ' placed');

		$account = isset($tpl['widgets']['my_account']['tree']) ? json_encode($tpl['widgets']['my_account']['tree']) : '';
		pg_assert_contains('"href":"__my_account_wishlist_url"', $account, $id . ' linked from My Account');

		// The My Account link is drawn for a signed-in visitor only and its
		// page sends a guest to sign in, so the footer carries the link a
		// guest follows to their cookie list.
		$footer = isset($tpl['shared']['footer']['tree']) ? json_encode($tpl['shared']['footer']['tree']) : '';
		pg_assert_contains('"href":"{{page:favourites}}"', $footer, $id . ' linked from the footer for guests');
	}
}
