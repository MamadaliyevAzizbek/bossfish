<template>
  <WorkerShell>
    <div v-if="loading" class="empty">{{ $t('loading') }}</div>

    <template v-else-if="farm">
      <div class="page-head">
        <div>
          <h1>{{ $t('workerCabinet') }}</h1>
          <p>{{ farm.name }} · {{ farm.region }}</p>
        </div>
      </div>

      <div v-if="ponds.length > 1" class="panel" style="margin-bottom: 1rem">
        <label class="field">
          <span>{{ $t('selectPond') }}</span>
          <select v-model="selectedPondId" @change="savePondChoice">
            <option v-for="p in ponds" :key="p.id" :value="p.id">
              {{ p.name }} · {{ p.area_m2 }} {{ $t('hectare') }}
            </option>
          </select>
        </label>
      </div>

      <div v-if="activePond" class="panel" style="margin-bottom: 1rem">
        <p><strong>{{ activePond.name }}</strong> · {{ activePond.area_m2 }} {{ $t('hectare') }} · {{ $t('stock') }}: {{ activePond.current_stock || 0 }}</p>
      </div>

      <div class="action-grid">
        <router-link
          class="action-card action-daily"
          :to="{ name: 'worker-daily', query: { pondId: selectedPondId } }"
        >
          <ActionNavIcon name="daily" />
          <div class="action-card__body">
            <h3>{{ $t('dailyReport') }}</h3>
            <p>{{ $t('dailyReportWarehouseHint') }}</p>
          </div>
        </router-link>
        <router-link
          class="action-card action-main"
          :to="{ name: 'worker-main-report', query: { pondId: selectedPondId } }"
        >
          <ActionNavIcon name="main" />
          <div class="action-card__body">
            <h3>{{ $t('mainReport') }}</h3>
            <p>{{ $t('mainReportDescription') }}</p>
          </div>
        </router-link>
      </div>

      <div class="panel" style="margin-top: 1rem">
        <h2>{{ $t('workerCanDoTitle') }}</h2>
        <ul class="worker-capabilities">
          <li v-for="item in capabilityItems" :key="item">{{ item }}</li>
        </ul>
        <p class="field-hint">{{ $t('workerCannotDoHint') }}</p>
      </div>
    </template>

    <div v-else class="panel empty">
      <p>{{ $t('workerNoFarm') }}</p>
    </div>
  </WorkerShell>
</template>

<script>
import { computed, onMounted, ref } from 'vue';
import ActionNavIcon from '../components/ActionNavIcon.vue';
import WorkerShell from '../components/WorkerShell.vue';
import { t } from '../i18n';

export default {
  components: { ActionNavIcon, WorkerShell },
  setup() {
    const loading = ref(true);
    const farm = ref(null);
    const ponds = ref([]);
    const selectedPondId = ref(null);

    const activePond = computed(() => ponds.value.find((p) => p.id === selectedPondId.value) || null);

    const capabilityItems = computed(() => [
      t('workerCanDaily'),
      t('workerCanMain'),
      t('workerCanWarehouseUse'),
    ]);

    function savePondChoice() {
      if (selectedPondId.value) {
        localStorage.setItem('worker_pond_id', String(selectedPondId.value));
      }
    }

    onMounted(async () => {
      try {
        const { data } = await window.axios.get('/worker/me');
        farm.value = data.farm;
        ponds.value = data.ponds || [];
        const saved = localStorage.getItem('worker_pond_id');
        const savedId = saved ? Number(saved) : null;
        const match = ponds.value.find((p) => p.id === savedId);
        selectedPondId.value = match?.id || data.pond?.id || ponds.value[0]?.id || null;
        savePondChoice();
      } finally {
        loading.value = false;
      }
    });

    return {
      loading,
      farm,
      ponds,
      selectedPondId,
      activePond,
      capabilityItems,
      savePondChoice,
    };
  },
};
</script>
