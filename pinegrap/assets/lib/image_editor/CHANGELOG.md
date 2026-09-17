# Changelog

## 7.2.2 | 2021-02-12

-   Improve Vue2 components so attribute updates are caught correctly.
-   Improve crop rectangle size calculations when switching between aspect ratios.
-   Improve NextJS project set up to use CSS modules.
-   Improve rendering of shape selection when switching view while a shape is selected.
-   Add press escape key to close Doka Image Editor modal.
-   Add `stickerEnableSelectImage` prop to enable / disable custom sticker selection.
-   Add FilePond implementation example to Vue 3 preset.
-   Add FilePond implementation example to React and React TypeScript presets.
-   Add CSS Modules doka.module.css file for use with NextJS.
-   Fix issue where loading a new image would crash the editor.
-   Fix crop rectangle not animating after using scroll wheel to zoom out when already zoomed out.
-   Fix rotator anchor rendering in the wrong position when holding shift.
-   Fix line snapping in the wrong direction when holding shift.

## 7.2.1 | 2021-02-08

-   Fix issue with drawing rectangles in annotation view on normal displays.
-   Fix issue where text color could was always black.
-   Fix issue where outline for rectangles wasn't being drawn.
-   Fix problem with events not being fired correctly in Vue v2 components.
-   Fix issue on Vue where assigning of initial props caused settings to be applied incorrectly.
-   Fix problem with annotation stickers placed incorrectly on rotated image.
-   Fix load event firing twice when loading images shortly after each other.
-   Fix PNG aliased transparent pixels.
-   Add `postprocessImageData` hook to `createDefaultImageWriter`.

## 7.2.0 | 2021-02-04

-   Add line snapping, hold shift to draw straight lines or to draw lines at a 45 degree angle.
-   Add Vue 2 example project and components.
-   Optimize vertical space use on iOS Safari.
-   Optimize creation and memory usage of canvas elements.
-   Optimize interaction with hitboxes small shapes.
-   Fix `imageCrop` incorrectly applied when cropLimitToBounds was set to `false`.
-   Fix annotations being drawn outside the image bounds on the output image.
-   Fix imageBackgroundColor not being applied to pixels outside of imageData.
-   Fix problem where buttons in shape popup menu could not be clicked when under top nav bar.
-   Fix issue with opening browser dev tools and the keydown state not being released.
-   Fix contextmenu causing events to be attached incorrectly.
-   Fix filter being applied to image background fill.
-   Fix blending of image effects with image data being slightly incorrect.
-   Restructured Angular and Vue packages and preset folders.

## 7.1.0 | 2021-01-29

-   Fix issue with tiny gaps in sharpie lines on Safari.
-   Fix `cornerRadius` not applied to rectangle in output image.
-   Fix preview rendering of rectangles that had `cornerRadius`, `backgroundSize`, and `backgroundImage` applied.
-   Fix `cornerRadius` overlapping if larger than half rectangle width.
-   Dramatically improve text preview update speed when changing text color.
-   Lower compressed package size by 2KB by optimising GLSL shaders.
-   Optimise draw performance of rectangles and text shapes.
-   Fix Angular minimum required version being 11.
-   Fix issue with SSR and how the `<doka-image-editor>` was defined.
-   Fix `imageBackgroundColor` not being applied in preview and output.
-   Improve render quality of colors in color picker buttons.
-   Add auto setting of shape `strokeWidth` if `strokeColor` is changed in editor.
-   Add NextJS preset project.
-   Add Angular View Engine preset project for Angular versions 8 and lower.

## 7.0.2 | 2021-01-25

-   Fix negative clarity value not blurring output image.
-   Fix problem with restoring annotation/decoration state with text shapes and image shapes.
-   Fix rotated text not rendering.
-   Fix problem with backgroundSize 'cover' and 'contain' not rendering correctly.
-   Fix performance of multiline text resizing.
-   Fix clipping of multiline text in output image.
-   Fix handling of returns in auto text blocks.
-   Fix not being removeable with eraser tool.
-   Fix clarity image preview showing black or white lines on iOS and M1.
-   Fix drag dropping stickers not working on mobile.

## 7.0.1 | 2021-01-22

-   Fix dragging and dropping stickers only working in annotation mode.
-   Fix image stickers not showing correctly.
-   Fix drawing emoji stickers not working sometimes.
-   Fix stickers being added while dragging over sticker thumbnail.
-   Fix stickers not being rotated rotated correctly when adding to a rotated image.
-   Fix clarity adjustments resulting in a black image.
-   Fix sticker presets `width` and `height` not being picked up correctly.
-   Fix circular shapes being incorreclty drawn on iOS.
-   Fix solid arrows not being drawn to output image.
-   Fix solid arrows not being drawn correctly on retina viewports.
-   Fix arrow tool missing arrow icon.
-   Fix stickers not being loaded correctly on Safari.
-   Fix automatic font-stack filtering for font family dropdown.
-   Fix Firefox navigating to previous page when trying to remove a shape with backspace key.
-   Fix decoration and annotation scale being incorrect when setting `imageTargetSize` property.
-   Fix menu buttons sometimes not responding to click.
-   Fix problem with free drawing circles because of incorrect tap gestured detection.
-   Fix android dragging of scrollable regions not working smoothly.
-   Lowered default size for sharpie and text.
-   Add `createDefaultImageOrienter`, use instead of `imageOrienter`.
-   Improve scroll container performance.
-   Removed preset util defaults from annotate and decorate tools.

