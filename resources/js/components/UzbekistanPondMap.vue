<template>
  <div class="uzbekistan-pond-map uzbekistan-pond-map--live">
    <div class="uz-map-ambient" aria-hidden="true">
      <span class="uz-map-ambient-glow" />
      <span class="uz-map-ambient-rays" />
    </div>
    <div ref="mapEl" class="uzbekistan-map-canvas" :class="{ 'is-loading': mapLoading }" />
    <p v-if="mapLoading" class="uz-map-status">{{ $t('loading') }}</p>
    <p v-else-if="mapError" class="uz-map-status uz-map-status--error">{{ mapError }}</p>
    <div v-if="legend" class="uzbekistan-map-legend">
      <span class="uzbekistan-map-legend-item">
        <i class="uz-legend-dot uz-legend-dot--pond" /> {{ legendPonds }}
      </span>
      <template v-if="legendRegionItems.length">
        <span
          v-for="item in legendRegionItems"
          :key="item.name"
          class="uzbekistan-map-legend-item"
        >
          <i class="uz-legend-dot" :style="{ background: item.color, boxShadow: `0 0 8px ${item.color}88` }" />
          {{ item.name }}
        </span>
      </template>
    </div>
  </div>
</template>

<script>
import {
  onMounted, onBeforeUnmount, ref, watch, computed,
} from 'vue';
import L from 'leaflet';
import {
  createStatsMap,
  regionCountIcon,
  pondMarker,
  pondPopupHtml,
  updateRegionLayerStyles,
  updateRegionLabelStyles,
} from '../utils/leafletMap';
import { regionCoordinates, regionCenter } from '../utils/regionCoordinates';
import { regionColor, regionColors } from '../utils/regionColors';

export default {
  props: {
    byRegion: { type: Array, default: () => [] },
    ponds: { type: Array, default: () => [] },
    highlightRegion: { type: String, default: '' },
    legendRegions: { type: String, default: '' },
    legendPonds: { type: String, default: '' },
    legend: { type: Boolean, default: true },
    labels: {
      type: Object,
      default: () => ({}),
    },
  },
  setup(props) {
    const mapEl = ref(null);
    const mapLoading = ref(true);
    const mapError = ref('');

    const legendRegionItems = computed(() => {
      if (props.highlightRegion) {
        return [{ name: props.highlightRegion, color: regionColor(props.highlightRegion) }];
      }
      return Object.keys(regionColors)
        .map((name) => ({ name, color: regionColor(name) }))
        .sort((a, b) => a.name.localeCompare(b.name, 'uz'));
    });

    let map = null;
    let regionLayer = null;
    let labelLayer = null;
    let layerGroup = null;

    function regionStatsMap() {
      const mapData = {};
      (props.byRegion || []).forEach((row) => {
        mapData[row.region] = row;
      });
      return mapData;
    }

    function renderLayers() {
      if (!map) return;
      if (layerGroup) {
        layerGroup.clearLayers();
      } else {
        layerGroup = L.layerGroup().addTo(map);
      }

      const stats = regionStatsMap();
      const highlight = props.highlightRegion;

      Object.entries(regionCoordinates).forEach(([name, coords]) => {
        if (highlight && name !== highlight) return;

        const row = stats[name];
        const count = row?.ponds_count || 0;
        if (!highlight && count === 0) return;

        const color = regionColor(name);

        const marker = L.marker([coords.lat + 0.18, coords.lng], {
          icon: regionCountIcon(count, color),
          zIndexOffset: highlight ? 200 : 100,
        });

        const directors = row?.directors_count || 0;
        const fish = row?.fish_count || 0;
        const area = row?.area_ha || 0;
        const lbl = props.labels;

        marker.bindPopup(`
          <div class="pond-map-popup">
            <strong class="pond-map-popup-title">${name}</strong>
            <div>${lbl.ponds || 'Havzalar'}: <strong>${count}</strong></div>
            <div>${lbl.directors || 'Direktorlar'}: <strong>${directors}</strong></div>
            <div>${lbl.area || 'Maydon'}: <strong>${area}</strong> ${lbl.hectare || 'ga'}</div>
            <div>${lbl.stock || 'Baliq'}: <strong>${fish}</strong> ${lbl.pieces || 'dona'}</div>
          </div>
        `);

        layerGroup.addLayer(marker);
      });

      (props.ponds || []).forEach((pond) => {
        if (pond.latitude == null || pond.longitude == null) return;

        const m = pondMarker(pond.latitude, pond.longitude, pond.region);

        m.bindPopup(pondPopupHtml(pond, {
          director: props.labels.director || 'Direktor',
          region: props.labels.region || 'Viloyat',
          area: props.labels.area || 'Maydon',
          hectare: props.labels.hectare || 'ga',
          stock: props.labels.stock || 'Baliq',
          pieces: props.labels.pieces || 'dona',
          notAssigned: props.labels.notAssigned || '—',
        }));

        layerGroup.addLayer(m);
      });
    }

    async function initMap() {
      if (!mapEl.value || map) return;

      mapLoading.value = true;
      mapError.value = '';

      try {
        const result = await createStatsMap(mapEl.value, { highlightRegion: props.highlightRegion });
        map = result.map;
        regionLayer = result.regionLayer;
        labelLayer = result.labelLayer;
        renderLayers();
        setTimeout(() => map?.invalidateSize(), 250);
      } catch (e) {
        console.error('Map init failed', e);
        mapError.value = 'Xarita yuklanmadi';
      } finally {
        mapLoading.value = false;
      }
    }

    function focusRegion() {
      if (!map || !props.highlightRegion) return;
      const center = regionCenter(props.highlightRegion);
      if (!center) return;
      map.flyTo([center.lat, center.lng], Math.max(map.getZoom() + 1, 7), { duration: 0.7 });
    }

    watch(() => [props.byRegion, props.ponds, props.highlightRegion], () => {
      updateRegionLayerStyles(regionLayer, props.highlightRegion);
      updateRegionLabelStyles(labelLayer, props.highlightRegion);
      renderLayers();
      focusRegion();
    }, { deep: true });

    onMounted(initMap);
    onBeforeUnmount(() => {
      map?.remove();
      map = null;
    });

    return { mapEl, mapLoading, mapError, legendRegionItems, regionColor };
  },
};
</script>
