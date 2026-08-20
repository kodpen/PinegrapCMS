<main id="content" class="container">
	<div class="row">
	    <div class="col-12">
	        <div class="row mb-2 flex-wrap">
                <div class="col-12 col-sm-12 col-md-6 col-xl-9 text-center text-md-start">
                    <h2 class="d-inline-block " data-bs-content="<?=lang('All order and product discounts.')?>" title="<?=lang('All Offers')?>"><?=lang('All Offers')?></h2>
                    <nav id="button_bar" class="navigation " aria-label="Button Bar">
                    	<a class="btn btn-sm btn-primary m-1 " href="add_offer.php" data-loading-content="<?=lang(array('string'=>'Loading') )?>"><span class="bi bi-plus-circle me-2"></span><?=lang('Create')?></a>
                    </nav>
                </div>
            </div>
            <div class="card my-4">
                <div class="card-body p-0 position-relative disable_shortcut">
					<table class="chart table-hover table display nowrap" style="display:none">
                        <thead>
                            <tr>
                                <th class="noVis"><?=lang('Action')?></th>
								<th><?=get_column_heading(lang('Offer Code'), ($_SESSION['software']['ecommerce']['view_offers']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_offers']['order'] ?? ''))?></th>
								<th><?=get_column_heading(lang('Message'), ($_SESSION['software']['ecommerce']['view_offers']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_offers']['order'] ?? ''))?></th>
								<th><?=get_column_heading(lang('Rule'), ($_SESSION['software']['ecommerce']['view_offers']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_offers']['order'] ?? ''))?></th>
								<th><?=lang('Actions')?></th>
								<th><?=get_column_heading(lang('Status'), ($_SESSION['software']['ecommerce']['view_offers']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_offers']['order'] ?? ''))?></th>
								<th><?=get_column_heading(lang('Start Date'), ($_SESSION['software']['ecommerce']['view_offers']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_offers']['order'] ?? ''))?></th>
								<th><?=get_column_heading(lang('End Date'), ($_SESSION['software']['ecommerce']['view_offers']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_offers']['order'] ?? ''))?></th>
								<th class="text-center"><?=get_column_heading(lang('Require Code'), ($_SESSION['software']['ecommerce']['view_offers']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_offers']['order'] ?? ''))?></th>
								<th class="text-center"><?=get_column_heading(lang('Best'), ($_SESSION['software']['ecommerce']['view_offers']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_offers']['order'] ?? ''))?></th>
								<th><?=get_column_heading(lang('Last Modified'), ($_SESSION['software']['ecommerce']['view_offers']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_offers']['order'] ?? ''))?></th>
                            </tr>
                        </thead>
                        <tbody>
							<?php foreach($offers as $offer): ?>
								<tr>
									<td class="align-middle text-start">
                					    <button type="button" class="m-1 btn-data-control btn btn-outline-primary border-2 " data-loading-content=" " title="<?=lang(array('string'=>'Edit'))?>" onclick="window.location.href='edit_offer.php?id=<?=$offer['id']?>'"><i class="bi bi-pencil"></i></button>
                					</td>
									<td class="chart_label align-middle <?php if ($offer['status_enabled']): ?>status_enabled text-success<?php else: ?>status_disabled text-danger<?php endif ?>">
										<?=h($offer['code'])?>
									</td>
									<td class="align-middle">
										<?=h($offer['description'])?>
									</td>
									<td class="align-middle">
										<?php if ($offer['offer_rule_id']): ?>
										<a class="btn btn-link link-secondary py-0 mb-2 badge" href="edit_offer_rule.php?id=<?=$offer['offer_rule_id']?>" title="<?=lang('Edit Offer Rule')?>">
											<?=h($offer['offer_rule_name'])?>
										</a>
										<?php endif ?>
									</td>
									<td class="align-middle text-center">
                    					<div class="row row-cols-2 align-middle">
											<?php if ($offer['actions']):?>
                    					    	<div class="dropdown col">
                    					    	    <button type="button" class="m-1 btn btn-sm p-1 no-popover dropdown-toggle" data-bs-auto-close="outside" data-bs-toggle="dropdown" aria-expanded="false"><span class="material-icons d-inline ecommerce-color">generating_tokens</span></button>
                    					    	    <ul class="dropdown-menu">
														<?php foreach($offer['actions'] as $key => $action): ?>
															<li><a class="dropdown-item" href="edit_offer_action.php?id=<?=$action['id']?>"><?=h($action['name'])?></a></li>
														<?php endforeach ?>
													</ul>
                					        	</div>
											<?php endif ?>
                					    </div>
                					</td>
									<td class="align-middle">
										<span class="badge fw-light <?php if ($offer['status'] == 'enabled'):?>bg-success<?php else: ?>bg-secondary<?php endif ?>"><?=h(lang(ucwords($offer['status'])))?></span>
									</td>
									<td class="align-middle">
										<?=prepare_form_data_for_output($offer['start_date'], 'date')?>
									</td>
									<td class="align-middle">
										<?=prepare_form_data_for_output($offer['end_date'], 'date')?>
									</td>
									<td  class="text-center align-middle">
										<?php if ($offer['require_code']): ?>
											<span class="material-icons">task_alt</span>
										<?php endif ?>
									</td>
									<td  class="text-center align-middle">
										<?php if ($offer['only_apply_best_offer']): ?>
											<span class="material-icons">task_alt</span>
										<?php endif ?>
									</td>
									<td class="align-middle">
										<?=get_relative_time(array('timestamp' => $offer['last_modified_timestamp']))?>
										<?php if ($offer['last_modified_username'] != ''): ?>
											<?=lang(array('string'=>'by {var:1}','vars'=>array( h($offer['last_modified_username']) ) ) )?>
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