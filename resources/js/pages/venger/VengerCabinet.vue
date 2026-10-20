<template>
  <div class="venger-clients venger-readable">
    <OwnerVengerNav v-if="isOwnerView" />
    <div class="page-head">
      <div>
        <h1>{{ $t('vengerClientsTitle') }}</h1>
        <p>{{ isOwnerView ? $t('ownerVengerClientsHint') : $t('vengerClientsOnlyHint') }}</p>
      </div>
      <button v-if="!isOwnerView" class="btn btn-primary" type="button" @click="openCreate"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg> {{ $t('addVengerClient') }}</button>
    </div>

    <div class="panel venger-search">
      <div class="form-grid venger-search__grid">
        <div class="field">
          <label>{{ $t('vengerSearchClients') }}</label>
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
      <p class="field-hint">
        {{ $t('vengerSearchResultCount', { n: filteredCount, total: stats.clients_count }) }}
      </p>
    </div>

    <div v-if="loading" class="empty">{{ $t('loading') }}</div>
    <div v-else-if="error" class="panel empty">{{ error }}</div>

    <template v-else>
      <div class="panel">
        <div class="table-scroll">
          <table>
            <thead>
              <tr>
                <th>{{ $t('fullName') }}</th>
                <th>{{ $t('region') }}</th>
                <th>{{ $t('district') }}</th>
                <th>{{ $t('phone') }}</th>
                <th v-if="isOwnerView">{{ $t('vengerStaff') }}</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="c in clients" :key="c.id">
                <td><strong>{{ c.name }}</strong></td>
                <td>{{ c.region || '—' }}</td>
                <td>{{ c.district || '—' }}</td>
                <td class="mono">{{ formatPhone(c.phone) }}</td>
                <td v-if="isOwnerView">{{ c.staff?.name || '—' }}</td>
                <td>
                  <router-link class="btn btn-outline btn-sm" :to="clientRoute(c.id)">
                    {{ $t('open') }}
                  </router-link>
                </td>
              </tr>
              <tr v-if="!clients.length">
                <td :colspan="isOwnerView ? 6 : 5" class="empty">{{ searchActive ? $t('vengerSearchEmpty') : $t('noVengerClients') }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>

    <div v-if="modal" class="modal-backdrop" @click.self="modal = false">
      <div class="modal">
        <h2 class="modal-title">{{ $t('addVengerClient') }}</h2>
        <form class="form-grid one-col" @submit.prevent="save">
          <div class="field">
            <label>{{ $t('firstName') }} <span class="req">*</span></label>
            <input v-model="form.first_name" required maxlength="120" :placeholder="$t('firstNamePlaceholder')" />
          </div>
          <div class="field">
            <label>{{ $t('lastName') }} <span class="req">*</span></label>
            <input v-model="form.last_name" required maxlength="120" :placeholder="$t('lastNamePlaceholder')" />
          </div>
          <RegionDistrictPicker
            :region="form.region"
            :district="form.district"
            @update:region="form.region = $event"
            @update:district="form.district = $event"
          />
          <div class="field">
            <label>{{ $t('address') }}</label>
            <input v-model="form.address" maxlength="500" :placeholder="$t('addressDetailPlaceholder')" />
          </div>
          <div class="field">
            <label>{{ $t('phone') }} <span class="req">*</span></label>
            <PhoneInput v-model="form.phone" required />
          </div>
          <div class="field">
            <label>{{ $t('notes') }}</label>
            <textarea v-model="form.notes" rows="3" maxlength="2000" />
          </div>
          <p v-if="formError" class="error">{{ formError }}</p>
          <div class="actions">
            <button class="btn btn-primary" type="submit">{{ $t('save') }}</button>
            <button class="btn btn-outline" type="button" @click="modal = false">{{ $t('cancel') }}</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script>
import { computed, onMounted, reactive, ref } from 'vue';
import PhoneInput from '../../components/PhoneInput.vue';
import RegionDistrictPicker from '../../components/RegionDistrictPicker.vue';
import OwnerVengerNav from '../../components/OwnerVengerNav.vue';
import auth from '../../auth';
import { uzbekistanDistricts } from '../../data/uzbekistanDistricts';
import { formatPhoneDisplay, isCompletePhone } from '../../utils/phone';
import { t } from '../../i18n';

const emptyForm = () => ({
  first_name: '',
  last_name: '',
  address: '',
  region: '',
  district: '',
  phone: '+998',
  notes: '',
});

export default {
  components: { PhoneInput, RegionDistrictPicker, OwnerVengerNav },
  setup() {
    const loading = ref(true);
    const error = ref('');
    const formError = ref('');
    const clients = ref([]);
    const stats = ref({ clients_count: 0, results_count: 0, filtered_count: 0 });
    const modal = ref(false);
    const form = reactive(emptyForm());
    const searchQ = ref('');
    const searchRegion = ref('');
    const regions = Object.keys(uzbekistanDistricts);
    const isOwnerView = computed(() => auth.isOwner());
    const apiBase = computed(() => (auth.isOwner() ? '/owner/venger' : '/venger'));
    let searchTimer = null;

    const filteredCount = computed(() => Number(stats.value.filtered_count ?? clients.value.length));
    const searchActive = computed(() => !!searchQ.value.trim() || !!searchRegion.value);

    function clientRoute(clientId) {
      return auth.isOwner()
        ? { name: 'owner-venger-client', params: { clientId } }
        : { name: 'venger-client', params: { clientId } };
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
        stats.value = data.stats || { clients_count: 0, results_count: 0, filtered_count: clients.value.length };
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

    function openCreate() {
      Object.assign(form, emptyForm());
      formError.value = '';
      modal.value = true;
    }

    async function save() {
      formError.value = '';
      if (!form.first_name.trim() || !form.last_name.trim()) {
        formError.value = t('fullNameRequired');
        return;
      }
      if (!form.region) {
        formError.value = t('selectRegionFirst');
        return;
      }
      if (!form.district) {
        formError.value = t('districtRequired');
        return;
      }
      if (!isCompletePhone(form.phone)) {
        formError.value = t('phoneIncompleteFull');
        return;
      }
      try {
        await window.axios.post('/venger/clients', {
          first_name: form.first_name.trim(),
          last_name: form.last_name.trim(),
          address: form.address || null,
          region: form.region,
          district: form.district,
          phone: form.phone,
          notes: form.notes || null,
        });
        modal.value = false;
        await load();
      } catch (e) {
        const errors = e.response?.data?.errors;
        formError.value = errors ? Object.values(errors).flat().join(' ') : (e.response?.data?.message || t('errorGeneric'));
      }
    }

    onMounted(load);

    return {
      loading, error, formError, clients, stats, modal, form,
      searchQ, searchRegion, regions, filteredCount, searchActive, isOwnerView,
      formatPhone, clientRoute, openCreate, save, load, onSearchInput,
    };
  },
};
</script>

<style scoped>
.venger-search { margin-bottom: 1rem; }
.venger-search__grid {
  grid-template-columns: 1.6fr 1fr;
}
@media (max-width: 720px) {
  .venger-search__grid { grid-template-columns: 1fr; }
}
</style>
