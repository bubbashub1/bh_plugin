(function () {
    'use strict';

    function bindPlannerNavigation() {
        document.querySelectorAll('[data-bh-planner] .bh-planner-shell__navigation a').forEach(function (link) {
            if (link.dataset.bhPlannerBound === '1') {
                return;
            }

            link.dataset.bhPlannerBound = '1';

            link.addEventListener('click', function (event) {
                event.preventDefault();

                var planner = link.closest('[data-bh-planner]');
                if (!planner) {
                    window.location.href = link.href;
                    return;
                }

                planner.setAttribute('aria-busy', 'true');

                fetch(link.href, {
                    credentials: 'same-origin',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                    .then(function (response) {
                        if (!response.ok) {
                            throw new Error('Planner request failed');
                        }
                        return response.text();
                    })
                    .then(function (html) {
                        var doc = new DOMParser().parseFromString(html, 'text/html');
                        var replacement = doc.querySelector('[data-bh-planner]');

                        if (!replacement) {
                            throw new Error('Planner content not found');
                        }

                        planner.replaceWith(replacement);
                        window.history.pushState({ bhPlanner: true }, '', link.href);
                        bindPlannerNavigation();
                    })
                    .catch(function () {
                        window.location.href = link.href;
                    })
                    .finally(function () {
                        var current = document.querySelector('[data-bh-planner]');
                        if (current) {
                            current.removeAttribute('aria-busy');
                        }
                    });
            });
        });
    }

    document.addEventListener('DOMContentLoaded', bindPlannerNavigation);

    window.addEventListener('popstate', function () {
        window.location.reload();
    });
}());
