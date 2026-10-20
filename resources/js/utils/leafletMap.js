import L from 'leaflet';
import { loadUzbekistanGeo, pointInUzbekistan } from './uzbekistanGeo';
import { loadUzbekistanRegionsGeo, regionNameFromGeoFeature } from './uzbekistanRegionsGeo';
import { regionColor } from './regionColors';
import { regionCoordinates } from './regionCoordinates';

let defaultIconFixed = false;

export function fixLeafletIcons() {
  if (defaultIconFixed) return;
  defaultIconFixed = true;

  // eslint-disable-next-line no-underscore-dangle
  delete L.Icon.Default.prototype._getIconUrl;
  L.Icon.Default.mergeOptions({
    iconRetinaUrl: require('leaflet/dist/images/marker-icon-2x.png'),
    iconUrl: require('leaflet/dist/images/marker-icon.png'),
    shadowUrl: require('leaflet/dist/images/marker-shadow.png'),
  });
}

export function getUzbekistanGeo() {
  return loadUzbekistanGeo();
}

function addCountryBorder(map, geoJson) {
  L.geoJSON(geoJson, {
    style: {
      fill: false,
      color: '#e3f2fd',
      weight: 2.5,
      opacity: 0.85,
      className: 'uz-map-border-outline',
    },
    interactive: false,
  }).addTo(map);
}

function regionLayerStyle(feature, highlightRegion = '') {
  const name = regionNameFromGeoFeature(feature);
  const color = regionColor(name);
  const isHighlighted = highlightRegion && name === highlightRegion;
  const isDimmed = highlightRegion && name !== highlightRegion;

  return {
    fillColor: color,
    fillOpacity: isDimmed ? 0.28 : (isHighlighted ? 0.88 : 0.72),
    color: 'rgba(255, 255, 255, 0.72)',
    weight: isHighlighted ? 2 : 1.1,
    className: 'uz-map-region-fill',
  };
}

export function addRegionLayers(map, regionsGeoJson, { highlightRegion = '' } = {}) {
  const layer = L.geoJSON(regionsGeoJson, {
    style: (feature) => regionLayerStyle(feature, highlightRegion),
    interactive: false,
  }).addTo(map);

  return layer;
}

export function updateRegionLayerStyles(layer, highlightRegion = '') {
  if (!layer) return;
  layer.eachLayer((featureLayer) => {
    const style = regionLayerStyle(featureLayer.feature, highlightRegion);
    featureLayer.setStyle(style);
  });
}

function escapeHtml(text) {
  return String(text)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

export function regionNameLabelIcon(name, { dimmed = false } = {}) {
  const dimClass = dimmed ? ' uz-map-region-label--dimmed' : '';

  return L.divIcon({
    className: 'uz-map-region-label-wrap',
    html: `<span class="uz-map-region-label${dimClass}">${escapeHtml(name)}</span>`,
    iconSize: [0, 0],
    iconAnchor: [0, 0],
  });
}

export function addRegionLabels(map, { highlightRegion = '' } = {}) {
  const layer = L.layerGroup();

  Object.entries(regionCoordinates).forEach(([name, coords]) => {
    const dimmed = highlightRegion && name !== highlightRegion;

    const marker = L.marker([coords.lat, coords.lng], {
      icon: regionNameLabelIcon(name, { dimmed }),
      interactive: false,
      zIndexOffset: 300,
      regionName: name,
    });

    layer.addLayer(marker);
  });

  layer.addTo(map);
  return layer;
}

export function updateRegionLabelStyles(layer, highlightRegion = '') {
  if (!layer) return;

  layer.eachLayer((marker) => {
    const name = marker.options.regionName;
    if (!name) return;
    const dimmed = highlightRegion && name !== highlightRegion;
    marker.setIcon(regionNameLabelIcon(name, { dimmed }));
  });
}

function addCountryLayer(map, geoJson) {
  L.geoJSON(geoJson, {
    style: {
      fillColor: '#1565c0',
      fillOpacity: 0.88,
      color: '#90caf9',
      weight: 2.5,
      className: 'uz-map-country-fill',
    },
    interactive: false,
  }).addTo(map);

  L.geoJSON(geoJson, {
    style: {
      fill: false,
      color: '#bbdefb',
      weight: 1.5,
      opacity: 0.55,
      dashArray: '5 7',
      className: 'uz-map-border-dash',
    },
    interactive: false,
  }).addTo(map);
}

export function fitMapToUzbekistan(map, geoJson, { padding = [14, 14], lock = 'strict' } = {}) {
  const bounds = L.geoJSON(geoJson).getBounds();
  map.fitBounds(bounds, { padding, animate: false });

  if (lock === 'strict') {
    map.setMaxBounds(bounds.pad(0.03));
    map.options.maxBoundsViscosity = 1.0;
    map.setMinZoom(map.getZoom());
  } else if (lock === 'soft') {
    map.setMaxBounds(bounds.pad(0.12));
    map.options.maxBoundsViscosity = 0.85;
    map.setMinZoom(5);
    map.setMaxZoom(10);
  }

  return bounds;
}

function createUzbekistanMap(container, { mode = 'stats', highlightRegion = '' } = {}) {
  fixLeafletIcons();

  const isPicker = mode === 'picker';

  const loaders = isPicker
    ? [loadUzbekistanGeo()]
    : [loadUzbekistanGeo(), loadUzbekistanRegionsGeo()];

  return Promise.all(loaders).then((results) => {
    const geoJson = results[0];
    const regionsGeoJson = results[1] || null;

    const map = L.map(container, {
      zoomControl: true,
      scrollWheelZoom: true,
      dragging: true,
      doubleClickZoom: true,
      touchZoom: true,
      boxZoom: false,
      keyboard: false,
      attributionControl: false,
    });

    let regionLayer = null;
    let labelLayer = null;

    if (regionsGeoJson) {
      regionLayer = addRegionLayers(map, regionsGeoJson, { highlightRegion });
      labelLayer = addRegionLabels(map, { highlightRegion });
      addCountryBorder(map, geoJson);
    } else {
      addCountryLayer(map, geoJson);
    }

    fitMapToUzbekistan(map, geoJson, { lock: isPicker ? 'strict' : 'soft' });

    container.classList.add(isPicker ? 'uz-map-picker-mode' : 'uz-map-stats-mode');

    setTimeout(() => map.invalidateSize(), 50);

    return { map, geoJson, regionLayer, labelLayer };
  });
}

export function createStatsMap(container, options = {}) {
  return createUzbekistanMap(container, { mode: 'stats', ...options });
}

export function createPickerMap(container) {
  return createUzbekistanMap(container, { mode: 'picker' });
}

/** Telegram uslubidagi joylashuv tanlash: real OSM xarita */
export function createStreetPickerMap(container, { center = [41.3111, 69.2797], zoom = 13 } = {}) {
  fixLeafletIcons();

  const map = L.map(container, {
    zoomControl: true,
    scrollWheelZoom: true,
    dragging: true,
    doubleClickZoom: true,
    touchZoom: true,
    boxZoom: false,
    keyboard: true,
    attributionControl: true,
  });

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; OpenStreetMap',
  }).addTo(map);

  map.setView(center, zoom);
  container.classList.add('uz-map-street-picker');

  setTimeout(() => map.invalidateSize(), 50);
  setTimeout(() => map.invalidateSize(), 300);

  return loadUzbekistanGeo().then((geoJson) => ({ map, geoJson }));
}

