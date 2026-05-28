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
        tipsFor: (state) => (plantId) =>
            state.tipsByPlant[String(plantId)] || [],
        likeCountFor: (state) => (plantId) =>
            state.likeCounts[String(plantId)] || 0,
        isLiked: (state) => (plantId) => Boolean(state.liked[String(plantId)]),
    },
    actions: {
        ensurePlantSocialDefaults(plantId) {
            const key = String(plantId);
            if (!Array.isArray(this.tipsByPlant[key]))
                this.tipsByPlant[key] = [];
            if (typeof this.likeCounts[key] !== "number")
                this.likeCounts[key] = 0;
            if (typeof this.liked[key] !== "boolean") this.liked[key] = false;
        },
        applyPlantSnapshot(plant) {
            if (!plant) return;

            const key = String(plant.apiId || plant.id);
            const raw = plant.raw || {};
            const tips = Array.isArray(raw.tips?.data)
                ? raw.tips.data
                : Array.isArray(raw.tips)
                  ? raw.tips
                  : null;

            this.ensurePlantSocialDefaults(key);

            if (tips) {
                this.tipsByPlant[key] = tips;
            }

            this.likeCounts[key] = Number(
                raw.likes_count ??
                    plant.likesCount ??
                    this.likeCounts[key] ??
                    0,
            );

            if (typeof raw.user_liked === "boolean") {
                this.liked[key] = raw.user_liked;
            } else if (typeof plant.userLiked === "boolean") {
                this.liked[key] = plant.userLiked;
            }
        },
        async loadLikeStatus(plantId) {
            if (!apiClient.token) return false;
            const key = String(plantId);

            const states = await this.hydrateLikeStates([plantId]);
            this.liked[key] = Boolean(states[key]);
            return this.liked[key];
        },
        async hydrateLikeStates(plantIds = []) {
            if (!apiClient.token || !plantIds.length) return {};

            const uniqueIds = [
                ...new Set(plantIds.map((id) => String(id)).filter(Boolean)),
            ];
            const params = new URLSearchParams();
            uniqueIds.forEach((id) => params.append("plant_ids[]", id));

            try {
                const payload = await apiClient.get(
                    `/likes/states?${params.toString()}`,
                );
                const states = payload.liked || {};

                uniqueIds.forEach((id) => {
                    this.liked[id] = Boolean(states[id]);
                });

                return uniqueIds.reduce((acc, id) => {
                    acc[id] = Boolean(states[id]);
                    return acc;
                }, {});
            } catch (error) {
                if (error?.status === 403) {
                    uniqueIds.forEach((id) => {
                        this.liked[id] = false;
                    });
                    return uniqueIds.reduce((acc, id) => {
                        acc[id] = false;
                        return acc;
                    }, {});
                }
                throw error;
            }
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
                    this.tipsByPlant[key] = unwrapApiCollection(
                        tipsResult.value,
                    );
                } else if (tipsResult.reason?.status !== 403) {
                    throw tipsResult.reason;
                }

                if (likesResult.status === "fulfilled") {
                    this.likeCounts[key] = Number(
                        likesResult.value.likes_count || 0,
                    );
                } else if (likesResult.reason?.status !== 403) {
                    throw likesResult.reason;
                }

                await this.loadLikeStatus(plantId);
            } catch (error) {
                this.error =
                    error?.message || "Не удалось загрузить социальные данные.";
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
            this.likeCounts[String(plantId)] =
                typeof payload.likes_count === "number"
                    ? payload.likes_count
                    : Math.max(
                          0,
                          (this.likeCounts[String(plantId)] || 0) +
                              (payload.liked ? 1 : -1),
                      );
        },
        async createTip(plantId, content) {
            if (!apiClient.token) {
                throw new Error("Нужно войти, чтобы отправлять советы.");
            }

            const payload = await apiClient.post(`/plants/${plantId}/tips`, {
                content,
            });
            const tip = payload.data || payload;
            const key = String(plantId);
            this.tipsByPlant[key] = [tip, ...(this.tipsByPlant[key] || [])];
            return tip;
        },
        async updateTipStatus(plantId, tipId, status) {
            if (!apiClient.token) {
                throw new Error("Нужно войти, чтобы управлять советами.");
            }

            const payload = await apiClient.put(`/tips/${tipId}/status`, {
                status,
            });
            const updated = payload.data || payload;
            const key = String(plantId);
            this.tipsByPlant[key] = (this.tipsByPlant[key] || []).map((tip) =>
                tip.id === updated.id ? { ...tip, ...updated } : tip,
            );
            return updated;
        },
        async reportPlant(plantId, reason, details = "") {
            if (!apiClient.token) {
                throw new Error("Нужно войти, чтобы отправлять жалобы.");
            }

            return apiClient.post(`/plants/${plantId}/reports`, {
                reason,
                details,
            });
        },
        async reportTip(tipId, reason, details = "") {
            if (!apiClient.token) {
                throw new Error("Нужно войти, чтобы отправлять жалобы.");
            }

            return apiClient.post(`/tips/${tipId}/reports`, {
                reason,
                details,
            });
        },
    },
});
