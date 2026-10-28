import { test, expect } from "@playwright/test";

test("desktop dashboard follows the five metrics, three charts, and four lists layout", async ({
  page,
}) => {
  const monthly = Array.from({ length: 12 }, (_, i) => ({
    month: i + 1,
    revenue: 200000 + (i % 4) * 250000,
    expense: 100000 + (i % 5) * 130000,
  }));
  const data = {
    revenue: { current: 1243000, percentage: 7 },
    expense: { current: 321000, percentage: 5 },
    monthly,
    in_progress_projects: { count: 3, track_count: 4 },
    awaiting_final_payment: { amount: 649000, project_count: 1 },
    quotations: { open_count: 2, awaiting_studio_count: 1 },
    package_sales: {
      items: [
        { name: "Paket A", project_count: 57 },
        { name: "Paket B", project_count: 32 },
        { name: "Paket C", project_count: 11 },
      ],
    },
    expense_categories: {
      items: [
        { category_name: "Maintenance", amount: 57 },
        { category_name: "Pembelian Alat", amount: 32 },
        { category_name: "Operasional", amount: 11 },
      ],
    },
    project_progress: [
      {
        id: 1,
        name: "Nama Project D",
        client_name: "John Doe",
        status: "in_progress",
        current_stage_label: "Mastering",
      },
      {
        id: 2,
        name: "Nama Project E",
        client_name: "John Doe",
        status: "in_progress",
        current_stage_label: "Editing",
      },
    ],
    recording_schedule: [
      {
        id: 1,
        project_name: "Nama Project A",
        client_name: "John Doe",
        start_at: "2026-10-28T13:00:00+07:00",
      },
    ],
    quotation_progress: [
      {
        id: 1,
        name: "Penawaran A",
        client_name: "Klien X",
        status: "awaiting_studio",
        status_label: "Klien Menawarkan Harga",
      },
    ],
    operator_activities: [
      {
        id: 1,
        description: "Operator A mengunggah hasil editing.",
        occurred_at: "2026-10-28T13:00:00+07:00",
      },
    ],
  };
  await setup(page, "manager", true, { "/manager/dashboard": { data } });
  await page.setViewportSize({ width: 1920, height: 1000 });
  await page.goto("/manager/dashboard");
  await expect(page.locator(".overview-metric")).toHaveCount(5);
  await expect(page.locator(".overview-charts > section")).toHaveCount(3);
  await expect(page.locator(".overview-lists > section")).toHaveCount(4);
  const lists = await page
    .locator(".overview-lists > section")
    .evaluateAll((nodes) =>
      nodes.map((node) => node.getBoundingClientRect().top),
    );
  expect(new Set(lists).size).toBe(1);
  await verifyAssets(page);
  await page.screenshot({
    path: "test-results/layout-dashboard-1920.png",
    fullPage: true,
  });
});

test("manager quotations filter, sort, and switch layout without changing the API endpoint", async ({
  page,
}) => {
  const quotes = [
    "awaiting_studio",
    "awaiting_client",
    "accepted",
    "declined",
  ].map((status, i) => ({
    id: i + 1,
    name: ["Penawaran Z", "Penawaran D", "Penawaran B", "Penawaran A"][i],
    status,
    status_label: status,
    specifications: packages[0],
    latest_offer: { price: 1250000 },
  }));
  await setup(page, "manager", true, {
    "/manager/quotations": { data: quotes },
  });
  await page.setViewportSize({ width: 1920, height: 1000 });
  await page.goto("/manager/quotation");
  await expect(page.locator(".quotation-card")).toHaveCount(4);
  await verifyAssets(page);
  await page.screenshot({
    path: "test-results/layout-quotations-1920.png",
    fullPage: true,
  });
  await page.getByRole("button", { name: "A–Z", exact: true }).click();
  await expect(page.locator(".quotation-card h3").first()).toHaveText(
    "Penawaran A",
  );
  await page.getByRole("button", { name: "Diterima (1)", exact: true }).click();
  await expect(page.locator(".quotation-card")).toHaveCount(1);
  await page.getByRole("button", { name: "Tabel", exact: true }).click();
  await expect(page.locator(".package-table tbody tr")).toHaveCount(1);
});

