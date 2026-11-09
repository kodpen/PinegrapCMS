/**
 * Pinegrap - Enterprise Website Platform
 *
 * Visual Page Editor - the theme part: the Themes tab of the assets panel,
 * the Bootstrap variable rows of the light and dark mode, the cascade rules
 * a theme colour writes, the tab's header and "save these colours as a
 * theme". Not needed to open a page: the editor runs this file the first
 * time the tab is shown (_sdPartLoad(), pg_designer_parts()). It reaches the
 * editor only through the kit (_sdPartKit()) and the host the assets panel
 * hands to render() (the user-styles content, the light/dark rule blocks,
 * the mode shown).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2026 Erdal Güral. All rights reserved.
 * @license     Proprietary, see license-editors.txt
 */
StyleDesigner.definePart('theme', function (K) {
    'use strict';

    var _sdT = K._sdT;
    var esc = K.esc;
    var sdToast = K.sdToast;
    var saveState = K.saveState;
    var applyAssetsToIframe = K.applyAssetsToIframe;
    var _pgApiPost = K._pgApiPost;
    var _sdUsesBootstrap = K._sdUsesBootstrap;
    var _sdThemes = K._sdThemes;
    var _sdThemeFind = K._sdThemeFind;
    var _sdThemeValue = K._sdThemeValue;
    var _sdOpenThemeSettings = K._sdOpenThemeSettings;

    // The assets panel's host, handed to every call: the user-styles
    // content, the rule-block helpers of the core and the mode shown.
    var H = null;
    function _themeBlockFor(mode, name) { return H.blockFor(mode, name); }
    function _themeSetDecl(content, kind, name, value) { return H.setDecl(content, kind, name, value); }
    function _themeUpgradeLightSelector(content) { return H.upgrade(content); }
    function _themeModeDecls(content, mode) { return H.modeDecls(content, mode); }
    function _themeVarHasDarkDefault(name) { return H.hasDarkDefault(name); }

        // Labels of the Themes panel's link decoration options; the values stay
        // the CSS keywords.
        function _sdThemeDecorationLabels() {
            return {
                'none': _sdT('No decoration'), 'underline': _sdT('Underline'),
                'overline': _sdT('Overline'), 'line-through': _sdT('Strikethrough')
            };
        }

        // Edits write light values under
        // `:root:not([data-bs-theme="dark"]), [data-bs-theme="light"] { … }`
        // (variables Bootstrap gives a dark value) or
        // `:root, [data-bs-theme="light"] { … }` (the rest, which also apply
        // in dark mode), and dark values under `[data-bs-theme="dark"] { … }`,
        // into the user-styles content (see _themeBlockFor()). Single source of truth — same place the bottom Styles
        // panel and "Dosyaya Aktar" extract from.
        //
        // Each group has an `id` (used for collapse persistence), a friendly
        // `label`, and a list of vars. Each var carries:
        //   • name  — the CSS custom property (`--bs-…`)
        //   • label — the human-friendly row label (e.g. "Primary")
        //   • kind  — 'color' (native picker), 'colorRgba' (text only —
        //             rgba()/hex8 not supported by input[type=color]),
        //             'text', 'select'
        //   • def   — `{ light: …, dark: … }` Bootstrap-5.3 stock values,
        //             used as the picker's fallback colour AND as the
        //             "is this row modified?" comparison source.
        //   • theme — true for the 8 Bootstrap theme colors (primary/…/dark).
        //             When set, changes also emit cascade override rules
        //             (.btn-X, .bg-X, .text-X, …) so the colour propagates
        //             through Bootstrap's component hierarchy.
        //   • opts  — list of values for kind='select' rows.
        var THEME_VAR_GROUPS = [
            {
                id: 'theme-colors',
                label: _sdT('Theme Colors'),
                defaultOpen: true,
                vars: [
                    { name: '--bs-primary',   label: _sdT('Primary'),   kind: 'color', theme: true, def: { light: '#0d6efd', dark: '#6ea8fe' } },
                    { name: '--bs-secondary', label: _sdT('Secondary'), kind: 'color', theme: true, def: { light: '#6c757d', dark: '#a7acb1' } },
                    { name: '--bs-success',   label: _sdT('Success'),   kind: 'color', theme: true, def: { light: '#198754', dark: '#75b798' } },
                    { name: '--bs-info',      label: _sdT('Info'),      kind: 'color', theme: true, def: { light: '#0dcaf0', dark: '#6edff6' } },
                    { name: '--bs-warning',   label: _sdT('Warning'),   kind: 'color', theme: true, def: { light: '#ffc107', dark: '#ffda6a' } },
                    { name: '--bs-danger',    label: _sdT('Danger'),    kind: 'color', theme: true, def: { light: '#dc3545', dark: '#ea868f' } },
                    { name: '--bs-light',     label: _sdT('Light'),     kind: 'color', theme: true, def: { light: '#f8f9fa', dark: '#343a40' } },
                    { name: '--bs-dark',      label: _sdT('Dark'),      kind: 'color', theme: true, def: { light: '#212529', dark: '#dee2e6' } }
                ]
            },
            {
                id: 'body-colors',
                label: _sdT('Body Colors'),
                defaultOpen: true,
                vars: [
                    { name: '--bs-body-color',       label: _sdT('Text Color'),           kind: 'color',     def: { light: '#212529', dark: '#dee2e6' } },
                    { name: '--bs-secondary-color',  label: _sdT('Secondary Text Color'), kind: 'colorRgba', def: { light: 'rgba(33, 37, 41, 0.75)', dark: 'rgba(222, 226, 230, 0.75)' } },
                    { name: '--bs-tertiary-color',   label: _sdT('Tertiary Text Color'),  kind: 'colorRgba', def: { light: 'rgba(33, 37, 41, 0.5)',  dark: 'rgba(222, 226, 230, 0.5)'  } },
                    { name: '--bs-body-bg',          label: _sdT('Background'),           kind: 'color',     def: { light: '#ffffff', dark: '#212529' } },
                    { name: '--bs-secondary-bg',     label: _sdT('Secondary BG'),         kind: 'color',     def: { light: '#e9ecef', dark: '#343a40' } },
                    { name: '--bs-tertiary-bg',      label: _sdT('Tertiary BG'),          kind: 'color',     def: { light: '#f8f9fa', dark: '#2b3035' } }
                ]
            },
            {
                id: 'text',
                label: _sdT('Text'),
                defaultOpen: true,
                vars: [
                    { name: '--bs-body-font-family', label: _sdT('Font Family'), kind: 'text', def: { light: 'var(--bs-font-sans-serif)', dark: 'var(--bs-font-sans-serif)' } },
                    { name: '--bs-body-font-size',   label: _sdT('Font Size'),   kind: 'text', def: { light: '1rem', dark: '1rem' } },
                    { name: '--bs-body-font-weight', label: _sdT('Font Weight'), kind: 'select', opts: ['100','200','300','400','500','600','700','800','900'], def: { light: '400', dark: '400' } },
                    { name: '--bs-body-line-height', label: _sdT('Line Height'), kind: 'text', def: { light: '1.5', dark: '1.5' } }
                ]
            },
            {
                id: 'links',
                label: _sdT('Links'),
                defaultOpen: true,
                vars: [
                    { name: '--bs-link-color',           label: _sdT('Color'),            kind: 'color', def: { light: '#0d6efd', dark: '#6ea8fe' } },
                    { name: '--bs-link-hover-color',     label: _sdT('Hover Color'),      kind: 'color', def: { light: '#0a58ca', dark: '#9ec5fe' } },
                    { name: '--bs-link-decoration',      label: _sdT('Decoration'),       kind: 'select', opts: ['none','underline','overline','line-through'], optLabels: _sdThemeDecorationLabels(), def: { light: 'underline', dark: 'underline' } },
                    { name: '--bs-link-hover-decoration',label: _sdT('Hover Decoration'), kind: 'select', opts: ['none','underline','overline','line-through'], optLabels: _sdThemeDecorationLabels(), def: { light: 'underline', dark: 'underline' } }
                ]
            },
            {
                id: 'borders',
                label: _sdT('Borders'),
                defaultOpen: false,
                vars: [
                    { name: '--bs-border-color',     label: _sdT('Border Color'),  kind: 'color', def: { light: '#dee2e6',  dark: '#495057'  } },
                    { name: '--bs-border-width',     label: _sdT('Border Width'),  kind: 'text',  def: { light: '1px',      dark: '1px'      } },
                    { name: '--bs-border-radius',    label: _sdT('Radius'),        kind: 'text',  def: { light: '0.375rem', dark: '0.375rem' } },
                    { name: '--bs-border-radius-sm', label: _sdT('Radius SM'),     kind: 'text',  def: { light: '0.25rem',  dark: '0.25rem'  } },
                    { name: '--bs-border-radius-lg', label: _sdT('Radius LG'),     kind: 'text',  def: { light: '0.5rem',   dark: '0.5rem'   } },
                    { name: '--bs-border-radius-xl', label: _sdT('Radius XL'),     kind: 'text',  def: { light: '1rem',     dark: '1rem'     } }
                ]
            },
            {
                id: 'focus-ring',
                label: _sdT('Focus Ring'),
                defaultOpen: false,
                vars: [
                    { name: '--bs-focus-ring-color',   label: _sdT('Color'),   kind: 'colorRgba', def: { light: 'rgba(13, 110, 253, 0.25)', dark: 'rgba(110, 168, 254, 0.25)' } },
                    { name: '--bs-focus-ring-width',   label: _sdT('Width'),   kind: 'text',      def: { light: '0.25rem', dark: '0.25rem' } },
                    { name: '--bs-focus-ring-opacity', label: _sdT('Opacity'), kind: 'text',      def: { light: '0.25',    dark: '0.25'    } }
                ]
            }
        ];

        // Theme color cascade: which Bootstrap utility/component classes
        // need an explicit override when the user changes a theme color.
        // Each entry generates one CSS rule.
        //
        // Why this exists: Bootstrap's components don't read --bs-primary
        // directly — they hardcode the colour at build time (e.g. .btn-primary
        // sets `--bs-btn-bg: #0d6efd`). Without the cascade, changing
        // --bs-primary would only affect a handful of utilities and leave
        // .btn-primary, .alert-primary, .badge.text-bg-primary untouched.
        //
        // mode='light' emits unprefixed selectors (apply by default).
        // mode='dark'  emits selectors prefixed with `[data-bs-theme="dark"]`
        // so they only kick in when the user toggles dark mode and don't
        // override the light cascade in light mode.
        function _themeCascadeRulesFor(varName, value, mode) {
            var slug = varName.replace(/^--bs-/, '');
            var v = value;
            var pref = (mode === 'dark') ? '[data-bs-theme="dark"] ' : '';
            return [
                pref + '.bg-'      + slug + ' { background-color: ' + v + ' !important; }',
                pref + '.text-'    + slug + ' { color: '            + v + ' !important; }',
                pref + '.border-'  + slug + ' { border-color: '     + v + ' !important; }',
                pref + '.link-'    + slug + ' { color: '            + v + ' !important; }',
                pref + '.text-bg-' + slug + ' { background-color: ' + v + ' !important; color: #fff !important; }',
                pref + '.btn-'     + slug + ' {' +
                    ' --bs-btn-bg: '                 + v + ';' +
                    ' --bs-btn-border-color: '       + v + ';' +
                    ' --bs-btn-hover-bg: '           + 'color-mix(in srgb, ' + v + ', black 15%);' +
                    ' --bs-btn-hover-border-color: ' + 'color-mix(in srgb, ' + v + ', black 20%);' +
                    ' --bs-btn-active-bg: '          + 'color-mix(in srgb, ' + v + ', black 20%);' +
                    ' --bs-btn-active-border-color: '+ 'color-mix(in srgb, ' + v + ', black 25%);' +
                    ' --bs-btn-disabled-bg: '        + v + ';' +
                    ' --bs-btn-disabled-border-color: ' + v + ';' +
                    ' }',
                pref + '.btn-outline-' + slug + ' {' +
                    ' --bs-btn-color: '              + v + ';' +
                    ' --bs-btn-border-color: '       + v + ';' +
                    ' --bs-btn-hover-bg: '           + v + ';' +
                    ' --bs-btn-hover-border-color: ' + v + ';' +
                    ' --bs-btn-active-bg: '          + v + ';' +
                    ' --bs-btn-active-border-color: '+ v + ';' +
                    ' --bs-btn-disabled-color: '     + v + ';' +
                    ' --bs-btn-disabled-border-color: ' + v + ';' +
                    ' }',
                pref + '.alert-' + slug + ' {' +
                    ' --bs-alert-color: '        + 'color-mix(in srgb, ' + v + ', black 50%);' +
                    ' --bs-alert-bg: '           + 'color-mix(in srgb, ' + v + ', white 80%);' +
                    ' --bs-alert-border-color: ' + 'color-mix(in srgb, ' + v + ', white 70%);' +
                    ' --bs-alert-link-color: '   + 'color-mix(in srgb, ' + v + ', black 60%);' +
                    ' }'
            ].join('\n');
        }

        // Update or remove a "cascade" block for a theme color. Marker
        // comments make the block self-identifying so the writer can find
        // and replace it idempotently. Empty `value` removes the block.
        // Each mode has its own block: light cascade is unscoped (default
        // styling), dark cascade is prefixed with `[data-bs-theme="dark"]`
        // so it only kicks in when the user toggles dark mode and doesn't
        // override the light cascade in light mode.
        function _writeCascadeBlock(content, varName, value, mode) {
            var slug = varName.replace(/^--bs-/, '');
            var startMark = '/* === pg-theme-cascade:' + slug + ':' + mode + ':start === */';
            var endMark   = '/* === pg-theme-cascade:' + slug + ':' + mode + ':end === */';
            var startIdx = content.indexOf(startMark);
            var endIdx   = (startIdx === -1) ? -1 : content.indexOf(endMark, startIdx);
            var newBlock = '';
            if (value) {
                newBlock = startMark + '\n' + _themeCascadeRulesFor(varName, value, mode) + '\n' + endMark;
            }
            if (startIdx !== -1 && endIdx !== -1) {
                // Replace existing — drop the whole region (markers + body).
                var endLen = endIdx + endMark.length;
                var pre = content.slice(0, startIdx).replace(/\n+$/, '');
                var post = content.slice(endLen).replace(/^\n+/, '');
                if (newBlock) {
                    return (pre ? pre + '\n\n' : '') + newBlock + (post ? '\n\n' + post : '');
                }
                return (pre + (post ? '\n\n' + post : '')).replace(/^\s+|\s+$/g, '') + (post ? '' : '');
            }
            // No existing — append.
            if (!newBlock) return content;
            return (content.replace(/\s+$/, '') ? content.replace(/\s+$/, '') + '\n\n' : '') + newBlock + '\n';
        }

        // Write a single theme var into the user-styles content, creating
        // the rule block on first write. Removes the declaration when
        // `value` is empty so the user can revert to the Bootstrap default.
        // For theme colors (primary/…/dark) ALSO emits a cascade block so
        // .btn-primary, .bg-primary, .alert-primary etc. follow along.
        function _writeThemeVar(mode, varName, value) {
            // Snapshot BEFORE we touch the user-styles content.
            // saveState() captures the css hidden field as part of `assets`,
            // so undo restores the entire pre-edit user-styles block —
            // variable + cascade rules together. Without this, theme-var
            // edits silently bypass the undo history.
            saveState();
            // A light block in the old form is rewritten on every write, so a
            // value set now never overrides a dark page. A light value lives
            // in one block only: the one its variable belongs to.
            var kind = _themeBlockFor(mode, varName);
            var content = _themeUpgradeLightSelector(H.userStyles());
            if (kind !== 'dark') content = _themeSetDecl(content, (kind === 'light') ? 'shared' : 'light', varName, '');
            content = _themeSetDecl(content, kind, varName, value);

            // For theme colors (primary/.../dark), also (re)emit the cascade
            // override block for the current mode. Light cascade is unscoped;
            // dark cascade is prefixed with [data-bs-theme="dark"] so the
            // two modes don't collide.
            var meta = _findThemeVarMeta(varName);
            if (meta && meta.theme) {
                content = _writeCascadeBlock(content, varName, value, mode);
            }
            // The host keeps it in the User Styles entry (created when missing).
            H.setUserStyles(content);
            // Refresh the canvas iframe so the new variable + cascade rules
            // take effect immediately. Without this, the save only updates
            // the hidden form field and the user has to interact with
            // something else for the iframe to pick up the change.
            applyAssetsToIframe();
        }

        // Look up a var's metadata (for `theme: true` checks etc.).
        function _findThemeVarMeta(name) {
            for (var i = 0; i < THEME_VAR_GROUPS.length; i++) {
                var g = THEME_VAR_GROUPS[i];
                for (var j = 0; j < g.vars.length; j++) {
                    if (g.vars[j].name === name) return g.vars[j];
                }
            }
            return null;
        }

        // Persisted collapse state per group. Defaults come from group def.
        var _themeGrpCollapseKey = 'sd_theme_grp_collapse';
        var _themeGrpCollapsed = {};
        try {
            var raw = localStorage.getItem(_themeGrpCollapseKey);
            if (raw) _themeGrpCollapsed = JSON.parse(raw) || {};
        } catch (e) {}
        function _isGrpCollapsed(g) {
            if (typeof _themeGrpCollapsed[g.id] === 'boolean') return _themeGrpCollapsed[g.id];
            return !g.defaultOpen;
        }
        function _setGrpCollapsed(id, collapsed) {
            _themeGrpCollapsed[id] = !!collapsed;
            try { localStorage.setItem(_themeGrpCollapseKey, JSON.stringify(_themeGrpCollapsed)); } catch (e) {}
        }

        // Header of the Themes tab: which theme file the design loads (and
        // that it wins over the variables below), plus "save as theme".
        function _renderThemeHead() {
            var head = document.getElementById('sd-theme-head');
            if (!head) return;
            var th = H.activeTheme();
            // The design's look and palette (Settings > Theme) come
            // first: they are what the variables below are laid over.
            var _lk = _sdThemeFind('look', _sdThemeValue('look'));
            var _pl = _sdThemeFind('palette', _sdThemeValue('palette'));
            var lookRow = (_sdUsesBootstrap() && _sdThemes())
                ? '<div class="sd-theme-head-row">' +
                      '<span class="bi bi-brush sd-theme-head-icon"></span>' +
                      '<span class="sd-theme-head-txt"><strong>' + esc(_lk ? _lk.name : _sdT('Plain Bootstrap')) + '</strong> · ' + esc(_pl ? _pl.name : _sdT('Bootstrap')) +
                          '<br><span class="sd-theme-head-hint">' + esc(_sdT('Look and colour palette of the design')) + '</span></span>' +
                      '<button type="button" class="sd-theme-head-btn" data-act="look" title="' + esc(_sdT('Change the look and the palette')) + '"><span class="bi bi-sliders"></span></button>' +
                  '</div>'
                : '';
            // Without Bootstrap there are no variables below to speak of or
            // to save as a theme; the theme file itself is any stylesheet.
            var _bsVars = _sdUsesBootstrap();
            head.innerHTML = lookRow +
                '<div class="sd-theme-head-row">' +
                    '<span class="bi bi-palette2 sd-theme-head-icon"></span>' +
                    '<span class="sd-theme-head-txt">' +
                        (th
                            ? '<strong>' + esc(th.name) + '</strong><br><span class="sd-theme-head-hint">' + esc(_bsVars ? _sdT('Loads after every stylesheet and overrides the variables below.') : _sdT('Loads after every stylesheet of the design.')) + '</span>'
                            : '<strong>' + esc(_sdT('No theme')) + '</strong><br><span class="sd-theme-head-hint">' + esc(_bsVars ? _sdT('The variables below live in User Styles.') : _sdT('A theme file loads after every stylesheet of the design.')) + '</span>') +
                    '</span>' +
                    '<button type="button" class="sd-theme-head-btn" data-act="pick" title="' + esc(_sdT('Choose a theme (Settings → Theme)')) + '"><span class="bi bi-gear"></span></button>' +
                '</div>' +
                (_bsVars
                    ? '<button type="button" class="sd-theme-save-btn" data-act="save">' +
                          '<span class="bi bi-save me-1"></span>' + esc(_sdT('Save these colours as a theme')) +
                      '</button>'
                    : '');
            head.querySelector('[data-act="pick"]').addEventListener('click', H.openDesignSettings);
            var _saveBtn = head.querySelector('[data-act="save"]');
            if (_saveBtn) _saveBtn.addEventListener('click', _sdSaveAsTheme);
            var lookBtn = head.querySelector('[data-act="look"]');
            if (lookBtn) lookBtn.addEventListener('click', _sdOpenThemeSettings);
        }
        // The part runs once, so the listener is bound once.
        document.addEventListener('pg-design-theme-change', function () { if (H) _renderThemeHead(); });

        // Write the light/dark variable blocks to a new theme file and make
        // it the design's theme. The variables stay in User Styles as well:
        // the file is a snapshot the operator can reuse on other designs.
        function _sdSaveAsTheme() {
            var css = H.themeCss();
            if (!css) {
                sdToast(_sdT('Nothing to save yet — change a colour or two first.'), 'warning', 4000);
                return;
            }
            var name = window.prompt(_sdT('Theme name:'), '');
            if (!name || !name.trim()) return;
            _pgApiPost('theme_save', { name: name.trim(), css: css }).then(function (res) {
                if (!res || res.status !== 'success') {
                    sdToast((res && res.message) ? res.message : _sdT('The theme could not be saved.'), 'error', 6000);
                    return;
                }
                var sel = document.querySelector('select[name="theme_id"]');
                if (sel) {
                    var opt = document.createElement('option');
                    opt.value = String(res.id); opt.textContent = res.name;
                    sel.appendChild(opt);
                    sel.value = String(res.id);
                    sel.dispatchEvent(new Event('change', { bubbles: true }));   // canvas link + lists
                }
                sdToast('<strong>' + esc(res.name) + '</strong> ' + esc(_sdT('was saved and selected as this design\'s theme. Save the design to keep the choice.')), 'success', 7000);
            }).catch(function () { sdToast(_sdT('Network error.'), 'error'); });
        }

        function renderThemePanel() {
            _renderThemeHead();
            var host = document.getElementById('sd-theme-vars');
            if (!host) return;
            // The variables below are Bootstrap's (--bs-*): a design built
            // without a framework, or on another one, reads none of them, so
            // neither they nor the light/dark blocks they are written to are
            // offered there.
            var _modeTabs = H.wrap.querySelector('.sd-theme-mode-tabs');
            if (_modeTabs) _modeTabs.style.display = _sdUsesBootstrap() ? '' : 'none';
            if (!_sdUsesBootstrap()) {
                host.innerHTML = '<p class="form-text small px-3 mb-0">' +
                    esc(_sdT('This design is built without Bootstrap, so the Bootstrap variables (colours, fonts, borders) are not offered here. Put your own variables in one of the design\'s stylesheets, or choose a theme file above.')) + '</p>';
                return;
            }
            // Gather current values once per render
            var content = H.userStyles();
            var current = _themeModeDecls(content, H.mode());
            // In dark mode a variable without a Bootstrap dark value shows
            // the light value it inherits there as its placeholder.
            var inherited = (H.mode() === 'dark') ? _themeModeDecls(content, 'light') : {};

            var html = '';
            THEME_VAR_GROUPS.forEach(function (g) {
                var collapsed = _isGrpCollapsed(g);
                html += '<div class="sd-theme-grp' + (collapsed ? ' collapsed' : '') + '" data-grp="' + g.id + '">' +
                            '<div class="sd-theme-grp-hdr" data-grp="' + g.id + '">' +
                                '<span class="bi bi-chevron-' + (collapsed ? 'right' : 'down') + ' sd-theme-grp-chev"></span>' +
                                '<span class="sd-theme-grp-label">' + g.label + '</span>' +
                                '<span class="sd-theme-grp-spacer"></span>' +
                                '<button type="button" class="sd-theme-grp-act" data-act="reset-all" data-grp="' + g.id + '" title="' + esc(_sdT('Reset every change in this group')) + '"><span class="bi bi-arrow-counterclockwise"></span></button>' +
                            '</div>' +
                            '<div class="sd-theme-grp-body"' + (collapsed ? ' style="display:none"' : '') + '>';

                g.vars.forEach(function (v) {
                    var val = current[v.name] || '';
                    var ph  = (typeof v.def[H.mode()] === 'string') ? v.def[H.mode()] : '';
                    if (H.mode() === 'dark' && !_themeVarHasDarkDefault(v.name) && inherited[v.name]) ph = inherited[v.name];
                    var modified = !!val;        // ← class hook for the blue label
                    var displayColor = val || ph;

                    var labelHtml = '<span class="sd-theme-row-name' + (modified ? ' modified' : '') + '"' +
                                    ' data-var="' + v.name + '"' +
                                    ' title="' + (modified ? _sdT('Click to return to the default') : v.name) + '">' +
                                    v.label + '</span>';

                    if (v.kind === 'color') {
                        // Native <input type="color"> only understands #rrggbb.
                        var hex = (displayColor || '').trim();
                        if (!/^#[0-9a-fA-F]{6}$/.test(hex)) hex = (ph || '#000000');
                        if (!/^#[0-9a-fA-F]{6}$/.test(hex)) hex = '#000000';
                        html +=
                            '<div class="sd-theme-row" data-var="' + v.name + '">' +
                                labelHtml +
                                '<input type="text" class="sd-theme-row-text" data-var="' + v.name + '" value="' + esc(val) + '" placeholder="' + esc(ph) + '">' +
                                '<input type="color" class="sd-theme-row-color" data-var="' + v.name + '" value="' + hex + '">' +
                            '</div>';
                    } else if (v.kind === 'colorRgba') {
                        // No native picker for rgba/hex8 — text-only.
                        html +=
                            '<div class="sd-theme-row sd-theme-row-rgba" data-var="' + v.name + '">' +
                                labelHtml +
                                '<input type="text" class="sd-theme-row-text" data-var="' + v.name + '" value="' + esc(val) + '" placeholder="' + esc(ph) + '">' +
                                '<span class="sd-theme-row-swatch" style="background:' + esc(displayColor) + '"></span>' +
                            '</div>';
                    } else if (v.kind === 'select') {
                        var opts = (v.opts || []).map(function (o) {
                            var sel = (val === o) || (!val && ph === o);
                            return '<option value="' + esc(o) + '"' + (sel ? ' selected' : '') + '>' + esc(v.optLabels && v.optLabels[o] ? v.optLabels[o] : o) + '</option>';
                        }).join('');
                        html +=
                            '<div class="sd-theme-row sd-theme-row-select" data-var="' + v.name + '">' +
                                labelHtml +
                                '<select class="sd-theme-row-sel" data-var="' + v.name + '">' + opts + '</select>' +
                            '</div>';
                    } else {
                        html +=
                            '<div class="sd-theme-row sd-theme-row-text-only" data-var="' + v.name + '">' +
                                labelHtml +
                                '<input type="text" class="sd-theme-row-text" data-var="' + v.name + '" value="' + esc(val) + '" placeholder="' + esc(ph) + '">' +
                            '</div>';
                    }
                });
                html += '</div></div>';
            });
            host.innerHTML = html;

            // ── Wire interactions ──────────────────────────────────────────
            // Group header → toggle collapse (chevron + body).
            host.querySelectorAll('.sd-theme-grp-hdr').forEach(function (hdr) {
                hdr.addEventListener('click', function (e) {
                    if (e.target.closest('.sd-theme-grp-act')) return;  // ignore toolbar clicks
                    var id = this.dataset.grp;
                    var grpEl = host.querySelector('.sd-theme-grp[data-grp="' + id + '"]');
                    if (!grpEl) return;
                    var nowCollapsed = !grpEl.classList.contains('collapsed');
                    grpEl.classList.toggle('collapsed', nowCollapsed);
                    var body = grpEl.querySelector('.sd-theme-grp-body');
                    var chev = grpEl.querySelector('.sd-theme-grp-chev');
                    if (body) body.style.display = nowCollapsed ? 'none' : '';
                    if (chev) chev.className = 'bi bi-chevron-' + (nowCollapsed ? 'right' : 'down') + ' sd-theme-grp-chev';
                    _setGrpCollapsed(id, nowCollapsed);
                });
            });
            // Group action buttons: each group's reset.
            host.querySelectorAll('.sd-theme-grp-act').forEach(function (btn) {
                btn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    var act = this.dataset.act;
                    var id  = this.dataset.grp;
                    if (act === 'reset-all') {
                        var grp = null;
                        for (var k = 0; k < THEME_VAR_GROUPS.length; k++) if (THEME_VAR_GROUPS[k].id === id) { grp = THEME_VAR_GROUPS[k]; break; }
                        if (!grp) return;
                        grp.vars.forEach(function (v) { _writeThemeVar(H.mode(), v.name, ''); });
                        renderThemePanel();
                        applyAssetsToIframe();
                    }
                });
            });

            // Color picker → mirror into text + write on change.
            host.querySelectorAll('.sd-theme-row-color').forEach(function (cp) {
                cp.addEventListener('input', function () {
                    var row = this.closest('.sd-theme-row');
                    var txt = row.querySelector('.sd-theme-row-text');
                    if (txt) txt.value = this.value;
                });
                cp.addEventListener('change', function () {
                    _writeThemeVar(H.mode(), this.dataset.var, this.value);
                    renderThemePanel();
                });
            });
            // Free text → mirror to color picker if hex, write on change.
            host.querySelectorAll('.sd-theme-row-text').forEach(function (inp) {
                inp.addEventListener('change', function () {
                    var row = this.closest('.sd-theme-row');
                    var cp  = row.querySelector('.sd-theme-row-color');
                    if (cp && /^#[0-9a-fA-F]{6}$/.test(this.value)) cp.value = this.value;
                    var sw  = row.querySelector('.sd-theme-row-swatch');
                    if (sw) sw.style.background = this.value || '';
                    _writeThemeVar(H.mode(), this.dataset.var, this.value.trim());
                    renderThemePanel();
                });
            });
            // Select dropdown → write on change.
            host.querySelectorAll('.sd-theme-row-sel').forEach(function (sel) {
                sel.addEventListener('change', function () {
                    _writeThemeVar(H.mode(), this.dataset.var, this.value);
                    renderThemePanel();
                });
            });

            // Label-as-reset: clicking a modified label reverts the override.
            host.querySelectorAll('.sd-theme-row-name').forEach(function (lbl) {
                lbl.addEventListener('click', function () {
                    if (!this.classList.contains('modified')) return;
                    _writeThemeVar(H.mode(), this.dataset.var, '');
                    renderThemePanel();
                    applyAssetsToIframe();
                });
            });
        }

    return {
        // Draws the header and the variable rows of the mode the host shows.
        render: function (host) {
            H = host;
            renderThemePanel();
        },
        // Writes one variable of `mode` (an empty value removes it).
        write: function (host, mode, name, value) {
            H = host;
            _writeThemeVar(mode, name, value);
        }
    };
});
