<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Schema step for version 2026.1.25. Loaded by includes/migrations/runner.php when an
// installation is behind this version; never requested on its own. The function
// must be safe to run twice: every ADD / CREATE asks first and skips what exists.
if (!defined('INSTALL_OR_UPDATE')) {
	exit;
}

function upgrade_to_2026_1_25() {
    // view_files / toolbar performance: cache expensive per-image computations
    // directly on the files row so list pages don't have to recompute them.
    //
    // - image_width / image_height : cached getimagesize() result. Avoids a disk
    //   stat + JPEG/PNG header decode per image row in view_files.php (lines
    //   ~410). Especially painful on OneDrive / network-mounted dev folders.
    //
    // - optimization_percent : cached calculate_optimizable_percent() result.
    //   The original function fully decodes + recompresses every unoptimized
    //   image just to display a "%" badge. With this column we compute it
    //   once and reuse it; recompute is triggered only when the user runs
    //   optimize.php or the file row is replaced.
    //
    // All three columns are nullable. NULL means "not computed yet" — the
    // first view_files render after upgrade fills them in lazily and persists
    // the result, so subsequent loads are O(1) per row.
    install_add_column('files', 'image_width', "SMALLINT UNSIGNED DEFAULT NULL");
    install_add_column('files', 'image_height', "SMALLINT UNSIGNED DEFAULT NULL");
    install_add_column('files', 'optimization_percent', "TINYINT UNSIGNED DEFAULT NULL");
}
