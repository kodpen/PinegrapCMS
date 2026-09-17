/*
 * pg_civ_variants.js
 *
 * Client-side variant chooser for the catalog_item_view widget when the
 * URL slug points at a display_type='select' product_group.
 *
 * Wires:
 *   • <select.pg-civ-variant-attr> elements (one per shared attribute)
 *   • [data-pg-bind="prop:field;..."] elements (every node the designer
 *     bound to a product field — title, price, image, brand, …)
 *   • The wrapping <form data-pg-civ-form> (product_id hidden input)
 *   • The carousel inside the widget (refreshed when image gallery changes)
 *   • The Sepete Ekle button (disabled until a complete variant is picked)
 *
 * Data flow:
 *   1. PHP emits one or more `window.pgCivVariants.push({...})` blobs
 *      (one per catalog_item_view widget on the page — usually exactly one).
 *   2. On DOMContentLoaded we walk each blob, find the picker DOM, and
 *      attach change listeners.
 *   3. When the visitor picks a complete combination → `applyVariant()`:
 *        - Find the matching product from preloaded data (instant).
 *        - Update every [data-pg-bind] element using the bound field map.
 *        - Refresh the carousel: rebuild slides + thumbs from new gallery.
 *        - Update product_id hidden input on the wrapping form.
 *        - Enable the Sepete Ekle button.
 *        - Fire a background API call to refresh stock + computed fields.
 *
 * SSR-safe: page works without JS — backend renders the FIRST product in
 * the group as the default. The variant chooser is purely an enhancement
 * that lets the visitor swap to a different product without a page reload.
 */
