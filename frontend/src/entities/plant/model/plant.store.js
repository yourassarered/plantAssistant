import { defineStore } from "pinia";

import { apiClient } from "@/shared/api/client";
import {
    mapApiPlant,
    mapCareSettingsFromForm,
    mapPlantFormToApi,
    unwrapApiCollection,
} from "@/shared/api/mappers";
import { summarizePlantCare } from "@/shared/lib/date/taskMarkers";

export const usePlantStore = defineStore("plants", {
    state: () => ({
        plants: [],
        activeFilter: "attention",
        feedMode: "public",
        search: "",
        sortBy: "created_at",
        sortOrder: "desc",
        loading: false,
        error: "",
        source: "public",
    }),
    getters: {
        all: (state) => state.plants,
        byId: (state) => (id) => state.plants.find((plant) => plant.id === id),
        filteredPlants(state) {
            if (state.activeFilter === "all") return state.plants;

            return state.plants.filter((plant) => {
                const care = summarizePlantCare(plant);
                if (state.activeFilter === "attention") {
                    return care.primaryState === "overdue" || care.primaryState === "today";
                }
                return care.markers.some((marker) => marker.state === state.activeFilter);
            });
        },
        attentionCount(state) {
            return state.plants.filter((plant) => {
                const care = summarizePlantCare(plant);
                return care.primaryState === "overdue" || care.primaryState === "today";
            }).length;
        },
    },
    actions: {
        setFilter(filter) {
            this.activeFilter = filter;
        },
        setFeedMode(mode) {
            this.feedMode = mode;
        },
        setSearch(search) {
            this.search = search;
        },
        setSort(sortBy, sortOrder = this.sortOrder) {
            this.sortBy = sortBy;
            this.sortOrder = sortOrder;
        },
        buildQuery(extra = {}) {
            const params = new URLSearchParams({
                per_page: "100",
                sort_by: this.sortBy,
                sort_order: this.sortOrder,
                ...extra,
            });

            if (this.search.trim()) {
                params.set("search", this.search.trim());
            }

            return params.toString();
        },
        feedPath() {
            const query = this.buildQuery();

            if (!apiClient.token) {
                return `/feed?${query}`;
            }

            if (this.feedMode === "private") {
                return `/plants?${this.buildQuery({ sort_by: this.sortBy === "likes" ? "created_at" : this.sortBy })}`;
            }

            if (this.feedMode === "personal") return `/feed/personal?${query}`;
            if (this.feedMode === "liked") return `/feed/liked?${query}`;
            if (this.feedMode === "recommendations") return `/feed/recommendations?${query}`;

            return `/feed?${query}`;
        },
        async loadPlants(mode = this.feedMode) {
            this.loading = true;
            this.error = "";
            this.feedMode = mode;

            try {
                const path = this.feedPath();
                const payload = await apiClient.get(path);
                this.source = apiClient.token && this.feedMode === "private" ? "private" : "public";
                this.plants = unwrapApiCollection(payload).map(mapApiPlant);
            } catch (error) {
                this.error = error.message;
                this.plants = [];
            } finally {
                this.loading = false;
            }
        },
        async loadOwnPlantsForCare() {
            if (!apiClient.token) return [];

            const params = new URLSearchParams({
                per_page: "100",
                sort_by: "created_at",
                sort_order: "desc",
            });

            const payload = await apiClient.get(`/plants?${params.toString()}`);
            return unwrapApiCollection(payload).map(mapApiPlant);
        },
        async loadPlant(id) {
            const path = apiClient.token ? `/plants/${id}` : `/plants/public/${id}`;
            const payload = await apiClient.get(path);
            const mapped = mapApiPlant(payload.data || payload);
            const existingIndex = this.plants.findIndex((plant) => plant.id === mapped.id);

            if (existingIndex >= 0) {
                this.plants.splice(existingIndex, 1, mapped);
            } else {
                this.plants.push(mapped);
            }

            return mapped;
        },
        async addPlant(values, imageFile = null) {
            if (!apiClient.token) {
                throw new Error("Нужно войти, чтобы добавлять растения.");
            }

            let roomId = null;
            if (values.room) {
                const roomsPayload = await apiClient.get("/rooms?per_page=100");
                const existingRoom = unwrapApiCollection(roomsPayload).find(
                    (room) => room.name.toLowerCase() === values.room.toLowerCase(),
                );

                if (existingRoom) {
                    roomId = existingRoom.id;
                } else {
                    const roomPayload = await apiClient.post("/rooms", { name: values.room });
                    roomId = roomPayload.data?.id || roomPayload.id;
                }
            }

            const plantPayload = await apiClient.post("/plants", mapPlantFormToApi(values, roomId));
            const plant = plantPayload.data || plantPayload;
            const careSettings = mapCareSettingsFromForm(values);

            await Promise.all(
                careSettings.map((setting) => apiClient.post(`/plants/${plant.id}/care-settings`, setting)),
            );

            if (imageFile) {
                const formData = new FormData();
                formData.append("image", imageFile);
                await apiClient.postForm(`/plants/${plant.id}/images`, formData);
            }

            await this.loadPlants();
            return plant;
        },
        async updatePlant(plant, values, imageFile = null) {
            if (!apiClient.token) {
                throw new Error("Нужно войти, чтобы редактировать растения.");
            }

            let roomId = plant.roomId || null;
            if (values.room && values.room !== plant.room) {
                const roomsPayload = await apiClient.get("/rooms?per_page=100");
                const existingRoom = unwrapApiCollection(roomsPayload).find(
                    (room) => room.name.toLowerCase() === values.room.toLowerCase(),
                );

                if (existingRoom) {
                    roomId = existingRoom.id;
                } else {
                    const roomPayload = await apiClient.post("/rooms", { name: values.room });
                    roomId = roomPayload.data?.id || roomPayload.id;
                }
            }

            const plantPayload = await apiClient.put(
                `/plants/${plant.apiId}`,
                mapPlantFormToApi(values, roomId),
            );
            const careSettings = mapCareSettingsFromForm(values);

            await Promise.all(
                careSettings.map((setting) => {
                    const existing = Object.values(plant.care || {}).find(
                        (schedule) => schedule.apiType === setting.type,
                    );

                    if (existing?.id) {
                        return apiClient.put(`/care-settings/${existing.id}`, {
                            interval_days: setting.interval_days,
                            is_enabled: true,
                        });
                    }

                    return apiClient.post(`/plants/${plant.apiId}/care-settings`, setting);
                }),
            );

            if (imageFile) {
                const formData = new FormData();
                formData.append("image", imageFile);
                await apiClient.postForm(`/plants/${plant.apiId}/images`, formData);
            }

            await this.loadPlants();
            return plantPayload.data || plantPayload;
        },
        async deletePlant(id) {
            await apiClient.delete(`/plants/${id}`);
            this.plants = this.plants.filter((plant) => plant.apiId !== id && plant.id !== String(id));
        },
    },
    persist: {
        key: "plant-assistant-plants",
        pick: ["activeFilter", "feedMode", "search", "sortBy", "sortOrder"],
    },
});
