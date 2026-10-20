<template>
  <DirectorShell>
    <div class="page-head page-head--toolbar">
      <strong class="page-head__context">{{ categoryTitle }}</strong>
      <router-link class="btn btn-outline btn-sm" :to="{ name: 'director-finance' }">{{ $t('back') }}</router-link>
    </div>

    <div v-if="loading" class="panel empty">{{ $t('loading') }}</div>
    <div v-else-if="invalidCategory" class="panel empty">{{ $t('noData') }}</div>

    <div v-else class="panel finance-entry-section finance-entry-section--primary">
      <FinanceQuickEntry
        :farm-id="farmId"
        :category-tabs="categoryTabs"
        :selected-category="routeCategory"
        :custom-sections="customSections"
        :ponds="ponds"
        :edit-id="route.query.edit"
        compact
        @select-category="goCategory"
        @saved="onSaved"
        @cancel-edit="clearEdit"
      />
    </div>
  </DirectorShell>
</template>

<script>
import { computed, onMounted, ref, watch } from 'vue';
import { setDirectorFarmId, useDirectorFarmId } from '../../utils/directorFarm';
import { useRoute, useRouter } from 'vue-router';
import DirectorShell from '../../components/DirectorShell.vue';
import FinanceQuickEntry from '../../components/FinanceQuickEntry.vue';
import {
  FINANCE_CATEGORIES,
  financeCategoryIcon,
  financeCategoryLabel as labelCategory,
  isValidFinanceCategory,
  isWarehouseFinanceCategory,
  toRouteCategory,
} from '../../utils/finance';
import { t } from '../../i18n';

export default {
  components: { DirectorShell, FinanceQuickEntry },
  setup() {
    const route = useRoute();
    const router = useRouter();
    const farmId = useDirectorFarmId();
    const routeCategory = computed(() => route.params.category);
    const loading = ref(true);
    const invalidCategory = ref(false);
    const farm = ref(null);
    const ponds = ref([]);
    const customSections = ref({});
    const overviewCategories = ref({});

    const categoryTitle = computed(() => (
      labelCategory(routeCategory.value, t, customSections.value)
    ));

    const categoryTabs = computed(() => {
      const fixed = FINANCE_CATEGORIES.map((category) => ({
        routeKey: category,
        label: labelCategory(category, t),
        icon: financeCategoryIcon(category),
        total: overviewCategories.value?.[category] || 0,
        warehouse: isWarehouseFinanceCategory(category),
        cardClass: `finance-category-card--${category}`,
        section: null,
      }));

      const custom = Object.entries(customSections.value).map(([id, section]) => ({
        routeKey: toRouteCategory(`custom:${id}`),
        label: section.name,
        icon: financeCategoryIcon(`custom:${id}`),
        total: 0,
        warehouse: false,
        cardClass: 'finance-category-card--custom',
        section: { id: Number(id), name: section.name },
      }));

      return [...fixed, ...custom];
    });

    function goCategory(routeKey) {
      router.push({
        name: 'director-finance-entry',
        params: { category: routeKey },
      });
    }

    function clearEdit() {
      router.replace({
        name: 'director-finance-entry',
        params: { category: routeCategory.value },
      });
    }

    function onSaved() {
      router.push({
        name: 'director-finance',
        query: { entry: routeCategory.value },
      });
    }

    async function load() {
      loading.value = true;
      invalidCategory.value = false;
      try {
        const overviewRes = await window.axios.get(`/director/farms/${farmId.value}/finance/overview`);
        const sections = overviewRes.data.custom_sections || [];
        overviewCategories.value = overviewRes.data.categories || {};
        ponds.value = overviewRes.data.ponds || [];
        customSections.value = Object.fromEntries(
          sections.map((section) => [String(section.id), { name: section.name }]),
        );

        const sectionIds = sections.map((section) => section.id);
        if (!isValidFinanceCategory(routeCategory.value, sectionIds)) {
          invalidCategory.value = true;
          return;
        }

        const farmRes = await window.axios.get(`/director/farms/${farmId.value}`);
        farm.value = farmRes.data;
      } finally {
        loading.value = false;
      }
    }

    watch(routeCategory, load);
    onMounted(load);

    return {
      farmId,
      routeCategory,
      farm,
      ponds,
      loading,
      invalidCategory,
      categoryTitle,
      categoryTabs,
      customSections,
      goCategory,
      clearEdit,
      onSaved,
    };
  },
};
</script>
