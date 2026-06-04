<!-- Swiper CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
<!-- Hero CSS -->
<link rel="stylesheet" href="assets/css/hero.css">

<!-- Hero Slider Section -->
<section class="hero-slider-container">
    <div class="swiper heroSwiper">
        <div class="swiper-wrapper">
            <div class="swiper-slide">
                <img src="https://static.pw.live/5eb393ee95fab7468a79d189/GLOBAL_CMS/a4d6b0c7-a9ee-41a0-9bff-ee3dd71b69b4.webp"
                    alt="MG Education Banner">
            </div>
            <!-- Additional slides can be added here -->
            <div class="swiper-slide">
                <img src="https://static.pw.live/5eb393ee95fab7468a79d189/GLOBAL_CMS/96f3ee89-9db0-4080-a438-84dff5ed139c.webp"
                    alt="MG Education Banner 2">
            </div>
        </div>
        <!-- Swiper Navigation -->
        <div class="swiper-button-next"></div>
        <div class="swiper-button-prev"></div>
    </div>
</section>

<!-- Swiper JS -->
<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>

<!-- Initialize Swiper -->
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const swiper = new Swiper('.heroSwiper', {
            loop: true,
            autoplay: {
                delay: 5000,
                disableOnInteraction: false,
            },
            navigation: {
                nextEl: '.swiper-button-next',
                prevEl: '.swiper-button-prev',
            },
            effect: 'fade',
            fadeEffect: {
                crossFade: true
            }
        });
    });
</script>