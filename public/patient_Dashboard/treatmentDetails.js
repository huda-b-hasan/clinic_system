async function loadTreatmentDetails() {
    const urlParams = new URLSearchParams(window.location.search);
    const treatmentId = urlParams.get('id');

    if (!treatmentId) {
        console.error('لم يتم العثور على معرف الخدمة في الرابط');
        return;
    }

    try {

        const response = await fetch(`/treatments/${treatmentId}`);
        if (!response.ok) throw new Error('فشل في جلب تفاصيل الخدمة');

        const treatment = await response.json();

        document.querySelector('.service-title').innerText = treatment.name;
        document.querySelector('.service-description').innerText = treatment.description || 'لا يوجد وصف متاح.';

        document.querySelector('.duration-badge').innerHTML = `🕒 ${treatment.duration || 30} دقيقة`;


        const priceNumContainer = document.querySelector('.price-num');
        const priceOldElement = document.querySelector('.price-old-detail');
        const priceStatusLabel = document.getElementById('price-status-label');

        const basePrice = parseFloat(treatment.base_price).toFixed(0);
        const discountPrice = treatment.discount_price ? parseFloat(treatment.discount_price).toFixed(0) : null;

        if (discountPrice && parseFloat(discountPrice) < parseFloat(basePrice)) {
            priceStatusLabel.innerText = "عرض خاص";
            priceStatusLabel.style.color = "#d9534f"; 
            priceOldElement.innerText = `${basePrice}  ل.س`;
            priceOldElement.style.display = "block"; 

            priceNumContainer.innerText = discountPrice; 
        } else {
            priceStatusLabel.innerText = "السعر";
            priceOldElement.style.display = "none"; 

            priceNumContainer.innerText = basePrice;
        }
        const mainImg = document.querySelector('.service-main-img');
        if (mainImg) {
            const imagePath = treatment.image.startsWith('/') ? treatment.image : `/${treatment.image}`;
            mainImg.src = imagePath;
            mainImg.onerror = function () {
                this.onerror = null;
                this.src = "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='400' height='200' viewBox='0 0 400 200'><rect width='100%25' height='100%25' fill='%23fcf9f6'/><text x='50%25' y='50%25' dominant-baseline='middle' text-anchor='middle' fill='%239c89b8' font-family='sans-serif' font-size='15' font-weight='600'>Lavender Beauty Clinic</text></svg>";
            };
        }

        const featuresList = document.querySelector('.features-list');
        if (featuresList) {
            featuresList.innerHTML = ''; 

            if (treatment.features && treatment.features.length > 0) {
                treatment.features.forEach(feature => {
                    const li = document.createElement('li');
                    li.innerHTML = `<span class="check-icon">✓</span> ${feature}`;
                    featuresList.appendChild(li);
                });
            } else {
                featuresList.innerHTML = '<li><span class="check-icon">✓</span> استشارة مخصصة قبل البدء</li>';
            }
        }
        loadRatingTreatment(treatmentId)

    } catch (error) {
        console.error('حدث خطأ أثناء تحميل تفاصيل الخدمة:', error);
    }
}
async function loadRatingTreatment(treatmentId){
        try {
        const response = await fetch(`http://127.0.0.1:8000/ratings/${treatmentId}`);
        if (!response.ok) throw new Error('فشل في جلب تقييمات الخدمة');

    
        const rating = await response.json();
        const ratingContainer=document.getElementById('reviews-grid');
        const treatmentName= rating.treatment_name;
        console.log(rating.average_rating)
        const average_rating=document.getElementById('average_rating').innerHTML+=`  5 /<span>${rating.average_rating}</span>`
        rating.ratings.forEach((element)=>{
            let starts=``;
            let counter =element.stars_number;
            while(counter>0){
                starts+=`<svg class="star-icon" viewBox="0 0 24 24" width="24" height="24" fill="#FFD700">
                <path d="M12 .587l3.668 7.431 8.2 1.192-5.934 5.787 1.4 8.168L12 18.896l-7.334 3.857 1.4-8.168L.132 9.21l8.2-1.192z"/>
                </svg>`
               counter--;
            }
            ratingContainer.innerHTML +=`<div class="review-card">
                <div class="review-header">
                    <div class="client-info">
                        <span class="client-avatar">${element.user.name[0]}</span>
                        <div>
                            <h4 class="client-name">${element.user.name}</h4>
                            <span class="treatment-name">${treatmentName}</span>
                        </div>

                    </div>
                    <div class="stars">
                    ${starts}
                    </div>
                </div>
                <p class="review-text">
                    ${element.comment}
                </p>
            </div>
            `;
        })

        


    } catch (error) {
        console.error('حدث خطأ أثناء تحميل تقييمات الخدمة:', error);
    }

} 
document.addEventListener('DOMContentLoaded', loadTreatmentDetails);

// ----------------------------------------------------------------
// 🌟 كود التحكم بالـ Modal (فتح وإغلاق فورم الحجز)

document.addEventListener('DOMContentLoaded', () => {
    const bookingModal = document.getElementById('bookingModal');
    const bookNowBtn = document.querySelector('.book-now-btn'); // زر احجزي الآن في صفحتك
    const closeModalBtn = document.getElementById('closeModalBtn');
    const cancelModalBtn = document.getElementById('cancelModalBtn');
    const modalServiceName = document.getElementById('modal-service-name');
    const appointmentForm = document.getElementById('appointmentForm');

    // 1. فتح النافذة عند الضغط على "احجزي الآن"
    if (bookNowBtn && bookingModal) {
        bookNowBtn.addEventListener('click', () => {
            // جلب اسم الخدمة الحالي من الصفحة وضعه في عنوان الفورم
            const serviceTitle = document.querySelector('.service-title').innerText;
            if (modalServiceName) {
                modalServiceName.innerText = serviceTitle;
            }
            
            // إظهار النافذة عبر إضافة كلاس active
            bookingModal.classList.add('active');
        });
    }

    // دالة مرنة لإغلاق النافذة
    const closeModal = () => {
        bookingModal.classList.remove('active');
    };

    // 2. إغلاق عند الضغط على زر X
    if (closeModalBtn) closeModalBtn.addEventListener('click', closeModal);

    // 3. إغلاق عند الضغط على زر إلغاء
    if (cancelModalBtn) cancelModalBtn.addEventListener('click', closeModal);

    // 4. إغلاق عند الضغط في أي مكان خارج كرت الفورم (على الخلفية المعتمة)
    if (bookingModal) {
        bookingModal.addEventListener('click', (e) => {
            if (e.target === bookingModal) {
                closeModal();
            }
        });
    }

    // 5. التعامل مع إرسال الفورم (Submit)
    if (appointmentForm) {
        appointmentForm.addEventListener('submit', (e) => {
            e.preventDefault(); // منع الصفحة من إعادة التحميل

            // هنا بتقدري تجهزي تجميع البيانات لتبعتيها للباك إند لاحقاً
            const bookingData = {
                doctor_id: document.getElementById('doctor-select').value,
                date: document.getElementById('booking-date').value,
                time: document.getElementById('booking-time').value,
                notes: document.getElementById('booking-notes').value
            };

            console.log('بيانات الحجز الجاهزة للإرسال:', bookingData);
            
            alert('تم تسجيل طلب حجزكِ المبدئي بنجاح!');
            closeModal(); // إغلاق الفورم بعد التجميع
            appointmentForm.reset(); // تفريغ الحقول لمرة قادمة
        });
    }
});