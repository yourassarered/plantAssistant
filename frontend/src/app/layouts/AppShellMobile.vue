<script setup>
import BottomNavMobile from "@/widgets/navigation/BottomNavMobile.vue";
</script>

<template>
    <div class="app-shell app-shell--mobile">
        <main class="app-shell__content">
            <RouterView />
        </main>
        <BottomNavMobile />
    </div>
</template>
