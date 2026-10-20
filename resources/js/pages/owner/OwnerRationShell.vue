<template>
  <div class="owner-rations-page">
    <div class="page-head">
      <div>
        <h1>{{ $t('rations') }}</h1>
        <p>{{ $t('rationsHint') }}</p>
      </div>
    </div>

    <nav class="owner-ration-nav panel">
      <router-link
        :to="{ name: 'owner-ration-products' }"
        :class="{ 'router-link-active': productsActive }"
      >
        {{ $t('productPrices') }}
      </router-link>
      <router-link
        :to="{ name: 'owner-ration-prices' }"
        :class="{ 'router-link-active': pricesActive }"
      >
        {{ $t('rationPrices') }}
      </router-link>
      <router-link
        :to="{ name: 'owner-ration-list' }"
        :class="{ 'router-link-active': listActive }"
      >
        {{ $t('savedRations') }}
      </router-link>
      <router-link
        :to="{ name: 'owner-ration-new' }"
        :class="{ 'router-link-active': newActive }"
      >
        {{ $t('newRation') }}
      </router-link>
    </nav>

    <router-view />
  </div>
</template>

<script>
import { computed } from 'vue';
import { useRoute } from 'vue-router';

export default {
  setup() {
    const route = useRoute();
    const productsActive = computed(() => route.name === 'owner-ration-products');
    const pricesActive = computed(() => route.name === 'owner-ration-prices');
    const listActive = computed(() => ['owner-ration-list', 'owner-ration-show'].includes(route.name));
    const newActive = computed(() => ['owner-ration-new', 'owner-ration-edit'].includes(route.name));

    return { productsActive, pricesActive, listActive, newActive };
  },
};
</script>
