<script setup>
import { CalendarDays, Leaf, ListTodo, Plus, Shield, UserRound } from "lucide-vue-next";
import { computed } from "vue";

import { useAuthStore } from "@/entities/auth/model/auth.store";

const authStore = useAuthStore();

const items = computed(() => [
    { to: "/feed", label: "Лента", icon: Leaf },
    { to: "/calendar", label: "Календарь", icon: CalendarDays },
    { to: "/tasks", label: "Уход", icon: ListTodo },
    { to: "/add-plant", label: "Добавить", icon: Plus },
    { to: "/profile", label: "Профиль", icon: UserRound },
    ...(authStore.isAdmin ? [{ to: "/admin", label: "Админ", icon: Shield }] : []),
]);
</script>

<template>
    <nav
        class="bottom-nav"
        :style="{ '--nav-count': items.length }"
        aria-label="Основная навигация"
    >
        <RouterLink
            v-for="item in items"
            :key="item.to"
            :to="item.to"
            class="bottom-nav__item"
        >
            <component :is="item.icon" :size="20" />
            <span>{{ item.label }}</span>
        </RouterLink>
    </nav>
</template>

<style scoped>
.bottom-nav {
    position: fixed;
    right: 12px;
    bottom: 14px;
    left: 12px;
    z-index: 20;
    display: grid;
    grid-template-columns: repeat(var(--nav-count), minmax(0, 1fr));
    max-width: 496px;
    margin: 0 auto;
    padding: 8px;
    border: 1px solid rgba(255, 255, 255, 0.72);
    border-radius: 18px;
    background: rgba(15, 112, 46, 0.94);
    box-shadow: 0 18px 40px rgba(7, 58, 24, 0.28);
}

.bottom-nav__item {
    display: grid;
    min-width: 0;
    height: 52px;
    place-items: center;
    border-radius: 12px;
    color: rgba(255, 255, 255, 0.78);
    font-size: 10px;
    font-weight: 800;
}

.bottom-nav__item span {
    overflow: hidden;
    max-width: 100%;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.bottom-nav__item.router-link-active {
    color: #0c5e2a;
    background: #fff;
}
</style>
