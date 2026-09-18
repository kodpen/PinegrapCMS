function init_page_designer(properties) {
    var
        userrole = properties.role,
        labels = properties.labels,
        starting_item_type = properties.starting_item_type,
        starting_item_id = properties.starting_item_id,

        query = properties.query,
        url = properties.url,
        style_id = '',
        page,
        code_type = '',
        code_id = '',
        content_changed = false,
        dynamic_regions = properties.dynamic_regions,
        editor;


    // Define the handler function separately
    function handlePregionInsert() {
        const doc = window.editor.getDoc();
        const cursor = doc.getCursor(); // Get current cursor position
        doc.replaceRange('<pregion></pregion>', cursor); // Insert at cursor
        RegionModalInstance.hide();
    }

    // Rebind the event from scratch
    function rebindPregionButton() {
        const button = document.getElementById('pregionButton');
        if (!button) return;

        // Remove any previous listener to avoid duplicates
        button.removeEventListener('click', handlePregionInsert);

        // Attach fresh listener
        button.addEventListener('click', handlePregionInsert);
    }

    function open_item(properties) {
        var type = properties.type;
        var id = properties.id;
        var active = properties.active;

        // If the content of the existing item has not been saved or cancelled yet,
        // then show confirmation to user and do not open new item.
        if (content_changed == true) {
            alert(labels['Sorry, please save or cancel your changes first.']);
            return false;
        }
        $('.page_designer_item').removeClass('active fw-bolder');
        $('#' + active).addClass('active fw-bolder');

        code_type = type;
        code_id = id;

        // Update the URL in the address bar, so that if the user refreshes the browser
        // then the correct item will be opened.
        history.replaceState({}, null,
            path + software_directory + '/page_designer.php?url=' + encodeURIComponent(url) + '&type=' + code_type + '&id=' + code_id);

        // If a layout is being opened in the code panel, then add class to code panel,
        // so a different color header can be shown.
        if (type == 'layout') {
            $('.code').addClass('layout');
        } else {
            $('.code').removeClass('layout');
        }

        switch (type) {
            case 'style':
                var output_heading = labels['Page Style'];
                var mode = 'htmlmixed';
                style_id = id;
                break;

            case 'layout':
                var output_heading = labels['Layout'];
                var mode = 'php';
                break;

            case 'css':
                var output_heading = labels['Design File'];
                var mode = 'css';
                break;

            case 'js':
                var output_heading = labels['Design File'];
                var mode = 'javascript';
                break;

            case 'designer_region':
                var output_heading = labels['Designer Region'];
                var mode = 'htmlmixed';
                break;

            case 'dynamic_region':
                var output_heading = labels['Dynamic Region'];
                var mode = 'php';
                break;
        }

        $('#editorpanel').html(`
            <div class="card-header d-flex align-items-center border-0 bg-transparent">
                    <span class="type rounded-top">${h(output_heading)}</span>
                    <span class="name me-2"></span>
                    <button data-bs-target="#editor_region_modal" data-bs-toggle="modal" class="me-auto  hide-on-fullscreen btn btn-sm btn-link link-secondary border-0 no-popover lh-1 p-0 bi bi-question" title="${h(labels['Help about the system tags'])}"></button>
                    <button class="btn btn-sm btn-link link-secondary border-0 no-popover lh-1 p-0 ms-auto me-2 bi fullscreen-toggle" data-bs-target="#editorpanel" title="${h(labels['Toggle Fullscreen'])}"></button>
            </div>
            <div class="card-body p-0 overflow-auto position-relative">
                <div class="editor_container">
                    <textarea id="content" style="display: none"></textarea>
                </div>
            </div>
            <div class="card-footer editor_control_buttons border-0" aria-label="content edit buttons">
                <div class="">
                    <button type="button" value="Save" class="btn btn-sm  btn-success save submit-primary disabled" title="${h(labels['Save'])} (Ctrl+S | &#8984;+S)"><span class="statusicon bi bi-floppy me-2" role="status" aria-hidden="true"></span> ${h(labels['Save'])}</button>
                    <button type="button" value="Cancel" class="btn btn-sm border-0 cancel submit-dark disabled" title="${h(labels['Undo Changes'])}" ><span class="statusicon bi bi-arrow-counterclockwise me-2" role="status" aria-hidden="true"></span>${h(labels['Undo Changes'])}</button>
                </div>
            </div>
            `);


        $('.page_designer .close').click(function (event) {
            event.preventDefault();
            close();
        });
        switch (type) {
            case 'style':
                var data = {
                    action: 'get_style',
                    style: {
                        id: id
                    }
                }

                break;

            case 'layout':
                var data = {
                    action: 'get_layout',
                    layout: {
                        id: id
                    }
                }

                break;

            case 'css':
            case 'js':
                var data = {
                    action: 'get_file',
                    file: {
                        id: id
                    }
                }

                break;

            case 'designer_region':
                var data = {
                    action: 'get_designer_region',
                    designer_region: {
                        id: id
                    }
                }

                break;

            case 'dynamic_region':
                var data = {
                    action: 'get_dynamic_region',
                    dynamic_region: {
                        id: id
                    }
                }

                break;
        }

        $.ajax({
            contentType: 'application/json',
            url: 'api.php',
            data: JSON.stringify(data),
            type: 'POST',
            success: function (response) {
                var readonly = false;

                switch (type) {
                    case 'style':
                        var name = response.style.name;
                        var content = response.style.code;
                        if (response.style.type == 'system') {
                            readonly = true;

                            $('.code .type').html(labels['System Page Style'] + '<strong class="ms-2 text-warning" style="font-size: 120%">(' + labels['read-only'] + ')</strong>');
                        }

                        break;

                    case 'layout':
                        var name = response.layout.name;
                        var content = response.layout.content;
                        break;

                    case 'css':
                    case 'js':
                        var name = response.file.name;
                        var content = response.file.content;

                        if (response.file.theme_type == 'system') {
                            readonly = true;

                            $('.code .type').html(labels['System Theme'] + '<strong class="ms-2 text-warning" style="font-size: 120%">(' + labels['read-only'] + ')</strong>');
                        }

                        break;

                    case 'designer_region':
                        var name = response.designer_region.name;
                        var content = response.designer_region.content;
                        break;

                    case 'dynamic_region':
                        var name = response.dynamic_region.name;
                        var content = response.dynamic_region.content;
                        break;
                }

                $('.code .name').html('<span class="mx-1">:</span>' + name);




                /*
                 * Fullscreen toggle.
                 *
                 * The previous detection tested document.fullScreenElement and
                 * elem.requestFullScreen with a capital S - names no shipping browser
                 * ever exposed - and it fell back to document.webkitIsFullScreen to
                 * decide direction. In Firefox none of the four probes exist, so the
                 * condition was always false and the button only ever tried to LEAVE
                 * fullscreen; it never entered. Safari below 16.4 has the prefixed
                 * methods only. Probe the real names in standard-first order instead.
                 */
                var pgFullscreenElement = function () {
                    return document.fullscreenElement
                        || document.webkitFullscreenElement
                        || document.mozFullScreenElement
                        || document.msFullscreenElement
                        || null;
                };

                var pgCallFullscreen = function (target, names) {
                    for (var i = 0; i < names.length; i++) {
                        var fn = target[names[i]];
                        if (typeof fn === 'function') {
                            try {
                                var result = fn.call(target);
                                // Only the standard API returns a promise.
                                if (result && typeof result.catch === 'function') {
                                    result.catch(function () { });
                                }
                            } catch (e) { }
                            return true;
                        }
                    }
                    return false;
                };

                $('.fullscreen-toggle').on('click', function () {
                    var targetSelector = $(this).attr('data-bs-target');
                    var elem = document.querySelector(targetSelector);
                    if (!elem) {
                        return;
                    }

                    if (!pgFullscreenElement()) {
                        pgCallFullscreen(elem, ['requestFullscreen', 'webkitRequestFullscreen', 'webkitRequestFullScreen', 'mozRequestFullScreen', 'msRequestFullscreen']);
                    } else {
                        pgCallFullscreen(document, ['exitFullscreen', 'webkitExitFullscreen', 'webkitCancelFullScreen', 'mozCancelFullScreen', 'msExitFullscreen']);
                    }
                });

                $('#content').val(content);
                editor = CodeMirror.fromTextArea(document.getElementById('content'), {
                    mode: mode,
                    lineNumbers: true,
                    indentUnit: 4,
                    coverGutterNextToScrollbar: 1,
                    styleActiveLine: true,
                    lineWrapping: false,
                    readOnly: readonly,
                    viewportMargin: Infinity,
                    matchTags: { bothTags: true },
                    autoCloseTags: true,
                    autoRefresh: true,
                    lint: true,
                    gutters: ["CodeMirror-lint-markers", "custom-gutter"],
                    autoCloseBrackets: true
                });
                $('#content').val(content);
                window.editor = editor;

                // --- Image preview logic ---
                var previewEl = document.createElement("img");
                previewEl.id = "cm-img-preview";
                previewEl.style.position = "fixed";
                previewEl.style.border = "1px solid #ccc";
                previewEl.style.maxWidth = "150px";
                previewEl.style.maxHeight = "150px";
                previewEl.style.display = "none";
                document.body.appendChild(previewEl);

                editor.on("mousemove", function (cm, e) {
                    var pos = cm.coordsChar({ left: e.clientX, top: e.clientY });
                    var token = cm.getTokenAt(pos);

                    // English comment: check if token looks like an image path
                    if (token.string.match(/\.(png|jpg|jpeg|gif)$/)) {
                        var src = token.string.replace(/['"]/g, ""); // remove quotes
                        // adapt {path} to real path if needed
                        src = src.replace("{path}", "/assets/");
                        previewEl.src = src;
                        previewEl.style.left = (e.clientX + 10) + "px";
                        previewEl.style.top = (e.clientY + 10) + "px";
                        previewEl.style.display = "block";
                    } else {
                        previewEl.style.display = "none";
                    }
                });

                // disable codemirror shortcut ctrl+d
                editor.setOption("extraKeys", {
                    "Ctrl-D": function (cm) { }
                });

                editor.focus();
                if (
                    type == 'style'
                    || type == 'designer_region'
                    || type == 'dynamic_region'
                ) {

                    var currentButtons = null;

                    // Add the region buttons to the gutter of the line the cursor is on.
                    editor.on('cursorActivity', function (cm) {
                        var cursor = cm.getCursor();
                        var line = cursor.line;

                        if (currentButtons && currentButtons.line !== line) {
                            cm.setGutterMarker(currentButtons.line, 'custom-gutter', null);
                            currentButtons = null;
                        }

                        if (!currentButtons) {
                            var addButton = document.createElement('button');
                            addButton.className = 'btn btn-primary bi bi-plus p-0 lh-1 position-absolute start-0 top-50 translate-middle hide-on-fullscreen';
                            addButton.setAttribute('data-bs-toggle', 'modal');
                            addButton.setAttribute('data-bs-target', '#regionModal');

                            var buttonContainer = document.createElement('div');
                            buttonContainer.className = 'btn-group';
                            buttonContainer.appendChild(addButton);

                            cm.setGutterMarker(line, 'custom-gutter', buttonContainer);
                            currentButtons = { line: line, element: buttonContainer };
                        }
                    });

                    // Page region button: insert a <pregion> tag at the cursor.
                    rebindPregionButton();


                }

                // Set Theme
                const theme = localStorage.getItem('pinegrap backend color scheme') || 'light';
                const prefersDarkScheme = window.matchMedia('(prefers-color-scheme: dark)').matches;
                const resolvedTheme = theme === 'auto' ? (prefersDarkScheme ? 'pastel-on-dark' : 'default') : (theme === 'dark' ? 'pastel-on-dark' : 'default');
                editor.setOption('theme', resolvedTheme);
                // Add listener for theme change
                window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
                    const prefersDarkScheme = window.matchMedia('(prefers-color-scheme: dark)').matches;
                    const theme = localStorage.getItem('pinegrap backend color scheme') || 'light';
                    const resolvedTheme = theme === 'auto' ? (prefersDarkScheme ? 'pastel-on-dark' : 'default') : (theme === 'dark' ? 'pastel-on-dark' : 'default');
                    editor.setOption('theme', resolvedTheme);
                });
                document.querySelectorAll('[data-bs-theme-value]').forEach(button => {
                    button.addEventListener('click', () => {
                        const selectedTheme = button.getAttribute('data-bs-theme-value');
                        const prefersDarkScheme = window.matchMedia('(prefers-color-scheme: dark)').matches;
                        const resolvedTheme = selectedTheme === 'auto' ? (prefersDarkScheme ? 'pastel-on-dark' : 'default') : (selectedTheme === 'dark' ? 'pastel-on-dark' : 'default');
                        editor.setOption('theme', resolvedTheme);
                    });
                });







                editor.on('change', function () {
                    // If this is the first time the editor content has been changed,
                    // then enable save & cancel buttons and actions for buttons.
                    if (content_changed == false) {
                        content_changed = true;

                        // Enable the save & cancel buttons.
                        $('.save').removeClass('disabled');
                        $('.cancel').removeClass('disabled');

                        // When the save button is clicked then save the form.
                        $('.save').click(function () {
                            $(this).html('<span class="statusicon spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>' + labels['Saving']);


                            editor.focus();
                            var new_content = editor.getValue();

                            switch (type) {
                                case 'style':
                                    var data = {
                                        action: 'update_style',
                                        token: software_token,
                                        style: {
                                            id: id,
                                            code: new_content
                                        }
                                    }

                                    break;

                                case 'layout':
                                    var data = {
                                        action: 'update_layout',
                                        token: software_token,
                                        layout: {
                                            id: id,
                                            content: new_content
                                        }
                                    }

                                    break;

                                case 'css':
                                case 'js':
                                    var data = {
                                        action: 'update_file',
                                        token: software_token,
                                        file: {
                                            id: id,
                                            content: new_content
                                        }
                                    }

                                    break;

                                case 'designer_region':
                                    var data = {
                                        action: 'update_designer_region',
                                        token: software_token,
                                        designer_region: {
                                            id: id,
                                            content: new_content
                                        }
                                    }

                                    break;

                                case 'dynamic_region':
                                    var data = {
                                        action: 'update_dynamic_region',
                                        token: software_token,
                                        dynamic_region: {
                                            id: id,
                                            content: new_content
                                        }
                                    }

                                    break;
                            }

                            $.ajax({
                                contentType: 'application/json',
                                url: 'api.php',
                                data: JSON.stringify(data),
                                type: 'POST',
                                success: function (response) {
                                    content_changed = false;

                                    // Store the new content, so if the cancel button is clicked
                                    // it will restore content from the last save, instead of
                                    // from when the editor was originally created.
                                    content = new_content;

                                    var preview_iframe = $('#preview');

                                    // If the preview iframe is currently on a front-end page
                                    // at this website, then reload the iframe.
                                    // The "|| page" is necessary, because there might be a PHP layout syntax error,
                                    // that prevents the software toolbar from appearing,
                                    // so that forces the page to be reloaded even if there is a PHP error on it.
                                    if (
                                        (check_iframe_access(preview_iframe[0]) == true) &&
                                        (
                                            (preview_iframe.contents().find('#software_toolbar').length) ||
                                            page
                                        )
                                    ) {
                                        document.getElementById('preview').contentWindow.location.reload();
                                    }

                                    // Disable the save & cancel buttons.
                                    $('.save').unbind('click');
                                    $('.save').addClass('disabled');
                                    $('.cancel').unbind('click');
                                    $('.cancel').addClass('disabled');

                                    setTimeout(function () {
                                        $('.save').html('<span class="statusicon bi bi-check-lg me-2" role="status" aria-hidden="true"></span>' + labels['Saved']);
                                    }, 500);
                                    setTimeout(function () {
                                        $('.save').html('<span class="statusicon bi bi-floppy me-2" role="status" aria-hidden="true"></span>' + labels['Save']);
                                    }, 1500);

                                }
                            });
                        });

                        // When the cancel button is clicked then restore original content
                        // and disable save & cancel buttons.
                        $('.cancel').click(function () {
                            // Update editor to contain original content.
                            editor.setValue(content);

                            content_changed = false;

                            // Disable the save & cancel buttons.
                            $('.save').unbind('click');
                            $('.save').addClass('disabled');
                            $('.cancel').unbind('click');
                            $('.cancel').addClass('disabled');
                        });
                    }
                });
            }
        });
    }

    var output_dynamic_region_button = '';
    if (dynamic_regions && dynamic_regions === 1) {
        output_dynamic_region_button = '<a type="button" id="dregionButton" class="btn text-start my-1" data-bs-toggle="modal" data-bs-target="#editor_modal_add_dynamic_region"><span class="bi text-warning-emphasis bi-filetype-php me-2"></span> ' + labels['Create Dynamic Region'] + '</a>';
    }
    $('body').append(`
        <main>
        <div class="container-fluid h-100 overflow-hidden pe-0">
          <div class="row h-100">
              <div class="col-9 col-md-10 col-xl-11 h-100">
                    <div class="row h-100">
                        <div class="col-12 col-lg-5 h-50 h-lg-100 p-2 pe-1 d-flex flex-column justify-content-center align-items-center">
                          <div id="previewpanel" class="card overflow-hidden rounded-4 border-2 border shadow-lg resizeable ">
                                <div class="card-header border shadow-sm rounded-4 d-flex flex-column pb-0 align-items-center z-1 hide-on-fullscreen backdrop" style="backdrop-filter: brightness(1) saturate(1.5) blur(20px);background-color: rgba(var(--bs-body-bg-rgb),60%);">
                                    <div class="mx-auto d-flex flex-row ">
                                        <button devicetype-set="mobile" class="fs-smaller px-1 py-0 btn btn-sm   bi bi-phone"></button>
                                        <button devicetype-set="tablet" class="fs-smaller px-1 py-0 btn btn-sm   bi bi-tablet"></button>
                                        <button devicetype-set="laptop" class="fs-smaller px-1 py-0 btn btn-sm   bi bi-laptop"></button>
                                        <button devicetype-set="wide" class="fs-smaller px-1 py-0 btn btn-sm     bi bi-badge-hd"></button>
                                        <button devicetype-set="desktop" class="fs-smaller px-1 py-0 btn btn-sm  bi bi-display"></button>
                                        <button devicetype-set="wider" class="fs-smaller px-1 py-0 btn btn-sm    bi bi-badge-4k"></button>
                                        <button data-bs-target="#previewpanel" class="fs-smaller px-1 py-0 btn btn-sm  bi fullscreen-toggle ms-2 no-popover " title="${h(labels['Toggle Fullscreen'])}"></button>
                                    </div>
                                    <span id="previewpanelsize" class="badge text-body mb-0 "></span>
                                </div>
                                <button data-bs-target="#previewpanel" class="fs-smaller px-1 py-0 btn btn-sm bi fullscreen-toggle show-on-fullscreen position-absolute end-0 top-0 me-3 mt-3 no-popover  z-3" title="${h(labels['Toggle Fullscreen'])}"></button>

                              <div class="card-body p-2">
                                  <iframe id="preview" src="${h(url)}" frameBorder="0" class="border-2 border rounded-3 overflow-hidden"></iframe>
                              </div>
                          </div>
                        </div>
                        <div class="col-12 col-lg-7 h-50 h-lg-100 p-2 ps-1 d-flex flex-column justify-content-center">
                          <div id="editorpanel" class="code card h-auto max-h-100 min-h-200px overflow-hidden">
                          <div class="progress" role="progressbar" aria-label="Animated striped example" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar progress-bar-striped bg-secondary progress-bar-animated" style="width: 100%"></div></div>
                          </div>
                        </div>
                    </div>
              </div> 
              <div class="col-3 col-md-2 col-xl-1 h-100 panel tool" id="panel_2" >
                    <div class="card rounded-0 bg-software-navbar content border-0 border-start h-100 overflow-hidden backdrop d-flex flex-column">
                        <div class="card-body p-0 p-lg-2 overflow-auto flex-shrink-1">
                            <span class="style"></span>
                            <ul class="listtree theme"></ul>
                            <ul class="listtree layout"></ul>
                            <ul class="listtree related_items"></ul>
                        </div>
                        <div id="console_errors" class="flex-shrink-0 border-top" style="display:none">
                            <div class="pd-console-header d-flex align-items-center px-2 py-1 gap-1" onclick="pdConsoleToggle()" style="cursor:pointer;user-select:none">
                                <span class="bi bi-terminal" style="font-size:.75rem"></span>
                                <span style="font-size:.7rem;font-family:monospace;font-weight:600">${h(labels['Console'])}</span>
                                <span id="pd_console_badge" class="badge rounded-pill bg-danger ms-1" style="font-size:.6rem;display:none"></span>
                                <button class="btn btn-sm p-0 px-1 ms-auto border-0" style="font-size:.65rem" title="${h(labels['Clear'])}" onclick="event.stopPropagation();pdConsoleClear()"><span class="bi bi-x-lg"></span></button>
                            </div>
                            <div id="pd_console_body" class="overflow-auto" style="display:none;max-height:200px"></div>
                        </div>
                    </div>
              </div>



              <div class="modal fade" id="regionModal" tabindex="-1" aria-labelledby="regionModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                  <div class="modal-content">
                    <div class="modal-header">
                      <h5 class="modal-title" id="regionModalLabel">${labels["Please choose a region type to create"]}</h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body d-flex flex-wrap flex-column">
                          <a type="button" id="pregionButton" class="btn text-start my-1"><span class="bi text-primary-emphasis bi-window me-2"></span> ${labels["Create Page Region"]}</a>
                          <a type="button" id="cregionButton" class="btn text-start my-1" data-bs-toggle="modal" data-bs-target="#editor_modal_add_designer_region"><span class="bi text-danger-emphasis fs-smaller bi-window-dock me-2"></span> ${labels["Create Designer Region"]}</a>
                          ${output_dynamic_region_button}
                    </div>
                  </div>
                </div>
              </div>
              <div class="modal modal-lg fade" id="editor_modal_add_designer_region" tabindex="-1" aria-labelledby="cregionModalDesignLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                  <div class="modal-content">
                    <div class="modal-header">
                      <h5 class="modal-title" id="cregionModalDesignLabel">${labels["Create Designer Region"]}</h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                          <div class="row">
                              <div class="col-12 col-md-6 my-2">
                                  <label for="designer_region_name" class="form-label">${labels["Designer Region Name"]}</label>
                                  <div class="input-group">
                                      <div class="input-group-text">&#60;cregion&#62;</div><input name="designer_region_name" id="designer_region_name" type="text" class="form-control" maxlength="100" required /><div class="input-group-text">&#60;/cregion&#62;</div>
                                  </div>
                              </div>
                              <h5 class="mt-5">${labels["Shared HTML Content to appear on associated Pages"]}</h5>
                              <div class="col-12 my-2">
                                  <label for="designer_region_code" class="form-label">${labels["HTML Code Snippet"]}</label>
                                  <textarea name="designer_region_code" id="designer_region_code" rows="30" cols="60" wrap="off"></textarea>
                              </div>
                          </div>
                    </div>
                    <div class="modal-footer">
                      <button type="button" id="saveCregionButton" name="submit_create" value="Create Designer Region" class="btn my-1 btn-success " data-loading-content="${labels["Creating"]}"><span class="btn-text">${labels["Create & Add"]}</span></button>
                    </div>
                  </div>
                </div>
              </div>

              <div class="modal modal-lg fade" id="editor_modal_add_dynamic_region" tabindex="-1" aria-labelledby="cregionModalDynamicLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                  <div class="modal-content">
                    <div class="modal-header">
                      <h5 class="modal-title" id="cregionModalDynamicLabel">${labels["Create Dynamic Region"]}</h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                          <div class="row">
                              <div class="col-12 col-md-6 my-2">
                                  <label for="dynamic_region_name" class="form-label">${labels["Dynamic Region Name"]}</label>
                                  <div class="input-group">
                                      <div class="input-group-text">&#60;dregion&#62;</div><input name="dynamic_region_name" id="dynamic_region_name" type="text" class="form-control" maxlength="100" required /><div class="input-group-text">&#60;/dregion&#62;</div>
                                  </div>
                              </div>
                              <h5 class="mt-5">${labels["Shared PHP Content to appear on associated Pages"]}</h5>
                              <div class="col-12 my-2">
                                  <label for="dynamic_region_code" class="form-label">${labels["PHP Code Snippet"]}</label>
                                  <textarea name="dynamic_region_code" id="dynamic_region_code" rows="30" cols="60" wrap="off"></textarea>
                              </div>
                          </div>
                    </div>
                    <div class="modal-footer">
                      <button type="button" id="saveDregionButton" name="submit_create" value="Create Dynamic Region" class="btn my-1 btn-success " data-loading-content="${labels["Creating"]}"><span class="btn-text">${labels["Create & Add"]}</span></button>
                    </div>
                  </div>
                </div>
              </div>


              
              <div class="modal modal-lg fade" id="editor_region_modal" tabindex="-1" aria-hidden="true">
                  <div class="modal-dialog modal-dialog-centered">
                      <div class="modal-content">
                          <div class="modal-header">
                              <h1 class="modal-title fs-5" id="exampleModalLabel">${h(labels['The System Tags'])}</h1>
                              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                          </div>
                          <div class="modal-body overflow-auto " style="white-space: nowrap;">
                              <code clas="text-nowrap"><p class="mb-0">&lt;head&gt;</p>
                                      &nbsp;&nbsp;&nbsp;&nbsp; &lt;meta_tags&gt;&lt;/meta_tags&gt;<br/>
                                      &nbsp;&nbsp;&nbsp;&nbsp; &lt;stylesheet&gt;&lt;/stylesheet&gt;<br/>
                                      &nbsp;&nbsp;&nbsp;&nbsp; &lt;title&gt;&lt;/title&gt;<br/>
                                  <p class="mb-0">&lt;/head&gt;</p>
                                  <p class="mb-0">&lt;body&gt;</p>
                                      &nbsp;&nbsp;&nbsp;&nbsp; &lt;ad&gt;name&lt;/ad&gt;<br/>
                                      &nbsp;&nbsp;&nbsp;&nbsp; &lt;cart&gt;&lt;/cart&gt;<br/>
                                      &nbsp;&nbsp;&nbsp;&nbsp; &lt;cregion&gt;name&lt;/cregion&gt;<br/>
                                      &nbsp;&nbsp;&nbsp;&nbsp; &lt;dregion&gt;name&lt;/dregion&gt;<br/>
                                      &nbsp;&nbsp;&nbsp;&nbsp; &lt;?php code ?&gt;<br/>
                                      &nbsp;&nbsp;&nbsp;&nbsp; &lt;if view-access folder-id="123"&gt;Content if visitor has access.&lt;/if&gt;<br/>
                                      &nbsp;&nbsp;&nbsp;&nbsp; &lt;else&gt;Content if visitor does not have access.&lt;/else&gt;<br/>
                                      &nbsp;&nbsp;&nbsp;&nbsp; &lt;login&gt;name&lt;/login&gt;<br/>
                                      &nbsp;&nbsp;&nbsp;&nbsp; &lt;menu&gt;name&lt;/menu&gt;<br/>
                                      &nbsp;&nbsp;&nbsp;&nbsp; &lt;menu_sequence&gt;name&lt;/menu_sequence&gt;<br/>
                                      &nbsp;&nbsp;&nbsp;&nbsp; &lt;pdf&gt;&lt;/pdf&gt;<br/>
                                      &nbsp;&nbsp;&nbsp;&nbsp; &lt;pregion&gt;&lt;/pregion&gt;<br/>
                                      &nbsp;&nbsp;&nbsp;&nbsp; &lt;system&gt;page name&lt;/system&gt;<br/>
                                      &nbsp;&nbsp;&nbsp;&nbsp; &lt;tcloud&gt;search results page name&lt;/tcloud&gt;<br/>
                                      &nbsp;&nbsp;&nbsp;&nbsp; &lt;link rel="stylesheet" href="/&lt;?=smart_cache('example.css')?&gt;" /&gt;<br/>
                                      &nbsp;&nbsp;&nbsp;&nbsp; &lt;img src="/&lt;?=smart_cache('example.png')?&gt;" /&gt;<br/>
                                      &nbsp;&nbsp;&nbsp;&nbsp; &lt;script src="/&lt;?=smart_cache('example.js')?&gt;"&gt;&lt;/script&gt;<br/>
                                  <p class="mb-0">&lt;/body&gt;</p>
                              </code>
                          </div>
                      </div>
                  </div>
              </div>
          </div>
        </div>
    </main>`);
    const previewPanel = document.getElementById('previewpanel');
    const RegionModalInstance = new bootstrap.Modal('#regionModal', {});
    const CregionModalInstance = new bootstrap.Modal('#editor_modal_add_designer_region', {});
    var DregionModalInstance = '';
    if (dynamic_regions && dynamic_regions === 1) {
        var DregionModalInstance = new bootstrap.Modal('#editor_modal_add_dynamic_region', {});
    }




    const submit_button = $('[name=submit_create]');
    //create codemirror for create designer region area
    const designer_region_CodeMirror = CodeMirror.fromTextArea(document.getElementById('designer_region_code'), {
        mode: 'htmlmixed',
        lineNumbers: true,
        indentUnit: 4,
        theme: 'pastel-on-dark',
        lineWrapping: false,
        styleActiveLine: true,
        readOnly: false,
        viewportMargin: Infinity,
        matchTags: {
            bothTags: true
        },
        autoCloseTags: true,
        autoRefresh: true,
        lint: true,
        gutters: ["CodeMirror-lint-markers"],
        autoCloseBrackets: true
    });
    //create codemirror for create dynamic region area
    const dynamic_region_CodeMirror = CodeMirror.fromTextArea(document.getElementById('dynamic_region_code'), {
        mode: 'php',
        lineNumbers: true,
        indentUnit: 4,
        theme: 'pastel-on-dark',
        lineWrapping: false,
        styleActiveLine: true,
        readOnly: false,
        viewportMargin: Infinity,
        matchTags: {
            bothTags: true
        },
        autoCloseTags: true,
        autoRefresh: true,
        lint: true,
        gutters: ["CodeMirror-lint-markers"],
        autoCloseBrackets: true
    });

    // Region-create wiring is bound once here rather than inside open_item(): the
    // modals, the submit button and the region buttons live outside the editor
    // panel, so re-binding on every open would fire one create request per open.
    $('#editor_modal_add_designer_region').on('hide.bs.modal', function () {
        $('#designer_region_name').val('');
        $('#designer_region_code').val('');
        designer_region_CodeMirror.setValue('');
        submit_button.removeClass('disabled');
        $('[name=designer_region_name]').removeClass('is-invalid');
    });
    if (dynamic_regions && dynamic_regions === 1) {
        $('#editor_modal_add_dynamic_region').on('hide.bs.modal', function () {
            $('#dynamic_region_name').val('');
            $('#dynamic_region_code').val('');
            dynamic_region_CodeMirror.setValue('');
            submit_button.removeClass('disabled');
            $('[name=dynamic_region_name]').removeClass('is-invalid');
        });
    }

    submit_button.click(function () {
        // Bound once at init; the editor variable holds the instance of the item
        // that is currently open, so it may still be empty.
        if (!editor) {
            return false;
        }
        var submit_button_val = $(this).val();
        if (submit_button_val == 'Create Designer Region') {
            $(this).addClass('disabled');
            var name = $('[name=designer_region_name]').val();
            //if name is empty focus name input
            if (name == '') {
                $('[name=designer_region_name]').addClass('is-invalid').focus();
                $(this).removeClass('disabled');
                return false;
            }


            //get content and create region with informations.
            var content = designer_region_CodeMirror.getValue();
            var data = {
                action: 'create_design_region',
                token: software_token,
                designer_region: {
                    name: name,
                    content: content
                }
            }
            $.ajax({
                contentType: 'application/json',
                url: 'api.php',
                data: JSON.stringify(data),
                type: 'POST',
                success: function (response) {
                    if (name) {
                        var doc = editor.getDoc();
                        var cursor = doc.getCursor();
                        doc.replaceRange('<cregion>' + name + '</cregion>', cursor);
                        RegionModalInstance.hide();
                        CregionModalInstance.hide();
                        if (dynamic_regions && dynamic_regions === 1) {
                            DregionModalInstance.hide();
                        }

                        editor.focus();
                    }


                }
            });

        }

        if (submit_button_val == 'Create Dynamic Region') {
            $(this).addClass('disabled');
            var name = $('[name=dynamic_region_name]').val();
            //if name is empty focus name input
            if (name == '') {
                $('[name=dynamic_region_name]').addClass('is-invalid').focus();
                $(this).removeClass('disabled');
                return false;
            }


            //get content and create region with informations.
            var code = dynamic_region_CodeMirror.getValue();
            var data = {
                action: 'create_dynamic_region',
                token: software_token,
                dynamic_region: {
                    name: name,
                    code: code
                }
            }
            $.ajax({
                contentType: 'application/json',
                url: 'api.php',
                data: JSON.stringify(data),
                type: 'POST',
                success: function (response) {
                    if (name) {
                        var doc = editor.getDoc();
                        var cursor = doc.getCursor();
                        doc.replaceRange('<dregion>' + name + '</dregion>', cursor);
                        RegionModalInstance.hide();
                        CregionModalInstance.hide();
                        DregionModalInstance.hide();
                        editor.focus();
                    }


                }
            });

        }

    });

    document.getElementById('cregionButton').addEventListener('click', function () {
        CregionModalInstance.show();
    });

    if (dynamic_regions && dynamic_regions === 1) {
        document.getElementById('dregionButton').addEventListener('click', function () {
            DregionModalInstance.show();
        });
    }

    // Persist the preview panel width and height.
    const saveToLocalStorage = (width, height) => {
        localStorage.setItem('previewWidth', width);
        localStorage.setItem('previewHeight', height);
    };

    // Read the device type from localStorage.
    const getDeviceTypeFromLocalStorage = () => {
        return localStorage.getItem('deviceType') || 'mobile';
    };

    // Persist the device type.
    const saveDeviceTypeToLocalStorage = (deviceType) => {
        localStorage.setItem('deviceType', deviceType);
    };

    // Load the device type from localStorage and apply its class.
    const loadDeviceType = () => {
        const deviceType = getDeviceTypeFromLocalStorage();
        $('#previewpanel').addClass(`${deviceType}-device`);
    };

    // Load the saved preview size from localStorage and apply it.
    const loadPreviewSizeFromLocalStorage = () => {
        const savedWidth = localStorage.getItem('previewWidth');
        const savedHeight = localStorage.getItem('previewHeight');

        if (savedWidth && savedHeight) {
            previewPanel.style.width = `${savedWidth}px`;
            previewPanel.style.height = `${savedHeight}px`;
        } else {
            previewPanel.style.width = `500px`;
            previewPanel.style.height = `900px`;

        }
    };

    // Restore the device type and the preview size on page load.
    document.addEventListener('DOMContentLoaded', () => {
        loadDeviceType();
        loadPreviewSizeFromLocalStorage();
        resizeObserver.observe(previewPanel);
    });

    // Compute the scaled screen size and write it into #previewpanelsize.
    const updatePreviewPanelSize = (width, height) => {
        let scaleSize = 1;
        if ($('#previewpanel').hasClass('mobile-device')) scaleSize = 1;
        else if ($('#previewpanel').hasClass('tablet-device')) scaleSize = 0.6666666666;
        else if ($('#previewpanel').hasClass('laptop-device')) scaleSize = 0.5;
        else if ($('#previewpanel').hasClass('wide-device')) scaleSize = 0.3333333333;
        else if ($('#previewpanel').hasClass('desktop-device')) scaleSize = 0.364;
        else if ($('#previewpanel').hasClass('wider-device')) scaleSize = 0.1666666667;

        const actualWidth = Math.round(width / scaleSize);
        const actualHeight = Math.round(height / scaleSize);
        $('#previewpanelsize').text(`${actualWidth}px * ${actualHeight}px`);

        if (actualWidth >= 992) {
            $('#previewpanel').addClass('desktop-size').removeClass('mobile-size');
        } else {
            $('#previewpanel').addClass('mobile-size').removeClass('desktop-size');
        }
    };

    // ResizeObserver that persists the size and refreshes the size badge.
    const resizeObserver = new ResizeObserver(() => {
        const newWidth = previewPanel.offsetWidth;
        const newHeight = previewPanel.offsetHeight;

        // Save the size in pixels.
        saveToLocalStorage(newWidth, newHeight);

        // Refresh the scaled screen size.
        updatePreviewPanelSize(newWidth, newHeight);
    });

    $('[devicetype-set]').click(function () {
        var deviceType = $(this).attr('devicetype-set');
        var width = 0, height = 0;

        switch (deviceType) {
            case 'mobile':
                width = 500;
                height = (500 * 16 / 9); // 9:16 aspect ratio
                $('#previewpanel')
                    .addClass('mobile-device')
                    .removeClass('tablet-device laptop-device wide-device desktop-device wider-device');
                break;
            case 'tablet':
                width = 500;
                height = (500 * 4 / 3);
                $('#previewpanel')
                    .addClass('tablet-device')
                    .removeClass('mobile-device laptop-device wide-device desktop-device wider-device');
                break;
            case 'laptop':
                width = 600;
                height = (600 * 9 / 16);
                $('#previewpanel')
                    .addClass('laptop-device')
                    .removeClass('mobile-device tablet-device wide-device desktop-device wider-device');
                break;
            case 'wide':
                width = 600;
                height = (600 * 9 / 16);
                $('#previewpanel')
                    .addClass('wide-device')
                    .removeClass('mobile-device tablet-device laptop-device desktop-device wider-device');
                break;
            case 'desktop':
                width = 700;
                height = (700 * 9 / 16);
                $('#previewpanel')
                    .addClass('desktop-device')
                    .removeClass('mobile-device tablet-device laptop-device wide-device wider-device');
                break;
            case 'wider':
                width = 700;
                height = (700 * 9 / 16);
                $('#previewpanel')
                    .addClass('wider-device')
                    .removeClass('mobile-device tablet-device laptop-device wide-device desktop-device');
                break;
        }


        previewPanel.style.width = width + 'px';
        previewPanel.style.height = height + 'px';

        // Persist the device type.
        saveDeviceTypeToLocalStorage(deviceType);

        // Refresh the scaled screen size.
        updatePreviewPanelSize(width, height);
    });




    init_preview_check();

    function init_preview_check() {

        var first_load = true;
        // When a url is loaded in the preview iframe, then do various things.
        $('#preview').on('load', function () {
            // Clear console on every new navigation.
            pdConsoleClear();

            // If the iframe contains a page at this site and not
            // some other external site, then we have access to it,
            // so continue.
            if (check_iframe_access(this) == true) {

                var preview_iframe = $('#preview');
                var iframeDocument = this.contentDocument || this.contentWindow.document;
                // ── Inject console interceptors into preview iframe ──────────
                (function (iw) {
                    // Format specifier handler (%c stripped, %s/%d/%f substituted)
                    function _fmt(a) {
                        if (!a.length) return '';
                        var s = a[0], i = 1, r;
                        if (typeof s !== 'string' || !/%(c|s|d|i|f|o|O)/.test(s)) {
                            return Array.prototype.slice.call(a).map(function (x) {
                                try { return typeof x === 'object' ? JSON.stringify(x) : String(x); } catch (e) { return String(x); }
                            }).join(' ');
                        }
                        r = s.replace(/%([csdifOo%])/g, function (m, t) {
                            if (t === '%') return '%';
                            if (i >= a.length) return m;
                            var v = a[i++];
                            if (t === 'c') return '';
                            if (t === 's') return String(v);
                            if (t === 'd' || t === 'i') return parseInt(v, 10);
                            if (t === 'f') return parseFloat(v);
                            try { return typeof v === 'object' ? JSON.stringify(v) : String(v); } catch (e) { return String(v); }
                        });
                        while (i < a.length) {
                            try { r += ' ' + (typeof a[i] === 'object' ? JSON.stringify(a[i]) : String(a[i])); } catch (e) { r += ' ' + String(a[i]); } i++;
                        }
                        return r;
                    }
                    // console.error / warn / log / info / debug
                    ['error', 'warn', 'log', 'info', 'debug'].forEach(function (m) {
                        var _o = iw.console[m].bind(iw.console);
                        iw.console[m] = function () {
                            pdConsoleAdd(m === 'debug' ? 'log' : m, _fmt(arguments));
                            _o.apply(null, arguments);
                        };
                    });
                    // console.assert → error when condition is falsy
                    var _oa = iw.console.assert.bind(iw.console);
                    iw.console.assert = function (cond) {
                        if (!cond) {
                            var rest = Array.prototype.slice.call(arguments, 1);
                            pdConsoleAdd('error', 'Assertion failed: ' + (rest.length ? _fmt(rest) : '(no message)'));
                        }
                        _oa.apply(null, arguments);
                    };
                    // JS runtime / syntax errors + resource load failures
                    iw.addEventListener('error', function (e) {
                        if (e.message !== undefined) {
                            var src = e.filename ? e.filename.replace(/^.*[\\/]/, '') : '';
                            pdConsoleAdd('error', (e.message || 'Error') + (src ? ' [' + src + ':' + e.lineno + ']' : ''));
                        } else {
                            var el = e.target || e.srcElement;
                            var url = (el && (el.src || el.href || el.currentSrc)) || '';
                            var tag = el && el.tagName ? el.tagName.toLowerCase() : 'resource';
                            pdConsoleAdd('error', 'Failed to load ' + tag + (url ? ': ' + url : ''));
                        }
                    }, true);
                    // Unhandled promise rejections
                    iw.addEventListener('unhandledrejection', function (e) {
                        var r = e.reason;
                        pdConsoleAdd('error', 'Unhandled Promise: ' + (r && r.message ? r.message : String(r)));
                    });
                    // XHR — status >= 400 or network error (status 0)
                    if (iw.XMLHttpRequest) {
                        var _xo = iw.XMLHttpRequest.prototype.open;
                        var _xs = iw.XMLHttpRequest.prototype.send;
                        iw.XMLHttpRequest.prototype.open = function (method, url) {
                            this._pdM = method; this._pdU = url;
                            return _xo.apply(this, arguments);
                        };
                        iw.XMLHttpRequest.prototype.send = function () {
                            var x = this;
                            x.addEventListener('loadend', function () {
                                if (x.status >= 400) {
                                    pdConsoleAdd('error', 'XHR ' + (x._pdM || 'GET') + ' ' + (x._pdU || '') + ' → ' + x.status);
                                } else if (x.status === 0 && x.readyState === 4) {
                                    pdConsoleAdd('error', 'XHR network error: ' + (x._pdU || ''));
                                }
                            });
                            return _xs.apply(this, arguments);
                        };
                    }
                    // fetch — non-ok responses + network failures
                    if (iw.fetch) {
                        var _ff = iw.fetch;
                        iw.fetch = function (input, init) {
                            var url = typeof input === 'string' ? input : (input && input.url) || String(input);
                            var method = (init && init.method) || 'GET';
                            return _ff.apply(iw, arguments).then(function (res) {
                                if (!res.ok) {
                                    pdConsoleAdd('error', 'Fetch ' + method + ' ' + url + ' → ' + res.status + ' ' + res.statusText);
                                }
                                return res;
                            }, function (err) {
                                pdConsoleAdd('error', 'Fetch failed: ' + url + ' — ' + (err && err.message ? err.message : String(err)));
                                throw err;
                            });
                        };
                    }
                })(this.contentWindow);
                // Create a style element that hides the Pinegrap button inside the preview.
                $('<style>', iframeDocument).prop({
                    type: 'text/css',
                    innerHTML: '#software_pinegrap_button_container { display: none !important; }'
                }).appendTo($(iframeDocument.head));

                page = preview_iframe[0].contentWindow.software_page;
                var preview_path = preview_iframe[0].contentWindow.location.pathname;
                // Get the length of software directory path (e.g. /pinegrap/)
                // so that we can check if preview panel path is a backend path.
                var backend_path_length = path.length + software_directory.length + 1;
                // If page info could not be found in the source of the page,
                // and a backend screen was not loaded in the preview panel,
                // then try to figure out the page from the preview panel path.
                if (
                    (page === undefined) &&
                    (preview_path.substring(0, backend_path_length) != (path + software_directory + '/'))
                ) {
                    // Get the item name without the path on the front,
                    // so we can look for a page with that name.
                    page = {
                        name: preview_path.substring(path.length)
                    };
                }
                var toolbar_iframe = preview_iframe.contents().find('#software_toolbar');
                // If the toolbar iframe was found in the preview window,
                // then that means the visitor is previewing a front-end page,
                // so continue.
                if (toolbar_iframe.length) {
                    // If this is the first page that is being loaded in the iframe,
                    // for the intial page designer load, and the toolbar is visible,
                    // then hide the toolbar.
                    if (first_load && toolbar_iframe.is(':visible')) {
                        preview_iframe.contents().find('#software_fullscreen_toggle').trigger('click');
                    }
                    // The button is on the page itself now, in the panel behind
                    // the SEO ring, not inside the toolbar iframe. Its text is
                    // read rather than guessed from an icon, so the text has to
                    // turn around with the title.
                    var page_designer_button = preview_iframe.contents().find('.page_designer_button');
                    page_designer_button.removeClass('closed');
                    page_designer_button.addClass('btn-danger open');
                    page_designer_button.text(labels['Close Page Designer']);
                    page_designer_button.attr('title', labels['Close Page Designer'] + ' (Ctrl+G | \u2318+G)');
                    page_designer_button.click(function (event) {
                        event.preventDefault();
                        close();
                    });
                }
                // If a front-end page appears in the preview panel,
                // then get info about page and then update tools panel.

                if (page) {
                    $.ajax({
                        contentType: 'application/json',
                        url: 'api.php',
                        data: JSON.stringify({
                            action: 'get_page',
                            page: page,
                        }),
                        type: 'POST',
                        success: function (response) {

                            page = response.page;
                            var page_link = $('.tool .pagelink,.pagelink');

                            page_link.empty();
                            if (page) {
                                $('#previewpaneltitle').text(labels['Preview'] + ' | ' + h(page.name));
                                page_link.html('<button title="' + labels['Refresh Page'] + ': ' + h(page.name) + '" class="fs-smaller px-1 py-0 btn btn-sm bi bi-arrow-clockwise"></button>');
                                var location = preview_iframe.contents().get(0).location;
                                url = location.pathname + location.search;
                                // Remove any previous click events.
                                page_link.unbind('click');
                                // Update preview iframe with url when page link is clicked.
                                page_link.click(function () {
                                    preview_iframe.attr('src', url);
                                });
                            } else {
                                page_link.html('<button title="' + labels['No Page'] + '"  style="cursor:default;" class="position-relative btn-sm  bg-transparent border border-2 border-auto-color rounded-pill p-1 m-1 material-icons pages-color">refresh<span class="position-absolute top-0 start-100 translate-middle material-icons h6 badge p-0 bg-danger rounded fw-light">priority_high</span></button>');
                                // Remove any previous click events.
                                page_link.unbind('click');
                            }
                            var layout_link = $('.tool .layout');
                            layout_link.empty();
                            layout_link.unbind('click');
                            var style = preview_iframe[0].contentWindow.software_style;
                            var layout_type = '';
                            if (style && style.layout_type) {
                                layout_type = style.layout_type;
                            } else if (page) {
                                layout_type = page.layout_type;
                            }
                            // If this page supports a custom layout,
                            // and it has a custom layout, then show layout link.
                            if (
                                page &&
                                check_if_page_type_supports_layout(page.type) &&
                                (layout_type == 'custom')
                            ) {
                                layout_link.html('<li><div class="d-flex lh-2"><a id="page_designer_page_id_' + page.id + '" href="#!" title="' + labels['Layout'] + '" class="btn btn-link link-body-emphasis text-decoration-none py-0 ps-1 page_designer_item"><span class="bi text-success-emphasis bi-file-earmark-code me-1"></span>' + labels['Layout'] + '</span></a></div></li>');
                                // Store the page id so that if a different URL is loaded in the preview panel,
                                // that might not have a page (e.g. backend screen), then it won't break layout click event.
                                var page_id = page.id;
                                // Open the layout in the code panel when the layout link is clicked.
                                layout_link.click(function () {
                                    open_item({
                                        type: 'layout',
                                        id: page_id,
                                        active: 'page_designer_page_id_' + page.id
                                    });
                                });
                            }
                            if (style) {
                                var new_style_id = style.id;
                            }
                            // If this is the first time that the page designer is loading,
                            // and an item type was passed in the query string,
                            // then load that item.
                            if ((code_type == '') && (starting_item_type != '')) {
                                open_item({
                                    type: starting_item_type,
                                    id: starting_item_id
                                });
                                // Otherwise if this is the first time the page designer is loading or there
                                // is a style in the code panel and there are not unsaved changes,
                                // and if the style for the page in the preview iframe is different
                                // from the last time the preview iframe was loaded,
                                // then open the style in the code panel.
                            } else if (
                                (
                                    (code_type == '') ||
                                    (code_type == 'style')
                                ) &&
                                (content_changed == false) &&
                                (style) &&
                                (new_style_id != style_id)
                            ) {
                                open_item({
                                    type: 'style',
                                    id: style.id
                                });
                                // Otherwise if there is a layout in the code panel,
                                // and there are no unsaved changes to that layout,
                                // and the preview page has a custom layout,
                                // and the new layout is different from the layout
                                // that is already open, then open the layout in the code panel.
                            } else if (
                                (code_type == 'layout') &&
                                (content_changed == false) &&
                                page &&
                                check_if_page_type_supports_layout(page.type) &&
                                (layout_type == 'custom') &&
                                (page.id != code_id)
                            ) {
                                open_item({
                                    type: 'layout',
                                    id: page.id
                                });
                                // Otherwise, no item was opened, so we need to update the URL in the address bar,
                                // so that if the user refreshes the page, then the correct URL will be opened,
                                // because the open_item() function did not do it for us.
                            } else {
                                history.replaceState({}, null,
                                    path + software_directory + '/page_designer.php?url=' + encodeURIComponent(url) + '&type=' + code_type + '&id=' + code_id);
                            }
                            var style_link = $('.tool .style');
                            style_link.empty();
                            if (style) {
                                style_link.html('<div class="d-flex lh-2"><a id="page_designer_style_id_' + style.id + '" href="#!" title="' + labels['Set in Page Properties'] + ' | ' + h(style.name) + '" class="btn btn-link link-body-emphasis text-decoration-none py-0 ps-1 page_designer_item"><span class="bi text-primary-emphasis bi-window me-2"></span>' + h(style.name) + '</span></a></div>');
                                // Remove any previous click events.
                                style_link.unbind('click');
                                // Open the style in the code panel when the style link is clicked.
                                style_link.click(function () {
                                    open_item({
                                        type: 'style',
                                        id: style.id,
                                        active: 'page_designer_style_id_' + style.id
                                    });
                                });
                            } else {
                                style_link.html('<div class="d-flex lh-2"><a href="#!" title="' + labels['no page style'] + '" class="btn btn-link link-body-emphasis text-decoration-none py-0 ps-1 page_designer_item">' + labels['no page style'] + '</span></a></div>');
                                // Remove any previous click events.
                                style_link.unbind('click');
                            }
                            var theme_link = $('.tool .theme');
                            theme_link.empty();
                            var theme = preview_iframe[0].contentWindow.software_theme;
                            // If a theme was outputted for <stylesheet></stylesheet> tags,
                            // then add theme link to tool panel.
                            if (theme) {


                                theme_link.html('<li><div class="d-flex lh-2"><a id="page_designer_theme_id_' + theme.id + '"  href="#!" title="&lt;stylesheet&gt;&lt;/stylesheet&gt; | ' + h(theme.name) + '" class="btn btn-link link-body-emphasis text-decoration-none py-0 ps-1 page_designer_item"><span class="bi text-danger-emphasis bi-palette2 me-2 fs-smaller"></span>' + h(theme.name) + '</span></a></div></li>')
                                // Remove any previous click events.
                                theme_link.unbind('click');
                                // Open the theme in the code panel when the theme link is clicked.
                                theme_link.click(function () {
                                    open_item({
                                        type: 'css',
                                        id: theme.id,
                                        active: 'page_designer_theme_id_' + theme.id

                                    });
                                });
                            } else {
                                theme_link.html('<li><div class="d-flex lh-2"><a href="#!" style="cursor:default;"  title="&lt;stylesheet&gt;&lt;/stylesheet&gt; | ' + labels['no theme'] + '" class="btn btn-link link-body-emphasis text-decoration-none py-0 ps-1 page_designer_item"><span class="bi text-danger-emphasis bi-palette2 me-2 fs-smaller"></span>' + labels['no theme'] + '</span></a></div></li>')

                                // Remove any previous click events.
                                theme_link.unbind('click');
                            }

                            $('.related_items').empty();
                            if (style) {
                                // Get related items by looking at the style content.
                                $.ajax({
                                    contentType: 'application/json',
                                    url: 'api.php',
                                    data: JSON.stringify({
                                        action: 'get_items_in_style',
                                        style_id: style.id
                                    }),
                                    type: 'POST',
                                    success: function (response) {
                                        var style_design_files = response.design_files;
                                        var style_designer_regions = response.designer_regions;
                                        var style_dynamic_regions = response.dynamic_regions;
                                        var style_system_regions = response.system_regions;
                                        // If there is at least one result for designer regions, then output it/them.
                                        if (style_designer_regions.length > 0) {
                                            $.each(style_designer_regions, function (index, designer_region) {
                                                var item_id_for_active_classes = 'page_designer_designer_region_id_' + designer_region.id;
                                                var related_item = $('<li><a id="' + item_id_for_active_classes + '" href="#!" title="' + h('<cregion>' + designer_region.name + '</cregion>') + '" class="btn btn-link link-body-emphasis text-decoration-none py-0 ps-1 page_designer_item"><span class="bi text-danger-emphasis fs-smaller bi-window-dock me-2"></span>' + h(designer_region.name) + '</span></a></li>');
                                                related_item.click(function () {
                                                    open_item({
                                                        type: 'designer_region',
                                                        id: designer_region.id,
                                                        active: item_id_for_active_classes
                                                    });
                                                });
                                                $('.related_items').append(related_item);
                                            });
                                        }
                                        // If there is at least one result for dynamic regions, then output it/them.
                                        if (style_dynamic_regions.length > 0) {
                                            $.each(style_dynamic_regions, function (index, dynamic_region) {
                                                var item_id_for_active_classes = 'page_designer_dynamic_region_id_' + dynamic_region.id;
                                                var related_item = $('<li><a id="' + item_id_for_active_classes + '" href="#!" title="' + h('<dregion>' + dynamic_region.name + '</dregion>') + '" class="btn btn-link link-body-emphasis text-decoration-none py-0 ps-1 page_designer_item"><span class="bi text-warning-emphasis bi-filetype-php me-2"></span>' + h(dynamic_region.name) + '</span></a></li>');
                                                related_item.click(function () {
                                                    open_item({
                                                        type: 'dynamic_region',
                                                        id: dynamic_region.id,
                                                        active: item_id_for_active_classes
                                                    });
                                                });
                                                $('.related_items').append(related_item);
                                            });
                                        }
                                        // If there is at least one result for system regions, then output it/them.
                                        if (style_system_regions.length > 0) {
                                            $.each(style_system_regions, function (index, system_region) {
                                                var related_item = $('<li><a href="#!" title="' + h('<cregion>' + system_region.name + '</cregion>') + '" class="btn btn-link link-body-emphasis text-decoration-none py-0 ps-1 page_designer_item"><span class="bi text-primary-emphasis bi-filetype-html me-2"></span>' + h(system_region.name) + '</span></a></li>');
                                                related_item.click(function () {
                                                    preview_iframe.attr('src', path + encodeURI(system_region.name));
                                                });
                                                $('.related_items').append(related_item);
                                            });
                                        }
                                        // If there is at least one result for design files, then output it/them.
                                        if (style_design_files.length > 0) {
                                            var number_of_items = 0;
                                            $.each(style_design_files, function (index, design_file) {


                                                // If this is not a theme, or it is a theme and it is a custom theme,
                                                // then continue to output result.
                                                if ((design_file.theme == 0) || (design_file.theme_type == 'custom')) {
                                                    number_of_items++;

                                                    var design_file_icon = '';
                                                    switch (design_file.type) {
                                                        case 'css':
                                                            design_file_icon = 'bi-filetype-css';
                                                            break;
                                                        case 'js':
                                                            design_file_icon = 'bi-filetype-js';
                                                            break;
                                                        default:
                                                            design_file_icon = 'bi-file-earmark';
                                                    }



                                                    if (design_file.type == 'css') {
                                                        a_title = 'title="' + h('<link href="{path}' + encodeURI(design_file.name) + '" rel="stylesheet" type="text/css">') + '"';
                                                    } else {
                                                        a_title = 'title="' + h('<script src="{path}' + encodeURI(design_file.name) + '" rel="javascript" type="text/javascript"></script>') + '"';
                                                    }
                                                    var item_id_for_active_classes = 'page_designer_design_file_id_' + design_file.id;
                                                    var related_item = $('<li><a id="' + item_id_for_active_classes + '" href="#!" ' + a_title + ' class="btn btn-link link-body-emphasis text-decoration-none py-0 ps-1 page_designer_item"><span class="bi text-info-emphasis ' + design_file_icon + ' me-2"></span>' + h(design_file.name) + '</span></a></li>');
                                                    related_item.click(function () {
                                                        open_item({
                                                            type: design_file.type,
                                                            id: design_file.id,
                                                            active: item_id_for_active_classes
                                                        });
                                                    });
                                                    $('.related_items').append(related_item);
                                                }
                                            });
                                        }

                                    }
                                });
                            }
                        }
                    });
                }
            }
            first_load = false;
        });
    }

    // ── Preview Console ──────────────────────────────────────────────────────
    var _pdConsoleOpen = false;
    var _pdConsoleCount = 0;

    function _pdConsoleSetExpanded(open) {
        var panel = document.getElementById('console_errors');
        var body = document.getElementById('pd_console_body');
        if (!panel || !body) { return; }
        _pdConsoleOpen = open;
        body.style.display = open ? '' : 'none';
        panel.style.flexGrow = open ? '1' : '0';
        panel.style.flexShrink = open ? '1' : '0';
        if (open) { body.scrollTop = body.scrollHeight; }
    }

    window.pdConsoleAdd = function (type, message) {
        _pdConsoleCount++;
        var panel = document.getElementById('console_errors');
        var body = document.getElementById('pd_console_body');
        var badge = document.getElementById('pd_console_badge');
        if (!panel) { return; }
        panel.style.display = '';
        badge.textContent = _pdConsoleCount;
        badge.style.display = '';
        var entry = document.createElement('div');
        entry.className = 'pd-ce pd-ce-' + type;
        entry.textContent = message;
        body.appendChild(entry);
        // Auto-open on first message
        if (!_pdConsoleOpen) { _pdConsoleSetExpanded(true); }
        else { body.scrollTop = body.scrollHeight; }
    };

    window.pdConsoleClear = function () {
        _pdConsoleCount = 0;
        var panel = document.getElementById('console_errors');
        var body = document.getElementById('pd_console_body');
        var badge = document.getElementById('pd_console_badge');
        if (!panel) { return; }
        body.innerHTML = '';
        badge.textContent = '';
        badge.style.display = 'none';
        panel.style.display = 'none';
        _pdConsoleSetExpanded(false);
    };

    window.pdConsoleToggle = function () {
        _pdConsoleSetExpanded(!_pdConsoleOpen);
    };

    // Replay any messages queued before pdConsoleAdd was available.
    if (typeof window._pdConsoleFlushQueue === 'function') {
        window._pdConsoleFlushQueue();
    }
    // ────────────────────────────────────────────────────────────────────────





    $('#EnableSearch').removeClass('d-none');
    let typingTimer;
    let doneTypingInterval = 300;
    let $queryinput = $('form.search_form input[name="query"]');
    $('#SearchBox').on('shown.bs.modal', function () {
        $queryinput.focus();
    });
    $('#SearchBox').on('hide.bs.modal', function () {
        //$('form.search_form .clear').click();
    });

    $queryinput.on('input change paste', function () {
        clearTimeout(typingTimer);
        if ($queryinput.val().length === 0) {
            $('form.search_form .clear').click();
        }

        typingTimer = setTimeout(doneTyping, doneTypingInterval);
    });

    $queryinput.on('keydown', function () {
        clearTimeout(typingTimer);

    });

    function doneTyping() {
        $('form.search_form').submit();
    }

    $('form.search_form').submit(function (event) {
        event.preventDefault();
        new_query = $('.query').val();

        if (new_query == '') {
            return false;
        }
        $('.search_results_empty_box').addClass('d-none');
        query = new_query;
        $('form.search_form .clear').removeClass('d-none');
        $('form.search_form .clear').on('click', function (event) {
            query = '';
            $('.query').val('');
            $('.query').focus();
            $('#search_results *').remove();
            $('#search_results').removeClass('show');
            $('.search_results_empty_box').removeClass('d-none');
            $('form.search_form .clear').addClass('d-none');


        });
        var styles;
        var design_files;
        var designer_regions;
        var dynamic_regions;

        // Send multiple AJAX requests in order to search multiple types of items.
        $.when(
            $.ajax({
                contentType: 'application/json',
                url: 'api.php',
                data: JSON.stringify({
                    action: 'get_styles',
                    type: 'custom',
                    search: query
                }),
                type: 'POST',
                success: function (response) {
                    styles = response.styles;
                }
            }),

            $.ajax({
                contentType: 'application/json',
                url: 'api.php',
                data: JSON.stringify({
                    action: 'get_design_files',
                    types: ['css', 'js'],
                    theme_type: 'custom',
                    search: query
                }),
                type: 'POST',
                success: function (response) {
                    design_files = response.design_files;
                }
            }),

            $.ajax({
                contentType: 'application/json',
                url: 'api.php',
                data: JSON.stringify({
                    action: 'get_designer_regions',
                    search: query
                }),
                type: 'POST',
                success: function (response) {
                    designer_regions = response.designer_regions;
                }
            }),

            $.ajax({
                contentType: 'application/json',
                url: 'api.php',
                data: JSON.stringify({
                    action: 'get_dynamic_regions',
                    search: query
                }),
                type: 'POST',
                success: function (response) {
                    dynamic_regions = response.dynamic_regions;
                }
            })

            // Once all of the AJAX requests are complete, then output search results.
        ).then(function () {
            $('#search_results *').remove();
            $('#search_results').addClass('show').append('<p class="fs-smaller">' + labels['Search Results'] + '</p>');

            // If there is at least one result for styles, then output it/them.
            if (styles.length > 0) {
                $.each(styles, function (index, style) {
                    var search_result = $('<button type="button" title="' + labels['Set in Page Properties'] + ' | ' + h(style.name) + '" class="btn btn-link d-flex text-nowrap w-100  link-body-emphasis text-decoration-none py-0 ps-1 page_designer_item page_style"><span class="bi text-primary-emphasis bi-window me-2"></span>' + h(style.name) + '</button>');
                    search_result.click(function () {
                        open_item({
                            type: 'style',
                            id: style.id
                        });
                    });

                    $('#search_results').append(search_result);
                });
            }

            // If there is at least one result for designer regions, then output it/them.
            if (designer_regions.length > 0) {
                $.each(designer_regions, function (index, designer_region) {
                    var search_result = $('<button title="' + h('<cregion>' + designer_region.name + '</cregion>') + '" class="btn btn-link d-flex text-nowrap w-100  link-body-emphasis text-decoration-none py-0 ps-1 page_designer_item"><span class="bi text-danger-emphasis fs-smaller bi-window-dock me-2"></span>' + h(designer_region.name) + '</button>');
                    search_result.click(function () {
                        open_item({
                            type: 'designer_region',
                            id: designer_region.id
                        });
                    });

                    $('#search_results').append(search_result);
                });
            }

            // If there is at least one result for dynamic regions, then output it/them.
            if (dynamic_regions.length > 0) {
                $.each(dynamic_regions, function (index, dynamic_region) {
                    var search_result = $('<button class="btn btn-link d-flex text-nowrap w-100  link-body-emphasis text-decoration-none py-0 ps-1 page_designer_item" title="' + h('<dregion>' + dynamic_region.name + '</dregion>') + '"><span class="bi text-warning-emphasis bi-filetype-php me-2"></span>' + h(dynamic_region.name) + '</button>');
                    search_result.click(function () {
                        open_item({
                            type: 'dynamic_region',
                            id: dynamic_region.id
                        });
                    });
                    $('#search_results').append(search_result);
                });
            }

            // If there is at least one result for design files, then output it/them.
            if (design_files.length > 0) {
                $.each(design_files, function (index, design_file) {

                    var design_file_icon = '';
                    switch (design_file.type) {
                        case 'css':
                            design_file_icon = 'bi-filetype-css';
                            break;
                        case 'js':
                            design_file_icon = 'bi-filetype-js';
                            break;
                        default:
                            design_file_icon = 'bi-file-earmark';
                    }

                    if (design_file.type == 'css') {
                        a_title = 'title="' + h('<link href="{path}' + encodeURI(design_file.name) + '" rel="stylesheet" type="text/css">') + '"';
                    } else {
                        a_title = 'title="' + h('<script src="{path}' + encodeURI(design_file.name) + '" rel="javascript" type="text/javascript"></script>') + '"';
                    }

                    var search_result = $('<button ' + a_title + ' class="btn btn-link d-flex text-nowrap w-100  link-body-emphasis text-decoration-none py-0 ps-1 page_designer_item"><span class="bi text-info-emphasis ' + design_file_icon + ' me-2"></span>' + h(design_file.name) + '</button>');
                    search_result.click(function () {
                        open_item({
                            type: design_file.type,
                            id: design_file.id
                        });
                    });

                    $('#search_results').append(search_result);
                });
            }



            if ((styles.length === 0) && (designer_regions.length === 0) && (dynamic_regions.length === 0) && (design_files.length === 0)) {
                $('#search_results').append('<p class="w-100  my-2 text-danger">' + labels['No Search Result'] + '.</p>');
            }

            $('#search_results button').click(function () {
                $('#SearchBox').modal('hide');
            });



        });
    });


    function close() {
        // If the iframe contains a page on this website,
        // (i.e. we have access to knowing what the URL is)
        // then get iframe location.
        if (check_iframe_access(document.getElementById('preview')) == true) {
            var location = $('#preview').contents().get(0).location.href;

            // Otherwise the iframe contains a page from a different website,
            // so we don't have access to knowing the location,
            // so use the last url at this site.
        } else {
            var location = url;
        }
        window.location.href = location;
    }

    // Add keyboard shortcuts.
    $(window).bind('keydown', function (event) {
        if (event.ctrlKey || event.metaKey) {
            switch (String.fromCharCode(event.which).toLowerCase()) {
                case 'd':
                    event.preventDefault();

                    var fullscreen_toggle = $('#preview').contents().find('#software_fullscreen_toggle');

                    if (fullscreen_toggle.length) {
                        fullscreen_toggle.trigger('click');
                    } else {
                        event.preventDefault();
                        $('#menu,#menu-toggle').toggleClass('active');
                    }

                    break;
                case 'g':
                    event.preventDefault();
                    close();
                    break;
                case 'e':
                    event.preventDefault();

                    var grid_toggle = $('#preview').contents().find('#grid_toggle');

                    if (grid_toggle.length) {
                        grid_toggle.trigger('click');
                    }

                    break;

                case 's':
                    event.preventDefault();
                    $('#editorpanel .save').trigger('click');
                    break;
            }
        }
    });

    $(window).on('beforeunload', function () {

        $.ajax({
            contentType: 'application/json',
            url: 'api.php',
            data: JSON.stringify({
                action: 'update_page_designer_properties',
                token: software_token,
                query: query
            }),
            type: 'POST',
            async: false
        })

        if (content_changed == true) {
            return labels['WARNING: If you leave this page, then your unsaved changes will be lost.'];
        }

    });

}
function check_if_page_type_supports_layout(page_type) {
    switch (page_type) {
        case 'billing information':
        case 'catalog':
        case 'catalog detail':
        case 'change password':
        case 'set password':
        case 'custom form':
        case 'email preferences':
        case 'express order':
        case 'forgot password':
        case 'form item view':
        case 'form list view':
        case 'login':
        case 'membership entrance':
        case 'my account':
        case 'my account profile':
        case 'view order':
        case 'order form':
        case 'order preview':
        case 'order receipt':
        case 'photo gallery':
        case 'registration entrance':
        case 'search results':
        case 'shipping address and arrival':
        case 'shipping method':
        case 'shopping cart':
        case 'update address book':
            return true;
            break;

        default:
            return false;
            break;
    }
}