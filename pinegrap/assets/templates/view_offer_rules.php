<main id="content" class="container">
	<div class="row">
	    <div class="col-12">
			<?=$liveform->get_messages()?>
	        <div class="row mb-2 flex-wrap">
                <div class="col-12 col-sm-12 text-center text-md-start">
                    <h2 class="d-inline-block " data-bs-content="<?=lang('All rules available to any offer.')?>" title="<?=lang('All Offer Rules')?>"><?=lang('All Offer Rules')?></h2>
                    <nav id="button_bar" class="navigation " aria-label="Button Bar">
                    	<a class="btn btn-sm btn-primary m-1 " href="add_offer_rule.php" data-loading-content="<?=lang(array('string'=>'Loading') )?>"><span class="bi bi-plus-circle me-2"></span><?=lang('Create')?></a>
                    </nav>
                </div>
            </div>
            <div class="card my-4">
                <div class="card-body p-0 position-relative disable_shortcut">
					<table class="chart table-hover table " style="width:100%;display:none">
                        <thead>
                            <tr>
                                <th class="noVis"><?=lang('Action')?></th>
								<th><?=get_column_heading(lang('Name'), ($_SESSION['software']['ecommerce']['view_offer_rules']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_offer_rules']['order'] ?? ''))?></th>
								<th class="text-end"><?=get_column_heading(lang('Required Subtotal'), ($_SESSION['software']['ecommerce']['view_offer_rules']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_offer_rules']['order'] ?? ''))?></th>
								<th><?=lang('Required Product')?></th>
								<th  class="text-center"><?=get_column_heading(lang('Required Quantity'), ($_SESSION['software']['ecommerce']['view_offer_rules']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_offer_rules']['order'] ?? ''))?></th>
								<th><?=get_column_heading(lang('Last Modified'), ($_SESSION['software']['ecommerce']['view_offer_rules']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_offer_rules']['order'] ?? ''))?></th>
                                
                            </tr>
                        </thead>
                        <tbody>
							<?php foreach($offer_rules as $offer_rule): ?>
								<tr>
								
									<td class="align-middle text-start">
                					    <button type="button" class="m-1 btn-data-control btn btn-outline-primary border-2 " data-loading-content=" " title="<?=lang(array('string'=>'Edit'))?>" onclick="window.location.href='edit_offer_rule.php?id=<?=$offer_rule['id']?>'"><i class="bi bi-pencil"></i></button>
                					</td>
									<td class="align-middle">
										<?=h($offer_rule['name'])?>
									</td>
									<td class="align-middle text-end">
										<?php if ($offer_rule['required_subtotal'] > 0): ?>
											<span class=" badge bg-secondary fw-lighter"><?=prepare_amount($offer_rule['required_subtotal'])?></span>
										<?php endif ?>
									</td>
									<td class="align-middle">
										<?php foreach($offer_rule['products'] as $product): ?>
										<div><?=h($product['name'])?> - <?=h($product['short_description'])?></div>
										<?php endforeach ?>
									</td>
									<td class="align-middle text-center">
										<?php if ($offer_rule['products'] and $offer_rule['required_quantity']): ?>
										<?=h(number_format($offer_rule['required_quantity']))?>
										<?php endif ?>
									</td>
									<td  class="align-middle">
										<?=get_relative_time(array('timestamp' => $offer_rule['last_modified_timestamp']))?>
										<?php if ($offer_rule['last_modified_username'] != ''): ?>
											<?=lang(array('string'=>'by {var:1}','vars'=>array( h($offer_rule['last_modified_username']) ) ) )?>
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