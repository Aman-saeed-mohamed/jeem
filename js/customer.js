document.addEventListener('DOMContentLoaded', () => {
    // Theme toggle functionality
    const themeToggleBtn = document.getElementById('theme-toggle');
    const root = document.documentElement;
    
    // Check for saved theme preference
    const savedTheme = localStorage.getItem('theme') || 'light';
    root.setAttribute('data-theme', savedTheme);
    updateThemeIcon(savedTheme);

    if (themeToggleBtn) {
        themeToggleBtn.addEventListener('click', () => {
            const currentTheme = root.getAttribute('data-theme');
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            
            root.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            updateThemeIcon(newTheme);
        });
    }

    function updateThemeIcon(theme) {
        if (!themeToggleBtn) return;
        if (theme === 'dark') {
            themeToggleBtn.innerHTML = '☀️';
        } else {
            themeToggleBtn.innerHTML = '🌙';
        }
    }

    // Set active nav link based on current page (.php pages)
    const currentPath = window.location.pathname.split('/').pop();
    const navLinks = document.querySelectorAll('.nav-link');

    navLinks.forEach(link => {
        const href = link.getAttribute('href');
        // Match exact page name or home fallback
        const isHome = (currentPath === '' || currentPath === 'customer_dashboard.php');
        if (href === currentPath || (isHome && href === 'customer_dashboard.php')) {
            link.classList.add('active');
        } else {
            link.classList.remove('active');
        }
    });

    // Carousel Functionality
    const carousel = document.querySelector('#carouselExample');
    if (carousel) {
        const inner = carousel.querySelector('.carousel-inner');
        const items = carousel.querySelectorAll('.carousel-item');
        const nextBtn = carousel.querySelector('.carousel-control-next');
        const prevBtn = carousel.querySelector('.carousel-control-prev');
        let currentIndex = 0;

        function showSlide(index) {
            if (index >= items.length) currentIndex = 0;
            else if (index < 0) currentIndex = items.length - 1;
            else currentIndex = index;
            
            inner.style.transform = `translateX(-${currentIndex * 100}%)`;
        }

        if(nextBtn) nextBtn.addEventListener('click', () => showSlide(currentIndex + 1));
        if(prevBtn) prevBtn.addEventListener('click', () => showSlide(currentIndex - 1));

        // Auto slide every 5 seconds
        setInterval(() => showSlide(currentIndex + 1), 5000);
    }
});
