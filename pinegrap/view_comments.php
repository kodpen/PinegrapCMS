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

include_once('liveform.class.php');
require_once(PG_FUNCTIONS_DIR . '/includes/forms/comment_form.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/comment_list_edit.php');

$quick_edit = pg_comment_quick_edit_columns();

// A value changed in place on the list (pg_list_edit_request() in
// includes/fn/output.php), answered in JSON before the screen is drawn.
// The rights are the comment screen's: validate_area_access() 'user' here,
// the page's folder in pg_comment_quick_update().
if (pg_list_edit_requested()) {
    $edit = pg_list_edit_request($quick_edit, function ($user) {
        return ((int) $user['role'] < 3) || no_acl_check($user['id']);
    });

    $saved = pg_comment_quick_update($edit['id'], $edit['field'], $edit['value'], $edit['user'], $quick_edit);

    pg_list_edit_reply($saved, ($saved['ok'] ?? false) ? 200 : (((($saved['code'] ?? '') === 'denied')) ? 403 : 422));
}

$user = validate_user();
validate_area_access($user, 'user');
$liveform = new liveform('view_comments');


// make sure the session values that this screen reads below actually exist
if (isset($_SESSION['software']['comments']) == false) {
    $_SESSION['software']['comments'] = array();
}

// The verified purchase filter: '' lists every comment, 'yes' the reviews of
// verified purchases, 'no' the rest. It is kept in the session like the
// search, so the sort links keep it; anything but the three choices lists
// every comment. Sites without the review columns have no filter.
$reviews_ready = pg_reviews_ready();
$verified = '';

if ($reviews_ready) {
    if (isset($_GET['verified'])) {
        $_SESSION['software']['comments']['verified'] =
            (is_string($_GET['verified']) && in_array($_GET['verified'], array('', 'yes', 'no'), true))
                ? $_GET['verified']
                : '';
    }

    $verified = (string) ($_SESSION['software']['comments']['verified'] ?? '');

    if (!in_array($verified, array('', 'yes', 'no'), true)) {
        $verified = '';
    }
}

// The product question filter (includes/fn/product_questions.php): ''
// lists every comment, 'questions' the questions, 'unanswered' those still
// waiting for an answer. Kept in the session like the verified filter; sites
// before 11.0.148 have no filter.
$questions_ready = pg_product_questions_ready();
$question_filter = '';

if ($questions_ready) {
    if (isset($_GET['questions'])) {
        $_SESSION['software']['comments']['questions'] =
            (is_string($_GET['questions']) && in_array($_GET['questions'], array('', 'questions', 'unanswered'), true))
                ? $_GET['questions']
                : '';
    }

    $question_filter = (string) ($_SESSION['software']['comments']['questions'] ?? '');

    if (!in_array($question_filter, array('', 'questions', 'unanswered'), true)) {
        $question_filter = '';
    }
}

$where = '';

// The columns this list sorts by, in the order the table shows them.
// pg_list_state() keeps the choice in the session as a code, so it survives
// a language change, and falls back to the default for anything else.
$sort_columns = array(
    'name'           => lang('Name'),
    'published'      => lang('Published'),
    'featured'       => lang('Featured'),
    'scheduled'      => lang('At a Scheduled Time'),
    'publish_cancel' => lang('Cancel if Added First'),
    'rating'         => lang('Rating'),
    'submitted'      => lang('Submitted'),
);

$sort_default = array('sort' => 'submitted', 'order' => 'desc');

$list_state = pg_list_state('view_comments', $sort_columns, $sort_default);
$sort_levels = $list_state['sorts'];

// What each sort code orders by. Only codes pg_list_state() let through
// reach pg_list_order_by(), and it writes nothing but these.
$order_by = pg_list_order_by($sort_levels, array(
    'name'           => 'name',
    'published'      => 'published',
    'featured'       => 'featured',
    'scheduled'      => 'publish_date_and_time',
    'publish_cancel' => 'publish_cancel',
    'rating'         => 'rating',
    'submitted'      => 'created_timestamp',
));

