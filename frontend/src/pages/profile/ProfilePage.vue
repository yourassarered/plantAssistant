<script setup>
import { computed, onMounted, ref, watch } from "vue";
import { Edit3, LogIn, LogOut, UserPlus, X } from "lucide-vue-next";
import { useRoute, useRouter } from "vue-router";
import { toast } from "vue-sonner";

import { useAuthStore } from "@/entities/auth/model/auth.store";
import { useDashboardStore } from "@/entities/dashboard/model/dashboard.store";
import { usePlantStore } from "@/entities/plant/model/plant.store";
import { useTaskStore } from "@/entities/task/model/task.store";
import { apiClient } from "@/shared/api/client";
import { unwrapApiCollection } from "@/shared/api/mappers";
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
const profileName = ref("");
const profileEmail = ref("");
const profilePassword = ref("");
const profilePasswordConfirmation = ref("");
const avatarFile = ref(null);
const avatarPreviewUrl = ref("");
const avatarCropX = ref(0);
const avatarCropY = ref(0);
const avatarCropSize = ref(0);
const avatarNaturalWidth = ref(0);
const avatarNaturalHeight = ref(0);
const isAvatarDragging = ref(false);
const isAvatarCropOpen = ref(false);
const avatarCropStage = ref(null);
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
const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

const reportStatusLabels = {
    pending: "На рассмотрении",
    accepted: "Принята",
    rejected: "Отклонена",
};

const reportTypeLabels = {
    plant: "Растение",
    tip: "Совет",
};

const title = computed(() => (mode.value === "login" ? "Вход" : "Регистрация"));

const refreshPrivateData = async () => {
    await plantStore.loadPlants("private");
    taskStore.syncFromPlants(plantStore.all);
    await dashboardStore.load();
    await loadMyReports();
};

const loadMyReports = async () => {
    const payload = await apiClient.get("/reports/my?per_page=20");
    myReports.value = unwrapApiCollection(payload);
};

const reportTitle = (report) => {
    if (report.target_type === "plant") {
        return report.target?.plant?.name || `Растение #${report.target_id}`;
    }

    return report.target?.plant?.name
        ? `Совет к растению «${report.target.plant.name}»`
        : `Совет #${report.target_id}`;
};

const reportStatus = (status) => reportStatusLabels[status] || status;
const reportType = (type) => reportTypeLabels[type] || type;

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
};

const clearAvatarDraft = () => {
    if (avatarPreviewUrl.value) {
        URL.revokeObjectURL(avatarPreviewUrl.value);
    }
    avatarFile.value = null;
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
    avatarFile.value = file;
    avatarPreviewUrl.value = URL.createObjectURL(file);
    isAvatarCropOpen.value = true;
};

const createCroppedAvatarFile = async () => {
    if (!avatarFile.value || !avatarPreviewUrl.value) return null;
    if (!avatarDisplayWidth.value || !avatarDisplayHeight.value) return null;

    const image = new Image();
    image.decoding = "async";
    image.src = avatarPreviewUrl.value;

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
    const scaleX = image.naturalWidth / avatarDisplayWidth.value;
    const scaleY = image.naturalHeight / avatarDisplayHeight.value;
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
            const croppedAvatar = await createCroppedAvatarFile();
            if (croppedAvatar) {
                await authStore.updateAvatar(croppedAvatar);
            }
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
    try {
        await authStore.deleteAvatar();
        clearAvatarDraft();
        toast.success("Аватар удален");
    } catch (error) {
        toast.error(error.message);
    }
};

const confirmAvatarCrop = () => {
    if (!avatarPreviewUrl.value) return;
    isAvatarCropOpen.value = false;
};

