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
$liveform = new liveform('view_comments');


// make sure the session values that this screen reads below actually exist
if (isset($_SESSION['software']['comments']) == false) {
    $_SESSION['software']['comments'] = array();
}

if (isset($_SESSION['software']['comments']['query']) == false) {
    $_SESSION['software']['comments']['query'] = '';
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

// these are only ever appended to further below, so they have to start out empty
$where = '';
$output_rows = '';
$number_of_results = 0;

// The columns this list sorts by, in the order the table shows them.
// pg_list_state() keeps the choice in the session as a code, so it survives
// a language change, and falls back to the default for anything else.
$sort_columns = array(
    'name'           => lang('Name'),
    'published'      => lang('Published'),
    'featured'       => lang('Featured'),
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
    'publish_cancel' => 'publish_cancel',
    'submitted'      => 'created_timestamp',
));

// get total number of comments
$query = "SELECT COUNT(page_id) FROM comments";
$result = mysqli_query(db::$con, $query) or output_error('Query failed.');
$row = mysqli_fetch_row($result);
$all_comments = $row[0];


$search_query = mb_strtolower(($_SESSION['software']['comments']['query'] ?? ''));

// create where clause for sql
$sql_search = "(LOWER(CONCAT_WS(',',  comments.name,comments.message,user.user_username)) LIKE '" . escape(pg_list_like_pattern($search_query)) . "')";

// The search and the verified filter narrow the list together.
$conditions = array();

if (($_SESSION['software']['comments']['query'] ?? '') != '') {
    // Get only the results the user wanted in the search.
    $conditions[] = $sql_search;
}

$sql_verified = pg_review_verified_filter_sql($verified);

