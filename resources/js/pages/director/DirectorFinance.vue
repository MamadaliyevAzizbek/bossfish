<template>
  <DirectorShell>
    <div v-if="loading" class="panel empty">{{ $t('loading') }}</div>

    <template v-else>
      <section class="panel finance-pond-context">
        <div class="finance-pond-context__head">
          <h2>{{ $t('financePondContextTitle') }}</h2>
          <p>{{ $t('financePondContextHint') }}</p>
        </div>

        <div class="finance-pond-chips" role="radiogroup" :aria-label="$t('financePondContextTitle')">
          <button
            type="button"
            role="radio"
            class="finance-pond-chip"
            :class="{ 'finance-pond-chip--active': !filterPondId }"
            :aria-checked="!filterPondId"
            @click="selectPondContext('')"
          >
            <span v-if="!filterPondId" class="finance-pond-chip__badge">✓ {{ $t('financePondChipSelected') }}</span>
            <strong>{{ $t('financePondContextAll') }}</strong>
            <small>{{ $t('financePondContextAllHint') }}</small>
          </button>

          <button
            v-for="pond in ponds"
            :key="pond.id"
            type="button"
            role="radio"
            class="finance-pond-chip"
            :class="{
              'finance-pond-chip--active': filterPondId === String(pond.id),
              'finance-pond-chip--closed': pond.is_closed || pond.closed_at,
            }"
            :aria-checked="filterPondId === String(pond.id)"
            @click="selectPondContext(String(pond.id))"
          >
            <span
              v-if="filterPondId === String(pond.id)"
              class="finance-pond-chip__badge"
            >✓ {{ $t('financePondChipSelected') }}</span>
            <strong>{{ pond.name }}</strong>
            <small>
              {{ pond.is_closed || pond.closed_at ? $t('pondClosedBadge') : $t('financePondContextOpenHint') }}
            </small>
          </button>
        </div>

        <p class="finance-pond-context__target" :class="{ 'finance-pond-context__target--warn': !canWriteExpense }">
          <template v-if="canWriteExpense">
            {{ $t('financeExpenseTarget', { target: expenseTargetLabel }) }}
          </template>
          <template v-else>
            {{ $t('financeClosedPondNoWrite') }}
          </template>
        </p>
      </section>

      <section class="panel finance-hero-entry">
        <div class="finance-hero-entry__head">
          <div>
            <h2>{{ $t('financeSimpleEntryTitle') }}</h2>
            <p>{{ canWriteExpense ? $t('financeSimpleEntryHintLocked') : $t('financeClosedPondNoWrite') }}</p>
          </div>
          <div class="finance-hero-entry__mini">
            <span>{{ filterPondId ? $t('financePondExpenses') : $t('financeTotalExpenses') }}</span>
            <strong>{{ formatMoney(overview.total_expenses) }}</strong>
          </div>
        </div>

        <FinanceQuickEntry
          v-if="canWriteExpense"
          :farm-id="farmId"
          :category-tabs="categoryTabs"
          :selected-category="entryCategory"
          :custom-sections="customSectionsMap"
          :ponds="ponds"
          :locked-scope-mode="lockedScopeMode"
          :locked-pond-id="lockedPondId"
          compact
          fast
          @select-category="selectEntryCategory"
          @saved="onEntrySaved"
        />
      </section>

      <section v-if="!filterPondId && pondFinanceRows.length" class="panel finance-pond-board">
        <div class="finance-pond-board__head">
          <h2>{{ $t('financeByPondTitle') }}</h2>
          <p>{{ $t('financeByPondHintShort') }}</p>
        </div>
        <div class="finance-pond-board__grid">
          <button
            v-for="row in pondFinanceRows"
            :key="row.pond_id"
            type="button"
            class="finance-pond-card"
            :class="row.profit >= 0 ? 'finance-pond-card--up' : 'finance-pond-card--down'"
            @click="selectPondContext(String(row.pond_id))"
          >
            <strong>{{ row.name }}</strong>
            <span>{{ $t('financeTotalExpenses') }}: {{ formatMoney(row.total_expenses) }}</span>
            <span>{{ $t('financeTotalSales') }}: {{ formatMoney(row.total_sales) }}</span>
            <em>{{ row.profit >= 0 ? $t('financeProfit') : $t('ownerDirectorInLoss') }}: {{ formatMoney(Math.abs(row.profit)) }}</em>
          </button>
        </div>
      </section>

      <!-- Diagrammalar -->
      <section class="finance-visuals">
        <div class="panel finance-visual-card">
          <div class="finance-visual-card__head">
            <h3>{{ $t('financeChartMixTitle') }}</h3>
          </div>
          <div class="finance-donut-wrap">
            <svg class="finance-donut" viewBox="0 0 120 120" role="img" :aria-label="$t('financeChartMixTitle')">
              <circle cx="60" cy="60" r="40" fill="none" stroke="#e8eee9" stroke-width="16" />
              <circle
                v-for="(seg, i) in donutSegments"
                :key="`donut-${i}`"
                cx="60"
                cy="60"
                r="40"
                fill="none"
                :stroke="seg.color"
                stroke-width="16"
                stroke-linecap="butt"
                :stroke-dasharray="seg.dash"
                :stroke-dashoffset="seg.offset"
                transform="rotate(-90 60 60)"
              />
              <text x="60" y="56" text-anchor="middle" font-size="11" font-weight="800" fill="#0b1f16">
                {{ chartExpenseShare }}%
              </text>
              <text x="60" y="70" text-anchor="middle" font-size="7.5" font-weight="650" fill="#5a7164">
                {{ $t('financeChartExpenseShare') }}
              </text>
            </svg>
            <ul class="finance-donut-legend">
              <li v-for="row in expenseBreakdown" :key="row.key">
                <i :style="{ background: row.color }" />
                <span>{{ row.label }}</span>
                <strong>{{ formatMoney(row.total) }}</strong>
                <em>{{ row.percent }}%</em>
              </li>
            </ul>
          </div>
        </div>

        <div class="panel finance-visual-card">
          <div class="finance-visual-card__head">
            <h3>{{ $t('financeChartBalanceTitle') }}</h3>
          </div>
          <div class="finance-bars">
            <div class="finance-bar">
              <strong>{{ formatMoney(overview.total_expenses) }}</strong>
              <div class="finance-bar__track">
                <div class="finance-bar__fill finance-bar__fill--expense" :style="{ height: expenseBarH }" />
              </div>
              <span>{{ $t('financeTotalExpenses') }}</span>
            </div>
            <div class="finance-bar">
              <strong>{{ formatMoney(overview.total_sales) }}</strong>
              <div class="finance-bar__track">
                <div class="finance-bar__fill finance-bar__fill--sale" :style="{ height: saleBarH }" />
              </div>
              <span>{{ $t('financeTotalSales') }}</span>
            </div>
            <div class="finance-bar">
              <strong>{{ formatMoney(overview.profit) }}</strong>
              <div class="finance-bar__track">
                <div
                  class="finance-bar__fill"
                  :class="overview.profit >= 0 ? 'finance-bar__fill--profit' : 'finance-bar__fill--loss'"
                  :style="{ height: profitBarH }"
                />
              </div>
              <span>{{ $t('financeProfit') }}</span>
            </div>
          </div>
          <p class="finance-visual-note">{{ balanceStory }}</p>
        </div>
      </section>

      <!-- Hisobotlar -->
      <section class="panel finance-simple-reports">
        <div class="finance-simple-step">
          <h2>{{ $t('financeSimpleReportsTitle') }}</h2>
          <button class="btn btn-outline btn-sm finance-simple-add-section" type="button" @click="openSectionModal">
            + {{ $t('financeAddSection') }}
          </button>
        </div>

        <div class="finance-simple-report-grid">
          <router-link
            v-for="tab in categoryTabs"
            :key="`report-${tab.routeKey}`"
            class="finance-simple-report-card"
            :class="[
              tab.cardClass,
              { 'finance-simple-report-card--warehouse': tab.warehouse },
            ]"
            :to="{ name: 'director-finance-report', params: { category: tab.routeKey } }"
          >
            <div class="finance-simple-report-card__top">
              <strong>{{ tab.label }}</strong>
              <button
                v-if="tab.section"
                class="finance-section-delete"
                type="button"
                :title="$t('delete')"
                @click.prevent.stop="removeSection(tab.section)"
              >
                ×
              </button>
            </div>
            <span class="finance-simple-report-card__sum">{{ formatMoney(tab.total) }}</span>
            <div class="finance-simple-report-card__bar" aria-hidden="true">
              <i :style="{ width: `${tab.share}%`, background: tab.color }" />
            </div>
            <small v-if="tab.warehouse">{{ $t('financeSimpleWarehouseBadge') }}</small>
          </router-link>
        </div>
      </section>
    </template>

    <div v-if="sectionModal" class="modal-backdrop" @click.self="sectionModal = false">
      <div class="modal">
        <h2 class="modal-title">{{ $t('financeAddSection') }}</h2>
        <form class="form-grid one-col" @submit.prevent="createSection">
          <div class="field">
            <label>{{ $t('financeSectionName') }}</label>
            <input
              v-model.trim="sectionName"
              type="text"
              maxlength="120"
              required
              :placeholder="$t('financeSectionNamePlaceholder')"
            />
            <small class="field-hint">{{ $t('financeSectionNameHint') }}</small>
          </div>
          <p v-if="sectionError" class="error">{{ sectionError }}</p>
          <div class="actions">
            <button class="btn btn-primary" type="submit" :disabled="sectionSaving">{{ $t('save') }}</button>
            <button class="btn btn-outline" type="button" @click="sectionModal = false">{{ $t('cancel') }}</button>
          </div>
        </form>
      </div>
    </div>
  </DirectorShell>
