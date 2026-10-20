<template>
  <div class="venger-ponds venger-readable">
    <OwnerVengerNav v-if="isOwnerView" />
    <div class="page-head">
      <div>
        <h1>{{ $t('vengerPondsTitle') }}</h1>
        <p>{{ isOwnerView ? $t('ownerVengerPondsHint') : $t('vengerPondsSimpleHint') }}</p>
      </div>
    </div>

    <div class="vp-stats">
      <button type="button" class="vp-stat vp-stat--open" :class="{ on: focus === 'open' }" @click="focus = 'open'">
        <span>{{ $t('vengerPondsTabOpen') }}</span>
        <strong>{{ stats.open }}</strong>
      </button>
      <button type="button" class="vp-stat vp-stat--done" :class="{ on: focus === 'final' }" @click="focus = 'final'">
        <span>{{ $t('vengerPondsTabDone') }}</span>
        <strong>{{ stats.final }}</strong>
      </button>
    </div>

    <div class="panel vp-search">
      <input
        v-model="searchQ"
        type="search"
        :placeholder="$t('vengerSearchPlaceholder')"
        @input="onSearchInput"
      />
    </div>

    <div v-if="loading" class="empty">{{ $t('loading') }}</div>
    <div v-else-if="error" class="panel empty">{{ error }}</div>

    <template v-else>
      <!-- Jarayonda -->
      <section v-show="focus === 'open' || focus === 'all'" class="vp-section">
        <header class="vp-section__head vp-section__head--open">
          <h2>{{ $t('vengerPondsTabOpen') }}</h2>
          <small>{{ stats.open }}</small>
        </header>
        <p class="vp-section__hint">{{ $t('vengerPondsOpenSectionHint') }}</p>

        <div class="vp-list">
          <article v-for="(r, idx) in openPonds" :key="r.id" class="vp-row" :class="`is-${r.workflow}`">
            <div class="vp-row__n">{{ idx + 1 }}</div>
            <div class="vp-row__body">
              <div class="vp-row__top">
                <strong>{{ pondTitle(r) }}</strong>
                <span class="vp-badge" :class="`vp-badge--${r.workflow}`">{{ workflowLabel(r) }}</span>
              </div>
              <p class="vp-row__client">
                {{ r.client?.name || '—' }}
                <span v-if="r.client?.phone" class="mono"> · {{ formatPhone(r.client.phone) }}</span>
                <span v-if="isOwnerView && r.staff?.name"> · {{ r.staff.name }}</span>
              </p>
              <p class="vp-row__meta">
                {{ fmt(r.area_ha, 2) }} ha
                <span>·</span>
                {{ formatDate(r.stocked_on) }}
                <template v-if="r.metrics?.fcr != null">
                  <span>·</span> FCR {{ fmt(r.metrics.fcr, 2) }}
                </template>
              </p>
            </div>
            <div class="vp-row__actions">
              <router-link
                v-if="!isOwnerView"
                class="btn btn-primary btn-sm"
                :to="{ name: 'venger-result-edit', params: { resultId: r.id } }"
              >
                {{ $t('vengerContinueResult') }}
              </router-link>
              <router-link class="btn btn-outline btn-sm" :to="resultRoute(r.id)">
                {{ $t('viewResult') }}
              </router-link>
            </div>
          </article>

          <div v-if="!openPonds.length" class="panel empty">{{ $t('vengerPondsOpenEmpty') }}</div>
        </div>
      </section>

      <!-- Yakunlangan — alohida arxiv -->
      <section v-show="focus === 'final' || focus === 'all'" class="vp-section vp-section--archive">
        <header class="vp-section__head vp-section__head--done">
          <h2>{{ $t('vengerPondsArchiveTitle') }}</h2>
          <small>{{ stats.final }}</small>
        </header>
        <p class="vp-section__hint">{{ $t('vengerPondsArchiveHint') }}</p>

        <div class="vp-list">
          <article v-for="(r, idx) in finalPonds" :key="r.id" class="vp-row is-final">
            <div class="vp-row__n">{{ idx + 1 }}</div>
            <div class="vp-row__body">
              <div class="vp-row__top">
                <strong>{{ pondTitle(r) }}</strong>
                <span class="vp-badge vp-badge--final">{{ $t('vengerPondConfirmed') }}</span>
              </div>
              <p class="vp-row__client">
                {{ r.client?.name || '—' }}
                <span v-if="r.client?.phone" class="mono"> · {{ formatPhone(r.client.phone) }}</span>
                <span v-if="isOwnerView && r.staff?.name"> · {{ r.staff.name }}</span>
              </p>
              <p class="vp-row__meta">
                {{ fmt(r.area_ha, 2) }} ha
                <span>·</span>
                {{ formatDate(r.stocked_on) }} → {{ formatDate(r.sold_on) }}
                <template v-if="r.metrics?.fcr != null">
                  <span>·</span> FCR {{ fmt(r.metrics.fcr, 2) }}
                </template>
                <template v-if="r.metrics?.sazan?.biomass_gain_kg != null">
                  <span>·</span> +{{ fmt(r.metrics.sazan.biomass_gain_kg) }} kg
                </template>
              </p>
            </div>
            <div class="vp-row__actions">
              <router-link class="btn btn-outline btn-sm" :to="resultRoute(r.id)">
                {{ $t('viewResult') }}
              </router-link>
            </div>
          </article>

          <div v-if="!finalPonds.length" class="panel empty">{{ $t('vengerPondsDoneEmpty') }}</div>
        </div>
      </section>
    </template>
  </div>
