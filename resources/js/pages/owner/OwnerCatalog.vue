<template>
  <div class="owner-catalog page">
    <div class="page-head">
      <div>
        <h1>{{ $t('ownerCatalogTitle') }}</h1>
        <p>{{ $t('ownerCatalogHint') }}</p>
      </div>
    </div>

    <div class="owner-catalog-tabs panel">
      <button
        v-for="tab in tabs"
        :key="tab.id"
        type="button"
        class="owner-catalog-tab"
        :class="{ 'is-active': activeType === tab.id }"
        @click="activeType = tab.id"
      >
        {{ $t(tab.labelKey) }}
        <small>{{ countByType(tab.id) }}</small>
      </button>
    </div>

    <div v-if="loading" class="empty">{{ $t('loading') }}</div>
    <div v-else-if="error" class="panel empty">{{ error }}</div>

    <template v-else>
      <section class="owner-catalog-grid">
        <article v-for="p in filtered" :key="p.id" class="owner-catalog-card panel">
          <div class="owner-catalog-card__media">
            <img v-if="p.image" :src="p.image" :alt="p.name" />
            <div v-else class="owner-catalog-card__placeholder">{{ typeLabel(p.type) }}</div>
          </div>
          <div class="owner-catalog-card__body">
            <span class="owner-catalog-card__type">{{ typeLabel(p.type) }}</span>
            <h3>{{ p.name }}</h3>
            <p v-if="p.slogan" class="owner-catalog-card__slogan">{{ p.slogan }}</p>
            <p v-if="p.usage_text" class="owner-catalog-card__usage">{{ p.usage_text }}</p>

            <label class="field">
              <span>{{ $t('ownerCatalogPriceOnly') }}</span>
              <div class="owner-catalog-price-row">
                <FormattedNumberInput v-model="priceDrafts[p.id]" :max-decimals="0" />
                <input v-model="unitDrafts[p.id]" type="text" class="owner-catalog-unit" :placeholder="$t('ownerCatalogPriceUnitPh')" />
                <button
                  type="button"
                  class="btn btn-primary btn-sm"
                  :disabled="savingId === p.id"
                  @click="savePrice(p)"
                >
                  {{ savingId === p.id ? $t('saving') : $t('save') }}
                </button>
              </div>
            </label>
            <p v-if="priceMsg[p.id]" class="owner-catalog-msg">{{ priceMsg[p.id] }}</p>
          </div>
        </article>

        <div v-if="!filtered.length" class="empty panel">{{ $t('ownerCatalogEmpty') }}</div>
      </section>

      <section class="panel owner-catalog-add">
        <h2>{{ $t('ownerCatalogAddTitle') }}</h2>
        <p class="field-hint">{{ $t('ownerCatalogAddHint') }}</p>

        <form class="owner-catalog-add-form" @submit.prevent="addProduct">
          <div class="field">
            <label>{{ $t('ownerCatalogType') }}</label>
            <select v-model="form.type" required>
              <option value="feed">{{ $t('ownerCatalogTypeFeed') }}</option>
              <option value="medicine">{{ $t('ownerCatalogTypeMedicine') }}</option>
              <option value="equipment">{{ $t('ownerCatalogTypeEquipment') }}</option>
            </select>
          </div>
          <div class="field">
            <label>{{ $t('ownerCatalogName') }}</label>
            <input v-model="form.name" type="text" required maxlength="255" />
          </div>
          <div class="field">
            <label>{{ $t('ownerCatalogSlogan') }}</label>
            <input v-model="form.slogan" type="text" maxlength="255" />
          </div>
          <div class="field">
            <label>{{ $t('price') }}</label>
            <FormattedNumberInput v-model="form.price" :max-decimals="0" required />
          </div>
          <div class="field">
            <label>{{ $t('ownerCatalogPriceUnit') }}</label>
            <input v-model="form.price_unit" type="text" :placeholder="$t('ownerCatalogPriceUnitPh')" />
          </div>
          <div class="field">
            <label>{{ $t('ownerCatalogUsage') }}</label>
            <input v-model="form.usage_text" type="text" maxlength="1000" />
          </div>
          <div class="field">
            <label>{{ $t('ownerCatalogSpecs') }}</label>
            <input v-model="form.specsText" type="text" :placeholder="$t('ownerCatalogSpecsPh')" />
          </div>
          <div class="field field--full">
            <label>{{ $t('description') }}</label>
            <textarea v-model="form.description" rows="3" maxlength="5000" />
          </div>
          <div class="field field--full">
            <label>{{ $t('ownerCatalogImage') }}</label>
            <input type="file" accept="image/*" @change="onImage" />
          </div>
          <div v-if="addError" class="form-error field--full">{{ addError }}</div>
          <div class="owner-catalog-add-actions field--full">
            <button class="btn btn-primary" type="submit" :disabled="adding">
              {{ adding ? $t('saving') : $t('ownerCatalogAddBtn') }}
            </button>
          </div>
        </form>
      </section>
    </template>
  </div>