</template>

<script>
import { computed, onMounted, ref, watch } from 'vue';
import { setDirectorFarmId, useDirectorFarmId } from '../../utils/directorFarm';
import { useRoute, useRouter } from 'vue-router';
import DirectorShell from '../../components/DirectorShell.vue';
import FinanceQuickEntry from '../../components/FinanceQuickEntry.vue';
import { showSaved } from '../../flash';
import {
  FINANCE_CATEGORIES,
  financeCategoryHint,
  financeCategoryIcon,
  financeCategoryLabel as labelCategory,
  isValidFinanceCategory,
  isWarehouseFinanceCategory,
  toRouteCategory,
} from '../../utils/finance';
import { formatMoney } from '../../utils/number';
import { t } from '../../i18n';

export default {
  components: { DirectorShell, FinanceQuickEntry },
  setup() {
    const route = useRoute();
    const router = useRouter();
    const farmId = useDirectorFarmId();
    const loading = ref(true);
    const farm = ref(null);
    const filterPondId = ref('');
    const ponds = ref([]);
    const entryCategory = ref('feed');
    const overview = ref({
      categories: {},
      custom_sections: [],
      total_expenses: 0,
      total_sales: 0,
      profit: 0,
      by_pond: [],
      ponds: [],
    });
    const sectionModal = ref(false);
    const sectionName = ref('');
    const sectionError = ref('');
    const sectionSaving = ref(false);

    const pondFinanceRows = computed(() => overview.value.by_pond || []);

    const selectedPond = computed(() => {
      if (!filterPondId.value) return null;
      return (ponds.value || []).find((p) => String(p.id) === String(filterPondId.value)) || null;
    });

    const selectedPondClosed = computed(() => {
      const pond = selectedPond.value;
      return !!(pond && (pond.is_closed || pond.closed_at));
    });

    const canWriteExpense = computed(() => !selectedPondClosed.value);

    const lockedScopeMode = computed(() => (filterPondId.value ? 'pond' : 'farm'));

    const lockedPondId = computed(() => (filterPondId.value ? Number(filterPondId.value) : null));

    const expenseTargetLabel = computed(() => {
      if (!filterPondId.value) return t('financeExpenseTargetFarm');
      return selectedPond.value?.name || t('financeExpenseTargetFarm');
    });

    const customSectionsMap = computed(() => Object.fromEntries(
      (overview.value.custom_sections || []).map((section) => [
        String(section.id),
        { name: section.name },
      ]),
    ));

    const CATEGORY_COLORS = {
      feed: '#2e7d32',
      medicine: '#c62828',
      salary: '#00838f',
      other: '#ef6c00',
      custom: '#6a1b9a',
    };

    const categoryTabs = computed(() => {
      const total = Math.max(Number(overview.value.total_expenses) || 0, 0.0001);

      const fixed = FINANCE_CATEGORIES.map((category) => {
        const amount = Number(overview.value.categories?.[category] || 0);
        return {
          routeKey: category,
          label: labelCategory(category, t),
          icon: financeCategoryIcon(category),
          total: amount,
          share: Math.round((amount / total) * 100),
          color: CATEGORY_COLORS[category],
          hint: financeCategoryHint(category, t),
          warehouse: isWarehouseFinanceCategory(category),
          cardClass: `finance-simple-report-card--${category}`,
          section: null,
        };
      });

      const custom = (overview.value.custom_sections || []).map((section) => {
        const amount = Number(section.total || 0);
        return {
          routeKey: toRouteCategory(section.category),
          label: section.name,
          icon: financeCategoryIcon(section.category),
          total: amount,
          share: Math.round((amount / total) * 100),
          color: CATEGORY_COLORS.custom,
          hint: t('financeCatCustomHint'),
          warehouse: false,
          cardClass: 'finance-simple-report-card--custom',
          section,
        };
      });

      return [...fixed, ...custom];
    });

    const expenseBreakdown = computed(() => (
      categoryTabs.value
        .filter((row) => row.total > 0)
        .map((row) => ({
          key: row.routeKey,
          label: row.label,
          total: row.total,
          percent: row.share,
          color: row.color,
        }))
    ));

    const donutSegments = computed(() => {
      const circ = 2 * Math.PI * 40;
      const total = Math.max(Number(overview.value.total_expenses) || 0, 0.0001);
      let cursor = 0;
      const rows = expenseBreakdown.value.length
        ? expenseBreakdown.value
        : [{ total: 1, color: '#e8eee9' }];

      return rows.map((row) => {
        const len = (Number(row.total) / total) * circ;
        const seg = {
          color: row.color,
          dash: `${len} ${circ - len}`,
          offset: -cursor,
        };
        cursor += len;
        return seg;
      });
    });

    const chartExpenseShare = computed(() => {
      const expense = Number(overview.value.total_expenses) || 0;
      const sale = Number(overview.value.total_sales) || 0;
      const base = expense + sale;
      if (base <= 0) return 0;
      return Math.round((expense / base) * 100);
    });

    const maxMoney = computed(() => Math.max(
      Number(overview.value.total_expenses) || 0,
      Number(overview.value.total_sales) || 0,
      Math.abs(Number(overview.value.profit) || 0),
      1,
    ));

    function barH(value) {
      const n = Math.abs(Number(value) || 0);
      return `${Math.max((n / maxMoney.value) * 100, n > 0 ? 8 : 2)}%`;
    }

    const expenseBarH = computed(() => barH(overview.value.total_expenses));
    const saleBarH = computed(() => barH(overview.value.total_sales));
    const profitBarH = computed(() => barH(overview.value.profit));

    const balanceStory = computed(() => {
      const profit = Number(overview.value.profit) || 0;
      if (profit > 0) {
        return t('financeBalanceStoryProfit', { amount: formatMoney(profit) });
      }
      if (profit < 0) {
        return t('financeBalanceStoryLoss', { amount: formatMoney(Math.abs(profit)) });
      }
      return t('financeBalanceStoryZero');
    });

    function selectEntryCategory(routeKey) {
      entryCategory.value = routeKey;
      router.replace({
        name: 'director-finance',
        query: { entry: routeKey },
      });
    }

    async function load() {
      loading.value = true;
      try {
        if (!farmId.value) {
          farm.value = null;
          overview.value = null;
          ponds.value = [];
          return;
        }
        const params = {};
        if (filterPondId.value) params.pond_id = filterPondId.value;
        const [farmRes, overviewRes] = await Promise.all([
          window.axios.get(`/director/farms/${farmId.value}`),
          window.axios.get(`/director/farms/${farmId.value}/finance/overview`, { params }),
        ]);
        farm.value = farmRes.data;
        overview.value = overviewRes.data;
        ponds.value = overviewRes.data.ponds || [];

        const queryEntry = route.query.entry;
        const sectionIds = (overview.value.custom_sections || []).map((section) => section.id);
        if (queryEntry && isValidFinanceCategory(String(queryEntry), sectionIds)) {
          entryCategory.value = String(queryEntry);
        } else if (!entryCategory.value || !isValidFinanceCategory(entryCategory.value, sectionIds)) {
          entryCategory.value = 'feed';
        }
      } catch (e) {
        farm.value = null;
        overview.value = null;
        ponds.value = [];
      } finally {
        loading.value = false;
      }
    }

    async function selectPondContext(pondId) {
      filterPondId.value = pondId || '';
      await load();
    }

    async function onEntrySaved() {
      const params = {};
      if (filterPondId.value) params.pond_id = filterPondId.value;
      const overviewRes = await window.axios.get(`/director/farms/${farmId.value}/finance/overview`, { params });
      overview.value = overviewRes.data;
      ponds.value = overviewRes.data.ponds || [];
    }

    function openSectionModal() {
      sectionName.value = '';
      sectionError.value = '';
      sectionModal.value = true;
    }

    async function createSection() {
      sectionError.value = '';
      sectionSaving.value = true;
      try {
        const { data } = await window.axios.post(`/director/farms/${farmId.value}/finance/sections`, {
          name: sectionName.value,
        });
        sectionModal.value = false;
        showSaved(t('saved'));
        await load();
        const routeKey = toRouteCategory(data.category);
        entryCategory.value = routeKey;
        router.replace({
          name: 'director-finance',
          query: { entry: routeKey },
        });
      } catch (e) {
        const errors = e.response?.data?.errors;
        sectionError.value = errors
          ? Object.values(errors).flat().join(' ')
          : (e.response?.data?.message || t('noData'));
      } finally {
        sectionSaving.value = false;
      }
    }

    async function removeSection(section) {
      if (!confirm(t('financeConfirmDeleteSection', { name: section.name }))) return;
      try {
        await window.axios.delete(`/director/farms/${farmId.value}/finance/sections/${section.id}`);
        showSaved(t('saved'));
        if (entryCategory.value === toRouteCategory(section.category)) {
          entryCategory.value = 'feed';
        }
        await load();
      } catch (e) {
        alert(e.response?.data?.message || t('noData'));
      }
    }

    watch(() => route.query.entry, (value) => {
      if (!value || loading.value) return;
      const sectionIds = (overview.value.custom_sections || []).map((section) => section.id);
      if (isValidFinanceCategory(String(value), sectionIds)) {
        entryCategory.value = String(value);
      }
    });

    onMounted(load);

    return {
      farmId,
      farm,
      loading,
      overview,
      ponds,
      filterPondId,
      pondFinanceRows,
      canWriteExpense,
      lockedScopeMode,
      lockedPondId,
      expenseTargetLabel,
      categoryTabs,
      customSectionsMap,
      entryCategory,
      expenseBreakdown,
      donutSegments,
      chartExpenseShare,
      expenseBarH,
      saleBarH,
      profitBarH,
      balanceStory,
      formatMoney,
      sectionModal,
      sectionName,
      sectionError,
      sectionSaving,
      selectEntryCategory,
      selectPondContext,
      onEntrySaved,
      openSectionModal,
      createSection,
      removeSection,
    };
  },
};
</script>

