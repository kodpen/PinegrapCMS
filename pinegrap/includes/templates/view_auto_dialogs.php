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
                <h2 class="d-inline-block "><?=lang('All Auto Dialogs')?></h2>
            </div>
            <?=pg_screen_toolbar(array(
                'primary' => array('label' => lang('Create'), 'url' => 'add_auto_dialog.php', 'icon' => 'bi-plus-circle'),
                'search'  => array(pg_list_table_search_box('view_auto_dialogs'), pg_list_sort_menu('view_auto_dialogs.php', $sort_columns, $sort_levels, $sort_default)),
            ))?>
            <?=pg_list_table_html($list['source'], $list['page'], lang('All Auto Dialogs'))?>
        </div>
    </div>
</main>
