<template>
  <section class="panel ration-section">
    <div class="owner-ration-section-head">
      <h2>{{ isEdit ? $t('editRation') : $t('newRation') }}</h2>
      <small>{{ $t('newRationHint') }}</small>
    </div>

    <div v-if="loading" class="empty">{{ $t('loading') }}</div>

    <form v-else @submit.prevent="saveRation">
      <div class="owner-ration-form-top">
        <div class="field">
          <label>{{ $t('rationName') }}</label>
          <input v-model="rationForm.name" type="text" class="owner-ration-name-input" required />
        </div>
        <div class="field">
          <label>{{ $t('kopNarx') }}</label>
          <FormattedNumberInput v-model="rationForm.kop_narx" :max-decimals="0" />
          <small class="field-hint">{{ $t('kopNarxHint') }}</small>
        </div>
      </div>

      <div class="owner-ration-line-grid">
        <article v-for="(line, index) in rationForm.items" :key="index" class="owner-ration-line-card">
          <div>
            <label>{{ $t('productName') }}</label>
            <select v-model="line.feed_product_id" required>
              <option value="">{{ $t('select') }}</option>
              <optgroup v-if="feedProducts.length" :label="$t('feedProductsSection')">
                <option v-for="p in feedProducts" :key="p.id" :value="p.id">{{ p.name }}</option>
              </optgroup>
              <optgroup v-if="vitaminProducts.length" :label="$t('vitaminsSection')">
                <option v-for="p in vitaminProducts" :key="p.id" :value="p.id">{{ p.name }}</option>
              </optgroup>
            </select>
          </div>
          <div>
            <label>{{ $t('qtyPer100kg') }}</label>
            <FormattedNumberInput v-model="line.qty_per_100kg" :max-decimals="3" required />
          </div>
          <div>
            <label>{{ $t('priceShort') }}</label>
            <div class="owner-ration-line-value">{{ formatMoney(linePrice(line)) }}</div>
          </div>
          <div>
            <label>{{ $t('sum') }}</label>
            <div class="owner-ration-line-value owner-ration-line-sum">{{ formatMoney(lineSum(line)) }}</div>
          </div>
          <button class="btn btn-outline btn-sm" type="button" :aria-label="$t('cancel')" @click="removeRationLine(index)">
            <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
          </button>
        </article>
      </div>

      <div class="owner-ration-summary-row">
        <div class="owner-ration-stat owner-ration-stat--wide">
          <span>{{ $t('sum100kg') }}</span>
          <strong>{{ formatMoney(previewTotals.total_sum) }}</strong>
        </div>
        <div class="owner-ration-stat">
          <span>{{ $t('tanNarx1kg') }}</span>
          <strong>{{ formatMoney(previewTotals.cost_per_kg) }}</strong>
        </div>
        <div v-if="previewTotals.cost_with_kop != null" class="owner-ration-stat">
          <span>{{ $t('costWithKop') }}</span>
          <strong>{{ formatMoney(previewTotals.cost_with_kop) }}</strong>
        </div>
      </div>

      <div class="actions ration-form-actions">
        <button class="btn btn-outline" type="button" @click="addRationLine">
          <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
          {{ $t('addRow') }}
        </button>
        <button class="btn btn-primary" type="submit" :disabled="rationSaving">
          {{ rationSaving ? $t('loading') : $t('saveRation') }}
        </button>
        <router-link class="btn btn-outline" :to="{ name: 'owner-ration-list' }">{{ $t('cancel') }}</router-link>
      </div>
      <p v-if="rationError" class="error">{{ rationError }}</p>
    </form>
  </section>
</template>

<script>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { showSaved } from '../../flash';
import { t } from '../../i18n';
import { formatMoney } from '../../utils/rationFormat';

