<template>
    <section class="page">
        <div class="panel not-found">
            <h1 class="page-title">Страница не найдена</h1>
            <p class="page-subtitle">
                Проверьте адрес или вернитесь в ленту растений.
            </p>
            <RouterLink to="/feed" class="not-found__link"
                >Перейти в ленту</RouterLink
            >
        </div>
    </section>
</template>

<style scoped>
.not-found {
    display: grid;
    justify-items: start;
    gap: 12px;
}

.not-found__link {
    display: inline-flex;
    align-items: center;
    min-height: 38px;
    padding: 0 14px;
    border-radius: var(--radius-sm);
    color: #fff;
    background: var(--color-green);
    font-weight: 800;
}
</style>
