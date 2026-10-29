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

// A product review (includes/fn/reviews.php): whether its purchase was
// verified, and the store's reply under it.
$review = pg_review_gate_applies($item_type, $item_id);
$verified_order_id = (int) $row['verified_order_id'];
$seller_reply = (string) $row['seller_reply'];
$seller_reply_at = (int) $row['seller_reply_at'];
$reply_allowed = $review && pg_review_replies_ready();


$form_reference_code = '';
$output_reference_button = '';
$output_reference_line = '';
$output_reference_buttons = '';
if($item_type === 'submitted_form'){
    //if this is custom page check referance code from forms
    $form_reference_code = $row['reference_code'];
    if($form_reference_code != ''){
        $output_reference_button = '<a class="btn btn-link pg-link-secondary py-0 mb-3 " href="' . h(OUTPUT_PATH . get_page_name($page_id) . pg_comment_view_query('?r=' . $form_reference_code, $comment_id) . '#c-' . $comment_id) . '"><i class="bi bi-link bi-me-2"></i>' . lang('Go To Comment') . '</a>';
        $output_reference_line = '<p class="p-0 m-0">' . lang('Form Reference Code') . ': ' . $form_reference_code . '</p>';
    }
    
}else if($item_type === 'product_group'){
    //if this is product group check address name from product groups
    $product_group_address_name = $row['product_group_address_name'];
    if($product_group_address_name != ''){
        $output_reference_button = '<a class="btn btn-link pg-link-secondary py-0 mb-3 " href="' . h(OUTPUT_PATH . get_page_name($page_id) . '/' . $product_group_address_name . pg_comment_view_query('', $comment_id) . '#c-' . $comment_id) . '"><i class="bi bi-link bi-me-2"></i>' . lang('Go To Comment') . '</a>';
    }

}else if($item_type === 'product'){
    //if this is product group check address name from product groups
    $product_address_name = $row['product_address_name'];
    if($product_address_name != ''){
        $output_reference_button = '<a class="btn btn-link pg-link-secondary py-0 mb-3 " href="' . h(OUTPUT_PATH . get_page_name($page_id) . '/' . $product_address_name . pg_comment_view_query('', $comment_id) . '#c-' . $comment_id) . '"><i class="bi bi-link bi-me-2"></i>' . lang('Go To Comment') . '</a>';
    }
}else if($item_type === ''){
    $output_reference_button = '<a class="btn btn-link pg-link-secondary py-0 mb-3 " href="' . h(OUTPUT_PATH . get_page_name($page_id) . pg_comment_view_query('', $comment_id) . '#c-' . $comment_id) . '"><i class="bi bi-link bi-me-2"></i>' . lang('Go To Comment') . '</a>';
}

//if there is a command button to output
// prepare button bar
if($output_reference_button != ''){
    $output_reference_buttons = '
    <nav id="button_bar" class="navigation " aria-label="' . h(lang('Button Bar')) . '">
        <div class=" btn-group btn-group-sm flex-wrap">
            ' . $output_reference_button . '
        </div>
    </nav>
    ';
}



