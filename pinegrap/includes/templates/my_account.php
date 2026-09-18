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
<div class="row">
	<div class="col-sm-6">
		<h2>
			<?=h(lang('Account'))?>
			<?php if ($logout_url): ?>
			<a href="<?=h($logout_url)?>" class="btn btn-default btn-secondary btn-xs">
			<?=h(lang('Logout'))?>
			</a>
			<?php endif ?>
			<?php if ($change_password_url): ?>
			<a href="<?=h($change_password_url)?>" class="btn btn-default btn-secondary btn-xs">
			<?=h(lang('Change Password'))?>
			</a>
			<?php endif ?>
		</h2>
		<dl class="dl-horizontal">
			<dt><?=h(lang('Username'))?></dt>
			<dd><?=h(USER_USERNAME)?></dd>
			<?php if (!empty($google_account_notice)): ?>
			<dt><?=h(lang('Sign-in'))?></dt>
			<dd><?=$google_account_notice?></dd>
			<?php endif ?>
			<?php if ($start_page_url): ?>
			<dt><?=h(lang('My Start Page'))?></dt>
			<dd><a href="<?=h($start_page_url)?>"><?=h($start_page_name)?></a></dd>
			<?php endif ?>
			<?php if ($reward_points): ?>
			<dt><?=h(lang('Reward Points'))?></dt>
			<dd><?=h(number_format($reward_points))?></dd>
			<?php endif ?>
		</dl>
	</div>
	<div class="col-sm-6">
		<h2>
			<?=h(lang('Email Preferences'))?>
			<?php if ($email_preferences_url): ?>
			<a href="<?=h($email_preferences_url)?>" class="btn btn-default btn-secondary btn-xs">
			<?=h(lang('Update'))?>
			</a>
			<?php endif ?>
		</h2>
		<p><?=h(USER_EMAIL_ADDRESS)?></p>
	</div>
</div>
<div class="row">
	<div class="col-sm-6">
		<h2>
			<?=h(lang('Profile'))?>
			<?php if ($my_account_profile_url): ?>
			<a href="<?=h($my_account_profile_url)?>" class="btn btn-default btn-secondary btn-xs">
			<?=h(lang('Update'))?>
			</a>
			<?php endif ?>
		</h2>
		<dl class="dl-horizontal">
			<?php if (
				$first_name or $last_name or $title or $company or $business_address_1
				
				or $business_address_2 or $business_city or $business_state or $business_zip_code
				
				or $business_country
				
				):?>
			<dt><?=h(lang('Contact'))?></dt>
			<dd>
				<?php if ($first_name or $last_name): ?>
				<?=h($first_name)?> <?=h($last_name)?><br>
				<?php endif ?>
				<?php if ($title): ?>
				<?=h($title)?><br>
				<?php endif ?>
				<?php if ($company): ?>
				<?=h($company)?><br>
				<?php endif ?>
				<?php if ($business_address_1): ?>
				<?=h($business_address_1)?><br>
				<?php endif ?>
				<?php if ($business_address_2): ?>
				<?=h($business_address_2)?><br>
				<?php endif ?>
				<?php if ($business_city): ?>
				<?=h($business_city)?><?php if ($business_state or $business_zip_code): ?>, <?php endif ?>
				<?php endif ?>
				<?php if ($business_state): ?>
				<?=h($business_state)?>
				<?php endif ?>
				<?php if ($business_zip_code): ?>
				<?=h($business_zip_code)?>
				<?php endif ?>
				<?php if (
					$business_city
					
					or $business_state
					
					or $business_zip_code
					
					):?>
				<br>
				<?php endif ?>
				<?php if ($business_country): ?>
				<?=h($business_country)?><br>
				<?php endif ?>
			</dd>
			<?php endif ?>
			<?php if ($business_phone): ?>
			<dt><?=h(lang('Main'))?></dt>
			<dd><?=h($business_phone)?></dd>
			<?php endif ?>
			<?php if ($mobile_phone): ?>
			<dt><?=h(lang('Mobile'))?></dt>
			<dd><?=h($mobile_phone)?></dd>
			<?php endif ?>
			<?php if ($home_phone): ?>
			<dt><?=h(lang('Home'))?></dt>
			<dd><?=h($home_phone)?></dd>
			<?php endif ?>
			<?php if ($business_fax): ?>
			<dt><?=h(lang('Fax'))?></dt>
			<dd><?=h($business_fax)?></dd>
			<?php endif ?>
			<?php if ($timezone): ?>
			<dt><?=h(lang('Timezone'))?></dt>
			<dd><?=h($timezone)?></dd>
			<?php endif ?>
		</dl>
	</div>
	<?php if (defined('USER_MEMBER_ID') && USER_MEMBER_ID): ?>
	<div class="col-sm-6">
		<h2><?=h(lang('Membership'))?></h2>
		<dl class="dl-horizontal">
			<dt><?=h(MEMBER_ID_LABEL)?></dt>
			<dd><?=h(USER_MEMBER_ID)?></dd>
			<dt><?=h(lang('Expiration Date'))?></dt>
			<dd>
				<?php if (USER_EXPIRATION_DATE == '0000-00-00'): ?>
				[<?=h(lang('None'))?>]
				<?php else: ?>
				<?=get_absolute_time(array(
					'timestamp' => strtotime(USER_EXPIRATION_DATE),
					
					'type' => 'date'))?>
				<?php endif ?>
			</dd>
		</dl>
	</div>
	<?php endif ?>
