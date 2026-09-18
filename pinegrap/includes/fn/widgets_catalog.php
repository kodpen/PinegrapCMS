<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A module of functions.php: System widgets for the catalog: listing, item view, breadcrumb, cross-sell, RSS and JSON-LD.
//
// Loaded by functions.php through require_once, never on its own. The
// functions were moved here from functions.php as they were; PG_FUNCTIONS_DIR
// is the software directory (what dirname(__FILE__) was in functions.php),
// see the note at the top of functions.php.
if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// Render a system widget's catalog_listing — products from a single product_group
// (or all groups when product_group_id = 0). Loop-area aware, mirrors the structure
// of _render_system_widget_form_list:
//
//   1. _split_widget_tree pulls the loop_area out of the tree (replaced by a marker).
//   2. Static portion renders ONCE.
//   3. Loop_area's children are compiled into a per-product template; each product
//      gets a clone with ^^token^^ placeholders replaced.
//   4. Accumulated loop output replaces the marker in the static HTML.
//
// Token palette (per-product, expanded vs form_list_view to cover the products schema):
//
//   Basic:       ^^__id^^, ^^__name^^, ^^__short_description^^, ^^__description^^,
//                ^^__address_name^^, ^^__detail_url^^, ^^__sort_order^^,
//                ^^__created_at^^
//   Price:       ^^__price^^, ^^__price_formatted^^
//   Image:       ^^__image_url^^, ^^__image_alt^^, ^^__thumbnail_url^^,
//                ^^__gallery_count^^
//   Stock:       ^^__inventory_tracked^^, ^^__inventory_quantity^^,
//                ^^__quantity_available^^, ^^__backorder^^, ^^__out_of_stock^^,
//                ^^__out_of_stock_label^^, ^^__out_of_stock_message^^
//   Identity:    ^^__sku^^ (=mpn), ^^__mpn^^, ^^__gtin^^, ^^__brand^^,
//                ^^__manufacturer^^ (alias of brand)
//   Physical:    ^^__weight^^, ^^__shippable^^, ^^__taxable^^
//   Group:       ^^__category_id^^, ^^__category_name^^, ^^__featured^^, ^^__is_new^^
//   Custom:      ^^__custom_field_1^^ … ^^__custom_field_4^^
//   Reward/SEO:  ^^__reward_points^^, ^^__seo_score^^
//
// Group-context tokens (featured, is_new, sort_order) are populated from
// products_groups_xref. When product_group_id = 0 (all products), they are
// resolved from the FIRST xref row (lowest product_group id) — Phase 1
// trade-off; later phases can expose per-group context if needed.
//
// URL parameters (shared with form_list_view):  page_number, query

// ============================================================================
// Recursive helper: build a nested <ul>/<li> tree of product_groups starting
// at $parent_id. Used by both the new `group_tree` binding section and the
// (legacy) `^^__group_tree_html^^` token. Walks the tree depth-first; each
// row becomes one <li> with the group's display URL inside an <a>.
//
// The tree is scoped to the widget's "world": the caller passes
// $parent_id = configured root (or 0 for global top-level), and recursion
// only descends into that subtree. Ancestors above the configured root are
// intentionally invisible — outside the widget's world.
//
// Active group is highlighted with both an `.active` class on <li> and a
// `.fw-bold` class on the <a> (Bootstrap utility classes — designer can
// re-skin via custom CSS).
//
// Depth cap of 6 prevents infinite recursion if cycles ever appear in the
// data (shouldn't, but defensive).
// ============================================================================
function _pg_render_catalog_group_tree($parent_id, $listing_path_base, $listing_root_url, $active_group_id, $detail_url_prefix, $depth = 0)
{
    if ($depth > 6) return '';
    $rows = db_items(
        "SELECT id, name, address_name, display_type
         FROM product_groups
         WHERE parent_id = '" . (int)$parent_id . "'
           AND enabled = 1
         ORDER BY sort_order ASC, name ASC"
    );
    if (!$rows) return '';
    $lvl_cls = ($depth === 0) ? 'pg-sw-cl-tree pg-sw-cl-tree-root list-unstyled mb-0' : 'pg-sw-cl-tree-sub list-unstyled ms-3';
    $out = '<ul class="' . $lvl_cls . '">';
    foreach ($rows as $r) {
        // URL: browse → drill into the listing on this page;
        //      select → variant-chooser detail page.
        $url = '#';
        if ($r['display_type'] === 'browse' && !empty($r['address_name'])) {
            $url = $listing_path_base . encode_url_path((string)$r['address_name']);
        } elseif ($r['display_type'] === 'select' && $detail_url_prefix !== '' && !empty($r['address_name'])) {
            $url = $detail_url_prefix . encode_url_path((string)$r['address_name']);
        }
        $is_active = ((int)$r['id'] === (int)$active_group_id);
        $out .= '<li class="pg-sw-cl-tree-item' . ($is_active ? ' active' : '') . '">';
        $out .= '<a href="' . h($url) . '" class="text-decoration-none' . ($is_active ? ' fw-bold' : '') . '">'
              . h($r['name']) . '</a>';
        $children_html = _pg_render_catalog_group_tree(
            (int)$r['id'], $listing_path_base, $listing_root_url, $active_group_id, $detail_url_prefix, $depth + 1
        );
        if ($children_html !== '') {
            $out .= $children_html;
        }
        $out .= '</li>';
    }
    $out .= '</ul>';
    return $out;
}

// ============================================================================
// Walk a catalog_listing widget tree and convert section / action / value
// bindings on the designer's nodes into the concrete render-time HTML.
//
// Designer-friendly architecture: instead of telling the designer "drop this
// `^^__breadcrumb_html^^` token here", we let them drop a real <nav> element
// and pick "Breadcrumb" from the data-binding panel. The element keeps its
// own CSS classes / attributes / selector chain — the binding only defines
// what content goes inside.
//
// Bindings recognized:
//   • _bindings.section = '<name>'  (on any container — div, nav, ul, ol)
//       breadcrumb         → emits the active-group breadcrumb HTML
//       group_tree         → emits the nested group navigation tree HTML
//       filter_chips       → emits the active-filter chip strip
//       filters_offcanvas  → emits the offcanvas with all filter forms
//       active_filters     → emits the (smaller) active attr-filter chips
//       attribute_filters  → emits the standalone attribute filter UI block
//
//   • _bindings.action = '<name>'  on btn / form
//       open_filters     (btn)  → button that opens the filters offcanvas
//       clear_filters    (btn)  → anchor to the clear-all-filters URL
//       submit_search    (btn)  → submit button against the search form
//       search_form      (form) → GET form scoped to the catalog page
//
//   • _bindings.value = '<name>'  on input
//       search_query     (input) → name=query, value=current search text
//
// Each section binding mutates the node into a content/custom_html node
// carrying a placeholder token. The token gets resolved by the existing
// $page_tokens substitution loop at the end of the render function — so
// the bindings function doesn't need to compute the actual HTML, just
// stamp the right token.
// ============================================================================
// True when a node exists only to drive a catalog feature the widget's
// options panel has switched OFF.
//
// The designer places the search box / "Filtreler" button / offcanvas as real
// elements in the tree, so they render regardless of the toggles. That
// produced the worst possible combination: the toolbar showed a "Filtreler"
// button that opened a completely empty offcanvas, because the backend had
// no filter forms to put in it. Rather than force the designer to delete and
// re-add those elements every time they flip a toggle, we keep them in the
// tree and simply drop them from the FRONTEND output while the feature is
// off — the designer canvas fades them instead (see style_designer.js,
// _sdCatalogBindingDisabled) so the toggle stays a real on/off switch.
function _pg_catalog_binding_disabled($node, $context)
{
    if (!is_array($node)) return false;
    $b = (isset($node['props']['_bindings']) && is_array($node['props']['_bindings']))
            ? $node['props']['_bindings'] : array();
    if (!$b) return false;

    $search_on  = !empty($context['search_ui_enabled']);
    $filters_on = !empty($context['filters_ui_enabled']);

    $action  = isset($b['action'])  ? (string)$b['action']  : '';
    $section = isset($b['section']) ? (string)$b['section'] : '';
    $value   = isset($b['value'])   ? (string)$b['value']   : '';

    if (!$search_on) {
        // search_form drops the whole subtree (input + submit) in one go.
        if ($action === 'search_form' || $action === 'submit_search') return true;
        if ($value  === 'search_query') return true;
    }
    if (!$filters_on) {
        if ($action === 'open_filters' || $action === 'clear_filters') return true;
        if ($section === 'filters_offcanvas' || $section === 'attribute_filters') return true;
    }
    // filter_chips / active_filters are intentionally NOT dropped: they also
    // carry category and search chips, and render to nothing when empty.
    return false;
}

function _apply_catalog_listing_bindings(&$node, $context, &$bindings_used = null)
{
    if (!is_array($node)) return;
    if (!is_array($bindings_used)) $bindings_used = array();
    $bindings = (isset($node['props']['_bindings']) && is_array($node['props']['_bindings']))
                    ? $node['props']['_bindings'] : array();

    // ── "View / Expand" adaptive label ──────────────────────────────────
    // A single button whose caption depends on where the row's link goes:
    //   • product, or product group with display_type='select'  → detail page
    //   • product group with display_type='browse'              → drills down
    // The two captions are authored by the DESIGNER (props._labelDetail /
    // props._labelExpand) rather than emitted by the server, so they can be
    // renamed, translated per site, or replaced with an icon — the server
    // only decides WHICH of the two applies to each row.
    //
    // Each node gets its OWN token because the captions live on the node: two
    // buttons in the same template can carry different wording. The loop fills
    // these tokens per row (see the render function's label pass).
    if (isset($bindings['text']) && $bindings['text'] === '__link_label') {
        if (!isset($bindings_used['link_labels'])) $bindings_used['link_labels'] = array();
        $tok = '__link_label_' . count($bindings_used['link_labels']);
        $bindings_used['link_labels'][$tok] = array(
            // Empty string is a legitimate choice — the designer may want no
            // caption at all (icon-only button), so we do NOT fall back to a
            // default here. The property panel seeds the defaults instead.
            'detail' => isset($node['props']['_labelDetail']) ? (string)$node['props']['_labelDetail'] : '',
            'expand' => isset($node['props']['_labelExpand']) ? (string)$node['props']['_labelExpand'] : '',
        );
        $node['props']['_bindings']['text'] = $tok;
        $bindings = $node['props']['_bindings'];
    }

    // ── Section markers — replace children with inner-only token ─────────
    // The designer's wrapping element (tag, cssClass, attrs) is preserved.
    // We swap their children for a single custom_html node carrying the
    // inner-only HTML token, which the $page_tokens substitution at the
    // end of catalog_listing resolves to the actual backend-computed
    // markup. This keeps the designer in full control of styling while
    // the inner content comes from the backend.
    //
    // Special case: filters_offcanvas REQUIRES specific Bootstrap classes
    // and an exact id to interop with the open_filters button. We auto-
    // stamp those onto the designer's element so they don't have to.
    //
    // $bindings_used (by-ref, populated as we walk): the catalog widget
    // renderer reads this set after the binding pass to SKIP auto-stitched
    // legacy duplicates — e.g. if `section_breadcrumb` is set, the body
    // composition omits the legacy $breadcrumb_html prefix so the visitor
    // doesn't see two breadcrumbs.
    if (isset($bindings['section'])) {
        $section_inner_token_map = array(
            'breadcrumb'        => '__breadcrumb_inner_html',
            'group_tree'        => '__group_tree_inner_html',
            'filter_chips'      => '__filter_chips_inner_html',
            'filters_offcanvas' => '__filters_offcanvas_inner_html',
            'active_filters'    => '__active_filters_inner_html',
            'attribute_filters' => '__attribute_filters_inner_html',
        );
        $sec = $bindings['section'];
        if (isset($section_inner_token_map[$sec])) {
            $bindings_used['section_' . $sec] = true;
            // For filters_offcanvas: stamp Bootstrap offcanvas classes + id
            // onto the designer's element. Without these the open_filters
            // button can't find its target and the offcanvas behaviour breaks.
            //
            // CRITICAL: `id` is a top-level prop in the tree node, NOT inside
            // _attrs. _render_extra_attrs explicitly SKIPS 'id' in the _attrs
            // loop (line ~24469) and only emits the top-level $props['id'].
            // So we must write to $node['props']['id'] directly. Same for
            // tabindex (no special handling — it goes through _attrs fine)
            // and aria-labelledby (also _attrs).
            if ($sec === 'filters_offcanvas' && !empty($context['filters_offcanvas_id'])) {
                $existing_cls = isset($node['props']['cssClass']) ? (string)$node['props']['cssClass'] : '';
                $required_cls = array('offcanvas', 'offcanvas-start', 'pg-sw-filters-offcanvas');
                $cls_arr = preg_split('/\s+/', $existing_cls);
                $cls_arr = array_filter($cls_arr, function ($c) { return $c !== ''; });
                foreach ($required_cls as $rc) {
                    if (!in_array($rc, $cls_arr, true)) $cls_arr[] = $rc;
                }
                $node['props']['cssClass'] = implode(' ', $cls_arr);
                // id goes directly on props (NOT in _attrs — render skips it there).
                $node['props']['id']       = $context['filters_offcanvas_id'];
                // tabindex + aria-labelledby through _attrs (those keys aren't skipped).
                $existing_attrs = (isset($node['props']['_attrs']) && is_array($node['props']['_attrs']))
                                    ? $node['props']['_attrs'] : array();
                $managed_attrs  = array('tabindex' => 1, 'aria-labelledby' => 1);
                $kept_attrs     = array();
                foreach ($existing_attrs as $a) {
                    if (is_array($a) && isset($a['name']) && !isset($managed_attrs[$a['name']])) {
                        $kept_attrs[] = $a;
                    }
                }
                $kept_attrs[] = array('name' => 'tabindex',        'value' => '-1');
                $kept_attrs[] = array('name' => 'aria-labelledby', 'value' => $context['filters_offcanvas_id'] . 'Title');
                $node['props']['_attrs'] = $kept_attrs;
            }
            // Wrapper preserved; children replaced with inner-only token.
            $node['children'] = array(
                array(
                    'type'  => 'content',
                    'props' => array(
                        'contentType' => 'custom_html',
                        'html'        => '^^' . $section_inner_token_map[$sec] . '^^',
                    ),
                    'children' => array(),
                ),
            );
            return;  // wrapper preserved; no further recursion into the now-replaced inner.
        }
    }

    // ── Component btn: action bindings ──────────────────────────────────
    if (isset($node['type']) && $node['type'] === 'component'
        && isset($node['props']['componentType']) && $node['props']['componentType'] === 'btn'
        && isset($bindings['action'])) {
        $action = $bindings['action'];

        // Helper: replace boilerplate default text with a meaningful label.
        // Saved trees from older starters (or freshly-dragged btn components)
        // ship with `text: 'Click Me'` or 'Button'. When the designer pins
        // an action, we substitute a Turkish label so the visitor doesn't
        // see "Click Me" sitting on a Filtreler button. The designer can
        // still set a custom text — we only override the boilerplate.
        $_pg_default_text = function ($current, $fallback) {
            if (!is_string($current)) return $fallback;
            $trim = trim($current);
            if ($trim === '' || $trim === 'Click Me' || $trim === 'Button') return $fallback;
            return $current;
        };

        if ($action === 'catalog_add_to_cart') {
            // Per-card "Sepete Ekle" — bindable replacement for the legacy
            // `^^__add_to_cart_button^^` raw-HTML token. Mutate to
            // <button type="submit"> + stamp `data-pg-catalog-atc="1"` so
            // the catalog per-row render (in the product loop) can wrap
            // it in a mini-form pointing at catalog_detail.php. Designer
            // styles the button with the standard Button options panel
            // (variant, size, outline, icon …) — no special UI needed.
            $bindings_used['action_catalog_add_to_cart'] = true;
            $node['props']['btnElement'] = 'button';
            $node['props']['btnType']    = 'submit';
            $node['props']['href']       = '';
            $existing = (isset($node['props']['_attrs']) && is_array($node['props']['_attrs']))
                            ? $node['props']['_attrs'] : array();
            $managed  = array('data-pg-catalog-atc' => 1);
            $kept     = array();
            foreach ($existing as $a) {
                if (is_array($a) && isset($a['name']) && !isset($managed[$a['name']])) {
                    $kept[] = $a;
                }
            }
            $kept[] = array('name' => 'data-pg-catalog-atc', 'value' => '1');
            $node['props']['_attrs'] = $kept;
            $node['props']['text'] = $_pg_default_text(
                isset($node['props']['text']) ? $node['props']['text'] : '',
                lang('Add to Cart')
            );
        }
        elseif ($action === 'open_filters' && !empty($context['filters_offcanvas_id'])) {
            // Button that opens the filters offcanvas via Bootstrap data-bs-*.
            $bindings_used['action_open_filters'] = true;
            $node['props']['btnElement'] = 'button';
            $node['props']['btnType']    = 'button';
            $node['props']['text']       = $_pg_default_text(
                isset($node['props']['text']) ? $node['props']['text'] : '',
                lang('Filters')
            );
            $existing = (isset($node['props']['_attrs']) && is_array($node['props']['_attrs']))
                            ? $node['props']['_attrs'] : array();
            $managed  = array('data-bs-toggle' => 1, 'data-bs-target' => 1, 'aria-controls' => 1);
            $kept     = array();
            foreach ($existing as $a) {
                if (is_array($a) && isset($a['name']) && !isset($managed[$a['name']])) {
                    $kept[] = $a;
                }
            }
            $kept[] = array('name' => 'data-bs-toggle', 'value' => 'offcanvas');
            $kept[] = array('name' => 'data-bs-target', 'value' => '#' . $context['filters_offcanvas_id']);
            $kept[] = array('name' => 'aria-controls', 'value' => $context['filters_offcanvas_id']);
            $node['props']['_attrs'] = $kept;
        } elseif ($action === 'clear_filters' && !empty($context['clear_filters_url'])) {
            // Plain anchor to the precomputed clear-all URL.
            $bindings_used['action_clear_filters'] = true;
            $node['props']['btnElement'] = 'a';
            $node['props']['href']       = $context['clear_filters_url'];
            $node['props']['text']       = $_pg_default_text(
                isset($node['props']['text']) ? $node['props']['text'] : '',
                lang('Clear Filters')
            );
        } elseif ($action === 'submit_search') {
            // Submit button — visitor pressing Enter inside the search input
            // already submits the form, but a click target makes the search
            // discoverable on touch devices.
            $bindings_used['action_submit_search'] = true;
            $node['props']['btnElement'] = 'button';
            $node['props']['btnType']    = 'submit';
            $node['props']['text']       = $_pg_default_text(
                isset($node['props']['text']) ? $node['props']['text'] : '',
                lang('Search')
            );
        }
    }

    // ── Semantic <form>: search_form binding ────────────────────────────
    // Designer dropped a real <form>; we set method=GET + action=current
    // listing URL and inject hidden inputs that preserve every other GET
    // param the page is currently using (sort, filters, page_number, etc.)
    // so submitting the search form doesn't lose unrelated query state.
    if (isset($node['type']) && $node['type'] === 'semantic'
        && isset($node['props']['tag']) && $node['props']['tag'] === 'form'
        && isset($bindings['action']) && $bindings['action'] === 'search_form') {
        $bindings_used['action_search_form'] = true;
        $existing = (isset($node['props']['_attrs']) && is_array($node['props']['_attrs']))
                        ? $node['props']['_attrs'] : array();
        $managed  = array('action' => 1, 'method' => 1, 'role' => 1);
        $kept     = array();
        foreach ($existing as $a) {
            if (is_array($a) && isset($a['name']) && !isset($managed[$a['name']])) {
                $kept[] = $a;
            }
        }
        $kept[] = array('name' => 'action', 'value' => isset($context['search_action_url']) ? $context['search_action_url'] : '');
        $kept[] = array('name' => 'method', 'value' => 'get');
        $kept[] = array('name' => 'role',   'value' => 'search');
        $node['props']['_attrs'] = $kept;
        // Inject hidden inputs as a custom_html FIRST child. Marker
        // `_pg_search_hidden` prevents duplicates on subsequent passes.
        $has_hidden = false;
        if (!empty($node['children']) && is_array($node['children'])) {
            foreach ($node['children'] as $ch) {
                if (is_array($ch) && isset($ch['type']) && $ch['type'] === 'content'
                    && isset($ch['props']['contentType']) && $ch['props']['contentType'] === 'custom_html'
                    && !empty($ch['props']['_pg_search_hidden'])) {
                    $has_hidden = true; break;
                }
            }
        }
        if (!$has_hidden && !empty($context['search_hidden_html'])) {
            $hidden_node = array(
                'type'     => 'content',
                'props'    => array(
                    'contentType'        => 'custom_html',
                    'html'               => $context['search_hidden_html'],
                    '_pg_search_hidden'  => true,
                ),
                'children' => array(),
            );
            if (!isset($node['children']) || !is_array($node['children'])) $node['children'] = array();
            array_unshift($node['children'], $hidden_node);
        }
    }

    // ── Semantic <input>: search_query binding ──────────────────────────
    // Sets type=search (semantic), name=query (or whatever the catalog uses),
    // and value=current query so the field round-trips after submission.
    if (isset($node['type']) && $node['type'] === 'semantic'
        && isset($node['props']['tag']) && $node['props']['tag'] === 'input'
        && isset($bindings['value']) && $bindings['value'] === 'search_query') {
        $bindings_used['value_search_query'] = true;
        $existing = (isset($node['props']['_attrs']) && is_array($node['props']['_attrs']))
                        ? $node['props']['_attrs'] : array();
        $managed  = array('type' => 1, 'name' => 1, 'value' => 1, 'maxlength' => 1);
        $kept     = array();
        foreach ($existing as $a) {
            if (is_array($a) && isset($a['name']) && !isset($managed[$a['name']])) {
                $kept[] = $a;
            }
        }
        $kept[] = array('name' => 'type',      'value' => 'search');
        $kept[] = array('name' => 'name',      'value' => isset($context['search_query_param']) ? $context['search_query_param'] : 'query');
        $kept[] = array('name' => 'value',     'value' => isset($context['search_query']) ? $context['search_query'] : '');
        $kept[] = array('name' => 'maxlength', 'value' => '100');
        $node['props']['_attrs'] = $kept;
    }

    if (!empty($node['children']) && is_array($node['children'])) {
        // Drop children whose feature is switched off BEFORE recursing, so a
        // disabled search form takes its input and submit button with it
        // instead of leaving orphans behind.
        $kept_children = array();
        foreach ($node['children'] as $child) {
            if (is_array($child) && _pg_catalog_binding_disabled($child, $context)) continue;
            $kept_children[] = $child;
        }
        $node['children'] = $kept_children;

        foreach ($node['children'] as &$child) {
            if (is_array($child)) _apply_catalog_listing_bindings($child, $context, $bindings_used);
        }
        unset($child);
    }
}