export function regionCountIcon(count, color = '#1565c0') {
  const size = Math.min(56, 30 + count * 4);
  const pulse = count > 0 ? ' uz-map-region-badge--pulse' : '';

  return L.divIcon({
    className: 'uz-map-region-icon',
    html: `<div class="uz-map-region-badge${pulse}" style="width:${size}px;height:${size}px;border-color:${color};background:linear-gradient(145deg,rgba(8,22,48,.95) 0%,${color} 100%)"><strong>${count}</strong><span class="uz-map-region-ring" style="border-color:${color}" aria-hidden="true"></span></div>`,
    iconSize: [size, size],
    iconAnchor: [size / 2, size / 2],
  });
}

export function regionHeatCircle(lat, lng, count, maxCount, color = '#42a5f5') {
  const intensity = Math.max(0.14, (count / Math.max(maxCount, 1)) * 0.52);
  const radius = 16000 + (count / Math.max(maxCount, 1)) * 38000;

  return L.circle([lat, lng], {
    radius,
    color: 'transparent',
    fillColor: color,
    fillOpacity: intensity,
    interactive: false,
  });
}

export function userLocationMarker(lat, lng, accuracy = 40) {
  const group = L.layerGroup();

  if (accuracy > 0) {
    group.addLayer(L.circle([lat, lng], {
      radius: Math.min(Math.max(accuracy, 25), 120),
      color: '#2196f3',
      weight: 1,
      fillColor: '#2196f3',
      fillOpacity: 0.15,
      interactive: false,
    }));
  }

  const icon = L.divIcon({
    className: 'uz-user-location-wrap',
    html: '<span class="uz-user-location"><span class="uz-user-location-core"></span><span class="uz-user-location-pulse"></span></span>',
    iconSize: [22, 22],
    iconAnchor: [11, 11],
  });

  group.addLayer(L.marker([lat, lng], { icon, interactive: false, zIndexOffset: 800 }));

  return group;
}

export function pondMarker(lat, lng, region = '') {
  const color = region ? regionColor(region) : '#66bb6a';
  const icon = L.divIcon({
    className: 'uz-pond-marker-wrap',
    html: `<span class="uz-pond-marker"><span class="uz-pond-marker-core" style="background:${color};box-shadow:0 0 12px ${color}99"></span><span class="uz-pond-marker-pulse" style="border-color:${color}aa"></span></span>`,
    iconSize: [20, 20],
    iconAnchor: [10, 10],
  });

  return L.marker([lat, lng], { icon, zIndexOffset: 500 });
}

export function pondPopupHtml(pond, labels) {
  const director = pond.director
    ? `<div><strong>${labels.director}:</strong> ${pond.director.name}${pond.director.phone ? ` · ${pond.director.phone}` : ''}</div>`
    : `<div><strong>${labels.director}:</strong> ${labels.notAssigned}</div>`;

  return `
    <div class="pond-map-popup">
      <strong class="pond-map-popup-title">${pond.name}</strong>
      <div>${labels.region}: ${pond.region}</div>
      <div>${labels.area}: ${pond.area_m2} ${labels.hectare}</div>
      <div>${labels.stock}: ${pond.current_stock || 0} ${labels.pieces}</div>
      ${director}
    </div>
  `;
}

export { pointInUzbekistan };
