import { createRouter, createWebHistory } from 'vue-router';
import auth from '../auth';
import { setDirectorFarmId } from '../utils/directorFarm';
import { getActingDirectorId } from '../utils/ownerContext';

import Home from '../pages/Home.vue';
import Guide from '../pages/Guide.vue';
import PublicLogin from '../pages/PublicLogin.vue';
import OwnerLogin from '../pages/OwnerLogin.vue';
import OwnerCabinet from '../pages/owner/OwnerCabinet.vue';
import OwnerDirectorHub from '../pages/owner/OwnerDirectorHub.vue';
import OwnerDirectors from '../pages/owner/OwnerDirectors.vue';
import OwnerWorkers from '../pages/owner/OwnerWorkers.vue';
import OwnerSecurity from '../pages/owner/OwnerSecurity.vue';
import OwnerVengerCarp from '../pages/owner/OwnerVengerCarp.vue';
import OwnerVengerStaff from '../pages/owner/OwnerVengerStaff.vue';
import OwnerPlanner from '../pages/owner/OwnerPlanner.vue';
import OwnerClosureRequests from '../pages/owner/OwnerClosureRequests.vue';
import OwnerRationShell from '../pages/owner/OwnerRationShell.vue';
import SupportContact from '../pages/SupportContact.vue';
import OwnerRationProducts from '../pages/owner/OwnerRationProducts.vue';
import OwnerRationList from '../pages/owner/OwnerRationList.vue';
import OwnerRationShow from '../pages/owner/OwnerRationShow.vue';
import OwnerRationForm from '../pages/owner/OwnerRationForm.vue';
import OwnerRationPrices from '../pages/owner/OwnerRationPrices.vue';
import OwnerCatalog from '../pages/owner/OwnerCatalog.vue';
import DirectorPonds from '../pages/director/DirectorPonds.vue';
import DirectorBranch from '../pages/director/DirectorBranch.vue';
import DirectorFisheryPonds from '../pages/director/DirectorFisheryPonds.vue';
import DirectorFishery from '../pages/director/DirectorFishery.vue';
import DirectorFinance from '../pages/director/DirectorFinance.vue';
import DirectorFinanceReport from '../pages/director/DirectorFinanceReport.vue';
import DirectorFinanceEntry from '../pages/director/DirectorFinanceEntry.vue';
import DirectorFinanceCategory from '../pages/director/DirectorFinanceCategory.vue';
import DirectorDailyReport from '../pages/director/DirectorDailyReport.vue';
import DirectorMainReport from '../pages/director/DirectorMainReport.vue';
import DirectorWaterAlert from '../pages/director/DirectorWaterAlert.vue';
import DirectorAddFish from '../pages/director/DirectorAddFish.vue';
import DirectorSaleLoss from '../pages/director/DirectorSaleLoss.vue';
import DirectorClosePond from '../pages/director/DirectorClosePond.vue';
import DirectorActivities from '../pages/director/DirectorActivities.vue';
import DirectorWarehouse from '../pages/director/DirectorWarehouse.vue';
import DirectorWarehouseIntake from '../pages/director/DirectorWarehouseIntake.vue';
import DirectorWarehouseStock from '../pages/director/DirectorWarehouseStock.vue';
import DirectorLegacyPondRedirect from '../pages/director/DirectorLegacyPondRedirect.vue';
import WorkerHome from '../pages/WorkerHome.vue';
import WorkerDailyReport from '../pages/worker/WorkerDailyReport.vue';
import WorkerMainReport from '../pages/worker/WorkerMainReport.vue';
import VengerLogin from '../pages/VengerLogin.vue';
import VengerCabinet from '../pages/venger/VengerCabinet.vue';
import VengerResultsHub from '../pages/venger/VengerResultsHub.vue';
import VengerPondsList from '../pages/venger/VengerPondsList.vue';
import VengerClientShow from '../pages/venger/VengerClientShow.vue';
import VengerResultForm from '../pages/venger/VengerResultForm.vue';
import VengerResultShow from '../pages/venger/VengerResultShow.vue';