function _render_system_widget_catalog_listing($product_group_id, $tree_json, $widget_id, $cfg = array())
{
    $product_group_id = (int)$product_group_id;  // 0 = all groups
    $widget_id        = (int)$widget_id;
    if ($tree_json === '' || $tree_json === null) return '';
    if (!is_array($cfg)) $cfg = array();

    // ── Resolve config values with defaults ───────────────────────────────
    $items_per_page = isset($cfg['items_per_page']) ? (int)$cfg['items_per_page'] : 10;
    if ($items_per_page <= 0)  $items_per_page = 10;
    if ($items_per_page > 500) $items_per_page = 500;

    $max_results = isset($cfg['max_results']) ? (int)$cfg['max_results'] : 0;
    if ($max_results < 0) $max_results = 0;
    if ($max_results > 100000) $max_results = 100000;

    $search_enabled = !empty($cfg['search_enabled']);
    $search_width_map = array('full' => 'w-100', 'half' => 'w-50', 'quarter' => 'w-25', 'auto' => 'w-auto');
    $search_width_key = (isset($cfg['search_width']) && isset($search_width_map[$cfg['search_width']]))
                            ? $cfg['search_width'] : 'full';
    $search_width_cls = $search_width_map[$search_width_key];
    $search_label = (isset($cfg['search_label']) && is_string($cfg['search_label']) && $cfg['search_label'] !== '')
                        ? substr($cfg['search_label'], 0, 100) : (string)lang('Search...');

    $empty_message = (isset($cfg['empty_message']) && is_string($cfg['empty_message']) && $cfg['empty_message'] !== '')
                        ? $cfg['empty_message'] : (string)lang('No products found.');

    // Catalog order-by whitelist. Defaults to sort_order (per-group ranking) which
    // matches user expectation "as arranged in product_groups".
    $order_by_field    = isset($cfg['order_by_field']) ? (string)$cfg['order_by_field'] : 'sort_order';
    $order_by_direction = (isset($cfg['order_by_direction']) && strtoupper($cfg['order_by_direction']) === 'ASC') ? 'ASC' : 'DESC';
    $order_by_2_field    = isset($cfg['order_by_2_field']) ? (string)$cfg['order_by_2_field'] : '';
    $order_by_2_direction = (isset($cfg['order_by_2_direction']) && strtoupper($cfg['order_by_2_direction']) === 'ASC') ? 'ASC' : 'DESC';

    // ── Phase 2 config: drill-down + frontend sort/filter ─────────────────
    // Group click behaviour:
    //   none       → groups not navigable; widget always lists products of $product_group_id
    //   drill_down → ?cat=N URL param activates a child group in-place (alt groups + their products)
    //   page       → group click redirects to a dedicated catalog page (group_nav_page_id)
    // Default is 'drill_down', NOT 'none' — a catalog that lists only products
    // and silently swallows its sub-categories doesn't match what anyone means
    // by "catalog". The legacy catalog page type has always rendered browsable
    // sub-groups alongside products in the same grid (see /shop-sidebar), and
    // that's the behaviour a designer expects the moment they drop the widget.
    // 'none' remains available as an explicit opt-out for single-group product
    // strips (e.g. a "featured products" band on a landing page).
    $group_navigation = isset($cfg['group_navigation']) ? (string)$cfg['group_navigation'] : 'drill_down';
    if (!in_array($group_navigation, array('none', 'drill_down', 'page'), true)) $group_navigation = 'drill_down';
    $group_nav_page_id = isset($cfg['group_nav_page_id']) ? (int)$cfg['group_nav_page_id'] : 0;

    $sort_control_enabled = !empty($cfg['sort_control_enabled']);
    // Whitelist of which sort options the dropdown should expose. Stored as
    // an array of canonical keys; unknown keys are silently dropped.
    $valid_sort_options = array('sort_order', 'name_asc', 'name_desc', 'price_asc', 'price_desc', 'newest');
    $sort_options = array();
    if (isset($cfg['sort_options']) && is_array($cfg['sort_options'])) {
        foreach ($cfg['sort_options'] as $so) {
            if (in_array($so, $valid_sort_options, true)) $sort_options[] = $so;
        }
    }
    if (!$sort_options) $sort_options = $valid_sort_options;  // empty → enable all by default

    $price_filter_enabled = !empty($cfg['price_filter_enabled']);
    $price_filter_step    = isset($cfg['price_filter_step']) ? max(1, (int)$cfg['price_filter_step']) : 1;

    $stock_filter_enabled = !empty($cfg['stock_filter_enabled']);
    $stock_filter_default = !empty($cfg['stock_filter_default']);

    // ── Phase 2.5 config: attribute filters ──────────────────────────────
    // show_attribute_filters  — display attribute-based filter UI (checkboxes/buttons).
    // attribute_filter_style  — 'checkboxes' (default) or 'buttons'.
    $attr_filter_enabled = !empty($cfg['show_attribute_filters']);
    $attr_filter_style   = (isset($cfg['attribute_filter_style']) && $cfg['attribute_filter_style'] === 'buttons') ? 'buttons' : 'checkboxes';

    // ── Select-type group config ──────────────────────────────────────────
    // Mode (browse vs. select) is derived per-row from product_groups.display_type
    // at render time — no widget-level toggle. These three settings only apply
    // when a row turns out to be a select-type group.
    $attribute_display = (isset($cfg['attribute_display']) && $cfg['attribute_display'] === 'dropdown') ? 'dropdown' : 'buttons';
    $show_add_to_cart  = !empty($cfg['show_add_to_cart']);
    $oos_behavior      = (isset($cfg['out_of_stock_behavior'])
                            && in_array($cfg['out_of_stock_behavior'], array('hide', 'disable', 'show'), true))
                            ? $cfg['out_of_stock_behavior'] : 'disable';

    // ── URL params (shared) ───────────────────────────────────────────────
    $page_param   = 'page_number';
    $query_param  = 'query';
    $current_page = isset($_GET[$page_param]) ? max(1, (int)$_GET[$page_param]) : 1;
    $search_query = ($search_enabled && isset($_GET[$query_param])) ? trim((string)$_GET[$query_param]) : '';
    if (strlen($search_query) > 100) $search_query = substr($search_query, 0, 100);

    // ── Active group resolution (path-based) ──────────────────────────────
    // Drill-down state lives in the URL path: /{liste_page}/{group_address_name}.
    // Router parses the leading segment as $page_name; the trailing segment is
    // the active group's address_name. We strip everything after the first '/'
    // (no multi-level drilldown URLs — folder hierarchy is reconstructed via
    // product_groups.parent_id, not the URL).
    //
    // Falls back to the widget's configured product_group_id when:
    //   - no path segment was supplied,
    //   - the segment is empty or too long, or
    //   - the slug doesn't match any enabled product group.
    $active_group_id   = $product_group_id;
    $url_drill_active  = false;  // true when URL path resolved an active group
    $url_segment_invalid = false;  // true when URL provided a segment but no group matched
    $base_path         = defined('PATH') ? PATH : '/';
    $page_slug_full    = isset($_GET['page']) ? (string)$_GET['page'] : '';
    $listing_page_slug = $page_slug_full;
    $_lps_pos = mb_strpos($listing_page_slug, '/', 0, 'UTF-8');
    if ($_lps_pos !== false) {
        $listing_page_slug = mb_substr($listing_page_slug, 0, $_lps_pos, 'UTF-8');
    }
    // Catalog hosted on the HOMEPAGE has no page slug ($listing_page_slug
    // empty). Path-based drill URLs like `/<group-slug>/` would 404 because
    // the router treats unknown slugs as missing pages — a `/<slug>` URL
    // would need a Pinegrap page named exactly that slug to exist.
    //
    // For the homepage case we therefore fall back to a query-string drill:
    //   /?cat=<group-slug>
    // The flag below switches both URL building (links use `?cat=…`) and
    // active group detection (read from `?cat=…` instead of the path).
    $is_homepage_catalog = ($listing_page_slug === '');
    // Base URL for drill-down links on this listing page.
    // When the catalog widget is on the homepage the page slug is empty —
    // in that case $base_path already ends with '/', so we MUST NOT append
    // another '/' or the resulting URL becomes '//<group_slug>' (broken,
    // routes to a different host in some browsers).
    $listing_path_base = $base_path . ($listing_page_slug !== ''
        ? encode_url_path($listing_page_slug) . '/'
        : '?cat=');
    // Bare URL for the listing page (no group segment) — used by "clear group"
    // / breadcrumb-root / chip-clear links. For homepage catalog this is
    // just $base_path (e.g. '/').
    $listing_root_url  = $base_path . encode_url_path($listing_page_slug);

    // Resolve the active group from the URL.
    // Path-based mode (catalog on a dedicated page):
    //     `/<page-slug>/<group-slug>` — split at the first '/'.
    // Query-based mode (homepage catalog):
    //     `/?cat=<group-slug>` — read from $_GET['cat'].
    $segment = '';
    if ($is_homepage_catalog) {
        $segment = isset($_GET['cat']) ? trim((string)$_GET['cat']) : '';
    } elseif ($page_slug_full !== '' && $_lps_pos !== false) {
        $segment = mb_substr($page_slug_full, $_lps_pos + 1, null, 'UTF-8');
        $next_slash = mb_strpos($segment, '/', 0, 'UTF-8');
        if ($next_slash !== false) $segment = mb_substr($segment, 0, $next_slash, 'UTF-8');
        $segment = urldecode(trim($segment));
    }
    if ($segment !== '' && strlen($segment) <= 200) {
        $found_id = (int)db_value(
            "SELECT id FROM product_groups
             WHERE address_name = '" . e($segment) . "'
               AND enabled = 1
             LIMIT 1"
        );
        if ($found_id > 0) {
            $active_group_id  = $found_id;
            $url_drill_active = true;
        } else {
            // Segment provided but no enabled group matched — treat as
            // a 404-ish URL. Catalog renders an alert instead of falling
            // through to the configured default group (which would be
            // misleading: visitor typed a category that doesn't exist).
            $url_segment_invalid = true;
        }
    }

    // Bail early with an alert when the URL carried an unmatched group segment.
    // We do this BEFORE doing any expensive query / filter / pagination work.
    if ($url_segment_invalid) {
        return '<div class="pg-sw-cl-not-found alert alert-warning" role="alert">'
             . h($empty_message)
             . '</div>';
    }

    // ?sort=... — only honored when sort_control_enabled
    $sort_url_key = ($sort_control_enabled && isset($_GET['sort'])) ? (string)$_GET['sort'] : '';
    if (!in_array($sort_url_key, $valid_sort_options, true)) $sort_url_key = '';

    // ?pmin / ?pmax — values are in display units (TL, USD), converted to cents for SQL
    $pmin_param = ($price_filter_enabled && isset($_GET['pmin']) && $_GET['pmin'] !== '') ? max(0, (int)$_GET['pmin']) : null;
    $pmax_param = ($price_filter_enabled && isset($_GET['pmax']) && $_GET['pmax'] !== '') ? max(0, (int)$_GET['pmax']) : null;
    if ($pmin_param !== null && $pmax_param !== null && $pmax_param < $pmin_param) {
        $pmax_param = $pmin_param;  // swap-resistant: clamp upper to at least lower
    }

    // ?stock — explicit override of the configured default
    if ($stock_filter_enabled) {
        $stock_filter_active = isset($_GET['stock']) ? ((int)$_GET['stock'] === 1) : $stock_filter_default;
    } else {
        $stock_filter_active = false;
    }

    // ?pg_attr_{attr_id}={option_id} — attribute filter params.
    // Uses pg_attr_ prefix to avoid collision with page/form/generic params.
    // Only one option per attribute is supported; multiple attributes can be
    // combined (AND semantics — product must satisfy all selected attributes).
    $active_attr_filters = array();  // [attribute_id(int) => option_id(int)]
    if ($attr_filter_enabled) {
        foreach ((array)$_GET as $_pgak => $_pgav) {
            if (strncmp($_pgak, 'pg_attr_', 8) === 0) {
                $_pgaid = (int)substr($_pgak, 8);
                $_pgaov = (int)$_pgav;
                if ($_pgaid > 0 && $_pgaov > 0) {
                    $active_attr_filters[$_pgaid] = $_pgaov;
                }
            }
        }
    }

    // Decode + split the widget tree into static + loop-template halves.
    $tree_decoded = json_decode($tree_json, true);
    if (!is_array($tree_decoded)) return '';
    // Per-widget messages — catalog_listing has no dedicated POST handler /
    // liveform of its own, so leave formName empty (collects every pending
    // session message). Auto-prepends a fallback messages node when the
    // designer hasn't placed one.
    _pg_inject_messages_node($tree_decoded, '');

    // ── Designer bindings pre-process ────────────────────────────────────
    // Convert section / action / value bindings on user-placed nodes into
    // concrete attrs + placeholder tokens. Section bindings collapse into
    // custom_html nodes carrying tokens like ^^__breadcrumb_html^^ that the
    // $page_tokens substitution at the end of this function resolves.
    //
    // Context bag: precompute the deterministic bits the binding pass needs
    // (offcanvas id, clear-all URL, search field name + current value, hidden
    // GET pairs to preserve other params). Anything that depends on later-
    // computed HTML stays out of the bag — those bind via tokens.
    $_pg_off_id = 'pgFilters' . (int)$widget_id;
    $_pg_clear_query_keys = array('cat', 'sort', 'pmin', 'pmax', 'stock', $query_param, $page_param);
    if (!empty($_GET) && is_array($_GET)) {
        foreach (array_keys($_GET) as $_pg_gk) {
            if (strncmp($_pg_gk, 'pg_attr_', 8) === 0) $_pg_clear_query_keys[] = $_pg_gk;
        }
    }
    $_pg_search_hidden = '';
    if (!empty($_GET) && is_array($_GET)) {
        foreach ($_GET as $_pg_sk => $_pg_sv) {
            if ($_pg_sk === $query_param || $_pg_sk === $page_param) continue;
            if (is_array($_pg_sv)) continue;
            $_pg_search_hidden .= '<input type="hidden" name="' . h($_pg_sk) . '" value="' . h((string)$_pg_sv) . '">';
        }
    }
    $_pg_bindings_used = array();
    _apply_catalog_listing_bindings($tree_decoded, array(
        'filters_offcanvas_id' => $_pg_off_id,
        'clear_filters_url'    => '?' . _build_clear_filters_query($_pg_clear_query_keys),
        'search_action_url'    => $listing_root_url,
        'search_query_param'   => $query_param,
        'search_query'         => $search_query,
        'search_hidden_html'   => $_pg_search_hidden,
        // Feature switches — nodes bound to a switched-off feature are
        // dropped from the output entirely (see _pg_catalog_binding_disabled).
        'search_ui_enabled'    => $search_enabled,
        'filters_ui_enabled'   => ($price_filter_enabled || $stock_filter_enabled || $attr_filter_enabled),
    ), $_pg_bindings_used);

    $split = _split_widget_tree($tree_decoded);

    if ($split['loop_children'] === null) {
        $loop_template = trim(_render_tree_node($tree_decoded, 0, 0));
        $static_html   = '';
    } else {
        $loop_template = '';
        foreach ($split['loop_children'] as $child) {
            $loop_template .= _render_tree_node($child, 0, 0);
        }
        $loop_template = trim($loop_template);
        $static_html   = trim(_render_tree_node($split['static_tree'], 0, 0));
    }

    // ── Resolve order_by → SQL clause (whitelist!) ────────────────────────
    // Catalog's sort_order lives on products_groups_xref (per-group), so a
    // group filter is required for it to be meaningful. Without group, we
    // fall back to the product id which is at least stable.
    //
    // URL `?sort=...` (when sort_control_enabled) takes precedence over the
    // configured order_by; otherwise the two-level configured order applies.
    $order_join_xref = false;
    $_resolve_order = function ($field, $dir) use (&$order_join_xref, $active_group_id) {
        if ($field === '' || $field === null) return null;
        switch ($field) {
            case 'id':           return "products.id $dir";
            case 'name':         return "products.name $dir";
            case 'price':        return "products.price $dir";
            case 'created_at':   return "products.timestamp $dir";
            case 'sort_order':
                if ($active_group_id > 0) { $order_join_xref = true; return "xref.sort_order $dir"; }
                return "products.id $dir";  // no group context → stable fallback
        }
        return null;
    };
    // URL sort key → (field, dir) mapping
    $url_sort_map = array(
        'sort_order' => array('sort_order', 'ASC'),
        'name_asc'   => array('name',       'ASC'),
        'name_desc'  => array('name',       'DESC'),
        'price_asc'  => array('price',      'ASC'),
        'price_desc' => array('price',      'DESC'),
        'newest'     => array('created_at', 'DESC'),
    );
    $order_parts = array();
    if ($sort_url_key !== '' && isset($url_sort_map[$sort_url_key])) {
        $r = $_resolve_order($url_sort_map[$sort_url_key][0], $url_sort_map[$sort_url_key][1]);
        if ($r) $order_parts[] = $r;
    }
    if (!$order_parts) {
        $primary = $_resolve_order($order_by_field, $order_by_direction);
        $order_parts[] = $primary ? $primary : "products.id $order_by_direction";
        if ($order_by_2_field !== '') {
            $secondary = $_resolve_order($order_by_2_field, $order_by_2_direction);
            if ($secondary) $order_parts[] = $secondary;
        }
    }
    // Final tie-breaker so identical primary/secondary values don't reorder
    // mid-page on subsequent loads.
    $order_parts[] = "products.id DESC";
    $order_by_sql  = implode(', ', $order_parts);

    // ── Build the unified WHERE conditions array ─────────────────────────
    // Always: products.enabled = 1
    // Optional: search (name + short/full descriptions)
    //           price filter (BETWEEN — converts display units → cents)
    //           stock filter (excludes out-of-stock)
    $search_active = $search_enabled && $search_query !== '';
    $extra_conditions = '';
    if ($search_active) {
        // Multi-field search — visitor's text matches against ANY of the
        // searchable product columns OR any of the product's attribute
        // option labels. Previously we only checked name + short/full
        // description, which missed products findable by tag (keywords /
        // meta_keywords), by URL slug (address_name), or by variant value
        // ("yeşil" should find products with Renk=Yeşil attribute). Result
        // was inconsistent search ("kısmen çalışıyor"). Wider field set
        // makes search match visitor expectations across the board.
        $q_esc = e($search_query);
        $extra_conditions .= " AND (
              products.name              LIKE '%$q_esc%'
           OR products.short_description LIKE '%$q_esc%'
           OR products.full_description  LIKE '%$q_esc%'
           OR products.details           LIKE '%$q_esc%'
           OR products.keywords          LIKE '%$q_esc%'
           OR products.meta_keywords     LIKE '%$q_esc%'
           OR products.meta_description  LIKE '%$q_esc%'
           OR products.address_name      LIKE '%$q_esc%'
           OR EXISTS (
                SELECT 1
                FROM products_attributes_xref _pas
                INNER JOIN product_attribute_options _pao
                  ON _pao.id = _pas.option_id
                WHERE _pas.product_id = products.id
                  AND _pao.label LIKE '%$q_esc%'
              )
        ) ";
    }
    if ($price_filter_enabled) {
        if ($pmin_param !== null) {
            $extra_conditions .= " AND products.price >= '" . (int)($pmin_param * 100) . "' ";
        }
        if ($pmax_param !== null) {
            $extra_conditions .= " AND products.price <= '" . (int)($pmax_param * 100) . "' ";
        }
    }
    if ($stock_filter_active) {
        // "Only items in stock" — invert the out-of-stock derivation.
        $extra_conditions .= " AND NOT (products.inventory = 1
                                    AND products.inventory_quantity <= 0
                                    AND products.backorder = 0) ";
    }

    // Attribute EXISTS filters — one correlated subquery per selected
    // {attribute_id, option_id} pair.  Multiple attributes are AND-combined:
    // a product must satisfy every selected attribute to appear in results.
    if ($active_attr_filters) {
        foreach ($active_attr_filters as $_af_aid => $_af_oid) {
            $extra_conditions .= " AND EXISTS (
                SELECT 1 FROM products_attributes_xref _pax
                WHERE _pax.product_id   = products.id
                  AND _pax.attribute_id = '" . (int)$_af_aid . "'
                  AND _pax.option_id    = '" . (int)$_af_oid . "'
            ) ";
        }
    }

    // Backwards-compat alias so the rest of the function keeps using $search_where
    $search_where = $extra_conditions;

    // Group filter: when set, INNER JOIN xref WHERE xref.product_group = X.
    // Use $active_group_id (URL-overridable in drill-down mode) instead of
    // the bare config value — that's what makes ?cat=N actually filter rows.
    //
    // Scope depends on what the visitor is doing:
    //
    //   • Plain browsing → DIRECT members only. The grid is a folder listing:
    //     sub-category cards plus the products that live in this group itself.
    //     Pulling the whole subtree here would dump every descendant product
    //     next to the very category cards that lead to them.
    //
    //   • Search or any filter active → WHOLE SUBTREE. The moment the visitor
    //     narrows by attribute / price / stock / text they've stopped browsing
    //     folders and started asking "show me matching products, wherever they
    //     are". Direct-only scoping made that combination useless: the filter
    //     offcanvas offers options collected across the subtree (see the
    //     attribute query below), so picking "Renk: Yeşil" at a catalog root
    //     returned zero results even though the option was on the list — the
    //     chairs live in a sub-category. Widening only while filtering keeps
    //     the browse experience intact and makes the filters mean what they say.
    $_pg_narrowing_active = ($search_active
                             || !empty($active_attr_filters)
                             || $pmin_param !== null
                             || $pmax_param !== null
                             || $stock_filter_active);
    $group_join  = '';
    $group_where = '';
    if ($active_group_id > 0) {
        if ($_pg_narrowing_active) {
            $_pg_scope    = _pg_catalog_group_subtree_ids(array($active_group_id));
            $_pg_scope_ids = isset($_pg_scope[$active_group_id])
                                ? $_pg_scope[$active_group_id]
                                : array($active_group_id);
            $_pg_scope_list = implode(',', array_map('intval', $_pg_scope_ids));
            $group_join = "INNER JOIN products_groups_xref xref
                              ON xref.product = products.id
                             AND xref.product_group IN ($_pg_scope_list)";
        } else {
            $group_join = "INNER JOIN products_groups_xref xref
                              ON xref.product = products.id
                             AND xref.product_group = '" . (int)$active_group_id . "'";
        }
        $group_where = '';
    } elseif ($order_join_xref) {
        $group_join = "LEFT JOIN products_groups_xref xref ON xref.product = products.id";
    }

    // ── Build the search box HTML ─────────────────────────────────────────
    $search_html = '';
    if ($search_enabled) {
        $hidden = '';
        if (!empty($_GET) && is_array($_GET)) {
            foreach ($_GET as $k => $v) {
                if ($k === $query_param || $k === $page_param) continue;
                if (is_array($v)) continue;
                $hidden .= '<input type="hidden" name="' . h($k) . '" value="' . h((string)$v) . '">';
            }
        }
        $search_html =
            '<form method="get" class="pg-sw-search mb-3" role="search">' .
                $hidden .
                '<div class="input-group input-group-sm ' . $search_width_cls . '">' .
                    '<input type="text" class="form-control" name="' . h($query_param) . '" ' .
                           'value="' . h($search_query) . '" placeholder="' . h($search_label) . '" maxlength="100">' .
                    '<button type="submit" class="btn btn-outline-secondary"><span class="bi bi-search"></span></button>' .
                    ($search_query !== ''
                        ? '<a class="btn btn-outline-secondary" href="?' . h(_pg_sw_build_query($query_param, '', $page_param, '')) . '"><span class="bi bi-x-lg"></span></a>'
                        : '') .
                '</div>' .
            '</form>';
    }

    $empty_html = '<div class="pg-sw-empty text-muted py-3">' . h($empty_message) . '</div>';

    // ── Resolve detail page URL prefix (catalog uses /slug/<address_name> path style) ──
    // INTENTIONALLY independent of $_GET / pagination state — see the form_list_view
    // counterpart for the longer rationale.
    //
    // Computed early so that breadcrumb / group_tree / child_groups (built
    // further down) have access even when we hit a "no products" early-exit.
    // The $detail_url_prefix is needed by the group tree to choose between
    // listing-drill URLs and select-group variant-chooser URLs.
    $detail_page_id    = isset($cfg['detail_page_id']) ? (int)$cfg['detail_page_id'] : 0;
    $detail_url_prefix = '';
    if ($detail_page_id > 0) {
        $detail_page_name = db_value(
            "SELECT page_name FROM page WHERE page_id = '" . (int)$detail_page_id . "' LIMIT 1"
        );
        if ($detail_page_name) {
            $detail_url_prefix = $base_path . encode_url_path($detail_page_name) . '/';
        }
    }

    // Resolve the active group's name once (used for __category_name when
    // a group filter is in effect — including drill-down's URL-driven group).
    // When no group context, we look it up per-product in the batch step below.
    $active_group_name = '';
    if ($active_group_id > 0) {
        $active_group_name = (string)db_value(
            "SELECT name FROM product_groups WHERE id = '" . (int)$active_group_id . "' LIMIT 1"
        );
    }

    // ── No-products fall-through flag ────────────────────────────────────
    // Instead of returning early when there are no products to loop, we set
    // this flag and continue down the function. The product loop body checks
    // it and emits an empty $loop_output; the body composition uses
    // $empty_html in the loop slot. This way breadcrumb / chips / offcanvas /
    // child_groups are still computed and the page_tokens substitution at
    // the END of the function runs — so the visitor sees a proper "no
    // products" page with breadcrumb intact instead of literal `^^…^^`
    // tokens leaking through bound containers.
    $no_products = false;

    if ($loop_template === '') {
        $no_products = true;
    }

    // ── Count total matching rows ─────────────────────────────────────────
    $total_records = 0;
    if (!$no_products) {
        $total_records = (int)db_value(
            "SELECT COUNT(DISTINCT products.id)
             FROM products
             $group_join
             WHERE products.enabled = 1
               $search_where"
        );
        if ($total_records === 0) {
            $no_products = true;
        }
    }

    // Apply max_results cap before pagination math.
    if ($max_results > 0 && $total_records > $max_results) {
        $total_records = $max_results;
    }

    // Pagination math — guarded for the $no_products case so we don't end up
    // with $total_pages=0 + $current_page>0 producing negative offsets.
    $total_pages = $no_products ? 0 : (int)ceil($total_records / $items_per_page);
    if ($total_pages > 0 && $current_page > $total_pages) $current_page = $total_pages;
    $offset = $no_products ? 0 : ($current_page - 1) * $items_per_page;
    $page_limit = $no_products ? 0 : $items_per_page;
    if (!$no_products && $max_results > 0) {
        $remaining = $max_results - $offset;
        if ($remaining < $page_limit) $page_limit = max(0, $remaining);
    }

    // ── Pull product rows (page slice) ────────────────────────────────────
    // SELECT pulls only the columns we expose as tokens — keeps the row size
    // bounded even with very wide product schemas. Group-context columns
    // (xref.sort_order, xref.featured, xref.new_date) are joined in directly
    // when product_group_id > 0; otherwise resolved per-product below.
    $products = array();  // initialized empty for the $no_products fall-through
    if (!$no_products) {
        $xref_select = '';
        if ($active_group_id > 0) {
            $xref_select = ", xref.sort_order AS xref_sort_order,
                             xref.featured  AS xref_featured,
                             xref.new_date  AS xref_new_date";
        }
        $products = db_items(
            "SELECT
                 products.id, products.name, products.address_name,
                 products.short_description, products.full_description,
                 products.details,
                 products.price, products.image_name, products.timestamp,
                 products.inventory, products.inventory_quantity, products.backorder,
                 products.out_of_stock_message,
                 products.mpn, products.gtin, products.brand, products.weight,
                 products.shippable, products.taxable, products.reward_points,
                 products.seo_score,
                 products.custom_field_1, products.custom_field_2,
                 products.custom_field_3, products.custom_field_4
                 $xref_select
             FROM products
             $group_join
             WHERE products.enabled = 1
               $search_where
             GROUP BY products.id
             ORDER BY $order_by_sql
             LIMIT $page_limit OFFSET $offset"
        );
        if (!$products) {
            $no_products = true;
            $products = array();
        }
    }

    // ── Batch lookups for cross-table tokens ──────────────────────────────
    // Gallery count (per-product COUNT) and per-product first-group context
    // (when no group filter is in effect). One query each, indexed lookups.
    // Skip when there are no products — empty IN() lists produce SQL errors.
    $product_ids = array();
    foreach ($products as $p) { $product_ids[] = (int)$p['id']; }
    $product_ids_str = implode(',', $product_ids);

    $gallery_counts = array();
    if ($product_ids_str !== '') {
        $gc_rows = db_items(
            "SELECT product, COUNT(*) AS c
             FROM products_images_xref
             WHERE product IN ($product_ids_str)
             GROUP BY product"
        );
        if (is_array($gc_rows)) {
            foreach ($gc_rows as $r) { $gallery_counts[(int)$r['product']] = (int)$r['c']; }
        }
    }

    $first_group_by_product = array();   // product_id → ['id'=>..., 'name'=>..., 'featured'=>0/1, 'new_date'=>'YYYY-MM-DD', 'sort_order'=>N]
    if ($active_group_id <= 0 && $product_ids_str !== '') {
        // No configured group → resolve each product's "first" xref row (lowest
        // product_group id) for category_id / category_name / featured / is_new.
        $fg_rows = db_items(
            "SELECT xref.product, xref.product_group, xref.sort_order, xref.featured, xref.new_date,
                    product_groups.name AS group_name
             FROM products_groups_xref xref
             LEFT JOIN product_groups ON product_groups.id = xref.product_group
             WHERE xref.product IN ($product_ids_str)
             ORDER BY xref.product ASC, xref.product_group ASC"
        );
        if (is_array($fg_rows)) {
            foreach ($fg_rows as $r) {
                $pid = (int)$r['product'];
                if (!isset($first_group_by_product[$pid])) {
                    $first_group_by_product[$pid] = array(
                        'id'         => (int)$r['product_group'],
                        'name'       => isset($r['group_name']) ? (string)$r['group_name'] : '',
                        'sort_order' => isset($r['sort_order']) ? (int)$r['sort_order'] : 0,
                        'featured'   => !empty($r['featured']) ? 1 : 0,
                        'new_date'   => isset($r['new_date']) ? (string)$r['new_date'] : '',
                    );
                }
            }
        }
    }

    // ── Token replacement per product ─────────────────────────────────────
    // Currency symbol comes from VISITOR_CURRENCY_SYMBOL when defined; fallback '$'.
    $currency_symbol = defined('VISITOR_CURRENCY_SYMBOL') ? VISITOR_CURRENCY_SYMBOL : '$';
    $base_path       = defined('OUTPUT_PATH') ? OUTPUT_PATH : '/';

    // ── Per-card "Sepete Ekle" destination settings ──────────────────────
    // The button itself is DESIGNER-OWNED: they place a real button and bind
    // `action='catalog_add_to_cart'`, and this renderer wraps the card in the
    // POST form. Only the destination is a widget setting, because it names a
    // page rather than describing markup:
    //   • cart_page_id > 0  → after add, jump straight to the cart page
    //   • stay_on_page == 1 → after add, return to the listing with
    //                          ?cart_added=1 so JS can fire a toast
    //
    // The old `^^__add_to_cart_button^^` token — which emitted a whole
    // server-built form with the caption, icon and Bootstrap classes baked in,
    // configured by add_to_cart_label / add_to_cart_button_class — is gone.
    // Two ways to render the same button meant the designer's styling silently
    // lost to widget settings (or vice versa) depending on which one a tree
    // happened to use.
    $atc_next_pid    = (int)(isset($cfg['add_to_cart_next_page_id']) ? $cfg['add_to_cart_next_page_id'] : 0);
    $atc_stay        = !empty($cfg['add_to_cart_stay_on_page']);
    $atc_software_dir = defined('OUTPUT_SOFTWARE_DIRECTORY') ? OUTPUT_SOFTWARE_DIRECTORY : 'software';
    $atc_action_url   = $base_path . $atc_software_dir . '/catalog_detail.php';
    $atc_request_uri  = function_exists('get_request_uri') ? get_request_uri() : (isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/');
    $atc_current_page_id = isset($_GET['page'])
        ? (int)db_value("SELECT page_id FROM page WHERE page_name = '" . e((string)$_GET['page']) . "' LIMIT 1")
        : 0;

    // Pre-load offer-driven discounted prices ONCE for the whole loop —
    // the helper does its own internal queries (offers, offer_actions,
    // session order code, etc.), so calling per-product would be a hot
    // N+1. Result: [product_id => discounted_price_in_cents] for any
    // product currently qualifying for an order-scope offer. Used to
    // populate the per-card discount tokens (__original_price_formatted,
    // __has_discount, __discount_percent…) that drive the strike-through
    // discount visual.
    $_pg_disc_prices = function_exists('get_discounted_product_prices')
        ? get_discounted_product_prices() : array();
    if (!is_array($_pg_disc_prices)) $_pg_disc_prices = array();

    $loop_output = '';
    foreach ($products as $p) {
        $pid = (int)$p['id'];

        // Group-context resolution (sort_order / featured / new_date / category)
        if ($active_group_id > 0) {
            $sort_order_val = isset($p['xref_sort_order']) ? (int)$p['xref_sort_order'] : 0;
            $featured_val   = !empty($p['xref_featured']) ? 1 : 0;
            $new_date_val   = isset($p['xref_new_date']) ? (string)$p['xref_new_date'] : '';
            $category_id    = $active_group_id;
            $category_name  = $active_group_name;
        } else {
            $fg = isset($first_group_by_product[$pid]) ? $first_group_by_product[$pid] : null;
            $sort_order_val = $fg ? $fg['sort_order'] : 0;
            $featured_val   = $fg ? $fg['featured']   : 0;
            $new_date_val   = $fg ? $fg['new_date']   : '';
            $category_id    = $fg ? $fg['id']         : 0;
            $category_name  = $fg ? $fg['name']       : '';
        }

        // is_new: new_date is within the last 30 days. Empty/zero new_date → 0.
        $is_new = 0;
        if ($new_date_val !== '' && $new_date_val !== '0000-00-00') {
            $new_ts = strtotime($new_date_val);
            if ($new_ts && (time() - $new_ts) <= (30 * 86400) && $new_ts <= time()) {
                $is_new = 1;
            }
        }

        // Stock derivation — see the catalog_item_view twin for the full
        // reasoning. `inventory` is the TRACKING switch: when 0 the product has
        // unlimited stock and `inventory_quantity` carries no meaning.
        // $oos_notice (show the message) ignores backorder; $out_of_stock
        // (block add-to-cart) honours it.
        $tracked = !empty($p['inventory']) ? 1 : 0;
        $qty     = (int)$p['inventory_quantity'];
        $backord = !empty($p['backorder']) ? 1 : 0;
        $oos_notice   = ($tracked && $qty <= 0) ? 1 : 0;
        $out_of_stock = ($oos_notice && !$backord) ? 1 : 0;
        $oos_label = $out_of_stock ? (string)lang('Out of stock') : '';

        // Image URL (primary). Empty image_name → empty token (designer can hide via CSS).
        $image_url = !empty($p['image_name'])
            ? $base_path . encode_url_path((string)$p['image_name'])
            : '';

        // Detail URL — catalog uses path-style: /<detail_page_name>/<product.address_name>.
        // Fallback '#' when either is missing (matches form_list_view convention).
        $detail_url = '#';
        if ($detail_url_prefix !== '' && !empty($p['address_name'])) {
            $detail_url = $detail_url_prefix . encode_url_path((string)$p['address_name']);
        }

        // Price formatting: Pinegrap stores cents (int); display as "{symbol}{N.NN}".
        // Effective price honors active offers via $_pg_disc_prices map.
        // `__price` / `__price_formatted` reflect the EFFECTIVE price; the
        // `__original_*` tokens keep the sticker for strike-through display.
        $original_price_cents = (int)$p['price'];
        // round(), not (int) — a percentage offer produces fractional cents
        // (29995 * 0.9 = 26995.5) and truncating renders ₺269.95 where the
        // legacy catalog renders ₺269.96, since prepare_price_for_output()
        // hands the raw value to number_format() which rounds.
        $price_cents          = isset($_pg_disc_prices[$pid]) ? (int)round($_pg_disc_prices[$pid]) : $original_price_cents;
        $price_decimal        = $price_cents / 100;
        $original_price_dec   = $original_price_cents / 100;
        $price_formatted      = $currency_symbol . number_format($price_decimal, 2, '.', ',');
        $original_price_formatted = $currency_symbol . number_format($original_price_dec, 2, '.', ',');
        $has_discount         = ($price_cents < $original_price_cents) ? 1 : 0;
        $disc_save_cents      = $original_price_cents - $price_cents;
        $disc_save_pct        = ($original_price_cents > 0 && $disc_save_cents > 0)
                                ? (int)round(($disc_save_cents / $original_price_cents) * 100) : 0;
        $price_block_html     = _pg_format_price_with_discount($original_price_cents, $price_cents, $currency_symbol);

        $values = array(
            // Basic
            '__id'                  => (string)$pid,
            '__name'                => isset($p['name']) ? (string)$p['name'] : '',
            '__address_name'        => isset($p['address_name']) ? (string)$p['address_name'] : '',
            '__short_description'   => isset($p['short_description']) ? (string)$p['short_description'] : '',
            '__description'         => isset($p['full_description']) ? (string)$p['full_description'] : '',
            '__details'             => isset($p['details']) ? (string)$p['details'] : '',
            '__detail_url'          => $detail_url,
            '__sort_order'          => (string)$sort_order_val,
            '__created_at'          => !empty($p['timestamp']) ? date('Y-m-d', (int)$p['timestamp']) : '',

            // Price — effective (post-discount when one applies).
            //   • __price (numeric, no symbol) — use for math / data attrs
            //   • __price_formatted — with currency symbol; AND when an
            //     offer is in effect this token expands to the strike+new
            //     HTML so a designer who only binds the formatted price
            //     still sees the discount visual without composing two
            //     spans.
            '__price'               => number_format($price_decimal, 2, '.', ''),
            '__price_formatted'     => $has_discount ? $price_block_html : $price_formatted,
            // Pre-rendered strike+new HTML — explicit alias so a designer
            // who wants to be sure they always see the discount visual
            // can bind to this instead of relying on __price_formatted's
            // conditional expansion.
            '__price_block_html'    => $price_block_html,
            // Phase 2 — single-product widgets (current Phase 2 scope) treat
            // every row as a fixed-price product so min/max collapse to the
            // same value. When the catalog widget is later extended to render
            // select-mode group cards (variant containers), these tokens will
            // pull from get_price_range() of the underlying group.
            '__price_min'           => number_format($price_decimal, 2, '.', ''),
            '__price_max'           => number_format($price_decimal, 2, '.', ''),
            '__price_range'         => $price_formatted,
            '__has_variants'        => '0',
            '__variant_count'       => '0',
            // Discount granular tokens — designer can compose their own
            // strike-through markup by binding the original to a strikethrough
            // span and the effective to a coloured span.
            // Discount granular tokens — when there's NO discount, the
            // `original_*` tokens are emitted as empty strings so any
            // designer-bound strike-through element renders empty (and
            // gets hidden by the `[data-pg-bind*="original_price"]:empty`
            // CSS rule). The `__price`/`__price_formatted` token is the
            // SOLE price visible in that case. When a discount IS active,
            // both tokens have values → the designer's strike-through +
            // discounted price both render.
            '__original_price'           => $has_discount ? number_format($original_price_dec, 2, '.', '') : '',
            '__original_price_formatted' => $has_discount ? $original_price_formatted : '',
            '__has_discount'             => (string)$has_discount,
            '__discount_amount'          => $disc_save_cents > 0 ? number_format($disc_save_cents / 100, 2, '.', '') : '',
            '__discount_amount_formatted'=> $disc_save_cents > 0 ? $currency_symbol . number_format($disc_save_cents / 100, 2, '.', ',') : '',
            '__discount_percent'         => $disc_save_pct > 0 ? (string)$disc_save_pct : '',

            // Image
            '__image_url'           => $image_url,
            '__image_alt'           => isset($p['name']) ? (string)$p['name'] : '',
            '__thumbnail_url'       => $image_url,  // alias — Pinegrap has no separate thumb yet
            '__gallery_count'       => (string)(isset($gallery_counts[$pid]) ? $gallery_counts[$pid] : 0),

            // Stock / inventory
            '__inventory_tracked'   => (string)$tracked,
            '__inventory_quantity'  => (string)$qty,
            '__quantity_available'  => (string)$qty,
            '__backorder'           => (string)$backord,
            '__out_of_stock'        => (string)$out_of_stock,
            '__out_of_stock_label'  => $oos_label,
            // Gated on $oos_notice — a back-orderable item is still off the
            // shelf. See the catalog_item_view twin.
            '__out_of_stock_message'=> ($oos_notice && !empty($p['out_of_stock_message']))
                                           ? _pg_rich_text_to_inline($p['out_of_stock_message']) : '',

            // Identity
            '__sku'                 => isset($p['mpn']) ? (string)$p['mpn'] : '',  // alias — Pinegrap uses MPN
            '__mpn'                 => isset($p['mpn']) ? (string)$p['mpn'] : '',
            '__gtin'                => isset($p['gtin']) ? (string)$p['gtin'] : '',
            '__brand'               => isset($p['brand']) ? (string)$p['brand'] : '',
            '__manufacturer'        => isset($p['brand']) ? (string)$p['brand'] : '',  // alias

            // Physical
            '__weight'              => isset($p['weight']) ? (string)$p['weight'] : '',
            '__shippable'           => !empty($p['shippable']) ? '1' : '0',
            '__taxable'             => !empty($p['taxable'])  ? '1' : '0',

            // Group / category
            '__category_id'         => (string)$category_id,
            '__category_name'       => $category_name,
            '__featured'            => (string)$featured_val,
            '__is_new'              => (string)$is_new,

            // Custom fields
            '__custom_field_1'      => isset($p['custom_field_1']) ? (string)$p['custom_field_1'] : '',
            '__custom_field_2'      => isset($p['custom_field_2']) ? (string)$p['custom_field_2'] : '',
            '__custom_field_3'      => isset($p['custom_field_3']) ? (string)$p['custom_field_3'] : '',
            '__custom_field_4'      => isset($p['custom_field_4']) ? (string)$p['custom_field_4'] : '',

            // Reward / SEO
            '__reward_points'       => isset($p['reward_points']) ? (string)$p['reward_points'] : '0',
            '__seo_score'           => isset($p['seo_score']) ? (string)$p['seo_score'] : '0',

            // NOTE: `__add_to_cart_button` (a whole server-built form with the
            // caption, icon and Bootstrap classes baked in) has been removed.
            // The card's add-to-cart button is designer-owned: drop a real
            // button, bind `action='catalog_add_to_cart'`, and the binding pass
            // wraps the card in the same POST form using the SAME hidden
            // fields. Keeping both would mean two ways to render one button,
            // where the designer's styling silently loses to widget settings
            // depending on which mechanism the tree used.
            //
            // (This is the system-widget renderer. The legacy custom-style
            // catalog pages in get_catalog.php are untouched — those are live
            // on real sites.)
        );

        // Adaptive link captions — a product row ALWAYS opens the detail page,
        // so it takes the "detail" caption. (Group rows choose between the two
        // based on display_type; see the group loop.)
        if (!empty($_pg_bindings_used['link_labels'])) {
            foreach ($_pg_bindings_used['link_labels'] as $_lbl_tok => $_lbl_pair) {
                $values[$_lbl_tok] = isset($_lbl_pair['detail']) ? $_lbl_pair['detail'] : '';
            }
        }

        $rendered = $loop_template;
        // Replace longest tokens first so '^^foo_bar^^' isn't truncated by '^^foo^^'.
        $keys = array_keys($values);
        usort($keys, function ($a, $b) { return strlen($b) - strlen($a); });
        foreach ($keys as $k) {
            $rendered = str_replace('^^' . $k . '^^', (string)$values[$k], $rendered);
        }
        // Strip any unresolved tokens.
        $rendered = preg_replace('/\^\^[A-Za-z0-9_]+\^\^/', '', $rendered);

        // Uniquify Bootstrap component IDs per loop iteration so tabs, collapses
        // and dropdowns in different rows don't share the same id/target.
        // Using both widget_id and product's DB id as suffix prevents collisions
        // across multiple instances of the same widget on the same page.
        $iter_suffix = '_pgw' . $widget_id . 'r' . $pid;
        $rendered = preg_replace('/\bid="([^"]+)"/',             'id="$1'              . $iter_suffix . '"', $rendered);
        $rendered = preg_replace('/data-bs-target="#([^"]+)"/',  'data-bs-target="#$1' . $iter_suffix . '"', $rendered);
        $rendered = preg_replace('/aria-controls="([^"]+)"/',    'aria-controls="$1'   . $iter_suffix . '"', $rendered);
        $rendered = preg_replace('/href="#([^"]+)"/',            'href="#$1'           . $iter_suffix . '"', $rendered);
        $rendered = preg_replace('/data-bs-parent="#([^"]+)"/',  'data-bs-parent="#$1' . $iter_suffix . '"', $rendered);

        // Strip Sepete Ekle button + form wrap when the row should NOT
        // accept add-to-cart actions. Two cases:
        //   1. PRODUCT is out of stock (`$out_of_stock` is set above per
        //      row from products.inventory + inventory_quantity + backorder)
        // (Group rows are handled in the SEPARATE group rendering loop;
        // they never carry catalog_add_to_cart markers because group
        // values omit the bindable button. Browse-type group hide is
        // enforced in that loop.)
        if (!empty($out_of_stock)
            && strpos($rendered, 'data-pg-catalog-atc="1"') !== false) {
            // Drop the entire <button …data-pg-catalog-atc="1"…>…</button>
            // element. We're not stripping the wrapping form (it's added
            // BELOW only when the button survived), so this also prevents
            // the form from being added at all.
            $rendered = preg_replace(
                '/<button[^>]*data-pg-catalog-atc="1"[^>]*>.*?<\/button>/is',
                '',
                $rendered
            );
        }

        // Per-card mini-form wrap — when the designer placed a btn with
        // `_bindings.action='catalog_add_to_cart'` (we marked it earlier
        // with `data-pg-catalog-atc="1"`), wrap THIS card in a tiny
        // <form> pointing at catalog_detail.php with the right hidden
        // fields. Mirrors the legacy `^^__add_to_cart_button^^` token's
        // mini-form, but lets the designer style the button via the
        // standard Button options panel.
        if (strpos($rendered, 'data-pg-catalog-atc="1"') !== false) {
            $_pg_atc_form_id = 'pgAtcForm_pgw' . $widget_id . 'r' . $pid;
            $_pg_atc_hidden = (function_exists('get_token_field') ? get_token_field() : '')
                . '<input type="hidden" name="product_id" value="' . (int)$pid . '">'
                . '<input type="hidden" name="quantity" value="1">'
                . '<input type="hidden" name="page_id" value="' . (int)$atc_current_page_id . '">'
                . '<input type="hidden" name="current_url" value="' . h($atc_request_uri) . '">'
                . ($atc_stay
                    ? ('<input type="hidden" name="next_url" value="' . h(_pg_sw_append_query($atc_request_uri, 'cart_added', (string)$pid)) . '">')
                    : ('<input type="hidden" name="next_page_id" value="' . (int)$atc_next_pid . '">'));
            // Use HTML5 `form` attribute on the bindable button so the
            // form can sit OUTSIDE the card markup (avoids nesting issues
            // when the designer's card layout is itself a form-control
            // ancestor). Stamp the form attr onto every button in the
            // card carrying our marker.
            $rendered = preg_replace(
                '/(<button[^>]*data-pg-catalog-atc="1")/',
                '$1 form="' . $_pg_atc_form_id . '"',
                $rendered
            );
            $rendered = '<form id="' . $_pg_atc_form_id . '" method="post" action="' . h($atc_action_url) . '"'
                      . ' class="d-none" onsubmit="this.querySelector(\'button[type=submit]\')?.setAttribute(\'disabled\',\'\')">'
                      . $_pg_atc_hidden
                      . '</form>'
                      . $rendered;
        }

        $loop_output .= $rendered;
    }

    // ── Pagination HTML (Bootstrap 5, ±2 windowing — same as form_list_view) ──
    $pagination_html = '';
    if ($total_pages > 1) {
        $pagination_html .= '<nav class="pg-sw-pagination" aria-label="' . h(lang('Page navigation')) . '"><ul class="pagination pagination-sm justify-content-center mt-3 mb-0">';
        if ($current_page > 1) {
            $pagination_html .= '<li class="page-item"><a class="page-link" href="?' .
                h(_pg_sw_build_query($page_param, $current_page - 1)) . '" aria-label="' . h(lang('Previous')) . '">&laquo;</a></li>';
        } else {
            $pagination_html .= '<li class="page-item disabled"><span class="page-link">&laquo;</span></li>';
        }
        $window = 2;
        $from = max(1, $current_page - $window);
        $to   = min($total_pages, $current_page + $window);
        if ($from > 1) {
            $pagination_html .= '<li class="page-item"><a class="page-link" href="?' .
                h(_pg_sw_build_query($page_param, 1)) . '">1</a></li>';
            if ($from > 2) $pagination_html .= '<li class="page-item disabled"><span class="page-link">…</span></li>';
        }
        for ($i = $from; $i <= $to; $i++) {
            if ($i === $current_page) {
                $pagination_html .= '<li class="page-item active"><span class="page-link">' . $i . '</span></li>';
            } else {
                $pagination_html .= '<li class="page-item"><a class="page-link" href="?' .
                    h(_pg_sw_build_query($page_param, $i)) . '">' . $i . '</a></li>';
            }
        }
        if ($to < $total_pages) {
            if ($to < $total_pages - 1) $pagination_html .= '<li class="page-item disabled"><span class="page-link">…</span></li>';
            $pagination_html .= '<li class="page-item"><a class="page-link" href="?' .
                h(_pg_sw_build_query($page_param, $total_pages)) . '">' . $total_pages . '</a></li>';
        }
        if ($current_page < $total_pages) {
            $pagination_html .= '<li class="page-item"><a class="page-link" href="?' .
                h(_pg_sw_build_query($page_param, $current_page + 1)) . '" aria-label="' . h(lang('Next')) . '">&raquo;</a></li>';
        } else {
            $pagination_html .= '<li class="page-item disabled"><span class="page-link">&raquo;</span></li>';
        }
        $pagination_html .= '</ul></nav>';
    }

    // ── Phase 2 frontend controls (sort / price / stock / chips / breadcrumb) ──
    // All controls submit GET params that the renderer reads at the top.
    // Filter changes reset page_number to 1 implicitly because the form/chip
    // links omit the page_number param, so the new page state doesn't outlive
    // a no-longer-valid page index.

    // Sort dropdown ----------------------------------------------------------
    $sort_html = '';
    if ($sort_control_enabled) {
        // Build options from the configured whitelist; preserve other GET params.
        $hidden_for_sort = '';
        if (!empty($_GET) && is_array($_GET)) {
            foreach ($_GET as $k => $v) {
                if ($k === 'sort' || $k === $page_param) continue;  // sort/page set by this form
                if (is_array($v)) continue;
                $hidden_for_sort .= '<input type="hidden" name="' . h($k) . '" value="' . h((string)$v) . '">';
            }
        }
        $sort_labels = array(
            'sort_order' => lang('Default order'),
            'name_asc'   => lang('Name: A → Z'),
            'name_desc'  => lang('Name: Z → A'),
            'price_asc'  => lang('Price: Low to High'),
            'price_desc' => lang('Price: High to Low'),
            'newest'     => lang('Recently added'),
        );
        $opts = '';
        foreach ($sort_options as $key) {
            $opts .= '<option value="' . h($key) . '"' . ($sort_url_key === $key ? ' selected' : '') . '>' .
                     h($sort_labels[$key]) . '</option>';
        }
        $sort_html =
            '<form method="get" class="pg-sw-sort mb-3 d-flex align-items-center gap-2" style="max-width:320px">' .
                $hidden_for_sort .
                '<label class="form-label mb-0 small text-muted">' . h(lang('Sort')) . ':</label>' .
                '<select name="sort" class="form-select form-select-sm" onchange="this.form.submit()">' . $opts . '</select>' .
                '<noscript><button type="submit" class="btn btn-sm btn-outline-secondary">' . h(lang('Apply')) . '</button></noscript>' .
            '</form>';
    }

    // Price filter form ------------------------------------------------------
    $price_filter_html = '';
    if ($price_filter_enabled) {
        $hidden_for_price = '';
        if (!empty($_GET) && is_array($_GET)) {
            foreach ($_GET as $k => $v) {
                if ($k === 'pmin' || $k === 'pmax' || $k === $page_param) continue;
                if (is_array($v)) continue;
                $hidden_for_price .= '<input type="hidden" name="' . h($k) . '" value="' . h((string)$v) . '">';
            }
        }
        $cur_min = ($pmin_param !== null) ? (string)$pmin_param : '';
        $cur_max = ($pmax_param !== null) ? (string)$pmax_param : '';
        $price_filter_html =
            '<form method="get" class="pg-sw-price-filter mb-3 d-flex align-items-center gap-2 flex-wrap">' .
                $hidden_for_price .
                '<label class="form-label mb-0 small text-muted">Fiyat:</label>' .
                '<input type="number" class="form-control form-control-sm" name="pmin" value="' . h($cur_min) . '" placeholder="Min" min="0" step="' . (int)$price_filter_step . '" style="max-width:100px">' .
                '<span class="text-muted">–</span>' .
                '<input type="number" class="form-control form-control-sm" name="pmax" value="' . h($cur_max) . '" placeholder="Max" min="0" step="' . (int)$price_filter_step . '" style="max-width:100px">' .
                '<button type="submit" class="btn btn-sm btn-outline-secondary">Uygula</button>' .
            '</form>';
    }

    // Stock filter checkbox --------------------------------------------------
    $stock_filter_html = '';
    if ($stock_filter_enabled) {
        // Toggle is rendered as a same-page link instead of a form so a click
        // produces a clean URL without "0" sentinel values when toggling off.
        $stock_target = $stock_filter_active ? '' : '1';
        $stock_link_query = _pg_sw_build_query('stock', $stock_target, $page_param, '');
        $stock_filter_html =
            '<div class="pg-sw-stock-filter form-check mb-3">' .
                '<input class="form-check-input" type="checkbox" id="pg-sw-stock-' . (int)$widget_id . '"' .
                       ($stock_filter_active ? ' checked' : '') .
                       ' onclick="window.location.search=\'?' . h($stock_link_query) . '\'">' .
                '<label class="form-check-label" for="pg-sw-stock-' . (int)$widget_id . '">' .
                    h(lang('In stock only')) .
                '</label>' .
            '</div>';
    }

    // Attribute filter HTML -------------------------------------------------
    // Fetches distinct attribute → option pairs visible under the current
    // group context (or all groups when active_group_id=0) for ENABLED
    // products. Results drive both the filter UI and the active-chip labels.
    //
    // ^^__attribute_filters_html^^  — the filter widget (checkboxes/buttons)
    // ^^__active_filters_html^^     — only the active-attribute chips
    //
    // Note: always shows all available options for the group regardless of
    // other active filters (so user can combine/remove filters freely).
    $attribute_filters_html = '';
    $available_attrs        = array();  // [attr_id => ['attr_label'=>'', 'options'=>[...]]]
    $active_attr_chips_html = '';
    if ($attr_filter_enabled) {
        // Filter scope: products of the active group AND of every enabled
        // descendant group. A top-level catalog usually holds no products of
        // its own; they live in sub-groups, and filtering on the direct xref
        // alone would leave the panel empty while the listing shows products.
        // The descendant set is walked in PHP by _pg_catalog_group_subtree_ids()
        // and passed as an IN (...) list, the same way the listing scope is
        // built; a recursive CTE would need MySQL 8, and the product still
        // runs on MySQL 5.7.
        if ($active_group_id > 0) {
            $_af_scope     = _pg_catalog_group_subtree_ids(array($active_group_id));
            $_af_scope_ids = isset($_af_scope[$active_group_id])
                                ? $_af_scope[$active_group_id]
                                : array($active_group_id);
            $_af_scope_list = implode(',', array_map('intval', $_af_scope_ids));
            $_af_rows = db_items(
                "SELECT DISTINCT
                        pa.id    AS attr_id,
                        pa.name  AS attr_name,
                        pa.label AS attr_label,
                        pao.id          AS option_id,
                        pao.label       AS option_label,
                        pao.sort_order  AS opt_sort
                 FROM products _p
                 INNER JOIN products_groups_xref _pgx
                         ON _pgx.product = _p.id
                        AND _pgx.product_group IN ($_af_scope_list)
                 INNER JOIN products_attributes_xref _pax ON _pax.product_id   = _p.id
                 INNER JOIN product_attributes       pa   ON pa.id              = _pax.attribute_id
                 INNER JOIN product_attribute_options pao ON pao.id             = _pax.option_id
                 WHERE _p.enabled = 1
                 ORDER BY pa.id ASC, pao.sort_order ASC, pao.id ASC"
            );
        } else {
            // No group filter — every enabled product is in scope.
            $_af_rows = db_items(
                "SELECT DISTINCT
                        pa.id    AS attr_id,
                        pa.name  AS attr_name,
                        pa.label AS attr_label,
                        pao.id          AS option_id,
                        pao.label       AS option_label,
                        pao.sort_order  AS opt_sort
                 FROM products _p
                 INNER JOIN products_attributes_xref _pax ON _pax.product_id   = _p.id
                 INNER JOIN product_attributes       pa   ON pa.id              = _pax.attribute_id
                 INNER JOIN product_attribute_options pao ON pao.id             = _pax.option_id
                 WHERE _p.enabled = 1
                 ORDER BY pa.id ASC, pao.sort_order ASC, pao.id ASC"
            );
        }
        if (is_array($_af_rows)) {
            foreach ($_af_rows as $_ar) {
                $_af_id = (int)$_ar['attr_id'];
                if (!isset($available_attrs[$_af_id])) {
                    // Prefer label (human-readable) over name (programmatic slug).
                    $_af_lbl = (isset($_ar['attr_label']) && (string)$_ar['attr_label'] !== '')
                                ? (string)$_ar['attr_label']
                                : (string)$_ar['attr_name'];
                    $available_attrs[$_af_id] = array('attr_label' => $_af_lbl, 'options' => array());
                }
                $available_attrs[$_af_id]['options'][] = array(
                    'option_id'    => (int)$_ar['option_id'],
                    'option_label' => (string)$_ar['option_label'],
                );
            }
        }

        if ($available_attrs) {
            $_afout = '<div class="pg-sw-attr-filters mb-3">';
            foreach ($available_attrs as $_afaid => $_afdef) {
                $_afpk  = 'pg_attr_' . $_afaid;
                $_afact = isset($active_attr_filters[$_afaid]) ? (int)$active_attr_filters[$_afaid] : 0;
                $_afout .= '<div class="pg-sw-attr-group mb-2">';
                $_afout .= '<div class="small fw-semibold mb-1">' . h($_afdef['attr_label']) . '</div>';
                if ($attr_filter_style === 'buttons') {
                    // Button group: "All" clears the param, each option sets it.
                    $_afout .= '<div class="d-flex flex-wrap gap-1">';
                    $_all_cls  = ($_afact === 0) ? 'btn-secondary' : 'btn-outline-secondary';
                    $_all_href = '?' . h(_pg_sw_build_query($_afpk, '', $page_param, ''));
                    $_afout   .= '<a href="' . $_all_href . '" class="btn btn-sm ' . $_all_cls . '">' . h(lang('All')) . '</a>';
                    foreach ($_afdef['options'] as $_opt) {
                        $_oid      = (int)$_opt['option_id'];
                        $_opt_cls  = ($_afact === $_oid) ? 'btn-secondary' : 'btn-outline-secondary';
                        $_opt_href = '?' . h(_pg_sw_build_query($_afpk, $_oid, $page_param, ''));
                        $_afout   .= '<a href="' . $_opt_href . '" class="btn btn-sm ' . $_opt_cls . '">'
                                   . h($_opt['option_label']) . '</a>';
                    }
                    $_afout .= '</div>';
                } else {
                    // Checkbox list: click toggles the option (on → off, off → on).
                    foreach ($_afdef['options'] as $_opt) {
                        $_oid       = (int)$_opt['option_id'];
                        $_checked   = ($_afact === $_oid);
                        $_tog_val   = $_checked ? '' : (string)$_oid;
                        $_cb_id     = 'pg-af-' . (int)$widget_id . '-' . $_afaid . '-' . $_oid;
                        $_qs        = h(_pg_sw_build_query($_afpk, $_tog_val, $page_param, ''));
                        $_afout    .=
                            '<div class="form-check">' .
                                '<input class="form-check-input" type="checkbox"' .
                                       ($_checked ? ' checked' : '') .
                                       ' id="' . $_cb_id . '"' .
                                       ' onclick="window.location.search=\'?' . $_qs . '\'">' .
                                '<label class="form-check-label" for="' . $_cb_id . '">' .
                                    h($_opt['option_label']) .
                                '</label>' .
                            '</div>';
                    }
                }
                $_afout .= '</div>';
            }
            $_afout .= '</div>';
            $attribute_filters_html = $_afout;
        }
    }

    // Breadcrumb -------------------------------------------------------------
    // Walks parent_id upward from the active group, STOPPING at the widget's
    // configured root (`$product_group_id` from cfg). This way the catalog's
    // "world" is the configured root + its descendants — any ancestors above
    // the root are intentionally hidden because they're outside this widget's
    // scope (a different catalog widget might surface them).
    //
    // Crumb composition:
    //   • "Ana Katalog" link → catalog root URL (active group dropped from URL)
    //   • Each ancestor BETWEEN the root and the active group → linkable
    //   • The active group itself → text (current location)
    //
    // At ROOT (no drill, $active_group_id == $product_group_id): only the
    // "Ana Katalog" crumb appears, marked active (current location). This
    // way a designer-bound <nav> still has visible content even on the
    // catalog landing page — the visitor sees their location consistently.
    //
    // When `$product_group_id == 0` (no configured root) the loop walks all
    // the way to parent_id=0, matching the legacy unbounded behaviour.
    //
    // Two-token output:
    //   • $breadcrumb_inner_html — just the <ol>…</ol> (no <nav> wrapper).
    //     Used when designer binds their own <nav>/<div>/etc. with section=
    //     breadcrumb — wrapper preserved, children replaced with this.
    //   • $breadcrumb_html       — full <nav><ol>…</ol></nav>. Legacy token
    //     for designers placing the raw `^^__breadcrumb_html^^` inside their
    //     own custom HTML.
    $breadcrumb_inner_html = '';
    $breadcrumb_html       = '';
    if ($group_navigation === 'drill_down') {
        $crumbs = array();
        if ($active_group_id > 0 && $active_group_id !== (int)$product_group_id) {
            $cur = $active_group_id;
            $safety = 16;  // pathological depth guard
            $_pg_root_id = (int)$product_group_id;
            while ($cur > 0 && $safety-- > 0) {
                // Stop AT the configured root — don't include it as its own crumb
                // because "Ana Katalog" already serves that role.
                if ($_pg_root_id > 0 && $cur === $_pg_root_id) break;
                $row = db_item(
                    "SELECT id, name, address_name, parent_id FROM product_groups WHERE id = '" . (int)$cur . "' LIMIT 1"
                );
                if (!$row) break;
                array_unshift($crumbs, $row);
                $cur = (int)$row['parent_id'];
            }
        }
        $bcParts = array();
        if ($crumbs) {
            // Drilled into a sub-category: "Ana Katalog" is a link back to
            // the catalog root; the trail ends at the active group as text.
            $bcParts[] = '<a href="' . h($listing_root_url) . '">' . h(lang('Main Catalog')) . '</a>';
            $last_idx = count($crumbs) - 1;
            foreach ($crumbs as $i => $c) {
                if ($i === $last_idx) {
                    $bcParts[] = '<span class="active">' . h($c['name']) . '</span>';
                } else {
                    $href = !empty($c['address_name'])
                        ? $listing_path_base . encode_url_path((string)$c['address_name'])
                        : $listing_root_url;
                    $bcParts[] = '<a href="' . h($href) . '">' . h($c['name']) . '</a>';
                }
            }
        } else {
            // At root (no drill): single non-link "Ana Katalog" crumb so the
            // designer-bound <nav> still has visible content.
            $bcParts[] = '<span class="active">' . h(lang('Main Catalog')) . '</span>';
        }
        $breadcrumb_inner_html =
            '<ol class="breadcrumb mb-0">' .
            implode('', array_map(function ($p) { return '<li class="breadcrumb-item">' . $p . '</li>'; }, $bcParts)) .
            '</ol>';
        $breadcrumb_html =
            '<nav class="pg-sw-breadcrumb mb-3" aria-label="breadcrumb">' .
            $breadcrumb_inner_html .
            '</nav>';
    }

    // Group tree -------------------------------------------------------------
    // Nested <ul>/<li> navigation of every group in the widget's "world"
    // (configured root + all descendants). Designer can drop any container
    // (a <nav>, a sidebar div, an offcanvas body) and bind it to
    // `section: group_tree`; the binding pre-process replaces the bound
    // node's children with the `^^__group_tree_inner_html^^` token (wrapper
    // preserved). Or place `^^__group_tree_html^^` in custom HTML for the
    // full <nav>-wrapped version.
    //
    // When `$product_group_id == 0` the tree starts from top-level groups
    // (parent_id=0); when configured to a specific root, it starts from
    // that root's direct children — mirroring the breadcrumb scope.
    //
    // Only built when drill-down navigation is on; otherwise the tree
    // wouldn't lead anywhere clickable.
    $group_tree_inner_html = '';
    $group_tree_html       = '';
    if ($group_navigation === 'drill_down') {
        $_pg_tree_root = (int)$product_group_id;
        $group_tree_inner_html = _pg_render_catalog_group_tree(
            $_pg_tree_root,
            $listing_path_base,
            $listing_root_url,
            $active_group_id,
            $detail_url_prefix
        );
        if ($group_tree_inner_html !== '') {
            $group_tree_html = '<nav class="pg-sw-cl-group-tree" aria-label="' . h(lang('Categories')) . '">'
                             . $group_tree_inner_html
                             . '</nav>';
        }
    }

    // Child groups — rendered with the SAME loop_template the designer wrote
    // for products, so groups and products share one card design and live in
    // one grid. Group rows fill in the universal subset of tokens (name,
    // image, short_description, address, url); product-only tokens
    // (price, inventory, mpn, gallery, …) collapse to empty via the final
    // ^^…^^ strip below — the designer template stays usable for both.
    //
    // ^^__row_type^^ ('group' / 'product') lets the designer add type-aware
    // CSS / wrapper markup if they want a visual distinction.
    //
    // Sort order: group rows come BEFORE products in the same grid, each
    // sorted by their own sort_order — matches a folder-listing UX (folders
    // first, then files). True interleaved sort across both is a future step.
    $child_groups_html = '';
    $has_children = 0;
    if ($group_navigation === 'drill_down' && $loop_template !== '') {
        // At ROOT (no active group) → show TOP-LEVEL groups (parent_id = 0).
        // When DRILLED INTO a parent → show that parent's direct children.
        // Both cases use the same loop_template so the designer's card
        // design renders categories at the homepage and sub-categories
        // after a drill click.
        $_pg_parent_filter = $active_group_id > 0
            ? (int)$active_group_id
            : 0;

        // Search-aware group filter: when the visitor has typed a query,
        // ONLY surface groups that either (a) match the query themselves
        // by name/short_description/address_name OR (b) contain matching
        // products in any descendant group (recursive). Without descendant
        // recursion, a top-level "Ofis Malzemeleri" group would not surface
        // when the visitor searched "Sandalye" even though "Ofis
        // Sandalyesi" subgroup contains matching products — the "kısmen
        // çalışıyor" symptom.
        //
        // Two steps, no recursive SQL (the product still runs on MySQL 5.7,
        // which has no CTEs). First the groups that DIRECTLY xref a matching
        // product are read. Then each candidate child group is expanded to
        // its enabled subtree with _pg_catalog_group_subtree_ids(), and the
        // child is kept when that subtree touches one of those groups; the
        // matching ids are added to the standard name/short_description/
        // address_name OR clause as an IN (...) list.
        if ($search_active) {
            $_q = e($search_query);
            $_pg_seed_rows = db_items(
                "SELECT DISTINCT _pgx.product_group AS id
                    FROM products_groups_xref _pgx
                    INNER JOIN products _p ON _p.id = _pgx.product
                    WHERE _p.enabled = 1
                      AND (
                           _p.name              LIKE '%$_q%'
                        OR _p.short_description LIKE '%$_q%'
                        OR _p.full_description  LIKE '%$_q%'
                        OR _p.details           LIKE '%$_q%'
                        OR _p.keywords          LIKE '%$_q%'
                        OR _p.meta_keywords     LIKE '%$_q%'
                        OR _p.meta_description  LIKE '%$_q%'
                        OR _p.address_name      LIKE '%$_q%'
                        OR EXISTS (
                              SELECT 1
                              FROM products_attributes_xref _pas
                              INNER JOIN product_attribute_options _pao
                                ON _pao.id = _pas.option_id
                              WHERE _pas.product_id = _p.id
                                AND _pao.label LIKE '%$_q%'
                           )
                      )"
            );
            $_pg_seed_ids = array();
            if (is_array($_pg_seed_rows)) {
                foreach ($_pg_seed_rows as $_sr) {
                    if ((int)$_sr['id'] > 0) $_pg_seed_ids[(int)$_sr['id']] = true;
                }
            }

            $_pg_candidate_rows = db_items(
                "SELECT id FROM product_groups
                 WHERE parent_id = '" . $_pg_parent_filter . "' AND enabled = 1"
            );
            $_pg_candidate_ids = array();
            if (is_array($_pg_candidate_rows)) {
                foreach ($_pg_candidate_rows as $_cr) {
                    if ((int)$_cr['id'] > 0) $_pg_candidate_ids[] = (int)$_cr['id'];
                }
            }

            $_pg_matched_ids = array();
            if ($_pg_seed_ids && $_pg_candidate_ids) {
                $_pg_subtrees = _pg_catalog_group_subtree_ids($_pg_candidate_ids);
                foreach ($_pg_candidate_ids as $_cid) {
                    $_tree = isset($_pg_subtrees[$_cid]) ? $_pg_subtrees[$_cid] : array($_cid);
                    foreach ($_tree as $_tid) {
                        if (isset($_pg_seed_ids[$_tid])) { $_pg_matched_ids[] = $_cid; break; }
                    }
                }
            }
            $_pg_matched_sql = $_pg_matched_ids
                ? " OR product_groups.id IN (" . implode(',', $_pg_matched_ids) . ")"
                : '';

            $children = db_items(
                "SELECT product_groups.id, product_groups.name, product_groups.address_name,
                       product_groups.image_name, product_groups.short_description,
                       product_groups.display_type, product_groups.sort_order
                FROM product_groups
                WHERE product_groups.parent_id = '" . $_pg_parent_filter . "'
                  AND product_groups.enabled = 1
                  AND (
                       product_groups.name              LIKE '%$_q%'
                    OR product_groups.short_description LIKE '%$_q%'
                    OR product_groups.address_name      LIKE '%$_q%'
                    " . $_pg_matched_sql . "
                  )
                ORDER BY product_groups.sort_order ASC, product_groups.name ASC"
            );
        } else {
            $children = db_items(
                "SELECT id, name, address_name, image_name, short_description, display_type, sort_order
                 FROM product_groups
                 WHERE parent_id = '" . $_pg_parent_filter . "'
                   AND enabled = 1
                 ORDER BY sort_order ASC, name ASC"
            );
        }
        if ($children && count($children) > 0) {
            $has_children = 1;
            // Batch min/max price per child group from products_groups_xref
            // → products. One query covers every child so we don't issue a
            // sub-query per row. Result is a map keyed by group_id.
            $_pg_grp_ids = array();
            foreach ($children as $_cg) { $_pg_grp_ids[] = (int)$_cg['id']; }
            $_pg_grp_ids = array_values(array_filter($_pg_grp_ids, function ($i) { return $i > 0; }));

            // Price range per child group.
            //
            // Three things a naive `MIN(price)/MAX(price) over direct xrefs`
            // gets wrong versus the legacy catalog's get_price_range():
            //   1. Nesting  — a browse group's range must span its WHOLE
            //      subtree. A category whose products all live in
            //      sub-categories otherwise shows no price at all.
            //   2. Offers   — legacy applies get_discounted_product_prices()
            //      BEFORE folding min/max, which is why the legacy card reads
            //      "₺269.96 - ₺299.95" where a raw-price query reads "₺299.95".
            //   3. Donations— excluded from ranges; a "pay what you want" row
            //      would otherwise drag every range down to its floor.
            //
            // Subtree expansion is shared with the RSS/JSON-LD feed — see
            // _pg_catalog_group_subtree_ids() for why it walks the table in
            // PHP instead of using a recursive SQL CTE.
            $_pg_grp_price = array();   // gid => [min, max, count, discounted, original]
            if ($_pg_grp_ids) {
                $_pg_subtree = _pg_catalog_group_subtree_ids($_pg_grp_ids);
                $_pg_all_ids = array();
                foreach ($_pg_subtree as $_ids) {
                    foreach ($_ids as $_i) $_pg_all_ids[$_i] = true;
                }

                // One query for every group in every subtree.
                $_pg_prod_by_group = array();   // gid => [pid => original price cents]
                $_pg_all_id_list = implode(',', array_keys($_pg_all_ids));
                if ($_pg_all_id_list !== '') {
                    $_pg_pp_rows = db_items(
                        "SELECT pgx.product_group AS gid, p.id AS pid, p.price AS price
                         FROM products_groups_xref pgx
                         INNER JOIN products p ON p.id = pgx.product
                         WHERE pgx.product_group IN ($_pg_all_id_list)
                           AND p.enabled = 1
                           AND p.selection_type != 'donation'"
                    );
                    if (is_array($_pg_pp_rows)) {
                        foreach ($_pg_pp_rows as $_pg_pp) {
                            $_pg_prod_by_group[(int)$_pg_pp['gid']][(int)$_pg_pp['pid']] = (int)$_pg_pp['price'];
                        }
                    }
                }

                // Fold each subtree into one range, applying offer discounts.
                // Keyed by product id so a product xref'd to several groups in
                // the same subtree is only counted once.
                foreach ($_pg_subtree as $_root => $_ids) {
                    $_seen = array();
                    foreach ($_ids as $_i) {
                        if (!isset($_pg_prod_by_group[$_i])) continue;
                        foreach ($_pg_prod_by_group[$_i] as $_pid => $_price) $_seen[$_pid] = $_price;
                    }
                    if (!$_seen) continue;
                    $_min = null; $_max = null; $_disc = false; $_orig_of_disc = 0;
                    foreach ($_seen as $_pid => $_orig) {
                        // round() for the same fractional-cents reason as the product loop.
                        $_eff = isset($_pg_disc_prices[$_pid]) ? (int)round($_pg_disc_prices[$_pid]) : (int)$_orig;
                        if ($_min === null || $_eff < $_min) $_min = $_eff;
                        if ($_max === null || $_eff > $_max) $_max = $_eff;
                        if ($_eff < (int)$_orig) { $_disc = true; $_orig_of_disc = (int)$_orig; }
                    }
                    $_pg_grp_price[(int)$_root] = array(
                        'min'        => (int)$_min,
                        'max'        => (int)$_max,
                        'count'      => count($_seen),
                        'discounted' => $_disc,
                        'original'   => $_orig_of_disc,
                    );
                }
            }
            $g_rows = '';
            foreach ($children as $cg) {
                // URL: browse → drill into child on this listing page;
                //      select → variant-chooser detail page.
                $cg_url = '#';
                if ($cg['display_type'] === 'browse' && !empty($cg['address_name'])) {
                    $cg_url = $listing_path_base . encode_url_path((string)$cg['address_name']);
                } elseif ($cg['display_type'] === 'select' && $detail_url_prefix !== '' && !empty($cg['address_name'])) {
                    $cg_url = $detail_url_prefix . encode_url_path((string)$cg['address_name']);
                }
                $cg_image_url = !empty($cg['image_name'])
                    ? $base_path . encode_url_path((string)$cg['image_name'])
                    : '';
                // Price range for this group — single price when min == max
                // (all products same effective price), otherwise "min - max".
                // Empty when the group has no priceable products.
                //
                // Display rules mirror legacy get_price_info():
                //   • exactly one product, discounted → strike-through original
                //     + new price (same visual the product cards use)
                //   • min == max                      → one price
                //   • otherwise                       → "min - max"
                $_pg_gp         = isset($_pg_grp_price[(int)$cg['id']]) ? $_pg_grp_price[(int)$cg['id']] : null;
                $_pg_min_cents  = $_pg_gp ? (int)$_pg_gp['min'] : 0;
                $_pg_max_cents  = $_pg_gp ? (int)$_pg_gp['max'] : 0;
                $_pg_min_str    = $currency_symbol . number_format($_pg_min_cents / 100, 2, '.', ',');
                $_pg_max_str    = $currency_symbol . number_format($_pg_max_cents / 100, 2, '.', ',');
                $_pg_grp_disc   = $_pg_gp ? !empty($_pg_gp['discounted']) : false;
                $_pg_grp_orig   = $_pg_gp ? (int)$_pg_gp['original'] : 0;
                $_pg_range_str  = $_pg_gp
                    ? (($_pg_min_cents === $_pg_max_cents) ? $_pg_min_str : ($_pg_min_str . ' - ' . $_pg_max_str))
                    : '';
                // Strike-through block only makes sense for a single-product
                // group; with several products the range already communicates
                // "from X", and pairing it with one product's sticker price
                // would be misleading.
                $_pg_grp_block  = ($_pg_gp && $_pg_grp_disc && (int)$_pg_gp['count'] === 1 && $_pg_grp_orig > 0)
                    ? _pg_format_price_with_discount($_pg_grp_orig, $_pg_min_cents, $currency_symbol)
                    : '';
                $g_values = array(
                    '__row_type'          => 'group',
                    '__id'                => (string)(int)$cg['id'],
                    '__name'              => (string)$cg['name'],
                    '__address_name'      => (string)$cg['address_name'],
                    '__short_description' => (string)$cg['short_description'],
                    '__description'       => '',
                    '__detail_url'        => $cg_url,
                    '__url'               => $cg_url,
                    '__display_type'      => (string)$cg['display_type'],
                    '__sort_order'        => (string)$cg['sort_order'],
                    '__image_url'         => $cg_image_url,
                    '__image_alt'         => (string)$cg['name'],
                    '__thumbnail_url'     => $cg_image_url,
                    // Price tokens — same names the product loop uses, so a
                    // single template can render either kind of row without
                    // an `if`. Min/max collapse to one when equal.
                    '__price'             => $_pg_gp ? number_format($_pg_min_cents / 100, 2, '.', '') : '',
                    '__price_formatted'   => ($_pg_grp_block !== '') ? $_pg_grp_block : $_pg_range_str,
                    '__price_block_html'  => ($_pg_grp_block !== '') ? $_pg_grp_block : $_pg_range_str,
                    '__price_min'         => $_pg_gp ? number_format($_pg_min_cents / 100, 2, '.', '') : '',
                    '__price_max'         => $_pg_gp ? number_format($_pg_max_cents / 100, 2, '.', '') : '',
                    '__price_range'       => $_pg_range_str,
                    // Discount tokens — parity with the product loop so a
                    // designer's "indirim" markup works on group cards too.
                    '__has_discount'             => $_pg_grp_disc ? '1' : '0',
                    '__original_price'           => ($_pg_grp_disc && $_pg_grp_orig > 0) ? number_format($_pg_grp_orig / 100, 2, '.', '') : '',
                    '__original_price_formatted' => ($_pg_grp_disc && $_pg_grp_orig > 0) ? $currency_symbol . number_format($_pg_grp_orig / 100, 2, '.', ',') : '',
                    '__has_variants'      => '1',
                    // Real product count in the subtree (was hard-coded 1/0).
                    '__variant_count'     => $_pg_gp ? (string)(int)$_pg_gp['count'] : '0',
                );
                // Adaptive link captions. A browse group EXPANDS in place; a
                // select group and every product open the detail page. See
                // _apply_catalog_listing_bindings for why each bound button
                // carries its own token.
                if (!empty($_pg_bindings_used['link_labels'])) {
                    $_lbl_kind = ($cg['display_type'] === 'browse') ? 'expand' : 'detail';
                    foreach ($_pg_bindings_used['link_labels'] as $_lbl_tok => $_lbl_pair) {
                        $g_values[$_lbl_tok] = isset($_lbl_pair[$_lbl_kind]) ? $_lbl_pair[$_lbl_kind] : '';
                    }
                }

                $g_rendered = $loop_template;
                // Longest keys first so '__short_description' is replaced before '__description'.
                $g_keys = array_keys($g_values);
                usort($g_keys, function ($a, $b) { return strlen($b) - strlen($a); });
                foreach ($g_keys as $k) {
                    $g_rendered = str_replace('^^' . $k . '^^', (string)$g_values[$k], $g_rendered);
                }
                // Strip remaining product-only tokens (inventory, mpn, gallery, …).
                $g_rendered = preg_replace('/\^\^[A-Za-z0-9_]+\^\^/', '', $g_rendered);

                // Strip Sepete Ekle for groups — adding a GROUP to cart
                // doesn't make sense; the visitor must drill into a
                // specific product first. Removes both the bindable
                // button (data-pg-catalog-atc) and any wrapping form.
                // Browse groups land on a sub-listing, select groups
                // open a variant chooser — neither needs add-to-cart
                // at the card level.
                $g_rendered = preg_replace(
                    '/<button[^>]*data-pg-catalog-atc="1"[^>]*>.*?<\/button>/is',
                    '',
                    $g_rendered
                );
                $g_rendered = preg_replace(
                    '/<form[^>]*id="pgAtcForm_[^"]*"[^>]*>.*?<\/form>/is',
                    '',
                    $g_rendered
                );

                $g_rows .= $g_rendered;
            }
            $child_groups_html = $g_rows;
        }
    }

    // Active filter chips ---------------------------------------------------
    // Renders one chip per filter actually in effect, plus a "Clear all" link.
    $chips = array();
    if ($url_drill_active && $active_group_id > 0) {
        $chips[] = array(
            'label' => lang(array('string' => 'Category: {var:1}', 'vars' => array($active_group_name ?: '#' . $active_group_id))),
            'href'  => $listing_root_url,
        );
    }
    if ($search_active) {
        $chips[] = array(
            'label' => lang(array('string' => 'Search: {var:1}', 'vars' => array($search_query))),
            'href'  => '?' . _pg_sw_build_query($query_param, '', $page_param, ''),
        );
    }
    if ($price_filter_enabled && ($pmin_param !== null || $pmax_param !== null)) {
        $lbl = lang(array('string' => 'Price: {var:1}', 'vars' => array(($pmin_param !== null ? $pmin_param : '∞') . '–' . ($pmax_param !== null ? $pmax_param : '∞'))));
        $chips[] = array(
            'label' => $lbl,
            'href'  => '?' . _pg_sw_build_query('pmin', '', $page_param, '') . (
                $pmax_param !== null ? '&' . _pg_sw_build_query('pmax', '', null, null) : ''
            ),
        );
        // Note: above href doesn't fully clear pmax; build via the cleaner path:
        $chips[count($chips)-1]['href'] = '?' . _build_clear_filters_query(array('pmin', 'pmax', $page_param));
    }
    if ($stock_filter_active && $stock_filter_enabled && !$stock_filter_default) {
        $chips[] = array(
            'label' => lang('In stock only'),
            'href'  => '?' . _pg_sw_build_query('stock', '0', $page_param, ''),
        );
    }
    if ($sort_url_key !== '') {
        $sort_lbls = array(
            'sort_order' => lang('Default order'), 'name_asc' => lang('Name ↑'), 'name_desc' => lang('Name ↓'),
            'price_asc' => lang('Price ↑'), 'price_desc' => lang('Price ↓'), 'newest' => lang('Recently added'),
        );
        $chips[] = array(
            'label' => lang(array('string' => 'Sort: {var:1}', 'vars' => array(isset($sort_lbls[$sort_url_key]) ? $sort_lbls[$sort_url_key] : $sort_url_key))),
            'href'  => '?' . _pg_sw_build_query('sort', '', $page_param, ''),
        );
    }

    // Attribute filter chips — one chip per active {attr, option} pair.
    // Added to the shared $chips array for inline rendering AND collected
    // separately for the standalone ^^__active_filters_html^^ token.
    if ($attr_filter_enabled && $active_attr_filters) {
        $_attr_cs = '';
        foreach ($active_attr_filters as $_c_aid => $_c_oid) {
            $_c_albl = isset($available_attrs[$_c_aid]) ? $available_attrs[$_c_aid]['attr_label'] : lang('Attribute');
            $_c_olbl = '';
            if (isset($available_attrs[$_c_aid])) {
                foreach ($available_attrs[$_c_aid]['options'] as $_c_opt) {
                    if ((int)$_c_opt['option_id'] === (int)$_c_oid) {
                        $_c_olbl = $_c_opt['option_label'];
                        break;
                    }
                }
            }
            if ($_c_olbl === '') $_c_olbl = '#' . $_c_oid;
            $_c_href = '?' . _pg_sw_build_query('pg_attr_' . $_c_aid, '', $page_param, '');
            $chips[] = array(
                'label' => $_c_albl . ': ' . $_c_olbl,
                'href'  => $_c_href,
            );
            $_attr_cs .=
                '<a href="' . h($_c_href) . '" class="badge text-bg-secondary text-decoration-none me-2 mb-1 d-inline-flex align-items-center" style="font-weight:normal">' .
                    h($_c_albl . ': ' . $_c_olbl) . ' <span class="bi bi-x-lg ms-2" style="font-size:.7em"></span>' .
                '</a>';
        }
        if ($_attr_cs) {
            $active_attr_chips_html = '<div class="pg-sw-attr-active-filters mb-3">' . $_attr_cs . '</div>';
        }
    }

    $chips_html = '';
    if ($chips) {
        $cs = '';
        foreach ($chips as $chip) {
            $cs .= '<a href="' . h($chip['href']) . '" class="badge text-bg-secondary text-decoration-none me-2 mb-1 d-inline-flex align-items-center" style="font-weight:normal">' .
                       h($chip['label']) . ' <span class="bi bi-x-lg ms-2" style="font-size:.7em"></span>' .
                   '</a>';
        }
        // Clear-all removes every filter-controlled param including pg_attr_* keys.
        $_clear_all_params = array('cat', 'sort', 'pmin', 'pmax', 'stock', $query_param, $page_param);
        foreach (array_keys($active_attr_filters) as $_caid) {
            $_clear_all_params[] = 'pg_attr_' . $_caid;
        }
        $cs .= '<a href="?' . h(_build_clear_filters_query($_clear_all_params)) .
               '" class="small text-decoration-none ms-2">' . h(lang('Clear all')) . '</a>';
        $chips_html = '<div class="pg-sw-active-filters mb-3">' . $cs . '</div>';
    }

    // ── Compose offcanvas + Filtreler button + Clear-all ────────────────────
    // The `__filters_offcanvas` token wraps every filter form (price, stock,
    // attribute) inside a Bootstrap left-side offcanvas. The `__filters_button`
    // token renders the trigger that opens it. Active-filter chips appear
    // both INSIDE the offcanvas (top) and as `^^__filter_chips^^` in the
    // main page area, so the visitor can clear filters from either place.
    $_pg_off_id = 'pgFilters' . (int)$widget_id;
    $_pg_any_filter_ui = ($price_filter_enabled || $stock_filter_enabled || $attr_filter_enabled);

    $clear_query_keys = array('pmin','pmax','stock','sort','attr');
    $clear_query_pairs = array();
    if (!empty($_GET) && is_array($_GET)) {
        foreach ($_GET as $k => $v) {
            if (in_array($k, $clear_query_keys, true) || $k === $page_param) continue;
            if (is_array($v)) continue;
            $clear_query_pairs[] = h($k) . '=' . urlencode((string)$v);
        }
    }
    $clear_url = '?' . implode('&', $clear_query_pairs);
    // `$stock_filter_active` reflects the CURRENT state — including the
    // configured default ("show only in-stock"). Treating that default as
    // "an active filter" makes the chip strip + clear button render on
    // every fresh page load, which the visitor sees as confusing UI clutter
    // (a "clear filters" button with nothing to clear). The right gate is:
    // stock counts as "active" only when the
    // visitor EXPLICITLY set ?stock= in the URL (i.e. opted out of or into
    // the default). Same intuition for any other configured-default filter
    // we add later.
    $has_active_filters = ($pmin_param !== null || $pmax_param !== null
                          || isset($_GET['stock'])
                          || !empty($active_attr_filters));
    $clear_button_html = $has_active_filters
        ? '<a href="' . $clear_url . '" class="btn btn-sm btn-outline-danger">'
          . '<i class="bi bi-x-circle me-1"></i>' . h(lang('Clear Filters')) . '</a>'
        : '';

    // Active chips block — used both as the col-12 row in the page layout
    // AND inside the offcanvas header. Wrapping div added so the designer
    // can hide-when-empty via CSS (`:empty`).
    //
    // Two variants — same data, different wrapping:
    //   • $filter_chips_inner_html — chips + clear button without a wrapping
    //     div. Used when designer's container is bound via section=
    //     filter_chips (their wrapper preserved).
    //   • $filter_chips_html — wrapped in `<div class="pg-sw-filter-chips ...">`.
    //     Legacy token for in-text token placement.
    $filter_chips_inner_html = '';
    $filter_chips_html       = '';
    if ($has_active_filters) {
        $filter_chips_inner_html =
              $active_attr_chips_html
            . ($clear_button_html !== '' ? '<span class="ms-auto">' . $clear_button_html . '</span>' : '');
        $filter_chips_html =
            '<div class="pg-sw-filter-chips d-flex align-items-center gap-2 flex-wrap py-2">'
          . $filter_chips_inner_html
          . '</div>';
    }

    // Filtreler button — opens the offcanvas.
    $filters_button_html = $_pg_any_filter_ui
        ? '<button type="button" class="btn btn-outline-secondary"'
        . ' data-bs-toggle="offcanvas" data-bs-target="#' . h($_pg_off_id) . '"'
        . ' aria-controls="' . h($_pg_off_id) . '">'
        . '<i class="bi bi-funnel me-1"></i>' . h(lang('Filters'))
        . ($has_active_filters ? ' <span class="badge bg-primary ms-1">!</span>' : '')
        . '</button>'
        : '';

    // Filters offcanvas — left-side, with close button + active chips at top
    // + every filter form below. Bootstrap 5 offcanvas markup so its built-in
    // JS handles open/close/keyboard escape without extra plumbing.
    //
    // Two variants:
    //   • $filters_offcanvas_inner_html — header + body without the outer
    //     `.offcanvas` wrapper. Used when designer binds their own offcanvas
    //     div (with the right class + id) via section=filters_offcanvas.
    //   • $filters_offcanvas_html — full offcanvas div, used by the legacy
    //     `^^__filters_offcanvas^^` token.
    //
    // The id MUST match `pgFilters{widget_id}` so the open_filters button's
    // data-bs-target finds it. When a designer binds a div, they're expected
    // to leave id management to the framework — section binding adds it.
    $filters_offcanvas_inner_html = '';
    $filters_offcanvas_html       = '';
    if ($_pg_any_filter_ui) {
        $filters_offcanvas_inner_html =
            '<div class="offcanvas-header border-bottom">'
          .   '<h5 class="offcanvas-title mb-0" id="' . h($_pg_off_id) . 'Title">'
          .     '<i class="bi bi-funnel me-2"></i>' . h(lang('Filters'))
          .   '</h5>'
          .   '<button type="button" class="btn-close" data-bs-dismiss="offcanvas"'
          .          ' aria-label="' . h(lang('Close')) . '"></button>'
          . '</div>'
          . '<div class="offcanvas-body">'
          .   ($filter_chips_html !== '' ? '<div class="border-bottom pb-2 mb-3">' . $filter_chips_html . '</div>' : '')
          .   $price_filter_html
          .   $stock_filter_html
          .   $attribute_filters_html
          . '</div>';
        $filters_offcanvas_html =
            '<div class="offcanvas offcanvas-start pg-sw-filters-offcanvas" tabindex="-1"'
          . ' id="' . h($_pg_off_id) . '" aria-labelledby="' . h($_pg_off_id) . 'Title">'
          . $filters_offcanvas_inner_html
          . '</div>';
    }

    // ── Stitch body ──────────────────────────────────────────────────────
    // Render order: breadcrumb → search/sort/filters toolbar (single row) →
    // active filter chips (col-12) → child groups → product loop → pagination
    // → offcanvas (sibling of everything; positioned by Bootstrap CSS).
    //
    // The toolbar wraps sort + search + Filtreler-button in a flex row so
    // the search input naturally pushes to the right with `ms-md-auto`. On
    // mobile the items stack via flex-wrap.
    //
    // Auto-stitched legacy controls SUPPRESSION:
    // When the designer's tree already provides any of these UI elements
    // via bindings (action=search_form / open_filters / submit_search,
    // section=filter_chips / filters_offcanvas / breadcrumb …), we skip
    // the auto-stitched legacy duplicate so the visitor doesn't see two
    // copies. Bindings are tracked in $_pg_bindings_used during the
    // pre-process pass above.
    $_skip_search_html  = !empty($_pg_bindings_used['action_search_form'])
                         || !empty($_pg_bindings_used['value_search_query'])
                         || !empty($_pg_bindings_used['action_submit_search']);
    $_skip_filters_btn  = !empty($_pg_bindings_used['action_open_filters']);
    $_toolbar_search    = $_skip_search_html  ? '' : $search_html;
    $_toolbar_filterbtn = $_skip_filters_btn  ? '' : $filters_button_html;

    $toolbar_html = '';
    if ($_toolbar_search !== '' || $sort_html !== '' || $_toolbar_filterbtn !== '') {
        $toolbar_html =
            '<div class="pg-sw-toolbar d-flex flex-wrap align-items-center gap-2 mb-3">'
          .   ($sort_html !== '' ? '<div class="pg-sw-toolbar-sort">' . $sort_html . '</div>' : '')
          .   ($_toolbar_search !== '' ? '<div class="pg-sw-toolbar-search ms-md-auto flex-grow-1 flex-md-grow-0">' . $_toolbar_search . '</div>' : '')
          .   ($_toolbar_filterbtn !== '' ? '<div class="pg-sw-toolbar-filters">' . $_toolbar_filterbtn . '</div>' : '')
          . '</div>';
    }

    // Toast notification for stay-on-page add-to-cart — fires when the
    // listing loads with `?cart_added=<id>` (set by the per-card form's
    // next_url). The JS is injected once per render; idempotent across
    // multiple catalog widgets on the same page via window flag. Uses
    // Bootstrap 5 Toast if available, otherwise a minimal inline fallback.
    // After firing, the cart_added param is stripped from the URL via
    // history.replaceState so a refresh doesn't re-fire the toast.
    $toast_html = '';
    if ($atc_stay) {
        $_atc_toast_msg = (string)(isset($cfg['add_to_cart_toast_message'])
            ? $cfg['add_to_cart_toast_message']
            : lang('Product added to cart.'));
        $toast_html = '<script>(function(){'
            . 'if(window.__pgCartToastInit)return;window.__pgCartToastInit=1;'
            . 'document.addEventListener("DOMContentLoaded",function(){'
            .   'try{'
            .     'var u=new URL(window.location.href);'
            .     'var added=u.searchParams.get("cart_added");'
            .     'if(!added)return;'
            .     'u.searchParams.delete("cart_added");'
            .     'history.replaceState({},"",u.pathname+(u.search?u.search:"")+u.hash);'
            .     'var msg=' . json_encode($_atc_toast_msg) . ';'
            .     'var c=document.createElement("div");'
            .     'c.style.cssText="position:fixed;top:1rem;right:1rem;z-index:2147483600;min-width:240px;max-width:360px";'
            .     'c.innerHTML=\'<div class="toast align-items-center text-bg-success border-0 show" role="alert"><div class="d-flex"><div class="toast-body"><i class="bi bi-cart-check me-2"></i>\'+msg+\'</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button></div></div>\';'
            .     'document.body.appendChild(c);'
            .     'setTimeout(function(){c.remove();},4500);'
            .     'try{var bsT=window.bootstrap&&window.bootstrap.Toast?new window.bootstrap.Toast(c.querySelector(".toast"),{delay:4500}):null;if(bsT)bsT.show();}catch(_){}'
            .   '}catch(_){}'
            . '});'
            . '})();</script>';
    }

    // $chips_html is the legacy all-filters chip block (cat + sort + price +
    // stock + attr with "Tümünü Temizle"). It's kept as the canonical chip
    // display in the auto-stitched body. $filter_chips_html (a smaller subset
    // showing only price/stock/attr with our "Filtreleri Temizle" button)
    // lives INSIDE the offcanvas only — emitting both would duplicate.
    //
    // Same suppression rule as toolbar: when the designer placed bound
    // versions of these blocks via section bindings, skip the auto-stitched
    // duplicates so the visitor doesn't see two copies.
    $_skip_breadcrumb = !empty($_pg_bindings_used['section_breadcrumb']);
    $_skip_chips      = !empty($_pg_bindings_used['section_filter_chips'])
                       || !empty($_pg_bindings_used['section_active_filters']);
    $_skip_offcanvas  = !empty($_pg_bindings_used['section_filters_offcanvas']);
    // When there are no products to loop, $loop_output is empty — substitute
    // the "Ürün bulunamadı" message in its place so the visitor sees a clear
    // signal instead of a void between the toolbar and the offcanvas.
    // Suppressed when child group cards ARE rendered (browse-type drill into
    // a parent that holds only sub-groups) — those groups already fill the
    // grid and an "empty" message would be misleading.
    $_loop_block = $loop_output;
    if ($no_products && $_loop_block === '' && $child_groups_html === '') {
        $_loop_block = $empty_html;
    }
    $body =
        ($_skip_breadcrumb ? '' : $breadcrumb_html) .
        $toolbar_html .
        ($_skip_chips      ? '' : $chips_html) .
        $child_groups_html .
        $_loop_block .
        $pagination_html .
        ($_skip_offcanvas  ? '' : $filters_offcanvas_html) .
        $toast_html;

    // Apply page-level (non-per-record) tokens to the body. These were not
    // available during per-record token replacement because they're widget-
    // wide and may sit in the static_html OR in the loop_template (designer
    // could use them anywhere).
    $page_tokens = array(
        '__active_cat_id'           => (string)$active_group_id,
        '__active_cat_name'         => $active_group_name,
        '__breadcrumb_html'         => $breadcrumb_html,
        '__group_tree_html'         => $group_tree_html,
        '__has_children'            => (string)$has_children,
        // Phase 2.5 attribute filter tokens — designer can place them anywhere
        // in the template; they are also auto-injected into $controls_html.
        '__attribute_filters_html'  => $attribute_filters_html,
        '__active_filters_html'     => $active_attr_chips_html,
        // New offcanvas-based filter tokens (designer can place each piece
        // wherever in the layout, or use the auto-stitched body).
        '__filters_button'          => $filters_button_html,
        '__filters_offcanvas'       => $filters_offcanvas_html,
        '__filter_chips'            => $filter_chips_html,
        '__has_active_filters'      => $has_active_filters ? '1' : '0',
        '__clear_filters_button'    => $clear_button_html,

        // ── Inner-only variants (no outer wrapper) ─────────────────────
        // Used by the section-binding pre-process: when a designer marks
        // their own <nav>/<div>/<aside>/<ul> with section=breadcrumb
        // (or group_tree, filter_chips, etc.), the binding pass replaces
        // that node's children with one of these inner-only tokens — so
        // the designer's wrapping tag, classes, and attributes are
        // preserved while the inner markup comes from the backend.
        '__breadcrumb_inner_html'        => $breadcrumb_inner_html,
        '__group_tree_inner_html'        => $group_tree_inner_html,
        '__filter_chips_inner_html'      => $filter_chips_inner_html,
        '__filters_offcanvas_inner_html' => $filters_offcanvas_inner_html,
        '__active_filters_inner_html'    => $active_attr_chips_html,
        '__attribute_filters_inner_html' => $attribute_filters_html,
    );
    foreach ($page_tokens as $k => $v) {
        $body = str_replace('^^' . $k . '^^', $v, $body);
    }
    if ($static_html !== '') {
        foreach ($page_tokens as $k => $v) {
            $static_html = str_replace('^^' . $k . '^^', $v, $static_html);
        }
    }

    // No auto-prepended messages placeholder — designer drops a Messages
    // content node inside the widget tree to control where alerts render.
    $_pg_assets = _pg_widget_layout_css_once();  // price-block + discount-hide + variant-states CSS
    if ($static_html === '') return $_pg_assets . $body;
    return $_pg_assets . str_replace('<!--pg-loop-slot-->', $body, $static_html);
}

