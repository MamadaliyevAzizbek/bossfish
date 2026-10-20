<template>
  <div class="feed-trend-chart">
    <svg
      class="feed-trend-chart__svg"
      :viewBox="`0 0 ${width} ${height}`"
      preserveAspectRatio="none"
      role="img"
      :aria-label="$t('feedTrendChartAria')"
    >
      <defs>
        <linearGradient :id="gradientId" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stop-color="rgba(239, 108, 0, 0.28)" />
          <stop offset="100%" stop-color="rgba(239, 108, 0, 0.02)" />
        </linearGradient>
      </defs>

      <line
        v-for="(tick, index) in gridTicks"
        :key="`grid-${index}`"
        :x1="padX"
        :y1="tick.y"
        :x2="width - padX"
        :y2="tick.y"
        class="feed-trend-chart__grid"
      />

      <rect
        v-for="(bar, index) in bars"
        :key="`bar-${index}`"
        :x="bar.x"
        :y="bar.y"
        :width="bar.width"
        :height="bar.height"
        class="feed-trend-chart__bar"
        :class="{ 'feed-trend-chart__bar--latest': index === bars.length - 1 }"
        rx="2"
      />

      <path
        v-if="linePath"
        :d="linePath"
        class="feed-trend-chart__line"
        fill="none"
      />

      <circle
        v-for="(point, index) in plottedPoints"
        :key="`point-${index}`"
        :cx="point.x"
        :cy="point.y"
        :r="index === plottedPoints.length - 1 ? 5 : 3.5"
        class="feed-trend-chart__dot"
        :class="{ 'feed-trend-chart__dot--latest': index === plottedPoints.length - 1 }"
      />
    </svg>

    <div class="feed-trend-chart__timeline">
      <div class="feed-trend-chart__marker feed-trend-chart__marker--start">
        <span class="feed-trend-chart__marker-label">{{ $t('feedTrendFirstDay') }}</span>
        <strong>{{ formatKg(firstDaily) }} kg</strong>
        <small v-if="firstDate">{{ formatShortDate(firstDate) }}</small>
      </div>

      <div class="feed-trend-chart__marker feed-trend-chart__marker--mid">
        <span class="feed-trend-chart__marker-label">{{ $t('feedAverageDaily') }}</span>
        <strong>{{ formatKg(averageDaily) }} kg</strong>
        <small>{{ trendLabel }}</small>
      </div>

      <div class="feed-trend-chart__marker feed-trend-chart__marker--end">
        <span class="feed-trend-chart__marker-label">{{ $t('feedTrendLatestDay') }}</span>
        <strong>{{ formatKg(latestDaily) }} kg</strong>
        <small v-if="lastDate">{{ formatShortDate(lastDate) }}</small>
      </div>
    </div>
  </div>
</template>

<script>
import { computed } from 'vue';
import { formatKg } from '../utils/number';
import { t } from '../i18n';

const WIDTH = 320;
const HEIGHT = 112;
const PAD_X = 18;
const PAD_Y = 16;

export default {
  props: {
    series: { type: Array, default: () => [] },
    summary: { type: Object, default: () => ({}) },
  },
  setup(props) {
    const width = WIDTH;
    const height = HEIGHT;
    const padX = PAD_X;
    const padY = PAD_Y;
    const gradientId = `feed-trend-${Math.random().toString(36).slice(2, 8)}`;

    const chartSeries = computed(() => (
      (props.series || []).filter((row) => row.date && row.feed_total_kg != null)
    ));

    const firstDaily = computed(() => chartSeries.value[0]?.feed_total_kg ?? 0);
    const latestDaily = computed(() => (
      props.summary?.latest_daily_kg ?? chartSeries.value[chartSeries.value.length - 1]?.feed_total_kg ?? 0
    ));
    const averageDaily = computed(() => props.summary?.average_daily_kg ?? 0);
    const firstDate = computed(() => props.summary?.first_date ?? chartSeries.value[0]?.date ?? null);
    const lastDate = computed(() => props.summary?.last_date ?? chartSeries.value[chartSeries.value.length - 1]?.date ?? null);

    const trendLabel = computed(() => {
      const direction = props.summary?.trend_direction;
      const percent = props.summary?.trend_percent;
      if (direction === 'up' && percent != null) {
        return t('feedTrendUpPercent', { percent });
      }
      if (direction === 'down' && percent != null) {
        return t('feedTrendDownPercent', { percent: Math.abs(percent) });
      }
      return t('feedTrendStable');
    });

    const plottedPoints = computed(() => {
      const rows = chartSeries.value;
      if (!rows.length) return [];

      const values = rows.map((row) => Number(row.feed_total_kg));
      const times = rows.map((row) => new Date(`${row.date}T12:00:00`).getTime());
      const minV = Math.min(...values);
      const maxV = Math.max(...values);
      const minT = Math.min(...times);
      const maxT = Math.max(...times);
      const vRange = Math.max(maxV - minV, maxV * 0.15, 1);
      const vBase = minV - vRange * 0.08;
      const innerW = width - padX * 2;
      const innerH = height - padY * 2;
      const tRange = Math.max(maxT - minT, 1);

      return rows.map((row) => {
        const time = new Date(`${row.date}T12:00:00`).getTime();
        const x = rows.length === 1
          ? padX + innerW / 2
          : padX + ((time - minT) / tRange) * innerW;
        const y = height - padY - ((Number(row.feed_total_kg) - vBase) / (vRange * 1.16)) * innerH;
        return { x, y, ...row };
      });
    });

    const bars = computed(() => {
      const points = plottedPoints.value;
      if (!points.length) return [];

      const baseline = height - padY;
      const barWidth = Math.max(
        6,
        Math.min(18, (width - padX * 2) / Math.max(points.length, 1) * 0.55),
      );

      return points.map((point) => ({
        x: point.x - barWidth / 2,
        y: point.y,
        width: barWidth,
        height: Math.max(baseline - point.y, 2),
      }));
    });

    const linePath = computed(() => {
      if (plottedPoints.value.length < 2) return '';
      return plottedPoints.value.map((point, index) => (
        `${index === 0 ? 'M' : 'L'} ${point.x.toFixed(2)} ${point.y.toFixed(2)}`
      )).join(' ');
    });

    const gridTicks = computed(() => {
      const points = plottedPoints.value;
      if (!points.length) return [];
      const ys = points.map((point) => point.y);
      const minY = Math.min(...ys);
      const maxY = Math.max(...ys);
      return [minY, (minY + maxY) / 2, maxY].map((y) => ({ y }));
    });

    function formatShortDate(value) {
      if (!value) return '';
      const parts = String(value).slice(0, 10).split('-');
      if (parts.length !== 3) return value;
      return `${parts[2]}.${parts[1]}.${parts[0]}`;
    }

    return {
      width,
      height,
      padX,
      gradientId,
      chartSeries,
      firstDaily,
      latestDaily,
      averageDaily,
      firstDate,
      lastDate,
      trendLabel,
      plottedPoints,
      bars,
      linePath,
      gridTicks,
      formatKg,
      formatShortDate,
    };
  },
};
</script>
