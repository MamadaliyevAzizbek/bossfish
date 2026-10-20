<template>
  <DirectorShell>
    <div class="page-head page-head--toolbar">
      <router-link class="btn btn-outline btn-sm" :to="{ name: 'director-fishery', params: { pondId } }">{{ $t('back') }}</router-link>
    </div>

    <PondDailyReportForm :pond-id="pondId" api-prefix="/director" />
  </DirectorShell>
</template>

<script>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import DirectorShell from '../../components/DirectorShell.vue';
import PondDailyReportForm from '../../components/PondDailyReportForm.vue';

export default {
  components: { DirectorShell, PondDailyReportForm },
  setup() {
    const route = useRoute();
    const pondId = computed(() => route.params.pondId);
    return { pondId };
  },
};
</script>
