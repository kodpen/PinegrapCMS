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
validate_area_access($user, 'designer');

$form = new liveform('generate_layout');

$page = db_item("SELECT page_name AS name FROM page WHERE page_id = '" . e($_GET['page_id']) . "'");

require('generate_layout_content.php');

$form->set('layout', "\n" . generate_layout_content($_GET['page_id']));

echo pg_page_shell([
        'title'=> lang('Generate Layout'),
        'extra classes'=>'design',
        'icon'=>'design',
        'heading'=>lang('Generate Layout'),
        'cancel'=>true,
        'auto_main'=>false,
    ]);

require('assets/templates/generate_layout.php');

echo output_footer();

$form->remove();