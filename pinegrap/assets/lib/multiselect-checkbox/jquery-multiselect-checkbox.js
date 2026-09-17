(function webpackUniversalModuleDefinition(root, factory) {
	if(typeof exports === 'object' && typeof module === 'object')
		module.exports = factory(require("jquery"));
	else if(typeof define === 'function' && define.amd)
		define("jQueryMultiselectCheckbox", ["jquery"], factory);
	else if(typeof exports === 'object')
		exports["jQueryMultiselectCheckbox"] = factory(require("jquery"));
	else
		root["jQueryMultiselectCheckbox"] = factory(root["jQuery"]);
})((typeof self !== 'undefined' ? self : (typeof global !== 'undefined' ? global : this)), function(__WEBPACK_EXTERNAL_MODULE_jquery__) {
return /******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./src/defaultOptions.js":
/*!*******************************!*\
  !*** ./src/defaultOptions.js ***!
  \*******************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _defaults_checkboxResolve__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./defaults/checkboxResolve */ "./src/defaults/checkboxResolve.js");
/* harmony import */ var _defaults_itemResolve__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./defaults/itemResolve */ "./src/defaults/itemResolve.js");
/* harmony import */ var _defaults_targetCheckStopPropagation__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./defaults/targetCheckStopPropagation */ "./src/defaults/targetCheckStopPropagation.js");
/* harmony import */ var _defaults_nextUntilFlattenDOM__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./defaults/nextUntilFlattenDOM */ "./src/defaults/nextUntilFlattenDOM.js");
/* harmony import */ var _defaults_prevUntilFlattenDOM__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./defaults/prevUntilFlattenDOM */ "./src/defaults/prevUntilFlattenDOM.js");
/*
 * Copyright (c) 2021 Anton Bagdatyev (Tonix)
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */




 // eslint-disable-next-line no-unused-vars

/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (function ($, window, document) {
  return {
    checkboxes: null,
    sync: null,
    // eslint-disable-next-line @typescript-eslint/no-empty-function
    onAllChecked: function onAllChecked() {},
    // eslint-disable-next-line @typescript-eslint/no-empty-function
    onAllUnchecked: function onAllUnchecked() {},
    // eslint-disable-next-line @typescript-eslint/no-empty-function
    onNotAllChecked: function onNotAllChecked() {},
    checkboxResolve: (0,_defaults_checkboxResolve__WEBPACK_IMPORTED_MODULE_0__["default"])($),
    itemResolve: _defaults_itemResolve__WEBPACK_IMPORTED_MODULE_1__["default"],
    targetCheckStopPropagation: _defaults_targetCheckStopPropagation__WEBPACK_IMPORTED_MODULE_2__["default"],
    eventsElement: document,
    syncEvent: "click",
    handleShiftForCheckbox: false,
    usePrevUntilNextUntilForSync: true,
    next: _defaults_nextUntilFlattenDOM__WEBPACK_IMPORTED_MODULE_3__["default"],
    prev: _defaults_prevUntilFlattenDOM__WEBPACK_IMPORTED_MODULE_4__["default"],
    checkedClassName: "jquery-multi-select-checkbox-checked",
    checkedKeyDataAttributeName: "jquery-multi-select-checkbox-checked-key",
    keyDataAttributeName: "key"
  };
});

/***/ }),

/***/ "./src/defaults/checkboxResolve.js":
/*!*****************************************!*\
  !*** ./src/defaults/checkboxResolve.js ***!
  \*****************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/*
 * Copyright (c) 2021 Anton Bagdatyev (Tonix)
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (function ($) {
  return function checkboxResolve($item, instance) {
    var self = instance;
    return $item.find("*").filter(function () {
      return $(this).is(self.options.checkboxes);
    });
  };
});

/***/ }),

/***/ "./src/defaults/itemResolve.js":
/*!*************************************!*\
  !*** ./src/defaults/itemResolve.js ***!
  \*************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ itemResolve)
/* harmony export */ });
/*
 * Copyright (c) 2021 Anton Bagdatyev (Tonix)
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */
function itemResolve($checkbox, instance) {
  var self = instance;
  return $checkbox.closest(self.options.sync);
}

/***/ }),

/***/ "./src/defaults/nextUntilFlattenDOM.js":
/*!*********************************************!*\
  !*** ./src/defaults/nextUntilFlattenDOM.js ***!
  \*********************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/*
 * Copyright (c) 2021 Anton Bagdatyev (Tonix)
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (function ($) {
  return (// eslint-disable-next-line no-unused-vars
    function nextUntilFlattenDOM($el, $prevSelected, $all, instance) {
      var $ret = $([]);
      var elFound = false;
      $all.each(function () {
        var $current = $(this);

        if ($current.is($el)) {
          elFound = true;
          return;
        } else if ($current.is($prevSelected)) {
          return false;
        } else if (elFound) {
          $ret = $ret.add($current);
        }
      });
      return $ret;
    }
  );
});

/***/ }),

/***/ "./src/defaults/prevUntilFlattenDOM.js":
/*!*********************************************!*\
  !*** ./src/defaults/prevUntilFlattenDOM.js ***!
  \*********************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/*
 * Copyright (c) 2021 Anton Bagdatyev (Tonix)
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (function ($) {
  return (// eslint-disable-next-line no-unused-vars
    function prevUntilFlattenDOM($el, $prevSelected, $all, instance) {
      var $ret = $([]);
      var prevSelectedFound = false;
      $all.each(function () {
        var $current = $(this);

        if ($current.is($prevSelected)) {
          prevSelectedFound = true;
          return;
        } else if ($current.is($el)) {
          return false;
        } else if (prevSelectedFound) {
          $ret = $ret.add($current);
        }
      });
      return $ret;
    }
  );
});

/***/ }),

/***/ "./src/defaults/targetCheckStopPropagation.js":
/*!****************************************************!*\
  !*** ./src/defaults/targetCheckStopPropagation.js ***!
  \****************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ targetCheckStopPropagation)
/* harmony export */ });
/*
 * Copyright (c) 2021 Anton Bagdatyev (Tonix)
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */
// eslint-disable-next-line no-unused-vars
function targetCheckStopPropagation(e, instance) {
  e.stopPropagation();
}

/***/ }),

/***/ "./src/helpers/checkForCheckboxAll.js":
/*!********************************************!*\
  !*** ./src/helpers/checkForCheckboxAll.js ***!
  \********************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ checkForCheckboxAll)
/* harmony export */ });
/*
 * Copyright (c) 2021 Anton Bagdatyev (Tonix)
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */
function checkForCheckboxAll($, toggled, instance) {
  var allTrue = true;
  var allFalse = true;
  var self = instance;
  $(self.options.checkboxes).each(function () {
    var checked = $(this).prop("checked");

    if (!checked) {
      allTrue = false;
    } else {
      allFalse = false;
    }
  });
  self.$el.prop("checked", allTrue);

  if (toggled) {
    if (allTrue) {
      self.options.onAllChecked(self.selectedMap);
    } else {
      if (allFalse) {
        self.options.onAllUnchecked(self.selectedMap);
      } else {
        self.options.onNotAllChecked(self.selectedMap);
      }
    }
  }
}

/***/ }),

/***/ "./src/helpers/checkUncheck.js":
/*!*************************************!*\
  !*** ./src/helpers/checkUncheck.js ***!
  \*************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ checkUncheck)
/* harmony export */ });
/*
 * Copyright (c) 2021 Anton Bagdatyev (Tonix)
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */
// eslint-disable-next-line no-unused-vars
function checkUncheck($el, bool, instance) {
  $el.prop("checked", bool);
  $el.trigger("change");
  return;
}

/***/ }),

/***/ "./src/helpers/emptyArrayIfNull.js":
/*!*****************************************!*\
  !*** ./src/helpers/emptyArrayIfNull.js ***!
  \*****************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ emptyArrayIfNull)
/* harmony export */ });
/*
 * Copyright (c) 2021 Anton Bagdatyev (Tonix)
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */
function emptyArrayIfNull(selector) {
  if (selector === null) {
    selector = [];
  }

  return selector;
}

/***/ }),

/***/ "./src/helpers/extendMap.js":
/*!**********************************!*\
  !*** ./src/helpers/extendMap.js ***!
  \**********************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ extendMap)
/* harmony export */ });
/*
 * Copyright (c) 2021 Anton Bagdatyev (Tonix)
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */
function extendMap(map) {
  map.orderedByI = function () {
    var desc = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : false;
    return map.values().sort(function (a, b) {
      return !desc ? a.i - b.i : b.i - a.i;
    });
  };

  return map;
}

/***/ }),

/***/ "./src/helpers/handleShift.js":
/*!************************************!*\
  !*** ./src/helpers/handleShift.js ***!
  \************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ handleShift)
/* harmony export */ });
/* harmony import */ var _nextUntil__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./nextUntil */ "./src/helpers/nextUntil.js");
/* harmony import */ var _prevUntil__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./prevUntil */ "./src/helpers/prevUntil.js");
/*
 * Copyright (c) 2021 Anton Bagdatyev (Tonix)
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */


function handleShift($, $el, instance) {
  var self = instance;
  var prevUntilNextUntil = {
    prev: self.options.prev,
    next: self.options.next
  };
  var $itemsCheckboxes;

  if (!self.options.usePrevUntilNextUntilForSync) {
    // checkbox
    $itemsCheckboxes = $(self.options.checkboxes);
  } else {
    // synced item
    $itemsCheckboxes = $(self.options.sync);
    prevUntilNextUntil.prev = _prevUntil__WEBPACK_IMPORTED_MODULE_1__["default"];
    prevUntilNextUntil.next = _nextUntil__WEBPACK_IMPORTED_MODULE_0__["default"];
  }

  if ($itemsCheckboxes.filter(".".concat(self.options.checkedClassName)).length <= 1) {
    return;
  }

  var selected = $el.hasClass(self.options.checkedClassName);
  var itemsSelected = self.selectedMap.values();

  if (selected && self.selectedMap.length > 1) {
    // `$prevSelected` is always a checkbox here if `self.options.usePrevUntilNextUntilForSync` is false.
    var $prevSelected = !self.options.usePrevUntilNextUntilForSync ? ($el.is(self.options.sync) ? ($el = self.options.checkboxResolve($el, self)) && void 0 : void 0) || itemsSelected[itemsSelected.length - 2].$el : self.options.itemResolve(itemsSelected[itemsSelected.length - 2].$el, self);
    var prevIndex = $itemsCheckboxes.index($prevSelected),
        itemIndex = $itemsCheckboxes.index($el);
    var toCall;
    var goingForwardFrom$el;

    if (prevIndex > itemIndex) {
      goingForwardFrom$el = true;
      toCall = "next";
    } else {
      goingForwardFrom$el = false;
      toCall = "prev";
    }

    prevUntilNextUntil[toCall]($el, $prevSelected, $itemsCheckboxes, self).each(function () {
      var $this = $(this);
      self.toggle($, $this, true);

      if ($this.is(self.options.checkboxes)) {
        self.toggle($, self.options.itemResolve($this, self), true);
      } else {
        self.toggle($, self.options.checkboxResolve($this, self), true);
      }
    });
    var fromIndex;

    if (goingForwardFrom$el) {
      var indexOfNext = $itemsCheckboxes.index($el) + 1;
      var $next = $itemsCheckboxes.eq(indexOfNext);

      while ($next.length && indexOfNext <= $itemsCheckboxes.length - 1) {
        if ($next.hasClass(self.options.checkedClassName)) {
          indexOfNext = $itemsCheckboxes.index($next) + 1;
          $next = $itemsCheckboxes.eq(indexOfNext);
        } else {
          break;
        }
      }

      fromIndex = indexOfNext - 1;
    } else {
      var indexOfPrev = $itemsCheckboxes.index($el) - 1;
      var $prev = $itemsCheckboxes.eq(indexOfPrev);

      while ($prev.length && indexOfPrev >= 0) {
        if ($prev.hasClass(self.options.checkedClassName)) {
          indexOfPrev = $itemsCheckboxes.index($prev) - 1;
          $prev = $itemsCheckboxes.eq(indexOfPrev);
        } else {
          break;
        }
      }

      fromIndex = indexOfPrev + 1;
    }

    self.selectedRanges.unshift({
      from: fromIndex,
      to: itemIndex
    });
  } else {
    $el.is(self.options.sync) ? ($el = self.options.checkboxResolve($el, self)) && void 0 : void 0;

    if (self.options.usePrevUntilNextUntilForSync && $el.is(self.options.checkboxes)) {
      $el = self.options.itemResolve($el, self);
    }

    var index = $itemsCheckboxes.index($el);

    for (var i = 0; i < self.selectedRanges.length; i++) {
      var range = self.selectedRanges[i];
      var from = range.from;
      var to = range.to;

      if (index >= from && index <= to || index >= to && index <= from) {
        // `index` is within range, uncheck all from `index` to `to`.
        var $toDeselect = void 0;

        if (to > index) {
          $toDeselect = $itemsCheckboxes.slice(index + 1, to + 1);
        } else {
          $toDeselect = $itemsCheckboxes.slice(to, index);
        }

        $toDeselect.each(function () {
          var $this = $(this);
          self.toggle($, $this, false);

          if ($this.is(self.options.checkboxes)) {
            self.toggle($, self.options.itemResolve($this, self), false);
          } else {
            self.toggle($, self.options.checkboxResolve($this, self), false);
          }
        });
        break;
      }
    }
  }
}

/***/ }),

/***/ "./src/helpers/nextUntil.js":
/*!**********************************!*\
  !*** ./src/helpers/nextUntil.js ***!
  \**********************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ nextUntil)
/* harmony export */ });
/*
 * Copyright (c) 2021 Anton Bagdatyev (Tonix)
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */
// eslint-disable-next-line no-unused-vars
function nextUntil($el, $prevSelected, $all, instance) {
  return $el.nextUntil($prevSelected);
}

/***/ }),

/***/ "./src/helpers/prevUntil.js":
/*!**********************************!*\
  !*** ./src/helpers/prevUntil.js ***!
  \**********************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ prevUntil)
/* harmony export */ });
/*
 * Copyright (c) 2021 Anton Bagdatyev (Tonix)
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */
// eslint-disable-next-line no-unused-vars
function prevUntil($el, $prevSelected, $all, instance) {
  return $el.prevUntil($prevSelected);
}

/***/ }),

/***/ "./src/helpers/setSelectedUniqueKey.js":
/*!*********************************************!*\
  !*** ./src/helpers/setSelectedUniqueKey.js ***!
  \*********************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ setSelectedUniqueKey)
/* harmony export */ });
/* harmony import */ var _extendMap__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./extendMap */ "./src/helpers/extendMap.js");
function _defineProperty(obj, key, value) { if (key in obj) { Object.defineProperty(obj, key, { value: value, enumerable: true, configurable: true, writable: true }); } else { obj[key] = value; } return obj; }

/*
 * Copyright (c) 2021 Anton Bagdatyev (Tonix)
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */

/**
 * @type {number}
 */

var itemIdCounter = 0;
function setSelectedUniqueKey($el, instance) {
  var self = instance;
  var checkedKey = $el.data(self.options.checkedKeyDataAttributeName);

  if (self.selectedMap.get(checkedKey)) {
    self.selectedMap = (0,_extendMap__WEBPACK_IMPORTED_MODULE_0__["default"])(self.selectedMap.unset(checkedKey));
  }

  var key = $el.data(self.options.keyDataAttributeName) || ++itemIdCounter;
  $el.data(self.options.checkedKeyDataAttributeName, key);
  var value = $el.val();
  var newMap = self.selectedMap.set(_defineProperty({}, key, {
    $el: $el,
    key: key,
    value: value || void 0,
    i: $el.index(self.options.checkboxes)
  }));
  self.selectedMap = (0,_extendMap__WEBPACK_IMPORTED_MODULE_0__["default"])(newMap);
}

/***/ }),

/***/ "./src/jQueryMultiselectCheckbox.js":
/*!******************************************!*\
  !*** ./src/jQueryMultiselectCheckbox.js ***!
  \******************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var immutable_linked_ordered_map__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! immutable-linked-ordered-map */ "./node_modules/immutable-linked-ordered-map/src/index.js");
/* harmony import */ var _helpers_checkUncheck__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./helpers/checkUncheck */ "./src/helpers/checkUncheck.js");
/* harmony import */ var _helpers_emptyArrayIfNull__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./helpers/emptyArrayIfNull */ "./src/helpers/emptyArrayIfNull.js");
/* harmony import */ var _helpers_checkForCheckboxAll__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./helpers/checkForCheckboxAll */ "./src/helpers/checkForCheckboxAll.js");
/* harmony import */ var _helpers_handleShift__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./helpers/handleShift */ "./src/helpers/handleShift.js");
/* harmony import */ var _helpers_setSelectedUniqueKey__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ./helpers/setSelectedUniqueKey */ "./src/helpers/setSelectedUniqueKey.js");
/* harmony import */ var _helpers_extendMap__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ./helpers/extendMap */ "./src/helpers/extendMap.js");
function _classCallCheck(instance, Constructor) { if (!(instance instanceof Constructor)) { throw new TypeError("Cannot call a class as a function"); } }

function _defineProperties(target, props) { for (var i = 0; i < props.length; i++) { var descriptor = props[i]; descriptor.enumerable = descriptor.enumerable || false; descriptor.configurable = true; if ("value" in descriptor) descriptor.writable = true; Object.defineProperty(target, descriptor.key, descriptor); } }

function _createClass(Constructor, protoProps, staticProps) { if (protoProps) _defineProperties(Constructor.prototype, protoProps); if (staticProps) _defineProperties(Constructor, staticProps); return Constructor; }

/*
 * Copyright (c) 2021 Anton Bagdatyev (Tonix)
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */






 // eslint-disable-next-line no-unused-vars

/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (function ($, window, document) {
  return (
    /*#__PURE__*/
    // eslint-disable-next-line @typescript-eslint/class-name-casing
    function () {
      function jQueryMultiselectCheckbox(element, options) {
        _classCallCheck(this, jQueryMultiselectCheckbox);

        this.$el = $(element);
        this.options = options;
        this.selectedMap = (0,_helpers_extendMap__WEBPACK_IMPORTED_MODULE_6__["default"])(new immutable_linked_ordered_map__WEBPACK_IMPORTED_MODULE_0__["default"]({
          mode: immutable_linked_ordered_map__WEBPACK_IMPORTED_MODULE_0__["default"].MODE.LIGHTWEIGHT
        }));
        this.selectedRanges = [];
        this.$lastSelected = void 0;
        this.$beforeLastSelected = void 0;
        this.init();
      }

      _createClass(jQueryMultiselectCheckbox, [{
        key: "init",
        value: function init() {
          this.options.checkboxes = (0,_helpers_emptyArrayIfNull__WEBPACK_IMPORTED_MODULE_2__["default"])(this.options.checkboxes);
          this.options.sync = (0,_helpers_emptyArrayIfNull__WEBPACK_IMPORTED_MODULE_2__["default"])(this.options.sync);
          this.initListeners();
          this.initState();
        }
      }, {
        key: "initListeners",
        value: function initListeners() {
          var self = this;

          this.selectCheckboxListenerFn = function (e) {
            self.selectCheckboxListener($(this), e);
          };

          this.$el.on("change", function (e) {
            return self.onChangeCheckboxSelectAll($(this), e);
          });

          if (this.options.eventsElement) {
            if (this.options.checkboxes) {
              $(this.options.eventsElement).on("click", this.options.checkboxes, this.selectCheckboxListenerFn);
            }

            if (this.options.sync) {
              $(this.options.eventsElement).on(this.options.syncEvent, this.options.sync, this.selectCheckboxListenerFn);
            }
          } else {
            if (this.options.checkboxes) {
              $(this.options.checkboxes).on("click", this.selectCheckboxListenerFn);
            }

            if (this.options.sync) {
              $(this.options.sync).on(this.options.syncEvent, this.selectCheckboxListenerFn);
            }
          }

          $(document).on("mousedown", this.options.sync, function (e) {
            if (e.shiftKey) {
              e.preventDefault();
            }
          });
        }
      }, {
        key: "initState",
        value: function initState() {
          if (this.$el.prop("checked")) {
            this.checkAll();
          } else {
            var $checkboxes = $(this.options.checkboxes);
            var $checked = $checkboxes.filter(":checked");

            if ($checkboxes.length === $checked.length) {
              this.checkAll();
            } else if ($checked.length) {
              $checked.each(function () {
                $(this).click();
              });
            }
          }
        }
      }, {
        key: "uncheckAll",
        value: function uncheckAll() {
          (0,_helpers_checkUncheck__WEBPACK_IMPORTED_MODULE_1__["default"])(this.$el, false, this);
        }
      }, {
        key: "checkAll",
        value: function checkAll() {
          (0,_helpers_checkUncheck__WEBPACK_IMPORTED_MODULE_1__["default"])(this.$el, true, this);
        }
      }, {
        key: "destroy",
        value: function destroy() {
          this.$el.off("change");

          if (this.options.eventsElement) {
            if (this.options.checkboxes) {
              $(this.options.eventsElement).off("click", this.options.checkboxes, this.selectCheckboxListenerFn);
            }

            if (this.options.sync) {
              $(this.options.eventsElement).off(this.options.syncEvent, this.options.sync, this.selectCheckboxListenerFn);
            }
          } else {
            if (this.options.checkboxes) {
              $(this.options.checkboxes).off("click", this.selectCheckboxListenerFn);
            }

            if (this.options.sync) {
              $(this.options.sync).off(this.options.syncEvent, this.selectCheckboxListenerFn);
            }
          }
        }
      }, {
        key: "selectCheckboxListener",
        value: function selectCheckboxListener($this, e) {
          var isCtrl = e.ctrlKey || e.metaKey;
          var isShift = e.shiftKey;
          var $target = $(e.target);
          var $checkbox = $this.is(this.options.checkboxes) ? $this : this.options.checkboxResolve($this, this);
          var $item = $this.is(this.options.sync) ? $this : this.options.itemResolve($this, this);
          var targetCheck = $target.is(this.options.checkboxes);

          if (targetCheck) {
            this.options.targetCheckStopPropagation(e, this);
          }

          var toToggle = targetCheck || isCtrl || isShift || this.options.syncEvenWithoutCtrl;

          if (toToggle) {
            this.toggle($, $checkbox, void 0);
            this.toggle($, $item, void 0, isShift);
          }

          (0,_helpers_checkForCheckboxAll__WEBPACK_IMPORTED_MODULE_3__["default"])($, toToggle, this);
        } // eslint-disable-next-line no-unused-vars

      }, {
        key: "onChangeCheckboxSelectAll",
        value: function onChangeCheckboxSelectAll($this, e) {
          var checked = $this.prop("checked");
          var self = this;
          $(this.options.checkboxes).each(function () {
            self.toggle($, $(this), checked);
          });
          $(this.options.sync).each(function () {
            self.toggle($, $(this), checked);
          });

          if (!checked) {
            this.selectedMap = (0,_helpers_extendMap__WEBPACK_IMPORTED_MODULE_6__["default"])(this.selectedMap.empty());
            this.selectedRanges = [];
            this.options.onAllUnchecked(this.selectedMap);
          } else {
            this.options.onAllChecked(this.selectedMap);
          }
        }
      }, {
        key: "toggle",
        value: function toggle($, $el, force, isShift) {
          if ($el.hasClass(this.options.checkedClassName)) {
            if (force !== true) {
              $el.removeClass(this.options.checkedClassName);

              if ($el.is(this.options.checkboxes)) {
                $el.prop("checked", false);
                var checkedKey = $el.data(this.options.checkedKeyDataAttributeName);

                if (this.selectedMap.get(checkedKey)) {
                  this.selectedMap = (0,_helpers_extendMap__WEBPACK_IMPORTED_MODULE_6__["default"])(this.selectedMap.unset(checkedKey));
                }
              }
            }
          } else {
            if (force === true || typeof force === "undefined") {
              $el.addClass(this.options.checkedClassName);

              if ($el.is(this.options.checkboxes)) {
                $el.prop("checked", true);
                (0,_helpers_setSelectedUniqueKey__WEBPACK_IMPORTED_MODULE_5__["default"])($el, this);
              }

              this.$beforeLastSelected = this.$lastSelected;
              this.$lastSelected = $el;
            }
          }

          if (isShift && ($el.is(this.options.checkboxes) && this.options.handleShiftForCheckbox || !$el.is(this.options.checkboxes) && !this.options.handleShiftForCheckbox)) {
            (0,_helpers_handleShift__WEBPACK_IMPORTED_MODULE_4__["default"])($, $el, this);
          }
        }
      }]);

      return jQueryMultiselectCheckbox;
    }()
  );
});

/***/ }),

/***/ "./node_modules/declarative-factory/src/index.js":
/*!*******************************************************!*\
  !*** ./node_modules/declarative-factory/src/index.js ***!
  \*******************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ declarativeFactory)
/* harmony export */ });
/* harmony import */ var js_utl__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! js-utl */ "./node_modules/js-utl/dist/es6/index.js");
/*
 * Copyright (c) 2021 Anton Bagdatyev (Tonix)
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */



/**
 * @type {Function}
 */
const testConditionFn = testCondition =>
  Boolean(
    typeof testCondition === "function" ? testCondition() : testCondition
  );

/**
 * A function to easily create interchangeable factories switching them
 * in a declarative way based on the given runtime parameters.
 *
 * @param {Function} tuples An array of tuples, each tuple being an array of two elements:
 *
 *                                - testCondition: A boolean value or a function returning a boolean value
 *                                                 which, if "true", instructs this function to return the "factory" factory
 *                                                 bound to this "testCondition".
 *                                                 Note that the first "factory" for which "testCondition" is truthy
 *                                                 will be returned and further tuples will be ignored;
 *
 *                                - factory: Anything. The value to return if "testCondition" is a truthy value
 *                                           or a function returning a truthy value.
 *
 *                            The last element of the returned tuples MAY not be a tuple array with two elements,
 *                            and in such case it will be treated as the default factory value to return if none
 *                            of the test conditions of the previous factories are satisfied.
 *                            If the last tuple is not a default value and none of the test conditions of the previous
 *                            factories are satisfied as well as for the factory of last tuple, then "undefined"
 *                            will be returned by this function.
 * @param {Array|undefined} deps Dependencies array to use.
 * @return {*} The first factory value for which the test is truthy or returns a truthy value, a default factory value,
 *             or "undefined".
 */
function declarativeFactory(tuples) {
  const factoryValue = (() => {
    let i = 0;
    // Loop through all the tuples except the last one (handled after this loop).
    for (; i < tuples.length - 1; i++) {
      const tuple = tuples[i];
      const [testCondition, factoryValue] = tuple;
      if (testConditionFn(testCondition)) {
        // Test condition for factory value is satisfied.
        return factoryValue;
      }
    }
    const lastTuple = tuples[i];
    if ((0,js_utl__WEBPACK_IMPORTED_MODULE_0__.isArray)(lastTuple) && lastTuple.length === 2) {
      const [testCondition, factoryValue] = lastTuple;
      if (testConditionFn(testCondition)) {
        // Test condition for last factory value is satisfied.
        return factoryValue;
      }

      // No default and no factory value satisfying a test condition.
      return void 0;
    } else {
      // Default factory value.
      return lastTuple;
    }
  })();

  return factoryValue;
}


/***/ }),

/***/ "./node_modules/immutable-linked-ordered-map/src/ImmutableLinkedOrderedMap.js":
/*!************************************************************************************!*\
  !*** ./node_modules/immutable-linked-ordered-map/src/ImmutableLinkedOrderedMap.js ***!
  \************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "ImmutableLinkedOrderedMap": () => (/* binding */ ImmutableLinkedOrderedMap),
/* harmony export */   "ImmutableLinkedOrderedMapMode": () => (/* binding */ ImmutableLinkedOrderedMapMode)
/* harmony export */ });
/* harmony import */ var pigretto__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! pigretto */ "./node_modules/pigretto/dist/es6/builtin/lazyObject.js");
/*
 * Copyright (c) 2021 Anton Bagdatyev (Tonix)
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */

/*!
 * =========================
 * ImmutableLinkedOrderedMap
 * =========================
 *
 * Copyright (c) 2021 Anton Bagdatyev (Tonix)
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */

/**
 * @author Anton Bagdatyev (Tonix) <antonytuft@gmail.com>
 */

/* ======================================================================================================== */



/**
 * @type {string}
 */
const MAP_TAG = "ImmutableLinkedOrderedMapEWoFkvQyQsM32wK4a5Kd";

/**
 * @type {string}
 */
const MAP_TAG_VALUE = true;

/**
 * @type {string}
 */
const DEFAULT_KEY_PROP_NAME = "id";

/**
 * @type {string}
 */
const MULTIWAY_MODE_INITIAL_MAP_TREE_DEPTH_VERSION = "";

/**
 * @type {string}
 */
const MULTIWAY_MODE_MAP_TREE_DEPTH_VERSION_SEPARATOR = "#";

/**
 * @type {Object}
 */
const ImmutableLinkedOrderedMapMode = {
  SINGLE: 1,
  MULTIWAY: 2,
  LIGHTWEIGHT: 3,
};

/**
 * @type {number}
 */
const DEFAULT_MAP_MODE = ImmutableLinkedOrderedMapMode.MULTIWAY;

/* ======================================================================================================== */

/**
 * Factory method to create a new immutable linked ordered map.
 *
 * @param {Array} [initialItems] Initial array of items to add to the map (defaults to an empty array).
 *                               Each item of the map MUST be an object with the following shape:
 *
 *                                   {
 *                                       [keyPropName]: keyPropValue,
 *                                       property: propertyValue,
 *                                       otherProperty: otherPropertyValue,
 *                                       ...
 *                                   }
 *
 *                               Where "[keyPropName]" is the name of the key property of the item.
 *                               In this case the map will map the value "keyPropValue" (the key)
 *                               to the whole item, which will be treated as the value of the map value, e.g.:
 *
 *                                   const map = new ImmutableLinkedOrderedMap({
 *                                       initialItems: [
 *                                           {
 *                                              id: 1, // <--- "[keyPropName] === 'id'"
 *                                              property: "A value",
 *                                              // ...
 *                                           },
 *                                           {
 *                                              id: 2,
 *                                              property: "Another value",
 *                                              // ...
 *                                           },
 *                                           // ...
 *                                       ]
 *                                   })
 *                                   map.get(2) // Will return: { id: 2, property: "Another value" }
 *
 *                               Or an object with a single property with the following shape (useful for primitives):
 *
 *                                   {
 *                                       key: value
 *                                   }
 *
 *                               In this case the map will map the key "key" to the value "value", and "value"
 *                               will be treated as the value of the map for the key "key", e.g.:
 *
 *                                   const map = new ImmutableLinkedOrderedMap({
 *                                       initialItems: [
 *                                           {
 *                                              key1: "abc"
 *                                           },
 *                                           {
 *                                              key2: "def"
 *                                           },
 *                                           // ...
 *                                       ]
 *                                   })
 *                                   map.get("key1") // Will return: "abc"
 *
 *                               Both items' shapes may coexist in the same map if client code is mixing pears with apples.
 *
 *                               Note that if an object with a single property which name is "[keyPropName]" is given,
 *                               then the whole object will be mapped and treated as the map's value, e.g. when "keyPropName === 'id'":
 *
 *                                   const map = new ImmutableLinkedOrderedMap({
 *                                       initialItems: [
 *                                           {
 *                                              id: 123
 *                                           },
 *                                           {
 *                                              id: 456
 *                                           },
 *                                           ...
 *                                       ]
 *                                   })
 *                                   map.get(123) // Will return: { id: 123 }
 *
 *                               The same item format is used for all mutation operations where an item or items may be given as parameters,
 *                               e.g. for "set", "replace", etc... .
 *
 * @param {string} [keyPropName] The name of the key property of an item which value should be used for the key of the map (defaults to "id").
 * @param {number} mode The mode of the map (a value of the enum-like object "ImmutableLinkedOrderedMapMode").
 *                      When the map is single mode ("ImmutableLinkedOrderedMapMode.SINGLE"), it will only allow a single mutation operation
 *                      per linked ordered immutable map instance.
 *                      This mode allows faster lookups as the version tree of the map will consist of only
 *                      one branch and should cover almost all practical use cases.
 *                      E.g.:
 *
 *                          const map = new ImmutableLinkedOrderedMap({
 *                              mode: ImmutableLinkedOrderedMapMode.SINGLE
 *                          })
 *                          const item = { id: 1, value: "A value" }
 *                          const newMap = map.set(item)
 *                          //const anotherNewMapFromMap = map.set({ id: 2, value: "Another value" }) // This line, if uncommented, will throw an error in single mode, as a mutation operation already occurred on "map"!
 *                          const anotherNewMapFromNewMap = newMap.set({ id: 2, value: "Another value" }) // This will work, as a mutation operation did not occur yet on "newMap".
 *                          const yetAnotherMap = newMap.set(item) // This works, because "newMap === yetAnotherMap" as the given item is the same and already existed in "newMap".
 *                          //const thisMapWouldNotWork = newMap.set({ id: 3, value: "Yet another value" }) // This will throw an error in single mode as for "anotherNewMapFromMap".
 *                          // ...
 *
 *                      The version tree of this map in single mode will look like the following:
 *
 *                          map (Initial version)
 *                             \
 *                             newMap/yetAnotherMap (Second version, remember, in this case "newMap === yetAnotherMap")
 *                                \
 *                                anotherNewMapFromNewMap (Third version)
 *
 *                      The other available mode is multiway mode ("ImmutableLinkedOrderedMapMode.MULTIWAY").
 *                      This is the default mode.
 *                      In this mode, multiple mutation operations can occur on the same map, each leading to a different
 *                      branch.
 *                      Lookups may be slower, particularly if a lot of mutation operations have happened,
 *                      as the structural sharing code will need to determine the right branch of the version tree
 *                      given a map which tries to lookup its data for a given key.
 *                      E.g.:
 *
 *                          const map = new ImmutableLinkedOrderedMap() // "ImmutableLinkedOrderedMapMode.MULTIWAY" is the default mode.
 *                          const newMap = map.set({ id: 1, value: "A value" })
 *                          const anotherNewMapFromMap = map.set({ id: 2, value: "Another value" }) // This will work in multiway mode, but not in single mode.
 *                          const anotherNewMapFromNewMap = newMap.set({ id: 2, value: "Another value" })
 *                          // ...
 *
 *                      The version tree of this map in multiway mode will look like the following:
 *
 *                                                                             map (Initial version, root node)
 *                                                                            /   \
 *                                                                           /     newMap (Second version, first branch)
 *                                                                          /        \
 *                          (Third version, second branch) anotherNewMapFromMap       \
 *                                                                                    anotherNewMapFromNewMap (Fourth version, first branch)
 *
 *                      This mode should be used only if client code needs to perform several mutation operations on the same map instance over time.
 *                      Also, a single mode map cannot become a multiway and viceversa.
 *
 *                      The last available mode is lightweight mode ("ImmutableLinkedOrderedMapMode.LIGHTWEIGHT").
 *                      This mode is the most restrictive and does not allow to perform any operation on a map on which a mutation operation
 *                      has occurred (basically once a mutation operation occurs on a map, it becomes useless and only the new map
 *                      could be used further).
 *                      The advantage is that a map in this mode will have the fastest lookups, as in this mode there isn't any version tree.
 *                      E.g.:
 *
 *                          const map = new ImmutableLinkedOrderedMap({
 *                              mode: ImmutableLinkedOrderedMapMode.LIGHTWEIGHT
 *                          })
 *                          const item = { id: 1, value: "A value" }
 *                          const newMap = map.set(item)
 *                          //const anotherNewMapFromMap = map.set({ id: 2, value: "Another value" }) // This line, if uncommented, will throw an error in lightweight mode, as with single mode.
 *                          //const value = map.get(1) // In lightweight mode, even this line will throw an error, as after a mutation operation occurs on a map, it will become useless.
 *                          const valueOfNewMap = newMap.get(1) // This works.
 *                          const anotherNewMapFromNewMap = newMap.unset(1) // This will also work, and from this point on, "newMap" will become useless and a further operation will have to be performed on "anotherNewMapFromNewMap"
 *                          // ...
 *
 *                      Note that the mentioned version tree is only an abstraction, a conceptual idea behind the structural sharing code of the map.
 *                      Internally, the structural sharing code does not create any tree.
 *                      For multiway mode, though, the structural sharing code keeps track of the version of the map in a string
 *                      which is then used to determine if a map is a descendant of another one during lookup operations.
 * @return {ImmutableLinkedOrderedMap} The new immutable linked ordered map.
 */
function newImmutableLinkedOrderedMap({
  initialItems = [],
  keyPropName = DEFAULT_KEY_PROP_NAME,
  mode = DEFAULT_MAP_MODE,
  lazy = false,
} = {}) {
  mode =
    (ImmutableLinkedOrderedMapForMode[mode] && mode) ||
    (mode = DEFAULT_MAP_MODE);
  let map = newMapFromMode(mode);
  hydrateNew.call(map, { keyPropName, mode });
  if (lazy) {
    map = newLazyMap(map, initialItems);
  } else {
    appendInitialItemsToMap(map, initialItems);
  }
  return map;
}

/* ======================================================================================================== */

/**
 * Creates a new map given the mode.
 *
 * @param {number} mode The mode of the map (a property value of the enum-like object "ImmutableLinkedOrderedMapMode").
 * @return {ImmutableLinkedOrderedMap} A new immutable linked ordered map.
 */
function newMapFromMode(mode) {
  const ImmutableLinkedOrderedMapClass =
    ImmutableLinkedOrderedMapForMode[mode].ImmutableLinkedOrderedMapClass;
  return new ImmutableLinkedOrderedMapClass();
}

/* ======================================================================================================== */

/**
 * Defines a getter and optional setter on an object's property.
 *
 * @param {Object} obj An object (may be a prototype).
 * @param {string} propname The property name.
 * @param {Function} getfn Getter function.
 * @param {Function} setfn Setter function.
 * @return {undefined}
 */
function prop(obj, propname, getfn, setfn) {
  const propObj = {};
  propObj[propname] = {
    get: getfn,
    set: setfn,
  };
  Object.defineProperties(obj, propObj);
}

/**
 * Makes a new node (used internally).
 *
 * @param {Object} previous Previous node.
 * @param {Object} next Next node.
 * @param {*} element The element of the node.
 * @return {Object} The node.
 */
function makeNode(previous, next, element) {
  return {
    previous,
    next,
    element,
  };
}

/* ======================================================================================================== */

/**
 * Hydrates a new map when the client code creates it.
 *
 * @param {string} keyPropName Name of the property to use for the keys of the map.
 * @param {number} mode The mode of the map (a property value of the enum-like object "ImmutableLinkedOrderedMapMode").
 * @return {undefined}
 */
function hydrateNew({ keyPropName, mode }) {
  /**
   * Each new map created from client code has a reference to a shared data structure called heap map.
   *
   * Beside the fancy name, it basically stores all the nodes holding the values of the map
   * linked together in order to maintain insertion order and maps all the keys which were ever added
   * to all the maps forked from the original one.
   *
   * The heap map maps keys to depths. Each time a new map is forked from a previous one,
   * the depth of the new forked map increases.
   *
   * For single mode each depth in turn maps directly to the node representing the value
   * of that key for that particular map. This allows quick lookups.
   *
   * For multiway mode each depth additionally maps to a stack which associates map versions for that depth
   * in the order they have been added to the version tree for that depth.
   * It may sound complicated, but basically each version represents a mutation on a map at any given time.
   * Lookups are slower because in this case when calling "map.get('key')", the structural sharing code must
   * determine if the given version in the stack of the heap map for that key and depth is an ancestor
   * of the version of the map on which "get" is called.
   */
  const heapMap = {};
  hydrate.call(this, {
    heapMap,
    depth: 0,
    length: 0,
    keyPropName,
    mode,
  });

  const hydrateMode = ImmutableLinkedOrderedMapForMode[mode].hydrate;
  hydrateMode && hydrateMode.call(this);
}

/**
 * Hydrates a map.
 *
 * @param {Object} heapMap The heap map is an object which maps a key to a linked ordered map
 *                         which in turn maps a depth of the version tree to a stack
 *                         (another linked oredered map used as a stack) of all the maps with that depth
 *                         with the first map being the last created map for that depth.
 * @param {number|undefined} depth The depth of the version tree.
 * @param {number|undefined} length The length of the map.
 * @param {string} keyPropName Name of the property to use for the keys of the map.
 * @param {number} mode The mode of the map (a value of the enum-like object "ImmutableLinkedOrderedMapMode").
 * @param {Object|undefined} head Head of the map.
 * @param {Object|undefined} tail Tail of the map.
 * @return {undefined}
 */
function hydrate({
  heapMap,
  depth,
  length,
  keyPropName,
  mode,
  head,
  tail,
  ancestorMap,
} = {}) {
  prop(this, "heapMap", () => heapMap);
  this.depth = depth || 0;
  this.length = length || 0;
  this.keyPropName = keyPropName;
  prop(this, "mode", () => mode);
  this.head = head || null;
  this.tail = tail || null;
  this.ancestorMap = ancestorMap || null;
  this.shouldNextForEachBreak = false;
  this.forEachNextFn = void 0;
  this.change = null;
  this[MAP_TAG] = MAP_TAG_VALUE;
}

/**
 * Returns a node or undefined if the node is an orphan node.
 *
 * @param {Object|undefined} node
 * @return {*} The node if it is valid and is not an orphan node, or "undefined" otherwise.
 */
function nodeOrUndefined(node) {
  return node && !isOrphanNode(node) ? node : void 0;
}

/**
 * Tests if a node is an orphan node or not.
 *
 * @param {Object} node The node to test.
 * @return {boolean} "true" if the node is orphan, "false" otherwise.
 */
function isOrphanNode(node) {
  return node.isOrphanNode;
}

/**
 * Creates a new lazy map which appends its initial items only when the map is used for the very first time
 * (by calling a method, accessing or setting one of its properties).
 *
 * @param {ImmutableLinkedOrderedMap} map The map.
 * @param {Array} items Array of items to append.
 * @return {ImmutableLinkedOrderedMap} The lazy map.
 */
function newLazyMap(map, initialItems = []) {
  map.length = initialItems.length;
  const lazyMap = (0,pigretto__WEBPACK_IMPORTED_MODULE_0__["default"])(map, {
    onceCallback: () => {
      map.length = 0;
      appendInitialItemsToMap(map, initialItems);
    },
  });
  return lazyMap;
}

/**
 * Mutates a map's shared structure (structural sharing) by appending items to the map.
 * This internal function assumes that the given map is empty.
 *
 * @param {ImmutableLinkedOrderedMap} map The map.
 * @param {Array} items Array of items to append.
 * @return {undefined}
 */
function appendInitialItemsToMap(map, items = []) {
  const { mode, keyPropName } = map;

  const keysMap = {};
  const length = items.length;
  if (length) {
    // There's at least one element.
    // Update tail.
    const lastItem = items[length - 1];
    const { key: tailKey, value: tailValue } = keyValueForItem(
      keyPropName,
      lastItem
    );

    // New tail.
    const newTail = ImmutableLinkedOrderedMapForMode[
      mode
    ].makeImmutableLinkedOrderedMapNode(map, null, null, tailKey, tailValue);
    ImmutableLinkedOrderedMapForMode[mode].updateHeapMap(map, newTail);

    // Previous tail.
    const previousTail = map.tail;
    map.tail = newTail;

    // The tail does not change.
    keysMap[tailKey] || (map.length++ && (keysMap[tailKey] = true));

    if (length > 1) {
      // There are at least two items.
      let lastNode = map.tail;

      // Looping on all the nodes below the last one, in reverse order to ignore previous duplicate keys.
      for (let i = length - 2; i >= 0; i--) {
        const item = items[i];
        const { key, value } = keyValueForItem(keyPropName, item);
        if (!keysMap[key]) {
          const node = ImmutableLinkedOrderedMapForMode[
            mode
          ].makeImmutableLinkedOrderedMapNode(map, null, null, key, value);
          ImmutableLinkedOrderedMapForMode[mode].updateHeapMap(map, node);
          ImmutableLinkedOrderedMapForMode[mode].bindNodes(map, node, lastNode);
          lastNode = node;
          map.length++ && (keysMap[key] = true);
        }
      }

      if (map.head === null) {
        map.head = lastNode;
      }
    } else {
      if (previousTail === null) {
        // "newTail" is the first node ever of this map.
        map.head = newTail;
      } else {
        // Only one item, i.e. only the one in "newTail".
        // There was a previous tail, though.
        ImmutableLinkedOrderedMapForMode[mode].bindNodes(
          map,
          previousTail,
          map.tail
        );
      }
    }
  }
}

/**
 * Gets the key and the value of an item.
 *
 * @param {string} keyPropName The name of the key property to use for the items of the map.
 * @param {Object} item An item.
 * @return {Object} An object with a "key" and a "value" property.
 */
function keyValueForItem(keyPropName, item) {
  let key;
  let value;
  if (item) {
    key = item[keyPropName];
    if (typeof key !== "undefined") {
      value = item;
    } else {
      for (const prop in item) {
        key = prop;
        break;
      }
      value = item[key];
    }
  }
  return {
    key,
    value,
  };
}

/**
 * Stores a change on a map.
 *
 * @param {ImmutableLinkedOrderedMap} map The map.
 * @param {string} changeLabel The label identifying the change.
 * @param {*} [payload] The optional payload of the change.
 * @return {undefined}
 */
function mapChange(map, changeLabel, payload = true) {
  map.change = {
    [changeLabel]: payload,
  };
}

/**
 * @type {boolean}
 */
let isFork = false;

/**
 * Forks a new map from an existent one, hydrating it as needed.
 *
 * @param {ImmutableLinkedOrderedMap} map The map from which to fork.
 * @return {ImmutableLinkedOrderedMap} The new forked map instance.
 */
function forkMap(map) {
  isFork = true;
  const newMap = newMapFromMode(map.mode);
  isFork = false;
  hydrate.call(newMap, {
    heapMap: map.heapMap,
    depth: map.depth,
    length: map.length,
    keyPropName: map.keyPropName,
    mode: map.mode,
    head: map.head,
    tail: map.tail,
    ancestorMap: map,
  });
  newMap.depth++;

  const fork = ImmutableLinkedOrderedMapForMode[map.mode].fork;
  fork && fork(map, newMap);
  return newMap;
}

/**
 * Adds an orphan node with "undefined" as a value to the given map for the given key.
 *
 * @param {ImmutableLinkedOrderedMap} map The map.
 * @param {string|number} key The key.
 * @return {undefined}
 */
function addImmutableLinkedOrderedMapOrphanNode(map, key) {
  const newNode = ImmutableLinkedOrderedMapForMode[
    map.mode
  ].makeImmutableLinkedOrderedMapNode(map, null, null, key, void 0);
  newNode.isOrphanNode = true;
  ImmutableLinkedOrderedMapForMode[map.mode].updateHeapMap(map, newNode);
}

/**
 * @type {boolean}
 */
let creatingNew = false;

/**
 * Base class of an immutable linked ordered map.
 *
 * This is the only class exposed to the code of the client using this library.
 * Internally it uses the factory function "newImmutableLinkedOrderedMap"
 * to return the correct subclass given the passed options.
 */
class ImmutableLinkedOrderedMap {
  /**
   * Constructor.
   *
   * @constructor
   *
   * @param {Object} options Options.
   */
  constructor(options) {
    if (isFork) {
      return;
    }
    if (!creatingNew) {
      creatingNew = true;
      const instance = newImmutableLinkedOrderedMap(options);
      return instance;
    } else {
      creatingNew = false;
      return this;
    }
  }

  /**
   * Getter for "Symbol.toStringTag" used for "Object.prototype.toString".
   */
  get [Symbol.toStringTag]() {
    return this.constructor.name;
  }

  /**
   * Sets a value or multiple values and returns a new updated version of this map
   * with the new values set.
   *
   * The same map instance will be returned if for the given item or all the given items
   * the values at their respective keys are the same (using the triple equality operator "===").
   *
   * @param {Object|Array} items An object representing the item to set or an array of items to set (same structure as for the the "initialItems" option
   *                             when creating a new map from scratch).
   * @param {boolean} prependMissing If set to true and the item is not in the map, it will be prepended instead of being appended.
   * @return {ImmutableLinkedOrderedMap} A new immutable linked ordered map or this map if nothing has changed.
   */
  set(items, prependMissing = false) {
    const itemsIsArray = Array.isArray(items);
    if (
      (itemsIsArray &&
        // items is an empty array.
        !items.length) ||
      // items is either a non-empty array or it's not an array.
      // If it's falsy, then return this same map.
      !items
    ) {
      // No valid item/items provided.
      return this;
    } else if (
      // If it's not falsy and is not an array, wrap it in an array.
      !itemsIsArray
    ) {
      items = [items];
    }

    // Initially, assume that all items exist in the map, therefore there isn't a new version yet.
    let map;
    let justForked = false;
    const inserted = [];
    const updated = [];
    const keysMap = {};

    let i = items.length - 1;
    const towards = 0;
    let nodes;
    let appendOperationOldTail;
    let lastPrepend;
    let loopEnd;
    const updateI = () => i--;
    const valid = () => i >= towards;
    const updateNodesBinding = ({ map, newNode, newNext }) => {
      ImmutableLinkedOrderedMapForMode[map.mode].bindNodes(
        map,
        newNode,
        newNext
      );
    };
    // eslint-disable-next-line @typescript-eslint/no-empty-function
    loopEnd = () => {}; // No-op.
    if (!prependMissing) {
      // Append missing.
      let firstPrepend = true;
      nodes = (map, newNode) => {
        // Append.
        let ret;
        if (firstPrepend) {
          // Store old tail before first appending operation.
          firstPrepend = false;
          if (map.tail !== null) {
            appendOperationOldTail = map.tail;
          }
          map.tail = newNode;
          ret = false;
        } else {
          ret = {
            newNext: lastPrepend,
          };
        }
        lastPrepend = newNode;
        return ret;
      };
      loopEnd = () => {
        if (lastPrepend) {
          if (map.head === null) {
            map.head = lastPrepend;
          }
          if (appendOperationOldTail) {
            ImmutableLinkedOrderedMapForMode[map.mode].bindNodes(
              map,
              appendOperationOldTail,
              lastPrepend
            );
          }
        }
      };
    } else {
      // Prepend missing.
      nodes = (map, newNode) => {
        // Prepend.
        const oldHead = map.head;
        map.head = newNode;
        return {
          newNext: oldHead,
        };
      };
    }
    for (; valid(); updateI()) {
      const item = items[i];
      const { key, value } = keyValueForItem(this.keyPropName, item);
      if (keysMap[key]) {
        // Duplicate key, ignore.
        continue;
      }
      keysMap[key] = true;

      const node = ImmutableLinkedOrderedMapForMode[this.mode].lookup(
        this,
        key
      );

      if (!node) {
        // Key is missing, either a new tail or a new head is needed.
        map =
          (map && ((justForked = false) || map)) ||
          ((justForked = true) && forkMap(this));
        const newNode = ImmutableLinkedOrderedMapForMode[
          map.mode
        ].makeImmutableLinkedOrderedMapNode(map, null, null, key, value);
        ImmutableLinkedOrderedMapForMode[map.mode].updateHeapMap(map, newNode);

        if (map.tail === null && prependMissing) {
          // Initially (i.e. for the first element), the tail is the head when we are prepending.
          map.tail = newNode;
          map.head = newNode;
        } else {
          const nodesRet = nodes(map, newNode);
          if (nodesRet) {
            const { newNext } = nodesRet;
            updateNodesBinding({ map, newNode, newNext });
          }
        }

        map.length++;
        inserted.unshift({
          key,
          value,
        });
      } else if (node.element.value !== value) {
        // Existent key, but value is different.
        map =
          (map && (justForked = false)) ||
          map ||
          ((justForked = true) && forkMap(this));

        const previous =
          (!justForked &&
            ImmutableLinkedOrderedMapForMode[map.mode].findMapNodeByDirection(
              map,
              node,
              "previous"
            )) ||
          ImmutableLinkedOrderedMapForMode[map.mode].findMapNodeByDirection(
            this,
            node,
            "previous"
          );

        const next =
          (!justForked &&
            ImmutableLinkedOrderedMapForMode[map.mode].findMapNodeByDirection(
              map,
              node,
              "next"
            )) ||
          ImmutableLinkedOrderedMapForMode[map.mode].findMapNodeByDirection(
            this,
            node,
            "next"
          );

        const newNode = ImmutableLinkedOrderedMapForMode[
          map.mode
        ].makeImmutableLinkedOrderedMapNode(map, null, null, key, value);
        ImmutableLinkedOrderedMapForMode[this.mode].updateHeapMap(map, newNode);

        // Check for head.
        if (previous !== null) {
          // There's a previous node, "node" is not a head.
          ImmutableLinkedOrderedMapForMode[map.mode].bindNodes(
            map,
            previous,
            newNode
          );
        } else {
          // "node" was a head. Update head.
          map.head = newNode;
        }

        // Check for tail.
        if (next !== null) {
          // There's a next node, "node" is not a tail.
          ImmutableLinkedOrderedMapForMode[map.mode].bindNodes(
            map,
            newNode,
            next
          );
        } else if (appendOperationOldTail) {
          // Tail has been updated during the first append operation.
          appendOperationOldTail = newNode;
        } else {
          // "node" was a tail. Update tail.
          map.tail = newNode;
        }

        updated.unshift({
          key,
          value,
        });
      }
    }
    loopEnd();

    if (!map) {
      // Nothing has changed.
      return this;
    } else {
      // Store the change on the new map and return it.
      mapChange(map, "set", {
        inserted,
        updated,
        prependMissing,
      });
      return map;
    }
  }

  /**
   * Replaces an item in the map, optionally appending or prepending it if "oldKey" is missing.
   *
   * The same map instance will be returned if for the given item
   * the value at "oldKey" is the same (using the triple equality operator "===").
   * Beware that in this case, "oldKey" will remain in the map even if the given "item" is the same
   * but was mutated and now has a different key!
   * Also, the same map instance will be returned if "oldKey" is missing and "addMissing" is true
   * and there's already the same item at the key of "item".
   *
   * Note that if the key of "item" is different from "oldKey" and it already exists in the map,
   * then the value of item will replace the underlying value for that same key
   * if the values are different (using the triple equality operator "===")
   * or a new untouched forked map will be returned.
   * This is to prevent duplicate keys in the map.
   *
   * @param {string|number} oldKey The old key to replace.
   * @param {Object} item The item (same structure as for the the "initialItems" option
   *                      when creating a new map from scratch).
   * @param {boolean} addMissing If set to true and "oldKey" is missing, will append the item if missing.
   *                             If the key of the item already exists in the map, the value at that key will be replaced
   *                             if the values are different (using the triple equality operator "===")
   * @param {boolean} prependMissing If set to true with "addMissing" set to true and "oldKey" is missing,
   *                                 will prepend the item, instead of appending it.
   * @return {ImmutableLinkedOrderedMap} A new immutable linked ordered map or this map if nothing has changed.
   */
  replace(oldKey, item, addMissing = false, prependMissing = false) {
    const node = ImmutableLinkedOrderedMapForMode[this.mode].lookup(
      this,
      oldKey
    );
    let map;
    let wasInserted = false;
    let wasUpdated = false;
    let hadExistentNodeForKey = false;
    let key;
    let value;

    if (!node) {
      // The old key does not exist.
      if (addMissing) {
        // Add missing key with new item.
        map = map || forkMap(this);

        // This needs to happen before updating the heap map so that all modes work!
        const existentNodeForKey = ImmutableLinkedOrderedMapForMode[
          this.mode
        ].lookup(this, key);

        const keyValue = keyValueForItem(map.keyPropName, item);
        key = keyValue.key;
        value = keyValue.value;

        const newNode = ImmutableLinkedOrderedMapForMode[
          map.mode
        ].makeImmutableLinkedOrderedMapNode(map, null, null, key, value);
        ImmutableLinkedOrderedMapForMode[map.mode].updateHeapMap(map, newNode);

        if (existentNodeForKey) {
          hadExistentNodeForKey = true;
          if (existentNodeForKey.element.value !== value) {
            if (existentNodeForKey === map.head) {
              map.head = newNode;
            }
            if (existentNodeForKey === map.tail) {
              map.tail = newNode;
            }
            const existentNodeForKeyPrevious = ImmutableLinkedOrderedMapForMode[
              this.mode
            ].findMapNodeByDirection(this, existentNodeForKey, "previous");
            const existentNodeForKeyNext = ImmutableLinkedOrderedMapForMode[
              this.mode
            ].findMapNodeByDirection(this, existentNodeForKey, "next");
            if (existentNodeForKeyPrevious !== null) {
              ImmutableLinkedOrderedMapForMode[map.mode].bindNodes(
                map,
                existentNodeForKeyPrevious,
                newNode
              );
            }
            if (existentNodeForKeyNext !== null) {
              ImmutableLinkedOrderedMapForMode[map.mode].bindNodes(
                map,
                newNode,
                existentNodeForKeyNext
              );
            }
            wasUpdated = true;
          }
        }

        if (!hadExistentNodeForKey) {
          map.length++;
          wasInserted = true;
          if (!prependMissing) {
            // Append new item.
            const newPrevious = map.tail;
            map.tail = newNode;

            if (newPrevious === null) {
              map.head = map.tail;
            } else {
              ImmutableLinkedOrderedMapForMode[map.mode].bindNodes(
                map,
                newPrevious,
                newNode
              );
            }
          } else {
            // Prepend new item.
            const newNext = map.head;
            map.head = newNode;

            if (newNext === null) {
              map.tail = map.head;
            } else {
              ImmutableLinkedOrderedMapForMode[map.mode].bindNodes(
                map,
                newNode,
                newNext
              );
            }
          }
        }
      }
    } else {
      // The old key exists.
      const keyValue = keyValueForItem(this.keyPropName, item);
      key = keyValue.key;
      value = keyValue.value;

      if (node.element.value !== value) {
        // Node already exists, it must be overridden in the new map.
        map = map || forkMap(this);

        const newNode = ImmutableLinkedOrderedMapForMode[
          map.mode
        ].makeImmutableLinkedOrderedMapNode(map, null, null, key, value);

        const previous = ImmutableLinkedOrderedMapForMode[
          this.mode
        ].findMapNodeByDirection(this, node, "previous");
        const next = ImmutableLinkedOrderedMapForMode[
          this.mode
        ].findMapNodeByDirection(this, node, "next");

        if (oldKey + "" !== key + "") {
          // "oldKey" differs from "key".
          addImmutableLinkedOrderedMapOrphanNode(map, oldKey);

          // This needs to happen before updating the heap map so that all modes work!
          const existentNodeForKey = ImmutableLinkedOrderedMapForMode[
            this.mode
          ].lookup(this, key);
          if (existentNodeForKey) {
            hadExistentNodeForKey = true;
            if (existentNodeForKey.element.value !== value) {
              if (existentNodeForKey === map.head) {
                map.head = newNode;
              }
              if (existentNodeForKey === map.tail) {
                map.tail = newNode;
              }
              const existentNodeForKeyPrevious = ImmutableLinkedOrderedMapForMode[
                this.mode
              ].findMapNodeByDirection(this, existentNodeForKey, "previous");
              const existentNodeForKeyNext = ImmutableLinkedOrderedMapForMode[
                this.mode
              ].findMapNodeByDirection(this, existentNodeForKey, "next");
              if (existentNodeForKeyPrevious !== null) {
                if (node !== existentNodeForKeyPrevious) {
                  ImmutableLinkedOrderedMapForMode[map.mode].bindNodes(
                    map,
                    existentNodeForKeyPrevious,
                    newNode
                  );
                } else if (previous !== null) {
                  ImmutableLinkedOrderedMapForMode[map.mode].bindNodes(
                    map,
                    previous,
                    newNode
                  );
                } else {
                  map.head = newNode;
                }
              }
              if (existentNodeForKeyNext !== null) {
                if (node !== existentNodeForKeyNext) {
                  ImmutableLinkedOrderedMapForMode[map.mode].bindNodes(
                    map,
                    newNode,
                    existentNodeForKeyNext
                  );
                } else if (next !== null) {
                  ImmutableLinkedOrderedMapForMode[map.mode].bindNodes(
                    map,
                    newNode,
                    next
                  );
                } else {
                  map.tail = newNode;
                }
              }
            }
            map.length--;
          }
        }

        if (!hadExistentNodeForKey) {
          if (previous === null) {
            // It's a head.
            map.head = newNode;
            if (next === null) {
              // It's also a tail.
              map.tail = newNode;
            } else {
              ImmutableLinkedOrderedMapForMode[map.mode].bindNodes(
                map,
                newNode,
                next
              );
            }
          } else if (next === null) {
            // It's a tail and has a previous node.
            map.tail = newNode;
            ImmutableLinkedOrderedMapForMode[map.mode].bindNodes(
              map,
              previous,
              newNode
            );
          } else {
            // It's a node in between.
            ImmutableLinkedOrderedMapForMode[map.mode].bindNodes(
              map,
              previous,
              newNode
            );
            ImmutableLinkedOrderedMapForMode[map.mode].bindNodes(
              map,
              newNode,
              next
            );
          }
        }

        ImmutableLinkedOrderedMapForMode[map.mode].updateHeapMap(map, newNode);
        wasUpdated = true;
      }
    }

    if (!map) {
      // Nothing has changed.
      return this;
    } else {
      // Store the change on the new map and return it.
      mapChange(map, "replace", {
        oldKey,
        key,
        value,
        wasInserted,
        wasUpdated,
        hadExistentNodeForKey,
        prependMissing,
      });
      return map;
    }
  }

  /**
   * Unsets a value, multiple values or an item by its key and returns a new updated version of this map
   * without those value/values.
   *
   * The same map instance will be returned if the given key or all the keys of the given items or item do not exist.
   *
   * @param {Object|Array|string|number} itemsOrKey An object or an array of objects to unset, or a key to unset.
   * @return {ImmutableLinkedOrderedMap} A new immutable linked ordered map or this map if nothing has changed.
   */
  unset(itemsOrKey) {
    const typeOfItemsOrKey = typeof itemsOrKey;
    if (typeOfItemsOrKey === "number" || typeOfItemsOrKey === "string") {
      return this.unsetKey(itemsOrKey);
    }

    const keyPropName = this.keyPropName;
    if (Array.isArray(itemsOrKey)) {
      // eslint-disable-next-line @typescript-eslint/no-this-alias
      let map = this;
      for (const item of itemsOrKey) {
        map = map.unsetKey(item[keyPropName]);
      }
      return map;
    }
    return this.unsetKey(itemsOrKey[keyPropName]);
  }

  /**
   * Unsets an item by its key.
   *
   * The same map instance will be returned if the given key does not exist.
   *
   * @param {string|number} key The key to unset.
   * @return {ImmutableLinkedOrderedMap} A new immutable linked ordered map or this map if nothing has changed.
   */
  unsetKey(key) {
    const node = ImmutableLinkedOrderedMapForMode[this.mode].lookup(this, key);
    let map;
    let value;

    if (node) {
      // Node exists and therefore must be unset in the new map.
      map = map || forkMap(this);
      value = node.element.value;

      // This new node is an orphan one.
      // Lookups to it in the context of this new map will lead to "undefined".
      addImmutableLinkedOrderedMapOrphanNode(map, key);

      const previous = ImmutableLinkedOrderedMapForMode[
        map.mode
      ].findMapNodeByDirection(this, node, "previous");
      const next = ImmutableLinkedOrderedMapForMode[
        map.mode
      ].findMapNodeByDirection(this, node, "next");

      if (previous === null) {
        // It's a head that's being removed.
        if (next === null) {
          // It's also a tail.
          map.head = null;
          map.tail = null;
          map.length = 0;
        } else {
          // It has a next node.
          const newHeadNode = ImmutableLinkedOrderedMapForMode[
            map.mode
          ].makeImmutableLinkedOrderedMapNode(
            map,
            null,
            null,
            next.element.key,
            next.element.value
          );
          ImmutableLinkedOrderedMapForMode[map.mode].updateHeapMap(
            map,
            newHeadNode
          );
          map.head = newHeadNode;
          const nextNext = ImmutableLinkedOrderedMapForMode[
            map.mode
          ].findMapNodeByDirection(this, next, "next");
          if (nextNext === null) {
            // The next node is a tail.
            map.tail = newHeadNode;
          } else {
            ImmutableLinkedOrderedMapForMode[map.mode].bindNodes(
              map,
              newHeadNode,
              nextNext
            );
          }
          map.length--;
        }
      } else if (next === null) {
        // It's a tail that's being removed and it has a previous node.
        const newTailNode = ImmutableLinkedOrderedMapForMode[
          map.mode
        ].makeImmutableLinkedOrderedMapNode(
          map,
          null,
          null,
          previous.element.key,
          previous.element.value
        );
        ImmutableLinkedOrderedMapForMode[map.mode].updateHeapMap(
          map,
          newTailNode
        );
        map.tail = newTailNode;
        const previousPrevious = ImmutableLinkedOrderedMapForMode[
          map.mode
        ].findMapNodeByDirection(this, previous, "previous");
        if (previousPrevious === null) {
          // The previous node is a head.
          map.head = newTailNode;
        } else {
          ImmutableLinkedOrderedMapForMode[map.mode].bindNodes(
            map,
            previousPrevious,
            newTailNode
          );
        }
        map.length--;
      } else {
        // It's a node in between.
        ImmutableLinkedOrderedMapForMode[map.mode].bindNodes(
          map,
          previous,
          next
        );
        map.length--;
      }
    }

    if (!map) {
      // Nothing has changed.
      return this;
    } else {
      mapChange(map, "unset", {
        key,
        value,
      });
      return map;
    }
  }

  /**
   * Empties the map.
   *
   * The same map instance will be returned if the map is already empty.
   *
   * @return {ImmutableLinkedOrderedMap} A new emptied immutable linked ordered map.
   */
  empty() {
    if (this.length <= 0) {
      // The map is already empty. Nothing has changed.
      return this;
    }

    const map = new ImmutableLinkedOrderedMap({
      initialItems: [],
      keyPropName: this.keyPropName,
      mode: this.mode,
    });
    map.length = 0;
    map.depth = this.depth + 1;
    map.ancestorMap = this;
    mapChange(map, "empty");
    return map;
  }

  /**
   * Lookups a value in the map.
   *
   * @param {string|number} key The key to lookup.
   * @return {*} The associated value, or undefined, if the given key is missing.
   */
  get(key) {
    const node = ImmutableLinkedOrderedMapForMode[this.mode].lookup(this, key);
    if (node) {
      return node.element.value;
    }
    return void 0;
  }

  /**
   * Returns a key/value pair of the first item in the map.
   *
   * @return {Object|undefined} An object with the property "key" (the key of the item in the map) and "value"
   *                            (the value of the item in the map).
   *                            If the map is empty, "undefined" will be returned.
   */
  first() {
    if (this.head) {
      return {
        key: this.head.element.key,
        value: this.head.element.value,
      };
    } else {
      return void 0;
    }
  }

  /**
   * Returns a key/value pair of the last item in the map.
   *
   * @return {Object|undefined} An object with the property "key" (the key of the item in the map) and "value"
   *                            (the value of the item in the map).
   *                            If the map is empty, "undefined" will be returned.
   */
  last() {
    if (this.tail) {
      return {
        key: this.tail.element.key,
        value: this.tail.element.value,
      };
    } else {
      return void 0;
    }
  }

  /**
   * Returns a range of key/value pairs before the given key up to a max number of items
   * (counting the given key if included).
   *
   * @param {string|number} key The key to lookup and use for the creation of the range.
   * @param {number} maxNumberOfItems Max number of key/value pairs to return for the range including or not including the item at the given key
   *                                  (depending on the value of the "itemAtKeyIncluded" opton).
   *                                  By default, all items before the given key are included in the returned range of key/value pairs.
   * @param {boolean} itemAtKeyIncluded Whether or not to include the item at the given key in the range (by default the item is included).
   * @return {Array} An array which represents the range, each item being a key/value pair representing an item of the map.
   *                 An empty array if the given key does not exist in the map or the given key does exist in the map but is not included
   *                 in the range and its item is the first of the map.
   *                 An empty array is also returned if "maxNumberOfItems" is less than or equal to 0.
   */
  rangeBefore(key, maxNumberOfItems = Infinity, itemAtKeyIncluded = true) {
    const node = ImmutableLinkedOrderedMapForMode[this.mode].lookup(this, key);
    if (!node || maxNumberOfItems <= 0) {
      return [];
    }

    let current = itemAtKeyIncluded
      ? node
      : ImmutableLinkedOrderedMapForMode[this.mode].findMapNodeByDirection(
          this,
          node,
          "previous"
        );
    if (!current) {
      return [];
    }

    const range = [{ key: current.element.key, value: current.element.value }];
    while (current && range.length < maxNumberOfItems) {
      current = ImmutableLinkedOrderedMapForMode[
        this.mode
      ].findMapNodeByDirection(this, current, "previous");
      if (!current) {
        break;
      }
      range.push({ key: current.element.key, value: current.element.value });
    }
    return range.reverse();
  }

  /**
   * Returns a range of key/value pairs after the given key up to a max number of items
   * (counting the given key if included).
   *
   * @param {string|number} key The key to lookup and use for the creation of the range.
   * @param {number} maxNumberOfItems Max number of key/value pairs to return for the range including or not including the item at the given key
   *                                  (depending on the value of the "itemAtKeyIncluded" opton).
   *                                  By default, all items after the given key are included in the returned range of key/value pairs.
   * @param {boolean} itemAtKeyIncluded Whether or not to include the item at the given key in the range (by default the item is included).
   * @return {Array} An array which represents the range, each item being a key/value pair representing an item of the map.
   *                 An empty array if the given key does not exist in the map or the given key does exist in the map but is not included
   *                 in the range and its item is the last of the map.
   *                 An empty array is also returned if "maxNumberOfItems" is less than or equal to 0.
   */
  rangeAfter(key, maxNumberOfItems = Infinity, itemAtKeyIncluded = true) {
    const node = ImmutableLinkedOrderedMapForMode[this.mode].lookup(this, key);
    if (!node || maxNumberOfItems <= 0) {
      return [];
    }

    let current = itemAtKeyIncluded
      ? node
      : ImmutableLinkedOrderedMapForMode[this.mode].findMapNodeByDirection(
          this,
          node,
          "next"
        );
    if (!current) {
      return [];
    }

    const range = [{ key: current.element.key, value: current.element.value }];
    while (current && range.length < maxNumberOfItems) {
      current = ImmutableLinkedOrderedMapForMode[
        this.mode
      ].findMapNodeByDirection(this, current, "next");
      if (!current) {
        break;
      }
      range.push({ key: current.element.key, value: current.element.value });
    }
    return range;
  }

  /**
   * Tests if the map is empty.
   *
   * @return {boolean} True if the map is empty, false otherwise.
   */
  isEmpty() {
    return this.length <= 0;
  }

  /**
   * Loop through all the values of this immutable linked ordered map in the order they were added.
   *
   * @param {Function} fn A callback function to call for each value stored in the map.
   *                      The callback will receive the value as the first argument, the key as the second argument
   *                      and the index of the item in the map as the third argument.
   * @param {boolean} [reversed] An optional boolean indicating whether to loop in reverse order (starting
   *                             from the tail node). The default is to loop through all the elements starting
   *                             from the head node.
   * @return {undefined}
   */
  forEach(fn, reversed = false) {
    let key;
    let value;
    let current;
    let nextNodeDirection;
    let i;
    let updateI;

    this.shouldNextForEachBreak = false;
    this.forEachNextFn = void 0;

    if (reversed) {
      current = this.tail;
      nextNodeDirection = "previous";
      i = this.length - 1;
      updateI = () => i--;
    } else {
      current = this.head;
      nextNodeDirection = "next";
      i = 0;
      updateI = () => i++;
    }

    while (current) {
      if (!this.shouldNextForEachBreak) {
        const element = current.element;
        key = element.key;
        value = element.value;
        const result = (this.forEachNextFn || fn).call(this, value, key, i);
        if (result === false) {
          // Break instantly.
          break;
        }
        current = ImmutableLinkedOrderedMapForMode[
          this.mode
        ].findMapNodeByDirection(this, current, nextNodeDirection);
        updateI();
      } else {
        break;
      }
    }
  }

  /**
   * Break the next "forEach" loop iteration occurring on this immutable linked ordered map.
   *
   * @return {undefined}
   */
  break() {
    this.shouldNextForEachBreak = true;
  }

  /**
   * Returns an array of values of this map.
   *
   * @param {boolean} [reversed] An optional boolean indicating whether to loop in reverse order (starting
   *                             from the tail node). The default is to loop through all the elements starting
   *                             from the head node.
   *                             If "reversed" is set to "true", the returned values will be in reverse order.
   * @return {Array} An array of all the values of this map, in the order they were added to the map.
   */
  values(reversed = false) {
    const array = new Array(this.length);
    let i = 0;
    this.forEach(function (value) {
      array[i] = value;
      i++;
    }, reversed);
    return array;
  }

  /**
   * Returns an array of keys of this map.
   *
   * @param {boolean} [reversed] An optional boolean indicating whether to loop in reverse order (starting
   *                             from the tail node). The default is to loop through all the elements starting
   *                             from the head node.
   *                             If "reversed" is set to "true", the returned keys will be in reverse order.
   * @return {Array} An array of all the keys of this map, in the order they were added to the map.
   */
  keys(reversed = false) {
    const array = new Array(this.length);
    let i = 0;
    this.forEach(function (value, key) {
      array[i] = key;
      i++;
    }, reversed);
    return array;
  }

  /**
   * Returns an array of key/value pairs for each item in the map.
   *
   * @param {boolean} [reversed] An optional boolean indicating whether to loop in reverse order (starting
   *                             from the tail node). The default is to loop through all the elements starting
   *                             from the head node.
   *                             If "reversed" is set to "true", the returned key/value pairs will be in reverse order.
   * @return {Array} An array of objects where each object has the property "key" (the key of the item in the map)
   *                 and "value" (the value of the item in the map).
   */
  keysValues(reversed = false) {
    const array = new Array(this.length);
    let i = 0;
    this.forEach((value, key) => {
      array[i] = {
        key,
        value,
      };
      i++;
    }, reversed);
    return array;
  }

  /**
   * Map all the values of this immutable linked ordered map in the order they were added
   * to a new array.
   *
   * @param {Function} fn A callback function to call for each value stored in the map.
   *                      The callback will receive the value as the first argument,
   *                      the key as the second argument, and the index of the item in the map as the third argument.
   *                      It's return value will be used as an element of the returned array for that item.
   * @param {boolean} [reversed] An optional boolean indicating whether to loop in reverse order (starting
   *                             from the tail node). The default is to loop through all the elements starting
   *                             from the head node.
   *                             If "reversed" is set to "true", the returned mapped values will be in reverse order.
   * @return {Array} An array with the mapped values.
   */
  map(fn, reversed = false) {
    const array = new Array(this.length);
    let i = 0;
    this.forEach((value, key, index) => {
      array[i] = fn(value, key, index);
      i++;
    }, reversed);
    return array;
  }

  /**
   * Reduce all the values of this immutable linked ordered map to a single output value.
   *
   * @param {Function} fn A callback function to call for each value stored in the map.
   *                      The callback will receive the accumulator as the first argument,
   *                      the current value as the second argument, the current value's key as the third argument
   *                      and the index of the item in the map as the fourth argument.
   *
   *                      It's return value is assigned to the accumulator, whose value is remembered across each iteration
   *                      throughout the map and ultimately becomes the final, single resulting value.
   *
   *                      The first time the callback is called, accumulator and current value can be one of two values.
   *                      If "initialValue" is provided in the call to "reduce()", then accumulator will be equal to "initialValue",
   *                      and the current value will be equal to the first value in the map.
   *                      If no "initialValue" is provided, then the accumulator will be equal to the first or last value in the map
   *                      (if "reversed" is either "false" or "true", respectively), and the current value will be equal to the value of
   *                      the next or previous item (again, if "reversed" is either "false" or "true", respectively).
   * @param {*} initialValue A value to use as the first argument to the first call of the callback.
   *                         If no "initialValue" is supplied, the first element in the map will be used and skipped.
   *                         Calling "reduce()" on an empty map without an initial value will throw a "TypeError".
   * @param {boolean} [reversed] An optional boolean indicating whether to loop in reverse order (starting
   *                             from the tail node). The default is to loop through all the elements starting
   *                             from the head node.
   * @return {*} The single value that results from the reduction.
   */
  reduce(fn, initialValue, reversed = false) {
    const hasInitialValue = arguments.length > 1;
    let acc;
    let skipFirst = false;

    if (!hasInitialValue) {
      if (!this.length) {
        throw new TypeError(
          `ImmutableLinkedOrderedMap type error: Reduce of empty map with no initial value`
        );
      }
      skipFirst = true;
      acc = (reversed ? this.last() : this.first()).value;
    } else {
      acc = initialValue;
    }

    const accFn = (value, key, index) => {
      acc = fn(acc, value, key, index);
    };
    const overridableFn = function (value, key, index) {
      this.forEachNextFn = accFn;
      if (skipFirst) {
        return;
      }
      accFn(value, key, index);
    };
    this.forEach(overridableFn, reversed);

    return acc;
  }

  /**
   * Filters the values of this immutable linked ordered map returning an array with the filtered values.
   *
   * @param {Function} fn A callback function to call for each value stored in the map.
   *                      The callback will receive the value as the first argument,
   *                      the key as the second argument, and the index of the item in the map as the third argument.
   *                      The values for which the callback returned a falsy value will not be added to the returned
   *                      array of filtered items.
   * @param {boolean} [reversed] An optional boolean indicating whether to loop in reverse order (starting
   *                             from the tail node). The default is to loop through all the elements starting
   *                             from the head node.
   *                             If "reversed" is set to "true", the returned filtered values will be in reverse order.
   * @return {Array} An array with the filtered values.
   */
  filter(fn, reversed = false) {
    const array = [];
    let i = 0;
    this.forEach((value, key, index) => {
      const res = fn(value, key, index);
      if (res) {
        array[i] = value;
        i++;
      }
    }, reversed);
    return array;
  }

  /**
   * Tests whether all values in the map pass the test implemented by the provided function.
   *
   * @param {Function} fn A callback function to call for each value stored in the map.
   *                      The callback will receive the value as the first argument, the key as the second argument
   *                      and the index of the item in the map as the third argument.
   *                      If the returned value of the callback for a given value is falsy, this method will instantly return "false".
   * @param {boolean} [reversed] An optional boolean indicating whether to loop in reverse order (starting
   *                             from the tail node). The default is to loop through all the elements starting
   *                             from the head node.
   * @return {boolean} "true" all values in the map pass the test implemented by the provided callback function, "false" otherwise.
   */
  every(fn, reversed = false) {
    let allTrue = true;
    this.forEach((value, key, index) => {
      const res = fn(value, key, index);
      if (!res) {
        allTrue = false;
        return false;
      }
    }, reversed);
    return allTrue;
  }

  /**
   * Tests whether at least one element in the map passes the test implemented by the provided function.
   *
   * @param {Function} fn A callback function to call for each value stored in the map.
   *                      The callback will receive the value as the first argument, the key as the second argument
   *                      and the index of the item in the map as the third argument.
   *                      If the returned value of the callback for a given value is truthy, this method will instantly return "true".
   * @param {boolean} [reversed] An optional boolean indicating whether to loop in reverse order (starting
   *                             from the tail node). The default is to loop through all the elements starting
   *                             from the head node.
   * @return {boolean} "true" if at least one element passes the test implemented by the provided callback function, "false" otherwise.
   */
  some(fn, reversed = false) {
    let ret = false;
    this.forEach((value, key, index) => {
      const res = fn(value, key, index);
      if (res) {
        ret = true;
        return false;
      }
    }, reversed);
    return ret;
  }
}

/* ======================================================================================================== */

/**
 * Binds two nodes of a map in single mode.
 *
 * @param {MultiwayModeImmutableLinkedOrderedMap} map The map.
 * @param {Object} previousNode Previous node.
 * @param {Object} nextNode Next node.
 * @return {undefined}
 */
function bindSingleModeNodes(map, previousNode, nextNode) {
  const { depth } = map;
  previousNode.next.set(depth, nextNode, true);
  nextNode.previous.set(depth, previousNode, true);
}

/**
 * Makes a new node for an immutable linked ordered map in single mode (used internally).
 *
 * @param {ImmutableLinkedOrderedMap} map The map.
 * @param {Object|null} previous Previous node or null if there isn't one.
 * @param {Object|null} next Next node or null if there isn't one.
 * @param {string|number} key The key of the mapped value.
 * @param {*} value The mapped value.
 * @return {Object} The node.
 */
function makeSingleModeImmutableLinkedOrderedMapNode(
  map,
  previous,
  next,
  key,
  value
) {
  const { depth } = map;

  const previousDepthMap = new LinkedOrderedMap();
  previousDepthMap.set(depth, previous);

  const nextDepthMap = new LinkedOrderedMap();
  nextDepthMap.set(depth, next);

  const node = makeNode(previousDepthMap, nextDepthMap, {
    key,
    value,
  });
  return node;
}

/**
 * Finds a previous or next node of a node in the context of an immutable linked ordered map
 * in single mode.
 *
 * @param {SingleModeImmutableLinkedOrderedMap} map The map.
 * @param {Object} fromNode From node.
 * @param {string} nextNodeDirection The direction, either "previous" or "next".
 * @return {Object|null} The previous node if it exists, or null if it doesn't (e.g. when called on a head node).
 */
function findSingleModeMapNodeByDirection(map, fromNode, nextNodeDirection) {
  if (
    (fromNode === map.tail && nextNodeDirection === "next") ||
    (fromNode === map.head && nextNodeDirection === "previous")
  ) {
    return null;
  }

  const { depth } = map;
  const directionMap = fromNode[nextNodeDirection];
  let node = null;
  directionMap.forEach((depthKey, v) => {
    if (depth >= depthKey) {
      node = v;
      return false;
    }
  });
  return node;
}

/**
 * Updates a heap map shared structure (structural sharing) of a map in single mode.
 *
 * @param {SingleModeImmutableLinkedOrderedMap} map The map which shared heap map should be updated.
 * @param {Object} itemNode The linked node of the item.
 * @return {undefined}
 */
function updateSingleModeHeapMap(map, itemNode) {
  const { heapMap, depth } = map;
  const key = itemNode.element.key;

  if (!heapMap[key]) {
    // Depth map.
    heapMap[key] = new LinkedOrderedMap();
  }
  heapMap[key].set(depth, itemNode, true);
}

/**
 * Lookups a node in a single mode map.
 *
 * @param {SingleModeImmutableLinkedOrderedMap} map The map.
 * @param {string|number} key The key to lookup.
 * @return {Object|undefined} The node in the heap map or undefined, if the value for that key is missing.
 */
function lookupSingleMode(map, key) {
  const { heapMap, depth } = map;

  if (!heapMap[key]) {
    return void 0;
  }
  const depthMap = heapMap[key];
  let node;
  depthMap.forEach((depthKey, v) => {
    if (depth >= depthKey) {
      node = v;
      return false;
    }
  });
  return nodeOrUndefined(node);
}

/**
 * Throws an error for a second mutation operation on an immutable linkerd ordered map
 * in single mode.
 *
 * @param {string} operation A string identifying the mutation operation.
 * @return {undefined}
 * @throws {Error}
 */
function throwSingleModemutationOperationOccurredError(operation) {
  throw new Error(
    `ImmutableLinkedOrderedMap error: Mutation operation "${operation}" is not allowed on a map in single mode on which a mutation operation already occurred once.`
  );
}

/**
 * Single mode immutable linked ordered map.
 */
class SingleModeImmutableLinkedOrderedMap extends ImmutableLinkedOrderedMap {
  /**
   * Constructor.
   *
   * @constructor
   *
   * @param {Object} options Options.
   */
  constructor(options) {
    const instance = super(options);

    instance.mutationOperationOccurred = false;
    return instance;
  }

  /**
   * {@inheritdoc}
   */
  set(items, prependMissing = false) {
    this.mutationOperationOccurred &&
      throwSingleModemutationOperationOccurredError("set");
    const map = super.set(items, prependMissing);
    if (this !== map) {
      this.mutationOperationOccurred = true;
    }
    return map;
  }

  /**
   * {@inheritdoc}
   */
  replace(oldKey, item, addMissing = false, prependMissing = false) {
    this.mutationOperationOccurred &&
      throwSingleModemutationOperationOccurredError("replace");
    const map = super.replace(oldKey, item, addMissing, prependMissing);
    if (this !== map) {
      this.mutationOperationOccurred = true;
    }
    return map;
  }

  /**
   * {@inheritdoc}
   */
  unset(key) {
    this.mutationOperationOccurred &&
      throwSingleModemutationOperationOccurredError("unset");
    const map = super.unset(key);
    if (this !== map) {
      this.mutationOperationOccurred = true;
    }
    return map;
  }

  /**
   * {@inheritdoc}
   */
  empty() {
    this.mutationOperationOccurred &&
      throwSingleModemutationOperationOccurredError("empty");
    const map = super.empty();
    if (this !== map) {
      this.mutationOperationOccurred = true;
    }
    return map;
  }
}

/* ======================================================================================================== */

/**
 * Binds two nodes of a map in multiway mode.
 *
 * @param {MultiwayModeImmutableLinkedOrderedMap} map The map.
 * @param {Object} previousNode Previous node.
 * @param {Object} nextNode Next node.
 * @return {undefined}
 */
function bindMultiwayModeNodes(map, previousNode, nextNode) {
  const { depth, version } = map;

  if (!previousNode.next.map[depth]) {
    previousNode.next.set(depth, new LinkedOrderedMap(), true);
  }
  const previousNodeDepthMap = previousNode.next.get(depth);
  previousNodeDepthMap.set(version, nextNode, true);

  if (!nextNode.previous.map[depth]) {
    nextNode.previous.set(depth, new LinkedOrderedMap(), true);
  }
  const nextNodeDepthMap = nextNode.previous.get(depth);
  nextNodeDepthMap.set(version, previousNode, true);
}

/**
 * Makes a new node for an immutable linked ordered map in multiway mode (used internally).
 *
 * @param {ImmutableLinkedOrderedMap} map The map.
 * @param {Object|null} previous Previous node or null if there isn't one.
 * @param {Object|null} next Next node or null if there isn't one.
 * @param {string|number} key The key of the mapped value.
 * @param {*} value The mapped value.
 * @return {Object} The node.
 */
function makeMultiwayModeImmutableLinkedOrderedMapNode(
  map,
  previous,
  next,
  key,
  value
) {
  const { depth, version } = map;

  const previousDepthMap = new LinkedOrderedMap();
  const previousVersionMap = new LinkedOrderedMap();
  previousVersionMap.set(version, previous);
  previousDepthMap.set(depth, previousVersionMap);

  const nextDepthMap = new LinkedOrderedMap();
  const nextVersionMap = new LinkedOrderedMap();
  nextVersionMap.set(version, next);
  nextDepthMap.set(depth, nextVersionMap);

  const node = makeNode(previousDepthMap, nextDepthMap, {
    key,
    value,
  });
  return node;
}

/**
 * Hydrates a map in multiway mode.
 *
 * @param {string} version The version of the map in the context of the version tree,
 *                         used during lookups.
 */
function hydrateMultiwayMode({
  version = MULTIWAY_MODE_INITIAL_MAP_TREE_DEPTH_VERSION,
} = {}) {
  this.version = version;
}

/**
 * Finds a previous or next node of a node in the context of an immutable linked ordered map
 * in multiway mode.
 *
 * @param {MultiwayModeImmutableLinkedOrderedMap} map The map.
 * @param {Object} fromNode From node.
 * @param {string} nextNodeDirection The direction, either "previous" or "next".
 * @return {Object|null} The previous node if it exists, or null if it doesn't (e.g. when called on a head node).
 */
function findMultiwayModeMapNodeByDirection(map, fromNode, nextNodeDirection) {
  if (
    (fromNode === map.tail && nextNodeDirection === "next") ||
    (fromNode === map.head && nextNodeDirection === "previous")
  ) {
    return null;
  }

  const { depth, version } = map;
  const directionMap = fromNode[nextNodeDirection];
  let node = null;
  directionMap.forEach((depthKey, v) => {
    if (depth >= depthKey) {
      v.forEach((stackVersion, possibleNode) => {
        if (isAncestorVersionOfDescendantVersion(stackVersion, version)) {
          node = possibleNode;
          return false;
        }
      });
      if (node) {
        return false;
      }
    }
  });
  return node;
}

/**
 * Updates a heap map shared structure (structural sharing) of a map in multiway mode.
 *
 * @param {MultiwayModeImmutableLinkedOrderedMap} map The map which shared heap map should be updated.
 * @param {Object} itemNode The linked node of the item.
 * @return {undefined}
 */
function updateMultiWayModeHeapMap(map, itemNode) {
  const { heapMap, depth, version } = map;
  const key = itemNode.element.key;

  if (!heapMap[key]) {
    // Depth map.
    heapMap[key] = new LinkedOrderedMap();
  }
  if (!heapMap[key].map[depth]) {
    // This new linked ordered map functions as a stack.
    heapMap[key].set(depth, new LinkedOrderedMap(), true);
  }
  const stack = heapMap[key].get(depth);

  // Always prepend a new version. Indeed, "stack" here (which is a linked ordered map)
  // acts like a stack data structure.
  stack.set(version, itemNode, true);
}

/**
 * Lookups a node in a multiway mode map.
 *
 * @param {MultiwayModeImmutableLinkedOrderedMap} map The map.
 * @param {string|number} key The key to lookup.
 * @return {Object|undefined} The node in the heap map or undefined, if the value for that key is missing.
 */
function lookupMultiwayMode(map, key) {
  const { heapMap, depth, version } = map;

  if (!heapMap[key]) {
    return void 0;
  }
  const depthMap = heapMap[key];
  let node = void 0;
  depthMap.forEach((depthKey, v) => {
    if (depth >= depthKey) {
      v.forEach((stackVersion, possibleNode) => {
        if (isAncestorVersionOfDescendantVersion(stackVersion, version)) {
          node = possibleNode;
          return false;
        }
      });
      if (node) {
        return false;
      }
    }
  });
  return nodeOrUndefined(node);
}

/**
 * Called when a map in multiway mode is forked.
 *
 * @param {ImmutableLinkedOrderedMap} ancestorMap Ancestor map.
 * @param {ImmutableLinkedOrderedMap} forkedMap The forked map.
 * @return {ImmutableLinkedOrderedMap} forkedMap The forked map.
 */
function forkMultiwayModeMap(ancestorMap, forkedMap) {
  forkedMap.version = !ancestorMap.version.length
    ? `${++ancestorMap.childrenCount}${MULTIWAY_MODE_MAP_TREE_DEPTH_VERSION_SEPARATOR}`
    : `${
        ancestorMap.version
      }${++ancestorMap.childrenCount}${MULTIWAY_MODE_MAP_TREE_DEPTH_VERSION_SEPARATOR}`;
  return forkedMap;
}

/**
 * Tests if a version is an ancestor version of a descendant version (used internally for maps in multiway mode).
 *
 * @param {string} possibleAncestorVersion Possible ancestor version.
 * @param {string} descendantVersion Descendant version.
 * @return {boolean} True if the given possible ancestor version is in fact the ancestor version of the given descendant version,
 *                   false otherwise.
 */
function isAncestorVersionOfDescendantVersion(
  possibleAncestorVersion,
  descendantVersion
) {
  return (
    descendantVersion.substring(0, possibleAncestorVersion.length) ===
    possibleAncestorVersion
  );
}

/**
 * Multiway mode immutable linked ordered map.
 */
class MultiwayModeImmutableLinkedOrderedMap extends ImmutableLinkedOrderedMap {
  /**
   * Constructor.
   *
   * @constructor
   *
   * @param {Object} options Options.
   */
  constructor(options) {
    const instance = super(options);

    instance.childrenCount = 0;
    return instance;
  }
}

/* ======================================================================================================== */

/**
 * Binds two nodes of a map in lightweight mode.
 *
 * @param {LightweightModeImmutableLinkedOrderedMap} map The map.
 * @param {Object} previousNode Previous node.
 * @param {Object} nextNode Next node.
 * @return {undefined}
 */
function bindLightweightModeNodes(map, previousNode, nextNode) {
  previousNode.next = nextNode;
  nextNode.previous = previousNode;
}

/**
 * Lookups a node in a lightweight mode map.
 *
 * @param {LightweightModeImmutableLinkedOrderedMap} map The map.
 * @param {string|number} key The key to lookup.
 * @return {Object|undefined} The node in the heap map or undefined, if the value for that key is missing.
 */
function lookupLightweightMode(map, key) {
  const { heapMap } = map;
  const node = heapMap[key];
  return nodeOrUndefined(node);
}

/**
 * Updates a heap map shared structure (structural sharing) of a map in multiway mode.
 *
 * @param {LightweightModeImmutableLinkedOrderedMap} map The map which shared heap map should be updated.
 * @param {Object} itemNode The linked node of the item.
 * @return {undefined}
 */
function updateLightweightModeHeapMap(map, itemNode) {
  const { heapMap } = map;
  const key = itemNode.element.key;
  heapMap[key] = itemNode;
}

/**
 * Finds a previous or next node of a node in the context of an immutable linked ordered map
 * in lightweight mode.
 *
 * @param {LightweightModeImmutableLinkedOrderedMap} map The map.
 * @param {Object} fromNode From node.
 * @param {string} nextNodeDirection The direction, either "previous" or "next".
 * @return {Object|null} The previous node if it exists, or null if it doesn't (e.g. when called on a head node).
 */
function findLightweightModeMapNodeByDirection(
  map,
  fromNode,
  nextNodeDirection
) {
  return fromNode[nextNodeDirection];
}

/**
 * Makes a new node for an immutable linked ordered map in lightweight mode (used internally).
 *
 * @param {ImmutableLinkedOrderedMap} map The map.
 * @param {Object|null} previous Previous node or null if there isn't one.
 * @param {Object|null} next Next node or null if there isn't one.
 * @param {string|number} key The key of the mapped value.
 * @param {*} value The mapped value.
 * @return {Object} The node.
 */
function makeLightweightModeImmutableLinkedOrderedMapNode(
  map,
  previous,
  next,
  key,
  value
) {
  // "map" is not used in this function when we are in lightweight mode.
  const node = makeNode(previous, next, {
    key,
    value,
  });
  return node;
}

/**
 * Throws an error for a second mutation operation on an immutable linkerd ordered map
 * in lightweight mode.
 *
 * @param {string} operation A string identifying the mutation operation.
 * @return {undefined}
 * @throws {Error}
 */
function throwLightweightModeOperationAftermutationOperationOccurredError(
  operation
) {
  throw new Error(
    `ImmutableLinkedOrderedMap error: Operation "${operation}" is not allowed on a map in lightweight mode on which a mutation operation occurred once.`
  );
}

/**
 * Lightweight mode immutable linked ordered map.
 */
class LightweightModeImmutableLinkedOrderedMap extends ImmutableLinkedOrderedMap {
  /**
   * Constructor.
   *
   * @constructor
   *
   * @param {Object} options Options.
   */
  constructor(options) {
    const instance = super(options);

    instance.mutationOperationOccurred = false;
    return instance;
  }

  /**
   * {@inheritdoc}
   */
  set(items, prependMissing = false) {
    this.mutationOperationOccurred &&
      throwLightweightModeOperationAftermutationOperationOccurredError("set");
    const map = super.set(items, prependMissing);
    if (this !== map) {
      this.mutationOperationOccurred = true;
    }
    return map;
  }

  /**
   * {@inheritdoc}
   */
  replace(oldKey, item, addMissing = false, prependMissing = false) {
    this.mutationOperationOccurred &&
      throwLightweightModeOperationAftermutationOperationOccurredError(
        "replace"
      );
    const map = super.replace(oldKey, item, addMissing, prependMissing);
    if (this !== map) {
      this.mutationOperationOccurred = true;
    }
    return map;
  }

  /**
   * {@inheritdoc}
   */
  unset(key) {
    this.mutationOperationOccurred &&
      throwLightweightModeOperationAftermutationOperationOccurredError("unset");
    const map = super.unset(key);
    if (this !== map) {
      this.mutationOperationOccurred = true;
    }
    return map;
  }

  /**
   * {@inheritdoc}
   */
  empty() {
    this.mutationOperationOccurred &&
      throwLightweightModeOperationAftermutationOperationOccurredError("empty");
    const map = super.empty();
    if (this !== map) {
      this.mutationOperationOccurred = true;
    }
    return map;
  }

  /**
   * {@inheritdoc}
   */
  get(key) {
    this.mutationOperationOccurred &&
      throwLightweightModeOperationAftermutationOperationOccurredError("get");
    return super.get(key);
  }

  /**
   * {@inheritdoc}
   */
  first() {
    this.mutationOperationOccurred &&
      throwLightweightModeOperationAftermutationOperationOccurredError("first");
    return super.first();
  }

  /**
   * {@inheritdoc}
   */
  last() {
    this.mutationOperationOccurred &&
      throwLightweightModeOperationAftermutationOperationOccurredError("last");
    return super.last();
  }

  /**
   * {@inheritdoc}
   */
  isEmpty() {
    this.mutationOperationOccurred &&
      throwLightweightModeOperationAftermutationOperationOccurredError(
        "isEmpty"
      );
    return super.isEmpty();
  }

  /**
   * {@inheritdoc}
   */
  forEach(fn, reversed = false) {
    this.mutationOperationOccurred &&
      throwLightweightModeOperationAftermutationOperationOccurredError(
        "forEach"
      );
    return super.forEach(fn, reversed);
  }

  /**
   * {@inheritdoc}
   */
  break() {
    this.mutationOperationOccurred &&
      throwLightweightModeOperationAftermutationOperationOccurredError("break");
    return super.break();
  }

  /**
   * {@inheritdoc}
   */
  values(reversed = false) {
    this.mutationOperationOccurred &&
      throwLightweightModeOperationAftermutationOperationOccurredError(
        "values"
      );
    return super.values(reversed);
  }

  /**
   * {@inheritdoc}
   */
  keys(reversed = false) {
    this.mutationOperationOccurred &&
      throwLightweightModeOperationAftermutationOperationOccurredError("keys");
    return super.keys(reversed);
  }

  /**
   * {@inheritdoc}
   */
  keysValues(reversed = false) {
    this.mutationOperationOccurred &&
      throwLightweightModeOperationAftermutationOperationOccurredError(
        "keysValues"
      );
    return super.keysValues(reversed);
  }

  /**
   * {@inheritdoc}
   */
  map(fn, reversed = false) {
    this.mutationOperationOccurred &&
      throwLightweightModeOperationAftermutationOperationOccurredError("map");
    return super.map(fn, reversed);
  }

  /**
   * {@inheritdoc}
   */
  reduce(fn, initialValue, reversed = false) {
    this.mutationOperationOccurred &&
      throwLightweightModeOperationAftermutationOperationOccurredError(
        "reduce"
      );
    return super.reduce(fn, initialValue, reversed);
  }

  /**
   * {@inheritdoc}
   */
  filter(fn, reversed = false) {
    this.mutationOperationOccurred &&
      throwLightweightModeOperationAftermutationOperationOccurredError(
        "filter"
      );
    return super.filter(fn, reversed);
  }

  /**
   * {@inheritdoc}
   */
  every(fn, reversed = false) {
    this.mutationOperationOccurred &&
      throwLightweightModeOperationAftermutationOperationOccurredError("every");
    return super.every(fn, reversed);
  }

  /**
   * {@inheritdoc}
   */
  some(fn, reversed = false) {
    this.mutationOperationOccurred &&
      throwLightweightModeOperationAftermutationOperationOccurredError("some");
    return super.some(fn, reversed);
  }
}

/* ======================================================================================================== */

/**
 * A mutable linked ordered map (used internally).
 *
 * @constructor
 */
function LinkedOrderedMap() {
  /**
   * @property {Object} map An object used for fast key lookups.
   */
  this.map = {};

  /**
   * @property {LinkedList} keyValueList A list keeping the keys with the corresponding values in the order they were added while
   *                                     adding them to this linked ordered map.
   */
  this.keyValueList = new LinkedList();

  /**
   * @property {Boolean} A boolean indicating whether the next "forEach" loop iteration should break.
   */
  this.shouldNextForEachBreak = false;
}

/**
 * Sets a value correlated with a key. If another value with the same key already exists,
 * it will be overwritten by the new value.
 *
 * @param {string|number} key A key used to lookup the value subsequently.
 * @param {*} value Anything The value to set.
 * @param {boolean} prepend By default, this method appends a new element to the linked list.
 *                          If set to "true", new elements will be prepended.
 * @return {undefined}
 */
LinkedOrderedMap.prototype.set = function (key, value, prepend = false) {
  if (key in this.map) {
    // key already exists, replace value.
    this.map[key].element.value = value;
  } else {
    // Insert new key and value.
    const appendOrPrepend = prepend ? "prepend" : "append";
    const node = this.keyValueList[appendOrPrepend]({ key, value }); // Keys are needed here too if we want to loop through them within a "forEach" loop.
    this.map[key] = node; // The map will reference the node containing the key and the corresponding value.
  }
};

/**
 * Removes an element from the map.
 *
 * @param {string|number} key The key of the element to remove.
 * @return {undefined}
 * @throws {Error} If the given key does not exist.
 */
LinkedOrderedMap.prototype.remove = function (key) {
  if (key in this.map) {
    this.keyValueList.remove(this.map[key]); // Removing the node from the underlying linked list.
    delete this.map[key]; // Removing the key from the underlying map.
  } else {
    throw new Error("key does not exist");
  }
};

/**
 * Empties this map.
 *
 * @return {undefined}
 */
LinkedOrderedMap.prototype.empty = function () {
  this.map = {};
  this.keyValueList = new LinkedList();
  this.shouldNextForEachBreak = false;
};

/**
 * Tests whether this map is empty.
 *
 * @return {boolean} True if empty, false otherwise.
 */
LinkedOrderedMap.prototype.isEmpty = function () {
  return this.getLength() <= 0;
};

/**
 * Retrieves a map value.
 *
 * @param {string|number} key The key.
 * @param {boolean} [returnWholeNode] True to return the whole node of the internal linked list,
 *                                    otherwise, returns just the value.
 * @return {*} The value correlated with the specified key or undefined if no value exists for that key.
 *             If "returnWholeNode" is "true", returns the whole node of the internal linked list.
 */
LinkedOrderedMap.prototype.get = function (key, returnWholeNode = false) {
  return (
    this.map[key] &&
    (returnWholeNode ? this.map[key] : this.map[key].element.value)
  );
};

/**
 * Loop through the elements in the order they were added.
 *
 * @param {Function} f A callback to call for each value stored in the map. The callback will receive the key as the first argument
 *                     and the value as the second argument.
 * @param {boolean} [reversed] An optional boolean indicating whether to loop in reverse order (starting
 *                             from the tail node). The default is to loop through all the elements starting
 *                             from the head node.
 * @return {undefined}
 */
LinkedOrderedMap.prototype.forEach = function (f, reversed = false) {
  let key, value;
  this.shouldNextForEachBreak = false;
  // eslint-disable-next-line @typescript-eslint/no-this-alias
  const thisMap = this;
  this.keyValueList.forEach(function (i, element) {
    if (!this.shouldNextForEachBreak) {
      key = element.key;
      value = element.value;
      const result = f.call(thisMap, key, value);
      if (result === false) {
        return false;
      }
    } else {
      return false;
    }
  }, reversed);
};

/**
 * Break the next "forEach" loop iteration occurring on this linked ordered map.
 *
 * @return {undefined}
 */
LinkedOrderedMap.prototype.break = function () {
  this.shouldNextForEachBreak = true;
};

/**
 * Gets the length of the map, i.e. the number of key value pairs in it.
 *
 * @return {Number} The number of key value pairs stored in this map.
 */
LinkedOrderedMap.prototype.getLength = function () {
  return this.keyValueList.length;
};

/**
 * Returns an array containing the values of this ordered map in the order they were added originally.
 *
 * @return {Array<Anything>} An array containing the values stored in the map.
 */
LinkedOrderedMap.prototype.toArray = function () {
  const array = new Array(this.getLength());
  let i = 0;
  this.forEach(function (key, value) {
    array[i] = value;
    i++;
  });
  return array;
};

/**
 * Returns the keys of this ordered map in the order their corresponding values were added to this map.
 *
 * @return {Array<Number|String>} An array containing the keys of this ordered map.
 */
LinkedOrderedMap.prototype.keys = function () {
  const array = new Array(this.getLength());
  let i = 0;
  this.forEach(function (key) {
    array[i] = key;
    i++;
  });
  return array;
};

/**
 * Static method that creates a new map from an existent array of items.
 * Each item must have a property identified by the "key" parameter.
 *
 * @param {Array} array
 * @param {string|number} key
 * @return {LinkedOrderedMap}
 */
LinkedOrderedMap.fromArray = function (array, key) {
  const map = new LinkedOrderedMap();
  for (let i = 0; i < array.length; i++) {
    const item = array[i];
    map.set(item[key], item);
  }
  return map;
};

/* ======================================================================================================== */

/**
 * A mutable linked list (used internally).
 *
 * @constructor
 */
function LinkedList() {
  /**
   * @property {Object} head The head node of this linked list.
   */
  this.head = null;

  /**
   * @property {Object} tail The tail node of this linked list.
   */
  this.tail = null;

  /**
   * @property {Number} length The length of this linked list
   */
  this.length = 0;

  /**
   * @property {Boolean} shouldNextForEachBreak A boolean indicating whether the next "forEach" loop iteration should break.
   */
  this.shouldNextForEachBreak = false;
}

/**
 * Adds an element to the tail of this linked list. After this method is called,
 * the tail node will reference an object containing the added element.
 *
 * @param {*} anything Anything to add to this linked list.
 * @return {Object} A reference to the new added node object of this linked list.
 */
LinkedList.prototype.append = function (anything) {
  this.length++;

  if (this.head) {
    const oldTail = this.tail;
    this.tail = makeNode(oldTail, null, anything);
    oldTail.next = this.tail;
  } else {
    this.tail = makeNode(null, null, anything);
    this.head = this.tail;
  }
  return this.tail;
};

/**
 * Adds an element to the head of this linked list. After this method is called,
 * the head of this linked list will reference an object containing the added element.
 *
 * @param {*} anything Anything to add to this linked list.
 * @return {Object} A reference to the new added node object of this linked list.
 */
LinkedList.prototype.prepend = function (anything) {
  this.length++;

  if (this.head) {
    const oldHead = this.head;
    this.head = makeNode(null, oldHead, anything);
    oldHead.previous = this.head;
  } else {
    this.tail = makeNode(null, null, anything);
    this.head = this.tail;
  }
  return this.head;
};

/**
 * Removes a node from this linked list.
 *
 * @param {Object} node The node to remove (not the element added, but the object
 *                      "{ next : nextNode, previous : previousNode, element : anything }"
 *                      previously created when adding the element to this linked list.
 * @return {LinkeList} A reference to this linked list.
 */
LinkedList.prototype.remove = function (node) {
  if (node !== null) {
    if (node.previous) {
      node.previous.next = node.next;
      if (node.next) {
        node.next.previous = node.previous;
      } else {
        this.tail = node.previous;
      }
      this.length--;
    } else if (node.next) {
      this.head = node.next;
      this.head.previous = null;
      this.length--;
    } else {
      this.head = null;
      this.tail = null;
      this.length = 0;
    }
    node = null;
  }
};

/**
 * Removes the last element from the list (the element of the tail node) and returns it.
 *
 * @return {*} The element of the tail node of this linked list.
 */
LinkedList.prototype.pop = function () {
  if (this.tail) {
    const element = this.tail.element;
    this.remove(this.tail);
    return element;
  }
};

/**
 * Removes the first element from the list (the element of the tail node) and returns it.
 *
 * @return {*} The element of the head node of this linked list.
 */
LinkedList.prototype.shift = function () {
  if (this.head) {
    const element = this.head.element;
    this.remove(this.head);
    return element;
  }
};

/**
 * Loops on this linked list.
 *
 * @param {Function} fn A function which will be called for each element in this linked list:
 *
 *                          fn(Number i, Anything element)
 *
 *                      The function will receive two parameters:
 *                      - the first parameter will be the index of the element (starting from 0 and incrementing by 1
 *                        if the parameter "reversed" is false, or from the length of this linked list minus 1
 *                        and decrementing by 1 if the parameter "reversed" is true)
 *                      - the second parameter will be the nth element of the list.
 *
 *                      Within the function body, this will point to the linked list instance and "this.break()" can be
 *                      called to stop the next iteration.
 *                      Otherwise, if the function returns false, the loop will stop immediately.
 * @param {boolean} [reversed] An optional boolean indicating whether to loop in reverse order (starting
 *                             from the tail node). The default is to loop through all the elements starting
 *                             from the head node.
 * @return {undefined}
 */
LinkedList.prototype.forEach = function (fn, reversed = false) {
  let current, nextNodeDirection, i, updateIndexFn;
  this.shouldNextForEachBreak = false;
  if (reversed) {
    i = this.length - 1;
    current = this.tail;
    nextNodeDirection = "previous";
    updateIndexFn = function () {
      i--;
    };
  } else {
    i = 0;
    current = this.head;
    nextNodeDirection = "next";
    updateIndexFn = function () {
      i++;
    };
  }

  while (current) {
    if (!this.shouldNextForEachBreak) {
      const element = current.element;
      const result = fn.call(this, i, element);
      if (result === false) {
        break;
      }
      current = current[nextNodeDirection];
      updateIndexFn();
    } else {
      break;
    }
  }
};

/**
 * Breaks the next "forEach" loop iteration occurring on this linked list.
 *
 * @return {undefined}
 */
LinkedList.prototype.break = function () {
  this.shouldNextForEachBreak = true;
};

/* ======================================================================================================== */

/**
 * @type {Object}
 */
const ImmutableLinkedOrderedMapForMode = {
  [ImmutableLinkedOrderedMapMode.SINGLE]: {
    ImmutableLinkedOrderedMapClass: SingleModeImmutableLinkedOrderedMap,
    lookup: lookupSingleMode,
    updateHeapMap: updateSingleModeHeapMap,
    findMapNodeByDirection: findSingleModeMapNodeByDirection,
    makeImmutableLinkedOrderedMapNode: makeSingleModeImmutableLinkedOrderedMapNode,
    bindNodes: bindSingleModeNodes,
  },
  [ImmutableLinkedOrderedMapMode.MULTIWAY]: {
    ImmutableLinkedOrderedMapClass: MultiwayModeImmutableLinkedOrderedMap,
    hydrate: hydrateMultiwayMode,
    lookup: lookupMultiwayMode,
    fork: forkMultiwayModeMap,
    updateHeapMap: updateMultiWayModeHeapMap,
    findMapNodeByDirection: findMultiwayModeMapNodeByDirection,
    makeImmutableLinkedOrderedMapNode: makeMultiwayModeImmutableLinkedOrderedMapNode,
    bindNodes: bindMultiwayModeNodes,
  },
  [ImmutableLinkedOrderedMapMode.LIGHTWEIGHT]: {
    ImmutableLinkedOrderedMapClass: LightweightModeImmutableLinkedOrderedMap,
    lookup: lookupLightweightMode,
    updateHeapMap: updateLightweightModeHeapMap,
    findMapNodeByDirection: findLightweightModeMapNodeByDirection,
    makeImmutableLinkedOrderedMapNode: makeLightweightModeImmutableLinkedOrderedMapNode,
    bindNodes: bindLightweightModeNodes,
  },
};

ImmutableLinkedOrderedMap.MODE = ImmutableLinkedOrderedMapMode;
ImmutableLinkedOrderedMap.isMap = value =>
  !!(value && value[MAP_TAG] === MAP_TAG_VALUE);

/* ======================================================================================================== */


/***/ }),

/***/ "./node_modules/immutable-linked-ordered-map/src/index.js":
/*!****************************************************************!*\
  !*** ./node_modules/immutable-linked-ordered-map/src/index.js ***!
  \****************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* reexport safe */ _ImmutableLinkedOrderedMap__WEBPACK_IMPORTED_MODULE_0__.ImmutableLinkedOrderedMap),
/* harmony export */   "ImmutableLinkedOrderedMapMode": () => (/* reexport safe */ _ImmutableLinkedOrderedMap__WEBPACK_IMPORTED_MODULE_0__.ImmutableLinkedOrderedMapMode),
/* harmony export */   "lazyMap": () => (/* reexport safe */ _shortcuts_lazyMap__WEBPACK_IMPORTED_MODULE_1__.lazyMap)
/* harmony export */ });
/* harmony import */ var _ImmutableLinkedOrderedMap__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./ImmutableLinkedOrderedMap */ "./node_modules/immutable-linked-ordered-map/src/ImmutableLinkedOrderedMap.js");
/* harmony import */ var _shortcuts_lazyMap__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./shortcuts/lazyMap */ "./node_modules/immutable-linked-ordered-map/src/shortcuts/lazyMap.js");
/* harmony import */ var _shortcuts_map__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./shortcuts/map */ "./node_modules/immutable-linked-ordered-map/src/shortcuts/map.js");
/*
 * Copyright (c) 2021 Anton Bagdatyev (Tonix)
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */





[
  ["lazyMap", _shortcuts_lazyMap__WEBPACK_IMPORTED_MODULE_1__.lazyMap],
  ["map", _shortcuts_map__WEBPACK_IMPORTED_MODULE_2__.map],
].map(([key, value]) => (_ImmutableLinkedOrderedMap__WEBPACK_IMPORTED_MODULE_0__.ImmutableLinkedOrderedMap[key] = value));



/***/ }),

/***/ "./node_modules/immutable-linked-ordered-map/src/shortcuts/lazyMap.js":
/*!****************************************************************************!*\
  !*** ./node_modules/immutable-linked-ordered-map/src/shortcuts/lazyMap.js ***!
  \****************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "lazyMap": () => (/* binding */ lazyMap)
/* harmony export */ });
/* harmony import */ var _map__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./map */ "./node_modules/immutable-linked-ordered-map/src/shortcuts/map.js");
/*
 * Copyright (c) 2021 Anton Bagdatyev (Tonix)
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */



/**
 * Shortcut function to create a lazy immutable linked ordered map.
 *
 * @param {Object} options Options.
 * @return {ImmutableLinkedOrderedMap} A new lazy immutable linked ordered map for the given options.
 */
const lazyMap = (options = {}) =>
  (0,_map__WEBPACK_IMPORTED_MODULE_0__.map)({
    ...options,
    lazy: true,
  });


/***/ }),

/***/ "./node_modules/immutable-linked-ordered-map/src/shortcuts/map.js":
/*!************************************************************************!*\
  !*** ./node_modules/immutable-linked-ordered-map/src/shortcuts/map.js ***!
  \************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "map": () => (/* binding */ map)
/* harmony export */ });
/* harmony import */ var _ImmutableLinkedOrderedMap__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ../ImmutableLinkedOrderedMap */ "./node_modules/immutable-linked-ordered-map/src/ImmutableLinkedOrderedMap.js");
/*
 * Copyright (c) 2021 Anton Bagdatyev (Tonix)
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */



/**
 * Shortcut function to create an immutable linked ordered map.
 *
 * @param {Object} options Options.
 * @return {ImmutableLinkedOrderedMap} A new immutable linked ordered map for the given options.
 */
const map = (options = {}) =>
  new _ImmutableLinkedOrderedMap__WEBPACK_IMPORTED_MODULE_0__.ImmutableLinkedOrderedMap({
    ...options,
  });


/***/ }),

/***/ "./node_modules/js-utl/dist/es6/index.js":
/*!***********************************************!*\
  !*** ./node_modules/js-utl/dist/es6/index.js ***!
  \***********************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "config": () => (/* reexport safe */ _modules_core__WEBPACK_IMPORTED_MODULE_0__.config),
/* harmony export */   "isObjectEmpty": () => (/* reexport safe */ _modules_core__WEBPACK_IMPORTED_MODULE_0__.isObjectEmpty),
/* harmony export */   "isObject": () => (/* reexport safe */ _modules_core__WEBPACK_IMPORTED_MODULE_0__.isObject),
/* harmony export */   "isPlainObject": () => (/* reexport safe */ _modules_core__WEBPACK_IMPORTED_MODULE_0__.isPlainObject),
/* harmony export */   "isArray": () => (/* reexport safe */ _modules_core__WEBPACK_IMPORTED_MODULE_0__.isArray),
/* harmony export */   "isEmpty": () => (/* reexport safe */ _modules_core__WEBPACK_IMPORTED_MODULE_0__.isEmpty),
/* harmony export */   "isEmptyOr0": () => (/* reexport safe */ _modules_core__WEBPACK_IMPORTED_MODULE_0__.isEmptyOr0),
/* harmony export */   "getGlobalObject": () => (/* reexport safe */ _modules_core__WEBPACK_IMPORTED_MODULE_0__.getGlobalObject),
/* harmony export */   "uniqueId": () => (/* reexport safe */ _modules_core__WEBPACK_IMPORTED_MODULE_0__.uniqueId),
/* harmony export */   "deepArrayCompare": () => (/* reexport safe */ _modules_core__WEBPACK_IMPORTED_MODULE_0__.deepArrayCompare),
/* harmony export */   "deepObjectCompare": () => (/* reexport safe */ _modules_core__WEBPACK_IMPORTED_MODULE_0__.deepObjectCompare),
/* harmony export */   "nestedObjectConstructValue": () => (/* reexport safe */ _modules_core__WEBPACK_IMPORTED_MODULE_0__.nestedObjectConstructValue),
/* harmony export */   "cloneDeeplyJSON": () => (/* reexport safe */ _modules_core__WEBPACK_IMPORTED_MODULE_0__.cloneDeeplyJSON),
/* harmony export */   "isReferenceType": () => (/* reexport safe */ _modules_core__WEBPACK_IMPORTED_MODULE_0__.isReferenceType),
/* harmony export */   "isPrimitiveType": () => (/* reexport safe */ _modules_core__WEBPACK_IMPORTED_MODULE_0__.isPrimitiveType),
/* harmony export */   "hasCyclicReference": () => (/* reexport safe */ _modules_core__WEBPACK_IMPORTED_MODULE_0__.hasCyclicReference),
/* harmony export */   "typeToStr": () => (/* reexport safe */ _modules_core__WEBPACK_IMPORTED_MODULE_0__.typeToStr),
/* harmony export */   "cloneObjDeeply": () => (/* reexport safe */ _modules_core__WEBPACK_IMPORTED_MODULE_0__.cloneObjDeeply),
/* harmony export */   "deepObjectExtend": () => (/* reexport safe */ _modules_core__WEBPACK_IMPORTED_MODULE_0__.deepObjectExtend),
/* harmony export */   "deepObjectCloningExtend": () => (/* reexport safe */ _modules_core__WEBPACK_IMPORTED_MODULE_0__.deepObjectCloningExtend),
/* harmony export */   "extend": () => (/* reexport safe */ _modules_core__WEBPACK_IMPORTED_MODULE_0__.extend),
/* harmony export */   "extendDecorate": () => (/* reexport safe */ _modules_core__WEBPACK_IMPORTED_MODULE_0__.extendDecorate),
/* harmony export */   "shallowExtend": () => (/* reexport safe */ _modules_core__WEBPACK_IMPORTED_MODULE_0__.shallowExtend),
/* harmony export */   "includesTypeCoercion": () => (/* reexport safe */ _modules_core__WEBPACK_IMPORTED_MODULE_0__.includesTypeCoercion),
/* harmony export */   "nestedPropertyValue": () => (/* reexport safe */ _modules_core__WEBPACK_IMPORTED_MODULE_0__.nestedPropertyValue),
/* harmony export */   "getNestedPropertyValue": () => (/* reexport safe */ _modules_core__WEBPACK_IMPORTED_MODULE_0__.getNestedPropertyValue),
/* harmony export */   "hasNestedPropertyValue": () => (/* reexport safe */ _modules_core__WEBPACK_IMPORTED_MODULE_0__.hasNestedPropertyValue),
/* harmony export */   "setNestedPropertyValue": () => (/* reexport safe */ _modules_core__WEBPACK_IMPORTED_MODULE_0__.setNestedPropertyValue),
/* harmony export */   "isUndefined": () => (/* reexport safe */ _modules_core__WEBPACK_IMPORTED_MODULE_0__.isUndefined),
/* harmony export */   "isInt": () => (/* reexport safe */ _modules_core__WEBPACK_IMPORTED_MODULE_0__.isInt),
/* harmony export */   "ctypeDigit": () => (/* reexport safe */ _modules_core__WEBPACK_IMPORTED_MODULE_0__.ctypeDigit),
/* harmony export */   "isIntegerOrIntegerStr": () => (/* reexport safe */ _modules_core__WEBPACK_IMPORTED_MODULE_0__.isIntegerOrIntegerStr),
/* harmony export */   "isCallable": () => (/* reexport safe */ _modules_core__WEBPACK_IMPORTED_MODULE_0__.isCallable),
/* harmony export */   "findIndex": () => (/* reexport safe */ _modules_core__WEBPACK_IMPORTED_MODULE_0__.findIndex),
/* harmony export */   "firstPropValue": () => (/* reexport safe */ _modules_core__WEBPACK_IMPORTED_MODULE_0__.firstPropValue),
/* harmony export */   "isStrictlyTrue": () => (/* reexport safe */ _modules_core__WEBPACK_IMPORTED_MODULE_0__.isStrictlyTrue),
/* harmony export */   "isTruthy": () => (/* reexport safe */ _modules_core__WEBPACK_IMPORTED_MODULE_0__.isTruthy),
/* harmony export */   "allTruthy": () => (/* reexport safe */ _modules_core__WEBPACK_IMPORTED_MODULE_0__.allTruthy),
/* harmony export */   "allNotUndefined": () => (/* reexport safe */ _modules_core__WEBPACK_IMPORTED_MODULE_0__.allNotUndefined),
/* harmony export */   "isJSONString": () => (/* reexport safe */ _modules_core__WEBPACK_IMPORTED_MODULE_0__.isJSONString),
/* harmony export */   "noOpFn": () => (/* reexport safe */ _modules_core__WEBPACK_IMPORTED_MODULE_0__.noOpFn),
/* harmony export */   "is": () => (/* reexport safe */ _modules_core__WEBPACK_IMPORTED_MODULE_0__.is),
/* harmony export */   "shallowEqual": () => (/* reexport safe */ _modules_core__WEBPACK_IMPORTED_MODULE_0__.shallowEqual),
/* harmony export */   "objectPropEqual": () => (/* reexport safe */ _modules_core__WEBPACK_IMPORTED_MODULE_0__.objectPropEqual),
/* harmony export */   "partialShallowEqual": () => (/* reexport safe */ _modules_core__WEBPACK_IMPORTED_MODULE_0__.partialShallowEqual),
/* harmony export */   "shallowObjectDiff": () => (/* reexport safe */ _modules_core__WEBPACK_IMPORTED_MODULE_0__.shallowObjectDiff),
/* harmony export */   "nestedMapSet": () => (/* reexport safe */ _modules_core__WEBPACK_IMPORTED_MODULE_0__.nestedMapSet),
/* harmony export */   "nestedMapHas": () => (/* reexport safe */ _modules_core__WEBPACK_IMPORTED_MODULE_0__.nestedMapHas),
/* harmony export */   "nestedMapGet": () => (/* reexport safe */ _modules_core__WEBPACK_IMPORTED_MODULE_0__.nestedMapGet),
/* harmony export */   "nestedTreeMapSet": () => (/* reexport safe */ _modules_core__WEBPACK_IMPORTED_MODULE_0__.nestedTreeMapSet),
/* harmony export */   "nestedTreeMapHas": () => (/* reexport safe */ _modules_core__WEBPACK_IMPORTED_MODULE_0__.nestedTreeMapHas),
/* harmony export */   "nestedTreeMapGet": () => (/* reexport safe */ _modules_core__WEBPACK_IMPORTED_MODULE_0__.nestedTreeMapGet),
/* harmony export */   "mapYield": () => (/* reexport safe */ _modules_core__WEBPACK_IMPORTED_MODULE_0__.mapYield),
/* harmony export */   "str": () => (/* reexport safe */ _modules_core__WEBPACK_IMPORTED_MODULE_0__.str),
/* harmony export */   "mapObject": () => (/* reexport safe */ _modules_core__WEBPACK_IMPORTED_MODULE_0__.mapObject),
/* harmony export */   "mapToObject": () => (/* reexport safe */ _modules_core__WEBPACK_IMPORTED_MODULE_0__.mapToObject),
/* harmony export */   "propSelection": () => (/* reexport safe */ _modules_core__WEBPACK_IMPORTED_MODULE_0__.propSelection),
/* harmony export */   "prototypeChainProperties": () => (/* reexport safe */ _modules_core__WEBPACK_IMPORTED_MODULE_0__.prototypeChainProperties),
/* harmony export */   "prop": () => (/* reexport safe */ _modules_core__WEBPACK_IMPORTED_MODULE_0__.prop),
/* harmony export */   "defineProperty": () => (/* reexport safe */ _modules_core__WEBPACK_IMPORTED_MODULE_0__.defineProperty),
/* harmony export */   "completeObjectAssign": () => (/* reexport safe */ _modules_core__WEBPACK_IMPORTED_MODULE_0__.completeObjectAssign),
/* harmony export */   "_": () => (/* reexport safe */ _modules_fn__WEBPACK_IMPORTED_MODULE_1__._),
/* harmony export */   "proceedCallingFn": () => (/* reexport safe */ _modules_fn__WEBPACK_IMPORTED_MODULE_1__.proceedCallingFn),
/* harmony export */   "curry": () => (/* reexport safe */ _modules_fn__WEBPACK_IMPORTED_MODULE_1__.curry),
/* harmony export */   "POJOCurry": () => (/* reexport safe */ _modules_fn__WEBPACK_IMPORTED_MODULE_1__.POJOCurry),
/* harmony export */   "compose": () => (/* reexport safe */ _modules_fn__WEBPACK_IMPORTED_MODULE_1__.compose),
/* harmony export */   "pipe": () => (/* reexport safe */ _modules_fn__WEBPACK_IMPORTED_MODULE_1__.pipe),
/* harmony export */   "pick": () => (/* reexport safe */ _modules_fn__WEBPACK_IMPORTED_MODULE_1__.pick),
/* harmony export */   "liftBinaryFn": () => (/* reexport safe */ _modules_fn__WEBPACK_IMPORTED_MODULE_1__.liftBinaryFn),
/* harmony export */   "juxt": () => (/* reexport safe */ _modules_fn__WEBPACK_IMPORTED_MODULE_1__.juxt),
/* harmony export */   "converge": () => (/* reexport safe */ _modules_fn__WEBPACK_IMPORTED_MODULE_1__.converge),
/* harmony export */   "execIfPOJOHas": () => (/* reexport safe */ _modules_fn__WEBPACK_IMPORTED_MODULE_1__.execIfPOJOHas),
/* harmony export */   "execWithExistentProps": () => (/* reexport safe */ _modules_fn__WEBPACK_IMPORTED_MODULE_1__.execWithExistentProps),
/* harmony export */   "forGen": () => (/* reexport safe */ _modules_fn__WEBPACK_IMPORTED_MODULE_1__.forGen),
/* harmony export */   "flatten": () => (/* reexport safe */ _modules_fn__WEBPACK_IMPORTED_MODULE_1__.flatten),
/* harmony export */   "flattenDeep": () => (/* reexport safe */ _modules_fn__WEBPACK_IMPORTED_MODULE_1__.flattenDeep),
/* harmony export */   "cartesianProduct": () => (/* reexport safe */ _modules_fn__WEBPACK_IMPORTED_MODULE_1__.cartesianProduct),
/* harmony export */   "identityFn": () => (/* reexport safe */ _modules_fn__WEBPACK_IMPORTED_MODULE_1__.identityFn),
/* harmony export */   "identityArgsFn": () => (/* reexport safe */ _modules_fn__WEBPACK_IMPORTED_MODULE_1__.identityArgsFn),
/* harmony export */   "chain": () => (/* reexport safe */ _modules_fn__WEBPACK_IMPORTED_MODULE_1__.chain),
/* harmony export */   "stringHashArray": () => (/* reexport safe */ _modules_hash__WEBPACK_IMPORTED_MODULE_2__.stringHashArray),
/* harmony export */   "hashString": () => (/* reexport safe */ _modules_hash__WEBPACK_IMPORTED_MODULE_2__.hashString),
/* harmony export */   "onePassStringHash": () => (/* reexport safe */ _modules_hash__WEBPACK_IMPORTED_MODULE_2__.onePassStringHash),
/* harmony export */   "timeout": () => (/* reexport safe */ _modules_promise__WEBPACK_IMPORTED_MODULE_3__.timeout),
/* harmony export */   "minDelayPromise": () => (/* reexport safe */ _modules_promise__WEBPACK_IMPORTED_MODULE_3__.minDelayPromise),
/* harmony export */   "maxDelayFallbackPromise": () => (/* reexport safe */ _modules_promise__WEBPACK_IMPORTED_MODULE_3__.maxDelayFallbackPromise),
/* harmony export */   "applyAsync": () => (/* reexport safe */ _modules_promise__WEBPACK_IMPORTED_MODULE_3__.applyAsync),
/* harmony export */   "composeAsync": () => (/* reexport safe */ _modules_promise__WEBPACK_IMPORTED_MODULE_3__.composeAsync),
/* harmony export */   "buildQueryString": () => (/* reexport safe */ _modules_web__WEBPACK_IMPORTED_MODULE_4__.buildQueryString),
/* harmony export */   "formData": () => (/* reexport safe */ _modules_web__WEBPACK_IMPORTED_MODULE_4__.formData),
/* harmony export */   "xhr": () => (/* reexport safe */ _modules_web__WEBPACK_IMPORTED_MODULE_4__.xhr),
/* harmony export */   "checkNetwork": () => (/* reexport safe */ _modules_web__WEBPACK_IMPORTED_MODULE_4__.checkNetwork),
/* harmony export */   "waitNetwork": () => (/* reexport safe */ _modules_web__WEBPACK_IMPORTED_MODULE_4__.waitNetwork),
/* harmony export */   "setCookie": () => (/* reexport safe */ _modules_web__WEBPACK_IMPORTED_MODULE_4__.setCookie),
/* harmony export */   "getCookie": () => (/* reexport safe */ _modules_web__WEBPACK_IMPORTED_MODULE_4__.getCookie),
/* harmony export */   "unsetCookie": () => (/* reexport safe */ _modules_web__WEBPACK_IMPORTED_MODULE_4__.unsetCookie),
/* harmony export */   "isInViewport": () => (/* reexport safe */ _modules_web__WEBPACK_IMPORTED_MODULE_4__.isInViewport),
/* harmony export */   "isScrolledIntoView": () => (/* reexport safe */ _modules_web__WEBPACK_IMPORTED_MODULE_4__.isScrolledIntoView),
/* harmony export */   "hasVerticalScrollbar": () => (/* reexport safe */ _modules_web__WEBPACK_IMPORTED_MODULE_4__.hasVerticalScrollbar),
/* harmony export */   "hasHorizontalScrollbar": () => (/* reexport safe */ _modules_web__WEBPACK_IMPORTED_MODULE_4__.hasHorizontalScrollbar),
/* harmony export */   "elementUniqueId": () => (/* reexport safe */ _modules_web__WEBPACK_IMPORTED_MODULE_4__.elementUniqueId),
/* harmony export */   "getElementComputedStyle": () => (/* reexport safe */ _modules_web__WEBPACK_IMPORTED_MODULE_4__.getElementComputedStyle),
/* harmony export */   "elementInnerDimensions": () => (/* reexport safe */ _modules_web__WEBPACK_IMPORTED_MODULE_4__.elementInnerDimensions),
/* harmony export */   "countTextareaLines": () => (/* reexport safe */ _modules_web__WEBPACK_IMPORTED_MODULE_4__.countTextareaLines),
/* harmony export */   "isScrollOnBottom": () => (/* reexport safe */ _modules_web__WEBPACK_IMPORTED_MODULE_4__.isScrollOnBottom),
/* harmony export */   "getVerticalScrollBarWidth": () => (/* reexport safe */ _modules_web__WEBPACK_IMPORTED_MODULE_4__.getVerticalScrollBarWidth),
/* harmony export */   "isEllipsisActive": () => (/* reexport safe */ _modules_web__WEBPACK_IMPORTED_MODULE_4__.isEllipsisActive),
/* harmony export */   "copyTextToClipboard": () => (/* reexport safe */ _modules_web__WEBPACK_IMPORTED_MODULE_4__.copyTextToClipboard),
/* harmony export */   "rAFLooper": () => (/* reexport safe */ _modules_web__WEBPACK_IMPORTED_MODULE_4__.rAFLooper),
/* harmony export */   "nestedRAF": () => (/* reexport safe */ _modules_web__WEBPACK_IMPORTED_MODULE_4__.nestedRAF),
/* harmony export */   "getRawURIFragment": () => (/* reexport safe */ _modules_web__WEBPACK_IMPORTED_MODULE_4__.getRawURIFragment),
/* harmony export */   "getDecodedURIFragment": () => (/* reexport safe */ _modules_web__WEBPACK_IMPORTED_MODULE_4__.getDecodedURIFragment),
/* harmony export */   "appendEncodedJSONFragmentToURI": () => (/* reexport safe */ _modules_web__WEBPACK_IMPORTED_MODULE_4__.appendEncodedJSONFragmentToURI),
/* harmony export */   "getDecodedJSONFromFragmentURI": () => (/* reexport safe */ _modules_web__WEBPACK_IMPORTED_MODULE_4__.getDecodedJSONFromFragmentURI),
/* harmony export */   "getQueryStringArgsMultiDim": () => (/* reexport safe */ _modules_web__WEBPACK_IMPORTED_MODULE_4__.getQueryStringArgsMultiDim),
/* harmony export */   "cursorFocus": () => (/* reexport safe */ _modules_web__WEBPACK_IMPORTED_MODULE_4__.cursorFocus),
/* harmony export */   "parseQueryStringArgsMultiDim": () => (/* reexport safe */ _modules_web__WEBPACK_IMPORTED_MODULE_4__.parseQueryStringArgsMultiDim),
/* harmony export */   "detectWrapped": () => (/* reexport safe */ _modules_web__WEBPACK_IMPORTED_MODULE_4__.detectWrapped),
/* harmony export */   "maxNestingLevel": () => (/* reexport safe */ _modules_web__WEBPACK_IMPORTED_MODULE_4__.maxNestingLevel),
/* harmony export */   "getScrollableAncestor": () => (/* reexport safe */ _modules_web__WEBPACK_IMPORTED_MODULE_4__.getScrollableAncestor),
/* harmony export */   "smoothScrollToTop": () => (/* reexport safe */ _modules_web__WEBPACK_IMPORTED_MODULE_4__.smoothScrollToTop),
/* harmony export */   "randomInt": () => (/* reexport safe */ _modules_rand__WEBPACK_IMPORTED_MODULE_5__.randomInt),
/* harmony export */   "randomFloat": () => (/* reexport safe */ _modules_rand__WEBPACK_IMPORTED_MODULE_5__.randomFloat),
/* harmony export */   "randomStr": () => (/* reexport safe */ _modules_rand__WEBPACK_IMPORTED_MODULE_5__.randomStr),
/* harmony export */   "randomDifferentFromValue": () => (/* reexport safe */ _modules_rand__WEBPACK_IMPORTED_MODULE_5__.randomDifferentFromValue),
/* harmony export */   "randomArrayShuffle": () => (/* reexport safe */ _modules_rand__WEBPACK_IMPORTED_MODULE_5__.randomArrayShuffle),
/* harmony export */   "uuid": () => (/* reexport safe */ _modules_rand__WEBPACK_IMPORTED_MODULE_5__.uuid),
/* harmony export */   "noncanonicalUUID": () => (/* reexport safe */ _modules_rand__WEBPACK_IMPORTED_MODULE_5__.noncanonicalUUID),
/* harmony export */   "time": () => (/* reexport safe */ _modules_time__WEBPACK_IMPORTED_MODULE_6__.time),
/* harmony export */   "msToTime": () => (/* reexport safe */ _modules_time__WEBPACK_IMPORTED_MODULE_6__.msToTime),
/* harmony export */   "millisecToSec": () => (/* reexport safe */ _modules_time__WEBPACK_IMPORTED_MODULE_6__.millisecToSec),
/* harmony export */   "escapeRegExp": () => (/* reexport safe */ _modules_string__WEBPACK_IMPORTED_MODULE_7__.escapeRegExp),
/* harmony export */   "trimCharacterMask": () => (/* reexport safe */ _modules_string__WEBPACK_IMPORTED_MODULE_7__.trimCharacterMask),
/* harmony export */   "trimCharacterRegex": () => (/* reexport safe */ _modules_string__WEBPACK_IMPORTED_MODULE_7__.trimCharacterRegex),
/* harmony export */   "trim": () => (/* reexport safe */ _modules_string__WEBPACK_IMPORTED_MODULE_7__.trim),
/* harmony export */   "trimLeft": () => (/* reexport safe */ _modules_string__WEBPACK_IMPORTED_MODULE_7__.trimLeft),
/* harmony export */   "trimRight": () => (/* reexport safe */ _modules_string__WEBPACK_IMPORTED_MODULE_7__.trimRight),
/* harmony export */   "concatWithInnerOuterSeparators": () => (/* reexport safe */ _modules_string__WEBPACK_IMPORTED_MODULE_7__.concatWithInnerOuterSeparators),
/* harmony export */   "optionsValueLabel": () => (/* reexport safe */ _modules_string__WEBPACK_IMPORTED_MODULE_7__.optionsValueLabel),
/* harmony export */   "startsWith": () => (/* reexport safe */ _modules_string__WEBPACK_IMPORTED_MODULE_7__.startsWith),
/* harmony export */   "ucaseFirst": () => (/* reexport safe */ _modules_string__WEBPACK_IMPORTED_MODULE_7__.ucaseFirst),
/* harmony export */   "reverseString": () => (/* reexport safe */ _modules_string__WEBPACK_IMPORTED_MODULE_7__.reverseString),
/* harmony export */   "separateIntThousands": () => (/* reexport safe */ _modules_string__WEBPACK_IMPORTED_MODULE_7__.separateIntThousands),
/* harmony export */   "googleMapBestZoomLevelFromBounds": () => (/* reexport safe */ _modules_google__WEBPACK_IMPORTED_MODULE_8__.googleMapBestZoomLevelFromBounds),
/* harmony export */   "delay": () => (/* reexport safe */ _modules_callback__WEBPACK_IMPORTED_MODULE_9__.delay),
/* harmony export */   "debounce": () => (/* reexport safe */ _modules_callback__WEBPACK_IMPORTED_MODULE_9__.debounce),
/* harmony export */   "throttle": () => (/* reexport safe */ _modules_callback__WEBPACK_IMPORTED_MODULE_9__.throttle),
/* harmony export */   "basename": () => (/* reexport safe */ _modules_path__WEBPACK_IMPORTED_MODULE_10__.basename),
/* harmony export */   "pathinfo": () => (/* reexport safe */ _modules_path__WEBPACK_IMPORTED_MODULE_10__.pathinfo),
/* harmony export */   "filenameExtension": () => (/* reexport safe */ _modules_path__WEBPACK_IMPORTED_MODULE_10__.filenameExtension),
/* harmony export */   "dirname": () => (/* reexport safe */ _modules_path__WEBPACK_IMPORTED_MODULE_10__.dirname),
/* harmony export */   "b2d": () => (/* reexport safe */ _modules_convert__WEBPACK_IMPORTED_MODULE_11__.b2d),
/* harmony export */   "d2b": () => (/* reexport safe */ _modules_convert__WEBPACK_IMPORTED_MODULE_11__.d2b),
/* harmony export */   "turnNthBitOff": () => (/* reexport safe */ _modules_bitwise__WEBPACK_IMPORTED_MODULE_12__.turnNthBitOff),
/* harmony export */   "turnNthBitOn": () => (/* reexport safe */ _modules_bitwise__WEBPACK_IMPORTED_MODULE_12__.turnNthBitOn),
/* harmony export */   "toggleNthBit": () => (/* reexport safe */ _modules_bitwise__WEBPACK_IMPORTED_MODULE_12__.toggleNthBit),
/* harmony export */   "checkNthBitOn": () => (/* reexport safe */ _modules_bitwise__WEBPACK_IMPORTED_MODULE_12__.checkNthBitOn),
/* harmony export */   "getLuminance": () => (/* reexport safe */ _modules_color__WEBPACK_IMPORTED_MODULE_13__.getLuminance),
/* harmony export */   "yieldCombinationsWithoutRepetition": () => (/* reexport safe */ _modules_combinatorics__WEBPACK_IMPORTED_MODULE_14__.yieldCombinationsWithoutRepetition),
/* harmony export */   "uniqueProgressiveIncrementalCombinations": () => (/* reexport safe */ _modules_combinatorics__WEBPACK_IMPORTED_MODULE_14__.uniqueProgressiveIncrementalCombinations),
/* harmony export */   "yieldUniqueProgressiveIncrementalCombinations": () => (/* reexport safe */ _modules_combinatorics__WEBPACK_IMPORTED_MODULE_14__.yieldUniqueProgressiveIncrementalCombinations),
/* harmony export */   "yieldAllSubsequences": () => (/* reexport safe */ _modules_combinatorics__WEBPACK_IMPORTED_MODULE_14__.yieldAllSubsequences),
/* harmony export */   "yieldUniqueSubsequences": () => (/* reexport safe */ _modules_combinatorics__WEBPACK_IMPORTED_MODULE_14__.yieldUniqueSubsequences),
/* harmony export */   "yieldPermutations": () => (/* reexport safe */ _modules_combinatorics__WEBPACK_IMPORTED_MODULE_14__.yieldPermutations),
/* harmony export */   "round": () => (/* reexport safe */ _modules_math__WEBPACK_IMPORTED_MODULE_15__.round),
/* harmony export */   "sum": () => (/* reexport safe */ _modules_math__WEBPACK_IMPORTED_MODULE_15__.sum),
/* harmony export */   "intDiv": () => (/* reexport safe */ _modules_math__WEBPACK_IMPORTED_MODULE_15__.intDiv),
/* harmony export */   "isEven": () => (/* reexport safe */ _modules_math__WEBPACK_IMPORTED_MODULE_15__.isEven),
/* harmony export */   "isOdd": () => (/* reexport safe */ _modules_math__WEBPACK_IMPORTED_MODULE_15__.isOdd),
/* harmony export */   "proportion": () => (/* reexport safe */ _modules_math__WEBPACK_IMPORTED_MODULE_15__.proportion),
/* harmony export */   "unshiftArray": () => (/* reexport safe */ _modules_array__WEBPACK_IMPORTED_MODULE_16__.unshiftArray),
/* harmony export */   "cloneArray": () => (/* reexport safe */ _modules_array__WEBPACK_IMPORTED_MODULE_16__.cloneArray),
/* harmony export */   "arraySliceFromValueToValue": () => (/* reexport safe */ _modules_array__WEBPACK_IMPORTED_MODULE_16__.arraySliceFromValueToValue),
/* harmony export */   "areArrayItemsAllCoercibleToNumber": () => (/* reexport safe */ _modules_array__WEBPACK_IMPORTED_MODULE_16__.areArrayItemsAllCoercibleToNumber),
/* harmony export */   "arrayOrArrayLike": () => (/* reexport safe */ _modules_array__WEBPACK_IMPORTED_MODULE_16__.arrayOrArrayLike),
/* harmony export */   "lastOfArray": () => (/* reexport safe */ _modules_array__WEBPACK_IMPORTED_MODULE_16__.lastOfArray),
/* harmony export */   "firstOfArray": () => (/* reexport safe */ _modules_array__WEBPACK_IMPORTED_MODULE_16__.firstOfArray),
/* harmony export */   "arrayFindReverse": () => (/* reexport safe */ _modules_array__WEBPACK_IMPORTED_MODULE_16__.arrayFindReverse),
/* harmony export */   "arrayMax": () => (/* reexport safe */ _modules_array__WEBPACK_IMPORTED_MODULE_16__.arrayMax),
/* harmony export */   "arrayMin": () => (/* reexport safe */ _modules_array__WEBPACK_IMPORTED_MODULE_16__.arrayMin),
/* harmony export */   "sortNums": () => (/* reexport safe */ _modules_array__WEBPACK_IMPORTED_MODULE_16__.sortNums),
/* harmony export */   "objectMin": () => (/* reexport safe */ _modules_object__WEBPACK_IMPORTED_MODULE_17__.objectMin),
/* harmony export */   "mean": () => (/* reexport safe */ _modules_stats__WEBPACK_IMPORTED_MODULE_18__.mean),
/* harmony export */   "median": () => (/* reexport safe */ _modules_stats__WEBPACK_IMPORTED_MODULE_18__.median),
/* harmony export */   "minAbsDeviationFromValue": () => (/* reexport safe */ _modules_stats__WEBPACK_IMPORTED_MODULE_18__.minAbsDeviationFromValue),
/* harmony export */   "minAbsDeviationFromExcludedValue": () => (/* reexport safe */ _modules_stats__WEBPACK_IMPORTED_MODULE_18__.minAbsDeviationFromExcludedValue),
/* harmony export */   "clampLat": () => (/* reexport safe */ _modules_map_coordinates__WEBPACK_IMPORTED_MODULE_19__.clampLat),
/* harmony export */   "wrapLng": () => (/* reexport safe */ _modules_map_coordinates__WEBPACK_IMPORTED_MODULE_19__.wrapLng),
/* harmony export */   "normalizeLat": () => (/* reexport safe */ _modules_map_coordinates__WEBPACK_IMPORTED_MODULE_19__.normalizeLat),
/* harmony export */   "normalizeLng": () => (/* reexport safe */ _modules_map_coordinates__WEBPACK_IMPORTED_MODULE_19__.normalizeLng),
/* harmony export */   "xrange": () => (/* reexport safe */ _modules_iterator__WEBPACK_IMPORTED_MODULE_20__.xrange),
/* harmony export */   "isValidDate": () => (/* reexport safe */ _modules_date__WEBPACK_IMPORTED_MODULE_21__.isValidDate),
/* harmony export */   "isValidDateTimeStr": () => (/* reexport safe */ _modules_date__WEBPACK_IMPORTED_MODULE_21__.isValidDateTimeStr),
/* harmony export */   "filterInt": () => (/* reexport safe */ _modules_constraint__WEBPACK_IMPORTED_MODULE_22__.filterInt),
/* harmony export */   "filterFloat": () => (/* reexport safe */ _modules_constraint__WEBPACK_IMPORTED_MODULE_22__.filterFloat)
/* harmony export */ });
/* harmony import */ var _modules_core__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./modules/core */ "./node_modules/js-utl/dist/es6/modules/core/index.js");
/* harmony import */ var _modules_fn__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./modules/fn */ "./node_modules/js-utl/dist/es6/modules/fn.js");
/* harmony import */ var _modules_hash__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./modules/hash */ "./node_modules/js-utl/dist/es6/modules/hash.js");
/* harmony import */ var _modules_promise__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./modules/promise */ "./node_modules/js-utl/dist/es6/modules/promise.js");
/* harmony import */ var _modules_web__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./modules/web */ "./node_modules/js-utl/dist/es6/modules/web.js");
/* harmony import */ var _modules_rand__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ./modules/rand */ "./node_modules/js-utl/dist/es6/modules/rand.js");
/* harmony import */ var _modules_time__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ./modules/time */ "./node_modules/js-utl/dist/es6/modules/time.js");
/* harmony import */ var _modules_string__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! ./modules/string */ "./node_modules/js-utl/dist/es6/modules/string.js");
/* harmony import */ var _modules_google__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! ./modules/google */ "./node_modules/js-utl/dist/es6/modules/google.js");
/* harmony import */ var _modules_callback__WEBPACK_IMPORTED_MODULE_9__ = __webpack_require__(/*! ./modules/callback */ "./node_modules/js-utl/dist/es6/modules/callback.js");
/* harmony import */ var _modules_path__WEBPACK_IMPORTED_MODULE_10__ = __webpack_require__(/*! ./modules/path */ "./node_modules/js-utl/dist/es6/modules/path.js");
/* harmony import */ var _modules_convert__WEBPACK_IMPORTED_MODULE_11__ = __webpack_require__(/*! ./modules/convert */ "./node_modules/js-utl/dist/es6/modules/convert.js");
/* harmony import */ var _modules_bitwise__WEBPACK_IMPORTED_MODULE_12__ = __webpack_require__(/*! ./modules/bitwise */ "./node_modules/js-utl/dist/es6/modules/bitwise.js");
/* harmony import */ var _modules_color__WEBPACK_IMPORTED_MODULE_13__ = __webpack_require__(/*! ./modules/color */ "./node_modules/js-utl/dist/es6/modules/color.js");
/* harmony import */ var _modules_combinatorics__WEBPACK_IMPORTED_MODULE_14__ = __webpack_require__(/*! ./modules/combinatorics */ "./node_modules/js-utl/dist/es6/modules/combinatorics.js");
/* harmony import */ var _modules_math__WEBPACK_IMPORTED_MODULE_15__ = __webpack_require__(/*! ./modules/math */ "./node_modules/js-utl/dist/es6/modules/math.js");
/* harmony import */ var _modules_array__WEBPACK_IMPORTED_MODULE_16__ = __webpack_require__(/*! ./modules/array */ "./node_modules/js-utl/dist/es6/modules/array.js");
/* harmony import */ var _modules_object__WEBPACK_IMPORTED_MODULE_17__ = __webpack_require__(/*! ./modules/object */ "./node_modules/js-utl/dist/es6/modules/object.js");
/* harmony import */ var _modules_stats__WEBPACK_IMPORTED_MODULE_18__ = __webpack_require__(/*! ./modules/stats */ "./node_modules/js-utl/dist/es6/modules/stats.js");
/* harmony import */ var _modules_map_coordinates__WEBPACK_IMPORTED_MODULE_19__ = __webpack_require__(/*! ./modules/map-coordinates */ "./node_modules/js-utl/dist/es6/modules/map-coordinates.js");
/* harmony import */ var _modules_iterator__WEBPACK_IMPORTED_MODULE_20__ = __webpack_require__(/*! ./modules/iterator */ "./node_modules/js-utl/dist/es6/modules/iterator.js");
/* harmony import */ var _modules_date__WEBPACK_IMPORTED_MODULE_21__ = __webpack_require__(/*! ./modules/date */ "./node_modules/js-utl/dist/es6/modules/date.js");
/* harmony import */ var _modules_constraint__WEBPACK_IMPORTED_MODULE_22__ = __webpack_require__(/*! ./modules/constraint */ "./node_modules/js-utl/dist/es6/modules/constraint.js");
/*
 * Copyright (c) 2021 Anton Bagdatyev (Tonix)
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */

/**
 * Public API exposed to client code.
 */























//# sourceMappingURL=index.js.map

/***/ }),

/***/ "./node_modules/js-utl/dist/es6/modules/array.js":
/*!*******************************************************!*\
  !*** ./node_modules/js-utl/dist/es6/modules/array.js ***!
  \*******************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "unshiftArray": () => (/* binding */ unshiftArray),
/* harmony export */   "cloneArray": () => (/* binding */ cloneArray),
/* harmony export */   "arraySliceFromValueToValue": () => (/* binding */ arraySliceFromValueToValue),
/* harmony export */   "areArrayItemsAllCoercibleToNumber": () => (/* binding */ areArrayItemsAllCoercibleToNumber),
/* harmony export */   "arrayOrArrayLike": () => (/* binding */ arrayOrArrayLike),
/* harmony export */   "lastOfArray": () => (/* binding */ lastOfArray),
/* harmony export */   "firstOfArray": () => (/* binding */ firstOfArray),
/* harmony export */   "arrayFindReverse": () => (/* binding */ arrayFindReverse),
/* harmony export */   "arrayMax": () => (/* binding */ arrayMax),
/* harmony export */   "arrayMin": () => (/* binding */ arrayMin),
/* harmony export */   "sortNums": () => (/* binding */ sortNums)
/* harmony export */ });
/*
 * Copyright (c) 2021 Anton Bagdatyev (Tonix)
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */

/**
 * Array-related utility functions.
 */

/**
 * Unshifts an array.
 *
 * @param {Array} arr The array.
 * @param {*} item The item to unshift.
 * @return {undefined}
 */
function unshiftArray(arr, item) {
  let len = arr.length;

  while (len) {
    arr[len] = arr[len - 1];
    len--;
  }

  arr[0] = item;
}
/**
 * Clones an array.
 *
 * @param {Array} arr The array to clone.
 * @return {Array} The cloned array.
 */

function cloneArray(arr) {
  return arr.slice(0);
}
/**
 * Gets a slice of an array from a value up until another.
 *
 * @param {Array} arr The input array.
 * @param {number} from The "from" lower value.
 * @param {number} to The "two" upper value.
 * @return {Array} The slice as a new array.
 */

function arraySliceFromValueToValue(arr, from, to) {
  const ret = [];
  let include = false;

  for (const value of arr) {
    if (!include && value == from) {
      ret.push(value);
      include = true;
    } else if (include && value == to) {
      ret.push(value);
      break;
    } else if (include) {
      ret.push(value);
    }
  }

  return ret;
}
/**
 * Tests whether all the elements of an array are coercible to a number or not.
 *
 * @param {Array} array An array.
 * @return {boolean} True if all the elements are coercible to a number, false otherwise.
 */

function areArrayItemsAllCoercibleToNumber(array) {
  const res = !array.some(isNaN);
  return res;
}
/**
 * Copies an array or converts an array-like object to a new array.
 *
 * @param {*} arg Array or array-like object.
 * @return {Array} An array.
 */

function arrayOrArrayLike(arg) {
  return Array.prototype.slice.call(arg);
}
/**
 * Returns the last element of the given array.
 *
 * @param {Array} array An array.
 * @return {*} The last element of the array or undefined if there isn't one.
 */

function lastOfArray(array) {
  return array[array.length - 1];
}
/**
 * Returns the first element of the given array.
 *
 * @param {Array} array An array.
 * @return {*} The first element of the array or undefined if there isn't one.
 */

function firstOfArray(array) {
  return array[0];
}
/**
 * Like {@link Array.prototype.find}, but in reverse order.
 *
 * @param {Array} array An array.
 * @param {Function} fn Function to use for the test. The function will receive the array element as parameter.
 * @return {*} The first element which satisfies the test in the array by seeking for the element in reverse order
 *             (i.e. the last element of the array for which the test is satisfied).
 *             If no element satisfies the test, "undefined" is returned.
 */

function arrayFindReverse(array, fn) {
  let l = array.length;
  let ret = void 0;

  while (l) {
    l--;

    if (fn(array[l])) {
      ret = array[l];
      break;
    }
  }

  return ret;
}
/**
 * Finds the maximum value of an array of numbers.
 *
 * @param {number[]} array An array of numbers.
 * @return {number|undefined} The maximum value of the array, or "undefined"
 *                            if the given array is empty.
 */

const arrayMax = array => array.length ? array.reduce((carry, current) => current > carry ? current : carry, -Infinity) : void 0;
/**
 * Finds the minimum value of an array of numbers.
 *
 * @param {number[]} array An array of numbers.
 * @return {number|undefined} The minimum value of the array, or "undefined"
 *                            if the given array is empty.
 */

const arrayMin = array => array.length ? array.reduce((carry, current) => current < carry ? current : carry, +Infinity) : void 0;
/**
 * Sorts an array of numbers returning a new array with the sorted
 * numbers (does not mutate the original).
 *
 * @param {number[]} arrayOfNums An array of numbers.
 * @param {boolean} [desc] True for descending order, false for ascending order (default).
 * @return {number[]} A new array with the sorted numbers.
 */

const sortNums = (arrayOfNums, desc = false) => [...arrayOfNums].sort((a, b) => !desc // asc
? a - b : // desc
b - a);
//# sourceMappingURL=array.js.map

/***/ }),

/***/ "./node_modules/js-utl/dist/es6/modules/bitwise.js":
/*!*********************************************************!*\
  !*** ./node_modules/js-utl/dist/es6/modules/bitwise.js ***!
  \*********************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "turnNthBitOff": () => (/* binding */ turnNthBitOff),
/* harmony export */   "turnNthBitOn": () => (/* binding */ turnNthBitOn),
/* harmony export */   "toggleNthBit": () => (/* binding */ toggleNthBit),
/* harmony export */   "checkNthBitOn": () => (/* binding */ checkNthBitOn)
/* harmony export */ });
/*
 * Copyright (c) 2021 Anton Bagdatyev (Tonix)
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */

/**
 * Bitwise utility functions.
 */

/**
 * Turns nth bit off.
 *
 * @param {number} num A number.
 * @param {number} nth Nth bit to turn off (starting from 1 for the rightmost bit).
 * @return {number} New number with the nth bit turned off.
 */
const turnNthBitOff = (num, nth) => num & ~(1 << nth - 1);
/**
 * Turns nth bit on.
 *
 * @param {number} num A number.
 * @param {number} nth Nth bit to turn on (starting from 1 for the rightmost bit).
 * @return {number} New number with the nth bit turned off.
 */

const turnNthBitOn = (num, nth) => num | 1 << nth - 1;
/**
 * Toggles nth bit.
 *
 * @param {number} num A number.
 * @param {number} nth Nth bit to toggle (starting from 1 for the rightmost bit).
 * @return {number} New number with the nth bit toggled.
 */

const toggleNthBit = (num, nth) => num ^ 1 << nth - 1;
/**
 * Checks if nth bit is on.
 *
 * @param {number} num A number.
 * @param {number} nth Nth bit to check.
 * @param {number} 0 if the nth bit is off, otherwise a number greater than 0 if the nth bit is on.
 */

const checkNthBitOn = (num, nth) => num & 1 << nth - 1;
//# sourceMappingURL=bitwise.js.map

/***/ }),

/***/ "./node_modules/js-utl/dist/es6/modules/callback.js":
/*!**********************************************************!*\
  !*** ./node_modules/js-utl/dist/es6/modules/callback.js ***!
  \**********************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "delay": () => (/* binding */ delay),
/* harmony export */   "debounce": () => (/* binding */ debounce),
/* harmony export */   "throttle": () => (/* binding */ throttle)
/* harmony export */ });
/* harmony import */ var _core__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./core */ "./node_modules/js-utl/dist/es6/modules/core/index.js");
/*
 * Copyright (c) 2021 Anton Bagdatyev (Tonix)
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */

/**
 * Utility functions for callback handling (like debouncing, throttling).
 */

/**
 * Delays execution of a callback and cancels a previously registered callback if it wasn't executed yet.
 */

const delay = function () {
  let timer = 0;
  /**
   * Inner function.
   *
   * @param {Function} callback The callback to execute.
   * @param {number} ms Milliseconds to wait before executing the callback.
   * @return {undefined}
   */

  return function (callback, ms) {
    clearTimeout(timer);
    timer = setTimeout(function () {
      callback();
    }, ms);
  };
}();
/**
 * Debounces a function.
 *
 * @param {Function} fn A function.
 * @param {number} wait Wait interval in milliseconds.
 * @return {Function} A new function, debounced.
 */

function debounce(fn, wait) {
  let timer = void 0;
  return function (...args) {
    !(0,_core__WEBPACK_IMPORTED_MODULE_0__.isUndefined)(timer) && clearTimeout(timer);
    timer = setTimeout(function () {
      fn(...args);
    }, wait);
  };
}
/**
 * Throttles a function.
 *
 * @see https://www.sitepoint.com/throttle-scroll-events/
 *
 * @param {Function} fn A function.
 * @param {number} wait Wait interval in milliseconds.
 * @return {Function} A new function, throttled.
 */

function throttle(fn, wait) {
  let time = Date.now();
  return function (...args) {
    if (time + wait - Date.now() < 0) {
      fn(...args);
      time = Date.now();
    }
  };
}
//# sourceMappingURL=callback.js.map

/***/ }),

/***/ "./node_modules/js-utl/dist/es6/modules/color.js":
/*!*******************************************************!*\
  !*** ./node_modules/js-utl/dist/es6/modules/color.js ***!
  \*******************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "getLuminance": () => (/* binding */ getLuminance)
/* harmony export */ });
/*
 * Copyright (c) 2021 Anton Bagdatyev (Tonix)
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */

/**
 * Color-related utility functions.
 */

/**
 * Gets a color's luminance.
 *
 * @param {number} color Color int value (RGB).
 * @return {number} An int greater than 160 if the color is considered dark and less than or
 *                  equal to 160 if the color is considered light.
 */
function getLuminance(color) {
  const c = parseInt(color, 16);
  const r = (c & 0xff0000) >> 16;
  const g = (c & 0x00ff00) >> 8;
  const b = c & 0x0000ff;
  return 0.299 * r + 0.587 * g + 0.114 * b;
}
//# sourceMappingURL=color.js.map

/***/ }),

/***/ "./node_modules/js-utl/dist/es6/modules/combinatorics.js":
/*!***************************************************************!*\
  !*** ./node_modules/js-utl/dist/es6/modules/combinatorics.js ***!
  \***************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "yieldCombinationsWithoutRepetition": () => (/* binding */ yieldCombinationsWithoutRepetition),
/* harmony export */   "uniqueProgressiveIncrementalCombinations": () => (/* binding */ uniqueProgressiveIncrementalCombinations),
/* harmony export */   "yieldUniqueProgressiveIncrementalCombinations": () => (/* binding */ yieldUniqueProgressiveIncrementalCombinations),
/* harmony export */   "yieldAllSubsequences": () => (/* binding */ yieldAllSubsequences),
/* harmony export */   "yieldUniqueSubsequences": () => (/* binding */ yieldUniqueSubsequences),
/* harmony export */   "yieldPermutations": () => (/* binding */ yieldPermutations)
/* harmony export */ });
/* harmony import */ var _core__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./core */ "./node_modules/js-utl/dist/es6/modules/core/index.js");
/* harmony import */ var _array__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./array */ "./node_modules/js-utl/dist/es6/modules/array.js");
/*
 * Copyright (c) 2021 Anton Bagdatyev (Tonix)
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */

/**
 * Combinatorics utility functions.
 */


/**
 * Yields all the combinations of an array without repetitions (binomial coefficient).
 *
 * @generator
 * @param {Array} items An array.
 * @param {number} numberOfItemsPerCombination The number of elements in each combination
 *                                             (this function assumes it to be less than "items.length"
 *                                             and greater than 0).
 * @param {boolean} yieldCopy True if the yielded combination should be a copy (default) or
 *                            the internal array used during the generation of the current combination.
 * @yields {Array} The next combination.
 */

const yieldCombinationsWithoutRepetition = function* (items, numberOfItemsPerCombination, yieldCopy = true) {
  const l = items.length;
  const prefix = [];

  const recurse = function* recurse(items, l, prefix, numberOfItemsPerCombination, nextIndex = 0) {
    // Same as:
    // if (nextIndex === numberOfItemsPerCombination) {
    if (prefix.length === numberOfItemsPerCombination) {
      // Base case:
      yield yieldCopy ? (0,_array__WEBPACK_IMPORTED_MODULE_1__.arrayOrArrayLike)(prefix) : prefix;
    } else {
      // Recurrent case:
      for (let i = nextIndex; i < l && // Remaining needed items
      numberOfItemsPerCombination - prefix.length <= // Remaining available items
      l - i; i++) {
        prefix.push(items[i]);
        yield* recurse(items, l, prefix, numberOfItemsPerCombination, i + 1);
        prefix.pop();
      }
    }
  };

  yield* recurse(items, l, prefix, numberOfItemsPerCombination);
};
/**
 * Generate unique, progressive and incremental combinations.
 *
 * @param {Array} items An array of items.
 * @return {Array[]} An array of arrays, each representing a unique progressive incremental combination.
 *                   An empty array is returned if the given items array is empty.
 */

const uniqueProgressiveIncrementalCombinations = items => {
  const len = items.length;

  if (len === 0) {
    return [];
  }

  const last = (0,_array__WEBPACK_IMPORTED_MODULE_1__.arrayOrArrayLike)(items); // Shallow copy/clone of the given array.

  if (len === 1) {
    // [1] => [[1]]
    return [last];
  }

  const ret = []; // [1], [2], [3], ..., [n]

  items.map(item => ret.push([item]));

  if (len > 2) {
    // There  are at least three items.
    for (let numberOfItemsPerCombination = 2; numberOfItemsPerCombination < len; numberOfItemsPerCombination++) {
      for (const combination of yieldCombinationsWithoutRepetition(items, numberOfItemsPerCombination)) {
        ret.push(combination);
      }
    }
  }

  ret.push(last);
  return ret;
};
/**
 * Yields unique, progressive and incremental combinations.
 *
 * @generator
 * @param {Array} items An array of items.
 * @param {boolean} yieldCopy True if some of the yielded combinations should be a copy (default)
 *                         of the corresponding internal array used during the generation of the current combination
 *                         or that same array should be returned (if "yieldCopy" is "false").
 * @yields {Array} An array, each representing the next unique progressive incremental combination.
 *                 An empty array is yielded if the given items array is empty.
 */

const yieldUniqueProgressiveIncrementalCombinations = function* (items, yieldCopy = true) {
  const len = items.length;

  if (len === 0) {
    return;
  }

  const last = yieldCopy ? (0,_array__WEBPACK_IMPORTED_MODULE_1__.arrayOrArrayLike)(items) : items; // Shallow copy/clone of the given array.

  if (len === 1) {
    // [1] => [[1]]
    yield last;
    return;
  } // [1], [2], [3], ..., [n]


  yield* (0,_core__WEBPACK_IMPORTED_MODULE_0__.mapYield)(items, item => [item]);

  if (len > 2) {
    // There  are at least three items.
    for (let numberOfItemsPerCombination = 2; numberOfItemsPerCombination < len; numberOfItemsPerCombination++) {
      for (const combination of yieldCombinationsWithoutRepetition(items, numberOfItemsPerCombination, yieldCopy)) {
        yield combination;
      }
    }
  }

  yield last;
};
/**
 * Yields all the subsequences of the given array of items.
 *
 * @generator
 * @param {Array} items An array of items to use to yield subsequences.
 * @yields {Array} The next subsequence.
 */

const yieldAllSubsequences = function* (items) {
  const l = items.length;

  for (let i = 0; i <= l; i++) {
    for (const combination of yieldCombinationsWithoutRepetition(items, i)) {
      yield combination;
    }
  }
};
/**
 * Yields only the unique subsequences of the given array of items.
 *
 * @generator
 * @param {Array} items An array of items to use to yield subsequences.
 * @yields {Array} The next unique subsequence.
 */

const yieldUniqueSubsequences = function* (items) {
  const map = new Map();
  yield [];

  for (const subsequence of yieldAllSubsequences(items)) {
    if (!(0,_core__WEBPACK_IMPORTED_MODULE_0__.nestedMapHas)(map, subsequence)) {
      (0,_core__WEBPACK_IMPORTED_MODULE_0__.nestedMapSet)(map, subsequence, true);
      yield subsequence;
    }
  }
};
/**
 * Yields all the permutations of the given array of items.
 *
 * @generator
 * @param {Array} items An array of items to use to yield permutations.
 * @param {boolean} yieldCopy True if the yielded permutation should be a copy (default) or
 *                            the internal array used during the generation of the current permutation.
 * @yields {Array} The next permutation.
 */

const yieldPermutations = function* (items, yieldCopy = true) {
  const currentPermutationPrefix = [];
  const currentPermutationIndicesMap = {};

  const permute = function* () {
    if (currentPermutationPrefix.length === items.length) {
      yield yieldCopy ? (0,_array__WEBPACK_IMPORTED_MODULE_1__.arrayOrArrayLike)(currentPermutationPrefix) : currentPermutationPrefix;
    } else {
      for (let i = 0; i < items.length; i++) {
        if (currentPermutationIndicesMap[i]) {
          continue;
        }

        currentPermutationPrefix.push(items[i]);
        currentPermutationIndicesMap[i] = true;
        yield* permute();
        delete currentPermutationIndicesMap[i];
        currentPermutationPrefix.pop();
      }
    }
  };

  yield* permute();
};
//# sourceMappingURL=combinatorics.js.map

/***/ }),

/***/ "./node_modules/js-utl/dist/es6/modules/constraint.js":
/*!************************************************************!*\
  !*** ./node_modules/js-utl/dist/es6/modules/constraint.js ***!
  \************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "filterInt": () => (/* binding */ filterInt),
/* harmony export */   "filterFloat": () => (/* binding */ filterFloat)
/* harmony export */ });
/*
 * Copyright (c) 2021 Anton Bagdatyev (Tonix)
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */

/**
 * Constraints and filtering utility functions.
 */

/**
 * Filters the given string so that it only includes digit (0-9)
 * and an optional leading sign ("+", which is removed if present, or "-"),
 * i.e. represents a valid integer parsable with "Number.parseInt()".
 *
 * @param {string} str The input string.
 * @return {string} The valid input string or an empty string if there isn't any digit.
 */
function filterInt(str) {
  let filtered = str.replace(/[^0-9]/g, "");
  filtered = filtered.replace(/^[0]+([1-9])/, "$1");
  filtered = filtered.replace(/^[0]+$/, "0");

  if (str && filtered.length && str[0] === "-") {
    filtered = `-${filtered}`;
  }

  return filtered;
}
/**
 * Filters the given string so that it only includes digits (0-9), a decimal separator ("." character)
 * and an optional leading sign ("+", which is removed if present, or "-"),
 * i.e. represents a valid float parsable with "Number.parseFloat()".
 *
 * @param {string} str The input string.
 * @return {string} The valid float string or an empty string if there isn't any digit or decimal separator.
 *                  If there isn't any digit in the input string and there is a decimal separator ("." character),
 *                  the returned string will be "0.", i.e. a parsable "0." string.
 */

function filterFloat(str) {
  let filtered = str.replace(/[^0-9.]/g, "");
  const regex = /(\..*)\./g;
  const replace = "$1";

  do {
    filtered = filtered.replace(regex, replace);
  } while (filtered != filtered.replace(regex, replace));

  filtered === "." ? filtered = "0." : filtered;
  filtered = filtered.replace(/^[0]+([1-9])/, "$1");
  filtered = filtered.replace(/^[0]+($|\.)/, "0$1");

  if (str && filtered.length && str[0] === "-") {
    filtered = `-${filtered}`;
  }

  return filtered;
}
//# sourceMappingURL=constraint.js.map

/***/ }),

/***/ "./node_modules/js-utl/dist/es6/modules/convert.js":
/*!*********************************************************!*\
  !*** ./node_modules/js-utl/dist/es6/modules/convert.js ***!
  \*********************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "b2d": () => (/* binding */ b2d),
/* harmony export */   "d2b": () => (/* binding */ d2b)
/* harmony export */ });
/*
 * Copyright (c) 2021 Anton Bagdatyev (Tonix)
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */

/**
 * Utility functions related to conversion of data from one format to another.
 */

/**
 * Binary to decimal.
 *
 * @param {string} x Binary string.
 * @return {number} Decimal number.
 */
const b2d = x => parseInt(x, 2);
/**
 * Decimal to binary.
 *
 * @param {number} x Decimal number.
 * @return {string} Binary representation.
 */

const d2b = x => x.toString(2);
//# sourceMappingURL=convert.js.map

/***/ }),

/***/ "./node_modules/js-utl/dist/es6/modules/core/compare.js":
/*!**************************************************************!*\
  !*** ./node_modules/js-utl/dist/es6/modules/core/compare.js ***!
  \**************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "is": () => (/* binding */ is),
/* harmony export */   "objectPropEqual": () => (/* binding */ objectPropEqual),
/* harmony export */   "shallowEqual": () => (/* binding */ shallowEqual)
/* harmony export */ });
/**
 * Copyright (c) 2013-present, Facebook, Inc.
 * All rights reserved.
 *
 * This source code is licensed under the BSD-style license found in the
 * LICENSE file in the root directory of this source tree. An additional grant
 * of patent rights can be found in the PATENTS file in the same directory.
 *
 * @typechecks
 *
 */

/**
 * @type {Function}
 */
const hasOwnProperty = Object.prototype.hasOwnProperty;
/**
 * Function implementing "Object.is" behaviour.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Object/is
 *
 * @param {*} x The first value to compare.
 * @param {*} y The second value to compare.
 * @return {boolean} A boolean indicating whether or not the two arguments are the same value.
 */

function is(x, y) {
  // SameValue algorithm
  if (x === y) {
    // Steps 1-5, 7-10
    // Steps 6.b-6.e: +0 != -0
    return x !== 0 || 1 / x === 1 / y;
  } else {
    // Step 6.a: NaN == NaN
    return x !== x && y !== y;
  }
}
/**
 * Checks whether a prop of an object equals in the other object (shallow comparison).
 *
 * @param {Object} objA The first object.
 * @param {Object} objB The second object.
 * @param {string} prop The name of the property.
 * @return {boolean} True if the value of "prop" in "objA" is shallowly equal to the value of "prop" in "objB".
 */

function objectPropEqual(objA, objB, prop) {
  return hasOwnProperty.call(objB, prop) && is(objA[prop], objB[prop]);
}
/**
 * Performs equality by iterating through keys on an object and returning "false"
 * when any key has values which are not strictly equal between the arguments.
 * Returns "true" when the values of all keys are strictly equal.
 *
 * @see https://stackoverflow.com/questions/22266826/how-can-i-do-a-shallow-comparison-of-the-properties-of-two-objects-with-javascri#answer-37636728
 *
 * @param {*} objA First object.
 * @param {*} objB Second object.
 * @return {boolean}
 */

function shallowEqual(objA, objB) {
  if (is(objA, objB)) {
    return true;
  }

  if (typeof objA !== "object" || objA === null || typeof objB !== "object" || objB === null) {
    return false;
  }

  const keysA = Object.keys(objA);
  const keysB = Object.keys(objB);

  if (keysA.length !== keysB.length) {
    return false;
  } // Test for A's keys different from B.


  for (let i = 0; i < keysA.length; i++) {
    const prop = keysA[i];

    if (!objectPropEqual(objA, objB, prop)) {
      return false;
    }
  }

  return true;
}
//# sourceMappingURL=compare.js.map

/***/ }),

/***/ "./node_modules/js-utl/dist/es6/modules/core/index.js":
/*!************************************************************!*\
  !*** ./node_modules/js-utl/dist/es6/modules/core/index.js ***!
  \************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "shallowEqual": () => (/* reexport safe */ _compare__WEBPACK_IMPORTED_MODULE_0__.shallowEqual),
/* harmony export */   "objectPropEqual": () => (/* reexport safe */ _compare__WEBPACK_IMPORTED_MODULE_0__.objectPropEqual),
/* harmony export */   "is": () => (/* reexport safe */ _compare__WEBPACK_IMPORTED_MODULE_0__.is),
/* harmony export */   "config": () => (/* binding */ config),
/* harmony export */   "isObjectEmpty": () => (/* binding */ isObjectEmpty),
/* harmony export */   "isObject": () => (/* binding */ isObject),
/* harmony export */   "isPlainObject": () => (/* binding */ isPlainObject),
/* harmony export */   "isArray": () => (/* binding */ isArray),
/* harmony export */   "isCallable": () => (/* binding */ isCallable),
/* harmony export */   "isEmpty": () => (/* binding */ isEmpty),
/* harmony export */   "isEmptyOr0": () => (/* binding */ isEmptyOr0),
/* harmony export */   "getGlobalObject": () => (/* binding */ getGlobalObject),
/* harmony export */   "uniqueId": () => (/* binding */ uniqueId),
/* harmony export */   "nestedPropertyValue": () => (/* binding */ nestedPropertyValue),
/* harmony export */   "getNestedPropertyValue": () => (/* binding */ getNestedPropertyValue),
/* harmony export */   "hasNestedPropertyValue": () => (/* binding */ hasNestedPropertyValue),
/* harmony export */   "setNestedPropertyValue": () => (/* binding */ setNestedPropertyValue),
/* harmony export */   "nestedMapSet": () => (/* binding */ nestedMapSet),
/* harmony export */   "nestedMapHas": () => (/* binding */ nestedMapHas),
/* harmony export */   "nestedMapGet": () => (/* binding */ nestedMapGet),
/* harmony export */   "nestedTreeMapSet": () => (/* binding */ nestedTreeMapSet),
/* harmony export */   "nestedTreeMapHas": () => (/* binding */ nestedTreeMapHas),
/* harmony export */   "nestedTreeMapGet": () => (/* binding */ nestedTreeMapGet),
/* harmony export */   "mapYield": () => (/* binding */ mapYield),
/* harmony export */   "deepArrayCompare": () => (/* binding */ deepArrayCompare),
/* harmony export */   "deepObjectCompare": () => (/* binding */ deepObjectCompare),
/* harmony export */   "nestedObjectConstructValue": () => (/* binding */ nestedObjectConstructValue),
/* harmony export */   "cloneDeeplyJSON": () => (/* binding */ cloneDeeplyJSON),
/* harmony export */   "isReferenceType": () => (/* binding */ isReferenceType),
/* harmony export */   "isPrimitiveType": () => (/* binding */ isPrimitiveType),
/* harmony export */   "hasCyclicReference": () => (/* binding */ hasCyclicReference),
/* harmony export */   "typeToStr": () => (/* binding */ typeToStr),
/* harmony export */   "cloneObjDeeply": () => (/* binding */ cloneObjDeeply),
/* harmony export */   "deepObjectExtend": () => (/* binding */ deepObjectExtend),
/* harmony export */   "deepObjectCloningExtend": () => (/* binding */ deepObjectCloningExtend),
/* harmony export */   "extend": () => (/* binding */ extend),
/* harmony export */   "extendDecorate": () => (/* binding */ extendDecorate),
/* harmony export */   "shallowExtend": () => (/* binding */ shallowExtend),
/* harmony export */   "includesTypeCoercion": () => (/* binding */ includesTypeCoercion),
/* harmony export */   "isUndefined": () => (/* binding */ isUndefined),
/* harmony export */   "isInt": () => (/* binding */ isInt),
/* harmony export */   "ctypeDigit": () => (/* binding */ ctypeDigit),
/* harmony export */   "isIntegerOrIntegerStr": () => (/* binding */ isIntegerOrIntegerStr),
/* harmony export */   "findIndex": () => (/* binding */ findIndex),
/* harmony export */   "firstPropValue": () => (/* binding */ firstPropValue),
/* harmony export */   "isStrictlyTrue": () => (/* binding */ isStrictlyTrue),
/* harmony export */   "isTruthy": () => (/* binding */ isTruthy),
/* harmony export */   "allTruthy": () => (/* binding */ allTruthy),
/* harmony export */   "allNotUndefined": () => (/* binding */ allNotUndefined),
/* harmony export */   "isJSONString": () => (/* binding */ isJSONString),
/* harmony export */   "noOpFn": () => (/* binding */ noOpFn),
/* harmony export */   "partialShallowEqual": () => (/* binding */ partialShallowEqual),
/* harmony export */   "shallowObjectDiff": () => (/* binding */ shallowObjectDiff),
/* harmony export */   "str": () => (/* binding */ str),
/* harmony export */   "mapObject": () => (/* binding */ mapObject),
/* harmony export */   "mapToObject": () => (/* binding */ mapToObject),
/* harmony export */   "propSelection": () => (/* binding */ propSelection),
/* harmony export */   "prototypeChainProperties": () => (/* binding */ prototypeChainProperties),
/* harmony export */   "prop": () => (/* binding */ prop),
/* harmony export */   "defineProperty": () => (/* binding */ defineProperty),
/* harmony export */   "completeObjectAssign": () => (/* binding */ completeObjectAssign)
/* harmony export */ });
/* harmony import */ var _compare__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./compare */ "./node_modules/js-utl/dist/es6/modules/core/compare.js");
function ownKeys(object, enumerableOnly) { var keys = Object.keys(object); if (Object.getOwnPropertySymbols) { var symbols = Object.getOwnPropertySymbols(object); if (enumerableOnly) { symbols = symbols.filter(function (sym) { return Object.getOwnPropertyDescriptor(object, sym).enumerable; }); } keys.push.apply(keys, symbols); } return keys; }

function _objectSpread(target) { for (var i = 1; i < arguments.length; i++) { var source = arguments[i] != null ? arguments[i] : {}; if (i % 2) { ownKeys(Object(source), true).forEach(function (key) { _defineProperty(target, key, source[key]); }); } else if (Object.getOwnPropertyDescriptors) { Object.defineProperties(target, Object.getOwnPropertyDescriptors(source)); } else { ownKeys(Object(source)).forEach(function (key) { Object.defineProperty(target, key, Object.getOwnPropertyDescriptor(source, key)); }); } } return target; }

function _defineProperty(obj, key, value) { if (key in obj) { Object.defineProperty(obj, key, { value: value, enumerable: true, configurable: true, writable: true }); } else { obj[key] = value; } return obj; }

/*
 * Copyright (c) 2021 Anton Bagdatyev (Tonix)
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */


/**
 * Core utility functions.
 */

/**
 * Optional configuration with useful properties.
 *
 * @type {Object}
 */

const config = {
  uniqueIdPrefix: "",
  elementUniqueIdPrefix: "",
  checkNetworkURI: null
};
/**
 * Tests if an object is empty.
 *
 * @param {Object} obj The object to test.
 * @return {boolean} "true" if the given object is empty (does not have own properties), "false" otherwise.
 */

function isObjectEmpty(obj) {
  for (const prop in obj) {
    if (Object.prototype.hasOwnProperty.call(obj, prop)) {
      return false;
    }
  }

  return true;
}
/**
 * @type {string}
 */

const objPrototypeToString = Object.prototype.toString.call({});
/**
 * Tests if a variable is an object.
 *
 * @param {*} obj The variable to test.
 * @return {boolean} "true" if "obj" is indeed an object, "false" otherwise.
 */

const isObject = function (obj) {
  return objPrototypeToString === Object.prototype.toString.call(obj);
};
/**
 * Tests if a variable is a plain object (i.e. "{}", an object literal).
 *
 * @param {*} obj The variable to test.
 * @return {boolean} "true" if "obj" is a plain object, "false" otherwise.
 */

const isPlainObject = obj => {
  return obj !== null && typeof obj === "object" && obj.constructor === Object && isObject(obj);
};
/**
 * Tests to see whether something is an array or not.
 *
 * @param {*} something A variable to check whether it is an array or not.
 * @return {boolean} True if the parameter passed in is an array, false otherwise.
 */

function isArray(something) {
  return Object.prototype.toString.call(something) === Object.prototype.toString.call([]);
}
/**
 * Tests if the given value is callable.
 *
 * @param {*} v The value.
 * @return {boolean} True if callable, false otherwise.
 */

function isCallable(v) {
  return typeof v === "function";
}
/**
 * Tests if a variable is empty returning true for empty strings and empty arrays.
 *
 * @param {*} data The variable to test.
 * @return {boolean} True if the variable is empty, false otherwise.
 */

function isEmpty(data) {
  return !data || data.length === 0;
}
/**
 * Tests if a variable is empty or 0 ("0" string) returning true for empty strings,
 * empty arrays, the "0" string and empty values.
 *
 * @param {*} data The variable to test.
 * @return {boolean} True if the variable is empty or "0", false otherwise.
 */

function isEmptyOr0(data) {
  return !data || data === "0" || data.length === 0;
}
/**
 * Returns a reference to the global object.
 *
 * @return {Window|global} The global object (this function is cross-platform aware).
 */

function getGlobalObject() {
  return typeof __webpack_require__.g !== "undefined" ? __webpack_require__.g : window;
}
/**
 * @type {string}
 */

const JSUtlUniqueIdCounterProp = "JSUtlUniqueIdCounterLEzKKl87QCDxwVH";
/**
 * Generates a unique ID which can be used as an "id" attribute.
 *
 * @param {string|undefined} [uniqueIdPrefix] Local unique ID prefix which overrides the prefix
 *                                            set on the "config" configuration object.
 * @return {string} The unique ID.
 */

function uniqueId(uniqueIdPrefix = void 0) {
  const globalObject = getGlobalObject();
  globalObject[JSUtlUniqueIdCounterProp] = globalObject[JSUtlUniqueIdCounterProp] || 0;
  globalObject[JSUtlUniqueIdCounterProp]++;
  const uniqueIdCounter = globalObject[JSUtlUniqueIdCounterProp];
  const uniqueId = (uniqueIdPrefix || config.uniqueIdPrefix) + uniqueIdCounter;
  return uniqueId;
}
/**
 * Gets a nested value of an object given an array of nested property names (keys).
 *
 * @param {Object} data JS POJO object.
 * @param {Array} props Array of object nested keys.
 * @return {*} The leaf value.
 */

function nestedPropertyValue(data, props) {
  let root = data;

  for (let i = 0; i < props.length; i++) {
    const prop = props[i];
    root = root[prop];
  }

  return root;
}
/**
 * Alias for "nestedPropertyValue".
 *
 * @alias
 */

const getNestedPropertyValue = nestedPropertyValue;
/**
 * Checks if a nested value of an object given an array of nested property names (keys) exists.
 *
 * @param {Object} data JS POJO object.
 * @param {Array} props Array of object nested keys.
 * @return {boolean} True if the nested key exists, false otherwise.
 */

function hasNestedPropertyValue(data, props) {
  if (!props.length) {
    return false;
  }

  let root = data;

  for (let i = 0; i < props.length; i++) {
    const prop = props[i];

    if (!root[prop]) {
      return false;
    }

    root = root[prop];
  }

  return true;
}
/**
 * Sets a nested value of an object given an array of nested property names (keys).
 *
 * @param {Object} data JS POJO object.
 * @param {Array} props Array of object nested keys.
 * @param {*} value Leaf value.
 * @return {undefined}
 */

function setNestedPropertyValue(data, props, value) {
  if (!props.length) {
    return;
  }

  let root = data;
  let prev = null;

  for (let i = 0; i < props.length; i++) {
    const prop = props[i];

    if (typeof root[prop] !== "object") {
      root[prop] = {};
    }

    prev = root;
    root = root[prop];
  }

  if (prev) {
    prev[props[props.length - 1]] = value;
  }
}
/**
 * Sets a nested value on a nested map.
 *
 * @param {Map|WeakMap} map A map or weak map.
 * @param {Array} keys Array of keys to traverse. Each key will lead to a nested map.
 * @param {*} value The value to set at the inner key.
 * @return {undefined}
 */

const nestedMapSet = (map, keys, value) => {
  let i = 0;
  let current = map;

  while (i < keys.length - 1) {
    const key = keys[i];
    const nested = current.get(key);

    if (nested instanceof Map || nested instanceof WeakMap) {
      current = nested;
    } else {
      const newMap = new Map();
      current.set(key, newMap);
      current = newMap;
    }

    i++;
  }

  current.set(keys[i], value);
};
/**
 * Tests if a map has the given nested keys.
 *
 * @param {Map|WeakMap} map A map or weak map.
 * @param {Array} keys Array of keys to check. Each key represents a nested map.
 * @return {boolean} "true" if all the nested keys exist, false otherwise.
 */

const nestedMapHas = (map, keys) => {
  let current = map;
  let i = 0;
  const l = keys.length;

  while ((current instanceof Map || current instanceof WeakMap) && current.has(keys[i]) && i < l) {
    current = current.get(keys[i]);
    i++;
  }

  return i == l;
};
/**
 * Gets a value from a nested map.
 *
 * @param {Map|WeakMap} map A map or weak map.
 * @param {Array} keys Array of keys. Each key represents a nested map.
 * @return {*} The value of the map or "undefined" if there is no value for the given nested keys.
 */

const nestedMapGet = (map, keys) => {
  let current = map;
  let i = 0;
  const l = keys.length;

  while ((current instanceof Map || current instanceof WeakMap) && current.has(keys[i]) && i < l) {
    current = current.get(keys[i]);
    i++;
  }

  return i == l ? current : void 0;
};
/**
 * @type {Symbol}
 */

const treeMapSubtree = Symbol("treeMapSubtree");
/**
 * Sets a nested value on a nested tree map.
 *
 * @param {Map|WeakMap} rootMap A map or weak map to use as the root.
 * @param {Array} keys Array of keys to traverse. Each key will lead to a nested node of the tree map.
 * @param {*} value The value to set at the inner nested key.
 * @return {undefined}
 */

const nestedTreeMapSet = (rootMap, keys, value) => {
  let i = 0;
  let current = rootMap;
  const MapConstructor = rootMap instanceof WeakMap ? WeakMap : Map;

  while (i < keys.length - 1) {
    const key = keys[i];
    const nested = current.get(key);

    if (nested) {
      current = nested[treeMapSubtree] || (nested[treeMapSubtree] = new MapConstructor());
    } else {
      const newMap = new MapConstructor();
      const node = {
        [treeMapSubtree]: newMap,
        value: void 0
      };
      current.set(key, node);
      current = newMap;
    }

    i++;
  }

  const key = keys[i];
  !current.has(key) ? current.set(key, {
    value
  }) : current.get(key).value = value;
};
/**
 * Tests if a tree map has the given nested keys.
 *
 * @param {Map|WeakMap} rootMap The root of the map or weak map.
 * @param {Array} keys Array of keys to check. Each key represents a nested node of the tree map.
 * @return {boolean} "true" if all the nested keys exist, false otherwise.
 */

const nestedTreeMapHas = (rootMap, keys) => {
  let current = rootMap;
  let i = 0;
  const l = keys.length;

  while ((current instanceof Map || current instanceof WeakMap) && current.has(keys[i]) && i < l) {
    current = current.get(keys[i])[treeMapSubtree];
    i++;
  }

  return i == l;
};
/**
 * Gets a value from a nested tree map.
 *
 * @param {Map|WeakMap} rootMap The root of the map or weak map.
 * @param {Array} keys Array of keys. Each key represents a nested node of the tree map.
 * @return {*} The value of the tree map or "undefined" if there is no value for the given nested keys.
 */

const nestedTreeMapGet = (rootMap, keys) => {
  let current = rootMap;
  let i = 0;
  const lastIndex = keys.length - 1;

  while ((current instanceof Map || current instanceof WeakMap) && current.has(keys[i]) && i < lastIndex) {
    current = current.get(keys[i])[treeMapSubtree];
    i++;
  }

  if (i === lastIndex && current) {
    const lastKey = keys[i];

    if (current.has(lastKey)) {
      const nested = current.get(lastKey);
      return nested.value;
    }
  }

  return void 0;
};
/**
 * Yields values of an array mapping the yielded value.
 *
 * @generator
 * @param {Array} items An array of items.
 * @param {*} fn The function to call.
 *               The function will receive, in order the nth item,
 *               the index of the item in the array of items and the whole items array
 *               as parameters.
 * @param {*} thisArg Optional this arg of the called function (defaults to undefined).
 * @yields {*} The next yielded mapped item.
 */

function* mapYield(items, fn, thisArg = void 0) {
  items.map();
  const boundFn = fn.bind(thisArg);

  for (let i = 0; i < items.length; i++) {
    yield boundFn(items[i], i, items);
  }
}
/**
 * Compares two arrays deeply.
 *
 * @param {Array} arr1 First array.
 * @param {Array} arr2 Second array.
 * @return {boolean} True if they are equal (same indexes and same values), false otherwise.
 */

function deepArrayCompare(arr1, arr2) {
  if (arr1.length != arr2.length) {
    return false;
  }

  const toString = Object.prototype.toString;
  const arrayToStringStr = toString.call([]);

  for (let i = 0; i < arr1.length; i++) {
    if (!(i in arr2)) {
      return false;
    } else if (isPlainObject(arr1[i])) {
      if (!isPlainObject(arr2[i]) || // eslint-disable-next-line @typescript-eslint/no-use-before-define
      !deepObjectCompare(arr1[i], arr2[i])) {
        return false;
      }
    } else if (arrayToStringStr === toString.call(arr1[i])) {
      if (arrayToStringStr !== toString.call(arr2[i]) || !deepArrayCompare(arr1[i], arr2[i])) {
        return false;
      }
    } else {
      if (arr1[i] !== arr2[i]) {
        return false;
      }
    }
  }

  return true;
}
/**
 * Compare two objects deeply.
 *
 * @param {Object} obj1 First object.
 * @param {Object} obj2 Second object.
 * @return {boolean} True if they are equal (same properties and same values), false otherwise.
 */

function deepObjectCompare(obj1, obj2) {
  const toString = Object.prototype.toString;
  const arrayToStringStr = toString.call([]);

  for (const property in obj1) {
    if (!(property in obj2)) {
      // `obj2[property]` does not contain the property of `obj1`.
      return false;
    } else if (isPlainObject(obj1[property])) {
      // `obj1[property]` is an object.
      if (!isPlainObject(obj2[property]) || !deepObjectCompare(obj1[property], obj2[property])) {
        // `obj2[property]` is not an object or the branches are different.
        return false;
      }
    } else if (arrayToStringStr === toString.call(obj1[property])) {
      // `obj1[property]` is an array.
      if (arrayToStringStr !== toString.call(obj2[property]) || !deepArrayCompare(obj1[property], obj2[property])) {
        // `obj2[property]` is not an array or the two objects are different.
        return false;
      }
    } else {
      // `obj1[property]` is not an object and is not an array.
      if (obj1[property] !== obj2[property]) {
        return false;
      }
    }
  } // `obj1` equals `obj2` (has the same properties and the same values).


  return true;
}
/**
 * Nests the properties of an object using an array of props definitions and defaults.
 * Returns the leaf.
 * Example:
 *
 *      var obj = {};
 *      var nest = [{a: {}}, {b: {}}, {c: {}}, {d: {}}, {e: {}}, {f: {}}, {g: {}}, {h: {}} ];
 *      var leaf = nestedObjectConstructValue(obj, nest);
 *      leaf.i = 'i';
 *      leaf.l = { m: "m" };
 *      JSON.stringify(obj); // {"a":{"b":{"c":{"d":{"e":{"f":{"g":{"h":{"i":"i","l":{"m":"m"}}}}}}}}}}
 *      nest = nest.concat([ {n: {}}, {o: {}}, {p: {}} ]);
 *      var pLeaf = nestedObjectConstructValue(obj, nest);
 *      pLeaf.q = 'q';
 *      nest = nest.concat([ {q: {}} ]);
 *      nestedObjectConstructValue(obj, nest); // 'q'
 *
 * @param {Object} root The root object.
 * @param {Array} nestedPropsDef The nexted props definitions.
 * @param {boolean} [isRootArrayIfRootFalsy] True if the root should be an array if the first argument "root" is falsy.
 * @return {*} The leaf.
 */

function nestedObjectConstructValue(root, nestedPropsDef, isRootArrayIfRootFalsy) {
  root = root || (isRootArrayIfRootFalsy ? [] : {});
  let leaf = root;

  for (let i = 0; i < nestedPropsDef.length; i++) {
    const propDef = nestedPropsDef[i];
    const propKey = Object.keys(propDef)[0];
    const propDefault = propDef[propKey];
    leaf[propKey] = leaf[propKey] || propDefault;
    leaf = leaf[propKey];
  }

  return leaf;
}
/**
 * Clones an object deeply using the JSON API.
 *
 * @param {Object} obj The object to clone.
 * @return {Object} The cloned object.
 */

function cloneDeeplyJSON(obj) {
  return JSON.parse(JSON.stringify(obj));
}
/**
 * Tests whether the given value is a reference type or not.
 *
 * @param {*} value Any value which can be an object or a primitive type.
 * @return {boolean} True if the given value is a reference type, false otherwise.
 */

function isReferenceType(value) {
  return new Object(value) === value;
}
/**
 * Tests whether the given value is a primitive type or not.
 *
 * @param {*} value Any value which can be an object or a primitive type.
 * @return {boolean} True if the given value is a primitive type, false otherwise.
 */

function isPrimitiveType(value) {
  return new Object(value) !== value;
}
/**
 * Checks whether an object has a cyclic reference or not.
 *
 * @param {Object} obj The object to check for a cyclic reference.
 * @return {boolean} True if the object has a cyclic reference, false otherwise.
 */

function hasCyclicReference(obj) {
  const stackSet = [];
  let detected = false;

  function detect(obj) {
    if (detected) {
      return;
    }

    if (typeof obj !== "object") {
      return;
    }

    const indexOfObj = stackSet.indexOf(obj);

    if (indexOfObj !== -1) {
      detected = true;
      return;
    }

    stackSet.push(obj);

    for (const k in obj) {
      if (Object.prototype.hasOwnProperty.call(obj, k)) {
        detect(obj[k]);
      }
    }

    stackSet.splice(indexOfObj, 1);
    return;
  }

  detect(obj);
  return detected;
}
/**
 * Converts a type to its string representation.
 *
 * @param {*} type A variable.
 * @return {string} The string representation of "type".
 */

function typeToStr(type) {
  return Object.prototype.toString.call(type);
}
/**
 * Clones an object deeply and returns the clone.
 *
 * @param {Object} object The object to clone.
 * @return {Object} The clone.
 * @throws {Error} If a circular reference if even only one property is of an unkown type
 *                 (this should never happen) or a circular reference is detected.
 */

function cloneObjDeeply(object) {
  const newObject = new object.constructor();

  for (const prop in object) {
    // If the property is defined on the prototype, ignore it. We don't want to assign it for each clone instance.
    if (!Object.prototype.hasOwnProperty.call(object, prop)) {
      continue;
    }

    const property = object[prop];

    if (isPrimitiveType(property)) {
      newObject[prop] = property;
    } else if (isReferenceType(property)) {
      if (!hasCyclicReference(property)) {
        const clone = cloneObjDeeply(property);
        newObject[prop] = clone;
      } else {
        throw new Error("Circular reference detected inside of property '" + prop + "' (" + typeToStr(property) + ") in object (" + typeToStr(object) + ")");
      }
    } else {
      throw new Error("Oops! Unknown type for property '" + prop + "' (" + typeToStr(property) + ") in object (" + typeToStr(object) + ")");
    }
  }

  return newObject;
}
/**
 * Deep object extension implementation.
 * Nothing is returned, but the destination object will be modified and merged with the source object
 * so that properties of the source object which are objects will recursively merge with the corresponding
 * destination property while the other properties with all the other types will replace the properties of the
 * destination object.
 * Note that this method should not be used for inheritance via the Prototypal Combination Inheritance pattern.
 * Also, this method doesn't perform a deep object cloning, it just extends the destinationObject by adding properties
 * it doesn't have in a deep way.
 *
 * @param {Object} destinationObject The destination object which will be modified and merged with the source object.
 * @param {Object} sourceObject The source object which will be used to extend the destination object.
 * @param {Object} [options] An object containing the options for the extension.
 *                           The currently available options are:
 *
 *                               - extendsArrays (boolean: false): Whether or not to extend nested arrays too (defaults to false);
 *
 * @return {undefined}
 */

function deepObjectExtend(destinationObject, sourceObject, options = {}) {
  for (const property in sourceObject) {
    if (sourceObject[property] && isPlainObject(sourceObject[property])) {
      destinationObject[property] = destinationObject[property] || {};
      deepObjectExtend(destinationObject[property], sourceObject[property], options);
    } else if (options.extendArrays && sourceObject[property] && isArray(sourceObject[property])) {
      destinationObject[property] = destinationObject[property] || [];
      deepObjectExtend(destinationObject[property], sourceObject[property], options);
    } else {
      destinationObject[property] = sourceObject[property];
    }
  }
}
/**
 * Deep object cloning extension implementation. If the source objects contain a property with a reference type, a clone object
 * of the same type of that property will be created and then merged with the property object of the destination object.
 *
 * @param {Object} destinationObject The destination object which will be modified and merged with the source object.
 * @param {...Object} sourceObject One or more objects which will be used to extend the destination object.
 * @return {undefined}
 */

function deepObjectCloningExtend(...args) {
  const destinationObject = args[0];
  let sourceObject;

  for (let i = 1; args[i]; i++) {
    sourceObject = args[i];

    for (const property in sourceObject) {
      if (sourceObject[property] && isPlainObject(sourceObject[property])) {
        destinationObject[property] = destinationObject[property] || {};
        deepObjectExtend(destinationObject[property], cloneObjDeeply(sourceObject[property]));
      } else {
        destinationObject[property] = sourceObject[property];
      }
    }
  }
}
/**
 * Extends a destination object with the provided source objects.
 *
 * @param {Object} destinationObj The destination object.
 * @param {...Object|Array} sourceObjects The source objects. If the last argument is an array containing one single truthy element,
 *                                        it will be treated as an options parameter and its single first truthy element will be treated as object
 *                                        containing the options for the extension.
 *                                        The currently available options are:
 *
 *                                            - extendsArrays (boolean: false): Whether or not to extend nested arrays too (defaults to false);
 *
 * @return {Object} The destination object "destinationObj" given as parameter after extension.
 */

function extend(destinationObj, ...sourceObjects) {
  let options = {};

  if (sourceObjects.length) {
    const last = sourceObjects.pop();

    if (isArray(last) && last.length === 1 && isPlainObject(last[0])) {
      options = last[0];
    } else {
      sourceObjects.push(last);
    }
  }

  for (const sourceObject of sourceObjects) {
    deepObjectExtend(destinationObj, sourceObject, options);
  }

  return destinationObj;
}
/**
 * Extends a destination object with the provided source objects.
 *
 * @param {Object} destinationObject The destination object.
 * @param {...*} rest The source objects with the last parameter being an array of rules,
 *                    each rule being a tuple array where the first element is an array of "ORed" property names (strings or numbers)
 *                    or regexes matching property names for which the corresponding values should be decorated
 *                    (or a single property name or regex matching a property name if the decoration should only happen on a single property),
 *                    and where the second element is a callback to execute for each value which is a value of a property
 *                    of a source object.
 *                    The callback has the following signature:
 *
 *                        (value: *, prop: string|number, parent: Object) => *|undefined
 *
 *                    The callback will receive the final value after extension, its associated property and the parent object
 *                    where that property is set with that value.
 *                    Its returned value will be used as the final value of the property for that object in "destinationObject".
 *
 *                    If the last parameter is not an array of rules, it will be treated as the last source object to use for the extension
 *                    (the "extend" function will be simply called under the hood without any decoration logic).
 * @return {Object} The destination object "destinationObject" given as parameter after extension and, if the callback is given
 *                  as the last parameter, after applying the given callback.
 */

function extendDecorate(destinationObject, ...rest) {
  const rules = rest[rest.length - 1];

  if (isArray(rules)) {
    rest.pop();
    const sourceObjects = rest;
    const initialRetValue = {};
    const matchedRulesMap = new Map();
    const callbacksMap = new Map();
    const paths = [];

    const mapKeys = (destinationObject, sourceObject, currentStack, currentPath) => {
      for (const key in sourceObject) {
        currentStack.push({
          destinationObject,
          sourceObject,
          property: key,
          path: [...currentPath, key]
        });
      }
    };

    const matchRule = (rule, property) => {
      if (rule instanceof RegExp && typeof property === "string") {
        return property.match(rule);
      } else {
        return rule === property;
      }
    };

    const matchArrayRule = (arrayRule, property) => {
      for (const rule of arrayRule) {
        if (matchRule(rule, property)) {
          return true;
        }
      }

      return false;
    };

    const ruleMatches = (rule, property) => {
      if (isArray(rule)) {
        return matchArrayRule(rule, property);
      } else {
        return matchRule(rule, property);
      }
    };

    const matchRules = property => {
      if (!matchedRulesMap.has(property)) {
        const callbacks = [];

        for (const [rule, callback] of rules) {
          if (ruleMatches(rule, property)) {
            callbacks.push(callback);
          }
        }

        matchedRulesMap.set(property, callbacks);
      }

      return matchedRulesMap.get(property);
    };

    for (const sourceObject of sourceObjects) {
      const currentStack = [];
      const currentPath = [];
      mapKeys(destinationObject, sourceObject, currentStack, currentPath);

      while (currentStack.length) {
        const {
          destinationObject,
          sourceObject,
          property,
          path: currentPath
        } = currentStack.pop();

        if (sourceObject[property] && isPlainObject(sourceObject[property])) {
          // "sourceObject[property]" is an object of class "Object".
          destinationObject[property] = isPlainObject(destinationObject[property]) ? destinationObject[property] : {};
          mapKeys(destinationObject[property], sourceObject[property], currentStack, currentPath);
        } else {
          // "sourceObject[property]" is not an object.
          destinationObject[property] = sourceObject[property];
        }

        const callbacks = matchRules(property);

        if (callbacks && callbacks.length) {
          if (!nestedTreeMapHas(callbacksMap, currentPath)) {
            paths.push([...currentPath]);
          }

          nestedTreeMapSet(callbacksMap, currentPath, callbacks.map(callback => retValue => path => {
            const value = retValue === initialRetValue ? destinationObject[property] : retValue;
            return callback(value, property, destinationObject, path);
          }));
        }
      }
    } // Decorating the final nested values.


    for (let i = paths.length - 1; i >= 0; i--) {
      const path = paths[i];
      const callbacks = nestedTreeMapGet(callbacksMap, path) || [];
      let retValue = initialRetValue;

      for (const callback of callbacks) {
        retValue = callback(retValue)(path);
      }

      if (retValue !== initialRetValue) {
        setNestedPropertyValue(destinationObject, path, retValue);
      }
    }

    return destinationObject;
  } else {
    return extend(destinationObject, ...rest);
  }
}
/**
 * Shallowly extends a destination object with the provided source objects (first dimension).
 *
 * @param {Object} destinationObject The destination object.
 * @param {...Object} sourceObjects The source objects.
 * @return {Object} The destination object "destinationObject" given as parameter after shallow extension.
 */

function shallowExtend(destinationObject, ...sourceObjects) {
  sourceObjects.map(obj => Object.keys(obj).map(key => destinationObject[key] = obj[key]));
  return destinationObject;
}
/**
 * Like "Array.prototype.includes", but with type coercion.
 *
 * @param {Array} array The array.
 * @param {Anything} value The value.
 * @return {boolean} True if the value is included within the array (checking with type coercion`==`).
 */

function includesTypeCoercion(array, value) {
  for (const valueOfArray of array) {
    if (valueOfArray == value) {
      return true;
    }
  }

  return false;
}
/**
 * Tests whether a value is undefined or not.
 *
 * @param {*} value A value
 * @return {boolean} True value is undefined, false otherwise.
 */

function isUndefined(value) {
  return typeof value === "undefined";
}
/**
 * Tests if the given value is an int.
 *
 * @param {*} value The value.
 * @return {boolean} True if value is an int, false otherwise.
 */

function isInt(value) {
  return Number.isInteger(value);
}
/**
 * Tests if the given string is an integer string.
 *
 * @param {*} a The string.
 * @return {boolean} True if the given string is an integer string, false otherwise.
 */

function ctypeDigit(a) {
  return Boolean(a.match(/^[0-9]+$/));
}
/**
 * Tests if the given value is an int or an integer string.
 *
 * @param {*} a The value.
 * @return {boolean} True if the value is an int or an integer string, false otherwise.
 */

function isIntegerOrIntegerStr(a) {
  return Number.isInteger(a) || ctypeDigit(a);
}
/**
 * Finds the index of a value in an array without type juggling
 * (i.e. like "Array.prototype.indexOf", but using "==" for equality comparison).
 *
 * @param {Array} array An array.
 * @param {*} value A value.
 * @return {number} -1 if the value is not in array, otherwise the index of value in array.
 */

function findIndex(array, value) {
  return array.findIndex(el => el == value);
}
/**
 * Returns the first value of the first property of an object.
 *
 * @param {Object} obj The object.
 * @return {*} The value of the first property of the given object.
 */

function firstPropValue(obj) {
  let prop;

  for (prop in obj) {
    break;
  }

  return obj[prop];
}
/**
 * Tests if a value is strictly a boolean "true".
 *
 * @param {*} value A value.
 * @return {boolean} "true" if the value is a boolean "true", "false" otherwise.
 */

function isStrictlyTrue(value) {
  return value === true;
}
/**
 * Tests if a value is truthy or not.
 *
 * @param {*} value The value.
 * @return {boolean} "true" if the value is truthy (evaluates to boolean "true"), "false" otherwise.
 */

function isTruthy(value) {
  return Boolean(value);
}
/**
 * Tests if all the given values are truthy.
 *
 * @param {...*} values The values.
 * @return {boolean} "true" if and only if all the values are truthy.
 */

function allTruthy(...values) {
  return values.every(isTruthy);
}
/**
 * Tests if all the given values are not undefined.
 *
 * @param {...*} values The values.
 * @return {boolean} "true" if and only if all the values are not undefined.
 */

function allNotUndefined(...values) {
  return values.every(value => typeof value !== "undefined");
}
/**
 * Tests if a string is a valid JSON string.
 *
 * @param {string} str A string.
 * @return {boolean} "true" if the string represents a valid JSON string, "false" otherwise.
 */

function isJSONString(str) {
  try {
    JSON.parse(str);
  } catch (e) {
    return false;
  }

  return true;
}
/**
 * No-op function.
 *
 * @return {undefined}
 */
// eslint-disable-next-line @typescript-eslint/no-empty-function

function noOpFn() {}
/**
 * Tests if a partial object is a subset of another object.
 *
 * @param {Object} obj An object.
 * @param {Object} partialObj A partial object which may not have all the keys of "obj"
 *                            or may even have different keys, or keys with different values.
 * @return {boolean} False if "partialObj" has a key which is not in "obj",
 *                   or has at least one key which is also in "obj" but with a different value
 *                   (shallow comparison), true otherwise.
 */

function partialShallowEqual(obj, partialObj) {
  return (0,_compare__WEBPACK_IMPORTED_MODULE_0__.shallowEqual)(Object.keys(partialObj).reduce((carry, key) => {
    carry[key] = obj[key];
    return carry;
  }, {}), partialObj);
}
/**
 * Returns a shallow object diff, returning an object with two keys "objA" and "objB",
 * each containing an object with all the properties of one of the two objects which are not within
 * the other object, respectively.
 * If a property is on both objects but each object has a different value for that same property
 * (using shallow equality comparison), the returned property will be set on both objects with their
 * respective values.
 *
 * @param {Object} objA First object.
 * @param {Object} objB Second object.
 * @return {Object} An object containing the shallow diff, with two keys "objA" and "objB".
 */

function shallowObjectDiff(objA, objB) {
  const diff = {
    objA: {},
    objB: {}
  };

  if ((0,_compare__WEBPACK_IMPORTED_MODULE_0__.shallowEqual)(objA, objB)) {
    return diff;
  }

  const keysA = Object.keys(objA);
  const keysB = Object.keys(objB);

  for (let i = 0; i < keysA.length; i++) {
    const prop = keysA[i];

    if (!(0,_compare__WEBPACK_IMPORTED_MODULE_0__.objectPropEqual)(objA, objB, prop)) {
      diff.objA[prop] = objA[prop];

      if (hasOwnProperty.call(objB, prop)) {
        diff.objB[prop] = objB[prop];
      }
    }
  }

  for (let i = 0; i < keysB.length; i++) {
    const prop = keysB[i];

    if (!(0,_compare__WEBPACK_IMPORTED_MODULE_0__.objectPropEqual)(objB, objA, prop)) {
      diff.objB[prop] = objB[prop];

      if (hasOwnProperty.call(objA, prop)) {
        diff.objA[prop] = objA[prop];
      }
    }
  }

  return diff;
}
/**
 * Casts a value to a string.
 *
 * @param {*} v A value.
 * @return {string} The string representation of the value.
 */

function str(v) {
  return "" + v;
}
/**
 * Maps an object, executing a function on each of its properties
 * returning a new mapped object.
 *
 * @param {Object} obj The object to map.
 * @param {Function} fn The function to use for the mapping.
 * @return {Object} The new mapped object.
 */

const mapObject = (obj, fn) => Object.fromEntries(Object.entries(obj).map(([key, value], index) => [key, fn(value, key, index)]));
/**
 * Maps an array to an object.
 *
 * @param {Array} arr An array.
 * @param {Function} fn A function receiving the current value, its index as well as the whole array "arr"
 *                      as parameters returning a tuple with the key at index 0 and the value at index 1 to set on the object.
 * @returns {Object} The array mapped to an object.
 */

const mapToObject = (arr, fn) => {
  let res;
  return arr.reduce((carry, ...rest) => (res = fn(...rest)) && false || (carry[res[0]] = res[1]) && false || carry, {});
};
/**
 * Selects the truthy properties of an object or the properties of an object
 * passing a test specified with a callback function.
 *
 * @param {Object} obj The input object.
 * @param {Function} fn A function which will receive two parameters:
 *
 *                          - value: The current value of the input object for a given property;
 *                          - prop: The name of the current property of the input object.
 *
 *                      The function will have to return a boolean "true" indicating that this value
 *                      and property has to be included in the returned object, or "false" otherwise.
 *
 * @return {Object} An object having only the properties which passed the test
 *                  implemented by the "fn" callback function.
 */

const propSelection = (obj, fn = isTruthy) => Object.keys(obj).reduce((carry, key) => {
  if (fn(obj[key], key)) {
    carry[key] = obj[key];
  }

  return carry;
}, {});
/**
 * Returns all the properties of the given object traversing its prototype chain.
 *
 * @param {Object} obj The object.
 * @param {Object} [options] An object with options.
 * @param {?Object} [options.stopAtPrototype] The prototype in the chain at which to stop the traversing.
 *                                            Defaults to null, in which case the whole prototype chain will be traversed.
 * @param {boolean} [options.stopAtPrototypeInclude] Whether or not to include the properties of the given prototype at which to stop.
 *                                                   Works only if "stopAtPrototype" is set to a valid prototype object in the prototype chain
 *                                                   of "obj".
 *                                                   Defaults to true, in which case the properties of the prototype at which to stop will be included
 *                                                   in the returned array.
 * @return {string[]} An array of containing the names of the properties.
 */

function prototypeChainProperties(obj, {
  stopAtPrototype = null,
  stopAtPrototypeInclude = true
} = {}) {
  let current = obj;
  const map = {};
  let isStopPrototype = false;
  let stop = false;

  while (!stop && current && (!isStopPrototype || stopAtPrototypeInclude)) {
    if (isStopPrototype) {
      stop = true;
    }

    const properties = Object.getOwnPropertyNames(current);
    properties.map(property => map[property] = true);
    current = Object.getPrototypeOf(current);

    if (stopAtPrototype) {
      isStopPrototype = current === stopAtPrototype;
    }
  }

  const properties = Object.keys(map);
  return properties;
}
/**
 * Defines an object's property with a getter and an optional setter.
 *
 * @param {Object} obj An object (may be a prototype).
 * @param {string} propname The property name.
 * @param {Function} getfn Getter function.
 * @param {Function|undefined} [setfn] Setter function.
 * @return {undefined}
 */

function prop(obj, propname, getfn, setfn = void 0) {
  const propObj = {};
  propObj[propname] = {
    get: getfn,
    set: setfn
  };
  Object.defineProperties(obj, propObj);
}
/**
 * Defines a property on an object.
 *
 * @param {Object} o An object.
 * @param {string} p The property to define.
 * @param {Object} descriptor Optional object containing the descriptor's properties to use to override the default properties.
 * @return {Object} The object that was passed to the function.
 */

function defineProperty(o, p, descriptor = {}) {
  return Object.defineProperty(o, p, _objectSpread({
    configurable: false,
    enumerable: false,
    writable: true
  }, descriptor));
}
/**
 * Assigns the properties of the given source objects to the target object.
 *
 * @source https://stackoverflow.com/questions/39515321/spread-operator-issues-with-property-accessors-getters#answer-39521418
 *
 * @param {Object} target The target object.
 * @param {...Object} sources The source objects.
 * @return {Object} The target object.
 */

function completeObjectAssign(target, ...sources) {
  sources.forEach(source => {
    const descriptors = Object.keys(source).reduce((descriptors, key) => {
      descriptors[key] = Object.getOwnPropertyDescriptor(source, key);
      return descriptors;
    }, {}); // By default, Object.assign copies enumerable Symbols too.

    Object.getOwnPropertySymbols(source).forEach(sym => {
      const descriptor = Object.getOwnPropertyDescriptor(source, sym);

      if (descriptor.enumerable) {
        descriptors[sym] = descriptor;
      }
    });
    Object.defineProperties(target, descriptors);
  });
  return target;
}
//# sourceMappingURL=index.js.map

/***/ }),

/***/ "./node_modules/js-utl/dist/es6/modules/date.js":
/*!******************************************************!*\
  !*** ./node_modules/js-utl/dist/es6/modules/date.js ***!
  \******************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "isValidDate": () => (/* binding */ isValidDate),
/* harmony export */   "isValidDateTimeStr": () => (/* binding */ isValidDateTimeStr)
/* harmony export */ });
/*
 * Copyright (c) 2021 Anton Bagdatyev (Tonix)
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */

/**
 * Tests if the given value is a valid date.
 *
 * @param {*} value The value to test.
 * @return {boolean} True if it is a valid date, false otherwise.
 */
function isValidDate(value) {
  return Object.prototype.toString.call(value) === "[object Date]" && !isNaN(value.getTime());
}
/**
 * Tests if the given string is a valid datetime string (e.g. in ISO "YYYY-MM-DD HH:ii:ss" format).
 *
 * @param {string} str The given string.
 * @return {boolean} True if it is a valid datetime string, false otherwise.
 */

function isValidDateTimeStr(str) {
  return isValidDate(new Date(str));
}
//# sourceMappingURL=date.js.map

/***/ }),

/***/ "./node_modules/js-utl/dist/es6/modules/fn.js":
/*!****************************************************!*\
  !*** ./node_modules/js-utl/dist/es6/modules/fn.js ***!
  \****************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "_": () => (/* binding */ _),
/* harmony export */   "proceedCallingFn": () => (/* binding */ proceedCallingFn),
/* harmony export */   "curry": () => (/* binding */ curry),
/* harmony export */   "POJOCurry": () => (/* binding */ POJOCurry),
/* harmony export */   "compose": () => (/* binding */ compose),
/* harmony export */   "pipe": () => (/* binding */ pipe),
/* harmony export */   "pick": () => (/* binding */ pick),
/* harmony export */   "liftBinaryFn": () => (/* binding */ liftBinaryFn),
/* harmony export */   "juxt": () => (/* binding */ juxt),
/* harmony export */   "converge": () => (/* binding */ converge),
/* harmony export */   "execIfPOJOHas": () => (/* binding */ execIfPOJOHas),
/* harmony export */   "execWithExistentProps": () => (/* binding */ execWithExistentProps),
/* harmony export */   "forGen": () => (/* binding */ forGen),
/* harmony export */   "flatten": () => (/* binding */ flatten),
/* harmony export */   "flattenDeep": () => (/* binding */ flattenDeep),
/* harmony export */   "cartesianProduct": () => (/* binding */ cartesianProduct),
/* harmony export */   "identityFn": () => (/* binding */ identityFn),
/* harmony export */   "identityArgsFn": () => (/* binding */ identityArgsFn),
/* harmony export */   "chain": () => (/* binding */ chain)
/* harmony export */ });
/* harmony import */ var _core__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./core */ "./node_modules/js-utl/dist/es6/modules/core/index.js");
/* harmony import */ var _array__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./array */ "./node_modules/js-utl/dist/es6/modules/array.js");
function ownKeys(object, enumerableOnly) { var keys = Object.keys(object); if (Object.getOwnPropertySymbols) { var symbols = Object.getOwnPropertySymbols(object); if (enumerableOnly) { symbols = symbols.filter(function (sym) { return Object.getOwnPropertyDescriptor(object, sym).enumerable; }); } keys.push.apply(keys, symbols); } return keys; }

function _objectSpread(target) { for (var i = 1; i < arguments.length; i++) { var source = arguments[i] != null ? arguments[i] : {}; if (i % 2) { ownKeys(Object(source), true).forEach(function (key) { _defineProperty(target, key, source[key]); }); } else if (Object.getOwnPropertyDescriptors) { Object.defineProperties(target, Object.getOwnPropertyDescriptors(source)); } else { ownKeys(Object(source)).forEach(function (key) { Object.defineProperty(target, key, Object.getOwnPropertyDescriptor(source, key)); }); } } return target; }

function _defineProperty(obj, key, value) { if (key in obj) { Object.defineProperty(obj, key, { value: value, enumerable: true, configurable: true, writable: true }); } else { obj[key] = value; } return obj; }

function _objectWithoutProperties(source, excluded) { if (source == null) return {}; var target = _objectWithoutPropertiesLoose(source, excluded); var key, i; if (Object.getOwnPropertySymbols) { var sourceSymbolKeys = Object.getOwnPropertySymbols(source); for (i = 0; i < sourceSymbolKeys.length; i++) { key = sourceSymbolKeys[i]; if (excluded.indexOf(key) >= 0) continue; if (!Object.prototype.propertyIsEnumerable.call(source, key)) continue; target[key] = source[key]; } } return target; }

function _objectWithoutPropertiesLoose(source, excluded) { if (source == null) return {}; var target = {}; var sourceKeys = Object.keys(source); var key, i; for (i = 0; i < sourceKeys.length; i++) { key = sourceKeys[i]; if (excluded.indexOf(key) >= 0) continue; target[key] = source[key]; } return target; }

function _toPropertyKey(arg) { var key = _toPrimitive(arg, "string"); return typeof key === "symbol" ? key : String(key); }

function _toPrimitive(input, hint) { if (typeof input !== "object" || input === null) return input; var prim = input[Symbol.toPrimitive]; if (prim !== undefined) { var res = prim.call(input, hint || "default"); if (typeof res !== "object") return res; throw new TypeError("@@toPrimitive must return a primitive value."); } return (hint === "string" ? String : Number)(input); }

/*
 * Copyright (c) 2021 Anton Bagdatyev (Tonix)
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */

/**
 * Utility functions for functional programming.
 */


/**
 * @type {string}
 */

const JSUtlCurryPlaceholderProp = "JSUtlCurryPlaceholderPropRPecoyYmCYqZ2lE";
/**
 * Curry function placeholder.
 *
 * @type {Object}
 */

const _ = {
  [JSUtlCurryPlaceholderProp]: true
};
/**
 * @type {string}
 */

const JSUtlProceedCallingFnProp = "JSUtlProceedCallingFnPropRBmGaAgOCgftF9t";
/**
 * Return value of "onFnCall" to call the curried function and return its value.
 *
 * @type {Object}
 */

const proceedCallingFn = {
  [JSUtlProceedCallingFnProp]: true
};
/**
 * Curries a function.
 *
 * @see https://medium.com/@kj_huang/implementation-of-lodash-curry-function-8b1024d71e3b
 *
 * @param {Function} fn A function to curry in order to return the curried version of the function.
 * @param {Object} [obj] An optional object with further properties to tweak the currying behaviour
 *                       and execute code while collecting the arguments of the curried function.
 * @param {number|undefined} [obj.arity] The arity of the function, i.e. its number of arguments.
 *                                       If omitted, "fn.length" will be used.
 * @param {Function|undefined} [obj.onEffectiveArgAdded] An optional callback to execute whenever a new effective argument
 *                                                       (not a placeholder) is added to the curried function.
 *                                                       The callback will receive an object with the following properties as argument:
 *
 *                                                           - addedArg: The effective argument added;
 *                                                           - args: An array with all the previously arguments collected so far
 *                                                                   without considering "addedArg";
 *                                                           - fn: The function "fn";
 *                                                           - curriedFn: The current curried function;
 *
 *                                                       The return value of the function is ignored.
 * @param {Function|undefined} [obj.onPlaceholder] An optional callback to execute whenever a new placeholder is added to the curried function.
 *                                                 The callback will receive an object with the following properties as argument:
 *
 *                                                     - args: An array with all the previously arguments collected so far
 *                                                             without considering "addedArg";
 *                                                     - fn: The function "fn";
 *                                                     - curriedFn: The current curried function;
 *
 *                                                 The return value of the function is ignored.
 * @param {Function|undefined} [obj.onFnCall] An optional callback to execute just before calling the "fn" function
 *                                            (i.e. when the "fn" function is ready to be called and all its arguments have been collected).
 *
 *                                            The callback will receive an object with the following properties as argument:
 *
 *                                                - args: The array of the effective arguments of the "fn" function;
 *                                                - fn: The function "fn" which was initially passed to "curry";
 *                                                - curriedFn: The current curried function;
 *
 *                                            This way, the code of the callback may decide what to do and may even call the function on its own
 *                                            and prevent the call from the caller side (i.e. within the "curry" function).
 *
 *                                            The callback must explicitly tell "curry" to call the function by returing "proceedCallingFn".
 *                                            If the callback returns any other value, then "fn" will not be called in "curry" and the return value
 *                                            of this callback will be returned.
 * @param {Function|undefined} [obj.onCurriedFnFirstCall] An optional callback to execute only the first time when the first curried function returned by "curry"
 *                                                        is invoked for the very first time with the very first argument or arguments.
 *
 *                                                        The callback will receive an object with the following properties as argument:
 *
 *                                                            - addedArgs: The arguments provided by the caller;
 *                                                            - fn: The "fn" function;
 *                                                            - curriedFn: The current curried function;
 *
 * @param {Function|undefined} [obj.onNewCurriedFn] An optional callback to execute initially and each time a new curried function is going to be returned.
 *
 *                                                  The callback will an object with the following properties as argument:
 *
 *                                                      - curriedFn: The current curried function (same as "newCurriedFn" when this callback is invoked
 *                                                                   for the very first time);
 *                                                      - newCurriedFn: The new curried function (will be the same as "curriedFn" when this callback is invoked
 *                                                                      for the very first time);
 *
 *
 * @return {Function} The curried version of the function.
 */

const curry = (fn, {
  arity = void 0,
  onEffectiveArgAdded = void 0,
  onPlaceholder = void 0,
  onFnCall = void 0,
  onCurriedFnFirstCall = void 0,
  onNewCurriedFn = void 0
} = {}) => {
  let curriedFnFirstCall = true;
  const expectedNumberOfArgs = typeof arity !== "undefined" ? arity : fn.length;
  const nextParameterIndex = 0;
  const placeholdersIndices = [];

  const curriedInner = (expectedNumberOfArgs, nextParameterIndex, placeholdersIndices, ...args) => function curriedFn(...addedArgs) {
    let newExpectedNumberOfArgs = expectedNumberOfArgs;
    let newNextParameterIndex = nextParameterIndex;
    let newPlaceholdersIndices = null;
    let argsRequiredChange = false;
    curriedFnFirstCall && (onCurriedFnFirstCall && onCurriedFnFirstCall({
      addedArgs,
      fn,
      curriedFn
    }) || true) && (curriedFnFirstCall = false);
    let numberOfConsumablePlaceholders = placeholdersIndices.length;

    for (const addedArg of addedArgs) {
      !argsRequiredChange && (args = [...args]);
      argsRequiredChange = true; // "addedArg" may be either a placeholder or an effective argument.

      const isPlaceholder = addedArg ? addedArg[JSUtlCurryPlaceholderProp] === _[JSUtlCurryPlaceholderProp] : false;

      if (numberOfConsumablePlaceholders > 0 && !isPlaceholder) {
        // Argument is an effective argument consuming a previously set placeholder.
        onEffectiveArgAdded && onEffectiveArgAdded({
          addedArg,
          args,
          fn,
          curriedFn
        });
        let argIndex;

        if (newPlaceholdersIndices === null) {
          const [firstIndex, ...rest] = placeholdersIndices;
          argIndex = firstIndex;
          newPlaceholdersIndices = rest;
        } else {
          argIndex = newPlaceholdersIndices.shift();
        }

        args[argIndex] = addedArg;
        numberOfConsumablePlaceholders--;
        newExpectedNumberOfArgs--;
      } else {
        if (isPlaceholder) {
          // Argument is a new placeholder.
          if (newPlaceholdersIndices === null) {
            newPlaceholdersIndices = placeholdersIndices.concat(newNextParameterIndex);
          } else {
            newPlaceholdersIndices.push(newNextParameterIndex);
          }

          onPlaceholder && onPlaceholder({
            args,
            fn,
            curriedFn
          });
        } else {
          // Argument is an effective argument.
          newExpectedNumberOfArgs--;
          onEffectiveArgAdded && onEffectiveArgAdded({
            addedArg,
            args,
            fn,
            curriedFn
          });
        }

        args[newNextParameterIndex] = addedArg;
        newNextParameterIndex++;
      }
    } // eslint-disable-next-line @typescript-eslint/no-use-before-define


    return curried.call(null, curriedFn, newExpectedNumberOfArgs, newNextParameterIndex, newPlaceholdersIndices === null ? [...placeholdersIndices] : newPlaceholdersIndices, ...args);
  };

  const curried = (curriedFn, expectedNumberOfArgs, nextParameterIndex, placeholdersIndices, ...args) => {
    if (expectedNumberOfArgs <= 0) {
      if (onFnCall) {
        const shouldCallCurriedFn = onFnCall({
          args,
          fn,
          curriedFn
        });

        if (shouldCallCurriedFn && shouldCallCurriedFn[JSUtlProceedCallingFnProp] === proceedCallingFn[JSUtlProceedCallingFnProp]) {
          return fn(...args);
        } else {
          return shouldCallCurriedFn;
        }
      } else {
        return fn(...args);
      }
    } else {
      const newCurried = curriedInner(expectedNumberOfArgs, nextParameterIndex, placeholdersIndices, ...args);
      onNewCurriedFn && onNewCurriedFn({
        curriedFn,
        newCurriedFn: newCurried
      });
      return newCurried;
    }
  };

  const newCurried = curriedInner(expectedNumberOfArgs, nextParameterIndex, placeholdersIndices);
  onNewCurriedFn && onNewCurriedFn({
    curriedFn: newCurried,
    newCurriedFn: newCurried
  });
  return newCurried;
};
/**
 * Curries a POJO function, i.e. a function having only a single POJO (Plain Old JavaScript Object)
 * as parameter (a function with named arguments).
 *
 * Each object passed to the returned curried function will be merged with the final object
 * to pass to the function "fn" when finally calling it.
 *
 * @param {Function} fn A function to curry in order to return the curried version of the function.
 * @param {Object} [obj] An optional object with further properties to tweak the currying behaviour
 *                       and execute code while collecting the POJO objects which will construct
 *                       the final POJO object to pass to the curried function after plugging the curried POJO function.
 * @param {string} [obj.plugPropertyName] The name of the property which instructs that it's time to call the function.
 * @param {boolean} [obj.plugPropertyMustBeTruthy] By default, it is enough that the property with "obj.plugPropertyName" name
 *                                                 is set on the object passed to the curried function to instruct it to call
 *                                                 "fn".
 *                                                 If this property is set to "true", the property with "plugPropertyName" name
 *                                                 will also have to be truthy.
 * @param {Function} [obj.onPOJOArgMerged] An optional callback to execute right after merging the next POJO argument with the so far accumulated POJO object.
 *                                         The callback will an object with the following properties as argument:
 *
 *                                             - POJOArg: The given POJO argument;
 *                                             - POJO: the so far merged POJO object merged with the given "POJOArg";
 *                                             - fn: The "fn" function;
 *                                             - curriedFn: The current curried function;
 *
 * @param {Function} [obj.onFnCall] An optional callback to execute just before calling the "fn" function
 *                                  (i.e. when the "fn" function is ready to be called and has been plugged and all
 *                                  of its POJO object argument properties have been collected).
 *
 *                                  The callback will receive an object with the following properties as argument:
 *
 *                                      - POJO: The callback will receive the function "fn" POJO argument (an object) as its first argument;
 *                                      - fn: The "fn" function;
 *                                      - curriedFn: The current curried function;
 *
 *                                  This way, the code of the callback may decide what to do and may even call the function on its own
 *                                  and prevent the call from the caller side (i.e. within the "POJOCurry" function).
 *
 *                                  The callback must explicitly tell "POJOCurry" to call the function by returing "proceedCallingFn"
 *                                  If the callback returns any other value, then "fn" will not be called in "POJOCurry" and the return value
 *                                  of this callback will be returned.
 * @param {Function|undefined} [obj.onCurriedFnFirstCall] An optional callback to execute only the first time when the first curried function returned by "curry"
 *                                                        is invoked for the very first time with the very first POJO argument.
 *
 *                                                        The callback will receive an object with the following properties as argument:
 *
 *                                                            - POJOArg: The given POJO argument yet to merge with the internal final POJO;
 *                                                            - fn: The "fn" function;
 *                                                            - curriedFn: The current curried function;
 *
 * @param {Function|undefined} [obj.onNewCurriedFn] An optional callback to execute initially and each time a new curried function is going to be returned.
 *
 *                                                  The callback will receive an object with the following properties as argument:
 *
 *                                                      - curriedFn: The current curried function (same as "newCurriedFn" when this callback is invoked
 *                                                                   for the very first time);
 *                                                      - newCurriedFn: The new curried function (will be the same as "curriedFn" when this callback is invoked
 *                                                                      for the very first time);
 *
 * @return {Function} The curried version of the function.
 */

const POJOCurry = (fn, {
  plugPropertyName = "plugCurried",
  plugPropertyMustBeTruthy = false,
  onPOJOArgMerged = void 0,
  onFnCall = void 0,
  onCurriedFnFirstCall = void 0,
  onNewCurriedFn = void 0
} = {}) => {
  const POJO = {};
  let curriedFnFirstCall = true;

  const curriedInner = accPOJO => function curriedFn(POJOArg) {
    curriedFnFirstCall && (onCurriedFnFirstCall && onCurriedFnFirstCall({
      POJOArg,
      fn,
      curriedFn
    }) || true) && (curriedFnFirstCall = false);
    let POJO = Object.assign({}, accPOJO, POJOArg || {});

    if (Object.prototype.hasOwnProperty.call(POJO, plugPropertyName) && (!plugPropertyMustBeTruthy || POJO[plugPropertyName])) {
      const {
        [plugPropertyName]: POJOArgIgnoredProperty
      } = POJOArg,
            rest = _objectWithoutProperties(POJOArg, [plugPropertyName].map(_toPropertyKey));

      const {
        [plugPropertyName]: POJOIgnoredProperty
      } = POJO,
            POJORest = _objectWithoutProperties(POJO, [plugPropertyName].map(_toPropertyKey));

      POJO = POJORest;

      if (!(0,_core__WEBPACK_IMPORTED_MODULE_0__.isObjectEmpty)(rest)) {
        onPOJOArgMerged && onPOJOArgMerged({
          POJOArg: rest,
          POJO,
          fn,
          curriedFn
        });
      }

      let shouldCallCurriedFn = true;
      let ret = void 0;

      if (onFnCall) {
        ret = onFnCall({
          POJO,
          fn,
          curriedFn
        });
        shouldCallCurriedFn = ret ? ret[JSUtlProceedCallingFnProp] === proceedCallingFn[JSUtlProceedCallingFnProp] : false;
      }

      if (shouldCallCurriedFn) {
        return fn(POJO);
      } else {
        return ret;
      }
    } else {
      onPOJOArgMerged && onPOJOArgMerged({
        POJOArg,
        POJO,
        fn,
        curriedFn
      });
      const newCurried = curriedInner(POJO);
      onNewCurriedFn && onNewCurriedFn({
        curriedFn,
        newCurriedFn: newCurried
      });
      return newCurried;
    }
  };

  const newCurried = curriedInner(POJO);
  onNewCurriedFn && onNewCurriedFn({
    curriedFn: newCurried,
    newCurriedFn: newCurried
  });
  return newCurried;
};
/**
 * A utility function which composes functions or higher-order functions.
 *
 * @param {...Function|...Function[]} fns A list of functions or higher-order functions or arrays of functions
 *                                        (arrays will be flattened) to compose.
 * @return {Function} A function composed of all the functions or higher-order functions
 *                    used for composition.
 */

const compose = (...fns) => (...args) => {
  let outerArgs = args;
  let hoFn = void 0;
  fns = fns.flat(1);

  for (let i = fns.length - 1; i >= 0; i--) {
    const fn = fns[i];
    hoFn = fn(...outerArgs);
    outerArgs = [hoFn];
  }

  return hoFn;
};
/**
 * A utility function which pipes functions.
 *
 * @param {...Function|...Function[]} fns A list of functions or arrays of functions (arrays will be flattened)
 *                                        to pipe.
 * @return {Function} A function representing the pipe.
 */

const pipe = (...fns) => (...args) => {
  fns = fns.flat(1);
  return fns.length ? fns.reduce((arg, fn) => [fn(...arg)], args)[0] : void 0;
};
/**
 * Returns a function which lets picking the properties of an object.
 *
 * @param {...string|...number} props The properties to pick.
 * @return {Function} A function which if called picks the "props" properties from its argument object
 *                    and returns a new object with the picked properties.
 */

const pick = (...props) => o => props.reduce((a, e) => _objectSpread(_objectSpread({}, a), {}, {
  [e]: o[e]
}), {});
/**
 * Lifts two functions using a binary function which takes their results as arguments.
 *
 * @param {Function} binaryFn A binary function (i.e. a function which takes two arguments).
 * @return {Function} A higher-order function which has to be called with the first function as argument ("firstFn")
 *                    and returns another higher-order function which has to be called with the second function as argument ("secondFn").
 *                    Then, the returned function will take the parameters to pass to the two functions ("firstFn" and "secondFn")
 *                    and return the result of calling "binaryFn" with the result of those functions given as parameters.
 */

const liftBinaryFn = binaryFn => firstFn => secondFn => (...params) => binaryFn(firstFn(...params), secondFn(...params));
/**
 * Applies an array of functions to a list of values.
 *
 * @param {Function[]} fns An array of functions.
 * @return {Function} A function which if called with a list of values, will pass the list to each function of "fns"
 *                    and return an array with the values after applying each of the original "fns" to its parameters.
 */

const juxt = fns => (...values) => fns.map(fn => fn(...values));
/**
 * Converges a multi-arg function.
 *
 * @param {Function} multiArgFn A multi-arg function.
 * @param {Function[]} fns An array of functions to converge.
 *                         Each function will receive the parameter passed to the function returned
 *                         by this higher-order function (i.e. "params").
 * @return {Function} A function which, if called, will pass its arguments to each of the functions in "fns"
 *                    and pass each result of those functions to the multi-arg function "multiArgFn",
 *                    returning its result.
 */

const converge = (multiArgFn, fns) => (...params) => multiArgFn(...fns.map(fn => fn(...params)));
/**
 * Executes a callback if a POJO object has a property.
 *
 * @param {Object} POJO A POJO object.
 * @return {(prop: string|number) => (fn: Function) => *} A function which receives the property
 *                                                        and returns and returns a function receiving the callback
 *                                                        function ("fn") to execute.
 */

const execIfPOJOHas = POJO => prop => fn => Object.prototype.hasOwnProperty.call(POJO, prop) && fn(POJO[prop], POJO);
/**
 * Executes a callback with the existent properties of an object.
 *
 * @param {...string|...number} props The properties to check for existence.
 * @return {(o: Object) => (fn: (existentProps: string[]|number[]) => *) => *} A function which receives the POJO object for which to check for the given properties
 *                                                                             and returns a function receiving a callback which will receive the existent properties
 *                                                                             of the object as parameter.
 */

const execWithExistentProps = (...props) => o => fn => fn(props.filter(prop => Object.prototype.hasOwnProperty.call(o, prop)));
/**
 * Loops through the values of a generator and returns an array with its mapped
 * values mapped with the given callback.
 *
 * @param {GeneratorFunction} gen A generator function.
 * @return {(args: ...*) => (fn: (val: *) => *) => Array} A function which takes the arguments for the generator and returns
 *                                                        another function which takes the callback to use to map each
 *                                                        value of the generator returning an array with all the mapped values of the generator.
 */

const forGen = gen => (...args) => fn => {
  const generator = gen(...args);
  const arr = [];

  for (const value of generator) {
    const res = fn(value);
    arr.push(res);
  }

  return arr;
};
/**
 * Flattens an array (its first dimension, at most).
 *
 * @param {Array} arr An array.
 * @return {Array} A new flattened array.
 */

const flatten = arr => Array.prototype.concat.apply([], arr);
/**
 * Flattens an array with any dimension.
 *
 * @param {Array} arr An array.
 * @param {number} dimension The flattening dimension (defaults to 1, but can be greater, even "Infinity").
 * @return {Array} The flattened array.
 */

const flattenDeep = (arr, dimension = 1) => {
  return dimension > 0 ? arr.reduce((acc, val) => acc.concat((0,_core__WEBPACK_IMPORTED_MODULE_0__.isArray)(val) ? flattenDeep(val, dimension - 1) : val), []) : (0,_array__WEBPACK_IMPORTED_MODULE_1__.arrayOrArrayLike)(arr);
};
/**
 * Computes the cartesian product of the given sets.
 *
 * @param  {...Array} sets The sets to use to compute the cartesian product.
 * @return {Array} The cartesian product of the given sets.
 */

const cartesianProduct = (...sets) => sets.reduce((acc, set) => flatten(acc.map(x => set.map(y => [...x, y]))), [[]]);
/**
 * Identity function.
 *
 * @see https://en.wikipedia.org/wiki/Identity_function
 *
 * @param {*} value Any value.
 * @return {*} The same passed value.
 */

const identityFn = value => value;
/**
 * Identity function for multiple args returning an array of those args.
 *
 * @param  {...*} args The args.
 * @return {Array} The same args returned in an array.
 */

const identityArgsFn = (...args) => args;
/**
 * @type {Function}
 */

const chainLink = (fn, next) => args => fn(args, next && ((...args) => next(args)));
/**
 * A higher-order function to create a chain of functions following the Chain of Responsibility design pattern.
 *
 * @param {...Function|...Function[]} fns A list of functions or higher-order functions or arrays of functions
 *                                        (arrays will be flattened) to chain.
 * @return {Function} A function representing the chain of the given functions which, if called, will return the result of the chain.
 *                    Each function will receive the next function as its last parameter.
 */


const chain = (...fns) => (...args) => {
  fns = fns.flat(1);
  const chainFn = fns.reduceRight((nextChainLink, fn) => {
    return chainLink(fn, nextChainLink);
  }, void 0);
  return chainFn(args);
};
//# sourceMappingURL=fn.js.map

/***/ }),

/***/ "./node_modules/js-utl/dist/es6/modules/google.js":
/*!********************************************************!*\
  !*** ./node_modules/js-utl/dist/es6/modules/google.js ***!
  \********************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "googleMapBestZoomLevelFromBounds": () => (/* binding */ googleMapBestZoomLevelFromBounds)
/* harmony export */ });
/*
 * Copyright (c) 2021 Anton Bagdatyev (Tonix)
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */

/**
 * Utility functions related to Google.
 */

/**
 * Determines the best zoom level for a Google map.
 *
 * @param {google.maps.LatLngBounds} bounds The bounds.
 * @param {Object} mapDim JS POJO with the properties height and width.
 * @param {number} mapDim.height The height of the map's div.
 * @param {number} mapDim.width The width of the map's div.
 * @return {number} The best zoom level.
 */
function googleMapBestZoomLevelFromBounds(bounds, mapDim) {
  const WORLD_DIM = {
    height: 256,
    width: 256
  };
  const ZOOM_MAX = 21;

  function latRad(lat) {
    const sin = Math.sin(lat * Math.PI / 180);
    const radX2 = Math.log((1 + sin) / (1 - sin)) / 2;
    return Math.max(Math.min(radX2, Math.PI), -Math.PI) / 2;
  }

  function zoom(mapPx, worldPx, fraction) {
    return Math.floor(Math.log(mapPx / worldPx / fraction) / Math.LN2);
  }

  const ne = bounds.getNorthEast();
  const sw = bounds.getSouthWest();
  const latFraction = (latRad(ne.lat()) - latRad(sw.lat())) / Math.PI;
  const lngDiff = ne.lng() - sw.lng();
  const lngFraction = (lngDiff < 0 ? lngDiff + 360 : lngDiff) / 360;
  const latZoom = zoom(mapDim.height, WORLD_DIM.height, latFraction);
  const lngZoom = zoom(mapDim.width, WORLD_DIM.width, lngFraction);
  return Math.min(latZoom, lngZoom, ZOOM_MAX);
}
//# sourceMappingURL=google.js.map

/***/ }),

/***/ "./node_modules/js-utl/dist/es6/modules/hash.js":
/*!******************************************************!*\
  !*** ./node_modules/js-utl/dist/es6/modules/hash.js ***!
  \******************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "stringHashArray": () => (/* binding */ stringHashArray),
/* harmony export */   "hashString": () => (/* binding */ hashString),
/* harmony export */   "onePassStringHash": () => (/* binding */ onePassStringHash)
/* harmony export */ });
/*
 * Copyright (c) 2021 Anton Bagdatyev (Tonix)
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */

/**
 * Hashing-related utility functions.
 */

/**
 * Computes a hash of an array of strings (the order of strings does not matter).
 *
 * NOTE: An array with duplicate values given as parameter to this function
 *       may yield to a hash which would collide with other hashes computed
 *       on different arrays with this same function.
 *       Therefore is on behalf of the caller to be sure that "array" contains
 *       unique strings.
 *
 * @param {string[]} array An array of strings.
 * @return {number} A number representing the hash code of the array.
 */
function stringHashArray(array) {
  let code = 0;

  for (let i = 0; i < array.length; i++) {
    let n = 0;

    for (let j = 0; j < array[i].length; j++) {
      n = n * 251 ^ array[i].charCodeAt(j);
    }

    code ^= n;
  }

  return code;
}
/**
 * Returns the hash of a string.
 *
 * @see https://stackoverflow.com/questions/7616461/generate-a-hash-from-string-in-javascript#answer-7616484
 *
 * @param {string} str The string.
 * @return {number} The hash code of the string, represented as a number.
 */

const hashString = str => {
  let hash = 0,
      i,
      chr;

  if (str.length === 0) {
    return hash;
  }

  for (i = 0; i < str.length; i++) {
    chr = str.charCodeAt(i);
    hash = (hash << 5) - hash + chr;
    hash |= 0; // Convert to 32 bit integer.
  }

  return hash;
};
/**
 * A one-pass algorithm to compute the hash of a series of unique strings incrementally.
 *
 * NOTE: Duplicate values given as parameter to this function
 *       may yield to a hash which would collide with other hashes computed
 *       on different string series with this same function.
 *       Therefore is on behalf of the caller to be sure that the series of strings will be unique
 *       while calling this function incrementally.
 *
 * @param {string} str The string.
 * @param {number} [hash] The previous hash computed with this same function if this call is the continuation
 *                        of the unique string series.
 * @return {number} The next hash.
 */

function onePassStringHash(str, hash = 0) {
  let nextHash = hash;
  let n = 0;

  for (let j = 0; j < str.length; j++) {
    n = n * 251 ^ str.charCodeAt(j);
  }

  nextHash ^= n;
  return nextHash;
}
//# sourceMappingURL=hash.js.map

/***/ }),

/***/ "./node_modules/js-utl/dist/es6/modules/iterator.js":
/*!**********************************************************!*\
  !*** ./node_modules/js-utl/dist/es6/modules/iterator.js ***!
  \**********************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "xrange": () => (/* binding */ xrange)
/* harmony export */ });
/*
 * Copyright (c) 2021 Anton Bagdatyev (Tonix)
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */

/**
 * Utility functions related to iterators, generators and iterations.
 */

/**
 * Yields all the numbers of the specified range
 * (including "start" and "limit" if they fall in the range while stepping
 * with "step").
 *
 * @generator
 * @param {number} start An int specifying the start of the range.
 * @param {number} limit An int specifying the limit of the range.
 * @param {number} step An int specifying the step to take within each value of the range.
 * @yield {number} The next number of the range.
 */
function* xrange(start, limit, step = 1) {
  if (start < limit) {
    if (step <= 0) {
      throw new RangeError("Step must be a positive int greater than 0.");
    }

    for (let i = start; i <= limit; i += step) {
      yield i;
    }
  } else {
    if (step >= 0) {
      throw new RangeError("Step must be a negative int greater than 0.");
    }

    for (let i = start; i >= limit; i += step) {
      yield i;
    }
  }
}
//# sourceMappingURL=iterator.js.map

/***/ }),

/***/ "./node_modules/js-utl/dist/es6/modules/map-coordinates.js":
/*!*****************************************************************!*\
  !*** ./node_modules/js-utl/dist/es6/modules/map-coordinates.js ***!
  \*****************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "clampLat": () => (/* binding */ clampLat),
/* harmony export */   "wrapLng": () => (/* binding */ wrapLng),
/* harmony export */   "normalizeLat": () => (/* binding */ normalizeLat),
/* harmony export */   "normalizeLng": () => (/* binding */ normalizeLng)
/* harmony export */ });
/*
 * Copyright (c) 2021 Anton Bagdatyev (Tonix)
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */

/**
 * Utility functions related to the coordinates of a map.
 */

/**
 * Clamps a latitude value so that it is always between -90 and 90.
 *
 * Latitude ranges between -90 and 90 degrees, inclusive.
 * Values above or below this range will be clamped to the range [-90, 90].
 * This means that if the value specified is less than -90, it will be set to -90.
 * And if the value is greater than 90, it will be set to 90.
 *
 * @param {number|string} lat Latitude. Either a number or a string which can be casted to a number.
 * @return {number} Latitude, clamped.
 */
const clampLat = lat => Number(lat < -90 ? -90 : lat > 90 ? 90 : lat);
/**
 * Wraps a longitude value so that it is always between -180 and 180.
 *
 * Longitude ranges between -180 and 180 degrees, inclusive.
 * Values above or below this range will be wrapped so that they fall within the range.
 * For example, a value of -190 will be converted to 170.
 * A value of 190 will be converted to -170.
 * This reflects the fact that longitudes wrap around the globe.
 *
 * @param {number|string} lng Longitude. Either a number or a string which can be casted to a number.
 * @return {number} lng Longitude, wrapped.
 */

const wrapLng = lng => {
  lng = Number(lng);

  while (lng > 180) {
    lng -= 360;
  }

  while (lng < -180) {
    lng += 360;
  }

  return lng;
};
/**
 * Normalizes a latitude so that it is within the range [0, 180].
 *
 * 1. normalizeLat(90) === 0;
 * 2. normalizeLat(0) === 90;
 * 3. normalizeLat(-90) === 180;
 *
 * @param {number|string} lat Latitude. Either a number or a string which can be casted to a number.
 * @return {number} Latitude, normalized.
 */

const normalizeLat = lat => {
  lat = clampLat(lat);

  if (lat >= 0) {
    // 1. + 2.
    return 90 - lat;
  } else {
    // 3.
    return 90 + Math.abs(lat);
  }
};
/**
 * Normalizes a longitude so that it is within the range [0, 360].
 *
 * 1. normalizeLng(-180) === 0;
 * 2. normalizeLng(0) === 180;
 * 3. normalizeLng(180) === 360;
 *
 * @param {number} lng Longitude. Either a number or a string which can be casted to a number.
 * @return {number} Longitude, normalized.
 */

const normalizeLng = lng => {
  lng = wrapLng(lng);
  return lng + 180;
};
//# sourceMappingURL=map-coordinates.js.map

/***/ }),

/***/ "./node_modules/js-utl/dist/es6/modules/math.js":
/*!******************************************************!*\
  !*** ./node_modules/js-utl/dist/es6/modules/math.js ***!
  \******************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "round": () => (/* binding */ round),
/* harmony export */   "sum": () => (/* binding */ sum),
/* harmony export */   "intDiv": () => (/* binding */ intDiv),
/* harmony export */   "isEven": () => (/* binding */ isEven),
/* harmony export */   "isOdd": () => (/* binding */ isOdd),
/* harmony export */   "proportion": () => (/* binding */ proportion)
/* harmony export */ });
/*
 * Copyright (c) 2021 Anton Bagdatyev (Tonix)
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */

/**
 * Mathematical utility functions.
 */

/**
 * Rounds a number.
 *
 * @param {number} number The number to round.
 * @param {number} precision The decimal precision.
 * @return {number} The rounded number.
 */
function round(number, precision = 0) {
  const factor = Math.pow(10, precision);
  return Math.round(number * factor) / factor;
}
/**
 * Sums two or more numbers.
 *
 * @param {...number} numbers The numbers to sum.
 * @return {number} The sum.
 */

function sum(...numbers) {
  return numbers.reduce((carry, num) => carry + num);
}
/**
 * Integer division (without decimal part).
 *
 * @param {number} num A number.
 * @param {number} divideBy The number by which to divide "num".
 * @return {number} The result of the integer division.
 */

function intDiv(num, divideBy) {
  return Math.floor(num / divideBy);
}
/**
 * Tests if a number is even.
 *
 * @param {number} num A number.
 * @return {boolean} True if even, false if odd.
 */

function isEven(num) {
  return num % 2 === 0;
}
/**
 * Tests if a number is odd.
 *
 * @param {number} num A number.
 * @return {boolean} True if odd, false if even.
 */

function isOdd(num) {
  return !isEven(num);
}
/**
 * Converts a source value of a source range to a value that is proportional
 * in another destination range.
 *
 * @param {number[]} sourceTuple The source range tuple, i.e. a tuple of two values, the starting value of the range at index 0 and the ending value of the range at index 1,
 *                               respectively.
 *                               This function assumes that both the starting and ending values are positive numbers (the starting value can be 0).
 * @return {(destRange: number[]) => (sourceValue: number) => number} A function receiving the destination range tuple as argument (having the same shape as the source range)
 *                                                                    returning a function taking the source value as argument returning the value proportional
 *                                                                    in the destination range.
 */

const proportion = ([sourceFrom, sourceTo]) => ([destFrom, destTo]) => sourceValue => (sourceValue - sourceFrom) * ((destTo - destFrom) / (sourceTo - sourceFrom)) + destFrom;
//# sourceMappingURL=math.js.map

/***/ }),

/***/ "./node_modules/js-utl/dist/es6/modules/object.js":
/*!********************************************************!*\
  !*** ./node_modules/js-utl/dist/es6/modules/object.js ***!
  \********************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "objectMin": () => (/* binding */ objectMin)
/* harmony export */ });
/*
 * Copyright (c) 2021 Anton Bagdatyev (Tonix)
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */

/**
 * Object-related utility functions.
 */

/**
 * @type {Function}
 */
const defaultObjectComparator = (a, b) => a < b ? -1 : a > b ? 1 : 0;
/**
 * Finds the minimum value of an object.
 *
 * @param {Object} obj An object.
 * @param {Object} [options] An optional object with further options.
 * @param {(a: *, c: *) => number} [options.comparator] A comparator function receiving two values as arguments which must return a number less than 0 if the first
 *                                                      given value is the new minimum value.
 * @param {boolean} [options.returnAsKeyVal] True to return an object containing two properties `value` and `key`, namely the minimum value's key
 *                                           (`key` property) as well as the minimum value itself (`value` property).
 * @return {*} The minimum value or an object with the key and the minimum value if "returnAsKeyVal" is truthy.
 */


const objectMin = (obj, {
  comparator = defaultObjectComparator,
  returnAsKeyVal = false
} = {}) => {
  const min = {
    key: void 0,
    value: void 0
  };

  for (const key in obj) {
    const value = obj[key];

    if (typeof min.key === "undefined" || comparator(value, min.value) < 0) {
      min.key = key;
      min.value = value;
    }
  }

  return returnAsKeyVal ? min : min.value;
};
//# sourceMappingURL=object.js.map

/***/ }),

/***/ "./node_modules/js-utl/dist/es6/modules/path.js":
/*!******************************************************!*\
  !*** ./node_modules/js-utl/dist/es6/modules/path.js ***!
  \******************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "basename": () => (/* binding */ basename),
/* harmony export */   "pathinfo": () => (/* binding */ pathinfo),
/* harmony export */   "filenameExtension": () => (/* binding */ filenameExtension),
/* harmony export */   "dirname": () => (/* binding */ dirname)
/* harmony export */ });
/*
 * Copyright (c) 2021 Anton Bagdatyev (Tonix)
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */

/**
 * Utility functions for filenames and paths.
 */

/**
 * Returns the basename of a path.
 *
 * @param {string} path A path.
 * @return {string} The basename of the given path.
 */
function basename(path) {
  return path.split(/[\\/]/).pop();
}
/**
 * Returns info about a path.
 *
 * @param {string} path The path.
 * @param {number} [options] Optioanl options flag.
 */

function pathinfo(path, options) {
  //  discuss at: http://locutus.io/php/pathinfo/
  // original by: Nate
  //  revised by: Kevin van Zonneveld (http://kvz.io)
  // improved by: Brett Zamir (http://brett-zamir.me)
  // improved by: Dmitry Gorelenkov
  //    input by: Timo
  //      note 1: Inspired by actual PHP source: php5-5.2.6/ext/standard/string.c line #1559
  //      note 1: The way the bitwise arguments are handled allows for greater flexibility
  //      note 1: & compatability. We might even standardize this
  //      note 1: code and use a similar approach for
  //      note 1: other bitwise PHP functions
  //      note 1: Locutus tries very hard to stay away from a core.js
  //      note 1: file with global dependencies, because we like
  //      note 1: that you can just take a couple of functions and be on your way.
  //      note 1: But by way we implemented this function,
  //      note 1: if you want you can still declare the PATHINFO_*
  //      note 1: yourself, and then you can use:
  //      note 1: pathinfo('/www/index.html', PATHINFO_BASENAME | PATHINFO_EXTENSION);
  //      note 1: which makes it fully compliant with PHP syntax.
  //   example 1: pathinfo('/www/htdocs/index.html', 1)
  //   returns 1: '/www/htdocs'
  //   example 2: pathinfo('/www/htdocs/index.html', 'PATHINFO_BASENAME')
  //   returns 2: 'index.html'
  //   example 3: pathinfo('/www/htdocs/index.html', 'PATHINFO_EXTENSION')
  //   returns 3: 'html'
  //   example 4: pathinfo('/www/htdocs/index.html', 'PATHINFO_FILENAME')
  //   returns 4: 'index'
  //   example 5: pathinfo('/www/htdocs/index.html', 2 | 4)
  //   returns 5: {basename: 'index.html', extension: 'html'}
  //   example 6: pathinfo('/www/htdocs/index.html', 'PATHINFO_ALL')
  //   returns 6: {dirname: '/www/htdocs', basename: 'index.html', extension: 'html', filename: 'index'}
  //   example 7: pathinfo('/www/htdocs/index.html')
  //   returns 7: {dirname: '/www/htdocs', basename: 'index.html', extension: 'html', filename: 'index'}
  let opt = "";
  let realOpt = "";
  let optName = "";
  let optTemp = 0;
  const tmpArr = {};
  let cnt = 0;
  let i = 0;
  let haveBasename = false;
  let haveExtension = false;
  let haveFilename = false; // Input defaulting & sanitation

  if (!path) {
    return false;
  }

  if (!options) {
    options = "PATHINFO_ALL";
  } // Initialize binary arguments. Both the string & integer (constant) input is
  // allowed


  const OPTS = {
    PATHINFO_DIRNAME: 1,
    PATHINFO_BASENAME: 2,
    PATHINFO_EXTENSION: 4,
    PATHINFO_FILENAME: 8,
    PATHINFO_ALL: 0
  }; // PATHINFO_ALL sums up all previously defined PATHINFOs (could just pre-calculate)

  for (optName in OPTS) {
    if (Object.prototype.hasOwnProperty.call(OPTS, optName)) {
      OPTS.PATHINFO_ALL = OPTS.PATHINFO_ALL | OPTS[optName];
    }
  }

  if (typeof options !== "number") {
    // Allow for a single string or an array of string flags
    options = [].concat(options);

    for (i = 0; i < options.length; i++) {
      // Resolve string input to bitwise e.g. 'PATHINFO_EXTENSION' becomes 4
      if (OPTS[options[i]]) {
        optTemp = optTemp | OPTS[options[i]];
      }
    }

    options = optTemp;
  } // Internal Functions


  const _getExt = function (path) {
    const str = path + "";
    const dotP = str.lastIndexOf(".") + 1;
    return !dotP ? false : dotP !== str.length ? str.substr(dotP) : "";
  }; // Gather path infos


  if (options & OPTS.PATHINFO_DIRNAME) {
    const dirName = path.replace(/\\/g, "/").replace(/\/[^/]*\/?$/, ""); // dirname

    tmpArr.dirname = dirName === path ? "." : dirName;
  }

  if (options & OPTS.PATHINFO_BASENAME) {
    if (haveBasename === false) {
      haveBasename = basename(path);
    }

    tmpArr.basename = haveBasename;
  }

  if (options & OPTS.PATHINFO_EXTENSION) {
    if (haveBasename === false) {
      haveBasename = basename(path);
    }

    if (haveExtension === false) {
      haveExtension = _getExt(haveBasename);
    }

    if (haveExtension !== false) {
      tmpArr.extension = haveExtension;
    }
  }

  if (options & OPTS.PATHINFO_FILENAME) {
    if (haveBasename === false) {
      haveBasename = basename(path);
    }

    if (haveExtension === false) {
      haveExtension = _getExt(haveBasename);
    }

    if (haveFilename === false) {
      haveFilename = haveBasename.slice(0, haveBasename.length - (haveExtension ? haveExtension.length + 1 : haveExtension === false ? 0 : 1));
    }

    tmpArr.filename = haveFilename;
  } // If array contains only 1 element: return string


  cnt = 0;

  for (opt in tmpArr) {
    if (Object.prototype.hasOwnProperty.call(tmpArr, opt)) {
      cnt++;
      realOpt = opt;
    }
  }

  if (cnt === 1) {
    return tmpArr[realOpt];
  } // Return full-blown array


  return tmpArr;
}
/**
 * Returns the extension of a filename.
 *
 * @param {string} filename The filename.
 * @return {string} The extension of the given filename.
 */

function filenameExtension(filename) {
  const extension = pathinfo(filename, "PATHINFO_EXTENSION");

  if (typeof extension !== "string") {
    return "";
  }

  return extension;
}
/**
 * Returns the dirname of a path.
 *
 * @param {string} path A path.
 * @return {string} The dirname of the given path.
 */

function dirname(path) {
  //  discuss at: http://locutus.io/php/dirname/
  // original by: Ozh
  // improved by: XoraX (http://www.xorax.info)
  //   example 1: dirname('/etc/passwd')
  //   returns 1: '/etc'
  //   example 2: dirname('c:/Temp/x')
  //   returns 2: 'c:/Temp'
  //   example 3: dirname('/dir/test/')
  //   returns 3: '/dir'
  return path.replace(/\\/g, "/").replace(/\/[^/]*\/?$/, "");
}
//# sourceMappingURL=path.js.map

/***/ }),

/***/ "./node_modules/js-utl/dist/es6/modules/promise.js":
/*!*********************************************************!*\
  !*** ./node_modules/js-utl/dist/es6/modules/promise.js ***!
  \*********************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "timeout": () => (/* binding */ timeout),
/* harmony export */   "minDelayPromise": () => (/* binding */ minDelayPromise),
/* harmony export */   "maxDelayFallbackPromise": () => (/* binding */ maxDelayFallbackPromise),
/* harmony export */   "applyAsync": () => (/* binding */ applyAsync),
/* harmony export */   "composeAsync": () => (/* binding */ composeAsync)
/* harmony export */ });
/* harmony import */ var _core__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./core */ "./node_modules/js-utl/dist/es6/modules/core/index.js");
/*
 * Copyright (c) 2021 Anton Bagdatyev (Tonix)
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */

/**
 * Promise-related utility functions.
 */

/**
 * Creates a new timeout promise which will resolve after the given milliseconds timeout.
 *
 * @param {number} delayMs Delay in milliseconds after which the promise will resolve.
 * @param {*} [value] Optional value to resolve with.
 * @return {Promise} A new promise which resolves right after "delayMs" have elapsed.
 */

function timeout(delayMs, value) {
  return new Promise(resolve => setTimeout(resolve, delayMs, value));
}
/**
 * Creates a new promise with a minimum delay before it is fulfilled.
 *
 * @param {Promise} promise A promise.
 * @param {number} minDelay Minumum delay in milliseconds.
 * @return {Promise} A new promise which resolves with the given value of the given promise
 *                   or rejects with the rejected value of the promise if it rejects.
 */

function minDelayPromise(promise, minDelay) {
  return Promise.all([promise, timeout(minDelay)]).then(([value]) => {
    return value;
  }).catch(e => {
    throw e;
  });
}
/**
 * Resolves a promise with an optional fallback which fires if the promise does not resolve
 * under a certain time constraint.
 *
 * @param {Object} obj An object.
 * @param {Promise} obj.promise A promise.
 * @param {number} obj.maxDelayMs Max delay in milliseconds to wait before executing "onFallback" code if the time elapses.
 * @param {Function} obj.onFallback Function to execute if the given "promise" takes more than "maxDelayMs" to resolve.
 * @param {number} obj.fallbackMinDelayMs Number of milliseconds to wait if the fallback code gets executed before resolving the promise.
 * @return {Promise} A promise which resolves with the value of "promise" or rejects with the error of the given "promise".
 */

function maxDelayFallbackPromise({
  promise,
  maxDelayMs,
  // eslint-disable-next-line @typescript-eslint/no-empty-function
  onFallback = () => {},
  fallbackMinDelayMs
} = {}) {
  const uniqueIdentifier = (0,_core__WEBPACK_IMPORTED_MODULE_0__.uniqueId)();
  return Promise.race([promise, timeout(maxDelayMs).then(() => uniqueIdentifier)]).then(value => {
    if (value === uniqueIdentifier) {
      onFallback();
      return minDelayPromise(promise, fallbackMinDelayMs);
    } else {
      return value;
    }
  });
}
/**
 * Applies an async operation to a promise returning a new one.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/JavaScript/Guide/Using_promises#Composition
 *
 * @param {Promise} acc A promise.
 * @param {Function} onfulfilled Fulfilled callback.
 * @param {Function} onrejected Rejected callback.
 * @return {Promise} A new promise.
 */

const applyAsync = (acc, onfulfilled, onrejected) => {
  return typeof onrejected !== "undefined" ? acc.then(onfulfilled, onrejected) : acc.then(onfulfilled);
};
/**
 * Composes functions which are run subsequently
 * (sequential composition) using promises.
 *
 * This function is useful when a chain of operations is known ahead of time
 * and they must be executed subsequently.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/JavaScript/Guide/Using_promises#Composition
 *
 * @param {...(Function|Array)} funcs The functions (fulfilled callbacks). An array can be given and it MUST have
 *                                    the fulfilled callback at index 0 and the reject callback at index 1.
 * @return {Function} A new function that accepts an initial value to be passed through the composition pipeline.
 *                    If the returned function is called, it will return a Promise which represents the chain formed
 *                    by the promises composed subsequently.
 */

const composeAsync = (...funcs) => x => {
  return funcs.reduce((acc, func) => {
    let promise;

    if ((0,_core__WEBPACK_IMPORTED_MODULE_0__.isArray)(func)) {
      // Item at index 0 is the fulfilled callback,
      // while the item at index 1 is the reject callback.
      promise = applyAsync(acc, func[0], func[1]);
    } else {
      promise = applyAsync(acc, func);
    }

    return promise;
  }, Promise.resolve(x));
};
//# sourceMappingURL=promise.js.map

/***/ }),

/***/ "./node_modules/js-utl/dist/es6/modules/rand.js":
/*!******************************************************!*\
  !*** ./node_modules/js-utl/dist/es6/modules/rand.js ***!
  \******************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "randomStr": () => (/* binding */ randomStr),
/* harmony export */   "randomInt": () => (/* binding */ randomInt),
/* harmony export */   "randomFloat": () => (/* binding */ randomFloat),
/* harmony export */   "randomDifferentFromValue": () => (/* binding */ randomDifferentFromValue),
/* harmony export */   "uuid": () => (/* binding */ uuid),
/* harmony export */   "noncanonicalUUID": () => (/* binding */ noncanonicalUUID),
/* harmony export */   "randomArrayShuffle": () => (/* binding */ randomArrayShuffle)
/* harmony export */ });
/*
 * Copyright (c) 2021 Anton Bagdatyev (Tonix)
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */

/**
 * Utility functions providing random functionality.
 */

/**
 * Generates a random string.
 *
 * @param {number} length The length of the string to return.
 * @return {string} The random string.
 */
function randomStr(length) {
  let result = "";
  const characters = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";
  const charactersLength = characters.length;

  for (let i = 0; i < length; i++) {
    result += characters.charAt(Math.floor(Math.random() * charactersLength));
  }

  return result;
}
/**
 * Generates a random int between min and max (both included).
 *
 * @param {number} min Min int value.
 * @param {number} max Max int value.
 * @return {number} A random int between min and max.
 */

function randomInt(min = 0, max = Number.MAX_SAFE_INTEGER) {
  min = Math.ceil(min);
  max = Math.floor(max);
  return Math.floor(Math.random() * (max - min + 1)) + min;
}
/**
 * Generates a random float between min and max integers (both included).
 *
 * @param {number} min Min int value.
 * @param {number} max Max int value.
 * @return {number} A random float between min and max.
 */

function randomFloat(min = 0, max = 562949953421311) {
  const randInt = randomInt(min, max);

  if (randInt >= max) {
    return randInt;
  }

  return randInt + Math.random();
}
/**
 * Returns a random float (using "Math.random()") not equal to the given value.
 *
 * @param {number} value The given value.
 * @return {number} A random float not equal to value.
 */

function randomDifferentFromValue(value) {
  let ret = Math.random();

  while (value == ret) {
    ret = Math.random();
  }

  return ret;
}
/**
 * @type {Array}
 */

let noncanonicalUUIDCounter = 1;
/**
 * Generates a new UUID.
 *
 * @see https://github.com/tastejs/todomvc/blob/gh-pages/examples/react/js/utils.js
 *
 * @return {string} The UUID.
 */

function uuid() {
  let i, random;
  let uuid = "";

  for (i = 0; i < 32; i++) {
    random = Math.random() * 16 | 0;

    if (i === 8 || i === 12 || i === 16 || i === 20) {
      uuid += "-";
    }

    uuid += (i === 12 ? 4 : i === 16 ? random & 3 | 8 : random).toString(16);
  }

  return uuid;
}
/**
 * Generates a new noncanonical UUID. This noncanonical UUID has a further
 * int prefixed by "-" which is concatenated to a canonical UUID at the end
 * of the canonical UUID before returning this noncanonical UUID.
 * The int starts with 1 and increments every time this function is called.
 *
 * @return {string} A noncanonical UUID.
 */

function noncanonicalUUID() {
  const UUID = uuid();
  return UUID + "-" + noncanonicalUUIDCounter++;
}
/**
 * Shuffles an array randomly modifing the given array.
 *
 * @param {Array} array The input array.
 * @return {Array} A reference to the same array given as input,
 *                 but with its elements randomly shuffled.
 */

const shuffleArrayRandomly = array => {
  let i = array.length,
      temporaryValue,
      randomIndex;

  while (0 !== i) {
    randomIndex = Math.floor(Math.random() * i);
    i--;
    temporaryValue = array[i];
    array[i] = array[randomIndex];
    array[randomIndex] = temporaryValue;
  }

  return array;
};
/**
 * Returns an array with the elements of the given array randomly shuffled.
 *
 * @param {Array} array An array.
 * @return {Array} A new array with the same elements of the input array, but randomly shuffled.
 */


function randomArrayShuffle(array) {
  return shuffleArrayRandomly(Object.keys(array)).map(i => array[i]);
}
//# sourceMappingURL=rand.js.map

/***/ }),

/***/ "./node_modules/js-utl/dist/es6/modules/stats.js":
/*!*******************************************************!*\
  !*** ./node_modules/js-utl/dist/es6/modules/stats.js ***!
  \*******************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "mean": () => (/* binding */ mean),
/* harmony export */   "median": () => (/* binding */ median),
/* harmony export */   "minAbsDeviationFromValue": () => (/* binding */ minAbsDeviationFromValue),
/* harmony export */   "minAbsDeviationFromExcludedValue": () => (/* binding */ minAbsDeviationFromExcludedValue)
/* harmony export */ });
/* harmony import */ var _math__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./math */ "./node_modules/js-utl/dist/es6/modules/math.js");
/* harmony import */ var _array__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./array */ "./node_modules/js-utl/dist/es6/modules/array.js");
/*
 * Copyright (c) 2021 Anton Bagdatyev (Tonix)
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */

/**
 * Statistics-related utility functions.
 */


/**
 * Computes the mean of the given numbers.
 *
 * @param {...number} numbers The numbers.
 * @return {number} The mean.
 */

function mean(...numbers) {
  return (0,_math__WEBPACK_IMPORTED_MODULE_0__.sum)(...numbers) / numbers.length;
}
/**
 * Computes the median of the given numbers.
 *
 * @param {...number} numbers The numbers.
 * @return {number} The median value.
 */

function median(...numbers) {
  const sorted = (0,_array__WEBPACK_IMPORTED_MODULE_1__.sortNums)(numbers);
  const middleIndex = (0,_math__WEBPACK_IMPORTED_MODULE_0__.intDiv)(sorted.length, 2);
  return (0,_math__WEBPACK_IMPORTED_MODULE_0__.isOdd)(sorted.length) ? sorted[middleIndex] : mean(sorted[middleIndex - 1], sorted[middleIndex]);
}
/**
 * Computes the min deviation from a value.
 *
 * @param {number} value A value.
 * @return {(...numbers: number[]) => number} A function receiving the numbers to use to compute the minimum deviation
 *                                            from "value".
 */

const minAbsDeviationFromValue = value => (...numbers) => {
  let minAbsDeviation = Infinity;
  numbers.map(num => {
    const deviation = Math.abs(num - value);

    if (deviation < minAbsDeviation) {
      minAbsDeviation = deviation;
    }
  });
  return minAbsDeviation;
};
/**
 * Computes the min deviation from a value excluding that value from the numbers received.
 *
 * @param {number} value A value.
 * @return {(...numbers: number[]) => number} A function receiving the numbers to use to compute the minimum deviation
 *                                            from "value" exluding "value".
 */

const minAbsDeviationFromExcludedValue = value => (...numbers) => minAbsDeviationFromValue(value)(...numbers.filter(num => num !== value));
//# sourceMappingURL=stats.js.map

/***/ }),

/***/ "./node_modules/js-utl/dist/es6/modules/string.js":
/*!********************************************************!*\
  !*** ./node_modules/js-utl/dist/es6/modules/string.js ***!
  \********************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "escapeRegExp": () => (/* binding */ escapeRegExp),
/* harmony export */   "trimCharacterMask": () => (/* binding */ trimCharacterMask),
/* harmony export */   "trimCharacterRegex": () => (/* binding */ trimCharacterRegex),
/* harmony export */   "trim": () => (/* binding */ trim),
/* harmony export */   "trimLeft": () => (/* binding */ trimLeft),
/* harmony export */   "trimRight": () => (/* binding */ trimRight),
/* harmony export */   "concatWithInnerOuterSeparators": () => (/* binding */ concatWithInnerOuterSeparators),
/* harmony export */   "optionsValueLabel": () => (/* binding */ optionsValueLabel),
/* harmony export */   "startsWith": () => (/* binding */ startsWith),
/* harmony export */   "ucaseFirst": () => (/* binding */ ucaseFirst),
/* harmony export */   "reverseString": () => (/* binding */ reverseString),
/* harmony export */   "separateIntThousands": () => (/* binding */ separateIntThousands)
/* harmony export */ });
/* harmony import */ var _core__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./core */ "./node_modules/js-utl/dist/es6/modules/core/index.js");
/*
 * Copyright (c) 2021 Anton Bagdatyev (Tonix)
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */

/**
 * Utility functions for working with strings.
 */

/**
 * Escapes special characters for a JS regex.
 *
 * @see https://stackoverflow.com/questions/3115150/how-to-escape-regular-expression-special-characters-using-javascript#answer-9310752
 *
 * @param {string} text The string to escape.
 * @return {string} The same string with special regex characters escaped.
 */

function escapeRegExp(text) {
  return text.replace(/[-[\]{}()*+?.,\\^$|#\s]/g, "\\$&");
}
/**
 * Trim characters from the beginning and end of a string.
 *
 * @param {string} str The string.
 * @param {string} characterMask Character mask.
 * @param {Object} options Options.
 * @param {Object.boolean} options.shouldTrimLeft "true" to trim left (defaults to "true" if omitted).
 * @param {Object.boolean} options.shouldTrimRight "true" to trim right (defaults to "true" if omitted).
 * @return {string} The trimmed string.
 */

function trimCharacterMask(str, characterMask, options = {}) {
  if (typeof characterMask === "undefined") {
    characterMask = " ";
  }

  const escapedCharacterMask = escapeRegExp(characterMask);
  const shouldTrimLeft = typeof options.shouldTrimLeft === "undefined" || options.shouldTrimLeft;
  const shouldTrimRight = typeof options.shouldTrimRight === "undefined" || options.shouldTrimRight;
  const regexParts = [];

  if (shouldTrimLeft) {
    regexParts[regexParts.length] = "^[" + escapedCharacterMask + "]+";
  }

  if (shouldTrimRight) {
    regexParts[regexParts.length] = "[" + escapedCharacterMask + "]+$";
  }

  const regex = new RegExp(regexParts.join("|"), "gm");
  return str.replace(regex, "");
}
/**
 * Trim characters from the beginning and end of a string using an array
 * of regex characters.
 *
 * @param {string} str The string.
 * @param {string} regexCharacters Regex characters.
 * @param {string} additionalModifiers Additional regex modifiers.
 * @return {string} The trimmed string.
 */

function trimCharacterRegex(str, regexCharacters, additionalModifiers) {
  if (typeof regexCharacters === "undefined") {
    regexCharacters = ["\\s"];
  }

  additionalModifiers = additionalModifiers || "";
  const regex = new RegExp("(^(" + regexCharacters.join("|") + ")+)|((" + regexCharacters.join("|") + ")+$)", "g" + additionalModifiers);
  return str.replace(regex, "");
}
/**
 * Alias of "trimCharacterMask".
 * Trim characters from the beginning and end of a string.
 *
 * @param {string} str The string.
 * @param {string} characterMask Character mask.
 * @return {string} The trimmed string.
 */

function trim(str, characterMask) {
  return trimCharacterMask(str, characterMask);
}
/**
 * Trim characters from the beginning of a string.
 *
 * @param {string} str The string.
 * @param {string} characterMask Character mask.
 * @return {string} The left-trimmed string.
 */

function trimLeft(str, characterMask) {
  return trimCharacterMask(str, characterMask, {
    shouldTrimLeft: true,
    shouldTrimRight: false
  });
}
/**
 * Trim characters from the end of a string.
 *
 * @param {string} str The string.
 * @param {string} characterMask Character mask.
 * @return {string} The right-trimmed string.
 */

function trimRight(str, characterMask) {
  return trimCharacterMask(str, characterMask, {
    shouldTrimLeft: false,
    shouldTrimRight: true
  });
}
/**
 * Concats the values of an array with an outer separator using an inner separator
 * for first nesting level arrays.
 *
 * E.g.:
 *
 *     concatWithInnerOuterSeparators(["a", "value", ["with", "nested", "value"], "etc"], " # ", " $ ")
 *     // Returns:
 *     "a # value # with $ nested $ value # etc"
 *
 * @param {Array<string>|Array<Array<string>>} arrayToConcat An array of strings or arrays or strings.
 * @param {string} outerSeparator Outer separator.
 * @param {string} innerSeparator Inner separator.
 * @return {string} The concatenated string.
 */

function concatWithInnerOuterSeparators(arrayToConcat, outerSeparator = " ", innerSeparator = " ") {
  const toConcatFinally = [];

  for (const value of arrayToConcat) {
    if ((0,_core__WEBPACK_IMPORTED_MODULE_0__.isArray)(value)) {
      toConcatFinally[toConcatFinally.length] = trim(value.join(innerSeparator), innerSeparator);
    } else {
      toConcatFinally[toConcatFinally.length] = value;
    }
  }

  const ret = trim(toConcatFinally.join(outerSeparator), outerSeparator);
  return ret;
}
/**
 * Returns an array of options with a value and a label string.
 *
 * @param {Object} obj An object with parameters.
 * @param {Object[]} obj.arrayOfObjects An array of objects.
 * @param {string[]|Array.<string|string[]>} obj.concatObjectProps Properties, keys of each object to use for the label
 *                                                                 and to concat with "outerSeparator".
 *                                                                 A nested array of properties can be used to specify an inner
 *                                                                 part of the label to concat using the "innerSeparator".
 * @param {string} obj.valueProp Name of the property which value has to be used for the value property
 *                               of the returned array of objects.
 * @param {string} obj.outerSeparator Outer separator.
 * @param {string} obj.innerSeparator Inner separator.
 * @return {Object[]} Array of objects, each object with two properties:
 *
 *                        - "value": The value of the object;
 *                        - "label": The label of the object.
 *
 */

function optionsValueLabel({
  arrayOfObjects,
  concatObjectProps,
  valueProp = "id",
  outerSeparator = " ",
  innerSeparator = " "
}) {
  const options = [];

  for (const obj of arrayOfObjects) {
    const arrayToConcat = [];

    for (const prop of concatObjectProps) {
      if ((0,_core__WEBPACK_IMPORTED_MODULE_0__.isArray)(prop)) {
        arrayToConcat[arrayToConcat.length] = prop.map(propName => obj[propName]);
      } else {
        arrayToConcat[arrayToConcat.length] = obj[prop];
      }
    }

    options[options.length] = {
      value: obj[valueProp],
      label: concatWithInnerOuterSeparators(arrayToConcat, outerSeparator, innerSeparator)
    };
  }

  return options;
}
/**
 * Tests if a string starts with another
 *
 * @param {string} haystack The haystack string.
 * @param {string} needle The needle string.
 * @return {boolean} True if the haystack string starts with needle, false otherwise.
 */

function startsWith(haystack, needle) {
  return haystack.indexOf(needle, 0) === 0;
}
/**
 * Makes the first character of the given string uppercase.
 *
 * @param {string} string A string.
 * @return {string} The string with its first character made uppercase.
 */

function ucaseFirst(string) {
  return string.charAt(0).toUpperCase() + string.slice(1);
}
/**
 * Reverses a string.
 *
 * @param {string} string A string to reverse.
 * @return {string} The reversed string.
 */

function reverseString(string) {
  return string.split("").reverse().join("");
}
/**
 * Given an integer, returns a string containing the same integer with additional thousands separators.
 *
 * @param {number} num An integer.
 * @param {string} [sep] The thousands separator to use.
 * @return {string} The same integer with thousands separators.
 */

function separateIntThousands(num, sep = ",") {
  return num.toString().split("").reverse().map((d, i, a) => (i + 1) % 3 === 0 && i !== a.length - 1 ? `${sep}${d}` : d).reverse().join("");
}
//# sourceMappingURL=string.js.map

/***/ }),

/***/ "./node_modules/js-utl/dist/es6/modules/time.js":
/*!******************************************************!*\
  !*** ./node_modules/js-utl/dist/es6/modules/time.js ***!
  \******************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "time": () => (/* binding */ time),
/* harmony export */   "msToTime": () => (/* binding */ msToTime),
/* harmony export */   "millisecToSec": () => (/* binding */ millisecToSec)
/* harmony export */ });
/*
 * Copyright (c) 2021 Anton Bagdatyev (Tonix)
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */

/**
 * Time-related utility functions.
 */

/**
 * Returns the current Unix time in seconds.
 *
 * @return {number} Current Unix time in seconds.
 */
function time() {
  const d = new Date();
  const seconds = Math.round(d.getTime() / 1000);
  return seconds;
}
/**
 * Returns the time string approximated to the nearest microsecond
 * corresponding the given Unix timestamp in milliseconds
 * in the format "HH:mm:ss.ms".
 *
 * @param {number} [ms] The time in microseconds to convert into a string.
 *                      If omitted, the current time will be used.
 * @return {string} The time string.
 */

function msToTime(ms) {
  const date = new Date(ms || new Date().getTime());
  const isoDate = new Date(date.getTime() - date.getTimezoneOffset() * 60000).toISOString();
  return isoDate.slice(11, -1);
}
/**
 * Converts milliseconds to seconds.
 *
 * @param {number} millisec Number of milliseconds.
 * @return {number} The milliseconds in seconds.
 */

function millisecToSec(millisec) {
  return millisec * 0.001;
}
//# sourceMappingURL=time.js.map

/***/ }),

/***/ "./node_modules/js-utl/dist/es6/modules/web.js":
/*!*****************************************************!*\
  !*** ./node_modules/js-utl/dist/es6/modules/web.js ***!
  \*****************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "buildQueryString": () => (/* binding */ buildQueryString),
/* harmony export */   "formData": () => (/* binding */ formData),
/* harmony export */   "xhr": () => (/* binding */ xhr),
/* harmony export */   "checkNetwork": () => (/* binding */ checkNetwork),
/* harmony export */   "waitNetwork": () => (/* binding */ waitNetwork),
/* harmony export */   "setCookie": () => (/* binding */ setCookie),
/* harmony export */   "getCookie": () => (/* binding */ getCookie),
/* harmony export */   "unsetCookie": () => (/* binding */ unsetCookie),
/* harmony export */   "isInViewport": () => (/* binding */ isInViewport),
/* harmony export */   "isScrolledIntoView": () => (/* binding */ isScrolledIntoView),
/* harmony export */   "hasVerticalScrollbar": () => (/* binding */ hasVerticalScrollbar),
/* harmony export */   "hasHorizontalScrollbar": () => (/* binding */ hasHorizontalScrollbar),
/* harmony export */   "elementUniqueId": () => (/* binding */ elementUniqueId),
/* harmony export */   "getElementComputedStyle": () => (/* binding */ getElementComputedStyle),
/* harmony export */   "elementInnerDimensions": () => (/* binding */ elementInnerDimensions),
/* harmony export */   "countTextareaLines": () => (/* binding */ countTextareaLines),
/* harmony export */   "isScrollOnBottom": () => (/* binding */ isScrollOnBottom),
/* harmony export */   "getVerticalScrollBarWidth": () => (/* binding */ getVerticalScrollBarWidth),
/* harmony export */   "isEllipsisActive": () => (/* binding */ isEllipsisActive),
/* harmony export */   "copyTextToClipboard": () => (/* binding */ copyTextToClipboard),
/* harmony export */   "rAFLooper": () => (/* binding */ rAFLooper),
/* harmony export */   "nestedRAF": () => (/* binding */ nestedRAF),
/* harmony export */   "getRawURIFragment": () => (/* binding */ getRawURIFragment),
/* harmony export */   "getDecodedURIFragment": () => (/* binding */ getDecodedURIFragment),
/* harmony export */   "appendEncodedJSONFragmentToURI": () => (/* binding */ appendEncodedJSONFragmentToURI),
/* harmony export */   "getDecodedJSONFromFragmentURI": () => (/* binding */ getDecodedJSONFromFragmentURI),
/* harmony export */   "parseQueryStringArgsMultiDim": () => (/* binding */ parseQueryStringArgsMultiDim),
/* harmony export */   "getQueryStringArgsMultiDim": () => (/* binding */ getQueryStringArgsMultiDim),
/* harmony export */   "cursorFocus": () => (/* binding */ cursorFocus),
/* harmony export */   "detectWrapped": () => (/* binding */ detectWrapped),
/* harmony export */   "maxNestingLevel": () => (/* binding */ maxNestingLevel),
/* harmony export */   "getScrollableAncestor": () => (/* binding */ getScrollableAncestor),
/* harmony export */   "smoothScrollToTop": () => (/* binding */ smoothScrollToTop)
/* harmony export */ });
/* harmony import */ var _core__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./core */ "./node_modules/js-utl/dist/es6/modules/core/index.js");
/*
 * Copyright (c) 2021 Anton Bagdatyev (Tonix)
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */

/**
 * Web application (browser) utility functions.
 */

/**
 * Builds a form data instance or object recursively.
 *
 * @see https://stackoverflow.com/questions/22783108/convert-js-object-to-form-data#answer-42483509
 *
 * @param {FormData|Object} formData Form data instance or JS POJO object.
 * @param {Array|Object} data JS POJO object or array with the form data to use to build the forma data.
 * @param {boolean} [shouldEncodeURIComponent] Optionally instructs the function if the parameters should be encoded
 *                                             (keys and values) using "encodeURIComponent".
 * @param {string} [parentKey] Parent key for nested parameters (used internally for recursion).
 * @return {undefined}
 */

function buildFormData(formData, data, shouldEncodeURIComponent, parentKey) {
  if (data && typeof data === "object" && !(data instanceof Date) && !(data instanceof File)) {
    Object.keys(data).forEach(key => {
      buildFormData(formData, data[key], shouldEncodeURIComponent, parentKey ? `${parentKey}[${key}]` : shouldEncodeURIComponent ? encodeURIComponent(key) : key);
    });
  } else {
    // Leaf value.
    const value = data == null ? "" : shouldEncodeURIComponent ? encodeURIComponent(data) : data;
    formData instanceof FormData ? formData.append(parentKey, value) : formData[parentKey] = value;
  }
}
/**
 * Constructs a query string from the given data.
 * This method works with nested objects/arrays.
 *
 * @param {Array|Object} data The object.
 * @return {string} The query string.
 */


function buildQueryString(data) {
  const formData = {};
  buildFormData(formData, data, true); // "true" instructs the function to encode URI parts (keys and values).

  const parts = [];

  for (const prop in formData) {
    parts.push(prop + "=" + formData[prop]);
  }

  return parts.join("&");
}
/**
 * Converts an object (array or JS POJO object) to a form data instance.
 *
 * @param {Array|Object} data The data (array or POJO object).
 * @return {FormData} A form data instance.
 */

function formData(data) {
  const formData = new FormData();
  buildFormData(formData, data);
  return formData;
}
/**
 * Returns a new XMLHttpRequest or ActiveXObject object.
 *
 * @return {XMLHttpRequest|ActiveXObject}
 */

const xhr = () => window.ActiveXObject && new window.ActiveXObject("Microsoft.XMLHTTP") || new XMLHttpRequest();
/**
 * Checks whether the network is reachable or not.
 *
 * @param {string} URI An eventual URI to use for the request.
 *                     NOTE: If omitted, then "config.checkNetworkURI" will be used,
 *                           or, if "config.checkNetworkURI" is not set,
 *                           the website's hostname will be used.
 *                           Also, in every case, A GET query string with a random parameter
 *                           is always appended to the URI.
 * @return {Promise} A promise which resolves to "true" if the network is reachable and to "false" otherwise.
 *                   Note that the returned promise always resolves to "true" when this code runs on localhost.
 */

function checkNetwork(URI = null) {
  // Handle IE and more capable browsers.
  const xhrObj = xhr();
  return new Promise(resolve => {
    // Issue request and handle response.
    try {
      xhrObj.onreadystatechange = () => {
        if (xhrObj.readyState == 4) {
          resolve(xhrObj.status >= 200 && (xhrObj.status < 300 || xhrObj.status === 304));
        }
      }; // Open new request as a HEAD to the root hostname with a random param to bust the cache.


      xhrObj.open("HEAD", (URI || _core__WEBPACK_IMPORTED_MODULE_0__.config.checkNetworkURI || "//" + window.location.hostname + (window.location.port != 80 ? ":" + window.location.port : "")) + "?rand=" + Math.floor((1 + Math.random()) * 0x10000), // 0x10000 = 2^16
      true);
      xhrObj.send();
    } catch (error) {
      resolve(false);
    }
  });
}
/**
 * Returns a promise which resolves when the network is available.
 *
 * NOTE: If the network is available, the promise will resolve almost immediately.
 *
 * @param {number} intervalMillisecs An interval in milliseconds to wait before the next network check.
 * @return {Promise} A promise.
 */

function waitNetwork(intervalMillisecs = 3000) {
  return new Promise(resolve => {
    checkNetwork().then(isNetworkReachable => {
      if (isNetworkReachable) {
        resolve();
      } else {
        const interval = setInterval(() => {
          checkNetwork().then(isNetworkReachable => {
            if (isNetworkReachable) {
              clearInterval(interval);
              resolve();
            }
          });
        }, intervalMillisecs);
      }
    });
  });
}
/**
 * Sets a cookie value.
 *
 * @see https://www.w3schools.com/js/js_cookies.asp
 *
 * @param {string} cname The cookie name.
 * @param {string} cvalue The cookie value.
 * @param {number|undefined} exdays Number of days after which the cookie expires,
 *                                  or "undefined" to make the cookie expire at the end of the session.
 * @return {undefined}
 */

function setCookie(cname, cvalue, exdays) {
  let expires = "";

  if (exdays) {
    const d = new Date();
    d.setTime(d.getTime() + exdays * 24 * 60 * 60 * 1000);
    expires = "expires=" + d.toUTCString() + ";";
  }

  document.cookie = cname + "=" + cvalue + ";" + expires + "path=/";
}
/**
 * Gets a cookie value.
 *
 * @see https://www.w3schools.com/js/js_cookies.asp
 *
 * @param {string} cname The cookie name.
 * @return {string|undefined} The cookie value or "undefined", if not set.
 */

function getCookie(cname) {
  const name = cname + "=";
  const ca = document.cookie.split(";");

  for (let i = 0; i < ca.length; i++) {
    let c = ca[i];

    while (c.charAt(0) == " ") {
      c = c.substring(1);
    }

    if (c.indexOf(name) == 0) {
      return c.substring(name.length, c.length);
    }
  }

  return void 0;
}
/**
 * Unsets a cookie.
 *
 * @param {string} cname The cookie name.
 * @return {undefined}
 */

function unsetCookie(cname) {
  setCookie(cname, "", -365);
}
/**
 * Tests whether a DOM element is in viewport or not.
 *
 * @param {Element} elem The DOM element.
 * @return {boolean} True if it is in viewport, false otherwise.
 */

function isInViewport(elem) {
  const bounding = elem.getBoundingClientRect();
  return bounding.top >= 0 && bounding.left >= 0 && bounding.bottom <= (window.innerHeight || document.documentElement.clientHeight) && bounding.right <= (window.innerWidth || document.documentElement.clientWidth);
}
/**
 * Tests if a DOM element is scrolled into the scrollable view of its parent scrollable container.
 *
 * @param {Element} elem The DOM element to test.
 * @param {Element} holder The DOM element of the scrollable container of the DOM element to test.
 * @param {number|Function} minPx Minimum number of pixels of the element's height which must be scrolled into the view
 *                                in order to consider the element to be scrolled into view.
 *                                If a function is given, it will receive the element's bounding client rect as the first argument
 *                                as well as the holder element's bounding client rect as the second argument and must return
 *                                the minimum number of pixels.
 * @return {boolean} True if the given element is scrolled into view, false otherwise.
 */

function isScrolledIntoView(el, holder, minPx = 1) {
  const elRect = el.getBoundingClientRect();
  const holderRect = holder.getBoundingClientRect();
  const {
    top,
    bottom,
    height
  } = elRect;

  const minPxNumberFn = () => typeof minPx === "function" ? minPx(elRect, holderRect) : minPx;

  let bottomDiff;
  return top <= holderRect.top ? holderRect.top - top + minPxNumberFn() <= height : (bottomDiff = bottom - holderRect.bottom) < 0 ? true : holderRect.bottom >= top && minPx ? holderRect.bottom - top >= minPxNumberFn() : bottomDiff <= height;
}
/**
 * Tests if a DOM element has a vertical scrollbar.
 *
 * @param {Element} elem The DOM element.
 * @return {boolean} True it has a vertical scrollbar, false otherwise.
 */

function hasVerticalScrollbar(elem) {
  const hasVerticalScrollbar = elem.scrollHeight > elem.clientHeight;
  return hasVerticalScrollbar;
}
/**
 * Tests if a DOM element has a horizontal scrollbar.
 *
 * @param {Element} elem The DOM element.
 * @return {boolean} True it has a horizontal scrollbar, false otherwise.
 */

function hasHorizontalScrollbar(elem) {
  const hasHorizontalScrollbar = elem.scrollWidth > elem.clientWidth;
  return hasHorizontalScrollbar;
}
/**
 * Generates a unique ID which can be used for an element.
 *
 * @param {string|undefined} [elementUniqueIdPrefix] Local unique ID prefix which overrides the prefix
 *                                                   set on the "config" configuration object.
 * @return {string} The element unique ID.
 */

function elementUniqueId(elementUniqueIdPrefix = void 0) {
  const uniqueElementIdSuffix = (0,_core__WEBPACK_IMPORTED_MODULE_0__.uniqueId)();
  return (elementUniqueIdPrefix || _core__WEBPACK_IMPORTED_MODULE_0__.config.elementUniqueIdPrefix) + uniqueElementIdSuffix;
}
/**
 * Gets the computed style of an element.
 *
 * @param {Element} element DOM element.
 * @return {CSSStyleDeclaration} The computed style.
 */

function getElementComputedStyle(element) {
  return window.getComputedStyle(element);
}
/**
 * Gets element's inner dimensions (height and width without padding).
 *
 * @param {Element} element An element.
 * @return {Object} An object with "width" and "height" properties.
 */

function elementInnerDimensions(element) {
  const computedStyle = getElementComputedStyle(element);
  let elementHeight = element.clientHeight; // Height with padding.

  let elementWidth = element.clientWidth; // Width with padding

  elementHeight -= parseFloat(computedStyle.paddingTop) + parseFloat(computedStyle.paddingBottom);
  elementWidth -= parseFloat(computedStyle.paddingLeft) + parseFloat(computedStyle.paddingRight);
  return {
    width: elementWidth,
    height: elementHeight
  };
}
/**
 * @type {HTMLTextAreaElement}
 */

let countTextareaLinesBuffer;
/**
 * Returns the number of lines in a textarea, including wrapped lines.
 *
 * @see https://stackoverflow.com/questions/28905965/textarea-how-to-count-wrapped-lines-rows#answer-45252226
 *
 * @param {HTMLTextAreaElement} textarea A textarea element. Note that the textarea should have an integer line height
 *                                       to avoid rounding errors.
 */

function countTextareaLines(textarea) {
  if (countTextareaLinesBuffer == null) {
    countTextareaLinesBuffer = document.createElement("textarea");
    countTextareaLinesBuffer.style.border = "none";
    countTextareaLinesBuffer.style.height = "0";
    countTextareaLinesBuffer.style.overflow = "hidden";
    countTextareaLinesBuffer.style.padding = "0";
    countTextareaLinesBuffer.style.position = "absolute";
    countTextareaLinesBuffer.style.left = "0";
    countTextareaLinesBuffer.style.top = "0";
    countTextareaLinesBuffer.style.zIndex = "-1";
    document.body.appendChild(countTextareaLinesBuffer);
  }

  const cs = window.getComputedStyle(textarea);
  const pl = parseInt(cs.paddingLeft);
  const pr = parseInt(cs.paddingRight);
  let lh = parseInt(cs.lineHeight); // `cs.lineHeight` may return 'normal', which means line height = font size.

  if (isNaN(lh)) lh = parseInt(cs.fontSize); // Copy content width.

  countTextareaLinesBuffer.style.width = textarea.clientWidth - pl - pr + "px"; // Copy text properties.

  countTextareaLinesBuffer.style.font = cs.font;
  countTextareaLinesBuffer.style.letterSpacing = cs.letterSpacing;
  countTextareaLinesBuffer.style.whiteSpace = cs.whiteSpace;
  countTextareaLinesBuffer.style.wordBreak = cs.wordBreak;
  countTextareaLinesBuffer.style.wordSpacing = cs.wordSpacing;
  countTextareaLinesBuffer.style.wordWrap = cs.wordWrap; // Copy value.

  countTextareaLinesBuffer.value = textarea.value;
  let result = Math.floor(countTextareaLinesBuffer.scrollHeight / lh);

  if (result == 0) {
    result = 1;
  }

  return result;
}
/**
 * Checks if the scroll of an element is on the bottom.
 *
 * @param {Element} DOMNode Element.
 * @return {boolean} True if the scroll is on the bottom, false otherwise.
 */

function isScrollOnBottom(DOMNode) {
  const ret = DOMNode.scrollTop + DOMNode.offsetHeight >= DOMNode.scrollHeight;
  return ret;
}
/**
 * Returns the default browser's vertical scrollbar width.
 *
 * @return {Number} The scrollbar width.
 */

function getVerticalScrollBarWidth() {
  const scrollDiv = document.createElement("div");
  scrollDiv.className = "vertical-scrollbar-measure";
  const sheet = document.createElement("style");
  sheet.innerHTML = "div.vertical-scrollbar-measure { width: 100px; height: 100px; overflow: scroll; position: absolute; top: -9999px; }";
  document.body.appendChild(sheet);
  document.body.appendChild(scrollDiv);
  const scrollbarWidth = scrollDiv.offsetWidth - scrollDiv.clientWidth;
  document.body.removeChild(scrollDiv);
  const sheetParent = sheet.parentNode;
  sheetParent.removeChild(sheet);
  return scrollbarWidth;
}
/**
 * Tests if an element with "text-overflow: ellipsis;" has the ellipsis active
 * and therefore its text is truncated.
 *
 * @param {Element} e The DOM element.
 * @return {boolean} True if ellipsis are present, false otherwise.
 */

function isEllipsisActive(e) {
  return e.offsetWidth < e.scrollWidth;
}
/**
 * A fallback function to copy a text to clipboard.
 *
 * @param {string} text The text to copy.
 * @param {Function} [onSuccess] An optional callback to execute on success.
 * @param {Function} [onFailure] An optional callback to execute on failure.
 * @return {undefined}
 */

function fallbackCopyTextToClipboard(text, onSuccess, onFailure) {
  const bodyScrollTop = document.documentElement.scrollTop || document.body.scrollTop;
  const textArea = document.createElement("textarea");
  textArea.value = text;
  document.body.appendChild(textArea);
  textArea.focus();
  textArea.select();

  try {
    const successful = document.execCommand("copy");

    if (successful) {
      onSuccess && onSuccess();
    } else {
      onFailure && onFailure();
    }
  } catch (err) {
    onFailure && onFailure(err);
  }

  document.body.removeChild(textArea) && document.documentElement.scrollTop && (document.documentElement.scrollTop = bodyScrollTop) || (document.body.scrollTop = bodyScrollTop);
}
/**
 * Copies a text to clipboard.
 *
 * @param {string} text The text to copy.
 * @param {Function} [onSuccess] An optional callback to execute on success.
 * @param {Function} [onFailure] An optional callback to execute on failure.
 * @return {undefined}
 */


function copyTextToClipboard(text, onSuccess, onFailure) {
  if (!navigator.clipboard) {
    fallbackCopyTextToClipboard(text, onSuccess, onFailure);
    return;
  }

  navigator.clipboard.writeText(text).then(function () {
    onSuccess && onSuccess();
  }, function (err) {
    onFailure && onFailure(err);
  });
}
/**
 * Asynchronously invokes a callback multiple times, each in its own animation frame.
 *
 * @param {number} n The number of times the callback should be invoked.
 * @param {Function} callback The callback to invoke.
 * @return {*} The identifier of the first animation frame or "n" if it is falsy during the outermost call.
 */

const rAFLooper = (n, callback) => n && window.requestAnimationFrame(() => (callback(), rAFLooper(--n, callback)));
/**
 * Requests a predefined number of animation frames and executes a callback after.
 *
 * @param {number} count The number of animation frames to request before executing the callback.
 * @param {Function} callback The callback to execute after "count" animation frames have been requested.
 * @return {number} The identifier of the first animation frame.
 */

const nestedRAF = (count, callback) => {
  let c = count || 1;

  const innerCallback = () => {
    c--;

    if (!c) {
      callback();
      return;
    }

    window.requestAnimationFrame(innerCallback);
  };

  return window.requestAnimationFrame(innerCallback);
};
/**
 * Returns the raw contents of the URI fragment (i.e. everything after the hash ("#") character).
 *
 * @param {string} URIFragment The URI fragment.
 * @return {string} The raw contents of the URI fragment.
 */

function getRawURIFragment(URIFragment) {
  const fragment = (URIFragment || window.location.hash).replace(/^#/, "");
  return fragment;
}
/**
 * Returns the decoded contents of a URI fragment (i.e. everything after the hash ("#") character).
 *
 * @param {string} URIFragment The URI fragment.
 * @return {string} The contents of the URI fragment, decoded.
 */

function getDecodedURIFragment(URIFragment) {
  const fragment = decodeURIComponent(getRawURIFragment(URIFragment));
  return fragment;
}
/**
 * Appends an encoded JSON fragment to a URI.
 *
 * @param {string} URI The URI.
 * @param {*} data Data to encode in JSON format.
 */

function appendEncodedJSONFragmentToURI(URI, data) {
  return URI + "#" + encodeURIComponent(JSON.stringify(data));
}
/**
 * Returnes the decoded JSON data eventually stored in the URI fragment.
 *
 * @param {*} defaultData Default data to return if either the URI fragment is missing or
 *                        the content of the URI fragment is not a valid JSON-encoded string.
 * @return {*} The decoded JSON data or "defaultData".
 */

function getDecodedJSONFromFragmentURI(defaultData = null) {
  const fragment = window.location.hash;

  if (!(0,_core__WEBPACK_IMPORTED_MODULE_0__.isEmpty)(fragment)) {
    const decodedFragment = getDecodedURIFragment(fragment);

    if ((0,_core__WEBPACK_IMPORTED_MODULE_0__.isJSONString)(decodedFragment)) {
      return JSON.parse(decodedFragment);
    }
  }

  return defaultData;
}
/**
 * Parses a multidimensional query string and returns an object with the parsed args.
 *
 * @see https://stackoverflow.com/questions/8648892/convert-url-parameters-to-a-javascript-object#answer-44713056
 *
 * @param {string} str The query string.
 * @param {Object} [array] The base object to use (or a new object if omitted or falsy).
 * @return {Object} The object with the parsed data.
 */

function parseQueryStringArgsMultiDim(str, array) {
  if (!str) {
    str = window.location.search.substr(1);
  }

  let i, j, ct, p, lastObj, obj, undef, chr, tmp, key, value, postLeftBracketPos, keys, keysLen;

  const strArr = String(str).replace(/^&/, "").replace(/&$/, "").split("&"),
        sal = strArr.length,
        fixStr = function (str) {
    return decodeURIComponent(str.replace(/\+/g, "%20"));
  };

  if (!array) {
    array = {};
  }

  for (i = 0; i < sal; i++) {
    tmp = strArr[i].split("=");
    key = fixStr(tmp[0]);
    value = tmp.length < 2 ? "" : fixStr(tmp[1]);

    while (key.charAt(0) === " ") {
      key = key.slice(1);
    }

    if (key.indexOf("\x00") > -1) {
      key = key.slice(0, key.indexOf("\x00"));
    }

    if (key && key.charAt(0) !== "[") {
      keys = [];
      postLeftBracketPos = 0;

      for (j = 0; j < key.length; j++) {
        if (key.charAt(j) === "[" && !postLeftBracketPos) {
          postLeftBracketPos = j + 1;
        } else if (key.charAt(j) === "]") {
          if (postLeftBracketPos) {
            if (!keys.length) {
              keys.push(key.slice(0, postLeftBracketPos - 1));
            }

            keys.push(key.substr(postLeftBracketPos, j - postLeftBracketPos));
            postLeftBracketPos = 0;

            if (key.charAt(j + 1) !== "[") {
              break;
            }
          }
        }
      }

      if (!keys.length) {
        keys = [key];
      }

      for (j = 0; j < keys[0].length; j++) {
        chr = keys[0].charAt(j);

        if (chr === " " || chr === "." || chr === "[") {
          keys[0] = keys[0].substr(0, j) + "_" + keys[0].substr(j + 1);
        }

        if (chr === "[") {
          break;
        }
      }

      obj = array;

      for (j = 0, keysLen = keys.length; j < keysLen; j++) {
        key = keys[j].replace(/^['"]/, "").replace(/['"]$/, "");
        lastObj = obj;

        if (key !== "" && key !== " " || j === 0) {
          if (obj[key] === undef) {
            obj[key] = {};
          }

          obj = obj[key];
        } else {
          // To insert new dimension
          ct = -1;

          for (p in obj) {
            if (Object.prototype.hasOwnProperty.call(obj, p)) {
              if (+p > ct && p.match(/^\d+$/g)) {
                ct = +p;
              }
            }
          }

          key = ct + 1;
        }
      }

      lastObj[key] = value;
    }
  }
  /* ===== */

  /*
   * This code converts the object properties to arrays if they are objects with consecutive integer keys
   * from 0 to n, where n is the number of properties of that object minus one
   * (i.e. it converts meaningful objects which are to be interpreted as arrays to arrays).
   */


  const fnNormalizeObjToArrayIfPropsAreConsecutiveIntsFrom0 = function (obj) {
    const keys = Object.keys(obj);
    const truthMap = {};

    for (let i = 0; i < keys.length; i++) {
      truthMap[i] = true;
    }

    const array = [];

    for (let i = 0; i < keys.length; i++) {
      const prop = keys[i] + "";

      if (!prop.match(/^[0-9]+$/)) {
        return obj;
      }

      const intProp = Number(prop);

      if (truthMap[intProp]) {
        array[intProp] = obj[prop];
        delete truthMap[intProp];
      } else {
        return obj;
      }
    }

    if (Object.keys(truthMap).length === 0) {
      return array;
    } else {
      return obj;
    }
  };

  const fnNormalizeToArrayIfNeeded = function fnNormalizeToArrayIfNeeded(obj) {
    for (const prop in obj) {
      if ((0,_core__WEBPACK_IMPORTED_MODULE_0__.isPlainObject)(obj[prop])) {
        obj[prop] = fnNormalizeObjToArrayIfPropsAreConsecutiveIntsFrom0(obj[prop]);
        fnNormalizeToArrayIfNeeded(obj[prop]);
      }
    }
  };
  /* /===== */


  fnNormalizeToArrayIfNeeded(array);
  return array;
}
/**
 * Gets the query string arguments of the current location in a multidimensional fashion
 * (multidimension-aware).
 *
 * @return {Object} An object representing the query string arguments.
 */

function getQueryStringArgsMultiDim() {
  const obj = {};
  parseQueryStringArgsMultiDim(location.search.substring(1), obj);
  return obj;
}
/**
 * Focuses an input without scrolling.
 *
 * @see https://stackoverflow.com/questions/4963053/focus-to-input-without-scrolling
 *
 * @param {Element} elem The DOM element.
 */

const cursorFocus = function (elem) {
  let x, y; // More sources for scroll x, y offset.

  if (typeof window.pageXOffset !== "undefined") {
    x = window.pageXOffset;
    y = window.pageYOffset;
  } else if (typeof window.scrollX !== "undefined") {
    x = window.scrollX;
    y = window.scrollY;
  } else if (document.documentElement && typeof document.documentElement.scrollLeft !== "undefined") {
    x = document.documentElement.scrollLeft;
    y = document.documentElement.scrollTop;
  } else {
    x = document.body.scrollLeft;
    y = document.body.scrollTop;
  }

  elem.focus();

  if (typeof x !== "undefined") {
    window.scrollTo(x, y);
  }
};
/**
 * Detects wrapped elements.
 *
 * @param {string|Element[]} classNameOrElements A class name (with or without the leading dot) or the DOM elements to check.
 * @return {Element[]} The wrapped DOM elements.
 */

function detectWrapped(classNameOrElements) {
  let elements;

  if (typeof classNameOrElements === "string") {
    classNameOrElements = classNameOrElements.replace(/^\./, "");
    elements = document.getElementsByClassName(classNameOrElements);
  } else {
    elements = classNameOrElements;
  }

  const wrapped = [];
  let prev = {};
  let curr = {};

  for (let i = 0; i < elements.length; i++) {
    const element = elements[i];
    curr = element.getBoundingClientRect();

    if (prev && prev.top < curr.top) {
      wrapped.push(element);
    }

    prev = i === 0 ? curr : prev;
  }

  return wrapped;
}
/**
 * Gets the maximum nesting level of an element (or of the whole DOM if "document.body" is given as parameter).abs
 *
 * @param {Element} elem The DOM element from which to start identifying the maximum nesting level.
 * @return {number} The maximum nesting level, starting from 0 if the given element has no children.
 */

function maxNestingLevel(el) {
  if (!el.children) {
    return 0;
  }

  let max = -1;

  for (let i = 0; i < el.children.length; i++) {
    const nestingLevel = maxNestingLevel(el.children[i]);

    if (nestingLevel > max) {
      max = nestingLevel;
    }
  }

  return max + 1;
}
/**
 * @type {RegExp}
 */

const REGEXP_SCROLL_PARENT = /^(visible|hidden)/;
/**
 * Get the first scrollable ancestor of an element.
 *
 * @param {Element} el The element to use as the base from which to determine its first scrollable ancestor.
 * @return {Element} The first scrollable ancestor element scroll, or the document body.
 */

const getScrollableAncestor = el => !(el instanceof HTMLElement) || typeof window.getComputedStyle !== "function" ? null : el.scrollHeight >= el.clientHeight && !REGEXP_SCROLL_PARENT.test(window.getComputedStyle(el).overflowY || "visible") ? el : getScrollableAncestor(el.parentElement) || document.scrollingElement || document.body;
/**
 * Smoothly scrolls to the top of a scrollable element or the browser's window.
 *
 * @param {Element} [el] The element. Defaults to "window".
 * @return {undefined}
 */

const smoothScrollToTop = (el = window) => el.scroll({
  top: 0,
  behavior: "smooth"
});
//# sourceMappingURL=web.js.map

/***/ }),

/***/ "./node_modules/pigretto/dist/es6/advices/Advice.js":
/*!**********************************************************!*\
  !*** ./node_modules/pigretto/dist/es6/advices/Advice.js ***!
  \**********************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ Advice)
/* harmony export */ });
/*
 * Copyright (c) 2020 Anton Bagdatyev (Tonix)
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */
class Advice {
  constructor(fn) {
    this.fn = fn;
  }

}
//# sourceMappingURL=Advice.js.map

/***/ }),

/***/ "./node_modules/pigretto/dist/es6/advices/AfterAdvice.js":
/*!***************************************************************!*\
  !*** ./node_modules/pigretto/dist/es6/advices/AfterAdvice.js ***!
  \***************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ AfterAdvice)
/* harmony export */ });
/* harmony import */ var _Advice__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./Advice */ "./node_modules/pigretto/dist/es6/advices/Advice.js");
/*
 * Copyright (c) 2020 Anton Bagdatyev (Tonix)
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */

class AfterAdvice extends _Advice__WEBPACK_IMPORTED_MODULE_0__["default"] {}
//# sourceMappingURL=AfterAdvice.js.map

/***/ }),

/***/ "./node_modules/pigretto/dist/es6/advices/AroundAdvice.js":
/*!****************************************************************!*\
  !*** ./node_modules/pigretto/dist/es6/advices/AroundAdvice.js ***!
  \****************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ AroundAdvice)
/* harmony export */ });
/* harmony import */ var _Advice__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./Advice */ "./node_modules/pigretto/dist/es6/advices/Advice.js");
/*
 * Copyright (c) 2020 Anton Bagdatyev (Tonix)
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */

class AroundAdvice extends _Advice__WEBPACK_IMPORTED_MODULE_0__["default"] {}
//# sourceMappingURL=AroundAdvice.js.map

/***/ }),

/***/ "./node_modules/pigretto/dist/es6/advices/BeforeAdvice.js":
/*!****************************************************************!*\
  !*** ./node_modules/pigretto/dist/es6/advices/BeforeAdvice.js ***!
  \****************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ BeforeAdvice)
/* harmony export */ });
/* harmony import */ var _Advice__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./Advice */ "./node_modules/pigretto/dist/es6/advices/Advice.js");
/*
 * Copyright (c) 2020 Anton Bagdatyev (Tonix)
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */

class BeforeAdvice extends _Advice__WEBPACK_IMPORTED_MODULE_0__["default"] {}
//# sourceMappingURL=BeforeAdvice.js.map

/***/ }),

/***/ "./node_modules/pigretto/dist/es6/builtin/lazyObject.js":
/*!**************************************************************!*\
  !*** ./node_modules/pigretto/dist/es6/builtin/lazyObject.js ***!
  \**************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ lazyObject)
/* harmony export */ });
/* harmony import */ var _pigretto__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ../pigretto */ "./node_modules/pigretto/dist/es6/pigretto.js");
/* harmony import */ var _pointcuts_shorthands_call__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ../pointcuts/shorthands/call */ "./node_modules/pigretto/dist/es6/pointcuts/shorthands/call.js");
/* harmony import */ var _pointcuts_shorthands_set__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ../pointcuts/shorthands/set */ "./node_modules/pigretto/dist/es6/pointcuts/shorthands/set.js");
/* harmony import */ var _pointcuts_shorthands_get__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ../pointcuts/shorthands/get */ "./node_modules/pigretto/dist/es6/pointcuts/shorthands/get.js");
/* harmony import */ var escape_string_regexp__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! escape-string-regexp */ "./node_modules/pigretto/node_modules/escape-string-regexp/index.js");
/* harmony import */ var escape_string_regexp__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(escape_string_regexp__WEBPACK_IMPORTED_MODULE_0__);
/*
 * Copyright (c) 2020 Anton Bagdatyev (Tonix)
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */





/**
 * Builtin function using pigretto's API to implement lazy loading for objects.
 *
 * @param {Object} target A target object to lazy load. Lazy loading in this case means deferring an operation on the object
 *                        when it is used for the very first time by the client code.
 * @param {Object} options The options of the lazy loading.
 * @param {Function} onceCallback The callback to execute only once before performing the very first operation on the given "target"
 *                                when the given "target" is used for the very first time.
 *                                This callback is mandatory (otherwise it doesn't make sense to use "lazy()" at all)
 *                                and allows e.g. to perform a heavy initialization operation lazily on the underlying target object.
 * @param {Object} [options.ignoredTypes] The types which prototypes to ignore when going up the prototype chain in order to identify
 *                                        which prototypes to intercept when lazy loading.
 *                                        By default, only the properties set on the "Object" prototype will be ignored.
 *                                        This option can be used e.g. to avoid the performance of a heavy lazy initialization
 *                                        when calling the methods of a parent class of the given "target" object.
 * @param {boolean} [options.interceptConstructor] Whether or not to intercept the getting/setting of the "constructor" property.
 *                                                 Defaults to "false", meaning that when getting/setting "target.constructor",
 *                                                 this operation will not trigger the underlying lazy loading.
 * @param {string[]} [options.propertiesToIgnore] Property names or method names to ignore for lazy loading.
 * @param {Array} [options.pointcuts] Array of pointcut shorthands to use for interception and lazy loading.
 *                                    By default, only "call", "get" and "set" pointcuts will be used.
 *                                    Other pointcut shorthands (e.g. "apply", "construct") will be ignored, if passed.
 * @return {Proxy} A new proxy object for the given target which supports this builtin feature.
 */

function lazyObject(target, {
  onceCallback,
  ignoredTypes = [Object],
  interceptConstructor = false,
  propertiesToIgnore = [],
  pointcuts = [_pointcuts_shorthands_call__WEBPACK_IMPORTED_MODULE_1__["default"], _pointcuts_shorthands_get__WEBPACK_IMPORTED_MODULE_2__["default"], _pointcuts_shorthands_set__WEBPACK_IMPORTED_MODULE_3__["default"]]
} = {}) {
  let hasCall = false,
      hasGet = false,
      hasSet = false;
  const rules = [];
  const propertiesMap = {};
  const constructorsToIgnore = ignoredTypes.map(Type => Type.prototype.constructor);
  let current = target;
  let currentIsProto = false;

  while (current) {
    const constructor = current.constructor;

    if (!currentIsProto || !constructorsToIgnore.includes(constructor)) {
      const properties = Reflect.ownKeys(current);
      properties.map(property => propertiesMap[property] = {
        isMethod: property !== "constructor" && typeof target[property] === "function"
      });
    }

    current = Object.getPrototypeOf(current);
    currentIsProto = true;
  }

  propertiesToIgnore.map(propertyToIgnore => delete propertiesMap[propertyToIgnore]);
  !interceptConstructor && delete propertiesMap["constructor"];
  pointcuts.map(pointcut => {
    if (pointcut === _pointcuts_shorthands_call__WEBPACK_IMPORTED_MODULE_1__["default"]) {
      hasCall = true;
    } else if (pointcut === _pointcuts_shorthands_get__WEBPACK_IMPORTED_MODULE_2__["default"]) {
      hasGet = true;
    } else if (pointcut === _pointcuts_shorthands_set__WEBPACK_IMPORTED_MODULE_3__["default"]) {
      hasSet = true;
    }
  });
  const properties = Object.keys(propertiesMap);
  let firstOperation = true;

  const callback = function (...args) {
    if (firstOperation) {
      firstOperation = false;
      onceCallback.apply(this, args);
    }
  };

  const escape = names => names.map((escape_string_regexp__WEBPACK_IMPORTED_MODULE_0___default()));

  const regex = escapedNames => new RegExp(`^(${escapedNames.join("|")})$`);

  const addRule = regex => pointcut => rules.push([regex, pointcut().before(callback)]);

  if (hasCall) {
    const methodNames = properties.filter(property => propertiesMap[property].isMethod);
    const escapedMethodNames = escape(methodNames);
    addRule(regex(escapedMethodNames))(_pointcuts_shorthands_call__WEBPACK_IMPORTED_MODULE_1__["default"]);
  }

  if (hasGet || hasSet) {
    const propertyNames = properties.filter(property => !propertiesMap[property].isMethod);
    const escapedPropertyNames = escape(propertyNames);
    hasGet && addRule(regex(escapedPropertyNames))(_pointcuts_shorthands_get__WEBPACK_IMPORTED_MODULE_2__["default"]);
    hasSet && addRule(regex(escapedPropertyNames))(_pointcuts_shorthands_set__WEBPACK_IMPORTED_MODULE_3__["default"]);
  }

  const pigrettarget = (0,_pigretto__WEBPACK_IMPORTED_MODULE_4__["default"])(target, rules);
  return pigrettarget;
}
//# sourceMappingURL=lazyObject.js.map

/***/ }),

/***/ "./node_modules/pigretto/dist/es6/factory/handlerFactory.js":
/*!******************************************************************!*\
  !*** ./node_modules/pigretto/dist/es6/factory/handlerFactory.js ***!
  \******************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ handlerFactory)
/* harmony export */ });
/* harmony import */ var _trap_trapHandlerForRules__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ../trap/trapHandlerForRules */ "./node_modules/pigretto/dist/es6/trap/trapHandlerForRules.js");
/*
 * Copyright (c) 2020 Anton Bagdatyev (Tonix)
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */

/**
 * Factory function to generate a new handler object for the given proxy rules.
 *
 * @param {Object|Array} proxyRules The proxy rules.
 * @return {Object} The handler object for the given proxy rules.
 */

function handlerFactory(proxyRules) {
  const handler = (0,_trap_trapHandlerForRules__WEBPACK_IMPORTED_MODULE_0__["default"])(proxyRules);
  return handler;
}
//# sourceMappingURL=handlerFactory.js.map

/***/ }),

/***/ "./node_modules/pigretto/dist/es6/pigretto.js":
/*!****************************************************!*\
  !*** ./node_modules/pigretto/dist/es6/pigretto.js ***!
  \****************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ pigretto)
/* harmony export */ });
/* harmony import */ var _factory_handlerFactory__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./factory/handlerFactory */ "./node_modules/pigretto/dist/es6/factory/handlerFactory.js");
/*
 * Copyright (c) 2020 Anton Bagdatyev (Tonix)
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */

/**
 * Generates a new pigretto proxy object.
 *
 * A pigretto proxy object lets proxying and advising the following operations:
 *
 *    - Calling a method (`call()` method);
 *    - Getting a property (`get()` method);
 *    - Setting a property (`set()` method);
 *    - Calling a function (`[applyRule]` symbol property with `apply()` method);
 *    - Constructing a new object via the "new" keyword (`[constructRule]` symbol property with `construct()`).
 *
 * @param {*} target A function, a constructor function or an object to proxy.
 * @param {Object|Array} proxyRules The proxy rules.
 *                                  This parameter can be:
 *
 *                                      - An object containing proxy rules as keys and pointcuts with advices as values;
 *
 *                                      - An array of rules, each rule either being an object with proxy rules as keys
 *                                        and advices as values or a tuple of two elements: an array of rules of the same
 *                                        type or a single rule as the first element and a pointcut with advices for those rules or
 *                                        that rule as the second element;
 *
 * @return {Proxy} A new proxy object for the given target.
 */

function pigretto(target, proxyRules) {
  const handler = (0,_factory_handlerFactory__WEBPACK_IMPORTED_MODULE_0__["default"])(proxyRules);
  const proxy = new Proxy(target, handler);
  return proxy;
}
//# sourceMappingURL=pigretto.js.map

/***/ }),

/***/ "./node_modules/pigretto/dist/es6/pointcuts/FunctionCallPointcut.js":
/*!**************************************************************************!*\
  !*** ./node_modules/pigretto/dist/es6/pointcuts/FunctionCallPointcut.js ***!
  \**************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ FunctionCallPointcut)
/* harmony export */ });
/* harmony import */ var _Pointcut__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./Pointcut */ "./node_modules/pigretto/dist/es6/pointcuts/Pointcut.js");
/* harmony import */ var _advices_BeforeAdvice__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ../advices/BeforeAdvice */ "./node_modules/pigretto/dist/es6/advices/BeforeAdvice.js");
/* harmony import */ var _advices_AfterAdvice__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ../advices/AfterAdvice */ "./node_modules/pigretto/dist/es6/advices/AfterAdvice.js");
/* harmony import */ var _advices_AroundAdvice__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ../advices/AroundAdvice */ "./node_modules/pigretto/dist/es6/advices/AroundAdvice.js");
/*
 * Copyright (c) 2020 Anton Bagdatyev (Tonix)
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */




class FunctionCallPointcut extends _Pointcut__WEBPACK_IMPORTED_MODULE_0__["default"] {
  /**
   * Register a before advice for this pointcut.
   *
   * @param {Function} adviceFn The advice function. A function which receives the parameters
   *                            of the underlying proxied function before calling it.
   * @return {FunctionCallPointcut} This pointcut (fluent interface).
   */
  before(adviceFn) {
    this.advices.push(new _advices_BeforeAdvice__WEBPACK_IMPORTED_MODULE_1__["default"](adviceFn));
    return this;
  }
  /**
   * Register an after advice for this pointcut.
   *
   * @param {Function} adviceFn The advice function. A higher-order function which will receive
   *                            the parameters of the proxied function and MUST return a function
   *                            which will receive the return value of the underlying proxied function
   *                            as parameter.
   * @return {FunctionCallPointcut} This pointcut (fluent interface).
   */


  after(adviceFn) {
    this.advices.push(new _advices_AfterAdvice__WEBPACK_IMPORTED_MODULE_2__["default"](adviceFn));
    return this;
  }
  /**
   * Register an around advice for this pointcut.
   *
   * @param {Function} adviceFn The advice function. A higher-order function which will receive the "proceed"
   *                            function as parameter and which MUST return a function which will receive the parameters
   *                            of the underlying proxied function.
   *                            If the advice function proceeds, the callback given to "proceed" will receive
   *                            the return value to return to the caller or may return another value
   *                            instead of the current return value.
   * @return {FunctionCallPointcut} This pointcut (fluent interface).
   */


  around(adviceFn) {
    this.advices.push(new _advices_AroundAdvice__WEBPACK_IMPORTED_MODULE_3__["default"](adviceFn));
    return this;
  }

}
//# sourceMappingURL=FunctionCallPointcut.js.map

/***/ }),

/***/ "./node_modules/pigretto/dist/es6/pointcuts/MethodCallPointcut.js":
/*!************************************************************************!*\
  !*** ./node_modules/pigretto/dist/es6/pointcuts/MethodCallPointcut.js ***!
  \************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ MethodCallPointcut)
/* harmony export */ });
/* harmony import */ var _Pointcut__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./Pointcut */ "./node_modules/pigretto/dist/es6/pointcuts/Pointcut.js");
/* harmony import */ var _advices_BeforeAdvice__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ../advices/BeforeAdvice */ "./node_modules/pigretto/dist/es6/advices/BeforeAdvice.js");
/* harmony import */ var _advices_AfterAdvice__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ../advices/AfterAdvice */ "./node_modules/pigretto/dist/es6/advices/AfterAdvice.js");
/* harmony import */ var _advices_AroundAdvice__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ../advices/AroundAdvice */ "./node_modules/pigretto/dist/es6/advices/AroundAdvice.js");
/*
 * Copyright (c) 2020 Anton Bagdatyev (Tonix)
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */




class MethodCallPointcut extends _Pointcut__WEBPACK_IMPORTED_MODULE_0__["default"] {
  /**
   * Register a before advice for this pointcut.
   *
   * @param {Function} adviceFn The advice function. A function which receives the parameters
   *                            of the underlying proxied method call.
   * @return {MethodCallPointcut} This pointcut (fluent interface).
   */
  before(adviceFn) {
    this.advices.push(new _advices_BeforeAdvice__WEBPACK_IMPORTED_MODULE_1__["default"](adviceFn));
    return this;
  }
  /**
   * Register an after advice for this pointcut.
   *
   * @param {Function} adviceFn The advice function. A higher-order function which will receive
   *                            the parameters of the proxied method and MUST return a function
   *                            which will receive the return value of the underlying proxied function
   *                            as parameter.
   * @return {MethodCallPointcut} This pointcut (fluent interface).
   */


  after(adviceFn) {
    this.advices.push(new _advices_AfterAdvice__WEBPACK_IMPORTED_MODULE_2__["default"](adviceFn));
    return this;
  }
  /**
   * Register an around advice for this pointcut.
   *
   * @param {Function} adviceFn The advice function. A higher-order function which will receive the "proceed"
   *                            function as parameter and which MUST return a function which will receive the parameters
   *                            of the underlying proxied method.
   *                            If the advice function proceeds by calling the "proceed" function, the callback given to "proceed" will receive
   *                            the return value to return to the caller or may return another value
   *                            instead of the current return value.
   * @return {MethodCallPointcut} This pointcut (fluent interface).
   */


  around(adviceFn) {
    this.advices.push(new _advices_AroundAdvice__WEBPACK_IMPORTED_MODULE_3__["default"](adviceFn));
    return this;
  }

}
//# sourceMappingURL=MethodCallPointcut.js.map

/***/ }),

/***/ "./node_modules/pigretto/dist/es6/pointcuts/ObjectConstructionPointcut.js":
/*!********************************************************************************!*\
  !*** ./node_modules/pigretto/dist/es6/pointcuts/ObjectConstructionPointcut.js ***!
  \********************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ ObjectConstructionPointcut)
/* harmony export */ });
/* harmony import */ var _Pointcut__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./Pointcut */ "./node_modules/pigretto/dist/es6/pointcuts/Pointcut.js");
/* harmony import */ var _advices_BeforeAdvice__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ../advices/BeforeAdvice */ "./node_modules/pigretto/dist/es6/advices/BeforeAdvice.js");
/* harmony import */ var _advices_AfterAdvice__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ../advices/AfterAdvice */ "./node_modules/pigretto/dist/es6/advices/AfterAdvice.js");
/* harmony import */ var _advices_AroundAdvice__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ../advices/AroundAdvice */ "./node_modules/pigretto/dist/es6/advices/AroundAdvice.js");
/*
 * Copyright (c) 2020 Anton Bagdatyev (Tonix)
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */




class ObjectConstructionPointcut extends _Pointcut__WEBPACK_IMPORTED_MODULE_0__["default"] {
  /**
   * Register a before advice for this pointcut.
   *
   * @param {Function} adviceFn The advice function. A function which receives the constructor parameters
   *                            of the underlying proxied object construction.
   * @return {ObjectConstructionPointcut} This pointcut (fluent interface).
   */
  before(adviceFn) {
    this.advices.push(new _advices_BeforeAdvice__WEBPACK_IMPORTED_MODULE_1__["default"](adviceFn));
    return this;
  }
  /**
   * Register an after advice for this pointcut.
   *
   * @param {Function} adviceFn The advice function. A higher-order function which will receive
   *                            the parameters of the proxied object construction and MUST return a function
   *                            which will receive the instance object created by the underlying proxied
   *                            object construction.
   * @return {ObjectConstructionPointcut} This pointcut (fluent interface).
   */


  after(adviceFn) {
    this.advices.push(new _advices_AfterAdvice__WEBPACK_IMPORTED_MODULE_2__["default"](adviceFn));
    return this;
  }
  /**
   * Register an around advice for this pointcut.
   *
   * @param {Function} adviceFn The advice function. A higher-order function which will receive the "proceed"
   *                            function as parameter and which MUST return a function which will receive the constructor parameters
   *                            of the underlying object construction.
   *                            If the advice function proceeds, the callback given to "proceed" will receive
   *                            the instance object to return to the caller or may return another instance
   *                            instead of the current one.
   * @return {ObjectConstructionPointcut} This pointcut (fluent interface).
   */


  around(adviceFn) {
    this.advices.push(new _advices_AroundAdvice__WEBPACK_IMPORTED_MODULE_3__["default"](adviceFn));
    return this;
  }

}
//# sourceMappingURL=ObjectConstructionPointcut.js.map

/***/ }),

/***/ "./node_modules/pigretto/dist/es6/pointcuts/Pointcut.js":
/*!**************************************************************!*\
  !*** ./node_modules/pigretto/dist/es6/pointcuts/Pointcut.js ***!
  \**************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ Pointcut)
/* harmony export */ });
function _defineProperty(obj, key, value) { if (key in obj) { Object.defineProperty(obj, key, { value: value, enumerable: true, configurable: true, writable: true }); } else { obj[key] = value; } return obj; }

/*
 * Copyright (c) 2020 Anton Bagdatyev (Tonix)
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */
class Pointcut {
  constructor() {
    _defineProperty(this, "advices", []);
  }

}
//# sourceMappingURL=Pointcut.js.map

/***/ }),

/***/ "./node_modules/pigretto/dist/es6/pointcuts/PropertyGettingPointcut.js":
/*!*****************************************************************************!*\
  !*** ./node_modules/pigretto/dist/es6/pointcuts/PropertyGettingPointcut.js ***!
  \*****************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ PropertyGettingPointcut)
/* harmony export */ });
/* harmony import */ var _Pointcut__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./Pointcut */ "./node_modules/pigretto/dist/es6/pointcuts/Pointcut.js");
/* harmony import */ var _advices_BeforeAdvice__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ../advices/BeforeAdvice */ "./node_modules/pigretto/dist/es6/advices/BeforeAdvice.js");
/* harmony import */ var _advices_AfterAdvice__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ../advices/AfterAdvice */ "./node_modules/pigretto/dist/es6/advices/AfterAdvice.js");
/* harmony import */ var _advices_AroundAdvice__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ../advices/AroundAdvice */ "./node_modules/pigretto/dist/es6/advices/AroundAdvice.js");
/*
 * Copyright (c) 2020 Anton Bagdatyev (Tonix)
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */




class PropertyGettingPointcut extends _Pointcut__WEBPACK_IMPORTED_MODULE_0__["default"] {
  /**
   * Register a before advice for this pointcut.
   *
   * @param {Function} adviceFn The advice function. A function to execute before getting a property.
   * @return {PropertyGettingPointcut} This pointcut (fluent interface).
   */
  before(adviceFn) {
    this.advices.push(new _advices_BeforeAdvice__WEBPACK_IMPORTED_MODULE_1__["default"](adviceFn));
    return this;
  }
  /**
   * Register an after advice for this pointcut.
   *
   * @param {Function} adviceFn The advice function.
   *                            A function which will receive the current value of the property.
   * @return {PropertyGettingPointcut} This pointcut (fluent interface).
   */


  after(adviceFn) {
    this.advices.push(new _advices_AfterAdvice__WEBPACK_IMPORTED_MODULE_2__["default"](adviceFn));
    return this;
  }
  /**
   * Register an around advice for this pointcut.
   *
   * @param {Function} adviceFn The advice function. A higher-order function which will receive the "proceed"
   *                            function as parameter and which MUST return a function to execute before getting a property.
   *                            If the advice function proceeds, the callback given to "proceed" will receive
   *                            the value of the property to return to the caller or may return another value
   *                            to return to the caller instead of the one passed as argument.
   * @return {PropertyGettingPointcut} This pointcut (fluent interface).
   */


  around(adviceFn) {
    this.advices.push(new _advices_AroundAdvice__WEBPACK_IMPORTED_MODULE_3__["default"](adviceFn));
    return this;
  }

}
//# sourceMappingURL=PropertyGettingPointcut.js.map

/***/ }),

/***/ "./node_modules/pigretto/dist/es6/pointcuts/PropertySettingPointcut.js":
/*!*****************************************************************************!*\
  !*** ./node_modules/pigretto/dist/es6/pointcuts/PropertySettingPointcut.js ***!
  \*****************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ PropertySettingPointcut)
/* harmony export */ });
/* harmony import */ var _Pointcut__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./Pointcut */ "./node_modules/pigretto/dist/es6/pointcuts/Pointcut.js");
/* harmony import */ var _advices_BeforeAdvice__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ../advices/BeforeAdvice */ "./node_modules/pigretto/dist/es6/advices/BeforeAdvice.js");
/* harmony import */ var _advices_AfterAdvice__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ../advices/AfterAdvice */ "./node_modules/pigretto/dist/es6/advices/AfterAdvice.js");
/* harmony import */ var _advices_AroundAdvice__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ../advices/AroundAdvice */ "./node_modules/pigretto/dist/es6/advices/AroundAdvice.js");
/*
 * Copyright (c) 2020 Anton Bagdatyev (Tonix)
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */




class PropertySettingPointcut extends _Pointcut__WEBPACK_IMPORTED_MODULE_0__["default"] {
  /**
   * Register a before advice for this pointcut.
   *
   * @param {Function} adviceFn The advice function. A function to execute before setting a property
   *                            which will receive the previous property value.
   * @return {PropertySettingPointcut} This pointcut (fluent interface).
   */
  before(adviceFn) {
    this.advices.push(new _advices_BeforeAdvice__WEBPACK_IMPORTED_MODULE_1__["default"](adviceFn));
    return this;
  }
  /**
   * Register an after advice for this pointcut.
   *
   * @param {Function} adviceFn The advice function. A higher-order function which will receive
   *                            the previous value of the property and MUST return a new function
   *                            which will receive the new property value set on the object's property.
   * @return {PropertySettingPointcut} This pointcut (fluent interface).
   */


  after(adviceFn) {
    this.advices.push(new _advices_AfterAdvice__WEBPACK_IMPORTED_MODULE_2__["default"](adviceFn));
    return this;
  }
  /**
   * Register an around advice for this pointcut.
   *
   * @param {Function} adviceFn The advice function. A higher-order function which will receive the "proceed"
   *                            function as parameter and which MUST return a function which will receive the previous value of the property.
   *                            If the advice function proceeds, the callback given to "proceed" will receive
   *                            the new value of the property to return in order to set it on the object
   *                            or may return another value to set it on the object's property.
   * @return {PropertySettingPointcut} This pointcut (fluent interface).
   */


  around(adviceFn) {
    this.advices.push(new _advices_AroundAdvice__WEBPACK_IMPORTED_MODULE_3__["default"](adviceFn));
    return this;
  }

}
//# sourceMappingURL=PropertySettingPointcut.js.map

/***/ }),

/***/ "./node_modules/pigretto/dist/es6/pointcuts/shorthands/apply.js":
/*!**********************************************************************!*\
  !*** ./node_modules/pigretto/dist/es6/pointcuts/shorthands/apply.js ***!
  \**********************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ apply)
/* harmony export */ });
/* harmony import */ var _FunctionCallPointcut__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ../FunctionCallPointcut */ "./node_modules/pigretto/dist/es6/pointcuts/FunctionCallPointcut.js");
/*
 * Copyright (c) 2020 Anton Bagdatyev (Tonix)
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */

/**
 * Returns a new function call pointcut.
 *
 * @return {FunctionCallPointcut} A function call pointcut.
 */

function apply() {
  return new _FunctionCallPointcut__WEBPACK_IMPORTED_MODULE_0__["default"]();
}
//# sourceMappingURL=apply.js.map

/***/ }),

/***/ "./node_modules/pigretto/dist/es6/pointcuts/shorthands/call.js":
/*!*********************************************************************!*\
  !*** ./node_modules/pigretto/dist/es6/pointcuts/shorthands/call.js ***!
  \*********************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ call)
/* harmony export */ });
/* harmony import */ var _MethodCallPointcut__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ../MethodCallPointcut */ "./node_modules/pigretto/dist/es6/pointcuts/MethodCallPointcut.js");
/*
 * Copyright (c) 2020 Anton Bagdatyev (Tonix)
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */

/**
 * Returns a new method call pointcut.
 *
 * @return {MethodCallPointcut} A method call pointcut.
 */

function call() {
  return new _MethodCallPointcut__WEBPACK_IMPORTED_MODULE_0__["default"]();
}
//# sourceMappingURL=call.js.map

/***/ }),

/***/ "./node_modules/pigretto/dist/es6/pointcuts/shorthands/construct.js":
/*!**************************************************************************!*\
  !*** ./node_modules/pigretto/dist/es6/pointcuts/shorthands/construct.js ***!
  \**************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ construct)
/* harmony export */ });
/* harmony import */ var _ObjectConstructionPointcut__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ../ObjectConstructionPointcut */ "./node_modules/pigretto/dist/es6/pointcuts/ObjectConstructionPointcut.js");
/*
 * Copyright (c) 2020 Anton Bagdatyev (Tonix)
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */

/**
 * Returns a new object construction pointcut.
 *
 * @return {ObjectConstructionPointcut} An object construction pointcut.
 */

function construct() {
  return new _ObjectConstructionPointcut__WEBPACK_IMPORTED_MODULE_0__["default"]();
}
//# sourceMappingURL=construct.js.map

/***/ }),

/***/ "./node_modules/pigretto/dist/es6/pointcuts/shorthands/get.js":
/*!********************************************************************!*\
  !*** ./node_modules/pigretto/dist/es6/pointcuts/shorthands/get.js ***!
  \********************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ get)
/* harmony export */ });
/* harmony import */ var _PropertyGettingPointcut__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ../PropertyGettingPointcut */ "./node_modules/pigretto/dist/es6/pointcuts/PropertyGettingPointcut.js");
/*
 * Copyright (c) 2020 Anton Bagdatyev (Tonix)
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */

/**
 * Returns a new property getting pointcut.
 *
 * @return {PropertyGettingPointcut} A property getting pointcut.
 */

function get() {
  return new _PropertyGettingPointcut__WEBPACK_IMPORTED_MODULE_0__["default"]();
}
//# sourceMappingURL=get.js.map

/***/ }),

/***/ "./node_modules/pigretto/dist/es6/pointcuts/shorthands/set.js":
/*!********************************************************************!*\
  !*** ./node_modules/pigretto/dist/es6/pointcuts/shorthands/set.js ***!
  \********************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ set)
/* harmony export */ });
/* harmony import */ var _PropertySettingPointcut__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ../PropertySettingPointcut */ "./node_modules/pigretto/dist/es6/pointcuts/PropertySettingPointcut.js");
/*
 * Copyright (c) 2020 Anton Bagdatyev (Tonix)
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */

/**
 * Returns a new property setting pointcut.
 *
 * @return {PropertySettingPointcut} A property setting pointcut.
 */

function set() {
  return new _PropertySettingPointcut__WEBPACK_IMPORTED_MODULE_0__["default"]();
}
//# sourceMappingURL=set.js.map

/***/ }),

/***/ "./node_modules/pigretto/dist/es6/rules/ExactRule.js":
/*!***********************************************************!*\
  !*** ./node_modules/pigretto/dist/es6/rules/ExactRule.js ***!
  \***********************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ ExactRule)
/* harmony export */ });
/* harmony import */ var _Rule__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./Rule */ "./node_modules/pigretto/dist/es6/rules/Rule.js");
/*
 * Copyright (c) 2020 Anton Bagdatyev (Tonix)
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */

class ExactRule extends _Rule__WEBPACK_IMPORTED_MODULE_0__["default"] {
  constructor(property) {
    super();
    this.property = property;
  }

  matches(effectiveProperty) {
    return this.property === effectiveProperty;
  }

}
//# sourceMappingURL=ExactRule.js.map

/***/ }),

/***/ "./node_modules/pigretto/dist/es6/rules/ORedRule.js":
/*!**********************************************************!*\
  !*** ./node_modules/pigretto/dist/es6/rules/ORedRule.js ***!
  \**********************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ ORedRule)
/* harmony export */ });
/* harmony import */ var _Rule__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./Rule */ "./node_modules/pigretto/dist/es6/rules/Rule.js");
/*
 * Copyright (c) 2020 Anton Bagdatyev (Tonix)
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */

class ORedRule extends _Rule__WEBPACK_IMPORTED_MODULE_0__["default"] {
  constructor(rules) {
    super();
    this.rules = rules;
  }

  matches(effectivePropertyName) {
    for (const rule of this.rules) {
      if (rule.matches(effectivePropertyName)) {
        return true;
      }
    }

    return false;
  }

}
//# sourceMappingURL=ORedRule.js.map

/***/ }),

/***/ "./node_modules/pigretto/dist/es6/rules/RegexRule.js":
/*!***********************************************************!*\
  !*** ./node_modules/pigretto/dist/es6/rules/RegexRule.js ***!
  \***********************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ RegexRule)
/* harmony export */ });
/* harmony import */ var _Rule__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./Rule */ "./node_modules/pigretto/dist/es6/rules/Rule.js");
/*
 * Copyright (c) 2020 Anton Bagdatyev (Tonix)
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */

class RegexRule extends _Rule__WEBPACK_IMPORTED_MODULE_0__["default"] {
  constructor(regex) {
    super(); // This line prevents interference with the statefulness of the RegExp object.

    this.regex = new RegExp(regex);
  }

  matches(effectivePropertyName) {
    // This line resets the state of the RegExp object.
    this.regex.lastIndex = 0;
    return typeof effectivePropertyName !== "symbol" && this.regex.test(effectivePropertyName);
  }

}
//# sourceMappingURL=RegexRule.js.map

/***/ }),

/***/ "./node_modules/pigretto/dist/es6/rules/Rule.js":
/*!******************************************************!*\
  !*** ./node_modules/pigretto/dist/es6/rules/Rule.js ***!
  \******************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ Rule)
/* harmony export */ });
/*
 * Copyright (c) 2020 Anton Bagdatyev (Tonix)
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */
class Rule {}
//# sourceMappingURL=Rule.js.map

/***/ }),

/***/ "./node_modules/pigretto/dist/es6/rules/applyRule.js":
/*!***********************************************************!*\
  !*** ./node_modules/pigretto/dist/es6/rules/applyRule.js ***!
  \***********************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/*
 * Copyright (c) 2020 Anton Bagdatyev (Tonix)
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */

/**
 * @type {Symbol}
 */
const applyRule = Symbol("applyRule");
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (applyRule);
//# sourceMappingURL=applyRule.js.map

/***/ }),

/***/ "./node_modules/pigretto/dist/es6/rules/constructRule.js":
/*!***************************************************************!*\
  !*** ./node_modules/pigretto/dist/es6/rules/constructRule.js ***!
  \***************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/*
 * Copyright (c) 2020 Anton Bagdatyev (Tonix)
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */

/**
 * @type {Symbol}
 */
const constructRule = Symbol("constructRule");
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (constructRule);
//# sourceMappingURL=constructRule.js.map

/***/ }),

/***/ "./node_modules/pigretto/dist/es6/rules/parser/parseRule.js":
/*!******************************************************************!*\
  !*** ./node_modules/pigretto/dist/es6/rules/parser/parseRule.js ***!
  \******************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ parseRule)
/* harmony export */ });
/* harmony import */ var declarative_factory__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! declarative-factory */ "./node_modules/declarative-factory/src/index.js");
/* harmony import */ var js_utl__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! js-utl */ "./node_modules/js-utl/dist/es6/index.js");
/* harmony import */ var _ORedRule__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ../ORedRule */ "./node_modules/pigretto/dist/es6/rules/ORedRule.js");
/* harmony import */ var _RegexRule__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ../RegexRule */ "./node_modules/pigretto/dist/es6/rules/RegexRule.js");
/* harmony import */ var _ExactRule__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ../ExactRule */ "./node_modules/pigretto/dist/es6/rules/ExactRule.js");
/*
 * Copyright (c) 2020 Anton Bagdatyev (Tonix)
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */





const REGEX_STRING_TOKEN = "/";

const regexRule = regex => new _RegexRule__WEBPACK_IMPORTED_MODULE_2__["default"](regex);

const exactRule = property => new _ExactRule__WEBPACK_IMPORTED_MODULE_3__["default"](property);

const parseSingle = ruleToParse => {
  let factoryParam = ruleToParse;
  const factory = (0,declarative_factory__WEBPACK_IMPORTED_MODULE_0__["default"])([[() => ruleToParse instanceof RegExp, regexRule], [() => {
    if (typeof ruleToParse !== "string") {
      return false;
    }

    const initialRegexStringToken = ruleToParse.indexOf(REGEX_STRING_TOKEN);
    const lastRegexStringToken = ruleToParse.lastIndexOf(REGEX_STRING_TOKEN);

    if (initialRegexStringToken === 0 && lastRegexStringToken > 0) {
      const stringLiteralRegex = ruleToParse.substring(1, lastRegexStringToken);
      const flags = ruleToParse.substring(lastRegexStringToken + 1) || void 0;
      factoryParam = new RegExp(stringLiteralRegex, flags);
      return true;
    }

    return false;
  }, regexRule], exactRule]);
  return factory(factoryParam);
};

const parseMultiple = rulesToParse => {
  const rules = [];

  for (const ruleToParse of rulesToParse) {
    const rule = parseSingle(ruleToParse);
    rules.push(rule);
  }

  return new _ORedRule__WEBPACK_IMPORTED_MODULE_4__["default"](rules);
};

function parseRule(rule) {
  const parse = (0,declarative_factory__WEBPACK_IMPORTED_MODULE_0__["default"])([[(0,js_utl__WEBPACK_IMPORTED_MODULE_1__.isArray)(rule), parseMultiple], parseSingle]);
  return parse(rule);
}
//# sourceMappingURL=parseRule.js.map

/***/ }),

/***/ "./node_modules/pigretto/dist/es6/rules/rulesGenerator.js":
/*!****************************************************************!*\
  !*** ./node_modules/pigretto/dist/es6/rules/rulesGenerator.js ***!
  \****************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ rulesGenerator)
/* harmony export */ });
/* harmony import */ var declarative_factory__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! declarative-factory */ "./node_modules/declarative-factory/src/index.js");
/* harmony import */ var js_utl__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! js-utl */ "./node_modules/js-utl/dist/es6/index.js");
/*
 * Copyright (c) 2020 Anton Bagdatyev (Tonix)
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */



const objectRulesGenerator = function* (proxyRules) {
  const ruleKeys = Reflect.ownKeys(proxyRules);

  for (const ruleKey of ruleKeys) {
    const pointcut = proxyRules[ruleKey];
    yield {
      rule: ruleKey,
      pointcut
    };
  }
};

const arrayRulesGenerator = function* (proxyRules) {
  for (const proxyRule of proxyRules) {
    if ((0,js_utl__WEBPACK_IMPORTED_MODULE_1__.isArray)(proxyRule)) {
      // Tuple.
      const [rule, pointcut] = proxyRule;
      yield {
        rule,
        pointcut
      };
    } else {
      // Object.
      yield* objectRulesGenerator(proxyRule);
    }
  }
};
/**
 * A generator for proxy rules.
 *
 * @param {Object|Array} proxyRules An array of proxy rules or object with rules as keys.
 * @yields {Object} The next rule object, having two keys:
 *
 *                      - rule: The rule, as-is (as given by the client code);
 *                      - pointcut: The pointcut associated with that rule.
 *
 */


function* rulesGenerator(proxyRules) {
  const generator = (0,declarative_factory__WEBPACK_IMPORTED_MODULE_0__["default"])([[(0,js_utl__WEBPACK_IMPORTED_MODULE_1__.isArray)(proxyRules), arrayRulesGenerator], objectRulesGenerator]);
  yield* generator(proxyRules);
}
//# sourceMappingURL=rulesGenerator.js.map

/***/ }),

/***/ "./node_modules/pigretto/dist/es6/trap/Trap.js":
/*!*****************************************************!*\
  !*** ./node_modules/pigretto/dist/es6/trap/Trap.js ***!
  \*****************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ Trap)
/* harmony export */ });
/* harmony import */ var declarative_factory__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! declarative-factory */ "./node_modules/declarative-factory/src/index.js");
/* harmony import */ var _pointcuts_MethodCallPointcut__WEBPACK_IMPORTED_MODULE_10__ = __webpack_require__(/*! ../pointcuts/MethodCallPointcut */ "./node_modules/pigretto/dist/es6/pointcuts/MethodCallPointcut.js");
/* harmony import */ var _pointcuts_PropertyGettingPointcut__WEBPACK_IMPORTED_MODULE_11__ = __webpack_require__(/*! ../pointcuts/PropertyGettingPointcut */ "./node_modules/pigretto/dist/es6/pointcuts/PropertyGettingPointcut.js");
/* harmony import */ var _pointcuts_PropertySettingPointcut__WEBPACK_IMPORTED_MODULE_12__ = __webpack_require__(/*! ../pointcuts/PropertySettingPointcut */ "./node_modules/pigretto/dist/es6/pointcuts/PropertySettingPointcut.js");
/* harmony import */ var _pointcuts_FunctionCallPointcut__WEBPACK_IMPORTED_MODULE_14__ = __webpack_require__(/*! ../pointcuts/FunctionCallPointcut */ "./node_modules/pigretto/dist/es6/pointcuts/FunctionCallPointcut.js");
/* harmony import */ var _pointcuts_ObjectConstructionPointcut__WEBPACK_IMPORTED_MODULE_17__ = __webpack_require__(/*! ../pointcuts/ObjectConstructionPointcut */ "./node_modules/pigretto/dist/es6/pointcuts/ObjectConstructionPointcut.js");
/* harmony import */ var _rules_ExactRule__WEBPACK_IMPORTED_MODULE_9__ = __webpack_require__(/*! ../rules/ExactRule */ "./node_modules/pigretto/dist/es6/rules/ExactRule.js");
/* harmony import */ var _rules_applyRule__WEBPACK_IMPORTED_MODULE_13__ = __webpack_require__(/*! ../rules/applyRule */ "./node_modules/pigretto/dist/es6/rules/applyRule.js");
/* harmony import */ var _rules_constructRule__WEBPACK_IMPORTED_MODULE_16__ = __webpack_require__(/*! ../rules/constructRule */ "./node_modules/pigretto/dist/es6/rules/constructRule.js");
/* harmony import */ var _pointcuts_shorthands_apply__WEBPACK_IMPORTED_MODULE_15__ = __webpack_require__(/*! ../pointcuts/shorthands/apply */ "./node_modules/pigretto/dist/es6/pointcuts/shorthands/apply.js");
/* harmony import */ var _pointcuts_shorthands_construct__WEBPACK_IMPORTED_MODULE_18__ = __webpack_require__(/*! ../pointcuts/shorthands/construct */ "./node_modules/pigretto/dist/es6/pointcuts/shorthands/construct.js");
/* harmony import */ var _advices_BeforeAdvice__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! ../advices/BeforeAdvice */ "./node_modules/pigretto/dist/es6/advices/BeforeAdvice.js");
/* harmony import */ var _advices_AroundAdvice__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! ../advices/AroundAdvice */ "./node_modules/pigretto/dist/es6/advices/AroundAdvice.js");
/* harmony import */ var _executors_CallTrapExecutor__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./executors/CallTrapExecutor */ "./node_modules/pigretto/dist/es6/trap/executors/CallTrapExecutor.js");
/* harmony import */ var _executors_GetTrapExecutor__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./executors/GetTrapExecutor */ "./node_modules/pigretto/dist/es6/trap/executors/GetTrapExecutor.js");
/* harmony import */ var _executors_SetTrapExecutor__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./executors/SetTrapExecutor */ "./node_modules/pigretto/dist/es6/trap/executors/SetTrapExecutor.js");
/* harmony import */ var _executors_ApplyTrapExecutor__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ./executors/ApplyTrapExecutor */ "./node_modules/pigretto/dist/es6/trap/executors/ApplyTrapExecutor.js");
/* harmony import */ var _executors_ConstructTrapExecutor__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ./executors/ConstructTrapExecutor */ "./node_modules/pigretto/dist/es6/trap/executors/ConstructTrapExecutor.js");
/* harmony import */ var js_utl__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! js-utl */ "./node_modules/js-utl/dist/es6/index.js");
function _defineProperty(obj, key, value) { if (key in obj) { Object.defineProperty(obj, key, { value: value, enumerable: true, configurable: true, writable: true }); } else { obj[key] = value; } return obj; }

/*
 * Copyright (c) 2020 Anton Bagdatyev (Tonix)
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */



















class Trap {
  constructor() {
    _defineProperty(this, "advices", {
      // Requires matching (get trap). Requires distribution.
      call: [],
      // Requires matching (get trap). Requires distribution.
      get: [],
      // Requires matching (set trap). Requires distribution.
      set: [],
      // Does not require matching (apply trap). Requires distribution.
      apply: [],
      // Does not require matching (construct trap). Requires distribution.
      construct: []
    });

    _defineProperty(this, "distributedAdvices", {
      call: {
        before: void 0,
        around: void 0,
        after: void 0
      },
      get: {
        before: void 0,
        around: void 0,
        after: void 0
      },
      set: {
        before: void 0,
        around: void 0,
        after: void 0
      },
      apply: {
        before: void 0,
        around: void 0,
        after: void 0
      },
      construct: {
        before: void 0,
        around: void 0,
        after: void 0
      }
    });

    _defineProperty(this, "matchedDistributedAdvices", {
      call: new Map(),
      get: new Map(),
      set: new Map()
    });

    _defineProperty(this, "trapExecutors", {
      call: new _executors_CallTrapExecutor__WEBPACK_IMPORTED_MODULE_2__["default"](),
      get: new _executors_GetTrapExecutor__WEBPACK_IMPORTED_MODULE_3__["default"](),
      set: new _executors_SetTrapExecutor__WEBPACK_IMPORTED_MODULE_4__["default"](),
      apply: new _executors_ApplyTrapExecutor__WEBPACK_IMPORTED_MODULE_5__["default"](),
      construct: new _executors_ConstructTrapExecutor__WEBPACK_IMPORTED_MODULE_6__["default"]()
    });
  }

  /**
   * @private
   */
  hasAtLeastOneAdviceOfPointcutType(pointcutType) {
    return !!this.advices[pointcutType].length;
  }
  /**
   * @private
   */


  addAdvices(pointcutType, rule, advices = []) {
    advices.map(advice => this.advices[pointcutType].push({
      rule,
      advice
    }));
  }
  /**
   * @private
   */


  lazilyDistributeAdvices(pointcutType) {
    if ((0,js_utl__WEBPACK_IMPORTED_MODULE_1__.isUndefined)(this.distributedAdvices[pointcutType].before)) {
      this.distributedAdvices[pointcutType].before = [];
      this.distributedAdvices[pointcutType].around = [];
      this.distributedAdvices[pointcutType].after = [];

      for (const {
        rule,
        advice
      } of this.advices[pointcutType]) {
        const subKey = (0,declarative_factory__WEBPACK_IMPORTED_MODULE_0__["default"])([[() => advice instanceof _advices_BeforeAdvice__WEBPACK_IMPORTED_MODULE_7__["default"], "before"], [() => advice instanceof _advices_AroundAdvice__WEBPACK_IMPORTED_MODULE_8__["default"], "around"], "after"]);
        this.distributedAdvices[pointcutType][subKey].push({
          rule,
          advice
        });
      }
    }
  }
  /**
   * For "call", "get" and "set" advices.
   *
   * @private
   */


  lazilyMatchAdvices(pointcutType, property) {
    this.lazilyDistributeAdvices(pointcutType);
    const map = this.matchedDistributedAdvices[pointcutType];

    if (!map.has(property)) {
      const node = {
        before: [],
        around: [],
        after: []
      };
      map.set(property, node);
      const beforeAdvices = this.distributedAdvices[pointcutType].before;
      const aroundAdvices = this.distributedAdvices[pointcutType].around;
      const afterAdvices = this.distributedAdvices[pointcutType].after;

      const match = advices => key => {
        for (const {
          rule,
          advice
        } of advices) {
          const matches = rule.matches(property);

          if (matches) {
            node[key].push({
              rule,
              advice
            });
          }
        }
      };

      match(beforeAdvices)("before");
      match(aroundAdvices)("around");
      match(afterAdvices)("after");
    }
  }
  /**
   * Adds a pointcut and its associated rule to this trap.
   * If a pointcut is of an unknown type, it will be ignored.
   *
   * @param {Pointcut|Function} pointcut The pointcut or a function (for implicit apply and construct pointcuts).
   * @param {Rule} rule The rule.
   * @return {Trap} This trap object.
   */


  addPointcutRule(pointcut, rule) {
    const isImplicitBeforeAdvice = () => typeof pointcut === "function" && rule instanceof _rules_ExactRule__WEBPACK_IMPORTED_MODULE_9__["default"];

    const pointcutType = (0,declarative_factory__WEBPACK_IMPORTED_MODULE_0__["default"])([[() => pointcut instanceof _pointcuts_MethodCallPointcut__WEBPACK_IMPORTED_MODULE_10__["default"], "call"], [() => pointcut instanceof _pointcuts_PropertyGettingPointcut__WEBPACK_IMPORTED_MODULE_11__["default"], "get"], [() => pointcut instanceof _pointcuts_PropertySettingPointcut__WEBPACK_IMPORTED_MODULE_12__["default"], "set"], [() => {
      const isImplicit = isImplicitBeforeAdvice() && rule.property === _rules_applyRule__WEBPACK_IMPORTED_MODULE_13__["default"];

      if (isImplicit || pointcut instanceof _pointcuts_FunctionCallPointcut__WEBPACK_IMPORTED_MODULE_14__["default"]) {
        if (isImplicit) {
          pointcut = (0,_pointcuts_shorthands_apply__WEBPACK_IMPORTED_MODULE_15__["default"])().before(pointcut);
        }

        return true;
      }

      return false;
    }, "apply"], [() => {
      const isImplicit = isImplicitBeforeAdvice() && rule.property === _rules_constructRule__WEBPACK_IMPORTED_MODULE_16__["default"];

      if (isImplicit || pointcut instanceof _pointcuts_ObjectConstructionPointcut__WEBPACK_IMPORTED_MODULE_17__["default"]) {
        if (isImplicit) {
          pointcut = (0,_pointcuts_shorthands_construct__WEBPACK_IMPORTED_MODULE_18__["default"])().before(pointcut);
        }

        return true;
      }

      return false;
    }, "construct"]]);

    if (pointcutType) {
      const {
        advices
      } = pointcut;
      this.addAdvices(pointcutType, rule, advices);
    }

    return this;
  }

  hasGets() {
    return this.hasAtLeastOneAdviceOfPointcutType("call") || this.hasAtLeastOneAdviceOfPointcutType("get");
  }

  hasSets() {
    return this.hasAtLeastOneAdviceOfPointcutType("set");
  }

  hasApplies() {
    return this.hasAtLeastOneAdviceOfPointcutType("apply");
  }

  hasConstructs() {
    return this.hasAtLeastOneAdviceOfPointcutType("construct");
  }

  get(target, property, receiver) {
    // Method call and property access (getting) advices.
    this.lazilyMatchAdvices("get", property);
    this.lazilyMatchAdvices("call", property);
    const {
      before: getBefore,
      around: getAround,
      after: getAfter
    } = this.matchedDistributedAdvices.get.get(property);
    const propertyValue = this.trapExecutors.get.execute([target, property, receiver], getBefore, getAround, getAfter);
    const {
      before: callBefore,
      around: callAround,
      after: callAfter
    } = this.matchedDistributedAdvices.call.get(property);
    const returnValue = this.trapExecutors.call.execute([target, property, receiver, propertyValue], callBefore, callAround, callAfter);
    return returnValue;
  }

  set(target, property, value, receiver) {
    // Property access (setting) advices.
    this.lazilyMatchAdvices("set", property);
    const {
      before,
      around,
      after
    } = this.matchedDistributedAdvices.set.get(property);
    return this.trapExecutors.set.execute([target, property, value, receiver], before, around, after);
  }

  apply(target, thisArg, argumentsList) {
    // Function call advices.
    this.lazilyDistributeAdvices("apply");
    const {
      before,
      around,
      after
    } = this.distributedAdvices.apply;
    return this.trapExecutors.apply.execute([target, thisArg, argumentsList], before, around, after);
  }

  construct(target, argumentsList, newTarget) {
    // Object construction via the "new" keyword advices.
    this.lazilyDistributeAdvices("construct");
    const {
      before,
      around,
      after
    } = this.distributedAdvices.construct;
    return this.trapExecutors.construct.execute([target, argumentsList, newTarget], before, around, after);
  }

}
//# sourceMappingURL=Trap.js.map

/***/ }),

/***/ "./node_modules/pigretto/dist/es6/trap/executors/ApplyTrapExecutor.js":
/*!****************************************************************************!*\
  !*** ./node_modules/pigretto/dist/es6/trap/executors/ApplyTrapExecutor.js ***!
  \****************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _TrapExecutor__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./TrapExecutor */ "./node_modules/pigretto/dist/es6/trap/executors/TrapExecutor.js");
/* harmony import */ var _reflect_reflectApply__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ../reflect/reflectApply */ "./node_modules/pigretto/dist/es6/trap/reflect/reflectApply.js");
/* harmony import */ var _behaviours_withFunctionTrapExecutor__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./behaviours/withFunctionTrapExecutor */ "./node_modules/pigretto/dist/es6/trap/executors/behaviours/withFunctionTrapExecutor.js");
/*
 * Copyright (c) 2020 Anton Bagdatyev (Tonix)
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */



/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = ((0,_behaviours_withFunctionTrapExecutor__WEBPACK_IMPORTED_MODULE_0__.withFunctionTrapExecutor)(class ApplyTrapExecutor extends _TrapExecutor__WEBPACK_IMPORTED_MODULE_1__["default"] {
  getTrapArgsArgumentsListIndex() {
    return 2;
  }

  executeBeforeAdvice([target, thisArg, argumentsList], advice, rule) {
    const context = {
      target,
      thisArg,
      rule
    };
    advice.fn.apply(context, argumentsList);
  }

  executeAroundAdvice([target, thisArg, argumentsList], advice, rule, proceed) {
    const context = {
      target,
      thisArg,
      rule
    };
    return advice.fn.call(context, proceed).apply(context, argumentsList);
  }

  executeAfterAdvice([target, thisArg, argumentsList], advice, rule, returnValue) {
    const context = {
      target,
      thisArg,
      rule
    };
    advice.fn.call(context, ...argumentsList).apply(context, [returnValue]);
  }

  performUnderlyingOperation([target, thisArg, argumentsList]) {
    const returnValue = (0,_reflect_reflectApply__WEBPACK_IMPORTED_MODULE_2__["default"])(target, thisArg, argumentsList);
    return returnValue;
  }

  executeProceedCallback([target, thisArg, argumentsList], rule, returnValue, callback) {
    const context = {
      target,
      thisArg,
      rule,
      argumentsList
    };
    return callback.apply(context, [returnValue]);
  }

}));
//# sourceMappingURL=ApplyTrapExecutor.js.map

/***/ }),

/***/ "./node_modules/pigretto/dist/es6/trap/executors/CallTrapExecutor.js":
/*!***************************************************************************!*\
  !*** ./node_modules/pigretto/dist/es6/trap/executors/CallTrapExecutor.js ***!
  \***************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _TrapExecutor__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./TrapExecutor */ "./node_modules/pigretto/dist/es6/trap/executors/TrapExecutor.js");
/* harmony import */ var _behaviours_withFunctionTrapExecutor__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./behaviours/withFunctionTrapExecutor */ "./node_modules/pigretto/dist/es6/trap/executors/behaviours/withFunctionTrapExecutor.js");
/* harmony import */ var js_utl__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! js-utl */ "./node_modules/js-utl/dist/es6/index.js");
/*
 * Copyright (c) 2020 Anton Bagdatyev (Tonix)
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */



/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = ((0,_behaviours_withFunctionTrapExecutor__WEBPACK_IMPORTED_MODULE_1__.withFunctionTrapExecutor)(class CallTrapExecutor extends _TrapExecutor__WEBPACK_IMPORTED_MODULE_2__["default"] {
  getTrapArgsArgumentsListIndex() {
    return 4;
  }

  execute([target, property, receiver, propertyValue], before, around, after) {
    if (typeof propertyValue === "function" && [before, around, after].some(advices => !(0,js_utl__WEBPACK_IMPORTED_MODULE_0__.isEmpty)(advices))) {
      const superExecute = trapArgs => {
        return super.execute(trapArgs, before, around, after);
      };

      const wrapperFn = function (...args) {
        let boundThis;

        if (this !== receiver) {
          boundThis = this;
        } else {
          boundThis = target;
        }

        propertyValue = propertyValue.bind(boundThis);
        const trapArgs = [target, property, receiver, propertyValue, args];
        return superExecute(trapArgs);
      };

      return wrapperFn;
    }

    return propertyValue;
  }

  executeBeforeAdvice([target, property, receiver,, argumentsList], advice, rule) {
    const context = {
      target,
      property,
      receiver,
      rule
    };
    advice.fn.apply(context, argumentsList);
  }

  executeAroundAdvice([target, property, receiver,, argumentsList], advice, rule, proceed) {
    const context = {
      target,
      property,
      receiver,
      rule
    };
    return advice.fn.call(context, proceed).apply(context, argumentsList);
  }

  executeAfterAdvice([target, property, receiver,, argumentsList], advice, rule, returnValue) {
    const context = {
      target,
      property,
      receiver,
      rule
    };
    advice.fn.call(context, ...argumentsList).apply(context, [returnValue]);
  }

  performUnderlyingOperation([,,, originalFn, argumentsList]) {
    const returnValue = originalFn(...argumentsList);
    return returnValue;
  }

  executeProceedCallback([target, property, receiver,, argumentsList], rule, returnValue, callback) {
    const context = {
      target,
      property,
      receiver,
      rule,
      argumentsList
    };
    return callback.apply(context, [returnValue]);
  }

}));
//# sourceMappingURL=CallTrapExecutor.js.map

/***/ }),

/***/ "./node_modules/pigretto/dist/es6/trap/executors/ConstructTrapExecutor.js":
/*!********************************************************************************!*\
  !*** ./node_modules/pigretto/dist/es6/trap/executors/ConstructTrapExecutor.js ***!
  \********************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _TrapExecutor__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./TrapExecutor */ "./node_modules/pigretto/dist/es6/trap/executors/TrapExecutor.js");
/* harmony import */ var _reflect_reflectConstruct__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ../reflect/reflectConstruct */ "./node_modules/pigretto/dist/es6/trap/reflect/reflectConstruct.js");
/* harmony import */ var _behaviours_withFunctionTrapExecutor__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./behaviours/withFunctionTrapExecutor */ "./node_modules/pigretto/dist/es6/trap/executors/behaviours/withFunctionTrapExecutor.js");
/*
 * Copyright (c) 2020 Anton Bagdatyev (Tonix)
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */



/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = ((0,_behaviours_withFunctionTrapExecutor__WEBPACK_IMPORTED_MODULE_0__.withFunctionTrapExecutor)(class ConstructTrapExecutor extends _TrapExecutor__WEBPACK_IMPORTED_MODULE_1__["default"] {
  getTrapArgsArgumentsListIndex() {
    return 1;
  }

  executeBeforeAdvice([target, argumentsList, newTarget], advice, rule) {
    const context = {
      target,
      newTarget,
      rule
    };
    advice.fn.apply(context, argumentsList);
  }

  executeAroundAdvice([target, argumentsList, newTarget], advice, rule, proceed) {
    const context = {
      target,
      newTarget,
      rule
    };
    return advice.fn.call(context, proceed).apply(context, argumentsList);
  }

  executeAfterAdvice([target, argumentsList, newTarget], advice, rule, instance) {
    const context = {
      target,
      newTarget,
      rule
    };
    advice.fn.call(context, ...argumentsList).apply(context, [instance]);
  }

  performUnderlyingOperation([target, argumentsList, newTarget]) {
    const instance = (0,_reflect_reflectConstruct__WEBPACK_IMPORTED_MODULE_2__["default"])(target, argumentsList, newTarget);
    return instance;
  }

  executeProceedCallback([target, argumentsList, newTarget], rule, instance, callback) {
    const context = {
      target,
      newTarget,
      rule,
      argumentsList
    };
    return callback.apply(context, [instance]);
  }

}));
//# sourceMappingURL=ConstructTrapExecutor.js.map

/***/ }),

/***/ "./node_modules/pigretto/dist/es6/trap/executors/GetTrapExecutor.js":
/*!**************************************************************************!*\
  !*** ./node_modules/pigretto/dist/es6/trap/executors/GetTrapExecutor.js ***!
  \**************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ GetTrapExecutor)
/* harmony export */ });
/* harmony import */ var _TrapExecutor__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./TrapExecutor */ "./node_modules/pigretto/dist/es6/trap/executors/TrapExecutor.js");
/* harmony import */ var _reflect_reflectGet__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ../reflect/reflectGet */ "./node_modules/pigretto/dist/es6/trap/reflect/reflectGet.js");
/*
 * Copyright (c) 2020 Anton Bagdatyev (Tonix)
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */


class GetTrapExecutor extends _TrapExecutor__WEBPACK_IMPORTED_MODULE_0__["default"] {
  executeBeforeAdvice([target, property, receiver], advice, rule) {
    const context = {
      target,
      property,
      receiver,
      rule
    };
    advice.fn.apply(context);
  }

  executeAroundAdvice([target, property, receiver], advice, rule, proceed) {
    const context = {
      target,
      property,
      receiver,
      rule
    };
    return advice.fn.apply(context, [proceed]);
  }

  executeAfterAdvice([target, property, receiver], advice, rule, propertyValue) {
    const context = {
      target,
      property,
      receiver,
      rule
    };
    advice.fn.apply(context, [propertyValue]);
  }

  performUnderlyingOperation([target, property, receiver]) {
    const propertyValue = (0,_reflect_reflectGet__WEBPACK_IMPORTED_MODULE_1__["default"])(target, property, receiver);
    return propertyValue;
  }

  executeProceedCallback([target, property, receiver], rule, propertyValue, callback) {
    const context = {
      target,
      property,
      receiver,
      rule
    };
    return callback.apply(context, [propertyValue]);
  }

}
//# sourceMappingURL=GetTrapExecutor.js.map

/***/ }),

/***/ "./node_modules/pigretto/dist/es6/trap/executors/SetTrapExecutor.js":
/*!**************************************************************************!*\
  !*** ./node_modules/pigretto/dist/es6/trap/executors/SetTrapExecutor.js ***!
  \**************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ SetTrapExecutor)
/* harmony export */ });
/* harmony import */ var _TrapExecutor__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./TrapExecutor */ "./node_modules/pigretto/dist/es6/trap/executors/TrapExecutor.js");
/* harmony import */ var _reflect_reflectSet__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ../reflect/reflectSet */ "./node_modules/pigretto/dist/es6/trap/reflect/reflectSet.js");
/* harmony import */ var _reflect_reflectGet__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ../reflect/reflectGet */ "./node_modules/pigretto/dist/es6/trap/reflect/reflectGet.js");
function _defineProperty(obj, key, value) { if (key in obj) { Object.defineProperty(obj, key, { value: value, enumerable: true, configurable: true, writable: true }); } else { obj[key] = value; } return obj; }

/*
 * Copyright (c) 2020 Anton Bagdatyev (Tonix)
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */



class SetTrapExecutor extends _TrapExecutor__WEBPACK_IMPORTED_MODULE_0__["default"] {
  constructor(...args) {
    super(...args);

    _defineProperty(this, "previousPropertyValueMap", {});

    _defineProperty(this, "returnNewPropertyValueMap", {});

    _defineProperty(this, "updateWasSuccessfulMap", {});
  }

  startExecutionContext([target, property,, receiver]) {
    if (!Object.prototype.hasOwnProperty.call(this.previousPropertyValueMap, this.execContextID)) {
      const previousPropertyValue = (0,_reflect_reflectGet__WEBPACK_IMPORTED_MODULE_1__["default"])(target, property, receiver);
      this.previousPropertyValueMap[this.execContextID] = previousPropertyValue;
      this.returnNewPropertyValueMap[this.execContextID] = void 0;
      this.updateWasSuccessfulMap[this.execContextID] = false;
    }
  }

  endExecutionContext([,,,]) {
    delete this.previousPropertyValueMap[this.execContextID];
    delete this.returnNewPropertyValueMap[this.execContextID];
    delete this.updateWasSuccessfulMap[this.execContextID];
  }

  executeBeforeAdvice([target, property, value, receiver], advice, rule) {
    const previousPropertyValue = this.previousPropertyValueMap[this.execContextID];
    const context = {
      target,
      property,
      value,
      receiver,
      rule
    };
    advice.fn.apply(context, [previousPropertyValue]);
  }

  executeAroundAdvice([target, property, value, receiver], advice, rule, proceed) {
    const previousPropertyValue = this.previousPropertyValueMap[this.execContextID];
    const context = {
      target,
      property,
      value,
      receiver,
      rule
    };
    return advice.fn.call(context, proceed).apply(context, [previousPropertyValue]);
  }

  executeAfterAdvice([target, property, value, receiver], advice, rule, updateWasSuccessful) {
    const previousPropertyValue = this.previousPropertyValueMap[this.execContextID];
    const newPropertyValue = this.returnNewPropertyValueMap[this.execContextID];
    const context = {
      target,
      property,
      value,
      receiver,
      rule,
      updateWasSuccessful
    };
    advice.fn.call(context, previousPropertyValue).apply(context, [newPropertyValue]);
  }

  performUnderlyingOperation([target, property, value, receiver]) {
    const updateWasSuccessful = (0,_reflect_reflectSet__WEBPACK_IMPORTED_MODULE_2__["default"])(target, property, value, receiver);
    this.updateWasSuccessfulMap[this.execContextID] = updateWasSuccessful;
    this.returnNewPropertyValueMap[this.execContextID] = value;
    return value;
  }

  executeProceedCallback([target, property, value, receiver], rule, newPropertyValue, callback) {
    const context = {
      target,
      property,
      value,
      receiver,
      rule
    };
    const returnValue = callback.apply(context, [newPropertyValue]);
    return returnValue;
  }

  return([target, property,, receiver], returnValue) {
    if (returnValue !== this.returnNewPropertyValueMap[this.execContextID]) {
      this.performUnderlyingOperation([target, property, returnValue, receiver]);
    }

    return this.updateWasSuccessfulMap[this.execContextID];
  }

}
//# sourceMappingURL=SetTrapExecutor.js.map

/***/ }),

/***/ "./node_modules/pigretto/dist/es6/trap/executors/TrapExecutor.js":
/*!***********************************************************************!*\
  !*** ./node_modules/pigretto/dist/es6/trap/executors/TrapExecutor.js ***!
  \***********************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ TrapExecutor)
/* harmony export */ });
/* harmony import */ var js_utl__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! js-utl */ "./node_modules/js-utl/dist/es6/index.js");
function _defineProperty(obj, key, value) { if (key in obj) { Object.defineProperty(obj, key, { value: value, enumerable: true, configurable: true, writable: true }); } else { obj[key] = value; } return obj; }

/*
 * Copyright (c) 2020 Anton Bagdatyev (Tonix)
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */

/**
 * @type {Symbol}
 */

const noReturnValue = Symbol("noReturnValue");
class TrapExecutor {
  constructor() {
    _defineProperty(this, "execContextStack", []);

    _defineProperty(this, "execContextID", -1);
  }

  execute(trapArgs, before, around, after) {
    this.newExecutionContext();
    this.setUpExecutionContext(before, around);
    this.startExecutionContext(trapArgs);
    this.beforePhase(trapArgs, before);
    this.aroundPhase(trapArgs, around);
    const returnValue = this.proceedPhase(trapArgs);
    this.afterPhase(trapArgs, after, returnValue);
    this.endExecutionContext(trapArgs);
    this.cleanUpExecutionContext();
    return returnValue;
  }
  /**
   * @private
   */


  newExecutionContext() {
    const context = {
      proceeds: [],
      hasAtLeastOneAroundAdvice: false,
      hasAtLeastOneBeforeAdvice: false,
      returnValue: noReturnValue
    };
    this.execContextStack.push(context);
    this.execContextID++;
  }
  /**
   * @private
   */


  setUpExecutionContext(before, around) {
    this.execContextStack[this.execContextID].hasAtLeastOneBeforeAdvice = !!before.length;
    this.execContextStack[this.execContextID].hasAtLeastOneAroundAdvice = !!around.length;
  }
  /* eslint-disable no-unused-vars, @typescript-eslint/no-empty-function */


  startExecutionContext(trapArgs) {}
  /* eslint-enable no-unused-vars, @typescript-eslint/no-empty-function */

  /* eslint-disable no-unused-vars, @typescript-eslint/no-empty-function */


  endExecutionContext(trapArgs) {}
  /* eslint-enable no-unused-vars, @typescript-eslint/no-empty-function */

  /**
   * @private
   */


  cleanUpExecutionContext() {
    this.execContextStack.pop();
    this.execContextID--;
  }
  /**
   * @private
   */


  unsupportedMultipleProceeds(advice, rule) {
    // eslint-disable-next-line no-console
    console.error("pigretto - Multiple proceeds for the same advice are not supported, subsequent proceed has been ignored.", "\n\tadvice: ", advice, "\n\trule: ", rule);
  } // eslint-disable-next-line no-unused-vars


  executeBeforeAdvice(trapArgs, advice, rule) {
    throw new Error(`pigretto - ${this.constructor.name} trap executor does not implement "executeBeforeAdvice".`);
  } // eslint-disable-next-line no-unused-vars


  executeAroundAdvice(trapArgs, advice, rule, proceed) {
    throw new Error(`pigretto - ${this.constructor.name} trap executor does not implement "executeAroundAdvice".`);
  } // eslint-disable-next-line no-unused-vars


  executeAfterAdvice(trapArgs, advice, rule, returnValue) {
    throw new Error(`pigretto - ${this.constructor.name} trap executor does not implement "executeAfterAdvice".`);
  } // eslint-disable-next-line no-unused-vars


  performUnderlyingOperation(trapArgs) {
    throw new Error(`pigretto - ${this.constructor.name} trap executor does not implement "performUnderlyingOperation".`);
  } // eslint-disable-next-line no-unused-vars


  executeProceedCallback(trapArgs, rule, returnValue, callback) {
    throw new Error(`pigretto - ${this.constructor.name} trap executor does not implement "executeProceedCallback".`);
  }
  /**
   * @private
   */


  beforePhase(trapArgs, before) {
    for (const {
      rule,
      advice
    } of before) {
      this.executeBefore(trapArgs, advice, rule);
    }
  }
  /**
   * @private
   */


  executeBefore(trapArgs, advice, rule) {
    this.executeBeforeAdvice(trapArgs, advice, rule);
  }
  /**
   * @private
   */


  aroundPhase(trapArgs, around) {
    for (const {
      rule,
      advice
    } of around) {
      this.executeAround(trapArgs, advice, rule);

      if (this.execContextStack[this.execContextID].returnValue !== noReturnValue) {
        break;
      }
    }
  }
  /**
   * @private
   */


  executeAround(trapArgs, advice, rule) {
    let hasProceeded = false;

    const proceed = (params = void 0, fn = void 0) => {
      if (hasProceeded) {
        this.unsupportedMultipleProceeds(advice, rule);
        return;
      }

      hasProceeded = true;
      let finalParams = void 0;
      let finalFn = void 0;

      if ((0,js_utl__WEBPACK_IMPORTED_MODULE_0__.isArray)(params)) {
        finalParams = params;
        finalFn = typeof fn === "function" ? fn : void 0;
      } else if (typeof params === "function") {
        finalFn = params;
      }

      this.execContextStack[this.execContextID].proceeds.push({
        params: finalParams,
        fn: finalFn,
        rule
      });
    };

    const returnValue = this.executeAroundAdvice(trapArgs, advice, rule, proceed);

    if (!hasProceeded) {
      this.execContextStack[this.execContextID].returnValue = returnValue;
    }
  }
  /**
   * @private
   */


  afterPhase(trapArgs, after, returnValue) {
    for (const {
      rule,
      advice
    } of after) {
      this.executeAfter(trapArgs, advice, rule, returnValue);
    }
  }
  /**
   * @private
   */


  executeAfter(trapArgs, advice, rule, returnValue) {
    this.executeAfterAdvice(trapArgs, advice, rule, returnValue);
  }
  /**
   * @private
   */


  proceedPhase(trapArgs) {
    if (this.execContextStack[this.execContextID].returnValue !== noReturnValue) {
      return this.return(trapArgs, this.execContextStack[this.execContextID].returnValue);
    }

    let returnValue = this.performUnderlyingOperation(trapArgs);

    for (const {
      fn: callback,
      rule
    } of this.execContextStack[this.execContextID].proceeds) {
      if (typeof callback === "function") {
        returnValue = this.executeProceedCallback(trapArgs, rule, returnValue, callback);
      }
    }

    return this.return(trapArgs, returnValue);
  }

  return(trapArgs, returnValue) {
    return returnValue;
  }

}
//# sourceMappingURL=TrapExecutor.js.map

/***/ }),

/***/ "./node_modules/pigretto/dist/es6/trap/executors/behaviours/common/throwErrorIfDoesNotImplement.js":
/*!*********************************************************************************************************!*\
  !*** ./node_modules/pigretto/dist/es6/trap/executors/behaviours/common/throwErrorIfDoesNotImplement.js ***!
  \*********************************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "throwErrorIfDoesNotImplement": () => (/* binding */ throwErrorIfDoesNotImplement)
/* harmony export */ });
/*
 * Copyright (c) 2020 Anton Bagdatyev (Tonix)
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */
const throwErrorIfDoesNotImplement = (context, methodName, constructorFn) => {
  if (typeof context[methodName] !== "function") {
    throw new Error(`pigretto - ${constructorFn.name} trap executor does not implement "${methodName}".`);
  }
};
//# sourceMappingURL=throwErrorIfDoesNotImplement.js.map

/***/ }),

/***/ "./node_modules/pigretto/dist/es6/trap/executors/behaviours/withFunctionTrapExecutor.js":
/*!**********************************************************************************************!*\
  !*** ./node_modules/pigretto/dist/es6/trap/executors/behaviours/withFunctionTrapExecutor.js ***!
  \**********************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "withFunctionTrapExecutor": () => (/* binding */ withFunctionTrapExecutor)
/* harmony export */ });
/* harmony import */ var js_utl__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! js-utl */ "./node_modules/js-utl/dist/es6/index.js");
/* harmony import */ var _common_throwErrorIfDoesNotImplement__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./common/throwErrorIfDoesNotImplement */ "./node_modules/pigretto/dist/es6/trap/executors/behaviours/common/throwErrorIfDoesNotImplement.js");
function _defineProperty(obj, key, value) { if (key in obj) { Object.defineProperty(obj, key, { value: value, enumerable: true, configurable: true, writable: true }); } else { obj[key] = value; } return obj; }

/*
 * Copyright (c) 2020 Anton Bagdatyev (Tonix)
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */


const withFunctionTrapExecutor = superclass => {
  var _temp;

  const NewClass = (_temp = class NewClass extends superclass {
    constructor(...args) {
      super(...args);

      _defineProperty(this, "executedAtLeastOnce", false);

      _defineProperty(this, "lastProceedWithParamsIndexMap", {});

      _defineProperty(this, "finalTrapArgsMap", {});
    }

    startExecutionContext(trapArgs) {
      !this.executedAtLeastOnce && (0,_common_throwErrorIfDoesNotImplement__WEBPACK_IMPORTED_MODULE_1__.throwErrorIfDoesNotImplement)(superclass.prototype, "startExecutionContext", superclass);
      super.startExecutionContext(trapArgs);

      if (!this.executedAtLeastOnce && !Object.prototype.hasOwnProperty.call(this, "execContextID")) {
        throw new Error(`pigretto - ${superclass.name} trap executor does not have "execContextID" property.`);
      }

      this.lastProceedWithParamsIndexMap[this.execContextID] = -1;
      this.finalTrapArgsMap[this.execContextID] = void 0;
    }

    endExecutionContext(trapArgs) {
      !this.executedAtLeastOnce && (0,_common_throwErrorIfDoesNotImplement__WEBPACK_IMPORTED_MODULE_1__.throwErrorIfDoesNotImplement)(superclass.prototype, "endExecutionContext", superclass);
      super.endExecutionContext(trapArgs);
      this.finalTrapArgsMap[this.execContextID] = void 0;
      this.executedAtLeastOnce = true;
    }

    getFinalTrapArgs(trapArgs) {
      !this.executedAtLeastOnce && (0,_common_throwErrorIfDoesNotImplement__WEBPACK_IMPORTED_MODULE_1__.throwErrorIfDoesNotImplement)(this, "getTrapArgsArgumentsListIndex", superclass);
      const argumentsListIndex = this.getTrapArgsArgumentsListIndex();

      if (!this.executedAtLeastOnce && !Object.prototype.hasOwnProperty.call(this, "execContextStack")) {
        throw new Error(`pigretto - ${superclass.name} trap executor does not have "execContextStack" property.`);
      }

      const proceedsL = this.execContextStack[this.execContextID].proceeds.length;

      if (proceedsL) {
        let hasParamsOverride = false;

        for (let i = proceedsL - 1; i > this.lastProceedWithParamsIndexMap[this.execContextID]; i--) {
          const {
            params
          } = this.execContextStack[this.execContextID].proceeds[i];

          if ((0,js_utl__WEBPACK_IMPORTED_MODULE_0__.isArray)(params)) {
            hasParamsOverride = true;
            this.lastProceedWithParamsIndexMap[this.execContextID] = i;
            trapArgs = [...trapArgs];
            trapArgs[argumentsListIndex] = params;
            this.finalTrapArgsMap[this.execContextID] = trapArgs;
            break;
          }
        }

        if (!hasParamsOverride) {
          this.lastProceedWithParamsIndexMap[this.execContextID] = proceedsL - 1;
        }
      }

      if (!(0,js_utl__WEBPACK_IMPORTED_MODULE_0__.isUndefined)(this.finalTrapArgsMap[this.execContextID])) {
        return this.finalTrapArgsMap[this.execContextID];
      }

      return trapArgs;
    }

    executeAroundAdvice(trapArgs, advice, rule, proceed) {
      trapArgs = this.getFinalTrapArgs(trapArgs);
      !this.executedAtLeastOnce && (0,_common_throwErrorIfDoesNotImplement__WEBPACK_IMPORTED_MODULE_1__.throwErrorIfDoesNotImplement)(superclass.prototype, "executeAroundAdvice", superclass);
      return super.executeAroundAdvice(trapArgs, advice, rule, proceed);
    }

    executeAfterAdvice(trapArgs, advice, rule, returnValue) {
      trapArgs = this.getFinalTrapArgs(trapArgs);
      !this.executedAtLeastOnce && (0,_common_throwErrorIfDoesNotImplement__WEBPACK_IMPORTED_MODULE_1__.throwErrorIfDoesNotImplement)(superclass.prototype, "executeAfterAdvice", superclass);
      super.executeAfterAdvice(trapArgs, advice, rule, returnValue);
    }

    performUnderlyingOperation(trapArgs) {
      trapArgs = this.getFinalTrapArgs(trapArgs);
      !this.executedAtLeastOnce && (0,_common_throwErrorIfDoesNotImplement__WEBPACK_IMPORTED_MODULE_1__.throwErrorIfDoesNotImplement)(superclass.prototype, "performUnderlyingOperation", superclass);
      return super.performUnderlyingOperation(trapArgs);
    }

    executeProceedCallback(trapArgs, rule, returnValue, callback) {
      trapArgs = this.getFinalTrapArgs(trapArgs);
      !this.executedAtLeastOnce && (0,_common_throwErrorIfDoesNotImplement__WEBPACK_IMPORTED_MODULE_1__.throwErrorIfDoesNotImplement)(superclass.prototype, "executeProceedCallback", superclass);
      return super.executeProceedCallback(trapArgs, rule, returnValue, callback);
    }

  }, _temp);
  Object.defineProperty(NewClass, "name", {
    value: `WithFunctionTrapExecutor(${superclass.name})`,
    configurable: true
  });
  return NewClass;
};
//# sourceMappingURL=withFunctionTrapExecutor.js.map

/***/ }),

/***/ "./node_modules/pigretto/dist/es6/trap/reflect/reflectApply.js":
/*!*********************************************************************!*\
  !*** ./node_modules/pigretto/dist/es6/trap/reflect/reflectApply.js ***!
  \*********************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ reflectApply)
/* harmony export */ });
/*
 * Copyright (c) 2020 Anton Bagdatyev (Tonix)
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */
function reflectApply(target, thisArg, argumentsList) {
  return Reflect.apply(target, thisArg, argumentsList);
}
//# sourceMappingURL=reflectApply.js.map

/***/ }),

/***/ "./node_modules/pigretto/dist/es6/trap/reflect/reflectConstruct.js":
/*!*************************************************************************!*\
  !*** ./node_modules/pigretto/dist/es6/trap/reflect/reflectConstruct.js ***!
  \*************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ reflectConstruct)
/* harmony export */ });
/*
 * Copyright (c) 2020 Anton Bagdatyev (Tonix)
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */
function reflectConstruct(target, argumentsList, newTarget) {
  return newTarget ? Reflect.construct(target, argumentsList, newTarget) : new target(...argumentsList);
}
//# sourceMappingURL=reflectConstruct.js.map

/***/ }),

/***/ "./node_modules/pigretto/dist/es6/trap/reflect/reflectGet.js":
/*!*******************************************************************!*\
  !*** ./node_modules/pigretto/dist/es6/trap/reflect/reflectGet.js ***!
  \*******************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ reflectGet)
/* harmony export */ });
/*
 * Copyright (c) 2020 Anton Bagdatyev (Tonix)
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */
function reflectGet(target, property, receiver) {
  return Reflect.get(target, property, receiver);
}
//# sourceMappingURL=reflectGet.js.map

/***/ }),

/***/ "./node_modules/pigretto/dist/es6/trap/reflect/reflectSet.js":
/*!*******************************************************************!*\
  !*** ./node_modules/pigretto/dist/es6/trap/reflect/reflectSet.js ***!
  \*******************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ reflectSet)
/* harmony export */ });
/*
 * Copyright (c) 2020 Anton Bagdatyev (Tonix)
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */
function reflectSet(target, property, value, receiver) {
  return Reflect.set(target, property, value, receiver);
}
//# sourceMappingURL=reflectSet.js.map

/***/ }),

/***/ "./node_modules/pigretto/dist/es6/trap/trapHandlerForRules.js":
/*!********************************************************************!*\
  !*** ./node_modules/pigretto/dist/es6/trap/trapHandlerForRules.js ***!
  \********************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ trapHandlerForRules)
/* harmony export */ });
/* harmony import */ var _rules_rulesGenerator__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ../rules/rulesGenerator */ "./node_modules/pigretto/dist/es6/rules/rulesGenerator.js");
/* harmony import */ var _rules_parser_parseRule__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ../rules/parser/parseRule */ "./node_modules/pigretto/dist/es6/rules/parser/parseRule.js");
/* harmony import */ var _Trap__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./Trap */ "./node_modules/pigretto/dist/es6/trap/Trap.js");
function ownKeys(object, enumerableOnly) { var keys = Object.keys(object); if (Object.getOwnPropertySymbols) { var symbols = Object.getOwnPropertySymbols(object); if (enumerableOnly) symbols = symbols.filter(function (sym) { return Object.getOwnPropertyDescriptor(object, sym).enumerable; }); keys.push.apply(keys, symbols); } return keys; }

function _objectSpread(target) { for (var i = 1; i < arguments.length; i++) { var source = arguments[i] != null ? arguments[i] : {}; if (i % 2) { ownKeys(Object(source), true).forEach(function (key) { _defineProperty(target, key, source[key]); }); } else if (Object.getOwnPropertyDescriptors) { Object.defineProperties(target, Object.getOwnPropertyDescriptors(source)); } else { ownKeys(Object(source)).forEach(function (key) { Object.defineProperty(target, key, Object.getOwnPropertyDescriptor(source, key)); }); } } return target; }

function _defineProperty(obj, key, value) { if (key in obj) { Object.defineProperty(obj, key, { value: value, enumerable: true, configurable: true, writable: true }); } else { obj[key] = value; } return obj; }

/*
 * Copyright (c) 2020 Anton Bagdatyev (Tonix)
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */



/**
 * Generates a new trap handler object for the given rules.
 *
 * @param {Object|Array} proxyRules The proxy rules.
 * @return {Object} The new trap handler object for the given rules.
 */

function trapHandlerForRules(proxyRules) {
  const trap = new _Trap__WEBPACK_IMPORTED_MODULE_0__["default"]();
  const rules = (0,_rules_rulesGenerator__WEBPACK_IMPORTED_MODULE_1__["default"])(proxyRules);

  for (const {
    rule,
    pointcut
  } of rules) {
    const parsedRule = (0,_rules_parser_parseRule__WEBPACK_IMPORTED_MODULE_2__["default"])(rule);
    trap.addPointcutRule(pointcut, parsedRule);
  }

  return _objectSpread(_objectSpread(_objectSpread(_objectSpread({}, trap.hasGets() ? {
    // Trap for property access (getting) and method call.
    get(target, property, receiver) {
      return trap.get(target, property, receiver);
    }

  } : {}), trap.hasSets() ? {
    // Trap for property access (setting).
    set(target, property, value, receiver) {
      const updateWasSuccessful = trap.set(target, property, value, receiver);
      return updateWasSuccessful;
    }

  } : {}), trap.hasApplies() ? {
    // Trap for function call.
    apply(target, thisArg, argumentsList) {
      return trap.apply(target, thisArg, argumentsList);
    }

  } : {}), trap.hasConstructs() ? {
    // Trap for object construction with the "new" operator.
    construct(target, argumentsList, newTarget) {
      return trap.construct(target, argumentsList, newTarget);
    }

  } : {});
}
//# sourceMappingURL=trapHandlerForRules.js.map

/***/ }),

/***/ "./node_modules/pigretto/node_modules/escape-string-regexp/index.js":
/*!**************************************************************************!*\
  !*** ./node_modules/pigretto/node_modules/escape-string-regexp/index.js ***!
  \**************************************************************************/
/***/ ((module) => {



const matchOperatorsRegex = /[|\\{}()[\]^$+*?.-]/g;

module.exports = string => {
	if (typeof string !== 'string') {
		throw new TypeError('Expected a string');
	}

	return string.replace(matchOperatorsRegex, '\\$&');
};


/***/ }),

/***/ "jquery":
/*!******************************************************************************************!*\
  !*** external {"commonjs":"jquery","commonjs2":"jquery","amd":"jquery","root":"jQuery"} ***!
  \******************************************************************************************/
/***/ ((module) => {

module.exports = __WEBPACK_EXTERNAL_MODULE_jquery__;

/***/ })

/******/ 	});
/************************************************************************/
/******/ 	// The module cache
/******/ 	var __webpack_module_cache__ = {};
/******/ 	
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/ 		// Check if module is in cache
/******/ 		var cachedModule = __webpack_module_cache__[moduleId];
/******/ 		if (cachedModule !== undefined) {
/******/ 			return cachedModule.exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = __webpack_module_cache__[moduleId] = {
/******/ 			// no module.id needed
/******/ 			// no module.loaded needed
/******/ 			exports: {}
/******/ 		};
/******/ 	
/******/ 		// Execute the module function
/******/ 		__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 	
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/compat get default export */
/******/ 	(() => {
/******/ 		// getDefaultExport function for compatibility with non-harmony modules
/******/ 		__webpack_require__.n = (module) => {
/******/ 			var getter = module && module.__esModule ?
/******/ 				() => (module['default']) :
/******/ 				() => (module);
/******/ 			__webpack_require__.d(getter, { a: getter });
/******/ 			return getter;
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/define property getters */
/******/ 	(() => {
/******/ 		// define getter functions for harmony exports
/******/ 		__webpack_require__.d = (exports, definition) => {
/******/ 			for(var key in definition) {
/******/ 				if(__webpack_require__.o(definition, key) && !__webpack_require__.o(exports, key)) {
/******/ 					Object.defineProperty(exports, key, { enumerable: true, get: definition[key] });
/******/ 				}
/******/ 			}
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/global */
/******/ 	(() => {
/******/ 		__webpack_require__.g = (function() {
/******/ 			if (typeof globalThis === 'object') return globalThis;
/******/ 			try {
/******/ 				return this || new Function('return this')();
/******/ 			} catch (e) {
/******/ 				if (typeof window === 'object') return window;
/******/ 			}
/******/ 		})();
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/hasOwnProperty shorthand */
/******/ 	(() => {
/******/ 		__webpack_require__.o = (obj, prop) => (Object.prototype.hasOwnProperty.call(obj, prop))
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	(() => {
/******/ 		// define __esModule on exports
/******/ 		__webpack_require__.r = (exports) => {
/******/ 			if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 				Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 			}
/******/ 			Object.defineProperty(exports, '__esModule', { value: true });
/******/ 		};
/******/ 	})();
/******/ 	
/************************************************************************/
var __webpack_exports__ = {};
// This entry need to be wrapped in an IIFE because it need to be isolated against other modules in the chunk.
(() => {
/*!**********************!*\
  !*** ./src/index.js ***!
  \**********************/
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ initPlugin)
/* harmony export */ });
/* harmony import */ var jquery__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! jquery */ "jquery");
/* harmony import */ var jquery__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(jquery__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _jQueryMultiselectCheckbox__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./jQueryMultiselectCheckbox */ "./src/jQueryMultiselectCheckbox.js");
/* harmony import */ var _defaultOptions__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./defaultOptions */ "./src/defaultOptions.js");
/*
 * Copyright (c) 2021 Anton Bagdatyev (Tonix)
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */



/**
 * @type {string}
 */

var JQUERY_PLUGIN_NAMESPACE = "multiselectCheckbox";
/**
 * @type {Object}
 */

var globalObject = typeof __webpack_require__.g !== "undefined" ? __webpack_require__.g : window;
/**
 * @type {jQuery|undefined}
 */

var package$ = (jquery__WEBPACK_IMPORTED_MODULE_0___default()) && (jquery__WEBPACK_IMPORTED_MODULE_0___default().fn) && (jquery__WEBPACK_IMPORTED_MODULE_0___default()) || void 0;
/**
 * @type {Function}
 */

var initPluginID = function () {
  var nextInitPluginID = 0;
  return function ($) {
    if (!$.fn.multiselectCheckboxInitPluginID) {
      nextInitPluginID++;
      $.fn.multiselectCheckboxInitPluginID = nextInitPluginID;
    }

    return $.fn.multiselectCheckboxInitPluginID;
  };
}();
/**
 * @type {Object}
 */


var pluginIDMap = {};
/**
 * Initializes the plugin.
 *
 * @param {jQuery} $ jQuery.
 * @return {undefined}
 */

function initPlugin($) {
  var anotherPlugin = $.fn.multiselectCheckbox;
  var anotherInitPluginID = $.fn.multiselectCheckboxInitPluginID;
  var pluginID = initPluginID($);
  pluginIDMap[pluginID] = pluginIDMap[pluginID] ? pluginIDMap[pluginID] : {
    defaultOptions: (0,_defaultOptions__WEBPACK_IMPORTED_MODULE_2__["default"])($, globalObject, globalObject.document),
    jQueryMultiselectCheckbox: (0,_jQueryMultiselectCheckbox__WEBPACK_IMPORTED_MODULE_1__["default"])($, globalObject, globalObject.document)
  };
  var _pluginIDMap$pluginID = pluginIDMap[pluginID],
      defaultOptions = _pluginIDMap$pluginID.defaultOptions,
      jQueryMultiselectCheckbox = _pluginIDMap$pluginID.jQueryMultiselectCheckbox;

  $.fn.multiselectCheckbox = function multiselectCheckbox(option) {
    for (var _len = arguments.length, args = new Array(_len > 1 ? _len - 1 : 0), _key = 1; _key < _len; _key++) {
      args[_key - 1] = arguments[_key];
    }

    var result;
    this.each(function (_, element) {
      var $element = $(element);
      var isDestroy = option === "destroy";
      var plugin = $element.data(JQUERY_PLUGIN_NAMESPACE);

      if (!plugin) {
        if (isDestroy) {
          return;
        }

        var options = $.extend({}, defaultOptions, $element.data(), $.isPlainObject(option) && option);
        plugin = new jQueryMultiselectCheckbox(element, options);
        $element.data(JQUERY_PLUGIN_NAMESPACE, plugin);
      }

      if (typeof option === "string") {
        var fn = plugin[option];

        if (typeof fn === "function") {
          result = fn.apply(plugin, args);

          if (result === plugin) {
            result = void 0;
          }
        }

        if (isDestroy) {
          $element.removeData(JQUERY_PLUGIN_NAMESPACE);
        }
      }
    });
    return typeof result !== "undefined" ? result : this;
  };

  $.fn.multiselectCheckbox.Constructor = jQueryMultiselectCheckbox;

  $.fn.multiselectCheckbox.setDefaults = function setDefaults(optionsFactory) {
    var options = typeof optionsFactory === "function" ? optionsFactory($, globalObject, globalObject.document) : optionsFactory;
    $.extend(defaultOptions, $.isPlainObject(options) && options);
  };

  $.fn.multiselectCheckbox.noConflict = function noConflict() {
    $.fn.multiselectCheckbox = anotherPlugin;
    $.fn.multiselectCheckboxInitPluginID = anotherInitPluginID;
    return $.fn.multiselectCheckbox;
  };
}
package$ && initPlugin(package$);
})();

__webpack_exports__ = __webpack_exports__["default"];
/******/ 	return __webpack_exports__;
/******/ })()
;
});
//# sourceMappingURL=jquery-multiselect-checkbox.js.map