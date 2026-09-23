(function () {
    'use strict';

    var config = window.BubbaHubSavedSearches || {};
    var modal = null;

    function createModal() {
        if (modal) return modal;

        modal = document.createElement('div');
        modal.className = 'bh-saved-searches-modal';
        modal.hidden = true;
        modal.innerHTML =
            '<div class="bh-saved-searches-modal__backdrop" data-bh-modal-close></div>' +
            '<div class="bh-saved-searches-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="bh-saved-search-editor-title">' +
                '<button type="button" class="bh-saved-searches-modal__close" data-bh-modal-close aria-label="Close">×</button>' +
                '<div class="bh-saved-searches-modal__heading">' +
                    '<p class="bh-saved-searches__eyebrow">Saved Search</p>' +
                    '<h2 id="bh-saved-search-editor-title">Edit Search</h2>' +
                    '<p>Change your Main Search or Advanced Search options, then save your changes.</p>' +
                '</div>' +
                '<div class="bh-saved-searches-modal__body" data-bh-editor-body><p>Loading search options…</p></div>' +
            '</div>';

        document.body.appendChild(modal);
        modal.addEventListener('click', function (event) {
            if (event.target.closest('[data-bh-modal-close]')) closeModal();
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && modal && !modal.hidden) closeModal();
        });
        return modal;
    }

    function closeModal() {
        if (!modal) return;
        modal.hidden = true;
        document.body.classList.remove('bh-saved-searches-modal-open');
    }

    function prepareSaveForm() {
        var section = modal && modal.querySelector('.bh-directory-search');
        if (!section) return;

        var searchForm = section.querySelector('.bh-directory-search__form');
        var saveForm = section.querySelector('.bh-directory-search__save-form');
        if (!searchForm || !saveForm) return;

        var keys = ['bh_search','bh_category','bh_age_range','bh_region','bh_town','bh_day','bh_price','bh_free_activity','bh_view','bh_date','bh_saved_search_path'];

        keys.forEach(function (key) {
            saveForm.querySelectorAll('input[name="' + key + '"]').forEach(function (input) {
                input.remove();
            });
        });

        var data = new FormData(searchForm);
        keys.forEach(function (key) {
            data.getAll(key).forEach(function (value) {
                if (value !== '') {
                    var input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = key;
                    input.value = value;
                    saveForm.appendChild(input);
                }
            });
        });
    }

    function openEditor(id) {
        var instance = createModal();
        var body = instance.querySelector('[data-bh-editor-body]');
        body.innerHTML = '<p>Loading search options…</p>';
        instance.hidden = false;
        document.body.classList.add('bh-saved-searches-modal-open');

        var request = new URLSearchParams();
        request.append('action', 'bh_edit_saved_search');
        request.append('nonce', config.nonce || '');
        request.append('id', id);

        fetch(config.ajaxUrl || '', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
            body: request.toString()
        })
            .then(function (response) {
                if (!response.ok) throw new Error('Editor request failed');
                return response.json();
            })
            .then(function (result) {
                if (!result.success || !result.data || !result.data.html) throw new Error('Editor unavailable');

                body.innerHTML = result.data.html;

                var name = body.querySelector('input[name="bh_saved_search_name"]');
                if (name) name.value = result.data.name || '';

                var saveForm = body.querySelector('.bh-directory-search__save-form');
                if (saveForm) {
                    var button = saveForm.querySelector('button[type="submit"]');
                    if (button) button.textContent = 'Save Changes';
                    saveForm.addEventListener('submit', prepareSaveForm);
                }

                if (window.BubbaHubInitDirectorySearch) {
                    window.BubbaHubInitDirectorySearch();
                }
            })
            .catch(function () {
                body.innerHTML = '<p>Unable to load this saved search. Please close the window and try again.</p>';
            });
    }

    document.addEventListener('click', function (event) {
        var button = event.target.closest('[data-bh-edit-search]');
        if (!button) return;
        event.preventDefault();
        openEditor(button.getAttribute('data-bh-edit-search'));
    });
}());

