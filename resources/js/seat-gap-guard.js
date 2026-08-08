/**
 * Advisory-only seat gap feedback. The PHP policy remains authoritative and runs again inside
 * the checkout transaction. This module uses physical rendered coordinates and physical seat
 * ids, including both halves represented by one couple control.
 */
const MESSAGE_ISOLATED_SEAT = "Không thể chọn ghế này vì lựa chọn hiện tại sẽ để trống một ghế đơn trong hàng. Vui lòng chọn các ghế liền nhau.";
const guards = new WeakMap();

function positionsFor(button) {
    const geometry = (button.dataset.seatGeometry || "")
        .split(",")
        .map((item) => item.trim())
        .filter(Boolean)
        .map((item) => {
            const [seatId, x] = item.split(":");
            return { seatId, x: Number.parseInt(x || "", 10), button };
        })
        .filter(({ seatId, x }) => seatId !== "" && Number.isFinite(x));

    if (geometry.length) return geometry;

    const seatIds = (button.dataset.seatIds || "").split(",").map((id) => id.trim()).filter(Boolean);
    const x = Number.parseInt(button.dataset.seatX || "", 10);

    return seatIds.length === 1 && Number.isFinite(x)
        ? [{ seatId: seatIds[0], x, button }]
        : [];
}

function buildSegments(form) {
    const rows = new Map();

    form.querySelectorAll(".seat-button[data-seat-row]").forEach((button) => {
        const y = Number.parseInt(button.dataset.seatRow || "", 10);
        if (!Number.isFinite(y)) return;
        if (!rows.has(y)) rows.set(y, new Map());

        positionsFor(button).forEach((position) => {
            rows.get(y).set(position.x, position);
        });
    });

    const segments = [];
    rows.forEach((row) => {
        const xs = Array.from(row.keys()).sort((a, b) => a - b);
        let current = [];
        let previousX = null;

        xs.forEach((x) => {
            if (previousX !== null && x !== previousX + 1 && current.length) {
                segments.push(current);
                current = [];
            }
            current.push(row.get(x));
            previousX = x;
        });

        if (current.length) segments.push(current);
    });

    return segments;
}

function isBlocked(position, selectedSeatIds) {
    return position.button.dataset.seatAvailable === "0"
        || position.button.disabled
        || selectedSeatIds.has(position.seatId);
}

function isolatedSeatIds(segments, selectedSeatIds) {
    const isolated = new Set();

    segments.forEach((segment) => {
        segment.forEach((position, index) => {
            if (isBlocked(position, selectedSeatIds)) return;

            const left = index === 0 || isBlocked(segment[index - 1], selectedSeatIds);
            const right = index === segment.length - 1 || isBlocked(segment[index + 1], selectedSeatIds);
            if (left && right) isolated.add(position.seatId);
        });
    });

    return isolated;
}

function selectedSeatIds(form) {
    return new Set(
        Array.from(form.querySelectorAll('.seat-button[aria-pressed="true"]'))
            .flatMap((button) => (button.dataset.seatIds || "").split(","))
            .map((seatId) => seatId.trim())
            .filter(Boolean),
    );
}

function orphanMessage(segments, introduced) {
    const orphan = segments.flat().find((position) => introduced.has(position.seatId));
    const code = orphan?.button.dataset.seatCode?.trim();
    const row = orphan?.button.dataset.seatRowLabel?.trim();

    return code && row
        ? `Không thể tiếp tục vì ghế ${code} sẽ bị bỏ trống một mình trong hàng ${row}.`
        : MESSAGE_ISOLATED_SEAT;
}

function revealError(error) {
    if (!error) return;
    if (!error.hasAttribute("tabindex")) error.setAttribute("tabindex", "-1");
    error.focus({ preventScroll: true });
    error.scrollIntoView({ behavior: "smooth", block: "center" });
}

export function initializeSeatGapGuard() {
    document.querySelectorAll("form[data-seat-picker]").forEach((form) => {
        const existing = guards.get(form);
        if (existing) {
            existing();
            return;
        }

        const segments = buildSegments(form);
        if (!segments.length) return;

        const error = document.getElementById("seatSelectionError");
        const continueButton = document.getElementById("continueBookingButton");
        const baseline = isolatedSeatIds(segments, new Set());
        let userInteracted = false;

        const evaluate = () => {
            const selected = selectedSeatIds(form);
            const after = isolatedSeatIds(segments, selected);
            const introduced = new Set(Array.from(after).filter((seatId) => !baseline.has(seatId)));
            const invalid = selected.size > 0 && introduced.size > 0;

            form.dataset.seatGapInvalid = invalid ? "true" : "false";
            if (continueButton) {
                continueButton.disabled = selected.size === 0 || invalid;
            }
            if (error && invalid) {
                error.textContent = orphanMessage(segments, introduced);
                error.hidden = false;
                error.dataset.serverError = "false";
            } else if (error && (userInteracted || error.dataset.serverError !== "true")) {
                error.textContent = "";
                error.hidden = true;
                error.dataset.serverError = "false";
            }
        };

        form.addEventListener("click", (event) => {
            if (event.target.closest(".seat-button")) {
                userInteracted = true;
                window.setTimeout(evaluate, 0);
            }
        });
        form.addEventListener("seat-selection:changed", evaluate);
        form.addEventListener("submit", (event) => {
            userInteracted = true;
            evaluate();
            if (form.dataset.seatGapInvalid === "true") {
                event.preventDefault();
                revealError(error);
            }
        });

        guards.set(form, evaluate);
        form.dataset.seatGapGuardInitialized = "true";
        evaluate();
    });
}

document.addEventListener("DOMContentLoaded", initializeSeatGapGuard, { once: true });
window.addEventListener("pageshow", initializeSeatGapGuard);
