<template>
  <DirectorShell>
    <div class="page-head page-head--toolbar">
      <router-link class="btn btn-outline btn-sm" :to="{ name: 'director-fishery', params: { pondId } }">{{ $t('back') }}</router-link>
    </div>

    <section class="panel sale-loss-form-panel">
      <div class="sale-loss-panel-head">
        <h2>{{ editingId ? $t('edit') : $t('newSaleLossEntry') }}</h2>
        <p class="field-hint">{{ $t('estimateNoticeBody') }}</p>
      </div>

      <form @submit.prevent="onStepSubmit" class="sale-loss-form">
        <!-- 1: Baliq turi -->
        <div v-if="step === 1" class="wizard-step form-section-block">
          <h3>{{ $t('qWhichFish') }}</h3>
          <p class="field-hint">{{ $t('availableFishStockHint') }}</p>
          <div v-if="!stockItems.length" class="empty">{{ $t('noFishYet') }}</div>
          <div v-else class="stock-type-grid">
            <button
              v-for="item in stockItems"
              :key="item.fish_type"
              type="button"
              class="stock-type-card"
              :class="{ active: form.fish_type === item.fish_type }"
              @click="pickFish(item)"
            >
              <FishTypeBadge :type="item.fish_type" block />
              <span>{{ formatInt(item.count) }} {{ $t('pieces') }}</span>
              <span v-if="item.mass_kg != null">≈ {{ formatKgMass(item.mass_kg) }} kg</span>
            </button>
          </div>
        </div>

        <!-- 2: Sotuv / yo'qotish -->
        <div v-else-if="step === 2" class="wizard-step form-section-block">
          <h3>{{ $t('qOperationType') }}</h3>
          <p class="field-hint">{{ $t('chooseOperationType') }}</p>
          <div class="choice-row">
            <button type="button" class="choice-btn" :class="{ active: form.type === 'sale' }" @click="pickOperation('sale')">
              {{ $t('sellFish') }}
            </button>
            <button type="button" class="choice-btn choice-btn--loss" :class="{ active: form.type === 'loss' }" @click="pickOperation('loss')">
              {{ $t('loss') }}
            </button>
          </div>
          <div class="wizard-nav">
            <button type="button" class="btn btn-outline btn-sm" @click="goStep(1)">{{ $t('back') }}</button>
          </div>
        </div>

        <!-- 3: Miqdor -->
        <div v-else-if="step === 3" class="wizard-step form-section-block">
          <h3>{{ form.type === 'sale' ? $t('qSaleKgAndPrice') : $t('lossAmountTitle') }}</h3>
          <p class="field-hint">{{ form.type === 'sale' ? $t('saleKgOnlyHint') : $t('lossAnyTwoHint') }}</p>
          <div class="form-grid fishery-form linked-fields">
            <template v-if="form.type === 'sale'">
              <div class="field">
                <label>{{ $t('soldWeightKg') }}</label>
                <FormattedNumberInput
                  v-model="form.total_weight_kg"
                  :max-decimals="3"
                  required
                  autofocus
                  @input="syncSaleFromKg"
                />
                <small v-if="selectedStock?.mass_kg != null" class="field-hint">
                  {{ $t('availableForType') }}: ≈ {{ formatKgMass(selectedStock.mass_kg) }} kg
                </small>
              </div>
              <div class="field">
                <label>{{ $t('salePricePerKg') }}</label>
                <FormattedNumberInput v-model="form.unit_price" :max-decimals="0" required />
              </div>
              <div class="field form-wide linked-summary sale-total-summary">
                <label>{{ $t('saleTotalAuto') }}</label>
                <input :value="calculatedSaleAmount ? formatMoney(calculatedSaleAmount) : ''" type="text" readonly tabindex="-1" />
                <small class="field-hint">{{ $t('saleTotalFormula') }}</small>
              </div>
            </template>
            <template v-else>
              <div class="field">
                <label>{{ $t('estimatedCount') }}</label>
                <FormattedNumberInput
                  v-model="form.quantity"
                  integer-only
                  autofocus
                  @input="syncLossFrom('quantity')"
                />
              </div>
              <div class="field">
                <label>{{ $t('averageWeight') }}</label>
                <FormattedNumberInput
                  v-model="form.average_weight_g"
                  :max-decimals="1"
                  @input="syncLossFrom('average_weight_g')"
                />
                <small v-if="weightHint" class="field-hint">{{ weightHint }}</small>
              </div>
              <div class="field">
                <label>{{ $t('estimatedKg') }}</label>
                <FormattedNumberInput
                  v-model="form.total_weight_kg"
                  :max-decimals="3"
                  @input="syncLossFrom('total_weight_kg')"
                />
              </div>
              <div v-if="hasValidAmount" class="field form-wide linked-summary">
                <label>{{ $t('lossAutoSummary') }}</label>
                <input
                  :value="`${formatInt(resolvedQuantity)} ${$t('pieces')} · ${formatGrams(Number(form.average_weight_g))} g · ${formatKgMass(resolvedKg)} kg`"
                  type="text"
                  readonly
                  tabindex="-1"
                />
                <small class="field-hint">
                  {{ $t('availableForType') }}: {{ formatInt(selectedStock?.count || 0) }} {{ $t('pieces') }}
                </small>
              </div>
            </template>
          </div>
          <p v-if="amountError" class="error">{{ amountError }}</p>
          <div class="wizard-nav">
            <button type="button" class="btn btn-outline btn-sm" @click="goStep(2)">{{ $t('back') }}</button>
            <button type="button" class="btn btn-primary" @click="confirmAmount">{{ $t('nextStep') }}</button>
          </div>
        </div>

        <!-- 4: Sana va saqlash -->
        <div v-else-if="step === 4" class="wizard-step form-section-block">
          <h3>{{ $t('qReportDate') }}</h3>
          <div class="form-grid fishery-form">
            <div class="field form-wide">
              <label>{{ $t('date') }}</label>
              <input v-model="form.operated_at" type="date" required />
            </div>
            <div v-if="form.type === 'sale'" class="field form-wide linked-summary">
              <label>{{ $t('saleSummary') }}</label>
              <input :value="`${formatKgMass(resolvedKg)} kg × ${formatMoney(Number(form.unit_price) || 0)} = ${formatMoney(calculatedSaleAmount)}`" type="text" readonly />
            </div>
            <div v-else class="field form-wide linked-summary">
              <label>{{ $t('lossAutoSummary') }}</label>
              <input
                :value="`${formatInt(resolvedQuantity)} ${$t('pieces')} · ${formatGrams(Number(form.average_weight_g))} g · ${formatKgMass(resolvedKg)} kg`"
                type="text"
                readonly
              />
            </div>
            <div class="field form-wide">
              <label>{{ $t('qNotes') }}</label>
              <textarea v-model="form.notes" rows="2" :placeholder="$t('optional')" />
            </div>
          </div>
          <p v-if="error" class="error">{{ error }}</p>
          <div class="wizard-nav">
            <button type="button" class="btn btn-outline btn-sm" @click="goStep(3)">{{ $t('back') }}</button>
            <button type="submit" class="btn btn-primary" :disabled="saving">
              {{ saving ? $t('loading') : $t('save') }}
            </button>
            <button v-if="editingId" type="button" class="btn btn-outline" @click="resetForm">{{ $t('cancel') }}</button>
          </div>
        </div>
      </form>
    </section>

    <section ref="historySection" class="sale-loss-history-section">
      <div class="sale-loss-history-head">
        <div>
          <h2>{{ $t('saleLossHistoryTitle') }}</h2>
          <p class="field-hint">{{ $t('saleLossHistoryHint') }}</p>
        </div>
        <button v-if="operations.length" class="btn btn-outline btn-sm" type="button" @click="showHistory = !showHistory">
          {{ showHistory ? $t('hide') : $t('show') }}
        </button>
      </div>
      <div v-if="!operations.length" class="panel empty">{{ $t('saleLossHistoryEmpty') }}</div>
      <div v-else-if="showHistory" class="panel sale-loss-history-panel">
        <ul class="record-list sale-loss-history-list">
          <li v-for="op in operations" :key="op.id" class="record-card" :class="op.type === 'sale' ? 'tone-sale' : 'tone-loss'">
            <div class="record-head">
              <strong>{{ dateOnly(op.operated_at) }}</strong>
              <span class="record-tag">{{ op.type === 'sale' ? $t('sellFish') : $t('loss') }}</span>
            </div>
            <p class="sale-loss-history-line">
              <FishTypeBadge :type="op.fish_type" compact />
              <span v-if="op.type !== 'sale'">{{ formatInt(op.quantity) }} {{ $t('pieces') }}</span>
              <span v-if="op.type !== 'sale' && op.average_weight_kg"> · {{ formatGrams(kgToGrams(op.average_weight_kg)) }} g</span>
              <span v-if="op.total_weight_kg"> · {{ formatKgMass(op.total_weight_kg) }} kg</span>
              <span v-if="op.type === 'sale' && op.amount">
                · {{ formatMoney(saleUnitPrice(op)) }}/kg
                · {{ $t('total') }}: {{ formatMoney(op.amount) }}
              </span>
            </p>
            <div class="record-actions">
              <button class="btn btn-outline btn-sm" type="button" @click="startEdit(op)">{{ $t('edit') }}</button>
              <button class="btn btn-outline btn-sm" type="button" @click="remove(op)">{{ $t('delete') }}</button>
            </div>
          </li>
        </ul>
      </div>
    </section>
  </DirectorShell>
