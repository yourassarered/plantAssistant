<script setup>
import { computed, onMounted, ref, watch } from "vue";
import { useField, useForm } from "vee-validate";
import { useRoute, useRouter } from "vue-router";
import {
  ArrowLeft,
  Camera,
  CalendarClock,
  Droplets,
  ImagePlus,
  Leaf,
  RotateCw,
  Save,
  Scissors,
  Settings2,
  Trash2,
  Upload,
} from "lucide-vue-next";
import { toast } from "vue-sonner";

import { useAuthStore } from "@/entities/auth/model/auth.store";
import { usePlantStore } from "@/entities/plant/model/plant.store";
import { useTaskStore } from "@/entities/task/model/task.store";
import { careTypes } from "@/shared/lib/careTypes";
import { todayIsoDate } from "@/shared/lib/date/calendarGrid";
import UiButton from "@/shared/ui/UiButton.vue";
import UiField from "@/shared/ui/UiField.vue";
import { plantFormSchema } from "@/shared/validation/plant.schema";

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();
const plantStore = usePlantStore();
const taskStore = useTaskStore();
const today = todayIsoDate();
const photoFile = ref(null);
const plantImages = ref([]);
const photosLoading = ref(false);
const photoUploading = ref(false);
const loadedEditingPlant = ref(null);

const editingPlant = computed(() =>
  route.params.id
    ? loadedEditingPlant.value || plantStore.byId(route.params.id)
    : null,
);
const isEditMode = computed(() => Boolean(route.params.id));
const pageMode = computed(() => {
  if (!isEditMode.value) return "create";
  if (route.name === "edit-plant-care") return "care";
  if (route.name === "edit-plant-photos") return "photos";
  return "plant";
});
const showPlantSettings = computed(
  () => pageMode.value === "create" || pageMode.value === "plant",
);
const showCareSettings = computed(
  () => pageMode.value === "create" || pageMode.value === "care",
);
const showPhotoManager = computed(() => pageMode.value === "photos");
const currentPlantId = computed(
  () => editingPlant.value?.apiId || route.params.id,
);
const backTarget = computed(() =>
  route.params.id
    ? `/plants/${editingPlant.value?.id || route.params.id}`
    : "/my-plants",
);
const pageTitle = computed(() => {
  if (pageMode.value === "care") return "Настройки ухода";
  if (pageMode.value === "photos") return "Фотографии растения";
  return isEditMode.value ? "Настройки растения" : "Добавить растение";
});
const submitLabel = computed(() => {
  if (pageMode.value === "care") return "Сохранить уход";
  if (pageMode.value === "plant") return "Сохранить растение";
  return "Создать растение";
});

const { handleSubmit, errors, isSubmitting, resetForm } = useForm({
  validationSchema: plantFormSchema,
  initialValues: {
    name: "",
    room: "Кухня",
    height: 30,
    plantedAt: today,
    isPublic: false,
    waterEnabled: true,
    waterEveryDays: 4,
    feedEnabled: true,
    feedEveryDays: 21,
    pruneEnabled: true,
    pruneEveryDays: 30,
    rotateEnabled: true,
    rotateEveryDays: 7,
  },
});

const name = useField("name").value;
const room = useField("room").value;
const height = useField("height").value;
const plantedAt = useField("plantedAt").value;
const isPublic = useField("isPublic").value;
const waterEnabled = useField("waterEnabled").value;
const waterEveryDays = useField("waterEveryDays").value;
const feedEnabled = useField("feedEnabled").value;
const feedEveryDays = useField("feedEveryDays").value;
const pruneEnabled = useField("pruneEnabled").value;
const pruneEveryDays = useField("pruneEveryDays").value;
const rotateEnabled = useField("rotateEnabled").value;
const rotateEveryDays = useField("rotateEveryDays").value;

const careIcons = {
  water: Droplets,
  feed: Leaf,
  prune: Scissors,
  rotate: RotateCw,
};

