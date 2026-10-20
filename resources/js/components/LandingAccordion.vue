<template>
  <details class="landing-accordion" :class="{ 'landing-accordion--nested': nested }">
    <summary class="landing-accordion-summary">
      <span class="landing-accordion-title">{{ title }}</span>
      <span class="landing-accordion-chevron" aria-hidden="true" />
    </summary>
    <div class="landing-accordion-body">
      <slot />
    </div>
  </details>
</template>

<script>
export default {
  props: {
    title: { type: String, required: true },
    nested: { type: Boolean, default: false },
  },
};
</script>
