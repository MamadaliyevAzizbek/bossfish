<template>
  <div class="phone-input" :class="{ focused }">
    <span class="phone-prefix">+998</span>
    <input
      :value="local"
      type="tel"
      inputmode="numeric"
      maxlength="12"
      :placeholder="placeholder"
      :required="required"
      :disabled="disabled"
      @focus="focused = true"
      @blur="focused = false"
      @input="onInput"
    />
  </div>
</template>

<script>
import { computed, ref } from 'vue';
import { formatLocalPhone, localDigits, toFullPhone } from '../utils/phone';

export default {
  name: 'PhoneInput',
  props: {
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: '99 999 99 99' },
    required: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
  },
  emits: ['update:modelValue'],
  setup(props, { emit }) {
    const focused = ref(false);
    const local = computed(() => formatLocalPhone(props.modelValue));

    function onInput(e) {
      emit('update:modelValue', toFullPhone(e.target.value));
    }

    return { focused, local, onInput };
  },
};
</script>