</div>
<?php if ($affiliate): ?>
<h2><?=h(lang('Affiliate'))?></h2>
<dl class="dl-horizontal">
	<dt><?=h(lang('Affiliate Name'))?></dt>
	<dd><?=h($affiliate_name)?></dd>
	<dt><?=h(lang('Affiliate Code'))?></dt>
	<dd><?=h($affiliate_code)?></dd>
	<dt><?=h(lang('Affiliate Link'))?></dt>
	<dd><a href="<?=h($affiliate_url)?>" target="_blank"><?=h($affiliate_url)?></a></dd>
	<dt><?=h(lang('Commission Rate'))?></dt>
	<dd><?=h($affiliate_commission_rate)?>%</dd>
	<dt><?=h(lang('Pending Total'))?></dt>
	<dd><?=BASE_CURRENCY_SYMBOL . number_format($affiliate_pending_total, 2)?></dd>
	<dt><?=h(lang('Payable Total'))?></dt>
	<dd><?=BASE_CURRENCY_SYMBOL . number_format($affiliate_payable_total, 2)?></dd>
	<dt><?=h(lang('Paid Total'))?></dt>
	<dd><?=BASE_CURRENCY_SYMBOL . number_format($affiliate_paid_total, 2)?></dd>
</dl>
<?php if ($commissions): ?>
<h3><?=h(lang('Commissions'))?></h3>
<div class="table-responsive">
	<table class="table table-striped">
		<tr>
			<th><?=h(lang('Reference Code'))?></th>
			<th><?=h(lang('Date & Time'))?></th>
			<th><?=h(lang('Status'))?></th>
			<th class="text-right"><?=h(lang('Amount'))?></th>
		</tr>
		<?php foreach($commissions as $commission): ?>
		<tr>
			<td><?=h($commission['reference_code'])?></td>
			<td>
				<?=get_relative_time(array(
					'timestamp' => $commission['created_timestamp']))?>
			</td>
			<td><?=h($commission['status_label'])?></td>
			<td class="text-right">
				<?=BASE_CURRENCY_SYMBOL . number_format($commission['amount'], 2)?>
			</td>
		</tr>
		<?php endforeach ?>
	</table>
</div>
<?php endif ?>
<?php endif ?>
<?php if ($complete_orders or $incomplete_orders): ?>
<h2><?=h(lang('Order History'))?></h2>
<?php if ($complete_orders): ?>
<h3><?=h(lang('Complete Orders'))?></h3>
<div class="table-responsive">
	<table class="table table-striped">
		<tr>
			<th><?=h(lang('Order Number'))?></th>
			<th><?=h(lang('Date & Time'))?></th>
			<th class="text-right"><?=h(lang('Total'))?></th>
			<th></th>
		</tr>
		<?php foreach($complete_orders as $order): ?>
		<tr>
			<td><?=h($order['order_number'])?></td>
			<td>
				<?=get_relative_time(array(
					'timestamp' => $order['order_date']))?>
			</td>
			<td class="text-right">
				<?=$order['total_info']?>
			</td>
			<td class="text-center">
				<?php if ($order['view_url']): ?>
				<a href="<?=h($order['view_url'])?>" class="btn btn-default btn-secondary btn-sm">
				<?=h(lang('View'))?>
				</a>
				<?php endif ?>
				<a href="<?=h($order['reorder_url'])?>" class="btn btn-default btn-secondary btn-sm">
				<?=h(lang('Reorder'))?>
				</a>
			</td>
		</tr>
		<?php endforeach ?>
	</table>
