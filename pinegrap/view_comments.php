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
 *              2016–2026 Kodpen
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

foreach (array('sort', 'order', 'query') as $comments_session_key) {
    if (isset($_SESSION['software']['comments'][$comments_session_key]) == false) {
        $_SESSION['software']['comments'][$comments_session_key] = '';
    }
}

// these are only ever appended to further below, so they have to start out empty
$where = '';
$output_rows = '';
$number_of_results = 0;

// if sort was set, update session
if (isset($_REQUEST['sort'])) {
    // store sort in session
    $_SESSION['software']['comments']['sort'] = $_REQUEST['sort'];

    // clear order
    $_SESSION['software']['comments']['order'] = '';
}

// if order was set, update session
if (isset($_REQUEST['order'])) {
    // store sort in session
    $_SESSION['software']['comments']['order'] = $_REQUEST['order'];
}

// if the sort is not set yet (first visit to this screen), then default it to empty so the
// switch below falls through to its default case
if (isset($_SESSION['software']['comments']['sort']) == false) {
    $_SESSION['software']['comments']['sort'] = '';
}

switch (($_SESSION['software']['comments']['sort'] ?? ''))
{
    case lang('Name'):
        $sort_column = 'name';
        break;

    case lang('Published'):
        $sort_column = 'published';
        break;
    case lang('Featured'):
        $sort_column = 'featured';
        break;
    case lang('Cancel if Added First'):
        $sort_column = 'publish_cancel';
        break;
    case lang('Submitted'):
        $sort_column = 'created_timestamp';
        break;
    default:
        $sort_column = 'created_timestamp';
        $_SESSION['software']['comments']['sort'] = lang('Submitted');
}

if (!empty($_SESSION['software']['comments']['order'])) {
    $asc_desc = ($_SESSION['software']['comments']['order'] ?? '');
} elseif ($sort_column == 'created_timestamp') {
    $asc_desc = 'desc';
    $_SESSION['software']['comments']['order'] = 'desc';
} else {
    $asc_desc = 'asc';
    $_SESSION['software']['comments']['order'] = 'asc';
}



// get total number of comments
$query = "SELECT COUNT(page_id) FROM comments";
$result = mysqli_query(db::$con, $query) or output_error('Query failed.');
$row = mysqli_fetch_row($result);
$all_comments = $row[0];


$search_query = mb_strtolower(($_SESSION['software']['comments']['query'] ?? ''));

// create where clause for sql
$sql_search = "(LOWER(CONCAT_WS(',',  comments.name,comments.message,user.user_username)) LIKE '%" . escape($search_query) . "%')";

