<template>
  <DirectorShell>
    <div class="branch-grid">
      <button type="button" class="branch-card branch-intake" @click="go('director-warehouse-intake')">
        <div class="boss-hub-card__body">
          <HubSectionMark section="intake" />
          <h2>{{ $t('warehouseIntake') }}</h2>
        </div>
        <span class="boss-nav-card__arrow" aria-hidden="true"><svg viewBox="0 0 12 12" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 2l4 4-4 4"/></svg></span>
      </button>
      <button type="button" class="branch-card branch-stock" @click="go('director-warehouse-stock')">
        <div class="boss-hub-card__body">
          <HubSectionMark section="stock" />
          <h2>{{ $t('warehouseReserve') }}</h2>
        </div>
        <span class="boss-nav-card__arrow" aria-hidden="true"><svg viewBox="0 0 12 12" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 2l4 4-4 4"/></svg></span>
      </button>
    </div>
  </DirectorShell>
</template>

<script>
import { useRouter } from 'vue-router';
import DirectorShell from '../../components/DirectorShell.vue';
import HubSectionMark from '../../components/HubSectionMark.vue';

export default {
  components: { DirectorShell, HubSectionMark },
  setup() {
    const router = useRouter();

    function go(name) {
      router.push({ name });
    }

    return { go };
  },
};
</script>
