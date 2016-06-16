// Generated by LiveScript 1.4.0
/**
 * @package   Shop
 * @category  modules
 * @author    Nazar Mokrynskyi <nazar@mokrynskyi.com>
 * @copyright Copyright (c) 2014-2016, Nazar Mokrynskyi
 * @license   MIT License, see license.txt
 */
(function(){
  require(['jquery'], function($){
    $(function(){
      var L, set_attribute_types, color_set_attribute_type, string_attribute_types, make_modal;
      L = cs.Language('shop_');
      set_attribute_types = [1, 2, 6, 9];
      color_set_attribute_type = [1, 2, 6, 9];
      string_attribute_types = [5];
      make_modal = function(attributes, categories, title, action){
        var categories_list, modal;
        attributes = function(){
          var attributes_, attribute, ref$;
          attributes_ = {};
          for (attribute in ref$ = attributes) {
            attribute = ref$[attribute];
            attributes_[attribute.id] = attribute;
          }
          return attributes_;
        }();
        categories = function(){
          var categories_, category, ref$;
          categories_ = {};
          for (category in ref$ = categories) {
            category = ref$[category];
            categories_[category.id] = category;
          }
          return categories_;
        }();
        categories_list = function(){
          var categories_list_, keys, category, ref$, parent_category, i$, len$, key, results$ = [];
          categories_list_ = {
            '-': "<option disabled>" + L.none + "</option>"
          };
          keys = ['-'];
          for (category in ref$ = categories) {
            category = ref$[category];
            parent_category = parseInt(category.parent);
            while (parent_category && parent_category !== category) {
              parent_category = categories[parent_category];
              if (parent_category.parent === category.id) {
                break;
              }
              category.title = parent_category.title + ' :: ' + category.title;
              parent_category = parseInt(parent_category.parent);
            }
            categories_list_[category.title] = "<option value=\"" + category.id + "\">" + category.title + "</option>";
            keys.push(category.title);
          }
          keys.sort();
          for (i$ = 0, len$ = keys.length; i$ < len$; ++i$) {
            key = keys[i$];
            results$.push(categories_list_[key]);
          }
          return results$;
        }();
        categories_list = categories_list.join('');
        modal = $(cs.ui.simple_modal("<form>\n	<h3 class=\"cs-text-center\">" + title + "</h3>\n	<p>\n		" + L.category + ": <select is=\"cs-select\" name=\"category\" required>" + categories_list + "</select>\n	</p>\n	<div></div>\n</form>"));
        modal.item_data = {};
        modal.update_item_data = function(){
          var item, attribute, ref$, value;
          item = modal.item_data;
          modal.find('[name=price]').val(item.price);
          modal.find('[name=in_stock]').val(item.in_stock);
          modal.find("[name=soon][value=" + item.soon + "]").prop('checked', true);
          modal.find("[name=listed][value=" + item.listed + "]").prop('checked', true);
          if (item.images) {
            modal.add_images(item.images);
          }
          if (item.videos) {
            modal.add_videos(item.videos);
          }
          if (item.attributes) {
            for (attribute in ref$ = item.attributes) {
              value = ref$[attribute];
              modal.find("[name='attributes[" + attribute + "]']").val(value);
            }
          }
          if (item.tags) {
            modal.find('[name=tags]').val(item.tags.join(', '));
          }
        };
        modal.find('[name=category]').change(function(){
          var $this, category, attributes_list, images_container, videos_container;
          modal.find('form').serializeArray().forEach(function(item){
            var value, name, attribute;
            value = item.value;
            name = item.name;
            switch (name) {
            case 'tags':
              value = value.split(',').map($.trim);
              break;
            case 'images':
              if (value) {
                value = JSON.parse(value);
              }
            }
            if (attribute = name.match(/attributes\[([0-9]+)\]/)) {
              if (!modal.item_data.attributes) {
                modal.item_data.attributes = {};
              }
              modal.item_data.attributes[attribute[1]] = value;
            } else {
              modal.item_data[item.name] = value;
            }
          });
          $this = $(this);
          category = categories[$this.val()];
          attributes_list = function(){
            var i$, ref$, len$, attribute, values, color, results$ = [];
            for (i$ = 0, len$ = (ref$ = category.attributes).length; i$ < len$; ++i$) {
              attribute = ref$[i$];
              attribute = attributes[attribute];
              attribute.type = parseInt(attribute.type);
              if (set_attribute_types.indexOf(attribute.type) !== -1) {
                values = fn$();
                values = values.join('');
                color = attribute.type === color_set_attribute_type ? "<input is=\"cs-input-text\" type=\"color\">" : '';
                results$.push("<p>\n	" + attribute.title + ":\n	<select is=\"cs-select\" name=\"attributes[" + attribute.id + "]\">\n		<option value=\"\">" + L.none + "</option>\n		" + values + "\n	</select>\n	" + color + "\n</p>");
              } else if (string_attribute_types.indexOf(attribute.type) !== -1) {
                results$.push("<p>\n	" + attribute.title + ": <input is=\"cs-input-text\" name=\"attributes[" + attribute.id + "]\">\n</p>");
              } else {
                results$.push("<p>\n	" + attribute.title + ": <textarea is=\"cs-textarea\" autosize name=\"attributes[" + attribute.id + "]\"></textarea>\n</p>");
              }
            }
            return results$;
            function fn$(){
              var i$, ref$, len$, value, results$ = [];
              for (i$ = 0, len$ = (ref$ = attribute.value).length; i$ < len$; ++i$) {
                value = ref$[i$];
                results$.push("<option value=\"" + value + "\">" + value + "</option>");
              }
              return results$;
            }
          }();
          attributes_list = attributes_list.join('');
          $this.parent().next().html("<p>\n	" + L.price + ": <input is=\"cs-input-text\" name=\"price\" type=\"number\" value=\"0\" required>\n</p>\n<p>\n	" + L.in_stock + ": <input is=\"cs-input-text\" name=\"in_stock\" type=\"number\" value=\"1\" step=\"1\">\n</p>\n<p>\n	" + L.available_soon + ":\n	<label is=\"cs-label-button\"><input type=\"radio\" name=\"soon\" value=\"1\"> " + L.yes + "</label>\n	<label is=\"cs-label-button\"><input type=\"radio\" name=\"soon\" value=\"0\" checked> " + L.no + "</label>\n</p>\n<p>\n	" + L.listed + ":\n	<label is=\"cs-label-button\"><input type=\"radio\" name=\"listed\" value=\"1\" checked> " + L.yes + "</label>\n	<label is=\"cs-label-button\"><input type=\"radio\" name=\"listed\" value=\"0\"> " + L.no + "</label>\n</p>\n<p>\n	<span class=\"images\" style=\"display: block\"></span>\n	<button is=\"cs-button\" tight type=\"button\" class=\"add-images\">" + L.add_images + "</button>\n	<progress is=\"cs-progress\" hidden></progress>\n	<input type=\"hidden\" name=\"images\">\n</p>\n<p>\n	<div class=\"videos\"></div>\n	<button is=\"cs-button\" type=\"button\" class=\"add-video\">" + L.add_video + "</button>\n</p>\n" + attributes_list + "\n<p>\n	" + L.tags + ": <input is=\"cs-input-text\" name=\"tags\" placeholder=\"shop, high quality, e-commerce\">\n</p>\n<p>\n	<button is=\"cs-button\" primary type=\"submit\">" + action + "</button>\n</p>");
          images_container = modal.find('.images');
          modal.update_images = function(){
            var images, this$ = this;
            images = [];
            images_container.find('a').each(function(){
              images.push($(this).attr('href'));
            });
            modal.find('[name=images]').val(JSON.stringify(images));
            require(['html5sortable-no-jquery'], function(html5sortable){
              html5sortable(images_container.get(), 'destroy');
              html5sortable(images_container.get(), {
                forcePlaceholderSize: true,
                placeholder: '<button is="cs-button" icon="map-pin" style="vertical-align: top">'
              })[0].addEventListener('sortupdate', modal.update_images);
            });
          };
          modal.add_images = function(images){
            images.forEach(function(image){
              images_container.append("<a href=\"" + image + "\" target=\"_blank\" style=\"display: inline-block; padding: .5em; width: 150px\">\n	<img src=\"" + image + "\">\n	<br>\n	<button is=\"cs-button\" force-compact type=\"button\" class=\"remove-image\" style=\"width: 100%\">" + L.remove_image + "</button>\n</a>");
            });
            modal.update_images();
          };
          if (cs.file_upload) {
            (function(){
              var progress, uploader;
              progress = modal.find('.add-images').next()[0];
              uploader = cs.file_upload(modal.find('.add-images'), function(images){
                progress.hidden = true;
                modal.add_images(images);
              }, function(error){
                progress.hidden = true;
                cs.ui.notify(error, 'error');
              }, function(percents){
                progress.value = percents;
                progress.hidden = false;
              }, true);
              modal.on('close', bind$(uploader, 'destroy'));
            })();
          } else {
            modal.find('.add-images').click(function(){
              var image;
              image = prompt(L.image_url);
              if (image) {
                modal.add_images([image]);
              }
            });
          }
          modal.on('click', '.remove-image', function(){
            $(this).parent().remove();
            modal.update_images();
            return false;
          });
          videos_container = modal.find('.videos');
          modal.update_videos = function(){
            var this$ = this;
            require(['html5sortable-no-jquery'], function(html5sortable){
              html5sortable(videos_container.get(), 'destroy');
              html5sortable(videos_container.get(), {
                handle: '.handle',
                forcePlaceholderSize: true
              })[0].addEventListener('sortupdate', modal.update_videos);
            });
          };
          modal.add_videos = function(videos){
            videos.forEach(function(video){
              var added_video, video_video, video_poster;
              videos_container.append("<p>\n	<cs-icon icon=\"sort\" class=\"handle\"></cs-icon>\n	<select is=\"cs-select\" name=\"videos[type][]\" class=\"video-type\">\n		<option value=\"supported_video\">" + L.youtube_vimeo_url + "</option>\n		<option value=\"iframe\">" + L.iframe_url_or_embed_code + "</option>\n		<option value=\"direct_url\">" + L.direct_video_url + "</option>\n	</select>\n	<textarea is=\"cs-textarea\" autosize name=\"videos[video][]\" placeholder=\"" + L.url_or_code + "\" class=\"video-video\" rows=\"3\"></textarea>\n	<input is=\"cs-input-text\" name=\"videos[poster][]\" class=\"video-poster\" placeholder=\"" + L.video_poster + "\">\n	<button is=\"cs-button\" icon=\"close\" type=\"button\" class=\"delete-video\"></button>\n	<progress is=\"cs-progress\" hidden full-width></progress>\n</p>");
              added_video = videos_container.children('p:last');
              video_video = added_video.find('.video-video').val(video.video);
              video_poster = added_video.find('.video-poster').val(video.poster);
              if (cs.file_upload) {
                (function(){
                  var progress, uploader;
                  video_video.after("&nbsp;<button is=\"cs-button\" type=\"button\" icon=\"upload\"></button>");
                  progress = video_video.parent().find('progress')[0];
                  uploader = cs.file_upload(video_video.next(), function(video){
                    progress.hidden = true;
                    video_video.val(video[0]);
                  }, function(error){
                    progress.hidden = true;
                    cs.ui.notify(error, 'error');
                  }, function(percents){
                    progress.value = percents;
                    progress.hidden = false;
                  });
                  modal.on('close', bind$(uploader, 'destroy'));
                })();
                (function(){
                  var progress, uploader;
                  video_poster.after("&nbsp;<button is=\"cs-button\" type=\"button\" icon=\"upload\"></button>");
                  progress = video_video.parent().find('progress')[0];
                  uploader = cs.file_upload(video_poster.next(), function(poster){
                    progress.hidden = true;
                    video_poster.val(poster[0]);
                  }, function(error){
                    progress.hidden = true;
                    cs.ui.notify(error, 'error');
                  }, function(percents){
                    progress.value = percents;
                    progress.hidden = false;
                  });
                  modal.on('close', bind$(uploader, 'destroy'));
                })();
              }
              added_video.find('.video-type').val(video.type).change();
            });
            modal.update_videos();
          };
          modal.find('.add-video').click(function(){
            modal.add_videos([{
              video: '',
              poster: '',
              type: 'supported_video'
            }]);
          });
          videos_container.on('click', '.delete-video', function(){
            $(this).parent().remove();
          });
          videos_container.on('change', '.video-type', function(){
            var $this, container;
            $this = $(this);
            container = $this.parent();
            switch ($this.val()) {
            case 'supported_video':
              container.find('.video-video').next('button').hide();
              container.find('.video-poster').hide().next('button').hide();
              break;
            case 'iframe':
              container.find('.video-video').next('button').hide();
              container.find('.video-poster').show().next('button').show();
              break;
            case 'direct_url':
              container.find('.video-video').next('button').show();
              container.find('.video-poster').show().next('button').show();
            }
          });
          modal.update_item_data();
        });
        return modal;
      };
      $('html').on('mousedown', '.cs-shop-item-add', function(){
        cs.api(['get api/Shop/admin/attributes', 'get api/Shop/admin/categories']).then(function(arg$){
          var attributes, categories, modal;
          attributes = arg$[0], categories = arg$[1];
          modal = make_modal(attributes, categories, L.item_addition, L.add);
          modal.find("[name=category]").change();
          modal.find('form').submit(function(){
            cs.api('post api/Shop/admin/items', this).then(function(){
              return cs.ui.alert(L.added_successfully);
            }).then(bind$(location, 'reload'));
            return false;
          });
        });
      }).on('mousedown', '.cs-shop-item-edit', function(){
        var id;
        id = $(this).data('id');
        cs.api(['get api/Shop/admin/attributes', 'get api/Shop/admin/categories', "get api/Shop/admin/items/" + id]).then(function(arg$){
          var attributes, categories, item, modal;
          attributes = arg$[0], categories = arg$[1], item = arg$[2];
          modal = make_modal(attributes, categories, L.item_edition, L.edit);
          modal.find('form').submit(function(){
            cs.api("put api/Shop/admin/items/" + id, this).then(function(){
              return cs.ui.alert(L.edited_successfully);
            }).then(bind$(location, 'reload'));
            return false;
          });
          modal.item_data = item;
          modal.find("[name=category]").val(item.category).change();
        });
      }).on('mousedown', '.cs-shop-item-delete', function(){
        var id;
        id = $(this).data('id');
        cs.ui.confirm(L.sure_want_to_delete).then(function(){
          return cs.api("delete api/Shop/admin/items/" + id);
        }).then(function(){
          return cs.ui.alert(L.deleted_successfully);
        }).then(bind$(location, 'reload'));
      });
    });
  });
  function bind$(obj, key, target){
    return function(){ return (target || obj)[key].apply(obj, arguments) };
  }
}).call(this);