## 7.0.0 | 2021-01-15

Version 7 is a complete rewrite of Doka Image Editor. The look and feel is different and it is not backwards compatible. You can convert version 6 data to a version 7 compatible format using the `legacyDataToImageState` method exported by the Doka Image Editor module.

See below for a short overview of some of the new features and improvements.

-   Add landscape view to make better use of viewport space
-   Add improved viewport resizing
-   Add undo/redo and reset functionality
-   Add shape drawing API for drawing custom elements on top of the preview
-   Add zoom control
-   Add new render engine
-   Add vignette control for adding a retro look n' feel
-   Add gamma control for gamma correction
-   Add clarity control for slightly sharpening and blurring images
-   Add Custom Element
-   Add support for tree-shaking
-   Add drag/drop of stickers
-   Add uploading of custom stickers
-   Add rotating of shapes
-   Add z-ordering of shapes
-   Add duplicating shapes
-   Add various arrow end styles
-   Add text wrapping
-   Add improved color selection
-   Add image annotation
-   Add more filters
-   Add stickers to draw utils
-   Add separate sticker util
-   Add headless processing of images
-   Add improved FilePond integration plugin
-   Add improved Dropzone integration plugin
-   Add improved Uppy integration plugin
-   Add improved jQuery, React, Angular, Svelte, and Vue adapters
-   Fix issue with text not rendering correctly.
-   Fix position of text input box that wasn't centered.
-   Fix issue where reset or undo would restore beyond initial imageState.
-   Fix issue where loading a new image would not work.

## 6.20.0 | 2020-12-21

-   Add gold version of Doka 7.0, this version is ready for public use

## 6.19.0 | 2020-12-09

-   Add second beta version of Doka 7.0
-   Add `beforeDeselectMarkup(currentItem, nextItem)` which is called when A markup item is deselected, return `false` to prevent deselection, return `true` to allow deselection.

## 6.18.0 | 2020-11-19

-   Add first beta version of Doka 7.0
-   Add `afterUpdateStickerItem(button, data)` which is called when Doka updates the sticker thumbnail. Allows updating the sticker thumbnail HTML.
-   Add `beforeAddMarkup(markupItem)` hook which is called before a markup item is added, return `markupItem` or `false` to prevent adding the item.
-   Add `beforeRemoveMarkup(markupItem)` hook which is called before a markup item is removed, return `markupItem` to continue or `false` to prevent removing the item.
-   Add `onselectmarkup(markupItem)` callback which is called when a markup item has been selected.
-   Add `onremovemarkup(markupItem)` callback which is called when a markup item has been removed.
-   Add `onaddmarkup(markupItem)` callback which is called when a markup item has been added.

## 6.17.0 | 2020-10-28

-   Add React-Dropzone integration example.

## 6.16.1 | 2020-10-26

-   Fix issue with `filter` property not being applied when set to string.
-   Fix issue where markup dropdowns would not select active styles.
-   Fix issue where narrow viewport menu was rendered incorrectly when only one util was active.
-   Fix issue where loading a new image wasn't possible after a previous image failed to load.
-   Fix issue where Firefox would sometimes throw an error when loading a Blob

## 6.16.0 | 2020-09-09

-   Add `beforeReset` hook to allow cancelling a reset.
-   Fix issue where the Dropzone adapter would throw an error if Doka rejected an image file.

## 6.15.5 | 2020-09-04

-   Fix issue where setting the `outputData` object to Doka as new options would not recreate previous state.

## 6.15.4 | 2020-08-21

-   Fix issue where clicks on filters or stickers would sometimes not register.

## 6.15.3 | 2020-08-12

-   Fix issue where clicking outside of a dropdown would throw an error.

## 6.15.2 | 2020-08-10

-   Fix issue with some buttons not working when testing on simulated iOS devices in Chrome DevTools.

## 6.15.1 | 2020-08-07

-   Fix issue where TypeScript definitions were missing in Angular package.

## 6.15.0 | 2020-07-29

-   Adjust hitboxes on markup resize controls so small items can be dragged more easily.
-   Hide edge resize controls on small symbols.

## 6.14.0 | 2020-07-27

-   Improve determining image mime type and file name of images loaded from URLs.
-   Fix issue where draw input could be enabled in sticker util view.
-   Upgrade Angular example project to version 10.

## 6.13.2 | 2020-06-25

-   Fix issue where elements with fixed values in sticker markup were not rendered correctly.

## 6.13.1 | 2020-06-22

-   Fix issue where markup images on remote servers would trigger tainted canvas error.

## 6.13.0 | 2020-06-16

-   Add prevent swipe on edge of screen navigation on iOS 13.4+.
-   Fix issue on IE11 where `contains` wasn't properly polyfilled.

## 6.12.0 | 2020-05-29

