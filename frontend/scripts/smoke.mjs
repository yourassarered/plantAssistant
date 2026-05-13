const apiOrigin = process.env.SMOKE_API_ORIGIN || "http://127.0.0.1:8000";
const apiBase = process.env.SMOKE_API_BASE || "/api";
const email = process.env.SMOKE_EMAIL || "";
const password = process.env.SMOKE_PASSWORD || "";

const url = (path) => `${apiOrigin}${apiBase}${path}`;

const request = async (path, options = {}) => {
    const response = await fetch(url(path), {
        headers: {
            Accept: "application/json",
            ...(options.headers || {}),
        },
        ...options,
    });

    const text = await response.text();
    let payload = null;
    try {
        payload = text ? JSON.parse(text) : null;
    } catch {
        payload = text;
    }

    return { response, payload };
};

const assertOk = (result, label) => {
    if (!result.response.ok) {
        throw new Error(`${label}: HTTP ${result.response.status}`);
    }
};

const run = async () => {
    const checks = [
        ["/feed?per_page=1", "public feed"],
        ["/feed/trending?per_page=1", "trending feed"],
        ["/plants/public?per_page=1", "public plants"],
    ];

    for (const [path, label] of checks) {
        const result = await request(path);
        assertOk(result, label);
        console.log(`OK ${label}`);
    }

    if (email && password) {
        const login = await request("/auth/login", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ email, password }),
        });
        assertOk(login, "auth login");
        const token = login.payload?.access_token;
        if (!token) {
            throw new Error("auth login: no access token");
        }
        console.log("OK auth login");

        const me = await request("/auth/me", {
            headers: { Authorization: `Bearer ${token}` },
        });
        assertOk(me, "auth me");
        console.log("OK auth me");
    } else {
        console.log("SKIP auth checks (SMOKE_EMAIL/SMOKE_PASSWORD not set)");
    }

    console.log("Smoke checks passed.");
};

run().catch((error) => {
    console.error(`Smoke checks failed: ${error.message}`);
    process.exit(1);
});
