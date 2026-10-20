<template>
  <section v-if="loading" class="panel empty">{{ $t('loading') }}</section>
  <section v-else-if="!ration" class="panel empty">{{ $t('noData') }}</section>

  <template v-else>
    <section class="panel ration-card">
      <div class="ration-card-head">
        <div>
          <router-link class="btn btn-outline btn-sm ration-back-link" :to="{ name: 'owner-ration-list' }">
            {{ $t('backToRations') }}
          </router-link>
          <h2>{{ ration.name }}</h2>
        </div>
        <div class="ration-card-actions">
          <router-link class="btn btn-outline btn-sm" :to="{ name: 'owner-ration-edit', params: { id: ration.id } }">
            {{ $t('edit') }}
          </router-link>
          <button class="btn btn-outline btn-sm" type="button" @click="printRation">{{ $t('print400kg') }}</button>
          <button class="btn btn-outline btn-sm" type="button" @click="removeRation">{{ $t('delete') }}</button>
        </div>
      </div>

      <div class="ration-kpi-row">
        <div class="ration-kpi">
          <span>{{ $t('sum100kg') }}</span>
          <strong>{{ formatMoney(ration.totals.total_sum) }}</strong>
        </div>
        <div class="ration-kpi">
          <span>{{ $t('tanNarx1kg') }}</span>
          <strong>{{ formatMoney(ration.totals.cost_per_kg) }}</strong>
        </div>
        <div v-if="ration.totals.cost_with_kop != null" class="ration-kpi">
          <span>{{ $t('costWithKop') }}</span>
          <strong>{{ formatMoney(ration.totals.cost_with_kop) }}</strong>
        </div>
        <div v-if="ration.sale_price_per_kg != null" class="ration-kpi">
          <span>{{ $t('salePricePerKg') }}</span>
          <strong>{{ formatMoney(ration.sale_price_per_kg) }}</strong>
        </div>
      </div>

      <h3 class="owner-ration-section-head">{{ $t('ration100kg') }}</h3>
      <div class="owner-ration-product-grid">
        <article
          v-for="line in ration.items"
          :key="line.product_id"
          class="owner-ration-product-card owner-ration-show-item"
        >
          <h4>{{ line.product_name }}</h4>
          <div class="owner-ration-stat-grid">
            <div class="owner-ration-stat">
              <span>{{ $t('qtyPer100kgShort') }}</span>
              <strong>{{ formatQty(line.qty_per_100kg) }}</strong>
            </div>
            <div class="owner-ration-stat">
              <span>{{ $t('priceShort') }}</span>
              <strong>{{ formatMoney(line.price_per_kg) }}</strong>
            </div>
            <div class="owner-ration-stat owner-ration-stat--wide">
              <span>{{ $t('sum') }}</span>
              <strong>{{ formatMoney(line.line_sum) }}</strong>
            </div>
          </div>
        </article>
      </div>
      <div class="owner-ration-summary-row">
        <div class="owner-ration-stat">
          <span>{{ $t('total') }} ({{ $t('qtyPer100kgShort') }})</span>
          <strong>{{ formatQty(ration.totals.qty_per_100kg) }}</strong>
        </div>
        <div class="owner-ration-stat">
          <span>{{ $t('tanNarx1kg') }}</span>
          <strong>{{ formatMoney(ration.totals.cost_per_kg) }}</strong>
        </div>
        <div class="owner-ration-stat">
          <span>{{ $t('sum100kg') }}</span>
          <strong>{{ formatMoney(ration.totals.total_sum) }}</strong>
        </div>
      </div>

      <h3 class="owner-ration-section-head">{{ $t('ration400kg') }}</h3>
      <div class="owner-ration-product-grid owner-ration-product-grid--400">
        <article
          v-for="line in ration.variant_400kg.items"
          :key="line.product_id"
          class="owner-ration-product-card owner-ration-show-item"
        >
          <h4>{{ line.product_name }}</h4>
          <div class="owner-ration-stat">
            <span>{{ $t('quantity') }}</span>
            <strong>{{ formatKgLabelPrint(line.qty_kg) }}</strong>
          </div>
        </article>
      </div>
      <div class="owner-ration-summary-row">
        <div class="owner-ration-stat">
          <span>{{ $t('total') }}</span>
          <strong>{{ Math.round(ration.variant_400kg.batch_kg) }} кг</strong>
        </div>
      </div>
    </section>

    <RationPrint400 v-if="printRationData" :ration="printRationData" />
  </template>
</template>

<script>
import { onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import RationPrint400 from '../../components/RationPrint400.vue';
import { showSaved } from '../../flash';
import { t } from '../../i18n';
import { formatMoney, formatQty, formatKgLabelPrint } from '../../utils/rationFormat';

export default {
  components: { RationPrint400 },
  setup() {
    const route = useRoute();
    const router = useRouter();
    const loading = ref(true);
    const ration = ref(null);
    const printRationData = ref(null);

    async function load() {
      loading.value = true;
      try {
        const { data } = await window.axios.get(`/owner/rations/${route.params.id}`);
        ration.value = data.ration || null;
      } catch {
        ration.value = null;
      } finally {
        loading.value = false;
      }
    }

    function printRation() {
      printRationData.value = ration.value;
      requestAnimationFrame(() => {
        requestAnimationFrame(() => {
          window.print();
          setTimeout(() => { printRationData.value = null; }, 500);
        });
      });
    }

    async function removeRation() {
      if (!confirm(t('confirmDeleteRation'))) return;
      try {
        await window.axios.delete(`/owner/rations/${route.params.id}`);
        showSaved(t('deleted'));
        router.push({ name: 'owner-ration-list' });
      } catch (e) {
        window.alert(e.response?.data?.message || t('noData'));
      }
    }

    watch(() => route.params.id, load);
    onMounted(load);

    return {
      loading,
      ration,
      printRationData,
      formatMoney,
      formatQty,
      formatKgLabelPrint,
      printRation,
      removeRation,
    };
  },
};
</script>

<style scoped>
.ration-back-link {
  margin-bottom: 0.75rem;
}

.ration-card-head {
  display: flex;
  justify-content: space-between;
  gap: 1rem;
  align-items: flex-start;
  margin-bottom: 1rem;
}

.ration-card-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.ration-kpi-row {
  display: flex;
  flex-direction: column;
  gap: 0.65rem;
  margin-bottom: 1.5rem;
}

.ration-kpi {
  padding: 0.85rem 1rem;
  border-radius: 12px;
  background: #f4faf6;
  border: 1px solid #d0d5dd;
}

.ration-kpi span {
  display: block;
  font-size: 0.85rem;
  font-weight: 700;
  color: #3a5548;
  margin-bottom: 0.25rem;
}

.ration-kpi strong {
  font-size: clamp(1.2rem, 3vw, 1.5rem);
  font-variant-numeric: tabular-nums;
  color: #0f2a1c;
}

.owner-ration-show-item h4 {
  font-size: 1.15rem;
}
</style>
