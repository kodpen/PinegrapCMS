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
require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');
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



$query = 
    "SELECT
        comments.id,
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
        page.page_type,
        page.page_folder,
        page.comments_submitter_email_page_id,
        page.comments_watcher_email_page_id,
        page.comments_rating as comments_rating,
        user.user_username as created_username,
        forms.reference_code as reference_code,
        product_groups.address_name as product_group_address_name,
        products.address_name as product_address_name,
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
    $where
    ORDER BY $order_by";

$result = mysqli_query(db::$con, $query) or output_error('Query failed.');

$comments = array();
// Loop through the results
while ($row = mysqli_fetch_assoc($result)) {
    $comments[] = $row;
}

// Folders of the Recycle Bin: a comment on a page there gets no link to the
// site (the page is not on it) and a badge saying where its page is.
$bin_folders = array_flip(array_map('intval', (array) pg_recycle_bin_folder_ids()));

// Every comment the user may edit is printed: the list table driver sorts
// and searches them in the browser (browser mode) and keeps a heading's sort
// in the session.
$list_rows = array();

foreach ($comments as $comment) {
    $folder_id = $comment['page_folder'];
    $binned = isset($bin_folders[(int) $comment['page_folder']]);

    // Only comments on pages in folders the user can edit.
    if (check_edit_access($folder_id) != true) {
        continue;
    }

    $output_date_and_time = '';

    if ($comment['publish_date_and_time'] > 0 && $comment['publish_date_and_time'] != '0000-00-00 00:00:00') {
        // In the site's date and time format, as Edit Comment shows it.
        $output_date_and_time = h(prepare_form_data_for_output($comment['publish_date_and_time'], 'date and time', false));
    }

    // The comment's place on the site, when it has one.
    // A page in the Recycle Bin is not on the site: no address to go to.
    $comment_url = '';
    $comment_path = $binned ? '' : pg_comment_site_path(get_page_name($comment['page_id']), $comment);

    if ($comment_path !== '') {
        $comment_url = OUTPUT_PATH . $comment_path;
    }

    $output_reference_button = ($comment_url === '') ? '' : '<a class="btn btn-sm btn-text theme-secondary" href="' . h($comment_url . '#c-' . $comment['id']) . '" title="' . h(lang('Go To Comment')) . '" aria-label="' . h(lang('Go To Comment')) . '"><i class="bi bi-link" aria-hidden="true"></i></a>';

    $output_name = pg_comment_list_name($comment['name'])
        . ((int) $comment['verified_order_id'] > 0 ? ' <span class="badge theme-success fw-light">' . h(lang('Verified purchase')) . '</span>' : '')
        . (($comment['kind'] ?? 'comment') !== 'comment' ? ' <span class="badge ' . ((($comment['kind'] === 'question') && ((int) $comment['answer_count'] === 0)) ? 'theme-warning' : 'theme-secondary') . ' fw-light">' . h(pg_product_question_badge($comment['kind'], (int) $comment['answer_count'] > 0)) . '</span>' : '')
        . ($binned ? ' <span class="badge theme-secondary fw-light">' . h(lang('In the Recycle Bin')) . '</span>' : '');

    $list_rows[] = array(
        'id' => $comment['id'],
        'cells' => array(
            'name' => pg_list_cell('<a href="edit_comment.php?id=' . (int) $comment['id'] . '" data-pg-open>' . $output_name . '</a>'),
            'published' => pg_list_table_check_cell($comment['published'] == '1'),
            'featured' => pg_list_table_check_cell($comment['featured'] == '1'),
            'scheduled' => pg_list_cell(($output_date_and_time === '') ? '' : '<span class="badge theme-primary fw-light">' . $output_date_and_time . '</span>', 'align-middle', array('data-pg-sort-value' => ($output_date_and_time === '') ? '' : (string) $comment['publish_date_and_time'])),
            'publish_cancel' => pg_list_table_check_cell($comment['publish_cancel'] == '1'),
            'message' => pg_list_cell((pg_comment_file_is_image($comment['file_name'] ?? '') ? '<img src="' . h(OUTPUT_PATH . encode_url_path($comment['file_name'])) . '" alt="' . h(lang('Photo')) . '" loading="lazy" class="img-thumbnail float-start me-2" style="width: 50px; height: 50px; object-fit: cover">' : '') . '<p>' . h(truncate($comment['message'], 84, $dots = "...")) . '</p>'),
            'submitted' => pg_list_table_when_cell($comment['created_timestamp'], $comment['created_username'] ?? ''),
            'go' => pg_list_cell($output_reference_button),
        ),
    );
}

$list = pg_list_table_printed(array(
    'list' => 'view_comments',
    'columns' => array(
        'name' => array('heading' => lang('Name')),
        'published' => array('heading' => lang('Published'), 'kind' => 'check'),
        'featured' => array('heading' => lang('Featured'), 'kind' => 'check'),
        'scheduled' => array('heading' => lang('At a Scheduled Time'), 'kind' => 'date'),
        'publish_cancel' => array('heading' => lang('Cancel if Added First'), 'kind' => 'check'),
        'message' => array('heading' => lang('Message'), 'sortable' => false),
        'submitted' => array('heading' => lang('Submitted'), 'kind' => 'date'),
        'go' => array('heading' => lang('Go To Comment'), 'kind' => 'actions'),
    ),
    'levels' => $sort_levels,
    'rows' => $list_rows,
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