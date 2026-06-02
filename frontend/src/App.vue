<script setup>
import { useBreakpoints } from "@vueuse/core";
import { onMounted } from "vue";
import { Toaster } from "vue-sonner";

import { useAuthStore } from "@/entities/auth/model/auth.store";
import { useInAppNotifications } from "@/features/notifications/model/useInAppNotifications";
import AppShellDesktop from "./app/layouts/AppShellDesktop.vue";
import AppShellMobile from "./app/layouts/AppShellMobile.vue";

const breakpoints = useBreakpoints({
    desktop: 920,
});

const isDesktop = breakpoints.greaterOrEqual("desktop");
const authStore = useAuthStore();
useInAppNotifications();

onMounted(() => {
    authStore.ensureMeLoaded();
});
</script>

<template>
    <component :is="isDesktop ? AppShellDesktop : AppShellMobile" />
    <Toaster position="top-center" rich-colors />
</template>
