<template>
  <section class="panel ration-section">
    <div class="owner-ration-section-head">
      <h2>{{ $t('productPrices') }}</h2>
      <small>{{ $t('feedProductsHint') }} · {{ $t('clickToEditPrice') }}</small>
    </div>

    <div v-if="loading" class="empty">{{ $t('loading') }}</div>

    <template v-else>
      <form class="owner-ration-product-add" @submit.prevent="addProduct">
        <div class="field">
          <label>{{ $t('productName') }}</label>
          <input v-model="newProduct.name" type="text" required />
        </div>
        <div class="field">
          <label>{{ $t('pricePerKg') }}</label>
          <FormattedNumberInput v-model="newProduct.price_per_kg" :max-decimals="0" required />
        </div>
        <div class="field">
          <label>{{ $t('productCategory') }}</label>
          <select v-model="newProduct.category">
            <option value="feed">{{ $t('feedProductsSection') }}</option>
            <option value="vitamin">{{ $t('vitaminsSection') }}</option>
          </select>
        </div>
        <div class="actions">
          <button class="btn btn-primary" type="submit" :disabled="adding">
            {{ adding ? $t('loading') : $t('add') }}
          </button>
        </div>
      </form>

      <div class="product-price-stack">
        <ProductPriceTable
          :title="$t('feedProductsSection')"
          variant="feed"
          :products="feedProducts"
          :editing-id="editingPriceId"
          :saving-id="savingPriceId"
          v-model:draft="priceDraft"
          @start-edit="startPriceEdit"
          @save="savePrice"
          @cancel="cancelPriceEdit"
          @delete="removeProduct"
        />
        <ProductPriceTable
          :title="$t('vitaminsSection')"
          variant="vitamin"
          :products="vitaminProducts"
          :editing-id="editingPriceId"
          :saving-id="savingPriceId"
          v-model:draft="priceDraft"
          @start-edit="startPriceEdit"
          @save="savePrice"
          @cancel="cancelPriceEdit"
          @delete="removeProduct"
        />
      </div>
    </template>
  </section>
</template>

<script>
import { computed, onMounted, reactive, ref } from 'vue';
import { showSaved } from '../../flash';
import { t } from '../../i18n';
import ProductPriceTable from './ProductPriceTable.vue';

export default {
  components: { ProductPriceTable },
  setup() {
    const loading = ref(true);
    const products = ref([]);
    const adding = ref(false);
    const editingPriceId = ref(null);
    const savingPriceId = ref(null);
    const priceDraft = ref('');

    const newProduct = reactive({
      name: '',
      price_per_kg: '',
      category: 'feed',
    });

    const feedProducts = computed(() => products.value.filter((p) => p.category !== 'vitamin'));
    const vitaminProducts = computed(() => products.value.filter((p) => p.category === 'vitamin'));

    async function load() {
      loading.value = true;
      try {
        const { data } = await window.axios.get('/owner/rations/products');
        products.value = data.products || [];
      } finally {
        loading.value = false;
      }
    }

    function resetNewProduct() {
      Object.assign(newProduct, { name: '', price_per_kg: '', category: 'feed' });
    }

    async function addProduct() {
      adding.value = true;
      try {
        await window.axios.post('/owner/rations/products', {
          name: newProduct.name,
          price_per_kg: Number(newProduct.price_per_kg),
          category: newProduct.category,
        });
        showSaved(t('saved'));
        resetNewProduct();
        await load();
      } catch (e) {
        window.alert(e.response?.data?.message || t('noData'));
      } finally {
        adding.value = false;
      }
    }

    function startPriceEdit(product) {
      if (savingPriceId.value === product.id) return;
      editingPriceId.value = product.id;
      priceDraft.value = String(product.price_per_kg);
    }

    function cancelPriceEdit(product) {
      if (editingPriceId.value !== product.id) return;
      editingPriceId.value = null;
      priceDraft.value = '';
    }

    async function savePrice(product) {
      const isEditing = editingPriceId.value === product.id;
      const isSavingSame = savingPriceId.value === product.id;
      if (!isEditing && !isSavingSame) return;

      const nextPrice = Number(priceDraft.value);
      if (!Number.isFinite(nextPrice) || nextPrice < 0) {
        window.alert(t('invalidPrice'));
        return;
      }

      if (nextPrice === Number(product.price_per_kg)) {
        cancelPriceEdit(product);
        return;
      }

      savingPriceId.value = product.id;
      editingPriceId.value = null;
      try {
        const { data } = await window.axios.put(`/owner/rations/products/${product.id}`, {
          price_per_kg: nextPrice,
        });
        const index = products.value.findIndex((p) => p.id === product.id);
        if (index >= 0) products.value[index] = data.product;
        showSaved(t('saved'));
      } catch (e) {
        window.alert(e.response?.data?.message || t('noData'));
      } finally {
        savingPriceId.value = null;
        priceDraft.value = '';
      }
    }

    async function removeProduct(product) {
      if (!confirm(t('confirmDeleteProduct'))) return;
      try {
        await window.axios.delete(`/owner/rations/products/${product.id}`);
        showSaved(t('deleted'));
        await load();
      } catch (e) {
        window.alert(e.response?.data?.message || t('noData'));
      }
    }

    onMounted(load);

    return {
      loading,
      newProduct,
      adding,
      feedProducts,
      vitaminProducts,
      editingPriceId,
      savingPriceId,
      priceDraft,
      addProduct,
      startPriceEdit,
      cancelPriceEdit,
      savePrice,
      removeProduct,
    };
  },
};
</script>

<style scoped>
.product-price-stack {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}
</style>
