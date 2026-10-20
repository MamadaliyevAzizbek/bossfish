<template>
  <div class="landing-page landing-page--clean">
    <header class="landing-site-header">
      <div class="landing-site-header__top">
        <a class="landing-site-header__phone" :href="`tel:${$t('homeContactPhone').replace(/\s+/g, '')}`">
          {{ $t('homeContactPhone') }}
        </a>
        <a href="#kabinet" class="landing-site-header__logo" aria-label="BOSS FISH" @click.prevent="goToSection('kabinet')">
          <BrandLogo variant="light" compact />
        </a>
        <div class="landing-site-header__actions">
          <router-link v-if="cabinetRoute" class="landing-site-header__cabinet" :to="cabinetRoute">
            {{ $t('siteHomeCabinet') }}
          </router-link>
          <a class="landing-site-header__contact" href="#narxlar" @click.prevent="goToSection('narxlar')">
            {{ $t('homeNavContact') }}
          </a>
          <LanguageSwitcher embedded />
        </div>
      </div>
      <nav class="landing-site-nav" :aria-label="$t('homeSectionsNavLabel')">
        <a
          v-for="item in mainNav"
          :key="item.id"
          :href="`#${item.id}`"
          class="landing-site-nav__link"
          :class="{ 'landing-site-nav__link--active': activeNav === item.id }"
          @click.prevent="goToSection(item.id)"
        >
          {{ $t(item.labelKey) }}
        </a>
      </nav>
    </header>

    <!-- 1. Kabinet -->
    <section id="kabinet" class="landing-band landing-band--white">
      <div class="landing-band__inner">
        <header class="landing-hero landing-hero--clean">
          <h1 class="landing-hero-brand">
            <BrandLogo variant="light" class="landing-logo landing-logo--hero" />
            <span class="visually-hidden">BOSS FISH</span>
          </h1>
          <p class="landing-tagline">{{ $t('siteTagline') }}</p>
        </header>

        <div class="landing-portal landing-portal--cabinets" :aria-label="$t('homeNavCabinet')">
          <p class="landing-choose">{{ $t('homeChooseRole') }}</p>
          <div class="portal-grid portal-grid--cabinets">
            <router-link class="landing-cabinet-btn landing-cabinet-btn--director" :to="directorPortalRoute">
              <span class="landing-cabinet-btn__icon" aria-hidden="true">
                <svg viewBox="0 0 48 48" fill="none">
                  <circle cx="24" cy="16" r="7" stroke="currentColor" stroke-width="2.4"/>
                  <path d="M10 40c2.5-8 8-12 14-12s11.5 4 14 12" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/>
                  <path d="M34 14h8M38 10v8" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>
                </svg>
              </span>
              <span class="landing-cabinet-btn__body">
                <strong>{{ $t('pondDirector') }}</strong>
                <small>{{ $t('directorDescription') }}</small>
              </span>
              <span class="landing-cabinet-btn__cta">
                {{ $t('login') }}
                <svg aria-hidden="true" viewBox="0 0 12 12" width="12" height="12" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 2l4 4-4 4"/></svg>
              </span>
            </router-link>

            <router-link class="landing-cabinet-btn landing-cabinet-btn--worker" :to="workerPortalRoute">
              <span class="landing-cabinet-btn__icon" aria-hidden="true">
                <svg viewBox="0 0 48 48" fill="none">
                  <path d="M15 17c0-5.5 4-10 9-10s9 4.5 9 10" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/>
                  <path d="M13 17h22v2.5c0 2.2-2.2 4-4.8 4H17.8c-2.6 0-4.8-1.8-4.8-4V17z" stroke="currentColor" stroke-width="2.2" stroke-linejoin="round"/>
                  <circle cx="24" cy="28" r="5.5" stroke="currentColor" stroke-width="2.4"/>
                  <path d="M11 42c2.2-7 6.8-10.5 13-10.5S34.8 35 37 42" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/>
                </svg>
              </span>
              <span class="landing-cabinet-btn__body">
                <strong>{{ $t('worker') }}</strong>
                <small>{{ $t('workerDescription') }}</small>
              </span>
              <span class="landing-cabinet-btn__cta">
                {{ $t('login') }}
                <svg aria-hidden="true" viewBox="0 0 12 12" width="12" height="12" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 2l4 4-4 4"/></svg>
              </span>
            </router-link>
          </div>
        </div>
      </div>
    </section>

    <!-- 2. BOSS FISH BBT -->
    <section id="boss-fish" class="landing-band landing-band--muted" aria-labelledby="landing-bbt-title">
      <div class="landing-band__inner">
        <div class="landing-bbt">
          <header class="landing-bbt__head">
            <h2 id="landing-bbt-title">{{ $t('homeBbtTitle') }}</h2>
            <p>{{ $t('homeBbtLead') }}</p>
          </header>

          <div class="landing-bbt__layout">
            <div class="landing-bbt__cats" role="list">
              <button
                v-for="mod in bbtModules"
                :key="mod.id"
                type="button"
                role="listitem"
                class="landing-bbt-card"
                :class="{ 'landing-bbt-card--active': activeBbtId === mod.id }"
                @click="activeBbtId = mod.id"
              >
                <span class="landing-bbt-card__icon" aria-hidden="true" v-html="mod.icon"></span>
                <span class="landing-bbt-card__text">
                  <strong>{{ $t(mod.titleKey) }}</strong>
                  <small>{{ $t(mod.subtitleKey) }}</small>
                </span>
              </button>
            </div>

            <div class="landing-bbt__preview">
              <img
                :src="activeBbt.image"
                :alt="$t(activeBbt.titleKey)"
                loading="lazy"
              />
              <div class="landing-bbt__caption">
                <strong>{{ $t(activeBbt.titleKey) }}</strong>
                <small>{{ $t(activeBbt.subtitleKey) }}</small>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- 3. Hamkorliklar -->
    <section id="hamkorliklar" class="landing-band landing-band--white" aria-labelledby="landing-partners-title">
      <div class="landing-band__inner landing-partners-band">
        <header class="landing-partners-band__head">
          <h2 id="landing-partners-title">{{ $t('homePartnersSectionTitle') }}</h2>
          <p>{{ $t('homePartnersSectionLead') }}</p>
        </header>

        <div class="landing-partners-band__logos" :aria-label="$t('homePartners')">
          <div
            v-for="partner in landingPartners"
            :key="partner.name"
            class="landing-partners-band__logo"
          >
            <img :src="partner.logo" :alt="partner.name" />
          </div>
        </div>

        <div class="landing-partners-band__story">
          <article>
            <span class="landing-about-year">2023</span>
            <h3>{{ $t('homeAboutPartnershipTitle') }}</h3>
            <p>{{ $t('homeAboutPartnershipText') }}</p>
            <div class="landing-about-tags">
              <span>{{ $t('homeAboutTagFeed') }}</span>
              <span>{{ $t('homeAboutTagTechnology') }}</span>
              <span>{{ $t('homeAboutTagExperience') }}</span>
            </div>
          </article>
          <div class="landing-partners-band__photos">
            <img
              v-for="photo in partnershipPhotos.slice(0, 3)"
              :key="photo.src"
              :src="photo.src"
              :alt="$t(photo.altKey)"
              loading="lazy"
            />
          </div>
        </div>
      </div>
    </section>

    <!-- 4. Tariflar -->
    <section id="narxlar" class="landing-band landing-band--muted" aria-labelledby="landing-pricing-title">
      <div class="landing-band__inner landing-pricing">
        <header class="landing-pricing__head">
          <h2 id="landing-pricing-title">{{ $t('homePricingTitle') }}</h2>
          <p>{{ $t('homePricingLead') }}</p>
        </header>

        <div class="landing-pricing__grid">
          <article
            v-for="plan in pricingPlans"
            :key="plan.id"
            class="landing-price-card"
            :class="{ 'landing-price-card--popular': plan.popular }"
          >
            <span v-if="plan.popular" class="landing-price-card__ribbon" aria-hidden="false">
              <span class="landing-price-card__ribbon-text">{{ $t('homePricingPopular') }}</span>
            </span>
            <h3>{{ $t(plan.nameKey) }}</h3>
            <p class="landing-price-card__summary">{{ $t(plan.summaryKey) }}</p>
            <div class="landing-price-card__price">
              <template v-if="plan.monthPriceKey">
                <strong>{{ $t(plan.monthPriceKey) }}</strong>
                <small>{{ $t('homePricingPerMonth') }}</small>
                <p class="landing-price-card__year">
                  <span>{{ $t('homePricingYearPay') }}</span>
                  <b>{{ $t(plan.priceKey) }}</b>
                  <em>{{ $t('homePricingYearSave') }}</em>
                </p>
              </template>
              <template v-else>
                <strong>{{ $t(plan.priceKey) }}</strong>
              </template>
            </div>
            <ul>
              <li v-for="featureKey in plan.featureKeys" :key="featureKey">
                {{ $t(featureKey) }}
              </li>
            </ul>
            <a
              class="landing-price-card__cta"
              :href="`tel:${$t('homeContactPhone').replace(/\s+/g, '')}`"
            >
              {{ $t(plan.ctaKey) }}
            </a>
          </article>
        </div>
      </div>
    </section>

    <!-- 5. Yakuniy shior (Alfapos uslubi) -->
    <section id="shior" class="landing-band landing-band--white" aria-labelledby="landing-closer-title">
      <div class="landing-band__inner landing-closer">
        <div class="landing-closer__copy">
          <h2 id="landing-closer-title">{{ $t('homeCloserTitle') }}</h2>
          <p class="landing-closer__tagline">{{ $t('homeCloserTagline') }}</p>
          <a
            class="landing-closer__cta"
            :href="`tel:${$t('homeContactPhone').replace(/\s+/g, '')}`"
          >
            {{ $t('homeContactPhone') }}
          </a>
        </div>
        <div class="landing-closer__media">
          <img
            class="landing-closer__image"
            src="/images/landing/closer-fishfarm.jpg"
            :alt="$t('homeCloserAlt')"
            width="700"
            height="525"
            loading="lazy"
            decoding="async"
          />
        </div>
      </div>
    </section>

    <!-- Keyingi bo‘limlar keyin qo‘shiladi -->
    <template v-if="false">
      <section id="landing-about" class="landing-about" aria-labelledby="landing-about-title">
        <section id="landing-products" class="landing-products" aria-labelledby="landing-products-title">
          <header class="landing-products-head">
            <div>
              <span class="landing-products-kicker">{{ $t('homeProductsKicker') }}</span>
              <h3 id="landing-products-title">{{ $t('homeProductsTitle') }}</h3>
              <p>{{ $t('homeProductsLead') }}</p>
            </div>
            <div v-if="allFeedProducts.length > 1" class="landing-products-controls landing-products-controls--desktop">
              <button type="button" :aria-label="$t('homeProductsPrevious')" @click="scrollProducts(-1)">
                <svg viewBox="0 0 12 12" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 2L4 6l4 4"/></svg>
              </button>
              <button type="button" :aria-label="$t('homeProductsNext')" @click="scrollProducts(1)">
                <svg viewBox="0 0 12 12" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 2l4 4-4 4"/></svg>
              </button>
            </div>
          </header>

          <div ref="productTrack" class="landing-products-track">
            <article
              v-for="(product, index) in allFeedProducts"
              :key="product.id"
              class="landing-product-card"
              :class="`landing-product-card--${product.theme}`"
            >
              <div class="landing-product-visual">
                <span class="landing-product-number">{{ String(index + 1).padStart(2, '0') }}</span>
                <img :src="product.image" :alt="product.title || $t(product.titleKey)" loading="lazy" />
              </div>
              <div class="landing-product-info">
                <div class="landing-product-meta">
                  <span class="landing-product-category">{{ product.categoryLabel || $t(product.categoryKey) }}</span>
                  <strong v-if="product.price_label || product.priceKey" class="landing-product-price">
                    {{ product.price_label || $t(product.priceKey) }}
                  </strong>
                </div>
                <h4>{{ product.title || $t(product.titleKey) }}</h4>
                <p v-if="product.slogan" class="landing-product-slogan">{{ product.slogan }}</p>
                <div v-if="(product.specs || product.specKeys)?.length" class="landing-product-specs">
                  <span v-for="spec in (product.specs || product.specKeys)" :key="spec">
                    {{ product.specs ? spec : $t(spec) }}
                  </span>
                </div>
                <div v-if="product.variants?.length" class="landing-product-variants">
                  <div v-for="variant in product.variants" :key="variant.nameKey">
                    <strong>{{ $t(variant.nameKey) }}</strong>
                    <span>{{ $t(variant.priceKey) }}</span>
                  </div>
                </div>
                <p v-if="product.usage_text" class="landing-product-usage">{{ product.usage_text }}</p>
                <p>{{ product.description || $t(product.descriptionKey) }}</p>
              </div>
            </article>
            <div class="landing-products-more" aria-hidden="true">
              <span>+</span>
              <small>{{ $t('homeProductsMore') }}</small>
            </div>
          </div>
        </section>

        <section id="landing-medicines" class="landing-products landing-products--medicine" aria-labelledby="landing-medicines-title">
          <header class="landing-products-head">
            <div>
              <span class="landing-products-kicker">{{ $t('homeMedicinesKicker') }}</span>
              <h3 id="landing-medicines-title">{{ $t('homeMedicinesTitle') }}</h3>
              <p>{{ $t('homeMedicinesLead') }}</p>
            </div>
            <div v-if="landingMedicines.length > 1" class="landing-products-controls landing-products-controls--desktop">
              <button type="button" :aria-label="$t('homeProductsPrevious')" @click="scrollMedicines(-1)">
                <svg viewBox="0 0 12 12" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 2L4 6l4 4"/></svg>
              </button>
              <button type="button" :aria-label="$t('homeProductsNext')" @click="scrollMedicines(1)">
                <svg viewBox="0 0 12 12" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 2l4 4-4 4"/></svg>
              </button>
            </div>
          </header>

          <div v-if="medicinesLoading" class="landing-products-track">
            <article class="landing-medicine-empty">
              <div class="landing-medicine-empty-mark" aria-hidden="true"><span /></div>
              <div>
                <span class="landing-product-category">{{ $t('homeMedicinesKicker') }}</span>
                <h4>{{ $t('loading') }}</h4>
              </div>
            </article>
          </div>

          <div v-else-if="!landingMedicines.length" class="landing-products-track">
            <article class="landing-medicine-empty">
              <div class="landing-medicine-empty-mark" aria-hidden="true"><span /></div>
              <div>
                <span class="landing-product-category">{{ $t('homeMedicinesKicker') }}</span>
                <h4>{{ $t('homeMedicinesEmptyTitle') }}</h4>
                <p>{{ $t('homeMedicinesEmptyText') }}</p>
              </div>
            </article>
          </div>

          <div v-else ref="medicineTrack" class="landing-products-track">
            <article
              v-for="(product, index) in landingMedicines"
              :key="product.id"
              class="landing-product-card"
              :class="`landing-product-card--${product.theme || 'teal'}`"
            >
              <div class="landing-product-visual">
                <span class="landing-product-number">{{ String(index + 1).padStart(2, '0') }}</span>
                <img v-if="product.image" :src="product.image" :alt="product.name" loading="lazy" />
              </div>
              <div class="landing-product-info">
                <div class="landing-product-meta">
                  <span class="landing-product-category">{{ $t('homeMedicinesKicker') }}</span>
                  <strong class="landing-product-price">{{ product.price_label }}</strong>
                </div>
                <h4>{{ product.name }}</h4>
                <p v-if="product.slogan" class="landing-product-slogan">{{ product.slogan }}</p>
                <div v-if="product.specs?.length" class="landing-product-specs">
                  <span v-for="spec in product.specs" :key="spec">{{ spec }}</span>
                </div>
                <p v-if="product.usage_text" class="landing-product-usage">{{ product.usage_text }}</p>
                <p>{{ product.description }}</p>
              </div>
            </article>
          </div>
        </section>

        <div id="landing-services" class="landing-about-story landing-about-story--reverse">
          <article class="landing-about-copy landing-about-copy--service">
            <span class="landing-about-year">{{ $t('homeAboutToday') }}</span>
            <h3>{{ $t('homeAboutServiceTitle') }}</h3>
            <p>{{ $t('homeAboutServiceText') }}</p>
            <ul class="landing-about-services">
              <li
                v-for="(service, index) in serviceItems"
                :key="service.titleKey"
              >
                <div>
                  <b>{{ String(index + 1).padStart(2, '0') }}</b>
                  <span>{{ $t(service.titleKey) }}</span>
                </div>
              </li>
            </ul>
            <div class="landing-service-inspection">
              <div class="landing-service-inspection-head">
                <strong>{{ $t('homeAboutInspectionTitle') }}</strong>
                <span>{{ $t('homeAboutInspectionText') }}</span>
              </div>
              <div class="landing-service-inspection-photos">
                <img
                  v-for="photo in inspectionPhotos"
                  :key="photo.src"
                  :src="photo.src"
                  :alt="$t(photo.altKey)"
                  loading="lazy"
                />
              </div>
            </div>
            <div class="landing-service-process" :aria-label="$t('homeAboutServiceProcessTitle')">
              <span><b>01</b>{{ $t('homeAboutServiceProcessStudy') }}</span>
              <span><b>02</b>{{ $t('homeAboutServiceProcessAnalyze') }}</span>
              <span><b>03</b>{{ $t('homeAboutServiceProcessAct') }}</span>
            </div>
          </article>
        </div>

        <footer class="landing-about-foot">
          <strong>{{ $t('homeAboutClosingTitle') }}</strong>
          <p>{{ $t('homeAboutClosingText') }}</p>
        </footer>
      </section>
    </template>
  </div>
