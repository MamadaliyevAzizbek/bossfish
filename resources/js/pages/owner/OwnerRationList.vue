<template>
  <section class="panel ration-section">
    <div class="owner-ration-section-head">
      <h2>{{ $t('savedRations') }}</h2>
      <small>{{ $t('savedRationsHint') }}</small>
    </div>

    <div v-if="loading" class="empty">{{ $t('loading') }}</div>
    <div v-else-if="!rations.length" class="empty">{{ $t('noSavedRations') }}</div>

    <div v-else class="owner-ration-list-grid">
      <article v-for="ration in rations" :key="ration.id" class="owner-ration-list-card panel">
        <h3>{{ ration.name }}</h3>
        <p v-if="ration.notes" class="owner-ration-list-note">{{ ration.notes }}</p>

        <div class="owner-ration-stat-grid">
          <div class="owner-ration-stat">
            <span>{{ $t('sum100kg') }}</span>
            <strong>{{ formatMoney(ration.totals.total_sum) }}</strong>
          </div>
          <div class="owner-ration-stat">
            <span>{{ $t('tanNarx1kg') }}</span>
            <strong>{{ formatMoney(ration.totals.cost_with_kop ?? ration.totals.cost_per_kg) }}</strong>
          </div>
          <div v-if="ration.sale_price_per_kg != null" class="owner-ration-stat owner-ration-stat--wide">
            <span>{{ $t('salePricePerKg') }}</span>
            <strong>{{ formatMoney(ration.sale_price_per_kg) }}</strong>
          </div>
        </div>

        <div class="owner-ration-list-actions">
          <router-link class="btn btn-primary btn-sm" :to="{ name: 'owner-ration-show', params: { id: ration.id } }">
            {{ $t('openRation') }}
          </router-link>
          <router-link class="btn btn-outline btn-sm" :to="{ name: 'owner-ration-edit', params: { id: ration.id } }">
            {{ $t('edit') }}
          </router-link>
          <button class="btn btn-outline btn-sm" type="button" @click="removeRation(ration)">{{ $t('delete') }}</button>
        </div>
      </article>
    </div>
  </section>
</template>

<script>
import { onMounted, ref } from 'vue';
import { showSaved } from '../../flash';
import { t } from '../../i18n';
import { formatMoney } from '../../utils/rationFormat';

export default {
  setup() {
    const loading = ref(true);
    const rations = ref([]);

    async function load() {
      loading.value = true;
      try {
        const { data } = await window.axios.get('/owner/rations');
        rations.value = data.rations || [];
      } finally {
        loading.value = false;
      }
    }

    async function removeRation(ration) {
      if (!confirm(t('confirmDeleteRation'))) return;
      try {
        await window.axios.delete(`/owner/rations/${ration.id}`);
        showSaved(t('deleted'));
        await load();
      } catch (e) {
        window.alert(e.response?.data?.message || t('noData'));
      }
    }

    onMounted(load);

    return {
      loading,
      rations,
      formatMoney,
      removeRation,
    };
  },
};
</script>

<style scoped>
.owner-ration-list-note {
  margin: 0 0 0.75rem;
  font-size: 0.95rem;
  color: #3a5548;
  font-weight: 600;
}

.owner-ration-list-actions {
  display: flex;
  flex-direction: column;
  align-items: stretch;
  gap: 0.5rem;
  margin-top: 1rem;
}
</style>
