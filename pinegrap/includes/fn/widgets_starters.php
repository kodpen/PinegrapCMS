<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Visual Page Editor - the layout gallery's "Starters": ready-made,
 * style-neutral layouts a system widget kind ships with the release. A
 * list's starter is the repeated part of the widget (the children of its
 * loop area) and the classes of the element around it; the editor puts it
 * into the kind's default layout, so the toolbar, filters, pages and
 * messages of that layout stay as they are. A detail widget (a product
 * page, the cart, the order preview, a form record) holds its forms and
 * controls outside a repeated part, so its starter is the whole layout,
 * with markers where the editor puts parts of the default layout (the
 * variant pickers, the offer code form, the payment block). Bootstrap
 * classes and theme colours only, no fixed inline style; every word a
 * visitor or the designer reads goes through lang().
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * The starters of widget kind $kind (pg_sw_widget_kind()), in the order the
 * gallery lists them; an empty array for a kind without any.
 *
 * Each starter: array('key' => <stable name>, 'label' => <its name>,
 * 'loop' => <children of the loop area>, 'wrap' => <props for the element
 * holding the loop area, merged over the default layout's>), or instead of
 * 'loop' and 'wrap' 'body' => <the children of the layout's root>. A form
 * list marks the element its record's fields go into with
 * props.starterFields: the editor fills it from the chosen form, as the
 * default layout does. In a body, a node whose only prop is starterKeep
 * stands for the first node of the default layout matching it: 'loop' (the
 * widget's repeated part), 'content:<type>', 'region:<type>' or
 * '<binding>:<value>' (e.g. 'section:quick_add'); a body has one repeated
 * part, its own loop area or the kept one.
 *
 * @param string $kind
 * @return array
 */
function pg_sw_starters($kind)
{
    switch ((string)$kind) {
        case 'catalog_listing':
            return _pg_st_catalog_listing();
        case 'form_list_view':
            return _pg_st_form_list_view();
        case 'comments_recent':
            return _pg_st_comments_recent();
        case 'catalog_item_view':
            return _pg_st_catalog_item_view();
        case 'shopping_cart':
            return _pg_st_shopping_cart();
        case 'order_preview':
            return _pg_st_order_preview();
        case 'form_item_view':
            return _pg_st_form_item_view();
    }
    return array();
}

/**
 * The kinds that have starters: the gallery asks only for these.
 *
 * @return array
 */
function pg_sw_starter_kinds()
{
    return array('catalog_listing', 'form_list_view', 'comments_recent', 'catalog_item_view', 'shopping_cart', 'order_preview', 'form_item_view');
}

// ── Node builders ───────────────────────────────────────────────────────
// The shapes the editor's createNode() makes; the editor gives every node
// its id when the starter is tried.

function _pg_st_el($tag, $class, $name, $children = array(), $extra = array())
{
    $props = array('tag' => $tag, 'cssClass' => $class);
    if ($name !== '') $props['customName'] = $name;
    return array('type' => 'semantic', 'props' => array_merge($props, $extra), 'children' => $children);
}

function _pg_st_text($tag, $class, $name, $text, $bindings = array())
{
    $extra = array('text' => $text);
    if ($bindings) $extra['_bindings'] = $bindings;
    return _pg_st_el($tag, $class, $name, array(), $extra);
}

function _pg_st_link($text, $class, $name, $bindings)
{
    return array('type' => 'content', 'props' => array(
        'contentType' => 'link', 'text' => $text, 'href' => '#', 'cssClass' => $class,
        'customName' => $name, '_bindings' => $bindings,
    ), 'children' => array());
}

function _pg_st_image($class, $bindings)
{
    return array('type' => 'content', 'props' => array(
        'contentType' => 'image', 'src' => '', 'alt' => '', 'fluid' => true, 'rounded' => false,
        'cssClass' => $class, 'customName' => lang('Picture'), '_bindings' => $bindings,
        '_attrs' => array(array('name' => 'loading', 'value' => 'lazy')),
    ), 'children' => array());
}

// The default layout's first node matching $key, put here by the editor.
function _pg_st_keep($key)
{
    return array('type' => 'semantic', 'props' => array('starterKeep' => $key), 'children' => array());
}

// $props drawn only while $token has a value.
function _pg_st_if_value($token, $props = array())
{
    $props['_bindings'] = array_merge(isset($props['_bindings']) ? $props['_bindings'] : array(), array('show_if' => $token));
    $props['_bindParams'] = array('show_if' => array('op' => 'not_empty'));
    return $props;
}

// An element showing $token ($sample on the canvas); with $hide_empty
// drawn only while the token has a value.
function _pg_st_value($tag, $class, $name, $sample, $token, $hide_empty = false)
{
    $props = array('text' => $sample, '_bindings' => array('text' => $token));
    return _pg_st_el($tag, $class, $name, array(), $hide_empty ? _pg_st_if_value($token, $props) : $props);
}

// A button component bound to $action, as the default layouts draw them.
function _pg_st_button($text, $variant, $action, $class = '', $name = '')
{
    $props = array('componentType' => 'btn', 'text' => $text, 'variant' => $variant, 'size' => '', 'href' => '#',
        'cssClass' => $class, '_bindings' => array('action' => $action));
    if ($name !== '') $props['customName'] = $name;
    return array('type' => 'component', 'props' => $props, 'children' => array());
}

function _pg_st_col($children, $xs, $sm = '', $md = '', $lg = '', $class = '')
{
    return array('type' => 'col', 'props' => array(
        'xs' => $xs, 'sm' => $sm, 'md' => $md, 'lg' => $lg, 'xl' => '', 'xxl' => '',
        'offsetXs' => '', 'offsetMd' => '', 'order' => '', 'cssClass' => $class,
    ), 'children' => $children);
}

// ── Catalog card ────────────────────────────────────────────────────────

// The product's name, linked to its page; the link stretches over the card.
function _pg_st_product_title($tag, $class)
{
    return _pg_st_el($tag, $class, lang('Product Name'), array(
        _pg_st_link(lang('Product name'), 'stretched-link link-body-emphasis text-decoration-none', lang('Link'),
            array('text' => '__short_description', 'href' => '__detail_url')),
    ));
}

function _pg_st_product_picture($frame)
{
    return _pg_st_el('div', $frame, lang('Picture Frame'), array(
        _pg_st_image('object-fit-cover', array('src' => '__image_url', 'alt' => '__short_description')),
    ));
}

function _pg_st_price($class)
{
    return _pg_st_text('span', $class, lang('Price'), lang('Price'), array('text' => '__price_formatted'));
}

// The listing wraps the card in the form that adds the product; above the
// stretched link so it stays clickable.
function _pg_st_add_to_cart($outline)
{
    return array('type' => 'component', 'props' => array(
        'componentType' => 'btn', 'btnElement' => 'button', 'btnType' => 'submit',
        'variant' => 'primary', 'outline' => $outline, 'size' => 'sm', 'text' => lang('Add to Cart'),
        'cssClass' => '', '_bindings' => array('action' => 'catalog_add_to_cart'),
    ), 'children' => array());
}

// The favourite heart: drawn hidden by the listing, shown by the page's
// script; a product group's card has none.
function _pg_st_heart()
{
    return array('type' => 'component', 'props' => array(
        'componentType' => 'btn', 'btnElement' => 'button', 'btnType' => 'button',
        'variant' => 'secondary', 'outline' => true, 'size' => 'sm', 'text' => '',
        'cssClass' => 'position-absolute top-0 end-0 m-2 z-2 rounded-circle',
        'customName' => lang('Favourite'), '_bindings' => array('action' => 'catalog_wishlist'),
    ), 'children' => array(
        _pg_st_el('i', 'bi bi-heart', lang('Icon'), array(), array('_attrs' => array(array('name' => 'aria-hidden', 'value' => 'true')))),
        _pg_st_text('span', 'visually-hidden', lang('Screen Reader Text'), lang('Add to favourites')),
    ));
}

function _pg_st_catalog_listing()
{
    $grid = _pg_st_col(array(
        _pg_st_el('div', 'card h-100 position-relative overflow-hidden', lang('Product Card'), array(
            _pg_st_product_picture('ratio ratio-1x1 bg-body-tertiary'),
            _pg_st_heart(),
            _pg_st_el('div', 'card-body d-flex flex-column', lang('Card Body'), array(
                _pg_st_product_title('h3', 'h6 card-title mb-3'),
                _pg_st_el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mt-auto position-relative z-2', lang('Price and Button'), array(
                    _pg_st_price('fw-bold'),
                    _pg_st_add_to_cart(false),
                )),
            )),
        )),
    ), '6', '', '4', '3');

    $list = _pg_st_col(array(
        _pg_st_el('div', 'card position-relative overflow-hidden', lang('Product Card'), array(
            _pg_st_el('div', 'row g-0 align-items-stretch', lang('Card Row'), array(
                _pg_st_el('div', 'col-4 col-md-3', lang('Picture'), array(
                    _pg_st_product_picture('ratio ratio-1x1 h-100 bg-body-tertiary'),
                )),
                _pg_st_el('div', 'col-8 col-md-9', lang('Details'), array(
                    _pg_st_el('div', 'card-body d-flex flex-column h-100', lang('Card Body'), array(
                        _pg_st_product_title('h3', 'h5 card-title mb-1'),
                        _pg_st_text('p', 'card-text text-body-secondary small mb-2 pg-hide-if-empty', lang('Category'), lang('Category'),
                            array('text' => '__category_name')),
                        _pg_st_el('div', 'd-flex flex-wrap align-items-center gap-3 mt-auto position-relative z-2', lang('Price and Button'), array(
                            _pg_st_price('fw-bold fs-5'),
                            _pg_st_add_to_cart(true),
                        )),
                    )),
                )),
            )),
        )),
    ), '12');

    $tile = _pg_st_col(array(
        _pg_st_el('div', 'position-relative rounded overflow-hidden h-100', lang('Product Card'), array(
            _pg_st_product_picture('ratio ratio-4x5 bg-body-tertiary'),
            _pg_st_heart(),
            _pg_st_el('div', 'position-absolute bottom-0 start-0 end-0 m-2 p-2 rounded bg-body bg-opacity-75', lang('Label'), array(
                _pg_st_product_title('h3', 'h6 mb-1 text-truncate'),
                _pg_st_price('small fw-semibold text-body-secondary'),
            )),
        )),
    ), '6', '', '4', '3');

    return array(
        array('key' => 'grid', 'label' => lang('Card grid'), 'loop' => array($grid), 'wrap' => array('gutter' => '4', 'cssClass' => '')),
        array('key' => 'list', 'label' => lang('List with the picture beside'), 'loop' => array($list), 'wrap' => array('gutter' => '3', 'cssClass' => '')),
        array('key' => 'tiles', 'label' => lang('Picture tiles with a label'), 'loop' => array($tile), 'wrap' => array('gutter' => '3', 'cssClass' => '')),
    );
}

