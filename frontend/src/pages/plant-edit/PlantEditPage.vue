<script setup>
import { computed, onMounted, ref, watch } from "vue";
import { useField, useForm } from "vee-validate";
import { useRoute, useRouter } from "vue-router";
import { Save } from "lucide-vue-next";
import { toast } from "vue-sonner";

import { useAuthStore } from "@/entities/auth/model/auth.store";
import { usePlantStore } from "@/entities/plant/model/plant.store";
import { useTaskStore } from "@/entities/task/model/task.store";
import UiButton from "@/shared/ui/UiButton.vue";
import UiField from "@/shared/ui/UiField.vue";
import { plantFormSchema } from "@/shared/validation/plant.schema";

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();
const plantStore = usePlantStore();
const taskStore = useTaskStore();
const today = new Date().toISOString().slice(0, 10);
const imageFile = ref(null);

const editingPlant = computed(() => (route.params.id ? plantStore.byId(route.params.id) : null));
const isEditMode = computed(() => Boolean(route.params.id));

const { handleSubmit, errors, isSubmitting, setValues } = useForm({
    validationSchema: plantFormSchema,
    initialValues: {
        name: "",
        room: "Кухня",
        height: 30,
        plantedAt: today,
        isPublic: false,
        waterEveryDays: 4,
        feedEveryDays: 21,
        pruneEveryDays: 30,
        rotateEveryDays: 7,
    },
});

const fields = {
    name: useField("name").value,
    room: useField("room").value,
    height: useField("height").value,
    plantedAt: useField("plantedAt").value,
    isPublic: useField("isPublic").value,
    waterEveryDays: useField("waterEveryDays").value,
    feedEveryDays: useField("feedEveryDays").value,
    pruneEveryDays: useField("pruneEveryDays").value,
    rotateEveryDays: useField("rotateEveryDays").value,
};

const fillForm = () => {
    if (!editingPlant.value) return;

    setValues({
        name: editingPlant.value.name,
        room: editingPlant.value.room,
        height: editingPlant.value.height || 1,
        plantedAt: editingPlant.value.plantedAt || today,
        isPublic: editingPlant.value.isPublic,
        waterEveryDays: editingPlant.value.care?.water?.everyDays || 4,
        feedEveryDays: editingPlant.value.care?.feed?.everyDays || 21,
        pruneEveryDays: editingPlant.value.care?.prune?.everyDays || 30,
        rotateEveryDays: editingPlant.value.care?.rotate?.everyDays || 7,
    });
};

const onFileChange = (event) => {
    const file = event.target.files?.[0] || null;
    if (!file) {
        imageFile.value = null;
        return;
    }

    const allowedTypes = ["image/png", "image/jpeg", "image/webp"];
    if (!allowedTypes.includes(file.type)) {
        toast.error("Фото должно быть в формате PNG, JPG или WEBP.");
        event.target.value = "";
        imageFile.value = null;
        return;
    }

    if (file.size > 5 * 1024 * 1024) {
        toast.error("Максимальный размер фото 5 МБ.");
        event.target.value = "";
        imageFile.value = null;
        return;
    }

    imageFile.value = file;
};

const onSubmit = handleSubmit(async (values) => {
    try {
        if (isEditMode.value && editingPlant.value) {
            await plantStore.updatePlant(editingPlant.value, values, imageFile.value);
            toast.success("Растение обновлено");
        } else {
            await plantStore.addPlant(values, imageFile.value);
            toast.success("Растение добавлено");
        }

        taskStore.syncFromPlants(plantStore.all);
        router.push("/feed");
    } catch (error) {
        toast.error(error.message);
    }
});

onMounted(async () => {
    if (route.params.id && !plantStore.all.length) {
        await plantStore.loadPlants();
    }
    fillForm();
});

watch(editingPlant, fillForm);
</script>

<template>
    <section class="page">
        <header class="page-header">
            <div>
                <h1 class="page-title">{{ isEditMode ? "Редактировать растение" : "Добавить растение" }}</h1>
                <p class="page-subtitle">
                    Растение, фото и расписание ухода синхронизируются с Laravel API.
                </p>
            </div>
        </header>

        <div v-if="!authStore.isAuthenticated" class="panel plant-form-auth">
            Нужно войти в профиле, чтобы управлять растениями и расписанием ухода.
            <RouterLink to="/profile">
                <UiButton>Перейти к входу</UiButton>
            </RouterLink>
        </div>

        <form v-else class="plant-form panel" @submit.prevent="onSubmit">
            <UiField label="Название" :error="errors.name">
                <input v-model="fields.name" placeholder="Фикус" />
            </UiField>
            <UiField label="Комната" :error="errors.room">
                <input v-model="fields.room" />
            </UiField>
            <UiField label="Высота, см" :error="errors.height">
                <input v-model="fields.height" type="number" />
            </UiField>
            <UiField label="Дата посадки" :error="errors.plantedAt">
                <input v-model="fields.plantedAt" type="date" />
            </UiField>
            <UiField label="Фото растения">
                <input type="file" accept="image/png,image/jpeg,image/webp" @change="onFileChange" />
            </UiField>
            <UiField label="Полив каждые, дней" :error="errors.waterEveryDays">
                <input v-model="fields.waterEveryDays" type="number" />
            </UiField>
            <UiField label="Удобрение каждые, дней" :error="errors.feedEveryDays">
                <input v-model="fields.feedEveryDays" type="number" />
            </UiField>
            <UiField label="Подрезка каждые, дней" :error="errors.pruneEveryDays">
                <input v-model="fields.pruneEveryDays" type="number" />
            </UiField>
            <UiField label="Поворот каждые, дней" :error="errors.rotateEveryDays">
                <input v-model="fields.rotateEveryDays" type="number" />
            </UiField>

            <label class="plant-form__check">
                <input v-model="fields.isPublic" type="checkbox" />
                Показывать растение в публичной ленте
            </label>

            <UiButton type="submit" :disabled="isSubmitting">
                <Save :size="17" />
                {{ isSubmitting ? "Сохраняем..." : "Сохранить" }}
            </UiButton>
        </form>
    </section>
</template>

<style scoped>
.plant-form,
.plant-form-auth {
    display: grid;
    gap: 12px;
}

.plant-form {
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.plant-form__check,
.plant-form .ui-button {
    grid-column: 1 / -1;
}

.plant-form__check {
    display: flex;
    gap: 8px;
    align-items: center;
    font-weight: 800;
}

@media (max-width: 680px) {
    .plant-form {
        grid-template-columns: 1fr;
    }
}
</style>
