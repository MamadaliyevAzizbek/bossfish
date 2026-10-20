<template>
  <WorkerShell>
    <div class="page-head">
      <div>
        <h1>{{ $t('dailyReport') }}</h1>
        <p>{{ $t('dailyReportDescription') }}</p>
      </div>
      <router-link class="btn btn-outline btn-sm" :to="{ name: 'worker-home' }">{{ $t('back') }}</router-link>
    </div>

    <PondDailyReportForm v-if="pondId" :pond-id="pondId" api-prefix="/worker" @saved="$router.push({ name: 'worker-home' })" />
  </WorkerShell>
</template>

<script>
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import WorkerShell from '../../components/WorkerShell.vue';
import PondDailyReportForm from '../../components/PondDailyReportForm.vue';

export default {
  components: { WorkerShell, PondDailyReportForm },
  setup() {
    const route = useRoute();
    const pondId = ref(null);

    onMounted(async () => {
      if (route.query.pondId) {
        pondId.value = route.query.pondId;
        return;
      }
      const { data } = await window.axios.get('/worker/me');
      pondId.value = localStorage.getItem('worker_pond_id') || data.pond?.id || null;
    });

    return { pondId };
  },
};
</script>
