/**
 * Nearby and Nearest Meeting Locator (Client-side, Privacy by Design)
 *
 * Visitor GPS coordinates are NEVER sent to the server and NEVER logged in web access logs.
 * All distance calculations happen 100% client-side in browser JavaScript using the Haversine formula.
 */

/**
 * Haversine formula to compute distance between two GPS coordinates in kilometers.
 *
 * @param {number} lat1 Latitude of point 1 in degrees
 * @param {number} lon1 Longitude of point 1 in degrees
 * @param {number} lat2 Latitude of point 2 in degrees
 * @param {number} lon2 Longitude of point 2 in degrees
 * @returns {number} Distance in kilometers
 */
export function haversineDistance(lat1, lon1, lat2, lon2) {
    if (lat1 == null || lon1 == null || lat2 == null || lon2 == null) {
        return Infinity;
    }

    const nLat1 = Number(lat1);
    const nLon1 = Number(lon1);
    const nLat2 = Number(lat2);
    const nLon2 = Number(lon2);

    if (isNaN(nLat1) || isNaN(nLon1) || isNaN(nLat2) || isNaN(nLon2)) {
        return Infinity;
    }

    const R = 6371; // Earth's mean radius in kilometers
    const toRad = (deg) => (deg * Math.PI) / 180;

    const dLat = toRad(nLat2 - nLat1);
    const dLon = toRad(nLon2 - nLon1);

    const a =
        Math.sin(dLat / 2) * Math.sin(dLat / 2) +
        Math.cos(toRad(nLat1)) * Math.cos(toRad(nLat2)) *
        Math.sin(dLon / 2) * Math.sin(dLon / 2);

    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));

    return R * c;
}

/**
 * Sort an array of meeting objects by nearest distance, prioritizing meetings running today.
 *
 * @param {Array} meetings Array of meeting objects
 * @param {number} userLat User's latitude
 * @param {number} userLon User's longitude
 * @returns {Array} Sorted meetings array with computed distance property
 */
export function sortMeetingsByDistance(meetings, userLat, userLon) {
    return meetings
        .map((m) => {
            const lat = m.latitude ?? m.lat;
            const lng = m.longitude ?? m.lng;
            const distance = (lat != null && lng != null)
                ? haversineDistance(userLat, userLon, lat, lng)
                : Infinity;

            return {
                ...m,
                distance,
            };
        })
        .sort((a, b) => {
            const isTodayA = Boolean(a.is_today);
            const isTodayB = Boolean(b.is_today);

            // Prioritize meetings running today
            if (isTodayA && !isTodayB) return -1;
            if (!isTodayA && isTodayB) return 1;

            // Secondary: distance ascending
            if (a.distance !== b.distance) {
                return a.distance - b.distance;
            }

            return 0;
        });
}

/**
 * Format distance in kilometers for display badge.
 *
 * @param {number} distanceInKm Distance in kilometers
 * @returns {string} Formatted badge text e.g. "[2.4 km away]"
 */
export function formatDistanceBadge(distanceInKm) {
    if (distanceInKm == null || !isFinite(distanceInKm)) {
        return '';
    }
    return `[${distanceInKm.toFixed(1)} km away]`;
}

/**
 * Filter meeting cards DOM elements by city.
 *
 * @param {string} city City name to filter by (or empty/all for all cities)
 * @param {HTMLElement} [container] Container element holding the meeting cards
 */
export function filterMeetingsByCity(city, container = null) {
    const root = container || (typeof document !== 'undefined'
        ? (document.querySelector('[data-meetings-container]') || document.getElementById('meeting-cards-container'))
        : null);

    if (!root) return;

    const cards = root.querySelectorAll('[data-meeting-card]');
    const normalizedCity = (city || '').trim().toLowerCase();

    cards.forEach((card) => {
        const cardCity = (card.dataset.city || '').trim().toLowerCase();
        if (!normalizedCity || normalizedCity === 'all' || normalizedCity === cardCity) {
            card.style.display = '';
        } else {
            card.style.display = 'none';
        }
    });
}

/**
 * Update DOM cards with computed distance, update badges, and reorder them by distance.
 * Meetings running today are prioritized, followed by nearest distance.
 *
 * @param {number} userLat User's latitude
 * @param {number} userLon User's longitude
 * @param {HTMLElement} [container] Container element holding the meeting cards
 */