test("operator has aligned timeline/team and track/project columns and uploads through the existing endpoint", async ({
  page,
}) => {
  const stages = [
    {
      id: 9,
      project_id: 2,
      project_name: "Project Single",
      client_name: "Karina",
      project_status: "in_progress",
      stage: "recording",
      stage_label: "Recording",
      status: "completed",
      operator_name: "Operator Rekaman",
      tracks: [
        { id: 3, title: "Recording", status: "approved", progress_id: 7 },
      ],
    },
    {
      id: 10,
      project_id: 2,
      project_name: "Project Single",
      client_name: "Karina",
      project_status: "in_progress",
      stage: "editing",
      stage_label: "Editing",
      status: "in_progress",
      operator_name: "Operator Editing",
      requires_deadline: false,
      tracks: [
        { id: 4, title: "Lagu Pertama", status: "pending", progress_id: 8 },
        { id: 5, title: "Lagu Kedua", status: "pending", progress_id: 9 },
      ],
    },
  ];
  const writes = await setup(page, "operator", true, {
    "/operator/sessions": { data: [], meta: { production_stages: stages } },
  });
  await page.setViewportSize({ width: 1920, height: 1000 });
  await page.goto("/operator/tasks?stage=10");
  const top = await page.locator(".production-timeline-panel").boundingBox(),
    team = await page.locator(".production-team").boundingBox(),
    table = await page.locator(".production-work-table").boundingBox(),
    project = await page.locator(".production-project-card").boundingBox();
  expect(Math.abs(top.y - team.y)).toBeLessThan(2);
  expect(Math.abs(table.y - project.y)).toBeLessThan(2);
  expect(table.width).toBeGreaterThan(project.width * 2);
  await verifyAssets(page);
  await page.screenshot({
    path: "test-results/layout-operator-1920.png",
    fullPage: true,
  });
  await page
    .getByRole("button", { name: "Upload Lagu Pertama", exact: true })
    .click();
  const dialog = page.getByRole("dialog");
  await dialog
    .getByLabel("Tautan Hasil")
    .fill("https://example.test/hasil.wav");
  await dialog
    .getByRole("button", { name: "Kirim Hasil", exact: true })
    .click();
  await expect(
    dialog.getByText("Berhasil. Data terbaru sudah dimuat."),
  ).toBeVisible();
  expect(writes[0]).toEqual({
    path: "/operator/track-progress/8/tracks/4/submissions",
    body: { result_url: "https://example.test/hasil.wav" },
  });
});

test("booking blocks reflect actual start time and duration, not stacked list order", async ({
  page,
}) => {
  const day = new Intl.DateTimeFormat("en-CA", {
    timeZone: "Asia/Jakarta",
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
  }).format(new Date());
  const rows = [
    {
      id: 1,
      project_name: "Rekaman A",
      client_name: "Klien A",
      start_at: `${day}T10:00:00+07:00`,
      end_at: `${day}T12:00:00+07:00`,
    },
    {
      id: 2,
      project_name: "Rekaman B",
      client_name: "Klien B",
      start_at: `${day}T13:00:00+07:00`,
      end_at: `${day}T14:00:00+07:00`,
    },
  ];
  await setup(page, "manager", true, { "/manager/bookings": { data: rows } });
  await page.setViewportSize({ width: 1920, height: 1000 });
  await page.goto("/manager/booking");
  const blocks = page.locator(".calendar-session");
  await expect(blocks).toHaveCount(2);
  const first = await blocks.nth(0).boundingBox(),
    second = await blocks.nth(1).boundingBox();
  expect(second.y - first.y).toBeCloseTo(360, 0);
  expect(first.height).toBeGreaterThan(second.height);
  await page.screenshot({
    path: "test-results/layout-booking-1920.png",
    fullPage: true,
  });
});

