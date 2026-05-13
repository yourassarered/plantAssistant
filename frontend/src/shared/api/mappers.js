import { apiCareTypeToUi, uiCareTypeToApi } from "@/shared/lib/careTypes";

const apiOrigin = import.meta.env.VITE_API_ORIGIN || "";
const placeholderImage =
    "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 400 400'%3E%3Crect width='400' height='400' fill='%23dfe8dc'/%3E%3Cpath d='M205 304c-43-44-48-104-15-153 18 21 26 48 24 82 26-48 61-72 105-72-7 75-45 124-114 143Z' fill='%2316843a'/%3E%3Cpath d='M179 306c-52-19-84-59-94-121 54 4 92 34 113 90 9-48 31-84 66-108 20 66-8 117-85 139Z' fill='%23b8d94c'/%3E%3C/svg%3E";

const resolveAssetUrl = (url) => {
    if (!url) return placeholderImage;
    if (url.startsWith("http") || url.startsWith("data:")) return url;
    return `${apiOrigin}${url}`;
};

const dateOnly = (value) => {
    if (!value) return null;
    return String(value).slice(0, 10);
};

const stringValue = (value, fallback = "") => {
    if (typeof value === "string") return value;
    if (typeof value === "number") return String(value);
    if (value && typeof value === "object") {
        if (typeof value.name === "string") return value.name;
        if (typeof value.value === "string") return value.value;
    }
    return fallback;
};

export const unwrapApiCollection = (payload) => payload?.data || [];

export const mapApiPlant = (plant) => {
    const owner = plant.user?.data || plant.user || null;
    const roomRaw = plant.room?.data || plant.room || null;
    const careSettings = Array.isArray(plant.care_settings?.data)
        ? plant.care_settings.data
        : Array.isArray(plant.care_settings)
          ? plant.care_settings
          : [];

    const care = careSettings.reduce((acc, setting) => {
        const type = apiCareTypeToUi[setting.type];
        if (!type || !setting.is_enabled) return acc;

        acc[type] = {
            id: setting.id,
            everyDays: setting.interval_days,
            nextAt: setting.next_due_date || dateOnly(setting.last_done_at),
            apiType: setting.type,
        };

        return acc;
    }, {});

    return {
        id: String(plant.id),
        apiId: plant.id,
        name: stringValue(plant.name, "Без названия"),
        room: stringValue(roomRaw?.name ?? roomRaw, "Без комнаты"),
        roomId: roomRaw?.id ?? plant.room_id ?? null,
        image: resolveAssetUrl(plant.latest_image?.url),
        health: plant.care_logs?.length ? Math.max(55, 96 - plant.care_logs.length * 2) : 82,
        humidity: null,
        height: Number(plant.height || 0),
        plantedAt: dateOnly(plant.planted_at),
        note: plant.is_public ? "Публичное растение" : "Личное растение",
        isPublic: Boolean(plant.is_public),
        likesCount: Number(plant.likes_count || 0),
        userLiked: Boolean(
            plant.user_liked ??
                plant.is_liked ??
                plant.liked_by_user ??
                plant.viewer_has_liked ??
                false,
        ),
        userId: plant.user_id,
        ownerId: owner?.id || null,
        ownerName: stringValue(owner?.name, ""),
        ownerRank: owner?.rank ?? null,
        ownerAvatarUrl: owner?.avatar_url ? resolveAssetUrl(owner.avatar_url) : "",
        care,
        raw: plant,
    };
};

export const mapPlantFormToApi = (values, roomId = null) => ({
    name: values.name,
    planted_at: values.plantedAt || new Date().toISOString().slice(0, 10),
    height: Number(values.height) || null,
    room_id: roomId,
    is_public: Boolean(values.isPublic),
});

export const mapCareSettingsFromForm = (values) =>
    [
        ["water", values.waterEveryDays],
        ["feed", values.feedEveryDays],
        ["prune", values.pruneEveryDays],
        ["rotate", values.rotateEveryDays],
    ]
        .filter(([, interval]) => Number(interval) > 0)
        .map(([type, interval]) => ({
            type: uiCareTypeToApi[type],
            interval_days: Number(interval),
            is_enabled: true,
        }));
