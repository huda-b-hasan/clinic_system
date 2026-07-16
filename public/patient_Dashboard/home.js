document.addEventListener('DOMContentLoaded', async () => {
    try {
        const response = await fetch('/patient/profile', {
            method: 'GET',
            headers: {
                'Accept': 'application/json'
            }
        });

        const result = await response.json();

        if (response.ok && result.status === 'success') {
            // console.log('بيانات المريض بالكامل:', result.data);
            // set name in html
            document.getElementById('patientName').innerHTML = result.data.user.name;
            // أمثلة لكيفية عرض البيانات داخل عناصر الـ HTML عندكِ
            // document.getElementById('patient-name').textContent = result.data.user.name;
            // document.getElementById('patient-email').textContent = result.data.user.email;
            // document.getElementById('patient-phone').textContent = result.data.phone;
        } else {
            alert(result.message);
            window.location.href = '/login';
        }
    } catch (error) {
        console.error('حدث خطأ أثناء جلب البيانات:', error);
    }
});
// show the data like appintment session bill
// home.js

document.addEventListener("DOMContentLoaded", () => {
    // استدعاء دالة جلب البيانات بمجرد تحميل الصفحة
    fetchDashboardData();
});

async function fetchDashboardData() {
    try {
        // ضع المسار الصحيح للـ API الخاص بك هنا
        const response = await fetch('/patient/dashboard-data', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
                // إذا كنت تستخدم توكن للتصديق مرره هنا، لكن الباك إند يعتمد على الـ Session حالياً
            }
        });

        if (response.status === 403) {
            // الجلسة منتهية - تحويل المستخدم لصفحة تسجيل الدخول
            window.location.href = "../auth/login.html";
            return;
        }

        const resData = await response.json();
        console.log(resData)

        if (resData.status === 'success') {
            renderDashboard(resData);
        } else {
            console.error("فشل في جلب البيانات:", resData.message);
        }

    } catch (error) {
        console.error("حدث خطأ أثناء الاتصال بالخادم:", error);
    }
}





let appointmentIdToCancel = null;

function renderDashboard(resData) {
    const stats = resData.stats;
    const data = resData.data;

    // 2. تحديث عدادات بطاقات الإحصائيات
    document.getElementById('futureSession').textContent = `${stats.pending_appointments_count}  `;
    document.getElementById('lastSession').textContent = stats.completed_sessions_count;

    // 3. تحديث قائمة المواعيد القادمة ديناميكياً
    const upcomingContainer = document.querySelector('.upcoming-appointments');
    upcomingContainer.innerHTML = ''; 

    if (data.pending_appointments && data.pending_appointments.length > 0) {
        data.pending_appointments.forEach(appointment => {
            const appDate = new Date(appointment.appointment_date);
            const dateString = appDate.toISOString().split('T')[0]; 
            const timeString = appDate.toLocaleTimeString('ar-EG', { hour: '2-digit', minute: '2-digit', hour12: true });

            const today = new Date();
            const diffTime = appDate - today;
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

            let statusBadgeText = '';
            let badgeClass = 'normal';

            if (diffDays === 0) {
                statusBadgeText = 'اليوم';
                badgeClass = 'urgent';
            } else if (diffDays === 1) {
                statusBadgeText = 'غداً';
                badgeClass = 'urgent';
            } else if (diffDays > 1 && diffDays <= 3) {
                statusBadgeText = `متبقي ${diffDays} أيام`;
                badgeClass = 'urgent';
            } else if (diffDays > 3) {
                statusBadgeText = `متبقي ${diffDays} يوم`;
                badgeClass = 'normal';
            } else {
                statusBadgeText = 'لم تأت ';
            }

            const treatmentName = appointment.treatments && appointment.treatments.length > 0
                ? appointment.treatments[0].name
                : 'جلسة علاجية';
            const doctorName = appointment.doctor ? appointment.doctor.name : 'غير محدد';

            // تعديل هنا: تم إضافة data-id لزر الإلغاء وتغيير الـ id إلى class لأن الـ id يجب أن يكون فريداً
            const itemHtml = `
                <div class="appointment-item">
                    <div class="appointment-info">
                        <span class="service-name">${treatmentName}</span>
                        <span class="doctor">د. ${doctorName}</span>
                    </div>
                    <div class="appointment-date">
                        <span>${dateString}</span>
                        <span class="oclockSession">${timeString}</span>
                    </div>
                    <div class="appointment-status">
                        <span class="status-badge ${badgeClass}">${statusBadgeText}</span>
                        <button class="cancel-btn" data-id="${appointment.id}" type="button">إلغاء</button>
                    </div>
                </div>
            `;
            upcomingContainer.insertAdjacentHTML('beforeend', itemHtml);
        });

        // ربط أزرار الإلغاء بفتح النافذة المنبثقة
        document.querySelectorAll('.cancel-btn').forEach(button => {
            button.addEventListener('click', function() {
                appointmentIdToCancel = this.getAttribute('data-id');
                document.getElementById('cancelModal').style.display = 'flex';
            });
        });

    } else {
        upcomingContainer.innerHTML = '<p class="no-data">لا توجد مواعيد قادمة حالياً.</p>';
    }

    // 4. تحديث قائمة الجلسات السابقة
    const noSessionTxt = document.getElementById('noSession');
    const sessionsList = document.getElementById('sessions-list');
    sessionsList.innerHTML = ''; 

    if (data.completed_appointments && data.completed_appointments.length > 0) {
        noSessionTxt.style.display = 'none'; 

        data.completed_appointments.forEach(session => {
            const sessDate = new Date(session.appointment_date).toISOString().split('T')[0];
            const treatmentName = session.treatments && session.treatments.length > 0
                ? session.treatments[0].name
                : 'علاج مخصص';

            const sessionHtml = `
                <div class="appointment-item past-item" style=" margin-bottom: 10px; padding: 10px;">
                    <div class="appointment-info">
                        <span class="service-name" style="font-weight: bold;">${treatmentName}</span>
                        <span class="doctor">الطبيب: ${session.doctor_name}</span>
                        ${session.doctor_notes ? `<small class="notes" style="color: #777; display:block; margin-top:4px;">ملاحظة: ${session.doctor_notes}</small>` : ''}
                    </div>
                    <div class="appointment-date" style="text-align: left;">
                        <span>${sessDate}</span>
                    </div>
                </div>
            `;
            sessionsList.insertAdjacentHTML('beforeend', sessionHtml);
        });
    } else {
        noSessionTxt.style.display = 'block';
    }
}