<style scoped>
.finance-pond-context {
  margin-bottom: 1rem;
}

.finance-pond-context__head {
  margin-bottom: 0.75rem;
}

.finance-pond-context__head h2 {
  margin: 0 0 0.3rem;
  font-size: 1.15rem;
}

.finance-pond-context__head p {
  margin: 0;
  color: #5a7164;
  font-size: 0.9rem;
  line-height: 1.4;
}

.finance-pond-chips {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(158px, 1fr));
  gap: 0.7rem;
}

/* Flat selectors — Vue scoped + nesting buzilmasin */
.finance-pond-chip {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 0.3rem;
  min-height: 4.4rem;
  padding: 0.85rem 0.9rem;
  border: 2px dashed #b7c4bc;
  border-radius: 16px;
  background: #eef1ef !important;
  color: #6a7a70;
  cursor: pointer;
  font: inherit;
  text-align: left;
  opacity: 0.55;
  box-shadow: none;
}

.finance-pond-chip strong {
  font-size: 0.95rem;
  font-weight: 750;
  line-height: 1.2;
  color: #5a6b62;
}

.finance-pond-chip small {
  font-size: 0.72rem;
  font-weight: 600;
  color: #7a8a80;
}

.finance-pond-chip--closed:not(.finance-pond-chip--active) {
  opacity: 0.4;
}