</template>

<script>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import BrandLogo from '../components/BrandLogo.vue';
import auth from '../auth';
import LanguageSwitcher from '../components/LanguageSwitcher.vue';
import landingPartners from '../config/landingPartners.js';

const ICON_TECH = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 19h16M7 19V9l5-5 5 5v10M10 19v-4h4v4"/></svg>';
const ICON_FEED = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3c-3 4-6 6.5-6 10a6 6 0 0012 0c0-3.5-3-6-6-10z"/><path d="M9.5 14.5c.5 1.5 1.5 2.5 2.5 2.5"/></svg>';
const ICON_MED = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="8" y="3" width="8" height="18" rx="2"/><path d="M8 8h8M12 11v6"/></svg>';
const ICON_EQUIP = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="3"/><path d="M12 3v2M12 19v2M3 12h2M19 12h2M5.6 5.6l1.4 1.4M17 17l1.4 1.4M5.6 18.4L7 17M17 7l1.4-1.4"/></svg>';
const ICON_GUIDE = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 4h11a3 3 0 013 3v13H8a3 3 0 00-3 3V4z"/><path d="M8 4v16"/></svg>';
const ICON_WATER = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3s5 6 5 10a5 5 0 11-10 0c0-4 5-10 5-10z"/></svg>';

const bbtModules = [
  {
    id: 'technology',
    titleKey: 'homeBbtTechTitle',
    subtitleKey: 'homeBbtTechSubtitle',
    image: '/images/landing/pitch-modern-pond.jpg',
    icon: ICON_TECH,
  },
  {
    id: 'feed',
    titleKey: 'homeBbtFeedTitle',
    subtitleKey: 'homeBbtFeedSubtitle',
    image: '/images/landing/pitch-modern-app.jpg',
    icon: ICON_FEED,
  },
  {
    id: 'medicine',
    titleKey: 'homeBbtMedTitle',
    subtitleKey: 'homeBbtMedSubtitle',
    image: '/images/landing/pitch-modern-app.jpg',
    icon: ICON_MED,
  },
  {
    id: 'equipment',
    titleKey: 'homeBbtEquipTitle',
    subtitleKey: 'homeBbtEquipSubtitle',
    image: '/images/landing/pitch-modern-pond.jpg',
    icon: ICON_EQUIP,
  },
  {
    id: 'guides',
    titleKey: 'homeBbtGuideTitle',
    subtitleKey: 'homeBbtGuideSubtitle',
    image: '/images/landing/pitch-modern-app.jpg',
    icon: ICON_GUIDE,
  },
  {
    id: 'water',
    titleKey: 'homeBbtWaterTitle',
    subtitleKey: 'homeBbtWaterSubtitle',
    image: '/images/landing/pitch-modern-pond.jpg',
    icon: ICON_WATER,
  },
];

