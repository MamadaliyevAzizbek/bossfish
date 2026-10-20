<template>
  <div class="director-shell crm-shell">
    <div class="director-inner">
      <div v-if="directorBlocked" class="panel worker-access-blocked">
        <h2>{{ $t('workerDirectorAccessBlocked') }}</h2>
        <p>{{ $t('workerDirectorAccessHint') }}</p>
        <p v-if="linkedDirector">
          <strong>{{ $t('director') }}:</strong> {{ linkedDirector.name }}
          <span v-if="linkedDirector.phone"> · {{ linkedDirector.phone }}</span>
        </p>
      </div>

      <slot v-if="!directorBlocked" />
    </div>
  </div>
</template>

<script>
import { onMounted, ref } from 'vue';
import auth from '../auth';

export default {
  setup() {
    const pond = ref(null);
    const directorBlocked = ref(false);
    const linkedDirector = ref(null);

    onMounted(async () => {
      try {
        const { data } = await window.axios.get('/worker/me');
        pond.value = data.pond;
        linkedDirector.value = data.linked_director || null;
        directorBlocked.value = !!data.director_access_blocked;
      } catch (e) {
        pond.value = null;
        if (e.response?.status === 403) {
          directorBlocked.value = true;
          try {
            const { data } = await window.axios.get('/auth/me');
            linkedDirector.value = data.linked_director || null;
          } catch (_) {
            /* ignore */
          }
        }
      }
    });

    return { pond, directorBlocked, linkedDirector };
  },
};
</script>