</template>

<script>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import FormattedNumberInput from '../../components/FormattedNumberInput.vue';
import { t } from '../../i18n';

const emptyForm = () => ({
  type: 'medicine',
  name: '',
  slogan: '',
  description: '',
  price: '',
  price_unit: "so‘m / qop",
  usage_text: '',
  specsText: '',
  image: null,
});

export default {
  components: { FormattedNumberInput },
  setup() {
    const loading = ref(true);
    const error = ref('');
    const products = ref([]);
    const activeType = ref('medicine');
    const priceDrafts = reactive({});
    const unitDrafts = reactive({});
    const priceMsg = reactive({});
    const savingId = ref(null);
    const form = reactive(emptyForm());
    const adding = ref(false);
    const addError = ref('');

    const tabs = [
      { id: 'medicine', labelKey: 'ownerCatalogTypeMedicine' },
      { id: 'feed', labelKey: 'ownerCatalogTypeFeed' },
      { id: 'equipment', labelKey: 'ownerCatalogTypeEquipment' },
    ];

    const filtered = computed(() => products.value.filter((p) => p.type === activeType.value && p.is_active !== false));

    function countByType(type) {
      return products.value.filter((p) => p.type === type && p.is_active !== false).length;
    }

    function typeLabel(type) {
      if (type === 'feed') return t('ownerCatalogTypeFeed');
      if (type === 'equipment') return t('ownerCatalogTypeEquipment');
      return t('ownerCatalogTypeMedicine');
    }

    function syncDrafts(list) {
      list.forEach((p) => {
        priceDrafts[p.id] = p.price;
        unitDrafts[p.id] = p.price_unit || "so‘m / qop";
      });
    }

    async function load() {
      loading.value = true;
      error.value = '';
      try {
        const { data } = await window.axios.get('/owner/catalog/products');
        products.value = data.products || [];
        syncDrafts(products.value);
      } catch (e) {
        error.value = e.response?.data?.message || t('errorGeneric');
      } finally {
        loading.value = false;
      }
    }

    async function savePrice(p) {
      savingId.value = p.id;
      priceMsg[p.id] = '';
      try {
        const { data } = await window.axios.put(`/owner/catalog/products/${p.id}`, {
          price: Number(priceDrafts[p.id] || 0),
          price_unit: unitDrafts[p.id] || null,
        });
        const idx = products.value.findIndex((x) => x.id === p.id);
        if (idx >= 0) products.value[idx] = data;
        priceDrafts[p.id] = data.price;
        unitDrafts[p.id] = data.price_unit;
        priceMsg[p.id] = t('saved');
      } catch (e) {
        priceMsg[p.id] = e.response?.data?.message || t('errorGeneric');
      } finally {
        savingId.value = null;
      }
    }

    function onImage(e) {
      form.image = e.target.files?.[0] || null;
    }

    async function addProduct() {
      adding.value = true;
      addError.value = '';
      try {
        const fd = new FormData();
        fd.append('type', form.type);
        fd.append('name', form.name);
        if (form.slogan) fd.append('slogan', form.slogan);
        if (form.description) fd.append('description', form.description);
        fd.append('price', String(Number(form.price || 0)));
        if (form.price_unit) fd.append('price_unit', form.price_unit);
        if (form.usage_text) fd.append('usage_text', form.usage_text);
        const specs = form.specsText.split(',').map((s) => s.trim()).filter(Boolean);
        specs.forEach((s, i) => fd.append(`specs[${i}]`, s));
        if (form.image) fd.append('image', form.image);

        const { data } = await window.axios.post('/owner/catalog/products', fd, {
          headers: { 'Content-Type': 'multipart/form-data' },
        });
        products.value.push(data);
        syncDrafts([data]);
        activeType.value = data.type;
        Object.assign(form, emptyForm());
      } catch (e) {
        const errors = e.response?.data?.errors;
        addError.value = errors ? Object.values(errors).flat().join(' ') : (e.response?.data?.message || t('errorGeneric'));
      } finally {
        adding.value = false;
      }
    }

    watch(activeType, () => {
      addError.value = '';
    });

    onMounted(load);

    return {
      loading, error, products, activeType, tabs, filtered,
      priceDrafts, unitDrafts, priceMsg, savingId,
      form, adding, addError,
      countByType, typeLabel, savePrice, onImage, addProduct,
    };
  },
};
</script>

