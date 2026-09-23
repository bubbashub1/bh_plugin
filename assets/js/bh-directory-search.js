(function () {
    'use strict';

    function getDirectory(form) {
        return form.closest('.bh-directory');
    }

    function buildFilterUrl(form) {
        var url = new URL(window.location.href);
        var params = new URLSearchParams(new FormData(form));

        // Remove pagination whenever a filter changes.
        params.delete('bh_page');

        // Keep the existing page path but replace its query with the active filters.
        url.search = params.toString();
        return url;
    }

    function refreshDirectory(form, pushHistory) {
        var directory = getDirectory(form);
        if (!directory || directory.dataset.bhAjaxLoading === '1') {
            return;
        }

        var targetUrl = buildFilterUrl(form);
        directory.dataset.bhAjaxLoading = '1';
        directory.setAttribute('aria-busy', 'true');

        fetch(targetUrl.toString(), {
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Directory request failed');
                }
                return response.text();
            })
            .then(function (html) {
                var doc = new DOMParser().parseFromString(html, 'text/html');
                var replacement = doc.querySelector('.bh-directory');

                if (!replacement) {
                    throw new Error('Directory content not found');
                }

                directory.replaceWith(replacement);

                if (pushHistory) {
                    window.history.pushState({ bhDirectory: true }, '', targetUrl.toString());
                }

                initDirectorySearch();
            })
            .catch(function () {
                // If AJAX fails, use the normal WordPress request as a safe fallback.
                window.location.href = targetUrl.toString();
            });
    }

    function submitFilters(form) {
        if (!form || form.dataset.bhAutoSubmitting === '1') {
            return;
        }

        form.dataset.bhAutoSubmitting = '1';
        refreshDirectory(form, true);
    }

    function initAutoFilters() {
        document.querySelectorAll('.bh-directory-search__form').forEach(function (form) {
            if (form.dataset.bhAutoFiltersBound === '1') {
                return;
            }

            form.dataset.bhAutoFiltersBound = '1';

            form.addEventListener('submit', function (event) {
                event.preventDefault();
                submitFilters(form);
            });

            form.querySelectorAll('select, input[type="checkbox"]').forEach(function (field) {
                field.addEventListener('change', function () {
                    if (field.id === 'bh-region') {
                        return;
                    }
                    if (field.id === 'bh-saved-location' && field.value) {
                        var region = form.querySelector('#bh-region');
                        var town = form.querySelector('#bh-town');
                        if (region) region.value = '';
                        if (town) {
                            town.value = '';
                            town.innerHTML = '<option value="">Select a region first</option>';
                            town.disabled = true;
                        }
                    }
                    if (field.id === 'bh-town' && field.value) {
                        var savedLocation = form.querySelector('#bh-saved-location');
                        if (savedLocation) savedLocation.value = '';
                    }
                    submitFilters(form);
                });
            });

            var search = form.querySelector('input[name="bh_search"]');
            var searchTimer = null;
            if (search) {
                search.addEventListener('input', function () {
                    window.clearTimeout(searchTimer);
                    searchTimer = window.setTimeout(function () {
                        submitFilters(form);
                    }, 500);
                });

                search.addEventListener('keydown', function (event) {
                    if (event.key === 'Enter') {
                        window.clearTimeout(searchTimer);
                        submitFilters(form);
                    }
                });
            }

            var price = form.querySelector('input[name="bh_price"]');
            if (price) {
                price.addEventListener('change', function () {
                    submitFilters(form);
                });
            }
        });
    }

    function initRegionTown() {
        document.querySelectorAll('.bh-directory-search__form').forEach(function (form) {
            var region = form.querySelector('#bh-region');
            var town = form.querySelector('#bh-town');
            if (!region || !town || form.dataset.bhRegionTownBound === '1') {
                return;
            }

            form.dataset.bhRegionTownBound = '1';

            region.addEventListener('change', function () {
                var regionValue = region.value;
                var savedLocation = form.querySelector('#bh-saved-location');
                if (savedLocation) savedLocation.value = '';

                town.innerHTML = '';
                town.disabled = true;

                var placeholder = document.createElement('option');
                placeholder.value = '';
                placeholder.textContent = regionValue ? 'Loading towns…' : 'Select a region first';
                town.appendChild(placeholder);

                if (!regionValue) {
                    submitFilters(form);
                    return;
                }

                var body = new URLSearchParams();
                body.append('action', 'bh_get_towns');
                var config = window.BubbaHubDirectorySearch || {};
                body.append('nonce', config.nonce || '');
                body.append('region', regionValue);

                if (!config.ajaxUrl) {
                    town.innerHTML = '<option value="">AJAX unavailable — please refresh</option>';
                    town.disabled = false;
                    submitFilters(form);
                    return;
                }

                fetch(config.ajaxUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                    },
                    body: body.toString()
                })
                    .then(function (response) {
                        if (!response.ok) {
                            throw new Error('Town request failed');
                        }
                        return response.json();
                    })
                    .then(function (result) {
                        town.innerHTML = '';

                        var allOption = document.createElement('option');
                        allOption.value = '';
                        allOption.textContent = 'All towns';
                        town.appendChild(allOption);

                        if (result.success && result.data) {
                            result.data.forEach(function (item) {
                                var option = document.createElement('option');
                                option.value = item.value;
                                option.textContent = item.label;
                                town.appendChild(option);
                            });
                        }

                        town.disabled = false;
                        submitFilters(form);
                    })
                    .catch(function () {
                        town.innerHTML = '';
                        var retryOption = document.createElement('option');
                        retryOption.value = '';
                        retryOption.textContent = 'Unable to load towns — try again';
                        town.appendChild(retryOption);
                        town.disabled = false;
                        submitFilters(form);
                    });
            });
        });
    }

    function initDirectorySearch() {
        initRegionTown();
        initAutoFilters();
        initMobileFilterToggle();
    }

    function initMobileFilterToggle(){
        // Use delegated handling so the button still works after AJAX replaces the directory.
        if(document.body.dataset.bhFilterToggleBound==='1'){
            return;
        }
        document.body.dataset.bhFilterToggleBound='1';
        document.addEventListener('click',function(event){
            var button=event.target.closest('.bh-directory__filter-toggle');
            if(!button){
                return;
            }
            event.preventDefault();
            var filters=button.closest('.bh-directory__filters');
            if(!filters){
                return;
            }
            var open=filters.classList.toggle('is-open');
            button.setAttribute('aria-expanded',open?'true':'false');
        });
    }

