(function () {
    function positionVisitedControls() {
        document.querySelectorAll('.bh-visited-listing').forEach(function (visited) {
            var candidates = document.querySelectorAll('a, button');
            var bookmark = null;
            candidates.forEach(function (el) {
                var text = (el.textContent || '').trim();
                if (!bookmark && /^bookmark$/i.test(text)) bookmark = el;
            });
            if (!bookmark || !bookmark.parentElement) return;
            if (visited.parentElement !== bookmark.parentElement) bookmark.parentElement.appendChild(visited);
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