-   Add Sticker util. By default it's not enabled. Add `'sticker'` to the `util` property array to enable it.
-   Add `stickers` property. See documentation for details on how to set the available stickers.

## 6.11.0 | 2020-05-28

-   Add `beforeLoadImage` hook to allow custom file validation. Receives `file` object, should return a Promise. Resolve with `file` if everything is fine, reject with error status code if image should be rejected. Set `labelStatusLoadImageError` to a function to show a custom status message, the function receives the error status code and should return the label to show in the status bubble.
-   Fix issue with border color of markup elements that had a fill style.

## 6.10.1 | 2020-05-14

-   Fix issue in TypeScript definitions, `edit` method can resolve with `null`.
-   Fix issue on IE11 where menu/utils would not render after switching between them.
-   Fix issue with Angular example project not working on IE11 due to compiler problem.

## 6.10.0 | 2020-05-04

-   Update jQuery File Upload demo to use the `edit` functionality in the fileupload-ui library.

## 6.9.2 | 2020-05-01

-   Fix issue where image wasn't destroyed when loading a new image to an existing Doka instance resulting in a memory leak. This could cause problems with rendering on iOS or errors related to a missing WebGL context.

## 6.9.1 | 2020-04-30

-   Fix issue with IE11 incorrectly loading DataURL strings.

## 6.9.0 | 2020-04-28

-   Add `onready` event which fires when a loaded image is first presented.
-   Fix issue where `size` property would override FilePond input size.

## 6.8.1 | 2020-04-23

-   Improve handling of custom markup, now assigns unique id to markup item if no id set.
-   Fix issue where server side rendering would throw an error.

## 6.8.0 | 2020-04-15

-   Add auto-detection of image-orientation functionality now present in iOS Safari 13.4 and Chrome 81, this prevents incorrect rotation of JPEG images that contain orientation EXIF info.
-   Add `disableDokaImageEditor` option to jQuery File Upload package.
-   Improve defensive CSS styles for SVG elements.

## 6.7.0 | 2020-03-30

-   Add `allowButtonReset`, set to `false` to disable the reset button.
-   Fix issue where `size` property would not work when 'resize' util wasn't active.
-   Improve defensive styles to prevent collisions with other style frameworks.

## 6.6.0 | 2020-03-23

-   Improve hitboxes of paths, lines and markup anchor points for easier handling on small viewports.

## 6.5.5 | 2020-03-21

-   Fix issue with error thrown while loading preview editor.

## 6.5.4 | 2020-03-19

-   Improve defensive styles to prevent collisions with other style frameworks.
-   Fix issue where setting the `crop` property would reset other properties.
-   Fix placement of new markup elements to prevent them falling outside of image bounds.

## 6.5.3 | 2020-02-28

-   Fix issue where accessing localStorage would throw an error if not available due to privacy restrictions.

## 6.5.2 | 2020-02-24

-   Fix issue where IE11 would throw an error when loading dataURIs.

## 6.5.1 | 2020-02-14

-   Fix issue where dragging and resizing was no longer working on webkit browsers after scrolling the view on a touch screen.
-   Fix issue where `outputCanvasSyncTarget` wouldn't render the correct image on retina viewports.
-   Fix issue introduced in version 6.5.0 where `setData` would reset the view.

## 6.5.0 | 2020-02-13

-   Add `outputCanvasSyncTarget` property, set to a canvas element to draw the current webgl view to the target canvas.
-   Fix issue where setting new markup would not correctly clear existing markup.
-   Fix issue where when the width of an inline editor was exactly 600 pixels the cancel and reset button would both be visible.

## 6.4.3 | 2020-02-04

-   Fix issue where Firefox <= 58 would freeze when generating a preview image.
-   Fix issue where `cropMask` wouldn't redraw when updated.

## 6.4.2 | 2020-01-30

-   Fix issue where a race condition could happen when focus was moved outside of Doka.
-   Fix issue where close button on processing status message would not show when rendering doka inline.

## 6.4.1 | 2020-01-28

-   Fix issue where new filter logic would not be applied to filter previews and output image.

## 6.4.0 | 2020-01-27

-   Add `reject` handler to `afterCreateOutput`, hook can now be used to (for example) upload files and communicate the upload state to the user.
-   Fix issue where status bubbles were no longer animated.
-   Fix issue where filter color matrix would not correctly apply alpha values.

## 6.3.1 | 2020-01-17

-   Fix issue with response headers (when loading an image from URL) not being parsed correctly.

## 6.3.0 | 2020-01-14

If you're overriding default Doka styles this update might impact rendering and you might have to prepend your custom doka styles with `.doka--root` to make them more specific.

-   Add `cropAllowResizeRect`. Set to `false` to disable resizing the crop rectangle. Useful for when you only want to allow users to scale and rotate the image.
-   Add markup clipping to prevent markup from being rendered outside of the image crop area (on modern browsers only).
-   Improve defensive styles to prevent collisions with overly generic style selectors.
-   Improve profile examples to include file input elements for replacing existing images.
-   Fix issue where Safari would incorrectly render the crop size indicator on non-retinate displays.
-   Fix issue where `cropResizeMatchImageAspectRatio` would incorrectly zoom in when `imagePreviewScaleMode` was set to `'crop'`.