// ── Form list ───────────────────────────────────────────────────────────

function _pg_st_fields($tag, $class)
{
    return _pg_st_el($tag, $class, lang('Record'), array(), array('starterFields' => true));
}

function _pg_st_form_list_view()
{
    $cards = _pg_st_col(array(
        _pg_st_el('article', 'card h-100 shadow-sm', lang('Card'), array(
            _pg_st_fields('div', 'card-body'),
        )),
    ), '12', '', '6', '4');

    $rows = _pg_st_col(array(
        _pg_st_fields('article', 'border-bottom pb-3'),
    ), '12');

    $wide = _pg_st_col(array(
        _pg_st_el('article', 'card h-100', lang('Card'), array(
            _pg_st_fields('div', 'card-body p-4'),
        )),
    ), '12', '', '6');

    return array(
        array('key' => 'cards', 'label' => lang('Three cards to a row'), 'loop' => array($cards), 'wrap' => array('gutter' => '4', 'cssClass' => '')),
        array('key' => 'rows', 'label' => lang('One record to a row'), 'loop' => array($rows), 'wrap' => array('gutter' => '3', 'cssClass' => '')),
        array('key' => 'wide', 'label' => lang('Two wide cards to a row'), 'loop' => array($wide), 'wrap' => array('gutter' => '4', 'cssClass' => '')),
    );
}