if (($_SESSION['software']['comments']['query'] ?? '') != '') {
    // Get only the results the user wanted in the search.
    $where .= "WHERE $sql_search";
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
        comments.created_timestamp
    FROM comments
    LEFT JOIN forms ON forms.id = comments.item_id
    LEFT JOIN product_groups ON product_groups.id = comments.item_id
    LEFT JOIN products ON products.id = comments.item_id
    LEFT JOIN files ON comments.file_id = files.id
    LEFT JOIN page ON page.page_id = comments.page_id
    LEFT JOIN user ON comments.created_user_id = user.user_id
    $where
    ORDER BY $sort_column $asc_desc";

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
                $output_reference_button = '<a class="m-1 btn-data-control btn btn-outline-secondary border-2 " href="' . OUTPUT_PATH . get_page_name($comment['page_id']) . '?r=' . $form_reference_code . '#c-' . $comment['id'] . '" title="' . lang('Go To Comment') . '"><i class="bi bi-link"></i></a>';
            }

        }else if($comment['item_type'] === 'product_group'){
            //if this is product group check address name from product groups
            $product_group_address_name = $comment['product_group_address_name'];
            if($product_group_address_name != ''){
                $output_reference_button = '<a class="m-1 btn-data-control btn btn-outline-secondary border-2 " href="' . OUTPUT_PATH . get_page_name($comment['page_id']) . '/' . $product_group_address_name . '#c-' . $comment['id'] . '" title="' . lang('Go To Comment') . '"><i class="bi bi-link"></i></a>';
            }
        
        }else if($comment['item_type'] === 'product'){
            //if this is product group check address name from product groups
            $product_address_name = $comment['product_address_name'];
            if($product_address_name != ''){
                $output_reference_button = '<a class="m-1 btn-data-control btn btn-outline-secondary border-2 " href="' . OUTPUT_PATH . get_page_name($comment['page_id']) . '/' . $product_address_name . '#c-' . $comment['id'] . '" title="' . lang('Go To Comment') . '"><i class="bi bi-link"></i></a>';
            }
        }else if($comment['item_type'] === ''){
            $output_reference_button = '<a class="m-1 btn-data-control btn btn-outline-secondary border-2 " href="' . OUTPUT_PATH . get_page_name($comment['page_id']) . '#c-' . $comment['id'] . '"><i class="bi bi-link" title="' . lang('Go To Comment') . '"></i></a>';
        }






        $number_of_results++;

        


        $output_rows .= '
            <tr>
                <td class="align-middle text-start action-buttons">
                    <button type="button" class="m-1 btn-data-control btn btn-outline-primary border-2 " data-loading-content=" " title="' . lang('Edit') . '" onclick="window.location.href=\'' . $output_link_url . '\'"><i class="bi bi-pencil"></i></button>
                    ' . $output_reference_button . '
                </td>
                <td class="align-middle chart_label">' . h($comment['name']) . '</td>
                <td class="align-middle text-center">' . $output_published_check_mark . '</td>
                <td class="align-middle text-center">' . $output_featured_check_mark . '</td>
                <td class="align-middle"><span class="badge bg-primary fw-light">'.$output_date_and_time.'</span></td>
                <td class="align-middle text-center">' . $output_publishcancel_check_mark . '</td>
                <td class="align-middle"><p>' . h(truncate($comment['message'], 84, $dots = "...")) . '</p></td>
                <td class="align-middle">' . get_relative_time(array('timestamp' =>  $comment['created_timestamp'])) . $created_username . '</td>
            </tr>';
    }
        
}


print
    pg_page_shell(
        array(
            'title'=> lang('Comments'),
            'extra classes'=>'page',
            'icon'=>'page', 
            'heading'=>lang('Comments'),

        )
    ) . '
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '
            <div class="row mb-2  flex-wrap">
                <div class="col-12 text-center text-md-start">
                    <h2 class="d-inline-block " data-bs-content="' . lang('All site-wide comments and reviews that I can edit.') . '" title="' . lang('Comments') . '">' . lang('Comments') . '</h2>
                </div>
            </div>
            <form action="delete_comment.php" method="post" class="disable_shortcut">
                ' . get_token_field() . '
                <div class="card my-4">
                    <div class="card-body p-0 position-relative">
                        <table class="chart table-hover table " style="width:100%;display:none">
                            <thead>
                                <tr>
                                    <th class="noVis">' . lang(array('string'=>'Action') ) . '</th>
                                    <th>' . get_column_heading(lang('Name'), ($_SESSION['software']['comments']['sort'] ?? ''), ($_SESSION['software']['comments']['order'] ?? '')) . '</th>
                                    <th>' . get_column_heading(lang('Published'), ($_SESSION['software']['comments']['sort'] ?? ''), ($_SESSION['software']['comments']['order'] ?? '')) . '</th>
                                    <th>' . get_column_heading(lang('Featured'), ($_SESSION['software']['comments']['sort'] ?? ''), ($_SESSION['software']['comments']['order'] ?? '')) . '</th>
                                    <th>' . lang('At a Scheduled Time') . '</th>
                                    <th>' . get_column_heading(lang('Cancel if Added First'), ($_SESSION['software']['comments']['sort'] ?? ''), ($_SESSION['software']['comments']['order'] ?? '')) . '</th>
                                    <th>' . lang('Message') . '</th>
                                    <th>' . get_column_heading(lang('Submitted'), ($_SESSION['software']['comments']['sort'] ?? ''), ($_SESSION['software']['comments']['order'] ?? '')) . '</th>
                                </tr>
                            </thead>
                            <tbody>
                                ' . $output_rows . '
                            </tbody>
                        </table>
                    </div>
                </div>
            </form>
        </div>
    </div>
</main>' .
output_footer();
$liveform->remove_form('view_comments');