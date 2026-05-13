export const careTypes = {
    water: { api: "watering", label: "Полив", color: "#3886d9" },
    feed: { api: "fertilizing", label: "Подкормка", color: "#f5c947" },
    prune: { api: "pruning", label: "Обрезка", color: "#16843a" },
    rotate: { api: "rotation", label: "Поворот", color: "#f27036" },
};

export const apiCareTypeToUi = Object.fromEntries(
    Object.entries(careTypes).map(([uiType, config]) => [config.api, uiType]),
);

export const uiCareTypeToApi = Object.fromEntries(
    Object.entries(careTypes).map(([uiType, config]) => [uiType, config.api]),
);
