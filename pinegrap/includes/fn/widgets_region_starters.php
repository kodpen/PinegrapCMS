<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The starter layouts of the Menu, Menu Sequence, Ad Region and Tag Cloud
 * widgets on the server: the widget the bulk move to the visual designer
 * makes from a classic region node starts with the layout the editor's
 * "Make designable" gives it (_buildStarterTree()). Site output: Bootstrap 5.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/designer_import.php');

/**
 * The starter layout of a widget made from a region node, twin of the
 * menu_region, menu_sequence_region, ad_region and tag_cloud_region cases
 * of _buildStarterTree(). The texts are in the language of the user making
 * it, as the editor writes them.
 *
 * @param string $kind
 * @return array|null  null for any other kind
 */
function pg_ltd_widget_tree($kind)
{
    $el = function ($props, $children = array()) {
        return pg_di_mk('semantic', $props, $children);
    };
    $attr = function ($name, $value) {
        return array('name' => $name, 'value' => $value);
    };
    $suffix = substr(base_convert((string)mt_rand(1679616, 60466175), 10, 36), 0, 5);

    switch ((string)$kind) {
        case 'menu_region':
            $id = 'menu_' . $suffix;
            $when = function ($op) {
                return array('_bindings' => array('show_if' => '__menu_item_has_children'), '_bindParams' => array('show_if' => array('op' => $op)));
            };
            $link = function ($cls, $extra = array()) use ($el) {
                return $el(array_merge(array('tag' => 'a', 'cssClass' => $cls, 'text' => lang('Menu item'), 'href' => '#', 'customName' => lang('Link'),
                    '_bindings' => array('href' => '__menu_item_url', 'text' => '__menu_item_name')), $extra));
            };
            $loop = pg_di_mk('loop_area', array(), array(
                $el(array('tag' => 'li', 'cssClass' => 'nav-item', 'customName' => lang('Menu Item')), array(
                    $link('nav-link', $when('empty')),
                    $el(array_merge(array('tag' => 'div', 'cssClass' => 'dropdown', 'customName' => lang('Sub-items')), $when('not_empty')), array(
                        $el(array('tag' => 'a', 'cssClass' => 'nav-link dropdown-toggle', 'text' => lang('Menu item'), 'href' => '#', 'customName' => lang('Toggle'),
                            '_attrs' => array($attr('role', 'button'), $attr('data-bs-toggle', 'dropdown'), $attr('aria-expanded', 'false')),
                            '_bindings' => array('text' => '__menu_item_name'))),
                        $el(array('tag' => 'ul', 'cssClass' => 'dropdown-menu', 'customName' => lang('Sub-items of a menu item')), array(
                            pg_di_mk('loop_area', array('source' => 'menu.children'), array(
                                $el(array('tag' => 'li', 'cssClass' => '', 'customName' => lang('Sub-item')), array($link('dropdown-item'))),
                            )),
                        )),
                    )),
                )),
            ));
            $tree = pg_di_root(array(
                $el(array('tag' => 'nav', 'cssClass' => 'navbar navbar-expand-lg', 'customName' => lang('Menu'),
                    '_attrs' => array($attr('aria-label', lang('Menu'))), '_bindings' => array('eo_visible_if' => 'has_menu_items')), array(
                    $el(array('tag' => 'div', 'cssClass' => 'container-fluid px-0', 'customName' => lang('Container')), array(
                        $el(array('tag' => 'button', 'cssClass' => 'navbar-toggler', 'customName' => lang('Menu Button'),
                            '_attrs' => array($attr('type', 'button'), $attr('data-bs-toggle', 'collapse'), $attr('data-bs-target', '#' . $id),
                                $attr('aria-controls', $id), $attr('aria-expanded', 'false'), $attr('aria-label', lang('Open the menu')))), array(
                            $el(array('tag' => 'span', 'cssClass' => 'navbar-toggler-icon', 'customName' => lang('Icon'))),
                        )),
                        $el(array('tag' => 'div', 'cssClass' => 'collapse navbar-collapse', 'id' => $id, 'customName' => lang('Menu Items')), array(
                            $el(array('tag' => 'ul', 'cssClass' => 'navbar-nav', 'smartActive' => true, 'customName' => lang('Top Level')), array($loop)),
                        )),
                    )),
                )),
            ));
            break;

        case 'menu_sequence_region':
            $icon = function ($cls) use ($el, $attr) {
                return $el(array('tag' => 'i', 'cssClass' => $cls, 'customName' => lang('Icon'), '_attrs' => array($attr('aria-hidden', 'true'))));
            };
            $tree = pg_di_root(array(
                $el(array('tag' => 'nav', 'cssClass' => 'd-flex justify-content-between align-items-center gap-3 my-4', 'customName' => lang('Menu Sequence'),
                    '_attrs' => array($attr('aria-label', lang('Previous and next page'))), '_bindings' => array('eo_visible_if' => 'in_sequence')), array(
                    $el(array('tag' => 'a', 'cssClass' => 'btn btn-outline-secondary d-inline-flex align-items-center gap-2', 'href' => '#', 'customName' => lang('Previous'),
                        '_attrs' => array($attr('rel', 'prev')), '_bindings' => array('href' => '__previous_url', 'eo_visible_if' => 'has_previous')), array(
                        $icon('bi bi-arrow-left'),
                        $el(array('tag' => 'span', 'text' => lang('Previous page name'), 'customName' => lang('Label'), '_bindings' => array('text' => '__previous_name'))),
                    )),
                    $el(array('tag' => 'a', 'cssClass' => 'btn btn-outline-secondary d-inline-flex align-items-center gap-2 ms-auto', 'href' => '#', 'customName' => lang('Next'),
                        '_attrs' => array($attr('rel', 'next')), '_bindings' => array('href' => '__next_url')), array(
                        $el(array('tag' => 'span', 'text' => lang('Next page name'), 'customName' => lang('Label'), '_bindings' => array('text' => '__next_name'))),
                        $icon('bi bi-arrow-right'),
                    )),
                )),
                pg_di_mk('loop_area', array(), array()),
            ));
            break;

        case 'ad_region':
            $id = 'ads_' . $suffix;
            $control = function ($dir, $label) use ($el, $attr, $id) {
                return $el(array('tag' => 'button', 'cssClass' => 'carousel-control-' . $dir, 'customName' => $label,
                    '_attrs' => array($attr('type', 'button'), $attr('data-bs-target', '#' . $id), $attr('data-bs-slide', $dir)),
                    '_bindings' => array('eo_visible_if' => 'has_several_ads')), array(
                    $el(array('tag' => 'span', 'cssClass' => 'carousel-control-' . $dir . '-icon', 'customName' => lang('Icon'), '_attrs' => array($attr('aria-hidden', 'true')))),
                    $el(array('tag' => 'span', 'cssClass' => 'visually-hidden', 'text' => $label, 'customName' => lang('Label'))),
                ));
            };
            $tree = pg_di_root(array(
                $el(array('tag' => 'div', 'cssClass' => 'carousel slide', 'id' => $id, 'customName' => lang('Ad Region'),
                    '_attrs' => array($attr('data-bs-ride', '{{__ad_ride}}'), $attr('data-bs-interval', '{{__ad_interval}}'), $attr('data-bs-wrap', '{{__ad_wrap}}')),
                    '_bindings' => array('eo_visible_if' => 'has_ads')), array(
                    $el(array('tag' => 'div', 'cssClass' => 'carousel-inner', 'customName' => lang('Ads')), array(
                        pg_di_mk('loop_area', array(), array(
                            $el(array('tag' => 'div', 'cssClass' => 'carousel-item', 'customName' => lang('Ad')), array(
                                $el(array('tag' => 'div', 'cssClass' => '', 'text' => lang('Ad content (HTML)'), 'customName' => lang('Ad Content'),
                                    '_bindings' => array('text' => '__ad_content_html'))),
                                $el(array('tag' => 'div', 'cssClass' => 'carousel-caption d-none d-md-block', 'text' => lang('Ad caption (HTML)'), 'customName' => lang('Caption'),
                                    '_bindings' => array('text' => '__ad_caption_html', 'eo_visible_if' => 'ad_has_caption'))),
                            )),
                        )),
                    )),
                    $control('prev', lang('Previous')),
                    $control('next', lang('Next')),
                )),
            ));
            break;

        case 'tag_cloud_region':
            $links = array();
            foreach (array('fs-6', 'fs-5', 'fs-4', 'fs-3', 'fs-2') as $i => $cls) {
                $links[] = $el(array('tag' => 'a', 'cssClass' => $cls . ' text-decoration-none', 'text' => lang('Keyword'), 'href' => '#',
                    'customName' => str_replace('{var}', (string)($i + 1), lang('Weight {var}')),
                    '_bindings' => array('href' => '__tag_url', 'text' => '__tag_name', 'show_if' => '__tag_weight'),
                    '_bindParams' => array('show_if' => array('op' => 'eq', 'value' => (string)($i + 1)))));
            }
            $tree = pg_di_root(array(
                $el(array('tag' => 'nav', 'cssClass' => 'd-flex flex-wrap align-items-baseline column-gap-3 row-gap-1', 'customName' => lang('Tag Cloud'),
                    '_attrs' => array($attr('aria-label', lang('Tag Cloud'))), '_bindings' => array('eo_visible_if' => 'has_tags')), array(
                    pg_di_mk('loop_area', array(), array(
                        $el(array('tag' => 'span', 'cssClass' => '', 'customName' => lang('Keyword')), $links),
                    )),
                )),
            ));
            break;

        default:
            return null;
    }
    $seq = 0;
    pg_di_assign_ids($tree, $seq);
    return $tree;
}
