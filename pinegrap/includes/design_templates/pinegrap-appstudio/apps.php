<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Mobile App Developer", its apps: the staff page that
 * adds an app (the Apps form, a collection, with the sample apps written
 * in when it is made), the list of every app with its kind and platform
 * filters, the app's own page, and the list the home page places.
 * Included after parts.php in the same scope; returns array('pages' =>
 * ..., 'widgets' => ...).
 *
 * An app is a record of the Apps form. Its kind, platforms and price model
 * are pick lists whose answers are the words parts.php gives, so a link
 * with ?category= filters the list by the words a visitor sees. ?platform=
 * is compared with "contains": an app on "iOS and Android" shows under
 * each platform. The rating is a number field, so a list can sort on it;
 * the icon and the screenshots are image fields kept in the public folder.
 * Featured is the developer's own choice (office use only): the home page
 * shows the featured apps.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$app_pages = array();

// ── Pieces ──────────────────────────────────────────────────────────────

// A filter row of a list's settings: the field, how it compares and the
// value, or where the value comes from (a URL parameter, left out while
// the parameter is not in the address).
$app_filter = function ($field, $operator, $value, $param = '') {
    $row = array('field' => $field, 'operator' => $operator, 'value' => $value, 'dynamic_value' => $param !== '' ? 'url parameter' : '');
    if ($param !== '') $row['dynamic_value_attribute'] = $param;
    return $row;
};
// The answers of the Featured list: the first marks an app for the home.
$featured_choices = array(lang('Yes'), lang('No'));

// A link to a template page that sends values of the record it is drawn
// in: $params maps the address name to the field it takes (the renderer's
// __page_link).
$app_page_link = function ($page_key, $params) {
    $list = array();
    foreach ($params as $name => $token) $list[] = array('name' => $name, 'token' => $token);
    return array(
        '_bindings'   => array('href' => '__page_link'),
        '_bindParams' => array('href' => array('page_id' => '{{tab:' . $page_key . '}}', 'params' => $list)),
    );
};

// A list's settings: the Apps form, the app page, newest first.
$app_list_config = function ($more) {
    return array_merge(array(
        'regionType'          => 'form_list_view',
        'custom_form_page_id' => '{{tab:app_new}}',
        'detail_page_id'      => '{{tab:app}}',
        'order_by_field'      => 'submitted_date_and_time',
        'order_by_direction'  => 'DESC',
        'empty_message'       => lang('No app matches these choices yet. Try another kind of app or platform.'),
    ), $more);
};
// The cards of a list, in rows of three.
$app_grid = function () use ($widget_root, $row, $loop, $app_card) {
    return $widget_root(array($row(array($loop(array($app_card()))), '4')));
};

// The links above the list that narrow it: one per kind of app and one per
// platform, each opening the app list with the filter set.
$app_filter_bar = function () use ($el, $link, $span, $query, $category_titles, $platforms) {
    $chips = function ($label, $param, $values) use ($el, $link, $span, $query) {
        $children = array($span($label, 'small fw-semibold text-body-secondary me-1', lang('Label')));
        $children[] = $link(lang('All'), '{{page:apps}}', 'btn btn-sm btn-outline-secondary rounded-pill');
        foreach ($values as $v) {
            $children[] = $link($v, '{{page:apps}}?' . $query(array($param => $v)), 'btn btn-sm btn-outline-secondary rounded-pill');
        }
        return $el('div', 'd-flex flex-wrap align-items-center gap-2 mb-2', $label, $children);
    };
    $platform_names = array();
    foreach ($platforms as $p) $platform_names[] = $p['title'];
    return $el('nav', 'mb-4', lang('Filters'), array(
        $chips(lang('Kind of app'), 'category', $category_titles),
        $chips(lang('Platform'), 'platform', $platform_names),
    ));
};

// ── Pages ───────────────────────────────────────────────────────────────

