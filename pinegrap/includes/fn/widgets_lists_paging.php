<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A module of functions.php: the page links of a list widget as a list
// (widgets_lists.php), drawn in the form list, catalog listing and search
// results widgets.
//
// Loaded by functions.php through require_once, never on its own.
//
// Each widget draws its pagination itself (pg_sw_pagination_html()); a
// design that wants its own look puts a list of the page links in the
// widget instead, one row per link, and the built-in pagination is left
// out. The list is keyed by ^^__page_number^^, the page shown, which the
// widget fills in its surround and in every record: one set of rows, read
// wherever the list stands. The rows are built here, so their shape is
// tested without a database.
if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The paging source, in the shape of pg_sw_list_sources(). The number
// comes first: the A to Z order compares a row's first value.
function pg_sw_list_paging_sources()
{
    return array(
        'paging.pages' => array(
            'family'       => 'paging',
            'key'          => '__page_number',
            'label'        => lang('Page links'),
            'arg'          => '',
            'arg_required' => false,
            'tokens'       => array(
                array('__pager_number', lang('Page number (empty for a gap)')),
                array('__pager_url', lang('Page address (empty for a gap and the page shown)')),
                array('__pager_is_current', lang('The page shown (1 or empty)')),
                array('__pager_is_gap', lang('A gap between page numbers (1 or empty)')),
            ),
        ),
    );
}

// The page links of a list showing page $current of $pages under
// $page_param, in the shape the list pass reads: array('paging.pages' =>
// array($current => rows)). The same links as pg_sw_pagination_html():
// the first page, a gap, two pages either side of the current one, a gap,
// the last page; previous and next are the widget's own tokens
// (pg_sw_list_paging()). No rows for a single page, as that draws no
// pagination. Addresses as pg_sw_pagination_html() builds them, the other
// query values kept; none for the page shown, which the built-in
// pagination does not link either, so a condition on the address tells a
// link from the current page and a gap. Raw values, escaped once by the
// list pass.
function pg_sw_list_paging_rows($page_param, $current, $pages)
{
    $current = (int)$current;
    $pages   = (int)$pages;
    if ($pages <= 1) return array('paging.pages' => array());
    $current = max(1, min($pages, $current));

    $link = function ($n) use ($page_param, $current) {
        return array(
            '__pager_number'     => (string)$n,
            '__pager_url'        => ($n === $current) ? '' : '?' . _pg_sw_build_query($page_param, $n),
            '__pager_is_current' => ($n === $current) ? '1' : '',
            '__pager_is_gap'     => '',
        );
    };
    $gap = array('__pager_number' => '', '__pager_url' => '', '__pager_is_current' => '', '__pager_is_gap' => '1');

    $window = 2;
    $from = max(1, $current - $window);
    $to   = min($pages, $current + $window);
    $rows = array();
    if ($from > 1) {
        $rows[] = $link(1);
        if ($from > 2) $rows[] = $gap;
    }
    for ($i = $from; $i <= $to; $i++) $rows[] = $link($i);
    if ($to < $pages) {
        if ($to < $pages - 1) $rows[] = $gap;
        $rows[] = $link($pages);
    }
    return array('paging.pages' => array($current => $rows));
}
