<template>
  <div class="wq-panel">
    <div class="wq-panel__head">
      <strong>{{ $t('waterQualityKitTitle') }}</strong>
      <p>{{ $t('waterQualityKitHint') }}</p>
    </div>

    <div class="wq-legend" aria-hidden="true">
      <span class="wq-legend__item wq-legend__item--ok">{{ $t('waterLevelOk') }}</span>
      <span class="wq-legend__item wq-legend__item--caution">{{ $t('waterLevelCaution') }}</span>
      <span class="wq-legend__item wq-legend__item--danger">{{ $t('waterLevelDanger') }}</span>
    </div>

    <p v-if="bandLabel" class="wq-band">
      {{ $t('waterTimeBandLabel', { band: bandLabel }) }}
    </p>

    <div
      v-for="row in rows"
      :key="row.key"
      class="wq-metric"
      :class="row.level ? `wq-metric--${row.level}` : ''"
    >
      <div class="wq-metric__top">
        <span>{{ row.label }}</span>
        <em v-if="row.valueText">{{ row.valueText }}</em>
      </div>
      <div class="wq-scale" :style="{ '--wq-marker': row.marker != null ? `${row.marker}%` : null }">
        <div class="wq-scale__track" :class="`wq-scale__track--${row.key}`" />
        <span v-if="row.marker != null" class="wq-scale__marker" />
      </div>
      <small>{{ row.norm }}</small>
    </div>

    <div v-if="assessment.level !== 'ok' && assessment.issues.length" class="wq-alert" :class="`wq-alert--${assessment.level}`">
      <strong>{{ assessment.level === 'danger' ? $t('waterAlertTitleDanger') : $t('waterAlertTitleCaution') }}</strong>
      <ul>
        <li v-for="(issue, idx) in assessment.issues" :key="`${issue.metric}-${idx}`">
          {{ formatIssue(issue) }}
        </li>
      </ul>
      <p>{{ $t('waterAlertCallSpecialist', { phone: $t('homeContactPhone') }) }}</p>
    </div>
  </div>
</template>

<script>
import { computed } from 'vue';
import { t } from '../i18n';
import {
  DEFAULT_WATER_STANDARDS,
  evaluateWaterQuality,
  formatWaterIssue,
  metricMarkerPercent,
  timeBandKey,
} from '../utils/waterQuality';

const METRIC_KEYS = [
  'do_mg_l',
  'ph_level',
  'ammonia_mg_l',
  'nitrite_mg_l',
  'water_transparency_cm',
];

export default {
  name: 'WaterQualityPanel',
  props: {
    readings: { type: Object, required: true },
    standards: { type: Object, default: null },
  },
  setup(props) {
    const standards = computed(() => props.standards || DEFAULT_WATER_STANDARDS);
    const assessment = computed(() => evaluateWaterQuality(props.readings, standards.value));

    const bandLabel = computed(() => {
      const key = assessment.value.band;
      if (!key || key === 'unknown') return '';
      return t(`waterBand_${key}`);
    });

    const rows = computed(() => {
      const metrics = standards.value.metrics || {};
      const band = timeBandKey(props.readings?.test_time);
      return METRIC_KEYS.map((key) => {
        const cfg = metrics[key] || {};
        const value = props.readings?.[key];
        const level = assessment.value.levels?.[key] || null;
        const marker = metricMarkerPercent(value, cfg.scale_min, cfg.scale_max);
        return {
          key,
          label: t(`waterMetric_${key}`),
          valueText: value !== '' && value != null ? `${value}${cfg.unit ? ` ${cfg.unit}` : ''}` : '',
          marker,
          level,
          norm: normText(key, cfg, band),
        };
      });
    });

    function normText(key, cfg, band) {
      const b = cfg.bands?.[band] || cfg.bands?.unknown;
      if (!b) return '';
      if (key === 'do_mg_l') return t('waterNormDo', { min: b.ok_min });
      if (key === 'ph_level') return t('waterNormPh', { from: b.ok[0], to: b.ok[1] });
      if (key === 'ammonia_mg_l') return t('waterNormAmmonia', { max: b.ok_max });
      if (key === 'nitrite_mg_l') return t('waterNormNitrite', { max: b.ok_max });
      if (key === 'water_transparency_cm') return t('waterNormTransparency', { from: b.ok[0], to: b.ok[1] });
      return '';
    }

    function formatIssue(issue) {
      return formatWaterIssue(issue, t);
    }

    return { assessment, bandLabel, rows, formatIssue };
  },
};
</script>
