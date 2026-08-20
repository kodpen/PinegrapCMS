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

$liveform = new liveform('view_data');
$user = validate_user();
validate_ecommerce_access($user);

$output = '
<script type="text/javascript" src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/data.js?v=' . @filemtime(dirname(__FILE__) . '/assets/data.js') . '"></script>
<div class="row">
        <div class="col-12" id="content"></div>
    </div>
</main>
<script>
    view_products({
        "id": "content",
        "headers_url": "data_api.php?action=GETProductListSetup&token=' . $_SESSION['software']['token'] . '",
        "rows_url": "data_api.php?action=GETProductListSetup&token=' . $_SESSION['software']['token'] . '",
        "buttons": [
            {
                "name": "item_edit",
                "title": "' . lang('Edit') . '",
                "urls": "edit_product.php?id=",
                "icon": "bi bi-pencil"
            },
            {
                "name": "item_more",
                "buttons": [
                    {
                        "name": "item_delete",
                        "urls": "delete_product.php?id=",
                        "icon": "bi bi-trash",
                        "confirm": {
                            "type": "warning",
                            "message": "Confirm Message Here",
                            "cancel": true
                        }
                    }
                ]
            }
        ]
    });
</script>';

print
    pg_page_shell([
        'title'=> lang('Products Development'),
        'extra classes'=>'products',
        'icon'=>'store',
        'heading'=>lang('Products Development')
    ]) . $output . output_footer();
