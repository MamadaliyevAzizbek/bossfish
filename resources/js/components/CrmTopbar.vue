<template>
  <header class="crm-topbar">
    <div class="crm-topbar__left">
      <span class="crm-topbar__title">{{ title }}</span>
      <span v-if="subtitle" class="crm-topbar__subtitle">{{ subtitle }}</span>
    </div>

    <div class="crm-topbar__right">
      <div
        v-if="accessInfo"
        class="crm-topbar-access"
        :class="`crm-topbar-access--${accessInfo.tone}`"
        :title="accessInfo.untilLabel || undefined"
      >
        <span class="crm-topbar-access__days">{{ accessInfo.daysLabel }}</span>
        <span v-if="accessInfo.untilLabel" class="crm-topbar-access__until">{{ accessInfo.untilLabel }}</span>
      </div>

      <div v-if="showAlerts" class="crm-topbar-alerts" ref="alertsRoot">
        <button
          type="button"
          class="crm-topbar-bell"
          :aria-expanded="alertsOpen ? 'true' : 'false'"
          :aria-label="$t('directorAttentionTitle')"
          @click="toggleAlerts"
        >
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
            <path d="M13.73 21a2 2 0 0 1-3.46 0" />
          </svg>
          <span v-if="unreadAlerts.length" class="crm-topbar-bell__badge">{{ unreadAlerts.length > 9 ? '9+' : unreadAlerts.length }}</span>
        </button>

        <Transition name="crm-topbar-pop">
          <div v-if="alertsOpen" class="crm-topbar-alerts__panel" role="dialog" :aria-label="$t('directorAttentionTitle')">
            <div class="crm-topbar-alerts__head">
              <strong>{{ $t('directorAttentionTitle') }}</strong>
              <button
                v-if="unreadAlerts.length"
                type="button"
                class="crm-topbar-alerts__mark-all"
                @click="markAllRead"
              >
                {{ $t('directorAlertsMarkAllRead') }}
              </button>
            </div>
            <ul v-if="unreadAlerts.length" class="crm-topbar-alerts__list">
              <li
                v-for="alert in unreadAlerts"
                :key="alert.id"
                :class="`crm-topbar-alert crm-topbar-alert--${alert.tone}`"
              >
                <div class="crm-topbar-alert__body">
                  <strong>{{ alert.title }}</strong>
                  <span>{{ alert.detail }}</span>
                </div>
                <div class="crm-topbar-alert__actions">
                  <button
                    type="button"
                    class="btn btn-outline btn-sm"
                    @click="markRead(alert)"
                  >
                    {{ $t('directorAlertMarkRead') }}
                  </button>
                  <button
                    v-if="alert.route"
                    type="button"
                    class="btn btn-primary btn-sm"
                    @click="goAlert(alert)"
                  >
                    {{ alert.actionLabel || $t('alertReadDetails') }}
                  </button>
                </div>
              </li>
            </ul>
            <div v-else class="crm-topbar-alerts__empty">{{ $t('directorNoAlerts') }}</div>
          </div>
        </Transition>
      </div>

      <LanguageSwitcher embedded />
    </div>
  </header>
</template>

<script>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import auth from '../auth';
import { t } from '../i18n';
import { formatUzLongDate } from '../utils/date';
import {
  alertIdentity,
  filterUnreadAlerts,
  markAlertRead,
} from '../utils/directorAlerts';
import { setDirectorFarmId } from '../utils/directorFarm';
import { formatNumber } from '../utils/number';
import LanguageSwitcher from './LanguageSwitcher.vue';

const LOW_STOCK_QTY = 10;

