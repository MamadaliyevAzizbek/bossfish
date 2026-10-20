<template>
  <div class="pond-location-picker">
    <label v-if="label" class="pond-map-label">{{ label }}</label>
    <p v-if="hint" class="pond-map-hint">{{ hint }}</p>

    <button
      class="btn btn-primary btn-block-mobile pond-location-share-btn"
      type="button"
      :disabled="locating"
      @click="locateMe(false)"
    >
      <span class="pond-location-share-icon" aria-hidden="true">📍</span>
      <span class="pond-location-share-text">
        <strong>{{ locating ? $t('locating') : $t('shareMyLocation') }}</strong>
        <small>{{ $t('shareMyLocationHint') }}</small>
      </span>
    </button>

    <div class="pond-location-map-wrap">
      <div ref="mapEl" class="pond-map-canvas pond-map-canvas--street" />
      <p v-if="!mapReady" class="pond-map-loading">{{ $t('loading') }}</p>
    </div>

    <p v-if="locationHint && !locationError" class="pond-map-hint pond-map-hint--status">{{ locationHint }}</p>
    <p v-if="locationError" class="pond-map-error">{{ locationError }}</p>
    <p v-else-if="hasPoint" class="pond-map-coords">
      {{ coordsText }}
      <span v-if="accuracyMeters != null"> · ±{{ accuracyMeters }} m</span>
      · {{ $t('dragToAdjust') }}
    </p>
    <p v-else class="pond-map-coords pond-map-coords--empty">{{ emptyHint || $t('pickLocationEmpty') }}</p>
  </div>
</template>

<script>
import {
  onMounted, onBeforeUnmount, ref, watch, computed,
} from 'vue';
import L from 'leaflet';
import { createStreetPickerMap, userLocationMarker } from '../utils/leafletMap';
import { regionCenter } from '../utils/regionCoordinates';
import { pointInUzbekistan } from '../utils/uzbekistanGeo';
import { getPrecisePosition } from '../utils/geolocation';
import { t } from '../i18n';

