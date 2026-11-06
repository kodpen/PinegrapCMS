<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Comments list (view_comments.php) and its quick edit: every
 * value the comment screen (edit_comment.php) changes is a field,
 * checked and written by that screen's own form code
 * (includes/forms/comment_form.php).
 *
 * Loaded where it is needed (require_once), not by functions.php; it needs
 * includes/forms/comment_form.php.
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
 * The quick edit fields. The schedule is typed in the site's date and time
 * format, as on the screen; the screen's checks read it. Pure but for
 * lang().
 *
 * @return array field => definition
 */
function pg_comment_quick_edit_columns()
{
    $yes_no = array('1' => lang('Yes'), '0' => lang('No'));
    $ratings = array('0' => lang('None'));

    for ($stars = 1; $stars <= 5; $stars++) {
        $ratings[(string) $stars] = lang(array('string' => 'Rated {var:1} out of 5', 'vars' => array((string) $stars)));
    }

    return array(
        'name' => array('type' => 'text', 'label' => lang('Name'), 'max' => 100),
        'message' => array('type' => 'textarea', 'label' => lang('Message'), 'required' => true),
        'published' => array('type' => 'select', 'label' => lang('Published'), 'options' => $yes_no),
        'featured' => array('type' => 'select', 'label' => lang('Featured'), 'options' => $yes_no),
        'scheduled' => array('type' => 'text', 'label' => lang('At a Scheduled Time'), 'max' => 19),
        'publish_cancel' => array('type' => 'select', 'label' => lang('Cancel if Added First'), 'options' => $yes_no),
        'rating' => array('type' => 'select', 'label' => lang('Rating'), 'options' => $ratings),
    );
}

/**
 * The list's columns; each one the screen changes is editable (the message
 * over several lines). The store's reply and a question's answer are not
 * list columns; they stay on the comment screen.
 *
 * @param bool $edit whether the list has quick edit
 * @return array key => spec, as pg_list_table_printed() takes them
 */
function pg_comment_list_columns($edit)
{
    $columns = array(
        'name' => array('heading' => lang('Name'), 'editable' => 'text'),
        'published' => array('heading' => lang('Published'), 'kind' => 'check', 'editable' => 'select'),
        'featured' => array('heading' => lang('Featured'), 'kind' => 'check', 'editable' => 'select'),
        'scheduled' => array('heading' => lang('At a Scheduled Time'), 'kind' => 'date', 'editable' => 'text'),
        'publish_cancel' => array('heading' => lang('Cancel if Added First'), 'kind' => 'check', 'editable' => 'select'),
        'rating' => array('heading' => lang('Rating'), 'editable' => 'select'),
        'message' => array('heading' => lang('Message'), 'sortable' => false, 'editable' => 'textarea'),
        'submitted' => array('heading' => lang('Submitted'), 'kind' => 'date'),
        'go' => array('heading' => lang('Go To Comment'), 'kind' => 'actions'),
    );

    if (!$edit) {
        foreach ($columns as $key => $column) {
            unset($columns[$key]['editable']);
        }
    }

    return $columns;
}

/**
 * The list's query, without its ORDER BY.
 *
 * @param string $where           a WHERE clause, '' for none
 * @param int    $id              one comment only, 0 for all (ignores $where)
 * @param bool   $reviews_ready   pg_reviews_ready()
 * @param bool   $questions_ready pg_product_questions_ready()
 * @return string
 */