const careRows = computed(() => [
  {
    type: "water",
    icon: careIcons.water,
    config: careTypes.water,
    enabled: waterEnabled,
    interval: waterEveryDays,
    error: errors.waterEveryDays,
  },
  {
    type: "feed",
    icon: careIcons.feed,
    config: careTypes.feed,
    enabled: feedEnabled,
    interval: feedEveryDays,
    error: errors.feedEveryDays,
  },
  {
    type: "prune",
    icon: careIcons.prune,
    config: careTypes.prune,
    enabled: pruneEnabled,
    interval: pruneEveryDays,
    error: errors.pruneEveryDays,
  },
  {
    type: "rotate",
    icon: careIcons.rotate,
    config: careTypes.rotate,
    enabled: rotateEnabled,
    interval: rotateEveryDays,
    error: errors.rotateEveryDays,
  },
]);

const unwrapFormValue = (value) => {
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

const normalizeString = (value, fallback = "") => {
  const normalizedValue = unwrapFormValue(value);

  if (typeof normalizedValue === "string") return normalizedValue;
  if (typeof normalizedValue === "number") return String(normalizedValue);
  if (typeof normalizedValue === "boolean") return String(normalizedValue);
  if (normalizedValue && typeof normalizedValue === "object") {
    const scalarKey = ["name", "value", "label", "title"].find((key) =>
      ["string", "number", "boolean"].includes(
        typeof unwrapFormValue(normalizedValue[key]),
      ),
    );

    if (scalarKey) {
      return normalizeString(normalizedValue[scalarKey], fallback);
    }
  }

  return fallback;
};

const normalizeNumber = (value, fallback) => {
  const number = Number(unwrapFormValue(value));

  return Number.isFinite(number) && number > 0 ? number : fallback;
};

const normalizeBoolean = (value, fallback = false) => {
  const normalizedValue = unwrapFormValue(value);

  if (typeof normalizedValue === "boolean") return normalizedValue;
  if (typeof normalizedValue === "number") return normalizedValue === 1;
  if (typeof normalizedValue === "string") {
    return ["1", "true", "yes"].includes(normalizedValue.toLowerCase());
  }

  return fallback;
};

const careSettingValue = (type, fallbackDays) => {
  const settings = editingPlant.value?.careSettings || {};
  const visibleCare = editingPlant.value?.care || {};
  const setting = settings[type] || visibleCare[type] || null;

  return {
    enabled: setting ? normalizeBoolean(setting.isEnabled) : true,
    everyDays: normalizeNumber(setting?.everyDays, fallbackDays),
  };
};

const fillForm = () => {
  if (!editingPlant.value) return;

  const waterCare = careSettingValue("water", 4);
  const feedCare = careSettingValue("feed", 21);
  const pruneCare = careSettingValue("prune", 30);
  const rotateCare = careSettingValue("rotate", 7);

  resetForm({
    values: {
      name: normalizeString(editingPlant.value.name, ""),
      room: normalizeString(editingPlant.value.room, "Без комнаты"),
      height: normalizeNumber(editingPlant.value.height, 1),
      plantedAt: editingPlant.value.plantedAt || today,
      isPublic: normalizeBoolean(editingPlant.value.isPublic),
      waterEnabled: waterCare.enabled,
      waterEveryDays: waterCare.everyDays,
      feedEnabled: feedCare.enabled,
      feedEveryDays: feedCare.everyDays,
      pruneEnabled: pruneCare.enabled,
      pruneEveryDays: pruneCare.everyDays,
      rotateEnabled: rotateCare.enabled,
      rotateEveryDays: rotateCare.everyDays,
    },
  });
};

const onPhotoFileChange = (event) => {
  const file = event.target.files?.[0] || null;
  if (!file) {
    photoFile.value = null;
    return;
  }

  const allowedTypes = ["image/png", "image/jpeg", "image/webp"];
  if (!allowedTypes.includes(file.type)) {
    toast.error("Фото должно быть в формате PNG, JPG или WEBP.");
    event.target.value = "";
    photoFile.value = null;
    return;
  }

  if (file.size > 5 * 1024 * 1024) {
    toast.error("Максимальный размер фото 5 МБ.");
    event.target.value = "";
    photoFile.value = null;
    return;
  }

  photoFile.value = file;
};

const loadImages = async () => {
  if (!currentPlantId.value || !showPhotoManager.value) return;

  photosLoading.value = true;
  try {
    plantImages.value = await plantStore.loadPlantImages(currentPlantId.value);
  } catch (error) {
    toast.error(error.message);
  } finally {
    photosLoading.value = false;
  }
};

const uploadPhoto = async () => {
  if (!photoFile.value || !currentPlantId.value) return;

  photoUploading.value = true;
  try {
    await plantStore.addPlantImage(currentPlantId.value, photoFile.value);
    photoFile.value = null;
    await loadImages();
    toast.success("Фото загружено");
  } catch (error) {
    toast.error(error.message);
  } finally {
    photoUploading.value = false;
  }
};

const deletePhoto = async (image) => {
  if (!window.confirm("Удалить это фото?")) return;

  try {
    await plantStore.deletePlantImage(image.id, currentPlantId.value);
    await loadImages();
    toast.success("Фото удалено");
  } catch (error) {
    toast.error(error.message);
  }
};

const onSubmit = handleSubmit(async (values) => {
  try {
    if (isEditMode.value && editingPlant.value) {
      if (!editingPlant.value.canManage) {
        toast.error("Нет доступа к редактированию этого растения.");
        router.replace(`/plants/${editingPlant.value.id}`);
        return;
      }

      if (pageMode.value === "care") {
        await plantStore.updatePlantCare(editingPlant.value, values);
        toast.success("Настройки ухода сохранены");
      } else {
        await plantStore.updatePlantDetails(editingPlant.value, values);
        toast.success("Настройки растения сохранены");
      }
    } else {
      await plantStore.addPlant(values);
      toast.success("Растение добавлено");
    }

    taskStore.syncFromPlants(plantStore.all);
    router.push(
      isEditMode.value && editingPlant.value
        ? `/plants/${editingPlant.value.id}`
        : "/feed",
    );
  } catch (error) {
    toast.error(error.message);
  }
});

const loadPage = async () => {
  if (route.params.id) {
    const loadedPlant = await plantStore.loadPlant(route.params.id);
    if (!loadedPlant.canManage) {
      toast.error("Нет доступа к редактированию этого растения.");
      router.replace(`/plants/${loadedPlant.id}`);
      return;
    }
    loadedEditingPlant.value = loadedPlant;
    fillForm();
    await loadImages();
    return;
  }
};

onMounted(loadPage);

watch([() => route.params.id, () => route.name], loadPage);
</script>

<template>
  <section class="page">
    <header class="page-header">
      <div>
        <h1 class="page-title">
          {{ pageTitle }}
        </h1>
        <p class="page-subtitle">
          {{ editingPlant?.name || "РќРѕРІРѕРµ СЂР°СЃС‚РµРЅРёРµ" }}
        </p>
      </div>
      <RouterLink class="edit-back-link" :to="backTarget">
        <UiButton variant="ghost">
          <ArrowLeft :size="16" />
          Назад
        </UiButton>
      </RouterLink>
    </header>

    <nav v-if="isEditMode && editingPlant" class="edit-actions">
      <RouterLink :to="`/plants/${editingPlant.id}/edit`">
        <UiButton :variant="pageMode === 'plant' ? 'primary' : 'ghost'">
          <Settings2 :size="16" /> Растение
        </UiButton>
      </RouterLink>
      <RouterLink :to="`/plants/${editingPlant.id}/care`">
        <UiButton :variant="pageMode === 'care' ? 'primary' : 'ghost'">
          <CalendarClock :size="16" /> Уход
        </UiButton>
      </RouterLink>
      <RouterLink :to="`/plants/${editingPlant.id}/photos`">
        <UiButton :variant="pageMode === 'photos' ? 'primary' : 'ghost'">
          <Camera :size="16" /> Фото
        </UiButton>
      </RouterLink>
    </nav>

    <div v-if="!authStore.isAuthenticated" class="panel plant-form-auth">
      Нужно войти в профиле, чтобы управлять растениями и расписанием ухода.
      <RouterLink to="/profile">
        <UiButton>Перейти к входу</UiButton>
      </RouterLink>
    </div>

    <form
      v-else-if="!showPhotoManager"
      class="plant-form"
      @submit.prevent="onSubmit"
    >
      <section v-if="showPlantSettings" class="panel form-section">
        <h2 class="panel__title">Параметры растения</h2>
        <div class="plant-fields">
          <UiField label="Название" :error="errors.name">
            <input v-model="name" placeholder="Фикус" />
          </UiField>
          <UiField label="Комната" :error="errors.room">
            <input v-model="room" />
          </UiField>
          <UiField label="Высота, см" :error="errors.height">
            <input
              v-model="height"
              inputmode="decimal"
              placeholder="12,5"
              type="text"
            />
          </UiField>
          <UiField label="Дата посадки" :error="errors.plantedAt">
            <input v-model="plantedAt" type="date" />
          </UiField>
          <label class="plant-public-toggle">
            <span class="plant-public-toggle__text">
              <strong>Публичность</strong>
              <small>
                {{
                  isPublic
                    ? "Растение видно в публичной ленте"
                    : "Растение видно только вам"
                }}
              </small>
            </span>
            <span class="plant-public-toggle__control">
              <input v-model="isPublic" type="checkbox" />
              <span></span>
            </span>
          </label>
        </div>
      </section>

      <section v-if="showCareSettings" class="panel care-editor">
        <h2 class="panel__title">График ухода</h2>
        <div class="care-grid">
          <article
            v-for="row in careRows"
            :key="row.type"
            class="care-card"
            :class="{ 'care-card--off': !row.enabled.value }"
          >
            <div class="care-card__head">
              <span
                class="care-card__icon"
                :style="{ backgroundColor: row.config.color }"
              >
                <component :is="row.icon" :size="18" />
              </span>
              <div>
                <h3>{{ row.config.label }}</h3>
                <p>
                  {{ row.enabled.value ? "Активно" : "Отключено" }}
                </p>
              </div>
              <label class="care-switch">
                <input v-model="row.enabled.value" type="checkbox" />
                <span></span>
              </label>
            </div>
            <UiField label="Интервал, дней" :error="row.error">
              <input
                v-model="row.interval.value"
                :disabled="!row.enabled.value"
                min="1"
                type="number"
              />
            </UiField>
          </article>
        </div>
      </section>

      <UiButton type="submit" :disabled="isSubmitting">
        <Save :size="17" />
        {{ isSubmitting ? "Сохраняем..." : submitLabel }}
      </UiButton>
    </form>

    <section v-else class="panel photo-manager">
      <div class="photo-upload">
        <label class="photo-drop">
          <ImagePlus :size="26" />
          <span>{{ photoFile?.name || "Выбрать фото" }}</span>
          <input
            type="file"
            accept="image/png,image/jpeg,image/webp"
            @change="onPhotoFileChange"
          />
        </label>
        <UiButton :disabled="!photoFile || photoUploading" @click="uploadPhoto">
          <Upload :size="16" />
          {{ photoUploading ? "Загружаем..." : "Загрузить" }}
        </UiButton>
      </div>

      <div v-if="photosLoading" class="photo-state">
        Загружаем фотографии...
      </div>
      <div v-else-if="plantImages.length" class="photo-grid">
        <article
          v-for="image in plantImages"
          :key="image.id"
          class="photo-card"
        >
          <img :src="image.url" :alt="image.originalName" />
          <div class="photo-card__bar">
            <span>{{ image.originalName }}</span>
            <button type="button" @click="deletePhoto(image)">
              <Trash2 :size="16" />
            </button>
          </div>
        </article>
      </div>
      <div v-else class="photo-state">Фотографий пока нет.</div>
    </section>
  </section>
</template>

<style scoped>
.plant-form,
.plant-form-auth {
  display: grid;
  gap: 12px;
}

.page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}

