<template>
  <div
    class="finance-quick-entry finance-quick-entry--simple"
    :class="{ 'finance-quick-entry--has-pick': !!selectedCategory }"
  >
    <div v-if="!isScopeLocked" class="finance-quick-scope">
      <p class="finance-quick-pick__label">{{ $t('financeQuickPickPond') }}</p>
      <p class="finance-quick-lead">{{ $t('financeQuickPickPondHint') }}</p>

      <div class="finance-quick-scope-grid" role="radiogroup" :aria-label="$t('financeQuickPickPond')">
        <button
          type="button"
          role="radio"
          class="finance-quick-scope-btn"
          :class="{ 'finance-quick-scope-btn--active': scopeMode === 'farm' }"
          :aria-checked="scopeMode === 'farm'"
          @click="setScope('farm')"
        >
          <span class="finance-quick-scope-btn__title">{{ $t('financeScopeAllPonds') }}</span>
          <small>{{ $t('financeScopeAllPondsHint') }}</small>
        </button>

        <button
          v-for="pond in openPonds"
          :key="pond.id"
          type="button"
          role="radio"
          class="finance-quick-scope-btn"
          :class="{ 'finance-quick-scope-btn--active': scopeMode === 'pond' && Number(selectedPondId) === Number(pond.id) }"
          :aria-checked="scopeMode === 'pond' && Number(selectedPondId) === Number(pond.id)"
          @click="setScope('pond', pond.id)"
        >
          <span class="finance-quick-scope-btn__title">{{ pond.name }}</span>
          <small>{{ $t('financeScopeOnePondHint') }}</small>
        </button>
        <p v-if="!openPonds.length" class="field-hint">{{ $t('financeNoOpenPondsForExpense') }}</p>
      </div>

      <p v-if="scopeMode" class="finance-quick-picked finance-quick-picked--scope">
        <span class="finance-quick-picked__mark" aria-hidden="true">✓</span>
        {{ scopeLabel }}
      </p>
    </div>

    <p v-else-if="scopeMode" class="finance-quick-picked finance-quick-picked--scope">
      <span class="finance-quick-picked__mark" aria-hidden="true">✓</span>
      {{ $t('financeExpenseTarget', { target: scopeLabel }) }}
    </p>

    <template v-if="scopeMode">
      <div class="finance-quick-pick">
        <p class="finance-quick-pick__label">
          {{ isScopeLocked ? $t('financeQuickPickCategoryLocked') : $t('financeQuickPickCategory') }}
        </p>
        <p class="finance-quick-lead">{{ $t('financeQuickEntryHint') }}</p>

        <div
          class="finance-quick-categories finance-quick-categories--simple"
          role="radiogroup"
          :aria-label="$t('financeQuickPickCategory')"
        >
          <button
            v-for="tab in categoryTabs"
            :key="tab.routeKey"
            type="button"
            role="radio"
            class="finance-quick-cat-btn"
            :class="[
              `finance-quick-cat-btn--${tab.routeKey.startsWith('custom') ? 'custom' : tab.routeKey}`,
              {
                'finance-quick-cat-btn--active': selectedCategory === tab.routeKey,
                'finance-quick-cat-btn--idle': selectedCategory && selectedCategory !== tab.routeKey,
              },
            ]"
            :aria-checked="selectedCategory === tab.routeKey"
            @click="$emit('select-category', tab.routeKey)"
          >
            <span class="finance-quick-cat-icon" aria-hidden="true">{{ tab.icon || '📋' }}</span>
            <span class="finance-quick-cat-text">
              <span class="finance-quick-cat-label">{{ tab.label }}</span>
              <small v-if="tab.warehouse">{{ $t('financeSimpleWarehouseShort') }}</small>
            </span>
            <span
              v-if="selectedCategory === tab.routeKey"
              class="finance-quick-cat-check"
              aria-hidden="true"
            >✓</span>
          </button>
        </div>

        <p v-if="selectedCategory" class="finance-quick-picked">
          <span class="finance-quick-picked__mark" aria-hidden="true">✓</span>
          {{ $t('financeQuickSelected', { name: categoryTitle }) }}
        </p>
        <p v-if="selectedCategory === 'feed' && scopeMode === 'farm'" class="finance-quick-note">
          {{ $t('financeFeedAllocHint') }}
        </p>
      </div>

      <form
        v-if="selectedCategory"
        class="finance-quick-form finance-quick-form--simple"
        :class="{ 'finance-quick-form--fast': fast }"
        @submit.prevent="save"
      >
        <p class="finance-quick-form-title">
          {{ editingId ? $t('edit') : $t('financeAddExpense') }} — <strong>{{ categoryTitle }}</strong>
        </p>

        <div v-if="requiresWarehouseQuantity" class="finance-quick-note">
          {{ $t('financeQuickWarehouseNote') }}
        </div>

        <div class="finance-quick-grid" :class="{ 'finance-quick-grid--warehouse': requiresWarehouseQuantity }">
          <div class="finance-quick-field">
            <label>{{ $t('financeQuickStepDate') }}</label>
            <input v-model="form.expense_date" type="date" required class="finance-quick-input" />
          </div>

          <div class="finance-quick-field finance-quick-field--grow">
            <label>{{ $t('financeQuickStepName') }}</label>
            <input
              v-model.trim="form.title"
              type="text"
              maxlength="255"
              required
              class="finance-quick-input"
              :placeholder="expenseNamePlaceholder"
            />
          </div>

          <template v-if="requiresWarehouseQuantity">
            <div class="finance-quick-field">
              <label>{{ $t('financeQuickStepQty') }}</label>
              <FormattedNumberInput
                v-model="form.quantity"
                :max-decimals="3"
                required
                class="finance-quick-input"
                @input="syncAmount"
              />
            </div>
            <div class="finance-quick-field">
              <label>{{ $t('financeQuickStepPrice') }}</label>
              <FormattedNumberInput
                v-model="form.unit_price"
                :max-decimals="0"
                class="finance-quick-input"
                @input="syncAmount"
              />
            </div>
          </template>

          <div class="finance-quick-field">
            <label>{{ $t('financeQuickStepAmount') }}</label>
            <FormattedNumberInput
              v-model="form.amount"
              :max-decimals="0"
              required
              class="finance-quick-input finance-quick-input--amount"
            />
          </div>
        </div>

        <details v-if="fast" class="finance-quick-more">
          <summary>{{ $t('financeQuickMoreOptional') }}</summary>
          <div class="finance-quick-field">
            <label>{{ $t('financeQuickStepNotes') }}</label>
            <textarea
              v-model="form.description"
              rows="2"
              class="finance-quick-input finance-quick-textarea"
              :placeholder="$t('optional')"
            />
          </div>
        </details>
        <div v-else class="finance-quick-field">
          <label>{{ $t('financeQuickStepNotes') }}</label>
          <textarea
            v-model="form.description"
            rows="2"
            class="finance-quick-input finance-quick-textarea"
            :placeholder="$t('optional')"
          />
        </div>

        <p v-if="error" class="error finance-quick-error">{{ error }}</p>

        <div class="finance-quick-actions">
          <button class="btn btn-primary finance-quick-save" type="submit" :disabled="saving">
            {{ saving ? $t('registering') : $t('financeQuickSaveFast') }}
          </button>
          <button
            v-if="editingId"
            class="btn btn-outline finance-quick-cancel"
            type="button"
            @click="resetForm"
          >
            {{ $t('cancel') }}
          </button>
        </div>
      </form>
    </template>
  </div>
