<script setup>
import { Bell, CheckCheck, Trash2, X } from "lucide-vue-next";
import { computed, ref } from "vue";

import { useNotificationStore } from "@/entities/notification/model/notification.store";

const props = defineProps({
    compact: { type: Boolean, default: false },
});

const notificationStore = useNotificationStore();
const isOpen = ref(false);

const notifications = computed(() => notificationStore.latest);
const unreadCount = computed(() => notificationStore.unreadCount);
const unreadLabel = computed(() =>
    unreadCount.value > 9 ? "9+" : String(unreadCount.value),
);

const closePanel = () => {
    isOpen.value = false;
};
</script>

<template>
    <div
        class="notification-center"
        :class="{ 'notification-center--compact': props.compact }"
    >
        <button
            type="button"
            class="notification-trigger"
            :aria-expanded="isOpen"
            aria-label="Уведомления"
            @click="isOpen = !isOpen"
        >
            <Bell :size="18" />
            <span v-if="unreadCount" class="notification-trigger__badge">
                {{ unreadLabel }}
            </span>
        </button>

        <section v-if="isOpen" class="notification-panel">
            <header class="notification-panel__head">
                <div>
                    <strong>Уведомления</strong>
                    <span>Только пока приложение открыто</span>
                </div>
                <button
                    type="button"
                    class="notification-icon-button"
                    aria-label="Закрыть уведомления"
                    @click="closePanel"
                >
                    <X :size="16" />
                </button>
            </header>

            <div
                v-if="notifications.length"
                class="notification-panel__actions"
            >
                <button type="button" @click="notificationStore.markAllRead">
                    <CheckCheck :size="15" />
                    Прочитано
                </button>
                <button type="button" @click="notificationStore.clear">
                    <Trash2 :size="15" />
                    Очистить
                </button>
            </div>

            <div v-if="notifications.length" class="notification-list">
                <article
                    v-for="notification in notifications"
                    :key="notification.id"
                    class="notification-item"
                    :class="{
                        'notification-item--unread': !notification.read,
                        [`notification-item--${notification.type}`]: true,
                    }"
                >
                    <div>
                        <strong>{{ notification.title }}</strong>
                        <p v-if="notification.body">{{ notification.body }}</p>
                    </div>

                    <div class="notification-item__actions">
                        <RouterLink
                            v-if="notification.actionTo"
                            :to="notification.actionTo"
                            @click="notificationStore.markRead(notification.id)"
                        >
                            Открыть
                        </RouterLink>
                        <button
                            type="button"
                            @click="notificationStore.remove(notification.id)"
                        >
                            Убрать
                        </button>
                    </div>
                </article>
            </div>

            <p v-else class="notification-empty">Пока нет новых напоминаний.</p>
        </section>
    </div>
</template>

<style scoped>
.notification-center {
    position: relative;
    z-index: 30;
}

.notification-trigger {
    position: relative;
    display: inline-grid;
    width: 42px;
    height: 42px;
    place-items: center;
    border: 1px solid #d5dfd2;
    border-radius: 14px;
    color: #233627;
    background: #fff;
    box-shadow: 0 10px 22px rgba(22, 42, 24, 0.08);
    cursor: pointer;
}

.notification-trigger__badge {
    position: absolute;
    top: -5px;
    right: -5px;
    min-width: 19px;
    height: 19px;
    padding: 0 5px;
    border: 2px solid #fff;
    border-radius: 999px;
    color: #fff;
    font-size: 11px;
    font-weight: 900;
    line-height: 15px;
    text-align: center;
    background: #b3261e;
}

.notification-panel {
    position: absolute;
    top: calc(100% + 10px);
    left: 0;
    display: grid;
    width: min(340px, calc(100vw - 28px));
    gap: 12px;
    padding: 14px;
    border: 1px solid #d7e3d2;
    border-radius: 18px;
    background: rgba(255, 255, 255, 0.98);
    box-shadow: 0 24px 48px rgba(24, 45, 28, 0.18);
}

.notification-panel__head,
.notification-panel__actions,
.notification-item__actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}

.notification-panel__head div {
    display: grid;
    gap: 2px;
}

.notification-panel__head span,
.notification-empty,
.notification-item p {
    color: var(--color-muted);
}

.notification-panel__actions button,
.notification-item__actions button,
.notification-item__actions a,
.notification-icon-button {
    border: 0;
    color: #285f35;
    font-weight: 900;
    background: transparent;
    cursor: pointer;
}

.notification-panel__actions button {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 0;
}

.notification-icon-button {
    display: grid;
    width: 30px;
    height: 30px;
    place-items: center;
    border-radius: 50%;
    color: var(--color-muted);
}

.notification-list {
    display: grid;
    max-height: min(430px, 56vh);
    gap: 8px;
    overflow: auto;
}

.notification-item {
    display: grid;
    gap: 10px;
    padding: 11px;
    border: 1px solid #e2eadf;
    border-radius: 14px;
    background: #f8faf6;
}

.notification-item--unread {
    border-color: #bdd7b5;
    background: #f0f8ed;
}

.notification-item--warning.notification-item--unread {
    border-color: #f0c4a7;
    background: #fff5ed;
}

.notification-item strong,
.notification-item p,
.notification-empty {
    margin: 0;
}

.notification-item__actions {
    justify-content: flex-start;
}

.notification-item__actions a {
    text-decoration: none;
}

.notification-center--compact {
    position: fixed;
    top: 12px;
    right: 12px;
    z-index: 80;
}

.notification-center--compact .notification-panel {
    position: fixed;
    top: 62px;
    right: 12px;
    left: 12px;
    width: auto;
}
</style>
