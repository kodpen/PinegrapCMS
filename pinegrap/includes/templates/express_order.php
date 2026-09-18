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
<?=$messages?>
<?php
	// If there are pending or upsell offers, then show them.
	if ($number_of_special_offers):
	?>
<h2>
	<?=h($number_of_special_offers > 1 ? lang('Special Offers') : lang('Special Offer'))?>
</h2>
<?php
	// If there are pending offers, then start form
	if ($pending_offers):
	?>
<form <?=$attributes?>>
	<?php endif ?>
	<?php if ($number_of_special_offers > 1): ?>
	<ul class="form-inline">
		<?php else: ?>
		<div class="form-inline">
			<?php endif ?>
			<?php foreach($pending_offers as $offer): ?>
			<?php foreach($offer['offer_actions'] as $action): ?>
			<?php
				// The <p> adds necessary vertical spacing between li's
				// so that buttons do not touch.
				if ($number_of_special_offers > 1):
				?>
			<li>
				<p>
					<?php endif ?>
				<div class="form-group">
					<label>
					<?=h($offer['description'])?>
					<?php
						// If this offer has multiple actions that add a product, then also
						// show action name, so the customer will understand what action does.
						if ($offer['multiple_actions']):
						?>
					(<?=h($action['name'])?>)
					<?php endif ?>
					<?php
						// Add some spacing between the description
						// and the fields that follow.
						?>
					&nbsp;
					</label>
				</div>
				<?php
					// If this action needs the customer to select a recipient,
					// then show recipient fields.
					if ($action['recipient']):
					?>
				<div class="form-group">
					<label for="pending_offer_<?=$offer['id']?>_<?=$action['id']?>_ship_to"><?=h(lang('Ship to'))?></label>
					<select name="pending_offer_<?=$offer['id']?>_<?=$action['id']?>_ship_to" id="pending_offer_<?=$offer['id']?>_<?=$action['id']?>_ship_to" class="form-control"></select>
				</div>
				<?php
					// If add name is allowed for this action, then show field.
					// Some actions require certain recipients, so the add name
					// is not allowed in those cases.
					if ($action['add_name']):
					?>
				<div class="form-group">
					<input type="text" name="pending_offer_<?=$offer['id']?>_<?=$action['id']?>_add_name" id="pending_offer_<?=$offer['id']?>_<?=$action['id']?>_add_name" class="form-control" placeholder="or add name">
				</div>
				<?php endif ?>
				<?php endif ?>
				<div class="form-group">
					<button type="submit" name="add_pending_offer_<?=$offer['id']?>_<?=$action['id']?>" class="btn btn-primary btn-sm">
					<?=h(lang('Add'))?>
					</button>
				</div>
				<?php if ($number_of_special_offers > 1): ?>
				</p>
			</li>
			<?php endif ?>
			<?php endforeach ?>
			<?php endforeach ?>
			<?php foreach($upsell_offers as $offer): ?>
			<?php
				// The <p> adds necessary vertical spacing between li's
				// so that buttons do not touch.
				if ($number_of_special_offers > 1):
				?>
			<li>
				<p>
					<?php endif ?>
				<div class="form-group">
					<label>
					<?php if ($offer['upsell_message']): ?>
					<?=h($offer['upsell_message'])?>
					<?php else: ?>
					<?=h($offer['description'])?>
					<?php endif ?>
					</label>
				</div>
				<?php if ($offer['upsell_action_url']): ?>
				<div class="form-group">
					<a href="<?=h($offer['upsell_action_url'])?>" class="btn btn-default btn-secondary btn-sm">
					<?=h($offer['upsell_action_button_label'])?>
					</a>
				</div>
				<?php endif ?>
				<?php if ($number_of_special_offers > 1): ?>
				</p>
			</li>
			<?php endif ?>
			<?php endforeach ?>
			<?php if ($number_of_special_offers > 1): ?>
	</ul>
	<?php else: ?>
	</div>
	<?php endif ?>
	<?php
		// If there are pending offers, then close form
		if ($pending_offers):
		?>
	<?=$pending_system // Required hidden fields (do not remove) ?>
</form>
<?php endif ?>
<?php endif ?>
<?php
	// If quick add is enabled, then show that area.
	if ($quick_add):
	?>
<?php if ($quick_add['label']): ?>
<h2><?=h($quick_add['label'])?></h2>
<?php endif ?>
<form <?=$attributes?>>
	<div class="form-group">
		<label for="quick_add_product_id"><?=h(lang('Item'))?></label>
		<select name="quick_add_product_id" id="quick_add_product_id" class="form-control"></select>
	</div>
	<?php
		// The ids on the various container rows below allows the JS to
		// dynamically show and hide rows based on the product that is selected.
		?>
	<?php if ($quick_add['recipient']): ?>
	<div id="quick_add_ship_to_row" class="form-group">
		<label for="quick_add_ship_to"><?=h(lang('Ship to'))?></label>
		<select name="quick_add_ship_to" id="quick_add_ship_to" class="form-control"></select>
	</div>
	<div id="quick_add_add_name_row" class="form-group">
		<label for="quick_add_add_name"><?=h(lang('or add name'))?></label>
		<input type="text" name="quick_add_add_name" id="quick_add_add_name" class="form-control" placeholder="<?=h(lang('Example: Tom'))?>">
	</div>
	<?php endif ?>
	<?php if ($quick_add['quantity']): ?>
	<div id="quick_add_quantity_row" class="form-group">
		<label for="quick_add_quantity"><?=h(lang('Qty'))?></label>
		<input type="number" name="quick_add_quantity" id="quick_add_quantity" class="form-control">
	</div>
	<?php endif ?>
	<?php if ($quick_add['amount']): ?>
	<div id="quick_add_amount_row" class="form-group">
		<label for="quick_add_amount"><?=h(lang('Amount'))?></label>
		<div class="input-group">
			<span class="input-group-addon"><?=$currency_symbol?></span>
			<input type="number" step="any" name="quick_add_amount" id="quick_add_amount" class="form-control">
			<?php if ($currency_code): ?>
			<span class="input-group-addon"><?=h($currency_code)?></span>
			<?php endif ?>
		</div>
	</div>
	<?php endif ?>
	<?php if ($quick_add['available_products']): ?>
	<div class="form-group">
		<button type="submit" class="btn btn-default btn-secondary btn-sm">
		<?=h(lang('Add'))?>
		</button>
	</div>
	<?php endif ?>
	<?=$quick_add['system'] // Required hidden fields and JS (do not remove) ?>
</form>
<?php endif ?>
<?php
	// If there are no recipients in the order, then show message.
	if (!$recipients):
	?>
<p><strong><?=h(lang('No items have been added.'))?></strong></p>
<?php
	// Otherwise there is at least one recipient, so show items.
	else:
	?>
