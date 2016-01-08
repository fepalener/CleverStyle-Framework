// Generated by LiveScript 1.4.0
/**
 * @package   CleverStyle CMS
 * @author    Nazar Mokrynskyi <nazar@mokrynskyi.com>
 * @copyright Copyright (c) 2014-2016, Nazar Mokrynskyi
 * @license   MIT License, see license.txt
 */
(function(){
  var ready_original, functions, ready;
  if (document.body.hasAttribute('unresolved')) {
    document.body.setAttribute('unresolved-transition', '');
  }
  /**
   * Fix for jQuery "ready" event, trigger it after "WebComponentsReady" event triggered by WebComponents.js
   */
  ready_original = $.fn.ready;
  functions = [];
  ready = false;
  $.fn.ready = function(fn){
    functions.push(fn);
  };
  document.addEventListener('WebComponentsReady', (function(){
    function ready_callback(){
      if (!ready) {
        setTimeout(function(){
          document.body.removeAttribute('unresolved-transition');
        }, 200);
        document.removeEventListener('WebComponentsReady', ready_callback);
        Polymer.updateStyles();
        ready = true;
        $.fn.ready = ready_original;
        functions.forEach(function(it){
          it();
        });
        functions = [];
      }
    }
    return ready_callback;
  }()));
}).call(this);
