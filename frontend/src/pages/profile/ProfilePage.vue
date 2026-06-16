<script setup>
import { computed, onMounted, ref, watch } from "vue";
import {
    Edit3,
    Eye,
    EyeOff,
    LogIn,
    LogOut,
    UserPlus,
    X,
} from "lucide-vue-next";
import { useRoute, useRouter } from "vue-router";
import { toast } from "vue-sonner";

import { useAuthStore } from "@/entities/auth/model/auth.store";
import { useDashboardStore } from "@/entities/dashboard/model/dashboard.store";
import { usePlantStore } from "@/entities/plant/model/plant.store";
import { useTaskStore } from "@/entities/task/model/task.store";
import { apiClient } from "@/shared/api/client";
import { unwrapApiCollection } from "@/shared/api/mappers";
import {
    getReportReasonLabel,
    getReportStatusLabel,
    getReportTypeLabel,
} from "@/shared/lib/reports";
import CareCompletionChart from "@/shared/charts/CareCompletionChart.vue";
import UiButton from "@/shared/ui/UiButton.vue";
import UiField from "@/shared/ui/UiField.vue";

const authStore = useAuthStore();
const dashboardStore = useDashboardStore();
const plantStore = usePlantStore();
const taskStore = useTaskStore();
const route = useRoute();
const router = useRouter();

const mode = ref("login");
const name = ref("");
const email = ref("");
const password = ref("");
const passwordConfirmation = ref("");
const isPasswordVisible = ref(false);
const isPasswordConfirmationVisible = ref(false);
const profileName = ref("");
const profileEmail = ref("");
const profilePassword = ref("");
const profilePasswordConfirmation = ref("");
const isProfilePasswordVisible = ref(false);
const isProfilePasswordConfirmationVisible = ref(false);
const avatarFile = ref(null);
const avatarOriginalFile = ref(null);
const avatarOriginalPreviewUrl = ref("");
const avatarPreviewUrl = ref("");
const avatarCropX = ref(0);
const avatarCropY = ref(0);
const avatarCropSize = ref(0);
const avatarNaturalWidth = ref(0);
const avatarNaturalHeight = ref(0);
const isAvatarDragging = ref(false);
const isAvatarCropOpen = ref(false);
const avatarCropStage = ref(null);
const avatarCropImage = ref(null);
const avatarCropMode = ref("move");
const avatarDisplayWidth = ref(0);
const avatarDisplayHeight = ref(0);
const avatarDragStart = ref({
    pointerX: 0,
    pointerY: 0,
    cropX: 0,
    cropY: 0,
    cropSize: 0,
});
const isProfileEditing = ref(false);
const myReports = ref([]);
const receivedReports = ref([]);
const isReportsDialogOpen = ref(false);
const reportsDialogMode = ref("my");
const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

const title = computed(() => (mode.value === "login" ? "Вход" : "Регистрация"));
const passwordInputType = computed(() =>
    isPasswordVisible.value ? "text" : "password",
);
const passwordConfirmationInputType = computed(() =>
    isPasswordConfirmationVisible.value ? "text" : "password",
);
const profilePasswordInputType = computed(() =>
    isProfilePasswordVisible.value ? "text" : "password",
);
const profilePasswordConfirmationInputType = computed(() =>
    isProfilePasswordConfirmationVisible.value ? "text" : "password",
);

const refreshPrivateData = async () => {
    await plantStore.loadPlants("private");
    taskStore.syncFromPlants(plantStore.all);
    await dashboardStore.load();
    await Promise.all([loadMyReports(), loadReceivedReports()]);
};

const loadMyReports = async () => {
    const payload = await apiClient.get("/reports/my?per_page=20");
    myReports.value = unwrapApiCollection(payload);
};

const loadReceivedReports = async () => {
    const payload = await apiClient.get("/reports/received?per_page=20");
    receivedReports.value = unwrapApiCollection(payload);
};

const reportTitle = (report) => {
    if (report.target_type === "plant") {
        return report.target?.plant?.name || `Растение #${report.target_id}`;
    }

    return report.target?.plant?.name
        ? `Совет к растению «${report.target.plant.name}»`
        : `Совет #${report.target_id}`;
};

const reportStatus = (report) =>
    report.status_label || getReportStatusLabel(report.status);
const reportType = (report) => getReportTypeLabel(report.target_type);
const reportReason = (report) =>
    report.reason_label || getReportReasonLabel(report.reason);
const reportStatusTone = (report) => report.status || "neutral";
const reportDetailsText = (report) =>
    report.details || "Подробности не указаны.";
const reportResolutionText = (report) =>
    report.resolution_summary || report.admin_comment || "";
const activeReports = computed(() =>
    reportsDialogMode.value === "received"
        ? receivedReports.value
        : myReports.value,
);
const reportsDialogTitle = computed(() =>
    reportsDialogMode.value === "received" ? "Жалобы на меня" : "Мои жалобы",
);
const activeReportsEmptyText = computed(() =>
    reportsDialogMode.value === "received"
        ? "На ваши растения и советы жалоб пока нет."
        : "Вы пока не отправляли жалобы.",
);

const openReportsDialog = async (mode) => {
    reportsDialogMode.value = mode;
    isReportsDialogOpen.value = true;

    if (mode === "received") {
        await loadReceivedReports();
        return;
    }

    await loadMyReports();
};

const consumeAuthRequiredNotice = async (authRequiredFlag) => {
    if (authStore.isAuthenticated || authRequiredFlag !== "1") return;

    toast("Чтобы открыть этот раздел, нужно авторизоваться.");

    const nextQuery = { ...route.query };
    delete nextQuery.authRequired;

    await router.replace({
        name: "profile",
        query: nextQuery,
    });
};

const redirectAfterAuth = async () => {
    const redirect = route.query.redirect;
    if (typeof redirect === "string" && redirect.startsWith("/")) {
        await router.replace(redirect);
    }
};

