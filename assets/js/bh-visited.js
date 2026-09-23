(function () {
    function positionVisitedControl() {
        var visited = document.querySelector('.bh-visited-listing');
        if (!visited) return;

        var candidates = document.querySelectorAll('a, button');
        var bookmark = null;

        candidates.forEach(function (el) {
            var text = (el.textContent || '').trim();
            if (!bookmark && /^bookmark$/i.test(text)) {
                bookmark = el;
            }
        });

        if (!bookmark) return;

        var bookmarkGroup = bookmark.parentElement;
        if (!bookmarkGroup) return;

        if (visited.parentElement !== bookmarkGroup) {
            bookmarkGroup.appendChild(visited);
        }

        candidates.forEach(function (el) {
            var text = (el.textContent || '').trim();
            if (/^bookmark$/i.test(text)) {
                el.childNodes.forEach(function (node) {
                    if (node.nodeType === 3) node.textContent = node.textContent.replace(/bookmark/i, 'Save');
                });
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', positionVisitedControl);
    } else {
        positionVisitedControl();
    }

    window.setTimeout(positionVisitedControl, 500);
    window.setTimeout(positionVisitedControl, 1500);
}());
