<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// This file is a view partial. It is required from a page that has already run
// init.php and it depends on the constants and variables that bootstrap sets up.
// Requested directly over the web it runs with none of them and dies on the first
// constant it touches -- which is exactly how it turned up in the error log.
if (!defined('PG_INIT_LOADED')) {
    http_response_code(403);
    exit;
}
?>
<main id="content" class="container-fluid">
	<div class="row">
	    <div class="col-12">
			<?=$liveform->get_messages()?>
	        <div class="mb-3 text-center md:text-start">
                <h2 class="d-inline-block "><?=lang('All Key Codes')?></h2>
            </div>
            <?=pg_screen_toolbar(array(
                'primary'   => array('label' => lang('Create'), 'url' => 'add_key_code.php', 'icon' => 'bi-plus-circle'),
                'secondary' => array(
                    array('label' => lang('Import'), 'url' => 'import_key_codes.php', 'icon' => 'bi-box-arrow-in-right'),
                    '<form class="disable_shortcut" method="get"><button type="submit" name="submit_data" value="Export Key Codes" class="btn btn-sm btn-text theme-secondary"><i class="bi bi-file-earmark-arrow-down me-1" aria-hidden="true"></i>' . lang('Export') . '</button></form>',
                    '<a href="delete_key_codes.php" class="btn btn-sm btn-outline theme-warning"><i class="bi bi-trash me-1" aria-hidden="true"></i>' . lang('Delete All Key Codes') . '</a>',
                ),
                'search'    => array(pg_list_table_search_box('view_key_codes'), pg_list_sort_menu('view_key_codes.php', $sort_columns, $sort_levels, $sort_default)),
            ))?>
            <?=pg_list_table_html($list['source'], $list['page'], lang('All Key Codes'))?>
        </div>
    </div>
</main>