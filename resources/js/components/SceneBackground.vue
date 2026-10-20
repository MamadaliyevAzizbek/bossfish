<template>
  <div
    class="scene-layers"
    :class="{ 'scene-layers--kenburns': config.kenBurns }"
    :data-scene="sceneKey"
    aria-hidden="true"
  >
    <div class="scene-bg-image" />
    <div v-if="config.rays" class="ocean-rays" />
    <div v-if="config.bubbles" class="ocean-bubbles">
      <span
        v-for="n in config.bubbleCount"
        :key="n"
        class="ocean-bubble"
        :style="bubbleStyle(n)"
      />
    </div>
  </div>
</template>

<script>
import { computed } from 'vue';
import { sceneBackgrounds } from '../config/sceneBackgrounds';

export default {
  props: {
    sceneKey: { type: String, required: true },
  },
  setup(props) {
    const config = computed(() => sceneBackgrounds[props.sceneKey] || sceneBackgrounds.home);

    function bubbleStyle(n) {
      const left = 8 + ((n * 17) % 84);
      const delay = (n * 0.55) % 4.2;
      const duration = 6.5 + (n % 4);
      const size = 3 + (n % 5);
      return {
        left: `${left}%`,
        width: `${size}px`,
        height: `${size}px`,
        animationDelay: `${delay}s`,
        animationDuration: `${duration}s`,
      };
    }

    return { config, bubbleStyle };
  },
};
</script>