const submit = async () => {
    const loginEmail = email.value.trim();

    if (!emailPattern.test(loginEmail)) {
        toast.error("Укажите корректный email.");
        return;
    }

    if (!password.value || password.value.length < 8) {
        toast.error("Пароль должен быть не короче 8 символов.");
        return;
    }

    if (mode.value === "register") {
        if (!name.value.trim()) {
            toast.error("Укажите имя.");
            return;
        }
        if (password.value !== passwordConfirmation.value) {
            toast.error("Пароли не совпадают.");
            return;
        }
    }

    try {
        if (mode.value === "login") {
            await authStore.login({
                email: loginEmail,
                password: password.value,
            });
            toast.success("Вход выполнен");
        } else {
            await authStore.register({
                name: name.value.trim(),
                email: loginEmail,
                password: password.value,
                password_confirmation: passwordConfirmation.value,
            });
            toast.success("Аккаунт создан");
        }

        await refreshPrivateData();
        await redirectAfterAuth();
    } catch (error) {
        toast.error(error.message);
    }
};

const logout = async () => {
    await authStore.logout();
    dashboardStore.clear();
    taskStore.syncFromPlants([]);
    await plantStore.loadPlants();
};

const fillProfileForm = () => {
    profileName.value = authStore.user?.name || "";
    profileEmail.value = authStore.user?.email || "";
    profilePassword.value = "";
    profilePasswordConfirmation.value = "";
    isProfilePasswordVisible.value = false;
    isProfilePasswordConfirmationVisible.value = false;
};

const clearAvatarDraft = () => {
    [avatarPreviewUrl.value, avatarOriginalPreviewUrl.value]
        .filter(Boolean)
        .forEach((url, index, urls) => {
            if (urls.indexOf(url) === index) URL.revokeObjectURL(url);
        });

    avatarFile.value = null;
    avatarOriginalFile.value = null;
    avatarOriginalPreviewUrl.value = "";
    avatarPreviewUrl.value = "";
    avatarCropX.value = 0;
    avatarCropY.value = 0;
    avatarCropSize.value = 0;
    avatarNaturalWidth.value = 0;
    avatarNaturalHeight.value = 0;
    avatarDisplayWidth.value = 0;
    avatarDisplayHeight.value = 0;
    isAvatarDragging.value = false;
    isAvatarCropOpen.value = false;
};

const toggleAuthMode = () => {
    mode.value = mode.value === "login" ? "register" : "login";
    isPasswordVisible.value = false;
    isPasswordConfirmationVisible.value = false;
};

const clampAvatarCrop = (x, y) => {
    const maxX = Math.max(0, avatarDisplayWidth.value - avatarCropSize.value);
    const maxY = Math.max(0, avatarDisplayHeight.value - avatarCropSize.value);

    return {
        x: Math.min(maxX, Math.max(0, x)),
        y: Math.min(maxY, Math.max(0, y)),
    };
};

const setAvatarCrop = (x, y) => {
    const next = clampAvatarCrop(x, y);
    avatarCropX.value = next.x;
    avatarCropY.value = next.y;
};

const setAvatarCropSize = (size) => {
    const minSize = 48;
    const maxSize = Math.max(
        minSize,
        Math.min(avatarDisplayWidth.value, avatarDisplayHeight.value),
    );
    avatarCropSize.value = Math.min(maxSize, Math.max(minSize, size));
    setAvatarCrop(avatarCropX.value, avatarCropY.value);
};

const initializeAvatarCrop = () => {
    const stage = avatarCropStage.value;
    if (!stage) return;

    avatarDisplayWidth.value = stage.clientWidth;
    avatarDisplayHeight.value = stage.clientHeight;
    const size = Math.round(
        Math.min(avatarDisplayWidth.value, avatarDisplayHeight.value) * 0.72,
    );

    avatarCropSize.value = size;
    setAvatarCrop(
        (avatarDisplayWidth.value - size) / 2,
        (avatarDisplayHeight.value - size) / 2,
    );
};

const onAvatarImageLoad = (event) => {
    avatarNaturalWidth.value = event.target.naturalWidth;
    avatarNaturalHeight.value = event.target.naturalHeight;
    window.requestAnimationFrame(initializeAvatarCrop);
};

const onAvatarCropPointerDown = (event, mode = "move") => {
    if (!avatarPreviewUrl.value) return;

    isAvatarDragging.value = true;
    avatarCropMode.value = mode;
    avatarDragStart.value = {
        pointerX: event.clientX,
        pointerY: event.clientY,
        cropX: avatarCropX.value,
        cropY: avatarCropY.value,
        cropSize: avatarCropSize.value,
    };
    event.currentTarget.setPointerCapture?.(event.pointerId);
};

const onAvatarCropPointerMove = (event) => {
    if (!isAvatarDragging.value) return;

    const dx = event.clientX - avatarDragStart.value.pointerX;
    const dy = event.clientY - avatarDragStart.value.pointerY;

    if (avatarCropMode.value === "resize") {
        setAvatarCropSize(avatarDragStart.value.cropSize + Math.max(dx, dy));
        return;
    }

    setAvatarCrop(
        avatarDragStart.value.cropX + dx,
        avatarDragStart.value.cropY + dy,
    );
};

const onAvatarCropPointerUp = (event) => {
    isAvatarDragging.value = false;
    event.currentTarget.releasePointerCapture?.(event.pointerId);
};

const startProfileEdit = () => {
    fillProfileForm();
    clearAvatarDraft();
    isProfileEditing.value = true;
};

const cancelProfileEdit = () => {
    fillProfileForm();
    clearAvatarDraft();
    isProfileEditing.value = false;
};

const onAvatarFileChange = (event) => {
    const file = event.target.files?.[0] || null;
    if (!file) {
        clearAvatarDraft();
        return;
    }

    const allowedTypes = ["image/png", "image/jpeg", "image/webp"];
    if (!allowedTypes.includes(file.type)) {
        toast.error("Допустимы только PNG, JPG и WEBP.");
        event.target.value = "";
        clearAvatarDraft();
        return;
    }
    if (file.size > 5 * 1024 * 1024) {
        toast.error("Максимальный размер аватара 5 МБ.");
        event.target.value = "";
        clearAvatarDraft();
        return;
    }

    clearAvatarDraft();
    avatarOriginalFile.value = file;
    avatarOriginalPreviewUrl.value = URL.createObjectURL(file);
    avatarPreviewUrl.value = avatarOriginalPreviewUrl.value;
    isAvatarCropOpen.value = true;
};

