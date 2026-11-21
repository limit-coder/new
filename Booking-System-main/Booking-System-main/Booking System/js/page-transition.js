const transitionStorageKey = "public-page-transition";
const reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
const pageTransitionPages = new Set(["home.php", "about.php", "book.php"]);

if (sessionStorage.getItem(transitionStorageKey) === "enter") {
    sessionStorage.removeItem(transitionStorageKey);
    document.documentElement.classList.add("is-entering");
    document.body.addEventListener("animationend", (event) => {
        if (event.target === document.body && event.animationName === "public-page-enter") {
            document.documentElement.classList.remove("is-entering");
        }
    });
}

let navigationInProgress = false;

document.addEventListener("click", (event) => {
    if (
        event.defaultPrevented ||
        event.button !== 0 ||
        event.metaKey ||
        event.ctrlKey ||
        event.shiftKey ||
        event.altKey
    ) {
        return;
    }

    const target = event.target;
    if (!(target instanceof Element)) {
        return;
    }

    const link = target.closest("a[href]");
    if (!link || link.hasAttribute("download") || (link.target && link.target !== "_self")) {
        return;
    }

    const destination = new URL(link.href, window.location.href);
    const pageName = destination.pathname.split("/").pop();
    if (
        destination.origin !== window.location.origin ||
        !pageTransitionPages.has(pageName) ||
        (destination.pathname === window.location.pathname && destination.search === window.location.search)
    ) {
        return;
    }

    event.preventDefault();
    if (navigationInProgress) {
        return;
    }
    navigationInProgress = true;

    if (reduceMotion) {
        window.location.assign(destination.href);
        return;
    }

    sessionStorage.setItem(transitionStorageKey, "enter");
    document.documentElement.classList.add("is-leaving");
    window.setTimeout(() => window.location.assign(destination.href), 220);
});
