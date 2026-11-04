<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The comment writer of a content import (includes/fn/importers.php): a
 * comment of an export joins the thread of the record or page its post
 * became.
 *
 * - On a record it is written on the form's record page (its first form
 *   item view page), as the comments widget and add_comment.php write a
 *   record's comments; on a page, on the page itself.
 * - parent names the comment it answers by source key, so threads are kept
 *   once comments.parent_id is there; a parent not imported reads as none.
 * - A held comment is written unpublished, for the moderation screen.
 * - The commenter's name is kept, the address is not: comments carry no
 *   address column, and a commenter is never tied to a member account.
 *
 * The item's data: post (the post's source key), post_kind, parent, name,
 * email, message, published, time.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/import/write_record.php');

/**
 * A comment's text as the comments table keeps it: plain text, paragraphs
 * as blank lines. Pure.
 *
 * @param string $html
 * @return string
 */
function pg_import_comment_text($html)
{
    return pg_import_record_plain_text($html);
}

/**
 * Why a comment cannot be written on what its post became, null when it
 * can. Pure.
 *
 * @param array $data      the item's data
 * @param int   $target_id the record or page the post became, 0 when none
 * @return array|null a writer answer
 */
function pg_import_comment_target_problem($data, $target_id)
{
    if (!in_array((string) ($data['post_kind'] ?? ''), array('record', 'page'), true)) {
        return array('result' => 'skipped', 'id' => 0, 'reason' => lang('Only comments on posts and pages are imported.'));
    }

    if ((int) $target_id <= 0) {
        return array('result' => 'skipped', 'id' => 0, 'reason' => lang('The post of this comment was not imported.'));
    }

    return null;
}

/**
 * @param array $item
 * @param array $import pg_import_get()
 * @return array{result:string,id:int,reason:string}
 */
function pg_import_write_comment($item, $import)
{
    $data = (array) ($item['data'] ?? array());
    $related = pg_import_related_ids($import);
    $post_kind = (string) ($data['post_kind'] ?? '');
    $target_id = in_array($post_kind, array('record', 'page'), true) ? pg_import_item_id($related, $post_kind, (string) ($data['post'] ?? '')) : 0;
    $problem = pg_import_comment_target_problem($data, $target_id);

    if ($problem !== null) {
        return $problem;
    }

    if ($post_kind === 'record') {
        $form_page_id = (int) db_value("SELECT page_id FROM forms WHERE id = '" . $target_id . "'");
        $page_id = ($form_page_id > 0) ? (int) db_value("SELECT page_id FROM form_item_view_pages
            WHERE custom_form_page_id = '" . $form_page_id . "' AND collection = 'a'
            ORDER BY page_id LIMIT 1") : 0;
        $item_type = 'submitted_form';
        $item_id = $target_id;

        if ($page_id <= 0) {
            return array('result' => 'skipped', 'id' => 0, 'reason' => lang('The form has no page that shows its records, so its comments have nowhere to go.'));
        }
    } else {
        $page_id = $target_id;
        $item_type = '';
        $item_id = 0;
    }

    $message = pg_import_comment_text((string) ($data['message'] ?? ''));

    if ($message === '') {
        return array('result' => 'skipped', 'id' => 0, 'reason' => lang('The comment is empty.'));
    }

    $published = !empty($data['published']) ? '1' : '0';
    $time = (int) ($data['time'] ?? 0);
    $time = ($time > 0) ? $time : time();
    $editor_id = (int) ($import['user_id'] ?? 0);
    $columns = 'page_id, item_id, item_type, name, message, published, created_user_id, created_timestamp, last_modified_user_id';
    $values = array((string) $page_id, (string) $item_id, $item_type, mb_substr(trim((string) ($data['name'] ?? '')), 0, 100), $message, $published, '0', (string) $time, (string) $editor_id);

    if (pg_comment_replies_ready()) {
        $parent_key = (string) ($data['parent'] ?? '');
        $columns .= ', parent_id';
        $values[] = (string) (($parent_key !== '') ? pg_import_item_id($related, 'comment', $parent_key) : 0);
    }

    $placeholders = implode(', ', array_fill(0, count($values), '?'));
    $saved = db_q("INSERT INTO comments (" . $columns . ", last_modified_timestamp) VALUES (" . $placeholders . ", UNIX_TIMESTAMP())", $values);
    $comment_id = $saved ? (int) mysqli_insert_id(db::$con) : 0;

    if ($comment_id <= 0) {
        return array('result' => 'failed', 'id' => 0, 'reason' => lang('The comment could not be saved.'));
    }

    if ($published === '1') {
        pg_comment_refresh_record_info($page_id, $item_type, $item_id);
    }

    if (function_exists('pg_page_cache_mark_dirty')) {
        pg_page_cache_mark_dirty();
    }

    return array('result' => 'made', 'id' => $comment_id, 'reason' => '');
}

/**
 * Removes an imported comment as the comment screen's delete does: the row,
 * its notifications and the record's comment counters. Replies are removed
 * before it (undo runs newest first); one written since by a visitor reads
 * as top-level, as after any delete.
 *
 * @param int $comment_id
 * @return true
 */
function pg_import_unwrite_comment($comment_id)
{
    $comment_id = (int) $comment_id;
    $row = db_item("SELECT page_id, item_type, item_id FROM comments WHERE id = '" . $comment_id . "'");

    if (!is_array($row)) {
        return true;
    }

    db_q("DELETE FROM comments WHERE id = ?", array((string) $comment_id));
    db_q("DELETE FROM notifications WHERE comment_id = ?", array((string) $comment_id));
    pg_comment_refresh_record_info($row['page_id'], $row['item_type'], $row['item_id']);

    if (function_exists('pg_page_cache_mark_dirty')) {
        pg_page_cache_mark_dirty();
    }

    return true;
}
