/* ============================================================
   Fit Generation — Dashboard charts + editable bindings
   All data below is placeholder (UI-only). Charts re-render
   automatically whenever a bound value is edited or dragged.
   ============================================================ */

"use strict";

/* ---------- Shared Chart.js defaults ---------- */
Chart.defaults.font.family = "'Inter', system-ui, sans-serif";
Chart.defaults.font.size = 10.5;
Chart.defaults.color = "#8b8b9e";
Chart.defaults.borderColor = "rgba(255,255,255,0.05)";

const CURRENCY = "₨";`r`nconst fmtMoney = (v) => CURRENCY + Number(v).toLocaleString();
const fmtNum = (v) => Number(v).toLocaleString();
const parseNum = (t) => {
  const n = parseFloat(String(t).replace(/[^0-9.\-]/g, ""));
  return isNaN(n) ? 0 : n;
};

/* ============================================================
   1) REVENUE OVERVIEW — line/area chart (draggable points)
   ============================================================ */
const revenueData = {
  labels: ["12 May", "13 May", "14 May", "15 May", "16 May", "17 May", "18 May"],
  values: [11000, 16500, 27000, 28450, 14500, 17500, 30500],
};

const revCtx = document.getElementById("revenueChart").getContext("2d");

function revenueGradient(ctx, area) {
  const g = ctx.createLinearGradient(0, area.top, 0, area.bottom);
  g.addColorStop(0, "rgba(124, 92, 255, 0.35)");
  g.addColorStop(1, "rgba(124, 92, 255, 0.0)");
  return g;
}

