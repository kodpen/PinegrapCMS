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
		<label for="email"><?=lang('Email')?></label>
		<input
			type="email"
			id="email"
			name="email"
			class="form-control"
			autocomplete="email"
			spellcheck="false">
	</div>
	<div class="form-group">
		<label for="password"><?=lang('Password')?></label>
		<input
			type="password"
			id="password"
			name="password"
			class="form-control"
			autocomplete="current-password"
			spellcheck="false">
	</div>
	<?php if (REMEMBER_ME): ?>
	<div class="checkbox">
		<label>
		<input type="checkbox" name="remember_me" value="1">
		
		<?=lang('Remember Me')?>
		</label>
	</div>
	<?php endif ?>
	<div class="form-group">
		<button type="submit" class="btn btn-primary"><?=lang('Login')?></button>
	</div>
	<!-- Required hidden fields (do not remove) -->
	<?=$system?>
</form>
<?=$google_signin ?? ''?>
<?php if ($forgot_password_url): ?>
<p><a href="<?=h($forgot_password_url)?>"><?=lang('Forgot password?')?></a></p>
<?php endif ?>