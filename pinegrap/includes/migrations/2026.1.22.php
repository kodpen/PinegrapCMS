<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Schema step for version 2026.1.22. Loaded by includes/migrations/runner.php when an
// installation is behind this version; never requested on its own. The function
// must be safe to run twice: every ADD / CREATE asks first and skips what exists.
if (!defined('INSTALL_OR_UPDATE')) {
	exit;
}

function upgrade_to_2026_1_22() {
    // System widget support for shared components.
    // A shared component becomes a "system widget" when it has a "system_region_config" JSON:
    //   { "regionType": "form_list|catalog|...", "source_page": "blog",
    //     "templates": { "search": "...", "browse": "...", "item": "...", "pagination": "..." } }
    // At render time, _expand_shared_refs() detects this field and emits a
    // <!--pg-system-widget:ID--> marker instead of rendering the static tree.
    // get_page_content.php then calls _expand_system_widgets() which runs the appropriate
    // dynamic renderer (get_form_list_view.php, catalog renderer, etc.) using the compiled
    // templates. URL param namespace: sw{id}_query, sw{id}_browse_field_id, etc.
    install_add_column('shared_components', 'system_region_config', "LONGTEXT DEFAULT NULL");
}