// ============================================================================
// Walk a catalog_item_view widget tree and convert action/value bindings on
// component (button) and semantic (input) nodes into the concrete props the
// renderer expects.
//
// Bindings the designer can attach (in `node.props._bindings`):
//   - On a btn component:
//       _bindings.action === 'add_to_cart'
//         → renders as <button type="submit"> inside the auto-wrapped form.
//   - On a semantic <input> node:
//       _bindings.value === 'quantity'
//         → renders as <input type="number" name="quantity" min="1" …>
//         → max attribute is set when stock tracking is on AND backorder is off
//           (matches the Pinegrap rule: inventory=1 && backorder=0 ⇒ cap at
//           inventory_quantity; otherwise no max).
//
// The mutation is in-place. Designer-added attrs that don't collide with the
// managed keys are preserved; managed keys (type/name/min/max/value) are
// overwritten so a stale designer value can't sabotage the form.
// Universal messages-node injector for system widget trees.
//   - Pass 1: walks the tree and overrides every messages content node's
//     formName with the widget's liveform name (per-widget scope).
//   - Pass 2: if NO messages node exists, auto-prepends one to the first
//     container (or root if no container). This is a safety net for widget
//     trees saved before the starter tree shipped with Messages by default
//     — without it, legacy widgets would never surface server-side errors.
//
// Designer-controlled placement still wins: any messages node already in
// the tree just gets its formName synced; nothing is moved or duplicated.
// Walk tree, find the messages content node, and splice a custom_html
// sibling immediately after it. Used by widgets that need to place
// dynamic alerts (upsell offers, applied promos, …) inside the widget\'s
// message area without ENGAGING the designer\'s tree structure. If no
// messages node is found, prepends the HTML to the first container\'s
// children as a fallback so it still renders inside the widget body.
function _pg_inject_html_after_messages(&$tree, $html)
{
    if (!is_array($tree) || $html === '') return;
    $new_node = array(
        'type'     => 'content',
        'props'    => array('contentType' => 'custom_html', 'html' => $html, 'cssClass' => ''),
        'children' => array(),
    );
    $done = false;
    $walk = function (&$node) use (&$walk, $new_node, &$done) {
        if ($done) return;
        if (!is_array($node) || empty($node['children']) || !is_array($node['children'])) return;
        foreach ($node['children'] as $i => $child) {
            if (is_array($child) && isset($child['type']) && $child['type'] === 'content'
                && isset($child['props']['contentType']) && $child['props']['contentType'] === 'messages') {
                array_splice($node['children'], $i + 1, 0, array($new_node));
                $done = true;
                return;
            }
            $walk($node['children'][$i]);
            if ($done) return;
        }
    };
    $walk($tree);
    if (!$done && !empty($tree['children']) && is_array($tree['children'][0])
        && isset($tree['children'][0]['type']) && $tree['children'][0]['type'] === 'container') {
        $kids = isset($tree['children'][0]['children']) ? $tree['children'][0]['children'] : array();
        $tree['children'][0]['children'] = array_merge(array($new_node), $kids);
    }
}

