<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plant Assistant API Docs</title>
    <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css">
    <style>
        body { margin: 0; font-family: Inter, Arial, sans-serif; background: #f8fafc; }
        .top-panel { padding: 16px; background: #0f172a; color: #e2e8f0; }
        .controls { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; margin: 8px 0 12px; }
        .controls input, .controls button { height: 36px; border-radius: 6px; border: 1px solid #334155; padding: 0 10px; }
        .controls input { background: #0b1220; color: #f1f5f9; min-width: 260px; }
        .controls button { background: #2563eb; color: #fff; cursor: pointer; border: none; }
        .charts { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 12px; }
        .chart-card { background: #111827; border: 1px solid #1f2937; border-radius: 8px; padding: 12px; }
        .chart-card h3 { margin: 0 0 8px; font-size: 14px; color: #cbd5e1; }
        #actions { margin-top: 10px; font-size: 12px; color: #cbd5e1; max-height: 140px; overflow: auto; }
        #swagger-ui { background: #fff; }
    </style>
</head>
<body>
    <div class="top-panel">
        <strong>Admin Metrics</strong>
        <div class="controls">
            <input id="token" placeholder="Bearer token (admin)" />
            <input id="minutes" type="number" value="60" min="5" max="720" />
            <button id="loadMetrics" type="button">Load Metrics</button>
        </div>
        <div class="charts">
            <div class="chart-card">
                <h3>Requests Load (RPM / RPS)</h3>
                <canvas id="trafficChart" height="120"></canvas>
            </div>
            <div class="chart-card">
                <h3>Status Distribution</h3>
                <canvas id="statusChart" height="120"></canvas>
            </div>
        </div>
        <div id="actions"></div>
    </div>

    <div id="swagger-ui"></div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
    <script>
        let trafficChart;
        let statusChart;

        function renderTraffic(series) {
            const labels = series.map(p => new Date(p.timestamp).toLocaleTimeString());
            const rpm = series.map(p => p.requests_per_minute);
            const rps = series.map(p => p.requests_per_second);

            if (trafficChart) trafficChart.destroy();
            trafficChart = new Chart(document.getElementById("trafficChart"), {
                type: "line",
                data: {
                    labels,
                    datasets: [
                        { label: "RPM", data: rpm, borderColor: "#60a5fa", backgroundColor: "rgba(96,165,250,0.2)", tension: 0.3 },
                        { label: "RPS", data: rps, borderColor: "#34d399", backgroundColor: "rgba(52,211,153,0.2)", tension: 0.3 }
                    ]
                },
                options: { responsive: true, maintainAspectRatio: false }
            });
        }

        function renderStatuses(statuses) {
            if (statusChart) statusChart.destroy();
            statusChart = new Chart(document.getElementById("statusChart"), {
                type: "doughnut",
                data: {
                    labels: ["2xx", "4xx", "5xx"],
                    datasets: [{
                        data: [statuses["2xx"] || 0, statuses["4xx"] || 0, statuses["5xx"] || 0],
                        backgroundColor: ["#22c55e", "#f59e0b", "#ef4444"]
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false }
            });
        }

        function renderActions(actions) {
            const container = document.getElementById("actions");
            if (!actions?.length) {
                container.innerText = "No recent moderator actions.";
                return;
            }

            container.innerHTML = actions
                .map(item => `${item.created_at} | ${item.actor_name ?? "N/A"} | ${item.action} | ${item.target_type}#${item.target_id ?? "-"}`)
                .join("<br>");
        }

        async function loadMetrics() {
            const token = document.getElementById("token").value.trim();
            const minutes = document.getElementById("minutes").value || 60;

            if (!token) {
                alert("Provide admin bearer token first.");
                return;
            }

            const response = await fetch(`/api/admin/metrics/traffic?minutes=${minutes}`, {
                headers: { Authorization: `Bearer ${token}` }
            });

            if (!response.ok) {
                alert(`Failed to load metrics: HTTP ${response.status}`);
                return;
            }

            const data = await response.json();
            renderTraffic(data.traffic_series || []);
            renderStatuses(data.status_totals || {});
            renderActions(data.recent_moderator_actions || []);
        }

        window.onload = function () {
            window.ui = SwaggerUIBundle({
                url: "{{ asset('openapi.json') }}",
                dom_id: "#swagger-ui",
                deepLinking: true
            });

            document.getElementById("loadMetrics").addEventListener("click", loadMetrics);
        };
    </script>
</body>
</html>