// Every app, narrowed by kind and platform from the address.
$app_pages[] = array(
    'key'              => 'apps',
    'name'             => lang('apps'),
    'folder'           => 'public',
    'title'            => lang('My Apps'),
    'meta_description' => lang('The apps I make for iOS and Android: habits, running, travel, shared budgets and languages, each with its store links.'),
    'tree'             => $page(array(
        $page_header(lang('My Apps'), lang('Small, calm apps that do one thing well. Choose a kind of app or a platform to narrow the list.')),
        $el('section', 'py-5', lang('Apps'), array(
            $container(array($app_filter_bar(), $widget('apps_list'))),
        )),
        $shared('project_band'),
    )),
);

// One app: the icon, the name, the store buttons, the screenshots, the
// description, the features, the privacy summary and the ways to get help.
$app_pages[] = array(
    'key'              => 'app',
    'name'             => lang('app'),
    'folder'           => 'public',
    'title'            => lang('App'),
    'meta_description' => lang('An app for iPhone and Android: what it does, screenshots, features, privacy and where to download it.'),
    'sitemap'          => false,
    'tree'             => $page(array(
        $widget('app_detail'),
        $shared('project_band'),
    ), false),
);

// The staff page that adds an app. The form is the Apps collection; the
// sample apps are written in when the form is made, so the lists do not
// open empty (pg_cf_seed_sample_records()).
$sample_apps = array();
foreach ($apps as $key => $a) {
    $sample_apps[] = array(
        'name'            => $a['name'],
        'tagline'         => $a['tagline'],
        'category'        => $categories[$a['category']]['title'],
        'platforms'       => $platform_title($a['platforms']),
        'price_model'     => $price_models[$a['price']],
        'version'         => $a['version'],
        'rating'          => number_format($a['rating'], 1, '.', ''),
        'summary'         => $a['summary'],
        'description'     => $a['description'],
        'features'        => implode("\n", $a['features']),
        'icon'            => $portrait($a['name'], 512),
        'screenshot_1'    => $photo($key . '-1', 600, 1200),
        'screenshot_2'    => $photo($key . '-2', 600, 1200),
        'screenshot_3'    => $photo($key . '-3', 600, 1200),
        'app_store_url'   => in_array($a['platforms'], array('ios', 'both'), true) ? 'https://apps.apple.com/' : '',
        'google_play_url' => in_array($a['platforms'], array('android', 'both'), true) ? 'https://play.google.com/store/apps' : '',
        'privacy'         => $a['privacy'],
        'featured'        => $a['featured'] ? $featured_choices[0] : $featured_choices[1],
    );
}
$app_pages[] = array(
    'key'     => 'app_new',
    'name'    => lang('new-app'),
    'folder'  => 'private',
    'title'   => lang('New App'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'form'    => array(
        'form_name'            => lang('Apps'),
        'collection'           => 1,
        'confirmation_message' => lang('The app is published.'),
        'confirmation_page_id' => '{{tab:apps}}',
        'sample_records'       => $sample_apps,
        // The icon is the app's share image (pg_cf_share_image_field_id()).
        'share_image_field'    => 'icon',
    ),
    'tree'    => $staff_page(array(
        $page_header(lang('New App'), lang('Add the app with its icon, screenshots and store links; it appears in the list as soon as you send it. Mark it featured from the app\'s Edit button.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'p-4 rounded-4 border', lang('Form Card'), array($widget('app_form'))),
                    ), '', '8'),
                ), '', 'center'),
            )),
        )),
    )),
);

// ── Widget pieces ───────────────────────────────────────────────────────

// A screenshot of the app page, a phone-shaped frame that leaves when the
// record has none.
$app_screenshot = function ($field, $seed) use ($optional_image, $photo) {
    return $optional_image($field, 'name', 'rounded-4 object-fit-cover shadow-sm', 'ratio ratio-9x16', $photo($seed, 600, 1200), lang('Screenshot'));
};

