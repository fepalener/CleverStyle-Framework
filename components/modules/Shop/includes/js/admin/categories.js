// Generated by CoffeeScript 1.9.3

/**
 * @package   Shop
 * @category  modules
 * @author    Nazar Mokrynskyi <nazar@mokrynskyi.com>
 * @copyright Copyright (c) 2014-2016, Nazar Mokrynskyi
 * @license   MIT License, see license.txt
 */

(function() {
  $(function() {
    var L, make_modal;
    L = cs.Language;
    make_modal = function(attributes, categories, title, action) {
      var modal;
      attributes = (function() {
        var attribute, attributes_, i, key, keys, len, results;
        attributes_ = {};
        keys = [];
        for (attribute in attributes) {
          attribute = attributes[attribute];
          attributes_[attribute.title_internal] = "<option value=\"" + attribute.id + "\">" + attribute.title_internal + "</option>";
          keys.push(attribute.title_internal);
        }
        keys.sort();
        results = [];
        for (i = 0, len = keys.length; i < len; i++) {
          key = keys[i];
          results.push(attributes_[key]);
        }
        return results;
      })();
      attributes = attributes.join('');
      categories = (function() {
        var categories_, category;
        categories_ = {};
        for (category in categories) {
          category = categories[category];
          categories_[category.id] = category;
        }
        return categories_;
      })();
      categories = (function() {
        var categories_, category, i, key, keys, len, parent_category, results;
        categories_ = {};
        keys = ['-'];
        for (category in categories) {
          category = categories[category];
          parent_category = parseInt(category.parent);
          while (parent_category && parent_category !== category) {
            parent_category = categories[parent_category];
            if (parent_category.parent === category.id) {
              break;
            }
            category.title = parent_category.title + ' :: ' + category.title;
            parent_category = parseInt(parent_category.parent);
          }
          categories_[category.title] = "<option value=\"" + category.id + "\">" + category.title + "</option>";
          keys.push(category.title);
        }
        keys.sort();
        results = [];
        for (i = 0, len = keys.length; i < len; i++) {
          key = keys[i];
          results.push(categories_[key]);
        }
        return results;
      })();
      categories = categories.join('');
      modal = $(cs.ui.simple_modal("<form>\n	<h3 class=\"cs-text-center\">" + title + "</h3>\n	<p>\n		" + L.shop_parent_category + ":\n		<select is=\"cs-select\" name=\"parent\" required>\n			<option value=\"0\">" + L.none + "</option>\n			" + categories + "\n		</select>\n	</p>\n	<p>\n		" + L.shop_title + ": <input is=\"cs-input-text\" name=\"title\" required>\n	</p>\n	<p>\n		" + L.shop_description + ": <textarea is=\"cs-textarea\" autosize name=\"description\"></textarea>\n	</p>\n	<p class=\"image\" hidden style=\"width: 150px\">\n		" + L.shop_image + ":\n		<a target=\"_blank\">\n			<img>\n			<br>\n			<button is=\"cs-button\" force-compact type=\"button\" class=\"remove-image\" style=\"width: 100%\">" + L.shop_remove_image + "</button>\n		</a>\n		<input type=\"hidden\" name=\"image\">\n	</p>\n	<p>\n		<button is=\"cs-button\" tight type=\"button\" class=\"set-image\">" + L.shop_set_image + "</button>\n		<progress is=\"cs-progress\" hidden></progress>\n	</p>\n	<p>\n		" + L.shop_category_attributes + ": <select is=\"cs-select\" name=\"attributes[]\" multiple required size=\"5\">" + attributes + "</select>\n	</p>\n	<p>\n		" + L.shop_title_attribute + ": <select is=\"cs-select\" name=\"title_attribute\" required>" + attributes + "</select>\n	</p>\n	<p>\n		" + L.shop_description_attribute + ":\n		<select is=\"cs-select\" name=\"description_attribute\" required>\n			<option value=\"0\">" + L.none + "</option>\n			" + attributes + "\n		</select>\n	</p>\n	<p>\n		" + L.shop_visible + ":\n		<label is=\"cs-label-button\"><input type=\"radio\" name=\"visible\" value=\"1\" checked> " + L.yes + "</label>\n		<label is=\"cs-label-button\"><input type=\"radio\" name=\"visible\" value=\"0\"> " + L.no + "</label>\n	</p>\n	<p>\n		<button is=\"cs-button\" primary type=\"submit\">" + action + "</button>\n	</p>\n</form>"));
      modal.set_image = function(image) {
        modal.find('[name=image]').val(image);
        if (image) {
          return modal.find('.image').removeAttr('hidden').find('a').attr('href', image).find('img').attr('src', image);
        } else {
          return modal.find('.image').attr('hidden');
        }
      };
      modal.find('.remove-image').click(function() {
        return modal.set_image('');
      });
      if (cs.file_upload) {
        (function() {
          var progress, uploader;
          progress = modal.find('.set-image').next()[0];
          uploader = cs.file_upload(modal.find('.set-image'), function(image) {
            progress.hidden = true;
            return modal.set_image(image[0]);
          }, function(error) {
            progress.hidden = true;
            return cs.ui.notify(error, 'error');
          }, function(percents) {
            progress.value = percents;
            return progress.hidden = false;
          });
          return modal.on('hide.uk.modal', function() {
            return uploader.destroy();
          });
        })();
      } else {
        modal.find('.set-image').click(function() {
          var image;
          image = prompt(L.shop_image_url);
          if (image) {
            return modal.set_image(image);
          }
        });
      }
      return modal;
    };
    return $('html').on('mousedown', '.cs-shop-category-add', function() {
      return Promise.all([$.getJSON('api/Shop/admin/attributes'), $.getJSON('api/Shop/admin/categories')]).then(function(arg) {
        var attributes, categories, modal;
        attributes = arg[0], categories = arg[1];
        modal = make_modal(attributes, categories, L.shop_category_addition, L.shop_add);
        return modal.find('form').submit(function() {
          $.ajax({
            url: 'api/Shop/admin/categories',
            type: 'post',
            data: $(this).serialize(),
            success: function() {
              alert(L.shop_added_successfully);
              return location.reload();
            }
          });
          return false;
        });
      });
    }).on('mousedown', '.cs-shop-category-edit', function() {
      var id;
      id = $(this).data('id');
      return Promise.all([$.getJSON('api/Shop/admin/attributes'), $.getJSON('api/Shop/admin/categories'), $.getJSON("api/Shop/admin/categories/" + id)]).then(function(arg) {
        var attributes, categories, category, modal;
        attributes = arg[0], categories = arg[1], category = arg[2];
        modal = make_modal(attributes, categories, L.shop_category_edition, L.shop_edit);
        modal.find('form').submit(function() {
          $.ajax({
            url: "api/Shop/admin/categories/" + id,
            type: 'put',
            data: $(this).serialize(),
            success: function() {
              alert(L.shop_edited_successfully);
              return location.reload();
            }
          });
          return false;
        });
        modal.find('[name=parent]').val(category.parent);
        modal.find('[name=title]').val(category.title);
        modal.find('[name=description]').val(category.description);
        category.attributes.forEach(function(attribute) {
          return modal.find("[name='attributes[]'] > [value=" + attribute + "]").prop('selected', true);
        });
        modal.find('[name=title_attribute]').val(category.title_attribute);
        modal.find('[name=description_attribute]').val(category.description_attribute);
        modal.set_image(category.image);
        return modal.find("[name=visible][value=" + category.visible + "]").prop('checked', true);
      });
    }).on('mousedown', '.cs-shop-category-delete', function() {
      var id;
      id = $(this).data('id');
      if (confirm(L.shop_sure_want_to_delete_category)) {
        return $.ajax({
          url: "api/Shop/admin/categories/" + id,
          type: 'delete',
          success: function() {
            alert(L.shop_deleted_successfully);
            return location.reload();
          }
        });
      }
    });
  });

}).call(this);
