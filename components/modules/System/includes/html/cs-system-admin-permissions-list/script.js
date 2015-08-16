// Generated by CoffeeScript 1.9.3

/**
 * @package    CleverStyle CMS
 * @subpackage System module
 * @category   modules
 * @author     Nazar Mokrynskyi <nazar@mokrynskyi.com>
 * @copyright  Copyright (c) 2015, Nazar Mokrynskyi
 * @license    MIT License, see license.txt
 */

(function() {
  var L;

  L = cs.Language;

  Polymer({
    'is': 'cs-system-admin-permissions-list',
    behaviors: [cs.Polymer.behaviors.Language],
    properties: {
      tooltip_animation: '{animation:true,delay:200}',
      permissions: []
    },
    ready: function() {
      this.reload();
      return this.workarounds(this.shadowRoot);
    },
    workarounds: function(target) {
      var timeout;
      timeout = null;
      return cs.observe_inserts_on(target, (function(_this) {
        return function() {
          if (timeout) {
            clearTimeout(timeout);
          }
          return timeout = setTimeout((function() {
            timeout = null;
            return $(target).cs().tooltips_inside();
          }), 100);
        };
      })(this));
    },
    reload: function() {
      return $.when($.getJSON('api/System/admin/blocks'), $.getJSON('api/System/admin/permissions')).done((function(_this) {
        return function(blocks, permissions) {
          var group, id, index_to_title, label, labels, permissions_list, ref;
          index_to_title = {};
          blocks[0].forEach(function(block) {
            return index_to_title[block.index] = block.title;
          });
          permissions_list = [];
          ref = permissions[0];
          for (group in ref) {
            labels = ref[group];
            for (label in labels) {
              id = labels[label];
              permissions_list.push({
                id: id,
                group: group,
                label: label,
                description: group === 'Block' ? index_to_title[label] : ''
              });
            }
          }
          return _this.set('permissions', permissions_list);
        };
      })(this));
    },
    add_permission: function() {
      return $.cs.simple_modal("<h3>" + L.adding_permission + "</h3>\n<p class=\"uk-alert uk-alert-danger\">" + L.changing_settings_warning + "</p>\n<cs-system-admin-permissions-form/>").on('hide.uk.modal', (function(_this) {
        return function() {
          return _this.reload();
        };
      })(this));
    },
    edit_permission: function(e) {
      var permission;
      permission = e.model.permission;
      return $.cs.simple_modal("<h3>" + (L.editing_permission(permission.group + '/' + permission.label)) + "</h3>\n<p class=\"uk-alert uk-alert-danger\">" + L.changing_settings_warning + "</p>\n<cs-system-admin-permissions-form permission_id=\"" + permission.id + "\" label=\"" + (cs.prepare_attr_value(permission.label)) + "\" group=\"" + (cs.prepare_attr_value(permission.group)) + "\"/>").on('hide.uk.modal', (function(_this) {
        return function() {
          return _this.reload();
        };
      })(this));
    },
    delete_permission: function(e) {
      var permission;
      permission = e.model.permission;
      return UIkit.modal.confirm("<h3>" + (L.sure_delete_permission(permission.group + '/' + permission.label)) + "</h3>\n<p class=\"uk-alert uk-alert-danger\">" + L.changing_settings_warning + "</p>", (function(_this) {
        return function() {
          return $.ajax({
            url: 'api/System/admin/permissions/' + permission.id,
            type: 'delete',
            success: function() {
              UIkit.notify(L.changes_saved.toString(), 'success');
              return _this.splice('permissions', e.model.index, 1);
            }
          });
        };
      })(this));
    }
  });

}).call(this);