// Beside the app's words: its facts and the ways to get help with it.
$app_aside = function ($s) use ($el, $heading, $para, $link, $span, $icon, $bind, $app_page_link, $categories, $platform_title, $price_models) {
    $fact = function ($label, $value, $token) use ($el, $span, $bind) {
        return $el('div', 'd-flex justify-content-between gap-3 py-2 border-bottom small', $label, array(
            $span($label, 'text-body-secondary', lang('Label')),
            $span($value, 'fw-semibold text-end', lang('Value'), $bind($token)),
        ));
    };
    return $el('aside', 'card border-0 shadow-sm rounded-4', lang('App Facts'), array(
        $el('div', 'card-body p-4', lang('Card Body'), array(
            $heading('h2', lang('About the app'), 'h6 text-uppercase fw-semibold text-body-secondary mb-2'),
            $fact(lang('Version'), $s['version'], 'version'),
            $fact(lang('Kind of app'), $categories[$s['category']]['title'], 'category'),
            $fact(lang('Platforms'), $platform_title($s['platforms']), 'platforms'),
            $fact(lang('Price'), $price_models[$s['price']], 'price_model'),
            $el('div', 'd-grid gap-2 mt-4', lang('Buttons'), array(
                // Opens the release notes with this app chosen (?app=).
                $link(lang('Release notes'), '#', 'btn btn-outline-primary rounded-pill', array(),
                    $app_page_link('releases', array('app' => 'name'))),
                // Opens the support form with this app chosen (?app=).
                $link(lang('Get help with this app'), '#', 'btn btn-outline-secondary rounded-pill', array(),
                    $app_page_link('support', array('app' => 'name'))),
                $link(lang('Join the beta'), '{{page:beta}}', 'btn btn-link link-secondary'),
            )),
            $el('p', 'd-flex gap-2 small text-body-secondary mt-3 mb-0', lang('Note'), array(
                $icon('bi-info-circle', 'mt-1'),
                $span(lang('I answer every message myself, usually within a working day.'), '', lang('Text')),
            )),
        )),
    ));
};

