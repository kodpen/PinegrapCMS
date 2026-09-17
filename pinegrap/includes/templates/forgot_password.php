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
	<?php if (!$screen): ?>
	<div class="form-group">
		<label for="email">Email</label>
		<input type="email" name="email" id="email" class="form-control">
	</div>
	<button type="submit" class="btn btn-primary">Send Email</button>
	<?php elseif ($screen == 'password_hint'): ?>
	<p>Your Password Hint is: <strong><?=h($password_hint)?></strong></p>
	<p>Do you remember your password now?</p>
	<a href="<?=h($send_to)?>" class="btn btn-primary">Yes, I Remember</a>
	<button type="submit" class="btn btn-primary">No, I Forget</button>
	<?php endif ?>
	<!-- Required hidden fields (do not remove) -->
	<?=$system?>
</form>