const cancelAvatarCrop = () => {
    clearAvatarDraft();
};

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
                <h1 class="page-title">Профиль ухода</h1>
                <p class="page-subtitle">
                    Вход открывает личные растения, календарь ухода, лайки и
                    советы.
                </p>
            </div>
        </header>

        <div :class="{ 'profile-overview-grid': authStore.isAuthenticated }">
            <section v-if="!authStore.isAuthenticated" class="panel auth-panel">
                <div class="auth-panel__head">
                    <h2 class="panel__title">{{ title }}</h2>
                    <button
                        type="button"
                        @click="mode = mode === 'login' ? 'register' : 'login'"
                    >
                        {{
                            mode === "login"
                                ? "Создать аккаунт"
                                : "Уже есть аккаунт"
                        }}
                    </button>
                </div>

                <UiField v-if="mode === 'register'" label="Имя">
                    <input v-model="name" autocomplete="name" />
                </UiField>
                <UiField label="Email">
                    <input v-model="email" type="email" autocomplete="email" />
                </UiField>
                <UiField label="Пароль">
                    <input
                        v-model="password"
                        type="password"
                        autocomplete="current-password"
                    />
                </UiField>
                <UiField v-if="mode === 'register'" label="Повтор пароля">
                    <input
                        v-model="passwordConfirmation"
                        type="password"
                        autocomplete="new-password"
                    />
                </UiField>

                <UiButton :disabled="authStore.loading" @click="submit">
                    <component
                        :is="mode === 'login' ? LogIn : UserPlus"
                        :size="17"
                    />
                    {{ authStore.loading ? "Отправляем..." : title }}
                </UiButton>
            </section>

            <section v-else class="panel auth-panel profile-card">
                <div class="account-card__head">
                    <h2 class="panel__title">Аккаунт</h2>
                    <UiButton variant="ghost" @click="startProfileEdit">
                        <Edit3 :size="17" />
                        Редактировать
                    </UiButton>
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
                    </div>
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
                                Выбрать аватар
                                <input
                                    type="file"
                                    accept="image/png,image/jpeg,image/webp"
                                    @change="onAvatarFileChange"
                                />
                            </label>
                            <UiButton
                                v-if="authStore.user?.avatar_url"
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
                        <input
                            v-model="profilePassword"
                            type="password"
                            autocomplete="new-password"
                        />
                    </UiField>
                    <UiField label="Повтор пароля">
                        <input
                            v-model="profilePasswordConfirmation"
                            type="password"
                            autocomplete="new-password"
                        />
                    </UiField>
                    <div class="profile-edit__actions">
                        <UiButton variant="ghost" @click="cancelProfileEdit">
                            Отмена
                        </UiButton>
                        <UiButton @click="updateProfile"
                            >Сохранить профиль</UiButton
                        >
                    </div>
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
                                :src="avatarPreviewUrl"
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
                    <div class="profile-edit__actions">
                        <UiButton variant="ghost" @click="cancelAvatarCrop">
                            Отмена
                        </UiButton>
                        <UiButton @click="confirmAvatarCrop">
                            Применить кадр
                        </UiButton>
                    </div>
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

        <section v-if="authStore.isAuthenticated" class="panel reports-card">
            <h2 class="panel__title">Мои жалобы</h2>
            <article
                v-for="report in myReports"
                :key="report.id"
                class="report-status-row"
            >
                <div>
                    <strong>{{ reportTitle(report) }}</strong>
                    <span>{{ reportType(report.target_type) }} · {{ reportStatus(report.status) }}</span>
                </div>
                <p>
                    {{ report.resolution_summary || report.admin_comment || "Решение пока не вынесено." }}
                </p>
            </article>
            <p v-if="!myReports.length" class="reports-card__empty">
                Вы пока не отправляли жалобы.
            </p>
        </section>
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

.auth-panel__head button {
    border: 0;
    color: var(--color-green-dark);
    background: transparent;
    cursor: pointer;
    font-weight: 800;
}

.auth-panel span {
    color: var(--color-muted);
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
    overflow-y: auto;
    background: rgba(7, 30, 15, 0.56);
}

.avatar-crop-modal {
    position: fixed;
    inset: 0;
    z-index: 1100;
    display: grid;
    place-items: center;
    padding: 18px;
    background: rgba(7, 30, 15, 0.68);
}

.avatar-crop-dialog {
    display: grid;
    gap: 12px;
    width: min(620px, 100%);
    background: var(--color-surface);
}

.avatar-crop-workspace {
    display: grid;
    justify-items: center;
    overflow: auto;
    padding: 10px;
    border-radius: var(--radius-sm);
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
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
    width: min(620px, 100%);
    max-height: calc(100vh - 36px);
    overflow-y: auto;
    background: var(--color-surface);
}

.profile-edit__head,
.profile-edit__actions,
.avatar-editor,
.profile-edit .ui-button {
    grid-column: 1 / -1;
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
}

.avatar-upload {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 190px;
    min-height: 38px;
    padding: 0 14px;
    border-radius: var(--radius-sm);
    color: var(--color-green-dark);
    background: var(--color-green-soft);
    cursor: pointer;
    font-weight: 800;
}

.avatar-editor__controls :deep(.ui-button) {
    width: 190px;
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

.profile-edit__head,
.profile-edit__actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}

.profile-edit__close {
    display: grid;
    width: 34px;
    height: 34px;
    place-items: center;
    border: 0;
    border-radius: var(--radius-sm);
    color: var(--color-muted);
    background: var(--color-surface-soft);
    cursor: pointer;
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
    display: grid;
    gap: 12px;
}

.report-status-row {
    display: grid;
    gap: 8px;
    padding: 12px 0;
    border-bottom: 1px solid var(--color-border);
}

.report-status-row:last-of-type {
    border-bottom: 0;
}

.report-status-row div {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.report-status-row span,
.report-status-row p,
.reports-card__empty {
    margin: 0;
    color: var(--color-muted);
    font-weight: 800;
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

@media (max-width: 680px) {
    .profile-overview-grid {
        grid-template-columns: 1fr;
    }

    .profile-stats {
        grid-template-columns: 1fr;
    }

    .profile-edit {
        grid-template-columns: 1fr;
    }

    .avatar-editor {
        grid-template-columns: 1fr;
    }

    .account-card__head,
    .account-card__body,
    .profile-edit__actions {
        align-items: stretch;
        flex-direction: column;
    }

    .account-card__head :deep(.ui-button),
    .profile-edit__actions :deep(.ui-button) {
        width: 100%;
    }

    .dashboard-grid {
        grid-template-columns: 1fr;
    }
}
</style>
