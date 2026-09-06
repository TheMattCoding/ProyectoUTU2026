document.addEventListener('DOMContentLoaded', () => {
    const slides = document.querySelectorAll('.carousel-slide');
    const dots = document.querySelectorAll('.carousel-dots .dot');
    const btnPrev = document.getElementById('prevSlide');
    const btnNext = document.getElementById('nextSlide');
    
    if (slides.length === 0) return;

    let currentIndex = 0;
    let autoSlideInterval = null;
    const intervalTime = 5000;

    function showSlide(index) {
        if (index >= slides.length) currentIndex = 0;
        else if (index < 0) currentIndex = slides.length - 1;
        else currentIndex = index;

        slides.forEach((slide, i) => {
            if (i === currentIndex) {
                slide.classList.add('active');
            } else {
                slide.classList.remove('active');
            }
        });

        dots.forEach((dot, i) => {
            if (i === currentIndex) {
                dot.classList.add('active');
            } else {
                dot.classList.remove('active');
            }
        });
    }

    function nextSlide() {
        showSlide(currentIndex + 1);
    }

    function prevSlide() {
        showSlide(currentIndex - 1);
    }

    function startAutoSlide() {
        stopAutoSlide();
        autoSlideInterval = setInterval(nextSlide, intervalTime);
    }

    function stopAutoSlide() {
        if (autoSlideInterval) {
            clearInterval(autoSlideInterval);
        }
    }

    if (btnNext) {
        btnNext.addEventListener('click', () => {
            nextSlide();
            startAutoSlide();
        });
    }

    if (btnPrev) {
        btnPrev.addEventListener('click', () => {
            prevSlide();
            startAutoSlide();
        });
    }

    dots.forEach((dot, i) => {
        dot.addEventListener('click', () => {
            showSlide(i);
            startAutoSlide();
        });
    });

    const carouselCard = document.querySelector('.news-carousel-card');
    if (carouselCard) {
        carouselCard.addEventListener('mouseenter', stopAutoSlide);
        carouselCard.addEventListener('mouseleave', startAutoSlide);
    }

    showSlide(0);
    startAutoSlide();
});