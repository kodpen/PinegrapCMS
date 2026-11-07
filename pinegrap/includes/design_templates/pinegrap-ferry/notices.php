<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Ferry", its travel notices: the staff page that adds a
 * notice (the Travel Notices form, a collection, with the sample notices
 * written in when it is made), the list of every notice with its route and
 * kind filters, the notice's own page and the list the home page places.
 * Included after parts.php in the same scope; returns
 * array('pages' => ..., 'widgets' => ...).
 *
 * A notice is a record of the Travel Notices form. Route and kind are pick
 * lists whose answers are the words parts.php gives, so a link with
 * ?route= or ?kind= (the footer, the route pages, the filter bar) filters
 * the list by the words a visitor sees. When is words: the line writes
 * "this afternoon" or "weekdays for three weeks", never a fixed date the
 * sample would carry. Featured is the line's choice (office use only): the
 * home page shows the featured notices. The pieces defined here
 * ($fy_filter, $fy_featured_choices, $fy_page_link, $fy_filter_bar) serve
 * the bookings too.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$notice_pages = array();

// ── Pieces ──────────────────────────────────────────────────────────────

// A filter row of a list's settings: the field, how it compares and the
// value, or where the value comes from (a URL parameter, left out while
// the parameter is not in the address).
$fy_filter = function ($field, $operator, $value, $param = '') {
    $row = array('field' => $field, 'operator' => $operator, 'value' => $value, 'dynamic_value' => $param !== '' ? 'url parameter' : '');
    if ($param !== '') $row['dynamic_value_attribute'] = $param;
    return $row;
};
// The answers of the Featured list: the first marks a record for the home.
$fy_featured_choices = array(lang('Yes'), lang('No'));

// A link to a template page that sends values of the record it is drawn
// in: $params maps the address name to the field it takes (the renderer's
// __page_link).
$fy_page_link = function ($page_key, $params) {
    $list = array();
    foreach ($params as $name => $token) $list[] = array('name' => $name, 'token' => $token);
    return array(
        '_bindings'   => array('href' => '__page_link'),
        '_bindParams' => array('href' => array('page_id' => '{{tab:' . $page_key . '}}', 'params' => $list)),
    );
};

// The links above a list that narrow it: one per answer of each pick
// list, each opening the list page $page_key with the filter set.
$fy_filter_bar = function ($page_key, $groups) use ($el, $link, $span, $query) {
    $navs = array();
    foreach ($groups as $g) {
        $children = array($span($g['label'], 'small fw-semibold text-body-secondary me-1', lang('Label')));
        $children[] = $link(lang('All'), '{{page:' . $page_key . '}}', 'btn btn-sm btn-outline-secondary rounded-pill');
        foreach ($g['values'] as $v) {
            $children[] = $link($v, '{{page:' . $page_key . '}}?' . $query(array($g['param'] => $v)), 'btn btn-sm btn-outline-secondary rounded-pill');
        }
        $navs[] = $el('div', 'd-flex flex-wrap align-items-center gap-2 mb-2', $g['label'], $children);
    }
    return $el('nav', 'mb-4', lang('Filters'), $navs);
};

$notice_widgets = array();

return array('pages' => $notice_pages, 'widgets' => $notice_widgets);
