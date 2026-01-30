document.addEventListener('DOMContentLoaded', () => {
    // Seleciona só as imagens deste serviço
    const thumbs = Array.from(document.querySelectorAll('.gallery-item'));
    if (!thumbs.length) return;

    const popup = document.getElementById('imagePopup');
    const popupImg = document.getElementById('popupImage');
    const popupCaption = document.getElementById('popupCaption');
    const closeBtn = popup.querySelector('.close-btn');
    const prevBtn = popup.querySelector('.nav-btn.prev');
    const nextBtn = popup.querySelector('.nav-btn.next');

    let currentIndex = 0;
    const TOTAL = thumbs.length;
    const sources = thumbs.map(img => img.dataset.full || img.src);

    const lockScroll = () => document.body.classList.add('no-scroll');
    const unlockScroll = () => document.body.classList.remove('no-scroll');

    function updateCaption() {
        popupCaption.textContent = `item ${currentIndex + 1} de ${TOTAL}`;
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

    thumbs.forEach((img, index) => {
        img.draggable = false;
        img.addEventListener('mousedown', e => e.preventDefault());
        img.addEventListener('click', () => openLightbox(index));
    });

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

    closeBtn.addEventListener('click', e => {
        e.stopPropagation();
        closeLightbox();
    });

    popup.addEventListener('click', e => {
        if (e.target === popup) e.stopPropagation();
    });

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && popup.classList.contains('active')) {
            closeLightbox();
        }
    });

    popupImg.draggable = false;
    popupImg.addEventListener('mousedown', e => e.preventDefault());
});