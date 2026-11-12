document.addEventListener("DOMContentLoaded", () => {
    // 1. Auto-submit dropdown filters
    document.querySelectorAll("[data-autosubmit]").forEach((el) => {
        el.addEventListener("change", () => {
            if (el.form) el.form.submit();
        });
    });

    // 2. Render Chart.js
    if (typeof Chart !== "undefined") {
        document.querySelectorAll("canvas[data-chart]").forEach((canvas) => {
            try {
                const config = JSON.parse(canvas.getAttribute("data-chart"));
                new Chart(canvas, config);
            } catch (err) {
                console.error("Gagal merender grafik:", err);
            }
        });
    }

    // 3. Dynamic Calculation in Input Grid Table
    document.querySelectorAll("tr[data-row]").forEach((row) => {
        const numInputs = row.querySelectorAll("input.in-num");
        const avgSpan = row.querySelector("[data-avg]");
        const naCheck = row.querySelector("input[type=checkbox]");

        function hitungRata() {
            if (!avgSpan) return;
            let sum = 0;
            let count = 0;
            numInputs.forEach((inp) => {
                let s = inp.value.trim().replace(/\s+/g, "").replace(",", ".");
                if (s !== "" && !isNaN(s)) {
                    sum += parseFloat(s);
                    count++;
                }
            });
            if (count > 0) {
                avgSpan.textContent = (sum / count).toLocaleString("id-ID", {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                });
            } else {
                avgSpan.textContent = "–";
            }
        }

        if (naCheck) {
            naCheck.addEventListener("change", () => {
                const disabled = naCheck.checked;
                numInputs.forEach((inp) => {
                    inp.disabled = disabled;
                    if (disabled) inp.dataset.prev = inp.value;
                    else if (inp.dataset.prev) inp.value = inp.dataset.prev;
                });
                hitungRata();
            });
            if (naCheck.checked) {
                numInputs.forEach((inp) => (inp.disabled = true));
            }
        }

        numInputs.forEach((inp) => {
            inp.addEventListener("input", hitungRata);
        });

        hitungRata();
    });
});
