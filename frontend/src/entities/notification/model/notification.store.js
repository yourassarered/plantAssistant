import { defineStore } from "pinia";

const maxNotifications = 20;

const createId = () =>
    `${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;

export const useNotificationStore = defineStore("notifications", {
    state: () => ({
        items: [],
        seenKeys: {},
    }),
    getters: {
        unreadCount: (state) =>
            state.items.filter((notification) => !notification.read).length,
        latest: (state) => state.items.slice(0, maxNotifications),
    },
    actions: {
        notify({ key, title, body = "", type = "info", actionTo = "" }) {
            if (!key || this.seenKeys[key]) return null;

            const notification = {
                id: createId(),
                key,
                title,
                body,
                type,
                actionTo,
                read: false,
                createdAt: new Date().toISOString(),
            };

            this.seenKeys[key] = notification.createdAt;
            this.items = [notification, ...this.items].slice(
                0,
                maxNotifications,
            );

            return notification;
        },
        markRead(id) {
            this.items = this.items.map((notification) =>
                notification.id === id
                    ? { ...notification, read: true }
                    : notification,
            );
        },
        markAllRead() {
            this.items = this.items.map((notification) => ({
                ...notification,
                read: true,
            }));
        },
        remove(id) {
            this.items = this.items.filter(
                (notification) => notification.id !== id,
            );
        },
        clear(resetSeen = false) {
            this.items = [];
            if (resetSeen) {
                this.seenKeys = {};
            }
        },
    },
});
