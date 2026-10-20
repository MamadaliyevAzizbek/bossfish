<template>
  <DirectorShell>
    <div class="page-head page-head--toolbar">
      <span class="page-head__context">{{ $t('currentStock') }}: {{ stock }} {{ $t('pieces') }}</span>
      <router-link class="btn btn-outline btn-sm" :to="{ name: 'director-fishery', params: { pondId } }">{{ $t('back') }}</router-link>
    </div>

    <div class="panel estimate-info">
      <p>{{ $t('estimateNoticeBody') }}</p>
    </div>

    <div v-if="loadError" class="panel">
      <p class="error">{{ loadError }}</p>
      <div class="actions">
        <router-link class="btn btn-outline" :to="{ name: 'director-fishery-ponds' }">{{ $t('back') }}</router-link>
      </div>
    </div>

    <div v-else class="panel panel--add-fish">
      <h2>{{ editingId ? $t('edit') : $t('addFish') }}</h2>
      <form @submit.prevent="save" class="form-section-blocks fishery-form">
        <section class="form-section-block">
          <h3>{{ $t('date') }}</h3>
          <div class="form-grid">
            <div class="field form-wide">
              <label>{{ $t('qReportDate') }}</label>
              <input v-model="form.operated_at" type="date" required />
            </div>
          </div>
        </section>

        <section class="form-section-block">
          <h3>{{ $t('fishType') }}</h3>
          <p class="field-hint">{{ $t('selectFishType') }}</p>
          <div class="stock-type-grid">
            <button
              v-for="type in FISH_TYPES"
              :key="type"
              type="button"
              class="stock-type-card"
              :class="{ active: form.fish_type === type }"
              :aria-pressed="form.fish_type === type"
              @click="form.fish_type = type"
            >
              <FishTypeBadge :type="type" block />
            </button>
          </div>
        </section>

        <section class="form-section-block">
          <h3>{{ $t('quantity') }}</h3>
          <p class="field-hint">{{ $t('addFishMassHint') }}</p>
          <div class="form-grid">
            <div class="field">
              <label>{{ $t('qFishCount') }}</label>
              <FormattedNumberInput
                v-model="form.quantity"
                integer-only
                required
                @input="syncFrom('quantity')"
              />
            </div>
            <div class="field">
              <label>{{ $t('qAverageWeight') }}</label>
              <FormattedNumberInput
                v-model="form.average_weight_g"
                :max-decimals="1"
                @input="syncFrom('average_weight_g')"
              />
            </div>
            <div class="field">
              <label>{{ $t('totalWeightKg') }}</label>
              <FormattedNumberInput
                v-model="form.total_weight_kg"
                :max-decimals="3"
                @input="syncFrom('total_weight_kg')"
              />
            </div>
          </div>
          <p v-if="autoHint" class="field-hint">{{ autoHint }}</p>
        </section>

        <section class="form-section-block">
          <h3>{{ $t('addFishPriceTitle') }}</h3>
          <p class="field-hint">{{ $t('addFishPriceHint') }}</p>
          <div class="form-grid">
            <div class="field">
              <label>{{ $t('pricePerKg') }}</label>
              <FormattedNumberInput v-model="form.unit_price" :max-decimals="0" />
            </div>
            <div class="field">
              <label>{{ $t('amount') }}</label>
              <input
                type="text"
                readonly
                :value="purchaseAmount > 0 ? formatMoney(purchaseAmount) : '—'"
              />
            </div>
          </div>
        </section>

        <p v-if="error" class="error">{{ error }}</p>
        <div class="actions actions--form-foot">
          <button class="btn btn-primary btn-block-mobile" type="submit">{{ $t('save') }}</button>
          <button v-if="editingId" class="btn btn-outline btn-block-mobile" type="button" @click="resetForm">{{ $t('cancel') }}</button>
        </div>
      </form>
    </div>

    <div v-if="!loadError" class="panel">
      <h2>{{ $t('previousOperations') }}</h2>
      <div v-if="!operations.length" class="empty">{{ $t('noData') }}</div>
      <ul v-else class="record-list">
        <li v-for="op in operations" :key="op.id" class="record-card">
          <div class="record-head">
            <strong>{{ dateOnly(op.operated_at) }}</strong>
            <span class="record-tag record-tag--fish">
              <FishTypeBadge :type="op.fish_type" compact />
            </span>
          </div>
          <dl class="record-facts">
            <div>
              <dt>{{ $t('fishCount') }}</dt>
              <dd>{{ op.quantity }} {{ $t('pieces') }}</dd>
            </div>
            <div>
              <dt>{{ $t('averageWeight') }}</dt>
              <dd>{{ op.average_weight_kg ? `${formatGrams(kgToGrams(op.average_weight_kg))} g` : '—' }}</dd>
            </div>
            <div>
              <dt>{{ $t('totalWeightKg') }}</dt>
              <dd>{{ op.total_weight_kg ? `${formatKgMass(op.total_weight_kg)} kg` : '—' }}</dd>
            </div>
            <div v-if="op.amount">
              <dt>{{ $t('amount') }}</dt>
              <dd>{{ formatMoney(op.amount) }}</dd>
            </div>
          </dl>
          <div class="record-actions">
            <button class="btn btn-outline btn-sm" type="button" @click="startEdit(op)">{{ $t('edit') }}</button>
            <button class="btn btn-outline btn-sm" type="button" @click="remove(op)">{{ $t('delete') }}</button>
          </div>
        </li>
      </ul>
    </div>
  </DirectorShell>
