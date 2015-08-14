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
  var ADMIN_GROUP_ID, BOT_GROUP_ID, L, USER_GROUP_ID;

  L = cs.Language;

  ADMIN_GROUP_ID = 1;

  USER_GROUP_ID = 2;

  BOT_GROUP_ID = 3;

  Polymer({
    'is': 'cs-system-admin-groups-list',
    behaviors: [cs.Polymer.behaviors.Language],
    properties: {
      tooltip_animation: '{animation:true,delay:200}',
      groups: []
    },
    ready: function() {
      this.reload();
      this.workarounds(this.shadowRoot);
      return cs.observe_inserts_on(this.shadowRoot, this.workarounds);
    },
    workarounds: function(target) {
      return $(target).cs().tooltips_inside();
    },
    reload: function() {
      return $.getJSON('api/System/admin/groups', (function(_this) {
        return function(groups) {
          groups.forEach(function(group) {
            return group.allow_to_delete = group.id != ADMIN_GROUP_ID && group.id != USER_GROUP_ID && group.id != BOT_GROUP_ID;
          });
          return _this.set('groups', groups);
        };
      })(this));
    },
    add_group: function() {
      return $.cs.simple_modal("<h3>" + L.adding_a_group + "</h3>\n<cs-system-admin-groups-form/>").on('hide.uk.modal', (function(_this) {
        return function() {
          return _this.reload();
        };
      })(this));
    },
    edit_group: function(e) {
      var group;
      group = e.model.group;
      return $.cs.simple_modal("<h3>" + (L.editing_of_group(group.title)) + "</h3>\n<cs-system-admin-groups-form group_id=\"" + group.id + "\" group_title=\"" + (cs.prepare_attr_value(group.title)) + "\" description=\"" + (cs.prepare_attr_value(group.description)) + "\"/>").on('hide.uk.modal', (function(_this) {
        return function() {
          return _this.reload();
        };
      })(this));
    },
    delete_group: function(e) {
      var group;
      group = e.model.group;
      return UIkit.modal.confirm("<h3>" + (L.sure_delete_group(group.title)) + "</h3>", (function(_this) {
        return function() {
          return $.ajax({
            url: 'api/System/admin/groups/' + group.id,
            type: 'delete',
            success: function() {
              UIkit.notify(L.changes_saved.toString(), 'success');
              return _this.splice('groups', e.model.index, 1);
            }
          });
        };
      })(this));
    },
    edit_permissions: function(e) {
      var group, title;
      group = e.model.group;
      title = L.permissions_for_group(group.title);
      return $.cs.simple_modal("<h2>" + title + "</h2>\n<cs-system-admin-permissions-for group=\"" + group.id + "\" for=\"group\"/>");
    }
  });

}).call(this);
