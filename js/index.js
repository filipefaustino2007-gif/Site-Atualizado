// Slideshow automático sincronizado com zoom + botões manuais + bloqueio de cliques
let currentSlide = 0;
let isAnimating = false;
let slideInterval;

document.addEventListener("DOMContentLoaded", () => {
    const slides = document.querySelectorAll(".slide");
    const prevBtn = document.querySelector(".prev");
    const nextBtn = document.querySelector(".next");
    const transitionTime = 1000; // ms (igual à duração da animação CSS)
    const slideDelay = 7000; // intervalo entre slides

    // Mostra o primeiro slide
    slides[currentSlide].classList.add("active");

    // Função que limpa classes de transição
    function resetClasses() {
        slides.forEach(slide => {
            slide.classList.remove(
                "slide-in-right",
                "slide-out-left",
                "slide-in-left",
                "slide-out-right",
                "active"
            );
        });
    }

    // Função principal para mostrar slide
    function showSlide(nextIndex, direction) {
        if (isAnimating) return; // evita múltiplos cliques
        isAnimating = true;

        const totalSlides = slides.length;
        const next = (nextIndex + totalSlides) % totalSlides;

        const current = slides[currentSlide];
        const upcoming = slides[next];

        const outClass = direction === "next" ? "slide-out-left" : "slide-out-right";
        const inClass = direction === "next" ? "slide-in-right" : "slide-in-left";

        resetClasses(); // limpa tudo antes da transição
        current.classList.add(outClass);
        upcoming.classList.add(inClass);

        // Espera até o final da animação
        setTimeout(() => {
            resetClasses();
            upcoming.classList.add("active");
            currentSlide = next;
            isAnimating = false;
        }, transitionTime);
    }

    function nextSlide() {
        showSlide(currentSlide + 1, "next");
    }

    function prevSlide() {
        showSlide(currentSlide - 1, "prev");
    }

    // Controlador de intervalo automático
    function startAutoSlide() {
        clearInterval(slideInterval);
        slideInterval = setInterval(() => {
            if (!isAnimating) nextSlide();
        }, slideDelay);
    }

    // Inicia slideshow automático
    startAutoSlide();

    // Botões de navegação
    nextBtn.addEventListener("click", () => {
        clearInterval(slideInterval);
        nextSlide();
        startAutoSlide();
    });

    prevBtn.addEventListener("click", () => {
        clearInterval(slideInterval);
        prevSlide();
        startAutoSlide();
    });
});

// ==== DRAG-TO-SLIDE PARA O SLIDESHOW DO TOPO ====
(() => {
    const container = document.querySelector('.slideshow-container');
    if (!container) return;

    const prevBtn = container.querySelector('.prev');
    const nextBtn = container.querySelector('.next');

    let isDown = false;
    let startX = 0;

    container.addEventListener('mousedown', (e) => {
        isDown = true;
        startX = e.clientX;
    });

    window.addEventListener('mouseup', (e) => {
        if (!isDown) return;
        isDown = false;

        const diff = e.clientX - startX;
        const threshold = 60; // mínimo de arrasto para contar como swipe

        if (diff > threshold && prevBtn) {
            prevBtn.click();      // arrastou para a direita → slide anterior
        } else if (diff < -threshold && nextBtn) {
            nextBtn.click();      // arrastou para a esquerda → slide seguinte
        }
    });

    container.addEventListener('mouseleave', () => {
        isDown = false;
    });
})();