function pg_comment_list_sql($where, $id, $reviews_ready, $questions_ready)
{
    if ((int) $id > 0) {
        $where = "WHERE comments.id = '" . (int) $id . "'";
    }

    return "SELECT
            comments.id,
            comments.page_id,
            comments.item_id,
            comments.item_type,
            comments.name,
            comments.message,
            comments.rating,
            files.id AS file_id,
            files.name AS file_name,
            files.size AS file_size,
            comments.published,
            comments.publish_date_and_time,
            comments.publish_cancel,
            comments.featured,
            comments.created_user_id,
            page.page_name,
            page.page_type,
            page.page_folder,
            page.comments_submitter_email_page_id,
            page.comments_watcher_email_page_id,
            page.comments_rating AS comments_rating,
            user.user_username AS created_username,
            forms.reference_code AS reference_code,
            forms.user_id AS submitter_user_id,
            product_groups.address_name AS product_group_address_name,
            products.address_name AS product_address_name,
            " . ($reviews_ready ? "comments.verified_order_id," : "0 AS verified_order_id,") . "
            " . ($questions_ready
                ? "comments.kind, (SELECT COUNT(*) FROM comments AS answers WHERE (answers.answer_of = comments.id) AND (answers.kind = 'answer')) AS answer_count,"
                : "'comment' AS kind, 0 AS answer_count,") . "
            comments.created_timestamp
        FROM comments
        LEFT JOIN forms ON forms.id = comments.item_id
        LEFT JOIN product_groups ON product_groups.id = comments.item_id
        LEFT JOIN products ON products.id = comments.item_id
        LEFT JOIN files ON comments.file_id = files.id
        LEFT JOIN page ON page.page_id = comments.page_id
        LEFT JOIN user ON comments.created_user_id = user.user_id
        " . $where;
}

/**
 * The fields a comment's row offers: the cancel switch only while the
 * comment is scheduled (the screen shows it only then), the rating only
 * while its page rates comments (the screen draws the stars only then).
 * Pure.
 *
 * @param array $comment    pg_comment_list_sql() row
 * @param array $quick_edit field => definition, empty for none
 * @return array field => definition
 */
function pg_comment_quick_row_fields(array $comment, array $quick_edit)
{
    if (pg_comment_form_publish_state($comment) !== 'schedule') {
        unset($quick_edit['publish_cancel']);
    }

    if (empty($comment['comments_rating'])) {
        unset($quick_edit['rating']);
    }

    return $quick_edit;
}

/**
 * The value a comment's field opens with: the stored value; the schedule in
 * the site's date and time format, as the screen shows it. Pure but for the
 * site's date format.
 *
 * @param string $field a pg_comment_quick_edit_columns() field
 * @param array  $comment
 * @return string
 */
function pg_comment_quick_value($field, array $comment)
{
    switch ($field) {
        case 'published':
        case 'featured':
        case 'publish_cancel':
            return ((string) ($comment[$field] ?? '') === '1') ? '1' : '0';

        case 'scheduled':
            return (pg_comment_form_publish_state($comment) === 'schedule')
                ? (string) prepare_form_data_for_output($comment['publish_date_and_time'], 'date and time', false)
                : '';

        case 'rating':
            return (string) pg_comment_rating_value($comment['rating'] ?? 0);
    }

    return (string) ($comment[$field] ?? '');
}

/**
 * The edit screen's form values for a change made on the list: the stored
 * comment as the screen opens it, with the one change. Published Yes
 * publishes now; No takes a published comment off the site and leaves a
 * scheduled one scheduled. A date schedules the comment (a newer comment
 * cancels it unless it was scheduled without that); an empty date takes a
 * scheduled comment off the schedule. Pure but for the site's date format.
 *
 * @param array  $comment pg_comment_list_sql() row
 * @param string $field
 * @param mixed  $value   pg_list_edit_request()'s value
 * @return array field => value
 */
function pg_comment_quick_form_values(array $comment, $field, $value)
{
    $state = pg_comment_form_publish_state($comment);
    $value = (string) $value;

    $values = array(
        'name' => (string) $comment['name'],
        'message' => (string) $comment['message'],
        'rating' => pg_comment_quick_value('rating', $comment),
        'publish' => $state,
        'publish_date_and_time' => pg_comment_quick_value('scheduled', $comment),
        'publish_cancel' => ((string) $comment['publish_cancel'] === '1') ? '1' : '',
        'featured' => ((string) $comment['featured'] === '1') ? '1' : '',
    );

    switch ($field) {
        case 'name':
        case 'message':
        case 'rating':
            $values[$field] = $value;
            break;

        case 'featured':
        case 'publish_cancel':
            $values[$field] = ($value === '1') ? '1' : '';
            break;

        case 'published':
            if ($value === '1') {
                $values['publish'] = 'published';
            } elseif ($state === 'published') {
                $values['publish'] = 'not_published';
            }

            break;

        case 'scheduled':
            if ($value !== '') {
                $values['publish'] = 'schedule';
                $values['publish_date_and_time'] = $value;

                if ($state !== 'schedule') {
                    $values['publish_cancel'] = '1';
                }
            } elseif ($state === 'schedule') {
                $values['publish'] = 'not_published';
            }

            break;
    }

    return $values;
}