// ── Recent comments ─────────────────────────────────────────────────────

function _pg_st_comment_name($class)
{
    return _pg_st_text('span', $class, lang('Name'), lang('Name'), array('text' => '__comment_name'));
}

function _pg_st_comment_date()
{
    return _pg_st_text('span', 'pg-hide-if-empty', lang('Date'), lang('Date'), array('text' => '__comment_date'));
}

function _pg_st_comment_thread($class)
{
    return _pg_st_link(lang('Thread Title'), $class, lang('Thread Title'),
        array('href' => '__comment_url', 'text' => '__comment_thread_title'));
}

function _pg_st_comment_excerpt($tag, $class)
{
    return _pg_st_text($tag, $class, lang('Excerpt'), lang('Comment text'), array('text' => '__comment_excerpt'));
}

function _pg_st_comment_stars($class)
{
    return _pg_st_el('div', $class, lang('Stars'), pg_sw_part_comment_stars(), array('_bindings' => array('eo_visible_if' => 'comment_has_rating')));
}

function _pg_st_comments_recent()
{
    $card = _pg_st_el('div', 'col-12 col-md-6', lang('Comment'), array(
        _pg_st_el('div', 'card h-100', lang('Card'), array(
            _pg_st_el('div', 'card-body d-flex flex-column gap-2', lang('Card Body'), array(
                _pg_st_comment_stars('text-warning small'),
                _pg_st_comment_excerpt('p', 'card-text mb-0'),
                _pg_st_el('div', 'd-flex flex-wrap align-items-center gap-2 small text-body-secondary mt-auto', lang('Name Line'), array(
                    _pg_st_comment_name('fw-semibold text-body'),
                    _pg_st_comment_date(),
                )),
                _pg_st_comment_thread('small text-decoration-none'),
            )),
        )),
    ));

    $quote = _pg_st_el('figure', 'mb-0', lang('Comment'), array(
        _pg_st_el('blockquote', 'blockquote fs-6 mb-2', lang('Quote'), array(
            _pg_st_comment_excerpt('p', 'mb-0'),
        )),
        _pg_st_el('figcaption', 'blockquote-footer mb-0', lang('Name Line'), array(
            _pg_st_comment_name('fw-semibold'),
            _pg_st_el('span', 'mx-1', lang('Separator'), array(), array('text' => '·', '_attrs' => array(array('name' => 'aria-hidden', 'value' => 'true')))),
            _pg_st_comment_thread('text-decoration-none'),
        )),
    ));

    return array(
        array('key' => 'cards', 'label' => lang('Cards side by side'), 'loop' => array($card), 'wrap' => array('cssClass' => 'row g-3')),
        array('key' => 'quotes', 'label' => lang('Quotes'), 'loop' => array($quote), 'wrap' => array('cssClass' => 'd-flex flex-column gap-4')),
    );
}

// ── Whole layouts ───────────────────────────────────────────────────────

// The widget's outer container, where the editor puts the Messages block.
function _pg_st_container($children, $class = 'py-4')
{
    return array('type' => 'container', 'props' => array('fluid' => false, 'cssClass' => $class), 'children' => $children);
}

// A heading of a block: words the designer changes in place.
function _pg_st_heading($tag, $class, $text)
{
    return _pg_st_text($tag, $class, lang('Heading'), $text);
}

// ── Product detail ──────────────────────────────────────────────────────