</template>

<script>
import { computed, reactive, ref, watch } from 'vue';
import { showSaved } from '../flash';
import {
  financeCategoryLabel as labelCategory,
  financeCategoryPlaceholder,
  isWarehouseFinanceCategory,
  toApiCategory,
} from '../utils/finance';
import { t } from '../i18n';

export default {
  props: {
    farmId: { type: [String, Number], required: true },
    categoryTabs: { type: Array, required: true },
    selectedCategory: { type: String, default: null },
    customSections: { type: Object, default: () => ({}) },
    ponds: { type: Array, default: () => [] },
    editId: { type: [String, Number], default: null },
    compact: { type: Boolean, default: false },
    fast: { type: Boolean, default: false },
    /** Parent already chose farm/pond — hide second picker */
    lockedScopeMode: { type: String, default: null }, // 'farm' | 'pond' | null
    lockedPondId: { type: [String, Number], default: null },
  },
  emits: ['select-category', 'saved', 'cancel-edit'],
  setup(props, { emit }) {
    const saving = ref(false);
    const error = ref('');
    const editingId = ref(null);
    const scopeMode = ref(null); // 'farm' | 'pond'
    const selectedPondId = ref(null);

    const isScopeLocked = computed(() => (
      props.lockedScopeMode === 'farm' || props.lockedScopeMode === 'pond'
    ));
    const today = new Date().toISOString().slice(0, 10);
    const form = reactive({
      expense_date: today,
      title: '',
      quantity: '',
      unit_price: '',
      amount: '',
      description: '',
    });

    const openPonds = computed(() => (props.ponds || []).filter((p) => !p.is_closed && !p.closed_at));

    const requiresWarehouseQuantity = computed(() => (
      props.selectedCategory ? isWarehouseFinanceCategory(props.selectedCategory) : false
    ));

    const categoryTitle = computed(() => (
      props.selectedCategory
        ? labelCategory(props.selectedCategory, t, props.customSections)
        : ''
    ));

    const expenseNamePlaceholder = computed(() => (
      props.selectedCategory ? financeCategoryPlaceholder(props.selectedCategory, t) : ''
    ));

    const scopeLabel = computed(() => {
      if (scopeMode.value === 'farm') return t('financeExpenseTargetFarm');
      const pond = (props.ponds || []).find((p) => Number(p.id) === Number(selectedPondId.value));
      return pond?.name || t('financeScopePondSelected', { name: '—' });
    });

    function setScope(mode, pondId = null) {
      if (isScopeLocked.value && !props.editId) return;
      if (mode === 'pond') {
        const open = openPonds.value.some((p) => Number(p.id) === Number(pondId));
        if (!open) return;
      }
      scopeMode.value = mode;
      selectedPondId.value = mode === 'pond' ? pondId : null;
    }

    function applyLockedScope() {
      if (!isScopeLocked.value) return;
      if (props.lockedScopeMode === 'pond' && props.lockedPondId != null && props.lockedPondId !== '') {
        scopeMode.value = 'pond';
        selectedPondId.value = props.lockedPondId;
        return;
      }
      scopeMode.value = 'farm';
      selectedPondId.value = null;
    }

    function dateOnly(value) {
      return typeof value === 'string' ? value.slice(0, 10) : value;
    }

    function syncAmount() {
      const qty = Number(form.quantity);
      const price = Number(form.unit_price);
      if (Number.isFinite(qty) && Number.isFinite(price) && qty > 0 && price > 0) {
        form.amount = String(Math.round(qty * price * 100) / 100);
      }
    }

    function resetForm() {
      editingId.value = null;
      Object.assign(form, {
        expense_date: today,
        title: '',
        quantity: '',
        unit_price: '',
        amount: '',
        description: '',
      });
      error.value = '';
      emit('cancel-edit');
    }

    async function loadEditRow(editId) {
      if (!editId || !props.selectedCategory) return;
      const { data } = await window.axios.get(`/director/farms/${props.farmId}/finance/expenses`, {
        params: { category: toApiCategory(props.selectedCategory) },
      });
      const row = (data.data || []).find((item) => String(item.id) === String(editId));
      if (!row) return;
      editingId.value = row.id;
      if (row.pond_id) {
        setScope('pond', row.pond_id);
      } else {
        setScope('farm');
      }
      Object.assign(form, {
        expense_date: dateOnly(row.expense_date),
        title: row.title,
        quantity: row.quantity ?? '',
        unit_price: row.unit_price ?? '',
        amount: row.amount ?? '',
        description: row.description ?? '',
      });
    }

    async function save() {
      if (!props.selectedCategory || !scopeMode.value) return;
      error.value = '';
      if (requiresWarehouseQuantity.value) syncAmount();
      saving.value = true;
      try {
        const payload = {
          category: toApiCategory(props.selectedCategory),
          expense_date: form.expense_date,
          title: form.title,
          amount: form.amount,
          description: form.description || null,
          scope: scopeMode.value === 'farm' ? 'farm' : 'pond',
          pond_id: scopeMode.value === 'pond' ? selectedPondId.value : null,
        };
        if (requiresWarehouseQuantity.value) {
          payload.quantity = form.quantity !== '' ? form.quantity : null;
          payload.unit_price = form.unit_price !== '' ? form.unit_price : null;
        }
        if (editingId.value) {
          await window.axios.put(`/director/farms/${props.farmId}/finance/expenses/${editingId.value}`, payload);
        } else {
          await window.axios.post(`/director/farms/${props.farmId}/finance/expenses`, payload);
        }
        showSaved(t('saved'));
        resetForm();
        emit('saved');
      } catch (e) {
        const errors = e.response?.data?.errors;
        error.value = errors ? Object.values(errors).flat().join(' ') : (e.response?.data?.message || t('noData'));
      } finally {
        saving.value = false;
      }
    }

    watch(
      () => [props.selectedCategory, props.editId],
      async ([category, editId]) => {
        if (!category) {
          resetForm();
          return;
        }
        if (editId) {
          await loadEditRow(editId);
        } else if (!editingId.value) {
          Object.assign(form, {
            expense_date: today,
            title: '',
            quantity: '',
            unit_price: '',
            amount: '',
            description: '',
          });
          error.value = '';
          applyLockedScope();
        }
      },
      { immediate: true },
    );

    watch(
      () => [props.lockedScopeMode, props.lockedPondId],
      () => {
        if (!editingId.value) applyLockedScope();
      },
      { immediate: true },
    );

    return {
      form,
      error,
      saving,
      editingId,
      scopeMode,
      selectedPondId,
      openPonds,
      isScopeLocked,
      scopeLabel,
      requiresWarehouseQuantity,
      categoryTitle,
      expenseNamePlaceholder,
      setScope,
      syncAmount,
      resetForm,
      save,
    };
  },
};
</script>
