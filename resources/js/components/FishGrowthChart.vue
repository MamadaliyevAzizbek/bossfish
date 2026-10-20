<template>
  <div class="fish-growth-chart">
    <svg
      class="fish-growth-chart__svg"
      :viewBox="`0 0 ${width} ${height}`"
      preserveAspectRatio="none"
      role="img"
      :aria-label="ariaLabel"
    >
      <defs>
        <linearGradient :id="gradientId" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stop-color="rgba(36, 120, 86, 0.28)" />
          <stop offset="100%" stop-color="rgba(36, 120, 86, 0.02)" />
        </linearGradient>
      </defs>

      <line
        v-for="(tick, index) in gridTicks"
        :key="`grid-${index}`"
        :x1="padX"
        :y1="tick.y"
        :x2="width - padX"
        :y2="tick.y"
        class="fish-growth-chart__grid"
      />

      <path
        v-if="areaPath"
        :d="areaPath"
        :fill="`url(#${gradientId})`"
        class="fish-growth-chart__area"
      />

      <path
        v-if="linePath"
        :d="linePath"
        class="fish-growth-chart__line"
        fill="none"
      />

      <path
        v-if="pendingPath"
        :d="pendingPath"
        class="fish-growth-chart__line fish-growth-chart__line--pending"
        fill="none"
      />

      <circle
        v-for="(point, index) in plottedPoints"
        :key="`point-${index}`"
        :cx="point.x"
        :cy="point.y"
        :r="index === 0 || index === plottedPoints.length - 1 ? 5 : 3.5"
        class="fish-growth-chart__dot"
        :class="{
          'fish-growth-chart__dot--start': index === 0,
          'fish-growth-chart__dot--end': index === plottedPoints.length - 1 && hasWeighing,
        }"
      />

      <g
        v-for="(point, index) in datedPoints"
        :key="`label-${index}`"
        class="fish-growth-chart__point-label"
      >
        <text
          :x="point.labelX"
          :y="point.weightY"
          class="fish-growth-chart__weight"
          :text-anchor="point.textAnchor"
        >
          {{ formatGrams(point.weight_g) }} g
        </text>
        <text
          :x="point.labelX"
          :y="point.dateY"
          class="fish-growth-chart__date"
          :text-anchor="point.textAnchor"
        >
          {{ formatDotDate(point.date) }}
        </text>
      </g>
    </svg>

    <div class="fish-growth-chart__timeline">
      <div class="fish-growth-chart__marker fish-growth-chart__marker--start">
        <span class="fish-growth-chart__marker-label">{{ $t('initialWeight') }}</span>
        <strong>{{ formatGrams(initialWeightG) }} g</strong>
        <small v-if="initialDate">{{ formatShortDate(initialDate) }}</small>
      </div>

      <div class="fish-growth-chart__marker fish-growth-chart__marker--mid">
        <span class="fish-growth-chart__marker-label">{{ $t('growthDays') }}</span>
        <strong v-if="hasWeighing && growthDays > 0">{{ formatInt(growthDays) }} {{ $t('days') }}</strong>
        <strong v-else class="muted">—</strong>
        <small v-if="hasWeighing && gainPerFishG != null">+{{ formatGrams(gainPerFishG) }} g</small>
        <small v-else>{{ $t('fishGrowthAwaitWeigh') }}</small>
      </div>

      <div class="fish-growth-chart__marker fish-growth-chart__marker--end">
        <span class="fish-growth-chart__marker-label">{{ $t('currentWeight') }}</span>
        <strong v-if="hasWeighing">{{ formatGrams(currentWeightG) }} g</strong>
        <strong v-else class="muted">?</strong>
        <small v-if="hasWeighing && currentDate">{{ formatShortDate(currentDate) }}</small>
      </div>
    </div>
  </div>
</template>

<script>
import { computed } from 'vue';
import { formatUzDayMonth } from '../utils/date';
import { fishLabel } from '../utils/fish';
import { formatGrams, formatInt } from '../utils/number';
import { t } from '../i18n';

const WIDTH = 320;
const HEIGHT = 156;
const PAD_X = 22;
const PAD_TOP = 28;
const PAD_BOTTOM = 28;