// Name, brand, rating and price at the head of the buying column.
function _pg_st_product_head($title_class)
{
    return array(
        _pg_st_value('p', 'small text-uppercase text-body-secondary mb-1', lang('Brand'), lang('Brand'), '__brand', true),
        _pg_st_text('h1', $title_class, lang('Product Name'), lang('Product name'), array('text' => '__short_description')),
        _pg_st_el('div', 'd-flex align-items-center gap-1 small mb-3', lang('Rating'), array(
            _pg_st_el('i', 'bi bi-star-fill text-warning', lang('Icon'), array(), array('_attrs' => array(array('name' => 'aria-hidden', 'value' => 'true')))),
            _pg_st_text('span', 'fw-semibold', lang('Average Rating'), '4.5', array('text' => '__rating_avg')),
            _pg_st_text('span', 'text-body-secondary', lang('Number of Reviews'), '12', array('text' => '__rating_count')),
            _pg_st_text('span', 'text-body-secondary', lang('Label'), lang('reviews')),
        ), array('_bindings' => array('eo_visible_if' => 'has_rating'))),
    );
}

// What the visitor buys with: the stock note, the variant pickers, the
// recipient, quantity with Add to Cart and the favourite heart, and the
// back-in-stock form, the pickers and forms as the default layout draws them.
function _pg_st_product_buy()
{
    return array(
        _pg_st_value('p', 'text-danger fw-semibold mb-3', lang('Out of Stock Message'), lang('Sorry, this product is not available right now.'), '__out_of_stock_message', true),
        _pg_st_keep('content:variant_attr'),
        _pg_st_keep('section:recipient_picker'),
        _pg_st_el('div', 'row g-2 align-items-center', lang('Add-to-Cart Group'), array(
            _pg_st_el('div', 'col-4 col-sm-3', lang('Quantity'), array(
                _pg_st_el('input', 'form-control', lang('Quantity'), array(), array(
                    '_attrs' => array(array('name' => 'aria-label', 'value' => lang('Quantity'))),
                    '_bindings' => array('value' => 'quantity'),
                )),
            )),
            _pg_st_el('div', 'col', lang('Button'), array(
                _pg_st_button(lang('Add to Cart'), 'primary', 'add_to_cart', 'w-100'),
            )),
            _pg_st_el('div', 'col-auto', lang('Favourite'), array(
                array('type' => 'component', 'props' => array(
                    'componentType' => 'btn', 'btnElement' => 'button', 'btnType' => 'button',
                    'variant' => 'secondary', 'outline' => true, 'size' => '', 'text' => '', 'cssClass' => '',
                    'customName' => lang('Favourite'), '_bindings' => array('action' => 'wishlist'),
                ), 'children' => array(
                    _pg_st_el('i', 'bi bi-heart', lang('Icon'), array(), array('_attrs' => array(array('name' => 'aria-hidden', 'value' => 'true')))),
                    _pg_st_text('span', 'visually-hidden', lang('Screen Reader Text'), lang('Add to favourites')),
                )),
            )),
        )),
        _pg_st_keep('eo_visible_if:can_notify'),
    );
}

// A block of the product's long text under its own heading, drawn only
// when the product has that text.
function _pg_st_product_text($heading, $token, $sample, $class)
{
    return _pg_st_el('section', $class, $heading, array(
        _pg_st_heading('h2', 'h5 mb-3', $heading),
        _pg_st_text('div', 'text-body-secondary', $heading, $sample, array('text' => $token)),
    ), _pg_st_if_value($token));
}

function _pg_st_catalog_item_view()
{
    $sections = _pg_st_container(array_merge(
        array(_pg_st_keep('show_if:__has_breadcrumb')),
        array(_pg_st_el('div', 'row g-4 g-lg-5 align-items-start', lang('Product'), array(
            _pg_st_el('div', 'col-12 col-lg-7', lang('Pictures'), array(_pg_st_keep('content:carousel'))),
            _pg_st_el('div', 'col-12 col-lg-5', lang('Buying'), array_merge(
                _pg_st_product_head('h2 mb-2'),
                array(_pg_st_text('p', 'fs-3 fw-bold mb-3', lang('Price'), lang('Price'), array('text' => '__price_formatted'))),
                _pg_st_product_buy()
            )),
        ))),
        array(
            _pg_st_product_text(lang('Description'), '__description', lang('The product description will appear here.'), 'mt-5 pt-4 border-top'),
            _pg_st_product_text(lang('Details'), '__details', lang('The product details will appear here.'), 'mt-5'),
            _pg_st_el('section', 'mt-5', lang('Reviews'), array(
                _pg_st_heading('h2', 'h5 mb-3', lang('Reviews')),
                _pg_st_keep('region:comments_block'),
            )),
            _pg_st_keep('loop'),
        )
    ));

    $centred = _pg_st_container(array(
        _pg_st_el('div', 'row justify-content-center', lang('Product'), array(
            _pg_st_el('div', 'col-12 col-lg-8', lang('Column'), array_merge(
                array(
                    _pg_st_keep('show_if:__has_breadcrumb'),
                    _pg_st_el('div', 'mb-4', lang('Pictures'), array(_pg_st_keep('content:carousel'))),
                ),
                _pg_st_product_head('h3 mb-2'),
                array(_pg_st_text('p', 'fs-4 fw-bold mb-3', lang('Price'), lang('Price'), array('text' => '__price_formatted'))),
                _pg_st_product_buy(),
                array(
                    _pg_st_el('div', 'mt-4 border-top', lang('More about the product'), array(
                        _pg_st_el('details', 'border-bottom py-3', lang('Description'), array(
                            _pg_st_text('summary', 'fw-semibold', lang('Heading'), lang('Description')),
                            _pg_st_text('div', 'text-body-secondary pt-3', lang('Description'), lang('The product description will appear here.'), array('text' => '__description')),
                        ), _pg_st_if_value('__description', array('_attrs' => array(array('name' => 'open', 'value' => ''))))),
                        _pg_st_el('details', 'border-bottom py-3', lang('Details'), array(
                            _pg_st_text('summary', 'fw-semibold', lang('Heading'), lang('Details')),
                            _pg_st_text('div', 'text-body-secondary pt-3', lang('Details'), lang('The product details will appear here.'), array('text' => '__details')),
                        ), _pg_st_if_value('__details')),
                    )),
                    _pg_st_el('section', 'mt-5', lang('Reviews'), array(
                        _pg_st_heading('h2', 'h5 mb-3', lang('Reviews')),
                        _pg_st_keep('region:comments_block'),
                    )),
                ),
                array(_pg_st_keep('loop'))
            )),
        )),
    ));

    return array(
        array('key' => 'sections', 'label' => lang('Pictures beside, the texts below'), 'body' => array($sections)),
        array('key' => 'centred', 'label' => lang('One centred column'), 'body' => array($centred)),
    );
}

