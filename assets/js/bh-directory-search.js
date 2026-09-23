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
    }

    window.BubbaHubInitDirectorySearch = initDirectorySearch;

    document.addEventListener('DOMContentLoaded', initDirectorySearch);

    window.addEventListener('popstate', function () {
        window.location.reload();
    });
}());
