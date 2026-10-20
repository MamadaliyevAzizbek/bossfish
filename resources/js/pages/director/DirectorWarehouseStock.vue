<template>
  <DirectorShell>
    <div class="page-head page-head--toolbar">
      <router-link class="btn btn-outline btn-sm" :to="{ name: 'director-warehouse' }">{{ $t('back') }}</router-link>
    </div>

    <div class="warehouse-stock-summary" role="tablist" :aria-label="$t('category')">
      <button
        type="button"
        class="warehouse-stock-summary-card warehouse-stock-summary-card--all"
        :class="{ 'warehouse-stock-summary-card--active': stockFilter === 'all' }"
        role="tab"
        :aria-selected="stockFilter === 'all'"
        @click="stockFilter = 'all'"
      >
        <span class="warehouse-stock-summary-label">{{ $t('all') }}</span>
      </button>
      <button
        v-for="card in summaryCards"
        :key="card.id"
        type="button"
        class="warehouse-stock-summary-card"
        :class="[
          `warehouse-stock-summary-card--${card.id}`,
          { 'warehouse-stock-summary-card--active': stockFilter === card.id },
        ]"
        role="tab"
        :aria-selected="stockFilter === card.id"
        @click="stockFilter = card.id"
      >
        <span class="warehouse-stock-summary-label">{{ card.label }}</span>
        <strong class="warehouse-stock-summary-value">{{ card.value }}</strong>
        <small class="warehouse-stock-summary-meta">{{ card.meta }}</small>
      </button>
    </div>

    <p v-if="stockFilter === 'feed' || stockFilter === 'medicine'" class="warehouse-stock-note">
      {{ $t('warehouseConsumableReadOnlyNote') }}
    </p>

    <template v-if="!stockItems.length">
      <div class="panel warehouse-stock-empty-panel">
        <p class="warehouse-stock-empty-title">{{ $t('warehouseReserveEmpty') }}</p>
        <p class="warehouse-stock-empty-hint">{{ $t('warehouseStockEmptyHint') }}</p>
      </div>
    </template>

    <template v-else>
      <section
        v-for="section in visibleSections"
        :key="section.id"
        class="warehouse-stock-section"
        :class="`warehouse-stock-section--${section.id}`"
      >
        <header class="warehouse-stock-section-head">
          <div class="warehouse-stock-section-title">
            <span class="warehouse-stock-section-dot" aria-hidden="true" />
            <h2>{{ section.label }}</h2>
          </div>
          <span class="warehouse-stock-section-total">{{ section.totalLabel }}</span>
        </header>

        <div v-if="!section.items.length" class="warehouse-stock-empty-panel warehouse-stock-empty-panel--inline">
          <p>{{ $t('warehouseStockNoItemsInCategory') }}</p>
        </div>

        <div v-else class="warehouse-stock-grid">
          <article
            v-for="item in section.items"
            :key="item.id"
            class="warehouse-stock-card"
            :class="[
              `warehouse-stock-card--${item.category}`,
              stockLevelClass(item),
            ]"
          >
            <div class="warehouse-stock-card-top">
              <span class="warehouse-stock-badge" :class="stockLevelClass(item)">
                {{ stockStatusLabel(item) }}
              </span>
              <img
                v-if="item.photo_url"
                :src="item.photo_url"
                :alt="item.name"
                class="warehouse-stock-card-photo"
              />
            </div>

            <h3 class="warehouse-stock-card-name">{{ item.name }}</h3>

            <div class="warehouse-stock-card-qty-row">
              <strong class="warehouse-stock-card-qty">{{ formatQty(item.quantity) }}</strong>
              <span class="warehouse-stock-card-unit">{{ unitLabel(item.unit) }}</span>
            </div>

            <button
              v-if="item.category === 'equipment' && Number(item.quantity) > 0"
              class="btn btn-outline btn-block-mobile warehouse-stock-issue-btn"
              type="button"
              @click="openIssue(item)"
            >
              {{ $t('warehouseIssue') }}
            </button>
          </article>
        </div>
      </section>
    </template>

    <div v-if="issueItem" class="modal-backdrop" @click.self="closeIssue">
      <div class="modal warehouse-stock-issue-modal">
        <h2 class="modal-title">{{ $t('warehouseIssue') }}</h2>
        <p class="warehouse-stock-issue-name">{{ issueItem.name }}</p>
        <form class="form-grid one-col" @submit.prevent="submitIssue">
          <div class="field">
            <label>{{ $t('quantity') }}</label>
            <FormattedNumberInput
              v-model="issueQty"
              :max-decimals="2"
              required
            />
            <small class="field-hint">
              {{ $t('warehouseStockLeft', { qty: `${formatQty(issueItem.quantity)} ${unitLabel(issueItem.unit)}` }) }}
            </small>
          </div>
          <p v-if="issueError" class="error">{{ issueError }}</p>
          <div class="actions">
            <button class="btn btn-primary" type="submit" :disabled="issuing">{{ issuing ? $t('loading') : $t('save') }}</button>
            <button class="btn btn-outline" type="button" @click="closeIssue">{{ $t('cancel') }}</button>
          </div>
        </form>
      </div>
    </div>
  </DirectorShell>
</template>