const routes = [
    { path: '/', name: 'home', component: Home, meta: { scene: 'home', landing: true } },
    { path: '/asosiy-oyna', redirect: { name: 'home' } },
    { path: '/qollanma', name: 'site-guide', component: Guide, meta: { scene: 'home' } },

    { path: '/direktor/kirish', name: 'director-login', component: PublicLogin, props: { role: 'director' }, meta: { guest: true, scene: 'directorLogin' } },
    { path: '/direktor/royxat', redirect: { name: 'director-login' } },
    { path: '/hodim/kirish', name: 'worker-login', component: PublicLogin, props: { role: 'worker' }, meta: { guest: true, scene: 'workerLogin' } },
    { path: '/hodim/royxat', redirect: { name: 'worker-login' } },

    { path: '/egasi/kirish', name: 'owner-login', component: OwnerLogin, meta: { guest: true, scene: 'ownerLogin' } },
    { path: '/venger/kirish', name: 'venger-login', component: VengerLogin, meta: { guest: true, scene: 'vengerLogin' } },
    { path: '/venger', name: 'venger-cabinet', component: VengerCabinet, meta: { auth: true, venger: true } },
    { path: '/venger/natijalar', name: 'venger-results-hub', component: VengerResultsHub, meta: { auth: true, venger: true } },
    { path: '/venger/havzalar', name: 'venger-ponds', component: VengerPondsList, meta: { auth: true, venger: true } },
    { path: '/venger/mijoz/:clientId', name: 'venger-client', component: VengerClientShow, meta: { auth: true, venger: true } },
    { path: '/venger/mijoz/:clientId/natija', name: 'venger-result-new', component: VengerResultForm, meta: { auth: true, venger: true } },
    { path: '/venger/natija/:resultId', name: 'venger-result', component: VengerResultShow, meta: { auth: true, venger: true } },
    { path: '/venger/natija/:resultId/tahrir', name: 'venger-result-edit', component: VengerResultForm, meta: { auth: true, venger: true } },

    { path: '/egasi', name: 'owner-cabinet', component: OwnerCabinet, meta: { auth: true, owner: true } },
    { path: '/egasi/direktor/:directorId', name: 'owner-director-hub', component: OwnerDirectorHub, meta: { auth: true, owner: true, director: true, directorHub: true } },
    { path: '/egasi/mijozlar', name: 'owner-clients', component: OwnerDirectors, meta: { auth: true, owner: true } },
    { path: '/egasi/direktorlar', redirect: { name: 'owner-clients' } },
    { path: '/egasi/hodimlar', name: 'owner-workers', component: OwnerWorkers, meta: { auth: true, owner: true } },
    { path: '/egasi/arizalar', name: 'owner-closure-requests', component: OwnerClosureRequests, meta: { auth: true, owner: true } },
    { path: '/murojaat', name: 'support-contact', component: SupportContact, meta: { scene: 'home', support: true } },
    {
        path: '/egasi/ratsion',
        component: OwnerRationShell,
        meta: { auth: true, owner: true },
        redirect: { name: 'owner-ration-products' },
        children: [
            { path: 'mahsulotlar', name: 'owner-ration-products', component: OwnerRationProducts, meta: { auth: true, owner: true } },
            { path: 'narxlar', name: 'owner-ration-prices', component: OwnerRationPrices, meta: { auth: true, owner: true } },
            { path: 'ratsionlar', name: 'owner-ration-list', component: OwnerRationList, meta: { auth: true, owner: true } },
            { path: 'ratsionlar/:id', name: 'owner-ration-show', component: OwnerRationShow, meta: { auth: true, owner: true } },
            { path: 'yangi', name: 'owner-ration-new', component: OwnerRationForm, meta: { auth: true, owner: true } },
            { path: 'yangi/:id', name: 'owner-ration-edit', component: OwnerRationForm, meta: { auth: true, owner: true } },
        ],
    },
    { path: '/egasi/asosiy-oyna', name: 'owner-catalog', component: OwnerCatalog, meta: { auth: true, owner: true } },
    { path: '/egasi/kundalik', name: 'owner-planner', component: OwnerPlanner, meta: { auth: true, owner: true } },
    { path: '/egasi/venger-carp', name: 'owner-venger', component: OwnerVengerCarp, meta: { auth: true, owner: true } },
    { path: '/egasi/venger-carp/hodimlar', name: 'owner-venger-staff', component: OwnerVengerStaff, meta: { auth: true, owner: true } },
    { path: '/egasi/venger-carp/mijozlar', name: 'owner-venger-clients', component: VengerCabinet, meta: { auth: true, owner: true } },
    { path: '/egasi/venger-carp/havzalar', name: 'owner-venger-ponds', component: VengerPondsList, meta: { auth: true, owner: true } },
    { path: '/egasi/venger-carp/natijalar', name: 'owner-venger-results', component: VengerResultsHub, meta: { auth: true, owner: true } },
    { path: '/egasi/venger-carp/mijoz/:clientId', name: 'owner-venger-client', component: VengerClientShow, meta: { auth: true, owner: true } },
    { path: '/egasi/venger-carp/natija/:resultId', name: 'owner-venger-result', component: VengerResultShow, meta: { auth: true, owner: true } },
    { path: '/egasi/xavfsizlik', name: 'owner-security', component: OwnerSecurity, meta: { auth: true, owner: true } },

    { path: '/direktor', name: 'director-home', component: DirectorPonds, meta: { auth: true, director: true, directorHub: true, scene: 'farms' } },
    { path: '/direktor/hojalik', name: 'director-farm-hub', component: DirectorBranch, meta: { auth: true, director: true, directorHub: true, scene: 'branch' } },
    { path: '/direktor/baliqchilik', name: 'director-fishery-ponds', component: DirectorFisheryPonds, meta: { auth: true, director: true, directorHub: true, scene: 'ponds' } },
    { path: '/direktor/moliya', name: 'director-finance', component: DirectorFinance, meta: { auth: true, director: true, directorHub: true, scene: 'finance' } },
    { path: '/direktor/moliya/xisobot/:category', name: 'director-finance-report', component: DirectorFinanceReport, meta: { auth: true, director: true, directorHub: true, scene: 'finance' } },
    { path: '/direktor/moliya/kiritish/:category', name: 'director-finance-entry', component: DirectorFinanceEntry, meta: { auth: true, director: true, directorHub: true, scene: 'finance' } },
    { path: '/direktor/moliya/xarajat/:category', name: 'director-finance-category', component: DirectorFinanceCategory, meta: { auth: true, director: true, directorHub: true, scene: 'finance' } },
    { path: '/direktor/moliya/investitsiya', redirect: { name: 'director-finance' } },
    { path: '/direktor/ombor', name: 'director-warehouse', component: DirectorWarehouse, meta: { auth: true, director: true, directorHub: true } },
    { path: '/direktor/ombor/kirim', name: 'director-warehouse-intake', component: DirectorWarehouseIntake, meta: { auth: true, director: true } },
    { path: '/direktor/ombor/zaxira', name: 'director-warehouse-stock', component: DirectorWarehouseStock, meta: { auth: true, director: true } },
    { path: '/direktor/murojaat', name: 'director-support', component: SupportContact, meta: { auth: true, director: true, support: true } },

    // Eski ichma-ich URL → yangi flat bo‘limlar
    {
        path: '/direktor/hojalik/:farmId',
        redirect: (to) => {
            setDirectorFarmId(to.params.farmId);
            return { name: 'director-farm-hub' };
        },
    },
    {
        path: '/direktor/hojalik/:farmId/baliqchilik',
        redirect: (to) => {
            setDirectorFarmId(to.params.farmId);
            return { name: 'director-fishery-ponds' };
        },
    },
    {
        path: '/direktor/hojalik/:farmId/moliya',
        redirect: (to) => {
            setDirectorFarmId(to.params.farmId);
            return { name: 'director-finance' };
        },
    },
    {
        path: '/direktor/hojalik/:farmId/moliya/xisobot/:category',
        redirect: (to) => {
            setDirectorFarmId(to.params.farmId);
            return { name: 'director-finance-report', params: { category: to.params.category } };
        },
    },
    {
        path: '/direktor/hojalik/:farmId/moliya/kiritish/:category',
        redirect: (to) => {
            setDirectorFarmId(to.params.farmId);
            return { name: 'director-finance-entry', params: { category: to.params.category } };
        },
    },
    {
        path: '/direktor/hojalik/:farmId/moliya/xarajat/:category',
        redirect: (to) => {
            setDirectorFarmId(to.params.farmId);
            return { name: 'director-finance-category', params: { category: to.params.category } };
        },
    },
    {
        path: '/direktor/hojalik/:farmId/moliya/investitsiya',
        redirect: (to) => {
            setDirectorFarmId(to.params.farmId);
            return { name: 'director-finance' };
        },
    },
    {
        path: '/direktor/hojalik/:farmId/ombor',
        redirect: (to) => {
            setDirectorFarmId(to.params.farmId);
            return { name: 'director-warehouse' };
        },
    },
    {
        path: '/direktor/hojalik/:farmId/ombor/kirim',
        redirect: (to) => {
            setDirectorFarmId(to.params.farmId);
            return { name: 'director-warehouse-intake' };
        },
    },
    {
        path: '/direktor/hojalik/:farmId/ombor/zaxira',
        redirect: (to) => {
            setDirectorFarmId(to.params.farmId);
            return { name: 'director-warehouse-stock' };
        },
    },

    { path: '/direktor/hovuz/:pondId/baliqchilik', name: 'director-fishery', component: DirectorFishery, meta: { auth: true, director: true, directorHub: true, scene: 'fishery' } },
    { path: '/direktor/hovuz/:pondId/kunlik-hisobot', name: 'director-daily', component: DirectorDailyReport, meta: { auth: true, director: true } },
    { path: '/direktor/hovuz/:pondId/asosiy-hisobot', name: 'director-main-report', component: DirectorMainReport, meta: { auth: true, director: true } },
    { path: '/direktor/hovuz/:pondId/suv-ogohlantirish/:reportId', name: 'director-water-alert', component: DirectorWaterAlert, meta: { auth: true, director: true } },
    { path: '/direktor/hovuz/:pondId/baliq-qoshish', name: 'director-add-fish', component: DirectorAddFish, meta: { auth: true, director: true } },
    { path: '/direktor/hovuz/:pondId/sotish-poterya', name: 'director-sale-loss', component: DirectorSaleLoss, meta: { auth: true, director: true } },
    { path: '/direktor/hovuz/:pondId/havzani-yopish', name: 'director-close-pond', component: DirectorClosePond, meta: { auth: true, director: true } },
    { path: '/direktor/hovuz/:pondId/hisobotlar', name: 'director-activities', component: DirectorActivities, meta: { auth: true, director: true } },

    { path: '/direktor/havza/:pondId', name: 'director-legacy-pond', component: DirectorLegacyPondRedirect, meta: { auth: true, director: true } },
    { path: '/direktor/havza/:pondId/baliqchilik', redirect: (to) => ({ name: 'director-fishery', params: { pondId: to.params.pondId } }) },
    { path: '/direktor/havza/:pondId/moliya', component: DirectorLegacyPondRedirect, meta: { auth: true, director: true } },
    { path: '/direktor/havza/:pondId/ombor', component: DirectorLegacyPondRedirect, meta: { auth: true, director: true } },
    { path: '/direktor/havza/:pondId/ombor/kirim', component: DirectorLegacyPondRedirect, meta: { auth: true, director: true } },
    { path: '/direktor/havza/:pondId/ombor/zaxira', component: DirectorLegacyPondRedirect, meta: { auth: true, director: true } },
    { path: '/direktor/havza/:pondId/kunlik-hisobot', redirect: (to) => ({ name: 'director-daily', params: { pondId: to.params.pondId } }) },
    { path: '/direktor/havza/:pondId/asosiy-hisobot', redirect: (to) => ({ name: 'director-main-report', params: { pondId: to.params.pondId } }) },
    { path: '/direktor/havza/:pondId/baliq-qoshish', redirect: (to) => ({ name: 'director-add-fish', params: { pondId: to.params.pondId } }) },
    { path: '/direktor/havza/:pondId/sotish-poterya', redirect: (to) => ({ name: 'director-sale-loss', params: { pondId: to.params.pondId } }) },
    { path: '/direktor/havza/:pondId/hisobotlar', redirect: (to) => ({ name: 'director-activities', params: { pondId: to.params.pondId } }) },

    { path: '/hodim', name: 'worker-home', component: WorkerHome, meta: { auth: true, worker: true } },
    { path: '/hodim/kunlik-hisobot', name: 'worker-daily', component: WorkerDailyReport, meta: { auth: true, worker: true } },
    { path: '/hodim/asosiy-hisobot', name: 'worker-main-report', component: WorkerMainReport, meta: { auth: true, worker: true } },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

router.beforeEach(async (to, from, next) => {
    if (auth.hasValidSession() && !auth.state.user) {
        await auth.fetchUser();
    }

    // Landing (`/` / bossfish.uz) always stays public — even when logged in.
    // Kabinetga kirish alohida login / sidebar orqali.

    if (to.meta.auth && !auth.isAuthenticated()) {
        if (auth.hasValidSession()) {
            auth.clearAuth();
        }
        if (to.meta.owner) return next({ name: 'owner-login' });
        if (to.meta.venger) return next({ name: 'venger-login' });
        if (to.meta.director) return next({ name: 'director-login' });
        if (to.meta.worker) return next({ name: 'worker-login' });
        return next({ name: 'home' });
    }

    if (to.meta.guest && auth.isAuthenticated()) {
        // Boshqa rol loginiga kirmasin — o‘z kabinetiga
        return next(auth.homeForRole());
    }

    if (to.meta.guest && auth.hasValidSession() && !auth.state.user) {
        await auth.fetchUser();
        if (auth.isAuthenticated()) {
            return next(auth.homeForRole());
        }
        // fetchUser muvaffaqiyatsiz — toza login formasi
    }

    if (to.meta.owner && !auth.isOwner()) {
        return next({ name: 'home' });
    }

    if (to.meta.venger && !auth.isVenger()) {
        return next({ name: 'home' });
    }

    if (to.meta.director && !auth.isDirector() && !auth.isOwner()) {
        return next({ name: 'home' });
    }

    // Egasi odatda /direktor ga kirmaydi; lekin «direktor sifatida» rejimida ruxsat.
    if (auth.isOwner() && to.name === 'director-home' && !getActingDirectorId()) {
        return next({ name: 'owner-cabinet' });
    }

    if (to.meta.worker && !auth.isWorker()) {
        return next({ name: 'home' });
    }

    next();
});

export default router;
