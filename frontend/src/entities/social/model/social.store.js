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
        ensurePlantSocialDefaults(plantId) {
            const key = String(plantId);
            if (!Array.isArray(this.tipsByPlant[key])) this.tipsByPlant[key] = [];
            if (typeof this.likeCounts[key] !== "number") this.likeCounts[key] = 0;
            if (typeof this.liked[key] !== "boolean") this.liked[key] = false;
        },
        async loadLikeStatus(plantId) {
            if (!apiClient.token) return false;
            const key = String(plantId);

            try {
                const payload = await apiClient.get(`/plants/${plantId}/likes/is-liked`);
                const liked = Boolean(payload.liked);
                this.liked[key] = liked;
                return liked;
            } catch (error) {
                if (error?.status === 403) {
                    this.liked[key] = false;
                    return false;
                }
                throw error;
            }
        },
        async hydrateLikeStates(plantIds = []) {
            if (!apiClient.token || !plantIds.length) return {};

            const uniqueIds = [...new Set(plantIds.map((id) => String(id)).filter(Boolean))];
            const results = await Promise.allSettled(uniqueIds.map((id) => this.loadLikeStatus(id)));

            return uniqueIds.reduce((acc, id, index) => {
                const result = results[index];
                acc[id] = result.status === "fulfilled" ? Boolean(result.value) : false;
                return acc;
            }, {});
        },
        async loadPlantSocial(plantId) {
            this.loading = true;
            this.error = "";
            const key = String(plantId);
            this.ensurePlantSocialDefaults(plantId);

            try {
                if (!apiClient.token) {
                    return;
                }

                const [tipsResult, likesResult] = await Promise.allSettled([
                    apiClient.get(`/plants/${plantId}/tips?per_page=30`),
                    apiClient.get(`/plants/${plantId}/likes/count`),
                ]);

                if (tipsResult.status === "fulfilled") {
                    this.tipsByPlant[key] = unwrapApiCollection(tipsResult.value);
                } else if (tipsResult.reason?.status !== 403) {
                    throw tipsResult.reason;
                }

                if (likesResult.status === "fulfilled") {
                    this.likeCounts[key] = Number(likesResult.value.likes_count || 0);
                } else if (likesResult.reason?.status !== 403) {
                    throw likesResult.reason;
                }

                await this.loadLikeStatus(plantId);
            } catch (error) {
                this.error = error?.message || "Failed to load social data";
            } finally {
                this.loading = false;
            }
        },
        async toggleLike(plantId) {
            if (!apiClient.token) {
                throw new Error("Sign in to like plants.");
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
                throw new Error("Sign in to send tips.");
            }

            const payload = await apiClient.post(`/plants/${plantId}/tips`, { content });
            const tip = payload.data || payload;
            const key = String(plantId);
            this.tipsByPlant[key] = [tip, ...(this.tipsByPlant[key] || [])];
            return tip;
        },
        async updateTipStatus(plantId, tipId, status) {
            if (!apiClient.token) {
                throw new Error("Sign in to manage tips.");
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
                throw new Error("Sign in to send reports.");
            }

            return apiClient.post(`/plants/${plantId}/reports`, { reason, details });
        },
        async reportTip(tipId, reason, details = "") {
            if (!apiClient.token) {
                throw new Error("Sign in to send reports.");
            }

            return apiClient.post(`/tips/${tipId}/reports`, { reason, details });
        },
    },
});