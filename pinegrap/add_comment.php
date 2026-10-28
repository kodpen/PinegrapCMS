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

validate_token_field();

check_banned_ip_addresses('add comment');

include_once('liveform.class.php');
$liveform = new liveform('add_comment', $_POST['page_id']);
$liveform->add_fields_to_session();

// get values from form
$send_to = $liveform->get_field_value('send_to');
$page_id = $liveform->get_field_value('page_id');
$item_id = $liveform->get_field_value('item_id');
$item_type = $liveform->get_field_value('item_type');
$name = $liveform->get_field_value('name');
$name_type = $liveform->get_field_value('name_type');
$message = $liveform->get_field_value('message');
$watcher = $liveform->get_field_value('watcher');
// The comment this one answers, if any; checked further down, after the page's gates.
$parent_id_posted = $liveform->get_field_value('parent_id');

// The stars are one to five, or none (0); whatever else was posted is none.
$rating = (int) $liveform->get_field_value('rating');
if (($rating < 1) || ($rating > 5)) {
    $rating = 0;
}





// get page properties
$query =
    "SELECT
        page_name,
        page_folder as folder_id,
        page_type,
        comments,
        comments_label,
        comments_allow_new_comments,
        comments_disallow_new_comment_message,
        comments_automatic_publish,
        comments_allow_user_to_select_name,
        comments_require_login_to_comment,
        comments_allow_file_attachments,
        comments_administrator_email_to_email_address,
        comments_administrator_email_subject,
        comments_administrator_email_conditional_administrators,
        comments_submitter_email_page_id,
        comments_watcher_email_page_id
    FROM page
    WHERE page_id = ?";
$result = db_q($query, array((string) $page_id)) or output_error('Query failed.');
$row = mysqli_fetch_assoc($result);

$page_name = $row['page_name'];
$folder_id = $row['folder_id'];
$page_type = $row['page_type'];
$comments = $row['comments'];
$comments_label = $row['comments_label'];
$comments_allow_new_comments = $row['comments_allow_new_comments'];
$comments_disallow_new_comment_message = $row['comments_disallow_new_comment_message'];
$comments_automatic_publish = $row['comments_automatic_publish'];
$comments_allow_user_to_select_name = $row['comments_allow_user_to_select_name'];
$comments_require_login_to_comment = $row['comments_require_login_to_comment'];
$comments_allow_file_attachments = $row['comments_allow_file_attachments'];
$comments_administrator_email_to_email_address = $row['comments_administrator_email_to_email_address'];
$comments_administrator_email_subject = $row['comments_administrator_email_subject'];
$comments_administrator_email_conditional_administrators = $row['comments_administrator_email_conditional_administrators'];
$comments_submitter_email_page_id = $row['comments_submitter_email_page_id'];
$comments_watcher_email_page_id = $row['comments_watcher_email_page_id'];

// The thread the comment may join, worked out again from the page rather than
// taken from the hidden fields (includes/fn/comments.php). A page built in the
// visual editor shows a submitted form through its form_item_view widget
// rather than a page type, and its comments are that record's: for the checks
// below it is a form item view page, and the widget's access setting is its
// submitter security (see pg_sw_record_comment_context()). A classic catalog,
// catalog detail or calendar event view page, or a designed page showing one
// product (pg_comment_thread_page_type()), takes only a product, product
// group or calendar event it shows (pg_comment_classic_item_shown()). A
// refusal is answered further down, after the page's own gates.
$comment_thread = pg_comment_thread_verify($page_id, $page_type, $item_type, $item_id);
$comment_record = null;
if ($comment_thread['accept']) {
    $item_type = $comment_thread['item_type'];
    $item_id = $comment_thread['item_id'];
    $comment_record = $comment_thread['record'];
    if ($comment_record !== null) {
        $page_type = 'form item view';
    }
}

$comment_label = get_comment_label(array('label' => $comments_label));
$output_comment_label = h($comment_label);
$comment_label_lowercase = mb_strtolower($comment_label);
$output_comment_label_lowercase = h($comment_label_lowercase);

// validate fields that need to be validated
$liveform->validate_required_field('message', lang(array('string'=>'A {var:1} is required.','vars'=>$output_comment_label_lowercase)));

// If the user selected the publish schedule option, then validate those fields.
if ($liveform->get_field_value('publish') == 'schedule') {
    $liveform->validate_required_field('publish_date_and_time', lang(array('string'=>'Please select the date & time when you want the {var:1} to be published.','vars'=>$output_comment_label_lowercase)) );

    // If there is not already an error for the date & time field,
    // and the value is not valid, then add error.
    if (
        ($liveform->check_field_error('publish_date_and_time') == false)
        && (validate_date_and_time($liveform->get_field_value('publish_date_and_time')) == false)
    ) {
        $liveform->mark_error('publish_date_and_time', lang(array('string' => 'Please enter a valid date & time when you want the {var:1} to be published.', 'vars' => $output_comment_label_lowercase)));
    }

    // If there is not already an error for the date & time field,
    // and the date & time is in the past, then add error.
    if (
        ($liveform->check_field_error('publish_date_and_time') == false)
        && (prepare_form_data_for_input($liveform->get_field_value('publish_date_and_time'), 'date and time') < date('Y-m-d H:i'))
    ) {
        $liveform->mark_error('publish_date_and_time', lang('Sorry, the date & time you entered is in the past. Please enter a future date & time.'));
    }
}

