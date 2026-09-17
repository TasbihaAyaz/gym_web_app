/* ============================================================
   Fit Generation — Dashboard charts (live data)
   ============================================================ */

"use strict";

(function () {
  const data = window.dashboardData || {};
  const CURRENCY = data.currency || "₨";
  const fmtMoney = (v) => CURRENCY + Number(v).toLocaleString();
  const fmtNum = (v) => Number(v).toLocaleString();

  if (typeof Chart === "undefined") return;

  Chart.defaults.font.family = "'Inter', system-ui, sans-serif";
  Chart.defaults.font.size = 10.5;
  Chart.defaults.color = "#8b8b9e";
  Chart.defaults.borderColor = "rgba(255,255,255,0.05)";

  /* Revenue line chart */
  const revCanvas = document.getElementById("revenueChart");
  if (revCanvas) {
    const revenue = data.revenue || { labels: [], values: [] };
    const revCtx = revCanvas.getContext("2d");
    const maxRev = Math.max(1000, ...(revenue.values || [0]));

    new Chart(revCtx, {
      type: "line",
      data: {
        labels: revenue.labels || [],
        datasets: [{
          label: "Revenue",
          data: revenue.values || [],
          borderColor: "#8b6cff",
          borderWidth: 2.5,
          tension: 0.45,
          fill: true,
          backgroundColor: (c) => {
            const { ctx, chartArea } = c.chart;
            if (!chartArea) return "rgba(124,92,255,0.15)";
            const g = ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
            g.addColorStop(0, "rgba(124, 92, 255, 0.35)");
            g.addColorStop(1, "rgba(124, 92, 255, 0.0)");
            return g;
          },
          pointRadius: 4,
          pointHoverRadius: 6,
          pointBackgroundColor: "#0b0b12",
          pointBorderColor: "#a78bfa",
          pointBorderWidth: 2,
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: "nearest", intersect: false },
        plugins: {
          legend: { display: false },
          tooltip: {
            backgroundColor: "#1c1c2b",
            borderColor: "#2c2c3a",
            borderWidth: 1,
            titleColor: "#f1f1f5",
            bodyColor: "#a78bfa",
            padding: 10,
            displayColors: false,
            callbacks: {
              label: (item) => "Revenue   " + fmtMoney(item.parsed.y),
            },
          },
        },
        scales: {
          y: {
            min: 0,
            suggestedMax: maxRev * 1.2,
            ticks: {
              callback: (v) => (v === 0 ? CURRENCY + "0" : CURRENCY + (v >= 1000 ? v / 1000 + "K" : v)),
            },
            grid: { color: "rgba(255,255,255,0.05)" },
            border: { display: false },
          },
          x: {
            grid: { display: false },
            border: { display: false },
          },
        },
      },
    });
  }

  /* Membership donut */
  const donutCanvas = document.getElementById("donutChart");
  if (donutCanvas) {
    const membership = data.membership || [0, 0, 0, 0];
    const isDark = document.documentElement.getAttribute("data-theme") === "dark";
    new Chart(donutCanvas.getContext("2d"), {
      type: "doughnut",
      data: {
        labels: ["Active", "Leave", "Pending", "Cancelled"],
        datasets: [{
          data: membership,
          backgroundColor: ["#16a34a", "#d97706", "#2563eb", "#dc2626"],
          borderColor: isDark ? "#14141f" : "#ffffff",
          borderWidth: 3,
          borderRadius: 4,
          hoverOffset: 4,
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: "74%",
        plugins: {
          legend: { display: false },
          tooltip: {
            backgroundColor: isDark ? "#1c1c2b" : "#0f172a",
            borderColor: isDark ? "#2c2c3a" : "transparent",
            borderWidth: 1,
            padding: 10,
            titleColor: "#fff",
            bodyColor: "#fff",
            callbacks: { label: (item) => " " + item.label + ": " + fmtNum(item.parsed) },
          },
        },
      },
    });
  }

  /* Income vs expenses bars */
  const barCanvas = document.getElementById("barChart");
  if (barCanvas) {
    const ie = data.incomeExpenses || { labels: [], income: [], expenses: [] };
    const maxBar = Math.max(1000, ...(ie.income || [0]), ...(ie.expenses || [0]));

    new Chart(barCanvas.getContext("2d"), {
      type: "bar",
      data: {
        labels: ie.labels || [],
        datasets: [
          {
            label: "Income",
            data: ie.income || [],
            backgroundColor: "#22c55e",
            borderRadius: 5,
            barPercentage: 0.55,
            categoryPercentage: 0.6,
          },
          {
            label: "Expenses",
            data: ie.expenses || [],
            backgroundColor: "#ef4444",
            borderRadius: 5,
            barPercentage: 0.55,
            categoryPercentage: 0.6,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            backgroundColor: "#1c1c2b",
            borderColor: "#2c2c3a",
            borderWidth: 1,
            padding: 10,
            callbacks: { label: (item) => " " + item.dataset.label + ": " + fmtMoney(item.parsed.y) },
          },
        },
        scales: {
          y: {
            min: 0,
            suggestedMax: maxBar * 1.2,
            ticks: {
              callback: (v) => (v === 0 ? CURRENCY + "0" : CURRENCY + (v >= 1000 ? v / 1000 + "K" : v)),
            },
            grid: { color: "rgba(255,255,255,0.05)" },
            border: { display: false },
          },
          x: {
            grid: { display: false },
            border: { display: false },
          },
        },
      },
    });
  }

  /* Trainer performance — members per trainer */
  const trainerCanvas = document.getElementById("trainerChart");
  if (trainerCanvas) {
    const tp = data.trainerPerformance || { labels: [], values: [] };
    const values = tp.values || [];
    const top = Math.max(...values, 0);

    new Chart(trainerCanvas.getContext("2d"), {
      type: "bar",
      data: {
        labels: tp.labels || [],
        datasets: [
          {
            label: "Members",
            data: values,
            backgroundColor: values.map((v) => (v === top && top > 0 ? "#7c5cff" : "rgba(124,92,255,0.45)")),
            borderRadius: 5,
            barPercentage: 0.6,
            categoryPercentage: 0.7,
          },
        ],
      },
      options: {
        indexAxis: "y",
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            backgroundColor: "#1c1c2b",
            borderColor: "#2c2c3a",
            borderWidth: 1,
            padding: 10,
            callbacks: { label: (item) => " " + item.parsed.x + " members" },
          },
        },
        scales: {
          x: {
            min: 0,
            suggestedMax: Math.max(5, top * 1.15),
            ticks: { precision: 0 },
            grid: { color: "rgba(255,255,255,0.05)" },
            border: { display: false },
          },
          y: {
            grid: { display: false },
            border: { display: false },
          },
        },
      },
    });
  }
})();
