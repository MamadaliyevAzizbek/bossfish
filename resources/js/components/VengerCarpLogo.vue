<template>
  <component
    :is="tag"
    class="venger-carp-logo"
    :class="{
      'venger-carp-logo--compact': compact,
      'venger-carp-logo--on-dark': onDark,
      'venger-carp-logo--hero': hero,
    }"
  >
    <img src="/images/venger-carp-logo.png" alt="Venger Carp" class="venger-carp-logo__img" />
  </component>
</template>

<script>
export default {
  props: {
    tag: { type: String, default: 'div' },
    compact: { type: Boolean, default: false },
    onDark: { type: Boolean, default: false },
    hero: { type: Boolean, default: false },
  },
};
</script>

<style scoped>
.venger-carp-logo {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  line-height: 0;
}
.venger-carp-logo__img {
  display: block;
  width: auto;
  height: 42px;
  object-fit: contain;
}
.venger-carp-logo--compact .venger-carp-logo__img {
  height: 34px;
}
.venger-carp-logo--on-dark {
  background: #fff;
  border-radius: 12px;
  padding: 0.35rem 0.5rem;
  box-shadow: 0 6px 16px rgba(0, 0, 0, 0.16);
}
.venger-carp-logo--hero {
  background: #fff;
  border-radius: 14px;
  padding: 0.4rem 0.55rem;
  box-shadow: 0 8px 20px rgba(0, 0, 0, 0.18);
  border: 1px solid rgba(255, 255, 255, 0.65);
}
.venger-carp-logo--hero .venger-carp-logo__img {
  height: 52px;
}
</style>
