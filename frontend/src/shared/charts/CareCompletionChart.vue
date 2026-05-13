<script setup>
import { ArcElement, Chart as ChartJS, Legend, Tooltip } from "chart.js";
import { computed } from "vue";
import { Doughnut } from "vue-chartjs";

ChartJS.register(ArcElement, Tooltip, Legend);

const props = defineProps({
    completed: { type: Number, required: true },
    total: { type: Number, required: true },
});

const data = computed(() => ({
    labels: ["Выполнено", "Осталось"],
    datasets: [
        {
            data: [props.completed, Math.max(props.total - props.completed, 0)],
            backgroundColor: ["#16843a", "#dbe2d6"],
            borderWidth: 0,
        },
    ],
}));

const options = {
    responsive: true,
    cutout: "68%",
    plugins: {
        legend: { display: false },
        tooltip: { enabled: true },
    },
};
</script>

<template>
    <div class="care-chart">
        <Doughnut :data="data" :options="options" />
        <strong>{{ completed }}/{{ total }}</strong>
    </div>
</template>

<style scoped>
.care-chart {
    position: relative;
    display: grid;
    width: min(220px, 100%);
    aspect-ratio: 1;
    place-items: center;
    margin: 0 auto;
}

.care-chart strong {
    position: absolute;
    font-size: 22px;
}
</style>
