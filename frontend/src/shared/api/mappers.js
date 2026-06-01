import { API_ORIGIN } from "@/shared/api/client";
import { apiCareTypeToUi, uiCareTypeToApi } from "@/shared/lib/careTypes";
import { todayIsoDate, toIsoDate } from "@/shared/lib/date/calendarGrid";

const placeholderImage =
    "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 400 400'%3E%3Crect width='400' height='400' fill='%23dfe8dc'/%3E%3Cpath d='M205 304c-43-44-48-104-15-153 18 21 26 48 24 82 26-48 61-72 105-72-7 75-45 124-114 143Z' fill='%2316843a'/%3E%3Cpath d='M179 306c-52-19-84-59-94-121 54 4 92 34 113 90 9-48 31-84 66-108 20 66-8 117-85 139Z' fill='%23b8d94c'/%3E%3C/svg%3E";

const unwrapApiValue = (value) => {
    // Бэкенд местами возвращает вложенные Laravel Resource-объекты с `data`,
    // поэтому разворачиваем их до «плоского» значения в одном месте.
    let current = value;
    const seen = new Set();

    while (
        current &&
        typeof current === "object" &&
        Object.prototype.hasOwnProperty.call(current, "data") &&
        !seen.has(current)
    ) {
        seen.add(current);
        current = current.data;
    }

    return current;
};

const objectField = (value, field) => {
    const object = unwrapApiValue(value);

    if (!object || typeof object !== "object") return undefined;

    return unwrapApiValue(object[field]);
};

const resolveAssetUrl = (url) => {
    const normalizedUrl = stringValue(url, "");

    if (!normalizedUrl) return placeholderImage;
    if (normalizedUrl.startsWith("data:")) return normalizedUrl;

    try {
        const assetUrl = new URL(normalizedUrl, API_ORIGIN);
        const apiOrigin = new URL(API_ORIGIN);

        if (
            assetUrl.pathname.startsWith("/storage/") &&
            ["localhost", "127.0.0.1", "::1"].includes(assetUrl.hostname) &&
            assetUrl.origin !== apiOrigin.origin
        ) {
            return new URL(
                `${assetUrl.pathname}${assetUrl.search}${assetUrl.hash}`,
                apiOrigin,
            ).toString();
        }

        return assetUrl.toString();
    } catch {
        return `${API_ORIGIN}${normalizedUrl}`;
    }
};

const dateOnly = (value) => {
    const normalizedValue = unwrapApiValue(value);

    if (!normalizedValue) return null;

    const stringifiedValue = String(normalizedValue);
    if (/^\d{4}-\d{2}-\d{2}$/.test(stringifiedValue)) return stringifiedValue;

    const parsedDate = new Date(stringifiedValue);
    if (!Number.isNaN(parsedDate.getTime())) return toIsoDate(parsedDate);

    return stringifiedValue.slice(0, 10);
};

const stringValue = (value, fallback = "") => {
    const normalizedValue = unwrapApiValue(value);

    if (typeof normalizedValue === "string") return normalizedValue;
    if (typeof normalizedValue === "number") return String(normalizedValue);
    if (typeof normalizedValue === "boolean") return String(normalizedValue);
    if (normalizedValue && typeof normalizedValue === "object") {
        const scalarKey = ["name", "value", "label", "title", "url"].find(
            (key) =>
                ["string", "number", "boolean"].includes(
                    typeof unwrapApiValue(normalizedValue[key]),
                ),
        );

        if (scalarKey) return stringValue(normalizedValue[scalarKey], fallback);
    }

    return fallback;
};

const numberValue = (value, fallback = 0) => {
    const normalizedValue = unwrapApiValue(value);
    const number = Number(normalizedValue);

    return Number.isFinite(number) ? number : fallback;
};

const formNumberValue = (value) => {
    if (typeof value === "string") {
        const normalized = value.trim().replace(",", ".");
        if (!normalized) return null;

        const number = Number(normalized);
        return Number.isFinite(number) ? number : null;
    }

    const number = Number(value);
    return Number.isFinite(number) ? number : null;
};

export const unwrapApiCollection = (payload) => unwrapApiValue(payload) || [];

export const mapApiPlantImage = (image) => ({
    id: image.id,
    plantId: image.plant_id,
    url: resolveAssetUrl(image.url),
    originalName: stringValue(image.original_name, "Фото растения"),
    size: numberValue(image.size, 0),
    createdAt: dateOnly(image.created_at),
});