$app_widgets = array(

    // The staff's Apps form: one app.
    'app_form' => array(
        'page'   => 'app_new',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('The app')),
            $row(array(
                $col(array($field('ma-app-name', lang('Name'), pg_cf_with_role($input('ma-app-name', 'text', 'name', 'off'), 'title'))), '6'),
                $col(array($field('ma-app-version', lang('Version'), $input('ma-app-version', 'text', 'version', 'off'), 'mb-3', lang('The newest version in the stores, for example 3.2.0.'))), '6'),
            ), '3'),
            $field('ma-app-tagline', lang('Tagline'), $input('ma-app-tagline', 'text', 'tagline', 'off'), 'mb-3',
                lang('One line under the name: what the app does for its user.')),
            $row(array(
                $col(array($field('ma-app-category', lang('Kind of app'), pg_cf_with_role($select('ma-app-category', 'category', lang('Choose a kind of app…'), $category_titles), 'category'))), '6'),
                $col(array($field('ma-app-platforms', lang('Platforms'), $select('ma-app-platforms', 'platforms', '', $platform_choices, true, $platform_both))), '6'),
                $col(array($field('ma-app-price', lang('Price model'), $select('ma-app-price', 'price_model', '', $price_models, true, $price_models[0]))), '6'),
                $col(array($field('ma-app-rating', lang('Rating'),
                    $input('ma-app-rating', 'number', 'rating', 'off', false, array('kind' => 'number'), array($attr('min', '1'), $attr('max', '5'), $attr('step', '0.1'))),
                    'mb-3', lang('The stores\' average, from 1 to 5 stars.'))), '6'),
            ), '3'),
            $form_step(2, lang('Words')),
            $field('ma-app-summary', lang('Summary'), pg_cf_with_role($textarea('ma-app-summary', 'summary', 2), 'description'), 'mb-3',
                lang('Two lines for the cards of the lists.')),
            $field('ma-app-description', lang('Description'), $textarea('ma-app-description', 'description', 6)),
            $field('ma-app-features', lang('Features'), $textarea('ma-app-features', 'features', 4, false), 'mb-3', lang('One feature on each line.')),
            $field('ma-app-privacy', lang('Privacy summary'), $textarea('ma-app-privacy', 'privacy', 3, false), 'mb-3',
                lang('What the app collects and where it keeps it, in plain words.')),
            $form_step(3, lang('Icon and screenshots')),
            $field('ma-app-icon', lang('Icon'), pg_cf_with_role($file('ma-app-icon', 'icon', 'public', 'image/*'), 'media'), 'mb-3',
                lang('A square picture, at least 512 pixels wide.')),
            $row(array(
                $col(array($field('ma-app-shot-1', lang('First Screenshot'), $file('ma-app-shot-1', 'screenshot_1', 'public', 'image/*'))), '4'),
                $col(array($field('ma-app-shot-2', lang('Second Screenshot'), $file('ma-app-shot-2', 'screenshot_2', 'public', 'image/*'))), '4'),
                $col(array($field('ma-app-shot-3', lang('Third Screenshot'), $file('ma-app-shot-3', 'screenshot_3', 'public', 'image/*'))), '4'),
            ), '3'),
            $form_step(4, lang('Store links')),
            $row(array(
                $col(array($field('ma-app-ios', lang('App Store address'), $input('ma-app-ios', 'url', 'app_store_url', 'off', false), 'mb-3', lang('Leave empty when the app is not on iOS.'))), '6'),
                $col(array($field('ma-app-android', lang('Google Play address'), $input('ma-app-android', 'url', 'google_play_url', 'off', false), 'mb-3', lang('Leave empty when the app is not on Android.'))), '6'),
            ), '3'),
            $field('ma-app-featured', lang('Featured'), $select('ma-app-featured', 'featured', '', $featured_choices, false, $featured_choices[1], array('office_use_only' => true)),
                'mb-3', lang('A featured app is shown on the home page.')),
            $submit(lang('Publish')),
            $loop(array()),
        )),
    ),

    // Every app, filtered by the address (kind, platform), with a search
    // over the words of the apps.
    'apps_list' => array(
        'page'   => 'apps',
        'slug'   => lang('form-list'),
        'config' => $app_list_config(array(
            'items_per_page' => 12,
            'filters'        => array(
                $app_filter('category', 'is equal to', '', 'category'),
                $app_filter('platforms', 'contains', '', 'platform'),
            ),
            'search_enabled' => true,
            'search_fields'  => array('name', 'tagline', 'summary', 'features', 'description'),
            'search_width'   => 'full',
            'search_label'   => lang('Search the apps: a name, a feature, a kind of app...'),
        )),
        'tree'   => $app_grid(),
    ),

    // The home page's featured apps, three at most.
    'home_apps' => array(
        'page'   => 'home',
        'slug'   => lang('form-list'),
        'config' => $app_list_config(array(
            'items_per_page' => 3,
            'max_results'    => 3,
            'filters'        => array($app_filter('featured', 'is equal to', $featured_choices[0])),
            'empty_message'  => lang('No featured apps yet.'),
        )),
        'tree'   => $app_grid(),
    ),
);