const createCroppedAvatarFile = async () => {
    if (!avatarOriginalFile.value || !avatarOriginalPreviewUrl.value)
        return null;

    const displayWidth =
        avatarCropImage.value?.clientWidth || avatarDisplayWidth.value;
    const displayHeight =
        avatarCropImage.value?.clientHeight || avatarDisplayHeight.value;
    if (!displayWidth || !displayHeight || !avatarCropSize.value) return null;

    const image = new Image();
    image.decoding = "async";
    image.src = avatarOriginalPreviewUrl.value;

    await new Promise((resolve, reject) => {
        image.onload = resolve;
        image.onerror = reject;
    });

    const size = 512;
    const canvas = document.createElement("canvas");
    canvas.width = size;
    canvas.height = size;

    const context = canvas.getContext("2d");
    if (!context) return null;
    const scaleX = image.naturalWidth / displayWidth;
    const scaleY = image.naturalHeight / displayHeight;
    const sourceX = avatarCropX.value * scaleX;
    const sourceY = avatarCropY.value * scaleY;
    const sourceWidth = avatarCropSize.value * scaleX;
    const sourceHeight = avatarCropSize.value * scaleY;

    context.drawImage(
        image,
        sourceX,
        sourceY,
        sourceWidth,
        sourceHeight,
        0,
        0,
        size,
        size,
    );

    const blob = await new Promise((resolve) =>
        canvas.toBlob(resolve, "image/jpeg", 0.9),
    );

    if (!blob) return null;

    return new File([blob], "avatar.jpg", { type: "image/jpeg" });
};

const updateProfile = async () => {
    const nextName = profileName.value.trim();
    const nextEmail = profileEmail.value.trim();

    if (!nextName) {
        toast.error("Имя не может быть пустым.");
        return;
    }
    if (!emailPattern.test(nextEmail)) {
        toast.error("Укажите корректный email.");
        return;
    }
    if (profilePassword.value && profilePassword.value.length < 8) {
        toast.error("Новый пароль должен быть не короче 8 символов.");
        return;
    }
    if (
        profilePassword.value &&
        profilePassword.value !== profilePasswordConfirmation.value
    ) {
        toast.error("Новый пароль и подтверждение не совпадают.");
        return;
    }

    try {
        const payload = {
            name: nextName,
            email: nextEmail,
        };

        if (profilePassword.value) {
            payload.password = profilePassword.value;
            payload.password_confirmation = profilePasswordConfirmation.value;
        }

        await authStore.updateProfile(payload);

        if (avatarFile.value) {
            await authStore.updateAvatar(avatarFile.value);
        }

        fillProfileForm();
        clearAvatarDraft();
        isProfileEditing.value = false;
        toast.success("Профиль обновлен");
    } catch (error) {
        toast.error(error.message);
    }
};

const deleteAvatar = async () => {
    if (!authStore.user?.hasAvatar) {
        toast.info("У профиля уже установлен стандартный аватар.");
        return;
    }

    try {
        await authStore.deleteAvatar();
        clearAvatarDraft();
        toast.success("Аватар удален");
    } catch (error) {
        toast.error(error.message);
    }
};

const confirmAvatarCrop = async () => {
    if (!avatarPreviewUrl.value) return;
    const croppedAvatar = await createCroppedAvatarFile();

    if (!croppedAvatar) {
        toast.error("Не удалось применить кадр. Выберите изображение ещё раз.");
        return;
    }

    const previousPreviewUrl = avatarPreviewUrl.value;
    avatarFile.value = croppedAvatar;
    avatarPreviewUrl.value = URL.createObjectURL(croppedAvatar);

    if (
        previousPreviewUrl &&
        previousPreviewUrl !== avatarOriginalPreviewUrl.value
    ) {
        URL.revokeObjectURL(previousPreviewUrl);
    }

    isAvatarCropOpen.value = false;
};

const cancelAvatarCrop = () => {
    clearAvatarDraft();
};

watch(
    () => route.query.authRequired,
    (authRequiredFlag) => {
        consumeAuthRequiredNotice(authRequiredFlag);
    },
    { immediate: true },
);

watch(
    () => authStore.user,
    (user) => {
        fillProfileForm();
        if (user) dashboardStore.load();
    },
    { immediate: true },
);

onMounted(async () => {
    if (!authStore.isAuthenticated) return;

    if (!authStore.user?.email) {
        await authStore.ensureMeLoaded();
    }

    if (authStore.isAuthenticated) {
        await refreshPrivateData();
    }
});
</script>

