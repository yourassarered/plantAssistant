<script setup>
import { CalendarDays, Leaf, ListTodo, Plus, Search, Shield, UserRound } from "lucide-vue-next";
import { computed } from "vue";

import { useAuthStore } from "@/entities/auth/model/auth.store";

const authStore = useAuthStore();

const navItems = computed(() => [
    { to: "/feed", label: "Растения", icon: Leaf },
    { to: "/calendar", label: "Календарь", icon: CalendarDays },
    { to: "/tasks", label: "Задачи", icon: ListTodo },
    { to: "/add-plant", label: "Добавить", icon: Plus },
    { to: "/profile", label: "Профиль", icon: UserRound },
    ...(authStore.isAdmin ? [{ to: "/admin", label: "Админка", icon: Shield }] : []),
]);
</script>

<template>
    <div class="desktop-shell">
        <aside class="desktop-sidebar">
            <RouterLink class="desktop-brand" to="/feed">
                <span class="desktop-brand__mark">PA</span>
                <span>
                    <strong>Plant Assistant</strong>
                    <small>уход без пропусков</small>
                </span>
            </RouterLink>

            <label class="desktop-search">
                <Search :size="18" />
                <input placeholder="Поиск растения" />
            </label>

            <nav class="desktop-nav">
                <RouterLink
                    v-for="item in navItems"
                    :key="item.to"
                    :to="item.to"
                    class="desktop-nav__link"
                >
                    <component :is="item.icon" :size="19" />
                    {{ item.label }}
                </RouterLink>
            </nav>
        </aside>

        <main class="desktop-content">
            <RouterView />
        </main>
    </div>
</template>
