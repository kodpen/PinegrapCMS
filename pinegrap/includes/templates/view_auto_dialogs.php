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
            <?=pg_screen_toolbar(array(
                'primary' => array('label' => lang('Create'), 'url' => 'add_auto_dialog.php', 'icon' => 'bi-plus-circle'),
                'search'  => array($output_filters, pg_list_table_search_box('view_auto_dialogs'), pg_list_sort_menu('view_auto_dialogs.php', $sort_columns, $sort_levels, $sort_default)),
            ))?>
            <div class="pg-figures" role="group" aria-label="<?=h(lang('Last 30 Days'))?>">
                <div class="pg-card pg-figure">
                    <span class="pg-figure-label"><?=lang('Enabled')?></span>
                    <span class="pg-figure-value"><?=h(pg_format_number($enabled_count, 0))?> <small>/ <?=h(pg_format_number(count($auto_dialogs), 0))?></small></span>
                    <span class="pg-figure-note"><?=lang('pop-ups open for visitors')?></span>
                </div>
                <?php foreach (array('shown' => lang('Times shown'), 'closed' => lang('Times closed'), 'taken' => lang('Times taken')) as $figure_event => $figure_label): ?>
                    <div class="pg-card pg-figure" data-pg-auto-dialog-total="<?=$figure_event?>">
                        <span class="pg-figure-label"><?=h($figure_label)?></span>
                        <span class="pg-figure-value"><?=h(pg_format_number($figure_total[$figure_event], 0))?></span>
                        <span class="pg-figure-note"><?=lang('last 30 days')?></span>
                    </div>
                <?php endforeach ?>
            </div>
            <?=pg_list_table_html($list['source'], $list['page'], lang('All Auto Dialogs'))?>
        </div>
    </div>
</main>
