// Generated by CoffeeScript 1.9.3

/**
 * @package   CleverStyle Widgets
 * @author    Nazar Mokrynskyi <nazar@mokrynskyi.com>
 * @copyright Copyright (c) 2015, Nazar Mokrynskyi
 * @license   MIT License, see license.txt
 */

(function() {
  Polymer({
    'is': 'cs-select',
    'extends': 'select',
    behaviors: [Polymer.cs.behaviors.size, Polymer.cs.behaviors.tight, Polymer.cs.behaviors["this"], Polymer.cs.behaviors.tooltip, Polymer.cs.behaviors.value],
    listeners: {
      'value-changed': '_value_changed'
    },
    properties: {
      selected: {
        notify: true,
        observer: '_selected_changed',
        type: Object
      }
    },
    ready: function() {
      var scroll_once;
      scroll_once = (function(_this) {
        return function() {
          _this._scroll_to_selected();
          return document.removeEventListener('WebComponentsReady', scroll_once);
        };
      })(this);
      document.addEventListener('WebComponentsReady', scroll_once);
      (function(_this) {
        return (function() {
          var callback, timeout;
          timeout = null;
          callback = function() {
            clearTimeout(timeout);
            return timeout = setTimeout((function() {
              var font_size, height_in_px;
              _this.removeEventListener(callback);
              if (_this.selected !== void 0) {
                _this._selected_changed(_this.selected);
              }
              height_in_px = _this.querySelector('option').getBoundingClientRect().height * _this.size;
              font_size = parseFloat(getComputedStyle(_this).fontSize);
              _this.style.height = "calc(" + height_in_px + "em / " + font_size + ")";
            }), 100);
          };
          return _this.addEventListener('dom-change', callback);
        });
      })(this)();
    },
    _scroll_to_selected: function() {
      var option, option_height, select_height;
      option = this.querySelector('option');
      if (!option) {
        return;
      }
      option_height = option.getBoundingClientRect().height;
      if (this.size > 1 && this.selectedOptions[0]) {
        this.scrollTop = option_height * (this.selectedIndex - Math.floor(this.size / 2)) + this._number_of_optgroups();
      }
      select_height = this.getBoundingClientRect().height;
      if (select_height >= option_height * (this.querySelectorAll('option').length + this.querySelectorAll('optgroup').length)) {
        this.style.overflowY = 'auto';
      }
    },
    _number_of_optgroups: function() {
      var count, optgroup;
      optgroup = this.selectedOptions[0].parentNode;
      count = 0;
      if (optgroup.tagName === 'OPTGROUP') {
        while (optgroup) {
          ++count;
          optgroup = optgroup.previousElementSibling;
        }
      }
      return count;
    },
    _value_changed: function() {
      var selected;
      selected = [];
      [].slice.call(this.selectedOptions).forEach(function(option) {
        return selected.push(option.value);
      });
      if (!this.multiple) {
        selected = selected[0] || void 0;
      }
      return this.set('selected', selected);
    },
    _selected_changed: function(selected) {
      var s;
      if (selected === void 0) {
        return;
      }
      selected = (function() {
        var i, len, results;
        if (selected instanceof Array) {
          results = [];
          for (i = 0, len = selected.length; i < len; i++) {
            s = selected[i];
            results.push(String(s));
          }
          return results;
        } else {
          return String(selected);
        }
      })();
      return [].slice.call(this.querySelectorAll('option')).forEach(function(option) {
        return option.selected = selected === option.value || (selected instanceof Array && selected.indexOf(option.value) !== -1);
      });
    }
  });

}).call(this);