window.BubbaHubInitDirectorySearch = initDirectorySearch;

    document.addEventListener('DOMContentLoaded', initDirectorySearch);

    window.addEventListener('popstate', function () {
        window.location.reload();
    });
}());

(function(){'use strict';
function clean(raw){var u;try{u=new URL(raw||window.location.href,window.location.origin)}catch(e){u=new URL(window.location.href)}var a=['bh_search','bh_category','bh_age_range','bh_region','bh_town','bh_saved_location','bh_day','bh_price','bh_free_activity','bh_view','bh_date'],c=new URL(u.origin+u.pathname);a.forEach(function(k){var v=u.searchParams.get(k);if(v)c.searchParams.set(k,v)});return c.toString()}
function icon(name){var paths={facebook:'<path d="M14 8h3V5h-3c-2.2 0-4 1.8-4 4v2H7v3h3v6h3v-6h3l1-3h-4V9c0-.6.4-1 1-1z"/>',whatsapp:'<path d="M12 2a10 10 0 0 0-8.7 15L2 22l5.2-1.3A10 10 0 1 0 12 2zm0 18c-1.6 0-3.1-.4-4.4-1.2l-.3-.2-3.1.8.8-3-.2-.3A8 8 0 1 1 12 20zm4.4-6.1c-.2-.1-1.3-.7-1.5-.7-.2-.1-.4-.1-.5.1-.2.2-.6.7-.7.8-.1.1-.3.2-.5.1-.3-.1-1-.4-1.9-1.2-.7-.6-1.2-1.3-1.3-1.5-.1-.2 0-.3.1-.4l.4-.5c.1-.1.1-.3.2-.4.1-.1 0-.3 0-.4-.1-.1-.5-1.2-.7-1.6-.2-.4-.4-.4-.5-.4h-.4c-.1 0-.4.1-.6.3-.2.2-.8.8-.8 1.9s.8 2.2.9 2.4c.1.2 1.6 2.5 3.9 3.4 2.3.9 2.3.6 2.7.6.4 0 1.3-.5 1.5-1 .2-.5.2-.9.1-1 0-.2-.2-.2-.4-.3z"/>',x:'<path d="M5 4l5.4 6.9L5.3 20h2.4l3.8-7.1 5.5 7.1H20l-6-7.7L19.5 4h-2.4l-3.5 6.5L8 4H5z"/>',email:'<path d="M3 5h18v14H3V5zm2 2v.5l7 4.5 7-4.5V7l-7 4.5L5 7z"/>',copy:'<path d="M8 8h11v11H8V8zm-3 8H3V3h13v2H5v11z"/>'};return '<svg viewBox="0 0 24 24" aria-hidden="true">'+paths[name]+'</svg>'}
function menu(button,url,title){var old=button.parentNode.querySelector('.bh-share-menu');if(old){old.remove();return}var box=document.createElement('div');box.className='bh-share-menu';var items=[['facebook','Facebook'],['whatsapp','WhatsApp'],['x','X'],['email','Email'],['copy','Copy link']];items.forEach(function(item){var b=document.createElement('button');b.type='button';b.className='bh-share-menu__item';b.setAttribute('aria-label',item[1]);b.title=item[1];b.innerHTML=icon(item[0]);b.addEventListener('click',function(){var link=url,text='Have a look at these family activities on Bubba Hub';if(item[0]==='facebook')window.open('https://www.facebook.com/sharer/sharer.php?u='+encodeURIComponent(link),'bhshare','width=700,height=600');else if(item[0]==='whatsapp')window.open('https://wa.me/?text='+encodeURIComponent(text+' '+link),'bhshare','width=700,height=600');else if(item[0]==='x')window.open('https://twitter.com/intent/tweet?text='+encodeURIComponent(text)+'&url='+encodeURIComponent(link),'bhshare','width=700,height=600');else if(item[0]==='email')window.location.href='mailto:?subject='+encodeURIComponent(title)+'&body='+encodeURIComponent(text+'\n\n'+link);else if(navigator.clipboard)navigator.clipboard.writeText(link).then(function(){b.setAttribute('aria-label','Copied');setTimeout(function(){b.setAttribute('aria-label',item[1])},1200)});else window.prompt('Copy this share link:',link)});box.appendChild(b)});button.insertAdjacentElement('afterend',box)}
document.addEventListener('click',function(e){var b=e.target.closest('[data-bh-share-search]');if(!b)return;e.preventDefault();menu(b,clean(window.location.href),'Bubba Hub family activities')});
document.addEventListener('click',function(e){if(!e.target.closest('.bh-share-menu')&&!e.target.closest('[data-bh-share-search]'))document.querySelectorAll('.bh-share-menu').forEach(function(m){m.remove()})});
document.addEventListener('submit',function(e){var f=e.target.closest('.bh-directory-search__save-form');if(!f)return;var b=f.querySelector('button[type="submit"]');if(!b||b.disabled)return;b.disabled=true;b.classList.add('is-saving');b.innerHTML='<span class="bh-directory-search__spinner" aria-hidden="true"></span>Saving…'})
}());
(function(){'use strict';
function initSaveSearchModal(){
 document.querySelectorAll('[data-bh-open-save-search]').forEach(function(button){
  if(button.dataset.bhModalBound==='1')return; button.dataset.bhModalBound='1';
  button.addEventListener('click',function(){
   var search=button.closest('.bh-directory-search');
   var modal=search ? search.querySelector('[data-bh-save-search-modal]') : null;
   if(!modal)return;
   // Move the modal to <body> before opening so no parent stacking context,
   // sticky filter, transform, or listing card can render above it.
   if(modal.parentNode !== document.body){document.body.appendChild(modal)}
   modal.hidden=false;
   document.body.classList.add('bh-save-search-open');
   var input=modal.querySelector('input[name="bh_saved_search_name"]'); if(input){input.focus()}
  });
 });
 document.querySelectorAll('[data-bh-close-save-search]').forEach(function(button){
  if(button.dataset.bhModalCloseBound==='1')return; button.dataset.bhModalCloseBound='1';
  button.addEventListener('click',function(){var modal=button.closest('[data-bh-save-search-modal]');if(modal){modal.hidden=true;document.body.classList.remove('bh-save-search-open')}});
 });
 document.querySelectorAll('[data-bh-save-search-modal]').forEach(function(modal){
  if(modal.dataset.bhKeyBound==='1')return; modal.dataset.bhKeyBound='1';
  modal.addEventListener('keydown',function(e){if(e.key==='Escape'){modal.hidden=true;document.body.classList.remove('bh-save-search-open')}});
 });
}
window.BubbaHubInitSaveSearchModal=initSaveSearchModal;
document.addEventListener('DOMContentLoaded',initSaveSearchModal);
}());