// ── Cart ────────────────────────────────────────────────────────────────

// The notes of a cart row under the product's name: unit price, low and
// no stock, the reserved event; each drawn only when it has a value.
function _pg_st_cart_item_notes()
{
    return array(
        _pg_st_value('p', 'small text-body-secondary mb-0', lang('Unit Price'), lang('Unit price'), '__item_price'),
        _pg_st_value('p', 'small text-warning mb-0', lang('Low Stock Warning'), lang('Only 3 left'), '__item_stock_warning', true),
        _pg_st_value('p', 'small text-danger mb-0', lang('Out of Stock Message'), lang('Out of stock'), '__item_out_of_stock_message', true),
        _pg_st_value('div', 'small', lang('Calendar Event'), '', '__item_calendar_event', true),
    );
}

function _pg_st_cart_item_name($class)
{
    return _pg_st_link(lang('Product name'), $class, lang('Product Name'), array('text' => '__item_short_description', 'href' => '__item_url'));
}

function _pg_st_cart_item_picture($class)
{
    return _pg_st_el('div', $class, lang('Picture Frame'), array(
        _pg_st_image('object-fit-cover', array('src' => '__item_image', 'alt' => '__item_name')),
    ));
}

function _pg_st_cart_qty($class)
{
    return _pg_st_el('input', $class, lang('Quantity Input'), array(), array(
        '_attrs' => array(array('name' => 'aria-label', 'value' => lang('Quantity'))),
        '_bindings' => array('value' => 'cart_qty'),
    ));
}

function _pg_st_cart_remove()
{
    return _pg_st_link(lang('Remove'), 'link-danger small text-decoration-none', lang('Remove'), array('action' => 'remove_from_cart'));
}

// The repeated part of the cart: the headings a recipient's and the
// recurring products' first row carry (empty on the others), then $row.
function _pg_st_cart_loop($row)
{
    return array('type' => 'loop_area', 'props' => array(), 'children' => array(
        _pg_st_value('div', '', lang('Recipient Heading'), '', '__item_recipient_heading', true),
        _pg_st_value('div', '', lang('Recurring Charges Heading'), '', '__item_recurring_heading', true),
        $row,
    ));
}

// The cart's own heading and its saved cart link above the items.
function _pg_st_cart_head()
{
    return array(
        _pg_st_value('h1', 'h3 mb-3', lang('Heading'), lang('My Cart'), '__shopping_cart_label'),
        _pg_st_value('div', 'mb-3', lang('Saved Cart Link'), '', '__saved_cart_link', true),
    );
}

// The offers, campaign gifts, quick add and the offer code: the forms of
// their own as the default layout draws them.
function _pg_st_cart_offers()
{
    return array(
        _pg_st_keep('content:applied_offers'),
        _pg_st_keep('section:pending_offers'),
        _pg_st_keep('section:quick_add'),
        _pg_st_el('div', 'card card-body mb-3', lang('Offer Code'), array(
            _pg_st_value('h2', 'h6 mb-2', lang('Heading'), lang('Offer Code'), '__special_offer_code_label'),
            _pg_st_value('p', 'small text-body-secondary mb-2', lang('Description'), lang('Enter your offer code here, if you have one.'), '__special_offer_code_message', true),
            _pg_st_keep('action:coupon_form'),
        )),
    );
}

function _pg_st_cart_sum_row($label, $token, $class = '')
{
    return _pg_st_el('div', 'd-flex justify-content-between gap-3 mb-1', $label, array(
        _pg_st_text('span', 'text-body-secondary', lang('Label'), $label),
        _pg_st_value('span', $class, $label, lang('Amount'), $token),
    ));
}