</div>
<?php endif ?>
<?php if ($incomplete_orders): ?>
<h3><?=h(lang('Incomplete Orders'))?></h3>
<div class="table-responsive">
	<table class="table table-striped">
		<tr>
			<th><?=h(lang('Reference Code'))?></th>
			<th><?=h(lang('Date & Time'))?></th>
			<th></th>
		</tr>
		<?php foreach($incomplete_orders as $order): ?>
		<tr>
			<td><?=h($order['reference_code'])?></td>
			<td>
				<?=get_relative_time(array(
					'timestamp' => $order['order_date']))?>
			</td>
			<td class="text-center">
				<?php if ($order['view_url']): ?>
				<a href="<?=h($order['view_url'])?>" class="btn btn-default btn-secondary btn-sm">
				<?=h(lang('View'))?>
				</a>
				<?php endif ?>
				<?php if ($order['active']): ?>
				<?php if ($order['order_url']): ?>
				<a href="<?=h($order['order_url'])?>" class="btn btn-primary btn-sm">
				<?=h(lang('Order'))?>
				</a>
				<?php else: ?>
				<a href="#" class="btn btn-primary btn-sm disabled">
				<?=h(lang('Active'))?>
				</a>
				<?php endif ?>
				<?php else: ?>
				<a href="<?=h($order['retrieve_url'])?>" class="btn btn-default btn-secondary btn-sm">
				<?=h(lang('Retrieve'))?>
				</a>
				<?php endif ?>
				<a href="<?=h($order['delete_url'])?>" class="btn btn-default btn-secondary btn-sm" onclick="return confirm('<?=h(escape_javascript(lang('The order will be deleted.')))?>')">
				<?=h(lang('Delete'))?>
				</a>
			</td>
		</tr>
		<?php endforeach ?>
	</table>
</div>
<?php endif ?>
<?php endif ?>
<?php if ($address_book): ?>
<h2>
	<?=h(lang('Address Book'))?>
	<?php if ($update_address_book_url): ?>
	<a href="<?=h($update_address_book_url)?>" class="btn btn-default btn-secondary btn-xs">
	<?=h(lang('Add Recipient'))?>
	</a>
	<?php endif ?>
</h2>
<?php if ($recipients): ?>
<div class="table-responsive">
	<table class="table table-striped">
		<tr>
			<th><?=h(lang('Ship to Name'))?></th>
			<th><?=h(lang('Full Name'))?></th>
			<th><?=h(lang('Delivery Address'))?></th>
			<th></th>
		</tr>
		<?php foreach($recipients as $recipient): ?>
		<tr>
			<td><?=h($recipient['ship_to_name'])?></td>
			<td>
				<?=h($recipient['salutation'])?>
				<?=h($recipient['first_name'])?>
				<?=h($recipient['last_name'])?>
			</td>
			<td>
				<?php if ($recipient['company']): ?>
				<?=h($recipient['company'])?><br>
				<?php endif ?>
				<?=h($recipient['address_1'])?><br>
				<?php if ($recipient['address_2']): ?>
				<?=h($recipient['address_2'])?><br>
				<?php endif ?>
				<?=h($recipient['city'])?>,
				<?=h($recipient['state'])?>
				<?=h($recipient['zip_code'])?><br>
				<?=h($recipient['country'])?><br>
			</td>
			<td class="text-center">
				<?php if ($recipient['update_url']): ?>
				<a href="<?=h($recipient['update_url'])?>" class="btn btn-default btn-secondary btn-sm">
				<?=h(lang('Update'))?>
				</a>
				<?php endif ?>
				<a href="<?=h($recipient['remove_url'])?>" class="btn btn-default btn-secondary btn-sm" onclick="return confirm('<?=h(escape_javascript($recipient['ship_to_name']))?> will be removed.')">
				<?=h(lang('Remove'))?>
				</a>
			</td>
		</tr>
		<?php endforeach ?>
	</table>
</div>
<?php endif ?>
<?php endif ?>