test("Manager, Client, and Operator inherit the shared palette and typography", async ({
  page,
}) => {
  for (const [role, path] of [
    ["manager", "/manager/packages"],
    ["client", "/client/packages"],
    ["operator", "/operator/tasks"],
  ]) {
    await page.unroute("**/api/v1/**");
    await setup(page, role);
    await page.goto(path);
    const button = page.locator("button.primary:visible").first();
    await expect(button).toBeVisible();
    await expect(button).toHaveCSS("background-color", "rgb(206, 67, 55)");
    await expect(button).toHaveCSS("color", "rgb(255, 255, 255)");
    await expect(button).toHaveCSS("font-size", "14px");
    await expect(button).toHaveCSS("font-weight", "500");
    await expect(button).toHaveCSS("line-height", "20px");
    expect(
      await button.evaluate((el) => getComputedStyle(el).fontFamily),
    ).toContain("DM Sans");
    await page.evaluate(() =>
      document.documentElement.style.setProperty("--ts-primary", "#123456"),
    );
    await expect(button).toHaveCSS("background-color", "rgb(18, 52, 86)");
  }
});

test("quotation and production layouts stay within a mobile viewport", async ({
  page,
}) => {
  await setup(page, "manager", true, {
    "/manager/quotations": {
      data: [
        {
          id: 1,
          name: "Penawaran Mobile",
          status: "awaiting_studio",
          status_label: "Klien Menawarkan Harga",
          specifications: {
            track_count: 1,
            recording_hours: 3,
            include_recording: true,
            include_editing: true,
          },
          latest_offer: { price: 1000000 },
        },
      ],
    },
  });
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto("/manager/quotation");
  await expect(page.locator(".quotation-card")).toHaveCount(1);
  expect(
    await page.evaluate(
      () => document.documentElement.scrollWidth <= innerWidth,
    ),
  ).toBe(true);
  await page.screenshot({
    path: "test-results/layout-quotation-mobile.png",
    fullPage: true,
  });
  await page.unroute("**/api/v1/**");
  await setup(page, "operator");
  await page.goto("/operator/tasks");
  await expect(page.locator(".production-work-table")).toBeVisible();
  expect(
    await page.evaluate(
      () => document.documentElement.scrollWidth <= innerWidth,
    ),
  ).toBe(true);
  await page.getByText("Pilih Project, Tahap & Lagu", { exact: true }).click();
  await expect(
    page.getByRole("combobox", { name: "Tahap Produksi" }),
  ).toBeVisible();
  await page.screenshot({
    path: "test-results/layout-operator-mobile.png",
    fullPage: true,
  });
});

const rates = [
  ["recording", 150000],
  ["editing", 200000],
  ["mixing", 350000],
  ["mastering", 250000],
].map(([stage, price]) => ({ stage, price }));
const packages = Array.from({ length: 6 }, (_, i) => ({
  id: i + 1,
  name: [
    "Paket Lengkap A",
    "Paket Lengkap B",
    "Rekaman Suara",
    "Rekaman Alat Musik",
    "Hanya Editing",
    "Hanya Mixing",
  ][i],
  description:
    "Paket produksi untuk karya Anda, dari rekaman hingga siap dirilis.",
  track_count: 1,
  recording_hours: 3,
  include_recording: i < 4,
  include_editing: i < 2 || i === 4,
  include_mixing: i < 2 || i === 5,
  include_mastering: i < 2,
  price: 1250000,
  is_active: true,
}));
async function setup(page, role = "manager", loggedIn = true, responses = {}) {
  const writes = [];
  await page.route("**/api/v1/**", async (route) => {
    const request = route.request(),
      path = new URL(request.url()).pathname.replace("/api/v1", "");
    if (request.method() === "OPTIONS")
      return route.fulfill({
        status: 204,
        headers: {
          "access-control-allow-origin": "http://localhost:4174",
          "access-control-allow-credentials": "true",
          "access-control-allow-headers": "content-type,x-xsrf-token",
          "access-control-allow-methods": "GET,POST,PUT,PATCH,DELETE",
        },
      });
    if (request.method() === "POST")
      writes.push({ path, body: request.postDataJSON() });
    if (Object.hasOwn(responses, path))
      return route.fulfill({ json: responses[path] });
    let data = [];
    if (path === "/auth/me")
      data = {
        id: 1,
        name: "Herwin O.",
        email: "test@example.test",
        role,
        role_label: role,
      };
    else if (path.endsWith("/packages"))
      data =
        request.method() === "POST"
          ? { id: 7, ...request.postDataJSON() }
          : packages;
    else if (path === "/manager/base-prices") data = rates;
    else if (path === "/notifications/unread-count") data = { unread_count: 2 };
    else if (path === "/operator/sessions")
      return route.fulfill({
        json: {
          data: [],
          meta: {
            production_stages: [
              {
                id: 10,
                project_id: 2,
                project_name: "Project Single",
                project_status: "in_progress",
                stage: "editing",
                stage_label: "Editing",
                status: "in_progress",
                operator_name: "Operator Studio",
                requires_deadline: false,
                tracks: [
                  {
                    id: 4,
                    title: "Lagu Pertama",
                    status: "pending",
                    progress_id: 8,
                  },
                ],
              },
            ],
          },
        },
      });
    await route.fulfill({
      status: path === "/auth/me" && !loggedIn ? 401 : 200,
      json: { data },
    });
  });
  return writes;
}
async function verifyAssets(page) {
  await page.evaluate(() => document.fonts.ready);
  expect(
    await page.evaluate(() => document.fonts.check('16px "DM Sans Variable"')),
  ).toBe(true);
  const images = page.locator("img:visible");
  for (const img of await images.all())
    expect(
      await img.evaluate(
        (el) =>
          el.complete &&
          el.naturalWidth > 0 &&
          el.getBoundingClientRect().width > 0,
      ),
    ).toBe(true);
}

