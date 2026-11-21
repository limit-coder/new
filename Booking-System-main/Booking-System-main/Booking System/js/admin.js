const tableWrap = document.querySelector("#table-wrap");
const emptyState = document.querySelector("#empty-state");
const statusFilter = document.querySelector("#status-filter");
const sortAlgorithmSelect = document.querySelector("#sort-algorithm");
const bookingRows = [...document.querySelectorAll("#booking-rows tr")];
const sectionLinks = [...document.querySelectorAll('.side-link[href="#overview"], .side-link[href="#bookings"]')];
const sections = [...document.querySelectorAll(".admin-main > section")];
const currentSectionLabel = document.querySelector("#current-section");

function showSection(sectionId, moveFocus = false) {
    const activeSection = sections.find((section) => section.id === sectionId) || sections[0];

    for (const section of sections) {
        section.hidden = section !== activeSection;
    }

    for (const link of sectionLinks) {
        const isActive = link.hash === `#${activeSection.id}`;
        link.classList.toggle("is-active", isActive);
        if (isActive) {
            link.setAttribute("aria-current", "page");
        } else {
            link.removeAttribute("aria-current");
        }
    }

    currentSectionLabel.textContent = activeSection.id === "bookings" ? "Bookings" : "Overview";

    if (moveFocus) {
        activeSection.querySelector("h1, h2")?.focus({ preventScroll: true });
        window.scrollTo({ top: 0, behavior: "smooth" });
    }
}

for (const link of sectionLinks) {
    link.addEventListener("click", (event) => {
        event.preventDefault();
        const sectionId = link.hash.slice(1);
        history.pushState(null, "", `#${sectionId}`);
        showSection(sectionId, true);
    });
}

function showSectionFromHash() {
    showSection(window.location.hash.slice(1));
}

window.addEventListener("hashchange", showSectionFromHash);
window.addEventListener("popstate", showSectionFromHash);

function filterBookings() {
    const selectedStatus = statusFilter.value;
    let visibleCount = 0;

    for (const row of bookingRows) {
        const matchesStatus = selectedStatus === "all" || row.dataset.status === selectedStatus;
        row.hidden = !matchesStatus;
        if (!row.hidden) visibleCount += 1;
    }

    tableWrap.hidden = visibleCount === 0;
    emptyState.hidden = visibleCount !== 0;
    if (bookingRows.length > 0) {
        emptyState.textContent = "No requests match your status filter.";
    }
}

statusFilter.addEventListener("change", filterBookings);
sortAlgorithmSelect.addEventListener("change", () => sortAlgorithmSelect.form.requestSubmit());
showSectionFromHash();
filterBookings();