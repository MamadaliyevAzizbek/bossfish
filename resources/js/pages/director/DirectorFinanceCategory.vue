<template>
  <div class="panel empty">{{ $t('loading') }}</div>
</template>

<script>
import { onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';

export default {
  setup() {
    const route = useRoute();
    const router = useRouter();

    onMounted(() => {
      router.replace({
        name: 'director-finance-entry',
        params: { category: route.params.category },
      });
    });
  },
};
</script>