// One app: the icon, name, tagline and rating, the store buttons, the
// screenshots, the description, the features, the privacy summary and the
// facts with the ways to get help.
$app_sample = $apps['tally'];
$app_widgets['app_detail'] = array(
    'page'   => 'app',
    'slug'   => lang('form-detail'),
    'config' => array(
        'regionType'          => 'form_item_view',
        'custom_form_page_id' => '{{tab:app_new}}',
        'access_control'      => 'public',
        'not_found_message'   => lang('This app could not be found. It may have been renamed or taken out of the stores.'),
    ),
    'tree'   => $widget_root(array(
        $container(array(
            $el('div', 'text-center py-5', lang('Not Found'), array(
                $icon('bi-phone', 'display-5 text-body-tertiary'),
                $heading('h2', lang('App not found'), 'h3 fw-semibold mt-3'),
                $para(lang('This app could not be found. It may have been renamed or taken out of the stores.'), 'text-body-secondary mb-4', $bind('not_found')),
                $link(lang('See my apps'), '{{page:apps}}', 'btn btn-primary rounded-pill px-4'),
            ), $show_when('record_not_found')),
            $el('article', '', lang('App'), array(
                $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-4', lang('Toolbar'), array(
                    $el('a', 'd-inline-flex align-items-center gap-1 small fw-semibold link-primary text-decoration-none', lang('Link'), array(
                        $icon('bi-arrow-left'),
                        $span(lang('All Apps'), '', lang('Link Text')),
                    ), array('href' => '{{page:apps}}')),
                    $edit_button(),
                )),
                // The heading: the icon, the name, the tagline, the rating
                // and the store buttons.
                $el('header', 'd-flex flex-column flex-md-row align-items-md-center gap-4 mb-5', lang('App Heading'), array(
                    $el('span', 'pg-field-value d-block flex-shrink-0 rounded-4 overflow-hidden shadow-sm pma-icon', lang('Icon Frame'), array(
                        $image($portrait($app_sample['name'], 256), $app_sample['name'], 'w-100 h-100 object-fit-cover',
                            array('_bindings' => array('src' => 'icon', 'alt' => 'name'), 'width' => '96', 'height' => '96')),
                    )),
                    $el('div', 'flex-grow-1', lang('Name and Tagline'), array(
                        $heading('h1', $app_sample['name'], 'pma-display display-6 fw-bold mb-1', $bind('name')),
                        $para($app_sample['tagline'], 'lead text-body-secondary mb-2', $bind('tagline')),
                        $el('div', 'd-flex flex-wrap align-items-center gap-2 small', lang('Meta'), array(
                            $el('span', 'd-inline-flex align-items-center gap-1', lang('Rating'), array(
                                $icon('bi-star-fill', 'text-warning'),
                                $span(number_format($app_sample['rating'], 1), 'fw-semibold', lang('Value'), $bind('rating')),
                            )),
                            $span($categories[$app_sample['category']]['title'], 'badge rounded-pill bg-body text-body-emphasis border', lang('Category'), $bind('category')),
                            $span($platform_title($app_sample['platforms']), 'badge rounded-pill bg-body text-body-emphasis border', lang('Platforms'), $bind('platforms')),
                            $span($price_models[$app_sample['price']], 'text-body-secondary', lang('Price'), $bind('price_model')),
                        )),
                    )),
                    $store_buttons('tally'),
                )),
                // The screenshots, side by side.
                $el('div', 'pg-hide-if-empty mb-5', lang('Screenshots'), array(
                    $row(array(
                        $col(array($app_screenshot('screenshot_1', 'tally-1')), '', '', '', '4'),
                        $col(array($app_screenshot('screenshot_2', 'tally-2')), '', '', '', '4'),
                        $col(array($app_screenshot('screenshot_3', 'tally-3')), '', '', '', '4'),
                    ), '3', 'center'),
                )),
                $row(array(
                    $col(array(
                        $el('div', 'pg-hide-if-empty mb-4', lang('Description'), array(
                            $heading('h2', lang('What it does'), 'h5 fw-semibold mb-2'),
                            $el('div', 'pg-field-value lh-lg', lang('Value'), array(), array_merge(array('text' => $app_sample['description']), $bind('description'))),
                        )),
                        $el('div', 'pg-hide-if-empty p-4 rounded-4 bg-body-tertiary mb-4', lang('Features'), array(
                            $heading('h2', lang('Features'), 'h5 fw-semibold mb-2'),
                            $el('div', 'pg-field-value lh-lg', lang('Value'), array(), array_merge(array('text' => implode("\n", $app_sample['features'])), $bind('features'))),
                        )),
                        $el('div', 'pg-hide-if-empty mb-4', lang('Privacy'), array(
                            $el('h2', 'd-flex align-items-center gap-2 h5 fw-semibold mb-2', lang('Heading'), array(
                                $icon('bi-shield-check', 'text-primary'),
                                $span(lang('Your privacy'), '', lang('Text')),
                            )),
                            $el('div', 'pg-field-value lh-lg', lang('Value'), array(), array_merge(array('text' => $app_sample['privacy']), $bind('privacy'))),
                            $link(lang('Read the full privacy policy'), '{{page:privacy}}', 'small'),
                        )),
                    ), '', '8'),
                    $col(array($app_aside($app_sample)), '', '4'),
                ), '4', '', 'g-lg-5'),
            ), $show_when('record_found')),
        ), 'py-5'),
    )),
);

return array('pages' => $app_pages, 'widgets' => $app_widgets);
