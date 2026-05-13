import { defineStore } from "pinia";

import { apiClient } from "@/shared/api/client";
import { unwrapApiCollection } from "@/shared/api/mappers";

export const useSocialStore = defineStore("social", {
    state: () => ({
        tipsByPlant: {},
        likeCounts: {},
        liked: {},
        loading: false,
        error: "",
    }),
    getters: {
        tipsFor: (state) => (plantId) => state.tipsByPlant[String(plantId)] || [],
        likeCountFor: (state) => (plantId) => state.likeCounts[String(plantId)] || 0,
        isLiked: (state) => (plantId) => Boolean(state.liked[String(plantId)]),
    },
    actions: {
        async loadPlantSocial(plantId) {
            this.loading = true;
            this.error = "";

            try {
                const [tipsPayload, likesPayload] = await Promise.all([
                    apiClient.get(`/plants/${plantId}/tips?per_page=30`),
                    apiClient.get(`/plants/${plantId}/likes/count`),
                ]);

                this.tipsByPlant[String(plantId)] = unwrapApiCollection(tipsPayload);
                this.likeCounts[String(plantId)] = likesPayload.likes_count || 0;

                if (apiClient.token) {
                    const likedPayload = await apiClient.get(`/plants/${plantId}/likes/is-liked`);
                    this.liked[String(plantId)] = Boolean(likedPayload.liked);
                }
            } catch (error) {
                this.error = error.message;
            } finally {
                this.loading = false;
            }
        },
        async toggleLike(plantId) {
            if (!apiClient.token) {
                throw new Error("Нужно войти, чтобы ставить лайки.");
            }

            const payload = await apiClient.post(`/plants/${plantId}/like`);
            this.liked[String(plantId)] = Boolean(payload.liked);
            this.likeCounts[String(plantId)] = Math.max(
                0,
                (this.likeCounts[String(plantId)] || 0) + (payload.liked ? 1 : -1),
            );
        },
        async createTip(plantId, content) {
            if (!apiClient.token) {
                throw new Error("Нужно войти, чтобы отправлять советы.");
            }

            const payload = await apiClient.post(`/plants/${plantId}/tips`, { content });
            const tip = payload.data || payload;
            const key = String(plantId);
            this.tipsByPlant[key] = [tip, ...(this.tipsByPlant[key] || [])];
            return tip;
        },
        async updateTipStatus(plantId, tipId, status) {
            if (!apiClient.token) {
                throw new Error("Нужно войти, чтобы управлять советами.");
            }

            const payload = await apiClient.put(`/tips/${tipId}/status`, { status });
            const updated = payload.data || payload;
            const key = String(plantId);
            this.tipsByPlant[key] = (this.tipsByPlant[key] || []).map((tip) =>
                tip.id === updated.id ? updated : tip,
            );
            return updated;
        },
        async reportPlant(plantId, reason, details = "") {
            if (!apiClient.token) {
                throw new Error("Нужно войти, чтобы отправлять жалобы.");
            }

            return apiClient.post(`/plants/${plantId}/reports`, { reason, details });
        },
        async reportTip(tipId, reason, details = "") {
            if (!apiClient.token) {
                throw new Error("Нужно войти, чтобы отправлять жалобы.");
            }

            return apiClient.post(`/tips/${tipId}/reports`, { reason, details });
        },
    },
});