function _pg_inject_messages_node(&$tree, $liveform_name = '')
{
    if (!is_array($tree)) return;

    $found = false;
    $walker = function (&$node) use (&$walker, &$found, $liveform_name) {
        if (!is_array($node)) return;
        if (isset($node['type']) && $node['type'] === 'content'
            && isset($node['props']['contentType']) && $node['props']['contentType'] === 'messages') {
            $found = true;
            if ($liveform_name !== '') {
                $node['props']['formName'] = $liveform_name;
            }
        }
        if (!empty($node['children']) && is_array($node['children'])) {
            foreach ($node['children'] as &$_pgc) { $walker($_pgc); }
            unset($_pgc);
        }
    };
    $walker($tree);

    if (!$found && !empty($tree['children'])) {
        $msg_node = array(
            'type'     => 'content',
            'props'    => array(
                'contentType' => 'messages',
                'cssClass'    => '',
                'formName'    => $liveform_name,
            ),
            'children' => array(),
        );
        if (is_array($tree['children'][0])
            && isset($tree['children'][0]['type'])
            && $tree['children'][0]['type'] === 'container') {
            $kids = isset($tree['children'][0]['children']) ? $tree['children'][0]['children'] : array();
            $tree['children'][0]['children'] = array_merge(array($msg_node), $kids);
        } else {
            array_unshift($tree['children'], $msg_node);
        }
    }
}

// True for nodes that should NOT render in the current context — e.g. a
// recipient binding in a single-recipient site. Caller filters these out
// before recursing so the tree walker never visits them.
function _civ_should_skip_node($node, $context)
{
    if (!is_array($node)) return false;

    // Single-recipient mode? Recipient picker UI is irrelevant. Skip every
    // node carrying recipient-related bindings (the inputs themselves), the
    // explicit `_bindings.section='recipient_picker'` marker (set on the
    // wrapping div in newer starter trees), AND any container whose direct
    // children include a recipient_select/recipient_name binding (heuristic
    // for older trees where the wrapping div has no marker but its children
    // are the picker inputs + a label paragraph). Without the heuristic the
    // label "Alıcı" rendered alone, with no controls beneath it.
    if (empty($context['multi_recipient_active'])) {
        $b = (isset($node['props']['_bindings']) && is_array($node['props']['_bindings']))
                ? $node['props']['_bindings'] : array();
        $val     = isset($b['value'])   ? $b['value']   : '';
        $section = isset($b['section']) ? $b['section'] : '';

        // 1) Direct binding on this node — the input/select itself.
        if ($val === 'recipient_select' || $val === 'recipient_name') return true;

        // 2) Explicit section marker on wrapping div.
        if ($section === 'recipient_picker') return true;

        // 3) Heuristic: container with at least one recipient-bound child
        //    counts as the picker wrapper. Drop the whole container.
        if (!empty($node['children']) && is_array($node['children'])) {
            foreach ($node['children'] as $_ch) {
                if (!is_array($_ch)) continue;
                $cb = (isset($_ch['props']['_bindings']) && is_array($_ch['props']['_bindings']))
                          ? $_ch['props']['_bindings'] : array();
                $cv = isset($cb['value']) ? $cb['value'] : '';
                if ($cv === 'recipient_select' || $cv === 'recipient_name') return true;
            }
        }
    }
    return false;
}

/**
 * Resolve the scope root for catalog_item_view breadcrumbs by inspecting the
 * catalog_listing widget on the linked catalog page.
 *
 * Walks page → style → style_tree_json to find shared_ref nodes pointing to
 * shared_components with regionType='catalog_listing'. Returns the first
 * matching widget's `product_group_id` from cfg.
 *
 * Why auto-derive? When a visitor lands on a product detail page that links
 * back to a catalog listing page, the breadcrumb should be CONFINED to the
 * categories WITHIN that listing's selected product group — not the absolute
 * root. Without this, the crumb walk leaks parent categories that live
 * outside the catalog's "world" (and offers links the visitor shouldn't see
 * inside this catalog context).
 *
 * Returns 0 when:
 *   • $page_id <= 0
 *   • Page has no style or no shared_refs
 *   • No referenced shared_component is a catalog_listing widget
 *   • The catalog_listing widget has product_group_id=0 (showing all → no scope)
 *
 * @param int $page_id
 * @return int
 */
function _civ_resolve_catalog_listing_root_group_id($page_id)
{
    $page_id = (int)$page_id;
    if ($page_id <= 0) return 0;

    // Pull the page's layout tree once. Anonymous widgets live as shared_refs
    // inside this JSON; we match by sharedId substring (cheap + index-friendly
    // compared to JSON parsing the entire tree).
    $style_tree = pg_page_tree_json($page_id);
    if ($style_tree === '') return 0;

    // Candidate catalog_listing widgets — one of these is the listing on the
    // page. We could intersect via JSON_EXTRACT, but a LIKE scan + substring
    // match is portable across MySQL versions and the candidate set stays
    // small (one per catalog page, typically <10 across a whole site).
    $cl_rows = db_items(
        "SELECT id, system_region_config FROM shared_components
         WHERE system_region_config LIKE '%\"regionType\":\"catalog_listing\"%'"
    );
    if (!is_array($cl_rows)) return 0;

    foreach ($cl_rows as $r) {
        $cid = (int)$r['id'];
        if ($cid <= 0) continue;
        // Match `"sharedId":<id>` followed by `,` (more props after) or `}`
        // (last prop) to avoid substring collisions (id=1 vs id=10, etc).
        if (strpos($style_tree, '"sharedId":' . $cid . ',') === false &&
            strpos($style_tree, '"sharedId":' . $cid . '}') === false) {
            continue;
        }
        $cl_cfg = json_decode((string)$r['system_region_config'], true);
        if (is_array($cl_cfg) && !empty($cl_cfg['product_group_id'])) {
            return (int)$cl_cfg['product_group_id'];
        }
        // Found the widget but it shows ALL groups — no scope to apply.
        return 0;
    }
    return 0;
}

/**
 * Render the catalog_item_view breadcrumb — Ana Katalog → Group → Subgroup → Product.
 *
 * Walks parent_id from the product's primary product_group up to the root
 * (or to the configured `catalog_listing_root_group_id` if set, mirroring
 * the catalog_listing widget's "world" scoping).
 *
 * URL strategy:
 *   • "Ana Katalog" + group crumbs link to the catalog listing page when
 *     `cfg['catalog_listing_page_id']` is set. Each group crumb appends
 *     its `address_name` slug so the visitor lands on that group's
 *     drill-down view.
 *   • When no catalog page is configured, group crumbs render as plain
 *     non-link text (still useful for context).
 *   • The final crumb is the active product / select-group (text only).
 *
 * Out-of-scope handling: when $root_group_id > 0 and the walk doesn't reach
 * that root (product lives in a different branch of the product_group tree),
 * group crumbs are dropped to avoid leaking categories outside the catalog
 * scope. The "Ana Katalog" link + final product crumb still render so the
 * visitor has a navigation anchor.
 *
 * @param array  $p              The active product row (id, name, short_description, …).
 * @param int    $select_group_id  Set when URL slug pointed at a select-group.
 * @param string $select_group_name Friendly name when select-group active.
 * @param int    $catalog_page_id   Optional listing page id for crumb URLs.
 * @param int    $root_group_id     Optional scope root (excludes ancestors above this).
 * @return array                  ['inner' => '<ol>…</ol>', 'full' => '<nav>…</nav>']
 */
function _civ_render_breadcrumb($p, $select_group_id, $select_group_name, $catalog_page_id, $root_group_id)
{
    $base_path = defined('OUTPUT_PATH') ? OUTPUT_PATH : '/';
    $catalog_root_url = '';
    $catalog_path_prefix = '';
    if ($catalog_page_id > 0) {
        $cat_page_name = db_value(
            "SELECT page_name FROM page WHERE page_id = '" . (int)$catalog_page_id . "' LIMIT 1"
        );
        if ($cat_page_name) {
            $catalog_root_url    = $base_path . encode_url_path($cat_page_name);
            $catalog_path_prefix = $catalog_root_url . '/';
        }
    }

    // Resolve the starting group:
    //   • If a select-group is active (URL slug = group slug), start from it
    //   • Otherwise, start from the product's primary product_group (lowest id)
    $start_group_id = 0;
    if ($select_group_id > 0) {
        $start_group_id = (int)$select_group_id;
    } elseif (is_array($p) && !empty($p['id'])) {
        $start_group_id = (int)db_value(
            "SELECT product_group FROM products_groups_xref
             WHERE product = '" . (int)$p['id'] . "'
             ORDER BY sort_order ASC, product_group ASC
             LIMIT 1"
        );
    }

    // Walk parent_id upward, collecting groups (excluding the configured root).
    // $reached_root tracks whether the walk landed inside the configured scope:
    //   • root not configured ($root_group_id == 0): always considered "reached"
    //   • root configured: only true when the walk actually hit it
    // When the walk doesn't reach a configured root, the product belongs to a
    // different branch of the tree (outside the catalog's "world") — drop the
    // group crumbs so we don't surface ancestors that aren't part of this
    // catalog scope.
    $crumbs = array();
    $cur = $start_group_id;
    $reached_root = ($root_group_id <= 0);
    $safety = 16;
    while ($cur > 0 && $safety-- > 0) {
        if ($root_group_id > 0 && $cur === $root_group_id) {
            $reached_root = true;
            break;
        }
        $row = db_item(
            "SELECT id, name, address_name, parent_id FROM product_groups WHERE id = '" . (int)$cur . "' LIMIT 1"
        );
        if (!$row) break;
        array_unshift($crumbs, $row);
        $cur = (int)$row['parent_id'];
    }
    if (!$reached_root) {
        // Out of scope — keep "Ana Katalog" + final product crumb only.
        $crumbs = array();
    }

    $bcParts = array();
    // First crumb: "Ana Katalog" — link if catalog page configured, text otherwise.
    if ($catalog_root_url !== '') {
        $bcParts[] = '<a href="' . h($catalog_root_url) . '">' . h(lang('Main Catalog')) . '</a>';
    } else {
        $bcParts[] = '<span>' . h(lang('Main Catalog')) . '</span>';
    }
    // Group ancestors → link when catalog page configured + address_name set.
    if ($crumbs) {
        // Last crumb in $crumbs is the IMMEDIATE parent group of the
        // displayed product. When the URL points at a select-group, that
        // group itself IS the final destination — display as active text.
        // When the URL points at a product, the immediate group is still
        // a clickable link (drilling INTO that group), and the product's
        // own name becomes the final active crumb.
        $last_idx = count($crumbs) - 1;
        $on_select_group = ((int)$select_group_id > 0);
        foreach ($crumbs as $i => $c) {
            $is_last_crumb = ($i === $last_idx);
            $href = ($catalog_path_prefix !== '' && !empty($c['address_name']))
                       ? $catalog_path_prefix . encode_url_path((string)$c['address_name'])
                       : '';
            if ($on_select_group && $is_last_crumb) {
                // The last crumb IS the active select-group. Show as text.
                $bcParts[] = '<span class="active">' . h($c['name']) . '</span>';
            } elseif ($href !== '') {
                $bcParts[] = '<a href="' . h($href) . '">' . h($c['name']) . '</a>';
            } else {
                $bcParts[] = '<span>' . h($c['name']) . '</span>';
            }
        }
    }
    // Final crumb: the product itself (when not already covered by select-group).
    if ($select_group_id <= 0 && is_array($p) && !empty($p['id'])) {
        $product_name = !empty($p['short_description']) ? (string)$p['short_description'] : (string)$p['name'];
        $bcParts[] = '<span class="active">' . h($product_name) . '</span>';
    }

    $inner = '<ol class="breadcrumb mb-0">'
           . implode('', array_map(function ($p) { return '<li class="breadcrumb-item">' . $p . '</li>'; }, $bcParts))
           . '</ol>';
    $full  = '<nav class="pg-civ-breadcrumb mb-3" aria-label="breadcrumb">' . $inner . '</nav>';
    return array('inner' => $inner, 'full' => $full);
}

/**
 * Render the per-item product-form as EDITABLE INPUT controls.
 *
 * Some products (e.g. Hediye Kartı, Üyelik, Konferans Kaydı) attach an
 * order form. The visitor fills those fields INSIDE the cart row — the
 * legacy custom-layout cart did this and the new designer-built cart
 * keeps the same UX: text inputs, textareas, selects, date pickers,
 * etc. nested under the product row, pre-filled from any existing
 * form_data, and submitted alongside the cart-update POST.
 *
 * Field naming follows the legacy convention so cart_action.php can
 * detect + save with one regex:
 *
 *   order_item_<item_id>_quantity_number_<q>_form_field_<field_id>
 *
 * Quantity loop:
 *   • `One Form per Quantity` → render ONE form-block per qty unit
 *     (each with "<Product> (i / N)" header)
 *   • `One Form per Product`  → render a SINGLE block (qty=1)
 *
 * Empty when:
 *   • $order_item_id / $product_id is 0
 *   • Product carries no form_fields
 *
 * @param int    $order_item_id  PK in order_items
 * @param int    $product_id     FK products.id
 * @param int    $quantity       Cart quantity for this item
 * @param string $product_name   Friendly name for per-quantity headers
 * @param string $form_qty_type  products.form_quantity_type
 *                                ('One Form per Quantity' | 'One Form per Product')
 * @param string $cart_form_id   id of the wrapping cart form — input
 *                                elements use HTML5 `form="..."` so they
 *                                submit alongside the qty-update form
 *                                regardless of where they sit in the DOM.
 * @return string                HTML block; empty when product has no form.
 */