export default {
  setup() {
    const route = useRoute();
    const router = useRouter();
    const loading = ref(true);
    const products = ref([]);
    const rationSaving = ref(false);
    const rationError = ref('');

    const isEdit = computed(() => !!route.params.id);

    const rationForm = reactive({
      name: '',
      kop_narx: '',
      notes: '',
      items: [{ feed_product_id: '', qty_per_100kg: '' }],
    });

    const feedProducts = computed(() => products.value.filter((p) => p.category !== 'vitamin'));
    const vitaminProducts = computed(() => products.value.filter((p) => p.category === 'vitamin'));
    const productMap = computed(() => Object.fromEntries(products.value.map((p) => [p.id, p])));

    function linePrice(line) {
      const p = productMap.value[line.feed_product_id];
      return p ? p.price_per_kg : 0;
    }

    function lineSum(line) {
      const qty = Number(line.qty_per_100kg) || 0;
      return qty * Number(linePrice(line));
    }

    const previewTotals = computed(() => {
      let qty = 0;
      let sum = 0;
      rationForm.items.forEach((line) => {
        const q = Number(line.qty_per_100kg) || 0;
        if (q <= 0 || !line.feed_product_id) return;
        qty += q;
        sum += lineSum(line);
      });
      const costPerKg = qty > 0 ? sum / qty : 0;
      const kop = rationForm.kop_narx !== '' ? Number(rationForm.kop_narx) : null;
      return {
        qty_per_100kg: qty,
        total_sum: sum,
        cost_per_kg: costPerKg,
        cost_with_kop: kop != null ? costPerKg + kop : null,
      };
    });

    async function load() {
      loading.value = true;
      try {
        const productsRes = await window.axios.get('/owner/rations/products');
        products.value = productsRes.data.products || [];

        if (isEdit.value) {
          const rationRes = await window.axios.get(`/owner/rations/${route.params.id}`);
          const ration = rationRes.data.ration;
          Object.assign(rationForm, {
            name: ration.name,
            kop_narx: ration.kop_narx != null ? String(ration.kop_narx) : '',
            notes: ration.notes || '',
            items: ration.items.map((line) => ({
              feed_product_id: line.product_id,
              qty_per_100kg: String(line.qty_per_100kg),
            })),
          });
        }
      } finally {
        loading.value = false;
      }
    }

    function addRationLine() {
      rationForm.items.push({ feed_product_id: '', qty_per_100kg: '' });
    }

    function removeRationLine(index) {
      rationForm.items.splice(index, 1);
      if (!rationForm.items.length) addRationLine();
    }

    async function saveRation() {
      rationError.value = '';
      rationSaving.value = true;
      try {
        const payload = {
          name: rationForm.name,
          kop_narx: rationForm.kop_narx !== '' ? Number(rationForm.kop_narx) : null,
          notes: rationForm.notes || null,
          items: rationForm.items
            .filter((line) => line.feed_product_id && Number(line.qty_per_100kg) > 0)
            .map((line) => ({
              feed_product_id: Number(line.feed_product_id),
              qty_per_100kg: Number(line.qty_per_100kg),
            })),
        };

        if (!payload.items.length) {
          rationError.value = t('rationItemsRequired');
          return;
        }

        let response;
        if (isEdit.value) {
          response = await window.axios.put(`/owner/rations/${route.params.id}`, payload);
        } else {
          response = await window.axios.post('/owner/rations', payload);
        }

        showSaved(t('saved'));
        const rationId = response.data.ration?.id || route.params.id;
        router.push({ name: 'owner-ration-show', params: { id: rationId } });
      } catch (e) {
        const errors = e.response?.data?.errors;
        rationError.value = errors ? Object.values(errors).flat().join(' ') : (e.response?.data?.message || t('noData'));
      } finally {
        rationSaving.value = false;
      }
    }

    onMounted(load);

    return {
      loading,
      products,
      feedProducts,
      vitaminProducts,
      rationForm,
      rationSaving,
      rationError,
      isEdit,
      previewTotals,
      formatMoney,
      linePrice,
      lineSum,
      addRationLine,
      removeRationLine,
      saveRation,
    };
  },
};
</script>

<style scoped>
.owner-ration-form-top {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  margin-bottom: 0.75rem;
}

.owner-ration-name-input {
  font-size: 1.2rem;
  font-weight: 600;
  padding: 0.6rem 0.75rem;
}

.ration-form-actions {
  margin-top: 1rem;
  display: flex;
  flex-direction: column;
  align-items: stretch;
  gap: 0.5rem;
}

@media (max-width: 700px) {
  .owner-ration-form-top {
    flex-direction: column;
  }
}
</style>