// The summary: the totals, the notices, the payment schedule of a
// recurring product, the currency picker and the two buttons.
function _pg_st_cart_summary($class)
{
    return _pg_st_el('div', $class, lang('Summary Card'), array(
        _pg_st_heading('h2', 'h5 mb-3', lang('Order Summary')),
        _pg_st_cart_sum_row(lang('Subtotal'), '__cart_subtotal'),
        _pg_st_cart_sum_row(lang('Discount'), '__cart_discount', 'text-success'),
        _pg_st_cart_sum_row(lang('Tax'), '__cart_tax'),
        _pg_st_el('div', 'd-flex justify-content-between gap-3 border-top pt-2 mt-2 mb-3', lang('Total Row'), array(
            _pg_st_text('span', 'fw-semibold', lang('Label'), lang('Total')),
            _pg_st_value('span', 'fw-bold fs-5', lang('Total'), lang('Amount'), '__cart_total'),
        )),
        _pg_st_value('p', 'small text-body-secondary mb-3', lang('Tax/Shipping Notice'), lang('Tax and shipping will be calculated at checkout.'), '__tax_shipping_notice', true),
        _pg_st_value('div', 'mb-3', lang('Offline Payment Confirmation'), '', '__offline_payment_checkbox', true),
        _pg_st_keep('eo_visible_if:has_recurring_items'),
        _pg_st_keep('text:__currency_selector'),
        _pg_st_el('div', 'd-grid gap-2', lang('Action Buttons'), array(
            _pg_st_button(lang('Proceed to Checkout'), 'primary', 'cart_checkout', 'w-100', lang('Checkout Button')),
            _pg_st_button(lang('Update'), 'outline-secondary', 'cart_update', 'w-100', lang('Update Button')),
        )),
    ));
}

function _pg_st_shopping_cart()
{
    $line = _pg_st_el('div', 'row g-3 align-items-center py-3 border-bottom', lang('Cart Row'), array(
        _pg_st_el('div', 'col-3 col-sm-2', lang('Picture'), array(_pg_st_cart_item_picture('ratio ratio-1x1 rounded overflow-hidden bg-body-tertiary'))),
        _pg_st_el('div', 'col-9 col-sm-5', lang('Details'), array_merge(
            array(_pg_st_cart_item_name('fw-semibold link-body-emphasis text-decoration-none d-block')),
            _pg_st_cart_item_notes()
        )),
        _pg_st_el('div', 'col-6 col-sm-2', lang('Quantity'), array(_pg_st_cart_qty('form-control form-control-sm'))),
        _pg_st_el('div', 'col-6 col-sm-3 text-end', lang('Row Total'), array(
            _pg_st_value('p', 'fw-semibold mb-1', lang('Row Total'), lang('Amount'), '__item_total'),
            _pg_st_cart_remove(),
        )),
    ));

    $stacked = _pg_st_container(array_merge(
        _pg_st_cart_head(),
        array(
            _pg_st_el('div', 'border-top mb-4', lang('Product List'), array(_pg_st_cart_loop($line))),
            _pg_st_el('div', 'row g-4', lang('Below the Items'), array(
                _pg_st_el('div', 'col-12 col-md-6', lang('Offers'), _pg_st_cart_offers()),
                _pg_st_el('div', 'col-12 col-md-6', lang('Summary'), array(_pg_st_cart_summary('card card-body'))),
            )),
        )
    ));

    $card = _pg_st_el('div', 'card mb-3', lang('Product Card'), array(
        _pg_st_el('div', 'card-body', lang('Card Body'), array(
            _pg_st_el('div', 'row g-3', lang('Card Row'), array(
                _pg_st_el('div', 'col-4 col-sm-3', lang('Picture'), array(_pg_st_cart_item_picture('ratio ratio-1x1 rounded overflow-hidden bg-body-tertiary'))),
                _pg_st_el('div', 'col-8 col-sm-9 d-flex flex-column', lang('Details'), array_merge(
                    array(_pg_st_el('div', 'd-flex justify-content-between align-items-start gap-2', lang('Name and Total'), array(
                        _pg_st_cart_item_name('fw-semibold link-body-emphasis text-decoration-none'),
                        _pg_st_value('span', 'fw-semibold text-nowrap', lang('Row Total'), lang('Amount'), '__item_total'),
                    ))),
                    _pg_st_cart_item_notes(),
                    array(_pg_st_el('div', 'd-flex align-items-center gap-3 mt-auto pt-2', lang('Quantity and Remove'), array(
                        _pg_st_cart_qty('form-control form-control-sm w-auto'),
                        _pg_st_cart_remove(),
                    )))
                )),
            )),
        )),
    ));

    $cards = _pg_st_container(array(
        _pg_st_el('div', 'row g-4', lang('Cart'), array(
            _pg_st_el('div', 'col-12 col-lg-8', lang('Product List'), array_merge(_pg_st_cart_head(), array(_pg_st_cart_loop($card)))),
            _pg_st_el('div', 'col-12 col-lg-4', lang('Summary'), array_merge(_pg_st_cart_offers(), array(_pg_st_cart_summary('card card-body bg-body-tertiary border-0')))),
        )),
    ));

    return array(
        array('key' => 'stacked', 'label' => lang('One column, the summary below the items'), 'body' => array($stacked)),
        array('key' => 'cards', 'label' => lang('Product cards, the summary beside them'), 'body' => array($cards)),
    );
}

// ── Order preview ───────────────────────────────────────────────────────