</template>

<script>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import DirectorShell from '../../components/DirectorShell.vue';
import FishTypeBadge from '../../components/FishTypeBadge.vue';
import { showSaved } from '../../flash';
import { t } from '../../i18n';
import { FISH_TYPES } from '../../utils/fish';
import { formatGrams, formatKgMass, formatMoney, kgToGrams } from '../../utils/number';

export default {
  components: { DirectorShell, FishTypeBadge },
  setup() {
    const route = useRoute();
    const router = useRouter();
    const pondId = computed(() => route.params.pondId);
    const stock = ref(0);
    const operations = ref([]);
    const editingId = ref(null);
    const error = ref('');
    const loadError = ref('');
    const editOrder = ref([]);
    const today = new Date().toISOString().slice(0, 10);
    const form = reactive({
      operated_at: today,
      fish_type: '',
      quantity: '',
      average_weight_g: '',
      total_weight_kg: '',
      unit_price: '',
    });

    const dateOnly = (value) => (typeof value === 'string' ? value.slice(0, 10) : value);

    function parsePositive(value) {
      const n = Number(value);
      return Number.isFinite(n) && n > 0 ? n : null;
    }

    function roundKg(n) {
      return Math.round(n * 1000) / 1000;
    }

    function roundGrams(n) {
      return Math.round(n * 10) / 10;
    }

    const purchaseAmount = computed(() => {
      const kg = parsePositive(form.total_weight_kg);
      const price = parsePositive(form.unit_price);
      if (!kg || !price) return 0;
      return Math.round(kg * price * 100) / 100;
    });

    const autoHint = computed(() => {
      const qty = parsePositive(form.quantity);
      const grams = parsePositive(form.average_weight_g);
      const kg = parsePositive(form.total_weight_kg);
      if (qty && grams && kg) return t('addFishAutoReady');
      if (qty && grams && !kg) return t('addFishAutoTotal');
      if (qty && kg && !grams) return t('addFishAutoAvg');
      return '';
    });

    function syncFrom(field) {
      const order = editOrder.value.filter((key) => key !== field);
      order.push(field);
      editOrder.value = order.slice(-2);

      const qty = parsePositive(form.quantity);
      const grams = parsePositive(form.average_weight_g);
      const kg = parsePositive(form.total_weight_kg);
      const sources = editOrder.value;

      const missing = ['quantity', 'average_weight_g', 'total_weight_kg'].find((key) => !sources.includes(key));
      if (!missing) return;

      if (missing === 'total_weight_kg' && qty != null && grams != null) {
        form.total_weight_kg = String(roundKg(qty * (grams / 1000)));
      } else if (missing === 'average_weight_g' && qty != null && kg != null) {
        form.average_weight_g = String(roundGrams((kg / qty) * 1000));
      }
    }

    function resetForm() {
      editingId.value = null;
      editOrder.value = [];
      Object.assign(form, {
        operated_at: today,
        fish_type: '',
        quantity: '',
        average_weight_g: '',
        total_weight_kg: '',
        unit_price: '',
      });
      error.value = '';
    }

    function startEdit(op) {
      editingId.value = op.id;
      Object.assign(form, {
        operated_at: dateOnly(op.operated_at),
        fish_type: op.fish_type || '',
        quantity: op.quantity,
        average_weight_g: op.average_weight_kg ? kgToGrams(op.average_weight_kg) : '',
        total_weight_kg: op.total_weight_kg ?? '',
        unit_price: op.amount && op.total_weight_kg
          ? String(Math.round((Number(op.amount) / Number(op.total_weight_kg)) * 100) / 100)
          : '',
      });
      editOrder.value = ['quantity', 'average_weight_g', 'total_weight_kg'].filter((key) => form[key] !== '' && form[key] != null);
      error.value = '';
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    async function load() {
      loadError.value = '';
      try {
        const [pondRes, opsRes] = await Promise.all([
          window.axios.get(`/director/ponds/${pondId.value}`),
          window.axios.get(`/director/ponds/${pondId.value}/fish-operations`, { params: { type: 'add' } }),
        ]);
        stock.value = pondRes.data.current_stock || 0;
        operations.value = opsRes.data.data || opsRes.data;
      } catch (e) {
        operations.value = [];
        stock.value = 0;
        loadError.value = e.response?.data?.message || t('noData');
      }
    }

    async function save() {
      error.value = '';
      if (!form.fish_type) {
        error.value = t('selectFishType');
        return;
      }
      if (!parsePositive(form.quantity)) {
        error.value = t('qFishCount');
        return;
      }
      if (!parsePositive(form.average_weight_g) && !parsePositive(form.total_weight_kg)) {
        error.value = t('addFishMassRequired');
        return;
      }
      try {
        const payload = {
          type: 'add',
          fish_type: form.fish_type,
          quantity: form.quantity,
          operated_at: form.operated_at,
          title: t('addFish'),
        };
        if (parsePositive(form.average_weight_g)) payload.average_weight_g = form.average_weight_g;
        if (parsePositive(form.total_weight_kg)) payload.total_weight_kg = form.total_weight_kg;
        if (parsePositive(form.unit_price)) payload.unit_price = form.unit_price;

        if (editingId.value) {
          await window.axios.put(`/director/ponds/${pondId.value}/fish-operations/${editingId.value}`, payload);
        } else {
          await window.axios.post(`/director/ponds/${pondId.value}/fish-operations`, payload);
        }

        showSaved(t('saved'));
        await router.push({ name: 'director-fishery', params: { pondId: pondId.value } });
      } catch (e) {
        const errors = e.response?.data?.errors;
        error.value = errors ? Object.values(errors).flat().join(' ') : (e.response?.data?.message || t('noData'));
      }
    }

    async function remove(op) {
      if (!confirm(t('confirmDeleteReport'))) return;
      const { data } = await window.axios.delete(`/director/ponds/${pondId.value}/fish-operations/${op.id}`);
      stock.value = data.current_stock;
      if (editingId.value === op.id) resetForm();
      await load();
    }

    onMounted(load);

    return {
      pondId,
      stock,
      operations,
      editingId,
      error,
      loadError,
      form,
      purchaseAmount,
      autoHint,
      FISH_TYPES,
      dateOnly,
      formatGrams,
      formatKgMass,
      formatMoney,
      kgToGrams,
      syncFrom,
      resetForm,
      startEdit,
      save,
      remove,
    };
  },
};
</script>