.edit-back-link {
  flex-shrink: 0;
  text-decoration: none;
}

.edit-actions {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 8px;
}

.edit-actions :deep(.ui-button) {
  width: 100%;
}

.form-section,
.care-editor,
.photo-manager {
  display: grid;
  gap: 12px;
}

.plant-fields {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}

.plant-form > .ui-button {
  justify-self: start;
}

.plant-public-toggle {
  display: flex;
  grid-column: 1 / -1;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 12px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  background: var(--color-surface-soft);
  cursor: pointer;
}

.plant-public-toggle__text {
  display: grid;
  gap: 3px;
}

.plant-public-toggle__text small {
  color: var(--color-muted);
  font-weight: 800;
}

.plant-public-toggle__control {
  position: relative;
  flex: 0 0 auto;
  width: 48px;
  height: 28px;
}

.plant-public-toggle__control input {
  position: absolute;
  opacity: 0;
}

.plant-public-toggle__control span {
  position: absolute;
  inset: 0;
  border-radius: 999px;
  background: #cfd7ca;
  transition: background 0.16s ease;
}

.plant-public-toggle__control span::after {
  position: absolute;
  top: 4px;
  left: 4px;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  background: #fff;
  box-shadow: 0 2px 7px rgba(23, 33, 24, 0.18);
  content: "";
  transition: transform 0.16s ease;
}

