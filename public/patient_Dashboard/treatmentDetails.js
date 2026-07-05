// متغير عالمي لحفظ بيانات المريضة بعد جلبها
// متغير عالمي لحفظ معرف المريضة
let currentPatientId = null;

async function loadPatientProfile() {
    try {
        const response = await fetch('/patient/profile'); 
        
        if (!response.ok) {
            console.warn('لم يتم التعرف على المريضة أو الجلسة منتهية');
            return;
        }

        const result = await response.json();
        
        if (result.status === 'success' && result.data) {
            currentPatientId = result.data.id; 
            
            const loggedInNameElem = document.getElementById('logged-in-patient-name');
            if (loggedInNameElem && result.data.user) {
                loggedInNameElem.innerText = result.data.user.name;
            }
        }
    } catch (error) {
        console.error('حدث خطأ أثناء جلب بيانات المريضة:', error);
    }
}

// 🌟 دالة جلب كل الطبيبات بشكل عام وملء الـ Select
async function loadDoctors() {
    const doctorSelect = document.getElementById('doctor-select');
    if (!doctorSelect) return;

    try {
        const response = await fetch('http://127.0.0.1:8000/doctors', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }
        });

        const result = await response.json();

        if (!response.ok) {
            throw new Error(result.message || 'فشل في جلب قائمة الطبيبات');
        }

        if (result.status && result.data) {
            doctorSelect.innerHTML = '<option value="" disabled selected>اختر الطبيبة المتخصصة...</option>';

            result.data.forEach(doctor => {
                const option = document.createElement('option');
                option.value = doctor.id;
                option.textContent = `د. ${doctor.name}`;
                doctorSelect.appendChild(option);
            });
        }
    } catch (error) {
        console.error('حدث خطأ أثناء تحميل قائمة الطبيبات:', error);
        doctorSelect.innerHTML = '<option value="" disabled selected>خطأ في تحميل الطبيبات...</option>';
    }
}

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

        const bookNowBtn = document.querySelector('.book-now-btn');
        if (bookNowBtn) {
            bookNowBtn.dataset.treatmentId = treatment.id;
            bookNowBtn.dataset.roomId = treatment.room_id || "1"; 
        }

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
        
        loadRatingTreatment(treatmentId);

    } catch (error) {
        console.error('حدث خطأ أثناء تحميل تفاصيل الخدمة:', error);
    }
}

async function loadRatingTreatment(treatmentId){
    try {
        const response = await fetch(`/ratings/${treatmentId}`);
        if (!response.ok) throw new Error('فشل في جلب تقييمات الخدمة');

        const rating = await response.json();
        const ratingContainer = document.getElementById('reviews-grid');
        ratingContainer.innerHTML = ''; 
        
        const treatmentName = rating.treatment_name;
        const avgRatingContainer = document.getElementById('average_rating');
        avgRatingContainer.innerHTML = `<h5>متوسط التقييم: 5 / <span>${rating.average_rating || 0}</span></h5>`;

        if (rating.ratings && rating.ratings.length > 0) {
            rating.ratings.forEach((element)=>{
                let starts = ``;
                let counter = element.stars_number;
                while(counter > 0){
                    starts += `<svg class="star-icon" viewBox="0 0 24 24" width="24" height="24" fill="#FFD700"><path d="M12 .587l3.668 7.431 8.2 1.192-5.934 5.787 1.4 8.168L12 18.896l-7.334 3.857 1.4-8.168L.132 9.21l8.2-1.192z"/></svg>`;
                    counter--;
                }
                ratingContainer.innerHTML += `<div class="review-card">
                    <div class="review-header">
                        <div class="client-info">
                            <span class="client-avatar">${element.user.name[0]}</span>
                            <div>
                                <h4 class="client-name">${element.user.name}</h4>
                                <span class="treatment-name">${treatmentName}</span>
                            </div>
                        </div>
                        <div class="stars">${starts}</div>
                    </div>
                    <p class="review-text">${element.comment}</p>
                </div>`;
            });
        }
    } catch (error) {
        console.error('حدث خطأ أثناء تحميل تقييمات الخدمة:', error);
    }
} 

// تشغيل جلب البيانات الأساسية عند تحميل الصفحة بالكامل
document.addEventListener('DOMContentLoaded', () => {
    loadTreatmentDetails();
    loadPatientProfile(); 
    loadDoctors(); 
});

// ----------------------------------------------------------------
// 🌟 كود التحكم بالـ Modal وإرسال الحجز الفعلي مع التنبيه بالفواتير

