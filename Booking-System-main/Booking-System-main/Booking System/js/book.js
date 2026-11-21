const checkInInput = document.querySelector("#check-in");
const checkOutInput = document.querySelector("#check-out");
const tourTypeInput = document.querySelector("#tour-type");
const checkInTimeInput = document.querySelector("#check-in-time");
const checkOutTimeInput = document.querySelector("#check-out-time");
const checkOutLabel = document.querySelector("#check-out-label");
const poolChoiceInput = document.querySelector("#pool-choice");
const confirmedBookings = window.confirmedBookings || [];

const localToday = new Date();
localToday.setMinutes(localToday.getMinutes() - localToday.getTimezoneOffset());
const todayValue = localToday.toISOString().slice(0, 10);

checkInInput.min = todayValue;
checkOutInput.min = todayValue;

function updateCheckoutDate() {
    const hasCheckInConflict = checkInInput.value && poolChoiceInput.value && confirmedBookings.some((booking) =>
        booking.pool_size === poolChoiceInput.value &&
        checkInInput.value <= booking.check_out && (
            tourTypeInput.value === "Overnight Tour"
                ? (checkOutInput.value || checkInInput.value) >= booking.check_in
                : checkInInput.value >= booking.check_in
        )
    );

    checkInInput.setCustomValidity(
        hasCheckInConflict
            ? "This date is unavailable because there is a confirmed booking."
            : ""
    );

    if (tourTypeInput.value === "Day Tour") {
        checkOutInput.min = checkInInput.value || todayValue;
        if (checkInInput.value) {
            checkOutInput.value = checkInInput.value;
        }
        checkOutInput.disabled = true;
        checkOutLabel.textContent = "Check-out (same day)";
        checkOutInput.setCustomValidity(
            checkInInput.value && checkOutInput.value !== checkInInput.value
                ? "Day tours must check out on the same date."
                : ""
        );
    } else {
        checkOutInput.disabled = false;
        checkOutLabel.textContent = "Check-out";
        checkOutInput.min = checkInInput.value || todayValue;
        checkOutInput.setCustomValidity(
            tourTypeInput.value === "Overnight Tour" &&
            checkInInput.value &&
            checkOutInput.value &&
            checkOutInput.value <= checkInInput.value
                ? "Overnight tours must check out after check-in."
                : ""
        );
    }
}

function updateCheckoutTime() {
    checkOutTimeInput.setCustomValidity(
        tourTypeInput.value === "Day Tour" &&
        checkInInput.value &&
        checkOutInput.value &&
        checkInInput.value === checkOutInput.value &&
        checkInTimeInput.value &&
        checkOutTimeInput.value &&
        checkOutTimeInput.value <= checkInTimeInput.value
            ? "Check-out time must be later than check-in time."
            : ""
    );
}

checkInInput.addEventListener("change", updateCheckoutDate);
checkOutInput.addEventListener("change", updateCheckoutDate);
poolChoiceInput.addEventListener("change", updateCheckoutDate);
tourTypeInput.addEventListener("change", () => {
    updateCheckoutDate();
    updateCheckoutTime();
});
checkInTimeInput.addEventListener("change", updateCheckoutTime);
checkOutTimeInput.addEventListener("change", updateCheckoutTime);
updateCheckoutDate();