(() => {
    const calendar = document.getElementById('dashboard-khmer-calendar');
    if (!calendar || !window.momentkh) return;

    const grid = calendar.querySelector('.khmer-calendar-grid');
    const body = grid.tBodies[0];
    const lunarDate = calendar.querySelector('[data-calendar-date]');
    const solarDate = calendar.querySelector('[data-calendar-solar]');
    const selectedHoliday = calendar.querySelector('[data-selected-holiday]');
    const locale = {en: 'en', km: 'km-KH-u-nu-khmr', zh: 'zh-CN'}[calendar.lang] || 'en';
    const holidayLabels = JSON.parse(calendar.dataset.holidayLabels);
    const fullDateLabel = new Intl.DateTimeFormat(locale, {timeZone: 'UTC', dateStyle: 'full'});
    const cambodiaDate = new Intl.DateTimeFormat('en-CA', {
        timeZone: 'Asia/Phnom_Penh', year: 'numeric', month: 'numeric', day: 'numeric',
    });
    const monthLabel = new Intl.DateTimeFormat(locale, {
        timeZone: 'UTC', month: 'long', year: 'numeric',
    });
    const numerals = new Intl.NumberFormat(locale);
    const holidays = calendar.querySelector('[data-calendar-holidays]');
    const holidayList = holidays.querySelector('ul');

    // Cambodia's 2026 Legal Reform Committee calendar and 2027 Sub-Decree No. 198.
    const commonHolidays = {
        '1-1': 'new_year',
        '1-7': 'victory',
        '3-8': 'women',
        '4-14': 'khmer_new_year', '4-15': 'khmer_new_year', '4-16': 'khmer_new_year',
        '5-1': 'labour',
        '5-14': 'king_birthday',
        '6-18': 'queen_mother',
        '9-24': 'constitution',
        '10-15': 'king_father',
        '10-29': 'coronation',
        '11-9': 'independence',
        '12-29': 'peace',
    };
    const holidaySchedules = {
        2026: {
            ...commonHolidays,
            '5-1': 'labour_and_visak',
            '5-5': 'ploughing',
            '10-10': 'pchum_ben', '10-11': 'pchum_ben', '10-12': 'pchum_ben',
            '11-23': 'water_festival', '11-24': 'water_festival', '11-25': 'water_festival',
        },
        2027: {
            ...commonHolidays,
            '5-20': 'visak', '5-24': 'ploughing',
            '9-29': 'pchum_ben', '9-30': 'pchum_ben', '10-1': 'pchum_ben',
            '11-12': 'water_festival', '11-13': 'water_festival', '11-14': 'water_festival',
        },
    };

    function today() {
        const parts = Object.fromEntries(cambodiaDate.formatToParts().map(({type, value}) => [type, value]));
        return new Date(Date.UTC(Number(parts.year), Number(parts.month) - 1, Number(parts.day)));
    }

    let selected = today();

    function selectDay(date) {
        selected = date;
        const year = date.getUTCFullYear();
        const month = date.getUTCMonth() + 1;
        const day = date.getUTCDate();
        const lunar = momentkh.fromGregorian(year, month, day, 12);
        solarDate.textContent = fullDateLabel.format(date);
        lunarDate.textContent = momentkh.format(lunar);
        const holiday = holidayLabels[holidaySchedules[year]?.[`${month}-${day}`]];
        selectedHoliday.querySelector('p').textContent = holiday || '';
        selectedHoliday.hidden = !holiday;
        for (const button of body.querySelectorAll('button')) {
            button.setAttribute('aria-pressed', Number(button.dataset.calendarDay) === day);
        }
    }

    function renderMonth() {
        const year = selected.getUTCFullYear();
        const month = selected.getUTCMonth();
        const first = new Date(Date.UTC(year, month, 1));
        const start = first.getUTCDay();
        const days = new Date(Date.UTC(year, month + 1, 0)).getUTCDate();
        const current = today().getTime();
        const schedule = holidaySchedules[year];
        const monthHolidays = new Map();

        grid.caption.textContent = monthLabel.format(first);
        body.replaceChildren();
        for (let slot = 0; slot < Math.ceil((start + days) / 7) * 7; slot++) {
            if (slot % 7 === 0) body.insertRow();
            const cell = body.rows[body.rows.length - 1].insertCell();
            const day = slot - start + 1;
            if (day < 1 || day > days) continue;

            const button = document.createElement('button');
            button.type = 'button';
            button.dataset.calendarDay = day;
            button.textContent = numerals.format(day);
            const holiday = holidayLabels[schedule?.[`${month + 1}-${day}`]];
            if (holiday) {
                button.classList.add('is-holiday');
                button.title = holiday;
                button.setAttribute('aria-label', `${button.textContent} · ${holiday}`);
                const flag = document.createElement('i');
                flag.className = 'bi bi-flag-fill';
                flag.setAttribute('aria-hidden', 'true');
                if (!monthHolidays.has(holiday)) monthHolidays.set(holiday, []);
                monthHolidays.get(holiday).push(button.textContent);
                button.append(flag);
            }
            if (Date.UTC(year, month, day) === current) button.setAttribute('aria-current', 'date');
            cell.append(button);
        }
        holidayList.replaceChildren();
        for (const [name, dates] of monthHolidays) {
            const item = document.createElement('li');
            const dateBadge = document.createElement('span');
            dateBadge.textContent = dates.join(', ');
            item.append(dateBadge, name);
            holidayList.append(item);
        }
        holidayList.hidden = monthHolidays.size === 0;
        holidays.querySelector('[data-holiday-coverage]').hidden = Boolean(schedule);
        holidays.hidden = Boolean(schedule) && monthHolidays.size === 0;
        selectDay(selected);
    }

    calendar.addEventListener('click', event => {
        const button = event.target.closest('button');
        if (!button) return;
        const year = selected.getUTCFullYear();
        const month = selected.getUTCMonth();
        if (button.dataset.calendarDay) {
            selectDay(new Date(Date.UTC(year, month, Number(button.dataset.calendarDay))));
            return;
        }
        const action = button.dataset.calendarAction;
        selected = action === 'today'
            ? today()
            : new Date(Date.UTC(year, month + (action === 'previous' ? -1 : 1), 1));
        renderMonth();
    });

    renderMonth();
    calendar.querySelector('[data-calendar-layout]').hidden = false;
    calendar.querySelector('[data-calendar-controls]').hidden = false;
    calendar.querySelector('[data-calendar-fallback]').hidden = true;
})();
