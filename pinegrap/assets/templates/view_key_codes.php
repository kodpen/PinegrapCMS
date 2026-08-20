<main id="content" class="container">
	<div class="row">
	    <div class="col-12">
			<?=$liveform->get_messages()?>
	        <div class="row mb-2 flex-wrap">
                <div class="col-12 text-center text-md-start">
                    <h2 class="d-inline-block " data-bs-content="<?=lang('All key codes assigned to specific offers.')?>" title="<?=lang('All Key Codes')?>"><?=lang('All Key Codes')?></h2>
                    <nav id="button_bar" class="navigation " aria-label="Button Bar">
                    	<a class="btn btn-sm btn-primary m-1 " href="add_key_code.php" data-loading-content="<?=lang(array('string'=>'Loading') )?>"><span class="bi bi-plus-circle me-2"></span><?=lang('Create')?></a>
						<form  class="disable_shortcut d-inline-block" method="get">
                            <div class=" btn-group btn-group-sm flex-wrap">
                                <a class="btn btn-link link-secondary py-0 m-1" href="import_key_codes.php"><span class="bi bi-box-arrow-in-right me-1"></span><?=lang(array('string'=>'Import') )?></a>
                                <button type="submit" name="submit_data" value="Export Key Codes" class="btn btn-link link-secondary py-0 m-1"><span class="bi bi-file-earmark-arrow-down bi-me-2"></span><?=lang(array('string'=>'Export') )?></button>
                            </div>
							<div class=" btn-group btn-group-sm flex-wrap">
								<a href="delete_key_codes.php" class="btn btn-link link-danger py-0 m-1"><span class="material-icons me-2">delete_forever</span><?=lang(array('string'=>'Delete All Key Codes') )?></a>
							</div>
                        </form>
                    </nav>
                </div>
            </div>
            <div class="card my-4">
                <div class="card-header chart-buttons justify-content-end d-flex flex-wrap"></div>
                <div class="card-body p-0 position-relative disable_shortcut">
					<table class="chart table-hover table " style="width:100%;display:none">
                        <thead>
                            <tr>
                                <th class="noVis"><?=lang('Action')?></th>
								<th><?=get_column_heading(lang('Key Code'), ($_SESSION['software']['ecommerce']['view_key_codes']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_key_codes']['order'] ?? ''))?></th>
								<th><?=get_column_heading(lang('Offer Code'), ($_SESSION['software']['ecommerce']['view_key_codes']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_key_codes']['order'] ?? ''))?></th>
								<th><?=get_column_heading(lang('Offer Message'), ($_SESSION['software']['ecommerce']['view_key_codes']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_key_codes']['order'] ?? ''))?></th>
								<th class="text-center"><?=get_column_heading(lang('Enabled'), ($_SESSION['software']['ecommerce']['view_key_codes']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_key_codes']['order'] ?? ''))?></th>
								<th><?=get_column_heading(lang('Expiration Date'), ($_SESSION['software']['ecommerce']['view_key_codes']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_key_codes']['order'] ?? ''))?></th>
								<th><?=get_column_heading(lang('Notes'), ($_SESSION['software']['ecommerce']['view_key_codes']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_key_codes']['order'] ?? ''))?></th>
								<th class="text-center"><?=get_column_heading(lang('Single-Use'), ($_SESSION['software']['ecommerce']['view_key_codes']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_key_codes']['order'] ?? ''))?></th>
								<th><?=get_column_heading(lang('Report'), ($_SESSION['software']['ecommerce']['view_key_codes']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_key_codes']['order'] ?? ''))?></th>
								<th><?=get_column_heading(lang('Last Modified'), ($_SESSION['software']['ecommerce']['view_key_codes']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_key_codes']['order'] ?? ''))?></th>
                            </tr>
                        </thead>
                        <tbody>
							<?php foreach($key_codes as $key_code): ?>
								<tr>
									<td class="align-middle text-start">
                					    <button type="button" class="m-1 btn-data-control btn btn-outline-primary border-2 " data-loading-content=" " title="<?=lang(array('string'=>'Edit'))?>" onclick="window.location.href='edit_key_code.php?id=<?=$key_code['id']?>'"><i class="bi bi-pencil"></i></button>
                					</td>
									<td class="chart_label align-middle <?php if ($key_code['status_enabled']): ?>status_enabled text-success<?php else: ?>status_disabled text-danger<?php endif ?>">
										<?=h($key_code['code'])?>
									</td>
									<td class="align-middle">
										<?php if ($key_code['offer_id']): ?>
										<a class="btn btn-link link-secondary py-0 mb-2 badge" href="edit_offer.php?id=<?=$key_code['offer_id']?>" title="<?=lang('Edit Offer')?>">
										<?=h($key_code['offer_code'])?>
										</a>
										<?php else: ?>
										<?=h($key_code['offer_code'])?>
										<?php endif ?>
									</td>
									<td class="align-middle">
										<?=h($key_code['offer_description'])?>
									</td>
									<td  class="align-middle text-center">
										<?php if ($key_code['enabled']): ?>
											<span class="material-icons">task_alt</span>
										<?php endif ?>
									</td>
									<td class="align-middle">
										<?php if ($key_code['expiration_date'] != '0000-00-00'): ?>
										<?=get_absolute_time(array(
											'timestamp' => strtotime($key_code['expiration_date']),
										
											'type' => 'date'))?>
										<?php endif ?>
									</td>
									<td class="align-middle">
										<?=nl2br(h($key_code['notes']))?>
									</td>
									<td class="align-middle text-center">
										<?php if ($key_code['single_use']): ?>
											<span class="material-icons">task_alt</span>
										<?php endif ?>
									</td>
									<td class="align-middle">
										<span class="badge fw-light text-light bg-secondary">
											<?php if ($key_code['report'] == 'key_code'): ?>
											<?=lang('Key Code')?>
											<?php else: ?>
												<?=lang('Offer Code')?>
											<?php endif ?>
										</span>
									</td>
									<td class="align-middle">
										<?=get_relative_time(array('timestamp' => $key_code['last_modified_timestamp']))?>
										<?php if ($key_code['last_modified_username'] != ''): ?>
											<?=lang(array('string'=>'by {var:1}','vars'=>array( h($key_code['last_modified_username']) ) ) )?>
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