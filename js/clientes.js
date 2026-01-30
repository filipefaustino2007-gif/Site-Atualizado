document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.row-click').forEach(row => {
        row.addEventListener('click', () => {
            const url = row.dataset.href;
            if (url) window.location.href = url;
        });
    });
});