<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Visual Page Editor - the layout gallery's "Starters": ready-made,
 * style-neutral layouts a system widget kind ships with the release. A
 * starter is the repeated part of the widget (the children of its loop
 * area) and the classes of the element around it; the editor puts it into
 * the kind's default layout, so the toolbar, filters, pages and messages
 * of that layout stay as they are. Bootstrap classes and theme colours
 * only, no fixed inline style; every word a visitor or the designer reads
 * goes through lang().
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
 * holding the loop area, merged over the default layout's>). A form list
 * marks the element its record's fields go into with props.starterFields:
 * the editor fills it from the chosen form, as the default layout does.
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
    return array('catalog_listing', 'form_list_view', 'comments_recent');
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
