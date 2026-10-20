<template>
  <DirectorShell>
    <div class="farm-hub">
      <div v-if="loading" class="panel empty">{{ $t('loading') }}</div>
      <div v-else-if="!farmId" class="panel empty">{{ $t('directorNoFarmYet') }}</div>

      <template v-else>
        <div class="dir-kpi-grid dir-kpi-grid--farm">
          <div class="dir-kpi">
            <span class="dir-kpi__label">{{ $t('ponds') }}</span>
            <strong class="dir-kpi__value">{{ formatNumber(kpis.ponds) }}</strong>
            <span class="dir-kpi__meta">{{ $t('pieces') }}</span>
          </div>
          <div class="dir-kpi">
            <span class="dir-kpi__label">{{ $t('totalFishCount') }}</span>
            <strong class="dir-kpi__value">{{ formatNumber(kpis.fish) }}</strong>
            <span class="dir-kpi__meta">{{ $t('pieces') }}</span>
          </div>
          <div class="dir-kpi dir-kpi--sales">
            <span class="dir-kpi__label">{{ $t('financeTotalSales') }}</span>
            <strong class="dir-kpi__value">{{ formatMoney(kpis.sales) }}</strong>
          </div>
          <div class="dir-kpi dir-kpi--expense">
            <span class="dir-kpi__label">{{ $t('financeTotalExpenses') }}</span>
            <strong class="dir-kpi__value">{{ formatMoney(kpis.expenses) }}</strong>
          </div>
          <div
            class="dir-kpi"
            :class="kpis.profit >= 0 ? 'dir-kpi--profit' : 'dir-kpi--loss'"
          >
            <span class="dir-kpi__label">{{ $t('financeProfit') }}</span>
            <strong class="dir-kpi__value">{{ formatMoney(kpis.profit) }}</strong>
          </div>
          <div class="dir-kpi">
            <span class="dir-kpi__label">{{ $t('warehouse') }}</span>
            <strong class="dir-kpi__value">{{ formatNumber(kpis.warehouseItems) }}</strong>
            <span class="dir-kpi__meta">{{ $t('directorWarehouseSku') }}</span>
          </div>
        </div>

        <div class="farm-hub__grid">
          <button type="button" class="farm-hub-card farm-hub-card--fishery" @click="go('director-fishery-ponds')">
            <span class="farm-hub-card__icon" aria-hidden="true">
              <HubSectionMark section="fishery" />
            </span>
            <span class="farm-hub-card__body">
              <strong>{{ $t('fishery') }}</strong>
            </span>
            <span class="farm-hub-card__arrow" aria-hidden="true">
              <svg viewBox="0 0 12 12" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 2l4 4-4 4"/></svg>
            </span>
          </button>

          <button type="button" class="farm-hub-card farm-hub-card--warehouse" @click="go('director-warehouse')">
            <span class="farm-hub-card__icon" aria-hidden="true">
              <HubSectionMark section="warehouse" />
            </span>
            <span class="farm-hub-card__body">
              <strong>{{ $t('warehouse') }}</strong>
            </span>
            <span class="farm-hub-card__arrow" aria-hidden="true">
              <svg viewBox="0 0 12 12" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 2l4 4-4 4"/></svg>
            </span>
          </button>

          <button type="button" class="farm-hub-card farm-hub-card--finance" @click="go('director-finance')">
            <span class="farm-hub-card__icon" aria-hidden="true">
              <HubSectionMark section="finance" />
            </span>
            <span class="farm-hub-card__body">
              <strong>{{ $t('finance') }}</strong>
            </span>
            <span class="farm-hub-card__arrow" aria-hidden="true">
              <svg viewBox="0 0 12 12" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 2l4 4-4 4"/></svg>
            </span>
          </button>
        </div>
      </template>
    </div>
  </DirectorShell>
</template>

<script>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import DirectorShell from '../../components/DirectorShell.vue';
import HubSectionMark from '../../components/HubSectionMark.vue';
import { formatMoney, formatNumber } from '../../utils/number';
import { setDirectorFarmId, useDirectorFarmId } from '../../utils/directorFarm';

export default {
  components: { DirectorShell, HubSectionMark },
  setup() {
    const router = useRouter();
    const farmId = useDirectorFarmId();
    const farm = ref(null);
    const ponds = ref([]);
    const overview = ref(null);
    const warehouse = ref([]);
    const loading = ref(true);

    const kpis = computed(() => {
      const pondList = ponds.value || [];
      const active = pondList.filter((p) => p.status !== 'closed' && !p.closed_at);
      const fish = active.reduce((sum, p) => sum + (Number(p.current_stock) || 0), 0);
      const ov = overview.value || {};
      return {
        ponds: active.length || Number(farm.value?.ponds_count) || 0,
        fish,
        sales: Number(ov.total_sales) || 0,
        expenses: Number(ov.total_expenses) || 0,
        profit: Number(ov.profit) || 0,
        warehouseItems: (warehouse.value || []).length,
      };
    });

    async function ensureFarmId() {
      if (farmId.value) return farmId.value;
      const { data } = await window.axios.get('/director/farms');
      const list = Array.isArray(data) ? data : [];
      if (list[0]) {
        setDirectorFarmId(list[0].id);
        return String(list[0].id);
      }
      return null;
    }

    async function load() {
      loading.value = true;
      try {
        const id = await ensureFarmId();
        if (!id) return;

        const [farmRes, pondsRes, finRes, whRes] = await Promise.allSettled([
          window.axios.get(`/director/farms/${id}`),
          window.axios.get(`/director/farms/${id}/ponds`),
          window.axios.get(`/director/farms/${id}/finance/overview`),
          window.axios.get(`/director/farms/${id}/warehouse`),
        ]);

        if (farmRes.status === 'fulfilled') {
          farm.value = farmRes.value.data;
          setDirectorFarmId(farmRes.value.data.id);
        }
        if (pondsRes.status === 'fulfilled') ponds.value = pondsRes.value.data || [];
        if (finRes.status === 'fulfilled') overview.value = finRes.value.data;
        if (whRes.status === 'fulfilled') warehouse.value = whRes.value.data || [];
      } finally {
        loading.value = false;
      }
    }

    function go(name) {
      router.push({ name });
    }

    onMounted(load);

    return {
      farmId,
      farm,
      loading,
      kpis,
      go,
      formatMoney,
      formatNumber,
    };
  },
};
</script>
