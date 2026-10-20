<template>
  <span
    class="hub-section-mark"
    :class="`hub-section-mark--${section}`"
    aria-hidden="true"
  >
    <!-- Balıqchilik -->
    <svg v-if="section === 'fishery'" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
      <path
        d="M8 24c0-8.5 6.8-15.5 15.5-16.5L28 4l4.5 3.5C40.5 9 44 16 44 24s-3.5 15-11.5 16.5L28 44l-4.5-3.5C14.8 39.5 8 32.5 8 24Z"
        stroke="currentColor"
        stroke-width="2.2"
        stroke-linejoin="round"
      />
      <path d="M18 24c0-3.3 2.7-6 6-6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" />
      <circle cx="30" cy="20" r="1.8" fill="currentColor" />
      <path d="M36 18l6-4M36 30l6 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
    </svg>

    <!-- Ombor -->
    <svg v-else-if="section === 'warehouse'" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
      <path
        d="M6 20 24 8l18 12v18a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V20Z"
        stroke="currentColor"
        stroke-width="2.2"
        stroke-linejoin="round"
      />
      <path d="M18 42V28h12v14" stroke="currentColor" stroke-width="2.2" stroke-linejoin="round" />
      <rect x="14" y="24" width="8" height="6" rx="1" stroke="currentColor" stroke-width="1.8" />
      <rect x="26" y="24" width="8" height="6" rx="1" stroke="currentColor" stroke-width="1.8" />
    </svg>

    <!-- Moliya -->
    <svg v-else-if="section === 'finance'" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
      <rect x="6" y="10" width="36" height="28" rx="3" stroke="currentColor" stroke-width="2.2" />
      <path d="M6 18h36" stroke="currentColor" stroke-width="2.2" />
      <path d="M14 30h6M28 30h6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" />
      <circle cx="24" cy="30" r="4" stroke="currentColor" stroke-width="2" />
      <path d="M24 28v4M22.5 30h3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
    </svg>

    <!-- Omborga kirim -->
    <svg v-else-if="section === 'intake'" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
      <path
        d="M8 16h24l4 8H4l4-8Z"
        stroke="currentColor"
        stroke-width="2.2"
        stroke-linejoin="round"
      />
      <path d="M12 24v14h16V24" stroke="currentColor" stroke-width="2.2" stroke-linejoin="round" />
      <path d="M24 8v8M20 12h8" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" />
      <path d="M32 30h8M36 26v8" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" />
    </svg>

    <!-- Ombor zaxirasi -->
    <svg v-else-if="section === 'stock'" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
      <path d="M8 14h32v26H8V14Z" stroke="currentColor" stroke-width="2.2" stroke-linejoin="round" />
      <path d="M8 22h32M8 30h32" stroke="currentColor" stroke-width="2" />
      <path d="M20 14V8h8v6" stroke="currentColor" stroke-width="2.2" stroke-linejoin="round" />
      <rect x="12" y="34" width="8" height="6" rx="1" fill="currentColor" opacity="0.25" />
      <rect x="28" y="34" width="8" height="6" rx="1" fill="currentColor" opacity="0.25" />
    </svg>
  </span>
</template>

<script>
export default {
  props: {
    section: {
      type: String,
      required: true,
      validator: (value) => ['fishery', 'warehouse', 'finance', 'intake', 'stock'].includes(value),
    },
  },
};
</script>
