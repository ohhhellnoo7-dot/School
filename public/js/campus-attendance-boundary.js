(() => {
    const container = document.querySelector('[data-campus-boundary]');
    const leaflet = window.L;
    if (!container || !leaflet) return;

    const center = [Number(container.dataset.latitude), Number(container.dataset.longitude)];
    const radius = Number(container.dataset.radius);
    const map = leaflet.map(container, { scrollWheelZoom: false });
    const boundary = leaflet.circle(center, {
        radius,
        color: '#166be6',
        weight: 2,
        dashArray: '7 5',
        fillOpacity: 0.19,
        interactive: false,
    }).addTo(map);
    leaflet.circleMarker(center, {
        radius: 7,
        color: '#fff',
        weight: 2,
        fillColor: '#e12939',
        fillOpacity: 1,
        interactive: false,
    }).addTo(map);
    map.fitBounds(boundary.getBounds(), { paddingTopLeft: [24, 78], paddingBottomRight: [24, 20], maxZoom: 18 });

    const tiles = leaflet.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    });
    tiles.once('tileload', () => {
        container.classList.add('campus-boundary-map-ready');
        container.inert = false;
        container.removeAttribute('aria-hidden');
        const fallback = container.parentElement.querySelector('iframe');
        fallback.inert = true;
        fallback.setAttribute('aria-hidden', 'true');
    });
    tiles.addTo(map);

    document.addEventListener('attendance-boundary-removed', () => {
        map.remove();
        container.remove();
        const fallback = document.querySelector('.campus-map-frame iframe');
        fallback.inert = false;
        fallback.removeAttribute('aria-hidden');
    }, { once: true });
})();