if ($sql_verified !== '') {
    $conditions[] = '(' . $sql_verified . ')';
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

// loop through styles in order to prepare to output them
foreach ($comments as $comment) {
    // if user does not have access then output error



    $folder_id = $comment['page_folder'];


    // Set checkmark in variable
    $output_checkmark = '<span class="material-icons">task_alt</span>';

    // if user can access folder of page that comment published.
    if (check_edit_access($folder_id) == true) {
    

        $output_link_url = 'edit_comment.php?id=' . $comment['id'];

        // if comment published, then prepare to output check mark image
        $output_published_check_mark='';
        if ($comment['published'] == '1') {
            $output_published_check_mark = $output_checkmark;
        }   

        // if comment featured, then prepare to output check mark image
        $output_featured_check_mark='';
        if ($comment['featured'] == '1') {
            $output_featured_check_mark = $output_checkmark;
        }   

        // if publish cancel, then prepare to output check mark image
        $output_publishcancel_check_mark='';
        if ($comment['publish_cancel'] == '1') {
            $output_publishcancel_check_mark = $output_checkmark;
        }  

        $created_username = '';
        
        if ($comment['created_username']) {
            $created_username = ' ' . lang(array('string'=>'by {var:1}','vars'=>array( h($comment['created_username']) ) ) );
        }else{
            $created_username = ' ' . lang(array('string'=>'by [{var:1}]','vars'=>lang('Unknown') ) );
        }
        
        $output_date_and_time ='';
        if($comment['publish_date_and_time'] > 0 && $comment['publish_date_and_time'] != '0000-00-00 00:00:00'){
            $output_date_and_time =h($comment['publish_date_and_time']);
        }



        
        $output_reference_button = '';
        $form_reference_code = '';
        if($comment['item_type'] === 'submitted_form'){
            //if this is custom page check referance code from forms
            $form_reference_code = $comment['reference_code'];
            if($form_reference_code != ''){
                $output_reference_button = '<a class="m-1 btn-data-control btn btn-outline theme-secondary border-2 " href="' . h(OUTPUT_PATH . get_page_name($comment['page_id']) . pg_comment_view_query('?r=' . $form_reference_code, $comment['id']) . '#c-' . $comment['id']) . '" title="' . lang('Go To Comment') . '"><i class="bi bi-link"></i></a>';
            }

        }else if($comment['item_type'] === 'product_group'){
            //if this is product group check address name from product groups
            $product_group_address_name = $comment['product_group_address_name'];
            if($product_group_address_name != ''){
                $output_reference_button = '<a class="m-1 btn-data-control btn btn-outline theme-secondary border-2 " href="' . h(OUTPUT_PATH . get_page_name($comment['page_id']) . '/' . $product_group_address_name . pg_comment_view_query('', $comment['id']) . '#c-' . $comment['id']) . '" title="' . lang('Go To Comment') . '"><i class="bi bi-link"></i></a>';
            }
        
        }else if($comment['item_type'] === 'product'){
            //if this is product group check address name from product groups
            $product_address_name = $comment['product_address_name'];
            if($product_address_name != ''){
                $output_reference_button = '<a class="m-1 btn-data-control btn btn-outline theme-secondary border-2 " href="' . h(OUTPUT_PATH . get_page_name($comment['page_id']) . '/' . $product_address_name . pg_comment_view_query('', $comment['id']) . '#c-' . $comment['id']) . '" title="' . lang('Go To Comment') . '"><i class="bi bi-link"></i></a>';
            }
        }else if($comment['item_type'] === ''){
            $output_reference_button = '<a class="m-1 btn-data-control btn btn-outline theme-secondary border-2 " href="' . h(OUTPUT_PATH . get_page_name($comment['page_id']) . pg_comment_view_query('', $comment['id']) . '#c-' . $comment['id']) . '"><i class="bi bi-link" title="' . lang('Go To Comment') . '"></i></a>';
        }






        $number_of_results++;

        


        $output_rows .= '
            <tr>
                <td class="align-middle text-start action-buttons">
                    <button type="button" class="m-1 btn-data-control btn btn-outline theme-primary border-2 " title="' . lang('Edit') . '" onclick="window.location.href=\'' . $output_link_url . '\'"><i class="bi bi-pencil"></i></button>
                    ' . $output_reference_button . '
                </td>
                <td class="align-middle chart_label">' . h(pg_comment_display_name($comment['name'])) . ((int) $comment['verified_order_id'] > 0 ? ' <span class="badge theme-success fw-light">' . h(lang('Verified purchase')) . '</span>' : '') . '</td>
                <td class="align-middle text-center">' . $output_published_check_mark . '</td>
                <td class="align-middle text-center">' . $output_featured_check_mark . '</td>
                <td class="align-middle"><span class="badge theme-primary fw-light">'.$output_date_and_time.'</span></td>
                <td class="align-middle text-center">' . $output_publishcancel_check_mark . '</td>
                <td class="align-middle"><p>' . h(truncate($comment['message'], 84, $dots = "...")) . '</p></td>
                <td class="align-middle">' . get_relative_time(array('timestamp' =>  $comment['created_timestamp'])) . $created_username . '</td>
            </tr>';
    }
        
}


// The verified filter in the toolbar's filter slot. It submits
// ?verified=yes|no, and ?verified= for all comments.
$output_verified_filter = '';

if ($reviews_ready) {
    $output_verified_filter = pg_filter_select('verified', array(
        ''    => lang('All comments'),
        'yes' => lang('Verified purchases'),
        'no'  => lang('Not verified'),
    ), $verified, array('label' => lang('Verified purchase'), 'action' => 'view_comments.php', 'id' => 'verified'));
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
            <div class="row mb-3  flex-wrap">
                <div class="col-12 text-center md:text-start">
                    <nav id="button_bar" class="navigation " aria-label="Button Bar">
                        <div class="pg-toolbar-grow"></div>
                        ' . pg_list_sort_menu('view_comments.php', $sort_columns, $sort_levels, $sort_default) . '
                        ' . $output_verified_filter . '
                    </nav>
                </div>
            </div>
            <div class="disable_shortcut">
                <div class="card my-7">
                    <div class="card-body p-0 position-relative">
                        <table class="chart table-hover table " style="width:100%;display:none">
                            <thead>
                                <tr>
                                    <th class="noVis">' . lang(array('string'=>'Action') ) . '</th>
                                    <th>' . get_column_heading(lang('Name'), $sort_levels, '', '', 'name') . '</th>
                                    <th>' . get_column_heading(lang('Published'), $sort_levels, '', '', 'published') . '</th>
                                    <th>' . get_column_heading(lang('Featured'), $sort_levels, '', '', 'featured') . '</th>
                                    <th>' . lang('At a Scheduled Time') . '</th>
                                    <th>' . get_column_heading(lang('Cancel if Added First'), $sort_levels, '', '', 'publish_cancel') . '</th>
                                    <th>' . lang('Message') . '</th>
                                    <th>' . get_column_heading(lang('Submitted'), $sort_levels, '', '', 'submitted') . '</th>
                                </tr>
                            </thead>
                            <tbody>
                                ' . $output_rows . '
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
' .
output_footer();
$liveform->remove_form('view_comments');