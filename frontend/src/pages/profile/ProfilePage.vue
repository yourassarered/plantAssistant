<script setup>
import { computed, ref, watch } from "vue";
import { LogIn, LogOut, UserPlus } from "lucide-vue-next";
import { toast } from "vue-sonner";

import { useAuthStore } from "@/entities/auth/model/auth.store";
import { useDashboardStore } from "@/entities/dashboard/model/dashboard.store";
import { usePlantStore } from "@/entities/plant/model/plant.store";
import { useTaskStore } from "@/entities/task/model/task.store";
import CareCompletionChart from "@/shared/charts/CareCompletionChart.vue";
import UiButton from "@/shared/ui/UiButton.vue";
import UiField from "@/shared/ui/UiField.vue";

const authStore = useAuthStore();
const dashboardStore = useDashboardStore();
const plantStore = usePlantStore();
const taskStore = useTaskStore();

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
const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

const title = computed(() => (mode.value === "login" ? "Вход" : "Регистрация"));

const refreshPrivateData = async () => {
    await plantStore.loadPlants();
    taskStore.syncFromPlants(plantStore.all);
    await dashboardStore.load();
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
            await authStore.login({ email: loginEmail, password: password.value });
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
    if (profilePassword.value && profilePassword.value !== profilePasswordConfirmation.value) {
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
        fillProfileForm();
        toast.success("Профиль обновлен");
    } catch (error) {
        toast.error(error.message);
    }
};

const updateAvatar = async () => {
    if (!avatarFile.value) return;
    const allowedTypes = ["image/png", "image/jpeg", "image/webp"];

    if (!allowedTypes.includes(avatarFile.value.type)) {
        toast.error("Допустимы только PNG, JPG и WEBP.");
        return;
    }
    if (avatarFile.value.size > 5 * 1024 * 1024) {
        toast.error("Максимальный размер аватара 5 МБ.");
        return;
    }

    try {
        await authStore.updateAvatar(avatarFile.value);
        avatarFile.value = null;
        toast.success("Аватар обновлен");
    } catch (error) {
        toast.error(error.message);
    }
};

const deleteAvatar = async () => {
    try {
        await authStore.deleteAvatar();
        toast.success("Аватар удален");
    } catch (error) {
        toast.error(error.message);
    }
};

watch(
    () => authStore.user,
    (user) => {
        fillProfileForm();
        if (user) dashboardStore.load();
    },
    { immediate: true },
);
</script>