<script>
import { computed, onMounted, ref } from 'vue';
import { setDirectorFarmId, useDirectorFarmId } from '../../utils/directorFarm';
import { useRoute } from 'vue-router';
import DirectorShell from '../../components/DirectorShell.vue';
import { showSaved } from '../../flash';
import { t } from '../../i18n';
import { formatNumber } from '../../utils/number';

const CATEGORY_ORDER = ['feed', 'medicine', 'equipment'];

export default {
  components: { DirectorShell },
  setup() {
    const route = useRoute();
    const farmId = useDirectorFarmId();
    const farm = ref(null);
    const stockItems = ref([]);
    const stockFilter = ref('all');
    const issueItem = ref(null);
    const issueQty = ref('');
    const issuing = ref(false);
    const issueError = ref('');

    function categoryLabel(category) {
      return ({
        feed: t('warehouseFeed'),
        medicine: t('warehouseMedicine'),
        equipment: t('warehouseEquipment'),
      }[category] || category);
    }

    function unitLabel(unit) {
      return unit === 'piece' ? t('pieces') : 'kg';
    }

    function formatQty(value) {
      const num = Number(value);
      if (!Number.isFinite(num)) return '0';
      return formatNumber(num, { maxDecimals: num >= 100 ? 0 : 2 });
    }

    function isInStock(item) {
      return Number(item.quantity) > 0;
    }

    function stockLevelClass(item) {
      return isInStock(item) ? 'warehouse-stock-card--ok' : 'warehouse-stock-card--empty';
    }

    function stockStatusLabel(item) {
      return isInStock(item) ? t('warehouseStockAvailable') : t('warehouseStockDepleted');
    }

    function sectionTotalLabel(category, items) {
      const inStock = items.filter(isInStock);
      if (!inStock.length) {
        return t('warehouseStockNoItemsInCategory');
      }

      if (category === 'equipment') {
        const total = inStock.reduce((sum, item) => sum + Number(item.quantity), 0);
        return t('warehouseStockSectionTotalPieces', {
          qty: formatQty(total),
          count: inStock.length,
        });
      }

      const total = inStock.reduce((sum, item) => sum + Number(item.quantity), 0);
      return t('warehouseStockSectionTotalKg', {
        qty: formatQty(total),
        count: inStock.length,
      });
    }

    function sortItems(items) {
      return [...items].sort((a, b) => {
        const stockDiff = Number(isInStock(b)) - Number(isInStock(a));
        if (stockDiff !== 0) return stockDiff;
        return a.name.localeCompare(b.name, 'uz');
      });
    }

    const visibleSections = computed(() => {
      const categories = stockFilter.value === 'all'
        ? CATEGORY_ORDER
        : [stockFilter.value];

      return categories.map((category) => {
        const items = sortItems(stockItems.value.filter((item) => item.category === category));
        return {
          id: category,
          label: categoryLabel(category),
          items,
          totalLabel: sectionTotalLabel(category, items),
        };
      });
    });

    const summaryCards = computed(() => {
      return CATEGORY_ORDER.map((category) => {
        const items = stockItems.value.filter((item) => item.category === category);
        const inStock = items.filter(isInStock);

        if (category === 'equipment') {
          const total = inStock.reduce((sum, item) => sum + Number(item.quantity), 0);
          return {
            id: category,
            label: categoryLabel(category),
            value: inStock.length ? `${formatQty(total)} ${t('pieces')}` : '—',
            meta: t('warehouseStockItemCount', { n: inStock.length }),
          };
        }

        const total = inStock.reduce((sum, item) => sum + Number(item.quantity), 0);
        return {
          id: category,
          label: categoryLabel(category),
          value: inStock.length ? `${formatQty(total)} kg` : '—',
          meta: t('warehouseStockItemCount', { n: inStock.length }),
        };
      });
    });

    async function loadFarm() {
      const { data } = await window.axios.get(`/director/farms/${farmId.value}`);
      farm.value = data;
    }

    async function loadStock() {
      const { data } = await window.axios.get(`/director/farms/${farmId.value}/warehouse`);
      stockItems.value = data;
    }

    function openIssue(item) {
      issueItem.value = item;
      issueQty.value = '';
      issueError.value = '';
    }

    function closeIssue() {
      issueItem.value = null;
      issueQty.value = '';
      issueError.value = '';
    }

    async function submitIssue() {
      if (!issueItem.value) return;
      issueError.value = '';
      issuing.value = true;
      try {
        await window.axios.post(
          `/director/farms/${farmId.value}/warehouse/${issueItem.value.id}/issue`,
          { quantity: Number(issueQty.value) },
        );
        showSaved(t('saved'));
        closeIssue();
        await loadStock();
      } catch (e) {
        const errors = e.response?.data?.errors;
        issueError.value = errors ? Object.values(errors).flat().join(' ') : (e.response?.data?.message || t('noData'));
      } finally {
        issuing.value = false;
      }
    }

    onMounted(async () => {
      await Promise.all([loadFarm(), loadStock()]);
    });

    return {
      farmId,
      farm,
      stockFilter,
      stockItems,
      summaryCards,
      visibleSections,
      formatQty,
      unitLabel,
      stockLevelClass,
      stockStatusLabel,
      issueItem,
      issueQty,
      issuing,
      issueError,
      openIssue,
      closeIssue,
      submitIssue,
    };
  },
};
</script>