// A line of the totals: $label and the amount, $sign in front, drawn only
// when $flag is on (always without one).
function _pg_st_op_row($label, $token, $flag, $class = 'py-1', $sign = '')
{
    $amount = array();
    if ($sign !== '') $amount[] = _pg_st_text('span', '', lang('Sign'), $sign);
    $amount[] = _pg_st_value('span', '', lang('Amount'), lang('Amount'), $token);
    $extra = $flag !== '' ? array('_bindings' => array('eo_visible_if' => $flag)) : array();
    return _pg_st_el('div', 'd-flex justify-content-between gap-3 ' . $class, $label, array(
        _pg_st_text('span', '', lang('Label'), $label),
        _pg_st_el('span', 'text-nowrap', lang('Amount'), $amount),
    ), $extra);
}

function _pg_st_op_note($class, $name, $text, $flag, $token = '')
{
    $bindings = array('eo_visible_if' => $flag);
    if ($token !== '') $bindings['text'] = $token;
    return _pg_st_el('p', $class, $name, array(), array('text' => $text, '_bindings' => $bindings));
}

// The totals the order preview computes, each line where the order has it.
function _pg_st_op_totals($class)
{
    return _pg_st_el('div', $class, lang('Totals'), array(
        _pg_st_op_row(lang('Subtotal'), '__cart_subtotal', 'shows_subtotal'),
        _pg_st_op_row(lang('Discount'), '__cart_discount', 'has_discount', 'py-1', '-'),
        _pg_st_op_row(lang('Tax'), '__cart_tax', 'has_tax'),
        _pg_st_op_row(lang('Shipping'), '__cart_shipping', 'has_shipping'),
        _pg_st_op_row(lang('Gift card'), '__cart_gift_card_discount', 'has_gift_card_discount', 'py-1', '-'),
        _pg_st_op_row(lang('Total'), '__cart_total', '', 'py-2 fw-bold border-top mt-1'),
        _pg_st_op_row(lang('Card surcharge'), '__cart_surcharge', 'has_surcharge'),
        _pg_st_op_row(lang('Total with the card surcharge'), '__cart_total_with_surcharge', 'has_surcharge', 'py-1 fw-bold'),
        _pg_st_op_note('small text-body-secondary mb-1', lang('Surcharge Note'), lang('The card surcharge is added only when you pay by card.'), 'surcharge_message'),
        _pg_st_op_row(lang('Amount charged'), '__cart_total_charged', 'has_currency_disclaimer', 'py-1 small'),
        _pg_st_op_note('small text-body-secondary mb-0', lang('Exchange Rate Note'), lang('Exchange rate note'), 'has_currency_disclaimer', '__currency_disclaimer'),
    ));
}

// A labelled value of the billing details, drawn when $flag is on.
function _pg_st_op_billing_line($name, $label, $label_token, $sample, $token, $flag)
{
    $label_props = array('text' => $label);
    if ($label_token !== '') $label_props['_bindings'] = array('text' => $label_token);
    return _pg_st_el('p', 'small mb-0', $name, array(
        _pg_st_el('span', 'text-body-secondary me-1', lang('Label'), array(), $label_props),
        _pg_st_value('span', '', $name, $sample, $token),
    ), array('_bindings' => array('eo_visible_if' => $flag)));
}

// The billing details with the link back to change them.
function _pg_st_op_billing($class)
{
    return _pg_st_el('div', $class, lang('Billing Information'), array(
        _pg_st_el('div', 'd-flex justify-content-between align-items-start gap-2 mb-2', lang('Heading'), array(
            _pg_st_heading('h2', 'h6 mb-0', lang('Billing Information')),
            _pg_st_link(lang('Change'), 'small', lang('Change'), array('href' => '__billing_update_url')),
        )),
        _pg_st_value('p', 'fw-semibold mb-0', lang('Full Name'), lang('Full Name'), '__billing_name'),
        _pg_st_value('p', 'mb-0', lang('Company'), lang('Company'), '__billing_company', true),
        _pg_st_value('div', 'small text-body-secondary', lang('Address'), lang('Address (lines)'), '__billing_address_html'),
        _pg_st_value('p', 'small mb-0', lang('Email'), lang('Email'), '__billing_email'),
        _pg_st_value('p', 'small mb-0', lang('Phone'), lang('Phone'), '__billing_phone', true),
        _pg_st_op_billing_line(lang('Custom field 1'), lang('Custom field 1 label'), '__custom_field_1_label', lang('Custom field 1'), '__custom_field_1', 'has_custom_field_1'),
        _pg_st_op_billing_line(lang('Custom field 2'), lang('Custom field 2 label'), '__custom_field_2_label', lang('Custom field 2'), '__custom_field_2', 'has_custom_field_2'),
        _pg_st_op_billing_line(lang('PO Number'), lang('PO Number') . ':', '', lang('PO Number'), '__po_number', 'has_po_number'),
        _pg_st_op_note('small mb-0', lang('Tax-exempt'), lang('Tax-exempt'), 'is_tax_exempt'),
        _pg_st_el('div', 'mt-2', lang('Custom Billing Form'), array(
            _pg_st_value('p', 'fw-semibold small mb-1', lang('Heading'), lang('Custom billing form title'), '__billing_form_title'),
            _pg_st_el('div', 'small', lang('Custom Billing Form Answers'), array(), array('_bindings' => array('section' => 'billing_form'))),
        ), array('_bindings' => array('eo_visible_if' => 'has_billing_form'))),
    ));
}

// The items and what they come to: recipients, offers, totals, gift cards
// and recurring payments.
function _pg_st_op_order()
{
    return array(
        _pg_st_keep('section:order_recipients'),
        _pg_st_keep('eo_visible_if:has_applied_offers'),
        _pg_st_op_totals('border-top pt-2 mb-4'),
        _pg_st_keep('section:applied_gift_cards'),
        _pg_st_keep('eo_visible_if:has_recurring'),
    );
}

