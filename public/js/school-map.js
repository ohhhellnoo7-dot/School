(() => {
    const scanner = document.querySelector('[data-scan-location]') || document.querySelector('[data-global-scan]');
    const dialog = document.querySelector('[data-scan-dialog]');
    const result = dialog?.querySelector('[data-scan-dialog-result]') || document.querySelector('[data-scan-result]');
    dialog?.querySelector('[data-close-scan]')?.addEventListener('click', () => dialog.close());
    const headerScanner = document.querySelector('.teacher-scan-shortcut');
    let nextScanAction = 'check_in';
    document.querySelector('[data-finish-work]')?.addEventListener('click', () => {
        if (!scanner || scanner.disabled) return;
        nextScanAction = 'check_out';
        scanner.click();
    });
    let loadingTimer;
    let loadingPercent = 1;
    let loadingStage = 0;
    const updateLoading = () => {
        const bar = result?.querySelector('.scan-loading-progress');
        if (!bar) return;
        bar.setAttribute('aria-valuenow', String(loadingPercent));
        bar.setAttribute('aria-valuetext', `${loadingPercent}% - ${['Accessing location', 'Getting coordinates', 'Preparing check-in'][loadingStage]}`);
        bar.querySelector('span').style.width = `${loadingPercent}%`;
        result.querySelector('[data-loading-percent]').textContent = `${loadingPercent}%`;
        result.querySelectorAll('.scan-loading-steps li').forEach((step, index) => {
            step.classList.toggle('is-active', index === loadingStage && loadingPercent < 100);
            step.classList.toggle('is-complete', index < loadingStage || loadingPercent === 100);
        });
    };
    const advanceLoading = stage => {
        loadingStage = stage;
        loadingPercent = Math.max(loadingPercent, [1, 25, 80][stage]);
        updateLoading();
    };
    const showLoading = (checking = false) => {
        dialog?.classList.remove('scan-dialog--celebration');
        dialog?.classList.remove('scan-dialog--failed');
        const template = document.getElementById('scan-loading-template');
        if (!dialog || !template) return false;
        dialog.classList.add('scan-dialog--loading');
        result.classList.remove('p-3', 'border', 'rounded-3', 'text-success', 'text-danger');
        if (!checking || !result.querySelector('.scan-loading')) {
            clearInterval(loadingTimer);
            result.replaceChildren(template.content.cloneNode(true));
            loadingPercent = 1;
            loadingStage = 0;
            updateLoading();
            // Device location has no measurable percentage; estimate within each stage.
            loadingTimer = setInterval(() => {
                loadingPercent = Math.min([24, 79, 95][loadingStage], loadingPercent + 1);
                updateLoading();
            }, 180);
        }
        result.setAttribute('aria-busy', 'true');
        if (checking) {
            advanceLoading(2);
            result.querySelector('[data-loading-heading]').textContent = 'Verifying your school location…';
            result.querySelector('[data-loading-description]').textContent = 'Checking the attendance zone and preparing your check-in.';
            result.querySelector('[role="progressbar"]').setAttribute('aria-valuetext', 'Verifying school location');
            result.querySelectorAll('.scan-loading-steps li').forEach((step, index) => {
                step.classList.toggle('is-active', index === 2);
                step.classList.toggle('is-complete', index < 2);
            });
        }
        return true;
    };
    const finishLoading = () => {
        clearInterval(loadingTimer);
        dialog?.classList.remove('scan-dialog--loading');
        result?.removeAttribute('aria-busy');
    };
    headerScanner?.addEventListener('click', event => {
        // On the map, both controls start the same scan; the link carries scan intent across pages.
        if (!scanner || !result) return;
        event.preventDefault();
        if (scanner.disabled) return;
        scanner.click();
    });
    const showReceipt = data => {
        finishLoading();
        dialog?.classList.remove('scan-dialog--failed');
        dialog?.classList.add('scan-dialog--celebration');
        const late = data.status === 'late' && data.action !== 'check_out';
        const card = document.createElement('article');
        card.className = `checkin-receipt${late ? ' checkin-receipt--late' : ''}`;
        const add = (tag, className, text, parent = card) => {
            const node = document.createElement(tag);
            node.className = className;
            node.textContent = text;
            parent.append(node);
            return node;
        };
        const confetti = add('div', 'checkin-confetti', '');
        confetti.setAttribute('aria-hidden', 'true');
        for (let index = 0; index < 12; index++) {
            const piece = add('span', '', '', confetti);
            piece.style.setProperty('--piece', index);
        }
        const symbol = add('div', 'checkin-symbol', '');
        symbol.setAttribute('aria-hidden', 'true');
        if (late) {
            add('i', 'bi bi-alarm', '', symbol);
        } else {
            symbol.innerHTML = '<svg class="checkin-drawn-check" viewBox="0 0 72 72" fill="none"><path d="m15 37 14 14 29-33" stroke="currentColor" stroke-width="7" stroke-linecap="round" stroke-linejoin="round" pathLength="1"/></svg>';
        }
        add('h2', 'checkin-title', late ? 'You made it!' : 'Congratulations!');
        add('div', 'checkin-ribbon', data.test_only ? 'Location Verified' : (late ? 'Late Check-in' : 'Check-in Successful'));
        add('p', 'checkin-subtitle', data.test_only ? 'Administrator test · no attendance saved.' : (late ? 'You checked in after the allowed time.' : 'You are Present today. 🎉'));
        const lateDuration = Number.isInteger(data.late_minutes) && data.late_minutes >= 60
            ? `${Math.floor(data.late_minutes / 60)}h${data.late_minutes % 60 ? ` ${data.late_minutes % 60}m` : ''}`
            : `${data.late_minutes} ${data.late_minutes === 1 ? 'minute' : 'minutes'}`;
        if (late) add('p', 'checkin-delay', Number.isInteger(data.late_minutes)
            ? `${lateDuration} late · from session start`
            : 'Late arrival · timing details unavailable');
        const details = add('div', 'checkin-details', '');
        const identity = add('div', 'checkin-identity', '', details);
        const avatar = add('span', 'checkin-avatar', (data.teacher_name || 'T').trim().charAt(0).toUpperCase(), identity);
        avatar.setAttribute('aria-hidden', 'true');
        const photoUrl = data.profile_photo_url || document.querySelector('.profile-link img.user-avatar')?.getAttribute('src');
        if (photoUrl) {
            const photo = document.createElement('img');
            photo.src = photoUrl;
            photo.alt = '';
            photo.addEventListener('error', () => photo.remove(), { once: true });
            avatar.append(photo);
        }
        const person = add('div', 'checkin-person', '', identity);
        add('strong', '', data.teacher_name || 'Teacher', person);
        if (data.teacher_number) add('small', '', `Teacher ID: ${data.teacher_number}`, person);
        if (!late) {
            const chill = add('span', 'checkin-chill', '😎', identity);
            chill.setAttribute('aria-hidden', 'true');
        } else {
            const angry = add('span', 'checkin-angry', '😤', identity);
            angry.setAttribute('aria-hidden', 'true');
        }
        add('span', 'checkin-presence', data.test_only ? 'Verified' : (late ? 'Late' : 'Present'), identity);
        const row = (icon, text, secondary) => {
            const line = add('div', 'checkin-detail', '', details);
            add('i', `bi bi-${icon}`, '', line).setAttribute('aria-hidden', 'true');
            const value = add('div', '', '', line);
            add('span', '', text, value);
            if (secondary) add('small', '', secondary, value);
        };
        const stamp = data.scanned_at ? new Date(data.scanned_at) : null;
        const validStamp = stamp && !Number.isNaN(stamp.getTime());
        // Match the school's attendance timezone, regardless of the device timezone.
        const format = options => new Intl.DateTimeFormat('en-GB', { timeZone: data.timezone || 'Asia/Phnom_Penh', ...options }).format(stamp);
        row('calendar4', validStamp ? format({ day: '2-digit', month: 'short', year: 'numeric' }) : 'Date unavailable');
        row('clock', validStamp ? format({ hour: '2-digit', minute: '2-digit', hour12: true }).toUpperCase() : 'Time unavailable', data.session ? `${data.session} session` : null);
        row('geo-alt-fill', data.location_name);
        row('bullseye', Number.isFinite(Number(data.accuracy_meters)) && data.accuracy_meters != null ? `±${data.accuracy_meters} m` : 'Unavailable', 'Location accuracy');
        add('div', 'checkin-status', data.test_only ? '✓  Inside school area' : (late ? '◷  Late' : '✓  Present'));
        add('div', 'checkin-landscape', 'អបអរសាទរ · SCHOOL ATTENDANCE').setAttribute('aria-hidden', 'true');
        result.classList.remove('p-3', 'border', 'rounded-3', 'text-success', 'text-danger');
        card.querySelector('.checkin-subtitle').textContent = data.test_only ? 'Administrator test · no attendance saved.' : (late ? 'Your attendance has been recorded as late.' : 'Your attendance has been recorded.');
        card.querySelector('.checkin-status').textContent = late ? '◷  Late attendance recorded' : '✓  Inside School Area';
        result.replaceChildren(card);
        if (data.action === 'check_out') {
            card.querySelector('.checkin-title').textContent = 'Work Finished!';
            card.querySelector('.checkin-ribbon').textContent = 'Check-out Successful';
            card.querySelector('.checkin-subtitle').textContent = 'Your departure time has been saved. Have a safe trip home!';
            card.querySelector('.checkin-status').textContent = '✓ Checked Out';
            card.querySelector('.checkin-presence').textContent = 'Finished';
            card.querySelector('.checkin-delay')?.remove();
            card.querySelector('.checkin-angry')?.remove();
            card.classList.remove('checkin-receipt--late');
        }
        if (!data.test_only && data.encouragement && (!late || data.action === 'check_out')) {
            const message = add('div', 'checkin-encouragement', '');
            add('span', 'checkin-encouragement-icon', data.action === 'check_out' ? '💛' : '🌟', message).setAttribute('aria-hidden', 'true');
            add('p', '', data.encouragement, message);
            details.before(message);
        }
        if (!dialog) card.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth', block: 'nearest' });
    };
    const showFailure = data => {
        finishLoading();
        dialog?.classList.remove('scan-dialog--celebration');
        dialog?.classList.add('scan-dialog--failed');
        result.classList.remove('p-3', 'border', 'rounded-3', 'text-success', 'text-danger');
        const card = document.createElement('article');
        card.className = 'checkin-failure';
        const outsideSession = data.reason === 'outside_session';
        if (outsideSession) card.classList.add('checkin-failure--session');
        const art = document.createElement('div');
        art.className = 'checkin-failure-art';
        art.setAttribute('aria-hidden', 'true');
        art.innerHTML = '<svg viewBox="0 0 320 175" fill="none" xmlns="http://www.w3.org/2000/svg"><defs><linearGradient id="failure-pin" x1="125" y1="10" x2="195" y2="145" gradientUnits="userSpaceOnUse"><stop stop-color="#ff7d98"/><stop offset=".5" stop-color="#ff315b"/><stop offset="1" stop-color="#d60035"/></linearGradient></defs><g class="failure-pin-rings"><ellipse cx="160" cy="151" rx="111" ry="22" fill="#ff8eb5" opacity=".35"/><ellipse cx="160" cy="151" rx="85" ry="15" stroke="white" stroke-width="3"/><ellipse cx="160" cy="151" rx="63" ry="12" fill="#ff5585" opacity=".5"/></g><g class="failure-pin-float"><path d="M160 9c-31 0-54 23-54 53 0 34 54 85 54 85s54-51 54-85c0-30-23-53-54-53Z" fill="url(#failure-pin)" stroke="#ffa6bd" stroke-width="2"/><circle cx="160" cy="61" r="30" fill="#fff9fc"/><path d="m147 48 26 26m0-26-26 26" stroke="#ed1647" stroke-width="9" stroke-linecap="round"/></g></svg>';
        card.append(art);
        if (outsideSession) {
            art.replaceChildren();
            const clock = document.createElement('i');
            clock.className = 'bi bi-clock-history';
            art.append(clock);
        }
        const add = (tag, className, text, parent = card) => {
            const node = document.createElement(tag);
            node.className = className;
            node.textContent = text;
            parent.append(node);
            return node;
        };
        const heading = add('h2', 'checkin-failure-title', data.action === 'check_out' ? 'Check-out ' : 'Check-in ');
        add('span', '', 'Failed!', heading);
        if (outsideSession) heading.textContent = 'Outside Session Hours';
        const outside = data.distance_meters !== undefined;
        add('p', 'checkin-failure-subtitle', data.message || 'The location check could not be completed.');
        const profile = document.querySelector('.profile-link');
        const name = data.teacher_name || profile?.querySelector('.user-info strong')?.textContent?.trim() || 'Your account';
        const identity = add('div', 'checkin-identity checkin-failure-identity', '');
        const avatar = add('span', 'checkin-avatar', name.charAt(0).toUpperCase(), identity);
        avatar.setAttribute('aria-hidden', 'true');
        const photoUrl = data.profile_photo_url || profile?.querySelector('img.user-avatar')?.getAttribute('src');
        if (photoUrl) {
            const photo = document.createElement('img');
            photo.src = photoUrl;
            photo.alt = '';
            photo.addEventListener('error', () => photo.remove(), { once: true });
            avatar.append(photo);
        }
        const person = add('div', 'checkin-person', '', identity);
        add('strong', '', name, person);
        add('small', '', data.action === 'check_out' ? 'Check-out was not completed' : 'Check-in was not completed', person);
        const crying = add('span', 'checkin-crying', '', identity);
        crying.setAttribute('aria-hidden', 'true');
        if (outsideSession) crying.classList.add('checkin-session-emoji');
        crying.innerHTML = '<svg viewBox="0 0 64 72" fill="none"><defs><radialGradient id="crying-face" cx=".35" cy=".25" r=".8"><stop stop-color="#fff296"/><stop offset=".65" stop-color="#ffd044"/><stop offset="1" stop-color="#f2a51e"/></radialGradient></defs><g class="crying-face"><circle cx="32" cy="30" r="26" fill="url(#crying-face)" stroke="#e9a42a"/><path d="M15 20q6 1 10-5m14 0q4 6 10 5" stroke="#895122" stroke-width="3" stroke-linecap="round"/><path d="M16 29q4-6 9 0m14 0q5-6 9 0" stroke="#613e29" stroke-width="3" stroke-linecap="round"/><ellipse cx="32" cy="43" rx="6" ry="7" fill="#73432c"/><path d="M29 47q3-3 6 0" stroke="#f6928c" stroke-width="3" stroke-linecap="round"/></g><g fill="#36bdff" stroke="#d2f6ff" stroke-width="1"><path class="crying-tear crying-tear--left" d="M20 29c-1 4-4 7-4 10a4 4 0 0 0 8 0c0-3-3-6-4-10Z"/><path class="crying-tear crying-tear--right" d="M44 29c-1 4-4 7-4 10a4 4 0 0 0 8 0c0-3-3-6-4-10Z"/></g></svg>';
        if (outsideSession) {
            crying.textContent = '🕒';
            person.querySelector('small').textContent = 'Waiting for your next session';
        }
        if (outside && !outsideSession) {
            const details = add('dl', 'checkin-failure-details', '');
            const meters = value => Number.isFinite(Number(value)) ? `${new Intl.NumberFormat('en-US').format(Number(value))} m` : 'Unavailable';
            [
                ['geo-alt-fill', 'School area', data.location_name || 'Active attendance location'],
                ['signpost-split', 'Estimated distance', meters(data.distance_meters)],
                ['crosshair', 'Location accuracy', data.accuracy_meters == null ? 'Unavailable' : `±${meters(data.accuracy_meters)}`],
                ['bullseye', 'Allowed radius', meters(data.radius_meters)],
            ].forEach(([icon, label, value]) => {
                const row = add('div', 'checkin-failure-row', '', details);
                const term = add('dt', '', '', row);
                add('i', `bi bi-${icon}`, '', term).setAttribute('aria-hidden', 'true');
                add('span', '', label, term);
                add('dd', '', value, row);
            });
        }
        if (data.device_location && !outsideSession) {
            const diagnostics = add('details', 'checkin-location-diagnostics', '');
            add('summary', '', 'Device location details', diagnostics);
            const { latitude, longitude } = data.device_location;
            add('p', '', `Your device reported: ${latitude.toFixed(6)}, ${longitude.toFixed(6)}. The map pin shows the saved school area, not your live position.`, diagnostics);
            if (data.distance_meters > 100000) {
                add('p', '', 'This reading is far from the school. Enable precise location in your device settings, remove any browser location override, or try scanning on a phone with GPS.', diagnostics);
            }
        }
        const retry = add('button', 'checkin-failure-retry', '');
        retry.type = 'button';
        add('i', 'bi bi-arrow-repeat', '', retry).setAttribute('aria-hidden', 'true');
        add('span', '', 'Try Again', retry);
        retry.addEventListener('click', () => {
            nextScanAction = data.action || 'check_in';
            scanner?.click();
        });
        if (outsideSession) {
            retry.replaceChildren();
            add('i', 'bi bi-calendar3', '', retry).setAttribute('aria-hidden', 'true');
            add('span', '', 'View Timetable', retry);
            retry.addEventListener('click', event => {
                event.stopImmediatePropagation();
                window.location.assign(dialog.dataset.timetableUrl);
            }, { capture: true });
        }
        result.replaceChildren(card);
    };
    const show = (target, message, state = '') => {
        if (target === result && state === 'error') return showFailure({ message });
        if (target === result) dialog?.classList.remove('scan-dialog--failed');
        if (target === result) finishLoading();
        if (target === result) target.classList.add('p-3', 'border', 'rounded-3');
        target.textContent = message;
        target.classList.remove('text-success', 'text-danger');
        if (state) target.classList.add(state === 'success' ? 'text-success' : 'text-danger');
    };
    const mapInput = document.getElementById('map-location');
    let targetVersion = 0;
    mapInput?.addEventListener('input', () => {
        targetVersion++;
        if (result && scanner?.dataset.checkTarget === 'true') {
            show(result, 'Location coordinates changed. Click Set attendance location before scanning.');
        }
    });
    // Run after the shared confirmation handler; only send an approved submission.
    document.addEventListener('submit', async event => {
        const form = event.target.closest('form[data-remove-location]');
        if (!form || event.defaultPrevented) return;
        event.preventDefault();
        const button = form.querySelector('button');
        if (button.disabled) return;
        const status = document.querySelector('[data-remove-location-status]');
        button.disabled = true;
        button.textContent = 'Removing...';
        show(status, 'Removing location...');
        try {
            const response = await fetch(form.action, {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Accept': 'application/json' },
                body: new FormData(form),
            });
            if (response.status === 419 || response.status === 401) throw new Error('Your session expired. Refresh the page and sign in again.');
            if (!response.ok) throw new Error('Could not remove this location. Please try again.');
            const data = await response.json();
            if (!data.success) throw new Error('Could not remove this location. Please try again.');
            const row = form.closest('[data-saved-location-row]');
            const nextButton = row.nextElementSibling?.querySelector('button') || row.previousElementSibling?.querySelector('button');
            row.remove();
            show(status, data.message, 'success');
            const remaining = document.querySelector('[data-saved-location-list] [data-attendance-eligible="true"]');
            if (scanner && !remaining) scanner.disabled = true;
            if (!remaining) {
                const summary = document.querySelector('[data-active-location-summary]');
                if (summary) summary.textContent = 'No active attendance location. Save or activate a location before scanning.';
            }
            if (result) show(result, remaining
                ? 'Locations changed. Start a new scan against the active attendance location.'
                : 'No active attendance location. Save or activate a location before scanning.');
            nextButton?.focus({ preventScroll: true });
        } catch (error) {
            show(status, error instanceof TypeError ? 'Could not reach the server. Check your connection and try again.' : error.message, 'error');
            button.disabled = false;
            button.textContent = 'Remove';
        }
    });
    const historyRoot = document.querySelector('[data-location-history]');
    if (historyRoot) {
        const input = document.getElementById('map-location');
        const menu = document.getElementById('map-location-history');
        const key = historyRoot.dataset.historyKey;
        const saved = JSON.parse(historyRoot.dataset.savedLocations);
        let recent = [];
        try {
            const stored = JSON.parse(localStorage.getItem(key) || '[]');
            if (Array.isArray(stored)) recent = stored.filter(value => typeof value === 'string' && value.length <= 300);
        } catch (_) { /* History still works when browser storage is unavailable. */ }
        const remember = value => {
            value = value.trim();
            if (!value || value.length > 300) return;
            recent = [value, ...recent.filter(previous => previous !== value)];
            try { localStorage.setItem(key, JSON.stringify(recent)); } catch (_) {}
        };
        // Also remember locations loaded through a map link or a saved location.
        remember(input.value);
        const close = () => {
            menu.hidden = true;
            input.setAttribute('aria-expanded', 'false');
        };
        const render = (filter = '') => {
            menu.replaceChildren();
            const heading = document.createElement('div');
            heading.className = 'small text-secondary px-2 py-1';
            heading.textContent = 'Location history';
            menu.append(heading);
            const seen = new Set();
            const entries = [...recent.map(value => saved.find(entry => entry.value === value) || { label: value, value }), ...saved];
            for (const entry of entries) {
                if (seen.has(entry.value) || !`${entry.label} ${entry.value}`.toLowerCase().includes(filter.toLowerCase())) continue;
                seen.add(entry.value);
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'btn btn-light text-start w-100 mb-1';
                button.textContent = entry.label === entry.value ? entry.value : `${entry.label} (${entry.value})`;
                button.addEventListener('click', () => {
                    input.value = entry.value;
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                    remember(entry.value);
                    close();
                    input.focus();
                    close();
                });
                menu.append(button);
            }
            if (!seen.size) {
                const empty = document.createElement('p');
                empty.className = 'small text-secondary px-2 mb-1';
                empty.textContent = 'No previous locations found.';
                menu.append(empty);
            }
            menu.hidden = false;
            input.setAttribute('aria-expanded', 'true');
        };
        input.addEventListener('focus', () => render());
        input.addEventListener('click', () => render());
        input.addEventListener('input', () => render(input.value.trim()));
        input.addEventListener('keydown', event => {
            if (event.key === 'Escape') close();
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                if (menu.hidden) render();
                menu.querySelector('button')?.focus();
            }
        });
        historyRoot.addEventListener('focusout', event => {
            if (!historyRoot.contains(event.relatedTarget)) close();
        });
        document.addEventListener('click', event => {
            if (!historyRoot.contains(event.target)) close();
        });
        input.form.addEventListener('submit', () => remember(input.value));
    }
    document.querySelector('[data-copy-map-coordinates]')?.addEventListener('click', () => {
        const status = document.querySelector('[data-coordinate-status]');
        const parts = document.getElementById('map-location').value.trim().split(',');
        const validNumber = /^[+-]?(?:\d+(?:\.\d*)?|\.\d+)$/;
        if (parts.length !== 2 || !parts.every(value => validNumber.test(value.trim()))) {
            show(status, 'Enter latitude, longitude in the map box, for example 11.5878424,104.9306214. For an address, copy its coordinates from Google Maps first.', 'error');
            return;
        }
        const [latitude, longitude] = parts.map(Number);
        if (Math.abs(latitude) > 90 || Math.abs(longitude) > 180) {
            show(status, 'Latitude must be between -90 and 90, and longitude between -180 and 180.', 'error');
            return;
        }
        document.getElementById('school-latitude').value = latitude;
        document.getElementById('school-longitude').value = longitude;
        show(status, 'Coordinates copied. Enter a name and radius, then save the attendance location to enable check-in there.');
        document.getElementById('school-location-name').focus();
    });
    const locate = () => new Promise((resolve, reject) => {
        if (!window.isSecureContext) return reject(new Error('Location scanning needs HTTPS. Open the secure website address.'));
        if (!navigator.geolocation) return reject(new Error('Your browser does not support location access.'));
        let best = null;
        let watchId;
        let settled = false;
        const started = Date.now();
        const finish = error => {
            if (settled) return;
            settled = true;
            clearTimeout(deadline);
            clearTimeout(quickFix);
            if (watchId !== undefined) navigator.geolocation.clearWatch(watchId);
            if (best) resolve(best);
            else reject(error || new Error('Your device could not find a fresh location. Enable precise location and try again.'));
        };
        const deadline = setTimeout(() => finish(), 10000);
        const quickFix = setTimeout(() => {
            if (best && best.coords.accuracy <= 50) finish();
        }, 5000);
        // Keep listening while the device refines its initial network-based fix.
        watchId = navigator.geolocation.watchPosition(position => {
            const { latitude, longitude, accuracy } = position.coords;
            if (![latitude, longitude, accuracy].every(Number.isFinite) || accuracy < 0
                || position.timestamp < started - 5000) return;
            if (loadingStage === 0) advanceLoading(1);
            if (!best || accuracy <= best.coords.accuracy) best = position;
            if (Date.now() - started >= 5000 && best.coords.accuracy <= 50) finish();
        }, error => {
            // A timeout may be followed by a better fix; permission denial cannot.
            if (error.code !== 1) return;
            finish(new Error({
                1: 'Location permission was denied. Enable location access for this site and try again.',
                2: 'Your device could not find its location. Turn on location services and try again.',
                3: 'Location scan timed out. Move to an open area and try again.',
            }[error.code] || 'Unable to read your location. Please try again.'));
        }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 });
    });
    scanner?.addEventListener('click', async () => {
        if (scanner.disabled) return;
        const action = nextScanAction;
        nextScanAction = 'check_in';
        if (dialog && !dialog.open) dialog.showModal();
        const version = targetVersion;
        const target = scanner.dataset.checkTarget === 'true' ? mapInput.value.trim() : undefined;
        scanner.disabled = true;
        const minimumLoading = new Promise(resolve => setTimeout(resolve, 3000));
        showLoading();
        headerScanner?.setAttribute('aria-disabled', 'true');
        headerScanner?.setAttribute('aria-busy', 'true');
        if (!dialog) show(result, 'Finding your current location…');
        try {
            const position = await locate();
            if (!showLoading(true)) show(result, 'Checking your distance from the school…');
            const response = await fetch(scanner.dataset.endpoint, {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                body: JSON.stringify({ action, expected_location_id: scanner.dataset.activeId ? Number(scanner.dataset.activeId) : undefined, target_location: target, latitude: position.coords.latitude, longitude: position.coords.longitude, accuracy: position.coords.accuracy, sampled_at: new Date(position.timestamp).toISOString() }),
            });
            if (response.status === 419 || response.status === 401) throw new Error('Your session expired. Refresh the page and sign in again.');
            if (response.status === 429) throw new Error('Too many scans. Wait one minute and try again.');
            if (response.status === 403) throw new Error('Your account is not allowed to check in. Contact an administrator.');
            if (response.status === 404) throw new Error('No active attendance location is available. Ask an administrator to save or activate one.');
            const data = await response.json();
            await minimumLoading;
            if (version !== targetVersion) return;
            clearInterval(loadingTimer);
            loadingPercent = 100;
            updateLoading();
            await new Promise(resolve => setTimeout(resolve, 350));
            if (version !== targetVersion) return;
            if (response.ok && data.success && data.teacher_name && ['present', 'late'].includes(data.status)) {
                showReceipt(data);
                return;
            }
            const message = data.message || 'The location check could not be completed. Please scan again.';
            if (!data.success) {
                showFailure({ ...data, action, message, device_location: position.coords });
                return;
            }
            show(result, message + (data.location_name ? ` Area: ${data.location_name}.` : '') + (data.distance_meters !== undefined ? ` Estimated distance: ${data.distance_meters} m. Location accuracy: ±${data.accuracy_meters} m. Allowed radius: ${data.radius_meters} m.` : ''), response.ok && data.success ? 'success' : 'error');
        } catch (error) {
            await minimumLoading;
            if (version !== targetVersion) return;
            show(result, error instanceof TypeError ? 'Could not reach the server. Check your connection and scan again.' : error.message, 'error');
        } finally {
            clearInterval(loadingTimer);
            scanner.disabled = false;
            headerScanner?.removeAttribute('aria-disabled');
            headerScanner?.removeAttribute('aria-busy');
        }
    });
    const scanUrl = new URL(window.location.href);
    if (scanUrl.searchParams.get('scan') === '1') {
        // Consume once so refreshing or returning to this page does not trigger another scan.
        scanUrl.searchParams.delete('scan');
        window.history.replaceState(window.history.state, '', scanUrl);
        if (scanner && !scanner.disabled) scanner.click();
    }
})();