(function () {
    'use strict';

    // Wait for the queued blobs (PHP pushes via inline scripts above us).
    function init() {
        if (!window.pgCivVariants || !window.pgCivVariants.length) return;
        window.pgCivVariants.forEach(function (data) {
            try { wireVariantChooser(data); } catch (e) { /* swallow per-instance */ }
        });
    }

    /**
     * Wire one variant-chooser instance.
     * @param {Object} data — the JSON payload PHP pushed:
     *   { group_id, group_name, attrs: {aid: {label, options: {oid: label}}},
     *     products: [{id, name, ..., attrs: {aid: oid}, gallery: [url]}],
     *     currency_symbol, api_url }
     */
    function wireVariantChooser(data) {
        if (!data || !data.products || !data.attrs) return;

        // Find any variant-attribute select on the page belonging to this
        // group's attributes. Two layouts emit such elements:
        //   • Legacy `.pg-civ-variant-picker` (auto-injected single block)
        //   • New `variant_attr` content type (one wrap per attribute)
        // The JS doesn't care which one — it just needs ONE select per
        // attribute and the wrapping form. We anchor on the first
        // `.pg-civ-variant-attr` in the document and walk up to its form.
        var anchor = document.querySelector(
            '.pg-civ-variant-picker[data-pg-civ-group-id="' + data.group_id + '"]'
        ) || document.querySelector('.pg-civ-variant-attr[data-pg-civ-attr]');
        if (!anchor) return;

        // The wrapping form (catalog_item_view auto-wraps everything).
        // We look up by walking ancestors so the controls can sit anywhere
        // inside the widget tree without needing a known parent.
        var form = anchor.closest('form[data-pg-civ-form]') || anchor.closest('form');

        // Disable Sepete Ekle until a complete variant is picked. We mark
        // EVERY add_to_cart-bound submit button (the designer may have
        // multiple — e.g. one in the toolbar, one in a sticky footer).
        var addBtns = form ? form.querySelectorAll('button[type="submit"]') : [];
        addBtns.forEach(function (b) {
            // Only disable submits that look like add-to-cart (the form's
            // PRIMARY action). If the designer added unrelated submits we'd
            // be over-eager — but in practice the auto-wrapped form has
            // exactly one submit (Sepete Ekle).
            b.setAttribute('data-pg-civ-orig-disabled', b.disabled ? '1' : '0');
            b.disabled = true;
            b.classList.add('pg-civ-awaiting-variant');
        });

        // Track the visitor's current selection. attribute_id → option_id.
        // Pre-fill from data.default_selection so the picker reflects the
        // currently-displayed product (the URL-resolved one). When the URL
        // pointed at /urun/Mocha-Office-Chair, default_selection is
        // {color_attr_id: mocha_option_id} → JS pre-selects Mocha + enables
        // Sepete Ekle. Visitor still sees ALL options and can switch.
        var selection = {};
        var defaults = (data.default_selection && typeof data.default_selection === 'object') ? data.default_selection : {};
        Object.keys(data.attrs).forEach(function (aid) {
            selection[aid] = defaults[aid] ? String(defaults[aid]) : '';
        });

        // Wire each <select.pg-civ-variant-attr>. Also apply defaults to
        // the actual <select> elements so the visitor sees the matching
        // option already chosen on first paint.
        //
        // For the new variant_attr render styles (radio / btn-group / etc.),
        // the visible inputs are radios/checkboxes paired with a hidden
        // mirror <select.pg-civ-variant-attr.d-none>. We listen to BOTH
        // the mirror selects (for the legacy designer-placed picker) AND
        // the visible radios — radios update the mirror select's value
        // before triggering the standard `change` flow.
        //
        // We also scope the search to the form (not just the picker block)
        // because variant_attr templates can be placed ANYWHERE in the
        // designer's tree — including outside the auto-injected picker.
        var scopeForm = form || document;
        var selects = scopeForm.querySelectorAll('.pg-civ-variant-attr');
        selects.forEach(function (sel) {
            var aid = sel.getAttribute('data-pg-civ-attr');
            if (selection[aid]) {
                sel.value = selection[aid];
            }
            sel.addEventListener('change', function () {
                var attrId = sel.getAttribute('data-pg-civ-attr');
                selection[attrId] = sel.value;
                onSelectionChanged();
            });
        });

        // Visible radios / checkboxes from the variant_attr templates →
        // mirror their value into the hidden <select> + dispatch its
        // change event so the existing flow runs. Single-pick semantics
        // for checkboxes are enforced by un-checking siblings on click.
        var radios = scopeForm.querySelectorAll('.pg-civ-variant-attr-radio');
        radios.forEach(function (r) {
            r.addEventListener('change', function () {
                var aid = r.getAttribute('data-pg-civ-attr-mirror');
                if (!aid) return;
                // Single-pick checkboxes — uncheck siblings.
                if (r.type === 'checkbox') {
                    var grp = scopeForm.querySelectorAll('.pg-civ-variant-attr-radio[data-pg-civ-attr-mirror="' + aid + '"]');
                    grp.forEach(function (other) {
                        if (other !== r) other.checked = false;
                        // Also clear .active on labels (Bootstrap btn-check styling).
                        var lbl = scopeForm.querySelector('label[for="' + other.id + '"]');
                        if (lbl) lbl.classList.toggle('active', other.checked);
                    });
                    var ownLbl = scopeForm.querySelector('label[for="' + r.id + '"]');
                    if (ownLbl) ownLbl.classList.toggle('active', r.checked);
                }
                // Mirror value to the hidden select.
                var mirror = scopeForm.querySelector('select.pg-civ-variant-attr[data-pg-civ-attr="' + aid + '"]');
                if (mirror) {
                    mirror.value = r.checked ? r.value : '';
                    mirror.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });
        });

        // Initial pass — when defaults gave us a complete combination, the
        // Sepete Ekle button enables immediately and stock check fires once.
        // (Page already shows the matching product via SSR — no DOM update
        // needed for the initial frame.)
        var _hasInitialComplete = true;
        Object.keys(selection).forEach(function (aid) {
            if (!selection[aid]) _hasInitialComplete = false;
        });
        if (_hasInitialComplete) {
            // Don't re-apply (the SSR already painted the right product).
            // Just enable Sepete Ekle and trigger a background stock check.
            addBtns.forEach(function (b) {
                b.disabled = false;
                b.classList.remove('pg-civ-awaiting-variant');
            });
            if (data.default_product_id) refreshFromApi(data.default_product_id);
        }

        function onSelectionChanged() {
            // Need every attribute chosen for a complete combination.
            var complete = true;
            Object.keys(selection).forEach(function (aid) {
                if (!selection[aid]) complete = false;
            });
            if (!complete) {
                // Partial — disable Sepete Ekle (revert any half-applied).
                addBtns.forEach(function (b) { b.disabled = true; b.classList.add('pg-civ-awaiting-variant'); });
                return;
            }
            var match = findVariant(selection);
            if (!match) {
                // No product satisfies the chosen combination — surface
                // the situation near the controls rather than silently
                // failing. Anchor (picker OR first variant_attr select)
                // is the cheapest "near the user's interaction" target.
                showPickerNotice(anchor, 'Bu seçim için ürün bulunamadı.');
                addBtns.forEach(function (b) { b.disabled = true; });
                return;
            }
            clearPickerNotice(anchor);
            applyVariant(match);
            addBtns.forEach(function (b) { b.disabled = false; b.classList.remove('pg-civ-awaiting-variant'); });

            // Background API refresh for stock accuracy + any field not
            // preloaded. Failure is tolerated — the preloaded data is
            // already on screen.
            refreshFromApi(match.id);
        }

        function findVariant(sel) {
            for (var i = 0; i < data.products.length; i++) {
                var p = data.products[i];
                var ok = true;
                Object.keys(sel).forEach(function (aid) {
                    if (String(p.attrs[aid]) !== String(sel[aid])) ok = false;
                });
                if (ok) return p;
            }
            return null;
        }

        function applyVariant(p) {
            // 1. Update every [data-pg-bind] element inside the form.
            //    The data-pg-bind attribute is "prop1:field1;prop2:field2;..."
            //    where prop is HTML prop (text/html/src/alt/href/value) and
            //    field is the product key (name/price_formatted/image_url…).
            var scope = form || document;
            var els = scope.querySelectorAll('[data-pg-bind]');
            els.forEach(function (el) {
                var spec = el.getAttribute('data-pg-bind');
                if (!spec) return;
                spec.split(';').forEach(function (pair) {
                    var parts = pair.split(':');
                    if (parts.length !== 2) return;
                    var prop  = parts[0].trim();
                    var field = parts[1].trim();
                    if (!prop || !field) return;
                    if (!(field in p)) return;
                    var value = p[field];
                    if (value === null || value === undefined) value = '';
                    setProp(el, prop, String(value));
                });
            });

            // 2. Refresh the carousel — rebuild slides + thumbnails from
            //    the new product's gallery. Walks all carousels inside
            //    the form scope.
            if (p.gallery && p.gallery.length && form) {
                var carousels = form.querySelectorAll('.pg-carousel-wrapper, .carousel.pg-carousel');
                carousels.forEach(function (cw) { rebuildCarousel(cw, p.gallery); });
            }

            // 3. Update product_id hidden input on the form.
            if (form) {
                var pidInp = form.querySelector('input[type="hidden"][name="product_id"]');
                if (pidInp) pidInp.value = String(p.id);
            }

            // 4. Update browser URL slug (without reload) so refresh /
            //    bookmark / share lands on the chosen variant. The slug
            //    is the variant's address_name when set.
            if (p.address_name && window.history && window.history.replaceState) {
                try {
                    var u = new URL(window.location.href);
                    var parts = u.pathname.split('/');
                    parts[parts.length - 1] = encodeURIComponent(p.address_name);
                    u.pathname = parts.join('/');
                    window.history.replaceState({}, '', u.pathname + u.search + u.hash);
                } catch (_) {}
            }

            // 5. Refresh cross-sell suggestions for the new product. Cross-sell
            //    is keyed by the source product (different products → different
            //    "ordered together" patterns), so a fresh lookup is needed.
            //    Uses the existing get_cross_sell_items API endpoint.
            refreshCrossSell(p.id);
        }

        /**
         * Refresh the cross-sell row for a new source product. Replaces the
         * inner HTML of the FIRST `[data-pg-civ-cross-sell="1"]` element
         * in the form scope.
         */
        function refreshCrossSell(productId) {
            if (!data.api_url || !productId || !form) return;
            var cs = form.querySelector('[data-pg-civ-cross-sell="1"]');
            if (!cs) return;
            var detailPid    = cs.getAttribute('data-pg-civ-detail-page') || '0';
            var count        = cs.getAttribute('data-pg-civ-count') || '4';
            var columns      = parseInt(cs.getAttribute('data-pg-civ-columns'), 10) || 4;
            var cardClass    = cs.getAttribute('data-pg-civ-card-class') || '';
            var imageAspect  = cs.getAttribute('data-pg-civ-image-aspect') || '1/1';
            var showPrice    = cs.getAttribute('data-pg-civ-show-price') !== '0';
            var colMd        = Math.floor(12 / columns);
            var colSm        = columns >= 4 ? 6 : 12;

            // Use the new endpoint that returns Tier-1 (order history) with
            // automatic Tier-2 fallback (same-group siblings). Both tiers
            // return the same item shape, including a pre-built
            // `price_block_html` string with strike-through markup baked in
            // when a discount applies.
            //
            // CRITICAL: api.php reads the request via
            //   `json_decode(file_get_contents('php://input'), true)`
            // — it does NOT inspect $_POST. Posting FormData here would make
            // `$request` null on the server, $_x_pid fall to 0, and the
            // endpoint silently return zero items. With my "don't wipe on
            // empty" graceful-degradation fix above, that would manifest as
            // stale cross-sell rows from the PREVIOUS variant lingering on
            // screen after the visitor switches variants — which exactly
            // matches the reported "Turuncu görünürken cross-sell'de yine
            // Turuncu var" symptom (the stale row IS the previous variant
            // showing up in its own cross-sell strip). Send JSON instead.
            var body = {
                action: 'get_cross_sell_for_product',
                product: { id: parseInt(productId, 10) },
                count: parseInt(count, 10)
            };
            if (parseInt(detailPid, 10) > 0) {
                body.detail_page_id = parseInt(detailPid, 10);
            }
            fetch(data.api_url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body),
                credentials: 'same-origin'
            })
                .then(function (r) { return r.json(); })
                .then(function (json) {
                    if (!json || json.status !== 'success' || !Array.isArray(json.items) || json.items.length === 0) {
                        // No items returned for the new variant. Rather than
                        // wiping the wrapper (which would HIDE the cross-sell
                        // section and surprise the visitor mid-flow), keep
                        // whatever the SSR / previous variant rendered. The
                        // displayed items remain related to the same product
                        // family, so they stay reasonable suggestions even
                        // when the per-variant lookup returns nothing
                        // (e.g. a brand-new variant with no order history
                        // and no group siblings of its own).
                        return;
                    }
                    // Rebuild the cards. Mirrors _civ_render_cross_sell PHP
                    // markup — keep the two in sync if the card layout
                    // ever changes. price_block_html comes pre-rendered
                    // from the server so the strike-through styling stays
                    // identical to SSR.
                    var html = '<h3 class="h5 mb-3">Birlikte sıkça satın alınanlar</h3><div class="row g-3">';
                    json.items.forEach(function (it) {
                        var img = it.image_url
                            ? '<img src="' + escAttr(it.image_url) + '" class="card-img-top" alt="" loading="lazy" style="aspect-ratio:' + escAttr(imageAspect) + ';object-fit:cover">'
                            : '<div class="card-img-top bg-body-tertiary" style="aspect-ratio:' + escAttr(imageAspect) + '"></div>';
                        var label = (it.short_description || it.name || '').toString();
                        var priceBlock = '';
                        if (showPrice && it.price_block_html) {
                            priceBlock = '<div class="small mt-1">' + it.price_block_html + '</div>';
                        }
                        html += '<div class="col-' + colSm + ' col-md-' + colMd + '">'
                              + '<a href="' + escAttr(it.url || '#') + '" class="card h-100 text-decoration-none text-body' + (cardClass ? ' ' + escAttr(cardClass) : '') + '">'
                              + img
                              + '<div class="card-body p-2">'
                              + '<div class="small fw-semibold text-truncate">' + escHtml(label) + '</div>'
                              + priceBlock
                              + '</div>'
                              + '</a>'
                              + '</div>';
                    });
                    html += '</div>';
                    cs.innerHTML = html;
                })
                .catch(function () { /* leave existing row in place */ });
        }

        /**
         * Rebuild a carousel's slides + thumbnails from a new gallery list.
         * Preserves the carousel's wrapper/options (thumbsPosition, lightbox,
         * etc.) — only swaps inner images. Bootstrap re-initializes
         * automatically because the carousel root is the same element.
         */
        function rebuildCarousel(wrapper, gallery) {
            var carouselEl = wrapper.classList.contains('carousel') ? wrapper : wrapper.querySelector('.carousel');
            if (!carouselEl) return;
            var inner = carouselEl.querySelector('.carousel-inner');
            var indicators = carouselEl.querySelector('.carousel-indicators');
            var thumbs = wrapper.querySelector('.pg-carousel-thumbs');
            var crlId = carouselEl.id;
            if (!inner) return;

            // Detect lightbox + Fancybox gallery name — slides use
            // <a data-fancybox="..." href="..."><img></a> wrap.
            var firstLbAnchor = inner.querySelector('a[data-fancybox]');
            var hasLightbox = !!firstLbAnchor;
            var fbGallery = hasLightbox ? (firstLbAnchor.getAttribute('data-fancybox') || '') : '';

            // Detect zoom-on-hover.
            var hasZoom = !!inner.querySelector('.pg-zoom-on-hover');

            // Build new inner — Fancybox needs the anchor wrap to know
            // which URL to open at full size.
            inner.innerHTML = gallery.map(function (url, i) {
                var act = (i === 0) ? ' active' : '';
                var zoomCls = hasZoom ? ' pg-zoom-on-hover' : '';
                var zoomAttr = hasZoom ? ' data-pg-zoom-src="' + escAttr(url) + '"' : '';
                if (hasLightbox) {
                    return '<div class="carousel-item' + act + '">'
                        + '<a href="' + escAttr(url) + '"'
                        + ' data-fancybox="' + escAttr(fbGallery) + '"'
                        + ' class="pg-carousel-slide-link d-block w-100' + zoomCls + '"'
                        + zoomAttr + '>'
                        + '<img src="' + escAttr(url) + '" class="d-block w-100" alt="" loading="lazy">'
                        + '</a>'
                        + '</div>';
                }
                return '<div class="carousel-item' + act + '">'
                    + '<img src="' + escAttr(url) + '" class="d-block w-100' + zoomCls + '"'
                    + ' alt="" loading="lazy"' + zoomAttr + '>'
                    + '</div>';
            }).join('');

            // Rebuild indicators (only when present + multi-slide).
            if (indicators) {
                if (gallery.length > 1) {
                    indicators.innerHTML = gallery.map(function (_, i) {
                        var sel = (i === 0) ? ' class="active" aria-current="true"' : '';
                        return '<button type="button" data-bs-target="#' + crlId + '" data-bs-slide-to="' + i + '"' + sel + ' aria-label="Slide ' + (i + 1) + '"></button>';
                    }).join('');
                } else {
                    indicators.innerHTML = '';
                }
            }

            // Rebuild thumbs (preserve size + active-border style by reading
            // the first existing thumb's inline style). Fancybox-enabled
            // thumbs use <a data-fancybox> wrap; plain thumbs are bare <img>.
            if (thumbs && gallery.length > 1) {
                var firstThumb = thumbs.querySelector('.pg-carousel-thumb');
                var thumbStyle = firstThumb ? firstThumb.getAttribute('style') : 'width:60px;height:60px;object-fit:cover;cursor:pointer';
                thumbs.innerHTML = gallery.map(function (url, i) {
                    var actCls = (i === 0) ? ' active' : '';
                    if (hasLightbox) {
                        // Thumbs are triggers (not gallery members) so the
                        // total count stays N (not 2N). data-fancybox-trigger
                        // + data-fancybox-index opens the EXISTING gallery
                        // at the requested index.
                        return '<a href="' + escAttr(url) + '"'
                            + ' data-fancybox-trigger="' + escAttr(fbGallery) + '"'
                            + ' data-fancybox-index="' + i + '"'
                            + ' data-bs-target="#' + crlId + '" data-bs-slide-to="' + i + '"'
                            + ' class="pg-carousel-thumb-link">'
                            + '<img src="' + escAttr(url) + '"'
                            + ' class="pg-carousel-thumb' + actCls + '" style="' + escAttr(thumbStyle) + '" alt="" loading="lazy">'
                            + '</a>';
                    }
                    return '<img src="' + escAttr(url) + '" data-bs-target="#' + crlId + '" data-bs-slide-to="' + i + '"'
                        + ' class="pg-carousel-thumb' + actCls + '" style="' + escAttr(thumbStyle) + '" alt="" loading="lazy">';
                }).join('');
            } else if (thumbs && gallery.length <= 1) {
                thumbs.innerHTML = '';
            }

            // Tell Bootstrap to reset its internal slide index. Without this
            // the carousel may try to jump to an invalid index after gallery
            // shrinks.
            try {
                var bs = window.bootstrap && window.bootstrap.Carousel;
                if (bs) {
                    var inst = bs.getInstance(carouselEl);
                    if (inst) { inst.dispose(); }
                    new bs(carouselEl);
                }
            } catch (_) {}

            // Re-bind Fancybox so the new <a data-fancybox> elements
            // are picked up. The global init in _pg_fancybox_assets_once()
            // exposes window.pgFancyboxRebind() for exactly this case
            // (unbind + rebind to refresh the gallery).
            try {
                if (typeof window.pgFancyboxRebind === 'function') {
                    window.pgFancyboxRebind();
                }
            } catch (_) {}
        }

        function refreshFromApi(productId) {
            if (!data.api_url || !productId) return;
            // api.php reads the request body via json_decode(php://input). See
            // refreshCrossSell above for the full reasoning — FormData would
            // make $request null and leak nothing useful into the action.
            fetch(data.api_url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'get_product',
                    product: { id: parseInt(productId, 10) }
                }),
                credentials: 'same-origin'
            })
                .then(function (r) { return r.json(); })
                .then(function (json) {
                    if (!json || json.status !== 'success' || !json.product) return;
                    var p = json.product;
                    // Re-apply only the fields the API returned (overrides the
                    // preloaded values with fresh stock + computed fields).
                    var scope = form || document;
                    var els = scope.querySelectorAll('[data-pg-bind]');
                    els.forEach(function (el) {
                        var spec = el.getAttribute('data-pg-bind');
                        if (!spec) return;
                        spec.split(';').forEach(function (pair) {
                            var parts = pair.split(':');
                            if (parts.length !== 2) return;
                            var prop  = parts[0].trim();
                            var field = parts[1].trim();
                            if (!(field in p)) return;
                            var v = p[field];
                            if (v === null || v === undefined) v = '';
                            setProp(el, prop, String(v));
                        });
                    });
                    // Stock-aware Sepete Ekle: disable when the API reports
                    // out_of_stock=1 (more authoritative than preloaded data).
                    if (parseInt(p.out_of_stock, 10) === 1) {
                        addBtns.forEach(function (b) { b.disabled = true; b.classList.add('pg-civ-out-of-stock'); });
                    } else {
                        addBtns.forEach(function (b) { b.classList.remove('pg-civ-out-of-stock'); });
                    }
                })
                .catch(function () { /* ignore — preloaded data is the floor */ });
        }
    }

    /**
     * Apply a prop value to an element. Handles the prop-name conventions
     * used by the designer's binding system:
     *   text  → element.textContent (when no children) OR innerHTML (heading/paragraph allow inline tags)
     *   html  → element.innerHTML (designer-html-rendered content)
     *   src   → element.src + element.style.backgroundImage (for css backgrounds)
     *   alt   → element.alt
     *   href  → element.href
     *   value → element.value (form inputs)
     */
    function setProp(el, prop, val) {
        switch (prop) {
            case 'text':
                // Heading/paragraph content types support inline HTML — use
                // innerHTML so embedded <strong> / <em> survive. For plain
                // span / div we still prefer textContent to avoid XSS via
                // an unsanitized API response — but the API is internal so
                // we trust it; consumers that need stricter handling can
                // wrap their bindings with text nodes only.
                el.innerHTML = val;
                break;
            case 'html':
                el.innerHTML = val;
                break;
            case 'src':
                if (el.tagName === 'IMG') {
                    el.src = val;
                } else {
                    el.style.backgroundImage = val ? 'url("' + val.replace(/"/g, '\\"') + '")' : '';
                }
                break;
            case 'alt':
                el.alt = val;
                break;
            case 'href':
                el.href = val;
                break;
            case 'value':
                if ('value' in el) el.value = val;
                break;
            default:
                // Unknown prop — no-op (designer might add new bindings).
                break;
        }
    }

    function escAttr(s) {
        return String(s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }
    function escHtml(s) {
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function showPickerNotice(picker, msg) {
        var n = picker.querySelector('.pg-civ-picker-notice');
        if (!n) {
            n = document.createElement('div');
            n.className = 'pg-civ-picker-notice alert alert-warning py-1 mt-2 mb-0';
            picker.appendChild(n);
        }
        n.textContent = msg;
    }
    function clearPickerNotice(picker) {
        var n = picker.querySelector('.pg-civ-picker-notice');
        if (n) n.remove();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
