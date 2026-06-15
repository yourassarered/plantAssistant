import { defineStore } from "pinia";

import { apiClient } from "@/shared/api/client";
import {
    mapApiPlantImage,
    mapApiPlant,
    mapCareSettingsFromForm,
    mapPlantFormToApi,
    unwrapApiCollection,
} from "@/shared/api/mappers";
import { summarizePlantCare } from "@/shared/lib/date/taskMarkers";

const allowedPlantFilters = new Set(["attention", "all"]);

const normalizePlantFilter = (filter) =>
    allowedPlantFilters.has(filter) ? filter : "attention";

const compareText = (left, right) =>
    String(left || "").localeCompare(String(right || ""), "ru", {
        sensitivity: "base",
    });

const careStateWeight = (state) => {
    if (state === "overdue") return 0;
    if (state === "today") return 1;
    if (state === "soon") return 2;
    return 3;
};

const comparePlantsByCarePriority = (left, right) => {
    const leftCare = summarizePlantCare(left);
    const rightCare = summarizePlantCare(right);
    const weightDiff =
        careStateWeight(leftCare.primaryState) -
        careStateWeight(rightCare.primaryState);

    if (weightDiff !== 0) return weightDiff;

    const leftDueAt = leftCare.markers[0]?.dueAt || "9999-12-31";
    const rightDueAt = rightCare.markers[0]?.dueAt || "9999-12-31";

    if (leftDueAt !== rightDueAt) {
        return leftDueAt.localeCompare(rightDueAt);
    }

    return compareText(left.name, right.name);
};

const sortPlantsForMyCollection = (plants, sortBy, sortOrder) => {
    const normalizedSortBy = ["name", "planted_at"].includes(sortBy)
        ? sortBy
        : "created_at";
    const direction = sortOrder === "asc" ? 1 : -1;

    if (normalizedSortBy === "name") {
        return [...plants].sort(
            (left, right) =>
                compareText(left.name, right.name) * direction ||
                compareText(left.room, right.room) * direction,
        );
    }

    if (normalizedSortBy === "planted_at") {
        return [...plants].sort(
            (left, right) =>
                String(left.plantedAt || "").localeCompare(
                    String(right.plantedAt || ""),
                ) * direction || compareText(left.name, right.name),
        );
    }

    return [...plants].sort(comparePlantsByCarePriority);
};

const applyLikedPlants = (plants, likedPlantIds) => {
    if (!Array.isArray(likedPlantIds)) return plants;

    const liked = new Set(likedPlantIds.map((id) => String(id)));

    return plants.map((plant) => ({
        ...plant,
        userLiked: liked.has(String(plant.apiId)),
    }));
};

const formStringValue = (value, fallback = "") => {
    if (typeof value === "string") return value.trim();
    if (typeof value === "number") return String(value);
    if (typeof value === "boolean") return String(value);
    if (value && typeof value === "object") {
        if (Object.prototype.hasOwnProperty.call(value, "data")) {
            return formStringValue(value.data, fallback);
        }

        for (const key of ["name", "value", "label", "title"]) {
            const candidate = formStringValue(value[key], null);

            if (candidate !== null) return candidate;
        }
    }

    return fallback;
};

