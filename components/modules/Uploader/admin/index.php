<?php
/**
 * @package   Uploader
 * @category  modules
 * @author    Nazar Mokrynskyi <nazar@mokrynskyi.com>
 * @copyright Copyright (c) 2015-2016, Nazar Mokrynskyi
 * @license   MIT License, see license.txt
 */
namespace cs;
use
	h;

$L           = Language::instance();
$Page        = Page::instance();
$module_data = Config::instance()->module('Uploader');
if (isset($_POST['max_file_size'], $_POST['confirmation_time'])) {
	if ($module_data->set(
		[
			'max_file_size'     => xap($_POST['max_file_size']),
			'confirmation_time' => (int)$_POST['confirmation_time']
		]
	)
	) {
		$Page->success($L->changes_saved);
	} else {
		$Page->warning($L->changes_save_error);
	}
}

$L = Language::instance();
$Page->content(
	h::{'form[is=cs-form]'}(
		h::label("$L->upload_size_limit (b, kb, mb, gb)").
		h::{'input[is=cs-input-text][compact][name=max_file_size]'}(
			[
				'value' => $module_data->max_file_size
			]
		).
		h::{'label info'}('uploader_confirmation_time').
		h::{'input[is=cs-input-text][compact][name=confirmation_time]'}(
			[
				'value' => $module_data->confirmation_time
			]
		).
		$L->seconds.
		h::{'p button[is=cs-button][type=submit]'}(
			$L->save,
			[
				'tooltip' => $L->save_info
			]
		)
	)
);
