<template>
  <div class="venger-results-hub venger-readable">
    <OwnerVengerNav v-if="isOwnerView" />
    <div class="page-head">
      <div>
        <h1>{{ $t('vengerResultsHubTitle') }}</h1>
        <p>{{ isOwnerView ? $t('ownerVengerResultsHint') : $t('vengerResultsHubHint') }}</p>
      </div>
    </div>

    <div class="panel">
      <div class="form-grid venger-hub-search">
        <div class="field">
          <label>{{ $t('vengerPickClient') }}</label>
          <input
            v-model="searchQ"
            type="search"
            :placeholder="$t('vengerSearchPlaceholder')"
            @input="onSearchInput"
          />
        </div>
        <div class="field">
          <label>{{ $t('region') }}</label>
          <select v-model="searchRegion" @change="load">
            <option value="">{{ $t('allRegions') }}</option>
            <option v-for="r in regions" :key="r" :value="r">{{ r }}</option>
          </select>
        </div>
      </div>
    </div>

    <div v-if="loading" class="empty">{{ $t('loading') }}</div>
    <div v-else-if="error" class="panel empty">{{ error }}</div>

    <div v-else class="panel">
      <div class="table-scroll">
        <table>
          <thead>
            <tr>
              <th>{{ $t('fullName') }}</th>
              <th>{{ $t('region') }}</th>
              <th>{{ $t('phone') }}</th>
              <th>{{ $t('vengerResultsTitle') }}</th>
              <th v-if="isOwnerView">{{ $t('vengerStaff') }}</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="c in clients" :key="c.id">
              <td><strong>{{ c.name }}</strong></td>
              <td>{{ c.region || '—' }}{{ c.district ? `, ${c.district}` : '' }}</td>
              <td class="mono">{{ formatPhone(c.phone) }}</td>
              <td>{{ number(c.results_count) }}</td>
              <td v-if="isOwnerView">{{ c.staff?.name || '—' }}</td>
              <td class="hub-actions">
                <router-link
                  class="btn btn-primary btn-sm"
                  :to="clientRoute(c.id)"
                >
                  {{ isOwnerView ? $t('open') : $t('vengerOpenClientWork') }}
                </router-link>
              </td>
            </tr>
            <tr v-if="!clients.length">
              <td :colspan="isOwnerView ? 6 : 5" class="empty">
                {{ searchActive ? $t('vengerSearchEmpty') : $t('noVengerClients') }}
                <router-link v-if="!isOwnerView" :to="{ name: 'venger-cabinet' }" class="hub-link">{{ $t('addVengerClient') }}</router-link>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script>
import { computed, onMounted, ref } from 'vue';
import OwnerVengerNav from '../../components/OwnerVengerNav.vue';
import auth from '../../auth';
import { uzbekistanDistricts } from '../../data/uzbekistanDistricts';
import { formatPhoneDisplay } from '../../utils/phone';
import { t } from '../../i18n';

export default {
  components: { OwnerVengerNav },
  setup() {
    const loading = ref(true);
    const error = ref('');
    const clients = ref([]);
    const searchQ = ref('');
    const searchRegion = ref('');
    const regions = Object.keys(uzbekistanDistricts);
    const isOwnerView = computed(() => auth.isOwner());
    const apiBase = computed(() => (auth.isOwner() ? '/owner/venger' : '/venger'));
    let searchTimer = null;

    const searchActive = computed(() => !!searchQ.value.trim() || !!searchRegion.value);

    function clientRoute(clientId) {
      return auth.isOwner()
        ? { name: 'owner-venger-client', params: { clientId }, query: { from: 'results' } }
        : { name: 'venger-client', params: { clientId }, query: { from: 'results' } };
    }

    function number(v) {
      const n = Number(v || 0);
      return Number.isFinite(n) ? n.toLocaleString() : '0';
    }

    function formatPhone(phone) {
      return formatPhoneDisplay(phone || '');
    }

    async function load() {
      loading.value = true;
      error.value = '';
      try {
        const params = {};
        if (searchQ.value.trim()) params.q = searchQ.value.trim();
        if (searchRegion.value) params.region = searchRegion.value;
        const { data } = await window.axios.get(`${apiBase.value}/clients`, { params });
        clients.value = data.clients || [];
      } catch (e) {
        error.value = e.response?.data?.message || t('errorGeneric');
      } finally {
        loading.value = false;
      }
    }

    function onSearchInput() {
      clearTimeout(searchTimer);
      searchTimer = setTimeout(load, 280);
    }

    onMounted(load);

    return {
      loading, error, clients, searchQ, searchRegion, regions, searchActive, isOwnerView,
      number, formatPhone, clientRoute, load, onSearchInput,
    };
  },
};
</script>

<style scoped>
.venger-hub-search {
  grid-template-columns: 1.6fr 1fr;
}
.hub-actions { text-align: right; }
.hub-link { display: inline-block; margin-left: 0.5rem; }
@media (max-width: 720px) {
  .venger-hub-search { grid-template-columns: 1fr; }
}
</style>
