const carouselTrack = document.querySelector('.carousel-track');
const carouselSlides = Array.from(document.querySelectorAll('.carousel-slide'));
const carouselDots = Array.from(document.querySelectorAll('.carousel-dots .dot'));

if (carouselTrack && carouselSlides.length > 0) {
    let activePhotoIndex = 0;

    function showPhoto(index) {
        activePhotoIndex = (index + carouselSlides.length) % carouselSlides.length;
        carouselTrack.style.transform = `translateX(-${activePhotoIndex * 100}%)`;

        carouselDots.forEach((dot, dotIndex) => {
            const isActive = dotIndex === activePhotoIndex;
            dot.classList.toggle('is-active', isActive);

            if (isActive) {
                dot.setAttribute('aria-current', 'true');
            } else {
                dot.removeAttribute('aria-current');
            }
        });
    }

    carouselDots.forEach((dot, index) => {
        dot.addEventListener('click', () => showPhoto(index));
    });

    window.setInterval(() => showPhoto(activePhotoIndex + 1), 5000);
}
