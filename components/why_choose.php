<!-- Why Choose CSS -->
<link rel="stylesheet" href="assets/css/why_choose.css">

<!-- ============================================================
     MG EDUCATION — Full Hero Banner + Stats Bar
     Modelled on Physics Wallah homepage layout:
     • Top: Gradient hero — left text + right circular people + chat bubbles
     • Bottom: Flat white stats bar (4 items with vertical dividers)
     ============================================================ -->

<!-- ===== PART 1 : HERO BANNER ===== -->
<section class="wc-hero-section">

    <div class="wc-hero-container">

        <!-- LEFT: Headline + Subtitle + CTA -->
        <div class="wc-hero-left">
            <h2 class="wc-hero-title">
                India's <span class="wc-grad">Trusted &amp;</span><br>
                <span class="wc-grad">Affordable</span><br>
                Educational Platform
            </h2>
            <p class="wc-hero-sub">
                Unlock your potential by enrolling with MG Education &amp; Social Development Organization — transforming lives through quality education and community growth.
            </p>
            <a href="#" class="wc-hero-btn">
                <i class="fa-solid fa-rocket"></i> Get Started
            </a>
        </div>

        <!-- RIGHT: Single Hero Image/Graphics -->
        <div class="wc-hero-right">
            <img src="https://static.pw.live/5eb393ee95fab7468a79d189/f39fbcd9-1769-40f9-b2d6-1965c294ca68.png" alt="Educational Illustration" class="wc-hero-img">
        </div>

    </div><!-- /wc-hero-container -->
</section>

<!-- ===== PART 2 : STATS BAR ===== -->
<section class="wc-bar-section">
    <div class="wc-bar-container">

        <!-- Item 1: Daily Live Classes -->
        <div class="wc-bar-item">
            <div class="wc-bar-icon">
                <div class="wc-icon-live">
                    <i class="fa-solid fa-video"></i>
                    <span class="wc-live-label">LIVE</span>
                </div>
            </div>
            <div class="wc-bar-text">
                <div class="wc-bar-stat">Daily Live</div>
                <div class="wc-bar-sub">Interactive classes</div>
            </div>
        </div>

        <div class="wc-bar-divider"></div>

        <!-- Item 2: Study Materials -->
        <div class="wc-bar-item">
            <div class="wc-bar-icon">
                <div class="wc-icon-books">
                    <i class="fa-solid fa-book-open-reader"></i>
                </div>
            </div>
            <div class="wc-bar-text">
                <div class="wc-bar-stat">
                    <span class="wc-counter" data-target="50000" data-format="short">50,000+</span>
                </div>
                <div class="wc-bar-sub">Tests, sample papers &amp; notes</div>
            </div>
        </div>

        <div class="wc-bar-divider"></div>

        <!-- Item 3: Doubt Sessions -->
        <div class="wc-bar-item">
            <div class="wc-bar-icon">
                <div class="wc-icon-doubt">
                    <i class="fa-solid fa-circle-question"></i>
                </div>
            </div>
            <div class="wc-bar-text">
                <div class="wc-bar-stat">24 x 7</div>
                <div class="wc-bar-sub">Doubt solving sessions</div>
            </div>
        </div>

        <div class="wc-bar-divider"></div>

        <!-- Item 4: Study Centres -->
        <div class="wc-bar-item">
            <div class="wc-bar-icon">
                <div class="wc-icon-centres">
                    <i class="fa-solid fa-medal"></i>
                </div>
            </div>
            <div class="wc-bar-text">
                <div class="wc-bar-stat">
                    <span class="wc-counter" data-target="15" data-format="plus">15+</span>
                </div>
                <div class="wc-bar-sub">Study &amp; training centres</div>
            </div>
        </div>

    </div><!-- /wc-bar-container -->
</section>

<!-- Counter Animation Script -->
<script>
(function () {
    function formatNumber(n, format) {
        if (format === 'short' && n >= 1000) return Math.floor(n / 1000) + ',000 +';
        if (format === 'plus') return n + ' +';
        return n;
    }

    function animateCounter(el) {
        const target    = parseInt(el.dataset.target, 10);
        const format    = el.dataset.format || '';
        const duration  = 1600;
        const stepTime  = 16;
        const steps     = Math.ceil(duration / stepTime);
        let   current   = 0;
        const increment = target / steps;

        const timer = setInterval(() => {
            current += increment;
            if (current >= target) { current = target; clearInterval(timer); }
            el.textContent = formatNumber(Math.floor(current), format);
        }, stepTime);
    }

    const counters = document.querySelectorAll('.wc-counter');
    if ('IntersectionObserver' in window) {
        const obs = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) { animateCounter(entry.target); obs.unobserve(entry.target); }
            });
        }, { threshold: 0.5 });
        counters.forEach(el => obs.observe(el));
    } else {
        counters.forEach(el => animateCounter(el));
    }
})();
</script>