<template>
    <section class="page">
        <header v-if="authStore.isAuthenticated" class="page-header">
            <div>
                <h1 class="page-title">Профиль</h1>
            </div>
        </header>

        <div :class="{ 'profile-overview-grid': authStore.isAuthenticated }">
            <section
                v-if="!authStore.isAuthenticated"
                class="panel auth-panel auth-panel--guest"
            >
                <div class="auth-panel__head">
                    <h2 class="panel__title">{{ title }}</h2>
                </div>
                <p class="auth-panel__intro">
                    Авторизация откроет доступ к вашим растениям, задачам ухода,
                    лайкам и советам.
                </p>

                <UiField v-if="mode === 'register'" label="Имя">
                    <input v-model="name" autocomplete="name" />
                </UiField>
                <UiField label="Email">
                    <input v-model="email" type="email" autocomplete="email" />
                </UiField>
                <UiField label="Пароль">
                    <div class="password-field">
                        <input
                            v-model="password"
                            :type="passwordInputType"
                            :autocomplete="
                                mode === 'login'
                                    ? 'current-password'
                                    : 'new-password'
                            "
                        />
                        <button
                            type="button"
                            class="password-field__toggle"
                            :aria-label="
                                isPasswordVisible
                                    ? 'Скрыть пароль'
                                    : 'Показать пароль'
                            "
                            @click="isPasswordVisible = !isPasswordVisible"
                        >
                            <component
                                :is="isPasswordVisible ? EyeOff : Eye"
                                :size="18"
                            />
                        </button>
                    </div>
                </UiField>
                <UiField v-if="mode === 'register'" label="Повтор пароля">
                    <div class="password-field">
                        <input
                            v-model="passwordConfirmation"
                            :type="passwordConfirmationInputType"
                            autocomplete="new-password"
                        />
                        <button
                            type="button"
                            class="password-field__toggle"
                            :aria-label="
                                isPasswordConfirmationVisible
                                    ? 'Скрыть пароль'
                                    : 'Показать пароль'
                            "
                            @click="
                                isPasswordConfirmationVisible =
                                    !isPasswordConfirmationVisible
                            "
                        >
                            <component
                                :is="
                                    isPasswordConfirmationVisible ? EyeOff : Eye
                                "
                                :size="18"
                            />
                        </button>
                    </div>
                </UiField>

                <UiButton :disabled="authStore.loading" @click="submit">
                    <component
                        :is="mode === 'login' ? LogIn : UserPlus"
                        :size="17"
                    />
                    {{ authStore.loading ? "Отправляем..." : title }}
                </UiButton>
                <p class="auth-panel__switch-row">
                    <span>
                        {{
                            mode === "login"
                                ? "Нет аккаунта?"
                                : "Уже есть аккаунт?"
                        }}
                    </span>
                    <button
                        class="auth-panel__switch"
                        type="button"
                        @click="toggleAuthMode"
                    >
                        {{ mode === "login" ? "Зарегистрироваться" : "Войти" }}
                    </button>
                </p>
            </section>

            <section v-else class="panel auth-panel profile-card">
                <div class="account-card__head">
                    <h2 class="panel__title">Аккаунт</h2>
                </div>
                <div class="account-card__body">
                    <img
                        v-if="authStore.user?.avatar_url"
                        :src="authStore.user.avatar_url"
                        alt=""
                        class="profile-avatar"
                    />
                    <div class="account-card__identity">
                        <strong>{{
                            authStore.user?.name || "Пользователь"
                        }}</strong>
                        <span>{{
                            authStore.user?.email || "Email загружается..."
                        }}</span>
                        <span
                            >Предупреждения:
                            {{ authStore.user?.warnings_count || 0 }}/3</span
                        >
                    </div>
                </div>
                <div class="account-card__actions">
                    <UiButton variant="ghost" @click="startProfileEdit">
                        <Edit3 :size="17" />
                        Редактировать
                    </UiButton>
                    <UiButton variant="ghost" @click="openReportsDialog('my')">
                        Жалобы
                    </UiButton>
                </div>
                <UiButton variant="ghost" @click="logout">
                    <LogOut :size="17" />
                    Выйти
                </UiButton>
            </section>

            <section
                v-if="authStore.isAuthenticated"
                class="panel profile-card"
            >
                <h2 class="panel__title">Выполнение</h2>
                <CareCompletionChart
                    :completed="taskStore.dueNowCompletedCount"
                    :total="taskStore.dueNowTasks.length"
                />
            </section>
        </div>

        <Teleport to="body">
            <div
                v-if="authStore.isAuthenticated && isProfileEditing"
                class="profile-edit-modal"
                @click="cancelProfileEdit"
            >
                <section class="panel profile-edit" @click.stop>
                    <div class="profile-edit__head">
                        <h2 class="panel__title">Редактировать профиль</h2>
                        <button
                            class="profile-edit__close"
                            type="button"
                            aria-label="Закрыть"
                            @click="cancelProfileEdit"
                        >
                            <X :size="18" />
                        </button>
                    </div>
                    <div class="profile-edit__body">
                        <div class="avatar-editor">
                            <div class="avatar-editor__preview">
                                <img
                                    v-if="
                                        avatarPreviewUrl ||
                                        authStore.user?.avatar_url
                                    "
                                    :src="
                                        avatarPreviewUrl ||
                                        authStore.user.avatar_url
                                    "
                                    alt=""
                                />
                                <span v-else>{{
                                    (profileName || authStore.user?.name || "?")
                                        .slice(0, 1)
                                        .toUpperCase()
                                }}</span>
                            </div>
                            <div class="avatar-editor__controls">
                                <label class="avatar-upload">
                                    Сделать фото
                                    <input
                                        type="file"
                                        accept="image/*"
                                        capture="environment"
                                        @change="onAvatarFileChange"
                                    />
                                </label>
                                <UiButton
                                    v-if="authStore.user?.hasAvatar"
                                    variant="ghost"
                                    @click="deleteAvatar"
                                >
                                    Удалить текущий
                                </UiButton>
                            </div>
                        </div>
                        <UiField label="Имя">
                            <input v-model="profileName" />
                        </UiField>
                        <UiField label="Email">
                            <input v-model="profileEmail" type="email" />
                        </UiField>
                        <UiField label="Новый пароль">
                            <div class="password-field">
                                <input
                                    v-model="profilePassword"
                                    :type="profilePasswordInputType"
                                    autocomplete="new-password"
                                />
                                <button
                                    type="button"
                                    class="password-field__toggle"
                                    :aria-label="
                                        isProfilePasswordVisible
                                            ? 'Скрыть пароль'
                                            : 'Показать пароль'
                                    "
                                    @click="
                                        isProfilePasswordVisible =
                                            !isProfilePasswordVisible
                                    "
                                >
                                    <component
                                        :is="
                                            isProfilePasswordVisible
                                                ? EyeOff
                                                : Eye
                                        "
                                        :size="18"
                                    />
                                </button>
                            </div>
                        </UiField>
                        <UiField label="Повтор пароля">
                            <div class="password-field">
                                <input
                                    v-model="profilePasswordConfirmation"
                                    :type="profilePasswordConfirmationInputType"
                                    autocomplete="new-password"
                                />
                                <button
                                    type="button"
                                    class="password-field__toggle"
                                    :aria-label="
                                        isProfilePasswordConfirmationVisible
                                            ? 'Скрыть пароль'
                                            : 'Показать пароль'
                                    "
                                    @click="
                                        isProfilePasswordConfirmationVisible =
                                            !isProfilePasswordConfirmationVisible
                                    "
                                >
                                    <component
                                        :is="
                                            isProfilePasswordConfirmationVisible
                                                ? EyeOff
                                                : Eye
                                        "
                                        :size="18"
                                    />
                                </button>
                            </div>
                        </UiField>
                    </div>
                    <footer class="profile-edit__actions">
                        <UiButton variant="ghost" @click="cancelProfileEdit">
                            Отмена
                        </UiButton>
                        <UiButton @click="updateProfile">
                            Сохранить профиль
                        </UiButton>
                    </footer>
                </section>
            </div>
        </Teleport>

        <Teleport to="body">
            <div
                v-if="isAvatarCropOpen && avatarPreviewUrl"
                class="avatar-crop-modal"
                @click="cancelAvatarCrop"
            >
                <section class="panel avatar-crop-dialog" @click.stop>
                    <div class="profile-edit__head">
                        <h2 class="panel__title">Кадр аватара</h2>
                        <button
                            class="profile-edit__close"
                            type="button"
                            aria-label="Закрыть"
                            @click="cancelAvatarCrop"
                        >
                            <X :size="18" />
                        </button>
                    </div>
                    <div class="avatar-crop-workspace">
                        <div ref="avatarCropStage" class="avatar-crop-stage">
                            <img
                                ref="avatarCropImage"
                                :src="avatarOriginalPreviewUrl"
                                alt=""
                                class="avatar-crop-image"
                                @load="onAvatarImageLoad"
                            />
                            <div
                                class="avatar-crop-box"
                                :style="{
                                    left: `${avatarCropX}px`,
                                    top: `${avatarCropY}px`,
                                    width: `${avatarCropSize}px`,
                                    height: `${avatarCropSize}px`,
                                }"
                                @pointerdown="
                                    onAvatarCropPointerDown($event, 'move')
                                "
                                @pointermove="onAvatarCropPointerMove"
                                @pointerup="onAvatarCropPointerUp"
                                @pointercancel="onAvatarCropPointerUp"
                            >
                                <span class="avatar-crop-box__circle"></span>
                                <button
                                    type="button"
                                    class="avatar-crop-resize"
                                    aria-label="Изменить размер кадра"
                                    @pointerdown.stop="
                                        onAvatarCropPointerDown(
                                            $event,
                                            'resize',
                                        )
                                    "
                                    @pointermove="onAvatarCropPointerMove"
                                    @pointerup="onAvatarCropPointerUp"
                                    @pointercancel="onAvatarCropPointerUp"
                                ></button>
                            </div>
                        </div>
                    </div>
                    <footer class="profile-edit__actions">
                        <UiButton variant="ghost" @click="cancelAvatarCrop">
                            Отмена
                        </UiButton>
                        <UiButton @click="confirmAvatarCrop">
                            Применить кадр
                        </UiButton>
                    </footer>
                </section>
            </div>
        </Teleport>

        <section v-if="authStore.isAuthenticated" class="profile-stats">
            <article class="panel profile-card">
                <strong>{{
                    dashboardStore.overview?.plants?.total ??
                    plantStore.all.length
                }}</strong>
                <span>растений</span>
            </article>
            <article class="panel profile-card">
                <strong
                    >{{
                        dashboardStore.health?.health_percentage ?? 100
                    }}%</strong
                >
                <span>здоровье коллекции</span>
            </article>
            <article class="panel profile-card">
                <strong>{{
                    dashboardStore.overview?.social?.likes_received ?? 0
                }}</strong>
                <span>лайков получено</span>
            </article>
        </section>

        <section v-if="authStore.isAuthenticated" class="dashboard-grid">
            <article class="panel dashboard-card profile-card">
                <h2 class="panel__title">Социальный профиль</h2>
                <span
                    >Подписчики:
                    {{ dashboardStore.overview?.social?.followers ?? 0 }}</span
                >
                <span
                    >Подписки:
                    {{ dashboardStore.overview?.social?.following ?? 0 }}</span
                >
                <span
                    >Ранг:
                    {{
                        dashboardStore.overview?.achievements?.rank ??
                        authStore.user?.rank ??
                        0
                    }}</span
                >
            </article>

            <article class="panel dashboard-card profile-card">
                <h2 class="panel__title">Активность за 30 дней</h2>
                <span
                    >Действий ухода:
                    {{ dashboardStore.activity?.total_actions ?? 0 }}</span
                >
                <span
                    >Среднее в день:
                    {{
                        dashboardStore.activity?.average_actions_per_day ?? 0
                    }}</span
                >
                <span
                    >За месяц:
                    {{
                        dashboardStore.overview?.activity?.care_actions_month ??
                        0
                    }}</span
                >
            </article>

            <article class="panel dashboard-card profile-card">
                <h2 class="panel__title">Состояние растений</h2>
                <span
                    >В порядке:
                    {{ dashboardStore.health?.well_cared_for ?? 0 }}</span
                >
                <span
                    >Требуют ухода:
                    {{ dashboardStore.health?.needs_attention ?? 0 }}</span
                >
                <span
                    >Срочно:
                    {{
                        dashboardStore.health?.needs_urgent_attention ?? 0
                    }}</span
                >
            </article>
        </section>

        <Teleport to="body">
            <div
                v-if="authStore.isAuthenticated && isReportsDialogOpen"
                class="reports-modal"
                @click.self="isReportsDialogOpen = false"
            >
                <section class="panel reports-card">
                    <div class="reports-card__head">
                        <div class="reports-card__title-row">
                            <h2 class="panel__title">
                                {{ reportsDialogTitle }}
                            </h2>
                            <button
                                class="reports-card__close"
                                type="button"
                                aria-label="Закрыть"
                                @click="isReportsDialogOpen = false"
                            >
                                <X :size="18" />
                            </button>
                        </div>
                        <div class="reports-card__tabs">
                            <button
                                type="button"
                                :class="{ active: reportsDialogMode === 'my' }"
                                @click="openReportsDialog('my')"
                            >
                                Мои жалобы
                            </button>
                            <button
                                type="button"
                                :class="{
                                    active: reportsDialogMode === 'received',
                                }"
                                @click="openReportsDialog('received')"
                            >
                                Жалобы на меня
                            </button>
                        </div>
                    </div>
                    <div class="reports-card__body">
                        <article
                            v-for="report in activeReports"
                            :key="report.id"
                            class="report-status-row"
                            :data-status="reportStatusTone(report)"
                        >
                            <div class="report-status-row__header">
                                <div class="report-status-row__title">
                                    <strong>{{ reportTitle(report) }}</strong>
                                    <span>Жалоба #{{ report.id }}</span>
                                </div>
                                <div class="report-status-row__badges">
                                    <span
                                        class="report-status-row__badge"
                                        :data-tone="reportStatusTone(report)"
                                    >
                                        {{ reportStatus(report) }}
                                    </span>
                                    <span
                                        class="report-status-row__badge report-status-row__badge--soft"
                                    >
                                        {{ reportType(report) }}
                                    </span>
                                    <span
                                        class="report-status-row__badge report-status-row__badge--reason"
                                    >
                                        {{ reportReason(report) }}
                                    </span>
                                </div>
                            </div>

                            <section class="report-status-row__note">
                                <span>Описание</span>
                                <p>{{ reportDetailsText(report) }}</p>
                            </section>

                            <section
                                v-if="reportResolutionText(report)"
                                class="report-status-row__resolution"
                            >
                                <span>Решение</span>
                                <p>{{ reportResolutionText(report) }}</p>
                            </section>
                        </article>
                        <p
                            v-if="!activeReports.length"
                            class="reports-card__empty"
                        >
                            {{ activeReportsEmptyText }}
                        </p>
                    </div>
                    <div class="reports-card__actions">
                        <UiButton
                            variant="ghost"
                            @click="isReportsDialogOpen = false"
                        >
                            Закрыть
                        </UiButton>
                    </div>
                </section>
            </div>
        </Teleport>
    </section>
