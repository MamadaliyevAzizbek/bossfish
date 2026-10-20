<template>
  <div class="owner-ration-product-group">
    <h3>{{ title }}</h3>

    <div v-if="!products.length" class="empty">{{ $t('noData') }}</div>

    <div v-else class="owner-ration-product-grid">
      <article
        v-for="product in products"
        :key="product.id"
        class="owner-ration-product-card"
        :class="{ 'owner-ration-product-card--vitamin': variant === 'vitamin' }"
      >
        <h4>{{ product.name }}</h4>

        <div>
          <label class="sr-only">{{ $t('pricePerKg') }}</label>
          <FormattedNumberInput
            v-if="editingId === product.id"
            :ref="setPriceInput"
            :model-value="draft"
            :max-decimals="0"
            class="owner-ration-product-price-input"
            @update:model-value="$emit('update:draft', $event)"
            @blur="$emit('save', product)"
            @keydown.enter.prevent="$emit('save', product)"
            @keydown.esc.prevent="$emit('cancel', product)"
          />
          <button
            v-else
            type="button"
            class="owner-ration-product-price-btn"
            :disabled="savingId === product.id"
            @click="$emit('start-edit', product)"
          >
            {{ formatMoney(product.price_per_kg) }}
          </button>
          <small class="field-hint">{{ $t('pricePerKg') }}</small>
        </div>

        <button class="btn btn-outline btn-sm" type="button" @click="$emit('delete', product)">
          {{ $t('delete') }}
        </button>
      </article>
    </div>
  </div>
</template>

<script>
import { nextTick } from 'vue';
import { formatMoney } from '../../utils/rationFormat';

export default {
  props: {
    title: { type: String, required: true },
    products: { type: Array, required: true },
    variant: { type: String, default: 'feed' },
    editingId: { type: [Number, null], default: null },
    savingId: { type: [Number, null], default: null },
    draft: { type: String, default: '' },
  },
  emits: ['start-edit', 'save', 'cancel', 'delete', 'update:draft'],
  setup() {
    function setPriceInput(el) {
      if (!el) return;
      nextTick(() => {
        el.inputEl?.focus?.();
        el.inputEl?.select?.();
      });
    }

    return { formatMoney, setPriceInput };
  },
};
</script>

<style scoped>
.owner-ration-product-group h3 {
  margin: 0 0 0.85rem;
  font-size: 1.2rem;
  font-weight: 700;
  padding-left: 0.65rem;
  border-left: 3px solid rgba(106, 159, 212, 0.65);
  color: #fff;
}

.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  border: 0;
}
</style>