## 6.2.0 | 2020-01-06

-   Add `imagePreviewScaleMode`, set to `'crop'` to use actual pixel size of crop for preview image. Set to `'image'` to use pixel size of source image for preview image. Default is `'stage'`, fits preview to stage which matches the current behavior.
-   Improve rendering of keyboard indicator on top of image, now only shows when navigating to image preview with tab key.
-   Fix issue where `markupUtil` selection wouldn't work on init.
-   Fix issue with `allowImageFlipVertical` not working correctly.
-   Fix issue with color selection util not rendering correctly.
-   Fix issue where crop rectangle edges would not fully cover image.

## 6.1.0 | 2020-01-02

-   Add custom color selection util to the markup editor. Disable by setting `markupAllowCustomColor` to `false`.
-   Improve main menu style for narrow viewports. The corners are now rounded and the main action button has a prominent color.

## 6.0.0 | 2019-12-30

Markup style defaults properties have been renamed. This is a breaking change and therefor the major version number has changed as well. Previously the default styles were set using the index of the option in the linked style options list. Starting from this version the value is set using the actual value instead of the index.

This is only a breaking change if you've set different default values for the markup styles.

-   Add draw tool to markup view.
-   Set default markup style values using `markupColor` , `markupFontSize`, `markupFontFamily`, `markupShapeStyle`, and `markupLineDecoration`.
-   Add `markupDrawDistance` property, this controls the smoothness of the draw line.
-   Add auto switching between a dark and bright selection highlight based on the shape colors.
-   Add label properties for markup utils.
-   Fix issue with image head not being copied correctly when outputting a new image.

## 5.13.0 | 2019-12-20

-   Add more information to `update` event.
-   Add `cropAllowRotate` to toggle rotation control in crop util.
-   Add `util` property to programatically set the current active util.
-   Fix issue where Chrome would throw error when accessing custom response headers.
-   Improve `setData` call, can now apply output data object.

## 5.12.0 | 2019-12-16

-   Add Svelte adapter components.
-   Fix issue where setting aspect ratio using the API resulted in an error message.

## 5.11.2 | 2019-12-12

-   Fix issue with SSR introduced by 5.11.1
-   Fix issue with dragging the editor window on iOS.

## 5.11.1 | 2019-12-12

-   Fix issue where adding multiple color filters would not render a scrollbar.
-   Fix issue where dragging the color adjustment tools would sometimes cause the knob to snap back to center after release.
-   Fix issue with IE throwing an error during loading and processing status.

## 5.11.0 | 2019-12-09

