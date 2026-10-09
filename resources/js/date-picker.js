/**
 * The date range picker on the booking wizard. It replaces the browser's
 * own date inputs, which ignore the organiser's language and cannot show which
 * days are fully booked. Both ends are event days, so a one-day event starts
 * and ends on the same date. The chosen dates live in two hidden inputs, so the
 * wizard reads, saves and validates them exactly as before.
 */
const DAY_MS = 86400000;

const toIso = (year, month, day) => `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
const parse = (iso) => iso.split('-').map(Number);
const addDays = (iso, days) => {
    const [year, month, day] = parse(iso);
    const date = new Date(Date.UTC(year, month - 1, day) + days * DAY_MS);
    return toIso(date.getUTCFullYear(), date.getUTCMonth() + 1, date.getUTCDate());
};
const diffDays = (from, to) => {
    const [y1, m1, d1] = parse(from);
    const [y2, m2, d2] = parse(to);
    return Math.round((Date.UTC(y2, m2 - 1, d2) - Date.UTC(y1, m1 - 1, d1)) / DAY_MS);
};

/**
 * @param {object} options
 * @param {HTMLElement} options.root  The [data-date-picker] element.
 * @param {HTMLInputElement} options.startDate
 * @param {HTMLInputElement} options.endDate
 * @param {{today: string, maxDays: number, locale: string}} options.config
 * @param {Record<string, string>} options.labels
 * @param {(iso: string) => string} options.formatDate
 * @param {() => void} options.onChange  Called after the guest changes either date.
 */
export function createDatePicker({ root, startDate, endDate, config, labels, formatDate, onChange }) {
    const grid = root.querySelector('[data-cal-grid]');
    const title = root.querySelector('[data-cal-title]');
    const weekdays = root.querySelector('[data-cal-weekdays]');
    const prevButton = root.querySelector('[data-cal-prev]');
    const nextButton = root.querySelector('[data-cal-next]');
    const slots = {
        start_date: root.querySelector('[data-slot="start_date"]'),
        end_date: root.querySelector('[data-slot="end_date"]'),
    };

    /** @type {Set<string>|null} Open days, or null while unknown (then every future day is allowed). */
    let openDays = null;
    let lastDay = null;
    let [year, month] = parse(config.today);

    const monthTitle = new Intl.DateTimeFormat(config.locale, { month: 'long', year: 'numeric' });
    const dayLabel = new Intl.DateTimeFormat(config.locale, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });

    // 2024-01-01 is a Monday, so seven days from it are the weekday names, Monday first.
    const weekdayFormat = new Intl.DateTimeFormat(config.locale, { weekday: 'short', timeZone: 'UTC' });
    weekdays.replaceChildren(...Array.from({ length: 7 }, (_, index) => {
        const name = document.createElement('span');
        name.textContent = weekdayFormat.format(new Date(Date.UTC(2024, 0, 1 + index)));
        return name;
    }));

    const isOpenDay = (iso) => iso >= config.today && (openDays === null || openDays.has(iso));

    /** The last event day reachable from the chosen start without crossing a fully booked day. */
    function latestEndDate(from) {
        let last = from;
        for (let offset = 0; offset < config.maxDays; offset += 1) {
            const day = addDays(from, offset);
            if (!isOpenDay(day)) break;
            last = day;
        }
        return last;
    }

    function selectable(iso) {
        const from = startDate.value;
        const choosingOut = from && !endDate.value;

        if (choosingOut && iso >= from) return iso <= latestEndDate(from);

        return isOpenDay(iso);
    }

    function setDates(from, to) {
        startDate.value = from;
        endDate.value = to;
        onChange();
        render();
    }

    function pick(iso) {
        const from = startDate.value;

        if (from && !endDate.value && iso >= from) {
            setDates(from, iso);
        } else if (isOpenDay(iso)) {
            setDates(iso, '');
        }
    }

    function moveFocus(cell, step) {
        const cells = [...grid.querySelectorAll('button:not(:disabled)')];
        const target = cells[cells.indexOf(cell) + step];
        if (target) {
            cell.tabIndex = -1;
            target.tabIndex = 0;
            target.focus();
        }
    }

    function render() {
        title.textContent = monthTitle.format(new Date(Date.UTC(year, month - 1, 1)));

        const first = new Date(Date.UTC(year, month - 1, 1));
        const offset = (first.getUTCDay() + 6) % 7;
        const total = new Date(Date.UTC(year, month, 0)).getUTCDate();
        const from = startDate.value;
        const to = endDate.value;
        const cells = [];
        let tabbable = null;

        for (let blank = 0; blank < offset; blank += 1) cells.push(document.createElement('span'));

        for (let day = 1; day <= total; day += 1) {
            const iso = toIso(year, month, day);
            const button = document.createElement('button');
            const allowed = selectable(iso);

            button.type = 'button';
            button.textContent = String(day);
            button.dataset.date = iso;
            button.disabled = !allowed;
            button.tabIndex = -1;
            button.className = 'wizard-cal-day';

            const spoken = dayLabel.format(new Date(Date.UTC(year, month - 1, day)));
            button.setAttribute('aria-label', !allowed && iso >= config.today ? `${spoken}, ${labels.cal_full}` : spoken);

            if (iso < config.today) button.classList.add('is-past');
            else if (!allowed) button.classList.add('is-full');
            if (iso === from) button.classList.add('is-start');
            if (iso === to) button.classList.add('is-end');
            if (from && to && iso > from && iso < to) button.classList.add('is-between');
            if (iso === from || iso === to) button.setAttribute('aria-pressed', 'true');

            if (allowed && (tabbable === null || iso === from)) tabbable = button;
            cells.push(button);
        }

        if (tabbable) {
            tabbable.tabIndex = 0;
            tabbable.dataset.calFocus = '';
        }

        grid.replaceChildren(...cells);

        const [currentYear, currentMonth] = parse(config.today);
        prevButton.disabled = year === currentYear && month === currentMonth;
        const ceiling = (lastDay ? addDays(lastDay, 1) : addDays(config.today, 366)).slice(0, 7);
        nextButton.disabled = toIso(year, month, 1).slice(0, 7) >= ceiling;

        slots.start_date.querySelector('[data-slot-value]').textContent = from ? formatDate(from) : labels.cal_choose;
        slots.end_date.querySelector('[data-slot-value]').textContent = to ? formatDate(to) : labels.cal_choose;
        slots.start_date.toggleAttribute('data-active', !from || Boolean(to));
        slots.end_date.toggleAttribute('data-active', Boolean(from) && !to);
    }

    function step(delta) {
        month += delta;
        if (month < 1) { month = 12; year -= 1; }
        if (month > 12) { month = 1; year += 1; }
        render();
    }

    grid.addEventListener('click', (event) => {
        const cell = event.target.closest('button[data-date]');
        if (cell && !cell.disabled) pick(cell.dataset.date);
    });

    grid.addEventListener('keydown', (event) => {
        const cell = event.target.closest('button[data-date]');
        const moves = { ArrowRight: 1, ArrowLeft: -1, ArrowDown: 7, ArrowUp: -7 };
        if (cell && moves[event.key]) {
            event.preventDefault();
            moveFocus(cell, moves[event.key]);
        }
    });

    prevButton.addEventListener('click', () => step(-1));
    nextButton.addEventListener('click', () => step(1));

    return {
        /** Applies the days the server says are open. A failed or empty answer leaves the picker permissive or closed, respectively. */
        setAvailability(dates) {
            openDays = new Set(dates);
            lastDay = dates.length ? dates[dates.length - 1] : null;
            render();
        },
        get hasOpenDays() {
            return openDays === null || openDays.size > 0;
        },
        /** Shows the month of the chosen move-in, or the current month. */
        show() {
            [year, month] = parse(startDate.value || config.today);
            render();
        },
        clear() {
            startDate.value = '';
            endDate.value = '';
            this.show();
        },
    };
}
