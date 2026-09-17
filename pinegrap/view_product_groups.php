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

// The product catalog runs on the file manager's shell.
//
// product_groups is a folder tree in everything but name -- parent_id,
// sort_order, name, image_name, seo_score -- and products hang off groups the
// way pages hang off folders, so the same toolbar, tree, grid, preview panel
// and context menu serve both.  Setting the area and including the screen is
// the whole of it: one implementation, two addresses, and a fix to either one
// lands in both.
//
// This replaced a classic nested-tree screen that never shipped outside
// development, so there is no second catalog address to keep working.
$explorer_area = 'catalog';

include('view_folders.php');