(function(){'use strict';
function moveMoreFilters(){
 document.querySelectorAll('.bh-directory').forEach(function(directory){
  var search=directory.querySelector('.bh-directory__search-top .bh-directory-search');
  var advanced=search ? search.querySelector('.bh-directory-search__advanced') : null;
  var panel=directory.querySelector('.bh-directory__filter-panel');
  if(!advanced||!panel)return;
  if(advanced.parentNode!==panel){panel.appendChild(advanced)}
 });
}
document.addEventListener('DOMContentLoaded',moveMoreFilters);
}());

(function(){'use strict';
function closeSearchPopovers(){
 document.querySelectorAll('.bh-search-popover-panel[data-bh-popover-open="1"]').forEach(function(panel){
  panel.hidden=true; panel.removeAttribute('data-bh-popover-open');
 });
 document.querySelectorAll('.bh-search-popover-trigger[aria-expanded="true"]').forEach(function(btn){btn.setAttribute('aria-expanded','false')});
 var backdrop=document.querySelector('.bh-search-popover-backdrop'); if(backdrop) backdrop.remove();
 document.body.classList.remove('bh-search-popover-open');
}
function positionPanel(trigger,panel){
 var r=trigger.getBoundingClientRect(), gap=8;
 var w=Math.min(560,window.innerWidth-32), left=Math.max(16,Math.min(r.left,window.innerWidth-w-16));
 var top=r.bottom+gap;
 if(top+panel.offsetHeight>window.innerHeight-16) top=Math.max(16,r.top-panel.offsetHeight-gap);
 panel.style.left=left+'px'; panel.style.top=top+'px';
}
function openSearchPopover(trigger){
 var field=trigger.closest('.bh-search-popover-field'); if(!field)return;
 var key=trigger.getAttribute('data-bh-search-popover');
 var panel=field.querySelector('[data-bh-popover-panel="'+key+'"]'); if(!panel)return;
 closeSearchPopovers();
 if(panel.parentNode!==document.body) document.body.appendChild(panel);
 var backdrop=document.createElement('div'); backdrop.className='bh-search-popover-backdrop'; backdrop.addEventListener('click',closeSearchPopovers);
 document.body.appendChild(backdrop);
 panel.hidden=false; panel.setAttribute('data-bh-popover-open','1');
 trigger.setAttribute('aria-expanded','true');
 positionPanel(trigger,panel);
 document.body.classList.add('bh-search-popover-open');
 var input=panel.querySelector('[data-bh-search-input]');
 if(input){input.focus(); input.select();}
}
function initSearchPopovers(){
 document.querySelectorAll('[data-bh-search-popover]').forEach(function(trigger){
  if(trigger.dataset.bhPopoverBound==='1')return;
  trigger.dataset.bhPopoverBound='1';
  trigger.addEventListener('click',function(e){e.preventDefault();openSearchPopover(trigger)});
 });
 document.querySelectorAll('[data-bh-choice-for]').forEach(function(choice){
  if(choice.dataset.bhChoiceBound==='1')return;
  choice.dataset.bhChoiceBound='1';
  choice.addEventListener('click',function(){
   var form=choice.closest('.bh-directory-search__form'); if(!form)return;
   var field=form.querySelector('#'+choice.getAttribute('data-bh-choice-for')); if(!field)return;
   field.value=choice.getAttribute('data-value')||'';
   var trigger=choice.closest('.bh-search-popover-field')?.querySelector('.bh-search-popover-trigger');
   if(trigger){var value=choice.textContent.trim(); var out=trigger.querySelector('.bh-search-popover-trigger__value'); if(out)out.textContent=value}
   closeSearchPopovers();
   submitFilters(form);
  });
 });
 document.querySelectorAll('[data-bh-search-input]').forEach(function(input){
  if(input.dataset.bhInputBound==='1')return;
  input.dataset.bhInputBound='1';
  input.addEventListener('input',function(){
   var field=input.closest('.bh-search-popover-field'); var hidden=field?field.querySelector('input[name="bh_search"].bh-search-popover-value'):null;
   if(!hidden)return;
   hidden.value=input.value;
   var out=field.querySelector('.bh-search-popover-trigger__value'); if(out)out.textContent=input.value||'Groups, classes, activities…';
  });
  input.addEventListener('keydown',function(e){
   if(e.key==='Enter'){e.preventDefault();var form=input.closest('.bh-directory-search__form');if(form){closeSearchPopovers();submitFilters(form)}}
  });
 });
 document.addEventListener('keydown',function(e){if(e.key==='Escape')closeSearchPopovers()});
 window.addEventListener('resize',function(){var panel=document.querySelector('.bh-search-popover-panel[data-bh-popover-open="1"]');var trigger=document.querySelector('.bh-search-popover-trigger[aria-expanded="true"]');if(panel&&trigger)positionPanel(trigger,panel)});
}
var oldInit=window.BubbaHubInitDirectorySearch;
window.BubbaHubInitDirectorySearch=function(){if(typeof oldInit==='function')oldInit();initSearchPopovers()};
document.addEventListener('DOMContentLoaded',initSearchPopovers);
}());
