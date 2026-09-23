(function () {
    'use strict';

    function submitFilters(form) {
        if (!form || form.dataset.bhAutoSubmitting === '1') {
            return;
        }

        form.dataset.bhAutoSubmitting = '1';

        // Reset pagination whenever filters change.
        var page = form.querySelector('input[name="bh_page"]');
        if (page) {
            page.value = '';
        }

        form.submit();
    }

    function initAutoFilters() {
        document.querySelectorAll('.bh-directory-search__form').forEach(function (form) {
            if (form.dataset.bhAutoFiltersBound === '1') {
                return;
            }

            form.dataset.bhAutoFiltersBound = '1';

            // Dropdowns and checkboxes update the directory immediately.
            form.querySelectorAll('select, input[type="checkbox"]').forEach(function (field) {
                field.addEventListener('change', function () {
                    // Region needs to load its towns first; do not submit until
                    // the user has selected a town or another filter.
                    if (field.id === 'bh-region') {
                        return;
                    }
                    submitFilters(form);
                });
            });

            // Search text updates after the user pauses typing.
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

            // Price is a free-text filter, so update when the field loses focus.
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
                    // Clearing the region is itself a filter change.
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

                        // Region is a filter too, so refresh immediately after
                        // the town list has been loaded.
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

    document.addEventListener('DOMContentLoaded', function () {
        initRegionTown();
        initAutoFilters();
    });
}());
