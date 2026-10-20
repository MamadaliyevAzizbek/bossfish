<template>
  <DirectorShell>
    <div class="page-head page-head--toolbar">
      <strong v-if="pond?.name" class="page-head__context">{{ pond.name }}</strong>
      <router-link class="btn btn-outline btn-sm" :to="backLink">{{ $t('back') }}</router-link>
    </div>

    <div v-if="pond?.closed_at" class="panel pond-closed-banner">
      <h2>{{ $t('pondClosedTitle') }}</h2>
      <p>{{ $t('pondClosedHint') }}</p>
      <div class="pond-closed-banner__actions">
        <router-link class="btn btn-outline btn-sm" :to="{ name: 'director-activities', params: { pondId } }">
          {{ $t('finalReportArchive') }}
        </router-link>
        <router-link
          class="btn btn-primary btn-sm"
          :to="{ name: 'director-fishery-ponds', query: { seasonFrom: pondId } }"
        >
          {{ $t('openNextSeason') }}
        </router-link>
      </div>
    </div>

    <div v-else-if="pond?.has_pending_closure_request" class="panel closure-pending-banner">
      <h2>{{ $t('closureRequestPendingTitle') }}</h2>
      <p>{{ $t('pondLockedPendingClosure') }}</p>
      <router-link class="btn btn-outline btn-sm" :to="{ name: 'director-close-pond', params: { pondId } }">
        {{ $t('closureRequestStatus') }}
      </router-link>
      <router-link class="btn btn-outline btn-sm" :to="{ name: 'director-activities', params: { pondId } }">
        {{ $t('reports') }}
      </router-link>
    </div>

    <div v-else class="action-grid">
      <router-link class="action-card action-daily" :to="{ name: 'director-daily', params: { pondId } }">
        <ActionNavIcon name="daily" />
        <div class="action-card__body">
          <h3>{{ $t('dailyReport') }}</h3>
        </div>
      </router-link>
      <router-link class="action-card action-main" :to="{ name: 'director-main-report', params: { pondId } }">
        <ActionNavIcon name="main" />
        <div class="action-card__body">
          <h3>{{ $t('mainReport') }}</h3>
        </div>
      </router-link>
      <router-link class="action-card action-add" :to="{ name: 'director-add-fish', params: { pondId } }">
        <ActionNavIcon name="add" />
        <div class="action-card__body">
          <h3>{{ $t('addFish') }}</h3>
        </div>
      </router-link>
      <router-link class="action-card action-sale" :to="{ name: 'director-sale-loss', params: { pondId } }">
        <ActionNavIcon name="sale" />
        <div class="action-card__body">
          <h3>{{ $t('saleOrLoss') }}</h3>
        </div>
      </router-link>
      <router-link class="action-card action-reports" :to="{ name: 'director-activities', params: { pondId } }">
        <ActionNavIcon name="reports" />
        <div class="action-card__body">
          <h3>{{ $t('reports') }}</h3>
        </div>
      </router-link>
      <router-link class="action-card action-close-pond" :to="{ name: 'director-close-pond', params: { pondId } }">
        <ActionNavIcon name="close" />
        <div class="action-card__body">
          <h3>{{ $t('closePond') }}</h3>
        </div>
      </router-link>
    </div>
  </DirectorShell>
</template>

<script>
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import ActionNavIcon from '../../components/ActionNavIcon.vue';
import DirectorShell from '../../components/DirectorShell.vue';
import { setDirectorFarmId } from '../../utils/directorFarm';

export default {
  components: { ActionNavIcon, DirectorShell },
  setup() {
    const route = useRoute();
    const router = useRouter();
    const pondId = computed(() => route.params.pondId);
    const pond = ref(null);
    const backLink = computed(() => {
      const farmId = pond.value?.farm_id || localStorage.getItem('director_farm_id');
      if (farmId) {
        return { name: 'director-fishery-ponds' };
      }
      return { name: 'director-home' };
    });

    onMounted(async () => {
      const { data } = await window.axios.get(`/director/ponds/${pondId.value}`);
      pond.value = data;
      if (data.farm_id) {
        setDirectorFarmId(data.farm_id);
      }
      if (data.closed_at) {
        router.replace({ name: 'director-activities', params: { pondId: pondId.value } });
      }
    });

    return { pond, pondId, backLink };
  },
};
</script>