const partnershipPhotos = [
  { src: '/images/about/china-partnership-team.png', altKey: 'homeAboutAltTeam', captionKey: 'homeAboutCaptionPartnership' },
  { src: '/images/about/partnership-signing.png', altKey: 'homeAboutAltSigning', captionKey: 'homeAboutCaptionAgreement' },
  { src: '/images/about/nutriera-factory.png', altKey: 'homeAboutAltFactory', captionKey: 'homeAboutCaptionProduction' },
  { src: '/images/about/official-visit.png', altKey: 'homeAboutAltVisit', captionKey: 'homeAboutCaptionMeeting' },
  { src: '/images/about/agreement-team.png', altKey: 'homeAboutAltTeam', captionKey: 'homeAboutCaptionPartnership' },
];

const serviceItems = [
  { titleKey: 'homeAboutServiceProject' },
  { titleKey: 'homeAboutServicePlan' },
  { titleKey: 'homeAboutServiceTechnology' },
  { titleKey: 'homeAboutServiceWater' },
  { titleKey: 'homeAboutServiceFish' },
  { titleKey: 'homeAboutServiceDisease' },
];

const inspectionPhotos = [
  { src: '/images/about/field-service.png?v=2', altKey: 'homeAboutAltField' },
  { src: '/images/about/oxygen-test.png?v=2', altKey: 'homeAboutAltOxygen' },
  { src: '/images/about/water-test.png?v=2', altKey: 'homeAboutAltWater' },
  { src: '/images/about/fish-health-check.png?v=2', altKey: 'homeAboutAltFish' },
];