<template>
    <section class="page">
        <header class="page-header">
            <div>
                <h1 class="page-title">Профиль ухода</h1>
                <p class="page-subtitle">
                    Вход открывает личные растения, календарь ухода, лайки и советы.
                </p>
            </div>
        </header>

        <div class="desktop-grid">
            <section v-if="!authStore.isAuthenticated" class="panel auth-panel">
                <div class="auth-panel__head">
                    <h2 class="panel__title">{{ title }}</h2>
                    <button type="button" @click="mode = mode === 'login' ? 'register' : 'login'">
                        {{ mode === "login" ? "Создать аккаунт" : "Уже есть аккаунт" }}
                    </button>
                </div>

                <UiField v-if="mode === 'register'" label="Имя">
                    <input v-model="name" autocomplete="name" />
                </UiField>
                <UiField label="Email">
                    <input v-model="email" type="email" autocomplete="email" />
                </UiField>
                <UiField label="Пароль">
                    <input v-model="password" type="password" autocomplete="current-password" />
                </UiField>
                <UiField v-if="mode === 'register'" label="Повтор пароля">
                    <input
                        v-model="passwordConfirmation"
                        type="password"
                        autocomplete="new-password"
                    />
                </UiField>

                <UiButton :disabled="authStore.loading" @click="submit">
                    <component :is="mode === 'login' ? LogIn : UserPlus" :size="17" />
                    {{ authStore.loading ? "Отправляем..." : title }}
                </UiButton>
            </section>

            <section v-else class="panel auth-panel">
                <h2 class="panel__title">Аккаунт</h2>
                <img v-if="authStore.user?.avatar_url" :src="authStore.user.avatar_url" alt="" class="profile-avatar" />
                <strong>{{ authStore.user?.name || "Пользователь" }}</strong>
                <span>{{ authStore.user?.email }}</span>
                <UiButton variant="ghost" @click="logout">
                    <LogOut :size="17" />
                    Выйти
                </UiButton>
            </section>

            <section class="panel">
                <h2 class="panel__title">Выполнение</h2>
                <CareCompletionChart :completed="taskStore.completedCount" :total="taskStore.all.length" />
            </section>
        </div>

        <section v-if="authStore.isAuthenticated" class="panel profile-edit">
            <h2 class="panel__title">Редактировать профиль</h2>
            <UiField label="Имя">
                <input v-model="profileName" />
            </UiField>
            <UiField label="Email">
                <input v-model="profileEmail" type="email" />
            </UiField>
            <UiField label="Новый пароль">
                <input v-model="profilePassword" type="password" autocomplete="new-password" />
            </UiField>
            <UiField label="Повтор пароля">
                <input
                    v-model="profilePasswordConfirmation"
                    type="password"
                    autocomplete="new-password"
                />
            </UiField>
            <UiButton @click="updateProfile">Сохранить профиль</UiButton>

            <UiField label="Аватар">
                <input
                    type="file"
                    accept="image/png,image/jpeg,image/webp"
                    @change="avatarFile = $event.target.files?.[0] || null"
                />
            </UiField>
            <div class="profile-avatar-actions">
                <UiButton variant="ghost" @click="updateAvatar">Загрузить аватар</UiButton>
                <UiButton v-if="authStore.user?.avatar_url" variant="ghost" @click="deleteAvatar">
                    Удалить аватар
                </UiButton>
            </div>
        </section>

        <section class="profile-stats">
            <article class="panel">
                <strong>{{ dashboardStore.overview?.plants?.total ?? plantStore.all.length }}</strong>
                <span>растений</span>
            </article>
            <article class="panel">
                <strong>{{ dashboardStore.health?.health_percentage ?? 100 }}%</strong>
                <span>здоровье коллекции</span>
            </article>
            <article class="panel">
                <strong>{{ dashboardStore.overview?.social?.likes_received ?? 0 }}</strong>
                <span>лайков получено</span>
            </article>
        </section>

        <section v-if="authStore.isAuthenticated" class="dashboard-grid">
            <article class="panel dashboard-card">
                <h2 class="panel__title">Социальный профиль</h2>
                <span>Подписчики: {{ dashboardStore.overview?.social?.followers ?? 0 }}</span>
                <span>Подписки: {{ dashboardStore.overview?.social?.following ?? 0 }}</span>
                <span>Rank: {{ dashboardStore.overview?.achievements?.rank ?? authStore.user?.rank ?? 0 }}</span>
            </article>

            <article class="panel dashboard-card">
                <h2 class="panel__title">Активность за 30 дней</h2>
                <span>Действий ухода: {{ dashboardStore.activity?.total_actions ?? 0 }}</span>
                <span>Среднее в день: {{ dashboardStore.activity?.average_actions_per_day ?? 0 }}</span>
                <span>За месяц: {{ dashboardStore.overview?.activity?.care_actions_month ?? 0 }}</span>
            </article>

            <article class="panel dashboard-card">
                <h2 class="panel__title">Состояние растений</h2>
                <span>В порядке: {{ dashboardStore.health?.well_cared_for ?? 0 }}</span>
                <span>Требуют ухода: {{ dashboardStore.health?.needs_attention ?? 0 }}</span>
                <span>Срочно: {{ dashboardStore.health?.needs_urgent_attention ?? 0 }}</span>
            </article>
        </section>
    </section>
</template>

<style scoped>
.auth-panel,
.profile-stats {
    display: grid;
    gap: 12px;
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

.profile-edit {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
}

.profile-edit .panel__title,
.profile-edit .ui-button {
    grid-column: 1 / -1;
}

.profile-avatar-actions {
    display: flex;
    grid-column: 1 / -1;
    flex-wrap: wrap;
    gap: 10px;
}

.profile-stats {
    grid-template-columns: repeat(3, minmax(0, 1fr));
}

.dashboard-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
}

.dashboard-card {
    display: grid;
    gap: 8px;
}

.dashboard-card span {
    color: var(--color-muted);
    font-weight: 800;
}

.profile-stats article {
    display: grid;
    gap: 4px;
}

.profile-stats strong {
    font-size: 34px;
}

.profile-stats span {
    color: var(--color-muted);
    font-weight: 800;
}

@media (max-width: 680px) {
    .profile-stats {
        grid-template-columns: 1fr;
    }

    .profile-edit {
        grid-template-columns: 1fr;
    }

    .dashboard-grid {
        grid-template-columns: 1fr;
    }
}
</style>
