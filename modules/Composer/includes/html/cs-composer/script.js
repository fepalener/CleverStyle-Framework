// Generated by LiveScript 1.4.0
/**
 * @package   Composer
 * @category  modules
 * @author    Nazar Mokrynskyi <nazar@mokrynskyi.com>
 * @copyright Copyright (c) 2015-2016, Nazar Mokrynskyi
 * @license   MIT License, see license.txt
 */
(function(){
  var L;
  L = cs.Language('composer_');
  Polymer({
    'is': 'cs-composer',
    behaviors: [cs.Polymer.behaviors.Language('composer_')],
    properties: {
      action: String,
      canceled: Boolean,
      force: Boolean,
      'package': String,
      status: String
    },
    ready: function(){
      var method, data, this$ = this;
      cs.Event.once('admin/Composer/canceled', function(){
        this$.canceled = true;
      });
      method = this.action === 'uninstall' ? 'delete' : 'post';
      data = {
        name: this['package'],
        force: this.force
      };
      cs.api(method + " api/Composer", data).then(function(result){
        this$._save_scroll_position();
        this$.status = (function(){
          switch (result.code) {
          case 0:
            return L.updated_successfully;
          case 1:
            return L.update_failed;
          case 2:
            return L.dependencies_conflict;
          }
        }());
        if (result.description) {
          this$.$.result.innerHTML = result.description;
          this$._restore_scroll_position();
        }
        if (!result.code) {
          setTimeout(function(){
            cs.Event.fire('admin/Composer/updated');
          }, 2000);
        }
      });
      setTimeout(bind$(this, '_update_progress'), 1000);
    },
    _update_progress: function(){
      var this$ = this;
      cs.api('get api/Composer').then(function(data){
        if (this$.status || this$.canceled) {
          return;
        }
        this$._save_scroll_position();
        this$.$.result.innerHTML = data;
        this$._restore_scroll_position();
        setTimeout(bind$(this$, '_update_progress'), 1000);
      });
    },
    _save_scroll_position: function(){
      var ref$;
      this._scroll_after = false;
      if ((ref$ = this.parentElement.$) != null && ref$.content) {
        this._scroll_after = this.parentElement.$.content.scrollHeight - this.parentElement.$.content.offsetHeight === this.parentElement.$.content.scrollTop;
      }
    },
    _restore_scroll_position: function(){
      if (this._scroll_after) {
        this.parentElement.$.content.scrollTop = this.parentElement.$.content.scrollHeight - this.parentElement.$.content.offsetHeight;
      }
    }
  });
  function bind$(obj, key, target){
    return function(){ return (target || obj)[key].apply(obj, arguments) };
  }
}).call(this);
