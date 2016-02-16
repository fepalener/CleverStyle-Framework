// Generated by LiveScript 1.4.0
/**
 * @package   Service worker cache
 * @category  modules
 * @author    Nazar Mokrynskyi <nazar@mokrynskyi.com>
 * @copyright Copyright (c) 2015-2016, Nazar Mokrynskyi
 * @license   MIT License, see license.txt
 */
(function(){
  if (!caches) {
    return;
  }
  addEventListener('fetch', function(event){
    event.respondWith(caches.match(event.request).then(function(response){
      var request_copy;
      if (response) {
        return response;
      } else {
        request_copy = event.request.clone();
        return fetch(request_copy).then(function(response){
          var path, ref$, response_copy;
          if (response && response.status === 200 && response.type === 'basic') {
            path = (ref$ = response.url.match(/:\/\/[^/]+\/(.+)$/)) != null ? ref$[1] : void 8;
            if (path && path.match(/^components|includes|storage\/pcache|themes/)) {
              response_copy = response.clone();
              caches.open('frontend-cache').then(function(cache){
                cache.put(event.request, response_copy);
              });
            }
          }
          return response;
        });
      }
    }));
  });
}).call(this);
