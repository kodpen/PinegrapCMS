<?php
/**
 * PineGrap - Enterprise Website Platform
 *
 * Originally developed as LiveSite by Camelback Web Architects.
 * Since 2017, maintained and evolved by Erdal Güral (Kodpen) under the name PineGrap.
 * The final LiveSite update (2019) has been integrated into PineGrap.
 * LiveSite remains available as a separate downloadable legacy version.
 *
 * @author      Camelback Web Architects
 *              Erdal Güral (Kodpen)
 * @link        https://livesite.com
 *              https://kodpen.com
 * @copyright   2001–2019 Camelback Consulting, Inc.
 *              2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
$user = validate_user();
validate_area_access($user, 'user');

// Stop early when the screen is opened without an existing record (stale
// link, hand-typed URL, row deleted meanwhile, an id sent as an array): a
// "not found" screen instead of PHP notices from the unchecked row below or
// an error page.
$id = is_scalar($_REQUEST['id'] ?? null) ? (int) $_REQUEST['id'] : 0;
if (($id < 1) || ((int) db_value("SELECT COUNT(*) FROM comments WHERE id = '" . $id . "'") < 1)) {
    output_error(lang('Record not found.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.', 404);
}

// get comment information
$query = 
    "SELECT
        comments.id as id,
        comments.page_id,
        comments.item_id,
        comments.item_type,
        comments.name,
        comments.message,
        comments.rating,
        files.id as file_id,
        files.name as file_name,
        files.size as file_size,
        comments.published,
        comments.publish_date_and_time,
        comments.publish_cancel,
        comments.featured,
        comments.created_user_id,
        page.page_folder,
        page.comments_submitter_email_page_id,
        page.comments_watcher_email_page_id,
        page.comments_rating as comments_rating,
        user.user_username as created_username,
        forms.reference_code as reference_code,
        forms.user_id AS submitter_user_id,
        product_groups.address_name as product_group_address_name,
        products.address_name as product_address_name,
        " . (pg_reviews_ready() ? "comments.verified_order_id," : "0 AS verified_order_id,") . "
        " . (pg_review_replies_ready() ? "comments.seller_reply, comments.seller_reply_at," : "'' AS seller_reply, 0 AS seller_reply_at,") . "
        " . (pg_schema_has('comments', 'remote_actor') ? "comments.remote_actor," : "'' AS remote_actor,") . "
        comments.created_timestamp
    FROM comments
    LEFT JOIN forms ON forms.id = comments.item_id
    LEFT JOIN product_groups ON product_groups.id = comments.item_id
    LEFT JOIN products ON products.id = comments.item_id
    LEFT JOIN files ON comments.file_id = files.id
    LEFT JOIN page ON page.page_id = comments.page_id
    LEFT JOIN user ON comments.created_user_id = user.user_id
    WHERE comments.id = ?";
$result = db_q($query, array((string) $_REQUEST['id'])) or output_error('Query failed.');
$row = mysqli_fetch_assoc($result);

// A stale or deleted comment id gives null here, and every read below -- plus
// $row['created_username'] further down -- would run against it.
if (!$row) {
    output_error(lang('Sorry, the item could not be found.'), 404);
}

$page_id = $row['page_id'];
$comment_id = $row['id'];
$item_id = $row['item_id'];
$item_type = $row['item_type'];
$name = $row['name'];
$message = $row['message'];
$rating = $row['rating'];
$file_id = $row['file_id'];
$file_name = $row['file_name'];
$file_size = $row['file_size'];
$published = $row['published'];
$publish_date_and_time = $row['publish_date_and_time'];
$publish_cancel = $row['publish_cancel'];
$featured = $row['featured'];
$created_user_id = (int) $row['created_user_id'];
$submitter_user_id = (int) $row['submitter_user_id'];
$folder_id = $row['page_folder'];
$comments_submitter_email_page_id = $row['comments_submitter_email_page_id'];
$comments_watcher_email_page_id = $row['comments_watcher_email_page_id'];
$created_timestamp = $row['created_timestamp'];
$comments_rating = $row['comments_rating'];

// A comment from the Fediverse names its writer's actor; a Manager or above
// may block that actor or its whole server (includes/fn/activitypub_moderation.php).
$remote_actor = (string) $row['remote_actor'];
$can_block = ($remote_actor !== '') && ($user['role'] < 3);

// A product review (includes/fn/reviews.php): whether its purchase was
// verified, and the store's reply under it.
$review = pg_review_gate_applies($item_type, $item_id);
$verified_order_id = (int) $row['verified_order_id'];
$seller_reply = (string) $row['seller_reply'];
$seller_reply_at = (int) $row['seller_reply_at'];
// A product question and its staff answer (includes/fn/product_questions.php):
// a question is answered here, never replied to as a review.
$question_state = pg_product_question_state($comment_id);
$comment_kind = $question_state['kind'];
$question_answer = $question_state['answer'];
$reply_allowed = $review && pg_review_replies_ready() && ($comment_kind === 'comment');


$output_reference_line = '';

// A record's comment also names the record's reference code.
if (($item_type === 'submitted_form') && ((string) $row['reference_code'] !== '')) {
    $output_reference_line = h($row['reference_code']);
}

// The comment's place on the site, as the comment mails link it.
$output_reference_button = '';
$comment_path = pg_comment_site_path(get_page_name($page_id), $row);

if ($comment_path !== '') {
    $output_reference_button = '<a class="btn btn-sm btn-text theme-secondary" href="' . h(OUTPUT_PATH . $comment_path . '#c-' . $comment_id) . '"><i class="bi bi-link me-2" aria-hidden="true"></i>' . lang('Go To Comment') . '</a>';
}

// A page in the Recycle Bin is not on the site, so its comment gets no link.
$binned = in_array((int) $folder_id, array_map('intval', (array) pg_recycle_bin_folder_ids()), true);

if ($binned) {
    $output_reference_button = '';
}




// if user does not have access then output error
if (check_edit_access($folder_id) == false) {
    log_activity(lang('access denied to edit comment because user does not have access to modify folder that the page is in'), $_SESSION['sessionusername']);
    output_error(lang('Access denied.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
}

include_once('liveform.class.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/activitypub_moderation.php');
require_once(PG_FUNCTIONS_DIR . '/includes/forms/comment_form.php');
$liveform = new liveform('edit_comment', $_REQUEST['id']);

// if the form has not been submitted
if (!$_POST) {

    // A visitor who was not signed in has no username: the line shows only
    // when the comment was added, as the Comments list does.
    $created_username = '';

    if ($row['created_username']) {
        $created_username = ' ' . lang(array('string'=>'by {var:1}','vars'=>array( h($row['created_username']) ) ) );
    }

    // if edit comment screen has not been submitted already, pre-populate fields with data
    if (isset($_SESSION['software']['liveforms']['edit_comment'][$_GET['id']]) == false) {
        $liveform->assign_field_value('send_to', ($_GET['send_to'] ?? ''));
        $liveform->assign_field_value('id', $_GET['id']);
        $liveform->assign_field_value('name', $name);
        $liveform->assign_field_value('message', $message);
        $liveform->assign_field_value('rating', $rating);
        $liveform->assign_field_value('seller_reply', $seller_reply);
        $liveform->assign_field_value('question_answer', ($question_answer !== null) ? (string) $question_answer['message'] : '');

        if ($published) {
            $liveform->assign_field_value('publish', 'published');

        } else if ($publish_date_and_time != '0000-00-00 00:00:00') {
            $liveform->assign_field_value('publish', 'schedule');

        } else {
            $liveform->assign_field_value('publish', 'not_published');
        }

        // If the comment is a scheduled comment, then set values for schedule fields.
        if ($publish_date_and_time != '0000-00-00 00:00:00') {
            $liveform->assign_field_value('publish_date_and_time', prepare_form_data_for_output($publish_date_and_time, 'date and time'));
            $liveform->assign_field_value('publish_cancel', $publish_cancel);

        // Otherwise the comment is not a scheduled comment,
        // so set values for scheduled fields to default values.
        } else {
            // If the date format is month and then day, then use that format.
            if (DATE_FORMAT == 'month_day') {
                $month_and_day_format = 'n/j';

            // Otherwise the date format is day and then month, so use that format.
            } else {
                $month_and_day_format = 'j/n';
            }

            $liveform->assign_field_value('publish_date_and_time', date($month_and_day_format . '/Y g:i A', time() + 3600));

            $liveform->assign_field_value('publish_cancel', '1');

            $liveform->assign_field_value('publish_cancel', '1');
        }

        $liveform->assign_field_value('featured', $featured);
    }
    
    $output_file_attachment = '';
    
    // if there is a file attachment, then output it
    if ($file_name != '') {
        // we are using a separate link for the image and the file name because we don't want an underline on the image and we don't want to have to update all themes with new CSS
        // The row can outlive its file (a site restored without data/files):
        // then the name is shown with a note, with no thumbnail or link.
        $file_on_disk = is_file(FILE_DIRECTORY_PATH . '/' . $file_name);

        // An image (a review photo) shows as a thumbnail above its link.
        $output_file_thumbnail = ($file_on_disk && pg_comment_file_is_image($file_name))
            ? '<a href="' . h(OUTPUT_PATH . encode_url_path($file_name)) . '" target="_blank" rel="noopener" class="d-inline-block mb-2"><img src="' . h(OUTPUT_PATH . encode_url_path($file_name)) . '" alt="' . h(lang('Photo')) . '" class="img-thumbnail" style="max-width: 240px; max-height: 240px"></a>'
            : '';
        $output_file_attachment =
            '<div class="col-12 my-3">
                <label class="form-label">' . lang('Attachment') . '</label>
                ' . $output_file_thumbnail . '
                <div class="input-group">
                    ' . ($file_on_disk
                        ? '<a class=" btn btn-subtle theme-secondary border" href="' . OUTPUT_PATH . h(encode_url_path($file_name)) . '" target="_blank">
                        <span class="material-icons">attach_file</span>
                        ' . h($file_name) . ' (' . convert_bytes_to_string($file_size) . ') 
                    </a>'
                        : '<span class="input-group-text"><span class="material-icons me-1" aria-hidden="true">attach_file</span>' . h($file_name) . ' <span class="fg-secondary ms-1">(' . lang('not on the disk') . ')</span></span>') . '
                    <div class="input-group-text">
                        <div class="col-12">
                            <div class="form-field">
                            <div class="switch">' . $liveform->output_field(array('type'=>'checkbox', 'name'=>'delete_file_attachment', 'id'=>'delete_file_attachment', 'value'=>'1', 'class'=>'danger no-bs')) . '</div>
                                <label class="fg-danger" for="delete_file_attachment">' . lang('Delete Attachment') . '</label>
                            </div>
                        </div>
                    </div> 
                </div>
            </div>';
    }

    // Whose purchase a product review comes from, and the field the store
    // answers it in (includes/fn/reviews.php).
    $output_review_line = '';
    $output_seller_reply_field = '';

    if ($comment_kind !== 'comment') {
        $output_review_line = '<p class="mb-0 pt-3">' . h(pg_product_question_badge($comment_kind, $question_answer !== null)) . '</p>';
    } elseif ($review) {
        if ($verified_order_id > 0) {
            $order = db_item("SELECT id, order_number FROM orders WHERE id = '" . $verified_order_id . "'");

            if ($order) {
                $order_number = (string) $order['order_number'];
                $output_order = h(($order_number !== '') ? $order_number : (string) $verified_order_id);

                if (defined('USER_MANAGE_ECOMMERCE') && USER_MANAGE_ECOMMERCE) {
                    $output_order = '<a href="view_order.php?id=' . $verified_order_id . '">' . $output_order . '</a>';
                }

            // The order was deleted after the review was written: the review
            // stays verified, but there is nothing left to link to.
            } else {
                $output_order = '#' . $verified_order_id . ' ' . h(lang('(deleted)'));
            }

            $output_review_line = '<p class="mb-0 pt-3">' . lang(array('string' => 'Verified purchase: order {var:1}', 'vars' => array($output_order))) . '</p>';
        } else {
            $output_review_line = '<p class="mb-0 pt-3">' . lang('Not a verified purchase') . '</p>';
        }
    }

    if ($reply_allowed) {
        $output_seller_reply_field = '
            <div class="col-12 my-3">
                <label for="seller_reply" class="form-label">' . lang('Reply from the store') . '</label>
                ' . $liveform->output_field(array('type'=>'textarea', 'name'=>'seller_reply', 'id'=>'seller_reply', 'rows'=>'3', 'maxlength'=>'5000', 'class'=>'form-control')) . '
                <div class="form-text">' . (($seller_reply_at > 0) ? lang(array('string' => 'Replied {var:1}.', 'vars' => array(get_relative_time(array('timestamp' => $seller_reply_at))))) . ' ' : '') . lang('Shown under the review, with the date it was written.') . '</div>
            </div>';
    }

    // The staff answer to a product question: published with the question,
    // shown under it; emptied, the answer is removed.
    if ($comment_kind === 'question') {
        $output_seller_reply_field = '
            <div class="col-12 my-3">
                <label for="question_answer" class="form-label">' . lang('Answer') . '</label>
                ' . $liveform->output_field(array('type'=>'textarea', 'name'=>'question_answer', 'id'=>'question_answer', 'rows'=>'3', 'maxlength'=>'5000', 'class'=>'form-control')) . '
                <div class="form-text">' . lang('Shown under the question as the store\'s answer. Leave it empty to remove the answer.') . '</div>
            </div>';
    }

    $publish_options = array();
    $publish_options[lang('Published')] = 'published';
    $publish_options[lang('At a Scheduled Time')] = 'schedule';
    $publish_options[lang('Not Published')] = 'not_published';

    $output_rating_field = '';
                       
    if($comments_rating != false){
        $output_rating_field = '
        <style>@layer custom {
            /* The star is a mask painted with the text colour, so it follows
               the theme; it sits on ::before because a mask on the label would
               also clip the focus outline of the label. */
            .rating label::before{
                content: "";
                display: block;
                width: 16px;
                height: 16px;
                background-color: currentColor;
                -webkit-mask-image: url("data:image/svg+xml,<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'16\' height=\'16\' viewBox=\'0 0 16 16\'><path d=\'M3.612 15.443c-.386.198-.824-.149-.746-.592l.83-4.73L.173 6.765c-.329-.314-.158-.888.283-.95l4.898-.696L7.538.792c.197-.39.73-.39.927 0l2.184 4.327 4.898.696c.441.062.612.636.282.95l-3.522 3.356.83 4.73c.078.443-.36.79-.746.592L8 13.187l-4.389 2.256z\'/></svg>");
                mask-image: url("data:image/svg+xml,<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'16\' height=\'16\' viewBox=\'0 0 16 16\'><path d=\'M3.612 15.443c-.386.198-.824-.149-.746-.592l.83-4.73L.173 6.765c-.329-.314-.158-.888.283-.95l4.898-.696L7.538.792c.197-.39.73-.39.927 0l2.184 4.327 4.898.696c.441.062.612.636.282.95l-3.522 3.356.83 4.73c.078.443-.36.79-.746.592L8 13.187l-4.389 2.256z\'/></svg>");
                -webkit-mask-repeat: no-repeat;
                mask-repeat: no-repeat;
                -webkit-mask-position: center;
                mask-position: center;
            }
            .rating .btn-check:checked+label~label::before{
                -webkit-mask-image: url("data:image/svg+xml,<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'16\' height=\'16\' viewBox=\'0 0 16 16\'><path d=\'M2.866 14.85c-.078.444.36.791.746.593l4.39-2.256 4.389 2.256c.386.198.824-.149.746-.592l-.83-4.73 3.522-3.356c.33-.314.16-.888-.282-.95l-4.898-.696L8.465.792a.513.513 0 0 0-.927 0L5.354 5.12l-4.898.696c-.441.062-.612.636-.283.95l3.523 3.356-.83 4.73zm4.905-2.767-3.686 1.894.694-3.957a.565.565 0 0 0-.163-.505L1.71 6.745l4.052-.576a.525.525 0 0 0 .393-.288L8 2.223l1.847 3.658a.525.525 0 0 0 .393.288l4.052.575-2.906 2.77a.565.565 0 0 0-.163.506l.694 3.957-3.686-1.894a.503.503 0 0 0-.461 0z\'/></svg>");
                mask-image: url("data:image/svg+xml,<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'16\' height=\'16\' viewBox=\'0 0 16 16\'><path d=\'M2.866 14.85c-.078.444.36.791.746.593l4.39-2.256 4.389 2.256c.386.198.824-.149.746-.592l-.83-4.73 3.522-3.356c.33-.314.16-.888-.282-.95l-4.898-.696L8.465.792a.513.513 0 0 0-.927 0L5.354 5.12l-4.898.696c-.441.062-.612.636-.283.95l3.523 3.356-.83 4.73zm4.905-2.767-3.686 1.894.694-3.957a.565.565 0 0 0-.163-.505L1.71 6.745l4.052-.576a.525.525 0 0 0 .393-.288L8 2.223l1.847 3.658a.525.525 0 0 0 .393.288l4.052.575-2.906 2.77a.565.565 0 0 0-.163.506l.694 3.957-3.686-1.894a.503.503 0 0 0-.461 0z\'/></svg>");
            }
            .rating .btn-check:focus-visible+label{outline: 2px solid var(--pg-primary-ink); outline-offset: 2px;}
            /* Forced colours paint a background Canvas, which hides the
               masked star; CanvasText keeps both star shapes visible. */
            @media (forced-colors: active) {
                .rating label::before{background-color: CanvasText;}
            }
        }</style>

        <div class="col-12 my-3">
        <label id="comment_rating_label" class="form-label d-block">' . lang('Rating') . '</label>
        <div class="rating btn-group d-inline-flex w-auto" role="radiogroup" aria-labelledby="comment_rating_label">
            ' . $liveform->output_field(array('type'=>'radio', 'name'=>'rating', 'id'=>'comment_rating_1', 'value'=>'1', 'class'=>'btn-check radio', 'aria-label'=>lang(array('string'=>'Rated {var:1} out of 5', 'vars'=>array('1'))))) . '
            <label for="comment_rating_1" style="cursor:pointer;min-width: 16px;min-height: 16px;"></label>
            ' . $liveform->output_field(array('type'=>'radio', 'name'=>'rating', 'id'=>'comment_rating_2', 'value'=>'2', 'class'=>'btn-check radio', 'aria-label'=>lang(array('string'=>'Rated {var:1} out of 5', 'vars'=>array('2'))))) . '
            <label for="comment_rating_2" style="cursor:pointer;min-width: 16px;min-height: 16px;"></label>
            ' . $liveform->output_field(array('type'=>'radio', 'name'=>'rating', 'id'=>'comment_rating_3', 'value'=>'3', 'class'=>'btn-check radio', 'aria-label'=>lang(array('string'=>'Rated {var:1} out of 5', 'vars'=>array('3'))))) . '
            <label for="comment_rating_3" style="cursor:pointer;min-width: 16px;min-height: 16px;"></label>
            ' . $liveform->output_field(array('type'=>'radio', 'name'=>'rating', 'id'=>'comment_rating_4', 'value'=>'4', 'class'=>'btn-check radio', 'aria-label'=>lang(array('string'=>'Rated {var:1} out of 5', 'vars'=>array('4'))))) . '
            <label for="comment_rating_4" style="cursor:pointer;min-width: 16px;min-height: 16px;"></label>
            ' . $liveform->output_field(array('type'=>'radio', 'name'=>'rating', 'id'=>'comment_rating_5', 'value'=>'5', 'checked'=>'checked', 'class'=>'btn-check radio', 'aria-label'=>lang(array('string'=>'Rated {var:1} out of 5', 'vars'=>array('5'))))) . '
            <label for="comment_rating_5" style="cursor:pointer;min-width: 16px;min-height: 16px;"></label>
        </div>
        </div>';
    }


    // The Summary in the status column: what is read, not edited. A row is
    // a label and a value; the last row has no rule under it.
    $summary_rows = array(
        array(lang('Added'), get_relative_time(array('timestamp' => $created_timestamp)) . $created_username),
        array(lang('Page'), h(pg_comment_page_label(get_page_name($page_id), $page_id)) . ($binned ? ' (' . h(lang('In the Recycle Bin')) . ')' : '')));

    if ($output_reference_line !== '') {
        $summary_rows[] = array(lang('Form Reference Code'), $output_reference_line);
    }

    if ($remote_actor !== '') {
        $summary_rows[] = array(lang('From the Fediverse'), h($remote_actor));
    }

    $output_summary_rows = '';

    foreach ($summary_rows as $index => $summary_row) {
        $output_summary_rows .= '
                                            <div class="d-flex justify-content-between gap-3 py-2' . (($index < count($summary_rows) - 1) ? ' border-bottom' : '') . '">
                                                <dt class="fw-normal">' . h($summary_row[0]) . '</dt>
                                                <dd class="mb-0 fw-bold text-end text-break">' . $summary_row[1] . '</dd>
                                            </div>';
    }

    print
    pg_page_shell(
        array(
            'title'=> lang(array('string'=>'Edit {var:1}','vars'=>lang('Comment'))),
            'extra classes'=>'page',
            'icon'=>'page', 
            'heading'=> lang(array('string'=>'Edit {var:1}','vars'=>lang('Comment'))),
            'heading_description' => lang('Edit and publish this comment.'),
            'cancel' => array('enable' => 'true', 'url' => pg_send_to_url(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_comments.php'))

        ,
            'breadcrumb' => array(array('label' => lang('Comments'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_comments.php'), array('label' => lang('Edit Comment'))),
        )
    ) . '
<main id="content" class="container-fluid">
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->get_warnings() . '
                ' . $liveform->output_notices() . '
                ' . pg_screen_toolbar(array('secondary' => $output_reference_button)) . '
                <form name="form" action="edit_comment.php" method="post">
                    ' . get_token_field() . '
                    ' . $liveform->output_field(array('type'=>'hidden', 'name'=>'send_to')) . '
                    ' . $liveform->output_field(array('type'=>'hidden', 'name'=>'id')) . '
                    <div class="row">
                        <div class="col-12 lg:col-8 2xl:col-9 order-1">
                            <div class="card mb-7">
                                <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('Details') . '
                                </h2>
                                <div class="card-body pt-0">
                                    <div class="row">
                                        <div class="col-12 md:col-6 my-3">
                                            <label for="name" class="form-label">' . lang('Display Name') . '</label>
                                            ' . $liveform->output_field(array('type'=>'text', 'name'=>'name', 'id'=>'name', 'class'=>'form-control', 'maxlength'=>'100')) . '
                                        </div>
                                        <div class="col-12 my-3">
                                            <label for="message" class="form-label">' . lang('Comment') . '</label>
                                            ' . $liveform->output_field(array('type'=>'textarea', 'name'=>'message', 'id'=>'message', 'rows'=>'10', 'class'=>'form-control', 'required'=>'required')) . '
                                        </div>
                                        ' . $output_seller_reply_field . '
                                        ' . $output_rating_field . '
                                        ' . $output_file_attachment . '
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 lg:col-4 2xl:col-3 order-2">
                            <!-- 3.3rem: the fixed control panel header covers the top of the window. -->
                            <div class="position-sticky" style="top:3.3rem;">
                                <div class="card mb-7">
                                    <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                        ' . lang('Publish') . '
                                    </h2>
                                    <div class="card-body pt-0">
                                        <div class="my-3">
                                            <label for="publish" class="form-label visually-hidden">' . lang('Publish') . '</label>
                                            ' . $liveform->output_field(array( 'type' => 'select', 'class' => 'form-control', 'id' => 'publish', 'name' => 'publish', 'options' => $publish_options)) . '
                                        </div>
                                        <div class="my-3" id="publish_schedule" hidden>
                                            <div class="border-1 border p-3 rounded-4">
                                                <label for="publish_date_and_time" class="form-label">' . lang('Publish Date') . '</label>
                                                ' . $liveform->output_field(array(
                                                    'type' => 'text',
                                                    'id' => 'publish_date_and_time',
                                                    'name' => 'publish_date_and_time',
                                                    'maxlength' => '19',
                                                    'class' => 'form-control',
                                                    'data-pg-datetime' => '')) . '

                                                <div class="form-field ms-1 mt-3">
                                                    <div class="switch">' . $liveform->output_field(array(
                                                        'type' => 'checkbox',
                                                        'name' => 'publish_cancel',
                                                        'id' => 'publish_cancel',
                                                        'value' => '1',
                                                        'class' => 'no-bs')) . '</div>
                                                    <label class="" for="publish_cancel">' . lang('Cancel if a new comment is added first.') . '</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="form-field mt-5">
                                            <div class="switch">' . $liveform->output_field(array(
                                                'type' => 'checkbox',
                                                'name' => 'featured',
                                                'id' => 'featured',
                                                'value' => '1',
                                                'class' => 'no-bs')) . '</div>
                                            <label class="" for="featured">' . lang('Featured') . '</label>
                                            <div class="form-text">' . lang('Highlight Featured Comment') . '</div>
                                        </div>
                                        ' . get_date_time_picker_format() . pg_fields_script() . '
                                        <script>init_edit_comment_publish()</script>
                                    </div>
                                </div>
                                <div class="card mb-7">
                                    <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                        ' . lang('Summary') . '
                                    </h2>
                                    <div class="card-body pt-0">
                                        <dl class="mb-0">' . $output_summary_rows . '
                                        </dl>' . $output_review_line . '
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 order-3 position-sticky" style="bottom:0; z-index:3;">
                            <nav class="buttons navigation text-center mb-7" aria-label="' . h(lang('data edit buttons')) . '">
                                <div class="container">
                                    <div class="btn-group flex-wrap justify-content-center">
                                        <button type="submit" id="save_button" name="submit_save" value="Save" class="btn my-1  btn-solid theme-success "><span class="material-icons me-3">save</span><span class="pg-btn-label" >' . lang(array('string'=>'Save') ) . '</span></button>
                                        <button type="submit" name="submit_delete" value="Delete" formnovalidate class="btn my-1  btn-solid theme-danger " data-confirm-content="' . lang(array('string'=>'WARNING: This {var:1} will be permanently deleted.','vars'=>array(lang('comment')))) . '"><span class="material-icons me-3">delete</span><span class="pg-btn-label" >' . lang(array('string'=>'Delete') ) . '</span></button>' . ($can_block ? '
                                        <button type="submit" name="submit_block" value="actor" formnovalidate class="btn my-1 btn-solid theme-danger" data-confirm-content="' . h(lang('This account will be blocked: its comments, likes and boosts are deleted and it can no longer follow the site or reply.')) . '"><span class="material-icons me-3">block</span><span class="pg-btn-label">' . lang('Block Account') . '</span></button>
                                        <button type="submit" name="submit_block" value="domain" formnovalidate class="btn my-1 btn-solid theme-danger" data-confirm-content="' . h(lang(array('string' => 'Every account on {var:1} will be blocked: their comments, likes and boosts are deleted and they can no longer follow the site or reply.', 'vars' => array(pg_ap_host($remote_actor))))) . '"><span class="material-icons me-3">domain_disabled</span><span class="pg-btn-label">' . lang('Block Server') . '</span></button>' : '') . '
                                    </div>
                                </div>
                            </nav>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    