</template>

<script>
import { computed, onMounted, reactive, ref } from 'vue';
import OwnerVengerNav from '../../components/OwnerVengerNav.vue';
import auth from '../../auth';
import { formatPhoneDisplay } from '../../utils/phone';
import { t } from '../../i18n';

export default {
  components: { OwnerVengerNav },
  setup() {
    const loading = ref(true);
    const error = ref('');
    const focus = ref('open');
    const searchQ = ref('');
    const openPonds = ref([]);
    const finalPonds = ref([]);
    const stats = reactive({ open: 0, final: 0, total: 0 });
    const isOwnerView = computed(() => auth.isOwner());
    const apiBase = computed(() => (auth.isOwner() ? '/owner/venger' : '/venger'));
    let searchTimer = null;

    function resultRoute(resultId) {
      return auth.isOwner()
        ? { name: 'owner-venger-result', params: { resultId } }
        : { name: 'venger-result', params: { resultId } };
    }

    function fmt(v, decimals = 0) {
      const n = Number(v);
      if (!Number.isFinite(n)) return '0';
      return n.toLocaleString(undefined, {
        maximumFractionDigits: decimals,
        minimumFractionDigits: decimals > 0 ? Math.min(decimals, 2) : 0,
      });
    }

    function formatDate(value) {
      if (!value) return '—';
      try { return new Date(value).toLocaleDateString(); } catch (e) { return value; }
    }

    function formatPhone(phone) {
      return formatPhoneDisplay(phone || '');
    }

    function pondTitle(r) {
      return r.title || t('vengerPondUntitled');
    }

    function workflowLabel(r) {
      if (r.workflow === 'mid') return t('vengerPondInProgress');
      return t('vengerPondStarted');
    }

    async function load() {
      loading.value = true;
      error.value = '';
      try {
        const params = {};
        if (searchQ.value.trim()) params.q = searchQ.value.trim();
        const { data } = await window.axios.get(`${apiBase.value}/ponds`, { params });
        openPonds.value = data.open || data.ponds || [];
        finalPonds.value = data.final || [];
        Object.assign(stats, data.stats || {
          open: openPonds.value.length,
          final: finalPonds.value.length,
          total: openPonds.value.length + finalPonds.value.length,
        });
        if (focus.value === 'open' && !stats.open && stats.final) {
          focus.value = 'final';
        }
      } catch (e) {
        error.value = e.response?.data?.message || t('errorGeneric');
      } finally {
        loading.value = false;
      }
    }

    function onSearchInput() {
      clearTimeout(searchTimer);
      searchTimer = setTimeout(load, 280);
    }

    onMounted(load);

    return {
      loading, error, focus, searchQ, openPonds, finalPonds, stats, isOwnerView,
      fmt, formatDate, formatPhone, pondTitle, workflowLabel, resultRoute, onSearchInput,
    };
  },
};
</script>