function _pg_render_cart_item_form_data($order_item_id, $product_id, $quantity, $product_name, $form_qty_type, $cart_form_id = '', $legend = '')
{
    if ($order_item_id <= 0 || $product_id <= 0) return '';
    // `rows` and `cols` are MySQL reserved words → must be backticked.
    // We alias them to underscore-suffixed names for cleaner PHP access.
    $fields = db_items(
        "SELECT id, label, type, required, default_value, `rows` AS rows_, `cols` AS cols_,
                size, maxlength, multiple, information, wysiwyg, contact_field
         FROM form_fields
         WHERE (product_id = '" . (int)$product_id . "') AND (form_type = 'product')
         ORDER BY sort_order ASC, id ASC"
    );
    if (!$fields) return '';

    // Pre-load existing form_data so we can pre-fill INPUT values on render.
    $existing = db_items(
        "SELECT form_field_id, data, type AS data_type, quantity_number
         FROM form_data
         WHERE order_item_id = '" . (int)$order_item_id . "'
         ORDER BY quantity_number ASC, id ASC"
    );
    $by_q = array();
    if (is_array($existing)) {
        foreach ($existing as $r) {
            $q = max(1, (int)$r['quantity_number']);
            $by_q[$q][(int)$r['form_field_id']] = $r;
        }
    }

    // Pre-load options for pick-list / radio / checkbox fields.
    // Single batched query, then index by field_id.
    $field_ids = array();
    foreach ($fields as $f) {
        if (in_array($f['type'], array('pick list', 'radio button', 'check box'), true)) {
            $field_ids[] = (int)$f['id'];
        }
    }
    $options_by_field = array();
    if ($field_ids) {
        $ids_csv = implode(',', $field_ids);
        // form_field_options stores the visible option text in `label`, not `name`
        // (the products schema renamed similar columns earlier — this query was
        // left out of date and threw "Unknown column 'name'" the first time a
        // cart row with a pick-list / radio / checkbox field tried to render).
        $opt_rows = db_items(
            "SELECT form_field_id, label, value
             FROM form_field_options
             WHERE form_field_id IN ($ids_csv)
             ORDER BY sort_order ASC, id ASC"
        );
        if (is_array($opt_rows)) {
            foreach ($opt_rows as $opt) {
                $options_by_field[(int)$opt['form_field_id']][] = array(
                    'name'  => (string)$opt['label'],
                    'value' => (string)$opt['value'],
                );
            }
        }
    }

    $is_per_qty   = ($form_qty_type === 'One Form per Quantity');
    $qty_iter     = $is_per_qty ? max(1, (int)$quantity) : 1;
    $multi_header = $is_per_qty && $qty_iter > 1;
    $form_attr    = $cart_form_id !== '' ? ' form="' . h($cart_form_id) . '"' : '';

    // Legend text comes from the DATA: the admin-set form name (products.
    // form_name), falling back to the product name. Callers used to print
    // their own heading above this block with an invented label and a
    // decorative icon — invented copy the operator can't edit, so it's gone.
    // Legacy renders exactly this: <fieldset> + <legend> with the form name
    // and "(n / m)" when a form repeats per quantity.
    $legend_base = ($legend !== '') ? $legend : $product_name;

    $out = '<div class="pg-cart-item-form mt-3" data-pg-cart-item-form="' . (int)$order_item_id . '">';
    for ($q = 1; $q <= $qty_iter; $q++) {
        $val_map = isset($by_q[$q]) ? $by_q[$q] : array();

        $out .= '<fieldset class="pg-cart-item-form-block border rounded p-3 mt-2">';
        if ($legend_base !== '' || $multi_header) {
            // `float-none w-auto px-2` is the Bootstrap 5 recipe for a legend
            // that sits ON the fieldset border instead of stretching full width.
            $out .= '<legend class="float-none w-auto px-2 fs-6 fw-semibold">'
                  . h($legend_base)
                  . ($multi_header ? ' (' . $q . ' / ' . $qty_iter . ')' : '')
                  . '</legend>';
        }

        foreach ($fields as $f) {
            $fid    = (int)$f['id'];
            $type   = (string)$f['type'];
            $label  = (string)$f['label'];
            $req    = !empty($f['required']) ? ' <span class="text-danger">*</span>' : '';
            $req_attr = !empty($f['required']) ? ' required' : '';
            $row    = isset($val_map[$fid]) ? $val_map[$fid] : null;
            $val    = $row ? (string)$row['data'] : (string)$f['default_value'];
            // Stored date/time fields use a unix timestamp. Convert for the
            // matching <input> shape (date / datetime-local / time).
            $val_for_input = $val;
            if ($val !== '' && is_numeric($val)) {
                if ($type === 'date')               $val_for_input = date('Y-m-d', (int)$val);
                elseif ($type === 'date and time')  $val_for_input = date('Y-m-d\TH:i', (int)$val);
                elseif ($type === 'time')           $val_for_input = date('H:i', (int)$val);
            }
            $name = 'order_item_' . (int)$order_item_id . '_quantity_number_' . $q . '_form_field_' . $fid;
            $id   = 'pgCif_' . (int)$order_item_id . '_q' . $q . '_f' . $fid;

            // 'information' renders only the label / info HTML — no input.
            if ($type === 'information') {
                $info = (string)$f['information'];
                if ($label !== '' || $info !== '') {
                    $out .= '<div class="mb-3 small">';
                    if ($label !== '') $out .= '<div class="fw-semibold">' . h($label) . '</div>';
                    if ($info !== '')  $out .= '<div class="text-muted">' . $info . '</div>';
                    $out .= '</div>';
                }
                continue;
            }

            $out .= '<div class="mb-3">';
            $out .= '<label for="' . h($id) . '" class="form-label">' . h($label) . $req . '</label>';

            switch ($type) {
                case 'text area':
                    $rows_ = max(1, (int)$f['rows_']);
                    $cols_ = max(1, (int)$f['cols_']);
                    $maxl  = (int)$f['maxlength'];
                    $out .= '<textarea name="' . h($name) . '" id="' . h($id) . '"'
                          . ' class="form-control"'
                          . ' rows="' . $rows_ . '"'
                          . ' cols="' . $cols_ . '"'
                          . ($maxl > 0 ? ' maxlength="' . $maxl . '"' : '')
                          . $form_attr . $req_attr
                          . '>' . h($val_for_input) . '</textarea>';
                    break;

                case 'pick list':
                    $multi  = !empty($f['multiple']) ? ' multiple' : '';
                    $multi_n = !empty($f['multiple']) ? '[]' : '';
                    $opts   = isset($options_by_field[$fid]) ? $options_by_field[$fid] : array();
                    $out .= '<select name="' . h($name . $multi_n) . '" id="' . h($id) . '"'
                          . ' class="form-select"' . $multi . $form_attr . $req_attr . '>';
                    if (!$multi) $out .= '<option value="">' . h(lang('Select...')) . '</option>';
                    foreach ($opts as $opt) {
                        $sel = ((string)$opt['value'] === (string)$val) ? ' selected' : '';
                        $out .= '<option value="' . h($opt['value']) . '"' . $sel . '>' . h($opt['name']) . '</option>';
                    }
                    $out .= '</select>';
                    break;

                case 'radio button':
                    $opts = isset($options_by_field[$fid]) ? $options_by_field[$fid] : array();
                    $out .= '<div>';
                    foreach ($opts as $i => $opt) {
                        $rid = $id . '_' . $i;
                        $chk = ((string)$opt['value'] === (string)$val) ? ' checked' : '';
                        $out .= '<div class="form-check">'
                              . '<input class="form-check-input" type="radio"'
                              . ' name="' . h($name) . '" id="' . h($rid) . '"'
                              . ' value="' . h($opt['value']) . '"'
                              . $form_attr . $req_attr . $chk . '>'
                              . '<label class="form-check-label" for="' . h($rid) . '">' . h($opt['name']) . '</label>'
                              . '</div>';
                    }
                    $out .= '</div>';
                    break;

                case 'check box':
                    $opts = isset($options_by_field[$fid]) ? $options_by_field[$fid] : array();
                    // Selected values stored as comma- or pipe-separated.
                    $selected_set = array();
                    foreach (preg_split('/[,|]/', $val) as $sv) {
                        $sv = trim($sv);
                        if ($sv !== '') $selected_set[$sv] = true;
                    }
                    $out .= '<div>';
                    foreach ($opts as $i => $opt) {
                        $rid = $id . '_' . $i;
                        $chk = isset($selected_set[(string)$opt['value']]) ? ' checked' : '';
                        $out .= '<div class="form-check">'
                              . '<input class="form-check-input" type="checkbox"'
                              . ' name="' . h($name) . '[]" id="' . h($rid) . '"'
                              . ' value="' . h($opt['value']) . '"'
                              . $form_attr . $chk . '>'
                              . '<label class="form-check-label" for="' . h($rid) . '">' . h($opt['name']) . '</label>'
                              . '</div>';
                    }
                    $out .= '</div>';
                    break;

                case 'date':
                    $out .= '<input type="date" name="' . h($name) . '" id="' . h($id) . '"'
                          . ' class="form-control" value="' . h($val_for_input) . '"'
                          . $form_attr . $req_attr . '>';
                    break;

                case 'date and time':
                    $out .= '<input type="datetime-local" name="' . h($name) . '" id="' . h($id) . '"'
                          . ' class="form-control" value="' . h($val_for_input) . '"'
                          . $form_attr . $req_attr . '>';
                    break;

                case 'time':
                    $out .= '<input type="time" name="' . h($name) . '" id="' . h($id) . '"'
                          . ' class="form-control" value="' . h($val_for_input) . '"'
                          . $form_attr . $req_attr . '>';
                    break;

                case 'email address':
                    $out .= '<input type="email" name="' . h($name) . '" id="' . h($id) . '"'
                          . ' class="form-control" value="' . h($val_for_input) . '"'
                          . ((int)$f['maxlength'] > 0 ? ' maxlength="' . (int)$f['maxlength'] . '"' : '')
                          . $form_attr . $req_attr . '>';
                    break;

                case 'file upload':
                    $out .= '<input type="file" name="' . h($name) . '" id="' . h($id) . '"'
                          . ' class="form-control"'
                          . $form_attr . $req_attr . '>';
                    if ($val !== '') {
                        $out .= '<div class="form-text small">' . h(lang('Current file:')) . ' ' . h($val_for_input) . '</div>';
                    }
                    break;

                case 'text box':
                default:
                    $out .= '<input type="text" name="' . h($name) . '" id="' . h($id) . '"'
                          . ' class="form-control" value="' . h($val_for_input) . '"'
                          . ((int)$f['maxlength'] > 0 ? ' maxlength="' . (int)$f['maxlength'] . '"' : '')
                          . $form_attr . $req_attr . '>';
                    break;
            }

            $out .= '</div>';
        }
        $out .= '</fieldset>';   // opened as <fieldset> above (border + legend)
    }
    $out .= '</div>';
    return $out;
}

/**
 * Render the per-item product-form as READ-ONLY output (no inputs).
 *
 * Sibling of _pg_render_cart_item_form_data(): same data source, same
 * fieldset/legend/label shape, but the values are printed instead of
 * being placed in editable controls. Used by the `order_view` system
 * widget, where the order is already placed — showing live inputs there
 * would imply the visitor can still change what they bought.
 *
 * Legacy parity: get_order_receipt.php / get_view_order_screen_content.php
 * call get_submitted_product_form_content_with_form_fields(), which
 * produces the same label/value pairs (as bare <tr> rows for the old
 * table layout). The value semantics are copied exactly:
 *   • multiple form_data rows for one field  → joined with ", "
 *   • wysiwyg field                          → filtered with pg_sanitize_rich_text(), not escaped
 *   • everything else                        → prepare_form_data_for_output(…, true)
 *   • type 'information'                     → label + info block, no value
 *
 * Deviation from legacy (deliberate): when the order item has NO saved
 * form_data at all, we return '' instead of a fieldset full of blank
 * labels. Orders placed before a form was attached to the product would
 * otherwise render an empty bordered box on the customer's order page.
 *
 * @param int    $order_item_id  PK in order_items
 * @param int    $product_id     FK products.id
 * @param int    $quantity       Ordered quantity (drives the per-qty loop)
 * @param string $form_qty_type  products.form_quantity_type
 *                                ('One Form per Quantity' | 'One Form per Product')
 * @param string $legend         Fieldset legend (products.form_name, already lang()'d)
 * @return string                HTML block; empty when there is nothing to show.
 */
function _pg_render_order_item_form_data_readonly($order_item_id, $product_id, $quantity, $form_qty_type, $legend = '')
{
    $order_item_id = (int)$order_item_id;
    $product_id    = (int)$product_id;
    if ($order_item_id <= 0 || $product_id <= 0) return '';

    $fields = db_items(
        "SELECT id, label, type, information, wysiwyg
         FROM form_fields
         WHERE (product_id = '" . $product_id . "') AND (form_type = 'product')
         ORDER BY sort_order ASC, id ASC"
    );
    if (!$fields) return '';

    // Load every saved value in ONE query, then index by quantity_number →
    // field_id → list of values. The legacy helper runs a SELECT per field
    // per quantity; on a qty-10 gift card with 5 fields that is 50 queries.
    $rows = db_items(
        "SELECT form_field_id, data, quantity_number
         FROM form_data
         WHERE order_item_id = '" . $order_item_id . "'
         ORDER BY quantity_number ASC, id ASC"
    );
    if (!$rows) return '';   // nothing was ever submitted for this line item

    $by_q = array();
    foreach ($rows as $r) {
        $q = max(1, (int)$r['quantity_number']);
        $by_q[$q][(int)$r['form_field_id']][] = (string)$r['data'];
    }

    // Same quantity loop as the editable renderer and the legacy receipt:
    // 'One Form per Quantity' repeats the block, capped at 100.
    $is_per_qty = ($form_qty_type === 'One Form per Quantity');
    $qty_iter   = $is_per_qty ? min(100, max(1, (int)$quantity)) : 1;
    $multi      = $is_per_qty && $qty_iter > 1;

    $out = '<div class="pg-ov-item-form mt-2" data-pg-ov-item-form="' . $order_item_id . '">';
    for ($q = 1; $q <= $qty_iter; $q++) {
        $vals = isset($by_q[$q]) ? $by_q[$q] : array();

        $out .= '<fieldset class="pg-ov-item-form-block border rounded p-3 mt-2">';
        if ($legend !== '' || $multi) {
            $out .= '<legend class="float-none w-auto px-2 fs-6 fw-semibold">'
                  . h($legend)
                  . ($multi ? ' (' . $q . ' / ' . $qty_iter . ')' : '')
                  . '</legend>';
        }

        foreach ($fields as $f) {
            $fid   = (int)$f['id'];
            $type  = (string)$f['type'];
            $label = (string)$f['label'];

            if ($type === 'information') {
                $info = (string)$f['information'];
                if ($label !== '' || $info !== '') {
                    $out .= '<div class="mb-2 small">';
                    if ($label !== '') $out .= '<div class="fw-semibold">' . h($label) . '</div>';
                    if ($info !== '')  $out .= '<div class="text-muted">' . $info . '</div>';
                    $out .= '</div>';
                }
                continue;
            }

            $data = isset($vals[$fid]) ? implode(', ', $vals[$fid]) : '';
            // wysiwyg fields hold markup typed by the shopper, so the value is
            // filtered instead of escaped: escaping would show raw tags to
            // the visitor, while printing it as-is would trust whatever was
            // stored (rows saved before the store path filtered, or written
            // by any other route). Same treatment as the other order views.
            $output_data = !empty($f['wysiwyg'])
                ? prepare_form_data_for_output(pg_sanitize_rich_text($data), $type, false)
                : prepare_form_data_for_output($data, $type, true);

            // No grid opinion here — label above, value below, full width.
            // Same stacking as the editable renderer so a designer styling
            // one gets the other for free.
            $out .= '<div class="mb-2">'
                  . '<div class="form-label mb-0 fw-semibold">' . h($label) . '</div>'
                  . '<div class="pg-ov-form-value">' . $output_data . '</div>'
                  . '</div>';
        }
        $out .= '</fieldset>';
    }
    $out .= '</div>';
    return $out;
}

/**
 * Render the gift-card recipient details as READ-ONLY output.
 *
 * Gift cards are NOT driven by products.form — they carry their own
 * products.gift_card flag and a fixed four-column row in
 * order_item_gift_cards. See the note in _render_system_widget_shopping_cart:
 * the same block exists in three places, so a change here usually needs a
 * companion change there.
 *
 * Legacy parity: get_order_receipt.php prints Amount / Recipient Email /
 * Your Name / Message / Delivery Date, with "Immediate" when the delivery
 * date is 0000-00-00.
 *
 * @param int      $order_item_id PK in order_items
 * @param int      $quantity      Ordered quantity (one block per card, capped 100)
 * @param int      $unit_cents    Per-card amount in cents
 * @param callable $fmt_money     Cents → display string
 * @param callable $fmt_date      'YYYY-MM-DD' → display string ('' when unusable)
 * @return string                 HTML block; empty when no rows saved.
 */
function _pg_render_order_item_gift_card_readonly($order_item_id, $quantity, $unit_cents, $fmt_money, $fmt_date)
{
    $order_item_id = (int)$order_item_id;
    if ($order_item_id <= 0 || (int)$quantity <= 0) return '';

    $rows = db_items(
        "SELECT quantity_number, from_name, recipient_email_address, message, delivery_date
         FROM order_item_gift_cards
         WHERE order_item_id = '" . $order_item_id . "'
         ORDER BY quantity_number ASC"
    );
    if (!$rows) return '';

    $by_q = array();
    foreach ($rows as $r) { $by_q[max(1, (int)$r['quantity_number'])] = $r; }

    $count = min(100, max(1, (int)$quantity));   // same cap as legacy
    $multi = $count > 1;

    $line = function ($label, $value_html) {
        return '<div class="mb-2">'
             . '<div class="form-label mb-0 fw-semibold">' . h($label) . '</div>'
             . '<div class="pg-ov-form-value">' . $value_html . '</div>'
             . '</div>';
    };

    $out = '<div class="pg-ov-item-gift-card mt-2" data-pg-ov-item-gift-card="' . $order_item_id . '">';
    for ($q = 1; $q <= $count; $q++) {
        if (!isset($by_q[$q])) continue;   // card never filled in — skip, don't stub
        $gc = $by_q[$q];

        $delivery = (string)$gc['delivery_date'];
        $delivery_out = ($delivery === '' || $delivery === '0000-00-00')
            ? lang('Immediate')
            : call_user_func($fmt_date, $delivery);

        $out .= '<fieldset class="pg-ov-item-gift-card-block border rounded p-3 mt-2">'
              . '<legend class="float-none w-auto px-2 fs-6 fw-semibold">'
              . h(lang('Gift card')) . ($multi ? ' (' . $q . ' / ' . $count . ')' : '')
              . '</legend>'
              . $line(lang('Amount'),         '<strong>' . h(call_user_func($fmt_money, (int)$unit_cents)) . '</strong>')
              . $line(lang('Recipient Email'), h((string)$gc['recipient_email_address']))
              . $line(lang('Your Name'),       h((string)$gc['from_name']))
              . $line(lang('Message'),         nl2br(h((string)$gc['message'])))
              . $line(lang('Delivery Date'),   h($delivery_out))
              . '</fieldset>';
    }
    $out .= '</div>';
    return $out;
}

/**
 * Render a unified "price block" — strike-through original + discounted
 * value when a product has an active offer, plain price when not.
 *
 * Used by every widget that displays a per-product price (catalog_listing
 * cards, shopping_cart rows, catalog_item_view cross-sell cards). Keeps
 * the visual consistent across all surfaces:
 *   • No discount → `<span class="pg-price">₺199.95</span>`
 *   • Discount    → `<span class="pg-price-original">₺299.95</span>
 *                    <span class="pg-price-current">₺269.95</span>`
 *
 * The associated CSS lives in pg_carousel_layout.css (loaded once per page
 * by _pg_fancybox_assets_once); designer can override via custom style.
 *
 * @param int    $original_cents  Sticker price in cents.
 * @param int    $effective_cents Price after offers in cents (= original
 *                                when no discount applies).
 * @param string $currency_symbol Locale currency symbol (₺, $, €, …).
 * @param array  $opts            Optional: ['wrap' => true]  → wrap the
 *                                whole block in a <span class="pg-price-block">.
 *                                Default: true.
 * @return string                 Inline HTML.
 */
function _pg_format_price_with_discount($original_cents, $effective_cents, $currency_symbol, $opts = array())
{
    $wrap   = !isset($opts['wrap']) || !empty($opts['wrap']);
    $orig_d = ((int)$original_cents) / 100;
    $eff_d  = ((int)$effective_cents) / 100;
    $orig_s = $currency_symbol . number_format($orig_d, 2, '.', ',');
    $eff_s  = $currency_symbol . number_format($eff_d,  2, '.', ',');
    $has_d  = ((int)$effective_cents < (int)$original_cents);
    if ($has_d) {
        $inner = '<span class="pg-price-original">' . $orig_s . '</span>'
               . '<span class="pg-price-current">'  . $eff_s  . '</span>';
    } else {
        $inner = '<span class="pg-price">' . $eff_s . '</span>';
    }
    return $wrap ? '<span class="pg-price-block' . ($has_d ? ' has-discount' : '') . '">' . $inner . '</span>' : $inner;
}

/**
 * Render the catalog_item_view "cross-sell" section for a given product.
 *
 * Cross-sell = "ordered together" suggestions from the legacy
 * get_cross_sell_items() helper (analyzes order_items history). Output is
 * a horizontal-scroll row of compact product cards. Empty when no
 * suggestions can be computed (new sites with no order history, or
 * products that have never been ordered together with anything).
 *
 * The variant chooser JS (pg_civ_variants.js) re-fetches via the
 * `get_cross_sell_items` API endpoint when the visitor switches variants,
 * so the cross-sell row always reflects the currently-displayed product.
 *
 * @param int   $product_id           Source product (the product being viewed).
 * @param int   $catalog_detail_pid   Page id used to build per-card detail URLs.
 *                                    When 0, cards link to '#'.
 * @param int   $count                Max number of items (default 4).
 * @return string                     Wrapped <div> containing the row HTML.
 *                                    Always wrapped (even when empty) so the
 *                                    JS refresh has a stable target.
 */
/**
 * Expand `<!--pg-variant-attr:base64(cfg)-->` markers in $html by cloning
 * the configured template once per shared attribute in $vattrs.
 *
 * Designer drops ONE `variant_attr` content node where they want the
 * variant chooser to appear. At render time, this helper finds every
 * marker in the rendered HTML and REPLACES it with the actual selector
 * UI — one copy per attribute. So a product with both "Renk" + "Beden"
 * gets two select/radio/btn-group blocks, each pre-wired to the variant
 * chooser JS via `.pg-civ-variant-attr` + `data-pg-civ-attr="<id>"`.
 *
 * Render styles supported (cfg.renderStyle):
 *   • select        — native <select>
 *   • radio         — vertical radio list
 *   • checkbox      — horizontal pill row (single-pick)
 *   • button_group  — Bootstrap btn-group radio toggle
 *   • button_list   — vertical stack of full-width btn-outline rows
 *
 * cfg.labelClass / controlClass / itemClass let the designer tune the
 * Bootstrap classes on each piece without touching backend code.
 *
 * @param string $html       The rendered HTML containing markers.
 * @param array  $vattrs     [attr_id => ['label'=>..., 'options'=>[oid=>label, …]]]
 * @param array  $defaults   Selected option per attr — [attr_id => option_id]
 * @return string            $html with all markers expanded.
 */
function _civ_expand_variant_attr_markers($html, $vattrs, $defaults = array())
{
    if ($html === '' || empty($vattrs)) {
        // No attributes (or no markers) — strip any markers so they don't
        // leak as HTML comments to the visitor.
        return preg_replace('/<!--pg-variant-attr:[A-Za-z0-9+\/=]+-->/', '', $html);
    }
    if (!is_array($defaults)) $defaults = array();

    return preg_replace_callback(
        '/<!--pg-variant-attr:([A-Za-z0-9+\/=]+)-->/',
        function ($m) use ($vattrs, $defaults) {
            $cfg = json_decode(base64_decode($m[1]), true);
            if (!is_array($cfg)) $cfg = array();
            $style    = isset($cfg['renderStyle']) ? $cfg['renderStyle'] : 'select';
            // The checkbox style was removed — variant attributes are
            // single-select. Legacy records still carrying 'checkbox'
            // automatically fall back to 'radio'.
            if ($style === 'checkbox') $style = 'radio';
            if (!in_array($style, array('select','radio','button_group','button_list'), true)) $style = 'select';
            $lblCls   = isset($cfg['labelClass'])   ? (string)$cfg['labelClass']   : 'form-label fw-semibold';
            $ctlCls   = isset($cfg['controlClass']) ? (string)$cfg['controlClass'] : '';
            $itmCls   = isset($cfg['itemClass'])    ? (string)$cfg['itemClass']    : '';
            $rootCls  = isset($cfg['cssClass'])     ? (string)$cfg['cssClass']     : 'mb-3';

            $out = '';
            foreach ($vattrs as $aid => $adef) {
                $aid_int  = (int)$aid;
                $sel_oid  = isset($defaults[$aid]) ? (int)$defaults[$aid] : 0;
                $label    = isset($adef['label']) ? (string)$adef['label'] : '';
                $options  = isset($adef['options']) && is_array($adef['options']) ? $adef['options'] : array();
                $name_attr = 'pg_civ_attr_' . $aid_int;

                $out .= '<div class="pg-civ-variant-attr-wrap ' . h($rootCls) . '" data-pg-civ-attr-wrap="' . $aid_int . '">';
                $out .= '<label class="' . h($lblCls) . '">' . h($label) . '</label>';

                switch ($style) {
                    case 'radio':
                        $out .= '<div class="' . h($ctlCls) . '" data-pg-civ-attr-control="' . $aid_int . '">';
                        // Hidden mirror <select> so the existing JS hook
                        // (.pg-civ-variant-attr) keeps working unchanged
                        // — the visible radios update its value on change.
                        $out .= '<select class="pg-civ-variant-attr d-none" data-pg-civ-attr="' . $aid_int . '">';
                        $out .= '<option value="">' . h(lang('Select...')) . '</option>';
                        foreach ($options as $oid => $olbl) {
                            $sel = ((int)$oid === $sel_oid) ? ' selected' : '';
                            $out .= '<option value="' . (int)$oid . '"' . $sel . '>' . h((string)$olbl) . '</option>';
                        }
                        $out .= '</select>';
                        // Visible radios.
                        foreach ($options as $oid => $olbl) {
                            $rid = $name_attr . '_' . (int)$oid;
                            $chk = ((int)$oid === $sel_oid) ? ' checked' : '';
                            $out .= '<div class="form-check ' . h($itmCls) . '">'
                                  . '<input class="form-check-input pg-civ-variant-attr-radio" type="radio"'
                                  . ' name="' . h($name_attr) . '"'
                                  . ' id="' . h($rid) . '"'
                                  . ' value="' . (int)$oid . '"'
                                  . ' data-pg-civ-attr-mirror="' . $aid_int . '"'
                                  . $chk . '>'
                                  . '<label class="form-check-label" for="' . h($rid) . '">' . h((string)$olbl) . '</label>'
                                  . '</div>';
                        }
                        $out .= '</div>';
                        break;

                    case 'button_group':
                    case 'button_list':
                        $is_vertical = ($style === 'button_list');
                        $grp_cls = $is_vertical ? 'btn-group-vertical w-100' : 'btn-group';
                        $out .= '<select class="pg-civ-variant-attr d-none" data-pg-civ-attr="' . $aid_int . '">';
                        $out .= '<option value="">' . h(lang('Select...')) . '</option>';
                        foreach ($options as $oid => $olbl) {
                            $sel = ((int)$oid === $sel_oid) ? ' selected' : '';
                            $out .= '<option value="' . (int)$oid . '"' . $sel . '>' . h((string)$olbl) . '</option>';
                        }
                        $out .= '</select>';
                        $out .= '<div class="' . $grp_cls . ' ' . h($ctlCls) . '" role="group" data-pg-civ-attr-control="' . $aid_int . '">';
                        foreach ($options as $oid => $olbl) {
                            $rid    = $name_attr . '_' . (int)$oid;
                            $chk    = ((int)$oid === $sel_oid) ? ' checked' : '';
                            $active = ((int)$oid === $sel_oid) ? ' active' : '';
                            $out .= '<input type="radio" class="btn-check pg-civ-variant-attr-radio"'
                                  . ' name="' . h($name_attr) . '"'
                                  . ' id="' . h($rid) . '"'
                                  . ' value="' . (int)$oid . '"'
                                  . ' data-pg-civ-attr-mirror="' . $aid_int . '"'
                                  . ' autocomplete="off"' . $chk . '>'
                                  . '<label class="btn btn-outline-primary' . $active . ' ' . h($itmCls) . '" for="' . h($rid) . '">'
                                  . h((string)$olbl) . '</label>';
                        }
                        $out .= '</div>';
                        break;

                    // 'checkbox' case removed — variant attributes are
                    // single-select by definition (a "Renk" can't be both
                    // Mavi AND Kırmızı simultaneously). Legacy 'checkbox'
                    // records fall back to 'radio' above.


                    case 'select':
                    default:
                        $out .= '<select class="form-select pg-civ-variant-attr ' . h($ctlCls) . '" data-pg-civ-attr="' . $aid_int . '">';
                        $out .= '<option value="">' . h(lang('Select...')) . '</option>';
                        foreach ($options as $oid => $olbl) {
                            $sel = ((int)$oid === $sel_oid) ? ' selected' : '';
                            $out .= '<option value="' . (int)$oid . '"' . $sel . '>' . h((string)$olbl) . '</option>';
                        }
                        $out .= '</select>';
                        break;
                }

                $out .= '</div>';
            }
            return $out;
        },
        $html
    );
}

/**
 * Compute cross-sell item list for a product. Two-tier strategy:
 *   • Tier 1: order-history-based via legacy get_cross_sell_items()
 *   • Tier 2: same-product-group siblings (fallback for fresh sites OR
 *              for products with no order history yet)
 *
 * Returns a normalized array of items shaped like:
 *   [['id'=>…, 'name'=>…, 'short_description'=>…, 'image_name'=>…,
 *     'image_url'=>…, 'url'=>…, 'price'=>'199.95',
 *     'original_price'=>'199.95', 'has_discount'=>0|1], …]
 *
 * The same shape is consumed by _civ_render_cross_sell_html (server-side
 * card markup) AND the new `get_cross_sell_for_product` API action (JSON
 * for the variant-chooser JS refresh path), keeping output consistent.
 *
 * @param int $product_id        Source product (the product being viewed).
 * @param int $catalog_detail_pid Page id used for per-card detail URLs.
 * @param int $count             Max number of items.
 * @return array                 Normalized items list (possibly empty).
 */
function _civ_get_cross_sell_items($product_id, $catalog_detail_pid, $count = 4)
{
    $items = array();
    if ($product_id <= 0) return $items;

    if (!function_exists('get_cross_sell_items')) {
        $cs_path = PG_FUNCTIONS_DIR . '/get_cross_sell_items.php';
        if (file_exists($cs_path)) require_once($cs_path);
    }
    $base_path = defined('OUTPUT_PATH') ? OUTPUT_PATH : '/';

    // Resolve detail page URL prefix (used by both tiers when present).
    $url_prefix = '';
    if ($catalog_detail_pid > 0) {
        $dp_name = db_value(
            "SELECT page_name FROM page WHERE page_id = '" . (int)$catalog_detail_pid . "' LIMIT 1"
        );
        if ($dp_name) $url_prefix = $base_path . encode_url_path($dp_name) . '/';
    }

    // Discount lookup — applied per item. Single call per request.
    $disc_prices = function_exists('get_discounted_product_prices')
        ? get_discounted_product_prices() : array();
    if (!is_array($disc_prices)) $disc_prices = array();

    // ── Tier 1: order-history-based ─────────────────────────────────────
    if (function_exists('get_cross_sell_items')) {
        $request = array(
            'products'        => array(array('id' => $product_id)),
            'number_of_items' => $count,
            'days'            => 365,
            'discounted'      => true,
        );
        if ($catalog_detail_pid > 0) {
            $request['catalog_detail_page'] = array('id' => $catalog_detail_pid);
        }
        $resp = @get_cross_sell_items($request);
        if (is_array($resp) && !empty($resp['items']) && $resp['status'] === 'success') {
            foreach ($resp['items'] as $it) {
                $items[] = _civ_normalize_xsell_item($it, $url_prefix, $disc_prices);
            }
        }
    }

    // ── Tier 2: same-group siblings ─────────────────────────────────────
    if (empty($items)) {
        $rows = db_items(
            "SELECT DISTINCT
                 p.id, p.name, p.short_description, p.address_name,
                 p.image_name, p.price
             FROM products p
             INNER JOIN products_groups_xref pgx1 ON pgx1.product = p.id
             WHERE p.enabled = 1
               AND p.id != '" . (int)$product_id . "'
               AND pgx1.product_group IN (
                   SELECT product_group FROM products_groups_xref
                   WHERE product = '" . (int)$product_id . "'
               )
             ORDER BY p.id DESC
             LIMIT " . (int)$count
        );
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $items[] = _civ_normalize_xsell_item(array(
                    'id'                => (int)$row['id'],
                    'name'              => (string)$row['name'],
                    'short_description' => (string)$row['short_description'],
                    'address_name'      => (string)$row['address_name'],
                    'image_name'        => (string)$row['image_name'],
                    'price_cents'       => (int)$row['price'],  // raw cents from DB
                ), $url_prefix, $disc_prices);
            }
        }
    }
    return $items;
}

/**
 * Normalize a cross-sell item from either tier into a stable shape.
 * Resolves URL, image URL, and adds discount fields from $disc_prices.
 */
function _civ_normalize_xsell_item($it, $url_prefix, $disc_prices)
{
    $base_path = defined('OUTPUT_PATH') ? OUTPUT_PATH : '/';
    $currency  = defined('VISITOR_CURRENCY_SYMBOL') ? VISITOR_CURRENCY_SYMBOL : '$';
    $pid = isset($it['id']) ? (int)$it['id'] : 0;

    // Tier 1 returns 'price' as decimal string. Tier 2 passes 'price_cents'
    // (raw cents). Normalize so downstream gets `price_cents`.
    if (isset($it['price_cents'])) {
        $orig_cents = (int)$it['price_cents'];
    } elseif (isset($it['price']) && is_numeric($it['price'])) {
        $price_v = (float)$it['price'];
        // Heuristic: if value > 1000 assume already in cents (legacy
        // get_catalog_item path); otherwise treat as decimal currency.
        $orig_cents = ($price_v > 1000) ? (int)$price_v : (int)round($price_v * 100);
    } else {
        $orig_cents = 0;
    }
    $eff_cents = isset($disc_prices[$pid]) ? (int)$disc_prices[$pid] : $orig_cents;
    $has_d     = ($eff_cents < $orig_cents) ? 1 : 0;

    $image_name = isset($it['image_name']) ? (string)$it['image_name'] : '';
    $image_url  = $image_name !== '' ? $base_path . encode_url_path($image_name) : '';
    $address    = isset($it['address_name']) ? (string)$it['address_name'] : '';
    $url        = isset($it['url']) && $it['url'] !== '' && $it['url'] !== '#'
                    ? (string)$it['url']
                    : (($url_prefix !== '' && $address !== '') ? $url_prefix . encode_url_path($address) : '#');

    return array(
        'id'                       => $pid,
        'name'                     => isset($it['name']) ? (string)$it['name'] : '',
        'short_description'        => isset($it['short_description']) ? (string)$it['short_description'] : '',
        'image_name'               => $image_name,
        'image_url'                => $image_url,
        'url'                      => $url,
        'price'                    => number_format($eff_cents / 100, 2, '.', ''),
        'price_cents'              => $eff_cents,
        'price_formatted'          => $currency . number_format($eff_cents / 100, 2, '.', ','),
        'original_price'           => number_format($orig_cents / 100, 2, '.', ''),
        'original_price_cents'     => $orig_cents,
        'original_price_formatted' => $currency . number_format($orig_cents / 100, 2, '.', ','),
        'has_discount'             => (string)$has_d,
        'discount_amount_formatted'=> $has_d ? $currency . number_format(($orig_cents - $eff_cents) / 100, 2, '.', ',') : '',
        'discount_percent'         => ($has_d && $orig_cents > 0) ? (string)((int)round((($orig_cents - $eff_cents) / $orig_cents) * 100)) : '',
        'price_block_html'         => _pg_format_price_with_discount($orig_cents, $eff_cents, $currency),
    );
}

function _civ_render_cross_sell($product_id, $catalog_detail_pid, $opts = array())
{
    // Designer-tunable knobs (passed via $cfg from catalog_item_view widget):
    //   • count            — how many items to show (default 4, max 12)
    //   • columns          — Bootstrap col split (1 / 2 / 3 / 4 / 6)
    //   • heading          — heading text override (default "Birlikte sıkça
    //                         satın alınanlar" / "Benzer ürünler" depending
    //                         on which tier provided the items)
    //   • heading_tag      — h2 / h3 / h4 / h5 / h6 (default h3)
    //   • show_heading     — bool (default true)
    //   • show_price       — bool (default true)
    //   • card_class       — extra CSS classes appended to every <a class="card …">
    //                         so designer can tune card look without touching PHP
    //   • image_aspect     — aspect-ratio for card thumbnails (default '1/1')
    //   • root_class       — extra CSS classes on the wrapper div (so the
    //                         designer can target via custom CSS / utility
    //                         spacing classes)
    if (!is_array($opts)) $opts = array();
    $count = isset($opts['count']) ? max(1, min(12, (int)$opts['count'])) : 4;
    $columns = isset($opts['columns']) ? (int)$opts['columns'] : 4;
    if (!in_array($columns, array(1, 2, 3, 4, 6), true)) $columns = 4;
    $col_md = (int)(12 / $columns);
    $col_sm = $columns >= 4 ? 6 : 12;  // tighten to 2-up on phones for 4+/cols layouts
    $heading_tag    = isset($opts['heading_tag']) && in_array($opts['heading_tag'], array('h2','h3','h4','h5','h6'), true)
                        ? $opts['heading_tag'] : 'h3';
    $show_heading   = !isset($opts['show_heading']) || $opts['show_heading'];
    $show_price     = !isset($opts['show_price'])   || $opts['show_price'];
    $card_class     = isset($opts['card_class']) ? trim((string)$opts['card_class']) : '';
    $image_aspect   = isset($opts['image_aspect']) ? (string)$opts['image_aspect'] : '1/1';
    $root_class     = isset($opts['root_class']) ? trim((string)$opts['root_class']) : 'mt-4';
    $custom_heading = isset($opts['heading']) && (string)$opts['heading'] !== '' ? (string)$opts['heading'] : null;

    $wrapper_open  = '<div class="pg-civ-cross-sell ' . h($root_class) . '" data-pg-civ-cross-sell="1"'
                   . ' data-pg-civ-source-id="' . (int)$product_id . '"'
                   . ' data-pg-civ-detail-page="' . (int)$catalog_detail_pid . '"'
                   . ' data-pg-civ-count="' . (int)$count . '"'
                   . ' data-pg-civ-columns="' . (int)$columns . '"'
                   . ' data-pg-civ-card-class="' . h($card_class) . '"'
                   . ' data-pg-civ-image-aspect="' . h($image_aspect) . '"'
                   . ' data-pg-civ-show-price="' . ($show_price ? '1' : '0') . '"'
                   . '>';
    $wrapper_close = '</div>';
    if ($product_id <= 0) return $wrapper_open . $wrapper_close;

    // Pull items via the shared helper — same logic the API endpoint uses
    // so SSR + JS-refresh produce identical output.
    $items   = _civ_get_cross_sell_items($product_id, $catalog_detail_pid, $count);
    $heading = $custom_heading !== null
                ? $custom_heading
                : (lang('Frequently bought together'));

    if (empty($items)) {
        return $wrapper_open . $wrapper_close;
    }

    $cs_html = '';
    if ($show_heading) {
        $cs_html .= '<' . $heading_tag . ' class="h5 mb-3">' . h($heading) . '</' . $heading_tag . '>';
    }
    $cs_html .= '<div class="row g-3">';
    $card_extra = $card_class !== '' ? ' ' . h($card_class) : '';
    foreach ($items as $it) {
        $cs_name  = isset($it['name']) ? h($it['name']) : '';
        $cs_short = isset($it['short_description']) ? h($it['short_description']) : '';
        $cs_image = isset($it['image_url']) ? (string)$it['image_url'] : '';
        $cs_url   = isset($it['url']) ? (string)$it['url'] : '#';
        $cs_price_block = isset($it['price_block_html']) ? (string)$it['price_block_html'] : '';
        $cs_html .= '<div class="col-' . (int)$col_sm . ' col-md-' . (int)$col_md . '">'
                  . '<a href="' . h($cs_url) . '" class="card h-100 text-decoration-none text-body' . $card_extra . '">'
                  . ($cs_image !== ''
                        ? '<img src="' . h($cs_image) . '" class="card-img-top" alt="" loading="lazy" style="aspect-ratio:' . h($image_aspect) . ';object-fit:cover">'
                        : '<div class="card-img-top bg-body-tertiary" style="aspect-ratio:' . h($image_aspect) . '"></div>')
                  . '<div class="card-body p-2">'
                  . '<div class="small fw-semibold text-truncate">' . ($cs_short !== '' ? $cs_short : $cs_name) . '</div>'
                  . ($show_price ? '<div class="small mt-1">' . $cs_price_block . '</div>' : '')
                  . '</div>'
                  . '</a>'
                  . '</div>';
    }
    $cs_html .= '</div>';
    return $wrapper_open . $cs_html . $wrapper_close;
}