export default {
  props: {
    fishType: { type: String, required: true },
    series: { type: Array, default: () => [] },
    initialWeightG: { type: Number, default: null },
    initialDate: { type: String, default: null },
    currentWeightG: { type: Number, default: null },
    currentDate: { type: String, default: null },
    growthDays: { type: Number, default: 0 },
    gainPerFishG: { type: Number, default: null },
    hasWeighing: { type: Boolean, default: false },
  },
  setup(props) {
    const width = WIDTH;
    const height = HEIGHT;
    const padX = PAD_X;
    const padTop = PAD_TOP;
    const padBottom = PAD_BOTTOM;
    const gradientId = `fish-growth-${props.fishType}-${Math.random().toString(36).slice(2, 8)}`;

    const ariaLabel = computed(() => (
      `${fishLabel(props.fishType, t)} ${t('fishGrowthChartAria')}`
    ));

    const chartSeries = computed(() => {
      const rows = (props.series || []).filter((row) => row.date && row.weight_g != null);
      if (rows.length >= 2) {
        return rows;
      }

      if (props.initialWeightG != null && props.initialDate) {
        const start = { date: props.initialDate, weight_g: props.initialWeightG };
        if (props.hasWeighing && props.currentWeightG != null && props.currentDate) {
          return [
            start,
            { date: props.currentDate, weight_g: props.currentWeightG },
          ];
        }
        return [start];
      }

      return rows;
    });

    const plottedPoints = computed(() => {
      const rows = chartSeries.value;
      if (!rows.length) {
        return [];
      }

      const weights = rows.map((row) => Number(row.weight_g));
      const times = rows.map((row) => new Date(`${row.date}T12:00:00`).getTime());
      const minW = Math.min(...weights);
      const maxW = Math.max(...weights);
      const minT = Math.min(...times);
      const maxT = Math.max(...times);
      const wRange = Math.max(maxW - minW, maxW * 0.15, 10);
      const wBase = minW - wRange * 0.08;
      const innerW = width - padX * 2;
      const innerH = height - padTop - padBottom;

      if (rows.length === 1) {
        const y = height - padBottom - ((weights[0] - wBase) / (wRange * 1.16)) * innerH;
        return [
          { x: padX + innerW * 0.12, y, ...rows[0] },
          { x: padX + innerW * 0.88, y, pending: true, ...rows[0] },
        ];
      }

      const tRange = Math.max(maxT - minT, 1);

      return rows.map((row, index) => {
        const time = new Date(`${row.date}T12:00:00`).getTime();
        const x = padX + ((time - minT) / tRange) * innerW;
        const y = height - padBottom - ((Number(row.weight_g) - wBase) / (wRange * 1.16)) * innerH;

        return { x, y, ...row, index };
      });
    });

    const datedPoints = computed(() => {
      const points = plottedPoints.value.filter((point) => !point.pending && point.date);
      const total = points.length;
      const dotGap = 14; // nuqtadan sal joy

      return points.map((point, index) => {
        let textAnchor = 'middle';
        let labelX = point.x;
        if (index === 0) {
          textAnchor = 'start';
          labelX = Math.max(4, point.x - 2);
        } else if (index === total - 1) {
          textAnchor = 'end';
          labelX = Math.min(width - 4, point.x + 2);
        }

        // Son — doim nuqta tepasida; sana — pastida
        const weightY = Math.max(11, point.y - dotGap);
        const dateY = Math.min(height - 4, point.y + dotGap + 4);

        return {
          ...point,
          labelX,
          weightY,
          dateY,
          textAnchor,
        };
      });
    });

    const linePath = computed(() => {
      const points = plottedPoints.value.filter((point) => !point.pending);
      if (points.length < 2) {
        return '';
      }

      return points.map((point, index) => (
        `${index === 0 ? 'M' : 'L'} ${point.x.toFixed(2)} ${point.y.toFixed(2)}`
      )).join(' ');
    });

    const pendingPath = computed(() => {
      if (props.hasWeighing || plottedPoints.value.length < 2) {
        return '';
      }

      const [start, end] = plottedPoints.value;
      return `M ${start.x.toFixed(2)} ${start.y.toFixed(2)} L ${end.x.toFixed(2)} ${end.y.toFixed(2)}`;
    });

    const areaPath = computed(() => {
      const points = plottedPoints.value.filter((point) => !point.pending);
      if (points.length < 2 || !linePath.value) {
        return '';
      }

      const baseline = height - padBottom;
      const first = points[0];
      const last = points[points.length - 1];

      return `${linePath.value} L ${last.x.toFixed(2)} ${baseline} L ${first.x.toFixed(2)} ${baseline} Z`;
    });

    const gridTicks = computed(() => {
      const points = plottedPoints.value.filter((point) => !point.pending);
      if (!points.length) {
        return [];
      }

      const ys = points.map((point) => point.y);
      const minY = Math.min(...ys);
      const maxY = Math.max(...ys);

      return [minY, (minY + maxY) / 2, maxY].map((y) => ({ y }));
    });

    function formatShortDate(value) {
      return formatUzDayMonth(value) || '';
    }

    function formatDotDate(value) {
      return formatUzDayMonth(value) || '';
    }

    return {
      width,
      height,
      padX,
      gradientId,
      ariaLabel,
      plottedPoints,
      datedPoints,
      linePath,
      pendingPath,
      areaPath,
      gridTicks,
      formatGrams,
      formatInt,
      formatShortDate,
      formatDotDate,
    };
  },
};
</script>
