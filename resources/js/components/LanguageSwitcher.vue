<template>
  <div
    class="language-switcher"
    :class="{ 'is-open': open, 'language-switcher--embedded': embedded }"
    ref="root"
  >
    <button
      class="language-button"
      type="button"
      :aria-expanded="open ? 'true' : 'false'"
      aria-haspopup="listbox"
      :aria-controls="menuId"
      :aria-label="$t('language')"
      @click="toggle"
    >
      <span class="language-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="9" />
          <path d="M3 12h18" />
          <path d="M12 3a14 14 0 0 1 0 18" />
          <path d="M12 3a14 14 0 0 0 0 18" />
        </svg>
      </span>
      <span class="language-label">{{ currentLabel }}</span>
      <span class="language-caret" aria-hidden="true">
        <svg viewBox="0 0 12 12" width="10" height="10" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path d="M2.5 4.5 6 8l3.5-3.5" />
        </svg>
      </span>
    </button>

    <Transition name="language-menu">
      <div
        v-if="open"
        :id="menuId"
        class="language-menu"
        role="listbox"
        :aria-label="$t('language')"
      >
        <button
          v-for="language in languages"
          :key="language.code"
          type="button"
          role="option"
          :aria-selected="locale === language.code ? 'true' : 'false'"
          :class="{ active: locale === language.code }"
          @click="select(language.code)"
        >
          {{ language.label }}
        </button>
      </div>
    </Transition>
  </div>
</template>

<script>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { i18nState, languages, setLocale } from '../i18n';

export default {
  props: {
    embedded: { type: Boolean, default: false },
  },
  setup() {
    const open = ref(false);
    const root = ref(null);
    const menuId = `language-menu-${Math.random().toString(36).slice(2, 9)}`;
    const locale = computed(() => i18nState.locale);
    const currentLabel = computed(() => languages.find((item) => item.code === locale.value)?.label);

    function toggle() {
      open.value = !open.value;
    }

    function select(code) {
      setLocale(code);
      open.value = false;
    }

    function onDocClick(event) {
      if (root.value && !root.value.contains(event.target)) {
        open.value = false;
      }
    }

    function onKeydown(event) {
      if (event.key === 'Escape' && open.value) {
        open.value = false;
      }
    }

    onMounted(() => {
      document.addEventListener('click', onDocClick);
      document.addEventListener('keydown', onKeydown);
    });

    onBeforeUnmount(() => {
      document.removeEventListener('click', onDocClick);
      document.removeEventListener('keydown', onKeydown);
    });

    return {
      open,
      root,
      menuId,
      locale,
      currentLabel,
      languages,
      toggle,
      select,
    };
  },
};
</script>
