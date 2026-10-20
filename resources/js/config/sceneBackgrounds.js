/** Har bir yo‘nalish uchun alohida baliqchilik fon sahnesi */
export const sceneBackgrounds = {
    home: {
        image: '/images/scenes/scene-home-coral.png',
        position: 'center 35%',
        rays: true,
        bubbles: true,
        bubbleCount: 8,
        kenBurns: true,
    },
    directorLogin: {
        image: '/images/scenes/scene-login-dawn.png',
        position: 'center 45%',
        rays: true,
        bubbles: true,
        bubbleCount: 5,
        kenBurns: true,
    },
    workerLogin: {
        image: '/images/scenes/scene-fishery-underwater.png',
        position: 'center 50%',
        rays: true,
        bubbles: true,
        bubbleCount: 7,
        kenBurns: false,
    },
    ownerLogin: {
        image: '/images/scenes/scene-pond-evening.png',
        position: 'center 40%',
        rays: false,
        bubbles: true,
        bubbleCount: 4,
        kenBurns: true,
    },
    vengerLogin: {
        image: '/images/scenes/scene-pond-evening.png',
        position: 'center 40%',
        rays: false,
        bubbles: true,
        bubbleCount: 4,
        kenBurns: true,
    },
    ponds: {
        image: '/images/scenes/scene-ponds-aerial.png',
        position: 'center 30%',
        rays: true,
        bubbles: false,
        bubbleCount: 0,
        kenBurns: true,
    },
    branch: {
        image: '/images/scenes/scene-pond-evening.png',
        position: 'center 55%',
        rays: false,
        bubbles: true,
        bubbleCount: 5,
        kenBurns: true,
    },
    fishery: {
        image: '/images/scenes/scene-fishery-underwater.png',
        position: 'center 48%',
        rays: true,
        bubbles: true,
        bubbleCount: 7,
        kenBurns: false,
    },
    finance: {
        image: '/images/scenes/scene-ponds-aerial.png',
        position: 'center 70%',
        rays: false,
        bubbles: false,
        bubbleCount: 0,
        kenBurns: true,
    },
    ownerDashboard: {
        image: '/images/scenes/scene-coastal-sunset.png',
        position: 'center 45%',
        rays: true,
        bubbles: true,
        bubbleCount: 6,
        kenBurns: true,
    },
    ownerDirectors: {
        image: '/images/scenes/scene-ocean-sunset.png',
        position: 'center 50%',
        rays: true,
        bubbles: false,
        bubbleCount: 0,
        kenBurns: true,
    },
    ownerWorkers: {
        image: '/images/scenes/scene-fishery-underwater.png',
        position: 'center 48%',
        rays: true,
        bubbles: true,
        bubbleCount: 5,
        kenBurns: false,
    },
    ownerFarms: {
        image: '/images/scenes/scene-ponds-aerial.png',
        position: 'center 32%',
        rays: true,
        bubbles: false,
        bubbleCount: 0,
        kenBurns: true,
    },
    ownerPonds: {
        image: '/images/scenes/scene-ponds-aerial.png',
        position: 'center 32%',
        rays: true,
        bubbles: false,
        bubbleCount: 0,
        kenBurns: true,
    },
    ownerSecurity: {
        image: '/images/scenes/scene-pond-evening.png',
        position: 'center 40%',
        rays: false,
        bubbles: true,
        bubbleCount: 4,
        kenBurns: true,
    },
    ownerPlanner: {
        image: '/images/scenes/scene-login-dawn.png',
        position: 'center 42%',
        rays: true,
        bubbles: false,
        bubbleCount: 0,
        kenBurns: true,
    },
    directorForm: {
        image: '/images/scenes/scene-coastal-sunset.png',
        position: 'center 55%',
        rays: true,
        bubbles: true,
        bubbleCount: 4,
        kenBurns: true,
    },
};

export function sceneForRoute(route) {
    if (route.meta?.scene) return route.meta.scene;
    if (route.meta?.directorHub) {
        const hubMap = {
            'director-home': 'ponds',
            'director-farm-hub': 'branch',
            'director-fishery-ponds': 'ponds',
            'director-fishery': 'fishery',
            'director-finance': 'finance',
        };
        return hubMap[route.name] || 'ponds';
    }
    if (route.meta?.director && !route.meta?.directorHub) {
        return 'directorForm';
    }
    if (route.meta?.owner || route.meta?.venger || route.name === 'support-contact') {
        const ownerMap = {
            'owner-cabinet': 'ownerDashboard',
            'owner-directors': 'ownerDirectors',
            'owner-clients': 'ownerDirectors',
            'owner-workers': 'ownerWorkers',
            'owner-closure-requests': 'ownerPonds',
            'support-contact': 'ownerDashboard',
            'owner-ration-products': 'ownerPonds',
            'owner-ration-prices': 'ownerPonds',
            'owner-ration-list': 'ownerPonds',
            'owner-ration-show': 'ownerPonds',
            'owner-ration-new': 'ownerPonds',
            'owner-ration-edit': 'ownerPonds',
            'owner-catalog': 'ownerPonds',
            'owner-planner': 'ownerPlanner',
            'owner-security': 'ownerSecurity',
            'owner-venger': 'ownerSecurity',
            'owner-venger-client': 'ownerSecurity',
            'owner-venger-result': 'ownerDashboard',
            'venger-cabinet': 'ownerDashboard',
            'venger-results-hub': 'ownerDashboard',
            'venger-ponds': 'ownerDashboard',
            'venger-client': 'ownerDashboard',
            'venger-result-new': 'ownerDashboard',
            'venger-result': 'ownerDashboard',
            'venger-result-edit': 'ownerDashboard',
        };
        return ownerMap[route.name] || 'ownerDashboard';
    }
    return null;
}
