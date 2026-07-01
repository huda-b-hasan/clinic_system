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

function renderDashboard(resData) {
    const stats = resData.stats;
    const data = resData.data;


    // 2. تحديث عدادات بطاقات الإحصائيات (Summary Cards)
    document.getElementById('futureSession').textContent = `${stats.future_appointments_count}  `;
    document.getElementById('lastSession').textContent = stats.past_sessions_count;
    document.getElementById('billNotPaid').textContent = stats.unpaid_bills_count;

    // 3. تحديث قائمة المواعيد القادمة ديناميكياً
    const upcomingContainer = document.querySelector('.upcoming-appointments');
    upcomingContainer.innerHTML = ''; // تنظيف العناصر الثابتة القديمة

    if (data.future_appointments && data.future_appointments.length > 0) {
        data.future_appointments.forEach(appointment => {
            const appDate = new Date(appointment.appointment_date);
            
            // تنسيق التاريخ والوقت
            const dateString = appDate.toISOString().split('T')[0]; // YYYY-MM-DD
            const timeString = appDate.toLocaleTimeString('ar-EG', { hour: '2-digit', minute: '2-digit', hour12: true });

            // حساب الأيام المتبقية (اعتماداً على سنة 2026 الحالية)
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
                    </div>
                </div>
            `;
            upcomingContainer.insertAdjacentHTML('beforeend', itemHtml);
        });
    } else {
        upcomingContainer.innerHTML = '<p class="no-data">لا توجد مواعيد قادمة حالياً.</p>';
    }

    // 4. تحديث قائمة الجلسات السابقة
    const noSessionTxt = document.getElementById('noSession');
    const sessionsList = document.getElementById('sessions-list');
    sessionsList.innerHTML = ''; // تنظيف القائمة

    if (data.past_sessions && data.past_sessions.length > 0) {
        noSessionTxt.style.display = 'none'; // إخفاء جملة "لم تقم بأي جلسة"
        
        data.past_sessions.forEach(session => {
            const sessDate = new Date(session.appointment_date).toISOString().split('T')[0];
            const treatmentName = session.treatments && session.treatments.length > 0 
                ? session.treatments[0].name 
                : 'علاج مخصص';

            const sessionHtml = `
                <div class="appointment-item past-item" style="border-right: 4px solid #6b5b95; margin-bottom: 10px; padding: 10px; background: #fdfbf7;">
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