/**
 * A comment's cells on the list, with their quick edit attributes.
 *
 * @param array $comment     pg_comment_list_sql() row
 * @param array $quick_edit  field => definition, empty for none
 * @param array $bin_folders folder id => anything, the Recycle Bin's folders
 * @return array key => pg_list_cell()
 */
function pg_comment_list_cells(array $comment, array $quick_edit, array $bin_folders = array())
{
    $id = (int) $comment['id'];
    $fields = pg_comment_quick_row_fields($comment, $quick_edit);
    $binned = isset($bin_folders[(int) $comment['page_folder']]);

    $attributes = function ($field, array $more = array()) use ($comment, $fields, $id) {
        return $more + pg_list_edit_cell_attributes($fields, $field, $id, pg_comment_quick_value($field, $comment));
    };

    $check = function ($field) use ($comment, $attributes) {
        $cell = pg_list_table_check_cell((string) $comment[$field] === '1');

        return pg_list_cell($cell['html'], $cell['class'], $attributes($field, $cell['attributes']));
    };

    $scheduled = pg_comment_quick_value('scheduled', $comment);

    // The comment's place on the site, when it has one. A page in the
    // Recycle Bin is not on the site: no address to go to.
    $path = $binned ? '' : pg_comment_site_path((string) ($comment['page_name'] ?? ''), $comment);
    $go = ($path === '') ? '' : '<a class="btn btn-sm btn-text theme-secondary" href="' . h(OUTPUT_PATH . $path . '#c-' . $id) . '" title="' . h(lang('Go To Comment')) . '" aria-label="' . h(lang('Go To Comment')) . '"><i class="bi bi-link" aria-hidden="true"></i></a>';

    $kind = (string) ($comment['kind'] ?? 'comment');
    $name = pg_comment_list_name($comment['name'])
        . ((int) $comment['verified_order_id'] > 0 ? ' <span class="badge theme-success fw-light">' . h(lang('Verified purchase')) . '</span>' : '')
        . ($kind !== 'comment' ? ' <span class="badge ' . ((($kind === 'question') && ((int) $comment['answer_count'] === 0)) ? 'theme-warning' : 'theme-secondary') . ' fw-light">' . h(pg_product_question_badge($kind, (int) $comment['answer_count'] > 0)) . '</span>' : '')
        . ($binned ? ' <span class="badge theme-secondary fw-light">' . h(lang('In the Recycle Bin')) . '</span>' : '');

    $rating = pg_comment_rating_value($comment['rating'] ?? 0);
    $output_rating = ($rating > 0)
        ? '<span aria-hidden="true">' . str_repeat('★', $rating) . '</span><span class="visually-hidden">' . h(lang(array('string' => 'Rated {var:1} out of 5', 'vars' => array((string) $rating)))) . '</span>'
        : '';

    $photo = pg_comment_file_is_image($comment['file_name'] ?? '')
        ? '<img src="' . h(OUTPUT_PATH . encode_url_path($comment['file_name'])) . '" alt="' . h(lang('Photo')) . '" loading="lazy" class="img-thumbnail float-start me-2" style="width: 50px; height: 50px; object-fit: cover">'
        : '';

    return array(
        'name' => pg_list_cell('<a href="edit_comment.php?id=' . $id . '" data-pg-open>' . $name . '</a>', 'align-middle', $attributes('name')),
        'published' => $check('published'),
        'featured' => $check('featured'),
        'scheduled' => pg_list_cell(($scheduled === '') ? '' : '<span class="badge theme-primary fw-light">' . h($scheduled) . '</span>', 'align-middle', $attributes('scheduled', array('data-pg-sort-value' => ($scheduled === '') ? '' : (string) $comment['publish_date_and_time']))),
        'publish_cancel' => $check('publish_cancel'),
        'rating' => pg_list_cell($output_rating, 'align-middle text-nowrap', $attributes('rating', array('data-pg-sort-value' => $rating))),
        'message' => pg_list_cell($photo . '<p class="mb-0">' . h(truncate($comment['message'], 84, '...')) . '</p>', 'align-middle', $attributes('message')),
        'submitted' => pg_list_table_when_cell($comment['created_timestamp'], $comment['created_username'] ?? ''),
        'go' => pg_list_cell($go),
    );
}