test("Figma package modal calculates live rates, toggles manual price, and submits the API contract", async ({
  page,
}) => {
  const writes = await setup(page);
  await page.setViewportSize({ width: 1440, height: 900 });
  await page.goto("/manager/packages");
  await page.getByRole("button", { name: "Buat Paket", exact: true }).click();
  const dialog = page.getByRole("dialog", { name: "Buat Paket Baru" });
  await expect(dialog.locator("output")).toContainText("1.250.000");
  await dialog.getByLabel("Nama Paket").fill("Paket Baru");
  await verifyAssets(page);
  await dialog.screenshot({
    path: "test-results/figma-package-modal-desktop.png",
  });
  await dialog.getByRole("button", { name: "Tambah Jumlah Lagu" }).click();
  await expect(dialog.locator("output")).toContainText("2.050.000");
  await dialog.getByRole("switch", { name: "Atur Harga Manual" }).check();
  await dialog.getByLabel("Harga Paket", { exact: false }).fill("1800000");
  await expect(dialog.locator("output")).toContainText("1.800.000");
  await dialog.getByRole("switch", { name: "Atur Harga Manual" }).uncheck();
  await expect(dialog.locator("output")).toContainText("2.050.000");
  await dialog.getByRole("button", { name: "Buat Paket", exact: true }).click();
  await expect(
    dialog.getByText("Berhasil. Data terbaru sudah dimuat."),
  ).toBeVisible();
  expect(writes[0]).toEqual({
    path: "/manager/packages",
    body: {
      name: "Paket Baru",
      description: null,
      track_count: 2,
      recording_hours: 3,
      include_recording: true,
      include_editing: true,
      include_mixing: true,
      include_mastering: true,
      manual_price: null,
      is_active: true,
    },
  });
});