// The verified and question filters narrow the list together; the search
// box searches the printed rows in the browser.
$conditions = array();

$sql_verified = pg_review_verified_filter_sql($verified);

if ($sql_verified !== '') {
    $conditions[] = '(' . $sql_verified . ')';
}

$sql_questions = pg_product_question_filter_sql($question_filter, $questions_ready);

if ($sql_questions !== '') {
    $conditions[] = '(' . $sql_questions . ')';
}

if ($conditions) {
    $where .= 'WHERE ' . implode(' AND ', $conditions);
}



$comments = (array) db_items(pg_comment_list_sql($where, 0, $reviews_ready, $questions_ready) . " ORDER BY " . $order_by);

// Folders of the Recycle Bin: a comment on a page there gets no link to the
// site (the page is not on it) and a badge saying where its page is.
$bin_folders = array_flip(array_map('intval', (array) pg_recycle_bin_folder_ids()));

// Every comment the user may edit is printed: the list table driver sorts
// and searches them in the browser (browser mode) and keeps a heading's sort
// in the session.
$list_rows = array();

foreach ($comments as $comment) {
    // Only comments on pages in folders the user can edit.
    if (check_edit_access($comment['page_folder']) != true) {
        continue;
    }

    $list_rows[] = array('id' => $comment['id'], 'cells' => pg_comment_list_cells($comment, $quick_edit, $bin_folders));
}

$list = pg_list_table_printed(array(
    'list' => 'view_comments',
    'columns' => pg_comment_list_columns(true),
    'levels' => $sort_levels,
    'rows' => $list_rows,
    'edit' => array('url' => 'view_comments.php', 'columns' => $quick_edit),
));


// The verified filter in the toolbar's filter slot. It submits
// ?verified=yes|no, and ?verified= for all comments.
$output_verified_filter = '';

if ($reviews_ready) {
    $output_verified_filter = pg_filter_select('verified', array(
        ''    => lang('All purchase statuses'),
        'yes' => lang('Verified purchases'),
        'no'  => lang('Not verified'),
    ), $verified, array('label' => lang('Verified purchase'), 'action' => 'view_comments.php', 'id' => 'verified'));
}

// The question filter beside it; its unanswered choice carries the count
// of questions waiting on the pages this user may edit.
$output_question_filter = '';

if ($questions_ready) {
    $unanswered_count = pg_product_questions_unanswered_count((USER_ROLE < 3) ? null : get_folders_that_user_has_access_to(USER_ID));
    $output_question_filter = pg_filter_select('questions', array(
        ''           => lang('All comments and questions'),
        'questions'  => lang('Product questions'),
        'unanswered' => lang(array('string' => 'Unanswered questions ({var:1})', 'vars' => pg_format_number($unanswered_count, 0))),
    ), $question_filter, array('label' => lang('Product questions'), 'action' => 'view_comments.php', 'id' => 'questions'));
}

print
    pg_page_shell(
        array(
            'title'=> lang('Comments'),
            'extra classes'=>'page',
            'icon'=>'page', 
            'heading'=>lang('Comments'),
            'heading_description' => lang('All site-wide comments and reviews that I can edit.'),

        )
    ) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '
            ' . pg_screen_toolbar(array(
                'search' => array(pg_list_table_search_box('view_comments'), pg_list_sort_menu('view_comments.php', $sort_columns, $sort_levels, $sort_default), $output_verified_filter, $output_question_filter),
            )) . '
            <div class="disable_shortcut">
                ' . pg_list_table_html($list['source'], $list['page'], lang('Comments')) . '
            </div>
        </div>
    </div>
</main>
' . pg_js_bundle_tags('assets/js/pg_list_table.min.js') . '
' .
output_footer();
$liveform->remove_form('view_comments');