-   Add [conditional compilation](https://pqina.nl/doka/docs/patterns/installation/#conditional-compilation) statements to MJS and SCSS source files. Add conditional compilation to your build script to optimize the output files based on which Doka utils are enabled (can save up to 30%).
-   Fix issue where scroll instruction wasn't aligned to pixel grid resulting in blurred text on non-retina displays.
-   Fix issue where mousedown inside the modal and mouseup outside the modal would close the Doka modal.

## 5.10.1 | 2019-12-02

-   Fix issue where Doka would throw an error on removal due to missing MODIFIER_KEYS variable.

## 5.10.0 | 2019-12-02

-   Add feature where tapping the modal overlay will close the modal.
-   Add progress indicator to load image status.
-   Add `afterCreateOutput` which allows modifying the output and updating the status message in the editor.
-   Add `labelResizeWidth`, `labelResizeHeight`, and `labelResizeApplyChanges` properties to control labels in resize view.
-   Add crop size indicator values are now animated when zooming in and out.
-   Add `cropAllowInstructionZoom`, when set to `true` this will show a small message to the user explaining how to zoom in and out (it'll only appear when the crop is centered and after two seconds of interaction). When the user scrolls or zooms in/out with the touchpad the message is hidden and will not re-appear (Doka will use `localStorage` to remember it has shown the message). Doka will use the value in the `storageName` property as the name for the `localStorage` entry.
-   Use `labelCropInstructionZoom` to change the label of the crop instruction bubble.
-   Update resize modifier key handling, when the array of key codes is empty the custom resize functionality is disabled.
-   Fix issue where clicks on menu wouldn't work while the menu bar was fading into view.
-   Fix issue where scrollbars where enabled on devices that showed no scrollbars causing a slight horizontal jump when opening the editor.
-   Fix issue where current and target aspect ratio wasn't calculated correctly resulting in weird zooming when switching between utils.
-   Fix issue where destroying the editor would throw a removeEventListener error when `allowDropFiles` was enabled.

## 5.9.0 | 2019-11-08

-   Update `labelStatusLoadImageError` to allow both a function and a string to be assigned. The function receives the image load error, the string returned by the function is presented in the status bubble shown by Doka to the user.
-   Fix issue where `getData` would not throw an error when called immidiately after Doka init.
-   Fix issue where status layer would be rendered outside of the editor when in modal mode.

## 5.8.0 | 2019-10-29

-   Add option to set `width` and `height` values to the aspect ratio dropdown. This turns the dropdown into a list of image size presets. When size values are used the aspect ratio is calculated based on the supplied dimensions.
-   Improve rendering performance and CSP compatibility by using `cssText` instead of `setAttribute` for updating view layout.

## 5.7.0 | 2019-10-10

-   Add `cropResizeMatchImageAspectRatio`, when enabled this will automatically adjust the crop aspect ratio to match the image aspect ratio if the user zooms out. Only works if the aspect ratio is set to "free".

## 5.6.0 | 2019-10-08

-   Add `cropAllowToggleLimit` to enable the "Crop selection" button.
-   Add `cropLimitToImageBounds` to toggle limiting the crop selection to the image bounds or allow cropping outside of the image.
-   Add `outputCanvasBackgroundColor` to control the color of the canvas when a transparent PNG is converted to JPEG and when cropping outside of the image bounds.
-   Add `outputCorrectImageExifOrientation` to toggle correcting image EXIF orientation on and off, this is on by default.
-   Fix issue where dragging the crop rectangle and scrolling the mouse wheel at the same time would throw an error.

If you are using Doka with FilePond, please update the following plugins

-   Image Edit to at least 1.5.0
-   Image Preview to at least 4.5.0
-   Image Transform to at least 3.5.0

## 5.5.1 | 2019-09-26

-   Fix issue where IE11 would not load images due to locked inner draw loop.

## 5.5.0 | 2019-09-24

-   Fix issue with missing handler in Angular profile demo.
-   Fix issue with Angular production build not working on example project, see example project README_DOKA.md for new installation instructions.
-   Add `onloadstart` callback.

## 5.4.1 | 2019-09-19

-   Fix issue with zoom scroll not working correctly within crop rectangle with inline editor.
-   Fix issue with Doka instantly focussing the first view on load.
-   Fix issue with color tools alignment.
-   Increase opacity of image overlay on all but crop view.
-   Lower height of crop and markup toolbar to create slightly more room for image.

## 5.4.0 | 2019-09-17

-   Add `allowBrowseFiles`, click on editor to upload new file while awaiting file input.
-   Add `cropAllowImageFlipHorizontal` and `cropAllowImageFlipVertical` to toggle flip buttons on and off.
-   Add `cropAllowImageTurnLeft` to toggle rotate left button.
-   Add `pointerEventsPolyfillScope`, set to 'document' to polyfill pointer events at the document level instead of the editor level, this will allow dragging outside of the editor on Safari (and other browsers lacking PointerEvent support).
-   Fix issue with WebGL error being thrown on black listed graphic cards/drivers.
-   Fix issue with scroll zoom not working when scrolling towards an already initialised editor.
-   Fix issue with weird image outline rendering issues on Firefox.
-   Fix issue where setting the `className` property would not assign the class to the editor root element.

## 5.3.1 | 2019-09-12

-   Improve WebGL performance on older devices by slightly lowering the canvas size. Doka now uses the `devicePixelRatio` times `.75`.
-   Fix issue where multi-touch interaction on the crop rectangle would throw an error.
-   Fix issue where releasing a drag operation on a markup shape would throw an error.

## 5.3.0 | 2019-09-06

-   Add `cropResizeScrollRectOnly` property to limit scroll resizing to the crop rectangle.
-   Fix issue where Edge would throw an error related to `toColor` method when closing the editor.

## 5.2.1 | 2019-09-03

-   Add "WebGL required" message to notify users when WebGL has been disabled on their browser.
-   Fix issue where main util menu wasn't always perfectly aligned to the center.
-   Fix issue where "Awaiting image" message would not show.
-   Fix issue where aspect ratio dropdown would show for a small moment.
-   Improve rendering of resize view.
-   Improve callbacks in TypeScript declaration file.

## 5.2.0 | 2019-09-02

-   Add Angular adapter components.
-   Fix problem with `cropAspectRatio` not updating the crop aspect ratio.
-   Fix TypeScript declaration file mismatch with standards.
-   Fix issue with crop size indicator throwing an error.
-   Fix issue with resize view not re-rendering correctly.
-   Improve TypeScript declaration file with better interfaces.

## 5.1.3 | 2019-08-29

-   Fix problem with double destroy method in GL layer.
-   Fix problem with hiding util buttons instead of removing them completely.
-   Fix problem where src scss file was not up to date.
-   Improve CSS to prevent style collisions.

## 5.1.2 | 2019-08-27

-   Fix issue with WebGL background gradient flickering on certain hardware.

## 5.1.1 | 2019-08-27

-   Fix problem with multitouch resizing not working.

## 5.1.0 | 2019-08-26

-   Improve rendering performance by switching to WebGL powered image layer.
-   Re-render when returning to editor from another tab.
-   Improve release of canvas memory on Safari.
-   All kinds of small performance improvements to limit use of CPU.
-   Add more error logging to process method.
-   Add keyboard navigation indicator icon.
-   Fix issue where pressing backspace to remove markup on Firefox would navigate away from the page.
-   Fix problem where creating the output image didn't work on iOS 10.

## 5.0.2 | 2019-08-22

-   Fix additional issue with shapes not loading after previous fix.
-   Fix problem with removing locked shapes.

## 5.0.1 | 2019-08-22

-   Fix issue where removed markup item was still rendered to canvas.
-   Fix issue where hidden error message would be thrown when closing the Doka modal on Firefox.

## 5.0.0 | 2019-08-15

Although the major version number has been changed, this version is mostly backwards compatible. The only breaking change is that now all utils except the resize util are enabled by default.

If you've integrated Doka with FilePond, please make sure you update the FilePond Image Transform, Image Preview, and Image Edit plugins.

-   Add markup support.
-   Add support for predefined markup.
-   Add support for controlling which markup elements can be modified / edited.
-   An extensive set of performance improvements.
-   Improvements to examples templates.
-   Fix `cropAspectRatioOptions` throwing an error when set to an empty array.
-   Other small bug fixes and improvements.

## 4.8.3 | 2019-08-07

-   Fix additional problem with image processing step that caused a crash when no output size was set.

## 4.8.2 | 2019-08-07

-   Fix problem where Vue component didn't render correctly.
-   Update React example to include initial crop aspect ratio.

## 4.8.1 | 2019-08-06

-   Fix problem where output upscale setting crashed image processing step.

## 4.8.0 | 2019-08-05

-   Add output image width and height to `output.data.image` property.
-   Fix polyfill URLs in file upload library integration examples.

## 4.7.2 | 2019-07-31

-   Fix `ResizeMode` TypeScript property.
-   Add `outputCanvasMemoryLimit` to TypeScript files.
-   Add `tslint:disable` to Vue components to silence tslint warnings.
-   Add very basic `createDoka.d.ts` file so Doka Vue components are seen as Components.

## 4.7.1 | 2019-07-24

-   Recover Vue FilePond Doka integration example.

## 4.7.0 | 2019-07-24

-   Fix issue where iOS canvas memory limit could be exceeded. Add the `outputCanvasMemoryLimit` property which is automatically set to 16 megapixels on iOS devices. Doka will automatically scale output canvas size on these devices to prevent transparent output images.

## 4.6.4 | 2019-07-23

-   Improve defensive CSS styles for better compatibility with CSS frameworks.
-   Fix problem where EXIF info wasn't copied when `outputStripImageHead` was set to `false`.

## 4.6.3 | 2019-07-23

-   Fix style problem with React FilePond Doka integration example.
-   Fix problem with Vue demo's throwing error when loading Web Workers.
-   Add Vue FilePond Doka integration example.

## 4.6.2 | 2019-07-11

-   Fix issue where filter thumbnail was still skewed in some situations.

## 4.6.1 | 2019-07-08

-   Fix issue where a small canvas element was appended to the document body.

## 4.6.0 | 2019-07-02

-   Add `initialState` property to set state to reset to when reset button is pressed. Set to `null` to reset to the default editor state.
-   Add React FilePond Doka example integration.
-   Fix issue where filter previews showed a skewed image.
-   Fix issue where setting the `size` property would throw an error.
-   Fix issue where a `size` property update would not hide resize percentage indicator.
-   Improved performance of filter preview rendering.

## 4.5.2 | 2019-07-01

-   Add color transitions to various parts of the editor for nicer theme switching.
-   Add jQuery adapter.

## 4.5.1 | 2019-06-22

-   Fix `cannot read property scale of null` error.

## 4.5.0 | 2019-06-21

-   Add conditional close button to image error status message when in fullscreen mode so user can close the editor when an image fails to load.
-   Optimise and speed up image resize logic.
-   Fix rendering of cancel/exit button icon on Safari.
-   Fix problem where `outputUpscale` when set to `false` would sometimes skip applying image filters.
-   Fix problem with wrapping of utility tabs.

## 4.4.2 | 2019-06-20

-   Fix issue with minimum crop size not being calculated correctly for big images.
-   Fix issue with updating the available utils property.

## 4.4.1 | 2019-06-17

-   Fix issue where flipping the image preview would hide it.
-   Fix style collision with normalize.css.
-   Fix style collisions created by blanket `* { box-sizing: border-box; }`.

## 4.4.0 | 2019-06-13

This update adds the bright theme and a color control panel.

All color values in CSS are now accessible through CSS Custom Properties, see documentation for a detailed guide. CSS Custom Properties are supported in all major browsers except Internet Explorer 11, it will still have to be styled "manually".

-   Set `--doka-invert` to `100%` on the `.doka--root` element to set bright theme mode.
-   Add line crop corner style. Set the `styleCropCorner` to `'line'`, default is `'circle'`.
-   Add brightness, contrast, exposure, and saturation controls. Enable by adding the `'color'` util to the `utils` property.
-   Improve resize preview by taking into account the `devicePixelRatio` when calculating the image preview width and height.
-   Slightly increase distance between utility buttons and crop rectangle, this results in less miss clicks.
-   Fix issue where crop icon color would not be based on current color.
-   Removed rounded corners from size indicator, this makes it look more distinct from the crop corners. Set `--doka-size-indicator--radius` to `9999em` to restore rounded corners.

## 4.3.3 | 2019-06-06

-   Fix issue where IE11 would throw an error because of using `Number.isNaN` instead of `isNaN`.
-   Fix issue where crop rectangle size could fall below minimum image size when switching from free to a fixed ratio crop.
-   Fix issue where crop rectangle size could fall below minimum image size when free cropping a very small image.
-   Fix issue when selecting crop aspect ratios that would result in invalid crops for very small images by hiding the invalid crop ratios.

## 4.3.2 | 2019-06-05

-   Fix issue where native examples no longer worked on IE11 because polyfill was loaded incorrectly.
-   Fix issue where min crop size was not respected while dragging corner crop controls.
-   Fix issue where filter thumbnail images were skewed when image EXIF orientation header was set.
-   Add "src" folder. This folder contains the merged source files. The JavaScript is written in ES6, this will need to be transpiled to function. The CSS files are written in Sass so will need to be converted to CSS to function.

## 4.3.1 | 2019-05-31

-   Fix issue where native examples did not load filters.
-   Fix issue where font weight of buttons could be easily and accidentally overruled by other styles.
-   Fix issue with internal paint loop not handling inactive tab state correctly.

## 4.3.0 | 2019-05-28

-   Fix issue where tab buttons would not enforce the Doka editor `line-height`.
-   Fix issue where image error status bubble would appear in bottom right corner on Safari 12.
-   Fix problem where document body would receive min height of 512 pixels.
-   Add image information object to `onload` event.
-   Add `onupdate` event which shares the current crop width and height.
-   Add `allowPreviewFitToView` option, set to `false` to prevent Doka from upscaling the preview image.
-   Update example projects to include filter and resize utilities.

## 4.2.0 | 2019-05-24

-   Add filters, enable by adding the `'filter'` string to the `utils` property.
-   Redesigned the Crop and Resize utility icons.
-   Fix issue where dragging an image would throw an error on Safari.
-   Fix issue where bottom safe area on iOS was always active, now only activates when Safari footer menu is not visible.
-   Fix issue where outlines were invisible in high contrast mode.
-   Fix issue where SVG elements were focusable on IE and Edge.
-   Update TypeScript definitions.

## 4.1.2 | 2019-05-09

-   Fix issue where React module was a build target instead of the original source files.

## 4.1.1 | 2019-05-02

-   Fix issue where certain aspect ratios could overflow the minimum and maximum image size.

## 4.1.0 | 2019-04-26

-   Fix issue where resize and crop size width and height were reported incorrectly for large images.
-   Fix issue where scrollwheel was still available in resize view.
-   Fix issue where degree character would render incorrectly by replacing it with its HTML entity `&deg;`.
-   Fix issue where rotating the image 180 degrees and then flipping it would in not flip along the correct axis.
-   Fix issue where typescript definitions for `getData`, `setData`, and `save` were malformed.
-   Improved rendering of crop/resize image edges, they now better overlap with the image.
-   Add `onloaderror` callback to programmaticaly handle image load error status.
-   Add `afterCreateBlob` hook to allow modifying the Blob data before it's outputted by Doka.

## 4.0.3 | 2019-04-11

-   Fix issue where loading indicator view would break outside of editor when editor was inlined on the page.
-   Fix issue where status message would unevenly draw background color of editor.
-   Fix issue where zoom button would be clickable while hidden.
-   Fix issue where settings the aspect ratio to free was no longer possible after loading an image with a given aspect ratio.
-   Update TypeScript doka.d.ts file with new options added in version 4.

## 4.0.2 | 2019-04-08

-   Fix issue where editor would not clean up properly when exiting full screen mode.

## 4.0.1 | 2019-04-04

-   Add feature where turning is automatically done by 180 degree intervals if 90 degree does not fit the required minimum crop size.
-   Fix issue where crop mask overlay would log a series of errors.
-   Fix issue where crop mask overlay animation would not sync up with image and crop animations.

## 4.0.0 | 2019-04-04

This is a major release as internally a lot of elements have changed, still it shouldn't break anything. If you're targetting Doka elements to apply different styles then the related CSS selectors might have to be updated.

-   Add Resize util. Enable by setting the `utils` option to `['crop', 'resize']`. The array also controls the order of the menu items.
-   Add `size`, `sizeMin`, and `sizeMax` options to control image output size.
-   Add size indicator in crop view, toggle with `cropShowSize`, default is `false`.
-   Change `cropMinImageWidth` default value to `1`.
-   Change `cropMinImageHeight` default value to `1`.
-   Add `labelButtonUtilCrop` option.
-   Add `labelButtonUtilResize` option.
-   Improve animations to be just a little bit less bouncy.
-   Fix issue where window resize sometimes caused view updates to not render correctly.
-   Fix issue where main menu overlapped with top crop edge and corners making it difficult to control the crop area in some situations.
-   Various tiny fixes and improvements.

## 3.1.0 | 2019-02-21

-   Add Doka methods to React components.
-   Add Vue components and demos.
-   Add crop mask example to React and Vue demos.
-   add `save` method to manually save the currently loaded image. This is basically an alias for `getData`.
-   add `onload` callback which is called when an image has been succesfully loaded.
-   Fix issue where Doka would prevent default on the `mousemove` event.
-   Fix issue where aspect ratio dropdown would not select the free aspect ratio.
-   Fix issue where modal close event was fired multiple times if modal was not immidiately removed from DOM.

## 3.0.0 | 2019-02-12

This release adds React components to the package.

-   Fix issue with zoom timeout where multiple Doka instances were sharing the same timeout id.
-   Fix issue where passing a `cropAspectRatio` to `edit` or `open` would make it the default crop aspect ratio.
-   Now only automatically closes and removes itself when in modal mode. When Doka is in preview or in-page layout mode it needs to be closed programatically.
-   Removed `queue` mode, the active file is now replaced if a new file is added, by listening to the `onconfirm` event it's still possible to walk over a queue of files. Samples have been updated.
-   Add `allowAutoClose` to disable automatically closing the modal. The modal can then be programatically closed using the `close()` method.
-   Add `allowAutoDestroy`, if enabled Doka will automatically destroy itself when the modal is closed.
-   Renamed `labelButtonClose` to `labelButtonCancel`.
-   Renamed `allowButtonClose` to `allowButtonCancel`.
-   Fix issue where `clear()` method did not work, it now removes the active image from view.
-   Cancelling editing an image will no longer reject the returned Promise, it will resolve it returning `null`.
-   Add `beforeCreateBlob` to adjust the output data before the Blob is created, useful for when you want to add a watermark.
-   Add `crop` options, to programmatically update the current crop rectangle.
-   Fix file processing popup, will now only show when the file is actually being processed.
-   Add more examples to the package.
-   Update TypeScript definitions.

## 2.3.1 | 2019-01-22

-   Update Typescript definitions.

## 2.3.0 | 2019-01-22

-   Add `cropMask` and `cropMaskInset` which can be used to plot a mask overlay on top of the crop area.

## 2.2.1 | 2019-01-17

-   Fix issue where server side rendering would not work.

## 2.2.0 | 2019-01-03

-   Add `cropMinImageWidth` and `cropMinImageHeight` properties.
-   Fix issue where zoom button could not be clicked when crop area was very small.

## 2.1.2 | 2018-12-27

-   Fix issue where a resize of the image would prevent the editor from working on Android.

## 2.1.1 | 2018-12-22

-   Fix issue where the image was flipped along the incorrect axis when rotated.
-   Remove flip indicator from flip buttons.

## 2.1.0 | 2018-12-22

-   Add typescript definitions file for Doka module.
-   Add modal layout mode, set `styleLayoutMode` to `modal` to enable (only works in fullscreen mode). See docs for further instructions.
-   Add key listener to allow closing of the modal and fullscreen mode by pressing the escape key.
-   Add `outputStripImageHead` property, set to `false` to copy JPEG EXIF data to output file.
-   Add /_ eslint-disable _/ statement to each file library file to prevent collisions with es lint.
-   Improve responsive rendering of crop tools menu so it correctly renders no matter how many buttons are active.
-   Fix FilePond sample, image edit plugin should be registered last.

## 2.0.1 | 2018-12-04

-   Fix issue with alignment of editor on IE11.
-   Fix issue with alignment of aspect ratio dropdown on IE11.
-   Fix issue with smoke overlay not rendering correctly on IE11.

## 2.0.0 | 2018-11-25

-   Add keyboard navigation.
-   Add aspect ratio dropdown.
-   Add loading and processing image indicators.
-   Add open API endpoint to manually open images.
-   Add open API endpoint to manually edit images.
-   Add getData API endpoint to manually retrieve image data.
-   Add setData API endpoint to manually update image data.
-   Add option to use Image and Canvas tags as a source.
-   Add option to configure labels.
-   Add option to drop images directly on the editor.
-   Add option to disable menu buttons.
-   Add zoom timeout option.
-   Add image transform options.
-   Add functionality that blocks interactions when loading or processing images.
-   Add container mode, run one or multiple editors in-page instead of fullscreen.
-   Add preview mode, run the editor in a preview overlay on top on an image.
-   Add integrations with different file upload solutions.
-   Improve rescaling of the editor.
-   Improve rendering by syncing with FilePond internal render engine if present.
-   Improve handling of keyboard focus, now moves to the editor when active.
-   Improve style of cancel, reset and confirm buttons.
-   Improve presentation of flip buttons.
-   Improve the keyboard focus indicator.
-   Fix issue where button icons would incorrectly align on older browsers.
-   Fix issue where the editor view would be incorrectly centered on Internet Explorer 11.
-   Fix issue where iOS bottom footer would interfere with the editor.
-   Fix issue where focus could leave editor when in fullscreen mode.
-   Fix close window animation.

## 1.0.2 | 2018-11-01

-   Fix issue with resize controls not being positioned correctly when the viewport is very big.

## 1.0.1 | 2018-10-12

-   Fix issue with dynamic font size on body of editor.
-   Fix issue with resize controls not being positioned correctly.
-   Fix issue where reset and zoom icons would be positioning incorrectly on small viewports.

## 1.0.0 | 2018-10-05

-   Initial release.