.finance-pond-chip--active {
  z-index: 2;
  opacity: 1 !important;
  border-style: solid !important;
  border-width: 3px !important;
  border-color: #ffeb3b !important;
  background: #128a4a !important;
  color: #fff !important;
  box-shadow: 0 0 0 4px rgba(18, 138, 74, 0.4), 0 10px 22px rgba(0, 0, 0, 0.25) !important;
  transform: scale(1.04);
}

.finance-pond-chip--active strong {
  font-size: 1.05rem;
  font-weight: 900;
  color: #fff !important;
}

.finance-pond-chip--active small {
  color: #e8fff2 !important;
  font-weight: 700;
}

.finance-pond-chip__badge {
  display: inline-flex;
  align-items: center;
  gap: 0.2rem;
  margin-bottom: 0.15rem;
  padding: 0.2rem 0.55rem;
  border-radius: 999px;
  background: #ffeb3b !important;
  color: #1a3d12 !important;
  font-size: 0.75rem;
  font-weight: 900;
  letter-spacing: 0.01em;
  line-height: 1.2;
}

.finance-pond-context__target {
  margin: 0.75rem 0 0;
  padding: 0.55rem 0.7rem;
  border-radius: 10px;
  background: #e8f5e9;
  color: #1b5e20;
  font-size: 0.9rem;
  font-weight: 750;
  line-height: 1.35;
}

