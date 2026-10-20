<template>
  <div class="owner-venger venger-readable">
    <OwnerVengerNav />

    <div class="page-head">
      <div>
        <h1>{{ $t('ownerVengerAnalyticsTitle') }}</h1>
        <p>{{ $t('ownerVengerAnalyticsHint') }}</p>
      </div>
    </div>

    <div v-if="loading" class="empty">{{ $t('loading') }}</div>
    <div v-else-if="error" class="panel empty">{{ error }}</div>

    <template v-else>
      <div class="ov-banner">
        <div>
          <strong>{{ $t('ownerVengerControlBadge') }}</strong>
          <p>{{ $t('ownerVengerControlText') }}</p>
        </div>
        <div class="ov-banner__meta">
          <span>{{ number(stats.clients_count) }} {{ $t('vengerClientsTitle').toLowerCase() }}</span>
          <span>{{ number(stats.ponds_total) }} {{ $t('vengerPondsTitle').toLowerCase() }}</span>
          <span>{{ number(analytics.totals?.regions_count) }} {{ $t('regions').toLowerCase() }}</span>
        </div>
      </div>

      <div class="ov-kpi">
        <div class="ov-kpi__card">
          <span>{{ $t('vengerClientsTitle') }}</span>
          <strong>{{ number(stats.clients_count) }}</strong>
          <em>{{ $t('ownerVengerKpiClients') }}</em>
        </div>
        <div class="ov-kpi__card ov-kpi__card--open">
          <span>{{ $t('vengerPondsTabOpen') }}</span>
          <strong>{{ number(stats.ponds_open) }}</strong>
          <em>{{ $t('ownerVengerPondsOpenHint') }}</em>
        </div>
        <div class="ov-kpi__card ov-kpi__card--done">
          <span>{{ $t('vengerPondsTabDone') }}</span>
          <strong>{{ number(stats.ponds_final) }}</strong>
          <em>{{ $t('ownerVengerPondsDoneHint') }}</em>
        </div>
        <div class="ov-kpi__card ov-kpi__card--rate">
          <span>{{ $t('ownerVengerCompletionRate') }}</span>
          <strong>{{ avg.completion_rate != null ? `${fmt(avg.completion_rate, 1)}%` : '—' }}</strong>
          <em>{{ $t('ownerVengerCompletionHint') }}</em>
        </div>
      </div>

      <div class="ov-kpi ov-kpi--avg">
        <div class="ov-kpi__card">
          <span>{{ $t('ownerVengerAvgFcr') }}</span>
          <strong>{{ avg.fcr != null ? fmt(avg.fcr, 2) : '—' }}</strong>
        </div>
        <div class="ov-kpi__card">
          <span>{{ $t('ownerVengerAvgYield') }}</span>
          <strong>{{ avg.yield_per_ha_kg != null ? `${fmt(avg.yield_per_ha_kg, 0)} kg/ha` : '—' }}</strong>
        </div>
        <div class="ov-kpi__card">
          <span>{{ $t('ownerVengerAvgArea') }}</span>
          <strong>{{ avg.area_ha != null ? `${fmt(avg.area_ha, 2)} ha` : '—' }}</strong>
        </div>
      </div>

      <div class="ov-charts">
        <section class="panel ov-chart">
          <div class="ov-chart__head">
            <h2>{{ $t('ownerVengerClientsByRegion') }}</h2>
            <small>{{ $t('ownerVengerClientsByRegionHint') }}</small>
          </div>
          <div v-if="!regionBars.length" class="empty">{{ $t('ownerVengerNoChartData') }}</div>
          <div v-else class="ov-hbar-list">
            <div v-for="row in regionBars" :key="row.region" class="ov-hbar">
              <div class="ov-hbar__label">
                <strong>{{ row.region }}</strong>
                <span>{{ number(row.count) }}</span>
              </div>
              <div class="ov-hbar__track">
                <div class="ov-hbar__fill ov-hbar__fill--region" :style="{ width: row.pct + '%' }" />
              </div>
            </div>
          </div>
        </section>

        <section class="panel ov-chart">
          <div class="ov-chart__head">
            <h2>{{ $t('ownerVengerResultsByYear') }}</h2>
            <small>{{ $t('ownerVengerResultsByYearHint') }}</small>
          </div>
          <div v-if="!yearBars.length" class="empty">{{ $t('ownerVengerNoChartData') }}</div>
          <div v-else class="ov-year-chart">
            <div v-for="y in yearBars" :key="y.year" class="ov-year">
              <div class="ov-year__bars">
                <div class="ov-year__col" :title="$t('vengerPondsTabDone')">
                  <div class="ov-year__fill ov-year__fill--final" :style="{ height: y.finalPct + '%' }" />
                </div>
                <div class="ov-year__col" :title="$t('vengerPondsTabOpen')">
                  <div class="ov-year__fill ov-year__fill--open" :style="{ height: y.openPct + '%' }" />
                </div>
              </div>
              <strong>{{ y.year }}</strong>
              <small>{{ number(y.total) }}</small>
            </div>
          </div>
          <div v-if="yearBars.length" class="ov-year-legend">
            <span><i class="dot final" /> {{ $t('vengerPondsTabDone') }}</span>
            <span><i class="dot open" /> {{ $t('vengerPondsTabOpen') }}</span>
          </div>
        </section>
      </div>

      <div class="ov-charts">
        <section class="panel ov-chart">
          <div class="ov-chart__head ov-chart__head--row">
            <div>
              <h2>{{ $t('ownerVengerTopAreas') }}</h2>
              <small>{{ geoMode === 'region' ? $t('ownerVengerTopAreasRegionHint') : $t('ownerVengerTopAreasDistrictHint') }}</small>
            </div>
            <div class="ov-geo-toggle" role="group" :aria-label="$t('ownerVengerTopAreas')">
              <button
                type="button"
                class="ov-geo-toggle__btn"
                :class="{ 'is-active': geoMode === 'region' }"
                @click="geoMode = 'region'"
              >
                {{ $t('ownerVengerGeoRegion') }}
              </button>
              <button
                type="button"
                class="ov-geo-toggle__btn"
                :class="{ 'is-active': geoMode === 'district' }"
                @click="geoMode = 'district'"
              >
                {{ $t('ownerVengerGeoDistrict') }}
              </button>
            </div>
          </div>
          <div v-if="!topAreaBars.length" class="empty">{{ $t('ownerVengerNoChartData') }}</div>
          <div v-else class="ov-hbar-list">
            <div
              v-for="row in topAreaBars"
              :key="geoMode === 'region' ? row.region : `${row.region}-${row.district}`"
              class="ov-hbar"
            >
              <div class="ov-hbar__label">
                <strong>{{ geoMode === 'region' ? row.region : row.district }}</strong>
                <span>{{ number(row.count) }}</span>
              </div>
              <em v-if="geoMode === 'district'" class="ov-hbar__sub">{{ row.region }}</em>
              <div class="ov-hbar__track">
                <div
                  class="ov-hbar__fill"
                  :class="geoMode === 'region' ? 'ov-hbar__fill--region' : 'ov-hbar__fill--district'"
                  :style="{ width: row.pct + '%' }"
                />
              </div>
            </div>
          </div>
        </section>

        <section class="panel ov-chart">
          <div class="ov-chart__head">
            <h2>{{ $t('ownerVengerFeedMix') }}</h2>
            <small>{{ $t('ownerVengerFeedMixHint') }}</small>
          </div>
          <div v-if="!feedMix.length" class="empty">{{ $t('ownerVengerNoChartData') }}</div>
          <div v-else class="ov-feed-mix">
            <div v-for="f in feedMix" :key="f.type" class="ov-feed-mix__item" :class="`is-${f.type}`">
              <strong>{{ f.label }}</strong>
              <span>{{ fmt(f.kg, 0) }} kg</span>
              <div class="ov-feed-mix__bar">
                <div :style="{ width: f.pct + '%' }" />
              </div>
              <em>{{ f.pct }}%</em>
            </div>
          </div>
          <div class="ov-totals">
            <div>
              <span>{{ $t('vengerFeedExtruder') }}</span>
              <strong>{{ fmt(analytics.totals?.extruder_kg, 0) }} kg</strong>
            </div>
            <div>
              <span>{{ $t('vengerFeedGranula') }}</span>
              <strong>{{ fmt(analytics.totals?.granula_kg, 0) }} kg</strong>
            </div>
            <div>
              <span>{{ $t('ownerVengerTotalFeed') }}</span>
              <strong>{{ fmt(analytics.totals?.feed_kg, 0) }} kg</strong>
            </div>
          </div>
        </section>
      </div>

      <div class="ov-grid">
        <section class="panel">
          <div class="page-head" style="margin: 0 0 0.75rem">
            <div>
              <h2 style="margin: 0">{{ $t('ownerVengerStaffWork') }}</h2>
              <p style="margin: 0.25rem 0 0">{{ $t('ownerVengerStaffWorkHint') }}</p>
            </div>
          </div>
          <div class="table-scroll">
            <table>
              <thead>
                <tr>
                  <th>{{ $t('name') }}</th>
                  <th>{{ $t('vengerClientsTitle') }}</th>
                  <th>{{ $t('vengerPondsTabOpen') }}</th>
                  <th>{{ $t('vengerPondsTabDone') }}</th>
                  <th>{{ $t('status') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="s in staff" :key="s.id">
                  <td>
                    <strong>{{ s.name }}</strong>
                    <div class="mono muted">{{ s.email }}</div>
                  </td>
                  <td>{{ number(s.clients_count) }}</td>
                  <td>{{ number(s.open_count) }}</td>
                  <td>{{ number(s.final_count) }}</td>
                  <td>
                    <span class="badge" :class="s.is_active ? 'active' : 'inactive'">
                      {{ s.is_active ? $t('active') : $t('inactive') }}
                    </span>
                  </td>
                </tr>
                <tr v-if="!staff.length">
                  <td colspan="5" class="empty">{{ $t('noVengerStaff') }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>

        <section class="panel">
          <div class="page-head" style="margin: 0 0 0.75rem">
            <div>
              <h2 style="margin: 0">{{ $t('ownerVengerActivity') }}</h2>
              <p style="margin: 0.25rem 0 0">{{ $t('ownerVengerActivityHint') }}</p>
            </div>
          </div>
          <div class="ov-activity">
            <article v-for="a in activity" :key="a.id" class="ov-activity__row">
              <div>
                <strong>{{ a.title }}</strong>
                <p>
                  {{ a.client?.name || '—' }}
                  <span v-if="a.staff"> · {{ a.staff.name }}</span>
                </p>
              </div>
              <div class="ov-activity__meta">
                <span class="badge" :class="a.status === 'final' ? 'active' : 'inactive'">
                  {{ a.status === 'final' ? $t('vengerPondConfirmed') : $t('vengerPondsTabOpen') }}
                </span>
                <router-link class="btn btn-outline btn-sm" :to="{ name: 'owner-venger-result', params: { resultId: a.id } }">
                  {{ $t('open') }}
                </router-link>
              </div>
            </article>
            <div v-if="!activity.length" class="empty">{{ $t('ownerVengerActivityEmpty') }}</div>
          </div>
        </section>
      </div>

      <div class="panel ov-login">
        <div>
          <h2>{{ $t('vengerHiddenLogin') }}</h2>
          <p>{{ $t('vengerStaffHint') }}</p>
          <strong class="mono">{{ loginUrl }}</strong>
        </div>
        <button class="btn btn-outline" type="button" @click="copyLink">
          {{ copied ? $t('copied') : $t('copy') }}
        </button>
      </div>
    </template>
  </div>
</template>

<script>
import { computed, onMounted, ref } from 'vue';
import OwnerVengerNav from '../../components/OwnerVengerNav.vue';
import { t } from '../../i18n';

function withPct(rows, key = 'count') {
  const max = Math.max(...rows.map((r) => Number(r[key] || 0)), 1);
  return rows.map((r) => ({
    ...r,
    pct: Math.max(Math.round((Number(r[key] || 0) / max) * 100), Number(r[key]) > 0 ? 6 : 0),
  }));
}

export default {
  components: { OwnerVengerNav },
  setup() {
    const loading = ref(true);
    const error = ref('');
    const staff = ref([]);
    const activity = ref([]);
    const analytics = ref({
      clients_by_region: [],
      clients_by_district: [],
      results_by_year: [],
      feed_types: [],
      averages: {},
      totals: {},
    });
    const stats = ref({
      staff_count: 0,
      active_count: 0,
      clients_count: 0,
      ponds_open: 0,
      ponds_final: 0,
      ponds_total: 0,
    });
    const loginUrl = ref('/venger/kirish');
    const copied = ref(false);
    const geoMode = ref('region');

    const avg = computed(() => analytics.value.averages || {});

    const regionBars = computed(() => withPct(analytics.value.clients_by_region || []));
    const topAreaBars = computed(() => {
      if (geoMode.value === 'district') {
        return withPct(analytics.value.clients_by_district || []);
      }
      return withPct(analytics.value.clients_by_region || []);
    });

    const yearBars = computed(() => {
      const rows = analytics.value.results_by_year || [];
      const max = Math.max(...rows.map((r) => Number(r.total || 0)), 1);
      return rows.map((r) => ({
        ...r,
        openPct: Math.max(Math.round((Number(r.open || 0) / max) * 100), r.open > 0 ? 8 : 0),
        finalPct: Math.max(Math.round((Number(r.final || 0) / max) * 100), r.final > 0 ? 8 : 0),
      }));
    });

    const feedMix = computed(() => {
      const rows = (analytics.value.feed_types || []).filter((f) => Number(f.kg || 0) > 0);
      const total = rows.reduce((s, r) => s + Number(r.kg || 0), 0) || 1;
      return rows.map((r) => ({
        ...r,
        pct: Math.round((Number(r.kg || 0) / total) * 100),
      }));
    });

    function number(value) {
      const n = Number(value || 0);
      return Number.isFinite(n) ? n.toLocaleString() : '0';
    }

    function fmt(v, decimals = 0) {
      const n = Number(v);
      if (!Number.isFinite(n)) return '0';
      return n.toLocaleString(undefined, {
        maximumFractionDigits: decimals,
        minimumFractionDigits: decimals > 0 ? Math.min(decimals, 2) : 0,
      });
    }

    async function load() {
      loading.value = true;
      error.value = '';
      try {
        const { data } = await window.axios.get('/owner/venger');
        staff.value = data.staff || [];
        activity.value = data.recent_activity || [];
        stats.value = data.stats || stats.value;
        analytics.value = data.analytics || analytics.value;
        loginUrl.value = data.login_url || `${window.location.origin}/venger/kirish`;
      } catch (e) {
        error.value = e.response?.data?.message || t('errorGeneric');
      } finally {
        loading.value = false;
      }
    }

    async function copyLink() {
      try {
        await navigator.clipboard.writeText(loginUrl.value);
        copied.value = true;
        setTimeout(() => { copied.value = false; }, 1800);
      } catch (e) { /* ignore */ }
    }

    onMounted(load);

    return {
      loading, error, staff, activity, stats, analytics, avg,
      geoMode, regionBars, topAreaBars, yearBars, feedMix,
      loginUrl, copied, number, fmt, copyLink,
    };
  },
};
</script>

<style scoped>
.ov-banner {
  display: flex;
  justify-content: space-between;
  gap: 1rem;
  align-items: center;
  margin-bottom: 0.9rem;
  padding: 1rem 1.1rem;
  border-radius: 16px;
  background: linear-gradient(135deg, #053825 0%, #0a6b40 55%, #0d8a52 100%);
  color: #fff;
  box-shadow: 0 12px 28px rgba(5, 56, 37, 0.22);
}
.ov-banner strong {
  display: block;
  font-size: 1.05rem;
  letter-spacing: 0.02em;
}
.ov-banner p {
  margin: 0.25rem 0 0;
  color: rgba(255, 255, 255, 0.92);
  font-size: 0.9rem;
}
.ov-banner__meta {
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem;
}
.ov-banner__meta span {
  padding: 0.3rem 0.65rem;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.16);
  font-size: 0.78rem;
  font-weight: 750;
  white-space: nowrap;
}

.ov-kpi {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 0.65rem;
  margin-bottom: 0.9rem;
}
.ov-kpi--avg { grid-template-columns: repeat(6, minmax(0, 1fr)); }
.ov-kpi__card {
  background: #fff;
  border-radius: 14px;
  padding: 0.85rem 0.95rem;
  border: 1px solid rgba(20, 80, 50, 0.1);
  display: grid;
  gap: 0.15rem;
}
.ov-kpi__card span { font-size: 0.74rem; font-weight: 700; color: #5b7266; }
.ov-kpi__card strong { font-size: 1.45rem; line-height: 1.1; letter-spacing: -0.02em; }
.ov-kpi__card em { font-style: normal; font-size: 0.72rem; color: #5b7266; font-weight: 600; }
.ov-kpi__card--open { background: linear-gradient(140deg, #fff6e5, #fff); }
.ov-kpi__card--open strong { color: #8a5a00; }
.ov-kpi__card--done { background: linear-gradient(140deg, #e8f5ee, #fff); }
.ov-kpi__card--done strong { color: #0d6b3f; }
.ov-kpi__card--rate { background: linear-gradient(140deg, #e8f0fb, #fff); }
.ov-kpi__card--rate strong { color: #0d4570; }

.ov-charts {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.85rem;
  margin-bottom: 0.9rem;
}
.ov-chart__head { margin-bottom: 0.75rem; }
.ov-chart__head--row {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: 0.65rem;
  align-items: flex-start;
}
.ov-chart__head h2 { margin: 0; font-size: 1.05rem; }
.ov-chart__head small { color: #5b7266; font-size: 0.82rem; }
.ov-geo-toggle {
  display: inline-flex;
  gap: 0.25rem;
  padding: 0.2rem;
  border-radius: 999px;
  background: #eef5f0;
}
.ov-geo-toggle__btn {
  border: 0;
  background: transparent;
  color: #3d5a4a;
  font: inherit;
  font-size: 0.82rem;
  font-weight: 650;
  padding: 0.35rem 0.75rem;
  border-radius: 999px;
  cursor: pointer;
}
.ov-geo-toggle__btn.is-active {
  background: #0a6b40;
  color: #fff;
}

.ov-hbar-list { display: grid; gap: 0.55rem; }
.ov-hbar__label {
  display: flex;
  justify-content: space-between;
  gap: 0.5rem;
  margin-bottom: 0.2rem;
  font-size: 0.88rem;
}
.ov-hbar__sub {
  display: block;
  margin: -0.1rem 0 0.2rem;
  font-style: normal;
  font-size: 0.72rem;
  color: #5b7266;
  font-weight: 600;
}
.ov-hbar__track {
  height: 12px;
  border-radius: 999px;
  background: #e8efea;
  overflow: hidden;
}
.ov-hbar__fill {
  height: 100%;
  border-radius: 999px;
  min-width: 4px;
}
.ov-hbar__fill--region { background: linear-gradient(90deg, #0d8a52, #1fd18a); }
.ov-hbar__fill--district { background: linear-gradient(90deg, #1a6fb5, #5eb0ef); }

.ov-year-chart {
  display: flex;
  align-items: flex-end;
  gap: 0.85rem;
  min-height: 180px;
  padding: 0.5rem 0.25rem 0;
  overflow-x: auto;
}
.ov-year {
  display: grid;
  justify-items: center;
  gap: 0.25rem;
  min-width: 52px;
}
.ov-year__bars {
  display: flex;
  align-items: flex-end;
  gap: 4px;
  height: 140px;
}
.ov-year__col {
  width: 16px;
  height: 100%;
  border-radius: 8px;
  background: #eef3ef;
  display: flex;
  align-items: flex-end;
  overflow: hidden;
}
.ov-year__fill {
  width: 100%;
  border-radius: 8px 8px 0 0;
  min-height: 4px;
}
.ov-year__fill--final { background: linear-gradient(180deg, #2fd887, #0d6b3f); }
.ov-year__fill--open { background: linear-gradient(180deg, #f0b429, #c47a12); }
.ov-year strong { font-size: 0.82rem; }
.ov-year small { color: #5b7266; font-weight: 700; }
.ov-year-legend {
  display: flex;
  gap: 1rem;
  margin-top: 0.65rem;
  font-size: 0.78rem;
  font-weight: 700;
  color: #5b7266;
}
.ov-year-legend .dot {
  display: inline-block;
  width: 10px;
  height: 10px;
  border-radius: 3px;
  margin-right: 0.3rem;
}
.ov-year-legend .dot.final { background: #0d6b3f; }
.ov-year-legend .dot.open { background: #c47a12; }

.ov-feed-mix { display: grid; gap: 0.75rem; margin-bottom: 0.85rem; }
.ov-feed-mix__item {
  padding: 0.7rem 0.8rem;
  border-radius: 12px;
  background: #f6faf7;
  border: 1px solid rgba(20, 80, 50, 0.08);
}
.ov-feed-mix__item strong { display: block; margin-bottom: 0.15rem; }
.ov-feed-mix__item > span { font-size: 1.2rem; font-weight: 800; }
.ov-feed-mix__bar {
  margin: 0.45rem 0 0.2rem;
  height: 10px;
  border-radius: 999px;
  background: #e8efea;
  overflow: hidden;
}
.ov-feed-mix__bar > div {
  height: 100%;
  border-radius: 999px;
}
.ov-feed-mix__item.is-extruder .ov-feed-mix__bar > div {
  background: linear-gradient(90deg, #f0b429, #c47a12);
}
.ov-feed-mix__item.is-granula .ov-feed-mix__bar > div {
  background: linear-gradient(90deg, #1a6fb5, #5eb0ef);
}
.ov-feed-mix__item em {
  font-style: normal;
  font-size: 0.78rem;
  font-weight: 700;
  color: #5b7266;
}

.ov-totals {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 0.5rem;
}
.ov-totals > div {
  padding: 0.55rem 0.6rem;
  border-radius: 10px;
  background: #fff;
  border: 1px solid rgba(20, 80, 50, 0.08);
}
.ov-totals span { display: block; font-size: 0.72rem; color: #5b7266; font-weight: 700; }
.ov-totals strong { font-size: 0.95rem; }

.ov-grid {
  display: grid;
  grid-template-columns: 1.1fr 0.9fr;
  gap: 0.85rem;
  margin-bottom: 0.9rem;
}
.muted { color: #5b7266; font-size: 0.78rem; margin-top: 0.15rem; }

.ov-activity { display: grid; gap: 0.55rem; }
.ov-activity__row {
  display: flex;
  justify-content: space-between;
  gap: 0.75rem;
  align-items: center;
  padding: 0.65rem 0.7rem;
  border-radius: 12px;
  background: #f4faf6;
  border: 1px solid rgba(20, 80, 50, 0.08);
}
.ov-activity__row p { margin: 0.15rem 0 0; font-size: 0.84rem; color: #5b7266; }
.ov-activity__meta { display: grid; gap: 0.35rem; justify-items: end; }

.ov-login {
  display: flex;
  justify-content: space-between;
  gap: 1rem;
  align-items: center;
}
.ov-login h2 { margin: 0 0 0.25rem; font-size: 1.05rem; }
.ov-login p { margin: 0 0 0.35rem; color: #5b7266; }

@media (max-width: 1100px) {
  .ov-kpi--avg { grid-template-columns: repeat(3, minmax(0, 1fr)); }
}
@media (max-width: 980px) {
  .ov-kpi { grid-template-columns: 1fr 1fr; }
  .ov-charts, .ov-grid { grid-template-columns: 1fr; }
}
@media (max-width: 560px) {
  .ov-kpi, .ov-kpi--avg, .ov-totals { grid-template-columns: 1fr; }
  .ov-banner { flex-direction: column; align-items: flex-start; }
  .ov-login { flex-direction: column; align-items: flex-start; }
}
</style>
