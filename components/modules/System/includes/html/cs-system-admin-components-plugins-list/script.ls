/**
 * @package    CleverStyle CMS
 * @subpackage System module
 * @category   modules
 * @author     Nazar Mokrynskyi <nazar@mokrynskyi.com>
 * @copyright  Copyright (c) 2015, Nazar Mokrynskyi
 * @license    MIT License, see license.txt
 */
L = cs.Language
active_switch	= (disabled, enabled) ->
	switch @active
	| 0		=> disabled
	| 1		=> enabled
Polymer(
	'is'		: 'cs-system-admin-components-plugins-list'
	behaviors	: [
		cs.Polymer.behaviors.Language
		cs.Polymer.behaviors.admin.System.components
		cs.Polymer.behaviors.admin.System.upload
	]
	ready : !->
		@reload()
	reload : ->
		plugins <~! $.getJSON('api/System/admin/plugins', _)
		plugins.forEach (plugin) !->
			active_switch_local		= active_switch.bind(plugin)
			plugin.class			= active_switch_local('cs-block-warning cs-text-warning', 'cs-block-success cs-text-success')
			plugin.icon				= active_switch_local('minus', 'check')
			plugin.icon_text		= active_switch_local(L.disabled, L.enabled)
			plugin.name_localized	= L[plugin.name] || plugin.name.replace('_', ' ')
			do !->
				for prop in ['license', 'readme']
					if plugin[prop]?.type
						tag						= if plugin[prop].type == 'txt' then 'pre' else 'div'
						plugin[prop].content	= "<#tag>#{plugin[prop].content}</#tag>"
			if plugin.meta
				plugin.info	= let (@ = plugin.meta)
					L.plugin_info(
						@package,
						@version,
						@description,
						@author,
						@website || L.none,
						@license,
						if @provide then [].concat(@provide).join(', ') else L.none,
						if @require then [].concat(@require).join(', ') else L.none,
						if @conflict then [].concat(@conflict).join(', ') else L.none,
						if @optional then [].concat(@optional).join(', ') else L.none,
						if @multilingual && @multilingual.indexOf('interface') != -1 then L.yes else L.no,
						if @multilingual && @multilingual.indexOf('content') != -1 then L.yes else L.no,
						if @languages then @languages.join(', ') else L.none
					)
		@set('plugins', plugins)
	/**
	 * Provides next events:
	 *  admin/System/components/plugins/enable/before
	 *  {name : module_name}
	 *
	 *  admin/System/components/plugins/enable/after
	 *  {name : module_name}
	 */
	_enable : (e) !->
		@_enable_component(e.model.plugin.name, 'plugin', e.model.plugin.meta)
	/**
	 * Provides next events:
	 *  admin/System/components/plugins/disable/before
	 *  {name : plugin_name}
	 *
	 *  admin/System/components/plugins/disable/after
	 *  {name : plugin_name}
	 */
	_disable : (e) !->
		@_disable_component(e.model.plugin.name, 'plugin')
	_remove_completely : (e) !->
		@_remove_completely_component(e.model.plugin.name, 'plugin')
	/**
	 * Provides next events:
	 *  admin/System/components/plugins/update/before
	 *  {name : plugin_name}
	 *
	 *  admin/System/components/plugins/update/after
	 *  {name : plugin_name}
	 */
	_upload : !->
		@_upload_package(@$.file).then (meta) !~>
			if meta.category != 'plugins' || !meta.package || !meta.version
				cs.ui.notify(L.this_is_not_plugin_installer_file, 'error', 5)
				return
			# Lookign for already installed plugin
			for plugin in @plugins
				if plugin.name == meta.package
					@_update_component(plugin.meta, meta)
					return
			# If plugin is not installed yet - lest just extract it
			@_extract(meta)
	_extract : (meta) !->
		$.ajax(
			url		: 'api/System/admin/plugins'
			type	: 'extract'
			success	: !~>
				@reload()
				@_enable_component(meta.package, 'plugin', meta)
		)
)
