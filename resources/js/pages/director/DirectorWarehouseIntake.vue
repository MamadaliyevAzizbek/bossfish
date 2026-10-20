<template>
  <DirectorShell>
    <div class="page-head page-head--toolbar">
      <router-link class="btn btn-outline btn-sm" :to="{ name: 'director-warehouse' }">{{ $t('back') }}</router-link>
    </div>

    <div class="panel warehouse-intake-panel">
      <div class="warehouse-intake-block">
        <h2>{{ $t('warehouseEquipment') }}</h2>
        <p class="field-hint warehouse-finance-note">{{ $t('warehouseFeedMedicineFinanceNote') }}</p>
        <form class="form-grid" @submit.prevent="addEquipment">
          <div class="field">
            <label>{{ $t('name') }}</label>
            <input v-model="equipmentForm.name" type="text" maxlength="120" required :placeholder="$t('warehouseEquipmentExample')" />
          </div>
          <div class="field">
            <label>{{ $t('unit') }}</label>
            <select v-model="equipmentForm.unit" required>
              <option value="piece">{{ $t('pieces') }}</option>
              <option value="kg">kg</option>
            </select>
          </div>
          <div class="field">
            <label>{{ $t('quantity') }}</label>
            <FormattedNumberInput v-model="equipmentForm.quantity" :max-decimals="2" required />
          </div>
          <div class="field warehouse-photo-field">
            <label>{{ $t('warehousePhoto') }}</label>
            <input
              ref="photoInput"
              type="file"
              accept="image/*"
              capture="environment"
              class="warehouse-photo-input"
              @change="onPhotoSelected"
            />
            <p class="muted">{{ $t('warehousePhotoHint') }}</p>
            <div v-if="photoPreview" class="warehouse-photo-preview">
              <img :src="photoPreview" :alt="equipmentForm.name || $t('warehousePhoto')" />
              <button class="btn btn-sm btn-outline" type="button" @click="clearPhoto">{{ $t('removePhoto') }}</button>
            </div>
          </div>
          <div class="actions" style="grid-column: 1 / -1">
            <button class="btn btn-primary" type="submit" :disabled="saving">{{ saving ? $t('loading') : $t('addToWarehouse') }}</button>
          </div>
        </form>
        <p v-if="error" class="error">{{ error }}</p>
      </div>
    </div>
  </DirectorShell>
</template>

<script>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { setDirectorFarmId, useDirectorFarmId } from '../../utils/directorFarm';
import { useRoute } from 'vue-router';
import DirectorShell from '../../components/DirectorShell.vue';
import { showSaved } from '../../flash';
import { t } from '../../i18n';

export default {
  components: { DirectorShell },
  setup() {
    const route = useRoute();
    const farmId = useDirectorFarmId();
    const farm = ref(null);
    const saving = ref(false);
    const error = ref('');
    const photoInput = ref(null);
    const photoPreview = ref('');

    const equipmentForm = reactive({
      name: '',
      unit: 'piece',
      quantity: '',
      photoFile: null,
    });

    async function loadFarm() {
      const { data } = await window.axios.get(`/director/farms/${farmId.value}`);
      farm.value = data;
    }

    function clearPhoto() {
      if (photoPreview.value) {
        URL.revokeObjectURL(photoPreview.value);
      }
      photoPreview.value = '';
      equipmentForm.photoFile = null;
      if (photoInput.value) {
        photoInput.value.value = '';
      }
    }

    function onPhotoSelected(event) {
      const file = event.target.files?.[0];
      if (photoPreview.value) {
        URL.revokeObjectURL(photoPreview.value);
      }
      photoPreview.value = '';
      equipmentForm.photoFile = null;
      if (!file) {
        return;
      }
      equipmentForm.photoFile = file;
      photoPreview.value = URL.createObjectURL(file);
    }

    async function addEquipment() {
      error.value = '';
      saving.value = true;
      try {
        const payload = new FormData();
        payload.append('category', 'equipment');
        payload.append('name', equipmentForm.name.trim());
        payload.append('unit', equipmentForm.unit);
        payload.append('quantity', String(Number(equipmentForm.quantity)));
        if (equipmentForm.photoFile) {
          payload.append('photo', equipmentForm.photoFile);
        }

        await window.axios.post(`/director/farms/${farmId.value}/warehouse`, payload, {
          headers: { 'Content-Type': 'multipart/form-data' },
        });
        showSaved(t('saved'));
        equipmentForm.name = '';
        equipmentForm.quantity = '';
        equipmentForm.unit = 'piece';
        clearPhoto();
      } catch (e) {
        const errors = e.response?.data?.errors;
        error.value = errors ? Object.values(errors).flat().join(' ') : (e.response?.data?.message || t('noData'));
      } finally {
        saving.value = false;
      }
    }

    onMounted(loadFarm);

    onBeforeUnmount(() => {
      if (photoPreview.value) {
        URL.revokeObjectURL(photoPreview.value);
      }
    });

    return {
      farmId,
      farm,
      equipmentForm,
      saving,
      error,
      photoInput,
      photoPreview,
      addEquipment,
      onPhotoSelected,
      clearPhoto,
    };
  },
};
</script>