// The payment block, the terms and the purchase button.
function _pg_st_op_finish()
{
    return array(
        _pg_st_keep('eo_visible_if:has_payment'),
        _pg_st_keep('eo_visible_if:has_terms'),
        _pg_st_el('div', 'd-flex flex-wrap gap-2 align-items-center', lang('Buttons'), array(
            _pg_st_el('button', 'btn btn-primary btn-lg', lang('Purchase Button'), array(), array(
                'text' => lang('Purchase Now'),
                '_bindings' => array('eo_visible_if' => 'can_purchase'),
                '_attrs' => array(array('name' => 'type', 'value' => 'submit'), array('name' => 'name', 'value' => 'submit_purchase_now'), array('name' => 'value', 'value' => '1')),
            )),
            _pg_st_link(lang('Back to the cart'), '', lang('Link'), array('href' => '__cart_url')),
        )),
    );
}

// A numbered step of the one-column preview.
function _pg_st_op_step($number, $heading, $children)
{
    return _pg_st_el('section', 'mb-4 pb-4 border-bottom', $heading, array_merge(array(
        _pg_st_el('h2', 'h5 d-flex align-items-center gap-2 mb-3', lang('Heading'), array(
            _pg_st_text('span', 'badge rounded-pill text-bg-primary', lang('Number'), $number),
            _pg_st_text('span', '', lang('Words'), $heading),
        )),
    ), $children));
}

function _pg_st_order_preview()
{
    $empty_loop = array('type' => 'loop_area', 'props' => array(), 'children' => array());

    $columns = _pg_st_container(array(
        _pg_st_keep('eo_visible_if:has_currency_choice'),
        _pg_st_heading('h1', 'h3 mb-4', lang('Order Preview')),
        _pg_st_keep('eo_visible_if:cart_empty'),
        _pg_st_el('div', 'row g-4', lang('Order'), array(
            _pg_st_el('div', 'col-12 col-lg-7', lang('Items'), array(
                _pg_st_el('div', 'card card-body', lang('Items Card'), _pg_st_op_order()),
            )),
            _pg_st_el('div', 'col-12 col-lg-5', lang('Billing and Payment'), array_merge(
                array(_pg_st_op_billing('card card-body mb-4')),
                _pg_st_op_finish()
            )),
        ), array('_bindings' => array('eo_visible_if' => 'has_items'))),
        $empty_loop,
    ));

    $steps = _pg_st_container(array(
        _pg_st_el('div', 'row justify-content-center', lang('Order Preview'), array(
            _pg_st_el('div', 'col-12 col-md-10 col-lg-8', lang('Column'), array(
                _pg_st_keep('eo_visible_if:has_currency_choice'),
                _pg_st_heading('h1', 'h3 mb-4', lang('Order Preview')),
                _pg_st_keep('eo_visible_if:cart_empty'),
                _pg_st_el('div', '', lang('Order'), array(
                    _pg_st_op_step('1', lang('Your order'), _pg_st_op_order()),
                    _pg_st_op_step('2', lang('Billing Information'), array(_pg_st_op_billing(''))),
                    _pg_st_op_step('3', lang('Payment'), _pg_st_op_finish()),
                ), array('_bindings' => array('eo_visible_if' => 'has_items'))),
                $empty_loop,
            )),
        )),
    ));

    return array(
        array('key' => 'columns', 'label' => lang('The order beside the billing and payment'), 'body' => array($columns)),
        array('key' => 'steps', 'label' => lang('Numbered steps in one column'), 'body' => array($steps)),
    );
}

// ── Form detail ─────────────────────────────────────────────────────────

// Said in place of the record when there is none for this visitor.
function _pg_st_form_not_found()
{
    return _pg_st_op_note('text-body-secondary', lang('No Record'), lang('There is no record to show.'), 'record_not_found');
}

function _pg_st_form_item_view()
{
    $plain = _pg_st_container(array(
        _pg_st_el('div', 'row justify-content-center', lang('Record'), array(
            _pg_st_el('div', 'col-12 col-lg-10', lang('Column'), array(
                _pg_st_form_not_found(),
                _pg_st_el('article', 'border-start border-4 border-primary ps-4', lang('Record'), array(_pg_st_keep('loop'))),
            )),
        )),
    ));

    $band = _pg_st_container(array(
        _pg_st_el('div', 'row justify-content-center', lang('Record'), array(
            _pg_st_el('div', 'col-12 col-md-10 col-lg-8', lang('Column'), array(
                _pg_st_form_not_found(),
                _pg_st_el('article', 'card shadow-sm', lang('Form Card'), array(
                    _pg_st_el('div', 'card-header bg-body-tertiary py-3', lang('Card Header'), array(
                        _pg_st_heading('h1', 'h5 mb-0', lang('Record details')),
                    )),
                    _pg_st_el('div', 'card-body p-4', lang('Card Body'), array(_pg_st_keep('loop'))),
                )),
            )),
        )),
    ));

    return array(
        array('key' => 'plain', 'label' => lang('A plain column with a line beside it'), 'body' => array($plain)),
        array('key' => 'band', 'label' => lang('A card with a heading band'), 'body' => array($band)),
    );
}