</template>

<style scoped>
.auth-panel,
.profile-stats {
    display: grid;
    gap: 12px;
}

.profile-overview-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(280px, 1fr);
    gap: 12px;
    align-items: stretch;
}

.profile-card {
    min-width: 0;
    height: 100%;
}

.account-card__head,
.account-card__body {
    display: flex;
    align-items: center;
    gap: 12px;
}

.account-card__head {
    justify-content: space-between;
}

.account-card__actions {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
    min-width: 0;
}

.account-card__actions :deep(.ui-button) {
    width: 100%;
    min-height: 42px;
    justify-content: center;
}

.account-card__identity {
    display: grid;
    min-width: 0;
    gap: 4px;
}

.account-card__identity strong,
.account-card__identity span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.auth-panel__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.auth-panel__switch {
    border: 0;
    padding: 0;
    color: var(--color-green-dark);
    background: transparent;
    cursor: pointer;
    font-weight: 800;
    text-decoration: underline;
    text-underline-offset: 3px;
}

.auth-panel span {
    color: var(--color-muted);
}

.auth-panel__switch-row {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 6px;
    margin: -2px 0 0;
    font-size: 14px;
    font-weight: 800;
}

.auth-panel__intro {
    margin: 0;
    color: var(--color-muted);
    font-size: 14px;
    line-height: 1.45;
    font-weight: 800;
}

