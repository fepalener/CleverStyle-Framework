<?php
/**
 * @package		CleverStyle CMS
 * @subpackage	System module
 * @category	modules
 * @author		Nazar Mokrynskyi <nazar@mokrynskyi.com>
 * @copyright	Copyright (c) 2011-2012, Nazar Mokrynskyi
 * @license		MIT License, see license.txt
 */
global $Config, $Index;
if (isset($Config->route[1]))  {
	header('Location: '.urldecode($Config->route[1]));
	code_header(301);
	interface_off();
	$Index->stop	= true;
}