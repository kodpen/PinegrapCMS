<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Visual Page Editor - the Favourites system widget (kind 'wishlist'): the
 * products the visitor saved with the hearts of the catalog, one loop_area
 * row each, newest first, with a button that takes a product out of the
 * list and one that puts it in the cart.
 *
 * The list is the one includes/fn/wishlist.php resolves for the request:
 * the signed-in account's, or the one a visitor who is not signed in keeps
 * under their pg_wishlist cookie. Nobody else's is ever read. A visitor
 * without the cookie sees the empty list. Because the page differs for
 * every visitor, every render marks it personal for the page cache.
 *
 * The row buttons post hidden forms printed after the widget and named by
 * the buttons' form attribute, so no form sits inside a card, a list or a
 * table: remove to wishlist.php, add to cart to catalog_detail.php (the
 * catalog cards' form), both with the session token.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// Saved products a widget lists when its settings name no number, and the
// most it lists whatever they name.
define('PG_WISHLIST_WIDGET_ROWS', 100);
define('PG_WISHLIST_WIDGET_ROWS_MAX', 500);

/**
 * Whether a saved product can go to the cart from the list: anything but a
 * product that tracks its stock, has none left and takes no backorders, the
 * same rule that takes the button off a catalog card.
 *
 * @param array $row products.inventory, inventory_quantity, backorder
 * @return bool
 */
function pg_wishlist_widget_can_add($row)
{
    $tracked = !empty($row['inventory']);
    $left = isset($row['inventory_quantity']) ? (int)$row['inventory_quantity'] : 0;
    $backorder = !empty($row['backorder']);

    return !($tracked && ($left <= 0) && !$backorder);
}

/**
 * The price a saved product sells at now: an offer's price when one applies
 * to it (get_discounted_product_prices(), read once for the list), else its
 * own; rounded, as the catalog rounds it.
 *
 * @param array $row products.id, price
 * @param array $ctx 'discounted' => product id => price in minor units
 * @return int minor units
 */
function pg_wishlist_widget_price($row, $ctx)
{
    $pid = (int)$row['id'];
    $own = (int)$row['price'];
    $discounted = (isset($ctx['discounted']) && is_array($ctx['discounted'])) ? $ctx['discounted'] : array();

    return isset($discounted[$pid]) ? (int)round($discounted[$pid]) : $own;
}

/**
 * The widget's visibility flags.
 *
 * @param int  $count     saved products listed
 * @param bool $signed_in whether the list is an account's
 * @return array
 */
function pg_wishlist_widget_flags($count, $signed_in)
{
    $wl_flags = array(
        'has_items'     => (int)$count > 0,
        'no_items'      => (int)$count === 0,
        'is_signed_in'  => (bool)$signed_in,
        'is_signed_out' => !$signed_in,
    );

    return $wl_flags;
}

/**
 * The flags of one saved product's row.
 *
 * @param array $row
 * @param array $ctx
 * @return array
 */
function pg_wishlist_widget_row_flags($row, $ctx)
{
    $in_stock = pg_wishlist_widget_can_add($row);
    $wl_row_flags = array(
        'item_in_stock'     => $in_stock,
        'item_out_of_stock' => !$in_stock,
        'item_has_discount' => pg_wishlist_widget_price($row, $ctx) < (int)$row['price'],
    );

    return $wl_row_flags;
}

/**
 * One saved product's tokens, not escaped yet: the catalog cards' names for
 * the same values, and the day it was saved.
 *
 * @param array $row products.id, name, short_description, address_name,
 *                   image_name, price, inventory, inventory_quantity,
 *                   backorder, the allow-listed columns
 *                   (pg_sw_entity_select()); wishlist_items.added_at
 * @param array $ctx base_path, detail_prefix (the detail page's address and
 *                   a slash, '' for none), currency_symbol, discounted,
 *                   date_format
 * @return array
 */
function pg_wishlist_widget_row_values($row, $ctx)
{
    $pid = (int)$row['id'];
    $address = isset($row['address_name']) ? (string)$row['address_name'] : '';
    $image = isset($row['image_name']) ? (string)$row['image_name'] : '';
    $prefix = isset($ctx['detail_prefix']) ? (string)$ctx['detail_prefix'] : '';
    $symbol = isset($ctx['currency_symbol']) ? $ctx['currency_symbol'] : null;
    $added = isset($row['added_at']) ? (int)$row['added_at'] : 0;

    $original = (int)$row['price'];
    $price = pg_wishlist_widget_price($row, $ctx);
    $has_discount = ($price < $original);
    $saved = $original - $price;
    $percent = ($has_discount && $original > 0) ? (int)round(($saved / $original) * 100) : 0;

    $values = array(
        '__id'                       => (string)$pid,
        '__name'                     => isset($row['name']) ? (string)$row['name'] : '',
        '__short_description'        => isset($row['short_description']) ? (string)$row['short_description'] : '',
        '__detail_url'               => ($prefix !== '' && $address !== '') ? $prefix . encode_url_path($address) : '#',
        '__image_url'                => ($image !== '') ? (isset($ctx['base_path']) ? (string)$ctx['base_path'] : '/') . encode_url_path($image) : '',
        '__image_alt'                => isset($row['name']) ? (string)$row['name'] : '',
        '__price'                    => number_format($price / 100, 2, '.', ''),
        '__price_formatted'          => $has_discount ? _pg_format_price_with_discount($original, $price, $symbol) : pg_visitor_money($price / 100, $symbol),
        '__original_price_formatted' => $has_discount ? pg_visitor_money($original / 100, $symbol) : '',
        '__discount_percent'         => ($percent > 0) ? (string)$percent : '',
        '__out_of_stock_label'       => pg_wishlist_widget_can_add($row) ? '' : (string)lang('Out of stock'),
        '__added_date'               => ($added > 0) ? _pg_sw_short_date($added, isset($ctx['date_format']) ? (string)$ctx['date_format'] : 'Y-m-d') : '',
    );

    // The product's allow-listed values (widgets_entity_fields.php).
    $values += pg_sw_entity_values('product', $row, array('currency_symbol' => $symbol));

    return $values;
}

/**
 * Turns a button bound to a list action into the submit button of the form
 * $form_id names. The wiring attributes are rewritten whatever the node
 * carries; a component button's boilerplate caption becomes the action's.
 *
 * @param array  $node    a component btn or a semantic <button>
 * @param string $form_id
 * @param string $action  wishlist_remove | wishlist_add_to_cart
 * @return void
 */
function _pg_wishlist_set_submit(&$node, $form_id, $action)
{
    $managed = array('type' => 1, 'form' => 1, 'formaction' => 1, 'formmethod' => 1, 'name' => 1, 'value' => 1);
    $kept = array();

    foreach ((isset($node['props']['_attrs']) && is_array($node['props']['_attrs'])) ? $node['props']['_attrs'] : array() as $a) {
        if (is_array($a) && isset($a['name']) && !isset($managed[strtolower((string)$a['name'])])) {
            $kept[] = $a;
        }
    }

    if ($node['type'] === 'component') {
        $node['props']['btnElement'] = 'button';
        $node['props']['btnType'] = 'submit';
        $node['props']['href'] = '';
        $text = isset($node['props']['text']) && is_string($node['props']['text']) ? trim($node['props']['text']) : '';
        if ($text === '' || $text === 'Click Me' || $text === 'Button') {
            $node['props']['text'] = ($action === 'wishlist_remove') ? lang('Remove') : lang('Add to Cart');
        }
    } else {
        $kept[] = array('name' => 'type', 'value' => 'submit');
    }

    $kept[] = array('name' => 'form', 'value' => $form_id);
    $node['props']['_attrs'] = $kept;
}

/**
 * Wires the list actions under $node: a component button or a <button>
 * bound to wishlist_remove or wishlist_add_to_cart submits the form
 * $form_ids names for that action. A node bound to one is dropped when it
 * cannot post (a link) or when there is nothing to post (no form id: a
 * product that cannot go to the cart, a button outside the rows).
 *
 * @param array $node
 * @param array $form_ids action => form id, '' for none
 * @param array $used     the actions wired, filled in
 * @return void
 */
function _pg_wishlist_wire_actions(&$node, $form_ids, &$used)
{
    if (!is_array($node) || empty($node['children']) || !is_array($node['children'])) {
        return;
    }

    $kept = array();

    foreach ($node['children'] as $child) {
        $action = (is_array($child) && isset($child['props']['_bindings']['action'])) ? (string)$child['props']['_bindings']['action'] : '';

        if ($action !== '' && array_key_exists($action, $form_ids)) {
            $type = isset($child['type']) ? (string)$child['type'] : '';
            $is_btn = ($type === 'component') && isset($child['props']['componentType']) && ($child['props']['componentType'] === 'btn');
            $is_button = ($type === 'semantic') && isset($child['props']['tag']) && (strtolower((string)$child['props']['tag']) === 'button');
            $form_id = (string)$form_ids[$action];

            if ((!$is_btn && !$is_button) || ($form_id === '')) {
                continue;
            }

            _pg_wishlist_set_submit($child, $form_id, $action);
            $used[$action] = true;
        }

        if (is_array($child)) {
            _pg_wishlist_wire_actions($child, $form_ids, $used);
        }

        $kept[] = $child;
    }

    $node['children'] = $kept;
}

/**
 * The hidden forms one row's buttons post: remove to wishlist.php and back
 * to this page; add to cart to catalog_detail.php, whose errors come back to
 * this page and whose success comes back with ?cart_added= for the notice.
 *
 * @param int   $pid
 * @param array $form_ids action => form id
 * @param array $used     the actions a button of the row was wired to
 * @param array $ctx      token_field, self, wishlist_url, cart_url
 * @return string
 */
function pg_wishlist_widget_forms($pid, $form_ids, $used, $ctx)
{
    $pid = (int)$pid;
    $token = isset($ctx['token_field']) ? (string)$ctx['token_field'] : '';
    $self = isset($ctx['self']) ? (string)$ctx['self'] : '';
    $out = '';

    if (!empty($used['wishlist_remove']) && $form_ids['wishlist_remove'] !== '') {
        $out .= '<form id="' . h($form_ids['wishlist_remove']) . '" method="post" action="' . h((string)$ctx['wishlist_url']) . '" class="d-none">'
              . $token
              . '<input type="hidden" name="action" value="remove">'
              . '<input type="hidden" name="product_id" value="' . $pid . '">'
              . '<input type="hidden" name="send_to" value="' . h($self) . '">'
              . '</form>';
    }

    if (!empty($used['wishlist_add_to_cart']) && $form_ids['wishlist_add_to_cart'] !== '') {
        $out .= '<form id="' . h($form_ids['wishlist_add_to_cart']) . '" method="post" action="' . h((string)$ctx['cart_url']) . '" class="d-none">'
              . $token
              . '<input type="hidden" name="product_id" value="' . $pid . '">'
              . '<input type="hidden" name="quantity" value="1">'
              . '<input type="hidden" name="current_url" value="' . h($self) . '">'
              . '<input type="hidden" name="next_url" value="' . h(_pg_sw_append_query($self, 'cart_added', (string)$pid)) . '">'
              . '</form>';
    }

    return $out;
}

/**
 * Stamps rel="nofollow" on every node under $node whose address is bound to
 * $token. The sign-in link carries ?send_to= this page, so every page gives
 * it another address: an action, not a page for crawlers to walk. The rel
 * prop is what a link, a semantic <a> and a button drawn as <a> print, and
 * a set prop hides an Attributes panel rel, so the words of both are kept
 * and nofollow is added once.
 *
 * @param array  $node
 * @param string $token the bound address, e.g. __login_url
 * @return void
 */
function _pg_wishlist_stamp_nofollow(&$node, $token)
{
    if (!is_array($node)) {
        return;
    }

    if (isset($node['props']['_bindings']['href']) && $node['props']['_bindings']['href'] === $token) {
        $rel = isset($node['props']['rel']) ? (string)$node['props']['rel'] : '';

        foreach ((isset($node['props']['_attrs']) && is_array($node['props']['_attrs'])) ? $node['props']['_attrs'] : array() as $a) {
            if (is_array($a) && isset($a['name']) && strtolower((string)$a['name']) === 'rel' && isset($a['value'])) {
                $rel .= ' ' . (string)$a['value'];
            }
        }

        $words = array();
        foreach (preg_split('/\s+/', strtolower(trim($rel . ' nofollow'))) as $word) {
            $words[$word] = true;
        }
        $node['props']['rel'] = implode(' ', array_keys($words));
    }

    if (!empty($node['children']) && is_array($node['children'])) {
        foreach ($node['children'] as &$child) {
            _pg_wishlist_stamp_nofollow($child, $token);
        }
        unset($child);
    }
}

/**
 * Draws the widget from its tree and the saved products read for it. Reads
 * nothing itself: _render_system_widget_wishlist() hands it the rows and the
 * context.
 *
 * @param array $tree      the widget tree, its Messages node already named
 * @param int   $widget_id
 * @param array $rows      the saved products, in list order
 * @param array $ctx       signed_in, self (this page, the way back),
 *                         login_url, shop_url, wishlist_url, cart_url,
 *                         token_field, and the row context of
 *                         pg_wishlist_widget_row_values()
 * @return string
 */
function pg_wishlist_widget_html($tree, $widget_id, $rows, $ctx)
{
    $widget_id = (int)$widget_id;
    $rows = is_array($rows) ? array_values($rows) : array();
    $login_url = isset($ctx['login_url']) ? (string)$ctx['login_url'] : '';
    $shop_url = isset($ctx['shop_url']) ? (string)$ctx['shop_url'] : '';

    _pg_member_drop_empty_links($tree, array('__login_url' => $login_url, '__shop_url' => $shop_url));
    _pg_wishlist_stamp_nofollow($tree, '__login_url');

    $split = _split_widget_tree($tree);

    // A list button outside the rows names no product.
    $unused = array();
    _pg_wishlist_wire_actions($split['static_tree'], array('wishlist_remove' => '', 'wishlist_add_to_cart' => ''), $unused);

    $wl_flags = pg_wishlist_widget_flags(count($rows), !empty($ctx['signed_in']));
    _eo_apply_visibility_bindings($split['static_tree'], $wl_flags);

    $cards = '';
    $forms = '';

    if ($split['loop_children'] !== null) {
        $row_tree = array('type' => 'root', 'props' => array(), 'children' => $split['loop_children']);

        foreach ($rows as $row) {
            $pid = (int)$row['id'];
            $t = $row_tree;
            $wl_row_flags = pg_wishlist_widget_row_flags($row, $ctx);
            _eo_apply_visibility_bindings($t, $wl_row_flags);

            $form_ids = array(
                'wishlist_remove'      => 'pgWlRemove_pgw' . $widget_id . 'r' . $pid,
                'wishlist_add_to_cart' => pg_wishlist_widget_can_add($row) ? 'pgWlCart_pgw' . $widget_id . 'r' . $pid : '',
            );
            $used = array();
            _pg_wishlist_wire_actions($t, $form_ids, $used);

            $html = '';
            foreach ($t['children'] as $child) {
                $html .= _render_tree_node($child, 0, 0);
            }

            // The prices are markup (pg_visitor_money()); the rest is text.
            $html = _pg_sw_fill_tokens($html, pg_sw_escape_token_values(pg_wishlist_widget_row_values($row, $ctx), array('__original_price_formatted')));
            $cards .= pg_sw_uniquify_row_ids(pg_sw_sweep_tokens($html), $widget_id, $pid);
            $forms .= pg_wishlist_widget_forms($pid, $form_ids, $used, $ctx);
        }
    }

    $rendered = str_replace('<!--pg-loop-slot-->', $cards, trim(_render_tree_node($split['static_tree'], 0, 0)));

    return _pg_member_apply_tokens($rendered, pg_sw_escape_token_values(array(
        '__wishlist_count' => (string)count($rows),
        '__login_url'      => $login_url,
        '__shop_url'       => $shop_url,
    ))) . $forms;
}

/**
 * The address of the first page carrying a Favourites widget the visitor
 * may open, '' when there is none: the My Account link
 * (^^__my_account_wishlist_url^^).
 *
 * @return string
 */
function pg_wishlist_widget_page_url()
{
    // page_tree_json arrived with the multi-page editor; before that upgrade
    // no designed page can carry a widget.
    if (function_exists('pg_multi_page_design_ready') && !pg_multi_page_design_ready()) {
        return '';
    }

    $name = _pg_sw_open_widget_page_name('wishlist');

    return ($name === '') ? '' : (defined('PATH') ? PATH : '/') . encode_url_path($name);
}

/**
 * Where the empty list's shop link goes: the page the settings name, else
 * the first designed page carrying a catalog widget the visitor may open,
 * else the legacy catalog page. '' when the site has none: the link drops.
 *
 * @param mixed $page_ref cfg shop_page_id
 * @return string
 */
function pg_wishlist_widget_shop_url($page_ref)
{
    $url = _pg_member_page_url(is_scalar($page_ref) ? (int)$page_ref : 0);

    if ($url !== '') {
        return $url;
    }

    if (!function_exists('pg_multi_page_design_ready') || pg_multi_page_design_ready()) {
        $name = _pg_sw_open_widget_page_name('catalog_listing');

        if ($name !== '') {
            return (defined('PATH') ? PATH : '/') . encode_url_path($name);
        }
    }

    $legacy = db_value("SELECT page_name FROM page WHERE page_type = 'catalog' ORDER BY page_id ASC LIMIT 1");

    return $legacy ? (defined('PATH') ? PATH : '/') . encode_url_path((string)$legacy) : '';
}

/**
 * Whether the page is drawn for nobody's visit: for the search index
 * (update_search_index.php, which runs as the manager who started it), for
 * the SEO score or the editor's preview (pg_seo_rendering()), or as the body
 * of an e-mail (the render context's 'email'), whose recipient is not the
 * visitor of the request that sends it. The request's account, cookie and
 * session belong to somebody else there.
 *
 * @return bool
 */
function pg_wishlist_widget_background()
{
    if (function_exists('pg_seo_rendering') && pg_seo_rendering()) {
        return true;
    }

    if (defined('UPDATE_SEARCH_INDEX') && UPDATE_SEARCH_INDEX === true) {
        return true;
    }

    $sw_ctx = function_exists('pg_sw_render_context') ? pg_sw_render_context() : array();

    return !empty($sw_ctx['email']);
}

// Render a 'wishlist' system widget ("Favourites"): the visitor's saved
// products. The list of the signed-in account, or of the visitor whose
// pg_wishlist cookie the request carries; a visitor without one sees the
// empty list. Never another person's: a page drawn for the search index,
// the SEO score, the editor's preview or an e-mail
// (pg_wishlist_widget_background()) shows the empty list too.
//
// loop_area: one row per saved product, newest first. Row tokens: the
// catalog cards' ^^__id^^, ^^__name^^, ^^__short_description^^,
// ^^__detail_url^^, ^^__image_url^^, ^^__image_alt^^, ^^__price^^,
// ^^__price_formatted^^, ^^__original_price_formatted^^,
// ^^__discount_percent^^, ^^__out_of_stock_label^^; and ^^__added_date^^
// (the day it was saved). Row flags: item_in_stock (it can go to the cart),
// item_out_of_stock, item_has_discount. Row actions (a component button or a
// <button>): wishlist_remove, wishlist_add_to_cart (left out for a product
// out of stock).
//
// Visibility flags: has_items, no_items, is_signed_in, is_signed_out (the
// list lives in this browser only). Tokens: ^^__wishlist_count^^,
// ^^__login_url^^ (back here after signing in), ^^__shop_url^^.
// Errors: liveform 'catalog_detail', where a refused add to cart is put.
// cfg: max_results (default 100, at most 500), login_page_id, shop_page_id.
function _render_system_widget_wishlist($tree_json, $widget_id, $cfg = array(), $mode = 'preview')
{
    // The list differs for every visitor: this answer is never stored for
    // anybody else, on every path below, the empty list and a broken tree
    // included.
    pg_page_cache_personal('wishlist');

    $widget_id = (int)$widget_id;
    if ($tree_json === '' || $tree_json === null) return '';
    if (!is_array($cfg)) $cfg = array();
    $tree = json_decode($tree_json, true);
    if (!is_array($tree)) return '';

    // Drawn inside somebody else's request, the widget belongs to no
    // visitor: their list is neither moved nor read, no cookie is sent, and
    // the page shows what a visitor without a list sees.
    $background = pg_wishlist_widget_background();

    // A visitor who has signed in since saving products finds them in the
    // account's list.
    if (!$background) {
        pg_wishlist_merge();
    }

    $limit = (isset($cfg['max_results']) && (int)$cfg['max_results'] > 0)
        ? min(PG_WISHLIST_WIDGET_ROWS_MAX, (int)$cfg['max_results']) : PG_WISHLIST_WIDGET_ROWS;
    $rows = array();

    if (!$background && pg_wishlist_ready()) {
        $where = pg_wishlist_owner_sql(pg_wishlist_owner(false));

        if ($where !== '') {
            $rows = db_items(
                "SELECT
                    products.id, products.name, products.short_description, products.address_name,
                    products.image_name, products.price,
                    products.inventory, products.inventory_quantity, products.backorder
                    " . pg_sw_entity_select('product', 'products') . ",
                    wishlist_items.added_at
                FROM wishlist_items
                INNER JOIN products ON products.id = wishlist_items.product_id
                WHERE " . $where . " AND " . pg_wishlist_product_live_sql() . "
                ORDER BY wishlist_items.added_at DESC, wishlist_items.id DESC
                LIMIT " . (int)$limit);

            if (!is_array($rows)) $rows = array();
        }
    }

    $base_path = defined('OUTPUT_PATH') ? OUTPUT_PATH : '/';
    $software  = _pg_acct_software_path();
    // In a background render the request's address is not this page's.
    $self      = $background ? '' : _pg_acct_url_without(function_exists('get_request_uri') ? (string)get_request_uri() : '', array('cart_added'));

    $login_url = _pg_acct_page_url(isset($cfg['login_page_id']) ? $cfg['login_page_id'] : 0, 'login');
    if ($login_url === '') $login_url = $software . '/';
    if ($self !== '') {
        $login_url .= ((strpos($login_url, '?') === false) ? '?' : '&') . 'send_to=' . urlencode($self);
    }

    $detail_name = pg_sw_catalog_detail_page_name(0);
    $discounted  = ($rows && function_exists('get_discounted_product_prices')) ? get_discounted_product_prices() : array();

    _pg_inject_messages_node($tree, 'catalog_detail');

    $html = pg_wishlist_widget_html($tree, $widget_id, $rows, array(
        'signed_in'       => !$background && pg_wishlist_user_id() > 0,
        'self'            => $self,
        'login_url'       => $login_url,
        'shop_url'        => pg_wishlist_widget_shop_url(isset($cfg['shop_page_id']) ? $cfg['shop_page_id'] : 0),
        'wishlist_url'    => $software . '/wishlist.php',
        'cart_url'        => $software . '/catalog_detail.php',
        'token_field'     => $background ? '' : get_token_field(),
        'base_path'       => $base_path,
        'detail_prefix'   => ($detail_name !== '') ? $base_path . encode_url_path($detail_name) . '/' : '',
        'currency_symbol' => defined('VISITOR_CURRENCY_SYMBOL') ? VISITOR_CURRENCY_SYMBOL : '$',
        'discounted'      => is_array($discounted) ? $discounted : array(),
        'date_format'     => pg_sw_default_date_format('date'),
    ));

    // A product put in the cart from here comes back with ?cart_added=: the
    // catalog's notice says so and links to the cart.
    if ($rows) {
        $html .= pg_sw_cart_toast_script('', function_exists('pg_sw_cart_url') ? pg_sw_cart_url(0) : '');
    }

    return $html;
}
