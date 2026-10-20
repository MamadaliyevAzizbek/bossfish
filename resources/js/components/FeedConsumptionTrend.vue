<template>
  <article class="feed-trend-card">
    <header class="feed-trend-card__head">
      <span class="feed-trend-card__icon" aria-hidden="true">🌾</span>
      <div class="feed-trend-card__meta">
        <strong>{{ $t('feedConsumptionTrendTitle') }}</strong>
        <span>
          {{ formatInt(summary.days_with_feed) }} {{ $t('days') }} ·
          {{ formatKg(summary.total_kg) }} kg {{ $t('feedCumulativeShort') }}
        </span>
      </div>
      <span class="feed-trend-badge" :class="`feed-trend-badge--${summary.trend_direction || 'stable'}`">
        {{ trendBadgeLabel }}
      </span>
    </header>

    <FeedTrendChart :series="series" :summary="summary" />

    <dl class="feed-trend-card__stats">
      <div>
        <dt>{{ $t('feedAverageDaily') }}</dt>
        <dd>{{ formatKg(summary.average_daily_kg) }} kg</dd>
      </div>
      <div>
        <dt>{{ $t('feedTrendLatestDay') }}</dt>
        <dd>{{ formatKg(summary.latest_daily_kg) }} kg</dd>
      </div>
      <div>
        <dt>{{ $t('feedAmountKg') }}</dt>
        <dd>{{ formatKg(feedOnlyTotal) }} kg</dd>
      </div>
      <div>
        <dt>{{ $t('wheatBranKg') }}</dt>
        <dd>{{ formatKg(branTotal) }} kg</dd>
      </div>
    </dl>

    <div class="feed-trend-days">
      <div class="feed-trend-days__head">
        <span>{{ $t('feedDailyBreakdown') }}</span>
        <small>{{ $t('feedDailyBreakdownHint') }}</small>
      </div>

      <div class="table-scroll feed-trend-days__scroll">
        <table class="report-table feed-trend-table">
          <thead>
            <tr>
              <th>{{ $t('date') }}</th>
              <th>{{ $t('feedDailyAmount') }}</th>
              <th>{{ $t('feedDelta') }}</th>
              <th>{{ $t('feedCumulative') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in reversedSeries" :key="row.date">
              <td>{{ formatShortDate(row.date) }}</td>
              <td class="num">
                <strong>{{ formatKg(row.feed_total_kg) }} kg</strong>
                <small v-if="row.feed_name" class="feed-trend-table__name">{{ row.feed_name }}</small>
              </td>
              <td class="num">
                <span
                  v-if="row.delta_kg != null"
                  class="feed-trend-delta"
                  :class="deltaClass(row.delta_kg)"
                >
                  {{ formatDelta(row) }}
                </span>
                <span v-else class="muted">—</span>
              </td>
              <td class="num">{{ formatKg(row.cumulative_kg) }} kg</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </article>
</template>

<script>
import { computed } from 'vue';
import FeedTrendChart from './FeedTrendChart.vue';
import { formatInt, formatKg, formatNumber } from '../utils/number';
import { t } from '../i18n';

export default {
  components: { FeedTrendChart },
  props: {
    data: { type: Object, required: true },
  },
  setup(props) {
    const series = computed(() => props.data?.series || []);
    const summary = computed(() => props.data?.summary || {});

    const reversedSeries = computed(() => [...series.value].reverse());

    const feedOnlyTotal = computed(() => (
      series.value.reduce((sum, row) => sum + Number(row.feeding_kg || 0), 0)
    ));

    const branTotal = computed(() => (
      series.value.reduce((sum, row) => sum + Number(row.wheat_bran_kg || 0), 0)
    ));

    const trendBadgeLabel = computed(() => {
      const direction = summary.value.trend_direction;
      const percent = summary.value.trend_percent;
      if (direction === 'up' && percent != null) {
        return t('feedTrendUp');
      }
      if (direction === 'down' && percent != null) {
        return t('feedTrendDown');
      }
      return t('feedTrendStable');
    });

    function formatShortDate(value) {
      if (!value) return '—';
      const parts = String(value).slice(0, 10).split('-');
      if (parts.length !== 3) return value;
      return `${parts[2]}.${parts[1]}.${parts[0]}`;
    }

    function deltaClass(delta) {
      if (delta > 0) return 'feed-trend-delta--up';
      if (delta < 0) return 'feed-trend-delta--down';
      return 'feed-trend-delta--flat';
    }

    function formatDelta(row) {
      const sign = row.delta_kg > 0 ? '+' : '';
      const kg = `${sign}${formatNumber(row.delta_kg, { decimals: 1 })} kg`;
      if (row.delta_percent != null && row.delta_kg !== 0) {
        const pSign = row.delta_percent > 0 ? '+' : '';
        return `${kg} (${pSign}${formatNumber(row.delta_percent, { decimals: 1 })}%)`;
      }
      return kg;
    }

    return {
      series,
      summary,
      reversedSeries,
      feedOnlyTotal,
      branTotal,
      trendBadgeLabel,
      formatInt,
      formatKg,
      formatShortDate,
      deltaClass,
      formatDelta,
    };
  },
};
</script>