<style scoped>
.owner-catalog-tabs {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  margin-bottom: 1rem;
  padding: 0.65rem;
}
.owner-catalog-tab {
  border: 1px solid rgba(10, 60, 40, 0.12);
  background: #f4faf6;
  border-radius: 12px;
  padding: 0.55rem 0.85rem;
  font-weight: 750;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
}
.owner-catalog-tab small {
  background: rgba(13, 138, 82, 0.12);
  color: #065c36;
  border-radius: 999px;
  padding: 0.1rem 0.45rem;
  font-size: 0.72rem;
}
.owner-catalog-tab.is-active {
  background: #0d8a52;
  color: #fff;
  border-color: transparent;
}
.owner-catalog-tab.is-active small {
  background: rgba(255, 255, 255, 0.22);
  color: #fff;
}
.owner-catalog-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 0.85rem;
  margin-bottom: 1.25rem;
}
.owner-catalog-card {
  display: grid;
  gap: 0.75rem;
  padding: 0.85rem;
}
.owner-catalog-card__media {
  border-radius: 14px;
  overflow: hidden;
  aspect-ratio: 16 / 10;
  background: #eef6f1;
}
.owner-catalog-card__media img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}
.owner-catalog-card__placeholder {
  height: 100%;
  display: grid;
  place-items: center;
  font-weight: 800;
  color: #4d6558;
}
.owner-catalog-card__type {
  font-size: 0.72rem;
  font-weight: 800;
  color: #0d8a52;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
.owner-catalog-card h3 {
  margin: 0.2rem 0 0.35rem;
  font-size: 1.05rem;
}
.owner-catalog-card__slogan,
.owner-catalog-card__usage {
  margin: 0 0 0.45rem;
  font-size: 0.84rem;
  color: #4d6558;
  line-height: 1.4;
}
.owner-catalog-price-row {
  display: grid;
  grid-template-columns: 1fr 1fr auto;
  gap: 0.4rem;
  align-items: center;
}
.owner-catalog-unit {
  min-width: 0;
}
.owner-catalog-msg {
  margin: 0.35rem 0 0;
  font-size: 0.78rem;
  font-weight: 700;
  color: #0d8a52;
}
.owner-catalog-add h2 {
  margin: 0 0 0.25rem;
}
.owner-catalog-add-form {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.75rem;
  margin-top: 0.85rem;
}
.field--full { grid-column: 1 / -1; }
.owner-catalog-add-actions { display: flex; justify-content: flex-end; }
.form-error { color: #b84343; font-weight: 700; font-size: 0.88rem; }
@media (max-width: 720px) {
  .owner-catalog-add-form { grid-template-columns: 1fr; }
  .owner-catalog-price-row { grid-template-columns: 1fr; }
}
</style>