// if CAPTCHA is enabled then validate CAPTCHA
if (CAPTCHA == TRUE) {
    validate_captcha_answer($liveform);
}

// An attachment the web server would run or read as its own settings is
// refused at validation, so the visitor is told rather than a renamed file
// quietly landing in the upload folder.
if (isset($_FILES['file']) && ($_FILES['file']['name'] != '') && pg_upload_name_blocked($_FILES['file']['name'])) {
    $liveform->mark_error('file', pg_upload_blocked_message(htmlspecialchars($_FILES['file']['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')));
}

// if an error exists, then send user back to previous screen
if ($liveform->check_form_errors() == true) {
    header('Location: ' . URL_SCHEME . HOSTNAME . pg_safe_redirect_path($liveform->get_field_value('send_to')) . '#software_add_comment');
    exit();
}

// if comments are disabled for the page, log and output error
if ($comments == 0) {
    log_activity(lang(array('string'=>'access denied to submit a comment to page ({var:1}) because comments are disabled','vars'=>$page_name)), $_SESSION['sessionusername']);
    output_error(lang('You do not have access to submit a comment to that page because comments are disabled.') . ' <a href="javascript:history.go(-1);">' . lang('Go back') . '</a>.');
}

// Whether the thread takes new comments: an item's own switch when it has one
// (allow_new_comments_for_items), else the page's; the comment form asks the
// same (includes/fn/comments.php).
$allow_new_comments = pg_comments_allow_new($page_id, $item_type, $item_id, ($comments_allow_new_comments == 1));

// if new comments are not allowed, log and output error
if ($allow_new_comments == FALSE) {
    log_activity(lang(array('string'=>'access denied to submit a comment to page ({var:1}) because new comments are not allowed','vars'=>$page_name)), $_SESSION['sessionusername']);
    output_error(h($comments_disallow_new_comment_message) . ' <a href="javascript:history.go(-1);">' . lang('Go back') . '</a>.');
}

// if the user is not logged in, and if the user is required to login to comment, then output an error
if ((isset($_SESSION['sessionusername']) == FALSE) && ($comments_require_login_to_comment == 1)) {
    log_activity(lang(array('string'=>'access denied to submit a comment to page ({var:1}) because visitor was not logged in','vars'=>$page_name)), $_SESSION['sessionusername']);
    output_error(lang(array('string'=>'You must login before you can submit a {var:1}.','vars'=>$output_comment_label_lowercase)) . ' <a href="javascript:history.go(-1);">' . lang('Go back') . '</a>.');
}

// get user information if user is logged in
$query =
    "SELECT
        user.user_id,
        user.user_role
    FROM user
    WHERE user.user_username = ?";
$result = db_q($query, array((string) ($_SESSION['sessionusername'] ?? ''))) or output_error('Query failed.');
$row = mysqli_fetch_assoc($result);

// A visitor who is not signed in has no row: no user, no role.
$user_id = $row['user_id'] ?? null;
$user_role = $row['user_role'] ?? null;

// if user does not have access to view page, log and output error
if (check_view_access($folder_id) == false) {
    log_activity(lang(array('string'=>'access denied to submit a comment to page ({var:1}) because visitor does not have view access to page','vars'=>$page_name)), $_SESSION['sessionusername']);
    output_error(lang(array('string'=>'You do not have access to submit a {var:1} to that page.','vars'=>$output_comment_label_lowercase)) . ' <a href="javascript:history.go(-1);">' . lang('Go back') . '</a>.');
}

$user['id'] = $user_id;
$user['role'] = $user_role;

// If the user is a basic user then get the folders they have access to.
if ($user['role'] == 3) {
    $folders_that_user_has_access_to = get_folders_that_user_has_access_to($user['id']);
}

// The thread check (pg_comment_thread_verify() above) is answered only here,
// after the page's own gates (comments on, new comments allowed, login, view
// access): a visitor who may not comment on the page gets the same answer
// whatever thread or record id the post named.

// A page with a form_item_view widget takes comments on the record it shows
// and on nothing else. A post naming the page itself or a product goes back
// to the form with what was typed, never onto a thread the page does not
// show. The posted thread is dropped from the form state: a hidden field
// prints the session value first, so the form drawn again would carry the
// refused thread rather than the record the page shows.
if ($comment_thread['refusal'] === 'item_type') {
    log_activity(lang(array('string'=>'access denied to submit a comment to page ({var:1}) because the item type was not valid for the page type','vars'=>$page_name)), $_SESSION['sessionusername'] ?? '');
    $liveform->add_error(lang(array('string'=>'Sorry, the {var:1} was not added, because the item type was not valid for the page type.','vars'=>$output_comment_label_lowercase)));
    $liveform->set_field('item_type', array());
    $liveform->set_field('item_id', array());
    header('Location: ' . URL_SCHEME . HOSTNAME . pg_safe_redirect_path($liveform->get_field_value('send_to')) . '#software_add_comment');
    exit();
}

// A record the widget does not show to this visitor (its access setting or
// record filters hide it, it was deleted, or it belongs to another form) is
// refused on an error page: such a record's page draws no comment form that
// could show a form error. The form state is dropped first, so the typed text
// does not turn up later on the form of another record of this page, where it
// would post to that record's thread. Every case gets the same sentence; only
// the log line differs.
if ($comment_thread['refusal'] === 'record') {
    $refused_record = (
        is_array($comment_thread['record'])
        && is_array($comment_thread['record']['form'])
        && is_scalar($item_id)
        && ((int) $item_id > 0)
        && ((int) $comment_thread['record']['form']['id'] === (int) $item_id)
    ) ? $comment_thread['record']['form'] : null;

    if ($refused_record !== null) {
        log_activity(lang(array('string'=>'access denied to submit a comment to page ({var:1}) because the visitor did not have view access to submitted form ({var:2})','vars'=>array( $page_name,$refused_record['reference_code'] ))), $_SESSION['sessionusername'] ?? '');
    } else {
        log_activity(lang(array('string'=>'access denied to submit a comment to page ({var:1}) because the submitted form could not be found','vars'=>$page_name)), $_SESSION['sessionusername'] ?? '');
    }

    $liveform->remove_form();
    output_error(lang(array('string'=>'Sorry, the {var:1} was not added, because you do not have access to view that submitted form.','vars'=>$output_comment_label_lowercase)) . ' <a href="javascript:history.go(-1);">' . lang('Go back') . '</a>.');
}

// If the item type is not valid for the page type (a legacy form item view
// page takes only a submitted form, a page without a form_item_view widget
// never one, and a page takes a product, product group or calendar event only
// when its page type shows that kind of item), then log activity and output
// error.
if ($comment_thread['refusal'] === 'page_type') {
    log_activity(lang(array('string'=>'access denied to submit a comment to page ({var:1}) because the item type was not valid for the page type','vars'=>$page_name)), $_SESSION['sessionusername'] ?? '');
    output_error(lang(array('string'=>'Sorry, the {var:1} was not added, because the item type was not valid for the page type.','vars'=>$output_comment_label_lowercase)) . ' <a href="javascript:history.go(-1);">' . lang('Go back') . '</a>.');
}

// A product, product group or calendar event this classic page does not show
// (it was deleted, the event is not published or not in this view's
// calendars, or the id names nothing) is refused on an error page, as a
// record the page does not show is: the page answers that item's address with
// an error of its own, so there is no form to go back to. The form state is
// dropped first: a hidden field prints the session value first, so the next
// comment form of this page, about another item, would carry the refused item
// and the typed text and post them again.
if ($comment_thread['refusal'] === 'item') {
    log_activity(lang(array('string'=>'access denied to submit a comment to page ({var:1}) because the item could not be found','vars'=>$page_name)), $_SESSION['sessionusername'] ?? '');
    $liveform->remove_form();
    output_error(lang(array('string'=>'Sorry, the {var:1} was not added, because the item could not be found.','vars'=>$output_comment_label_lowercase)) . ' <a href="javascript:history.go(-1);">' . lang('Go back') . '</a>.');
}

// If the item type is submitted form then prepare for that type of comment.
if ($item_type == 'submitted_form') {
    // Get various info for the submitted form.
    $submitted_form = db_item(
        "SELECT
            forms.id,
            forms.page_id AS custom_form_page_id,
            forms.reference_code,
            forms.form_editor_user_id,
            forms.user_id AS submitter_user_id,
            page.page_folder AS custom_form_folder_id,
            user.user_email AS form_editor_email_address
        FROM forms
        LEFT JOIN page ON forms.page_id = page.page_id
        LEFT JOIN user ON forms.form_editor_user_id = user.user_id
        WHERE forms.id = '" . escape($item_id) . "'");

    // If the submitted form was not found, then log and output error.
    if (!$submitted_form['id']) {
        log_activity(lang(array('string'=>'access denied to submit a comment to page ({var:1}) because the submitted form could not be found','vars'=>$page_name)), $_SESSION['sessionusername']);
        output_error(lang(array('string'=>'Sorry, the {var:1} was not added, because the submitted form could not be found.','vars'=>$output_comment_label_lowercase)) . ' <a href="javascript:history.go(-1);">' . lang('Go back') . '</a>.');
    }

    // Get submitter security for form item view. A designed page answers
    // through its widget, whose access setting and record filters
    // pg_comment_thread_verify() already applied: a record the widget does
    // not show to this visitor was refused above, so none is asked here.
    if ($comment_record !== null) {
        $form_item_view = array('submitter_security' => 0);
    } else {
        $form_item_view = db_item(
            "SELECT submitter_security
            FROM form_item_view_pages
            WHERE
                (page_id = '" . escape($page_id) . "')
                AND (collection = 'a')");
    }

    // If submitter security is enabled for the form item view,
    // then check if the viewer is authorized to view the submitted form.
    if ($form_item_view['submitter_security'] == 1) {
        // Assume that this visitor does not have access to view the submitted form
        // until we find out otherwise.
        $view_access = false;

        // If this visitor submitted this form during this browser session, then grant access.
        if (
            (is_array($_SESSION['software']['submitted_form_reference_codes']) == true)
            && (in_array($submitted_form['reference_code'], $_SESSION['software']['submitted_form_reference_codes']) == true)
        ) {
            $view_access = true;

        // Otherwise, if this visitor is logged in, then continue to check if visitor has view access.
        } else if (USER_LOGGED_IN == TRUE) {
            // If this user has edit access to the custom form,
            // or is the form editor for this submitted form,
            // or is the submitter by direct user id connection,
            // then the user has view access.
            if (
                (check_edit_access($submitted_form['custom_form_folder_id']) == true)
                || (USER_ID == $submitted_form['form_editor_user_id'])
                || (USER_ID == $submitted_form['submitter_user_id'])
            ) {
                $view_access = true;

            // Otherwise this user does not have access through those ways
            // so check if user has access in other ways.
            } else {
                $submitter_email_address = db_value(
                    "SELECT form_data.data
                    FROM form_data
                    LEFT JOIN form_fields ON form_data.form_field_id = form_fields.id
                    WHERE
                        (form_data.form_id = '" . $submitted_form['id'] . "')
                        AND (form_fields.contact_field = 'email_address')");

                // If this user has the same e-mail address as the connect-to-contact e-mail address
                // field value for this submitted form, then the user has view access.
                if (mb_strtolower(USER_EMAIL_ADDRESS) == mb_strtolower($submitter_email_address)) {
                    $view_access = true;

                // Otherwise this user is not the submitter, but if they are a watcher,
                // then grant access.
                } else if (
                    db_value(
                        "SELECT COUNT(*)
                        FROM watchers
                        WHERE
                            (
                                (user_id = '" . USER_ID . "')
                                OR (email_address = '" . escape(USER_EMAIL_ADDRESS) . "')
                            )
                            AND (page_id = '" . escape($page_id) . "')
                            AND (item_id = '" . $submitted_form['id'] . "')
                            AND (item_type = 'submitted_form')")
                    > 0
                ) {
                    $view_access = true;
                }
            }
        }

        // If this visitor does not have view access to the submitted form.
        // then log and output error.
        if ($view_access == false) {
            log_activity(lang(array('string'=>'access denied to submit a comment to page ({var:1}) because the visitor did not have view access to submitted form ({var:2})','vars'=>array( $page_name,$submitted_form['reference_code'] ))), $_SESSION['sessionusername']);
            output_error(lang(array('string'=>'Sorry, the {var:1} was not added, because you do not have access to view that submitted form.','vars'=>$output_comment_label_lowercase)) . ' <a href="javascript:history.go(-1);">' . lang('Go back') . '</a>.');
        }
    }
}

// A reply names the comment it answers in parent_id. Asked only now, after
// the page's own gates, the thread refusals and the record's view check, so
// a visitor who may not comment here learns nothing about the comments of
// the thread. The named comment must sit in this same thread and be one the
// writer can see: published, their own still waiting in this browser
// session, or any for staff and the record's form editor
// (includes/fn/comments.php). Anything else goes back to the form with what
// was typed and is never stored as a comment that answers none. One sentence
// answers every refusal, so a comment the writer cannot see reads as one that
// does not exist. parent_id is dropped from the form state: a hidden field
// prints the session value first, so the form drawn again would carry the
// refused parent.
$comment_parent = pg_comment_parent_row($parent_id_posted);
$comment_parent_check = pg_comment_parent_ok(
    $parent_id_posted,
    $comment_parent,
    $page_id,
    $item_type,
    $item_id,
    array(
        'staff' => check_edit_access($folder_id),
        'form_editor' => (
            ($item_type == 'submitted_form')
            && USER_LOGGED_IN
            && isset($submitted_form['form_editor_user_id'])
            && ((int) $submitted_form['form_editor_user_id'] > 0)
            && ((int) USER_ID === (int) $submitted_form['form_editor_user_id'])
        ),
        'own_ids' => (isset($_SESSION['software']['added_comments']) && is_array($_SESSION['software']['added_comments'])) ? $_SESSION['software']['added_comments'] : array(),
    )
);

if (!$comment_parent_check['accept']) {
    log_activity(lang(array('string' => 'access denied to submit a comment to page ({var:1}) because the comment it replies to could not be found or is not visible to the visitor', 'vars' => $page_name)), $_SESSION['sessionusername'] ?? '');
    $liveform->add_error(lang(array('string' => 'Sorry, the {var:1} was not added, because the one it replies to could not be found.', 'vars' => $output_comment_label_lowercase)));
    $liveform->set_field('parent_id', array());
    header('Location: ' . URL_SCHEME . HOSTNAME . pg_safe_redirect_path($liveform->get_field_value('send_to')) . '#software_add_comment');
    exit();
}

$parent_id = $comment_parent_check['parent_id'];

if ($parent_id === 0) {
    $comment_parent = null;
}

// A review of a product comes from somebody who bought it
// (includes/fn/reviews.php): signed in, with a bought order holding the
// product that no review of it names yet. The page's own form says so before
// anything is typed; this is the check that counts. Staff, who may edit the
// page, write as the store and are not asked.
$verified_order_id = 0;
$is_product_review = pg_review_gate_applies($item_type, $item_id);

if ($is_product_review && !check_edit_access($folder_id)) {
    $review = pg_review_eligibility((int) $user_id, $item_type, $item_id);

    if ($review['refusal'] !== '') {
        log_activity(lang(array('string' => 'access denied to submit a review to page ({var:1}) because the visitor has no purchase of the product left to review', 'vars' => $page_name)), $_SESSION['sessionusername'] ?? '');
        output_error(h(pg_review_refusal_message($review['refusal'])) . ' <a href="javascript:history.go(-1);">' . lang('Go back') . '</a>.');
    }

    $verified_order_id = (int) $review['order_id'];
}

// if the user is not logged in,
// or if the user has a role greater than a user role,
// or if the user has edit rights to this page,
// or if the item is a submitted form and this user is the form editor
// then save the name they entered
if (
    (isset($_SESSION['sessionusername']) == FALSE)
    || ($user['role'] < 3)
    || (check_folder_access_in_array($folder_id, $folders_that_user_has_access_to) == TRUE)
    || (($item_type == 'submitted_form') && ($user['id'] == $submitted_form['form_editor_user_id']))
) {
    $name = $name;

// A signed-in member who may not type a name gets the name
// pg_comment_member_name() decides: on a page of a visual design the
// contact's display name, elsewhere the login username. The posted name
// field is never read for them, and with the page's name select on, a
// name_type the form does not offer this member counts as no choice.
} else {
    $name = pg_comment_member_name(
        (string) ($_SESSION['sessionusername'] ?? ''),
        pg_comment_member_contact($user_id),
        pg_sw_page_is_visual($page_id),
        ($comments_allow_user_to_select_name == 1),
        $name_type
    );
}

// if there is not a name to save, then save anonymous
if ($name == '') {
    $name = 'Anonymous';
}

$file_id = 0;

// If file attachments are allowed and a file was uploaded, then add file.
if (($comments_allow_file_attachments == 1) && ($_FILES['file']['name'])) {
    $file_name = prepare_file_name($_FILES['file']['name']);

    // Check if file name is already in use and change it if necessary.
    $file_name = get_unique_name(array(
        'name' => $file_name,
        'type' => 'file'));

    $file_size = $_FILES['file']['size'];
    $file_temp_name = $_FILES['file']['tmp_name'];
    
    // Get the position of the last period in order to get the extension.
    $position_of_last_period = mb_strrpos($file_name, '.');

    $file_extension = '';
    
    // If there is an extension then remember it.
    if ($position_of_last_period !== false) {
        $file_extension = mb_substr($file_name, $position_of_last_period + 1);
    }
    
    // create file
    copy($file_temp_name, FILE_DIRECTORY_PATH . '/' . $file_name);

    // create file record in database
    $query =
        "INSERT INTO files (
            name,
            folder,
            type,
            size,
            user,
            design,
            attachment,
            timestamp)
        VALUES (
            ?,
            ?,
            ?,
            ?,
            ?,
            '0',
            '1',
            UNIX_TIMESTAMP())";
    $result = db_q($query, array((string) $file_name, (string) $folder_id, (string) $file_extension, (string) $file_size, (string) $user_id)) or output_error('Query failed.');
    
    $file_id = mysqli_insert_id(db::$con);
}

$published = '';
$publish_date_and_time = '';
$publish_cancel = '';

// If the user has access to publish comments for this page (i.e. edit access to page),
// or if this comment is for a submitted form and the user is the form editor,
// then allow user to set publish settings.
if (
    (check_edit_access($folder_id))
    ||
    (
        ($item_type == 'submitted_form')
        && (USER_LOGGED_IN)
        && (USER_ID == $submitted_form['form_editor_user_id'])
    )
) {
    switch ($liveform->get_field_value('publish')) {
        case 'now':
            $published = '1';
            break;
        
        case 'schedule':
            $publish_date_and_time = prepare_form_data_for_input($liveform->get_field_value('publish_date_and_time'), 'date and time');

            if ($liveform->get_field_value('publish_cancel')) {
                $publish_cancel = '1';
            }

            break;
    }
    
// else if automatic publish is enabled, then set the comment to be published
} else if ($comments_automatic_publish == 1) {
    $published = 1;
    
// else automatic publish is disabled, so set the comment to not be published
} else {
    $published = 0;
}






// The order that verified the purchase, once the column is there (11.0.14).
$sql_review_column = '';
$sql_review_value = '';

if (pg_reviews_ready()) {
    $sql_review_column = ",
        verified_order_id";
    $sql_review_value = ",
        '" . (int) $verified_order_id . "'";
}

// The comment this one answers, once the column is there (11.0.31). Before
// that no reply gets this far: pg_comment_parent_row() finds no parent, so
// the post was refused above.
$sql_parent_column = '';
$sql_parent_value = '';

if (pg_comment_replies_ready()) {
    $sql_parent_column = ",
        parent_id";
    $sql_parent_value = ",
        '" . (int) $parent_id . "'";
}

// add comment to database
$query = 
    "INSERT INTO comments (
        page_id,
        item_id,
        item_type,
        name,
        message,
        file_id,
        published,
        rating,
        publish_date_and_time,
        publish_cancel,
        created_user_id,
        created_timestamp,
        last_modified_user_id,
        last_modified_timestamp$sql_review_column$sql_parent_column)
     VALUES (
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        UNIX_TIMESTAMP(),
        ?,
        UNIX_TIMESTAMP()$sql_review_value$sql_parent_value)";
$result = db_q($query, array((string) $page_id, (string) $item_id, (string) $item_type, (string) $name, (string) $message, (string) $file_id, (string) $published, (string) $rating, (string) $publish_date_and_time, (string) $publish_cancel, (string) $user_id, (string) $user_id)) or output_error('Query failed.');

// get new comment's id
$comment_id = mysqli_insert_id(db::$con);

// A published review of a product changes its rating at once.
if ($published == '1') {
    pg_review_comment_changed($item_type, $item_id);
}

// A review of a product, published or held, is announced as it is written.
if ($is_product_review && ($comment_id > 0)) {
    pg_announce('product.review.created', pg_review_created_event($comment_id, $item_type, $item_id, $rating, $verified_order_id, $published, $publish_date_and_time));
}

// If this comment was published then cancel any necessary scheduled comments,
// that are set to be cancelled if new comments are added.
if ($published == '1') {
    db(
        "UPDATE comments
        SET
            publish_date_and_time = '0000-00-00 00:00:00',
            publish_cancel = '0'
        WHERE
            (publish_cancel = '1')
            AND (page_id = '" . e($page_id) . "')
            AND (item_id = '" . e($item_id) . "')
            AND (item_type = '" . e($item_type) . "')");
}
// Creating a notification.
create_notification(array('action'=>'new_comment', 'type'=>'success', 'title'=>$message, 'send_to'=>urlencode($send_to), 'comment_id'=>$comment_id, 'user'=>$name));


// if the added comments session array is not initialized, then initialize it
if (isset($_SESSION['software']['added_comments']) == FALSE) {
    $_SESSION['software']['added_comments'] = array();
}

// if there is a comment id then add comment id to added comments array
if ($comment_id != '') {
    $_SESSION['software']['added_comments'][] = $comment_id;
}

// if comment is published, and if this is a form item view, then update the submitted_form_info table so that form list views can show comment info
if (($published == '1') && ($page_type == 'form item view')) {
    // Get the number of views so we do not lose that data when we delete record below.
    $number_of_views = db_value("SELECT number_of_views FROM submitted_form_info WHERE (submitted_form_id = '" . escape($item_id) . "') AND (page_id = '" . escape($page_id) . "')");

    // get the number of published comments for this submitted form and page
    $query =
        "SELECT COUNT(*)
        FROM comments
        WHERE
            (page_id = ?)
            AND (item_id = ?)
            AND (item_type = ?)
            AND (published = '1')";
    $result = db_q($query, array((string) $page_id, (string) $item_id, (string) $item_type)) or output_error('Query failed.');
    $row = mysqli_fetch_row($result);
    $number_of_comments = $row[0];
    
    // delete the current record if one exists
    $query = "DELETE FROM submitted_form_info WHERE (submitted_form_id = ?) AND (page_id = ?)";
    $result = db_q($query, array((string) $item_id, (string) $page_id)) or output_error('Query failed.');
    
    // insert a new record
    $query = 
        "INSERT INTO submitted_form_info (
            submitted_form_id,
            page_id,
            number_of_views,
            number_of_comments,
            newest_comment_id)
         VALUES (
            ?,
            ?,
            ?,
            ?,
            ?)";
    $result = db_q($query, array((string) $item_id, (string) $page_id, (string) $number_of_views, (string) $number_of_comments, (string) $comment_id)) or output_error('Query failed.');
}

// prepare to e-mail addresses for the administrator e-mail
$administrator_email_addresses = array();

// if there is a to e-mail address, then add it to the array
if ($comments_administrator_email_to_email_address != '') {
    $administrator_email_addresses[] = $comments_administrator_email_to_email_address;
}

// if the page type is form item view then get additional administrator e-mail addresses
if ($page_type == 'form item view') {
    // if e-mailing conditional administrators is enabled, then continue to check if there are conditional administrators that should be e-mailed
    if ($comments_administrator_email_conditional_administrators == 1) {
        // get all form field options that have conditional administrator e-mail addresses where the data that was submitted matches the option value
        $query = 
            "SELECT form_field_options.email_address
            FROM form_field_options
            LEFT JOIN form_data ON form_field_options.form_field_id = form_data.form_field_id
            WHERE
                (form_field_options.page_id = ?)
                AND (form_data.form_id = ?)
                AND (form_field_options.email_address != '')
                AND (form_data.data = form_field_options.value)";
        $result = db_q($query, array((string) $submitted_form['custom_form_page_id'], (string) $item_id)) or output_error('Query failed.');
        
        // loop through results and add them to array
        while($row = mysqli_fetch_assoc($result)) {
            // We support multiple conditional admin email addresses, separated by comma,
            // (e.g. ^^example1@example.com,example2@example.com^^), so deal with that.
            $conditional_email_addresses = explode(',', $row['email_address']);

            foreach ($conditional_email_addresses as $conditional_email_address) {
                $conditional_email_address = trim($conditional_email_address);

                // if this e-mail address has not already been added to the array, then add it
                if (in_array($conditional_email_address, $administrator_email_addresses) == false) {
                    $administrator_email_addresses[] = $conditional_email_address;
                }
            }
        }
    }
    
    // if a form editor e-mail address was found and it has not already been added, then add it
    if (($submitted_form['form_editor_email_address'] != '') && (in_array($submitted_form['form_editor_email_address'], $administrator_email_addresses) == FALSE)) {
        $administrator_email_addresses[] = $submitted_form['form_editor_email_address'];
    }
}

// if there is at least one administrator e-mail address to e-mail, then prepare to send an e-mail to the administrator
if (count($administrator_email_addresses) > 0) {

    $body = '';
    
    $output_submitter_name = '';
    
    // if the comment submitter is logged in, then prepare to include username next to the name
    if ((isset($_SESSION['sessionusername']) == true) && ($_SESSION['sessionusername'] != '')) {
        $submitter_name = '';
                
        // if the username is the same as the display name, then get the user's full name from their contact record
        if ($_SESSION['sessionusername'] == $name) {
            // get the user's contact info
            $query =
                "SELECT
                    contacts.first_name,
                    contacts.last_name
                FROM user
                LEFT JOIN contacts ON user.user_contact = contacts.id
                WHERE user.user_username = ?";
            $result = db_q($query, array((string) $_SESSION['sessionusername'])) or output_error('Query failed');
            $row = mysqli_fetch_assoc($result);
            $first_name = $row['first_name'];
            $last_name = $row['last_name'];
            
            // if the first name is not blank, then add it to the submitter name
            if ($first_name != '') {
                $submitter_name = $first_name;
            }
            
            // if the last name is not blank, then add it to the submitter name
            if ($last_name != '') {
                if ($first_name != '') {
                    $submitter_name .= ' ';
                }
                
                $submitter_name .= $last_name;
            }
        }
        
        // if the submitter name is blank, then set the username as the submitter's name
        if ($submitter_name == '') {
            $submitter_name = $_SESSION['sessionusername'];
        }
        
        // output the submitter name
        $output_submitter_name = ' (' . $submitter_name . ')';
    }
    
    $attachment_line = '';
    
    // if there is an attachment, then add line to e-mail content with link to attachment
    if ($file_id != 0) {
        $attachment_line =
            "\n" .
            lang('Attachment') . ': ' . URL_SCHEME . HOSTNAME_SETTING . PATH . encode_url_path($file_name) . ' (' . convert_bytes_to_string($file_size) . ')' . "\n";
    }
    
    // if comment was published, then build a message that informs the administrator
    if ($published == '1') {
        $subject = '';
        
        // if the administrator e-mail subject is not blank, then prepare it for output and set it to the e-mail subject
        if ($comments_administrator_email_subject != '') {
            // if the item is a submitted form, then prepare to replace variables in subject with submitted form data
            if ($item_type == 'submitted_form') {
                $subject = get_variable_submitted_form_data_for_content($page_id, $item_id, $comments_administrator_email_subject, $prepare_for_html = FALSE);
                
            // else the item is not a submitted form, so just set the subject
            } else {
                $subject = $comments_administrator_email_subject;
            }
        
        // else use the default subject
        } else {
            $subject = lang(array('string'=>'A {var:1} has been added and published.','vars'=>$comment_label_lowercase));
        }
        
        // set body message
        $body .=
            lang(array('string'=>'The following {var:1} has been added and published','vars'=>$comment_label_lowercase)) . ':' . "\n" .
            "\n" .
            lang('Display Name') . ': ' . $name . $output_submitter_name . "\n" .
            "\n" .
            pg_comment_parent_mail_line($comment_parent) .
            $comment_label . ': ' . $message . "\n" .
            $attachment_line;
            
    // else build a message that tells the administrator that they need to publish the comment
    } else {
        $subject = '';
        
        // if the administrator e-mail subject is not blank, then prepare it for output and set it to the e-mail subject
        if ($comments_administrator_email_subject != '') {
            // if the item is a submitted form, then prepare to replace variables in subject with submitted form data
            if ($item_type == 'submitted_form') {
                $subject = get_variable_submitted_form_data_for_content($page_id, $item_id, $comments_administrator_email_subject, $prepare_for_html = FALSE);
                
            // else the item is not a submitted form, so just set the subject
            } else {
                $subject = $comments_administrator_email_subject;
            }
        
        // else use the default subject
        } else {
            $subject = lang(array('string'=>'A {var:1} has been added, but it has not been published yet.','vars'=>$comment_label_lowercase));

        }

        $output_dashes = '';
        $output_schedule = '';

        // If this is a scheduled comment, then output info about that.
        if ($publish_date_and_time) {
            // Output a line of dashes above and below the schedule comment intro,
            // so that a moderator can quickly understand that the comment is
            // scheduled.  We use 78 dashes because that should be wider
            // than the intro line in most cases, depending on the comment label.
            // Many email clients will wrap plain-text email lines after 78 chars,
            // so we don't want to use more than that.  It is probably better visually,
            // to use more than necessary, instead of less.  We could count
            // the total characters in the comment label and intro line,
            // but we are not going to bother with that for now.
            // Many email clients don't use monospace for plain-text emails,
            // so regardless of what we do, this will not line up properly anyway for many people.

            $output_dashes = '------------------------------------------------------------------------------' . "\n";

            $output_schedule = lang(array('string'=>'It is scheduled to be published on {var:1}.','vars'=>get_absolute_time(array('timestamp' => strtotime($publish_date_and_time), 'format' => 'plain_text', 'timezone_type' => 'site')))) . "\n";

            // If the schedule is set to be cancelled if new comments are added,
            // then add more info about that.
            if ($publish_cancel) {
                $output_schedule .= lang(array('string'=>'However, it will not be published if a new {var:1} is added first.','vars'=>$comment_label_lowercase)) . "\n";

            }
            $output_action = lang(array('string'=>'The {var:1} can be edit with the link below.','vars'=>$comment_label_lowercase));

        } else {
            $output_action = lang(array('string'=>'The {var:1} can be publish with the link below.','vars'=>$comment_label_lowercase));
           
        }
        
        // set body message
        $body .=
            $output_dashes .
            lang(array('string'=>'The following {var:1} has been added, but it has not been published yet.','vars'=>$comment_label_lowercase)) . "\n" .
            $output_schedule .
            $output_dashes .
            "\n" .
            lang('Display Name') . ': ' . $name . $output_submitter_name . "\n" .
            "\n" .
            pg_comment_parent_mail_line($comment_parent) .
            $comment_label . ': ' . $message . "\n" .
            $attachment_line .
            "\n" .
            $output_action . "\n" .
            "\n" .
            URL_SCHEME . HOSTNAME_SETTING . PATH . SOFTWARE_DIRECTORY . '/edit_comment.php?id=' . $comment_id . "\n";
    }

    // Every comment shows, featured or not, and a comments widget opens the
    // page of the thread that holds this one (pg_comment_view_query()).
    $query_string = pg_comment_view_query(get_query_string_for_page_url($page_type, $item_id, $item_type), $comment_id);

    // build the view comment link
    $body .=
        "\n" .
        lang(array('string'=>'The {var:1} appears at the link below.','vars'=>$comment_label_lowercase)) . "\n" .
        "\n" .
        URL_SCHEME . HOSTNAME_SETTING . PATH . get_page_name($page_id) . $query_string . '#c-' . $comment_id;

    email(array(
        'to' => $administrator_email_addresses,
        'from_name' => ORGANIZATION_NAME,
        'from_email_address' => EMAIL_ADDRESS,
        'subject' => $subject,
        'body' => $body
    ));

}

// if comment is published, and if this is a form item view, and if there is a page to send
// then send e-mail to submitter letting them know a comment has been added
// (not when the one writing is the submitter: nobody needs an e-mail about
// their own reply)
if (($published == '1') && ($page_type == 'form item view') && ($comments_submitter_email_page_id != '0')
    && !(USER_LOGGED_IN && isset($submitted_form['submitter_user_id']) && ((int)$submitted_form['submitter_user_id'] > 0)
         && ((int)USER_ID === (int)$submitted_form['submitter_user_id']))) {
    send_comment_email_to_custom_form_submitter($comment_id);
}

// if comment is published, and if there is a page to send
// then send e-mail to watchers letting them know a comment has been added
if (($published == '1') && ($comments_watcher_email_page_id != '0')) {
    send_comment_email_to_watchers($comment_id);
}

// if comment watching is enabled and the user is logged in and the user selected to be added as a watcher,
// then add user as a watcher if he/she is not already one
if (
    ($comments_watcher_email_page_id != 0)
    && (isset($_SESSION['sessionusername']) == TRUE)
    && ($watcher == 1)
) {
    // check if the user is already a watcher
    $query = 
        "SELECT id
        FROM watchers
        WHERE
            (user_id = ?)
            AND (page_id = ?)
            AND (item_id = ?)
            AND (item_type = ?)";
    $result = db_q($query, array((string) $user_id, (string) $page_id, (string) $item_id, (string) $item_type)) or output_error('Query failed.');
    
    // if the user is not already a watcher, then add the user as a watcher
    if (mysqli_num_rows($result) == 0) {
        $query = 
            "INSERT INTO watchers (
                user_id,
                page_id,
                item_id,
                item_type)
             VALUES (
                ?,
                ?,
                ?,
                ?)";
        $result = db_q($query, array((string) $user_id, (string) $page_id, (string) $item_id, (string) $item_type)) or output_error('Query failed.');
    }
}

// Determine if there are featured comments in order to determine if we need to modify URL and add comments parameter.
$query =
    "SELECT COUNT(*)
    FROM comments
    WHERE
        (page_id = ?)
        AND (item_id = ?)
        AND (item_type = ?)
        AND (featured = '1')";
$result = db_q($query, array((string) $page_id, (string) $item_id, (string) $item_type)) or output_error('Query failed.');
$row = mysqli_fetch_row($result);

// Send the writer back to the new comment: the page they posted from,
// without the comment they answered and the page of the thread they were on,
// naming the new comment (a comments widget opens the page that holds it),
// and showing every comment while featured comments exist.
header('Location: ' . URL_SCHEME . HOSTNAME . pg_comment_return_url(pg_safe_redirect_path($send_to), $comment_id, ($row[0] > 0)) . '#c-' . $comment_id);

// remove liveform
$liveform->remove_form();
?>