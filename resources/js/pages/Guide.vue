<template>
  <div class="landing-page guide-page">
    <SceneBackground scene-key="home" />

    <div class="landing-content guide-content">
      <header class="guide-page-head">
        <router-link class="guide-back" :to="{ name: 'home' }">{{ $t('back') }}</router-link>
        <h1>{{ $t('guidePageTitle') }}</h1>
        <p>{{ $t('guidePageIntro') }}</p>
      </header>

      <nav class="guide-toc" :aria-label="$t('guideTocAria')">
        <a href="#guide-schedule" class="guide-toc-link">{{ $t('guideScheduleTitle') }}</a>
        <a
          v-for="section in GUIDE_SECTIONS"
          :key="section.id"
          :href="`#guide-${section.id}`"
          class="guide-toc-link"
        >
          {{ $t(section.titleKey) }}
        </a>
      </nav>

      <section id="guide-schedule" class="guide-section guide-section--schedule">
        <GuideStandardsTable />
      </section>

      <article
        v-for="section in GUIDE_SECTIONS"
        :id="`guide-${section.id}`"
        :key="section.id"
        class="guide-section"
        :class="{ 'guide-section--standards': section.variant === 'standards' }"
      >
        <h2>{{ $t(section.titleKey) }}</h2>
        <p v-if="section.introKey" class="guide-section-intro">{{ $t(section.introKey) }}</p>
        <ol class="guide-list">
          <li v-for="key in section.itemKeys" :key="key">{{ $t(key) }}</li>
        </ol>
      </article>

      <footer class="guide-foot">
        <p>{{ $t('guidePageFooter') }}</p>
        <router-link class="landing-guide-link" :to="{ name: 'home' }">{{ $t('homeGuideBackHome') }}</router-link>
      </footer>
    </div>
  </div>
</template>

<script>
import SceneBackground from '../components/SceneBackground.vue';
import GuideStandardsTable from '../components/GuideStandardsTable.vue';
import { GUIDE_SECTIONS } from '../config/siteGuide';

export default {
  name: 'SiteGuide',
  components: { SceneBackground, GuideStandardsTable },
  setup() {
    return { GUIDE_SECTIONS };
  },
};
</script>
