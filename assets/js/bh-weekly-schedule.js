document.addEventListener('DOMContentLoaded', function () {
    const schedules = document.querySelectorAll('.bh-weekly-schedule-widget');
    if (!schedules.length) return;

    const mobile = window.matchMedia('(max-width: 600px)');

    schedules.forEach(function (schedule) {
        const days = Array.from(schedule.querySelectorAll('.bh-weekly-schedule-widget__day'));

        function setState() {
            if (mobile.matches) {
                days.forEach(function (day) {
                    day.open = day.classList.contains('is-today');
                });
            } else {
                days.forEach(function (day) {
                    day.open = true;
                });
            }
        }

        setState();
        if (typeof mobile.addEventListener === 'function') {
            mobile.addEventListener('change', setState);
        } else {
            mobile.addListener(setState);
        }
    });
});
