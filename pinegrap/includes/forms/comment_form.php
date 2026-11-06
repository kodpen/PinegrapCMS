<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The comment edit form's checks and save, shared by the edit screen
 * (edit_comment.php) and the Comments list's quick edit
 * (includes/fn/comment_list_edit.php): the publish choice, the message and
 * schedule checks, the UPDATE, and what a newly published comment sets off
 * (the record's comment info and the two comment e-mails).
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
 * A comment's publish choice as the edit screen's select opens it:
 * 'published', 'schedule' (a publish date is stored) or 'not_published'.
 * Pure.
 *
 * @param array $comment comments row (published, publish_date_and_time)
 * @return string
 */
function pg_comment_form_publish_state(array $comment)
{
    if ((string) ($comment['published'] ?? '') === '1') {
        return 'published';
    }

    $when = (string) ($comment['publish_date_and_time'] ?? '');

    if (($when !== '') && ($when !== '0000-00-00 00:00:00')) {
        return 'schedule';
    }

    return 'not_published';
}

/**
 * The edit screen's checks: a message is required; a scheduled comment
 * needs a valid date and time in the future. Errors are marked on the form.
 *
 * @param liveform $liveform
 */
function pg_comment_form_validate($liveform)
{
    $liveform->validate_required_field('message', lang('A comment is required.'));

    if ($liveform->get_field_value('publish') != 'schedule') {
        return;
    }

    $liveform->validate_required_field('publish_date_and_time', lang('Please select the date & time when you want the comment to be published.'));

    if (
        ($liveform->check_field_error('publish_date_and_time') == false)
        && (validate_date_and_time($liveform->get_field_value('publish_date_and_time')) == false)
    ) {
        $liveform->mark_error('publish_date_and_time', lang('Please enter a valid date & time when you want the comment to be published.'));
    }

    if (
        ($liveform->check_field_error('publish_date_and_time') == false)
        && (prepare_form_data_for_input($liveform->get_field_value('publish_date_and_time'), 'date and time') < date('Y-m-d H:i'))
    ) {
        $liveform->mark_error('publish_date_and_time', lang('Sorry, the date & time you entered is in the past. Please enter a future date & time.'));
    }
}

/**
 * The publish columns a save writes from the form's publish choice: a
 * published comment has no schedule, a scheduled one keeps its date and
 * whether a newer comment cancels it, an unpublished one neither.
 *
 * @param liveform $liveform
 * @return array published, publish_date_and_time, publish_cancel
 */
function pg_comment_form_publish_columns($liveform)
{
    $columns = array('published' => '', 'publish_date_and_time' => '', 'publish_cancel' => '');

    switch ($liveform->get_field_value('publish')) {
        case 'published':
            $columns['published'] = '1';
            break;

        case 'schedule':
            $columns['publish_date_and_time'] = (string) prepare_form_data_for_input($liveform->get_field_value('publish_date_and_time'), 'date and time');

            if ($liveform->get_field_value('publish_cancel')) {
                $columns['publish_cancel'] = '1';
            }

            break;
    }

    return $columns;
}

/**
 * Writes a comment's changed columns and stamps the change. Only the
 * columns the edit screen writes are taken; any other key is left out.
 *
 * @param int $id
 * @param array $columns column => value
 * @param int $user_id the user who made the change
 * @return bool whether the UPDATE ran
 */
function pg_comment_form_save($id, array $columns, $user_id)
{
    $allowed = array('name', 'message', 'rating', 'file_id', 'seller_reply', 'seller_reply_at', 'published', 'publish_date_and_time', 'publish_cancel', 'featured');
    $set = array();
    $params = array();

    foreach ($allowed as $column) {
        if (array_key_exists($column, $columns)) {
            $set[] = $column . ' = ?';
            $params[] = (string) $columns[$column];
        }
    }

    $set[] = 'last_modified_user_id = ?';
    $params[] = (string) (int) $user_id;
    $params[] = (string) (int) $id;

    return (bool) db_q("UPDATE comments SET " . implode(', ', $set) . ", last_modified_timestamp = UNIX_TIMESTAMP() WHERE id = ?", $params);
}

/**
 * What a saved comment sets off: the record's comment count and newest
 * comment (read by the form lists and by the e-mails' subject), and, for a
 * comment just published, the e-mail to the record's submitter and the one
 * to the page's watchers.
 *
 * @param array $comment the comment as it was before the save: id, page_id,
 *                       item_type, item_id, published,
 *                       comments_submitter_email_page_id,
 *                       comments_watcher_email_page_id, created_user_id,
 *                       submitter_user_id
 * @param string $new_published the saved published column
 */
function pg_comment_form_after_save(array $comment, $new_published)
{
    pg_comment_refresh_record_info($comment['page_id'], $comment['item_type'], $comment['item_id']);

    if (((string) $new_published !== '1') || ((string) $comment['published'] === '1')) {
        return;
    }

    if (pg_comment_submitter_mail_due($comment['item_type'], $comment['item_id'], $comment['comments_submitter_email_page_id'], (int) $comment['created_user_id'], (int) $comment['submitter_user_id'])) {
        send_comment_email_to_custom_form_submitter($comment['id']);
    }

    if ((int) $comment['comments_watcher_email_page_id'] != 0) {
        send_comment_email_to_watchers($comment['id']);
    }
}
