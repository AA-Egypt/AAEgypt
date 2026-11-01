import L from 'leaflet';
import iconUrl from 'leaflet/dist/images/marker-icon.png';
import iconRetinaUrl from 'leaflet/dist/images/marker-icon-2x.png';
import shadowUrl from 'leaflet/dist/images/marker-shadow.png';

// Configure default marker icons locally without external CDN requests
const DefaultIcon = L.icon({
    iconUrl,
    iconRetinaUrl,
    shadowUrl,
    iconSize: [25, 41],
    iconAnchor: [12, 41],
    popupAnchor: [1, -34],
    tooltipAnchor: [16, -28],
    shadowSize: [41, 41],
});
L.Marker.prototype.options.icon = DefaultIcon;

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

/**
 * Render a bilingual popup card with meeting/group name, address, time, and directions.
 */
export function createPopupContent(marker, locale = 'ar') {
    const isAr = locale === 'ar';
    const groupNameAr = marker.group_name_ar || '';
    const groupNameEn = marker.group_name_en || '';
    const name = marker.name || (isAr ? (groupNameAr || groupNameEn) : (groupNameEn || groupNameAr)) || 'Meeting';
    const address = marker.address || '';
    const city = marker.city || '';
    const fullAddress = [address, city].filter(Boolean).join(', ');
    const time = marker.time || '';
    const day = marker.day != null ? String(marker.day) : '';
    const timeText = [day, time].filter(Boolean).join(' - ');
    const directionUrl = marker.direction_url || `https://www.google.com/maps/dir/?api=1&destination=${marker.lat},${marker.lng}`;

    const directionsLabel = isAr ? 'الاتجاهات' : 'Get Directions';
    const groupLabel = isAr ? 'المجموعة:' : 'Group:';
    const addressLabel = isAr ? 'العنوان:' : 'Address:';
    const timeLabel = isAr ? 'الموعد:' : 'Time:';

    return `
        <div class="meeting-popup text-slate-800 leading-snug" dir="${isAr ? 'rtl' : 'ltr'}" style="min-width: 200px; max-width: 280px; font-family: inherit;">
            <div style="font-size: 15px; font-weight: bold; margin-bottom: 6px; color: #0f172a;">
                ${escapeHtml(name)}
            </div>
            ${(groupNameAr || groupNameEn) ? `
                <div style="font-size: 12px; margin-bottom: 6px; color: #475569;">
                    <span style="font-weight: 600;">${groupLabel}</span>
                    ${groupNameAr ? `<div style="color: #1e293b;">${escapeHtml(groupNameAr)}</div>` : ''}
                    ${groupNameEn ? `<div style="color: #64748b; font-family: sans-serif;">${escapeHtml(groupNameEn)}</div>` : ''}
                </div>
            ` : ''}
            ${fullAddress ? `
                <div style="font-size: 12px; margin-bottom: 6px; color: #475569;">
                    <span style="font-weight: 600;">${addressLabel}</span>
                    <div style="color: #334155;">${escapeHtml(fullAddress)}</div>
                </div>
            ` : ''}
            ${timeText ? `
                <div style="font-size: 12px; margin-bottom: 8px; color: #475569;">
                    <span style="font-weight: 600;">${timeLabel}</span>
                    <div style="color: #334155;">${escapeHtml(timeText)}</div>
                </div>
            ` : ''}
            <div style="margin-top: 8px; padding-top: 6px; border-top: 1px solid #e2e8f0;">
                <a href="${escapeHtml(directionUrl)}" target="_blank" rel="noopener noreferrer"
                   style="display: inline-flex; align-items: center; gap: 4px; font-size: 12px; font-weight: 600; color: #2563eb; text-decoration: none;">
                    <span>${directionsLabel}</span>
                    <span aria-hidden="true">&rarr;</span>
                </a>
            </div>
        </div>
    `;
}

/**
 * Initialize a single meeting map on a container element.
 */
export function initMeetingMap(element) {
    if (!element || element._leaflet_map) {
        return element?._leaflet_map || null;
    }

    let center = [30.0444, 31.2357]; // Centered on Cairo
    let zoom = 11;
    let markers = [];
    const locale = element.dataset.locale || document.documentElement.lang || 'ar';

    if (element.dataset.center) {
        try {
            center = JSON.parse(element.dataset.center);
        } catch (e) {
            console.error('Failed to parse data-center:', e);
        }
    }

    if (element.dataset.zoom) {
        const parsedZoom = parseInt(element.dataset.zoom, 10);
        if (!isNaN(parsedZoom)) {
            zoom = parsedZoom;
        }
    }

    if (element.dataset.markers) {
        try {
            markers = JSON.parse(element.dataset.markers);
        } catch (e) {
            console.error('Failed to parse data-markers:', e);
        }
    }

    const map = L.map(element, {
        minZoom: 5,
        maxZoom: 18,
    }).setView(center, zoom);

    element._leaflet_map = map;

    // Use local proxy tile endpoint - guaranteed uptime, zero tracking, rate-limit immune
    L.tileLayer('/tiles/{z}/{x}/{y}.png', {
        minZoom: 5,
        maxZoom: 18,
        attribution: '&copy; OpenStreetMap contributors',
    }).addTo(map);

    const markerGroup = L.featureGroup();

    if (Array.isArray(markers)) {
        markers.forEach(marker => {
            const lat = parseFloat(marker.lat);
            const lng = parseFloat(marker.lng);

            if (!isNaN(lat) && !isNaN(lng)) {
                const leafletMarker = L.marker([lat, lng]);
                leafletMarker.bindPopup(createPopupContent(marker, locale));
                markerGroup.addLayer(leafletMarker);
            }
        });
    }

    markerGroup.addTo(map);

    return map;
}

/**
 * Initialize all meeting map containers on the page.
 */
export function initAllMeetingMaps() {
    const mapElements = document.querySelectorAll('[data-meeting-map]');
    mapElements.forEach(el => initMeetingMap(el));
}

// Automatically initialize maps when DOM is ready
if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAllMeetingMaps);
    } else {
        initAllMeetingMaps();
    }
}