.plant-public-toggle__control input:checked + span {
  background: var(--color-green);
}

.plant-public-toggle__control input:checked + span::after {
  transform: translateX(20px);
}

.care-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}

.care-card {
  display: grid;
  gap: 12px;
  min-width: 0;
  padding: 12px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  background: var(--color-surface-soft);
}

.care-card--off {
  background: #f4f5f2;
}

.care-card__head {
  display: grid;
  grid-template-columns: 42px minmax(0, 1fr) auto;
  align-items: center;
  gap: 10px;
}

.care-card__icon {
  display: grid;
  width: 42px;
  height: 42px;
  place-items: center;
  border-radius: var(--radius-sm);
  color: #fff;
}

.care-card h3,
.care-card p {
  margin: 0;
}

.care-card h3 {
  font-size: 15px;
}

.care-card p,
.photo-state,
.photo-card__bar span {
  color: var(--color-muted);
  font-size: 13px;
  font-weight: 800;
}

.care-switch {
  position: relative;
  width: 48px;
  height: 28px;
}

.care-switch input {
  position: absolute;
  opacity: 0;
}

.care-switch span {
  position: absolute;
  inset: 0;
  border-radius: 999px;
  background: #cfd7ca;
  cursor: pointer;
  transition:
    background 0.16s ease,
    box-shadow 0.16s ease;
}

