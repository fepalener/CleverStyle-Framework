// Generated by LiveScript 1.4.0
/**
 * @package    CleverStyle CMS
 * @subpackage DarkEnergy theme
 * @author     Nazar Mokrynskyi <nazar@mokrynskyi.com>
 * @copyright  Copyright (c) 2015-2016, Nazar Mokrynskyi
 * @license    MIT License, see license.txt
 */
(function(){
  document.querySelector('.cs-mobile-menu').addEventListener('click', function(){
    if (this.hasAttribute('show')) {
      this.removeAttribute('show');
    } else {
      this.setAttribute('show', '');
    }
  });
}).call(this);