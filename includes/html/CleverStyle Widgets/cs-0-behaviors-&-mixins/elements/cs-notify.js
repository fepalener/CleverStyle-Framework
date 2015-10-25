// Generated by LiveScript 1.4.0
/**
 * @package   CleverStyle Widgets
 * @author    Nazar Mokrynskyi <nazar@mokrynskyi.com>
 * @copyright Copyright (c) 2015, Nazar Mokrynskyi
 * @license   MIT License, see license.txt
 */
(function(){
  var in_progress;
  in_progress = false;
  Polymer.cs.behaviors.csNotify = [
    Polymer.cs.behaviors['this'], {
      properties: {
        bottom: {
          reflectToAttribute: true,
          type: Boolean
        },
        content: String,
        error: {
          reflectToAttribute: true,
          type: Boolean
        },
        left: {
          reflectToAttribute: true,
          type: Boolean
        },
        noIcon: {
          reflectToAttribute: true,
          type: Boolean
        },
        right: {
          reflectToAttribute: true,
          type: Boolean
        },
        show: {
          reflectToAttribute: true,
          type: Boolean
        },
        success: {
          reflectToAttribute: true,
          type: Boolean
        },
        timeout: Number,
        top: {
          reflectToAttribute: true,
          type: Boolean
        },
        warning: {
          reflectToAttribute: true,
          type: Boolean
        }
      },
      listeners: {
        'content.tap': '_tap',
        transitionend: '_transitionend'
      },
      attached: function(){
        this.last_node = this.parentNode;
        if (this.parentNode.tagName !== 'HTML') {
          document.documentElement.appendChild(this);
          return;
        }
        if (!this.bottom && !this.top) {
          this.top = true;
        }
        setTimeout(this._show.bind(this), 0);
      },
      _tap: function(e){
        if (e.target === this.$.content || e.target === this.$.icon) {
          this._hide();
        }
      },
      _transitionend: function(){
        var ref$, in_progress;
        if (!this.show) {
          if ((ref$ = this.parentNode) != null) {
            ref$.removeChild(this);
          }
        }
        if (this.timeout) {
          setTimeout(this._hide.bind(this), this.timeout * 1000);
          this.timeout = 0;
        }
        if (in_progress === this) {
          in_progress = false;
        }
      },
      _show: function(){
        var in_progress, this$ = this;
        if (!in_progress) {
          in_progress = this;
        } else {
          setTimeout(this._show.bind(this), 100);
          return;
        }
        if (this.content) {
          this.innerHTML = this.content;
        }
        this._for_similar(function(child){
          var interesting_margin;
          interesting_margin = this$.top ? 'marginTop' : 'marginBottom';
          if (child !== this$ && parseFloat(child.style[interesting_margin] || 0) >= parseFloat(this$.style[interesting_margin] || 0)) {
            child._shift();
          }
        });
        this._initialized = true;
        this.show = true;
        this.fire('show');
      },
      _hide: function(){
        var in_progress, interesting_margin, this$ = this;
        if (!this.show) {
          return;
        }
        if (!in_progress) {
          in_progress = this;
        } else {
          setTimeout(this._hide.bind(this), 100);
          return;
        }
        this.show = false;
        interesting_margin = this.top ? 'marginTop' : 'marginBottom';
        this._for_similar(function(child){
          if (parseFloat(child.style[interesting_margin] || 0) > parseFloat(this$.style[interesting_margin] || 0)) {
            child._unshift();
          }
        });
        this.fire('hide');
      },
      _for_similar: function(callback){
        var tagName, bottom, left, right, top, i$, ref$, len$, child;
        tagName = this.tagName;
        bottom = this.bottom;
        left = this.left;
        right = this.right;
        top = this.top;
        for (i$ = 0, len$ = (ref$ = this.parentNode.children).length; i$ < len$; ++i$) {
          child = ref$[i$];
          if (child.show && child.tagName === tagName && child.bottom === bottom && child.left === left && child.right === right && child.top === top) {
            callback(child);
          }
        }
      },
      _shift: function(){
        var style;
        style = getComputedStyle(this);
        if (this.top) {
          this.style.marginTop = parseFloat(style.marginTop) + parseFloat(style.height) + 'px';
        } else {
          this.style.marginBottom = parseFloat(style.marginBottom) + parseFloat(style.height) + 'px';
        }
      },
      _unshift: function(){
        var style;
        style = getComputedStyle(this);
        if (this.top) {
          this.style.marginTop = parseFloat(style.marginTop) - parseFloat(style.height) + 'px';
        } else {
          this.style.marginBottom = parseFloat(style.marginBottom) - parseFloat(style.height) + 'px';
        }
      }
    }
  ];
}).call(this);
