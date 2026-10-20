<template>
  <DirectorShell>
    <div class="page-head page-head--toolbar">
      <strong class="page-head__context">{{ categoryTitle }}</strong>
      <router-link class="btn btn-outline btn-sm" :to="{ name: 'director-finance' }">{{ $t('back') }}</router-link>
    </div>

    <div v-if="loading" class="panel empty">{{ $t('loading') }}</div>
    <div v-else-if="invalidCategory" class="panel empty">{{ $t('noData') }}</div>

    <template v-else>
      <div class="finance-breakdown-head panel">
        <div>
          <h2>{{ categoryTitle }}</h2>
          <div class="finance-report-filter">
            <label>{{ $t('financeFilterPond') }}</label>
            <select v-model="filterPondId" @change="load">
              <option value="">{{ $t('financeFilterAllPonds') }}</option>
              <option v-for="pond in ponds" :key="pond.id" :value="String(pond.id)">{{ pond.name }}</option>
            </select>
          </div>
        </div>
        <strong>{{ formatMoney(categoryTotal) }}</strong>
      </div>

      <div class="panel finance-breakdown-panel">
        <div v-if="!entries.length" class="empty">{{ $t('noData') }}</div>
        <div v-else class="table-scroll finance-breakdown-scroll">
          <table class="report-table finance-table finance-breakdown-table">
            <thead>
              <tr>
                <th>{{ $t('date') }}</th>
                <th>{{ $t('financeExpenseName') }}</th>
                <th>{{ $t('pond') }}</th>
                <th>{{ $t('financeQuantity') }}</th>
                <th>{{ $t('financeUnitPrice') }}</th>
                <th>{{ $t('amount') }}</th>
                <th>{{ $t('financeEnteredBy') }}</th>
                <th>{{ $t('notes') }}</th>
                <th />
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in entries" :key="`${row.id}-${row.allocated_from_farm ? 'a' : 'd'}`">
                <td>{{ dateOnly(row.expense_date) }}</td>
                <td>
                  <strong>{{ row.title }}</strong>
                  <small v-if="row.allocated_from_farm" class="finance-alloc-badge">{{ $t('financeFeedShareBadge') }}</small>
                </td>
                <td>{{ row.pond?.name || $t('financeScopeAllPondsShort') }}</td>
                <td class="num">{{ row.quantity != null ? `${formatQty(row.quantity)} kg` : '—' }}</td>
                <td class="num">{{ row.unit_price != null ? formatMoney(row.unit_price) : '—' }}</td>
                <td class="num"><strong>{{ formatMoney(row.amount) }}</strong></td>
                <td>{{ row.user?.name || '—' }}</td>
                <td>{{ row.description || '—' }}</td>
                <td class="finance-row-actions">
                  <router-link
                    v-if="!row.allocated_from_farm"
                    class="btn btn-outline btn-sm"
                    :to="{ name: 'director-finance-entry', params: { category: routeCategory }, query: { edit: row.id } }"
                  >
                    {{ $t('edit') }}
                  </router-link>
                  <button
                    v-if="!row.allocated_from_farm"
                    class="btn btn-outline btn-sm"
                    type="button"
                    @click="remove(row)"
                  >{{ $t('delete') }}</button>
                </td>
              </tr>
            </tbody>
            <tfoot>
              <tr>
                <td colspan="5"><strong>{{ $t('financeCategoryTotal') }}</strong></td>
                <td class="num"><strong>{{ formatMoney(categoryTotal) }}</strong></td>
                <td colspan="3" />
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </template>
  </DirectorShell>
</template>

<script>
import { computed, onMounted, ref, watch } from 'vue';
import { setDirectorFarmId, useDirectorFarmId } from '../../utils/directorFarm';
import { useRoute } from 'vue-router';
import DirectorShell from '../../components/DirectorShell.vue';
import { showSaved } from '../../flash';
import {
  financeCategoryLabel as labelCategory,
  isValidFinanceCategory,
  toApiCategory,
} from '../../utils/finance';
import { formatMoney, formatNumber } from '../../utils/number';
import { t } from '../../i18n';

export default {
  components: { DirectorShell },
  setup() {
    const route = useRoute();
    const farmId = useDirectorFarmId();
    const routeCategory = computed(() => route.params.category);
    const loading = ref(true);
    const invalidCategory = ref(false);
    const farm = ref(null);
    const ponds = ref([]);
    const filterPondId = ref('');
    const customSections = ref({});
    const entries = ref([]);
    const categoryTotal = ref(0);

    const categoryTitle = computed(() => (
      labelCategory(routeCategory.value, t, customSections.value)
    ));

    function dateOnly(value) {
      return typeof value === 'string' ? value.slice(0, 10) : value;
    }

    function formatQty(value) {
      return formatNumber(value, { maxDecimals: 3 });
    }

    async function load() {
      loading.value = true;
      invalidCategory.value = false;
      try {
        const overviewRes = await window.axios.get(`/director/farms/${farmId.value}/finance/overview`);
        const sections = overviewRes.data.custom_sections || [];
        customSections.value = Object.fromEntries(
          sections.map((section) => [String(section.id), { name: section.name }]),
        );

        const sectionIds = sections.map((section) => section.id);
        if (!isValidFinanceCategory(routeCategory.value, sectionIds)) {
          invalidCategory.value = true;
          return;
        }

        const expenseParams = { category: toApiCategory(routeCategory.value) };
        if (filterPondId.value) expenseParams.pond_id = filterPondId.value;

        const [farmRes, entriesRes] = await Promise.all([
          window.axios.get(`/director/farms/${farmId.value}`),
          window.axios.get(`/director/farms/${farmId.value}/finance/expenses`, {
            params: expenseParams,
          }),
        ]);
        farm.value = farmRes.data;
        ponds.value = overviewRes.data.ponds || [];
        entries.value = entriesRes.data.data || [];
        categoryTotal.value = entriesRes.data.category_total || 0;
      } finally {
        loading.value = false;
      }
    }

    async function remove(row) {
      if (!confirm(t('confirmDeleteReport'))) return;
      await window.axios.delete(`/director/farms/${farmId.value}/finance/expenses/${row.id}`);
      showSaved(t('saved'));
      await load();
    }

    watch(routeCategory, load);
    onMounted(load);

    return {
      farmId,
      routeCategory,
      farm,
      ponds,
      filterPondId,
      loading,
      invalidCategory,
      entries,
      categoryTotal,
      categoryTitle,
      dateOnly,
      formatMoney,
      formatQty,
      remove,
    };
  },
};
</script>
