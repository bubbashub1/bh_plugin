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

(function(){'use strict';function clean(raw){var u;try{u=new URL(raw,window.location.origin)}catch(e){u=new URL(window.location.href)}var a=['bh_search','bh_category','bh_age_range','bh_region','bh_town','bh_saved_location','bh_day','bh_price','bh_free_activity','bh_view','bh_date'],c=new URL(u.origin+u.pathname);a.forEach(function(k){var v=u.searchParams.get(k);if(v)c.searchParams.set(k,v)});return c.toString()}document.addEventListener('click',function(e){var b=e.target.closest('[data-bh-share-url]');if(!b)return;e.preventDefault();var u=clean(b.getAttribute('data-bh-share-url')||''),o={title:b.getAttribute('data-bh-share-name')||'Bubba Hub family activities',text:'Have a look at these family activities on Bubba Hub',url:u};if(navigator.share){b.classList.add('is-sharing');navigator.share(o).catch(function(){}).finally(function(){b.classList.remove('is-sharing')})}else if(navigator.clipboard&&navigator.clipboard.writeText){navigator.clipboard.writeText(u).then(function(){alert('Share link copied to your clipboard.')})}else{window.prompt('Copy this share link:',u)}});document.addEventListener('submit',function(e){var f=e.target.closest('.bh-directory-search__save-form');if(!f)return;var b=f.querySelector('button[type="submit"]');if(!b||b.disabled)return;b.disabled=true;b.classList.add('is-saving');b.innerHTML='<span class="bh-directory-search__spinner" aria-hidden="true"></span>Saving…'})}());