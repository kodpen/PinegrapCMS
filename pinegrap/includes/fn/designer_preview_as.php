<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Visual Page Editor - the Preview's case model. The Preview can draw a page
 * with a chosen record, a chosen visitor and a chosen case (typical, empty,
 * long, sold out, error). This module knows, per system widget kind
 * (pg_sw_widget_kind()), which cases the kind has and what a case sets for
 * one widget: which record it draws, as which visitor, and the flags its
 * renderer reads. It is pure: no query, no session, no output; the server
 * turns a setup into a render and the editor lists the choices.
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
 * Every case, in the order the chooser lists them and "Check all cases"
 * draws them; 'typical' is the Preview as it is without a choice.
 *
 * @return string[]
 */
function pg_preview_as_cases()
{
    return array('typical', 'empty', 'long', 'sold_out', 'error');
}

/**
 * Every visitor, in the chooser's order; 'signed_out' is the Preview as it
 * is without a choice.
 *
 * @return string[]
 */
function pg_preview_as_visitors()
{
    return array('signed_out', 'signed_in', 'member', 'first_visit', 'returning');
}

/**
 * The kinds grouped by what their cases are. A list draws records (none,
 * the longest), a record widget draws one, a cart draws the visitor's
 * lines, a form can be sent with a required field missing.
 *
 * @return array group => kinds
 */
function _pg_preview_as_kind_groups()
{
    return array(
        'list' => array(
            'form_list_view', 'submitted_forms_list', 'form_list', 'blog_list',
            'calendar_view', 'photo_gallery', 'folder_view', 'search_results',
            'comments', 'comments_recent',
        ),
        'catalog_list' => array('catalog_listing'),
        'record'       => array('form_item_view', 'calendar_event_view'),
        'product'      => array('catalog_item_view'),
        'cart'         => array('shopping_cart', 'order_preview', 'express_order'),
        'form'         => array(
            'custom_form', 'login_form', 'registration', 'forgot_password',
            'affiliate_sign_up_form', 'billing_information',
            'shipping_address_and_arrival', 'order_form',
        ),
    );
}

/**
 * The cases of widget kind $kind, in pg_preview_as_cases() order; a kind
 * with no case of its own has 'typical' alone.
 *
 * @param string $kind pg_sw_widget_kind()
 * @return string[]
 */
function pg_preview_as_kind_cases($kind)
{
    $cases = array(
        'list'         => array('typical', 'empty', 'long'),
        'catalog_list' => array('typical', 'empty', 'long', 'sold_out'),
        'record'       => array('typical', 'long'),
        'product'      => array('typical', 'long', 'sold_out'),
        'cart'         => array('typical', 'empty', 'long'),
        'form'         => array('typical', 'error'),
    );
    if (is_string($kind) && $kind !== '') {
        foreach (_pg_preview_as_kind_groups() as $group => $kinds) {
            if (in_array($kind, $kinds, true)) return $cases[$group];
        }
    }
    return array('typical');
}

/**
 * Where the record chooser of a single-record widget searches: 'form' (the
 * form's submissions), 'product' (the group's products), 'event' (the
 * calendar's events), or '' for a kind that draws no chosen record.
 *
 * @param string $kind pg_sw_widget_kind()
 * @return string
 */
function pg_preview_as_record_source($kind)
{
    $sources = array(
        'form_item_view'      => 'form',
        'catalog_item_view'   => 'product',
        'calendar_event_view' => 'event',
    );
    return (is_string($kind) && isset($sources[$kind])) ? $sources[$kind] : '';
}

/**
 * The cases a page offers: every case of one of its widgets, in
 * pg_preview_as_cases() order, 'typical' always first.
 *
 * @param string[] $kinds the kinds of the page's widgets
 * @return string[]
 */
function pg_preview_as_page_cases($kinds)
{
    $have = array('typical' => true);
    if (is_array($kinds)) {
        foreach ($kinds as $kind) {
            foreach (pg_preview_as_kind_cases($kind) as $case) $have[$case] = true;
        }
    }
    $out = array();
    foreach (pg_preview_as_cases() as $case) {
        if (isset($have[$case])) $out[] = $case;
    }
    return $out;
}

/**
 * The visitors a page offers: signed out and signed in always, a member of
 * a group where the page has access rules, a first or returning visit
 * where it has audience conditions.
 *
 * @param array $page {access_rules: bool, audience: bool}
 * @return string[]
 */
