<script setup>
import PlantCard from "@/entities/plant/ui/PlantCard.vue";

defineProps({
    plants: { type: Array, required: true },
});
</script>

<template>
    <div class="plant-list">
        <PlantCard v-for="plant in plants" :key="plant.id" :plant="plant" />
    </div>
</template>

<style scoped>
.plant-list {
    display: grid;
    gap: 12px;
}

@media (min-width: 1180px) {
    .plant-list {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
</style>
