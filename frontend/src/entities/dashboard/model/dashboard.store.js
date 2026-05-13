import { defineStore } from "pinia";

import { apiClient } from "@/shared/api/client";

export const useDashboardStore = defineStore("dashboard", {
    state: () => ({
        overview: null,
        activity: null,
        health: null,
        loading: false,
        error: "",
    }),
    actions: {
        async load(days = 30) {
            if (!apiClient.token) return;

            this.loading = true;
            this.error = "";

            try {
                const [overview, activity, health] = await Promise.all([
                    apiClient.get("/dashboard/overview"),
                    apiClient.get(`/dashboard/activity?days=${days}`),
                    apiClient.get("/dashboard/plant-health"),
                ]);

                this.overview = overview;
                this.activity = activity;
                this.health = health;
            } catch (error) {
                this.error = error.message;
            } finally {
                this.loading = false;
            }
        },
        clear() {
            this.overview = null;
            this.activity = null;
            this.health = null;
            this.error = "";
        },
    },
});
