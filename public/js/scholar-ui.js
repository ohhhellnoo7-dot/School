(() => {
    // Keep navigation context while the document itself scrolls independently.
    const navigation = document.querySelector('.school-nav');
    if (navigation) {
        const key = 'scholar-navigation-scroll';
        try { navigation.scrollTop = Number(sessionStorage.getItem(key)) || 0; } catch (_) {}
        const active = navigation.querySelector('a.active');
        if (active) {
            const bounds = navigation.getBoundingClientRect();
            const item = active.getBoundingClientRect();
            if (item.top < bounds.top || item.bottom > bounds.bottom) {
                navigation.scrollTop += item.top - bounds.top - navigation.clientHeight / 2 + item.height / 2;
            }
            active.setAttribute('aria-current', 'page');
        }
        const save = () => { try { sessionStorage.setItem(key, String(navigation.scrollTop)); } catch (_) {} };
        navigation.addEventListener('scroll', save, {passive: true});
        window.addEventListener('pagehide', save);
    }
    // Missing photos retain a useful identity without a broken image icon.
    document.querySelectorAll('img.student-avatar[src], img.user-avatar[src]:not(#profile-preview)').forEach(photo => {
        const fallback = () => {
            const initials = document.createElement('span');
            initials.className = photo.className;
            initials.style.cssText = photo.style.cssText;
            const name = photo.classList.contains('user-avatar')
                ? document.querySelector('.user-info strong')?.textContent?.trim() || photo.alt
                : photo.alt;
            initials.textContent = Array.from(name || '?')[0].toUpperCase();
            initials.setAttribute('aria-label', name || 'Profile');
            photo.replaceWith(initials);
        };
        photo.addEventListener('error', fallback, {once: true});
        if (photo.complete && photo.naturalWidth === 0) fallback();
    });
    document.querySelectorAll('.table .btn[aria-label]').forEach(button => {
        if (!button.title) button.title = button.getAttribute('aria-label');
    });
})();