// Carousel portfolio — autoplay 3s, 4 centrais em destaque, SEM destaque durante drag
(() => {
    const carousel = document.querySelector('.portfolio-strip-carousel');
    const track = document.querySelector('.portfolio-strip-track');
    if (!carousel || !track) return;

    const items = Array.from(track.querySelectorAll('.portfolio-item'));
    if (items.length === 0) return;

    const gap = 28; // gap definido no CSS
    let isDragging = false;
    let isUserInteracting = false;
    let autoInterval = null;
    let resumeTimeout = null;

    function getItemWidth() {
        const rect = items[0].getBoundingClientRect();
        return Math.round(rect.width) + gap;
    }

    // === 4 IMAGENS MAIS PERTO DO CENTRO (só quando NÃO está a arrastar) ===
    function updateCenterItems() {
        if (isDragging) return; // NADA em destaque enquanto drag

        const carouselRect = carousel.getBoundingClientRect();
        const centerX = (carouselRect.left + carouselRect.right) / 2;

        items.forEach(el => el.classList.remove('is-center'));

        const sorted = items
            .map(el => {
                const rect = el.getBoundingClientRect();
                const itemCenter = (rect.left + rect.right) / 2;
                return { el, dist: Math.abs(itemCenter - centerX) };
            })
            .sort((a, b) => a.dist - b.dist);

        sorted.slice(0, 4).forEach(obj => obj.el.classList.add('is-center'));
    }

    // === AUTOPLAY CADA 3s ===
    function autoStep() {
        const slideWidth = getItemWidth();

        track.style.transition = 'transform 1.2s ease-in-out';
        track.style.transform = `translateX(-${slideWidth}px)`;

        setTimeout(() => {
            // reorganiza DOM (efeito "carrossel infinito")
            track.appendChild(track.firstElementChild);
            track.style.transition = 'none';
            track.style.transform = 'translateX(0)';
            void track.offsetWidth; // reflow
            track.style.transition = 'transform 1.2s ease-in-out';

            updateCenterItems();
        }, 1200);
    }

    function startAuto() {
        stopAuto();
        autoInterval = setInterval(() => {
            if (!isUserInteracting && !isDragging) {
                autoStep();
            }
        }, 3000); // 3 segundos
    }

    function stopAuto() {
        if (autoInterval) {
            clearInterval(autoInterval);
            autoInterval = null;
        }
        if (resumeTimeout) {
            clearTimeout(resumeTimeout);
            resumeTimeout = null;
        }
    }

    function pauseAutoForInteraction() {
        stopAuto();
        isUserInteracting = true;
        resumeTimeout = setTimeout(() => {
            isUserInteracting = false;
            startAuto();
        }, 3000);
    }

    // === DRAG / TOUCH ===
    let startX = 0;
    let startTranslate = 0;

    function getCurrentTranslateX() {
        const style = window.getComputedStyle(track);
        const transform = style.transform;
        if (!transform || transform === 'none') return 0;
        const matrix = new DOMMatrixReadOnly(transform);
        return matrix.m41;
    }

    function getLimits() {
        const maxRight = 0;
        const maxLeft = carousel.offsetWidth - track.scrollWidth - 1;
        return { maxLeft, maxRight };
    }

    function onDragStart(clientX) {
        isDragging = true;
        startX = clientX;
        startTranslate = getCurrentTranslateX();

        // sem transições e sem destaque enquanto arrasta
        track.style.transition = 'none';
        items.forEach(el => el.classList.remove('is-center'));

        carousel.classList.add('dragging');
        pauseAutoForInteraction();
    }

    function onDragMove(clientX) {
        if (!isDragging) return;

        const delta = clientX - startX;
        const slowDelta = delta * 0.6; // <-- MAIS DEVAGAR / MAIS "PESADO"
        let next = startTranslate + slowDelta;

        const { maxLeft, maxRight } = getLimits();
        if (next > maxRight) next = maxRight;
        if (next < maxLeft) next = maxLeft;

        track.style.transform = `translateX(${next}px)`;
        // NÃO chamamos updateCenterItems aqui → ZERO destaque no drag
    }

    function onDragEnd() {
        if (!isDragging) return;
        isDragging = false;
        carousel.classList.remove('dragging');

        const slideW = getItemWidth();
        let current = getCurrentTranslateX();
        const snapped = Math.round(current / slideW) * slideW;

        // snap suave
        track.style.transition = 'transform 650ms cubic-bezier(.2,.8,.2,1)';
        track.style.transform = `translateX(${snapped}px)`;

        // depois do snap → calcula as 4 centrais com base na posição atual
        setTimeout(() => {
            updateCenterItems();
            track.style.transition = 'transform 1.2s ease-in-out';
        }, 700);
    }

    // MOUSE
    carousel.addEventListener('mousedown', (e) => {
        e.preventDefault();
        onDragStart(e.clientX);
    });

    window.addEventListener('mousemove', (e) => {
        if (!isDragging) return;
        e.preventDefault();
        onDragMove(e.clientX);
    });

    window.addEventListener('mouseup', () => {
        if (!isDragging) return;
        onDragEnd();
    });

    carousel.addEventListener('mouseleave', () => {
        if (isDragging) onDragEnd();
    });

    // TOUCH
    carousel.addEventListener('touchstart', (e) => {
        if (e.touches.length > 1) return;
        onDragStart(e.touches[0].clientX);
    }, { passive: true });

    carousel.addEventListener('touchmove', (e) => {
        if (!isDragging || e.touches.length > 1) return;
        onDragMove(e.touches[0].clientX);
    }, { passive: false });

    carousel.addEventListener('touchend', () => {
        if (!isDragging) return;
        onDragEnd();
    });

    // Inicializa
    updateCenterItems();
    startAuto();

    window.addEventListener('resize', () => {
        updateCenterItems();
    });
})();



