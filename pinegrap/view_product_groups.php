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
validate_ecommerce_access($user);

include_once('liveform.class.php');
$liveform = new liveform('view_product_groups');

// output product group tree screen
echo
pg_page_shell([
        'title'=> lang('All Product Groups'),
        'extra classes'=>'products',
        'icon'=>'store',
        'heading'=>lang('All Product Groups')
    ]) . '
    <script src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/folder_tree.js?v=' . @filemtime(dirname(__FILE__) . '/assets/folder_tree.js') . '"></script>
    <script type="text/javascript">
        window.onload = init_product_group_tree;
        var Loading = "' . lang('Loading') . '";
        var Disable = "' . lang('Disable This') . '";
        var Enable = "' . lang('Enable This') . '";
    </script>
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->get_warnings() . '
                ' . $liveform->output_notices() . '
                <div class="row mb-2  flex-wrap">
                    <div class="col-12 col-sm-12 col-md-6 col-xl-9 text-center text-md-start">
                        <h2 class="d-inline-block " data-bs-content="' . lang('Organize all products and product groups for publishing.') . '" title="' . lang('All Product Groups') . '">' . lang('All Product Groups') . '</h2>
                        <nav id="button_bar" class="navigation " aria-label="Button Bar">
                            <a class="btn btn-sm btn-primary m-1 " href="add_product_group.php" data-loading-content="' . lang(array('string'=>'Loading') ) . '"><span class="bi bi-plus-circle me-2"></span>' . lang(array('string'=>'Create') ) . '</a>
                        </nav>
                    </div>
                </div>
                <div class="card my-4">
                    <div class="card-header chart-buttons justify-content-end d-flex flex-wrap border-0 bg-reset">
                        <span class="btn-group btn-group-sm flex-wrap"> 
                        <button type="button" onclick="update_product_group_tree(0, expand_all = true)" class="btn btn-secondary btn-sm" tabindex="0"><span><i class="bi bi-folder2-open me-2"></i>' . lang('Expand All') . '</span></button>
                        <button type="button" onclick="collapse_product_group_tree()" class="btn btn-secondary btn-sm" tabindex="0"><span><i class="bi bi-folder2 me-2"></i>' . lang('Collapse All') . '</span></button>
                       
                    </div>
                    <div class="card-body p-0 position-relative">   
                        <div id="product_group_tree">
                            <ul id="ul_0" style="margin: 0px; display: none"></ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>' .
    output_footer();
    
$liveform->remove_form('view_product_groups');
?>