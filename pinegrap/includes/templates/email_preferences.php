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
<form <?=$attributes?>>
	<div class="form-group">
		<label for="email_address"><?=lang('Email')?></label>
		<input type="email" name="email_address" id="email_address" autocomplete="off" class="form-control">
	</div>
	<div class="checkbox">
		<label>
		<input type="checkbox" name="opt_in" value="1">
		<?=h(OPT_IN_LABEL)?>
		</label>
	</div>
	<?php if ($contact_groups): ?>
	<div class="contact_groups" style="display: none">
		<h2><?=lang('Opt me in or out of the following lists')?>:</h2>
		<?php foreach($contact_groups as $contact_group): ?>
		<div class="checkbox">
			<label>
			<input type="checkbox" name="contact_group_<?=$contact_group['id']?>" value="1">
			<?=h($contact_group['name'])?>
			<?php if ($contact_group['description']): ?>
			- <?=h($contact_group['description'])?>
			<?php endif ?>
			</label>
		</div>
		<?php endforeach ?>
	</div>
	<?php endif ?>
	<button type="submit" class="btn btn-primary"><?=lang('Update')?></button>
	<?php if ($my_account_url): ?>
	<a href="<?=h($my_account_url)?>" class="btn btn-default btn-secondary"><?=lang('Cancel')?></a>
	<?php endif ?>
	<!-- Required hidden fields and JS (do not remove) -->
	<?=$system?>
</form>