.password-field {
    position: relative;
}

.password-field input {
    padding-right: 48px;
}

.password-field__toggle {
    position: absolute;
    top: 50%;
    right: 6px;
    display: grid;
    width: 36px;
    height: 36px;
    place-items: center;
    border: 0;
    border-radius: 10px;
    color: var(--color-muted);
    background: transparent;
    cursor: pointer;
    transform: translateY(-50%);
}

.password-field__toggle:hover {
    color: var(--color-green-dark);
    background: var(--color-surface-soft);
}

.profile-avatar {
    width: 72px;
    height: 72px;
    border-radius: 50%;
    object-fit: cover;
}

.profile-edit-modal {
    position: fixed;
    inset: 0;
    z-index: 1000;
    display: grid;
    place-items: center;
    padding: 18px;
    overflow: hidden;
    background: rgba(7, 30, 15, 0.56);
    backdrop-filter: blur(16px);
}

.avatar-crop-modal {
    position: fixed;
    inset: 0;
    z-index: 1100;
    display: grid;
    place-items: center;
    padding: 18px;
    background: rgba(7, 30, 15, 0.68);
    backdrop-filter: blur(16px);
}

.avatar-crop-dialog {
    display: grid;
    width: min(620px, 100%);
    max-width: calc(100vw - 36px);
    max-height: calc(100vh - 36px);
    max-height: calc(100dvh - 36px);
    grid-template-rows: auto minmax(0, 1fr) auto;
    gap: 0;
    padding: 0;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    overflow: hidden;
    background: var(--color-surface);
    box-shadow: var(--shadow-soft);
}

.avatar-crop-workspace {
    display: grid;
    justify-items: center;
    min-height: 0;
    overflow: auto;
    padding: 16px 16px 4px;
    background: #172119;
}

.avatar-crop-stage {
    position: relative;
    width: fit-content;
    max-width: 100%;
    line-height: 0;
    touch-action: none;
    user-select: none;
}

.avatar-crop-image {
    display: block;
    max-width: min(100%, 540px);
    max-height: min(52vh, 420px);
    object-fit: contain;
}

.avatar-crop-box {
    position: absolute;
    border: 2px solid #fff;
    cursor: move;
    box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.48);
    touch-action: none;
}

.avatar-crop-box__circle {
    position: absolute;
    inset: 0;
    border: 2px solid rgba(255, 255, 255, 0.72);
    border-radius: 50%;
    pointer-events: none;
}

.avatar-crop-resize {
    position: absolute;
    right: -9px;
    bottom: -9px;
    width: 18px;
    height: 18px;
    border: 2px solid #fff;
    border-radius: 50%;
    background: var(--color-green);
    cursor: nwse-resize;
}

