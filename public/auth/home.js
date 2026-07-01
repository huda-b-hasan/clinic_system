function initScrollSpy() {
    const sections = document.querySelectorAll('section[id]');
    const navLinks = document.querySelectorAll('.nav-link');

    const observerOptions = {
        root: null,
        rootMargin: '-20% 0px -40% 0px', 
        threshold: 0
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const currentId = entry.target.getAttribute('id');
                
                navLinks.forEach(link => {
                    if (link.getAttribute('href') === `#${currentId}`) {
                        link.classList.add('active');
                    } else {
                        link.classList.remove('active');
                    }
                });
            }
        });
    }, observerOptions);

    sections.forEach(section => observer.observe(section));
}

document.addEventListener('DOMContentLoaded', () => {
    if (typeof loadTreatments === 'function') loadTreatments();
    initScrollSpy();
});
// get the data of treatments 
async function loadTreatments() {
    const container = document.getElementById('treatments-container');
    if (!container) return;

    try {
        const response = await fetch('/treatments');
        if (!response.ok) throw new Error('فشل في جلب البيانات من السيرفر');
        
        const treatments = await response.json();
        
        container.innerHTML = ''; 

        treatments.forEach(treatment => {
            
            const basePrice = parseFloat(treatment.base_price).toFixed(0);
            const discountPrice = treatment.discount_price ? parseFloat(treatment.discount_price).toFixed(0) : null;

            let priceHTML = '';
            if (discountPrice && parseFloat(discountPrice) < parseFloat(basePrice)) {
                priceHTML = 
`                    <span class="price-old">${basePrice} $</span>
                    <span class="price-value price-discount">${discountPrice} $</span>`
                ;
            } else {
                priceHTML = 
`                    <span class="price-label">يبدأ من</span>
                    <span class="price-value">${basePrice} $</span>`
                ;
            }

            const fallbackSVG = "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='400' height='200' viewBox='0 0 400 200'><rect width='100%25' height='100%25' fill='%23fcf9f6'/><text x='50%25' y='50%25' dominant-baseline='middle' text-anchor='middle' fill='%239c89b8' font-family='sans-serif' font-size='15' font-weight='600'>Lavender Beauty Clinic</text></svg>";

            const imagePath = treatment.image.startsWith('/') ? treatment.image : `/${treatment.image}`;

            const card = document.createElement('div');
            card.className = 'treatment-card';
            
            card.innerHTML = 
`                <div class="treatment-img-wrapper">
                    <img src="${imagePath}" alt="${treatment.name}" class="treatment-img" 
                         onerror="this.onerror=null; this.src='${fallbackSVG}';">
                </div>
                <div class="treatment-content">
                    <h3 class="treatment-name">${treatment.name}</h3>
                    
                    <span class="treatment-feature">
                        <i class="far fa-clock"></i> ${treatment.duration || 30} دقيقة
                    </span>
                    
                    <p class="treatment-text">${treatment.description || 'لا يوجد وصف متاح حالياً لهذه الخدمة.'}</p>
                    
                    <div class="treatment-footer">

                        <div class="price-box">
                            ${priceHTML}
                        </div>
                    </div>
                </div>`
            ;
            container.appendChild(card);
        });

    } catch (error) {
        console.error('حدث خطأ أثناء تحميل الخدمات:', error);
        container.innerHTML = 
`            <div class="error-wrapper">
                <p class="error-message">نواجه عطلاً مؤقتاً في تحميل الخدمات، يرجى التحقق من اتصال السيرفر.</p>
            </div>`
        ;
    }
}