</template>

<script>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute } from 'vue-router';
import DirectorShell from '../../components/DirectorShell.vue';
import FishTypeBadge from '../../components/FishTypeBadge.vue';
import { showSaved } from '../../flash';
import { t } from '../../i18n';
import { formatGrams, formatInt, formatKgMass, formatMoney, kgToGrams } from '../../utils/number';

const FISH_LABELS = {
  sazan: 'fishSazan',
  tolstolob: 'fishTolstolob',
  oq_amur: 'fishWhiteAmur',
  targil_tolstolob: 'fishBighead',
};

function roundKg(value) {
  return Math.round(value * 1000) / 1000;
}

function roundGrams(value) {
  return Math.round(value * 10) / 10;
}

function parsePositive(value) {
  const n = Number(value);
  return Number.isFinite(n) && n > 0 ? n : null;
}

function isManualSaleLoss(op) {
  const title = (op.title || '').toLowerCase();
  return !title.includes('havza yop');
}

export default {
  components: { DirectorShell, FishTypeBadge },
  setup() {
    const route = useRoute();
    const pondId = computed(() => route.params.pondId);
    const historySection = ref(null);
    const stockItems = ref([]);
    const operations = ref([]);
    const editingId = ref(null);
    const error = ref('');
    const amountError = ref('');
    const saving = ref(false);
    const showHistory = ref(false);
    const step = ref(1);
    const lossEditOrder = ref([]);
    const today = new Date().toISOString().slice(0, 10);
    const form = reactive({
      type: '',
      operated_at: today,
      fish_type: '',
      quantity: '',
      total_weight_kg: '',
      average_weight_g: '',
      unit_price: '',
      amount: '',
      notes: '',
    });

    const dateOnly = (value) => (typeof value === 'string' ? value.slice(0, 10) : value);
    const fishLabel = (type) => (FISH_LABELS[type] ? t(FISH_LABELS[type]) : (type || '—'));
    const selectedStock = computed(() => stockItems.value.find((item) => item.fish_type === form.fish_type) || null);

    const weightHint = computed(() => {
      if (!selectedStock.value?.average_weight_g) return '';
      if (selectedStock.value.weight_source === 'main_report') {
        return t('weightFromMainReport', { date: selectedStock.value.weight_source_date || '—' });
      }
      if (selectedStock.value.weight_source === 'stock_add') {
        return t('weightFromStockAdd');
      }
      return '';
    });

    const resolvedQuantity = computed(() => Math.max(0, Math.floor(Number(form.quantity) || 0)));
    const resolvedKg = computed(() => Number(form.total_weight_kg) || 0);
    const resolvedAverageG = computed(() => Number(form.average_weight_g) || 0);

    const calculatedSaleAmount = computed(() => {
      const kg = Number(form.total_weight_kg);
      const unitPrice = Number(form.unit_price);
      return kg > 0 && unitPrice > 0 ? Math.round(kg * unitPrice) : 0;
    });

    const hasValidAmount = computed(() => {
      if (form.type === 'sale') {
        return resolvedKg.value > 0
          && resolvedAverageG.value > 0
          && resolvedQuantity.value > 0
          && calculatedSaleAmount.value > 0;
      }
      return resolvedQuantity.value > 0
        && resolvedAverageG.value > 0
        && resolvedKg.value > 0;
    });

    function markLossEdited(field) {
      lossEditOrder.value = [...lossEditOrder.value.filter((item) => item !== field), field].slice(-2);
    }

    function syncLossFrom(field) {
      markLossEdited(field);

      const qty = parsePositive(form.quantity);
      const grams = parsePositive(form.average_weight_g);
      const kg = parsePositive(form.total_weight_kg);
      const filled = {
        quantity: qty,
        average_weight_g: grams,
        total_weight_kg: kg,
      };
      const known = Object.keys(filled).filter((key) => filled[key] != null);
      if (known.length < 2) return;

      let sources = lossEditOrder.value.filter((key) => filled[key] != null);
      if (sources.length < 2) {
        sources = known.slice(0, 2);
      } else {
        sources = sources.slice(-2);
      }

      const missing = ['quantity', 'average_weight_g', 'total_weight_kg'].find((key) => !sources.includes(key));
      if (!missing) return;

      if (missing === 'total_weight_kg' && qty != null && grams != null) {
        form.total_weight_kg = String(roundKg(qty * (grams / 1000)));
      } else if (missing === 'average_weight_g' && qty != null && kg != null) {
        form.average_weight_g = String(roundGrams((kg / qty) * 1000));
      } else if (missing === 'quantity' && grams != null && kg != null) {
        form.quantity = String(Math.max(1, Math.round(kg / (grams / 1000))));
      }
    }

    function syncSaleFromKg() {
      const kg = parsePositive(form.total_weight_kg);
      const grams = parsePositive(form.average_weight_g);
      if (kg != null && grams != null) {
        form.quantity = String(Math.max(1, Math.round(kg / (grams / 1000))));
      }
    }

    function goStep(n) {
      step.value = n;
      error.value = '';
      amountError.value = '';
    }

    function pickFish(item) {
      form.fish_type = item.fish_type;
      form.average_weight_g = !editingId.value && item.average_weight_g
        ? String(item.average_weight_g)
        : '';
      form.type = '';
      form.quantity = '';
      form.total_weight_kg = '';
      form.unit_price = '';
      lossEditOrder.value = form.average_weight_g ? ['average_weight_g'] : [];
      step.value = 2;
    }

    function pickOperation(type) {
      form.type = type;
      form.quantity = '';
      form.total_weight_kg = '';
      form.unit_price = '';
      if (type === 'loss') {
        lossEditOrder.value = form.average_weight_g ? ['average_weight_g'] : [];
      }
      step.value = 3;
    }

    function confirmAmount() {
      amountError.value = '';
      if (form.type === 'sale') {
        syncSaleFromKg();
      }
      if (!hasValidAmount.value) {
        if (form.type === 'sale' && !Number(form.average_weight_g)) {
          amountError.value = t('saleAverageWeightRequired');
        } else if (form.type === 'sale') {
          amountError.value = t('saleKgPriceRequired');
        } else {
          amountError.value = t('lossAnyTwoRequired');
        }
        return;
      }
      step.value = 4;
    }

    function onStepSubmit() {
      if (step.value === 4) {
        save();
      }
    }

    function resetForm() {
      editingId.value = null;
      step.value = 1;
      lossEditOrder.value = [];
      Object.assign(form, {
        type: '',
        operated_at: today,
        fish_type: '',
        quantity: '',
        total_weight_kg: '',
        average_weight_g: '',
        unit_price: '',
        amount: '',
        notes: '',
      });
      error.value = '';
      amountError.value = '';
    }

    function startEdit(op) {
      editingId.value = op.id;
      showHistory.value = false;
      const qty = Number(op.quantity) || 0;
      const kg = op.total_weight_kg ?? (qty && op.average_weight_kg ? roundKg(qty * Number(op.average_weight_kg)) : '');
      const unitPrice = op.type === 'sale' && Number(kg) > 0 && Number(op.amount) > 0
        ? Math.round(Number(op.amount) / Number(kg))
        : '';
      Object.assign(form, {
        type: op.type,
        operated_at: dateOnly(op.operated_at),
        fish_type: op.fish_type || '',
        quantity: qty ? String(qty) : '',
        total_weight_kg: kg !== '' && kg !== null ? String(kg) : '',
        average_weight_g: op.average_weight_kg ? String(kgToGrams(op.average_weight_kg)) : '',
        unit_price: unitPrice ? String(unitPrice) : '',
        amount: op.amount ?? '',
        notes: op.notes || '',
      });
      lossEditOrder.value = op.type === 'loss'
        ? ['quantity', 'average_weight_g', 'total_weight_kg'].filter((key) => form[key])
        : [];
      step.value = 3;
      error.value = '';
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    async function load() {
      error.value = '';
      try {
        const [stockRes, opsRes] = await Promise.all([
          window.axios.get(`/director/ponds/${pondId.value}/fish-stock`),
          window.axios.get(`/director/ponds/${pondId.value}/fish-operations`),
        ]);
        stockItems.value = stockRes.data.stock || [];
        const all = opsRes.data.data || opsRes.data;
        operations.value = all
          .filter((op) => (op.type === 'sale' || op.type === 'loss') && isManualSaleLoss(op))
          .sort((a, b) => String(b.operated_at).localeCompare(String(a.operated_at)));
      } catch (e) {
        stockItems.value = [];
        operations.value = [];
        error.value = e.response?.data?.message || t('noData');
      }
    }

    async function save() {
      error.value = '';
      if (!form.fish_type || !form.type || !hasValidAmount.value) {
        error.value = t('noData');
        return;
      }

      saving.value = true;
      try {
        const payload = {
          type: form.type,
          fish_type: form.fish_type,
          average_weight_g: form.average_weight_g,
          quantity: form.type === 'loss' ? resolvedQuantity.value : undefined,
          total_weight_kg: form.total_weight_kg,
          unit_price: form.type === 'sale' ? form.unit_price : null,
          amount: form.type === 'sale' ? calculatedSaleAmount.value : null,
          operated_at: form.operated_at,
          notes: form.notes,
          title: form.type === 'sale' ? t('sellFish') : t('actionFishLoss'),
        };

        if (editingId.value) {
          await window.axios.put(`/director/ponds/${pondId.value}/fish-operations/${editingId.value}`, payload);
        } else {
          await window.axios.post(`/director/ponds/${pondId.value}/fish-operations`, payload);
        }

        showSaved(t('saved'));
        resetForm();
        await load();
        showHistory.value = true;
      } catch (e) {
        const errors = e.response?.data?.errors;
        error.value = errors ? Object.values(errors).flat().join(' ') : (e.response?.data?.message || t('noData'));
      } finally {
        saving.value = false;
      }
    }

    async function remove(op) {
      if (!confirm(t('confirmDeleteReport'))) return;
      try {
        await window.axios.delete(`/director/ponds/${pondId.value}/fish-operations/${op.id}`);
        if (editingId.value === op.id) resetForm();
        await load();
      } catch (e) {
        error.value = e.response?.data?.message || t('noData');
      }
    }

    function saleUnitPrice(op) {
      const kg = Number(op.total_weight_kg);
      const amount = Number(op.amount);
      return kg > 0 && amount > 0 ? amount / kg : 0;
    }

    onMounted(load);

    return {
      pondId,
      historySection,
      stockItems,
      selectedStock,
      operations,
      editingId,
      form,
      error,
      amountError,
      saving,
      showHistory,
      step,
      weightHint,
      resolvedQuantity,
      resolvedKg,
      calculatedSaleAmount,
      hasValidAmount,
      goStep,
      pickFish,
      pickOperation,
      confirmAmount,
      onStepSubmit,
      save,
      startEdit,
      resetForm,
      remove,
      syncLossFrom,
      syncSaleFromKg,
      dateOnly,
      fishLabel,
      formatInt,
      formatGrams,
      formatKgMass,
      formatMoney,
      kgToGrams,
      saleUnitPrice,
    };
  },
};
</script>
