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

    document.addEventListener('click', function (event) {
        var button = event.target.closest('[data-bh-print-calendar]');
        if (!button) {
            return;
        }

        var planner = button.closest('[data-bh-planner]');
        if (!planner) {
            return;
        }

        var calendar = planner.querySelector('.bh-planner');
        if (!calendar) {
            return;
        }

        document.body.classList.add('bh-printing-calendar');
        window.print();

        window.setTimeout(function () {
            document.body.classList.remove('bh-printing-calendar');
        }, 500);
    });

    document.addEventListener('DOMContentLoaded', bindPlannerNavigation);

    window.addEventListener('popstate', function () {
        window.location.reload();
    });
}());
