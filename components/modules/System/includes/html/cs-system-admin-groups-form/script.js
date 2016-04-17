// Generated by LiveScript 1.4.0
/**
 * @package    CleverStyle CMS
 * @subpackage System module
 * @category   modules
 * @author     Nazar Mokrynskyi <nazar@mokrynskyi.com>
 * @copyright  Copyright (c) 2015-2016, Nazar Mokrynskyi
 * @license    MIT License, see license.txt
 */
(function(){
  Polymer({
    'is': 'cs-system-admin-groups-form',
    behaviors: [cs.Polymer.behaviors.Language('system_admin_groups_')],
    properties: {
      group_id: Number,
      group_title: '',
      group_description: ''
    },
    ready: function(){
      var this$ = this;
      if (this.group_id) {
        $.getJSON('api/System/admin/groups/' + this.group_id, function(arg$){
          this$.group_title = arg$.title, this$.group_description = arg$.description;
        });
      }
    },
    save: function(){
      var this$ = this;
      $.ajax({
        url: 'api/System/admin/groups' + (this.group_id ? '/' + this.group_id : ''),
        type: this.group_id ? 'put' : 'post',
        data: {
          title: this.group_title,
          description: this.group_description
        },
        success: function(){
          cs.ui.notify(this$.L.changes_saved, 'success', 5);
        }
      });
    }
  });
}).call(this);
