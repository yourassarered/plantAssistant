export const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || "/api";
const TOKEN_KEY = "plant-assistant-token";

const deriveApiOrigin = () => {
    try {
        return new URL(API_BASE_URL, window.location.origin).origin;
    } catch {
        return window.location.origin;
    }
};

export const API_ORIGIN = import.meta.env.VITE_API_ORIGIN || deriveApiOrigin();

const readableErrorMessages = {
    "The provided credentials are incorrect.": "Неверный email или пароль.",
    "This action is unauthorized.": "Недостаточно прав для этого действия.",
    Unauthorized: "Недостаточно прав для этого действия.",
    Forbidden: "Доступ запрещён.",
    "Failed to fetch": "Не удалось связаться с сервером.",
    "Unauthenticated.": "Требуется авторизация.",
    "Вы уже отправляли жалобу на этот объект":
        "Вы уже отправляли жалобу на этот объект.",
    "Account is blocked. Login is unavailable.":
        "Аккаунт заблокирован. Вход недоступен.",
    "Аккаунт заблокирован. Вход и действия в системе недоступны.":
        "Аккаунт заблокирован. Вход и действия в системе недоступны.",
    "Care setting for this type already exists. Please update it instead.":
        "Для этого типа ухода настройка уже существует. Измените существующую запись.",
};

const readableFieldNames = {
    name: "имя",
    email: "email",
    password: "пароль",
    password_confirmation: "подтверждение пароля",
    image: "изображение",
    avatar: "аватар",
    room: "комната",
    room_id: "комната",
    planted_at: "дата посадки",
    height: "высота",
    interval_days: "интервал",
    content: "текст",
    reason: "причина",
    details: "подробности",
    status: "статус",
    role_name: "роль",
    rank: "ранг",
    plant_ids: "растения",
    resolution_action: "решение модератора",
    admin_comment: "комментарий модератора",
};

const normalizeFieldName = (field) =>
    String(field || "")
        .replace(/\.$/, "")
        .replace(/\s+/g, "_")
        .toLowerCase();

const toReadableFieldName = (field) =>
    readableFieldNames[normalizeFieldName(field)] || field;

const readableErrorPatterns = [
    [
        /^The (.+?) field is required\.$/i,
        ([, field]) => `Поле «${toReadableFieldName(field)}» обязательно.`,
    ],
    [
        /^The (.+?) field must be a valid email address\.$/i,
        () => "Укажите корректный email.",
    ],
    [
        /^The (.+?) field confirmation does not match\.$/i,
        ([, field]) =>
            `Подтверждение поля «${toReadableFieldName(field)}» не совпадает.`,
    ],
    [
        /^The (.+?) has already been taken\.$/i,
        ([, field]) =>
            `Значение поля «${toReadableFieldName(field)}» уже занято.`,
    ],
    [
        /^The selected (.+?) is invalid\.$/i,
        ([, field]) =>
            `Значение поля «${toReadableFieldName(field)}» указано неверно.`,
    ],
    [
        /^The (.+?) field must be at least (\d+) characters\.$/i,
        ([, field, count]) =>
            `Поле «${toReadableFieldName(field)}» должно содержать минимум ${count} символов.`,
    ],
    [
        /^The (.+?) field must be at least (\d+)\.$/i,
        ([, field, count]) =>
            `Поле «${toReadableFieldName(field)}» должно быть не меньше ${count}.`,
    ],
    [
        /^The (.+?) field may not be greater than (\d+) characters\.$/i,
        ([, field, count]) =>
            `Поле «${toReadableFieldName(field)}» должно содержать не больше ${count} символов.`,
    ],
    [
        /^The (.+?) field may not be greater than (\d+) kilobytes\.$/i,
        ([, field, count]) =>
            `Размер поля «${toReadableFieldName(field)}» не должен превышать ${count} КБ.`,
    ],
    [
        /^The (.+?) field must be a string\.$/i,
        ([, field]) =>
            `Поле «${toReadableFieldName(field)}» должно быть строкой.`,
    ],
    [
        /^The (.+?) field must be an integer\.$/i,
        ([, field]) =>
            `Поле «${toReadableFieldName(field)}» должно быть целым числом.`,
    ],
    [
        /^The (.+?) field must be true or false\.$/i,
        ([, field]) =>
            `Поле «${toReadableFieldName(field)}» должно быть логическим значением.`,
    ],
    [
        /^The (.+?) field must be a date\.$/i,
        ([, field]) =>
            `Поле «${toReadableFieldName(field)}» должно быть датой.`,
    ],
    [
        /^The (.+?) field must be an image\.$/i,
        ([, field]) =>
            `Поле «${toReadableFieldName(field)}» должно быть изображением.`,
    ],
    [
        /^The (.+?) field must be a file of type: (.+)\.$/i,
        ([, field, types]) =>
            `Поле «${toReadableFieldName(field)}» должно быть файлом формата: ${types}.`,
    ],
    [
        /^API request failed: (\d+)$/i,
        ([, status]) => `Ошибка запроса к API: ${status}.`,
    ],
];

const toReadableErrorMessage = (message) => {
    if (!message) return "Произошла ошибка.";
    if (readableErrorMessages[message]) return readableErrorMessages[message];

    for (const [pattern, formatter] of readableErrorPatterns) {
        const match = message.match(pattern);
        if (match) return formatter(match);
    }

    return message;
};

export const apiClient = {
    get token() {
        return localStorage.getItem(TOKEN_KEY);
    },

    setToken(token) {
        if (token) {
            localStorage.setItem(TOKEN_KEY, token);
        } else {
            localStorage.removeItem(TOKEN_KEY);
        }
    },

    async request(path, options = {}) {
        const isFormData = options.body instanceof FormData;
        const response = await fetch(`${API_BASE_URL}${path}`, {
            ...options,
            headers: {
                Accept: "application/json",
                ...(options.body && !isFormData
                    ? { "Content-Type": "application/json" }
                    : {}),
                ...(this.token
                    ? { Authorization: `Bearer ${this.token}` }
                    : {}),
                ...options.headers,
            },
            body: isFormData
                ? options.body
                : options.body
                  ? JSON.stringify(options.body)
                  : undefined,
        });

        const payload =
            response.status === 204
                ? null
                : await response.json().catch(() => null);

        if (!response.ok) {
            const validationMessage = payload?.errors
                ? Object.values(payload.errors).flat().join(" ")
                : "";
            const message =
                validationMessage ||
                payload?.message ||
                `API request failed: ${response.status}`;
            const error = new Error(toReadableErrorMessage(message));
            error.status = response.status;
            error.payload = payload;
            throw error;
        }

        return payload;
    },

    get(path) {
        return this.request(path);
    },

    post(path, body) {
        return this.request(path, { method: "POST", body });
    },

    postForm(path, formData) {
        return this.request(path, { method: "POST", body: formData });
    },

    put(path, body) {
        return this.request(path, { method: "PUT", body });
    },

    delete(path) {
        return this.request(path, { method: "DELETE" });
    },
};
