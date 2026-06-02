<script setup>
import { Leaf, ListTodo, Shield, Sprout, UserRound } from "lucide-vue-next";
import { computed } from "vue";

import { useAuthStore } from "@/entities/auth/model/auth.store";
import tabIcon from "@/shared/assets/tab-icon.svg";
import NotificationCenter from "@/widgets/notifications/NotificationCenter.vue";

const authStore = useAuthStore();

const navItems = computed(() => [
    { to: "/feed", label: "Лента", icon: Leaf },
    { to: "/my-plants", label: "Мои растения", icon: Sprout },
    { to: "/tasks", label: "Задачи", icon: ListTodo },
    { to: "/profile", label: "Профиль", icon: UserRound },
    ...(authStore.isAdmin
        ? [{ to: "/admin", label: "Админка", icon: Shield }]
        : []),
]);
</script>

<template>
    <div class="desktop-shell">
        <aside class="desktop-sidebar">
            <RouterLink class="desktop-brand" to="/feed">
                <span class="desktop-brand__mark">
                    <img :src="tabIcon" alt="" class="desktop-brand__icon" />
                </span>
                <span>
                    <strong>Plant Assistant</strong>
                    <small>уход без пропусков</small>
                </span>
            </RouterLink>

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

            <NotificationCenter class="desktop-notifications" />
        </aside>

        <main class="desktop-content">
            <RouterView />
        </main>
    </div>
</template>

<style scoped>
.desktop-shell {
    display: grid;
    grid-template-columns: 280px minmax(0, 1fr);
    min-height: 100dvh;
    background: #edf2eb;
}

.desktop-sidebar {
    display: grid;
    align-content: start;
    gap: 14px;
    padding: 18px;
    border-right: 1px solid #d8e2d4;
    background: #f7faf5;
}

.desktop-brand {
    display: flex;
    align-items: center;
    gap: 10px;
    color: inherit;
    transition: transform 0.18s ease;
}

.desktop-brand__mark {
    display: grid;
    width: 36px;
    height: 36px;
    place-items: center;
    border-radius: 10px;
    color: #fff;
    font-weight: 900;
    background: #0f702e;
}

.desktop-brand__icon {
    width: 22px;
    height: 22px;
    object-fit: contain;
}

.desktop-brand strong {
    display: block;
}

.desktop-brand small {
    color: var(--color-muted);
}

.desktop-nav {
    display: grid;
    gap: 8px;
}

.desktop-notifications {
    margin-top: 6px;
}

.desktop-nav__link {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    min-height: 40px;
    padding: 0 12px;
    border-radius: 10px;
    color: #2d3a2f;
    font-weight: 800;
    transition:
        transform 0.18s ease,
        background-color 0.18s ease,
        color 0.18s ease,
        box-shadow 0.18s ease;
}

.desktop-nav__link.router-link-active {
    color: #fff;
    background: #0f702e;
    box-shadow: 0 12px 24px rgba(15, 112, 46, 0.18);
}

@media (hover: hover) and (pointer: fine) {
    .desktop-brand:hover {
        transform: translateY(-1px);
    }

    .desktop-nav__link:hover {
        transform: translateX(4px);
        background: rgba(15, 112, 46, 0.08);
    }

    .desktop-nav__link.router-link-active:hover {
        background: #0c5e2a;
    }
}

.desktop-content {
    padding: 18px;
}
</style>
