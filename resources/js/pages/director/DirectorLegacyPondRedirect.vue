<template>
  <div />
</template>

<script>
import { onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { setDirectorFarmId } from '../../utils/directorFarm';

export default {
  setup() {
    const route = useRoute();
    const router = useRouter();

    onMounted(async () => {
      try {
        const { data } = await window.axios.get(`/director/ponds/${route.params.pondId}`);
        const farmId = data.farm_id;

        if (!farmId) {
          await router.replace({ name: 'director-home' });
          return;
        }

        setDirectorFarmId(farmId);
        const path = route.path;

        if (path.includes('/ombor/kirim')) {
          await router.replace({ name: 'director-warehouse-intake' });
          return;
        }

        if (path.includes('/ombor/zaxira')) {
          await router.replace({ name: 'director-warehouse-stock' });
          return;
        }

        if (path.includes('/ombor')) {
          await router.replace({ name: 'director-warehouse' });
          return;
        }

        if (path.includes('/moliya')) {
          await router.replace({ name: 'director-finance' });
          return;
        }

        await router.replace({ name: 'director-farm-hub' });
      } catch (e) {
        await router.replace({ name: 'director-home' });
      }
    });

    return {};
  },
};
</script>