(function(){'use strict';
function clean(raw){var u;try{u=new URL(raw,window.location.origin)}catch(e){u=new URL(window.location.href)}var a=['bh_search','bh_category','bh_age_range','bh_region','bh_town','bh_saved_location','bh_day','bh_price','bh_free_activity','bh_view','bh_date'],c=new URL(u.origin+u.pathname);a.forEach(function(k){var v=u.searchParams.get(k);if(v)c.searchParams.set(k,v)});return c.toString()}
function icon(name){var paths={facebook:'<path d="M14 8h3V5h-3c-2.2 0-4 1.8-4 4v2H7v3h3v6h3v-6h3l1-3h-4V9c0-.6.4-1 1-1z"/>',whatsapp:'<path d="M12 2a10 10 0 0 0-8.7 15L2 22l5.2-1.3A10 10 0 1 0 12 2zm0 18c-1.6 0-3.1-.4-4.4-1.2l-.3-.2-3.1.8.8-3-.2-.3A8 8 0 1 1 12 20zm4.4-6.1c-.2-.1-1.3-.7-1.5-.7-.2-.1-.4-.1-.5.1-.2.2-.6.7-.7.8-.1.1-.3.2-.5.1-.3-.1-1-.4-1.9-1.2-.7-.6-1.2-1.3-1.3-1.5-.1-.2 0-.3.1-.4l.4-.5c.1-.1.1-.3.2-.4.1-.1.1-.3 0-.4-.1-.1-.5-1.2-.7-1.6-.2-.4-.4-.4-.5-.4h-.4c-.1 0-.4.1-.6.3-.2.2-.8.8-.8 1.9s.8 2.2.9 2.4c.1.2 1.6 2.5 3.9 3.4 2.3.9 2.3.6 2.7.6.4 0 1.3-.5 1.5-1 .2-.5.2-.9.1-1 0-.2-.2-.2-.4-.3z"/>',x:'<path d="M5 4l5.4 6.9L5.3 20h2.4l3.8-7.1 5.5 7.1H20l-6-7.7L19.5 4h-2.4l-3.5 6.5L8 4H5z"/>',email:'<path d="M3 5h18v14H3V5zm2 2v.5l7 4.5 7-4.5V7l-7 4.5L5 7z"/>',copy:'<path d="M8 8h11v11H8V8zm-3 8H3V3h13v2H5v11z"/>'};return '<svg viewBox="0 0 24 24" aria-hidden="true">'+paths[name]+'</svg>'}
function show(button,url,title){var old=button.parentNode.querySelector('.bh-share-menu');if(old){old.remove();return}var box=document.createElement('div');box.className='bh-share-menu';[['facebook','Facebook'],['whatsapp','WhatsApp'],['x','X'],['email','Email'],['copy','Copy link']].forEach(function(item){var b=document.createElement('button');b.type='button';b.className='bh-share-menu__item';b.setAttribute('aria-label',item[1]);b.title=item[1];b.innerHTML=icon(item[0]);b.addEventListener('click',function(){var link=url,text='Have a look at these family activities on Bubba Hub';if(item[0]==='facebook')window.open('https://www.facebook.com/sharer/sharer.php?u='+encodeURIComponent(link),'bhshare','width=700,height=600');else if(item[0]==='whatsapp')window.open('https://wa.me/?text='+encodeURIComponent(text+' '+link),'bhshare','width=700,height=600');else if(item[0]==='x')window.open('https://twitter.com/intent/tweet?text='+encodeURIComponent(text)+'&url='+encodeURIComponent(link),'bhshare','width=700,height=600');else if(item[0]==='email')window.location.href='mailto:?subject='+encodeURIComponent(title)+'&body='+encodeURIComponent(text+'\n\n'+link);else if(navigator.clipboard)navigator.clipboard.writeText(link).then(function(){b.setAttribute('aria-label','Copied');setTimeout(function(){b.setAttribute('aria-label',item[1])},1200)});else window.prompt('Copy this share link:',link)});box.appendChild(b)});button.insertAdjacentElement('afterend',box)}
document.addEventListener('click',function(e){var b=e.target.closest('[data-bh-share-url]');if(!b)return;e.preventDefault();show(b,clean(b.getAttribute('data-bh-share-url')||''),b.getAttribute('data-bh-share-name')||'Bubba Hub family activities')});
document.addEventListener('click',function(e){if(!e.target.closest('.bh-share-menu')&&!e.target.closest('[data-bh-share-url]'))document.querySelectorAll('.bh-share-menu').forEach(function(m){m.remove()})});
document.addEventListener('submit',function(e){var f=e.target.closest('.bh-directory-search__save-form');if(!f)return;var b=f.querySelector('button[type="submit"]');if(!b||b.disabled)return;b.disabled=true;b.classList.add('is-saving');b.innerHTML='<span class="bh-directory-search__spinner" aria-hidden="true"></span>Saving…'})
}());