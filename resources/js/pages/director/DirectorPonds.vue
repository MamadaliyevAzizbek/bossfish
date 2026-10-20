<template>
  <DirectorShell>
    <div v-if="loading" class="panel empty">{{ $t('loading') }}</div>

    <div v-else-if="!farms.length" class="panel empty">
      {{ $t('directorNoFarmYet') }}
    </div>

    <template v-else>
      <div class="dir-kpi-grid">
        <div class="dir-kpi">
          <span class="dir-kpi__label">{{ $t('farms') }}</span>
          <strong class="dir-kpi__value">{{ formatNumber(stats.farms) }}</strong>
          <span class="dir-kpi__meta">{{ $t('pieces') }}</span>
        </div>
        <div class="dir-kpi">
          <span class="dir-kpi__label">{{ $t('ponds') }}</span>
          <strong class="dir-kpi__value">{{ formatNumber(stats.ponds) }}</strong>
          <span class="dir-kpi__meta">{{ $t('pieces') }}</span>
        </div>
        <div class="dir-kpi">
          <span class="dir-kpi__label">{{ $t('totalFishCount') }}</span>
          <strong class="dir-kpi__value">{{ formatNumber(stats.fish) }}</strong>
          <span class="dir-kpi__meta">{{ $t('pieces') }}</span>
        </div>
        <div class="dir-kpi dir-kpi--sales">
          <span class="dir-kpi__label">{{ $t('financeTotalSales') }}</span>
          <strong class="dir-kpi__value">{{ formatMoney(stats.sales) }}</strong>
        </div>
        <div class="dir-kpi dir-kpi--expense">
          <span class="dir-kpi__label">{{ $t('financeTotalExpenses') }}</span>
          <strong class="dir-kpi__value">{{ formatMoney(stats.expenses) }}</strong>
        </div>
        <div
          class="dir-kpi"
          :class="stats.profit >= 0 ? 'dir-kpi--profit' : 'dir-kpi--loss'"
        >
          <span class="dir-kpi__label">{{ $t('financeProfit') }}</span>
          <strong class="dir-kpi__value">{{ formatMoney(stats.profit) }}</strong>
        </div>
        <div class="dir-kpi">
          <span class="dir-kpi__label">{{ $t('warehouse') }}</span>
          <strong class="dir-kpi__value">{{ formatNumber(stats.warehouseItems) }}</strong>
          <span class="dir-kpi__meta">{{ $t('directorWarehouseSku') }}</span>
        </div>
        <div class="dir-kpi">
          <span class="dir-kpi__label">{{ $t('financeTotalInvestments') }}</span>
          <strong class="dir-kpi__value">{{ formatMoney(stats.investments) }}</strong>
        </div>
      </div>

      <div class="dir-dash__columns">
        <section class="panel dir-dash__panel">
          <div class="panel-head">
            <h2>{{ $t('directorAttentionTitle') }}</h2>
          </div>
          <ul v-if="visibleAlerts.length" class="dir-alert-list">
            <li
              v-for="alert in visibleAlerts"
              :key="alert.id"
              class="dir-alert"
              :class="`dir-alert--${alert.tone}`"
            >
              <div class="dir-alert__body">
                <strong>{{ alert.title }}</strong>
                <span>{{ alert.detail }}</span>
              </div>
              <div class="dir-alert__actions">
                <button
                  type="button"
                  class="btn btn-outline btn-sm"
                  @click="markAlertAsRead(alert)"
                >
                  {{ $t('directorAlertMarkRead') }}
                </button>
                <button
                  v-if="alert.action"
                  type="button"
                  class="btn btn-primary btn-sm"
                  @click="runAlertAction(alert)"
                >
                  {{ alert.actionLabel }}
                </button>
              </div>
            </li>
          </ul>
          <div v-else class="dir-dash__quiet">{{ $t('directorNoAlerts') }}</div>
        </section>

        <section class="panel dir-dash__panel">
          <div class="panel-head">
            <h2>{{ $t('directorQuickActions') }}</h2>
          </div>
          <div class="dir-quick-grid">
            <button
              type="button"
              class="dir-quick"
              :disabled="!primaryFarm"
              @click="goFarmSection('director-fishery-ponds')"
            >
              <strong>{{ $t('fishery') }}</strong>
            </button>
            <button
              type="button"
              class="dir-quick"
              :disabled="!primaryFarm"
              @click="goFarmSection('director-warehouse')"
            >
              <strong>{{ $t('warehouse') }}</strong>
            </button>
            <button
              type="button"
              class="dir-quick"
              :disabled="!primaryFarm"
              @click="goFarmSection('director-finance')"
            >
              <strong>{{ $t('finance') }}</strong>
            </button>
          </div>
        </section>
      </div>

      <section class="panel dir-dash__panel">
        <div class="panel-head">
          <h2>{{ $t('farms') }}</h2>
        </div>
        <div class="crm-stat-grid">
          <button
            v-for="farm in farms"
            :key="farm.id"
            type="button"
            class="crm-stat-card crm-farm-card"
            :class="{ 'crm-farm-card--active': String(farm.id) === String(primaryFarm?.id) }"
            @click="openFarm(farm)"
          >
            <div class="crm-stat-card__top">
              <span class="crm-stat-card__label">{{ farm.region || $t('farm') }}</span>
              <span class="crm-stat-card__meta">{{ farm.ponds_count || 0 }} {{ $t('pondsCount') }}</span>
            </div>
            <strong class="crm-stat-card__value">{{ farm.name }}</strong>
            <span class="crm-farm-card__go">{{ $t('manageFarm') }} →</span>
          </button>
        </div>
      </section>
    </template>
  </DirectorShell>
