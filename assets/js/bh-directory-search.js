(function () {
    'use strict';

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
                    return;
                }

                var body = new URLSearchParams();
                body.append('action', 'bh_get_towns');
                body.append('nonce', window.BubbaHubDirectorySearch ? BubbaHubDirectorySearch.nonce : '');
                body.append('region', regionValue);

                fetch(window.BubbaHubDirectorySearch.ajaxUrl, {
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
                    })
                    .catch(function () {
                        town.innerHTML = '';
                        var retryOption = document.createElement('option');
                        retryOption.value = '';
                        retryOption.textContent = 'Unable to load towns — try again';
                        town.appendChild(retryOption);
                        town.disabled = false;
                    });
            });
        });
    }

    document.addEventListener('DOMContentLoaded', initRegionTown);
}());