document.addEventListener('DOMContentLoaded', () => {
    const bookingModal = document.getElementById('bookingModal');
    const bookNowBtn = document.querySelector('.book-now-btn'); 
    const closeModalBtn = document.getElementById('closeModalBtn');
    const cancelModalBtn = document.getElementById('cancelModalBtn');
    const modalServiceName = document.getElementById('modal-service-name');
    const appointmentForm = document.getElementById('appointmentForm');

    if (bookNowBtn && bookingModal) {
        bookNowBtn.addEventListener('click', () => {
            if (!currentPatientId) {
                alert('الرجاء تسجيل الدخول أولاً لتتمكني من الحجز.');
                return;
            }
            const serviceTitle = document.querySelector('.service-title').innerText;
            if (modalServiceName) {
                modalServiceName.innerText = serviceTitle;
            }
            // إزالة أي رسالة خطأ قديمة عند فتح النافذة مجدداً
            const oldAlert = document.getElementById('invoice-alert-div');
            if (oldAlert) oldAlert.remove();

            bookingModal.classList.add('active');
        });
    }

    const closeModal = () => {
        bookingModal.classList.remove('active');
    };

    if (closeModalBtn) closeModalBtn.addEventListener('click', closeModal);
    if (cancelModalBtn) cancelModalBtn.addEventListener('click', closeModal);

    if (bookingModal) {
        bookingModal.addEventListener('click', (e) => {
            if (e.target === bookingModal) closeModal();
        });
    }

if (appointmentForm) {
    appointmentForm.addEventListener('submit', async (e) => {
        e.preventDefault(); 

        // 1. إزالة أي تنبيهات سابقة (سواء نجاح أو فشل) عند الضغط مجدداً
        const oldAlert = document.getElementById('booking-alert-div');
        if (oldAlert) oldAlert.remove();

        const treatmentId = bookNowBtn.dataset.treatmentId;
        const roomId = bookNowBtn.dataset.roomId;
        const doctorId = document.getElementById('doctor-select').value;
        const bookingDate = document.getElementById('booking-date').value;
        const bookingTime = document.getElementById('booking-time').value;
        const promoCodeValue = document.getElementById('promoCode').value.trim();

        // معالجة الوقت
        let formattedTime = bookingTime;
        if (bookingTime.split(':').length === 2) {
            formattedTime = `${bookingTime}:00`;
        }
        const fullAppointmentDate = `${bookingDate} ${formattedTime}`;

        const bookingData = {
            patient_id: currentPatientId, 
            user_id: currentPatientId, 
            doctor_id: doctorId,
            room_id: roomId,
            appointment_date: fullAppointmentDate,
            treatment_ids: [parseInt(treatmentId)]
        };

        if (promoCodeValue !== '') {
            bookingData.promo_code = promoCodeValue;
        }

        // دالة موحدة لعرض التنبيهات (أخضر للنجاح / أحمر للفشل) داخل الفورم
        const showAlertInDiv = (message, type = 'error') => {
            const alertDiv = document.createElement('div');
            alertDiv.id = 'booking-alert-div';
            
            // التنسيق العام المشترك
            alertDiv.style.padding = '12px 15px';
            alertDiv.style.marginBottom = '20px';
            alertDiv.style.borderRadius = '8px';
            alertDiv.style.fontWeight = '600';
            alertDiv.style.textAlign = 'center';
            alertDiv.style.fontSize = '14px';
            
            // تخصيص الألوان بناءً على الحالة
            if (type === 'success') {
                alertDiv.style.backgroundColor = '#f0fdf4'; // خلفية خضراء ناعمة جداً
                alertDiv.style.color = '#15803d';           // نص أخضر غامق مريح
                alertDiv.style.border = '1px solid #bbf7d0';   // حد أخضر خفيف
            } else {
                alertDiv.style.backgroundColor = '#fff5f5'; // خلفية حمراء ناعمة
                alertDiv.style.color = '#e53e3e';           // نص أحمر غامق
                alertDiv.style.border = '1px solid #fed7d7';   // حد أحمر خفيف
            }
            
            alertDiv.innerText = message;
            
            // إدراج الـ Div في أعلى النموذج فوراً
            appointmentForm.insertBefore(alertDiv, appointmentForm.firstChild);
        };

        try {
            const response = await fetch('http://127.0.0.1:8000/appointments', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(bookingData)
            });

            const result = await response.json();

            // 2. حالة الفشل (فواتير، تعارض وقت، كود منتهي...) -> عرض div أحمر
            if (!response.ok) {
                showAlertInDiv(result.message || 'عذراً، فشل في إتمام الحجز.', 'error');
                return; 
            }

            // 3. حالة النجاح التام -> عرض div أخضر متناسق وإعادة تهيئة الحقول
            showAlertInDiv('✨ ' + result.message, 'success');
            appointmentForm.reset(); 

            // (اختياري) إغلاق المودال تلقائياً بعد ثانيتين لكي يرى المستخدم رسالة النجاح أولاً
            setTimeout(() => {
                closeModal();
                const successAlert = document.getElementById('booking-alert-div');
                if (successAlert) successAlert.remove(); // تنظيف الرسالة بعد الإغلاق
            }, 2500);

        } catch (error) {
            // 4. حالة خطأ الشبكة أو السيرفر -> عرض div أحمر
            console.error(error);
            showAlertInDiv('❌ تعذر الاتصال بالسيرفر، يرجى التحقق من الشبكة.', 'error');
        }
    });
}
});