const landingProducts = [
  {
    id: 'feed-1-6-37',
    categoryKey: 'homeProductCategoryFeed',
    titleKey: 'homeProductFeedOneTitle',
    descriptionKey: 'homeProductFeedOneDescription',
    image: '/images/products/feed/sinking-feed-1-6mm-37.png',
    theme: 'red',
    priceKey: 'homeProductFeedOnePrice',
    specKeys: [
      'homeProductFeedOneSpecProtein',
      'homeProductFeedOneSpecSize',
      'homeProductFeedOneSpecFish',
      'homeProductFeedOneSpecType',
    ],
  },
  {
    id: 'functional-feed-1-6-37',
    categoryKey: 'homeProductCategoryFeed',
    titleKey: 'homeProductFunctionalTitle',
    descriptionKey: 'homeProductFunctionalDescription',
    image: '/images/products/feed/sinking-feed-1-6mm-37.png',
    theme: 'carrot',
    priceKey: 'homeProductFunctionalPrice',
    specKeys: [
      'homeProductFeedOneSpecProtein',
      'homeProductFeedOneSpecSize',
      'homeProductFeedOneSpecFish',
      'homeProductFunctionalSpecVitamin',
    ],
  },
  {
    id: 'grower-feed-50-500',
    categoryKey: 'homeProductCategoryFeed',
    titleKey: 'homeProductGrowerTitle',
    descriptionKey: 'homeProductGrowerDescription',
    image: '/images/products/feed/sinking-feed-50-500g.png',
    theme: 'red',
    specKeys: [
      'homeProductGrowerSpecFish',
      'homeProductFeedOneSpecType',
      'homeProductGrowerSpecVariants',
    ],
    variants: [
      {
        nameKey: 'homeProductGrowerVariant35',
        priceKey: 'homeProductGrowerPrice35',
      },
      {
        nameKey: 'homeProductGrowerVariant32',
        priceKey: 'homeProductGrowerPrice32',
      },
    ],
  },
  {
    id: 'grower-feed-large-granule',
    categoryKey: 'homeProductCategoryFeed',
    titleKey: 'homeProductLargeTitle',
    descriptionKey: 'homeProductLargeDescription',
    image: '/images/products/feed/sinking-feed-large-granule.png',
    theme: 'red',
    specKeys: [
      'homeProductLargeSpecSize',
      'homeProductFeedOneSpecType',
      'homeProductGrowerSpecVariants',
    ],
    variants: [
      {
        nameKey: 'homeProductGrowerVariant35',
        priceKey: 'homeProductGrowerPrice35',
      },
      {
        nameKey: 'homeProductGrowerVariant32',
        priceKey: 'homeProductGrowerPrice32',
      },
    ],
  },
  {
    id: 'extruded-floating-feed-4mm-35',
    categoryKey: 'homeProductCategoryFeed',
    titleKey: 'homeProductExtrudedTitle',
    descriptionKey: 'homeProductExtrudedDescription',
    image: '/images/products/feed/extruded-floating-feed-4mm-35.png',
    theme: 'blue',
    priceKey: 'homeProductExtrudedPrice',
    specKeys: [
      'homeProductExtrudedSpecProtein',
      'homeProductExtrudedSpecSize',
      'homeProductExtrudedSpecType',
      'homeProductExtrudedSpecUse',
    ],
  },
];

