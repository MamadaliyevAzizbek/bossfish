<template>
  <component :is="tag" class="brand-logo" :class="[`brand-logo-${variant}`, { 'brand-logo-compact': compact }]">
    <img :src="src" alt="BOSS FISH" class="brand-logo-image" />
    <span v-if="showPost" class="brand-logo-post">/POST</span>
  </component>
</template>

<script>
/**
 * variant:
 *  - light → ochiq (oq) fon uchun: qora matnli logo
 *  - dark  → qorong‘i fon uchun: oq matnli logo
 */
// v=2 — tozalangan shaffof logolar (chet chiziqlari olib tashlangan)
const LOGO = {
  light: '/images/boss-logo-on-light.png?v=2',
  dark: '/images/boss-logo-on-dark.png?v=2',
};

export default {
  props: {
    tag: { type: String, default: 'div' },
    variant: {
      type: String,
      default: 'light',
      validator: (v) => v === 'light' || v === 'dark',
    },
    compact: { type: Boolean, default: false },
    showPost: { type: Boolean, default: false },
  },
  computed: {
    src() {
      return LOGO[this.variant] || LOGO.light;
    },
  },
};
</script>
