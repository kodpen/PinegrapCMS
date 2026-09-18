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
<?php foreach($order_receipt_messages as $message): ?>
<?=$message?>
<?php endforeach ?>
<dl class="dl-horizontal">
	<dt><?=h(lang('Order Number'))?></dt>
	<dd><strong><?=$order_number?></strong></dd>
	<dt><?=h(lang('Order Date'))?></dt>
	<dd>
		<?=get_absolute_time(array(
			'timestamp' => $order_date,
			
			'size' => 'long'))?>
	</dd>
</dl>
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
				<td colspan="5">
					<h3>
						<?=h(lang('Ship to'))?>
						<?php if (ECOMMERCE_RECIPIENT_MODE == 'multi-recipient'): ?>
						<strong><?=h($recipient['ship_to_name'])?></strong>
						<?php endif ?>
					</h3>
					<p>
						<?=h($recipient['name'])?>
						<?php if ($recipient['address']): ?>
						<?php if ($recipient['name']): ?>
						<br>
						<?php endif ?>
						<?=h($recipient['address'])?>
						<?php endif ?>
					</p>
					<?php
						// If there is a custom shipping form,
						
						// then allow customer to review it.
						
						if ($recipient['form']):
						
						?>
					<?php if ($recipient['form_title']): ?>
					<h4><?=h($recipient['form_title'])?></h4>
					<?php endif ?>
					<dl class="dl-horizontal">
						<?php foreach ($recipient['fields'] as $field): ?>
						<?php if ($field['type'] == 'information'): ?>
						<?=$field['information']?>
						<?php else: ?>
						<dt><?=$field['label']?></dt>
						<dd><?=$field['data_info']?></dd>
						<?php endif ?>
						<?php endforeach ?>
					</dl>
					<?php endif ?>
					<?php
						// If there are active arrival dates,
						
						// then show arrival date info.
						
						if ($arrival_dates):
						
						?>
					<p>
						<?=h(lang('Requested Arrival Date'))?>:
						<?php
							// If this is a custom arrival date
							
							// then show actual date.
							
							if ($recipient['arrival_date_custom']):
							
							?>
						<?=get_absolute_time(array(
							'timestamp' => strtotime($recipient['arrival_date']),
							
							'type' => 'date',
							
							'size' => 'long'))?>
						<?php
							// Otherwise the arrival date is not custom,
							
							// so show arrival date name.
							
							else:
							
							?>
						<?=h($recipient['arrival_date_name'])?>
						<?php endif ?>
					</p>
					<?php endif ?>
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
						
						// by the customer, then show values.
						
						if ($item['recurring_schedule']):
						
						?>
					<h4><?=h(lang('Payment Schedule'))?></h4>
					<dl class="dl-horizontal">
						<dt><?=h(lang('Frequency'))?></dt>
						<dd><?=h($item['recurring_payment_period'])?></dd>
						<dt><?=h(lang('Number of Payments'))?></dt>
						<dd>
							<?php if ($item['recurring_number_of_payments']): ?>
							<?=number_format($item['recurring_number_of_payments'])?>
							<?php else: ?>
							[<?=h(lang('no limit'))?>]
							<?php endif ?>
						</dd>
						<?php
							// We only allow the start date to be selected
							
							// for certain payment gateways.
							
							if ($start_date):
							
							?>
						<dt><?=h(lang('Start Date'))?></dt>
						<dd>
							<?=get_absolute_time(array('timestamp' => strtotime($item['recurring_start_date']), 'type' => 'date', 'size' => 'long'))?>
						</dd>
						<?php endif ?>
					</dl>
					<?php endif ?>
					<?php
						// If this is a gift card, then show info.
						
						if ($item['gift_card']):
						
						?>
					<?php foreach($item['gift_cards'] as $gift_card): ?>
					<h4>
						<?=h(lang('Gift Card'))?>
						<?php if ($item['number_of_gift_cards'] > 1): ?>
						(<?=h(lang(array('string' => '{var:1} of {var:2}', 'vars' => array($gift_card['quantity_number'], $item['number_of_gift_cards']))))?>)
						<?php endif ?>
					</h4>
					<dl class="dl-horizontal">
						<dt><?=h(lang('Amount'))?></dt>
						<dd><strong><?=$item['price_info']?></strong></dd>
						<dt><?=h(lang('Recipient Email'))?></dt>
						<dd><?=h($gift_card['recipient_email_address'])?></dd>
						<dt><?=h(lang('Your Name'))?></dt>
						<dd><?=h($gift_card['from_name'])?></dd>
						<dt><?=h(lang('Message'))?></dt>
						<dd><?=nl2br(h($gift_card['message']))?></dd>
						<dt><?=h(lang('Delivery Date'))?></dt>
						<dd>
							<?php if ($gift_card['delivery_date'] == '0000-00-00'): ?>
							<?=h(lang('Immediate'))?>
							<?php else: ?>
							<?=get_absolute_time(array('timestamp' => strtotime($gift_card['delivery_date']), 'type' => 'date', 'size' => 'long'))?>
							<?php endif ?>
						</dd>
					</dl>
					<?php endforeach ?>
					<?php endif ?>
					<?php
						// If this item has a product form,
						
						// then show form for review.
						
						if ($item['form']):
						
						?>
					<?php foreach($item['forms'] as $form): ?>
					<h4>
						<?php if ($item['form_title']): ?>
						<?=h($item['form_title'])?>
						<?php endif ?>
						<?php if ($item['number_of_forms'] > 1): ?>
						(<?=h(lang(array('string' => '{var:1} of {var:2}', 'vars' => array($form['quantity_number'], $item['number_of_forms']))))?>)
						<?php endif ?>
					</h4>
					<dl class="dl-horizontal">
						<?php foreach ($form['fields'] as $field): ?>
						<?php if ($field['type'] == 'information'): ?>
						<?=$field['information']?>
						<?php else: ?>
						<dt><?=$field['label']?></dt>
						<dd><?=$field['data_info']?></dd>
						<?php endif ?>
						<?php endforeach ?>
					</dl>
					<?php endforeach ?>
					<?php endif ?>
				</td>
				<td class="text-center">
					<?php
						// If the item is not a donation then show quantity.
						
						if ($item['selection_type'] != 'donation'):
						
						?>
					<?=number_format($item['quantity'])?>
					<?php endif ?>
				</td>
				<td class="text-right">
					<?php if ($item['selection_type'] != 'donation'): ?>
					<span class="visible-xs-inline"><?=h(lang('Price'))?>:</span>
					<?=$item['price_info']?>
					<?php endif ?>
				</td>
				<td class="text-right">
					<span class="visible-xs-inline"><?=h(lang('Amount'))?>:</span>
					<?=$item['amount_info']?>
				</td>
			</tr>
			<?php endif ?>
			<?php endforeach ?>
			<?php
				// If this is a shipping recipient then show shipping fee row.
				
				if ($recipient['shipping']):
				
				?>
			<tr>
				<td colspan="2">
					<?=h(lang('Shipping Method'))?>:
					<?=h($recipient['shipping_method_name'])?><?php if ($recipient['shipping_method_description']): ?>; <?=h($recipient['shipping_method_description'])?>
					<?php endif ?>
				</td>
				<td></td>
				<td></td>
				<td class="text-right">
					<span class="visible-xs-inline"><?=h(lang('Shipping'))?>:</span>
					<?=$recipient['shipping_cost_info']?>
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
				
				// would be redundant with the total.
				
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
				<td class="text-right"><?=$shipping_info?></td>
			</tr>
			<?php endif ?>
			<?php if ($gift_card_discount_info): ?>
			<tr>
				<th scope="row" class="text-right"><?=h($number_of_applied_gift_cards > 1 ? lang('Gift Cards') : lang('Gift Card'))?>:</th>
				<td class="text-right">-<?=$gift_card_discount_info?></td>
			</tr>
			<?php endif ?>
			<?php if ($surcharge_info): ?>
			<tr>
				<th scope="row" class="text-right"><?=h(lang('Surcharge'))?>:</th>
				<td class="text-right"><?=$surcharge_info?></td>
			</tr>
			<?php endif ?>
			<tr>
				<th scope="row" class="text-right" style="width: 100%"><?=h(lang('Total'))?>:</th>
				<td class="text-right">
					<strong>
					<?=$total_info?><?php if ($base_currency_total_info): ?>*
					(<?=$base_currency_total_info?>)<?php endif ?>
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
				<td colspan="6">
					<h3>
						<?=h(lang('Ship to'))?>
						<?php if (ECOMMERCE_RECIPIENT_MODE == 'multi-recipient'): ?>
						<strong><?=h($recipient['ship_to_name'])?></strong>
						<?php endif ?>
					</h3>
					<?php
						// If the address and other info about
						
						// this recipient was not already shown
						
						// in nonrecurring area, then show it.
						
						if (!$recipient['in_nonrecurring']):
						
						?>
					<p>
						<?=h($recipient['name'])?>
						<?php if ($recipient['address']): ?>
						<?php if ($recipient['name']): ?>
						<br>
						<?php endif ?>
						<?=h($recipient['address'])?>
						<?php endif ?>
					</p>
					<?php
						// If there is a custom shipping form,
						
						// then allow customer to review it.
						
						if ($recipient['form']):
						
						?>
					<?php if ($recipient['form_title']): ?>
					<h4><?=h($recipient['form_title'])?></h4>
					<?php endif ?>
					<dl class="dl-horizontal">
						<?php foreach ($recipient['fields'] as $field): ?>
						<?php if ($field['type'] == 'information'): ?>
						<?=$field['information']?>
						<?php else: ?>
						<dt><?=$field['label']?></dt>
						<dd><?=$field['data_info']?></dd>
						<?php endif ?>
						<?php endforeach ?>
					</dl>
					<?php endif ?>
					<?php endif ?>
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
					<h4><?=h(lang('Payment Schedule'))?></h4>
					<dl class="dl-horizontal">
						<dt><?=h(lang('Frequency'))?></dt>
						<dd><?=h($item['recurring_payment_period'])?></dd>
						<dt><?=h(lang('Number of Payments'))?></dt>
						<dd>
							<?php if ($item['recurring_number_of_payments']): ?>
							<?=number_format($item['recurring_number_of_payments'])?>
							<?php else: ?>
							[<?=h(lang('no limit'))?>]
							<?php endif ?>
						</dd>
						<?php
							// We only allow the start date to be selected
							
							// for certain payment gateways.
							
							if ($start_date):
							
							?>
						<dt><?=h(lang('Start Date'))?></dt>
						<dd>
							<?=get_absolute_time(array('timestamp' => strtotime($item['recurring_start_date']), 'type' => 'date', 'size' => 'long'))?>
						</dd>
						<?php endif ?>
					</dl>
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
					<?php foreach($item['forms'] as $form): ?>
					<h4>
						<?php if ($item['form_title']): ?>
						<?=h($item['form_title'])?>
						<?php endif ?>
						<?php if ($item['number_of_forms'] > 1): ?>
						(<?=h(lang(array('string' => '{var:1} of {var:2}', 'vars' => array($form['quantity_number'], $item['number_of_forms']))))?>)
						<?php endif ?>
					</h4>
					<dl class="dl-horizontal">
						<?php foreach ($form['fields'] as $field): ?>
						<?php if ($field['type'] == 'information'): ?>
						<?=$field['information']?>
						<?php else: ?>
						<dt><?=$field['label']?></dt>
						<dd><?=$field['data_info']?></dd>
						<?php endif ?>
						<?php endforeach ?>
					</dl>
					<?php endforeach ?>
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
					<?=number_format($item['quantity'])?>
					<?php endif ?>
				</td>
				<td class="text-right">
					<?php if ($item['selection_type'] != 'donation'): ?>
					<span class="visible-xs-inline"><?=h(lang('Price'))?>:</span>
					<?=$item['price_info']?>
					<?php endif ?>
				</td>
				<td class="text-right">
					<span class="visible-xs-inline"><?=h(lang('Amount'))?>:</span>
					<?=$item['amount_info']?>
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
<div>
	<?php if ($custom_field_1_label and $custom_field_1): ?>
	<?=h($custom_field_1_label)?>: <?=h($custom_field_1)?><br>
	<?php endif ?>
	<?php if ($custom_field_2_label and $custom_field_2): ?>
	<?=h($custom_field_2_label)?>: <?=h($custom_field_2)?><br>
	<?php endif ?>
	<?=h($billing_salutation)?> <?=h($billing_first_name)?> <?=h($billing_last_name)?><br>
	<?php if ($billing_company): ?>
	<?=h($billing_company)?><br>
	<?php endif ?>
	<?=h($billing_address_1)?><br>
	<?php if ($billing_address_2): ?>
	<?=h($billing_address_2)?><br>
	<?php endif ?>
	<?=h($billing_city)?>, <?=h($billing_state)?> <?=h($billing_zip_code)?><br>
	<?=h($billing_country)?><br>
	<?=h(lang('Phone'))?>: <?=h($billing_phone_number)?><br>
	<?php if ($billing_fax_number): ?>
	<?=h(lang('Fax'))?>: <?=h($billing_fax_number)?><br>
	<?php endif ?>
	<?=h($billing_email_address)?><br>
	<?php if ($po_number): ?>
	<?=h(lang('PO Number'))?>: <?=h($po_number)?><br>
	<?php endif ?>
	<?php if ($tax_exempt): ?>
	<?=h(lang('Tax-Exempt'))?><br>
	<?php endif ?>
	<?php
		// If there is a custom billing form,
		
		// then allow customer to review it.
		
		if ($billing_form):
		
		?>
	<?php if ($billing_form_title): ?>
	<h4><?=h($billing_form_title)?></h4>
	<?php endif ?>
	<dl class="dl-horizontal">
		<?php foreach ($fields as $field): ?>
		<?php if ($field['type'] == 'information'): ?>
		<?=$field['information']?>
		<?php else: ?>
		<dt><?=$field['label']?></dt>
		<dd><?=$field['data_info']?></dd>
		<?php endif ?>
		<?php endforeach ?>
	</dl>
	<?php endif ?>
</div>
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
		(<?=h(lang('Remaining Balance'))?>: <?=$gift_card['new_balance_info']?>)
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
<?php if ($payment_method): ?>
<h2><?=h(lang('Payment'))?></h2>
<dl class="dl-horizontal">
	<dt><?=h(lang('Payment Method'))?></dt>
	<dd><?=h($payment_method_label)?></dd>
	<?php if ($payment_method == 'Credit/Debit Card'): ?>
	<dt><?=h(lang('Card Type'))?></dt>
	<dd><?=h($card_type)?></dd>
	<dt><?=h(lang('Card Number'))?></dt>
	<dd><?=h($card_number)?></dd>
	<?php endif ?>
</dl>
<?php endif ?>
<?php if ($auto_registration): ?>
<h2><?=h(lang('New Account'))?></h2>
<p><?=h(lang('We have created a new account for you so you can view your orders on our site. You can find your login info below.'))?></p>
<dl class="dl-horizontal">
	<dt><?=h(lang('Email'))?></dt>
	<dd><?=h($auto_registration_email_address)?></dd>
	<dt><?=h(lang('Password'))?></dt>
	<dd><?=h($auto_registration_password)?></dd>
</dl>
<?php endif ?>