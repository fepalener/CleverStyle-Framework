/**
 * @package   Blockchain payment
 * @category  modules
 * @author    Nazar Mokrynskyi <nazar@mokrynskyi.com>
 * @copyright Copyright (c) 2015-2016, Nazar Mokrynskyi
 * @license   MIT License, see license.txt
 */
L = cs.Language('blockchain_payment_')
Polymer(
	is			: 'cs-blockchain-payment-pay'
	properties	:
		description		: ''
		address			: ''
		amount			: Number
		progress_text	:
			type	: String
			value	: L.waiting_for_payment
	ready : !->
		@set('description', JSON.parse(@description))
		@set('text', L.scan_or_transfer(@amount, @address))
		new QRCode(
			@$.qr
			height	: 512
			text	: 'bitcoin:' + @address + '?amount=' + @amount
			width	: 512
		)
		@update_status()
	update_status : !->
		cs.api('get api/Blockchain_payment/' + @.dataset.id).then (data) !~>
			if parseInt(data.confirmed)
				location.reload()
				return
			if parseInt(data.paid)
				@set('progress_text', L.waiting_for_confirmations)
			setTimeout(@~update_status, 5000)
);