export const mapApiPlant = (plant) => {
    const owner =
        unwrapApiValue(plant.owner) || unwrapApiValue(plant.user) || null;
    const roomRaw = unwrapApiValue(plant.room) || null;
    const latestImage = unwrapApiValue(plant.latest_image) || null;
    const careSettingsRaw = unwrapApiValue(plant.care_settings);
    const careLogs = unwrapApiCollection(plant.care_logs);
    const careSettings = Array.isArray(careSettingsRaw)
        ? careSettingsRaw.map(unwrapApiValue)
        : [];
    const reportSummary = unwrapApiValue(plant.report_summary) || {};

    const allCareSettings = careSettings.reduce((acc, setting) => {
        const type = apiCareTypeToUi[setting.type];
        if (!type) return acc;

        acc[type] = {
            id: setting.id,
            everyDays: numberValue(setting.interval_days, 0),
            nextAt: dateOnly(setting.next_due_date || setting.last_done_at),
            apiType: setting.type,
            isEnabled: Boolean(setting.is_enabled),
        };

        return acc;
    }, {});
    const care = Object.fromEntries(
        Object.entries(allCareSettings).filter(
            ([, setting]) => setting.isEnabled,
        ),
    );

    return {
        id: String(plant.id),
        apiId: plant.id,
        name: stringValue(plant.name, "Без названия"),
        room: stringValue(
            objectField(roomRaw, "name") ?? roomRaw,
            "Без комнаты",
        ),
        roomId: objectField(roomRaw, "id") ?? plant.room_id ?? null,
        image: resolveAssetUrl(objectField(latestImage, "url")),
        health: careLogs.length ? Math.max(55, 96 - careLogs.length * 2) : 82,
        humidity: null,
        height: numberValue(plant.height, 0),
        plantedAt: dateOnly(plant.planted_at),
        note: plant.is_public ? "Публичное растение" : "Личное растение",
        isPublic: Boolean(plant.is_public),
        isPublicLocked: Boolean(plant.is_public_locked),
        publicHiddenAt: dateOnly(plant.public_hidden_at),
        publicHiddenReason: stringValue(plant.public_hidden_reason, ""),
        hiddenDueToBlock: Boolean(plant.hidden_due_to_block),
        likesCount: numberValue(plant.likes_count, 0),
        userLiked: Boolean(
            plant.user_liked ??
            plant.is_liked ??
            plant.liked_by_user ??
            plant.viewer_has_liked ??
            false,
        ),
        canManage: Boolean(plant.can_manage),
        canDelete: Boolean(plant.can_delete),
        canCompleteCare: Boolean(plant.can_complete_care),
        userId: plant.user_id,
        ownerId:
            objectField(owner, "id") ?? plant.owner_id ?? plant.user_id ?? null,
        ownerName: stringValue(
            objectField(owner, "name") ?? plant.owner_name,
            "",
        ),
        ownerRank: objectField(owner, "rank") ?? plant.owner_rank ?? null,
        ownerIsBlocked: Boolean(
            objectField(owner, "is_blocked") ?? plant.owner_is_blocked ?? false,
        ),
        ownerAvatarUrl: objectField(owner, "avatar_url")
            ? resolveAssetUrl(objectField(owner, "avatar_url"))
            : "",
        careSettings: allCareSettings,
        careLogs: careLogs.map((log) => ({
            id: log.id,
            plantId: log.plant_id,
            type: apiCareTypeToUi[log.type] || log.type,
            apiType: log.type,
            performedAt: dateOnly(log.performed_at),
            comment: stringValue(log.comment, ""),
        })),
        reportSummary: {
            total: numberValue(reportSummary.total, 0),
            pending: numberValue(reportSummary.pending, 0),
            accepted: numberValue(reportSummary.accepted, 0),
            rejected: numberValue(reportSummary.rejected, 0),
        },
        care,
        raw: plant,
    };
};

export const mapPlantFormToApi = (values, roomId = null) => ({
    name: values.name,
    planted_at: values.plantedAt || todayIsoDate(),
    height: formNumberValue(values.height),
    room_id: roomId,
    is_public: Boolean(values.isPublic),
});

export const mapCareSettingsFromForm = (values) =>
    [
        ["water", values.waterEveryDays, values.waterEnabled],
        ["feed", values.feedEveryDays, values.feedEnabled],
        ["prune", values.pruneEveryDays, values.pruneEnabled],
        ["rotate", values.rotateEveryDays, values.rotateEnabled],
    ]
        .filter(([, interval]) => Number(interval) > 0)
        .map(([type, interval, enabled]) => ({
            type: uiCareTypeToApi[type],
            interval_days: Number(interval),
            is_enabled: enabled === undefined ? true : Boolean(enabled),
        }));