const revenueChart = new Chart(revCtx, {
  type: "line",
  data: {
    labels: revenueData.labels,
    datasets: [{
      label: "Revenue",
      data: revenueData.values,
      borderColor: "#8b6cff",
      borderWidth: 2.5,
      tension: 0.45,
      fill: true,
      backgroundColor: (c) => {
        const { ctx, chartArea } = c.chart;
        if (!chartArea) return "rgba(124,92,255,0.15)";
        return revenueGradient(ctx, chartArea);
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
          title: (items) => items[0].label + ", 2025",
          label: (item) => "Revenue   " + fmtMoney(item.parsed.y),
        },
      },
    },
    scales: {
      y: {
        min: 0,
        suggestedMax: 40000,
        ticks: {
          stepSize: 10000,
          callback: (v) => (v === 0 ? CURRENCY + "0" : CURRENCY + v / 1000 + "K"),
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

/* ============================================================
   2) MEMBERSHIP STATUS — doughnut (bound to legend values)
   ============================================================ */
const donutColors = ["#22c55e", "#f59e0b", "#3b82f6", "#ef4444"];
const donutData = [846, 256, 102, 44];

const donutChart = new Chart(document.getElementById("donutChart").getContext("2d"), {
  type: "doughnut",
  data: {
    labels: ["Active", "Leave", "Pending", "Cancelled"],
    datasets: [{
      data: donutData.slice(),
      backgroundColor: donutColors,
      borderColor: "#14141f",
      borderWidth: 4,
      borderRadius: 6,
      hoverOffset: 6,
    }],
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    cutout: "72%",
    plugins: {
      legend: { display: false },
      tooltip: {
        backgroundColor: "#1c1c2b",
        borderColor: "#2c2c3a",
        borderWidth: 1,
        padding: 10,
        callbacks: { label: (item) => " " + item.label + ": " + fmtNum(item.parsed) },
      },
    },
  },
});

function refreshDonut() {
  const vals = [...document.querySelectorAll(".donut-val")].map((el) => parseNum(el.textContent));
  const total = vals.reduce((a, b) => a + b, 0) || 1;
  donutChart.data.datasets[0].data = vals;
  donutChart.update();
  document.getElementById("donutTotal").textContent = fmtNum(total);
  document.querySelectorAll(".legend-pct").forEach((el) => {
    const idx = +el.dataset.pct;
    el.textContent = "(" + ((vals[idx] / total) * 100).toFixed(1) + "%)";
  });
}

document.querySelectorAll(".donut-val").forEach((el) => {
  el.addEventListener("input", refreshDonut);
  el.addEventListener("blur", refreshDonut);
});

/* ============================================================
   3) INCOME VS EXPENSES — grouped bars (draggable)
   ============================================================ */
const barChart = new Chart(document.getElementById("barChart").getContext("2d"), {
  type: "bar",
  data: {
    labels: ["Week 1", "Week 2", "Week 3", "Week 4", "Week 5"],
    datasets: [
      {
        label: "Income",
        data: [30000, 25500, 31000, 36500, 21000],
        backgroundColor: "#22c55e",
        borderRadius: 5,
        barPercentage: 0.55,
        categoryPercentage: 0.6,
      },
      {
        label: "Expenses",
        data: [12500, 10000, 13500, 15500, 9000],
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
        suggestedMax: 40000,
        ticks: {
          stepSize: 10000,
          callback: (v) => (v === 0 ? CURRENCY + "0" : CURRENCY + v / 1000 + "K"),
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

/* ============================================================
   4) Drag-to-edit for line + bar charts
   ============================================================ */
function enableDrag(chart) {
  const canvas = chart.canvas;
  let active = null; // { datasetIndex, index }

  const hit = (evt) => {
    const els = chart.getElementsAtEventForMode(evt, "nearest", { intersect: true }, false);
    return els.length ? { datasetIndex: els[0].datasetIndex, index: els[0].index } : null;
  };

  const valueFromY = (evt) => {
    const rect = canvas.getBoundingClientRect();
    const y = (evt.touches ? evt.touches[0].clientY : evt.clientY) - rect.top;
    const scale = chart.scales.y;
    return Math.max(scale.min, Math.min(scale.max, scale.getValueForPixel(y)));
  };

  const start = (evt) => {
    active = hit(evt);
    if (active) canvas.style.cursor = "grabbing";
  };
  const move = (evt) => {
    if (!active) {
      canvas.style.cursor = hit(evt) ? "grab" : "default";
      return;
    }
    evt.preventDefault();
    const v = Math.round(valueFromY(evt) / 100) * 100;
    chart.data.datasets[active.datasetIndex].data[active.index] = v;
    chart.update("none");
  };
  const end = () => {
    active = null;
    canvas.style.cursor = "default";
  };

  canvas.addEventListener("mousedown", start);
  canvas.addEventListener("mousemove", move);
  window.addEventListener("mouseup", end);
  canvas.addEventListener("touchstart", start, { passive: true });
  canvas.addEventListener("touchmove", move, { passive: false });
  window.addEventListener("touchend", end);
}

enableDrag(revenueChart);
enableDrag(barChart);

/* ============================================================
   5) Popular Classes — capacity % edits move progress bars
   ============================================================ */
document.querySelectorAll(".class-row").forEach((row) => {
  const capVal = row.querySelector(".cap-val");
  const fill = row.querySelector(".cap-fill");
  const sync = () => {
    const pct = Math.max(0, Math.min(100, parseNum(capVal.textContent)));
    fill.style.width = pct + "%";
  };
  capVal.addEventListener("input", sync);
  capVal.addEventListener("blur", sync);
});

/* ============================================================
   6) contenteditable niceties: Enter = commit (blur), no newlines
   ============================================================ */
document.querySelectorAll("[contenteditable=true]").forEach((el) => {
  el.addEventListener("keydown", (e) => {
    if (e.key === "Enter") {
      e.preventDefault();
      el.blur();
    }
  });
  // strip pasted formatting
  el.addEventListener("paste", (e) => {
    e.preventDefault();
    const text = (e.clipboardData || window.clipboardData).getData("text/plain");
    document.execCommand("insertText", false, text);
  });
});