.profile-edit {
    display: grid;
    grid-template-rows: auto minmax(0, 1fr) auto;
    gap: 0;
    isolation: isolate;
    width: min(760px, 100%);
    max-width: calc(100vw - 36px);
    max-height: calc(100vh - 36px);
    max-height: calc(100dvh - 36px);
    padding: 0;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    overflow: hidden;
    overscroll-behavior: contain;
    background: var(--color-surface);
    box-shadow: var(--shadow-soft);
}

.profile-edit__body {
    display: grid;
    gap: 12px;
    min-width: 0;
    min-height: 0;
    overflow-y: auto;
    overflow-x: hidden;
    padding: 16px 16px 4px;
    overscroll-behavior: contain;
}

.avatar-editor {
    display: grid;
    grid-template-columns: 142px minmax(0, 1fr);
    gap: 14px;
    align-items: start;
    padding: 12px;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-sm);
    background: var(--color-surface-soft);
}

.avatar-editor__preview {
    display: grid;
    width: 128px;
    aspect-ratio: 1 / 1;
    place-items: center;
    overflow: hidden;
    border-radius: 50%;
    color: var(--color-green-dark);
    background: var(--color-green-soft);
    font-size: 42px;
    font-weight: 900;
    touch-action: none;
    user-select: none;
}

.avatar-editor__preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    pointer-events: none;
    will-change: transform;
}

.avatar-editor__controls,
.avatar-crop-controls {
    display: grid;
    gap: 10px;
    min-width: 0;
}

.avatar-upload {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    min-height: 38px;
    padding: 0 14px;
    border-radius: var(--radius-sm);
    color: var(--color-green-dark);
    background: var(--color-green-soft);
    cursor: pointer;
    font-weight: 800;
}

.avatar-editor__controls :deep(.ui-button) {
    width: 100%;
}

.avatar-upload input {
    display: none;
}

.avatar-crop-controls label {
    display: grid;
    gap: 5px;
    color: var(--color-muted);
    font-size: 12px;
    font-weight: 800;
}

.avatar-crop-controls input {
    width: 100%;
}

.profile-edit__head {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: start;
    gap: 12px;
    min-width: 0;
    padding: 16px 16px 18px;
    border-bottom: 1px solid rgba(23, 33, 24, 0.08);
    background: rgba(255, 255, 255, 0.94);
    backdrop-filter: blur(16px);
    box-shadow: 0 10px 24px rgba(7, 30, 15, 0.05);
}

.profile-edit__head .panel__title {
    margin: 0;
}

.profile-edit__close {
    display: grid;
    width: 40px;
    height: 40px;
    place-items: center;
    border: 0;
    border-radius: var(--radius-sm);
    color: var(--color-muted);
    background: var(--color-surface-soft);
    cursor: pointer;
}

.profile-edit__actions {
    display: grid;
    grid-template-columns: 1fr;
    gap: 10px;
    padding: 14px 16px max(14px, env(safe-area-inset-bottom));
    border-top: 1px solid rgba(23, 33, 24, 0.08);
    background: linear-gradient(
        180deg,
        rgba(255, 255, 255, 0.9),
        var(--color-surface) 34%
    );
}

.profile-edit__actions :deep(.ui-button) {
    width: 100%;
    min-height: 46px;
}

.profile-stats {
    grid-template-columns: repeat(3, minmax(0, 1fr));
    align-items: stretch;
}

.dashboard-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
    align-items: stretch;
}

.dashboard-card {
    display: grid;
    align-content: start;
    gap: 8px;
}

.dashboard-card span {
    color: var(--color-muted);
    font-weight: 800;
}

.reports-card {
    position: relative;
    display: grid;
    gap: 0;
    isolation: isolate;
    width: min(720px, 100%);
    max-width: calc(100vw - 36px);
    max-height: calc(100vh - 36px);
    max-height: calc(100dvh - 36px);
    grid-template-rows: auto minmax(0, 1fr) auto;
    padding: 0;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    background: var(--color-surface);
    overflow: hidden;
    box-shadow: var(--shadow-soft);
}

.reports-card__head {
    display: grid;
    gap: 10px;
    padding: 16px 16px 18px;
    border-bottom: 1px solid rgba(23, 33, 24, 0.08);
    background: rgba(255, 255, 255, 0.94);
    backdrop-filter: blur(16px);
    box-shadow: 0 10px 24px rgba(7, 30, 15, 0.05);
}

.reports-card__tabs {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px;
    width: 100%;
}

.reports-card__title-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: start;
    gap: 12px;
    min-width: 0;
}

.reports-card__title-row .panel__title {
    min-width: 0;
}

.reports-card__tabs button {
    width: 100%;
    min-height: 34px;
    padding: 0 12px;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-sm);
    color: var(--color-muted);
    background: var(--color-surface-soft);
    cursor: pointer;
    font-weight: 800;
}

.reports-card__tabs button.active {
    color: #fff;
    border-color: var(--color-green);
    background: var(--color-green);
}

.reports-modal {
    position: fixed;
    inset: 0;
    z-index: 1000;
    display: grid;
    place-items: center;
    padding: 18px;
    overflow: hidden;
    background: rgba(7, 30, 15, 0.58);
    backdrop-filter: blur(16px);
}

.reports-card__close {
    display: grid;
    width: 40px;
    height: 40px;
    place-items: center;
    border: 0;
    border-radius: var(--radius-sm);
    color: var(--color-muted);
    background: var(--color-surface-soft);
    cursor: pointer;
}

.reports-card__body {
    display: grid;
    gap: 12px;
    min-width: 0;
    min-height: 0;
    overflow-y: auto;
    overflow-x: hidden;
    padding: 16px 16px 4px;
    overscroll-behavior: contain;
}

.reports-card__actions {
    display: grid;
    padding: 14px 16px max(14px, env(safe-area-inset-bottom));
    border-top: 1px solid rgba(23, 33, 24, 0.08);
    background: linear-gradient(
        180deg,
        rgba(255, 255, 255, 0.9),
        var(--color-surface) 34%
    );
}

.reports-card__actions :deep(.ui-button) {
    width: 100%;
    min-height: 46px;
}