export default {
  props: {
    latitude: { type: [Number, String], default: null },
    longitude: { type: [Number, String], default: null },
    region: { type: String, default: '' },
    label: { type: String, default: '' },
    hint: { type: String, default: '' },
    emptyHint: { type: String, default: '' },
    autoLocate: { type: Boolean, default: true },
  },
  emits: ['update:latitude', 'update:longitude'],
  setup(props, { emit }) {
    const mapEl = ref(null);
    const mapReady = ref(false);
    const locating = ref(false);
    const locationError = ref('');
    const locationHint = ref('');
    const accuracyMeters = ref(null);
    let map = null;
    let geoJson = null;
    let marker = null;
    let userLayer = null;
    let autoLocated = false;

    const hasPoint = computed(() => (
      props.latitude != null
      && props.longitude != null
      && props.latitude !== ''
      && props.longitude !== ''
    ));

    const coordsText = computed(() => {
      if (!hasPoint.value) return '';
      return `${Number(props.latitude).toFixed(6)}, ${Number(props.longitude).toFixed(6)}`;
    });

    function emitCoords(lat, lng) {
      emit('update:latitude', Math.round(lat * 1e7) / 1e7);
      emit('update:longitude', Math.round(lng * 1e7) / 1e7);
    }

    function defaultCenter() {
      const center = regionCenter(props.region);
      if (center) return [center.lat, center.lng];
      return [41.3111, 69.2797];
    }

    function showUserLayer(lat, lng, accuracy) {
      if (!map) return;
      if (userLayer) map.removeLayer(userLayer);
      userLayer = userLocationMarker(lat, lng, accuracy).addTo(map);
    }

    function clearUserLayer() {
      if (userLayer && map) {
        map.removeLayer(userLayer);
        userLayer = null;
      }
    }

    function setMarker(lat, lng, { fly = true, zoom = 16 } = {}) {
      if (!map) return;

      if (marker) {
        marker.setLatLng([lat, lng]);
      } else {
        marker = L.marker([lat, lng], { draggable: true, zIndexOffset: 1000 }).addTo(map);
        marker.on('dragend', () => {
          const { lat: la, lng: ln } = marker.getLatLng();
          emitCoords(la, ln);
          showUserLayer(la, ln, 25);
          if (geoJson && !pointInUzbekistan(geoJson, la, ln)) {
            locationError.value = t('locationOutsideUz');
          } else {
            locationError.value = '';
            locationHint.value = t('locationDetected');
          }
        });
      }

      if (fly) {
        map.flyTo([lat, lng], Math.max(map.getZoom(), zoom), { duration: 0.7 });
      } else {
        map.setView([lat, lng], Math.max(map.getZoom(), zoom));
      }
    }

    function clearMarker() {
      if (marker && map) {
        map.removeLayer(marker);
        marker = null;
      }
    }

    async function locateMe(silent = false) {
      locating.value = true;
      locationError.value = '';
      accuracyMeters.value = null;
      if (silent) locationHint.value = t('locatingPrecise');

      try {
        const pos = await getPrecisePosition({
          targetAccuracy: 10,
          timeoutMs: 22000,
        });

        accuracyMeters.value = Math.round(pos.accuracy);
        showUserLayer(pos.lat, pos.lng, pos.accuracy);
        emitCoords(pos.lat, pos.lng);
        setMarker(pos.lat, pos.lng, { fly: true, zoom: pos.accuracy <= 15 ? 18 : 17 });

        if (geoJson && !pointInUzbekistan(geoJson, pos.lat, pos.lng)) {
          locationError.value = t('locationOutsideUz');
        } else if (pos.accuracy <= 10) {
          locationHint.value = t('locationPreciseOk', { m: Math.round(pos.accuracy) });
        } else {
          locationHint.value = t('locationApproximate', { m: Math.round(pos.accuracy) });
        }
      } catch (e) {
        if (e?.message === 'unsupported') {
          locationError.value = t('locationUnsupported');
        } else if (e?.code === 1) {
          locationError.value = t('locationDenied');
        } else {
          locationError.value = t('locationFailed');
        }

        if (!hasPoint.value && map) {
          const [lat, lng] = defaultCenter();
          map.setView([lat, lng], 11);
        }
      } finally {
        locating.value = false;
      }
    }

    async function initMap() {
      if (!mapEl.value || map) return;

      const [lat, lng] = hasPoint.value
        ? [Number(props.latitude), Number(props.longitude)]
        : defaultCenter();

      const result = await createStreetPickerMap(mapEl.value, {
        center: [lat, lng],
        zoom: hasPoint.value ? 16 : 11,
      });

      map = result.map;
      geoJson = result.geoJson;
      mapReady.value = true;

      map.on('click', (e) => {
        const { lat: clickLat, lng: clickLng } = e.latlng;
        locationError.value = '';
        emitCoords(clickLat, clickLng);
        setMarker(clickLat, clickLng, { fly: false, zoom: map.getZoom() });
        showUserLayer(clickLat, clickLng, 20);
        if (geoJson && !pointInUzbekistan(geoJson, clickLat, clickLng)) {
          locationError.value = t('locationOutsideUz');
        }
      });

      if (hasPoint.value) {
        setMarker(lat, lng, { fly: false, zoom: 16 });
        showUserLayer(lat, lng, 30);
      } else if (props.autoLocate && !autoLocated) {
        autoLocated = true;
        await locateMe(true);
      }

      [100, 350, 700, 1200].forEach((ms) => {
        setTimeout(() => map?.invalidateSize(), ms);
      });
    }

    watch(() => props.region, () => {
      if (!map || hasPoint.value || locating.value) return;
      const [lat, lng] = defaultCenter();
      map.setView([lat, lng], Math.max(map.getZoom(), 11));
    });

    watch(hasPoint, (val) => {
      if (!map) return;
      if (val) {
        setMarker(Number(props.latitude), Number(props.longitude), { fly: false });
      } else {
        clearMarker();
        clearUserLayer();
      }
    });

    onMounted(initMap);
    onBeforeUnmount(() => {
      map?.remove();
      map = null;
    });

    return {
      mapEl,
      mapReady,
      locating,
      locationError,
      locationHint,
      accuracyMeters,
      hasPoint,
      coordsText,
      locateMe,
    };
  },
};
</script>