test("package filters and view switch work; desktop and mobile assets render locally", async ({
  page,
}) => {
  await setup(page);
  await page.setViewportSize({ width: 1920, height: 1000 });
  await page.goto("/manager/packages");
  await expect(page.locator(".package-card")).toHaveCount(6);
  await verifyAssets(page);
  await page.screenshot({
    path: "test-results/figma-packages-desktop.png",
    fullPage: true,
  });
  await page.getByRole("button", { name: "Mastering (2)" }).click();
  await expect(page.locator(".package-card")).toHaveCount(2);
  await page.getByRole("button", { name: "Tabel", exact: true }).click();
  await expect(page.locator(".package-table tbody tr")).toHaveCount(2);
  await page.getByRole("button", { name: "Kartu", exact: true }).click();
  await page.setViewportSize({ width: 390, height: 844 });
  await page.getByRole("button", { name: "Buat Paket", exact: true }).click();
  const dialog = page.getByRole("dialog", { name: "Buat Paket Baru" });
  await expect(dialog).toBeVisible();
  expect(
    await dialog.evaluate((el) => el.scrollWidth <= el.clientWidth + 1),
  ).toBe(true);
  expect(
    await page.evaluate(
      () => document.documentElement.scrollWidth <= innerWidth,
    ),
  ).toBe(true);
  await dialog.screenshot({
    path: "test-results/figma-package-modal-mobile.png",
  });
  await dialog
    .getByRole("button", { name: "Buat Paket", exact: true })
    .scrollIntoViewIfNeeded();
  await expect(
    dialog.getByRole("button", { name: "Buat Paket", exact: true }),
  ).toBeInViewport();
  await dialog.screenshot({
    path: "test-results/figma-package-modal-mobile-pricing.png",
  });
});

test("client uses the Figma top navigation and operator keeps its production actions", async ({
  page,
}) => {
  await setup(page, "client");
  await page.goto("/client/packages");
  await expect(
    page.getByRole("heading", { name: "Paket yang Kami Sediakan" }),
  ).toBeVisible();
  await expect(page.locator(".client-navbar")).toBeVisible();
  await verifyAssets(page);
  await page.screenshot({
    path: "test-results/figma-client-catalog.png",
    fullPage: true,
  });
  await page.unroute("**/api/v1/**");
  await setup(page, "operator");
  await page.goto("/operator/tasks");
  await expect(
    page.getByRole("button", { name: "Kirim Hasil / Upload" }),
  ).toBeEnabled();
  await page.screenshot({
    path: "test-results/figma-operator-production.png",
    fullPage: true,
  });
});

test("login uses the local Figma artwork and bundled DM Sans", async ({
  page,
}) => {
  await setup(page, "client", false);
  await page.goto("/login");
  await expect(page.getByRole("heading", { name: "Sign In" })).toBeVisible();
  await verifyAssets(page);
  await page.screenshot({
    path: "test-results/figma-login.png",
    fullPage: true,
  });
});

test("client custom quotation submits the existing API schema and prevents duplicate submission", async ({
  page,
}) => {
  const writes = await setup(page, "client");
  await page.goto("/client/packages");
  const form = page.locator(".client-quote-layout");
  await form.getByLabel("Nama Penawaran").fill("Single Baru");
  await form.getByLabel("Input Harga Tawaran").fill("1200000");
  await form
    .getByRole("button", { name: "Kirim Penawaran", exact: true })
    .click();
  await expect(
    form.getByText("Penawaran berhasil dikirim. Tunggu tanggapan studio."),
  ).toBeVisible();
  await expect(
    form.getByRole("button", { name: "Kirim Penawaran", exact: true }),
  ).toBeDisabled();
  expect(writes).toEqual([
    {
      path: "/client/quotations",
      body: {
        name: "Single Baru",
        track_count: 1,
        recording_hours: 3,
        include_recording: true,
        include_editing: true,
        include_mixing: true,
        include_mastering: true,
        notes: null,
        offer_price: 1200000,
      },
    },
  ]);
});

test("manager booking calendar navigates weeks and renders empty schedules", async ({
  page,
}) => {
  await setup(page);
  await page.goto("/manager/booking");
  await expect(page.locator(".week-day-column")).toHaveCount(7);
  const heading = await page.locator(".calendar-week h1").innerText();
  await page.getByRole("button", { name: "Bulan berikutnya" }).click();
  await expect(page.locator(".calendar-week h1")).not.toHaveText(heading);
  await page.getByRole("button", { name: "Hari Ini", exact: true }).click();
  await expect(page.locator(".calendar-week h1")).toHaveText(heading);
  await page.getByRole("button", { name: "Minggu berikutnya" }).click();
  await expect(page.locator(".calendar-free")).toHaveCount(7);
  await page.screenshot({
    path: "test-results/figma-booking-calendar.png",
    fullPage: true,
  });
});