</main>' .
    output_footer();
    
    $liveform->remove_form();

// else the form has been submitted
} else {
    validate_token_field();
    
    $liveform->add_fields_to_session();
    
    $bookmark = '';

    // A block removes this comment with everything else of the actors it
    // covers, so the screen goes back to the list.
    $block_kind = (string) $liveform->get_field_value('submit_block');

    if (($block_kind === 'actor') || ($block_kind === 'domain')) {
        if (!$can_block) {
            output_error(lang('Access denied.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
        }

        $blocked = pg_ap_block_add($block_kind, $remote_actor);
        $liveform->remove_form();
        $liveform_view_comments = new liveform('view_comments');

        if ($blocked['ok']) {
            log_activity(lang(array('string' => 'Fediverse {var:1} was blocked', 'vars' => array($blocked['value']))), $_SESSION['sessionusername']);
            $liveform_view_comments->add_notice(lang(array('string' => '{var:1} is blocked; what the site kept of it has been deleted.', 'vars' => array(h($blocked['value'])))));
        } else {
            $liveform_view_comments->mark_error('', lang('The block could not be saved.'));
        }

        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_comments.php');
        exit();
    }
    
    // if comment was selected for deletion, then delete the comment
    if ($liveform->get_field_value('submit_delete') == 'Delete') {
        // if there is a file attachment, then check if we should delete it
        if ($file_name != '') {
            // check if the file attachment is used by another comment (multiple comments can share the same file attachment when pages are duplicated)
            $query = "SELECT id FROM comments WHERE (file_id = ?) AND (id != ?)";
            $result = db_q($query, array((string) $file_id, (string) ($liveform->get_field_value('id')))) or output_error('Query failed.');
            
            // if the file attachment is not used by another comment, then delete the file
            if (mysqli_num_rows($result) == 0) {
                // delete file from database
                $query = "DELETE FROM files WHERE id = ?";
                $result = db_q($query, array((string) $file_id)) or output_error('Query failed.');
                
                // delete file on file system
                @unlink(FILE_DIRECTORY_PATH . '/' . $file_name);
                
                // log that the file was deleted
                log_activity(lang(array('string'=>'file attachment ({var:1}) was deleted because a comment on page ({var:2}) was deleted','vars'=>array($file_name,get_page_name($page_id)) )), $_SESSION['sessionusername']);
            }
        }
        
        // delete comment
        $query = "DELETE FROM comments WHERE id = ?";
        $result = db_q($query, array((string) ($liveform->get_field_value('id')))) or output_error('Query failed.');
        
        // also delete notification created for this comment.
        $query = "DELETE FROM notifications WHERE comment_id = ?";
        $result = db_q($query, array((string) ($liveform->get_field_value('id')))) or output_error('Query failed.');

        // A question goes with its answer.
        if ($comment_kind === 'question') {
            db_q("DELETE FROM comments WHERE (answer_of = ?) AND (kind = 'answer')", array((string) (int) $comment_id));
        }

        // A form record's comment count and newest comment, read by the form
        // lists (includes/fn/comments.php).
        pg_comment_refresh_record_info($page_id, $item_type, $item_id);

        log_activity( lang(array('string'=>'comment on page ({var:1}) was deleted','vars'=>get_page_name($page_id) )), $_SESSION['sessionusername']);
        
    // else the comment was edited, so update the comment
    } else {
        // The message and the schedule (includes/forms/comment_form.php).
        pg_comment_form_validate($liveform);

        // if there is an error, forward user back to edit comment screen
        if ($liveform->check_form_errors() == true) {
            header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/edit_comment.php?id=' . $liveform->get_field_value('id') . '&send_to=' . urlencode($liveform->get_field_value('send_to')));
            exit();
        }
        
        $columns = array(
            'name' => (string) $liveform->get_field_value('name'),
            'message' => (string) $liveform->get_field_value('message'),
            'featured' => (string) $liveform->get_field_value('featured'),
        );
        
        // if the file attachment was selected to be deleted and the file still exists, then check if we should delete it
        if (($liveform->get_field_value('delete_file_attachment') == 1) && ($file_name != '')) {
            // prepare SQL to clear file id for comment
            $columns['file_id'] = '0';
            
            // check if the file attachment is used by another comment (multiple comments can share the same file attachment when pages are duplicated)
            $query = "SELECT id FROM comments WHERE (file_id = ?) AND (id != ?)";
            $result = db_q($query, array((string) $file_id, (string) ($liveform->get_field_value('id')))) or output_error('Query failed.');
            
            // if the file attachment is not used by another comment, then delete the file
            if (mysqli_num_rows($result) == 0) {
                // delete file from database
                $query = "DELETE FROM files WHERE id = ?";
                $result = db_q($query, array((string) $file_id)) or output_error('Query failed.');
                
                // delete file on file system
                @unlink(FILE_DIRECTORY_PATH . '/' . $file_name);
                
                // log that the file was deleted
                log_activity(lang(array('string'=>'file attachment ({var:1}) for a comment on page ({var:2}) was deleted','vars'=>array($file_name,get_page_name($page_id)) )), $_SESSION['sessionusername']);
            }
        }

        $columns += pg_comment_form_publish_columns($liveform);
        $new_published = $columns['published'];

        // The store's reply to a product review, dated when it is first
        // written or changed (includes/fn/reviews.php).
        if ($reply_allowed) {
            $new_seller_reply = pg_review_reply_text($liveform->get_field_value('seller_reply'));
            $columns['seller_reply'] = $new_seller_reply;
            $columns['seller_reply_at'] = (string) pg_review_reply_time($seller_reply, $new_seller_reply, $seller_reply_at, time());
        }

        // The stars are drawn only while the page has its rating on; with it
        // off the stored rating stays, since the missing radios would write 0.
        // The value is one to five, or none (0), as the comment form stores it.
        if ($comments_rating != false) {
            $columns['rating'] = (string) pg_comment_rating_value($liveform->get_field_value('rating'));
        }

        pg_comment_form_save($comment_id, $columns, (int) $user['id']) or output_error('Query failed.');

        log_activity( lang(array('string'=>'comment on page ({var:1}) was modified','vars'=>get_page_name($page_id))), $_SESSION['sessionusername']);

        // The staff answer to a product question, written under the name of
        // the member of staff who answers.
        if ($comment_kind === 'question') {
            $answer_saved = pg_product_question_answer_save($comment_id, $liveform->get_field_value('question_answer'), (int) $user['id'], (string) ($user['username'] ?? ''));

            // The asker hears of a new answer once.
            if ($answer_saved === 'added') {
                pg_product_question_answer_mail($comment_id);
            }

            if ($answer_saved !== '') {
                log_activity(lang(array('string' => 'answer to a product question on page ({var:1}) was saved', 'vars' => get_page_name($page_id))), $_SESSION['sessionusername']);
            }
        }

        // The record's comment info, and the submitter's and watchers' e-mails
        // for a comment just published.
        pg_comment_form_after_save($row, $new_published);

        // create bookmark for header
        $bookmark = '#c-' . $liveform->get_field_value('id');
    }

    // A review published, held back, re-rated or deleted moves its product's
    // rating (includes/fn/reviews.php).
    pg_review_comment_changed($item_type, $item_id);

    // if there is a send to, then forward user to send to
    if ($liveform->get_field_value('send_to') != '') {
        header('Location: ' . URL_SCHEME . HOSTNAME . pg_safe_redirect_path($liveform->get_field_value('send_to')) . $bookmark);
        
    // else there is not a send to, so forward user to the view comments screen
    } else {
        // The list says what happened; a return to the site needs no notice.
        $liveform_view_comments = new liveform('view_comments');
        $liveform_view_comments->add_notice(($liveform->get_field_value('submit_delete') == 'Delete') ? lang('The comment has been deleted.') : lang('The comment has been saved.'));

        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_comments.php');
    }
    
    $liveform->remove_form();
}
?>