// --- منطق التحكم بالنوافذ والإشعارات ---

// إغلاق النافذة عند الضغط على "تراجع"
document.getElementById('closeModalBtn').addEventListener('click', () => {
    document.getElementById('cancelModal').style.display = 'none';
    appointmentIdToCancel = null;
});

// عند الضغط على "نعم، إلغاء"
document.getElementById('confirmCancelBtn').addEventListener('click', () => {
    if (!appointmentIdToCancel) return;

    // إرسال طلب الإلغاء للـ API في Laravel
    fetch(`/appointments/${appointmentIdToCancel}/cancel`, { // تأكدي من مسار الـ Route الخاص بك
        method: 'PUT', // أو DELETE/PUT حسب الـ Route عندك
        headers: {
            'Content-Type': 'application/json',
            // 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') // إذا كنت تستخدمين جارد الويب
        }
    })
    .then(response => response.json())
    .then(data => {
        // إخفاء نافذة التأكيد
        document.getElementById('cancelModal').style.display = 'none';

        if (data.success) {
            // 1. إظهار الإشعار الأخضر
            const toast = document.getElementById('toastNotification');
            toast.textContent = `✓ ${data.message}`;
            toast.style.display = 'block';

            // 2. إخفاء الإشعار بعد 4 ثوانٍ
            setTimeout(() => {
                toast.style.display = 'none';
            }, 4000);

            // 3. إعادة تحديث البيانات بالصفحة (أعيدي استدعاء دالة جلب البيانات هنا)
            // fetchDashboardData(); 
        } else {
            alert(data.message); // في حال حدث خطأ من الـ backend
        }
    })
    .catch(error => {
        console.error('Error:', error);
        document.getElementById('cancelModal').style.display = 'none';
    });
});
const countApiUrl = '/patient/pending-bills/count';

fetch(countApiUrl, {
    method: 'GET',
    headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json'
    }
})
    .then(response => response.json())
    .then(data => {
        console.log(data)

        if (data.status) {
            document.getElementById('billNotPaid').textContent = data.pending_bills_count;

        } else {
            document.getElementById('billNotPaid').textContent = 0;

        }
    })
    .catch(error => console.error('حدث خطأ أثناء جلب العدد:', error));

document.addEventListener("DOMContentLoaded", function () {
    // استدعاء الدالة بمجرد تحميل الصفحة
    checkPendingRating();
});

function checkPendingRating() {
    // 1. تحديد عنصر الكارد من الـ HTML
    const ratingAlertCard = document.getElementById('recent-session-alert');

    // مسار الـ Route في لارافيل
    const apiUrl = '/patient/check-pending-rating';

    // 2. إرسال الطلب إلى الـ API
    fetch(apiUrl, {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        }
    })
        .then(response => response.json())
        .then(result => {
            console.log(result)
            // 3. قراءة النتيجة والتحكم بظهور الكارد
            if (result.status && result.has_pending) {

                // تخصيص النص باسم المعالجة القادمة من الـ API
                const alertTextParagraph = ratingAlertCard.querySelector('.alert-text p');
                if (alertTextParagraph && result.data.treatment_name) {
                    alertTextParagraph.innerHTML = `لقد أتممتِ جلسة <strong>(${result.data.treatment_name})</strong> مؤخراً، يسعدنا جداً أن تشاركينا تقييمكِ لمساعدتنا في تقديم الأفضل دائماً.`;
                }

                // إظهار الكارد بالـ Flex ليتناسق مع التنسيقات
                ratingAlertCard.style.display = 'flex';

            } else {
                // إخفاء الكارد تماماً إذا لم يكن هناك جلسة تحتاج تقييم خلال أسبوع
                ratingAlertCard.style.display = 'none';
            }
        })
        .catch(error => {
            console.error('حدث خطأ أثناء جلب بيانات التقييم المعلق:', error);
            // في حال حدوث خطأ، نُخفي الكارد لضمان تجربة مستخدم نظيفة
            if (ratingAlertCard) {
                ratingAlertCard.style.display = 'none';
            }
        });
}