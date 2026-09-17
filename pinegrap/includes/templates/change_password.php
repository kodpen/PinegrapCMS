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
		<label for="email_address">Email*</label>
		<input type="email" name="email_address" id="email_address" autocomplete="off" class="form-control">
	</div>
	<?php if (!empty($password_not_set)): ?>
	<div class="alert alert-info"><?=lang('Your account signs in with Google and has no password yet. Set one below; afterwards you can sign in either way.')?></div>
	<?php else: ?>
	<div class="form-group">
		<label for="current_password">Current Password*</label>
		<input type="password" name="current_password" id="current_password" class="form-control">
	</div>
	<?php endif ?>
	<?php if ($strong_password_help): ?>
	<?=$strong_password_help?>
	<?php endif ?>
	<div class="form-group">
		<label for="new_password">New Password*</label>
		<input type="password" name="new_password" id="new_password" class="form-control">
	</div>
	<div class="form-group">
		<label for="new_password_verify">Confirm New Password*</label>
		<input type="password" name="new_password_verify" id="new_password_verify" class="form-control">
	</div>
	<?php if (PASSWORD_HINT): ?>
	<div class="form-group">
		<label for="password_hint">Password Hint</label>
		<input type="text" name="password_hint" id="password_hint" class="form-control">
	</div>
	<?php endif ?>
	<button type="submit" class="btn btn-primary">Change Password</button>
	<?php if ($my_account_url): ?>
	<a href="<?=h($my_account_url)?>" class="btn btn-default btn-secondary">Cancel</a>
	<?php endif ?>
	<!-- Required hidden fields (do not remove) -->
	<?=$system?>
</form>