function _apply_catalog_item_view_bindings(&$node, $context)
{
    if (!is_array($node)) return;

    $bindings = (isset($node['props']['_bindings']) && is_array($node['props']['_bindings']))
                    ? $node['props']['_bindings'] : array();

    // (Messages content node formName auto-fill is handled centrally by
    // _pg_inject_messages_node — called by the widget render fn before
    // this binding pass, so messages are already scoped here.)

    // ── Section: breadcrumb (designer's <nav>/<div> wrapper) ──────────────
    // Designer drops a <nav> (or any container) with `_bindings.section =
    // 'breadcrumb'`. We replace the children with a custom_html node
    // carrying `^^__breadcrumb_inner_html^^` — designer's wrapper tag,
    // classes, and attrs survive; the inner <ol>…<li>…</li>…</ol> comes
    // from backend (Ana Katalog → Group → Subgroup → Product). When the
    // product can't be resolved (not found), the token is empty so the
    // wrapper renders as an empty element.
    if (isset($bindings['section']) && $bindings['section'] === 'breadcrumb') {
        $node['children'] = array(
            array(
                'type'     => 'content',
                'props'    => array('contentType' => 'custom_html', 'html' => '^^__breadcrumb_inner_html^^'),
                'children' => array(),
            ),
        );
        return;  // wrapper preserved; no further recursion into the now-replaced inner.
    }

    // ── Section: recipient_picker container marker ────────────────────────
    // The recipient_picker section binding is just a hide-when-empty marker
    // for the container that wraps the Alıcı select + add_name input. We
    // ALSO stamp `data-pg-civ-section="recipient_picker"` on the element
    // so the variant-picker auto-injection (in catalog_item_view render)
    // can locate it via string-search and insert the variant picker JUST
    // BEFORE the recipient block — landing in the visually-correct spot
    // (Renk → Alıcı → Quantity + Sepete Ekle).
    if (isset($bindings['section']) && $bindings['section'] === 'recipient_picker') {
        if (!isset($node['props']['_attrs']) || !is_array($node['props']['_attrs'])) {
            $node['props']['_attrs'] = array();
        }
        // Drop any previous marker so re-renders don't accumulate duplicates.
        $node['props']['_attrs'] = array_values(array_filter($node['props']['_attrs'], function ($a) {
            return !(is_array($a) && isset($a['name']) && $a['name'] === 'data-pg-civ-section');
        }));
        $node['props']['_attrs'][] = array('name' => 'data-pg-civ-section', 'value' => 'recipient_picker');
    }

    // ── Button: Add-to-Cart action ───────────────────────────────────────────
    if (isset($node['type']) && $node['type'] === 'component'
        && isset($node['props']['componentType']) && $node['props']['componentType'] === 'btn'
        && isset($bindings['action']) && $bindings['action'] === 'add_to_cart') {
        $node['props']['btnElement'] = 'button';
        $node['props']['btnType']    = 'submit';
        // href becomes meaningless on a real <button>; clear so the renderer
        // doesn't emit a stray attribute.
        $node['props']['href']       = '';
        // Stamp `data-pg-civ-action="add_to_cart"` on the button so the
        // variant-picker auto-inject (and any future enhancement targeting
        // the cart button) can locate it via string-search. Acts as a
        // fallback anchor when no recipient_picker section exists in the
        // tree (e.g. digital products like e-gift cards that don't ship).
        if (!isset($node['props']['_attrs']) || !is_array($node['props']['_attrs'])) {
            $node['props']['_attrs'] = array();
        }
        $node['props']['_attrs'] = array_values(array_filter($node['props']['_attrs'], function ($a) {
            return !(is_array($a) && isset($a['name']) && $a['name'] === 'data-pg-civ-action');
        }));
        $node['props']['_attrs'][] = array('name' => 'data-pg-civ-action', 'value' => 'add_to_cart');
    }

    // ── Input: recipient_name (free-form new recipient) ─────────────────────
    // The new-recipient input is HIDDEN by default — only revealed when the
    // user picks "+ Yeni alıcı ekle" from the recipient_select dropdown. The
    // companion JS (emitted with the select) toggles `display` based on the
    // sibling select's value. Marker `data-pg-recipient-input` is the hookup
    // anchor; `style="display:none"` is the initial state so SSR matches the
    // first JS pass without flicker.
    if (isset($node['type']) && $node['type'] === 'semantic'
        && isset($node['props']['tag']) && $node['props']['tag'] === 'input'
        && isset($bindings['value']) && $bindings['value'] === 'recipient_name') {
        $existing = (isset($node['props']['_attrs']) && is_array($node['props']['_attrs']))
                        ? $node['props']['_attrs'] : array();
        $managed  = array('type' => 1, 'name' => 1, 'data-pg-recipient-input' => 1, 'style' => 1);
        $kept     = array();
        // Preserve any pre-existing inline style the designer set; merge our
        // display:none in front so the runtime JS can toggle it cleanly via
        // `el.style.display = ''`.
        $existing_style = '';
        foreach ($existing as $a) {
            if (is_array($a) && isset($a['name'])) {
                if ($a['name'] === 'style') {
                    $existing_style = isset($a['value']) ? (string)$a['value'] : '';
                } elseif (!isset($managed[$a['name']])) {
                    $kept[] = $a;
                }
            }
        }
        $kept[] = array('name' => 'type', 'value' => 'text');
        $kept[] = array('name' => 'name', 'value' => 'add_name');
        $kept[] = array('name' => 'data-pg-recipient-input', 'value' => '1');
        $merged_style = trim('display:none;' . ($existing_style !== '' ? ' ' . $existing_style : ''));
        $kept[] = array('name' => 'style', 'value' => $merged_style);
        $node['props']['_attrs'] = $kept;
    }

    // ── Carousel: auto-fill slides from product gallery ────────────────────
    // catalog_item_view renders a single product, so the carousel inside
    // pulls images from `products.image_name` (main) + `products_images_xref`
    // (gallery). Pre-process collected the URLs into context['gallery']; this
    // walker just stamps them onto every carousel node it finds.
    if (isset($node['type']) && $node['type'] === 'content'
        && isset($node['props']['contentType']) && $node['props']['contentType'] === 'carousel'
        && isset($context['gallery']) && is_array($context['gallery'])) {
        $node['props']['slides'] = $context['gallery'];
    }

    // ── Semantic <select>: recipient_select (existing recipients dropdown) ──
    // Replace the node IN PLACE with a custom_html content node carrying the
    // full select markup. UX:
    //   • Placeholder option ("Alıcı seçin...") sits at the top of the
    //     dropdown but is `disabled` so the visitor can't fall back to it
    //     after picking. It is NOT selected — the first real option
    //     (typically "kendim") is the default. So the closed select shows
    //     "kendim", and the dropdown opens with the prompt visible at top.
    //   • Sentinel "- add name below -" relabeled to "+ Yeni alıcı ekle"
    //     for clarity. The VALUE stays "- add name below -" so
    //     catalog_detail.php's existing branch keeps recognizing it.
    //   • Companion JS toggles the linked recipient_name input visibility
    //     based on the chosen option (hidden by default; appears when "+ Yeni
    //     alıcı ekle" is picked, and auto-focused for fast typing).
    // Avoids having to teach _render_tree_node about <option> children with
    // dynamic values.
    if (isset($node['type']) && $node['type'] === 'semantic'
        && isset($node['props']['tag']) && $node['props']['tag'] === 'select'
        && isset($bindings['value']) && $bindings['value'] === 'recipient_select') {
        $cls      = isset($node['props']['cssClass']) ? (string)$node['props']['cssClass'] : '';
        $opts     = (isset($context['recipient_options']) && is_array($context['recipient_options']))
                        ? $context['recipient_options'] : array();
        // Sentinel value the catalog_detail.php redirect logic checks against.
        $add_sentinel = lang('- add name below -');
        // Disabled placeholder at top — visible only when the dropdown is
        // open. Not `selected`, so the FIRST REAL option below it is the
        // active default value when the page first paints.
        $optsHtml = '<option value="" disabled>'
                  . h(lang('Select recipient...')) . '</option>';
        $is_first_real = true;
        foreach ($opts as $val => $label) {
            $val_s   = (string)$val;
            $label_s = (string)$label;
            if ($val_s === '' && $label_s === '') continue; // skip legacy blank
            if ($val_s === $add_sentinel) {
                $optsHtml .= '<option value="' . h($val_s) . '" data-pg-recipient-add="1">'
                           . h(lang('+ Add new recipient')) . '</option>';
            } else {
                // `selected` on the first real entry so the dropdown opens
                // with a sane default rather than the placeholder text.
                $sel = $is_first_real ? ' selected' : '';
                $optsHtml .= '<option value="' . h($val_s) . '"' . $sel . '>' . h($label_s) . '</option>';
                $is_first_real = false;
            }
        }
        // `required` left off — the placeholder is disabled, so the field
        // always has a non-empty value (the first real option). `required`
        // would only ever block the visitor needlessly.
        $selectHtml = '<select name="ship_to" data-pg-recipient-select="1"'
                    . ($cls !== '' ? ' class="' . h($cls) . '"' : '')
                    . '>' . $optsHtml . '</select>';
        // Companion JS (idempotent — safe to emit per render). Wires every
        // [data-pg-recipient-select] in the page to the nearest
        // [data-pg-recipient-input] inside the same form. Toggles `display`
        // on change. Auto-focuses the input when "+ Yeni alıcı ekle" picked.
        // The script uses a global init flag so multiple recipient widgets on
        // one page only register the listener once.
        $selectHtml .= '<script>(function(){'
                     . 'if(window.__pgRecipBind)return;window.__pgRecipBind=1;'
                     . 'document.addEventListener("change",function(e){'
                     .   'var t=e.target;'
                     .   'if(!t||t.getAttribute("data-pg-recipient-select")!=="1")return;'
                     .   'var f=t.form||t.closest("form");if(!f)return;'
                     .   'var inp=f.querySelector("[data-pg-recipient-input]");if(!inp)return;'
                     .   'var opt=t.options[t.selectedIndex];'
                     .   'var addPicked=opt&&opt.getAttribute("data-pg-recipient-add")==="1";'
                     .   'inp.style.display=addPicked?"":"none";'
                     .   'if(addPicked){try{inp.focus();}catch(_){}}else{inp.value="";}'
                     . '});'
                     . '})();</script>';
        // Mutate the node into a custom_html content. _render_tree_node emits
        // the html prop verbatim for this contentType, so the original CSS
        // class etc. still apply.
        $node['type']     = 'content';
        $node['props']    = array('contentType' => 'custom_html', 'html' => $selectHtml);
        $node['children'] = array();
        // No recursion below — node is now a leaf.
        return;
    }

    // ── Input: quantity binding ──────────────────────────────────────────────
    if (isset($node['type']) && $node['type'] === 'semantic'
        && isset($node['props']['tag']) && $node['props']['tag'] === 'input'
        && isset($bindings['value']) && $bindings['value'] === 'quantity') {
        $existing = (isset($node['props']['_attrs']) && is_array($node['props']['_attrs']))
                        ? $node['props']['_attrs'] : array();
        $managed  = array('type' => 1, 'name' => 1, 'min' => 1, 'max' => 1, 'value' => 1);
        $kept     = array();
        foreach ($existing as $a) {
            if (is_array($a) && isset($a['name']) && !isset($managed[$a['name']])) {
                $kept[] = $a;
            }
        }
        $kept[] = array('name' => 'type',  'value' => 'number');
        $kept[] = array('name' => 'name',  'value' => 'quantity');
        $min_qty = isset($context['min_quantity']) ? (int)$context['min_quantity'] : 1;
        if ($min_qty < 1) $min_qty = 1;
        $kept[] = array('name' => 'min',   'value' => (string)$min_qty);
        $default_qty = isset($context['default_quantity']) ? (int)$context['default_quantity'] : $min_qty;
        if ($default_qty < $min_qty) $default_qty = $min_qty;
        $kept[] = array('name' => 'value', 'value' => (string)$default_qty);
        $max_qty = isset($context['max_quantity']) ? (int)$context['max_quantity'] : 0;
        if ($max_qty > 0) {
            // Sanity: max must be >= min, otherwise the browser will reject input.
            if ($max_qty < $min_qty) $max_qty = $min_qty;
            $kept[] = array('name' => 'max', 'value' => (string)$max_qty);
        }
        $node['props']['_attrs'] = $kept;
    }

    if (!empty($node['children']) && is_array($node['children'])) {
        // Filter children: drop any node tagged as context-skipped (e.g. a
        // recipient picker on a single-recipient site). Done BEFORE recursion
        // so a skipped subtree's bindings never run.
        $kept = array();
        foreach ($node['children'] as &$child) {
            if (is_array($child) && _civ_should_skip_node($child, $context)) continue;
            _apply_catalog_item_view_bindings($child, $context);
            $kept[] = $child;
        }
        unset($child);
        $node['children'] = $kept;
    }
}