const findOrCreateRoomId = async (roomName) => {
    if (!roomName) return null;

    const roomsPayload = await apiClient.get("/rooms?per_page=100");
    const existingRoom = unwrapApiCollection(roomsPayload).find(
        (room) => room.name.toLowerCase() === roomName.toLowerCase(),
    );

    if (existingRoom) return existingRoom.id;

    const roomPayload = await apiClient.post("/rooms", {
        name: roomName,
    });

    return roomPayload.data?.id || roomPayload.id;
};

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
            const activeFilter = normalizePlantFilter(state.activeFilter);

            const plants =
                activeFilter === "all"
                    ? state.plants
                    : state.plants.filter((plant) => {
                          const care = summarizePlantCare(plant);
                          return (
                              activeFilter === "attention" &&
                              (care.primaryState === "overdue" ||
                                  care.primaryState === "today")
                          );
                      });

            return sortPlantsForMyCollection(
                plants,
                state.sortBy,
                state.sortOrder,
            );
        },
        attentionCount(state) {
            return state.plants.filter((plant) => {
                const care = summarizePlantCare(plant);
                return (
                    care.primaryState === "overdue" ||
                    care.primaryState === "today"
                );
            }).length;
        },
    },
    actions: {
        setFilter(filter) {
            this.activeFilter = normalizePlantFilter(filter);
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
        feedPath(mode = this.feedMode) {
            const query = this.buildQuery();

            if (!apiClient.token) {
                return `/feed?${query}`;
            }

            if (mode === "private") {
                return `/plants?${this.buildQuery({ sort_by: this.sortBy === "likes" ? "created_at" : this.sortBy })}`;
            }

            if (mode === "personal") return `/feed/personal?${query}`;
            if (mode === "liked") return `/feed/liked?${query}`;
            if (mode === "recommendations")
                return `/feed/recommendations?${query}`;

            return `/feed?${query}`;
        },
        async loadPlants(mode = this.feedMode) {
            this.loading = true;
            this.error = "";
            if (mode !== "private") {
                this.feedMode = mode;
            }

            try {
                const path = this.feedPath(mode);
                const payload = await apiClient.get(path);
                this.source =
                    apiClient.token && mode === "private"
                        ? "private"
                        : "public";
                this.plants = applyLikedPlants(
                    unwrapApiCollection(payload).map(mapApiPlant),
                    payload?.liked_plants,
                );
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
            const path = apiClient.token
                ? `/plants/${id}`
                : `/plants/public/${id}`;
            const payload = await apiClient.get(path);
            const mapped = mapApiPlant(payload.data || payload);
            const existingIndex = this.plants.findIndex(
                (plant) => plant.id === mapped.id,
            );

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

            const roomId = await findOrCreateRoomId(
                formStringValue(values.room),
            );

            const plantPayload = await apiClient.post(
                "/plants",
                mapPlantFormToApi(values, roomId),
            );
            const plant = plantPayload.data || plantPayload;
            const careSettings = mapCareSettingsFromForm(values);

            await Promise.all(
                careSettings.map((setting) =>
                    apiClient.post(
                        `/plants/${plant.id}/care-settings`,
                        setting,
                    ),
                ),
            );

            if (imageFile) {
                const formData = new FormData();
                formData.append("image", imageFile);
                await apiClient.postForm(
                    `/plants/${plant.id}/images`,
                    formData,
                );
            }

            await this.loadPlants();
            return plant;
        },
        async updatePlantDetails(plant, values, { reload = true } = {}) {
            if (!apiClient.token) {
                throw new Error("Нужно войти, чтобы редактировать растения.");
            }

            const roomName = formStringValue(values.room);
            let roomId = plant.roomId || null;
            if (roomName && roomName !== plant.room) {
                roomId = await findOrCreateRoomId(roomName);
            }

            const plantPayload = await apiClient.put(
                `/plants/${plant.apiId}`,
                mapPlantFormToApi(values, roomId),
            );

            if (reload) {
                await this.loadPlant(plant.apiId);
            }

            return plantPayload.data || plantPayload;
        },
        async updatePlantCare(plant, values, { reload = true } = {}) {
            if (!apiClient.token) {
                throw new Error("Нужно войти, чтобы редактировать уход.");
            }

            const careSettings = mapCareSettingsFromForm(values);

            await Promise.all(
                careSettings.map((setting) => {
                    const existing = Object.values(
                        plant.careSettings || plant.care || {},
                    ).find((schedule) => schedule.apiType === setting.type);

                    if (existing?.id) {
                        return apiClient.put(`/care-settings/${existing.id}`, {
                            interval_days: setting.interval_days,
                            is_enabled: setting.is_enabled,
                        });
                    }

                    return apiClient.post(
                        `/plants/${plant.apiId}/care-settings`,
                        setting,
                    );
                }),
            );

            if (reload) {
                await this.loadPlant(plant.apiId);
            }
        },
        async updatePlant(plant, values, imageFile = null) {
            const updated = await this.updatePlantDetails(plant, values, {
                reload: false,
            });

            await this.updatePlantCare(plant, values, { reload: false });

            if (imageFile) {
                await this.addPlantImage(plant.apiId, imageFile);
            }

            await this.loadPlant(plant.apiId);
            return updated;
        },
        async loadPlantImages(plantId) {
            const path = apiClient.token
                ? `/plants/${plantId}/images?per_page=100`
                : `/plants/public/${plantId}/images?per_page=100`;
            const payload = await apiClient.get(path);

            return unwrapApiCollection(payload).map(mapApiPlantImage);
        },
        async addPlantImage(plantId, imageFile) {
            const formData = new FormData();
            formData.append("image", imageFile);
            const payload = await apiClient.postForm(
                `/plants/${plantId}/images`,
                formData,
            );

            await this.loadPlant(plantId);
            return mapApiPlantImage(payload.data || payload);
        },
        async deletePlantImage(imageId, plantId = null) {
            await apiClient.delete(`/plant-images/${imageId}`);

            if (plantId) {
                await this.loadPlant(plantId);
            }
        },
        async deletePlant(id) {
            await apiClient.delete(`/plants/${id}`);
            this.plants = this.plants.filter(
                (plant) => plant.apiId !== id && plant.id !== String(id),
            );
        },
    },
    persist: {
        key: "plant-assistant-plants",
        pick: ["activeFilter", "feedMode", "search", "sortBy", "sortOrder"],
    },
});
