import { createRouter, createWebHistory } from "vue-router";

import { useAuthStore } from "@/entities/auth/model/auth.store";

export const router = createRouter({
    history: createWebHistory(),
    routes: [
        { path: "/", redirect: "/feed" },
        {
            path: "/feed",
            name: "feed",
            component: () => import("@/pages/feed/FeedPage.vue"),
        },
        {
            path: "/my-plants",
            name: "my-plants",
            component: () => import("@/pages/my-plants/MyPlantsPage.vue"),
            meta: { requiresAuth: true },
        },
        {
            path: "/plants/:id",
            name: "plant-details",
            component: () => import("@/pages/plant/PlantDetailsPage.vue"),
        },
        {
            path: "/users/:id",
            name: "user-profile",
            component: () => import("@/pages/user-profile/UserProfilePage.vue"),
        },
        {
            path: "/tasks",
            name: "tasks",
            component: () => import("@/pages/tasks/TasksPage.vue"),
            meta: { requiresAuth: true },
        },
        {
            path: "/add-plant",
            name: "add-plant",
            component: () => import("@/pages/plant-edit/PlantEditPage.vue"),
            meta: { requiresAuth: true },
        },
        {
            path: "/plants/:id/edit",
            name: "edit-plant",
            component: () => import("@/pages/plant-edit/PlantEditPage.vue"),
            meta: { requiresAuth: true },
        },
        {
            path: "/plants/:id/care",
            name: "edit-plant-care",
            component: () => import("@/pages/plant-edit/PlantEditPage.vue"),
            meta: { requiresAuth: true },
        },
        {
            path: "/plants/:id/photos",
            name: "edit-plant-photos",
            component: () => import("@/pages/plant-edit/PlantEditPage.vue"),
            meta: { requiresAuth: true },
        },
        {
            path: "/profile",
            name: "profile",
            component: () => import("@/pages/profile/ProfilePage.vue"),
        },
        {
            path: "/admin",
            name: "admin",
            component: () => import("@/pages/admin/AdminPage.vue"),
            meta: { requiresAuth: true, requiresAdmin: true },
        },
        {
            path: "/:pathMatch(.*)*",
            name: "not-found",
            component: () => import("@/pages/not-found/NotFoundPage.vue"),
        },
    ],
});

router.beforeEach(async (to) => {
    const authStore = useAuthStore();
    await authStore.ensureMeLoaded();

    if (to.meta.requiresAuth && !authStore.isAuthenticated) {
        return { name: "profile", query: { redirect: to.fullPath } };
    }

    if (to.meta.requiresAdmin && !authStore.isAdmin) {
        return { name: "feed" };
    }

    return true;
});
