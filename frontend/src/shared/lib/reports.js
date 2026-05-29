export const reportReasonLabels = {
    inappropriate_image: "Неподходящее изображение",
    spam: "Спам",
    abuse: "Оскорбления",
    misinformation: "Недостоверная информация",
    other: "Другое",
};

export const reportStatusLabels = {
    pending: "На рассмотрении",
    accepted: "Принята",
    rejected: "Отклонена",
};

export const reportTypeLabels = {
    plant: "Растение",
    tip: "Совет",
};

export const reportReasonOptions = [
    {
        value: "inappropriate_image",
        label: reportReasonLabels.inappropriate_image,
    },
    { value: "spam", label: reportReasonLabels.spam },
    { value: "abuse", label: reportReasonLabels.abuse },
    { value: "misinformation", label: reportReasonLabels.misinformation },
    { value: "other", label: reportReasonLabels.other },
];

export const getReportReasonLabel = (reason) =>
    reportReasonLabels[reason] || reason || "Без причины";

export const getReportStatusLabel = (status) =>
    reportStatusLabels[status] || status || "Неизвестно";

export const getReportTypeLabel = (type) =>
    reportTypeLabels[type] || type || "Объект";

export const sumPlantReportSummaries = (summaries = []) =>
    summaries.reduce(
        (acc, summary) => ({
            total: acc.total + Number(summary?.total || 0),
            pending: acc.pending + Number(summary?.pending || 0),
            accepted: acc.accepted + Number(summary?.accepted || 0),
            rejected: acc.rejected + Number(summary?.rejected || 0),
        }),
        { total: 0, pending: 0, accepted: 0, rejected: 0 },
    );

export const plantReportIndicator = (summary = {}) => {
    const accepted = Number(summary.accepted || 0);
    const pending = Number(summary.pending || 0);
    const total = Number(summary.total || 0);

    if (accepted > 0) {
        return {
            visible: true,
            tone: "danger",
            text: `Жалобы: ${accepted}`,
        };
    }

    if (pending > 0) {
        return {
            visible: true,
            tone: "warning",
            text: `На проверке: ${pending}`,
        };
    }

    return {
        visible: total > 0,
        tone: "neutral",
        text: `Жалобы: ${total}`,
    };
};
