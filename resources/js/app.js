import L from 'leaflet';
import { initMeetingMap, initAllMeetingMaps, createPopupContent } from './components/meeting-map.js';
import {
    haversineDistance,
    sortMeetingsByDistance,
    formatDistanceBadge,
    filterMeetingsByCity,
    updateAndReorderCards,
    initNearbyLocator,
} from './components/nearby-locator.js';

window.L = L;
window.initMeetingMap = initMeetingMap;
window.initAllMeetingMaps = initAllMeetingMaps;
window.createPopupContent = createPopupContent;

window.haversineDistance = haversineDistance;
window.sortMeetingsByDistance = sortMeetingsByDistance;
window.formatDistanceBadge = formatDistanceBadge;
window.filterMeetingsByCity = filterMeetingsByCity;
window.updateAndReorderCards = updateAndReorderCards;
window.initNearbyLocator = initNearbyLocator;