.care-switch span::after {
  position: absolute;
  top: 4px;
  left: 4px;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  background: #fff;
  box-shadow: 0 2px 7px rgba(23, 33, 24, 0.18);
  content: "";
  transition: transform 0.16s ease;
}

.care-switch input:checked + span {
  background: var(--color-green);
}

.care-switch input:checked + span::after {
  transform: translateX(20px);
}

.care-card input:disabled {
  color: var(--color-muted);
  background: #eef0eb;
}

.photo-upload {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  gap: 12px;
  align-items: stretch;
}

.photo-drop {
  display: flex;
  align-items: center;
  gap: 10px;
  min-height: 56px;
  padding: 0 14px;
  border: 1px dashed var(--color-green);
  border-radius: var(--radius-sm);
  color: var(--color-green-dark);
  background: var(--color-green-soft);
  cursor: pointer;
  font-weight: 900;
}

.photo-drop input {
  display: none;
}

.photo-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  gap: 12px;
}

.photo-card {
  overflow: hidden;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  background: var(--color-surface);
}

.photo-card img {
  width: 100%;
  aspect-ratio: 4 / 3;
  object-fit: cover;
}

.photo-card__bar {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 36px;
  align-items: center;
  gap: 8px;
  padding: 8px;
}

.photo-card__bar span {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.photo-card__bar button {
  display: grid;
  width: 36px;
  height: 36px;
  place-items: center;
  border: 0;
  border-radius: var(--radius-sm);
  color: #fff;
  background: var(--color-red);
  cursor: pointer;
}

@media (max-width: 680px) {
  .page-header {
    display: grid;
  }

  .edit-back-link :deep(.ui-button) {
    width: 100%;
  }

  .edit-actions,
  .plant-fields,
  .care-grid,
  .photo-upload {
    grid-template-columns: 1fr;
  }

  .plant-form > .ui-button {
    width: 100%;
  }
}
</style>