export default {
  name: 'Home',
  components: { BrandLogo, LanguageSwitcher },
  setup() {
    const productTrack = ref(null);
    const medicineTrack = ref(null);
    const landingMedicines = ref([]);
    const catalogFeeds = ref([]);
    const medicinesLoading = ref(true);
    const activeBbtId = ref(bbtModules[0].id);
    const activeBbt = computed(() => (
      bbtModules.find((m) => m.id === activeBbtId.value) || bbtModules[0]
    ));
    const mainNav = [
      { id: 'kabinet', labelKey: 'homeNavCabinet' },
      { id: 'boss-fish', labelKey: 'homeNavBossFish' },
      { id: 'hamkorliklar', labelKey: 'homeNavPartners' },
      { id: 'narxlar', labelKey: 'homeNavPricing' },
    ];
    const pricingPlans = [
      {
        id: 'cabinet',
        nameKey: 'homePlanCabinetName',
        summaryKey: 'homePlanCabinetSummary',
        monthPriceKey: 'homePlanCabinetMonth',
        priceKey: 'homePlanCabinetPrice',
        ctaKey: 'homePricingChoose',
        popular: false,
        featureKeys: [
          'homePlanCabinetF1',
          'homePlanCabinetF2',
          'homePlanCabinetF3',
          'homePlanCabinetF4',
        ],
      },
      {
        id: 'control',
        nameKey: 'homePlanControlName',
        summaryKey: 'homePlanControlSummary',
        monthPriceKey: 'homePlanControlMonth',
        priceKey: 'homePlanControlPrice',
        ctaKey: 'homePricingChoose',
        popular: true,
        featureKeys: [
          'homePlanControlF1',
          'homePlanControlF2',
          'homePlanControlF3',
          'homePlanControlF4',
          'homePlanControlF5',
          'homePlanControlF6',
        ],
      },
      {
        id: 'individual',
        nameKey: 'homePlanIndividualName',
        summaryKey: 'homePlanIndividualSummary',
        monthPriceKey: null,
        priceKey: 'homePlanIndividualPrice',
        ctaKey: 'homePricingNegotiate',
        popular: false,
        featureKeys: [
          'homePlanIndividualF1',
          'homePlanIndividualF2',
          'homePlanIndividualF3',
          'homePlanIndividualF4',
          'homePlanIndividualF5',
        ],
      },
    ];
    const activeNav = ref('kabinet');
    let sectionObserver = null;

    function goToSection(id) {
      activeNav.value = id;
      const el = document.getElementById(id);
      if (el) {
        el.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    }

    function observeSections() {
      if (typeof IntersectionObserver === 'undefined') return;
      sectionObserver?.disconnect();
      sectionObserver = new IntersectionObserver(
        (entries) => {
          const visible = entries
            .filter((e) => e.isIntersecting)
            .sort((a, b) => b.intersectionRatio - a.intersectionRatio)[0];
          if (visible?.target?.id) {
            activeNav.value = visible.target.id;
          }
        },
        { rootMargin: '-20% 0px -55% 0px', threshold: [0.15, 0.4, 0.7] },
      );
      mainNav.forEach((item) => {
        const el = document.getElementById(item.id);
        if (el) sectionObserver.observe(el);
      });
    }

    const directorPortalRoute = computed(() => auth.portalRouteForRole('director'));
    const workerPortalRoute = computed(() => auth.portalRouteForRole('worker'));
    const cabinetRoute = computed(() => (
      auth.isAuthenticated() ? auth.homeForRole() : null
    ));

    const allFeedProducts = computed(() => [
      ...landingProducts,
      ...catalogFeeds.value.map((p) => ({
        ...p,
        title: p.name,
        categoryLabel: 'Yem',
        theme: p.theme || 'red',
      })),
    ]);

    function scrollTrack(track, direction) {
      if (!track) return;
      // Har bir slide track kengligiga teng — strelka ham shu qadam bilan yursin
      const amount = track.clientWidth || 0;
      if (!amount) return;
      track.scrollBy({ left: direction * amount, behavior: 'smooth' });
    }

    function scrollProducts(direction) {
      scrollTrack(productTrack.value, direction);
    }

    function scrollMedicines(direction) {
      scrollTrack(medicineTrack.value, direction);
    }

    async function loadCatalog() {
      medicinesLoading.value = true;
      try {
        const { data } = await window.axios.get('/catalog/products');
        const list = data.products || [];
        landingMedicines.value = list.filter((p) => p.type === 'medicine');
        catalogFeeds.value = list.filter((p) => p.type === 'feed');
      } catch (e) {
        landingMedicines.value = [];
        catalogFeeds.value = [];
      } finally {
        medicinesLoading.value = false;
      }
    }

    onMounted(() => {
      loadCatalog();
      observeSections();
    });

    onBeforeUnmount(() => {
      sectionObserver?.disconnect();
    });

    return {
      landingPartners,
      landingProducts,
      allFeedProducts,
      landingMedicines,
      medicinesLoading,
      bbtModules,
      activeBbtId,
      activeBbt,
      mainNav,
      activeNav,
      pricingPlans,
      goToSection,
      partnershipPhotos,
      serviceItems,
      inspectionPhotos,
      productTrack,
      medicineTrack,
      scrollProducts,
      scrollMedicines,
      directorPortalRoute,
      workerPortalRoute,
      cabinetRoute,
    };
  },
};
</script>