/**
 * The quick edit answer from a comment read again: the edited cell and
 * every other cell of the row (publishing clears the schedule, the cancel
 * switch comes and goes with it), each with the value its field opens with.
 *
 * @param array  $cells pg_comment_list_cells()
 * @param string $field the edited cell's column
 * @return array
 */
function pg_comment_quick_saved(array $cells, $field)
{
    $view = function ($cell) {
        $out = array('html' => $cell['html'], 'class' => $cell['class']);

        if (isset($cell['attributes']['data-pg-edit-value'])) {
            $out['value'] = (string) $cell['attributes']['data-pg-edit-value'];
        }

        return $out;
    };

    $answer = array('ok' => true) + $view($cells[$field]);
    $answer['value'] = $answer['value'] ?? '';
    $answer['cells'] = array();

    foreach ($cells as $key => $cell) {
        if ($key !== $field) {
            $answer['cells'][$key] = $view($cell);
        }
    }

    return $answer;
}

/**
 * Writes one value changed on the list through the comment screen's own
 * check and save, with the screen's rights (the page's folder editable by
 * the user) and what a save there sets off: the record's comment info, the
 * e-mails for a comment just published, the product's rating.
 *
 * @param int    $id
 * @param string $field a pg_comment_quick_edit_columns() field
 * @param mixed  $value pg_list_edit_request()'s value
 * @param array  $user  validate_user()
 * @param array  $quick_edit
 * @return array the answer for pg_list_edit_reply()
 */
function pg_comment_quick_update($id, $field, $value, array $user, array $quick_edit)
{
    $refuse = function ($message) {
        return array('ok' => false, 'code' => 'invalid', 'message' => (string) $message);
    };

    $reviews_ready = pg_reviews_ready();
    $questions_ready = pg_product_questions_ready();
    $comment = db_item(pg_comment_list_sql('', $id, $reviews_ready, $questions_ready));

    if (!$comment) {
        return $refuse(lang('Record not found.'));
    }

    if (check_edit_access($comment['page_folder']) != true) {
        return array('ok' => false, 'code' => 'denied', 'message' => lang('Access denied.'));
    }

    if (!isset(pg_comment_quick_row_fields($comment, $quick_edit)[$field])) {
        return $refuse(lang('This column cannot be changed here.'));
    }

    $form = new liveform('comment_quick_edit');
    $form->remove_form();

    foreach (pg_comment_quick_form_values($comment, $field, $value) as $form_field => $form_value) {
        $form->assign_field_value($form_field, $form_value);
    }

    pg_comment_form_validate($form);

    if ($form->check_form_errors()) {
        $messages = array_values(array_filter($form->field_error_texts(), 'strlen'));
        $form->remove_form();

        return $refuse($messages ? $messages[0] : lang('The value could not be saved.'));
    }

    $columns = array(
        'name' => (string) $form->get_field_value('name'),
        'message' => (string) $form->get_field_value('message'),
        'featured' => (string) $form->get_field_value('featured'),
    ) + pg_comment_form_publish_columns($form);

    if (!empty($comment['comments_rating'])) {
        $columns['rating'] = (string) pg_comment_rating_value($form->get_field_value('rating'));
    }

    $form->remove_form();

    if (!pg_comment_form_save($id, $columns, (int) $user['id'])) {
        return $refuse(lang('The value could not be saved.'));
    }

    log_activity(lang(array('string' => 'comment on page ({var:1}) was modified', 'vars' => array((string) $comment['page_name']))), $_SESSION['sessionusername'] ?? '');

    pg_comment_form_after_save($comment, $columns['published']);
    pg_review_comment_changed($comment['item_type'], $comment['item_id']);

    $row = db_item(pg_comment_list_sql('', $id, $reviews_ready, $questions_ready));

    if (!$row) {
        return array('ok' => true, 'value' => (string) $value);
    }

    $bin_folders = array_flip(array_map('intval', (array) pg_recycle_bin_folder_ids()));

    return pg_comment_quick_saved(pg_comment_list_cells($row, $quick_edit, $bin_folders), $field);
}