// ============================================================================
// Render a catalog_item_view system widget — single product detail.
//
// Companion to _render_system_widget_catalog_listing: the listing's
// ^^__detail_url^^ token links here (path-style: /page_name/product_slug).
// This renderer resolves the product from the current URL and applies tokens
// to the loop_area template ONCE (no iteration).
//
// URL resolution (in priority order):
//   a. Path-style: $_GET['page'] = 'page_name/product_address_name'
//      → product_address_name is extracted from path after first '/'.
//      Matches how catalog_listing builds its ^^__detail_url^^ links.
//   b. Query param: ?pid=N → direct product ID lookup (fallback / testing).
//
// Tokens (loop_area template + static portion):
//   All tokens from _render_system_widget_catalog_listing, PLUS:
//   ^^__not_found^^        — configured message when product not found; '' when found
//   ^^__gallery_image_1^^  — first extra gallery image URL (products_images_xref row 1)
//   ^^__gallery_image_2^^  — …row 2
//   ^^__gallery_image_3^^, ^^__gallery_image_4^^, ^^__gallery_image_5^^
//
// Config (system_region_config) keys:
//   product_group_id   int     0 = no group filter; >0 = restrict to this group (security)
//   not_found_message  string  shown via ^^__not_found^^ when product missing
//
function _render_system_widget_catalog_item_view($product_group_id, $tree_json, $widget_id, $cfg = array())
{
    $product_group_id = (int)$product_group_id;  // 0 = no group filter
    $widget_id        = (int)$widget_id;
    if ($tree_json === '' || $tree_json === null) return '';
    if (!is_array($cfg)) $cfg = array();

    // ── Config resolution ─────────────────────────────────────────────────
    $not_found_message = (isset($cfg['not_found_message']) && is_string($cfg['not_found_message']) && $cfg['not_found_message'] !== '')
                            ? $cfg['not_found_message'] : (string)lang('No products found.');
    // next_page_id: where to redirect after a successful add-to-cart submit.
    // 0 / empty → stay on the current product page (catalog_detail.php uses
    // the `current_url` hidden field as the redirect target). Legacy alias
    // `cart_page_id` accepted for forward-compat with any saved configs that
    // used the older name.
    $next_page_id = 0;
    if (isset($cfg['next_page_id']) && (int)$cfg['next_page_id'] > 0) {
        $next_page_id = (int)$cfg['next_page_id'];
    } elseif (isset($cfg['cart_page_id']) && (int)$cfg['cart_page_id'] > 0) {
        $next_page_id = (int)$cfg['cart_page_id'];
    }

    // ── Pre-process: action/value binding pass ────────────────────────────
    // Bindings on btn / input nodes need product context (default_quantity,
    // inventory + backorder for max). The full product fetch happens further
    // down with the complete column set; here we run a lightweight lookup so
    // the binding pass can mutate `tree_json` BEFORE the main decode + split
    // sees it. Mutating the JSON is cheaper than refactoring the entire
    // render pipeline to push product lookup above tree split.
    $_civ_pre_addr = '';
    $_civ_pre_pid  = 0;
    $_civ_page_full = isset($_GET['page']) ? (string)$_GET['page'] : '';
    if ($_civ_page_full !== '') {
        $_civ_slash = mb_strpos($_civ_page_full, '/', 0, 'UTF-8');
        if ($_civ_slash !== false) {
            $_civ_seg = mb_substr($_civ_page_full, $_civ_slash + 1, null, 'UTF-8');
            $_civ_next = mb_strpos($_civ_seg, '/', 0, 'UTF-8');
            if ($_civ_next !== false) $_civ_seg = mb_substr($_civ_seg, 0, $_civ_next, 'UTF-8');
            $_civ_seg = urldecode(trim($_civ_seg));
            if ($_civ_seg !== '' && strlen($_civ_seg) <= 200) $_civ_pre_addr = $_civ_seg;
        }
    }
    if ($_civ_pre_addr === '' && isset($_GET['pid']) && (int)$_GET['pid'] > 0) {
        $_civ_pre_pid = (int)$_GET['pid'];
    }
    $_civ_pre_p = null;
    if ($_civ_pre_addr !== '') {
        $_civ_pre_p = db_item(
            "SELECT id, inventory, inventory_quantity, backorder, default_quantity, shippable
             FROM products WHERE enabled = 1 AND address_name = '" . e($_civ_pre_addr) . "' LIMIT 1"
        );
        // Select-group fallback — same logic as the main fetch below, but
        // with the lightweight column set the bindings context needs.
        if (!$_civ_pre_p) {
            $_civ_grp_id = (int)db_value(
                "SELECT id FROM product_groups
                 WHERE address_name = '" . e($_civ_pre_addr) . "'
                   AND enabled = 1
                   AND display_type = 'select'
                 LIMIT 1"
            );
            if ($_civ_grp_id > 0) {
                $_civ_pre_p = db_item(
                    "SELECT products.id, products.inventory, products.inventory_quantity,
                            products.backorder, products.default_quantity, products.shippable
                     FROM products
                     INNER JOIN products_groups_xref pgx ON pgx.product = products.id
                     WHERE products.enabled = 1
                       AND pgx.product_group = '$_civ_grp_id'
                     ORDER BY pgx.sort_order ASC, products.id ASC
                     LIMIT 1"
                );
            }
        }
    } elseif ($_civ_pre_pid > 0) {
        $_civ_pre_p = db_item(
            "SELECT id, inventory, inventory_quantity, backorder, default_quantity, shippable
             FROM products WHERE enabled = 1 AND id = '" . (int)$_civ_pre_pid . "' LIMIT 1"
        );
    }
    $_civ_pre_tree = json_decode($tree_json, true);
    if (is_array($_civ_pre_tree)) {
        // Quantity context — config overrides win, product fallbacks otherwise.
        //   min (default 1)    : cfg.quantity_min  > 0
        //   max (default auto) : cfg.quantity_max  > 0  → use it
        //                        else inventory=1 && backorder=0 → inventory_quantity
        //                        else 0 (no max — unlimited)
        //   default (start val): cfg.quantity_default > 0
        //                        else products.default_quantity > 0
        //                        else 1
        $_civ_tracked = ($_civ_pre_p && !empty($_civ_pre_p['inventory']));
        $_civ_backord = ($_civ_pre_p && !empty($_civ_pre_p['backorder']));
        $_civ_min_cfg = isset($cfg['quantity_min'])     ? (int)$cfg['quantity_min']     : 0;
        $_civ_max_cfg = isset($cfg['quantity_max'])     ? (int)$cfg['quantity_max']     : 0;
        $_civ_def_cfg = isset($cfg['quantity_default']) ? (int)$cfg['quantity_default'] : 0;
        $_civ_min     = $_civ_min_cfg > 0 ? $_civ_min_cfg : 1;
        if ($_civ_max_cfg > 0) {
            $_civ_max = $_civ_max_cfg;
        } elseif ($_civ_tracked && !$_civ_backord && $_civ_pre_p) {
            $_civ_max = (int)$_civ_pre_p['inventory_quantity'];
        } else {
            $_civ_max = 0;
        }
        if ($_civ_def_cfg > 0) {
            $_civ_default = $_civ_def_cfg;
        } elseif ($_civ_pre_p && (int)$_civ_pre_p['default_quantity'] > 0) {
            $_civ_default = (int)$_civ_pre_p['default_quantity'];
        } else {
            $_civ_default = 1;
        }
        // Recipient context — site needs multi-recipient mode AND product
        // must be shippable for the recipient picker bindings to render.
        // Otherwise _civ_should_skip_node strips them from the tree.
        $_civ_multi_recip = (defined('ECOMMERCE_SHIPPING') && ECOMMERCE_SHIPPING == true
                             && defined('ECOMMERCE_RECIPIENT_MODE') && ECOMMERCE_RECIPIENT_MODE == 'multi-recipient'
                             && $_civ_pre_p && !empty($_civ_pre_p['shippable']));
        $_civ_recip_opts = ($_civ_multi_recip && function_exists('get_recipient_options'))
                                ? get_recipient_options() : array();
        // Carousel gallery — main image + xref images. The path prefix matches
        // legacy templates (OUTPUT_PATH for visitor-facing URLs). Empty file
        // names are filtered out so the carousel doesn't render broken slides.
        $_civ_gallery   = array();
        if ($_civ_pre_p) {
            $_civ_pid       = (int)$_civ_pre_p['id'];
            $_civ_path      = defined('OUTPUT_PATH') ? OUTPUT_PATH : '/';
            $_civ_main_img  = db_value("SELECT image_name FROM products WHERE id = '$_civ_pid' LIMIT 1");
            if ($_civ_main_img) {
                $_civ_gallery[] = $_civ_path . encode_url_path($_civ_main_img);
            }
            $_civ_extra_imgs = db_items(
                "SELECT file_name FROM products_images_xref
                 WHERE product = '$_civ_pid' AND file_name != ''"
            );
            if (is_array($_civ_extra_imgs)) {
                foreach ($_civ_extra_imgs as $_civ_row) {
                    if (!empty($_civ_row['file_name'])) {
                        $_civ_gallery[] = $_civ_path . encode_url_path($_civ_row['file_name']);
                    }
                }
            }
        }
        // Per-widget messages — inject/scope BEFORE binding pass so any
        // existing messages node gets formName='catalog_detail' and legacy
        // trees missing one get an auto-prepended fallback.
        _pg_inject_messages_node($_civ_pre_tree, 'catalog_detail');

        _apply_catalog_item_view_bindings($_civ_pre_tree, array(
            'min_quantity'           => $_civ_min,
            'default_quantity'       => $_civ_default,
            'max_quantity'           => $_civ_max,
            'multi_recipient_active' => $_civ_multi_recip,
            'recipient_options'      => $_civ_recip_opts,
            'gallery'                => $_civ_gallery,
        ));
        $_civ_remuxed = json_encode($_civ_pre_tree, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($_civ_remuxed !== false) $tree_json = $_civ_remuxed;
    }

    // Decode + split the widget tree into static + loop-template halves.
    // The loop_area renders ONCE (single product). Matches form_item_view pattern.
    $tree_decoded = json_decode($tree_json, true);
    if (!is_array($tree_decoded)) return '';
    // Per-widget messages safety net — auto-prepends a Messages content
    // node if the designer hasn't placed one. formName left empty so
    // every pending session message is shown until per-widget liveform
    // names are wired up individually.
    _pg_inject_messages_node($tree_decoded, '');
    $split = _split_widget_tree($tree_decoded);

    if ($split['loop_children'] === null) {
        // No loop_area → render the whole tree as the per-product template.
        $loop_template = trim(_render_tree_node($tree_decoded, 0, 0));
        $static_html   = '';
    } else {
        $loop_template = '';
        foreach ($split['loop_children'] as $child) {
            $loop_template .= _render_tree_node($child, 0, 0);
        }
        $loop_template = trim($loop_template);
        $static_html   = trim(_render_tree_node($split['static_tree'], 0, 0));
    }

    // ── Resolve the product from URL ──────────────────────────────────────
    // Primary: path-style /page_name/product_address_name
    //   When catalog_listing links to a detail page via detail_url_prefix, the
    //   full URL is /<page_name>/<product_address_name>. The router resolves the
    //   page by the leading segment and stores the full path in $_GET['page'],
    //   so we strip the page segment (everything before the first '/') and
    //   URL-decode the remainder to get the product address_name.
    // Fallback: ?pid=N for direct integer product ID access (testing / deep links).
    $product_address_name = '';
    $product_id_direct    = 0;

    $page_slug_full = isset($_GET['page']) ? (string)$_GET['page'] : '';
    if ($page_slug_full !== '') {
        $slash_pos = mb_strpos($page_slug_full, '/', 0, 'UTF-8');
        if ($slash_pos !== false) {
            $segment = mb_substr($page_slug_full, $slash_pos + 1, null, 'UTF-8');
            // Strip any further path segments — keep only the first after the page name.
            $next_slash = mb_strpos($segment, '/', 0, 'UTF-8');
            if ($next_slash !== false) $segment = mb_substr($segment, 0, $next_slash, 'UTF-8');
            $segment = urldecode(trim($segment));
            if ($segment !== '' && strlen($segment) <= 200) {
                $product_address_name = $segment;
            }
        }
    }
    if ($product_address_name === '' && isset($_GET['pid']) && (int)$_GET['pid'] > 0) {
        $product_id_direct = (int)$_GET['pid'];
    }

    // ── Fetch the product row ─────────────────────────────────────────────
    // Group filter is applied as a subquery (no additional SELECT columns needed).
    $group_filter = '';
    if ($product_group_id > 0) {
        $group_filter = "AND products.id IN (
                            SELECT product FROM products_groups_xref
                            WHERE product_group = '" . (int)$product_group_id . "'
                         )";
    }

    // Track whether the URL slug actually matched a product_group (display_
    // type=select). When TRUE, $p points to the FIRST product in the group
    // (the visitor's "default variant") so the page renders something
    // sensible while the variant chooser UI is being built. The token map
    // also flips $is_select_group so designer templates can branch on it
    // (e.g. show attribute pickers only when the route is a group).
    $is_select_group         = false;
    $select_group_id         = 0;
    $select_group_name       = '';
    $select_group_short_desc = '';

    $p = null;
    if ($product_address_name !== '') {
        $p = db_item(
            "SELECT
                 products.id, products.name, products.address_name,
                 products.short_description, products.full_description,
                 products.details,
                 products.price, products.image_name, products.timestamp,
                 products.inventory, products.inventory_quantity, products.backorder,
                 products.out_of_stock_message,
                 products.mpn, products.gtin, products.brand, products.weight,
                 products.shippable, products.taxable, products.reward_points,
                 products.seo_score,
                 products.custom_field_1, products.custom_field_2,
                 products.custom_field_3, products.custom_field_4
             FROM products
             WHERE products.enabled = 1
               AND products.address_name = '" . e($product_address_name) . "'
               $group_filter
             LIMIT 1"
        );
        // ── Branch A: URL slug matched a PRODUCT directly ────────────────
        // If that product is a member of a display_type='select' group,
        // we ALSO activate the variant chooser — pre-selecting the URL's
        // product as the default. This lets the visitor land on a deep
        // product link (e.g. /urun/Mocha-Office-Chair) AND still get the
        // attribute selectors for switching to siblings (Yeşil / Turuncu)
        // without losing context.
        if ($p) {
            $_pg_owning_group = db_item(
                "SELECT pg.id, pg.name, pg.short_description
                 FROM product_groups pg
                 INNER JOIN products_groups_xref pgx ON pgx.product_group = pg.id
                 WHERE pgx.product = '" . (int)$p['id'] . "'
                   AND pg.enabled = 1
                   AND pg.display_type = 'select'
                 LIMIT 1"
            );
            if (is_array($_pg_owning_group)) {
                $is_select_group         = true;
                $select_group_id         = (int)$_pg_owning_group['id'];
                $select_group_name       = (string)$_pg_owning_group['name'];
                $select_group_short_desc = (string)$_pg_owning_group['short_description'];
            }
        }
        // ── Branch B: URL slug matched a SELECT GROUP ────────────────────
        // No product hit — try resolving the slug as a select-type
        // product_group. The visitor is on /<detail-page>/<group-slug>
        // because catalog_listing's group card linked here. We pull the
        // group's first enabled product as the default representative
        // (sorted by xref.sort_order — designer-controlled "starts on"
        // variant) so price/image/etc. tokens have real values to render.
        if (!$p) {
            $_pg_grp_row = db_item(
                "SELECT id, name, short_description
                 FROM product_groups
                 WHERE address_name = '" . e($product_address_name) . "'
                   AND enabled = 1
                   AND display_type = 'select'
                 LIMIT 1"
            );
            if (is_array($_pg_grp_row)) {
                $is_select_group         = true;
                $select_group_id         = (int)$_pg_grp_row['id'];
                $select_group_name       = (string)$_pg_grp_row['name'];
                $select_group_short_desc = (string)$_pg_grp_row['short_description'];
                // Default variant = first enabled product in this group
                // ordered by xref.sort_order. Designer can change which
                // variant lands as default by editing sort_order in the
                // group's product list.
                $p = db_item(
                    "SELECT
                         products.id, products.name, products.address_name,
                         products.short_description, products.full_description,
                         products.details,
                         products.price, products.image_name, products.timestamp,
                         products.inventory, products.inventory_quantity, products.backorder,
                         products.out_of_stock_message,
                         products.mpn, products.gtin, products.brand, products.weight,
                         products.shippable, products.taxable, products.reward_points,
                         products.seo_score,
                         products.custom_field_1, products.custom_field_2,
                         products.custom_field_3, products.custom_field_4
                     FROM products
                     INNER JOIN products_groups_xref pgx ON pgx.product = products.id
                     WHERE products.enabled = 1
                       AND pgx.product_group = '" . $select_group_id . "'
                     ORDER BY pgx.sort_order ASC, products.id ASC
                     LIMIT 1"
                );
            }
        }
    } elseif ($product_id_direct > 0) {
        $p = db_item(
            "SELECT
                 products.id, products.name, products.address_name,
                 products.short_description, products.full_description,
                 products.details,
                 products.price, products.image_name, products.timestamp,
                 products.inventory, products.inventory_quantity, products.backorder,
                 products.out_of_stock_message,
                 products.mpn, products.gtin, products.brand, products.weight,
                 products.shippable, products.taxable, products.reward_points,
                 products.seo_score,
                 products.custom_field_1, products.custom_field_2,
                 products.custom_field_3, products.custom_field_4
             FROM products
             WHERE products.enabled = 1
               AND products.id = '" . (int)$product_id_direct . "'
               $group_filter
             LIMIT 1"
        );
    }

    // Tell visitor tracking which product this system widget resolved. The
    // three branches above all land here, so a slug hit, a select-group
    // default variant and a direct ?pid= are all recorded the same way.
    // Pages built with the visual designer are page_type 'standard', so the
    // legacy catalog detail hook never fires for them.
    if (is_array($p) && !empty($p['id'])) {
        pg_track_content('product', $p['id']);
    }

    $base_path       = defined('OUTPUT_PATH') ? OUTPUT_PATH : '/';
    $currency_symbol = defined('VISITOR_CURRENCY_SYMBOL') ? VISITOR_CURRENCY_SYMBOL : '$';

    // ── Variant chooser (select-type product groups) ─────────────────────
    // When the URL slug pointed at a display_type='select' product_group,
    // build:
    //   • $variant_picker_html — one <select> per shared attribute (e.g.
    //     "Renk: [Mocha/Yeşil/Turuncu]"). Backend stamps onchange handlers
    //     that the JS init wires to the variant-switching logic.
    //   • $variant_data — JS-side payload listing every product in the group
    //     with its attribute combination + a minimal field map so the JS
    //     can find "the product matching the chosen attribute combination"
    //     and update bound elements without a server round-trip.
    //
    // Strategy: data is preloaded (no AJAX needed for basic fields), but
    // when the visitor picks a complete combination, the JS ALSO calls
    // /api.php?action=get_product to refresh stock + computed fields like
    // price_formatted. This balances first-paint speed with stock accuracy.
    $variant_picker_html = '';
    $variant_data        = array();
    $variant_attr_count  = 0;
    // Pre-load offer-driven discounted prices once. Returns
    // [product_id => discounted_price_in_cents] for every product that
    // currently qualifies for an order-scope offer. We use this to compute
    // both the SSR price tokens (so the first paint shows the discount)
    // AND the per-variant payload (so JS can show the right strike-through
    // when the visitor switches variants).
    $_civ_disc_prices = function_exists('get_discounted_product_prices')
        ? get_discounted_product_prices() : array();
    if (!is_array($_civ_disc_prices)) $_civ_disc_prices = array();
    if ($is_select_group && $select_group_id > 0) {
        // Pull every enabled product in the group along with their
        // attribute → option assignments. One JOIN-rich query keeps it to
        // a single round-trip.
        $_civ_var_rows = db_items(
            "SELECT
                 products.id            AS product_id,
                 products.name          AS product_name,
                 products.address_name  AS product_address,
                 products.short_description AS product_short_desc,
                 products.full_description  AS product_full_desc,
                 products.details       AS product_details,
                 products.price         AS product_price_cents,
                 products.image_name    AS product_image,
                 products.inventory     AS product_inv,
                 products.inventory_quantity AS product_inv_qty,
                 products.backorder     AS product_backorder,
                 products.out_of_stock_message AS product_oos_msg,
                 products.mpn           AS product_mpn,
                 products.gtin          AS product_gtin,
                 products.brand         AS product_brand,
                 products.weight        AS product_weight,
                 products.shippable     AS product_shippable,
                 products.custom_field_1 AS product_cf1,
                 products.custom_field_2 AS product_cf2,
                 products.custom_field_3 AS product_cf3,
                 products.custom_field_4 AS product_cf4,
                 pa.id          AS attr_id,
                 pa.name        AS attr_name,
                 pa.label       AS attr_label,
                 pao.id         AS option_id,
                 pao.label      AS option_label,
                 pao.sort_order AS option_sort
             FROM products
             INNER JOIN products_groups_xref pgx ON pgx.product = products.id
             LEFT JOIN products_attributes_xref pax ON pax.product_id = products.id
             LEFT JOIN product_attributes        pa  ON pa.id  = pax.attribute_id
             LEFT JOIN product_attribute_options pao ON pao.id = pax.option_id
             WHERE products.enabled = 1
               AND pgx.product_group = '" . (int)$select_group_id . "'
             ORDER BY pgx.sort_order ASC, products.id ASC, pa.id ASC, pao.sort_order ASC"
        );

        // Re-key into:
        //   $vproducts[pid] = ['name'=>…, 'fields'=>[…], 'attrs'=>[attr_id=>option_id]]
        //   $vattrs[attr_id] = ['label'=>…, 'options'=>[option_id=>label, …]]
        $vproducts = array();
        $vattrs    = array();
        if (is_array($_civ_var_rows)) {
            foreach ($_civ_var_rows as $_vr) {
                $_pid = (int)$_vr['product_id'];
                if (!isset($vproducts[$_pid])) {
                    $_p_orig_cents  = (int)$_vr['product_price_cents'];
                    // Apply offer-driven discount when this product has an
                    // active order-scope offer. $_civ_disc_prices is keyed
                    // by product id; presence means a discount applies.
                    $_p_disc_cents  = isset($_civ_disc_prices[$_pid]) ? (int)$_civ_disc_prices[$_pid] : $_p_orig_cents;
                    $_p_has_disc    = ($_p_disc_cents < $_p_orig_cents) ? 1 : 0;
                    $_p_save_cents  = $_p_orig_cents - $_p_disc_cents;
                    $_p_save_pct    = ($_p_orig_cents > 0 && $_p_save_cents > 0)
                        ? (int)round(($_p_save_cents / $_p_orig_cents) * 100)
                        : 0;
                    // Same two-conclusion split as the main render path:
                    // $_p_oos_notice decides whether the "unavailable" copy is
                    // shown (backorder irrelevant — the item is still off the
                    // shelf), $_p_oos decides whether add-to-cart is blocked
                    // (backorder lifts it). `product_inv` is the TRACKING
                    // switch: 0 means unlimited stock and the quantity carries
                    // no meaning at all.
                    $_p_inv         = !empty($_vr['product_inv']) ? 1 : 0;
                    $_p_inv_qty     = (int)$_vr['product_inv_qty'];
                    $_p_back        = !empty($_vr['product_backorder']) ? 1 : 0;
                    $_p_oos_notice  = ($_p_inv && $_p_inv_qty <= 0) ? 1 : 0;
                    $_p_oos         = ($_p_oos_notice && !$_p_back) ? 1 : 0;
                    $_p_image_url   = !empty($_vr['product_image'])
                        ? $base_path . encode_url_path((string)$_vr['product_image'])
                        : '';
                    $vproducts[$_pid] = array(
                        'id'              => $_pid,
                        'name'            => (string)$_vr['product_name'],
                        'address_name'    => (string)$_vr['product_address'],
                        'short_description' => (string)$_vr['product_short_desc'],
                        'description'     => (string)$_vr['product_full_desc'],
                        'details'         => (string)$_vr['product_details'],
                        // Price tokens — `price`/`price_formatted` reflect
                        // the EFFECTIVE price (after discount when one
                        // applies). `original_price` keeps the pre-discount
                        // sticker for the strike-through. `has_discount`
                        // (1/0) lets the designer hide-when-empty markup
                        // for the "indirim" badge.
                        'price'           => number_format($_p_disc_cents / 100, 2, '.', ''),
                        'price_cents'     => $_p_disc_cents,
                        'price_formatted' => $currency_symbol . number_format($_p_disc_cents / 100, 2, '.', ','),
                        'original_price'           => number_format($_p_orig_cents / 100, 2, '.', ''),
                        'original_price_cents'     => $_p_orig_cents,
                        'original_price_formatted' => $currency_symbol . number_format($_p_orig_cents / 100, 2, '.', ','),
                        'has_discount'             => (string)$_p_has_disc,
                        'discount_amount'          => $_p_save_cents > 0 ? number_format($_p_save_cents / 100, 2, '.', '') : '',
                        'discount_amount_formatted' => $_p_save_cents > 0 ? $currency_symbol . number_format($_p_save_cents / 100, 2, '.', ',') : '',
                        'discount_percent'         => $_p_save_pct > 0 ? (string)$_p_save_pct : '',
                        'image_url'       => $_p_image_url,
                        'image_alt'       => (string)$_vr['product_short_desc'],
                        'thumbnail_url'   => $_p_image_url,
                        'mpn'             => (string)$_vr['product_mpn'],
                        'gtin'            => (string)$_vr['product_gtin'],
                        'brand'           => (string)$_vr['product_brand'],
                        'weight'          => (string)$_vr['product_weight'],
                        'shippable'       => !empty($_vr['product_shippable']) ? '1' : '0',
                        'custom_field_1'  => (string)$_vr['product_cf1'],
                        'custom_field_2'  => (string)$_vr['product_cf2'],
                        'custom_field_3'  => (string)$_vr['product_cf3'],
                        'custom_field_4'  => (string)$_vr['product_cf4'],
                        'inventory_quantity' => $_p_inv_qty,
                        'out_of_stock'    => (string)$_p_oos,
                        // Empty unless THIS variant is actually off the shelf.
                        // The payload feeds the client-side variant swap, which
                        // writes straight into the bound element — sending the
                        // operator's copy for every variant made an in-stock
                        // (or untracked) variant announce "not available" the
                        // moment the visitor switched to it.
                        'out_of_stock_message' => $_p_oos_notice ? _pg_rich_text_to_inline($_vr['product_oos_msg']) : '',
                        'attrs'           => array(),
                    );
                }
                $_aid = isset($_vr['attr_id']) ? (int)$_vr['attr_id'] : 0;
                $_oid = isset($_vr['option_id']) ? (int)$_vr['option_id'] : 0;
                if ($_aid > 0 && $_oid > 0) {
                    $vproducts[$_pid]['attrs'][$_aid] = $_oid;
                    if (!isset($vattrs[$_aid])) {
                        $_albl = (isset($_vr['attr_label']) && (string)$_vr['attr_label'] !== '')
                                    ? (string)$_vr['attr_label']
                                    : (string)$_vr['attr_name'];
                        $vattrs[$_aid] = array('label' => $_albl, 'options' => array());
                    }
                    if (!isset($vattrs[$_aid]['options'][$_oid])) {
                        $vattrs[$_aid]['options'][$_oid] = (string)$_vr['option_label'];
                    }
                }
            }
        }

        // Gallery URLs per product — one batched lookup so we don't issue
        // a query per variant. Fed into the JS payload as `gallery: [url, …]`.
        $variant_attr_count = count($vattrs);
        if ($vproducts) {
            $_pids_csv = implode(',', array_map('intval', array_keys($vproducts)));
            // products_images_xref has no `id` column — only (product,file_name).
            // Order by product groups all images for one product together;
            // file_name is a stable secondary so the gallery order is at
            // least deterministic across requests.
            $_gx_rows = db_items(
                "SELECT product, file_name FROM products_images_xref
                 WHERE product IN ($_pids_csv)
                 ORDER BY product ASC, file_name ASC"
            );
            foreach ($vproducts as &$_vp_ref) { $_vp_ref['gallery'] = array(); }
            unset($_vp_ref);
            if (is_array($_gx_rows)) {
                foreach ($_gx_rows as $_gx) {
                    $_pid = (int)$_gx['product'];
                    if (isset($vproducts[$_pid]) && !empty($_gx['file_name'])) {
                        $vproducts[$_pid]['gallery'][] = $base_path . encode_url_path((string)$_gx['file_name']);
                    }
                }
            }
            // Prepend the main image to each product's gallery (so the
            // gallery list always starts with the primary image).
            foreach ($vproducts as $_pid => &$_vp_ref2) {
                if ($_vp_ref2['image_url'] !== '') {
                    array_unshift($_vp_ref2['gallery'], $_vp_ref2['image_url']);
                }
            }
            unset($_vp_ref2);
        }

        // Default selection — the attr→option map of the currently-rendered
        // product ($p). When the visitor lands on /urun/Mocha-Office-Chair
        // the picker should already show "Renk: Mocha" + Sepete Ekle enabled,
        // not force them to re-pick the same colour they already navigated
        // to. The JS reads `default_selection` on init and pre-selects
        // those <option>s, treating them as a valid complete combination.
        // Computed BEFORE the auto-inject below so the SSR markup also
        // carries the `selected`/`checked` attribute on the active option —
        // essential for radio / button_group styles where there's no JS-side
        // pre-fill (the DOM IS the source of truth on first paint).
        $default_selection = array();
        if ($p && isset($vproducts[(int)$p['id']])) {
            $default_selection = $vproducts[(int)$p['id']]['attrs'];
        }

        // Auto-injected variant picker — uses the SAME render logic as the
        // designer-placed variant_attr content type (via a synthetic marker
        // run through _civ_expand_variant_attr_markers). That way the
        // widget-level cfg['variant_render_style'] setting (select / radio
        // / button_group / button_list) controls the auto-injected picker
        // identically to a designer-placed variant_attr template.
        $_civ_va_style = isset($cfg['variant_render_style']) ? (string)$cfg['variant_render_style'] : 'select';
        if (!in_array($_civ_va_style, array('select','radio','button_group','button_list'), true)) {
            $_civ_va_style = 'select';
        }
        if ($vattrs) {
            $_civ_synth_cfg = array(
                'renderStyle'  => $_civ_va_style,
                'labelClass'   => 'form-label fw-semibold',
                'controlClass' => '',
                'itemClass'    => '',
                'cssClass'     => 'mb-3',
            );
            $_civ_synth_marker = '<!--pg-variant-attr:'
                . base64_encode(json_encode($_civ_synth_cfg))
                . '-->';
            // Expansion handles the per-attribute loop + style switch.
            // Pass `$default_selection` so radios / btn-group items render
            // with `checked` + `active` on the option matching the URL-resolved
            // product. Without this the picker shows nothing pre-selected
            // even though the visitor IS already on a specific variant.
            // Wrap in pg-civ-variant-picker so the JS anchor lookup AND the
            // styling stay consistent with the legacy marker.
            $variant_picker_html = '<div class="pg-civ-variant-picker mb-3" data-pg-civ-group-id="' . (int)$select_group_id . '">'
                . _civ_expand_variant_attr_markers($_civ_synth_marker, $vattrs, $default_selection)
                . '</div>';
        }
        // Build the JS payload — embedded as a script tag at the end of
        // the widget's HTML. Loaded by pg_civ_variants.js (separate file)
        // which wires the attribute selects to DOM update logic.
        $variant_data = array(
            'group_id'          => (int)$select_group_id,
            'group_name'        => (string)$select_group_name,
            'attrs'             => $vattrs,
            'products'          => array_values($vproducts),
            'default_product_id'=> $p ? (int)$p['id'] : 0,
            'default_selection' => $default_selection,
            'currency_symbol'   => $currency_symbol,
            'api_url'           => $base_path . (defined('OUTPUT_SOFTWARE_DIRECTORY') ? OUTPUT_SOFTWARE_DIRECTORY : 'software') . '/api.php',
        );
    }

    // ── Build token map ───────────────────────────────────────────────────
    // The same keys are produced regardless of found/not-found so the designer's
    // template renders consistently. ^^__not_found^^ distinguishes the two states.
    if (!$p) {
        // Product not found (no URL param, bad slug, or group mismatch).
        // All product tokens collapse to empty strings so unresolved tokens in
        // the designer's "not found" region render cleanly via the final preg_replace.
        $values = array(
            '__id'                   => '',
            '__name'                 => '',
            '__address_name'         => '',
            '__short_description'    => '',
            '__description'          => '',
            '__details'              => '',
            '__detail_url'           => '#',
            '__sort_order'           => '',
            '__created_at'           => '',
            '__price'                => '',
            '__price_formatted'      => '',
            '__price_min'            => '',
            '__price_max'            => '',
            '__price_range'          => '',
            '__has_variants'         => '0',
            '__variant_count'        => '0',
            '__image_url'            => '',
            '__image_alt'            => '',
            '__thumbnail_url'        => '',
            '__gallery_count'        => '0',
            '__gallery_image_1'      => '',
            '__gallery_image_2'      => '',
            '__gallery_image_3'      => '',
            '__gallery_image_4'      => '',
            '__gallery_image_5'      => '',
            '__inventory_tracked'    => '0',
            '__inventory_quantity'   => '0',
            '__quantity_available'   => '0',
            '__backorder'            => '0',
            '__out_of_stock'         => '0',
            '__out_of_stock_label'   => '',
            '__out_of_stock_message' => '',
            '__sku'                  => '',
            '__mpn'                  => '',
            '__gtin'                 => '',
            '__brand'                => '',
            '__manufacturer'         => '',
            '__weight'               => '',
            '__shippable'            => '0',
            '__taxable'              => '0',
            '__category_id'          => '0',
            '__category_name'        => '',
            '__featured'             => '0',
            '__is_new'               => '0',
            '__custom_field_1'       => '',
            '__custom_field_2'       => '',
            '__custom_field_3'       => '',
            '__custom_field_4'       => '',
            '__reward_points'        => '0',
            '__seo_score'            => '0',
            '__not_found'            => $not_found_message,
            // Select-group tokens — empty/zero in not-found state.
            '__is_select_group'      => '0',
            '__select_group_id'      => '0',
            '__select_group_name'    => '',
            '__variant_picker'       => '',
            '__cross_sell_html'      => '',
            // Breadcrumb tokens — empty in not-found state.
            '__breadcrumb_html'      => '',
            '__breadcrumb_inner_html'=> '',
            // Discount tokens — empty in not-found state so designer's
            // strike-through markup renders as nothing.
            '__original_price'           => '',
            '__original_price_formatted' => '',
            '__has_discount'             => '0',
            '__discount_amount'          => '',
            '__discount_amount_formatted'=> '',
            '__discount_percent'         => '',
        );
    } else {
        $p_id = (int)$p['id'];

        // Breadcrumb — built once and reused via $values['__breadcrumb_*'].
        // Source order for the catalog listing page id (used for crumb
        // links + cross-sell card URLs):
        //   1. cfg['catalog_listing_page_id']    — explicit numeric id
        //   2. cfg['catalog_listing_page_name']  — slug (designer-friendly)
        //   3. 0 — crumbs render as plain text (no links)
        //
        // Scope root (where the walk stops) — three-tier resolution:
        //   1. cfg['catalog_listing_root_group_id'] — explicit override (any value, incl. 0)
        //   2. Auto-derived from the catalog page's catalog_listing widget's
        //      product_group_id — this is the "natural" scope: when the
        //      catalog page lists products under group X, the breadcrumb on
        //      the linked detail page should be confined to descendants of X.
        //   3. 0 — no scope, walks to absolute root (legacy behaviour).
        $_civ_cat_pid = 0;
        if (!empty($cfg['catalog_listing_page_id'])) {
            $_civ_cat_pid = (int)$cfg['catalog_listing_page_id'];
        } elseif (!empty($cfg['catalog_listing_page_name'])) {
            $_civ_cat_pid = (int)db_value(
                "SELECT page_id FROM page WHERE page_name = '"
                . e((string)$cfg['catalog_listing_page_name']) . "' LIMIT 1"
            );
        }
        if (isset($cfg['catalog_listing_root_group_id'])) {
            $_civ_cat_root = (int)$cfg['catalog_listing_root_group_id'];
        } else {
            $_civ_cat_root = $_civ_cat_pid > 0
                ? _civ_resolve_catalog_listing_root_group_id($_civ_cat_pid)
                : 0;
        }
        $_civ_breadcrumb = _civ_render_breadcrumb($p, (int)$select_group_id, (string)$select_group_name, $_civ_cat_pid, $_civ_cat_root);

        // Gallery images from products_images_xref (up to 5 named tokens + total count).
        $gallery_rows = db_items(
            "SELECT file_name FROM products_images_xref
             WHERE product = '" . (int)$p_id . "'
             ORDER BY file_name ASC
             LIMIT 10"
        );
        $gallery_urls = array();
        if (is_array($gallery_rows)) {
            foreach ($gallery_rows as $gr) {
                if (!empty($gr['file_name'])) {
                    $gallery_urls[] = $base_path . encode_url_path((string)$gr['file_name']);
                }
            }
        }
        $gallery_count = count($gallery_urls);

        // Group-context tokens: use the configured group when set; otherwise
        // resolve the product's first xref row (lowest product_group id).
        $category_id    = $product_group_id;
        $category_name  = '';
        $sort_order_val = 0;
        $featured_val   = 0;
        $new_date_val   = '';

        if ($product_group_id > 0) {
            $xref_row = db_item(
                "SELECT sort_order, featured, new_date
                 FROM products_groups_xref
                 WHERE product = '" . (int)$p_id . "' AND product_group = '" . (int)$product_group_id . "'
                 LIMIT 1"
            );
            if ($xref_row) {
                $sort_order_val = (int)$xref_row['sort_order'];
                $featured_val   = !empty($xref_row['featured']) ? 1 : 0;
                $new_date_val   = (string)$xref_row['new_date'];
            }
            $category_name = (string)db_value(
                "SELECT name FROM product_groups WHERE id = '" . (int)$product_group_id . "' LIMIT 1"
            );
        } else {
            $fg_row = db_item(
                "SELECT xref.product_group, xref.sort_order, xref.featured, xref.new_date,
                        product_groups.name AS group_name
                 FROM products_groups_xref xref
                 LEFT JOIN product_groups ON product_groups.id = xref.product_group
                 WHERE xref.product = '" . (int)$p_id . "'
                 ORDER BY xref.product_group ASC
                 LIMIT 1"
            );
            if ($fg_row) {
                $category_id    = (int)$fg_row['product_group'];
                $category_name  = isset($fg_row['group_name']) ? (string)$fg_row['group_name'] : '';
                $sort_order_val = isset($fg_row['sort_order']) ? (int)$fg_row['sort_order'] : 0;
                $featured_val   = !empty($fg_row['featured']) ? 1 : 0;
                $new_date_val   = isset($fg_row['new_date']) ? (string)$fg_row['new_date'] : '';
            }
        }

        // is_new: new_date within the last 30 days — mirrors catalog_listing.
        $is_new = 0;
        if ($new_date_val !== '' && $new_date_val !== '0000-00-00') {
            $new_ts = strtotime($new_date_val);
            if ($new_ts && (time() - $new_ts) <= (30 * 86400) && $new_ts <= time()) {
                $is_new = 1;
            }
        }

        // Stock derivation — same logic as catalog_listing.
        //
        // `inventory` is the TRACKING switch, not a quantity. When it's 0 the
        // product has unlimited stock and `inventory_quantity` is meaningless
        // (it is routinely left at 0), so nothing about stock may be inferred
        // from it. Only a tracked product can be out of stock.
        //
        //   inventory=0            → unlimited stock (quantity irrelevant)
        //   inventory=1, qty>0     → stokta
        //   inventory=1, qty<=0    → stokta yok
        //
        // Two SEPARATE conclusions follow, and conflating them was the bug:
        //   • $out_of_stock  — blocks add-to-cart. Backorder overrides it:
        //                      the visitor may still order.
        //   • $oos_notice    — shows the operator's "sorry, unavailable"
        //                      message. Backorder does NOT override it: the
        //                      item genuinely isn't on the shelf and the
        //                      customer should be told, even though they can
        //                      place a back order.
        $tracked      = !empty($p['inventory']) ? 1 : 0;
        $qty          = (int)$p['inventory_quantity'];
        $backord      = !empty($p['backorder']) ? 1 : 0;
        $oos_notice   = ($tracked && $qty <= 0) ? 1 : 0;
        $out_of_stock = ($oos_notice && !$backord) ? 1 : 0;
        $oos_label    = $out_of_stock ? (string)lang('Out of stock') : '';

        // Image URL (primary product image).
        $image_url = !empty($p['image_name'])
            ? $base_path . encode_url_path((string)$p['image_name'])
            : '';

        // Self URL (canonical page URL — same page the user is already on).
        // Reconstruct from $_GET['page'] so ^^__detail_url^^ can serve as
        // og:url / canonical link or "share this product" href.
        $self_url = '#';
        if (defined('PATH') && isset($_GET['page']) && (string)$_GET['page'] !== '') {
            $self_url = PATH . encode_url_path((string)$_GET['page']);
        }

        // Price formatting (cents → display) — with offer-driven discount.
        // $price_cents/$price_decimal/$price_formatted reflect the EFFECTIVE
        // price (after order-scope offers); $original_* keep the sticker
        // price for the strike-through. `__has_discount` (1/0) toggles the
        // designer's "indirim" badge / strike-through visibility via
        // hide-when-empty markup.
        $original_price_cents     = (int)$p['price'];
        $price_cents              = isset($_civ_disc_prices[$p_id]) ? (int)$_civ_disc_prices[$p_id] : $original_price_cents;
        $price_decimal            = $price_cents / 100;
        $original_price_decimal   = $original_price_cents / 100;
        $price_formatted          = $currency_symbol . number_format($price_decimal, 2, '.', ',');
        $original_price_formatted = $currency_symbol . number_format($original_price_decimal, 2, '.', ',');
        $has_discount             = ($price_cents < $original_price_cents) ? 1 : 0;
        $discount_save_cents      = $original_price_cents - $price_cents;
        $discount_save_pct        = ($original_price_cents > 0 && $discount_save_cents > 0)
                                    ? (int)round(($discount_save_cents / $original_price_cents) * 100) : 0;

        $values = array(
            // Basic
            '__id'                   => (string)$p_id,
            '__name'                 => isset($p['name']) ? (string)$p['name'] : '',
            '__address_name'         => isset($p['address_name']) ? (string)$p['address_name'] : '',
            '__short_description'    => isset($p['short_description']) ? (string)$p['short_description'] : '',
            '__description'          => isset($p['full_description']) ? (string)$p['full_description'] : '',
            '__details'              => isset($p['details']) ? (string)$p['details'] : '',
            '__detail_url'           => $self_url,
            '__sort_order'           => (string)$sort_order_val,
            '__created_at'           => !empty($p['timestamp']) ? date('Y-m-d', (int)$p['timestamp']) : '',

            // Price — effective (post-discount when one applies).
            //   • __price (numeric, no symbol) — for math / data attrs
            //   • __price_formatted — with currency symbol; AND when an
            //     offer is in effect this token expands to the strike+new
            //     HTML so a designer who only binds the formatted price
            //     still sees the discount visual without composing two
            //     spans.
            '__price'                => number_format($price_decimal, 2, '.', ''),
            '__price_formatted'      => $has_discount ? _pg_format_price_with_discount($original_price_cents, $price_cents, $currency_symbol) : $price_formatted,
            '__price_min'            => number_format($price_decimal, 2, '.', ''),
            '__price_max'            => number_format($price_decimal, 2, '.', ''),
            '__price_range'          => $has_discount ? _pg_format_price_with_discount($original_price_cents, $price_cents, $currency_symbol) : $price_formatted,
            // Price — sticker (pre-discount, for strike-through display).
            // EMPTY when no discount applies → the designer's bound
            // strike-through element auto-hides (via CSS :empty rule).
            // Only when an actual offer is in effect do these tokens
            // carry the original price for visible strike-through.
            '__original_price'           => $has_discount ? number_format($original_price_decimal, 2, '.', '') : '',
            '__original_price_formatted' => $has_discount ? $original_price_formatted : '',
            '__has_discount'             => (string)$has_discount,
            '__discount_amount'          => $discount_save_cents > 0 ? number_format($discount_save_cents / 100, 2, '.', '') : '',
            '__discount_amount_formatted'=> $discount_save_cents > 0 ? $currency_symbol . number_format($discount_save_cents / 100, 2, '.', ',') : '',
            '__discount_percent'         => $discount_save_pct > 0 ? (string)$discount_save_pct : '',
            // Pre-rendered strike+new HTML — the unified discount visual
            // across catalog/cart/cross-sell/upsell. Drop this single
            // token in place of __price_formatted to get the discount
            // strike-through "for free" (red strike + green discounted).
            '__price_block_html'         => _pg_format_price_with_discount($original_price_cents, $price_cents, $currency_symbol),
            '__has_variants'         => '0',
            '__variant_count'        => '0',

            // Image
            '__image_url'            => $image_url,
            '__image_alt'            => isset($p['name']) ? (string)$p['name'] : '',
            '__thumbnail_url'        => $image_url,   // alias — no separate thumb yet
            '__gallery_count'        => (string)$gallery_count,
            '__gallery_image_1'      => isset($gallery_urls[0]) ? $gallery_urls[0] : '',
            '__gallery_image_2'      => isset($gallery_urls[1]) ? $gallery_urls[1] : '',
            '__gallery_image_3'      => isset($gallery_urls[2]) ? $gallery_urls[2] : '',
            '__gallery_image_4'      => isset($gallery_urls[3]) ? $gallery_urls[3] : '',
            '__gallery_image_5'      => isset($gallery_urls[4]) ? $gallery_urls[4] : '',

            // Stock / inventory
            '__inventory_tracked'    => (string)$tracked,
            '__inventory_quantity'   => (string)$qty,
            '__quantity_available'   => (string)$qty,
            '__backorder'            => (string)$backord,
            '__out_of_stock'         => (string)$out_of_stock,
            '__out_of_stock_label'   => $oos_label,
            // Gated on $oos_notice, NOT $out_of_stock: a back-orderable item is
            // still off the shelf, so the notice belongs there too — only the
            // add-to-cart block is lifted. products.out_of_stock_message is
            // always populated (it's the operator's default copy), so it must
            // never be returned unconditionally. Empty string collapses the
            // bound element via the standard token cleanup.
            '__out_of_stock_message' => ($oos_notice && !empty($p['out_of_stock_message']))
                                            ? _pg_rich_text_to_inline($p['out_of_stock_message']) : '',

            // Identity
            '__sku'                  => isset($p['mpn']) ? (string)$p['mpn'] : '',  // alias
            '__mpn'                  => isset($p['mpn']) ? (string)$p['mpn'] : '',
            '__gtin'                 => isset($p['gtin']) ? (string)$p['gtin'] : '',
            '__brand'                => isset($p['brand']) ? (string)$p['brand'] : '',
            '__manufacturer'         => isset($p['brand']) ? (string)$p['brand'] : '',  // alias

            // Physical
            '__weight'               => isset($p['weight']) ? (string)$p['weight'] : '',
            '__shippable'            => !empty($p['shippable']) ? '1' : '0',
            '__taxable'              => !empty($p['taxable'])   ? '1' : '0',

            // Group / category
            '__category_id'          => (string)$category_id,
            '__category_name'        => $category_name,
            '__featured'             => (string)$featured_val,
            '__is_new'               => (string)$is_new,

            // Custom fields
            '__custom_field_1'       => isset($p['custom_field_1']) ? (string)$p['custom_field_1'] : '',
            '__custom_field_2'       => isset($p['custom_field_2']) ? (string)$p['custom_field_2'] : '',
            '__custom_field_3'       => isset($p['custom_field_3']) ? (string)$p['custom_field_3'] : '',
            '__custom_field_4'       => isset($p['custom_field_4']) ? (string)$p['custom_field_4'] : '',

            // Reward / SEO
            '__reward_points'        => isset($p['reward_points']) ? (string)$p['reward_points'] : '0',
            '__seo_score'            => isset($p['seo_score']) ? (string)$p['seo_score'] : '0',

            // not_found: empty when product is found so the designer's not-found region
            // renders blank. The token is exposed so conditional visibility can be bound.
            '__not_found'            => '',
            // Select-group state — TRUE when the URL slug matched a
            // display_type='select' product group rather than a product
            // directly. The visitor lands here from a catalog_listing
            // group card click. Designer can hide/show the variant picker
            // section based on `__is_select_group`. Group title takes
            // precedence over the resolved-default-product's title via
            // `__select_group_name` (full title) and `__select_group_id`
            // (numeric id for further lookups).
            '__is_select_group'      => $is_select_group ? '1' : '0',
            '__select_group_id'      => (string)$select_group_id,
            '__select_group_name'    => h($select_group_name),
            // Variant picker — populated by the select-group block above.
            // When the URL slug pointed at a display_type='select' group,
            // this contains <select> elements (one per shared attribute).
            // For direct-product URLs this stays empty.
            '__variant_picker'       => $variant_picker_html,
            // Breadcrumb — Ana Katalog → Group → Subgroup → Product/Variant.
            // Two tokens: `__breadcrumb_html` (full <nav>-wrapped) for raw
            // token placement; `__breadcrumb_inner_html` (just the <ol>)
            // for the section binding pattern (designer's <nav>/<div>
            // wrapper preserved). cfg['catalog_listing_page_id'] enables
            // crumb URLs pointing back at the listing page; without it
            // crumbs render as plain text.
            '__breadcrumb_html'      => isset($_civ_breadcrumb['full'])  ? $_civ_breadcrumb['full']  : '',
            '__breadcrumb_inner_html'=> isset($_civ_breadcrumb['inner']) ? $_civ_breadcrumb['inner'] : '',

            // Cross-sell items ("birlikte satın alınanlar" / "benzer ürünler")
            // — pre-rendered server-side. Tier 1: order-history-based via
            // get_cross_sell_items (when site has order data). Tier 2:
            // same-product-group fallback (siblings) when order history is
            // empty. The variant chooser JS refreshes this block via API
            // when the visitor switches variants so the suggestions reflect
            // the active product.
            //
            // Toggle: cfg['cross_sell_enabled'] — when explicitly false the
            // token resolves to '' and the auto-append below is skipped, so
            // designer can fully turn off cross-sell from the widget
            // settings panel without re-saving the tree. Default: ON.
            //
            // catalog_detail page id source order:
            //   1. cfg['catalog_detail_page_id'] (designer-set in widget config)
            //   2. The current page's id (visitor IS on the catalog detail page)
            // Falling back to the current page is what every catalog item
            // detail page actually wants — sibling products link to the
            // same detail-page route the visitor came in via.
            '__cross_sell_html'      => (isset($cfg['cross_sell_enabled']) && !$cfg['cross_sell_enabled'])
                ? ''
                : _civ_render_cross_sell(
                    (int)$p_id,
                    // catalog_detail page id source:
                    //   1. cfg['catalog_detail_page_id'] (explicit)
                    //   2. The current page (visitor IS on the detail page)
                    //   3. cfg['catalog_listing_page_name'] (slug fallback —
                    //      cross-sell cards link to listing's group view)
                    isset($cfg['catalog_detail_page_id']) && (int)$cfg['catalog_detail_page_id'] > 0
                        ? (int)$cfg['catalog_detail_page_id']
                        : (int)db_value("SELECT page_id FROM page WHERE page_name = '"
                                        . e(isset($_GET['page']) ? explode('/', (string)$_GET['page'])[0] : '')
                                        . "' LIMIT 1")
                ),
        );
    }

    // ── Auto-inject variant picker BEFORE recipient picker section ───────
    // Smart placement: when the designer hasn't placed
    // `^^__variant_picker^^` explicitly, we inject a placeholder marker
    // BEFORE the recipient_picker section in the rendered HTML — so the
    // picker lands in a sensible spot (Renk: …  /  Alıcı: …  /  Quantity
    // + Sepete Ekle). When no recipient picker exists, we fall back to
    // prepending the loop_template (better than nothing).
    //
    // The marker `<!--pg-variant-picker-slot-->` is stamped into the
    // recipient_picker section's _attrs as a `data-pg-civ-section` data
    // attribute so we can string-find it in the rendered HTML and inject
    // the picker just before that opening tag.
    //
    // First: tag the recipient_picker container with a recognizable
    // data attribute. This requires walking $_civ_pre_tree before the
    // binding pass — done in _civ_tag_section_for_variant_picker_slot.
    if ($is_select_group && $variant_picker_html !== '') {
        $_picker_token = '^^__variant_picker^^';
        // Designer-controlled placement signals — when ANY of these is in
        // the tree, skip the auto-inject. The designer chose where the
        // picker goes:
        //   • `^^__variant_picker^^` token (legacy explicit placement)
        //   • `<!--pg-variant-attr:...-->` markers (new variant_attr
        //     content type — rendered by _civ_expand_variant_attr_markers
        //     after token substitution, with full per-attribute cloning)
        $_marker_re_va = '/<!--pg-variant-attr:[A-Za-z0-9+\/=]+-->/';
        $_already_placed = (strpos($loop_template, $_picker_token) !== false)
                        || (strpos($static_html,   $_picker_token) !== false)
                        || preg_match($_marker_re_va, $loop_template)
                        || preg_match($_marker_re_va, $static_html);
        if (!$_already_placed) {
            // Smart placement, in order of preference:
            //   1. Before the recipient_picker section's container (typical
            //      shippable products with multi-recipient support)
            //   2. Before the Sepete Ekle button's row/grid wrapper (digital
            //      products + sites without multi-recipient — landing the
            //      picker right above the "add" action)
            //   3. Loop_template prepend (final fallback when neither anchor
            //      exists — at least the picker is visible somewhere)
            //
            // For #2 we walk back from the Sepete Ekle button to the closest
            // structural ancestor (a row / col / form-control container) by
            // matching a ROW-LIKE wrapper just before the button. Cheap heuristic
            // that lands the picker in a sensible spot without a full DOM walk.
            $_re_recipient = '/<([a-zA-Z][a-zA-Z0-9]*)\s+([^>]*data-pg-civ-section="recipient_picker"[^>]*)>/';
            $_re_sepete_row = '/(<div[^>]*class="[^"]*(?:row|d-grid|d-flex|input-group)[^"]*"[^>]*>(?:(?!<\/div>).)*?<button[^>]*data-pg-civ-action="add_to_cart")/s';
            $_re_sepete_btn = '/<button[^>]*data-pg-civ-action="add_to_cart"/';
            $_injected = false;

            // Scan loop_template first (where bound elements typically sit),
            // then static_html (chrome).
            foreach (array('loop_template', 'static_html') as $_target) {
                $_html = $$_target;
                if ($_html === '') continue;

                if (preg_match($_re_recipient, $_html)) {
                    $$_target = preg_replace(
                        $_re_recipient,
                        $variant_picker_html . '<$1 $2>',
                        $_html,
                        1
                    );
                    $_injected = true; break;
                }
                if (preg_match($_re_sepete_row, $_html)) {
                    $$_target = preg_replace(
                        $_re_sepete_row,
                        $variant_picker_html . '$1',
                        $_html,
                        1
                    );
                    $_injected = true; break;
                }
                if (preg_match($_re_sepete_btn, $_html)) {
                    $$_target = preg_replace(
                        $_re_sepete_btn,
                        $variant_picker_html . '$0',
                        $_html,
                        1
                    );
                    $_injected = true; break;
                }
            }

            // Final fallback — prepend to loop_template (or static slot)
            // so the picker still appears even when none of the anchors
            // were found.
            if (!$_injected) {
                if ($loop_template !== '') {
                    $loop_template = $variant_picker_html . $loop_template;
                } elseif ($static_html !== '') {
                    $static_html = str_replace(
                        '<!--pg-loop-slot-->',
                        $variant_picker_html . '<!--pg-loop-slot-->',
                        $static_html
                    );
                }
            }
        }
    }

    // ── Breadcrumb placement is now designer-controlled ──────────────────
    // Designer drops a `breadcrumb` content type (or binds a <nav> with
    // section='breadcrumb', or places `^^__breadcrumb_html^^` token) where
    // they want the breadcrumb to appear. The previous auto-inject made
    // breadcrumbs land in unexpected places (middle of layout, below
    // tabs) because the loop_slot lives wherever the designer's tree
    // happens to put it. Removing the auto-inject puts placement
    // entirely in the designer's hands.
    //
    // The breadcrumb content type marker `<!--pg-breadcrumb-placeholder-->`
    // gets substituted at the bottom of the render flow.

    // ── Auto-append cross-sell at end if not placed by designer ──────────
    // Designers can place `^^__cross_sell_html^^` anywhere they want. When
    // they don't, we append it AFTER the loop_template (or AFTER the loop
    // slot in static_html) so it sits at the bottom of the widget — the
    // conventional location for "ordered together" suggestions on a
    // product detail page.
    if (isset($values['__cross_sell_html']) && $values['__cross_sell_html'] !== '') {
        $_cs_token = '^^__cross_sell_html^^';
        $_cs_already_placed = (strpos($loop_template, $_cs_token) !== false)
                           || (strpos($static_html,   $_cs_token) !== false);
        if (!$_cs_already_placed) {
            if ($loop_template !== '') {
                $loop_template = $loop_template . $_cs_token;
            } elseif ($static_html !== '') {
                $static_html = str_replace(
                    '<!--pg-loop-slot-->',
                    '<!--pg-loop-slot-->' . $_cs_token,
                    $static_html
                );
            }
        }
    }

    // ── Apply tokens to template + static_html (single render, NO loop) ──
    // Longest keys first so '^^__out_of_stock_message^^' is replaced before
    // '^^__out_of_stock^^' (prefix-safe replacement order).
    $rendered = $loop_template;
    $static   = $static_html;
    $keys = array_keys($values);
    usort($keys, function ($a, $b) { return strlen($b) - strlen($a); });
    foreach ($keys as $k) {
        $tok = '^^' . $k . '^^';
        $rendered = str_replace($tok, (string)$values[$k], $rendered);
        if ($static !== '') $static = str_replace($tok, (string)$values[$k], $static);
    }
    // Strip any remaining unresolved tokens (matches form_item_view convention).
    $rendered = preg_replace('/\^\^[A-Za-z0-9_]+\^\^/', '', $rendered);
    if ($static !== '') $static = preg_replace('/\^\^[A-Za-z0-9_]+\^\^/', '', $static);

    $_civ_html = ($static === '') ? $rendered : str_replace('<!--pg-loop-slot-->', $rendered, $static);

    // ── Resolve breadcrumb content-type marker ───────────────────────────
    // Designer dropped a `breadcrumb` content node — replace its marker
    // with the live breadcrumb HTML computed earlier (in $values). When
    // not placed, the marker simply isn't present and nothing happens.
    $_civ_bc_html = isset($values['__breadcrumb_html']) ? $values['__breadcrumb_html'] : '';
    if (strpos($_civ_html, '<!--pg-breadcrumb-placeholder-->') !== false) {
        $_civ_html = str_replace('<!--pg-breadcrumb-placeholder-->', $_civ_bc_html, $_civ_html);
    }

    // ── Expand variant_attr markers (designer-placed templates) ──────────
    // Designer dropped one or more `variant_attr` content nodes in the
    // tree. They emit `<!--pg-variant-attr:base64(cfg)-->` markers in the
    // rendered HTML. _civ_expand_variant_attr_markers replaces each
    // marker with one selector block PER attribute the active
    // product/group has — so a single template call yields multiple
    // selectors when the product has multiple shared attributes
    // (Renk + Beden + Boyut → three blocks from one template).
    if ($is_select_group && !empty($vattrs)) {
        $_civ_html = _civ_expand_variant_attr_markers(
            $_civ_html,
            $vattrs,
            isset($default_selection) ? $default_selection : array()
        );
    } else {
        // Strip stray markers (no attrs available, e.g. direct-product URL
        // outside any select group). Markers as HTML comments would leak
        // to the visitor's source view.
        $_civ_html = preg_replace('/<!--pg-variant-attr:[A-Za-z0-9+\/=]+-->/', '', $_civ_html);
    }

    // ── Empty / mismatched URL → show alert instead of the widget design ──
    // If no product was resolved (URL had no trailing address_name segment,
    // or the segment didn't match an enabled product), the designer's layout
    // would render with empty tokens (no name, no price, broken image src) —
    // worse, the form would POST a blank product_id. Replace the entire
    // output with a single Bootstrap alert. Designer's not_found_message
    // (config "Bulunamadı mesajı") drives the text; default falls back to
    // the legacy "Sorry, the item could not be found." equivalent in TR.
    if (!$p) {
        // Wrap the not-found alert in a Bootstrap container so it inherits
        // the same width/padding as the rest of the page rather than
        // bleeding edge-to-edge across the viewport.
        return '<div class="container py-4">'
             . '<div class="pg-sw-civ-not-found alert alert-warning" role="alert">'
             . h($not_found_message)
             . '</div>'
             . '</div>';
    }

    // ── Auto-wrap in <form> targeting catalog_detail.php ──────────────────
    // Every catalog_item_view widget is wrapped — designer doesn't need to
    // place a form node. The wrapper carries the hidden fields catalog_detail
    // expects (CSRF, product_id, current_url). Designer-bound buttons rendered
    // via _bindings.action='add_to_cart' submit this form; everything else
    // (links, etc.) inside the wrapper still works normally.
    //
    // URL shape: OUTPUT_PATH + OUTPUT_SOFTWARE_DIRECTORY + '/catalog_detail.php'
    // Mirrors how legacy custom-style detail templates (get_catalog_detail.php)
    // build the form action — catalog_detail.php lives in the software/ dir.
    $_civ_out_path = defined('OUTPUT_PATH') ? OUTPUT_PATH : '/';
    $_civ_sw_dir   = defined('OUTPUT_SOFTWARE_DIRECTORY') ? OUTPUT_SOFTWARE_DIRECTORY : 'software';
    $_civ_action   = $_civ_out_path . $_civ_sw_dir . '/catalog_detail.php';

    // current_url is the redirect target catalog_detail.php uses on the
    // ERROR path (lines 49/95/118/151 of catalog_detail.php). It MUST
    // always point back to the product page so the visitor can read the
    // server-side validation message and fix their input — sending them
    // to /cart on error would make the message appear on the wrong page
    // and strand them away from the product.
    //
    // SUCCESS path uses next_page_id below, NOT current_url.
    $_civ_current_url = (isset($_SERVER['REQUEST_URI']) && $_SERVER['REQUEST_URI'] !== '')
                            ? (string)$_SERVER['REQUEST_URI']
                            : ($_civ_out_path . $_civ_page_full);

    $_civ_pid_for_form = ($p && isset($p['id'])) ? (int)$p['id'] : 0;
    $_civ_hidden = get_token_field()
        . '<input type="hidden" name="product_id" value="' . $_civ_pid_for_form . '">'
        . '<input type="hidden" name="current_url" value="' . h($_civ_current_url) . '">'
        // next_page_id: the "Sonraki Sayfa" widget config. catalog_detail.php
        // honors this on the SUCCESS path (preferred over the legacy
        // catalog_detail_pages table lookup) so a system-layout product page
        // can pick its own post-add destination without needing a
        // catalog_detail_pages row.
        . ($next_page_id > 0 ? '<input type="hidden" name="next_page_id" value="' . (int)$next_page_id . '">' : '');

    // ── Variant chooser script + data payload ────────────────────────────
    // Only emitted when the URL slug pointed at a select-type product group
    // AND the group has at least one shared attribute. The payload is a
    // self-contained JSON blob carrying every variant's bound fields plus
    // the attribute → option map, so the JS can find the matching product
    // for the visitor's chosen combination without a server round-trip.
    //
    // The script (pg_civ_variants.js) wires:
    //   • change handler on every .pg-civ-variant-attr <select>
    //   • when ALL attributes are chosen → find matching product
    //   • update [data-pg-bind="prop:field;…"] elements with new values
    //   • refresh the product_id hidden input + Sepete Ekle disabled state
    //   • optional API call to confirm stock + grab any field not preloaded
    $_civ_variant_script = '';
    if ($is_select_group && !empty($variant_data['products']) && !empty($variant_data['attrs'])) {
        $_civ_v_base = (defined('OUTPUT_PATH') ? OUTPUT_PATH : '/')
                     . (defined('OUTPUT_SOFTWARE_DIRECTORY') ? OUTPUT_SOFTWARE_DIRECTORY : 'software')
                     . '/assets/js';
        $_civ_v_v   = '1';  // bump on pg_civ_variants.js source changes
        $_civ_variant_script = '<script>window.pgCivVariants = window.pgCivVariants || [];'
            . 'window.pgCivVariants.push(' . json_encode($variant_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ');'
            . '</script>'
            . '<script src="' . h($_civ_v_base) . 'pg_civ_variants.js?v=' . $_civ_v_v . '" defer></script>';
    }

    // No auto-prepended messages placeholder — designer controls placement
    // via a Messages content node inside the widget tree.
    return _pg_widget_layout_css_once()
         . '<form action="' . h($_civ_action) . '" method="post" data-pg-civ-form="1">'
         . $_civ_hidden
         . $_civ_html
         . $_civ_variant_script
         . '</form>';
}

// ============================================================================
// Catalog RSS feed + structured data (JSON-LD) — system widget support
// ============================================================================
// Legacy Pinegrap already ships both an RSS feed (`?rss=true`) and
// schema.org JSON-LD structured data for products — see get_page.php's
// `elseif (isset($_GET['rss'])...)` branch and get_page_content.php's
// STRUTURED_DATA splice. BUT both of those are gated on `page.page_type`
// being one of the legacy values ('catalog', 'catalog detail', …) — a page
// built with the visual designer's "Katalog Ürünleri" (catalog_listing) /
// "Ürün Detay" (catalog_item_view) system widgets is typically
// page_type='standard' and never matches, so it silently got neither
// feature. The functions below extend both features to those pages,
// reusing the same product-resolution conventions as
// _render_system_widget_catalog_listing / _render_system_widget_catalog_item_view
// so the feed/structured-data always matches what the visitor actually sees.

// Locate the first system widget of a given regionType ('catalog_listing' or
// 'catalog_item_view') embedded as a shared_ref on a page's style tree.
// Mirrors the sharedId-substring-match technique already used by
// _civ_resolve_catalog_listing_root_group_id() above. Returns
//   array('widget_id'=>int, 'cfg'=>array, 'product_group_id'=>int)
// or null when the page has no widget of that type.
function _pg_find_system_widget_on_page($page_id, $region_type)
{
    $page_id = (int)$page_id;
    if ($page_id <= 0) return null;

    $style_tree = pg_page_tree_json($page_id);
    if ($style_tree === '') return null;

    $rows = db_items(
        "SELECT id, system_region_config FROM shared_components
         WHERE system_region_config LIKE '%\"regionType\":\"" . $region_type . "\"%'
         ORDER BY id ASC"
    );
    if (!is_array($rows)) return null;

    foreach ($rows as $r) {
        $wid = (int)$r['id'];
        if ($wid <= 0) continue;
        if (strpos($style_tree, '"sharedId":' . $wid . ',') === false &&
            strpos($style_tree, '"sharedId":' . $wid . '}') === false) {
            continue;
        }
        $cfg = json_decode((string)$r['system_region_config'], true);
        if (!is_array($cfg)) $cfg = array();
        return array(
            'widget_id'        => $wid,
            'cfg'              => $cfg,
            'product_group_id' => isset($cfg['product_group_id']) ? (int)$cfg['product_group_id'] : 0,
        );
    }
    return null;
}

// Resolve the "active" product group for RSS/structured-data purposes the
// same way _render_system_widget_catalog_listing resolves it for HTML
// rendering: the widget's configured product_group_id, overridable via the
// URL's drill-down segment (/<page-slug>/<group-slug>) or ?cat=<slug> on a
// homepage-hosted catalog. Intentionally simpler than the full HTML
// renderer's resolution (no search/attribute-filter state) — a feed
// represents "which product set", not one visitor's current query.
function _pg_catalog_listing_resolve_active_group($product_group_id, $page_slug_full)
{
    $active_group_id   = (int)$product_group_id;
    $listing_page_slug = $page_slug_full;
    $slash = mb_strpos($listing_page_slug, '/', 0, 'UTF-8');
    if ($slash !== false) $listing_page_slug = mb_substr($listing_page_slug, 0, $slash, 'UTF-8');
    $is_homepage = ($listing_page_slug === '');

    $segment = '';
    if ($is_homepage) {
        $segment = isset($_GET['cat']) ? trim((string)$_GET['cat']) : '';
    } elseif ($page_slug_full !== '' && $slash !== false) {
        $segment = mb_substr($page_slug_full, $slash + 1, null, 'UTF-8');
        $next = mb_strpos($segment, '/', 0, 'UTF-8');
        if ($next !== false) $segment = mb_substr($segment, 0, $next, 'UTF-8');
        $segment = urldecode(trim($segment));
    }
    if ($segment !== '' && strlen($segment) <= 200) {
        $found = (int)db_value(
            "SELECT id FROM product_groups WHERE address_name = '" . e($segment) . "' AND enabled = 1 LIMIT 1"
        );
        if ($found > 0) $active_group_id = $found;
    }
    return $active_group_id;
}

// Expand each of $root_ids into [self + every enabled descendant group id].
//
// Product groups nest, and almost every catalog operation that says "the
// products in this group" actually means "the products anywhere under this
// group" — the legacy catalog's get_products_in_product_group() and
// get_price_range() are both recursive for exactly this reason. A shop root
// typically holds NO products directly; they all live in sub-categories, so a
// direct-membership query returns nothing at all.
//
// Walks product_groups in PHP rather than issuing a recursive SQL CTE. The
// product runs on MySQL 5.7, which has no CTEs, so every place in this module
// that needs a group subtree (listing scope, attribute filter panel, group
// search, price ranges, feeds) goes through this helper and an IN (...) list.
// The table is small and the walk is cheap on ordinary catalog renders.
//
// Returns array(root_id => array(ids…)). Cycles and pathological depth are
// guarded; a group is never visited twice.
function _pg_catalog_group_subtree_ids($root_ids)
{
    $roots = array();
    foreach ((array)$root_ids as $r) {
        $r = (int)$r;
        if ($r > 0) $roots[$r] = true;
    }
    if (!$roots) return array();

    $rows = db_items("SELECT id, parent_id FROM product_groups WHERE enabled = 1");
    $kids = array();
    if (is_array($rows)) {
        foreach ($rows as $r) {
            $kids[(int)$r['parent_id']][] = (int)$r['id'];
        }
    }

    $out = array();
    foreach (array_keys($roots) as $root) {
        $acc   = array();
        $stack = array($root);
        $guard = 5000;
        while ($stack && $guard-- > 0) {
            $cur = array_pop($stack);
            if (isset($acc[$cur])) continue;   // also guards cycles
            $acc[$cur] = true;
            if (isset($kids[$cur])) {
                foreach ($kids[$cur] as $k) $stack[] = $k;
            }
        }
        $out[$root] = array_keys($acc);
    }
    return $out;
}

// Column set shared by every product row we pull for RSS / JSON-LD — kept in
// one place so the feed and the structured data always describe the product
// identically. Mirrors the columns _render_system_widget_catalog_listing /
// _render_system_widget_catalog_item_view already select for HTML rendering.
function _pg_catalog_rss_product_columns()
{
    return "products.id, products.name, products.title, products.address_name,
            products.short_description, products.full_description,
            products.meta_description, products.price, products.image_name,
            products.timestamp, products.inventory, products.inventory_quantity,
            products.backorder, products.mpn, products.gtin, products.brand,
            products.google_product_category";
}

// Pull the enabled product rows a catalog_listing RSS/JSON-LD feed should
// list, without the search/attribute-filter/pagination state (a feed lists
// the browsable set, not one visitor's current page/query).
//
// Membership is resolved over the group's WHOLE SUBTREE, matching the legacy
// catalog feed's get_products_in_product_group($id, $multilevel = true). A
// direct-membership query is wrong here: a shop root usually has no products
// of its own — every product hangs off a sub-category — so direct membership
// yields an empty <channel> even though the page clearly lists products.
//
// Capped at $limit rows so a huge catalog can't produce an unbounded feed.
function _pg_catalog_listing_rss_products($active_group_id, $order_by_field = 'sort_order', $order_by_direction = 'DESC', $limit = 250)
{
    $active_group_id    = (int)$active_group_id;
    $limit               = max(1, min(1000, (int)$limit));
    $order_by_direction  = (strtoupper($order_by_direction) === 'ASC') ? 'ASC' : 'DESC';

    $group_join    = '';
    $extra_select  = '';
    $order_sql     = 'products.id DESC';
    if ($active_group_id > 0) {
        $subtree = _pg_catalog_group_subtree_ids(array($active_group_id));
        $ids     = isset($subtree[$active_group_id]) ? $subtree[$active_group_id] : array($active_group_id);
        $id_list = implode(',', array_map('intval', $ids));

        $group_join = "INNER JOIN products_groups_xref xref
                          ON xref.product = products.id
                         AND xref.product_group IN ($id_list)";
        // A product can sit in several groups of the same subtree, so the
        // per-group sort_order has to be aggregated rather than read raw —
        // otherwise which row wins is arbitrary.
        $extra_select = ", MIN(xref.sort_order) AS _pg_sort";
        $field_map = array(
            'sort_order' => '_pg_sort',
            'name'       => 'products.name',
            'price'      => 'products.price',
            'newest'     => 'products.timestamp',
        );
        $field = isset($field_map[$order_by_field]) ? $field_map[$order_by_field] : '_pg_sort';
        $order_sql = "$field $order_by_direction, products.id DESC";
    } else {
        $field_map = array(
            'name'   => 'products.name',
            'price'  => 'products.price',
            'newest' => 'products.timestamp',
        );
        if (isset($field_map[$order_by_field])) {
            $order_sql = $field_map[$order_by_field] . " $order_by_direction, products.id DESC";
        }
    }

    return db_items(
        "SELECT " . _pg_catalog_rss_product_columns() . $extra_select . "
         FROM products
         $group_join
         WHERE products.enabled = 1
         GROUP BY products.id
         ORDER BY $order_sql
         LIMIT $limit"
    );
}

// Resolve the single product a catalog_item_view widget is currently
// showing — mirrors the address_name/pid resolution + select-type
// product_group → representative-variant fallback already implemented in
// _render_system_widget_catalog_item_view (functions.php, above), trimmed
// to only the columns RSS/JSON-LD need. When the URL doesn't pin a specific
// product (e.g. the feed URL just points at the widget's page with no
// /<slug> or ?pid=), falls back to the group's first enabled product —
// same "representative" convention the widget itself uses.
function _pg_catalog_item_resolve_product($product_group_id)
{
    $product_group_id     = (int)$product_group_id;
    $product_address_name = '';
    $product_id_direct    = 0;

    $page_full = isset($_GET['page']) ? (string)$_GET['page'] : '';
    if ($page_full !== '') {
        $slash = mb_strpos($page_full, '/', 0, 'UTF-8');
        if ($slash !== false) {
            $seg  = mb_substr($page_full, $slash + 1, null, 'UTF-8');
            $next = mb_strpos($seg, '/', 0, 'UTF-8');
            if ($next !== false) $seg = mb_substr($seg, 0, $next, 'UTF-8');
            $seg = urldecode(trim($seg));
            if ($seg !== '' && strlen($seg) <= 200) $product_address_name = $seg;
        }
    }
    if ($product_address_name === '' && isset($_GET['pid']) && (int)$_GET['pid'] > 0) {
        $product_id_direct = (int)$_GET['pid'];
    }

    $cols = _pg_catalog_rss_product_columns();
    $group_filter = ($product_group_id > 0)
        ? "AND EXISTS (SELECT 1 FROM products_groups_xref pgx WHERE pgx.product = products.id AND pgx.product_group = '" . $product_group_id . "')"
        : '';

    $p = null;
    if ($product_address_name !== '') {
        $p = db_item(
            "SELECT $cols FROM products
             WHERE products.enabled = 1 AND products.address_name = '" . e($product_address_name) . "' $group_filter
             LIMIT 1"
        );
        if (!$p) {
            // Slug may point at a select-type product_group (variant chooser
            // landing page) — fall back to its first enabled product.
            $grp_id = (int)db_value(
                "SELECT id FROM product_groups
                 WHERE address_name = '" . e($product_address_name) . "' AND enabled = 1 AND display_type = 'select'
                 LIMIT 1"
            );
            if ($grp_id > 0) {
                $p = db_item(
                    "SELECT $cols FROM products
                     INNER JOIN products_groups_xref pgx ON pgx.product = products.id
                     WHERE products.enabled = 1 AND pgx.product_group = '$grp_id'
                     ORDER BY pgx.sort_order ASC, products.id ASC LIMIT 1"
                );
            }
        }
    } elseif ($product_id_direct > 0) {
        $p = db_item(
            "SELECT $cols FROM products
             WHERE products.enabled = 1 AND products.id = '" . $product_id_direct . "' $group_filter
             LIMIT 1"
        );
    } elseif ($product_group_id > 0) {
        $p = db_item(
            "SELECT $cols FROM products
             INNER JOIN products_groups_xref pgx ON pgx.product = products.id
             WHERE products.enabled = 1 AND pgx.product_group = '" . $product_group_id . "'
             ORDER BY pgx.sort_order ASC, products.id ASC LIMIT 1"
        );
    }
    return $p;
}

// True when a tracked product with no backorder has zero quantity left —
// same derivation used throughout the codebase (legacy RSS branch, JSON-LD
// splice, catalog widgets).
function _pg_product_is_out_of_stock($product)
{
    return (!empty($product['inventory']) && empty($product['backorder']) && (int)$product['inventory_quantity'] <= 0);
}

// Best available display title / description for a product row, matching
// the legacy RSS branch's fallback order (title → short_description →
// name) and (meta_description → plain-text full_description).
function _pg_product_rss_title($product)
{
    if (!empty($product['title'])) return $product['title'];
    if (!empty($product['short_description'])) return $product['short_description'];
    return isset($product['name']) ? $product['name'] : '';
}
// html_entity_decode() before the caller's h(): descriptions are authored in
// the rich-text editor and land in the DB already entity-encoded ("y&uuml;ksek").
// Escaping that directly yields "y&amp;uuml;ksek" in the feed — the reader shows
// the raw entity. Legacy does the same decode-then-escape round trip.
function _pg_product_rss_description($product)
{
    $raw = '';
    if (!empty($product['meta_description'])) {
        $raw = trim($product['meta_description']);
    } elseif (!empty($product['full_description'])) {
        $raw = trim(convert_html_to_text($product['full_description']));
    }
    if ($raw === '') return '';
    return html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

// gid => "Root > Child > Grandchild" for every group under $root_id (root
// included, and used as the first path segment). Feeds this to <g:product_type>,
// which Google Shopping expects as a ' > '-delimited category trail — legacy
// builds the same string while recursing in get_products_in_product_group().
function _pg_catalog_group_paths($root_id)
{
    $root_id = (int)$root_id;
    if ($root_id <= 0) return array();

    $rows = db_items("SELECT id, parent_id, name FROM product_groups WHERE enabled = 1");
    $by_id = array();
    $kids  = array();
    if (is_array($rows)) {
        foreach ($rows as $r) {
            $by_id[(int)$r['id']] = (string)$r['name'];
            $kids[(int)$r['parent_id']][] = (int)$r['id'];
        }
    }
    if (!isset($by_id[$root_id])) return array();

    $paths = array();
    $stack = array(array($root_id, $by_id[$root_id]));
    $guard = 5000;
    while ($stack && $guard-- > 0) {
        list($cur, $path) = array_pop($stack);
        if (isset($paths[$cur])) continue;   // cycle guard
        $paths[$cur] = $path;
        if (isset($kids[$cur])) {
            foreach ($kids[$cur] as $k) {
                if (!isset($by_id[$k])) continue;
                $stack[] = array($k, $path . ' > ' . $by_id[$k]);
            }
        }
    }
    return $paths;
}

// product_id => group path, for every product anywhere under $root_id.
// A product can belong to several groups in the same subtree; we keep the
// DEEPEST path, since the most specific category is the useful one for a
// shopping feed (an office chair variant reads "Ofis Malzemeleri > Ofis
// Sandalyesi", not just "Ofis Malzemeleri").
function _pg_catalog_product_group_paths($root_id)
{
    $paths = _pg_catalog_group_paths($root_id);
    if (!$paths) return array();

    $id_list = implode(',', array_map('intval', array_keys($paths)));
    $rows = db_items(
        "SELECT product AS pid, product_group AS gid
         FROM products_groups_xref
         WHERE product_group IN ($id_list)"
    );
    $out = array();
    if (is_array($rows)) {
        foreach ($rows as $r) {
            $pid = (int)$r['pid'];
            $gid = (int)$r['gid'];
            if (!isset($paths[$gid])) continue;
            if (!isset($out[$pid]) || substr_count($paths[$gid], '>') > substr_count($out[$pid], '>')) {
                $out[$pid] = $paths[$gid];
            }
        }
    }
    return $out;
}

// Build one Google-Shopping-namespaced RSS <item> for a product row (as
// returned by _pg_catalog_listing_rss_products / _pg_catalog_item_resolve_product).
// Field mapping mirrors the legacy get_page.php RSS branch's <item> (title,
// link, description, g:price, g:condition, g:id, g:availability, …) — see
// that file's `elseif (isset($_GET['rss'])...)` block for the original.
// $extra accepts:
//   'sale_price_cents' — offer-discounted price, emitted as <g:sale_price>
//   'product_type'     — " > "-delimited category trail for <g:product_type>
function _pg_build_product_rss_item($product, $detail_url, $extra = array())
{
    if (!is_array($extra)) $extra = array();
    $currency_code = defined('BASE_CURRENCY_CODE') ? BASE_CURRENCY_CODE : '';
    $title       = _pg_product_rss_title($product);
    $description = _pg_product_rss_description($product);
    $price_decimal = sprintf('%01.2f', ((int)$product['price']) / 100);

    // Offer-driven sale price. Only emitted when it actually undercuts the
    // sticker price, so a same-value offer doesn't produce a pointless tag.
    $sale_price = '';
    if (isset($extra['sale_price_cents'])) {
        $_sale_cents = (int)round($extra['sale_price_cents']);
        if ($_sale_cents > 0 && $_sale_cents < (int)$product['price']) {
            $sale_price = '<g:sale_price>' . sprintf('%01.2f', $_sale_cents / 100) . ' ' . h($currency_code) . '</g:sale_price>';
        }
    }
    $product_type = (isset($extra['product_type']) && $extra['product_type'] !== '')
        ? '<g:product_type>' . h($extra['product_type']) . '</g:product_type>'
        : '';

    $image_link = '';
    if (!empty($product['image_name'])) {
        $image_url = URL_SCHEME . HOSTNAME_SETTING . (defined('OUTPUT_PATH') ? OUTPUT_PATH : '/') . h(encode_url_path($product['image_name']));
        $image_link = '<g:image_link>' . $image_url . '</g:image_link>';
    }
    $availability = _pg_product_is_out_of_stock($product) ? 'out of stock' : 'in stock';
    $category = '';
    if (!empty($product['google_product_category'])) {
        $category = '<g:google_product_category>' . h(trim($product['google_product_category'])) . '</g:google_product_category>';
    }
    $gtin  = !empty($product['gtin'])  ? '<g:gtin>' . h(trim($product['gtin'])) . '</g:gtin>' : '';
    $brand = !empty($product['brand']) ? '<g:brand>' . h(trim($product['brand'])) . '</g:brand>' : '';
    $mpn   = !empty($product['mpn'])   ? '<g:mpn>' . h(trim($product['mpn'])) . '</g:mpn>' : '';
    $pubdate = !empty($product['timestamp']) ? '<pubDate>' . h(date('r', (int)$product['timestamp'])) . '</pubDate>' : '';

    return '<item>
            <title>' . h($title) . '</title>
            <link>' . h($detail_url) . '</link>
            <guid isPermaLink="true">' . h($detail_url) . '</guid>
            ' . $pubdate . '
            <description>' . h($description) . '</description>
            ' . $image_link . '
            <g:price>' . $price_decimal . ' ' . h($currency_code) . '</g:price>
            ' . $sale_price . '
            <g:condition>new</g:condition>
            <g:id>' . (int)$product['id'] . '</g:id>
            ' . $category . '
            ' . $gtin . '
            ' . $brand . '
            ' . $mpn . '
            ' . $product_type . '
            <g:availability>' . $availability . '</g:availability>
        </item>';
}

// Resolve the absolute detail-page URL for a product listed in a
// catalog_listing feed. Prefers the widget's configured detail_page_id
// (same convention _render_system_widget_catalog_listing uses for its
// "İncele" links); falls back to the listing page itself with a ?pid=
// query param when no detail page is configured.
function _pg_catalog_listing_product_url($product, $listing_page_name, $detail_page_name)
{
    $base_path = defined('OUTPUT_PATH') ? OUTPUT_PATH : '/';
    if ($detail_page_name !== '' && !empty($product['address_name'])) {
        return URL_SCHEME . HOSTNAME_SETTING . $base_path . encode_url_path($detail_page_name) . '/' . encode_url_path($product['address_name']);
    }
    return URL_SCHEME . HOSTNAME_SETTING . $base_path . encode_url_path($listing_page_name) . '?pid=' . (int)$product['id'];
}

// Build the RSS channel pieces (title/link/description/items) for a
// catalog_listing system widget's page. Returned as an array rather than a
// full <rss> document because the caller (get_page.php) needs to slot these
// into the SAME $output_channel_* variables the legacy rss branch already
// assembles into the final XML — see that file's fallback `else` block.
function _pg_build_catalog_listing_rss_parts($page_id, $page_name, $widget)
{
    $cfg = $widget['cfg'];
    $active_group_id = _pg_catalog_listing_resolve_active_group(
        $widget['product_group_id'],
        isset($_GET['page']) ? (string)$_GET['page'] : ''
    );
    $order_by_field     = isset($cfg['order_by_field']) ? (string)$cfg['order_by_field'] : 'sort_order';
    $order_by_direction = (isset($cfg['order_by_direction']) && strtoupper($cfg['order_by_direction']) === 'ASC') ? 'ASC' : 'DESC';
    $max_results = (isset($cfg['max_results']) && (int)$cfg['max_results'] > 0) ? (int)$cfg['max_results'] : 250;

    $products = _pg_catalog_listing_rss_products($active_group_id, $order_by_field, $order_by_direction, $max_results);

    $detail_page_id   = isset($cfg['detail_page_id']) ? (int)$cfg['detail_page_id'] : 0;
    $detail_page_name = ($detail_page_id > 0)
        ? (string)db_value("SELECT page_name FROM page WHERE page_id = '" . $detail_page_id . "' LIMIT 1")
        : '';

    // Channel link mirrors the URL that produced the feed, so a drilled feed
    // (/<page>/<group-slug>?rss=true) points back at that sub-category rather
    // than the catalog root.
    $base_path = defined('OUTPUT_PATH') ? OUTPUT_PATH : '/';
    $page_full = isset($_GET['page']) ? (string)$_GET['page'] : '';
    $_slash    = mb_strpos($page_full, '/', 0, 'UTF-8');
    $_segment  = ($_slash !== false) ? mb_substr($page_full, $_slash + 1, null, 'UTF-8') : '';
    $channel_link = URL_SCHEME . HOSTNAME_SETTING . $base_path . encode_url_path($page_name)
                  . ($_segment !== '' ? '/' . h($_segment) : '');

    // Channel title / description describe the ACTIVE GROUP, falling back to
    // the host page — same precedence chain the legacy catalog feed uses
    // (group title → group short_description → page title → page name;
    //  group meta_description → plain-text full_description → page name).
    $channel_title = $page_name;
    $channel_desc  = $page_name;
    if ($active_group_id > 0) {
        $_grp = db_item(
            "SELECT name, title, short_description, meta_description, full_description
             FROM product_groups WHERE id = '" . (int)$active_group_id . "' LIMIT 1"
        );
        if (is_array($_grp)) {
            if (!empty($_grp['title']))                  $channel_title = trim($_grp['title']);
            elseif (!empty($_grp['short_description']))  $channel_title = trim($_grp['short_description']);
            elseif (!empty($_grp['name']))               $channel_title = trim($_grp['name']);

            if (!empty($_grp['meta_description']))       $channel_desc = trim($_grp['meta_description']);
            elseif (!empty($_grp['full_description']))   $channel_desc = trim(convert_html_to_text($_grp['full_description']));
            else                                        $channel_desc = $channel_title;
        }
    }

    // Offer prices + category trails, resolved once for the whole feed.
    $_disc  = function_exists('get_discounted_product_prices') ? get_discounted_product_prices() : array();
    if (!is_array($_disc)) $_disc = array();
    $_paths = ($active_group_id > 0) ? _pg_catalog_product_group_paths($active_group_id) : array();

    $items = '';
    if (is_array($products)) {
        foreach ($products as $product) {
            $_pid = (int)$product['id'];
            $items .= _pg_build_product_rss_item(
                $product,
                _pg_catalog_listing_product_url($product, $page_name, $detail_page_name),
                array(
                    'sale_price_cents' => isset($_disc[$_pid]) ? $_disc[$_pid] : null,
                    'product_type'     => isset($_paths[$_pid]) ? $_paths[$_pid] : '',
                )
            );
        }
    }

    return array(
        'title'       => h($channel_title),
        'link'        => $channel_link,
        'description' => h(html_entity_decode($channel_desc, ENT_QUOTES | ENT_HTML5, 'UTF-8')),
        'items'       => $items,
    );
}

// Build the RSS channel pieces for a catalog_item_view system widget's page
// — a single-item feed for whichever product the URL currently resolves to
// (see _pg_catalog_item_resolve_product). Same return shape as
// _pg_build_catalog_listing_rss_parts.
function _pg_build_catalog_item_rss_parts($page_id, $page_name, $widget)
{
    $product = _pg_catalog_item_resolve_product($widget['product_group_id']);

    $base_path = defined('OUTPUT_PATH') ? OUTPUT_PATH : '/';
    $page_full = isset($_GET['page']) ? (string)$_GET['page'] : '';
    $slash = mb_strpos($page_full, '/', 0, 'UTF-8');
    $channel_link = URL_SCHEME . HOSTNAME_SETTING . $base_path . encode_url_path($page_name)
                  . ($slash !== false ? '/' . h(mb_substr($page_full, $slash + 1, null, 'UTF-8')) : '');

    $items = '';
    $title = $page_name;
    if (is_array($product)) {
        $_pid   = (int)$product['id'];
        $_disc  = function_exists('get_discounted_product_prices') ? get_discounted_product_prices() : array();
        if (!is_array($_disc)) $_disc = array();
        $_paths = ($widget['product_group_id'] > 0) ? _pg_catalog_product_group_paths($widget['product_group_id']) : array();
        $items  = _pg_build_product_rss_item($product, $channel_link, array(
            'sale_price_cents' => isset($_disc[$_pid]) ? $_disc[$_pid] : null,
            'product_type'     => isset($_paths[$_pid]) ? $_paths[$_pid] : '',
        ));
        $title  = _pg_product_rss_title($product);
    }

    return array(
        'title'       => h($title),
        'link'        => $channel_link,
        'description' => h($title),
        'items'       => $items,
    );
}

// ── Structured data (JSON-LD) ────────────────────────────────────────────
// Single Product JSON-LD (schema.org), field-mapped to match the legacy
// get_page_content.php STRUTURED_DATA splice (name/description/sku/brand/
// mpn/gtin/offers.price/offers.availability/…) — see that file's `case
// 'catalog detail':` block. The legacy "review"/"aggregateRating" hard-coded
// placeholder block is intentionally NOT replicated here — that was a
// stop-gap ("google require it so we set random" per its own comment) and
// shipping fabricated ratings from a NEW code path isn't something to carry
// forward by default.
function _pg_build_product_jsonld($product, $canonical_url)
{
    $currency_code = defined('BASE_CURRENCY_CODE') ? BASE_CURRENCY_CODE : '';
    $image_url = '';
    if (!empty($product['image_name'])) {
        $image_url = URL_SCHEME . HOSTNAME_SETTING . (defined('OUTPUT_PATH') ? OUTPUT_PATH : '/') . encode_url_path($product['image_name']);
    }
    $description = trim(strip_tags((string)_pg_product_rss_description($product)));
    if ($description === '') $description = (string)lang('No Description');

    $data = array(
        '@context'    => 'https://schema.org/',
        '@type'       => 'Product',
        'name'        => _pg_product_rss_title($product),
        'description' => $description,
        'sku'         => isset($product['name']) ? $product['name'] : '',
    );
    if ($image_url !== '')          $data['image'] = $image_url;
    if (!empty($product['brand']))  $data['brand'] = array('@type' => 'Brand', 'name' => $product['brand']);
    if (!empty($product['mpn']))    $data['mpn']  = $product['mpn'];
    if (!empty($product['gtin']))   $data['gtin'] = $product['gtin'];

    $data['offers'] = array(
        '@type'           => 'Offer',
        'url'             => $canonical_url,
        'priceCurrency'   => $currency_code,
        'price'           => sprintf('%01.2f', ((int)$product['price']) / 100),
        'priceValidUntil' => date('Y-m-d', strtotime('+1 year')),
        'itemCondition'   => 'https://schema.org/NewCondition',
        'availability'    => _pg_product_is_out_of_stock($product) ? 'https://schema.org/OutOfStock' : 'https://schema.org/InStock',
    );

    // Google's merchant listing facts ride on the offer (shipping cost and
    // time, return policy). Site-wide values from Settings; nothing is added
    // until the operator configures them, and nothing for digital products.
    if (pg_merchant_jsonld_configured()) {
        $merchant_shippable = isset($product['shippable'])
            ? (int) $product['shippable']
            : (int) db_value("SELECT shippable FROM products WHERE id = '" . e(isset($product['id']) ? $product['id'] : 0) . "'");

        foreach (pg_product_merchant_jsonld_parts($merchant_shippable) as $merchant_key => $merchant_value) {
            $data['offers'][$merchant_key] = $merchant_value;
        }
    }
    return $data;
}

// ItemList JSON-LD for a catalog_listing page — one ListItem per listed
// product. The legacy structured-data splice never covered listing pages
// (only 'catalog detail'); ItemList is the correct schema.org type for a
// product listing and costs nothing extra given we already have the rows.
function _pg_build_catalog_listing_jsonld($products, $listing_page_name, $detail_page_name = '')
{
    $items = array();
    $position = 1;
    foreach ($products as $product) {
        $items[] = array(
            '@type'    => 'ListItem',
            'position' => $position++,
            'url'      => _pg_catalog_listing_product_url($product, $listing_page_name, $detail_page_name),
            'name'     => _pg_product_rss_title($product),
        );
    }
    return array(
        '@context'        => 'https://schema.org/',
        '@type'           => 'ItemList',
        'itemListElement' => $items,
    );
}
