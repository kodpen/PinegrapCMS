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
		<h2><?=h(lang('Login'))?></h2>
		<form <?=$attributes?>>
			<div class="form-group">
				<label for="login_email_address"><?=h(lang('Email'))?></label>
				<input type="text" name="u" id="login_email_address" class="form-control">
			</div>
			<div class="form-group">
				<label for="login_password"><?=h(lang('Password'))?></label>
				<input type="password" name="p" id="login_password" class="form-control">
			</div>
			<?php if (REMEMBER_ME): ?>
			<div class="checkbox">
				<label>
				<input type="checkbox" name="login_remember_me" value="1">
				<?=h(lang('Remember Me'))?>
				</label>
			</div>
			<?php endif ?>
			<div class="form-group">
				<button type="submit" class="btn btn-primary"><?=h(lang('Login'))?></button>
			</div>
			<!-- Required hidden fields (do not remove) -->
			<?=$login_system?>
		</form>
		<?=$google_signin ?? ''?>
		<?php if ($forgot_password_url): ?>
		<p><a href="<?=h($forgot_password_url)?>"><?=h(lang('Forgot password?'))?></a></p>
		<?php endif ?>
	</div>
	<div class="col-sm-6">
		<h2><?=h(lang('Register'))?></h2>
		<form <?=$attributes?>>
			<div class="form-group">
				<label for="member_id"><?=h(MEMBER_ID_LABEL)?>*</label>
				<input type="text" name="member_id" id="member_id" class="form-control">
			</div>
			<div class="form-group">
				<label for="first_name"><?=h(lang('First Name'))?>*</label>
				<input type="text" name="first_name" id="first_name" class="form-control">
			</div>
			<div class="form-group">
				<label for="last_name"><?=h(lang('Last Name'))?>*</label>
				<input type="text" name="last_name" id="last_name" class="form-control">
			</div>
			<div class="form-group">
				<label for="username"><?=h(lang('Username'))?>*</label>
				<input type="text" name="username" id="username" class="form-control">
			</div>
			<div class="form-group">
				<label for="register_email_address"><?=h(lang('Email'))?>*</label>
				<input type="email" name="email_address" id="register_email_address" class="form-control">
			</div>
			<div class="form-group">
				<label for="email_address_verify"><?=h(lang('Confirm Email'))?>*</label>
				<input type="email" name="email_address_verify" id="email_address_verify" class="form-control">
			</div>
			<?php if ($strong_password_help): ?>
			<?=$strong_password_help?>
			<?php endif ?>
			<div class="form-group">
				<label for="register_password"><?=h(lang('Password'))?>*</label>
				<input type="password" name="password" id="register_password" class="form-control">
			</div>
			<div class="form-group">
				<label for="password_verify"><?=h(lang('Confirm Password'))?>*</label>
				<input type="password" name="password_verify" id="password_verify" class="form-control">
			</div>
			<?php if (PASSWORD_HINT): ?>
			<div class="form-group">
				<label for="password_hint"><?=h(lang('Password Hint'))?></label>
				<input type="text" name="password_hint" id="password_hint" class="form-control">
			</div>
			<?php endif ?>
			<?php if (REMEMBER_ME): ?>
			<div class="checkbox">
				<label>
				<input type="checkbox" name="register_remember_me" value="1">
				<?=h(lang('Remember Me'))?>
				</label>
			</div>
			<?php endif ?>
			<div class="checkbox">
				<label>
				<input type="checkbox" name="opt_in" value="1">
				<?=h($opt_in_label)?>
				</label>
			</div>
			<button type="submit" class="btn btn-primary"><?=h(lang('Register'))?></button>
			<!-- Required hidden fields (do not remove) -->
			<?=$register_system?>
		</form>
	</div>
</div>