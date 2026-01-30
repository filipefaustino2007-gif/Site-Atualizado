document.addEventListener('DOMContentLoaded', () => {
    // === Seleciona todas as imagens clicáveis ===
    const thumbs = Array.from(document.querySelectorAll('.gallery-item'));
    if (!thumbs.length) return;

    // === Elementos principais ===
    const popup = document.getElementById('imagePopup');
    const popupImg = document.getElementById('popupImage');
    const popupCaption = document.getElementById('popupCaption');
    const closeBtn = popup.querySelector('.close-btn');
    const prevBtn = popup.querySelector('.nav-btn.prev');
    const nextBtn = popup.querySelector('.nav-btn.next');

    let currentIndex = 0;
    const TOTAL_FOR_CAPTION = thumbs.length;

    const sources = thumbs.map(img => img.dataset.full || img.src);

    // === Helpers ===
    const lockScroll = () => document.body.classList.add('no-scroll');
    const unlockScroll = () => document.body.classList.remove('no-scroll');

    function updateCaption() {
        popupCaption.textContent = `item ${currentIndex + 1} of ${TOTAL_FOR_CAPTION}`;
    }

    function updateImage() {
        popupImg.src = sources[currentIndex];
        updateCaption();
    }

    function openLightbox(index) {
        currentIndex = index;
        updateImage();
        popup.classList.add('active');
        popup.classList.remove('closing');
        lockScroll();
    }

    function closeLightbox() {
        popup.classList.add('closing');
        setTimeout(() => {
            popup.classList.remove('active', 'closing');
            unlockScroll();
        }, 350);
    }

    // === Liga eventos às thumbnails ===
    thumbs.forEach((img, index) => {
        img.draggable = false;
        img.addEventListener('mousedown', e => e.preventDefault());
        img.addEventListener('click', () => openLightbox(index));
    });

    // === Navegação ===
    prevBtn.addEventListener('click', e => {
        e.stopPropagation();
        currentIndex = (currentIndex - 1 + sources.length) % sources.length;
        updateImage();
    });

    nextBtn.addEventListener('click', e => {
        e.stopPropagation();
        currentIndex = (currentIndex + 1) % sources.length;
        updateImage();
    });

    // === Fechar ===
    closeBtn.addEventListener('click', e => {
        e.stopPropagation();
        closeLightbox();
    });

    // Não fecha ao clicar fora
    popup.addEventListener('click', e => {
        if (e.target === popup) e.stopPropagation();
    });

    // Fecha com Esc
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && popup.classList.contains('active')) closeLightbox();
    });

    // Evita arrasto/seleção na imagem grande
    popupImg.draggable = false;
    popupImg.addEventListener('mousedown', e => e.preventDefault());
});