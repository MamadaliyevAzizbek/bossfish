<template>
  <div class="fish-growth-board">
    <article
      v-for="row in rows"
      :key="row.fish_type"
      class="fish-growth-card"
    >
      <header class="fish-growth-card__head">
        <FishTypeBadge :type="row.fish_type" block />
        <div class="fish-growth-card__meta">
          <strong>{{ row.label }}</strong>
          <span>{{ formatInt(row.count) }} {{ $t('pieces') }} · {{ formatKgMass(row.mass_kg) }} kg</span>
        </div>
      </header>

      <FishGrowthChart
        :fish-type="row.fish_type"
        :series="row.series"
        :initial-weight-g="row.initial_weight_g"
        :initial-date="row.initial_date"
        :current-weight-g="row.current_weight_g"
        :current-date="row.current_date"
        :growth-days="row.growth_days"
        :gain-per-fish-g="row.gain_per_fish_g"
        :has-weighing="row.has_weighing"
      />

      <dl class="fish-growth-card__stats">
        <div>
          <dt>{{ $t('added') }}</dt>
          <dd>{{ formatInt(row.added) }}</dd>
        </div>
        <div>
          <dt>{{ $t('removedTotal') }}</dt>
          <dd>{{ formatInt(row.removed) }}</dd>
        </div>
        <div>
          <dt>{{ $t('weightGain') }}</dt>
          <dd>{{ row.gain_per_fish_g != null ? `+${formatGrams(row.gain_per_fish_g)} g` : '—' }}</dd>
        </div>
        <div>
          <dt>{{ $t('dailyGrowth') }}</dt>
          <dd>
            {{ row.daily_growth_per_fish_g != null
              ? `${formatNumber(row.daily_growth_per_fish_g, { decimals: 1 })} ${$t('gramPerFishPerDay')}`
              : '—' }}
          </dd>
        </div>
      </dl>
    </article>
  </div>
</template>

<script>
import FishGrowthChart from './FishGrowthChart.vue';
import FishTypeBadge from './FishTypeBadge.vue';
import { formatGrams, formatInt, formatKgMass, formatNumber } from '../utils/number';

export default {
  components: { FishGrowthChart, FishTypeBadge },
  props: {
    rows: { type: Array, default: () => [] },
  },
  setup() {
    return {
      formatInt,
      formatGrams,
      formatKgMass,
      formatNumber,
    };
  },
};
</script>
