<template>
  <section class="panel ration-section">
    <div class="owner-ration-section-head">
      <h2>{{ $t('rationPrices') }}</h2>
      <small>{{ $t('rationPricesHint') }}</small>
    </div>

    <div v-if="loading" class="empty">{{ $t('loading') }}</div>
    <div v-else-if="!rations.length" class="empty">{{ $t('noSavedRations') }}</div>

    <div v-else class="owner-ration-price-grid">
      <article v-for="ration in rations" :key="ration.id" class="owner-ration-price-card">
        <h3 class="owner-ration-price-card-name">{{ ration.name }}</h3>

        <div class="owner-ration-stat-grid">
          <div class="owner-ration-stat owner-ration-stat--cost">
            <span>{{ $t('tanNarx1kg') }}</span>
            <strong>{{ formatMoney(costBase(ration)) }}</strong>
          </div>

          <div class="owner-ration-stat owner-ration-stat--sale owner-ration-stat--wide">
            <span>{{ $t('salePricePerKg') }}</span>
            <FormattedNumberInput
              v-model="drafts[ration.id]"
              :max-decimals="0"
              class="owner-ration-sale-input"
              :placeholder="$t('salePricePerKg')"
              @keydown.enter.prevent="savePrice(ration)"
            />
          </div>

          <div
            class="owner-ration-stat owner-ration-stat--profit"
            :class="valueClass(previewProfit(ration))"
          >
            <span>{{ $t('profitPerKg') }}</span>
            <strong>{{ formatProfit(previewProfit(ration)) }}</strong>
          </div>

          <div
            class="owner-ration-stat owner-ration-stat--margin"
            :class="valueClass(previewMargin(ration))"
          >
            <span>{{ $t('marginPercent') }}</span>
            <strong>{{ formatMargin(previewMargin(ration)) }}</strong>
          </div>
        </div>

        <button
          class="btn btn-primary btn-block-mobile"
          type="button"
          :disabled="savingId === ration.id"
          @click="savePrice(ration)"
        >
          {{ savingId === ration.id ? $t('loading') : $t('save') }}
        </button>
      </article>
    </div>
  </section>
</template>

<script>
import { onMounted, reactive, ref } from 'vue';
import { showSaved } from '../../flash';
import { t } from '../../i18n';
import { formatMoney } from '../../utils/rationFormat';

export default {
  setup() {
    const loading = ref(true);
    const rations = ref([]);
    const drafts = reactive({});
    const savingId = ref(null);

    function costBase(ration) {
      return ration.totals.cost_with_kop ?? ration.totals.cost_per_kg;
    }

    function previewProfit(ration) {
      const sale = Number(drafts[ration.id]);
      const cost = Number(costBase(ration));
      if (!Number.isFinite(sale) || sale <= 0 || !Number.isFinite(cost)) return null;
      return sale - cost;
    }

    function previewMargin(ration) {
      const sale = Number(drafts[ration.id]);
      const profit = previewProfit(ration);
      if (profit == null || !Number.isFinite(sale) || sale <= 0) return null;
      return (profit / sale) * 100;
    }

    function formatProfit(value) {
      if (value == null || !Number.isFinite(value)) return '—';
      return formatMoney(value);
    }

    function formatMargin(value) {
      if (value == null || !Number.isFinite(value)) return '—';
      return `${value.toFixed(1)}%`;
    }

    function valueClass(value) {
      if (value == null || !Number.isFinite(value)) return '';
      if (value > 0) return 'owner-ration-stat--positive';
      if (value < 0) return 'owner-ration-stat--negative';
      return '';
    }

    async function load() {
      loading.value = true;
      try {
        const { data } = await window.axios.get('/owner/rations');
        rations.value = data.rations || [];
        rations.value.forEach((ration) => {
          drafts[ration.id] = ration.sale_price_per_kg != null ? String(ration.sale_price_per_kg) : '';
        });
      } finally {
        loading.value = false;
      }
    }

    async function savePrice(ration) {
      savingId.value = ration.id;
      try {
        const raw = drafts[ration.id];
        const payload = {
          sale_price_per_kg: raw !== '' ? Number(raw) : null,
        };
        const { data } = await window.axios.put(`/owner/rations/${ration.id}/sale-price`, payload);
        const index = rations.value.findIndex((r) => r.id === ration.id);
        if (index >= 0) rations.value[index] = data.ration;
        showSaved(t('saved'));
      } catch (e) {
        window.alert(e.response?.data?.message || t('noData'));
      } finally {
        savingId.value = null;
      }
    }

    onMounted(load);

    return {
      loading,
      rations,
      drafts,
      savingId,
      formatMoney,
      costBase,
      previewProfit,
      formatProfit,
      previewMargin,
      formatMargin,
      valueClass,
      savePrice,
    };
  },
};
</script>