export default {
  components: { LanguageSwitcher },
  props: {
    title: { type: String, default: '' },
    subtitle: { type: String, default: '' },
    showDirectorTools: { type: Boolean, default: false },
  },
  setup(props) {
    const router = useRouter();
    const alertsOpen = ref(false);
    const alertsRoot = ref(null);
    const alerts = ref([]);
    const loadingAlerts = ref(false);
    const readTick = ref(0);
    const userId = computed(() => auth.state.user?.id);

    const accessInfo = computed(() => {
      if (!props.showDirectorTools) return null;
      const u = auth.state.user;
      if (!u || auth.isOwner()) return null;
      const days = u.access_days_left;
      if (days == null && !u.access_until && !u.access_expires_at) return null;
      let tone = 'ok';
      if (typeof days === 'number') {
        if (days <= 3) tone = 'danger';
        else if (days <= 7) tone = 'warning';
      }
      const until = u.access_until || u.access_expires_at;
      return {
        tone,
        daysLabel: typeof days === 'number' ? t('daysLeftShort', { days }) : '',
        untilLabel: until ? formatUzLongDate(until) : '',
      };
    });

    const showAlerts = computed(() => props.showDirectorTools);

    const unreadAlerts = computed(() => {
      readTick.value;
      return filterUnreadAlerts(userId.value, alerts.value);
    });

    async function loadAlerts() {
      if (!props.showDirectorTools || !auth.isDirector()) {
        alerts.value = [];
        return;
      }
      loadingAlerts.value = true;
      const list = [];
      const u = auth.state.user;
      if (u && typeof u.access_days_left === 'number' && u.access_days_left <= 7) {
        list.push({
          type: 'access',
          tone: u.access_days_left <= 3 ? 'danger' : 'warning',
          title: t('accessWarningTitle'),
          detail: t('daysLeftShort', { days: u.access_days_left }),
          days: u.access_days_left,
        });
      }

      try {
        const { data: farms } = await window.axios.get('/director/farms');
        const farmList = Array.isArray(farms) ? farms : [];
        const [, waterRes, workRes] = await Promise.all([
          Promise.all(farmList.map(async (farm) => {
            const [whRes, reqRes] = await Promise.allSettled([
              window.axios.get(`/director/farms/${farm.id}/warehouse`),
              window.axios.get(`/director/farms/${farm.id}/pond-create-requests`),
            ]);
            if (whRes.status === 'fulfilled') {
              (whRes.value.data || []).forEach((item) => {
                const qty = Number(item.quantity) || 0;
                const isConsumable = item.category === 'feed' || item.category === 'medicine';
                if (isConsumable && qty <= LOW_STOCK_QTY) {
                  list.push({
                    type: 'stock',
                    tone: qty <= 0 ? 'danger' : 'warning',
                    title: farm.name,
                    detail: t('directorLowStockAlert', { name: item.name, qty: formatNumber(qty) }),
                    actionLabel: t('warehouse'),
                    farmId: farm.id,
                    itemId: item.id,
                    route: { name: 'director-warehouse' },
                  });
                }
              });
            }
            if (reqRes.status === 'fulfilled') {
              const raw = reqRes.value.data;
              const pending = (Array.isArray(raw) ? raw : (raw?.data || []))
                .filter((r) => !r.status || r.status === 'pending');
              if (pending.length) {
                list.push({
                  type: 'pond-request',
                  tone: 'warning',
                  title: farm.name,
                  detail: t('directorPendingPondsAlert', { count: pending.length }),
                  actionLabel: t('fishery'),
                  farmId: farm.id,
                  itemId: pending.length,
                  route: { name: 'director-fishery-ponds' },
                });
              }
            }
          })),
          window.axios.get('/director/water-quality-alerts').catch(() => null),
          window.axios.get('/director/work-reminders').catch(() => null),
        ]);

        const waterAlerts = waterRes?.data?.alerts || [];
        waterAlerts.forEach((item) => {
          const pondLabel = item.pondName || item.farmName || '';
          list.push({
            type: 'water-quality',
            tone: item.tone || (item.level === 'danger' ? 'danger' : 'warning'),
            title: t('waterAlertShortTitle'),
            detail: pondLabel
              ? t('waterAlertShortDetailPond', { pond: pondLabel })
              : t('waterAlertShortDetail'),
            actionLabel: t('alertReadDetails'),
            farmId: item.farmId,
            itemId: item.reportId || item.itemId,
            route: item.pondId && item.reportId
              ? {
                name: 'director-water-alert',
                params: { pondId: item.pondId, reportId: item.reportId },
              }
              : { name: 'director-fishery-ponds' },
          });
        });

        const workAlerts = workRes?.data?.alerts || [];
        workAlerts.forEach((item) => {
          const routeName = item.route || 'director-fishery-ponds';
          const actionLabel = item.type === 'feed-reminder'
            ? t('dailyReport')
            : t('mainReport');
          list.push({
            type: item.type,
            tone: item.tone || 'warning',
            title: item.pondName || item.farmName || t('workReminderTitle'),
            detail: t(item.message_key, item.message_params || {}),
            actionLabel,
            farmId: item.farmId,
            itemId: item.itemId,
            route: item.pondId
              ? { name: routeName, params: { pondId: item.pondId } }
              : { name: 'director-fishery-ponds' },
          });
        });
      } catch (e) {
        /* ignore */
      }

      alerts.value = list.slice(0, 15).map((alert) => ({
        ...alert,
        id: alertIdentity(alert),
      }));
      loadingAlerts.value = false;
    }

    function markRead(alert) {
      markAlertRead(userId.value, alert);
      readTick.value += 1;
    }

    function markAllRead() {
      unreadAlerts.value.forEach((alert) => markAlertRead(userId.value, alert));
      readTick.value += 1;
    }

    function toggleAlerts() {
      alertsOpen.value = !alertsOpen.value;
      if (alertsOpen.value) loadAlerts();
    }

    function goAlert(alert) {
      markRead(alert);
      alertsOpen.value = false;
      if (alert.farmId) setDirectorFarmId(alert.farmId);
      if (alert.route) router.push(alert.route);
    }

    function onDocClick(event) {
      if (alertsRoot.value && !alertsRoot.value.contains(event.target)) {
        alertsOpen.value = false;
      }
    }

    function onKeydown(event) {
      if (event.key === 'Escape') alertsOpen.value = false;
    }

    watch(() => props.showDirectorTools, (on) => {
      if (on) loadAlerts();
      else alerts.value = [];
    }, { immediate: true });

    onMounted(() => {
      document.addEventListener('click', onDocClick);
      document.addEventListener('keydown', onKeydown);
    });

    onBeforeUnmount(() => {
      document.removeEventListener('click', onDocClick);
      document.removeEventListener('keydown', onKeydown);
    });

    return {
      accessInfo,
      showAlerts,
      unreadAlerts,
      alertsOpen,
      alertsRoot,
      loadingAlerts,
      toggleAlerts,
      goAlert,
      markRead,
      markAllRead,
    };
  },
};
</script>