<style scoped>
.vp-stats {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.65rem;
  margin-bottom: 0.75rem;
}
.vp-stat {
  background: #fff;
  border-radius: 14px;
  padding: 0.9rem 1rem;
  border: 2px solid transparent;
  display: grid;
  gap: 0.15rem;
  text-align: left;
  cursor: pointer;
  font: inherit;
}
.vp-stat span { font-size: 0.8rem; font-weight: 700; color: #5b7266; }
.vp-stat strong { font-size: 1.7rem; line-height: 1; }
.vp-stat--open { background: linear-gradient(140deg, #fff6e5, #fff); border-color: rgba(196, 122, 18, 0.18); }
.vp-stat--open strong { color: #8a5a00; }
.vp-stat--done { background: linear-gradient(140deg, #e8f5ee, #fff); border-color: rgba(13, 107, 63, 0.16); }
.vp-stat--done strong { color: #0d6b3f; }
.vp-stat.on { box-shadow: 0 0 0 2px rgba(20, 122, 77, 0.35); }

.vp-search {
  margin-bottom: 1rem;
  padding: 0.75rem;
}
.vp-search input {
  width: 100%;
  min-height: 46px;
  border: 1px solid rgba(20, 80, 50, 0.2);
  border-radius: 10px;
  padding: 0.55rem 0.75rem;
  font-size: 16px;
}

.vp-section { margin-bottom: 1.4rem; }
.vp-section__head {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 0.5rem;
  margin-bottom: 0.25rem;
}
.vp-section__head h2 {
  margin: 0;
  font-size: 1.1rem;
}
.vp-section__head small {
  font-size: 0.9rem;
  font-weight: 800;
  color: #5b7266;
}
.vp-section__head--open h2 { color: #8a5a00; }
.vp-section__head--done h2 { color: #0d6b3f; }
.vp-section__hint {
  margin: 0 0 0.65rem;
  font-size: 0.84rem;
  color: #5b7266;
  font-weight: 600;
}
.vp-section--archive {
  padding-top: 0.35rem;
  border-top: 1px dashed rgba(20, 80, 50, 0.18);
}

.vp-list { display: grid; gap: 0.55rem; }
.vp-row {
  display: grid;
  grid-template-columns: auto 1fr auto;
  gap: 0.7rem;
  align-items: center;
  background: #fff;
  border-radius: 14px;
  padding: 0.8rem 0.9rem;
  border: 1px solid rgba(20, 80, 50, 0.12);
}
.vp-row.is-started { border-left: 4px solid #c9a227; }
.vp-row.is-mid { border-left: 4px solid #e08a00; }
.vp-row.is-final { border-left: 4px solid #147a4d; }

.vp-row__n {
  width: 28px;
  height: 28px;
  border-radius: 8px;
  display: grid;
  place-items: center;
  font-size: 0.8rem;
  font-weight: 800;
  background: #eef5f0;
  color: #2a4434;
}
.vp-row.is-final .vp-row__n {
  background: #d9f3e6;
  color: #0d6b3f;
}

.vp-row__top {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.4rem 0.55rem;
  margin-bottom: 0.15rem;
}
.vp-row__top strong { font-size: 1rem; }
.vp-row__client {
  margin: 0;
  font-size: 0.9rem;
  font-weight: 650;
  color: #234033;
}
.vp-row__meta {
  margin: 0.2rem 0 0;
  font-size: 0.8rem;
  color: #5b7266;
  display: flex;
  flex-wrap: wrap;
  gap: 0.25rem 0.35rem;
}

.vp-badge {
  display: inline-flex;
  padding: 0.2rem 0.5rem;
  border-radius: 999px;
  font-size: 0.7rem;
  font-weight: 800;
  white-space: nowrap;
}
.vp-badge--started { background: #fff3d9; color: #8a5a00; }
.vp-badge--mid { background: #ffe4c2; color: #9a4d00; }
.vp-badge--final { background: #d9f3e6; color: #0d6b3f; }

.vp-row__actions {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  min-width: 120px;
}
.vp-row__actions .btn { width: 100%; justify-content: center; }

@media (max-width: 640px) {
  .vp-row {
    grid-template-columns: auto 1fr;
  }
  .vp-row__actions {
    grid-column: 1 / -1;
    flex-direction: row;
    min-width: 0;
  }
  .vp-row__actions .btn { flex: 1; }
}
</style>
