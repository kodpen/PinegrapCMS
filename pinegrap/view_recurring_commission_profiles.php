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
validate_ecommerce_access($user);

include_once('liveform.class.php');
$liveform = new liveform('view_recurring_commission_profiles');

require_once(PG_FUNCTIONS_DIR . '/includes/fn/commission_list.php');

// The list table driver prints the first rows; the next ones come from its
// list_table_rows action (includes/panel/lists.php) as the list scrolls.
$list_sort = pg_commission_profile_list_sort();
$sort_columns = $list_sort['sort_columns'];
$sort_default = $list_sort['sort_default'];
$sort_levels = $list_sort['sort_levels'];

$list_source = pg_commission_profile_list_source($user, array());

if ($list_source === null) {
    output_error(lang('Access denied'));
}

$list_page = pg_list_table_page($list_source, array(), null);

if ($list_page === false) {
    output_error(lang('Query failed.'));
}

print
pg_page_shell([
        'title'=> lang('All Commission Profiles'),
        'extra classes'=>'products pg-measure-list',
        'icon'=>'store',
        'heading'=>lang('All Commission Profiles'),
        'heading_description' => lang('View all profiles for recurring commissions.'),
    ]) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '
           
            ' . pg_screen_toolbar(array(
                'search' => array(
                    '<label class="form-control form-control-sm form-adorn rounded-pill pg-toolbar-search"><span class="form-adorn-text" aria-hidden="true"><i class="bi bi-search"></i></span><input type="search" class="form-ghost" data-pg-list-search="view_recurring_commission_profiles" placeholder="' . h(lang('Search')) . '" aria-label="' . h(lang('Search')) . '"></label>',
                    pg_list_sort_menu('view_recurring_commission_profiles.php', $sort_columns, $sort_levels, $sort_default),
                ),
            )) . '
            ' . pg_list_table_html($list_source, $list_page, lang('All Commission Profiles')) . '
        </div>
    </div>
</main>
' . pg_js_bundle_tags('assets/js/pg_list_table.min.js') . '
' .
output_footer();

$liveform->remove_form();
?>