.finance-pond-context__target--warn {
  background: #fff3e0;
  color: #e65100;
}

.finance-pond-board {
  margin-bottom: 1rem;
}

.finance-pond-board__head {
  margin-bottom: 0.85rem;

  h2 {
    margin: 0 0 0.25rem;
    font-size: 1.15rem;
  }

  p {
    margin: 0;
    color: #5a7164;
    font-size: 0.9rem;
  }
}

.finance-pond-board__grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  gap: 0.65rem;
}

.finance-pond-card {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 0.25rem;
  padding: 0.85rem 0.9rem;
  border-radius: 14px;
  border: 1px solid rgba(10, 60, 40, 0.12);
  background: #fff;
  cursor: pointer;
  font: inherit;
  text-align: left;

  strong {
    font-size: 1rem;
  }

  span {
    font-size: 0.78rem;
    color: #5a7164;
  }

  em {
    margin-top: 0.2rem;
    font-style: normal;
    font-weight: 850;
    font-size: 0.92rem;
  }

  &--up em { color: #1b5e20; }
  &--down em { color: #8e0000; }
}

.finance-hero-entry {
  margin-bottom: 1rem;
  border: 1px solid rgba(13, 138, 82, 0.22);
  background:
    radial-gradient(600px 180px at 100% 0%, rgba(13, 138, 82, 0.1), transparent 55%),
    #fff;
}

.finance-hero-entry__head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 0.95rem;
}

.finance-hero-entry__badge {
  display: inline-flex;
  margin-bottom: 0.35rem;
  padding: 0.22rem 0.55rem;
  border-radius: 999px;
  background: rgba(13, 138, 82, 0.12);
  color: #065c36;
  font-size: 0.72rem;
  font-weight: 850;
  letter-spacing: 0.02em;
}

.finance-hero-entry__head h2 {
  margin: 0 0 0.25rem;
  font-size: 1.28rem;
}

.finance-hero-entry__head p {
  margin: 0;
  color: #5a7164;
  font-size: 0.9rem;
  line-height: 1.4;
  max-width: 42ch;
}

.finance-hero-entry__mini {
  flex-shrink: 0;
  min-width: 8.5rem;
  padding: 0.7rem 0.85rem;
  border-radius: 14px;
  background: #f4faf6;
  border: 1px solid rgba(10, 60, 40, 0.08);
  text-align: right;
}

.finance-hero-entry__mini span {
  display: block;
  font-size: 0.72rem;
  font-weight: 750;
  color: #5a7164;
}

.finance-hero-entry__mini strong {
  font-size: 1.05rem;
  font-weight: 850;
  color: #0b1f16;
}

.finance-visuals {
  display: grid;
  grid-template-columns: 1.15fr 1fr;
  gap: 0.85rem;
  margin-bottom: 1rem;
}

.finance-visual-card__head {
  margin-bottom: 0.75rem;
}

.finance-visual-card__head h3 {
  margin: 0 0 0.2rem;
  font-size: 1.05rem;
}

.finance-visual-card__head small {
  color: #5a7164;
  font-size: 0.82rem;
}

.finance-donut-wrap {
  display: grid;
  grid-template-columns: 140px 1fr;
  gap: 0.85rem;
  align-items: center;
}

.finance-donut {
  width: 140px;
  height: 140px;
}

.finance-donut-legend {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 0.45rem;
}

.finance-donut-legend li {
  display: grid;
  grid-template-columns: 10px 1fr auto auto;
  gap: 0.45rem;
  align-items: center;
  font-size: 0.82rem;
}

.finance-donut-legend i {
  width: 10px;
  height: 10px;
  border-radius: 3px;
}

.finance-donut-legend strong {
  font-weight: 800;
}

.finance-donut-legend em {
  font-style: normal;
  color: #5a7164;
  font-weight: 700;
  min-width: 2.4rem;
  text-align: right;
}

.finance-bars {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 0.65rem;
  align-items: end;
  min-height: 160px;
}

.finance-bar {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.35rem;
  text-align: center;
}

.finance-bar strong {
  font-size: 0.82rem;
  font-weight: 800;
}

.finance-bar span {
  font-size: 0.72rem;
  font-weight: 700;
  color: #5a7164;
}

.finance-bar__track {
  width: 100%;
  max-width: 52px;
  height: 110px;
  border-radius: 12px;
  background: #eef4f0;
  display: flex;
  align-items: flex-end;
  overflow: hidden;
}

.finance-bar__fill {
  width: 100%;
  border-radius: 12px 12px 0 0;
  min-height: 4px;
  transition: height 0.35s ease;
}

.finance-bar__fill--expense { background: linear-gradient(180deg, #ef5350, #c62828); }
.finance-bar__fill--sale { background: linear-gradient(180deg, #64b5f6, #1565c0); }
.finance-bar__fill--profit { background: linear-gradient(180deg, #66bb6a, #2e7d32); }
.finance-bar__fill--loss { background: linear-gradient(180deg, #ffb74d, #ef6c00); }

.finance-visual-note {
  margin: 0.85rem 0 0;
  padding: 0.6rem 0.75rem;
  border-radius: 12px;
  background: #f4faf6;
  font-size: 0.86rem;
  font-weight: 650;
  color: #0b1f16;
  line-height: 1.4;
}

.finance-simple-reports { margin-bottom: 1rem; }

.finance-simple-step {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
  margin-bottom: 0.95rem;
}

.finance-simple-step h2 {
  margin: 0 0 0.2rem;
  font-size: 1.12rem;
}

.finance-simple-step p {
  margin: 0;
  color: #5a7164;
  font-size: 0.88rem;
  line-height: 1.4;
}

.finance-simple-add-section {
  margin-left: auto;
  flex-shrink: 0;
}

.finance-simple-report-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
  gap: 0.65rem;
}

.finance-simple-report-card {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  padding: 0.9rem 0.95rem;
  border-radius: 14px;
  text-decoration: none;
  color: inherit;
  border: 1px solid rgba(10, 60, 40, 0.1);
  background: #f7faf8;
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.finance-simple-report-card:hover {
  transform: translateY(-1px);
  box-shadow: 0 8px 20px rgba(8, 40, 26, 0.08);
}

.finance-simple-report-card__top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.35rem;
}

.finance-simple-report-card__top strong { font-size: 0.98rem; }
.finance-simple-report-card__sum {
  font-size: 1.05rem;
  font-weight: 850;
  color: #065c36;
}

.finance-simple-report-card__bar {
  height: 6px;
  border-radius: 999px;
  background: rgba(10, 60, 40, 0.08);
  overflow: hidden;
  margin: 0.15rem 0 0.1rem;
}

.finance-simple-report-card__bar i {
  display: block;
  height: 100%;
  border-radius: inherit;
  max-width: 100%;
}

.finance-simple-report-card small {
  color: #5a7164;
  font-size: 0.78rem;
  font-weight: 650;
}

.finance-simple-report-card--feed { border-left: 4px solid #2e7d32; background: linear-gradient(160deg, #e8f5e9, #fff); }
.finance-simple-report-card--medicine { border-left: 4px solid #c62828; background: linear-gradient(160deg, #fce4ec, #fff); }
.finance-simple-report-card--salary { border-left: 4px solid #00838f; background: linear-gradient(160deg, #e0f7fa, #fff); }
.finance-simple-report-card--other { border-left: 4px solid #ef6c00; background: linear-gradient(160deg, #fff3e0, #fff); }
.finance-simple-report-card--custom { border-left: 4px solid #6a1b9a; background: linear-gradient(160deg, #f3e5f5, #fff); }
.finance-simple-report-card--warehouse small { color: #0d8a52; }

@media (max-width: 860px) {
  .finance-visuals { grid-template-columns: 1fr; }
  .finance-donut-wrap { grid-template-columns: 120px 1fr; }
}

@media (max-width: 720px) {
  .finance-hero-entry__head { flex-direction: column; }
  .finance-hero-entry__mini { text-align: left; width: 100%; }
  .finance-simple-step { flex-wrap: wrap; }
  .finance-simple-add-section { margin-left: 0; width: 100%; }
  .finance-donut-wrap { grid-template-columns: 1fr; justify-items: center; }
}
</style>
