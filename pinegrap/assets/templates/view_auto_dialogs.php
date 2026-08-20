<main id="content" class="container">
	<div class="row">
	    <div class="col-12">
			<?=$liveform->get_messages()?>
	        <div class="row mb-2 flex-wrap">
                <div class="col-12 col-sm-12 text-center text-md-start">
                    <h2 class="d-inline-block " data-bs-content="<?=lang('All dialogs that can automatically popup for visitors.')?>" title="<?=lang('All Auto Dialogs')?>"><?=lang('All Auto Dialogs')?></h2>
                    <nav id="button_bar" class="navigation " aria-label="Button Bar">
                    	<a class="btn btn-sm btn-primary m-1 " href="add_auto_dialog.php" data-loading-content="<?=lang(array('string'=>'Loading') )?>"><span class="bi bi-plus-circle me-2"></span><?=lang('Create')?></a>
                    </nav>
                </div>
            </div>
            <div class="card my-4">
                <div class="card-body p-0 position-relative disable_shortcut">
					<table class="chart table-hover table " style="width:100%;display:none">
                        <thead>
                            <tr>
								<th class="noVis"><?=lang('Action')?></th>
								<th><?=get_column_heading(lang('Name'), ($_SESSION['software']['view_auto_dialogs']['sort'] ?? ''), ($_SESSION['software']['view_auto_dialogs']['order'] ?? ''))?></th>
								<th class="text-center"><?=get_column_heading(lang('Enabled'), ($_SESSION['software']['view_auto_dialogs']['sort'] ?? ''), ($_SESSION['software']['view_auto_dialogs']['order'] ?? ''))?></th>
								<th><?=get_column_heading(lang('URL'), ($_SESSION['software']['view_auto_dialogs']['sort'] ?? ''), ($_SESSION['software']['view_auto_dialogs']['order'] ?? ''))?></th>
								<th><?=get_column_heading(lang('Width'), ($_SESSION['software']['view_auto_dialogs']['sort'] ?? ''), ($_SESSION['software']['view_auto_dialogs']['order'] ?? ''))?></th>
								<th><?=get_column_heading(lang('Height'), ($_SESSION['software']['view_auto_dialogs']['sort'] ?? ''), ($_SESSION['software']['view_auto_dialogs']['order'] ?? ''))?></th>
								<th><?=get_column_heading(lang('Delay'), ($_SESSION['software']['view_auto_dialogs']['sort'] ?? ''), ($_SESSION['software']['view_auto_dialogs']['order'] ?? ''))?></th>
								<th><?=get_column_heading(lang('Frequency'), ($_SESSION['software']['view_auto_dialogs']['sort'] ?? ''), ($_SESSION['software']['view_auto_dialogs']['order'] ?? ''))?></th>
								<th><?=get_column_heading(lang('Only on Page'), ($_SESSION['software']['view_auto_dialogs']['sort'] ?? ''), ($_SESSION['software']['view_auto_dialogs']['order'] ?? ''))?></th>
								<th><?=get_column_heading(lang('Created'), ($_SESSION['software']['view_auto_dialogs']['sort'] ?? ''), ($_SESSION['software']['view_auto_dialogs']['order'] ?? ''))?></th>
								<th><?=get_column_heading(lang('Last Modified'), ($_SESSION['software']['view_auto_dialogs']['sort'] ?? ''), ($_SESSION['software']['view_auto_dialogs']['order'] ?? ''))?></th>
							</tr>
                        </thead>
                        <tbody>
							<?php foreach($auto_dialogs as $auto_dialog): ?>
								<tr>
									<td class="align-middle text-start action-buttons">
                					    <button type="button" class="m-1 btn-data-control btn btn-outline-primary border-2 " data-loading-content=" " title="<?=lang(array('string'=>'Edit'))?>" onclick="window.location.href='edit_auto_dialog.php?id=<?=$auto_dialog['id']?>'"><i class="bi bi-pencil"></i></button>
										<a class="m-1 btn-data-control btn btn-outline-secondary border-2 " href="<?=(PATH . encode_url_path($home_page_name) . '?preview_auto_dialog=' . $auto_dialog['id'])?>" target="_blank"  title="<?=lang(array('string'=>'Preview'))?>"><i class="bi bi-link"></i></a>



                					</td>
									<td class="chart_label align-middle <?php if ($auto_dialog['enabled']): ?>text-success status_enabled<?php else: ?>text-danger status_disabled<?php endif ?>">
										<?=h($auto_dialog['name'])?>
									</td>
									<td class="align-middle text-center">
										<?php if ($auto_dialog['enabled']): ?>
											<span class="material-icons">task_alt</span>
										<?php endif ?>
									</td>
									<td class="align-middle"><?=h($auto_dialog['url'])?></td>
									<td class="align-middle">
										<?php if ($auto_dialog['width']): ?>
										<?=number_format($auto_dialog['width'])?>px
										<?php endif ?>
									</td>
									<td class="align-middle">
										<?php if ($auto_dialog['height']): ?>
										<?=number_format($auto_dialog['height'])?>px
										<?php endif ?>
									</td>
									<td class="align-middle">
										<?php if ($auto_dialog['delay']): ?>
											<?php $delay_suffix = '';
												if ($auto_dialog['delay'] > 1){
													$delay_suffix = 's';
												}
											?>
											<?=lang(array('string'=>'{var:1} second{suffix:1}','vars'=>number_format($auto_dialog['delay']),'suffix'=>array($delay_suffix))); ?>
										<?php endif ?>
									</td>
									<td class="align-middle">
										<?php if ($auto_dialog['frequency']): ?>
											<?php $frequency_suffix = '';
												if ($auto_dialog['frequency'] > 1){
													$frequency_suffix = 's';
												}
											?>
											<?=lang(array('string'=>'{var:1} hour{suffix:1}','vars'=>number_format($auto_dialog['frequency']),'suffix'=>array($frequency_suffix))); ?>
										<?php endif ?>
									</td>
									<td class="align-middle">
										<?=h($auto_dialog['page'])?>
									</td>
									<td class="align-middle">
										<?=get_relative_time(array('timestamp' => $auto_dialog['created_timestamp']))?>
										<?php if ($auto_dialog['created_username'] != ''): ?>
											<?=lang(array('string'=>'by {var:1}','vars'=>array( h($auto_dialog['created_username']) ) ) )?>
										<?php endif ?>
									</td>
									<td class="align-middle">
										<?=get_relative_time(array('timestamp' => $auto_dialog['last_modified_timestamp']))?>
										<?php if ($auto_dialog['last_modified_username'] != ''): ?>
											<?=lang(array('string'=>'by {var:1}','vars'=>array( h($auto_dialog['last_modified_username']) ) ) )?>
										<?php endif ?>
									</td>
								</tr>
							<?php endforeach ?>
						</tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>
