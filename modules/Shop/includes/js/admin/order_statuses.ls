/**
 * @package   Shop
 * @category  modules
 * @author    Nazar Mokrynskyi <nazar@mokrynskyi.com>
 * @copyright Copyright (c) 2014-2016, Nazar Mokrynskyi
 * @license   MIT License, see license.txt
 */
$ <-! require(['jquery'], _)
<-! $
L			= cs.Language('shop_')
make_modal	= (types, title, action) ->
	types	=
		for index, type of types
			"""<option value="#index">#type</option>"""
	types	= types.join('')
	modal	= $(cs.ui.simple_modal("""<form is="cs-form">
		<h3 class="cs-text-center">#title</h3>
		<label>#{L.title}</label>
		<input is="cs-input-text" name="title" required>
		<label>#{L.color}</label>
		<input is="cs-input-text" name="color"><br>
		<input is="cs-input-text" type="color">
		<label>#{L.order_status_type}</label>
		<select is="cs-select" name="type" required>#types</select>
		<label>#{L.send_update_status_email}</label>
		<div>
			<label is="cs-label-button"><input type="radio" name="send_update_status_email" value="1" checked> #{L.yes}</label>
			<label is="cs-label-button"><input type="radio" name="send_update_status_email" value="0"> #{L.no}</label>
		</div>
		<label>#{L.comment_used_in_email}</label>
		<textarea is="cs-textarea" autosize name="comment"></textarea>
		<br>
		<button is="cs-button" primary type="submit">#action</button>
	</form>"""))
	modal.find('[type=color]').change !->
		modal.find('[name=color]').val(
			$(@).val()
		)
	modal.find('[name=color]').change !->
		modal.find('[type=color]').val(
			$(@).val()
		)
	modal
$('html')
	.on('mousedown', '.cs-shop-order-status-add', !->
		cs.api('get api/Shop/admin/order_statuses/types').then (types) !->
			modal = make_modal(types, L.order_status_addition, L.add)
			modal.find('form').submit ->
				cs.api('post api/Shop/admin/order_statuses', @)
					.then -> cs.ui.alert(L.added_successfully)
					.then(location~reload)
				false
	)
	.on('mousedown', '.cs-shop-order-status-edit', !->
		id = $(@).data('id')
		cs.api([
			'get api/Shop/admin/order_statuses/types'
			"get api/Shop/admin/order_statuses/#id"
		]).then ([types, type]) !->
			modal = make_modal(types, L.order_status_edition, L.edit)
			modal.find('form').submit ->
				cs.api("put api/Shop/admin/order_statuses/#id", @)
					.then -> cs.ui.alert(L.edited_successfully)
					.then(location~reload)
				false
			modal.find('[name=title]').val(type.title)
			modal.find('[name=color]').val(type.color)
			modal.find('[type=color]').val(type.color)
			modal.find('[name=type]').val(type.type)
			modal.find("[name=send_update_status_email][value=#{type.send_update_status_email}]").prop('checked', true)
			modal.find('[name=comment]').val(type.comment)
	)
	.on('mousedown', '.cs-shop-order-status-delete', !->
		id = $(@).data('id')
		cs.ui.confirm(L.sure_want_to_delete)
			.then -> cs.api("delete api/Shop/admin/order_statuses/#id")
			.then -> cs.ui.alert(L.deleted_successfully)
			.then(location~reload)
	)
