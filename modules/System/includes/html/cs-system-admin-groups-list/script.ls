/**
 * @package    CleverStyle Framework
 * @subpackage System module
 * @category   modules
 * @author     Nazar Mokrynskyi <nazar@mokrynskyi.com>
 * @copyright  Copyright (c) 2015-2016, Nazar Mokrynskyi
 * @license    MIT License, see license.txt
 */
L				= cs.Language('system_admin_groups_')
ADMIN_GROUP_ID	= 1
USER_GROUP_ID	= 2
Polymer(
	'is'		: 'cs-system-admin-groups-list'
	behaviors	: [
		cs.Polymer.behaviors.Language('system_admin_groups_')
	]
	properties	:
		groups	: []
	ready : !->
		@reload()
	reload : !->
		cs.api('get api/System/admin/groups').then (groups) !~>
			groups.forEach (group) !->
				group.allow_to_delete	= group.id !~= ADMIN_GROUP_ID && group.id !~= USER_GROUP_ID
			@set('groups', groups)
	add_group : !->
		cs.ui.simple_modal("""
			<h3>#{L.group_addition}</h3>
			<cs-system-admin-groups-form/>
		""").addEventListener('close', @~reload)
	edit_group : (e) !->
		group	= e.model.group
		cs.ui.simple_modal("""
			<h3>#{L.editing_group(group.title)}</h3>
			<cs-system-admin-groups-form group_id="#{group.id}"/>
		""").addEventListener('close', @~reload)
	delete_group : (e) !->
		group	= e.model.group
		cs.ui.confirm(L.sure_delete_group(group.title))
			.then -> cs.api('delete api/System/admin/groups/' + group.id)
			.then !~>
				cs.ui.notify(L.changes_saved, 'success', 5)
				@splice('groups', e.model.index, 1)
	edit_permissions : (e) !->
		group	= e.model.group
		title	= L.permissions_for_group(group.title)
		cs.ui.simple_modal("""
			<h2>#{title}</h2>
			<cs-system-admin-permissions-for group="#{group.id}" for="group"/>
		""")
)
