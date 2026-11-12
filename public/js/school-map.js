(() => {
    const scanner = document.querySelector('[data-scan-location]') || document.querySelector('[data-global-scan]');
    const dialog = document.querySelector('[data-scan-dialog]');
    const result = dialog?.querySelector('[data-scan-dialog-result]') || document.querySelector('[data-scan-result]');
    dialog?.querySelector('[data-close-scan]')?.addEventListener('click', () => dialog.close());
    const headerScanner = document.querySelector('.teacher-scan-shortcut');
    const showLoading = (checking = false) => {
        const template = document.getElementById('scan-loading-template');
        if (!dialog || !template) return false;
        dialog.classList.add('scan-dialog--loading');
        result.classList.remove('p-3', 'border', 'rounded-3', 'text-success', 'text-danger');
        result.replaceChildren(template.content.cloneNode(true));
        result.setAttribute('aria-busy', 'true');
        if (checking) {
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
        const late = data.status === 'late';
        const card = document.createElement('article');
        card.className = `checkin-receipt${late ? ' checkin-receipt--late' : ''}`;
        const add = (tag, className, text, parent = card) => {
            const node = document.createElement(tag);
            node.className = className;
            node.textContent = text;
            parent.append(node);
            return node;
        };
        const symbol = add('div', 'checkin-symbol', '');
        add('i', late ? 'bi bi-alarm' : 'bi bi-check-lg', '', symbol).setAttribute('aria-hidden', 'true');
        add('div', 'checkin-ribbon', late ? 'You are Late!' : 'Congratulations!');
        add('h2', 'checkin-title', late ? 'Check-in Recorded' : 'Check-in Successful');
        add('p', 'checkin-subtitle', late ? 'You checked in after the allowed time.' : 'You are Present today. 🎉');
        if (late) add('p', 'checkin-delay', Number.isInteger(data.late_minutes)
            ? `${data.late_minutes} ${data.late_minutes === 1 ? 'minute' : 'minutes'} late · from session start`
            : 'Late arrival · timing details unavailable');
        const details = add('div', 'checkin-details', '');
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
        row('person', data.teacher_name, data.teacher_number);
        row('calendar4', validStamp ? format({ day: '2-digit', month: 'short', year: 'numeric' }) : 'Date unavailable');
        row('clock', validStamp ? format({ hour: '2-digit', minute: '2-digit', hour12: true }).toUpperCase() : 'Time unavailable', data.session ? `${data.session} session` : null);
        row('geo-alt-fill', data.location_name);
        add('div', 'checkin-status', late ? '◷  Late' : '✓  Present');
        add('div', 'checkin-landscape', 'អបអរសាទរ · SCHOOL ATTENDANCE').setAttribute('aria-hidden', 'true');
        result.classList.remove('p-3', 'border', 'rounded-3', 'text-success', 'text-danger');
        result.replaceChildren(card);
        if (!dialog) card.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth', block: 'nearest' });
    };
    const show = (target, message, state = '') => {
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
        navigator.geolocation.getCurrentPosition(resolve, error => {
            reject(new Error({
                1: 'Location permission was denied. Enable location access for this site and try again.',
                2: 'Your device could not find its location. Turn on location services and try again.',
                3: 'Location scan timed out. Move to an open area and try again.',
            }[error.code] || 'Unable to read your location. Please try again.'));
        }, { enableHighAccuracy: true, timeout: 20000, maximumAge: 0 });
    });
    scanner?.addEventListener('click', async () => {
        if (scanner.disabled) return;
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
                body: JSON.stringify({ expected_location_id: scanner.dataset.activeId ? Number(scanner.dataset.activeId) : undefined, target_location: target, latitude: position.coords.latitude, longitude: position.coords.longitude, accuracy: position.coords.accuracy, sampled_at: new Date(position.timestamp).toISOString() }),
            });
            if (response.status === 419 || response.status === 401) throw new Error('Your session expired. Refresh the page and sign in again.');
            if (response.status === 429) throw new Error('Too many scans. Wait one minute and try again.');
            if (response.status === 403) throw new Error('Your account is not allowed to check in. Contact an administrator.');
            if (response.status === 404) throw new Error('No active attendance location is available. Ask an administrator to save or activate one.');
            const data = await response.json();
            await minimumLoading;
            if (version !== targetVersion) return;
            if (response.ok && data.success && data.teacher_name && ['present', 'late'].includes(data.status)) {
                showReceipt(data);
                return;
            }
            const message = data.message || 'The location check could not be completed. Please scan again.';
            show(result, message + (data.location_name ? ` Area: ${data.location_name}.` : '') + (data.distance_meters !== undefined ? ` Estimated distance: ${data.distance_meters} m. Location accuracy: ±${data.accuracy_meters} m. Allowed radius: ${data.radius_meters} m.` : ''), response.ok && data.success ? 'success' : 'error');
        } catch (error) {
            await minimumLoading;
            if (version !== targetVersion) return;
            show(result, error instanceof TypeError ? 'Could not reach the server. Check your connection and scan again.' : error.message, 'error');
        } finally {
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
