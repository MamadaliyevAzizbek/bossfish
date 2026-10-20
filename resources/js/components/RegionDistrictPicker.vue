<template>
  <div class="region-district-picker">
    <div v-if="!lockedRegion" class="field">
      <label>{{ regionLabel || $t('selectRegion') }} <span class="req">*</span></label>
      <select
        :value="region"
        required
        @change="onRegionChange($event.target.value)"
      >
        <option value="">{{ $t('selectRegion') }}</option>
        <option v-for="name in regions" :key="name" :value="name">{{ name }}</option>
      </select>
    </div>

    <div v-else class="field">
      <label>{{ regionLabel || $t('region') }}</label>
      <input :value="lockedRegion" type="text" disabled />
    </div>

    <div class="field">
      <label>{{ districtLabel || $t('selectDistrict') }} <span class="req">*</span></label>
      <select
        :value="district"
        required
        :disabled="!activeRegion || !districtOptions.length"
        @change="$emit('update:district', $event.target.value)"
      >
        <option value="">{{ districtPlaceholder }}</option>
        <option v-for="name in districtOptions" :key="name" :value="name">{{ name }}</option>
      </select>
      <small v-if="activeRegion && !districtOptions.length" class="field-hint">{{ $t('noDistrictsForRegion') }}</small>
      <small v-else-if="!activeRegion" class="field-hint">{{ $t('selectRegionFirst') }}</small>
    </div>
  </div>
</template>

<script>
import { computed } from 'vue';
import { districtsForRegion, regionNames } from '../data/uzbekistanDistricts';
import { t } from '../i18n';

export default {
  props: {
    region: { type: String, default: '' },
    district: { type: String, default: '' },
    lockedRegion: { type: String, default: '' },
    regionLabel: { type: String, default: '' },
    districtLabel: { type: String, default: '' },
  },
  emits: ['update:region', 'update:district'],
  setup(props, { emit }) {
    const regions = regionNames();

    const activeRegion = computed(() => props.lockedRegion || props.region || '');

    const districtOptions = computed(() => districtsForRegion(activeRegion.value));

    const districtPlaceholder = computed(() => {
      if (!activeRegion.value) return t('selectRegionFirst');
      return t('selectDistrict');
    });

    function onRegionChange(value) {
      emit('update:region', value);
      emit('update:district', '');
    }

    return {
      regions,
      activeRegion,
      districtOptions,
      districtPlaceholder,
      onRegionChange,
    };
  },
};
</script>
