<template>
  <span class="fish-type-badge" :class="{ 'fish-type-badge--compact': compact, 'fish-type-badge--block': block }">
    <img :src="imageSrc" :alt="displayLabel" class="fish-type-badge__image" loading="lazy" />
    <span v-if="showLabel" class="fish-type-badge__label">
      <slot>{{ displayLabel }}</slot>
    </span>
  </span>
</template>

<script>
import { computed } from 'vue';
import { fishImageSrc, fishLabel } from '../utils/fish';
import { t } from '../i18n';

export default {
  props: {
    type: { type: String, required: true },
    showLabel: { type: Boolean, default: true },
    compact: { type: Boolean, default: false },
    block: { type: Boolean, default: false },
  },
  setup(props) {
    const imageSrc = computed(() => fishImageSrc(props.type));
    const displayLabel = computed(() => fishLabel(props.type, t));

    return { imageSrc, displayLabel };
  },
};
</script>