</template>

<script>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import DirectorShell from '../../components/DirectorShell.vue';
import auth from '../../auth';
import { t } from '../../i18n';
import {
  alertIdentity,
  filterUnreadAlerts,
  markAlertRead,
} from '../../utils/directorAlerts';
import { getDirectorFarmId, setDirectorFarmId } from '../../utils/directorFarm';
import { formatMoney, formatNumber } from '../../utils/number';

const LOW_STOCK_QTY = 10;

export default {
  components: { DirectorShell },
  setup() {
    const router = useRouter();
    const farms = ref([]);
    const ponds = ref([]);
    const financeByFarm = ref({});
    const warehouseByFarm = ref({});
    const pendingByFarm = ref({});
    const loading = ref(true);

    const primaryFarm = computed(() => {
      if (!farms.value.length) return null;
      const stored = getDirectorFarmId();
      const match = farms.value.find((f) => String(f.id) === String(stored));
      return match || farms.value[0];
    });

    const stats = computed(() => {
      const farmList = farms.value;
      const pondList = ponds.value;
      let expenses = 0;
      let sales = 0;
      let profit = 0;
      let investments = 0;
      let warehouseItems = 0;

      farmList.forEach((farm) => {
        const fin = financeByFarm.value[farm.id];
        if (fin) {
          expenses += Number(fin.total_expenses) || 0;
          sales += Number(fin.total_sales) || 0;
          profit += Number(fin.profit) || 0;
          investments += Number(fin.total_investments) || 0;
        }
        const wh = warehouseByFarm.value[farm.id] || [];
        warehouseItems += wh.length;
      });

      const fish = pondList.reduce((sum, p) => {
        if (p.status === 'closed' || p.closed_at) return sum;
        return sum + (Number(p.current_stock) || 0);
      }, 0);

      const activePonds = pondList.filter((p) => p.status !== 'closed' && !p.closed_at).length;

      return {
        farms: farmList.length,
        ponds: activePonds || farmList.reduce((s, f) => s + (Number(f.ponds_count) || 0), 0),
        fish,
        expenses,
        sales,
        profit,
        investments,
        warehouseItems,
      };
    });

    const readTick = ref(0);

    const alerts = computed(() => {
      const list = [];
      const user = auth.state.user;

      if (user && typeof user.access_days_left === 'number' && user.access_days_left <= 7) {
        list.push({
          type: 'access',
          tone: user.access_days_left <= 3 ? 'danger' : 'warning',
          title: t('accessWarningTitle'),
          detail: t('daysLeftShort', { days: user.access_days_left }),
          days: user.access_days_left,
        });
      }

      farms.value.forEach((farm) => {
        const pending = pendingByFarm.value[farm.id] || [];
        const waiting = pending.filter((r) => !r.status || r.status === 'pending');
        if (waiting.length) {
          list.push({
            type: 'pond-request',
            tone: 'warning',
            title: farm.name,
            detail: t('directorPendingPondsAlert', { count: waiting.length }),
            actionLabel: t('fishery'),
            farmId: farm.id,
            itemId: waiting.length,
            action: () => {
              setDirectorFarmId(farm.id);
              router.push({ name: 'director-fishery-ponds' });
            },
          });
        }

        const items = warehouseByFarm.value[farm.id] || [];
        items.forEach((item) => {
          const qty = Number(item.quantity) || 0;
          const isConsumable = item.category === 'feed' || item.category === 'medicine';
          if (isConsumable && qty <= LOW_STOCK_QTY) {
            list.push({
              type: 'stock',
              tone: qty <= 0 ? 'danger' : 'warning',
              title: farm.name,
              detail: t('directorLowStockAlert', {
                name: item.name,
                qty: formatNumber(qty),
              }),
              actionLabel: t('warehouse'),
              farmId: farm.id,
              itemId: item.id,
              action: () => {
                setDirectorFarmId(farm.id);
                router.push({ name: 'director-warehouse' });
              },
            });
          }
        });
      });

      return list.slice(0, 8).map((alert) => ({
        ...alert,
        id: alertIdentity(alert),
      }));
    });

    const visibleAlerts = computed(() => {
      readTick.value;
      return filterUnreadAlerts(auth.state.user?.id, alerts.value);
    });

    function markAlertAsRead(alert) {
      markAlertRead(auth.state.user?.id, alert);
      readTick.value += 1;
    }

    function runAlertAction(alert) {
      markAlertAsRead(alert);
      if (typeof alert.action === 'function') alert.action();
    }

    async function loadFarmExtras(farmId) {
      const [finRes, whRes, reqRes] = await Promise.allSettled([
        window.axios.get(`/director/farms/${farmId}/finance/overview`),
        window.axios.get(`/director/farms/${farmId}/warehouse`),
        window.axios.get(`/director/farms/${farmId}/pond-create-requests`),
      ]);

      if (finRes.status === 'fulfilled') {
        financeByFarm.value = { ...financeByFarm.value, [farmId]: finRes.value.data };
      }
      if (whRes.status === 'fulfilled') {
        warehouseByFarm.value = { ...warehouseByFarm.value, [farmId]: whRes.value.data || [] };
      }
      if (reqRes.status === 'fulfilled') {
        const data = reqRes.value.data;
        pendingByFarm.value = {
          ...pendingByFarm.value,
          [farmId]: Array.isArray(data) ? data : (data?.data || []),
        };
      }
    }

    async function load() {
      loading.value = true;
      try {
        const [farmsRes, pondsRes] = await Promise.all([
          window.axios.get('/director/farms'),
          window.axios.get('/director/ponds'),
        ]);
        farms.value = farmsRes.data || [];
        ponds.value = pondsRes.data || [];

        if (farms.value.length === 1) {
          setDirectorFarmId(farms.value[0].id);
        } else if (farms.value.length && !getDirectorFarmId()) {
          setDirectorFarmId(farms.value[0].id);
        }

        await Promise.all(farms.value.map((f) => loadFarmExtras(f.id)));
      } catch (e) {
        farms.value = [];
        ponds.value = [];
      } finally {
        loading.value = false;
      }
    }

    function openFarm(farm) {
      setDirectorFarmId(farm.id);
      router.push({ name: 'director-farm-hub' });
    }

    function goFarmSection(name) {
      const farm = primaryFarm.value;
      if (!farm) return;
      setDirectorFarmId(farm.id);
      router.push({ name });
    }

    onMounted(load);

    return {
      farms,
      loading,
      primaryFarm,
      stats,
      visibleAlerts,
      markAlertAsRead,
      runAlertAction,
      openFarm,
      goFarmSection,
      formatMoney,
      formatNumber,
    };
  },
};
</script>