<form <?=$attributes?>>
	<?php
		// If there are recurring items, then show heading to
		// differentiate "Today's Charges" from the "Recurring Charges".
		if ($recurring_items):
		?>
	<h2><?=h(lang('Today\'s Charges'))?></h2>
	<?php endif ?>
	<?php
		// If there are nonrecurring items, then place items in a column.
		if ($nonrecurring_items):
		?>
	<div class="row">
		<div class="col-lg-9">
			<table class="table mobile_stacked">
				<?php foreach($recipients as $recipient): ?>
				<?php
					// If this recipient has an item in nonrecurring transaction
					// then show this recipient and its items.
					if ($recipient['in_nonrecurring']):
					?>
				<?php
					// If this is a shipping recipient,
					// then show ship to heading.
					if ($recipient['ship_to_heading']):
					?>
				<tr>
					<td colspan="6">
						<h3>
							<?=h(lang('Ship to'))?>
							<?php if (ECOMMERCE_RECIPIENT_MODE == 'multi-recipient'): ?>
							<strong><?=h($recipient['ship_to_name'])?></strong>
							<?php endif ?>
						</h3>
					</td>
				</tr>
				<?php endif ?>
				<tr>
					<th><?=h(lang('Item'))?></th>
					<th><?=h(lang('Description'))?></th>
					<th class="text-center">
						<?php if ($recipient['non_donations_in_nonrecurring']): ?>
						<?=h(lang('Qty'))?>
						<?php endif ?>
					</th>
					<th class="text-right">
						<?php if ($recipient['non_donations_in_nonrecurring']): ?>
						<?=h(lang('Price'))?>
						<?php endif ?>
					</th>
					<th class="text-right"><?=h(lang('Amount'))?></th>
					<th></th>
				</tr>
				<?php foreach($recipient['items'] as $item): ?>
				<?php
					// If this item is in nonrecurring transaction then show it.
					if ($item['in_nonrecurring']):
					?>
				<tr>
					<td>
						<span class="visible-xs-inline"><?=h(lang('Item'))?>:</span>
						<?=h($item['name'])?>
					</td>
					<td>
						<?php
							// Use the page property to determine whether the
							// full or short description should be shown.
							if ($product_description_type == 'full_description'):
							?>
						<?php
							// If this item has an image, then start row structure
							// and output column for image.
							if ($item['image_url']):
							?>
						<div class="row">
							<div class="col-md-6">
								<?php
									// The containers around the image fixes Firefox
									// issue with responsive images in tables.
									?>
								<div class="responsive_table_image_1">
									<div class="responsive_table_image_2">
										<img src="<?=h($item['image_url'])?>" class="img-responsive img-fluid center-block">
									</div>
								</div>
							</div>
							<div class="col-md-6">
								<?php endif ?>
								<span style="font-weight:bold"><?=h($item['short_description'])?></span>
								<?=$item['full_description']?>
								<?php else: ?>
								<?=h($item['short_description'])?>
								<?php endif ?>
								<?php if ($item['show_out_of_stock_message']): ?>
								<?=$item['out_of_stock_message']?>
								<?php endif ?>
								<?php if ($item['calendar_event']): ?>
								<p>
									<?=h($item['calendar_event']['name'])?><br>
									<?=$item['calendar_event']['date_and_time_range']?>
								</p>
								<?php endif ?>
								<?php
									// If there was an image shown for this item,
									// then close column and row structure.
									if (
									    $item['image_url']
									    and ($product_description_type == 'full_description')
									):
									?>
							</div>
						</div>
						<?php endif ?>
						<?php
							// If the recurring schedule is editable
							// by the customer, then show fields.
							if ($item['recurring_schedule']):
							?>
						<fieldset>
							<legend><?=h(lang('Payment Schedule'))?></legend>
							<div class="form-group">
								<label for="recurring_payment_period_<?=$item['id']?>">
								<?=h(lang('Frequency'))?>*
								</label>
								<select name="recurring_payment_period_<?=$item['id']?>" id="recurring_payment_period_<?=$item['id']?>" class="form-control"></select>
							</div>
							<div class="form-group">
								<label for="recurring_number_of_payments_<?=$item['id']?>">
								<?=h(lang('Number of Payments'))?><?php if ($number_of_payments_required): ?>*<?php endif ?>
								</label>
								<input type="number" name="recurring_number_of_payments_<?=$item['id']?>" id="recurring_number_of_payments_<?=$item['id']?>" class="form-control">
								<p class="help-block">
									<?=$number_of_payments_message?>
								</p>
							</div>
							<?php
								// We only allow the start date to be selected
								// for certain payment gateways.
								if ($start_date):
								?>
							<div class="form-group">
								<label for="recurring_start_date_<?=$item['id']?>">
								<?=h(lang('Start Date'))?>*
								</label>
								<input type="text" name="recurring_start_date_<?=$item['id']?>" id="recurring_start_date_<?=$item['id']?>" class="form-control">
							</div>
							<?php endif ?>
						</fieldset>
						<?php endif ?>
						<?php
							// If this is a gift card, then show fields.
							if ($item['gift_card']):
							?>
						<?php
							// Show gift card fields for every quantity.
							for ($quantity_number = 1; $quantity_number <= $item['number_of_gift_cards']; $quantity_number++):
							?>
						<fieldset>
							<legend>
								<?=h(lang('Gift Card'))?>
								<?php if ($item['number_of_gift_cards'] > 1): ?>
								(<?=h(lang(array('string' => '{var:1} of {var:2}', 'vars' => array($quantity_number, $item['number_of_gift_cards']))))?>)
								<?php endif ?>
							</legend>
							<div class="form-group">
								<label>
								<?=h(lang('Amount'))?>
								</label>
								<p class="form-control-static">
									<strong><?=$item['price_info']?></strong>
								</p>
							</div>
							<div class="form-group">
								<label for="order_item_<?=$item['id']?>_quantity_number_<?=$quantity_number?>_gift_card_recipient_email_address">
								<?=h(lang('Recipient Email'))?>*
								</label>
								<input type="email" name="order_item_<?=$item['id']?>_quantity_number_<?=$quantity_number?>_gift_card_recipient_email_address" id="order_item_<?=$item['id']?>_quantity_number_<?=$quantity_number?>_gift_card_recipient_email_address" class="form-control" placeholder="recipient@example.com">
							</div>
							<div class="form-group">
								<label for="order_item_<?=$item['id']?>_quantity_number_<?=$quantity_number?>_gift_card_from_name">
								<?=h(lang('Your Name'))?>
								</label>
								<input type="text" name="order_item_<?=$item['id']?>_quantity_number_<?=$quantity_number?>_gift_card_from_name" id="order_item_<?=$item['id']?>_quantity_number_<?=$quantity_number?>_gift_card_from_name" class="form-control" placeholder="<?=h(lang('Your name that will appear in the email.'))?>">
								<p class="help-block">
									(<?=h(lang('leave blank if you want to be anonymous'))?>)
								</p>
							</div>
							<div class="form-group">
								<label for="order_item_<?=$item['id']?>_quantity_number_<?=$quantity_number?>_gift_card_message">
								<?=h(lang('Message'))?>
								</label>
								<textarea name="order_item_<?=$item['id']?>_quantity_number_<?=$quantity_number?>_gift_card_message" id="order_item_<?=$item['id']?>_quantity_number_<?=$quantity_number?>_gift_card_message" rows="3" class="form-control" placeholder="<?=h(lang('The message that will appear in the email.'))?>"></textarea>
							</div>
							<div class="form-group">
								<label for="order_item_<?=$item['id']?>_quantity_number_<?=$quantity_number?>_gift_card_delivery_date">
								<?=h(lang('Delivery Date'))?>
								</label>
								<input type="text" name="order_item_<?=$item['id']?>_quantity_number_<?=$quantity_number?>_gift_card_delivery_date" id="order_item_<?=$item['id']?>_quantity_number_<?=$quantity_number?>_gift_card_delivery_date" class="form-control" placeholder="<?=h(lang('Date the gift card e-mail should be sent.'))?>">
							</div>
						</fieldset>
						<?php endfor ?>
						<?php endif ?>
						<?php
							// If this item has a product form,
							// then show form.
							if ($item['form']):
							?>
						<?=render(array(
							'template' => 'product_form.php',
							'item' => $item
							))?>
						<?php endif ?>
					</td>
					<td class="text-center">
						<?php
							// If the item is not a donation then show quantity.
							if ($item['selection_type'] != 'donation'):
							?>
						<?php
							// If the item was added by an offer,
							// then just show uneditable quantity amount.
							if ($item['added_by_offer']):
							?>
						<?=number_format($item['quantity'])?>
						<?php
							// Otherwise the item was not added by an offer
							// so allow customer to change quantity.
							else:
							?>
						<div class="form-group">
							<label for="quantity[<?=$item['id']?>]" class="visible-xs-inline-block">
							<?=h(lang('Qty'))?>
							</label>
							<input type="number" name="quantity[<?=$item['id']?>]" id="quantity[<?=$item['id']?>]" class="form-control" style="min-width: 5em">
						</div>
						<?php endif ?>
						<?php endif ?>
					</td>
					<td class="text-right">
						<?php if ($item['selection_type'] != 'donation'): ?>
						<span class="visible-xs-inline"><?=h(lang('Price'))?>:</span>
						<?=$item['price_info']?>
						<?php endif ?>
					</td>
					<td class="text-right">
						<?php if ($item['selection_type'] == 'donation'): ?>
						<div class="form-group">
							<label for="donations[<?=$item['id']?>]" class="visible-xs-inline-block">
							<?=h(lang('Amount'))?>
							</label>
							<div class="input-group">
								<span class="input-group-addon">
								<?=$currency_symbol?>
								</span>
								<input type="number" step="any" name="donations[<?=$item['id']?>]" id="donations[<?=$item['id']?>]" class="form-control" style="min-width: 6em">
								<?php if ($currency_code): ?>
								<span class="input-group-addon">
								<?=h($currency_code)?>
								</span>
								<?php endif ?>
							</div>
						</div>
						<?php else: ?>
						<span class="visible-xs-inline"><?=h(lang('Amount'))?>:</span>
						<?=$item['amount_info']?>
						<?php endif ?>
					</td>
					<td class="text-center">
						<a href="<?=h($item['remove_url'])?>" class="btn btn-default btn-secondary btn-sm" title="<?=h(lang('Remove'))?>">
						<span class="glyphicon glyphicon-remove"></span>
						</a>
					</td>
				</tr>
				<?php endif ?>
				<?php endforeach ?>
				<?php
					// If this is a shipping recipient then show shipping fields
					if ($recipient['shipping']):
					?>
				<tr>
					<td colspan="6">
						<h3>
							<?=h(lang('Shipping Address'))?>
							<?php if (ECOMMERCE_RECIPIENT_MODE == 'multi-recipient'): ?>
							<?=lang(array('string' => 'for <strong>{var:1}</strong>', 'vars' => array(h($recipient['ship_to_name']))))?>
							<?php endif ?>
						</h3>
						<div class="form-group">
							<label for="shipping_<?=$recipient['id']?>_salutation">
							<?=h(lang('Salutation'))?>
							</label>
							<select
								id="shipping_<?=$recipient['id']?>_salutation"
								name="shipping_<?=$recipient['id']?>_salutation"
								autocomplete="section-shipping-<?=$recipient['id']?> shipping honorific-prefix"
								class="form-control">
							</select>
						</div>
						<div class="form-group">
							<label for="shipping_<?=$recipient['id']?>_first_name">
							<?=h(lang('First Name'))?>*
							</label>
							<input
								type="text"
								id="shipping_<?=$recipient['id']?>_first_name"
								name="shipping_<?=$recipient['id']?>_first_name"
								autocomplete="section-shipping-<?=$recipient['id']?> shipping given-name"
								spellcheck="false"
								class="form-control">
						</div>
						<div class="form-group">
							<label for="shipping_<?=$recipient['id']?>_last_name">
							<?=h(lang('Last Name'))?>*
							</label>
							<input
								type="text"
								id="shipping_<?=$recipient['id']?>_last_name"
								name="shipping_<?=$recipient['id']?>_last_name"
								autocomplete="section-shipping-<?=$recipient['id']?> shipping family-name"
								spellcheck="false"
								class="form-control">
						</div>
						<div class="form-group">
							<label for="shipping_<?=$recipient['id']?>_company">
							<?=h(lang('Company'))?>
							</label>
							<input
								type="text"
								id="shipping_<?=$recipient['id']?>_company"
								name="shipping_<?=$recipient['id']?>_company"
								autocomplete="section-shipping-<?=$recipient['id']?> shipping organization"
								spellcheck="false"
								class="form-control">
						</div>
						<div class="form-group">
							<label for="shipping_<?=$recipient['id']?>_address_1">
							<?=h(lang('Address 1'))?>*
							</label>
							<input
								type="text"
								id="shipping_<?=$recipient['id']?>_address_1"
								name="shipping_<?=$recipient['id']?>_address_1"
								autocomplete="section-shipping-<?=$recipient['id']?> shipping address-line1"
								spellcheck="false"
								class="form-control">
						</div>
						<div class="form-group">
							<label for="shipping_<?=$recipient['id']?>_address_2">
							<?=h(lang('Address 2'))?>
							</label>
							<input
								type="text"
								id="shipping_<?=$recipient['id']?>_address_2"
								name="shipping_<?=$recipient['id']?>_address_2"
								autocomplete="section-shipping-<?=$recipient['id']?> shipping address-line2"
								spellcheck="false"
								class="form-control">
						</div>
						<div class="form-group">
							<label for="shipping_<?=$recipient['id']?>_city">
							<?=h(lang('City'))?>*
							</label>
							<input
								type="text"
								id="shipping_<?=$recipient['id']?>_city"
								name="shipping_<?=$recipient['id']?>_city"
								autocomplete="section-shipping-<?=$recipient['id']?> shipping address-level2"
								spellcheck="false"
								class="form-control">
						</div>
						<div class="form-group">
							<label for="shipping_<?=$recipient['id']?>_country">
							<?=h(lang('Country'))?>*
							</label>
							<select
								id="shipping_<?=$recipient['id']?>_country"
								name="shipping_<?=$recipient['id']?>_country"
								autocomplete="section-shipping-<?=$recipient['id']?> shipping country"
								class="form-control">
							</select>
						</div>
						<div class="form-group">
							<label for="shipping_<?=$recipient['id']?>_state_text_box">
							<?=h(lang('State / Province'))?>
							</label>
							<input
								type="text"
								id="shipping_<?=$recipient['id']?>_state_text_box"
								name="shipping_<?=$recipient['id']?>_state"
								autocomplete="section-shipping-<?=$recipient['id']?> shipping address-level1"
								spellcheck="false"
								class="form-control">
							<label for="shipping_<?=$recipient['id']?>_state_pick_list" style="display: none">
							<?=h(lang('State / Province'))?>*
							</label>
							<select
								id="shipping_<?=$recipient['id']?>_state_pick_list"
								name="shipping_<?=$recipient['id']?>_state"
								autocomplete="section-shipping-<?=$recipient['id']?> shipping address-level1"
								class="form-control" style="display: none">
							</select>
						</div>
						<div class="form-group">
							<label for="shipping_<?=$recipient['id']?>_zip_code">
							<?=h(lang('Zip / Postal Code'))?><span id="shipping_<?=$recipient['id']?>_zip_code_required" style="display: none">*</span>
							</label>
							<input
								type="text"
								id="shipping_<?=$recipient['id']?>_zip_code"
								name="shipping_<?=$recipient['id']?>_zip_code"
								autocomplete="section-shipping-<?=$recipient['id']?> shipping postal-code"
								spellcheck="false"
								class="form-control">
						</div>
						<div><?=h(lang('Address Type'))?></div>
						<div class="radio">
							<label>
							<input
								type="radio"
								name="shipping_<?=$recipient['id']?>_address_type"
								value="residential">
							<?=h(lang('Residential'))?>
							</label>
						</div>
						<div class="radio">
							<label>
							<input
								type="radio"
								name="shipping_<?=$recipient['id']?>_address_type"
								value="business">
							<?=h(lang('Business'))?>
							</label>
						</div>
						<div class="form-group">
							<label for="shipping_<?=$recipient['id']?>_phone_number">
							<?=h(lang('Phone'))?>
							</label>
							<input
								type="tel"
								id="shipping_<?=$recipient['id']?>_phone_number"
								name="shipping_<?=$recipient['id']?>_phone_number"
								autocomplete="section-shipping-<?=$recipient['id']?> shipping tel"
								spellcheck="false"
								class="form-control">
						</div>
						<?php if ($custom_shipping_form): ?>
						<!-- Adds edit button and grid around form when user is in edit mode -->
						<?=$edit_custom_shipping_form_start?>
						<?=eval('?>' . generate_form_layout_content(array('page_id' => $page_id, 'form_type' => 'shipping', 'indent' => '                                                ')))?>
						<!-- Closes the edit grid -->
						<?=$edit_custom_shipping_form_end?>
						<?php endif ?>
						<?php if ($arrival_dates): ?>
						<h3>
							<?=h(lang('Requested Arrival Date'))?>
							<?php if (ECOMMERCE_RECIPIENT_MODE == 'multi-recipient'): ?>
							<?=lang(array('string' => 'for <strong>{var:1}</strong>', 'vars' => array(h($recipient['ship_to_name']))))?>
							<?php endif ?>
						</h3>
						<?php foreach($arrival_dates as $arrival_date): ?>
						<div class="radio">
							<label>
							<input
								type="radio"
								id="shipping_<?=$recipient['id']?>_arrival_date_<?=$arrival_date['id']?>"
								name="shipping_<?=$recipient['id']?>_arrival_date"
								value="<?=$arrival_date['id']?>">
							<?=h($arrival_date['name'])?>
							</label>
						</div>
						<p><?=$arrival_date['description']?></p>
						<?php if ($arrival_date['custom']): ?>
						<div class="form-group">
							<input
								type="text"
								id="shipping_<?=$recipient['id']?>_custom_arrival_date_<?=$arrival_date['id']?>"
								name="shipping_<?=$recipient['id']?>_custom_arrival_date_<?=$arrival_date['id']?>"
								class="form-control">
						</div>
						<?php endif ?>
						<?php endforeach ?>
						<?php endif ?>
						<p
							id="shipping_<?=$recipient['id']?>_message"
							class="alert alert-danger" style="display: none">
						</p>
						<h3
							id="shipping_<?=$recipient['id']?>_method_heading"
							style="display: none"
							>
							<?=h(lang('Shipping Method'))?>
							<?php if (ECOMMERCE_RECIPIENT_MODE == 'multi-recipient'): ?>
							<?=lang(array('string' => 'for <strong>{var:1}</strong>', 'vars' => array(h($recipient['ship_to_name']))))?>
							<?php endif ?>
						</h3>
						<table
							id="shipping_<?=$recipient['id']?>_methods"
							class="table mobile_stacked" style="display: none"
							>
							<thead>
								<tr>
									<th><?=h(lang('Select One'))?></th>
									<th class="text-right"><?=h(lang('Cost'))?></th>
									<th><?=h(lang('Details'))?></th>
								</tr>
							</thead>
							<tr class="method_row">
								<td>
									<div class="radio">
										<label>
										<input type="radio">
										<span class="name"></span>
										</label>
									</div>
								</td>
								<td class="text-right">
									<div class="cost"></div>
								</td>
								<td>
									<div class="delivery_date" style="display: none">
										<?=h(lang('Estimated Delivery'))?>:
										<strong class="date"></strong>
									</div>
									<div class="description"></div>
								</td>
							</tr>
						</table>
					</td>
				</tr>
				<?php endif ?>
				<?php endif ?>
				<?php endforeach ?>
			</table>
		</div>
		<div class="col-lg-3">
			<?php endif ?>
			<h3><?=h(lang('Totals'))?></h3>
			<table class="table">
				<?php
					// We only show the subtotal if there is an offer discount, tax,
					// shipping, or gift card discount.  Otherwise the subtotal
					// would be redundant with the total due.
					if ($show_subtotal):
					?>
				<tr>
					<th scope="row" class="text-right"><?=h(lang('Subtotal'))?>:</th>
					<td class="text-right"><?=$subtotal_info?></td>
				</tr>
				<?php endif ?>
				<?php if ($discount_info): ?>
				<tr>
					<th scope="row" class="text-right"><?=h(lang('Discount'))?>:</th>
					<td class="text-right">-<?=$discount_info?></td>
				</tr>
				<?php endif ?>
				<?php if ($tax_info): ?>
				<tr>
					<th scope="row" class="text-right"><?=h(lang('Tax'))?>:</th>
					<td class="text-right"><?=$tax_info?></td>
				</tr>
				<?php endif ?>
				<?php if ($shipping_info): ?>
				<tr>
					<th scope="row" class="text-right"><?=h(lang('Shipping'))?>:</th>
					<td class="text-right">
						<?php
							// The shipping class allows the shipping to be dynamically updated
							// when a customer selects a method.
							?>
						<span class="shipping"><?=$shipping_info?></span>
					</td>
				</tr>
				<?php endif ?>
				<?php if ($gift_card_discount_info): ?>
				<tr>
					<th scope="row" class="text-right"><?=h($number_of_applied_gift_cards > 1 ? lang('Gift Cards') : lang('Gift Card'))?>:</th>
					<td class="text-right">-<?=$gift_card_discount_info?></td>
				</tr>
				<?php endif ?>
				<?php if ($show_surcharge): ?>
				<tr class="surcharge_row">
					<th scope="row" class="text-right"><?=h(lang('Surcharge'))?>:</th>
					<td class="text-right"><?=$surcharge_info?></td>
				</tr>
				<tr class="surcharge_total_row">
					<th scope="row" class="text-right" style="width: 100%"><?=h(lang('Total Due'))?>:</th>
					<td class="text-right">
						<strong>
						<?=$total_with_surcharge_info?><?php if ($base_currency_total_with_surcharge_info): ?>*
						(<?=$base_currency_total_with_surcharge_info?>)<?php endif ?>
						</strong>
					</td>
				</tr>
				<?php endif ?>
				<tr class="total_row">
					<th scope="row" class="text-right" style="width: 100%"><?=h(lang('Total Due'))?>:</th>
					<td class="text-right">
						<?php
							// The total and base_currency_total classes allow the total to be
							// dynamically updated when a customer selects a shipping method.
							?>
						<strong>
						<span class="total"><?=$total_info?></span><?php if ($base_currency_total_info): ?>*
						(<span class="base_currency_total"><?=$base_currency_total_info?></span>)<?php endif ?>
						</strong>
					</td>
				</tr>
			</table>
			<?php
				// If the customer has a currency selected that is different
				// from the base currency, then show total disclaimer.
				if ($total_disclaimer):
				?>
			<p class="text-muted">
				<small>
				<?=lang(array('string' => '*This amount is based on our current currency exchange rate to {var:1} and may differ from the exact charges (displayed above in {var:1}).', 'vars' => array(h($base_currency_name))))?>
				</small>
			</p>
			<?php endif ?>
			<?php if ($show_special_offer_code): ?>
			<div class="form-group">
				<?php if ($special_offer_code_label): ?>
				<label for="special_offer_code"><?=h($special_offer_code_label)?></label>
				<?php endif ?>
				<input type="text" name="special_offer_code" id="special_offer_code" class="form-control">
				<?php if ($special_offer_code_message): ?>
				<p class="help-block">
					<?=h($special_offer_code_message)?>
				</p>
				<?php endif ?>
			</div>
			<?php endif ?>
			<?php if ($applied_offers): ?>
			<h3>
				<?=h($number_of_applied_offers > 1 ? lang('Applied Offers') : lang('Applied Offer'))?>
			</h3>
			<?php if ($number_of_applied_offers > 1): ?>
			<ul>
				<?php else: ?>
				<p>
					<?php endif ?>
					<?php foreach($applied_offers as $offer): ?>
					<?php if ($number_of_applied_offers > 1): ?>
				<li>
					<?php endif ?>
					<strong><em><?=h($offer['description'])?></em></strong>
					<?php if ($number_of_applied_offers > 1): ?>
				</li>
				<?php endif ?>
				<?php endforeach ?>
				<?php if ($number_of_applied_offers > 1): ?>
			</ul>
			<?php else: ?>
			</p>
			<?php endif ?>
			<?php endif ?>
			<div class="form-group">
				<?php
					// formnovalidate prevents browser from validating fields
					// (e.g. required fields) when update button is clicked.
					// This allows customer to partially complete and update cart.
					// Browser validation will only occur for checkout button.
					?>
				<button type="submit" name="submit_update" class="btn btn-default btn-secondary" formnovalidate>
				<?=h($update_button_label)?>
				</button>
				<?php
					// If the purchase now button for the sidebar should be shown
					// then show it.  We don't output the purchase now button in
					// the sidebar if there are recurring items because the recurring
					// items appear below the button and might confuse customers.
					if ($purchase_now_button and !$recurring_items):
					?>
				<button
					type="submit"
					name="submit_purchase_now"
					class="purchase_button btn btn-primary"
					<?php if ($paypal_express_checkout): ?>
					data-paypal-label="<?=h(lang('Continue to PayPal'))?>"
					<?php endif ?>
					>
				<?=h($purchase_now_button_label)?>
				</button>
				<?php endif ?>
			</div>
			<?php
				// If there are nonrecurring items, then close column and row.
				if ($nonrecurring_items):
				?>
		</div>
	</div>
	<?php endif ?>
	<?php if ($recurring_items): ?>
	<h2><?=h(lang('Recurring Charges'))?></h2>
	<div class="row">
		<div class="col-lg-9">
			<table class="table mobile_stacked">
				<?php foreach($recipients as $recipient): ?>
				<?php
					// If this recipient has an item for recurring transaction
					// then show this recipient and its items.
					if ($recipient['in_recurring']):
					?>
				<?php
					// If this is a shipping recipient,
					// then show ship to heading.
					if ($recipient['ship_to_heading']):
					?>
				<tr>
					<td colspan="7">
						<h3>
							<?=h(lang('Ship to'))?>
							<?php if (ECOMMERCE_RECIPIENT_MODE == 'multi-recipient'): ?>
							<strong><?=h($recipient['ship_to_name'])?></strong>
							<?php endif ?>
						</h3>
					</td>
				</tr>
				<?php endif ?>
				<tr>
					<th><?=h(lang('Item'))?></th>
					<th><?=h(lang('Description'))?></th>
					<th><?=h(lang('Frequency'))?></th>
					<th class="text-center">
						<?php if ($recipient['non_donations_in_recurring']): ?>
						<?=h(lang('Qty'))?>
						<?php endif ?>
					</th>
					<th class="text-right">
						<?php if ($recipient['non_donations_in_recurring']): ?>
						<?=h(lang('Price'))?>
						<?php endif ?>
					</th>
					<th class="text-right"><?=h(lang('Amount'))?></th>
					<th></th>
				</tr>
				<?php foreach($recipient['items'] as $item): ?>
				<?php
					// If this item is in recurring transaction then show it.
					if ($item['in_recurring']):
					?>
				<tr>
					<td>
						<span class="visible-xs-inline"><?=h(lang('Item'))?>:</span>
						<?=h($item['name'])?>
					</td>
					<td>
						<?php
							// Use the page property to determine whether the
							// full or short description should be shown.
							if ($product_description_type == 'full_description'):
							?>
						<?php
							// If this item has an image, then start row structure
							// and output column for image.
							if ($item['image_url']):
							?>
						<div class="row">
							<div class="col-md-6">
								<?php
									// The containers around the image fixes Firefox
									// issue with responsive images in tables.
									?>
								<div class="responsive_table_image_1">
									<div class="responsive_table_image_2">
										<img src="<?=h($item['image_url'])?>" class="img-responsive img-fluid center-block">
									</div>
								</div>
							</div>
							<div class="col-md-6">
								<?php endif ?>
								<span style="font-weight:bold"><?=h($item['short_description'])?></span>
								<?=$item['full_description']?>
								<?php else: ?>
								<?=h($item['short_description'])?>
								<?php endif ?>
								<?php if ($item['show_out_of_stock_message']): ?>
								<?=$item['out_of_stock_message']?>
								<?php endif ?>
								<?php if ($item['calendar_event']): ?>
								<p>
									<?=h($item['calendar_event']['name'])?><br>
									<?=$item['calendar_event']['date_and_time_range']?>
								</p>
								<?php endif ?>
								<?php
									// If there was an image shown for this item,
									// then close column and row structure.
									if (
									    $item['image_url']
									    and ($product_description_type == 'full_description')
									):
									?>
							</div>
						</div>
						<?php endif ?>
						<?php
							// If the recurring schedule is editable
							// by the customer, and this item does not
							// appear in the nonrecurring area,
							// then show fields.
							if (
							    $item['recurring_schedule']
							    and !$item['in_nonrecurring']
							):
							?>
						<fieldset>
							<legend><?=h(lang('Payment Schedule'))?></legend>
							<div class="form-group">
								<label for="recurring_payment_period_<?=$item['id']?>">
								<?=h(lang('Frequency'))?>*
								</label>
								<select name="recurring_payment_period_<?=$item['id']?>" id="recurring_payment_period_<?=$item['id']?>" class="form-control"></select>
							</div>
							<div class="form-group">
								<label for="recurring_number_of_payments_<?=$item['id']?>">
								<?=h(lang('Number of Payments'))?><?php if ($number_of_payments_required): ?>*<?php endif ?>
								</label>
								<input type="number" name="recurring_number_of_payments_<?=$item['id']?>" id="recurring_number_of_payments_<?=$item['id']?>" class="form-control">
								<p class="help-block">
									<?=$number_of_payments_message?>
								</p>
							</div>
							<?php
								// We only allow the start date to be selected
								// for certain payment gateways.
								if ($start_date):
								?>
							<div class="form-group">
								<label for="recurring_start_date_<?=$item['id']?>">
								<?=h(lang('Start Date'))?>*
								</label>
								<input type="text" name="recurring_start_date_<?=$item['id']?>" id="recurring_start_date_<?=$item['id']?>" class="form-control">
							</div>
							<?php endif ?>
						</fieldset>
						<?php endif ?>
						<?php
							// If this item has a product form,
							// and this item does not appear
							// in nonrecurring area, then show form.
							if (
							    $item['form']
							    and !$item['in_nonrecurring']
							):
							?>
						<?=render(array(
							'template' => 'product_form.php',
							'item' => $item
							))?>
						<?php endif ?>
					</td>
					<td>
						<span class="visible-xs-inline"><?=h(lang('Frequency'))?>:</span>
						<?=h($item['payment_period'])?>
					</td>
					<td class="text-center">
						<?php
							// If the item is not a donation then show quantity.
							if ($item['selection_type'] != 'donation'):
							?>
						<?php
							// If the item was added by an offer,
							// or was already shown in nonrecurring area,
							// then just show uneditable quantity amount,
							// (i.e. don't allow customer to change quantity).
							if ($item['added_by_offer'] or $item['in_nonrecurring']):
							?>
						<?=number_format($item['quantity'])?>
						<?php
							// Otherwise allow customer to change quantity.
							else:
							?>
						<div class="form-group">
							<label for="quantity[<?=$item['id']?>]" class="visible-xs-inline-block">
							<?=h(lang('Qty'))?>
							</label>
							<input type="number" name="quantity[<?=$item['id']?>]" id="quantity[<?=$item['id']?>]" class="form-control" style="min-width: 5em">
						</div>
						<?php endif ?>
						<?php endif ?>
					</td>
					<td class="text-right">
						<?php if ($item['selection_type'] != 'donation'): ?>
						<span class="visible-xs-inline"><?=h(lang('Price'))?>:</span>
						<?=$item['price_info']?>
						<?php endif ?>
					</td>
					<td class="text-right">
						<?php
							// If the item is a donation and it is not
							// already listed in nonrecurring area,
							// then allow customer to edit amount.
							if (
							    ($item['selection_type'] == 'donation')
							    and !$item['in_nonrecurring']
							):
							?>
						<div class="form-group">
							<label for="donations[<?=$item['id']?>]" class="visible-xs-inline-block">
							<?=h(lang('Amount'))?>
							</label>
							<div class="input-group">
								<span class="input-group-addon">
								<?=$currency_symbol?>
								</span>
								<input type="number" step="any" name="donations[<?=$item['id']?>]" id="donations[<?=$item['id']?>]" class="form-control" style="min-width: 6em">
								<?php if ($currency_code): ?>
								<span class="input-group-addon">
								<?=h($currency_code)?>
								</span>
								<?php endif ?>
							</div>
						</div>
						<?php else: ?>
						<span class="visible-xs-inline"><?=h(lang('Amount'))?>:</span>
						<?=$item['amount_info']?>
						<?php endif ?>
					</td>
					<td class="text-center">
						<?php
							// If the item is not already listed in
							// nonrecurring area, then show remove button.
							// We don't want multiple remove buttons
							// for the same item that could confuse customer.
							if (!$item['in_nonrecurring']):
							?>
						<a href="<?=h($item['remove_url'])?>" class="btn btn-default btn-secondary btn-sm" title="<?=h(lang('Remove'))?>">
						<span class="glyphicon glyphicon-remove"></span>
						</a>
						<?php endif ?>
					</td>
				</tr>
				<?php endif ?>
				<?php endforeach ?>
				<?php endif ?>
				<?php endforeach ?>
			</table>
		</div>
		<div class="col-lg-3">
			<h3><?=h(lang('Totals'))?></h3>
			<table class="table">
				<?php
					// Loop through the payment periods in order to show totals.
					foreach($payment_periods as $payment_period):
					?>
				<tr>
					<th scope="row" class="text-right" style="width: 100%">
						<?=h($payment_period['name'])?> <?=h(lang('Subtotal'))?>:
					</th>
					<td class="text-right">
						<?=$payment_period['subtotal_info']?>
					</td>
				</tr>
				<?php if ($payment_period['tax_info']): ?>
				<tr>
					<th scope="row" class="text-right" style="width: 100%">
						<?=h($payment_period['name'])?> <?=h(lang('Tax'))?>:
					</th>
					<td class="text-right">
						<?=$payment_period['tax_info']?>
					</td>
				</tr>
				<?php endif ?>
				<tr>
					<th scope="row" class="text-right" style="width: 100%">
						<?=h($payment_period['name'])?> <?=h(lang('Total'))?>:
					</th>
					<td class="text-right">
						<strong><?=$payment_period['total_info']?></strong>
					</td>
				</tr>
				<?php endforeach ?>
			</table>
		</div>
	</div>
	<?php endif ?>
	<h2><?=h(lang('Billing'))?></h2>
	<?php if ($billing_same_as_shipping): ?>
	<div class="checkbox">
		<label>
		<input
			type="checkbox"
			id="billing_same_as_shipping">
		<?=h(lang('Billing Address Same as Shipping'))?>
		</label>
	</div>
	<?php endif ?>    
	<?php if ($custom_field_1): ?>
	<div class="form-group">
		<label for="custom_field_1"><?=h($custom_field_1_label)?><?php if ($custom_field_1_required): ?>*<?php endif ?></label>
		<input type="text" name="custom_field_1" id="custom_field_1" class="form-control">
	</div>
	<?php endif ?>
	<?php if ($custom_field_2): ?>
	<div class="form-group">
		<label for="custom_field_2"><?=h($custom_field_2_label)?><?php if ($custom_field_2_required): ?>*<?php endif ?></label>
		<input type="text" name="custom_field_2" id="custom_field_2" class="form-control">
	</div>
	<?php endif ?>
	<div class="form-group">
		<label for="billing_salutation"><?=h(lang('Salutation'))?></label>
		<select
			name="billing_salutation"
			id="billing_salutation"
			autocomplete="section-billing billing honorific-prefix"
			class="form-control">
		</select>
	</div>
	<div class="form-group">
		<label for="billing_first_name"><?=h(lang('First Name'))?>*</label>
		<input
			type="text"
			name="billing_first_name"
			id="billing_first_name"
			autocomplete="section-billing billing given-name"
			spellcheck="false"
			class="form-control">
	</div>
	<div class="form-group">
		<label for="billing_last_name"><?=h(lang('Last Name'))?>*</label>
		<input
			type="text"
			name="billing_last_name"
			id="billing_last_name"
			autocomplete="section-billing billing family-name"
			spellcheck="false"
			class="form-control">
	</div>
	<div class="form-group">
		<label for="billing_company"><?=h(lang('Company'))?></label>
		<input
			type="text"
			name="billing_company"
			id="billing_company"
			autocomplete="section-billing billing organization"
			spellcheck="false"
			class="form-control">
	</div>
	<div class="form-group">
		<label for="billing_address_1"><?=h(lang('Address 1'))?>*</label>
		<input
			type="text"
			name="billing_address_1"
			id="billing_address_1"
			autocomplete="section-billing billing address-line1"
			spellcheck="false"
			class="form-control">
	</div>
	<div class="form-group">
		<label for="billing_address_2"><?=h(lang('Address 2'))?></label>
		<input
			type="text"
			name="billing_address_2"
			id="billing_address_2"
			autocomplete="section-billing billing address-line2"
			spellcheck="false"
			class="form-control">
	</div>
	<div class="form-group">
		<label for="billing_city"><?=h(lang('City'))?>*</label>
		<input
			type="text"
			name="billing_city"
			id="billing_city"
			autocomplete="section-billing billing address-level2"
			spellcheck="false"
			class="form-control">
	</div>
	<div class="form-group">
		<label for="billing_country"><?=h(lang('Country'))?>*</label>
		<select
			name="billing_country"
			id="billing_country"
			autocomplete="section-billing billing country"
			class="form-control">
		</select>
	</div>
	<div class="form-group">
		<label for="billing_state_text_box"><?=h(lang('State / Province'))?></label>
		<input
			type="text"
			name="billing_state"
			id="billing_state_text_box"
			autocomplete="section-billing billing address-level1"
			spellcheck="false"
			class="form-control">
		<label for="billing_state_pick_list" style="display: none"><?=h(lang('State / Province'))?>*</label>
		<select
			name="billing_state"
			id="billing_state_pick_list"
			autocomplete="section-billing billing address-level1"
			class="form-control"
			style="display: none">
		</select>
	</div>
	<div class="form-group">
		<label for="billing_zip_code"><?=h(lang('Zip / Postal Code'))?>*</label>
		<input
			type="text"
			name="billing_zip_code"
			id="billing_zip_code"
			autocomplete="section-billing billing postal-code"
			spellcheck="false"
			class="form-control">
	</div>
	<div class="form-group">
		<label for="billing_phone_number"><?=h(lang('Phone'))?>*</label>
		<input
			type="tel"
			name="billing_phone_number"
			id="billing_phone_number"
			autocomplete="section-billing billing tel"
			spellcheck="false"
			class="form-control">
	</div>
	<div class="form-group">
		<label for="billing_email_address"><?=h(lang('Email'))?>*</label>
		<input
			type="email"
			name="billing_email_address"
			id="billing_email_address"
			autocomplete="section-billing billing email"
			spellcheck="false"
			class="form-control">
	</div>
	<?php if ($opt_in): ?>
	<div class="checkbox">
		<label>
		<input type="checkbox" name="opt_in" value="1">
		<?=h($opt_in_label)?>
		</label>
	</div>
	<?php endif ?>
	<?php if ($po_number): ?>
	<div class="form-group">
		<label for="po_number"><?=h(lang('PO Number'))?></label>
		<input
			type="text"
			name="po_number"
			id="po_number"
			spellcheck="false"
			class="form-control">
	</div>
	<?php endif ?>
	<?php if ($referral_source): ?>
	<div class="form-group">
		<label for="referral_source"><?=h(lang('How did you hear about us?'))?></label>
		<select name="referral_source" id="referral_source" class="form-control"></select>
	</div>
	<?php endif ?>
	<?php if ($update_contact): ?>
	<div class="checkbox">
		<label>
		<input type="checkbox" name="update_contact" value="1">
		<?=h(lang('Update my contact info with this billing info.'))?>
		</label>
	</div>
	<?php endif ?>
	<?php if ($tax_exempt): ?>
	<div class="checkbox">
		<label>
		<input type="checkbox" name="tax_exempt" value="1">
		<?=h($tax_exempt_label)?>
		</label>
	</div>
	<?php endif ?>
	<?php if ($custom_billing_form): ?>
	<?=
		// Add edit button and grid if edit mode is enabled.
		$edit_custom_billing_form_start
		?>
	<?php if ($custom_billing_form_title): ?>
	<h2><?=h($custom_billing_form_title)?></h2>
	<?php endif ?>
	<?=eval('?>' . generate_form_layout_content(array('page_id' => $page_id, 'form_type' => 'billing', 'indent' => '            ')))?>
	<?=
		// Close the edit grid.
		$edit_custom_billing_form_end
		?>
	<?php endif ?>
	<?php if ($applied_gift_cards): ?>
	<h2>
		<?=h($number_of_applied_gift_cards > 1 ? lang('Applied Gift Cards') : lang('Applied Gift Card'))?>
	</h2>
	<?php if ($number_of_applied_gift_cards > 1): ?>
	<ul>
		<?php else: ?>
		<p>
			<?php endif ?>
			<?php foreach($applied_gift_cards as $gift_card): ?>
			<?php if ($number_of_applied_gift_cards > 1): ?>
		<li>
			<?php endif ?>
			<?=h($gift_card['protected_code'])?>
			(<?=h(lang('Remaining Balance'))?>: <?=$gift_card['remaining_balance_info']?>)
			<a href="<?=h($gift_card['remove_url'])?>" class="btn btn-default btn-secondary btn-sm" title="<?=h(lang('Remove'))?>">
			<span class="glyphicon glyphicon-remove"></span>
			</a>
			<?php if ($number_of_applied_gift_cards > 1): ?>
		</li>
		<?php endif ?>
		<?php endforeach ?>
		<?php if ($number_of_applied_gift_cards > 1): ?>
	</ul>
	<?php else: ?>
	</p>
	<?php endif ?>
	<?php endif ?>
	<?php if ($payment): ?>
	<h2><?=h(lang('Payment'))?></h2>
	<?php if ($gift_card_code): ?>
	<div class="form-group form-inline">
		<div class="form-group">
			<label for="gift_card_code"><?=h(lang('Gift Card Code'))?></label>
			<input type="text" name="gift_card_code" id="gift_card_code" class="form-control">
		</div>
		<button type="submit" name="submit_apply_gift_card" class="btn btn-default btn-secondary btn-sm" formnovalidate>
		<?=h(lang('Apply'))?>
		</button>
	</div>
	<?php endif ?>
	<?php if ($credit_debit_card): ?>
	<?php
		// If this is the only payment method, then show a heading.
		if ($number_of_payment_methods == 1):
		?>
	<h3><?=h(lang('Credit/Debit Card'))?></h3>
	<?php
		// Otherwise there are multiple payment methods, so show radio button.
		else:
		?>
	<div class="radio">
		<label>
		<input type="radio" name="payment_method" value="Credit/Debit Card">
		<?=h(lang('Credit/Debit Card'))?>
		</label>
	</div>
	<?php endif ?>
	<?php
		// This container and class allows the credit/debit card
		// fields to be dynamically shown/hidden as necesssary.
		?>
	<div class="credit_debit_card" style="display: none">
		<div class="row">
			<div class="col-sm-4">
				<div class="form-group">
					<label for="card_number"><?=h(lang('Card Number'))?>*</label>
					<input
						type="tel"
						id="card_number"
						name="card_number" 
						autocomplete="cc-number"
						spellcheck="false"
						inputmode="numeric"
						class="form-control">
				</div>
			</div>
			<div class="col-sm-3 col-xs-6">
				<?php // Use the following to have one text box for expiration. ?>
				<div class="form-group">
					<label for="expiration"><?=h(lang('Expiration'))?>*</label>
					<input
						type="tel"
						id="expiration"
						name="expiration" 
						autocomplete="cc-exp"
						spellcheck="false"
						inputmode="numeric"
						placeholder="<?=h(lang('MM / YY'))?>"
						class="form-control">
				</div>
				<?php
					/* Or, you can use the following to have two pick lists for
					expiration (month and year).
					
					<div class="form-group">
					    <label for="expiration_month">Expiration*</label>
					    <select
					        id="expiration_month"
					        name="expiration_month"
					        class="form-control">
					    </select>
					</div>
					
					<div class="form-group">
					    <select
					        id="expiration_year"
					        name="expiration_year"
					        class="form-control">
					    </select>
					</div>
					*/
					?>
			</div>
			<?php
				// If you do not need to collect the security code, and your gateway
				// allows it, then you may remove the code below.
				?>
			<div class="col-sm-3 col-xs-6">
				<div class="form-group">
					<label for="card_verification_number"><?=h(lang('Security Code'))?>*</label>
					<input
						type="tel"
						id="card_verification_number"
						name="card_verification_number" 
						autocomplete="cc-csc"
						spellcheck="false"
						inputmode="numeric"
						placeholder="<?=h(lang('CSC'))?>"
						maxlength="4"
						class="form-control">
					<?php if ($card_verification_number_url): ?>
					<p class="help-block">
						<a href="<?=h($card_verification_number_url)?>" target="_blank"><?=h(lang('What is this?'))?></a>
					</p>
					<?php endif ?>  
				</div>
			</div>
			<?php
				/* If you need to collect cardholder info (i.e. name on the card), then
				you may uncomment the code below.
				
				<div class="col-sm-4">
				    <div class="form-group">
				        <label for="cardholder">Cardholder*</label>
				        <input
				            type="text"
				            id="cardholder"
				            name="cardholder"
				            autocomplete="cc-name"
				            spellcheck="false"
				            class="form-control">
				    </div>
				</div>
				*/
				?>
		</div>
		<?php
			// We only show the surcharge message if there are other
			// payment method options (e.g. PayPal Express Checkout, Offline)
			// because the total might not include the surcharge until
			// the customer selects the credit/debit card payment method.
			if ($surcharge_message):
			?>
		<p class="text-muted">
			<small>
			<?=h(lang(array('string' => '{var:1}% surcharge has been added.', 'vars' => array($surcharge_percentage))))?>
			</small>
		</p>
		<?php endif ?>
	</div>
	<?php endif ?>
	<?php if ($paypal_express_checkout): ?>
	<?php
		// If this is the only payment method, then just show the PayPal image.
		if ($number_of_payment_methods == 1):
		?>
	<h3><img src="<?=h($paypal_express_checkout_image_url)?>" alt="PayPal"></h3>
	<?php
		// Otherwise there are multiple payment methods, so show radio button.
		else:
		?>
	<div class="radio">
		<label>
		<input type="radio" name="payment_method" value="PayPal Express Checkout">
		<img src="<?=h($paypal_express_checkout_image_url)?>" alt="PayPal">
		</label>
	</div>
	<?php endif ?>
	<?php endif ?>
	<?php if ($offline_payment): ?>
	<?php
		// If this is the only payment method, then show a heading.
		if ($number_of_payment_methods == 1):
		?>
	<h3><?=h($offline_payment_label)?></h3>
	<?php
		// Otherwise there are multiple payment methods, so show radio button.
		else:
		?>
	<div class="radio">
		<label>
		<input type="radio" name="payment_method" value="Offline Payment">
		<?=h($offline_payment_label)?>
		</label>
	</div>
	<?php endif ?>
	<?php endif ?>
	<?php endif ?>
	<?php if ($offline_payment_allowed): ?>
	<div class="checkbox">
		<label>
		<input type="checkbox" name="offline_payment_allowed" value="1">
		<?=lang(array('string' => 'Allow offline payment option for this {var:1} (and click update to apply).', 'vars' => array(h($shopping_cart_label))))?>
		</label>
	</div>
	<?php endif ?>
	<?php if ($terms_url): ?>
	<div class="checkbox">
		<label>
		<input type="checkbox" name="terms" value="1">
		<?=lang(array('string' => 'I agree to the <a href="{var:1}" target="_blank">terms and conditions</a>.', 'vars' => array(h($terms_url))))?>
		</label>
	</div>
	<?php endif ?>
	<div class="form-group">
		<?php
			// formnovalidate prevents browser from validating fields
			// (e.g. required fields) when update button is clicked.
			// This allows customer to partially complete and update cart.
			// Browser validation will only occur for checkout button.
			?>
		<button type="submit" name="submit_update" class="btn btn-default btn-secondary" formnovalidate>
		<?=h($update_button_label)?>
		</button>
		<?php
			// If there is at least one payment method and order is
			// allowed to be submitted, then show purchase now button.
			if ($purchase_now_button):
			?>
		<button
			type="submit"
			name="submit_purchase_now"
			class="purchase_button btn btn-primary"
			<?php if ($paypal_express_checkout): ?>
			data-paypal-label="<?=h(lang('Continue to PayPal'))?>"
			<?php endif ?>
			>
		<?=h($purchase_now_button_label)?>
		</button>
		<?php endif ?>
	</div>
	<?=$system // Required hidden fields and JS (do not remove) ?>
</form>
<p class="text-muted">
	<small>
	<?=lang(array('string' => 'This {var:1} has been saved. To retrieve this {var:1} at a later time, please use this link:', 'vars' => array(h($shopping_cart_label))))?><br>
	<a href="<?=h($retrieve_order_url)?>"><?=h($retrieve_order_url)?></a>
	</small>
</p>
<?php endif ?>
<?php if ($currency): ?>
<form <?=$currency_attributes?>>
	<div class="form-group">
		<label for="currency_id" class="sr-only"><?=h(lang('Currency'))?></label>
		<select name="currency_id" id="currency_id" class="form-control"></select>
	</div>
	<?=$currency_system // Required hidden fields and JS (do not remove) ?>
</form>
<?php endif ?>