function pg_preview_as_page_visitors($page)
{
    $page = is_array($page) ? $page : array();
    $out  = array('signed_out', 'signed_in');
    if (!empty($page['access_rules'])) $out[] = 'member';
    if (!empty($page['audience'])) {
        $out[] = 'first_visit';
        $out[] = 'returning';
    }
    return $out;
}

/**
 * A whole positive number from the browser, or 0.
 *
 * @param mixed $value
 * @return int
 */
function _pg_preview_as_id($value)
{
    if (is_int($value)) return max(0, $value);
    if (is_string($value) && $value !== '' && ctype_digit($value) && strlen($value) <= 18) return (int)$value;
    return 0;
}

/**
 * The page's choice as the browser keeps it, read back against what the
 * page offers: a record only on a page with a single-record widget, a
 * visitor and a case only when the page offers them; anything else falls
 * back to the Preview without a choice.
 *
 * @param mixed    $input {record, visitor, case}
 * @param string[] $kinds the kinds of the page's widgets
 * @param array    $page  {access_rules, audience}
 * @return array {record: int, visitor: string, case: string}
 */
function pg_preview_as_choice($input, $kinds, $page)
{
    $input = is_array($input) ? $input : array();
    $kinds = is_array($kinds) ? $kinds : array();

    $has_record = false;
    foreach ($kinds as $kind) {
        if (pg_preview_as_record_source($kind) !== '') {
            $has_record = true;
            break;
        }
    }

    $record  = $has_record ? _pg_preview_as_id(isset($input['record']) ? $input['record'] : 0) : 0;
    $visitor = isset($input['visitor']) ? $input['visitor'] : '';
    $case    = isset($input['case']) ? $input['case'] : '';

    return array(
        'record'  => $record,
        'visitor' => (is_string($visitor) && in_array($visitor, pg_preview_as_page_visitors($page), true)) ? $visitor : 'signed_out',
        'case'    => (is_string($case) && in_array($case, pg_preview_as_page_cases($kinds), true)) ? $case : 'typical',
    );
}

/**
 * What the page's choice sets for one widget of kind $kind. A case the
 * kind does not have draws the widget as on a typical visit.
 *
 * record: null for a kind with no chosen record, else {source, pick, id}:
 *   pick 'default' (what the Preview draws without a choice: the newest
 *   submission, the first product, the next event), 'chosen' (id), 'longest'
 *   (the record with the longest title and text) or 'sold_out' (a product
 *   out of stock); id is set for 'chosen' only.
 * visitor: one of pg_preview_as_visitors().
 * flags: what the renderer reads: 'empty' (no record, an empty cart, a
 *   search with no result), 'long' (the longest records), 'sold_out' (out
 *   of stock), 'form_error' (sent with a required field missing).
 *
 * @param string $kind   pg_sw_widget_kind()
 * @param mixed  $choice pg_preview_as_choice()
 * @return array {case, record, visitor, flags}
 */
function pg_preview_as_setup($kind, $choice)
{
    $choice  = is_array($choice) ? $choice : array();
    $case    = isset($choice['case']) ? $choice['case'] : '';
    $visitor = isset($choice['visitor']) ? $choice['visitor'] : '';
    if (!is_string($case) || !in_array($case, pg_preview_as_kind_cases($kind), true)) $case = 'typical';
    if (!is_string($visitor) || !in_array($visitor, pg_preview_as_visitors(), true)) $visitor = 'signed_out';

    $flags = array();
    if ($case === 'empty') $flags[] = 'empty';
    if ($case === 'long') $flags[] = 'long';
    if ($case === 'sold_out') $flags[] = 'sold_out';
    if ($case === 'error') $flags[] = 'form_error';

    $record = null;
    $source = pg_preview_as_record_source($kind);
    if ($source !== '') {
        $id = _pg_preview_as_id(isset($choice['record']) ? $choice['record'] : 0);
        if ($case === 'long') {
            $record = array('source' => $source, 'pick' => 'longest', 'id' => 0);
        } elseif ($case === 'sold_out') {
            $record = array('source' => $source, 'pick' => 'sold_out', 'id' => 0);
        } elseif ($id > 0) {
            $record = array('source' => $source, 'pick' => 'chosen', 'id' => $id);
        } else {
            $record = array('source' => $source, 'pick' => 'default', 'id' => 0);
        }
    }

    return array('case' => $case, 'record' => $record, 'visitor' => $visitor, 'flags' => $flags);
}