export function updateAndReorderCards(userLat, userLon, container = null) {
    const root = container || (typeof document !== 'undefined'
        ? (document.querySelector('[data-meetings-container]') || document.getElementById('meeting-cards-container'))
        : null);

    if (!root) return;

    const cards = Array.from(root.querySelectorAll('[data-meeting-card]'));
    if (!cards.length) return;

    // 1. Calculate distance for each card and update distance badge
    cards.forEach((card) => {
        const lat = parseFloat(card.dataset.lat);
        const lng = parseFloat(card.dataset.lng);

        if (!isNaN(lat) && !isNaN(lng)) {
            const distance = haversineDistance(userLat, userLon, lat, lng);
            card.dataset.distance = distance;

            const badge = card.querySelector('[data-distance-badge]');
            if (badge) {
                badge.textContent = formatDistanceBadge(distance);
                badge.classList.remove('hidden');
            }
        } else {
            card.dataset.distance = Infinity;
        }
    });

    // 2. Sort cards: today's meetings first, then sorted by distance ascending
    cards.sort((a, b) => {
        const isTodayA = a.dataset.isToday === 'true';
        const isTodayB = b.dataset.isToday === 'true';

        if (isTodayA && !isTodayB) return -1;
        if (!isTodayA && isTodayB) return 1;

        const distA = parseFloat(a.dataset.distance) || Infinity;
        const distB = parseFloat(b.dataset.distance) || Infinity;

        return distA - distB;
    });

    // 3. Append cards back in sorted order
    cards.forEach((card) => {
        root.appendChild(card);
    });
}

/**
 * Initialize the Nearby Locator component.
 */
export function initNearbyLocator() {
    if (typeof document === 'undefined') return;

    const locateBtn = document.getElementById('find-near-me-btn') || document.querySelector('[data-find-near-me]');
    const citySelect = document.getElementById('city-filter') || document.querySelector('[data-city-filter]');
    const statusNotice = document.getElementById('locator-status-notice');
    const container = document.querySelector('[data-meetings-container]') || document.getElementById('meeting-cards-container');

    // Handle manual city dropdown change (instant client-side filtering)
    if (citySelect) {
        citySelect.addEventListener('change', (e) => {
            filterMeetingsByCity(e.target.value, container);
        });
    }

    // Handle "Find Meetings Near Me" click
    if (locateBtn) {
        locateBtn.addEventListener('click', () => {
            if (!('geolocation' in navigator)) {
                showFallback(
                    document.documentElement.lang === 'ar'
                        ? 'خاصية تحديد الموقع غير مدعومة في متصفحك. يرجى اختيار منطقتك أو مدينتك يدوياً:'
                        : 'Geolocation is not supported by your browser. Please select your area/city manually:'
                );
                return;
            }

            // Visual feedback
            const originalContent = locateBtn.innerHTML;
            locateBtn.disabled = true;
            locateBtn.setAttribute('aria-busy', 'true');

            if (statusNotice) {
                statusNotice.classList.remove('hidden', 'bg-red-50', 'text-red-700', 'bg-emerald-50', 'text-emerald-700', 'bg-amber-50', 'text-amber-800');
                statusNotice.classList.add('bg-blue-50', 'text-blue-700');
                statusNotice.textContent = document.documentElement.lang === 'ar'
                    ? 'جاري تحديد موقعك...'
                    : 'Locating your position...';
            }

            // Explicit geolocation prompt triggered ONLY upon button click
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    locateBtn.disabled = false;
                    locateBtn.removeAttribute('aria-busy');
                    locateBtn.innerHTML = originalContent;

                    const { latitude, longitude } = position.coords;

                    // PRIVACY BY DESIGN: Coordinates are strictly kept client-side and NEVER dispatched to server
                    updateAndReorderCards(latitude, longitude, container);

                    if (statusNotice) {
                        statusNotice.classList.remove('hidden', 'bg-blue-50', 'text-blue-700', 'bg-red-50', 'text-red-700', 'bg-amber-50', 'text-amber-800');
                        statusNotice.classList.add('bg-emerald-50', 'text-emerald-700');
                        statusNotice.textContent = document.documentElement.lang === 'ar'
                            ? 'تم ترتيب الاجتماعات حسب الأقرب لموقعك اليوم.'
                            : 'Sorted by nearest meetings running today.';
                    }
                },
                (error) => {
                    locateBtn.disabled = false;
                    locateBtn.removeAttribute('aria-busy');
                    locateBtn.innerHTML = originalContent;

                    showFallback(
                        document.documentElement.lang === 'ar'
                            ? 'تم رفض إذن تحديد الموقع أو تعذر العثور عليه. يرجى اختيار منطقتك أو مدينتك يدوياً من القائمة:'
                            : 'Location permission was denied or unavailable. Please select your area or city manually from the dropdown:'
                    );
                },
                {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 60000,
                }
            );
        });
    }

    function showFallback(message) {
        if (statusNotice) {
            statusNotice.classList.remove('hidden', 'bg-blue-50', 'text-blue-700', 'bg-emerald-50', 'text-emerald-700');
            statusNotice.classList.add('bg-amber-50', 'text-amber-800', 'border', 'border-amber-200');
            statusNotice.textContent = message;
        }

        if (citySelect) {
            citySelect.focus();
            if (typeof citySelect.scrollIntoView === 'function') {
                citySelect.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        }
    }
}

// Auto-initialize when in browser environment
if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => initNearbyLocator());
    } else {
        initNearbyLocator();
    }
}

