<template>
  <input
    ref="inputEl"
    :value="displayValue"
    type="text"
    :inputmode="integerOnly ? 'numeric' : 'decimal'"
    :required="required"
    :disabled="disabled"
    :placeholder="placeholder"
    :autofocus="autofocus"
    @input="onInput"
    @blur="onBlur"
  />
</template>

<script>
import { ref, watch } from 'vue';
import { formatNumberInput, parseFormattedNumber, sanitizeFormattedNumberInput } from '../utils/number';

export default {
  name: 'FormattedNumberInput',
  props: {
    modelValue: { type: [String, Number], default: '' },
    integerOnly: { type: Boolean, default: false },
    maxDecimals: { type: Number, default: 2 },
    required: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    placeholder: { type: String, default: '' },
    autofocus: { type: Boolean, default: false },
  },
  emits: ['update:modelValue', 'input', 'blur'],
  setup(props, { emit }) {
    const inputEl = ref(null);
    const displayValue = ref('');

    function syncFromModel(value) {
      const raw = value === null || value === undefined ? '' : String(value);
      if (raw === '') {
        displayValue.value = '';
        return;
      }
      displayValue.value = formatNumberInput(raw, {
        integerOnly: props.integerOnly,
        maxDecimals: props.maxDecimals,
      });
    }

    function emitValue(raw) {
      const next = raw === '-' ? '' : raw;
      emit('update:modelValue', next);
      emit('input', next);
    }

    function onInput(event) {
      const { raw, display } = sanitizeFormattedNumberInput(event.target.value, {
        integerOnly: props.integerOnly,
        maxDecimals: props.maxDecimals,
      });
      displayValue.value = display;
      emitValue(raw);
    }

    function onBlur(event) {
      const raw = parseFormattedNumber(displayValue.value);
      if (raw && raw !== '-') {
        displayValue.value = formatNumberInput(raw, {
          integerOnly: props.integerOnly,
          maxDecimals: props.maxDecimals,
        });
        emitValue(raw);
      }
      emit('blur', event);
    }

    watch(
      () => props.modelValue,
      (value) => {
        const raw = value === null || value === undefined ? '' : String(value);
        const currentRaw = parseFormattedNumber(displayValue.value);
        if (raw !== currentRaw) {
          syncFromModel(raw);
        }
      },
      { immediate: true },
    );

    watch(
      () => [props.integerOnly, props.maxDecimals],
      () => syncFromModel(props.modelValue),
    );

    return {
      inputEl,
      displayValue,
      onInput,
      onBlur,
    };
  },
};
</script>
