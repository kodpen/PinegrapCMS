<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Comment rules shared by the comment handlers: which thread a comment posted
 * to a page may join.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A module of functions.php: comments.
//
// A thread is (page_id, item_type, item_id): the page the comment was posted
// to, and the item on it the comment is about - '' and 0 for the page itself,
// 'product', 'product_group', 'calendar_event' or 'submitted_form' with that
// item's id. The comment form carries all three as hidden fields, so a post
// can name any thread; the page's comment settings are read by the posted
// page_id, and the item is what the post claims. pg_comment_thread_verify()
// works out again which thread the post may join before add_comment.php
// stores anything.
if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * Which thread a comment posted to a page may join. Pure: the caller reads
 * the page and its record view widget (pg_comment_thread_verify()).
 *
 * - A legacy form item view page (page type 'form item view') takes only a
 *   submitted form; its form_item_view_pages row decides who may see which.
 *   A record view widget on such a page is not asked, as get_page_content.php
 *   does not ask it either.
 * - Any other page that carries a form_item_view widget shows the comments of
 *   the record that widget shows, so a post joins that record or nothing:
 *   item type 'submitted_form', and an id the widget resolves from its own
 *   custom form and lets this visitor see (access setting and record
 *   filters). The page's own thread ('' and 0), a product or another form's
 *   record is refused; it would be stored where the page never shows it.
 * - Any other page takes the posted item as it is, except a submitted form.
 *   Whether a product or calendar event id is one the page shows is not
 *   looked up here.
 *
 * $record_id is the id of the record the widget resolved for the posted id
 * (0 when it found none), $record_visible whether the visitor may see it.
 *
 * Returns array('accept' => bool, 'refusal' => string, 'item_type',
 * 'item_id'). 'refusal' is '' when accepted, 'item_type' for a type the
 * record view page does not take, 'record' for a record it does not show to
 * this visitor, 'page_type' for an item type a page without a record view
 * widget does not take. An accepted record view post carries the record's
 * id; any other accepted post carries the posted values unchanged.
 *
 * @param mixed      $page_type
 * @param bool       $has_record_widget
 * @param mixed      $item_type
 * @param mixed      $item_id
 * @param int|string $record_id
 * @param bool       $record_visible
 * @return array
 */
function pg_comment_thread_decision($page_type, $has_record_widget, $item_type, $item_id, $record_id = 0, $record_visible = false)
{
    $page_type = is_scalar($page_type) ? (string) $page_type : '';
    $type = is_scalar($item_type) ? (string) $item_type : '';

    $out = array(
        'accept'    => false,
        'refusal'   => '',
        'item_type' => $item_type,
        'item_id'   => $item_id,
    );

    if ($page_type === 'form item view') {
        if ($type !== 'submitted_form') {
            $out['refusal'] = 'page_type';
            return $out;
        }

        $out['accept'] = true;
        return $out;
    }

    if ($has_record_widget) {
        if ($type !== 'submitted_form') {
            $out['refusal'] = 'item_type';
            return $out;
        }

        $id = is_scalar($item_id) ? (int) $item_id : 0;
        $record_id = is_scalar($record_id) ? (int) $record_id : 0;

        if (($id <= 0) || ($record_id !== $id) || !$record_visible) {
            $out['refusal'] = 'record';
            return $out;
        }

        $out['accept'] = true;
        $out['item_type'] = 'submitted_form';
        $out['item_id'] = $record_id;
        return $out;
    }

    if ($type === 'submitted_form') {
        $out['refusal'] = 'page_type';
        return $out;
    }

    $out['accept'] = true;
    return $out;
}

/**
 * The thread a comment posted to $page_id may join: pg_comment_thread_decision()
 * over the page's record view widget.
 *
 * A page other than a legacy form item view is asked for its form_item_view
 * widget (pg_sw_record_comment_context()), with the posted id: the widget
 * looks the record up in its own custom form only and applies its access
 * setting and record filters, with no e-mail exception.
 *
 * Returns the decision plus 'record': the widget's context (null when the
 * page carries no such widget). add_comment.php treats a page with an
 * accepted record as a form item view page.
 *
 * @param int|string $page_id
 * @param mixed      $page_type the page row's page_type
 * @param mixed      $item_type posted
 * @param mixed      $item_id   posted
 * @return array
 */
function pg_comment_thread_verify($page_id, $page_type, $item_type, $item_id)
{
    $record = null;

    if (!is_scalar($page_type) || ((string) $page_type !== 'form item view')) {
        $record = pg_sw_record_comment_context((int) $page_id, is_scalar($item_id) ? (int) $item_id : 0);
    }

    $record_id = 0;

    if (($record !== null) && is_array($record['form']) && !empty($record['form']['id'])) {
        $record_id = (int) $record['form']['id'];
    }

    $decision = pg_comment_thread_decision(
        $page_type,
        $record !== null,
        $item_type,
        $item_id,
        $record_id,
        ($record !== null) && !empty($record['visible'])
    );

    $decision['record'] = $record;

    return $decision;
}
