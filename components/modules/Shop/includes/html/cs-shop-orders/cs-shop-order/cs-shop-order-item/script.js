// Generated by CoffeeScript 1.4.0

/**
 * @package       Shop
 * @order_status  modules
 * @author        Nazar Mokrynskyi <nazar@mokrynskyi.com>
 * @copyright     Copyright (c) 2014-2015, Nazar Mokrynskyi
 * @license       MIT License, see license.txt
*/


(function() {

  Polymer({
    ready: function() {
      var $this, discount, href, price, unit_price;
      this.$.img.innerHTML = this.querySelector('#img').outerHTML;
      href = this.querySelector('#link').href;
      if (href) {
        this.$.img.href = href;
        this.$.link.href = href;
      }
      this.item_title = this.querySelector('#link').innerHTML;
      $this = $(this);
      unit_price = $this.data('unit-price');
      price = $this.data('price');
      this.units = $this.data('units');
      this.unit_price_formatted = sprintf(cs.shop.settings.price_formatting, unit_price);
      this.price_formatted = sprintf(cs.shop.settings.price_formatting, price);
      discount = this.units * unit_price - price;
      if (discount) {
        discount = sprintf(cs.shop.settings.price_formatting, discount);
        return this.$.discount.innerHTML = "(" + cs.Language.shop_discount + ": " + discount + ")";
      }
    }
  });

}).call(this);
