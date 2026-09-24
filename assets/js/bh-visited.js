(function () {
    function positionVisitedControls() {
        document.querySelectorAll('.bh-visited-listing').forEach(function (visited) {
            var scope = visited.parentElement;
            var bookmark = null;
            var levels = 0;

            while (scope && levels < 7 && !bookmark) {
                var candidates = scope.querySelectorAll('a, button, [role="button"]');
                candidates.forEach(function (el) {
                    if (bookmark || el === visited || visited.contains(el)) return;
                    var text = (el.textContent || '').trim();
                    var label = (el.getAttribute('aria-label') || '') + ' ' + (el.getAttribute('title') || '');
                    if (/^bookmark$/i.test(text) || /bookmark/i.test(label)) bookmark = el;
                });
                if (!bookmark) {
                    scope = scope.parentElement;
                    levels++;
                }
            }

            if (!bookmark || !bookmark.parentElement) return;
            if (visited.parentElement !== bookmark.parentElement) {
                bookmark.parentElement.appendChild(visited);
            }
        });
    }

    function bindPlannerControls() {
        document.querySelectorAll('[data-bh-planner-toggle]').forEach(function (button) {
            if (button.dataset.bhPlannerBound) return;
            button.dataset.bhPlannerBound = '1';
            button.addEventListener('click', function (event) {
                event.stopPropagation();
                var id = button.getAttribute('data-bh-planner-toggle');
                var popover = document.querySelector('[data-bh-planner-popover="' + id + '"]');
                if (!popover) return;
                var open = !popover.hidden;
                document.querySelectorAll('[data-bh-planner-popover]').forEach(function (item) { item.hidden = true; });
                popover.hidden = open;
            });
        });
        document.querySelectorAll('[data-bh-planner-close]').forEach(function (button) {
            if (button.dataset.bhPlannerCloseBound) return;
            button.dataset.bhPlannerCloseBound = '1';
            button.addEventListener('click', function () {
                var popover = button.closest('[data-bh-planner-popover]');
                if (popover) popover.hidden = true;
            });
        });
    }

    function closePlannerPopovers(event) {
        if (event.target.closest('.bh-planner-listing-action')) return;
        document.querySelectorAll('[data-bh-planner-popover]').forEach(function (item) { item.hidden = true; });
    }

    function init() {
        positionVisitedControls();
        bindPlannerControls();
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
    document.addEventListener('click', closePlannerPopovers);
    window.setTimeout(init, 500);
    window.setTimeout(init, 1500);
}());