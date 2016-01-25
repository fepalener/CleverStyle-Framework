// Generated by LiveScript 1.4.0
/**
 * @package   CleverStyle CMS
 * @author    Nazar Mokrynskyi <nazar@mokrynskyi.com>
 * @copyright Copyright (c) 2014-2016, Nazar Mokrynskyi
 * @license   MIT License, see license.txt
 */
(function(){
  var ready_callback, ref$, value, date;
  if (document.body.hasAttribute('unresolved')) {
    document.body.setAttribute('unresolved-transition', '');
  }
  ready_callback = function(){
    Polymer.updateStyles();
    setTimeout(function(){
      document.body.removeAttribute('unresolved');
      setTimeout(function(){
        document.body.removeAttribute('unresolved-transition');
      }, 250);
    });
  };
  if ((ref$ = window.WebComponents) != null && ref$.flags) {
    document.addEventListener('WebComponentsReady', ready_callback);
  } else {
    addEventListener('load', ready_callback);
  }
  if (document.cookie.indexOf('shadow_dom=1') === -1) {
    value = 'registerElement' in document && 'import' in document.createElement('link') && 'content' in document.createElement('template') ? 1 : 0;
    date = new Date();
    date.setTime(date.getTime() + 30 * 24 * 3600 * 1000);
    document.cookie = ("shadow_dom=" + value + "; path=/; expires=") + date.toGMTString();
  }
}).call(this);