.report-status-row {
    position: relative;
    display: grid;
    gap: 12px;
    min-width: 0;
    padding: 14px;
    border: 1px solid var(--color-border);
    border-left: 5px solid var(--color-muted);
    border-radius: var(--radius-sm);
    background: linear-gradient(180deg, #fff, #fbfcf8);
    box-shadow: 0 8px 18px rgba(37, 49, 39, 0.07);
}

.report-status-row[data-status="pending"] {
    border-left-color: var(--color-yellow);
}

.report-status-row[data-status="accepted"] {
    border-left-color: var(--color-green);
}

.report-status-row[data-status="rejected"] {
    border-left-color: var(--color-red);
}

.report-status-row__header {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: start;
    gap: 12px;
    min-width: 0;
}

.report-status-row__title {
    display: grid;
    gap: 4px;
    min-width: 0;
}

.report-status-row__badges {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    flex-wrap: wrap;
    gap: 6px;
    min-width: 0;
}

.report-status-row__badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 26px;
    padding: 0 10px;
    border: 1px solid transparent;
    border-radius: 999px;
    color: var(--color-muted);
    background: #edf1ea;
    font-size: 12px;
    font-weight: 900;
    white-space: nowrap;
}

.report-status-row__badge[data-tone="pending"] {
    color: #7a5200;
    border-color: #f6d35e;
    background: #fff1bd;
}

.report-status-row__badge[data-tone="accepted"] {
    color: var(--color-green-dark);
    border-color: rgba(22, 132, 58, 0.18);
    background: var(--color-green-soft);
}

.report-status-row__badge[data-tone="rejected"] {
    color: #9b2013;
    border-color: rgba(224, 69, 50, 0.18);
    background: #ffe0dc;
}

.report-status-row__badge--soft {
    color: var(--color-green-dark);
    background: #eef8e6;
}

.report-status-row__badge--reason {
    color: #1e5f9c;
    background: #e4f0ff;
}

.report-status-row__note,
.report-status-row__resolution {
    display: grid;
    gap: 6px;
    min-width: 0;
    padding: 10px 12px;
    border-radius: var(--radius-sm);
}

.report-status-row__note {
    border: 1px solid rgba(107, 117, 104, 0.16);
    background: rgba(247, 248, 244, 0.82);
}

.report-status-row__resolution {
    border: 1px solid rgba(22, 132, 58, 0.18);
    background: var(--color-green-soft);
}

.report-status-row strong,
.report-status-row__title span,
.report-status-row__note span,
.report-status-row__resolution span,
.report-status-row__note p,
.report-status-row__resolution p,
.reports-card__empty {
    min-width: 0;
    margin: 0;
    font-weight: 800;
    overflow-wrap: anywhere;
    word-break: break-word;
}

.report-status-row strong {
    color: var(--color-ink);
    font-size: 16px;
}

.report-status-row__title span {
    color: var(--color-muted);
    font-size: 12px;
}

.report-status-row__note span,
.report-status-row__resolution span {
    color: var(--color-green-dark);
    font-size: 11px;
    font-weight: 900;
    letter-spacing: 0;
    text-transform: uppercase;
}

.report-status-row__note p,
.report-status-row__resolution p {
    color: var(--color-muted);
    line-height: 1.45;
}

.report-status-row__resolution p {
    color: var(--color-green-dark);
}

.profile-stats article {
    display: grid;
    align-content: center;
    gap: 4px;
    min-height: 128px;
}

.profile-stats strong {
    font-size: 34px;
}

.profile-stats span {
    color: var(--color-muted);
    font-weight: 800;
}

@media (max-width: 920px) {
    .profile-overview-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 680px) {
    .auth-panel--guest {
        gap: 14px;
        padding: 18px;
    }

    .auth-panel--guest .panel__title {
        font-size: 26px;
        line-height: 1.15;
    }

    .auth-panel--guest :deep(.ui-field) {
        gap: 8px;
        font-size: 15px;
    }

    .auth-panel--guest :deep(.ui-field input) {
        min-height: 50px;
        padding: 12px 14px;
        font-size: 16px;
    }

    .auth-panel--guest :deep(.ui-button) {
        min-height: 50px;
        font-size: 16px;
    }

    .auth-panel__switch-row {
        font-size: 15px;
    }

    .auth-panel__intro {
        font-size: 15px;
    }

    .password-field__toggle {
        width: 40px;
        height: 40px;
    }

    .profile-edit-modal {
        place-items: center;
        padding: 16px;
        overflow: hidden;
    }

    .profile-overview-grid {
        grid-template-columns: 1fr;
    }

    .profile-stats {
        grid-template-columns: 1fr;
    }

    .profile-edit {
        grid-template-columns: 1fr;
        grid-template-rows: auto minmax(0, 1fr);
        width: 100%;
        max-width: calc(100vw - 32px);
        max-height: min(92dvh, calc(100vh - 32px));
        overflow: hidden;
        padding-bottom: 0;
        border-radius: var(--radius-md);
    }

    .profile-edit__body {
        padding-bottom: 4px;
    }

    .avatar-editor {
        grid-template-columns: 1fr;
        justify-items: stretch;
    }

    .avatar-editor__preview {
        width: min(100%, 168px);
        justify-self: center;
    }

    .avatar-upload,
    .avatar-editor__controls :deep(.ui-button) {
        width: 100%;
        min-height: 46px;
    }

    .account-card__body,
    .profile-edit__actions {
        align-items: stretch;
    }

    .account-card__actions {
        grid-template-columns: 1fr;
    }

    .profile-edit__actions {
        margin-top: 2px;
    }

    .dashboard-grid {
        grid-template-columns: 1fr;
    }

    .reports-modal {
        place-items: center;
        padding: 16px;
    }

    .reports-card {
        width: min(720px, 100%);
        max-width: calc(100vw - 32px);
        max-height: min(92dvh, calc(100vh - 18px));
    }

    .reports-card__head,
    .reports-card__body,
    .reports-card__actions {
        padding-left: 14px;
        padding-right: 14px;
    }

    .reports-card__tabs {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .reports-card__tabs button {
        min-height: 42px;
    }

    .report-status-row__header {
        grid-template-columns: 1fr;
    }

    .report-status-row__badges {
        justify-content: flex-start;
    }
}
</style>