// if user does not have access then output error
if (check_edit_access($folder_id) == false) {
    log_activity(lang('access denied to edit comment because user does not have access to modify folder that the page is in'), $_SESSION['sessionusername']);
    output_error(lang('Access denied.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
}

include_once('liveform.class.php');
$liveform = new liveform('edit_comment', $_REQUEST['id']);

// if the form has not been submitted
if (!$_POST) {

    // if the created username is not known, then set to [Unknown]
    $created_username = '';
        
    if ($row['created_username']) {
        $created_username = ' ' . lang(array('string'=>'by {var:1}','vars'=>array( h($row['created_username']) ) ) );
    }else{
        $created_username = ' ' . lang(array('string'=>'by [{var:1}]','vars'=>lang('Unknown') ) );
    }

    // if edit comment screen has not been submitted already, pre-populate fields with data
    if (isset($_SESSION['software']['liveforms']['edit_comment'][$_GET['id']]) == false) {
        $liveform->assign_field_value('send_to', ($_GET['send_to'] ?? ''));
        $liveform->assign_field_value('id', $_GET['id']);
        $liveform->assign_field_value('name', $name);
        $liveform->assign_field_value('message', $message);
        $liveform->assign_field_value('rating', $rating);
        $liveform->assign_field_value('seller_reply', $seller_reply);

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
        // An image (a review photo) shows as a thumbnail above its link.
        $output_file_thumbnail = pg_comment_file_is_image($file_name)
            ? '<a href="' . h(OUTPUT_PATH . encode_url_path($file_name)) . '" target="_blank" rel="noopener" class="d-inline-block mb-2"><img src="' . h(OUTPUT_PATH . encode_url_path($file_name)) . '" alt="' . h(lang('Photo')) . '" class="img-thumbnail" style="max-width: 240px; max-height: 240px"></a>'
            : '';
        $output_file_attachment =
            '<div class="col-12 my-3">
                <label class="form-label">' . lang('Attachment') . '</label>
                ' . $output_file_thumbnail . '
                <div class="input-group">
                    <a class=" btn btn-subtle theme-secondary border" href="' . OUTPUT_PATH . h(encode_url_path($file_name)) . '" target="_blank">
                        <span class="material-icons">attach_file</span>
                        ' . h($file_name) . ' (' . convert_bytes_to_string($file_size) . ') 
                    </a>
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

    if ($review) {
        if ($verified_order_id > 0) {
            $order_number = (string) db_value("SELECT order_number FROM orders WHERE id = '" . $verified_order_id . "'");
            $output_order = h(($order_number !== '') ? $order_number : (string) $verified_order_id);

            if (defined('USER_MANAGE_ECOMMERCE') && USER_MANAGE_ECOMMERCE) {
                $output_order = '<a href="view_order.php?id=' . $verified_order_id . '">' . $output_order . '</a>';
            }

            $output_review_line = '<p class="p-0 m-0">' . lang(array('string' => 'Verified purchase: order {var:1}', 'vars' => array($output_order))) . '</p>';
        } else {
            $output_review_line = '<p class="p-0 m-0">' . lang('Not a verified purchase') . '</p>';
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

    $publish_options = array();
    $publish_options[lang('Published')] = 'published';
    $publish_options[lang('At a Scheduled Time')] = 'schedule';
    $publish_options[lang('Not Published')] = 'not_published';

    $output_rating_field = '';
                       
    if($comments_rating != false){
        $output_rating_field = '
        <style>@layer custom {
            .rating .btn-check:checked+label~label{
                background-image: url("data:image/svg+xml,<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'16\' height=\'16\' fill=\'currentColor\' class=\'bi bi-star\' viewBox=\'0 0 16 16\'><path d=\'M2.866 14.85c-.078.444.36.791.746.593l4.39-2.256 4.389 2.256c.386.198.824-.149.746-.592l-.83-4.73 3.522-3.356c.33-.314.16-.888-.282-.95l-4.898-.696L8.465.792a.513.513 0 0 0-.927 0L5.354 5.12l-4.898.696c-.441.062-.612.636-.283.95l3.523 3.356-.83 4.73zm4.905-2.767-3.686 1.894.694-3.957a.565.565 0 0 0-.163-.505L1.71 6.745l4.052-.576a.525.525 0 0 0 .393-.288L8 2.223l1.847 3.658a.525.525 0 0 0 .393.288l4.052.575-2.906 2.77a.565.565 0 0 0-.163.506l.694 3.957-3.686-1.894a.503.503 0 0 0-.461 0z\'/></svg>") !important;
            }
            html[data-bs-theme="dark"] .rating label{filter: invert(100%);}
            .rating .btn-check:focus-visible+label{outline: 2px solid var(--pg-primary-ink); outline-offset: 2px;}
        }</style>

        <label id="comment_rating_label" class="form-label">' . lang('Rating') . ':</label>
        <div class="rating btn-group" role="radiogroup" aria-labelledby="comment_rating_label">
            ' . $liveform->output_field(array('type'=>'radio', 'name'=>'rating', 'id'=>'comment_rating_1', 'value'=>'1', 'class'=>'btn-check radio', 'aria-label'=>lang(array('string'=>'Rated {var:1} out of 5', 'vars'=>array('1'))))) . '
            <label for="comment_rating_1" style="cursor:pointer;min-width: 16px;min-height: 16px;background-image: url(&quot;data: image/svg+xml,<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'16\' height=\'16\' fill=\'currentColor\' class=\'bi bi-star-fill\' viewBox=\'0 0 16 16\'><path d=\'M3.612 15.443c-.386.198-.824-.149-.746-.592l.83-4.73L.173 6.765c-.329-.314-.158-.888.283-.95l4.898-.696L7.538.792c.197-.39.73-.39.927 0l2.184 4.327 4.898.696c.441.062.612.636.282.95l-3.522 3.356.83 4.73c.078.443-.36.79-.746.592L8 13.187l-4.389 2.256z\'/></svg>&quot;);background-repeat: no-repeat;background-position: center;"></label>
            ' . $liveform->output_field(array('type'=>'radio', 'name'=>'rating', 'id'=>'comment_rating_2', 'value'=>'2', 'class'=>'btn-check radio', 'aria-label'=>lang(array('string'=>'Rated {var:1} out of 5', 'vars'=>array('2'))))) . '
            <label for="comment_rating_2" style="cursor:pointer;min-width: 16px;min-height: 16px;background-image: url(&quot;data: image/svg+xml,<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'16\' height=\'16\' fill=\'currentColor\' class=\'bi bi-star-fill\' viewBox=\'0 0 16 16\'><path d=\'M3.612 15.443c-.386.198-.824-.149-.746-.592l.83-4.73L.173 6.765c-.329-.314-.158-.888.283-.95l4.898-.696L7.538.792c.197-.39.73-.39.927 0l2.184 4.327 4.898.696c.441.062.612.636.282.95l-3.522 3.356.83 4.73c.078.443-.36.79-.746.592L8 13.187l-4.389 2.256z\'/></svg>&quot;);background-repeat: no-repeat;background-position: center;"></label>
            ' . $liveform->output_field(array('type'=>'radio', 'name'=>'rating', 'id'=>'comment_rating_3', 'value'=>'3', 'class'=>'btn-check radio', 'aria-label'=>lang(array('string'=>'Rated {var:1} out of 5', 'vars'=>array('3'))))) . '
            <label for="comment_rating_3" style="cursor:pointer;min-width: 16px;min-height: 16px;background-image: url(&quot;data: image/svg+xml,<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'16\' height=\'16\' fill=\'currentColor\' class=\'bi bi-star-fill\' viewBox=\'0 0 16 16\'><path d=\'M3.612 15.443c-.386.198-.824-.149-.746-.592l.83-4.73L.173 6.765c-.329-.314-.158-.888.283-.95l4.898-.696L7.538.792c.197-.39.73-.39.927 0l2.184 4.327 4.898.696c.441.062.612.636.282.95l-3.522 3.356.83 4.73c.078.443-.36.79-.746.592L8 13.187l-4.389 2.256z\'/></svg>&quot;);background-repeat: no-repeat;background-position: center;"></label>
            ' . $liveform->output_field(array('type'=>'radio', 'name'=>'rating', 'id'=>'comment_rating_4', 'value'=>'4', 'class'=>'btn-check radio', 'aria-label'=>lang(array('string'=>'Rated {var:1} out of 5', 'vars'=>array('4'))))) . '
            <label for="comment_rating_4" style="cursor:pointer;min-width: 16px;min-height: 16px;background-image: url(&quot;data: image/svg+xml,<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'16\' height=\'16\' fill=\'currentColor\' class=\'bi bi-star-fill\' viewBox=\'0 0 16 16\'><path d=\'M3.612 15.443c-.386.198-.824-.149-.746-.592l.83-4.73L.173 6.765c-.329-.314-.158-.888.283-.95l4.898-.696L7.538.792c.197-.39.73-.39.927 0l2.184 4.327 4.898.696c.441.062.612.636.282.95l-3.522 3.356.83 4.73c.078.443-.36.79-.746.592L8 13.187l-4.389 2.256z\'/></svg>&quot;);background-repeat: no-repeat;background-position: center;"></label>
            ' . $liveform->output_field(array('type'=>'radio', 'name'=>'rating', 'id'=>'comment_rating_5', 'value'=>'5', 'checked'=>'checked', 'class'=>'btn-check radio', 'aria-label'=>lang(array('string'=>'Rated {var:1} out of 5', 'vars'=>array('5'))))) . '
            <label for="comment_rating_5" style="cursor:pointer;min-width: 16px;min-height: 16px;background-image: url(&quot;data: image/svg+xml,<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'16\' height=\'16\' fill=\'currentColor\' class=\'bi bi-star-fill\' viewBox=\'0 0 16 16\'><path d=\'M3.612 15.443c-.386.198-.824-.149-.746-.592l.83-4.73L.173 6.765c-.329-.314-.158-.888.283-.95l4.898-.696L7.538.792c.197-.39.73-.39.927 0l2.184 4.327 4.898.696c.441.062.612.636.282.95l-3.522 3.356.83 4.73c.078.443-.36.79-.746.592L8 13.187l-4.389 2.256z\'/></svg>&quot;);background-repeat: no-repeat;background-position: center;"></label>
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
            'cancel'=>array('enable'=>'true','url'=>'view_comments.php')

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
                <div class="row mb-3  flex-wrap">
                    <div class="col-12 sm:col-12 text-center md:text-start">
<div class="row mb-3">
                            <div class="col-12 md:col">
                                
                                <p class="p-0 m-0">' . lang('Added') . ': ' . get_relative_time(array('timestamp' => $created_timestamp)) . ' ' . $created_username . '</p>
                                <p class="p-0 m-0">' . lang('Page') . ': ' . h(get_page_name($page_id)) . '</p>
                                ' . $output_reference_line . '
                                ' . $output_review_line . '
                            </div>
                        </div>
                        ' . $output_reference_buttons . '
                    </div>
                </div>
                <form name="form" action="edit_comment.php" method="post">
                    ' . get_token_field() . '
                    ' . $liveform->output_field(array('type'=>'hidden', 'name'=>'send_to')) . '
                    ' . $liveform->output_field(array('type'=>'hidden', 'name'=>'id')) . '
                    <div class="row">
                        <div class="col-12">
                            <div class="card my-7">
                                <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('Details') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 md:col-6 lg:col-4 my-3">
                                            <label for="name" class="form-label">' . lang('Display Name') . '</label>
                                            ' . $liveform->output_field(array('type'=>'text', 'name'=>'name', 'id'=>'name', 'class'=>'form-control', 'maxlength'=>'100')) . '
                                        </div>
                                            <div class="col-12 md:col-6 lg:col-4 my-3">
                                                <label for="publish" class="form-label">' . lang('Publish') . '</label>
                                                ' . $liveform->output_field(array( 'type' => 'select', 'class' => 'form-control', 'id' => 'publish', 'name' => 'publish', 'options' => $publish_options)) . '
                                        </div>
                                        <div class="col-12 my-3">
                                            <label for="message" class="form-label">' . lang('Comment') . '</label>
                                            ' . $liveform->output_field(array('type'=>'textarea', 'name'=>'message', 'id'=>'message', 'class'=>'form-control')) . '
                                        </div>
                                        ' . $output_seller_reply_field . '
                                        ' . $output_rating_field . '
                                        ' . $output_file_attachment . '
                                        <div class="col-12 md:col-6 lg:col-4 my-3" id="publish_schedule" style="display: none">
                                            <div class="border-1 border p-3 my-3 rounded-4">
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
                                        ' . get_date_time_picker_format() . pg_fields_script() . '
                                        <script>init_edit_comment_publish()</script>
                                        <div class="col-12 my-5">
                                            <div class="form-field">
                                                <div class="switch">' . $liveform->output_field(array(
                                                    'type' => 'checkbox',
                                                    'name' => 'featured',
                                                    'id' => 'featured',
                                                    'value' => '1',
                                                    'class' => 'no-bs')) . '</div>
                                                <label class="" for="featured">' . lang('Featured') . '</label>
                                                <div class="form-text">' . lang('Highlight Featured Comment') . '</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <nav class="buttons navigation text-center position-sticky mb-7" style="bottom:.5rem;" aria-label="' . h(lang('data edit buttons')) . '">
                        <div class="container">
                            <div class=" btn-group flex-wrap justify-content-center">
                                <button type="submit" id="save_button" name="submit_save" value="Save" class="btn my-1  btn-solid theme-success "><span class="material-icons me-3">save</span><span class="pg-btn-label" >' . lang(array('string'=>'Save') ) . '</span></button>
                                <button type="submit" name="submit_delete" value="Delete" class="btn my-1  btn-solid theme-danger " data-confirm-content="' . lang(array('string'=>'WARNING: This {var:1} will be permanently deleted.','vars'=>array(lang('comment')))) . '"><span class="material-icons me-3">delete</span><span class="pg-btn-label" >' . lang(array('string'=>'Delete') ) . '</span></button>
                            </div>
                        </div>
                    </nav>
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

        // A form record's comment count and newest comment, read by the form
        // lists (includes/fn/comments.php).
        pg_comment_refresh_record_info($page_id, $item_type, $item_id);

        log_activity( lang(array('string'=>'comment on page ({var:1}) was deleted','vars'=>get_page_name($page_id) )), $_SESSION['sessionusername']);
        
    // else the comment was edited, so update the comment
    } else {
        // validate fields that need to be validated
        $liveform->validate_required_field('message', lang('A comment is required.'));

        // If the user selected the publish schedule option, then validate those fields.
        if ($liveform->get_field_value('publish') == 'schedule') {
            $liveform->validate_required_field('publish_date_and_time', lang('Please select the date & time when you want the comment to be published.'));

            // If there is not already an error for the date & time field,
            // and the value is not valid, then add error.
            if (
                ($liveform->check_field_error('publish_date_and_time') == false)
                && (validate_date_and_time($liveform->get_field_value('publish_date_and_time')) == false)
            ) {
                $liveform->mark_error('publish_date_and_time', lang('Please enter a valid date & time when you want the comment to be published.') );
            }

            // If there is not already an error for the date & time field,
            // and the date & time is in the past, then add error.
            if (
                ($liveform->check_field_error('publish_date_and_time') == false)
                && (prepare_form_data_for_input($liveform->get_field_value('publish_date_and_time'), 'date and time') < date('Y-m-d H:i'))
            ) {
                $liveform->mark_error('publish_date_and_time', lang('Sorry, the date & time you entered is in the past. Please enter a future date & time.') );
            }
        }
        
        // if there is an error, forward user back to edit comment screen
        if ($liveform->check_form_errors() == true) {
            header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/edit_comment.php?id=' . $liveform->get_field_value('id'));
            exit();
        }
        
        $sql_file_id = "";
        
        // if the file attachment was selected to be deleted and the file still exists, then check if we should delete it
        if (($liveform->get_field_value('delete_file_attachment') == 1) && ($file_name != '')) {
            // prepare SQL to clear file id for comment
            $sql_file_id = "file_id = '0',";
            
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

        $new_published = '';
        $new_publish_date_and_time = '';
        $new_publish_cancel = '';

        switch ($liveform->get_field_value('publish')) {
            case 'published':
                $new_published = '1';
                break;
            
            case 'schedule':
                $new_publish_date_and_time = prepare_form_data_for_input($liveform->get_field_value('publish_date_and_time'), 'date and time');

                if ($liveform->get_field_value('publish_cancel')) {
                    $new_publish_cancel = '1';
                }

                break;
        }
        
        // The store's reply to a product review, dated when it is first
        // written or changed (includes/fn/reviews.php).
        $sql_seller_reply = '';

        if ($reply_allowed) {
            $new_seller_reply = pg_review_reply_text($liveform->get_field_value('seller_reply'));
            $sql_seller_reply = "seller_reply = '" . e($new_seller_reply) . "',
                seller_reply_at = '" . pg_review_reply_time($seller_reply, $new_seller_reply, $seller_reply_at, time()) . "',";
        }

        // update comment
        $query =
            "UPDATE comments
            SET
                name = '" . escape($liveform->get_field_value('name')) . "',
                message = '" . escape($liveform->get_field_value('message')) . "',
                rating = '" . escape($liveform->get_field_value('rating')) . "',
                $sql_file_id
                $sql_seller_reply
                published = '" . $new_published . "',
                publish_date_and_time = '" . e($new_publish_date_and_time) . "',
                publish_cancel = '" . $new_publish_cancel . "',
                featured = '" . escape($liveform->get_field_value('featured')) . "',
                last_modified_user_id = '" . escape($user['id']) . "',
                last_modified_timestamp = UNIX_TIMESTAMP()
            WHERE id = '" . escape($liveform->get_field_value('id')) . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

        log_activity( lang(array('string'=>'comment on page ({var:1}) was modified','vars'=>get_page_name($page_id))), $_SESSION['sessionusername']);

        // A form record's comment count and newest comment, read by the form
        // lists (includes/fn/comments.php). Rebuilt before the e-mails below,
        // whose subject reads the count and the newest comment from that row.
        pg_comment_refresh_record_info($page_id, $item_type, $item_id);
        
        // if the comment was just published,
        // and if it was not published before,
        // and if it is about a form record (on a form item view page or on a
        // page built in the visual designer that shows the record),
        // and if there is a page selected to send,
        // and if someone other than the record's submitter wrote it,
        // then send e-mail to custom form submitter letting him/her know a comment has been added
        if (
            ($new_published == 1)
            && ($published == 0)
            && pg_comment_submitter_mail_due($item_type, $item_id, $comments_submitter_email_page_id, $created_user_id, $submitter_user_id)
        ) {
            send_comment_email_to_custom_form_submitter($liveform->get_field_value('id'));
        }
        
        // if the comment was just published,
        // and if it was not published before,
        // and if there is a page selected to send
        // then send e-mail to watchers letting them know a comment has been added
        if (($new_published == 1) && ($published == 0) && ($comments_watcher_email_page_id != 0)) {
            send_comment_email_to_watchers($liveform->get_field_value('id'));
        }
        
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
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_comments.php');
    }
    
    $liveform->remove_form();
}
?>