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
include_once('liveform.class.php');
$liveform = new liveform('view_folders');
$user = validate_user();
validate_area_access($user, 'user');

print
pg_page_shell(
    array(
        'title'=> lang('Folders'),
        'extra classes'=>'folders',
        'icon'=>'folder', 
        'heading'=>lang('Folders'),
        
    )
) . '
<script src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/folder_tree.js?v=' . @filemtime(dirname(__FILE__) . '/assets/folder_tree.js') . '"></script>
    <script type="text/javascript">
        window.onload = init_folder_tree;
        var Loading = "' . lang('Loading') . '";
        var Access_Control = "' . lang('Access Control') . '";
        var Page_Style = "' . lang('Page Style') . '";
        var Page_Style_Override = "' . lang('Page Style Override') . '";
        var Public = "' . lang('Public') . '";
        var Guest = "' . lang('Guest') . '";
        var Private = "' . lang('Private') . '";
        var Registration = "' . lang('Registration') . '";
        var Membership = "' . lang('Membership') . '";
    </script>
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->get_warnings() . '
                ' . $liveform->output_notices() . '
                <div class="row mb-2  flex-wrap">
                    <div class="col-12 col-sm-12 col-md-6 col-xl-9 text-center text-md-start">
                        <h2 class="d-inline-block " data-bs-content="' . lang('All visible folders, including their pages & files.') . '" title="' . lang('My Folders') . '">' . lang('My Folders') . '</h2>
                        <nav id="button_bar" class="navigation " aria-label="Button Bar">
                            <a class="btn btn-sm btn-primary m-1 " href="add_folder.php" data-loading-content="' . lang(array('string'=>'Loading') ) . '"><span class="bi bi-plus-circle me-2"></span>' . lang(array('string'=>'Create') ) . '</a>
                        </nav>
                    </div>
                </div>
                <div class="card my-4">
                    <div class="card-header chart-buttons justify-content-end d-flex flex-wrap bg-reset border-0">
                        <span class="btn-group btn-group-sm flex-wrap"> 
                        <button type="button" onclick="update_folder_tree(0, expand_all = true)" class="btn btn-secondary btn-sm" tabindex="0"><span><i class="material-icons me-2">grid_view</i>' . lang('Expand All') . '</span></button>
                        <button type="button" onclick="collapse_folder_tree()" class="btn btn-secondary btn-sm" tabindex="0"><span><i class="material-icons me-2">window</i>' . lang('Collapse All') . '</span></button>
                    </div>
                    <div class="card-body p-0 position-relative">   
                        <div id="folder_tree">
                            <ul id="ul_0" style="margin: 0px; display: none"></ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>' . 
    output_footer();


$liveform->remove_form('view_folders');
?>
