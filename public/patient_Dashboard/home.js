// ==========================================
// 1. جلب بيانات المريض الشخصية لعرض الاسم
// ==========================================
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
            document.getElementById('patientName').innerHTML = result.data.user.name;
        } else {
            window.location.href = '/login';
        }
    } catch (error) {
        console.error('حدث خطأ أثناء جلب البيانات:', error);
    }
});

// ==========================================
// 2. إدارة ومراقبة المودال والتوست لإلغاء المواعيد
// ==========================================
let appointmentIdToCancel = null;

// دالة فتح المودال (يتم استدعاؤها برمجياً عند الضغط على أزرار الجدول)
function openCancelModal(id) {
    appointmentIdToCancel = id;
    const modal = document.getElementById('cancelModal');
    if (modal) {
        modal.style.display = 'flex';
    }
}

// دالة إغلاق وتنظيف المودال
function closeCancelModal() {
    const modal = document.getElementById('cancelModal');
    if (modal) {
        modal.style.display = 'none';
    }
    const reasonInput = document.getElementById('cancellation_reason');
    if (reasonInput) {
        reasonInput.value = ""; // تفريغ حقل النص
    }
    appointmentIdToCancel = null;
}

document.addEventListener("DOMContentLoaded", () => {
    // جلب البيانات الأساسية للداشبورد
    fetchDashboardData();
    // فحص التقييمات المعلقة
    checkPendingRating();

    // ربط زر الإغلاق العلوي (x)
    const closeBtn = document.getElementById('closeModalBtn');
    if (closeBtn) {
        closeBtn.addEventListener('click', closeCancelModal);
    }

    // ربط زر التراجع السفلي (تراجع)
    const backBtn = document.getElementById('backModalBtn');
    if (backBtn) {
        backBtn.addEventListener('click', closeCancelModal);
    }

    // ربط زر تأكيد الإلغاء الجديد بدون alert ومع الـ Toast الذكي
    const confirmBtn = document.getElementById('confirmCancelBtn');
    if (confirmBtn) {
        confirmBtn.addEventListener('click', () => {
            if (!appointmentIdToCancel) return;

            const cancellationReasonInput = document.getElementById('cancellation_reason');
            const cancellationReason = cancellationReasonInput ? cancellationReasonInput.value : '';
            const toast = document.getElementById('toastNotification');

            // إرسال طلب الإلغاء للـ API بالـ Method PUT وسيقوم الـ Controller بتحديد الجهة تلقائياً
            fetch(`/appointments/${appointmentIdToCancel}/cancel`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                },
                body: JSON.stringify({
                    cancellation_reason: cancellationReason
                })
            })
                .then(response => response.json())
                .then(data => {
                    closeCancelModal();

                    if (data.success) {
                        if (toast) {
                            toast.style.background = '#28a745'; // أخضر للنجاح
                            toast.textContent = `✓ ${data.message}`;
                            toast.style.display = 'block';
                        }

                        setTimeout(() => {
                            if (toast) toast.style.display = 'none';
                            location.reload(); // تحديث الجدول في الصفحة
                        }, 4000);
                    } else {
                        console.log(data)
                        if (toast) {
                            toast.style.background = '#dc3545'; // أحمر للفشل
                            toast.textContent = ` ${data.message}`;
                            toast.style.display = 'block';
                        }

                        setTimeout(() => {
                            if (toast) toast.style.display = 'none';
                        }, 6000);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    closeCancelModal();

                    if (toast) {
                        toast.style.background = '#dc3545'; // أحمر لخطأ السيرفر
                        toast.textContent = ' حدث خطأ غير متوقع أثناء الاتصال بالخادم.';
                        toast.style.display = 'block';
                    }

                    setTimeout(() => {
                        if (toast) toast.style.display = 'none';
                    }, 4000);
                });
        });
    }
});

// ==========================================
// 3. جلب وعرض بيانات لوحة التحكم (الداشبورد)
// ==========================================
async function fetchDashboardData() {
    try {
        const response = await fetch('/patient/dashboard-data', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }
        });

        if (response.status === 403) {
            window.location.href = "../auth/login.html";
            return;
        }

        const resData = await response.json();
        console.log(resData);

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
    checkCancellationNotification(resData.data.unread_cancellations); document.getElementById('futureSession').textContent = `${stats.pending_appointments_count}  `;
    document.getElementById('lastSession').textContent = stats.completed_sessions_count;

    const upcomingContainer = document.querySelector('.upcoming-appointments');
    upcomingContainer.innerHTML = '';

    if (data.pending_appointments && data.pending_appointments.length > 0) {
        data.pending_appointments.forEach(appointment => {
            const appDate = new Date(appointment.appointment_date);
            const dateString = appDate.toISOString().split('T')[0];
            const timeString = appDate.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });

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
                        <button class="cancel-btn" data-id="${appointment.id}" type="button">إلغاء</button>
                    </div>
                </div>
            `;
            upcomingContainer.insertAdjacentHTML('beforeend', itemHtml);
        });

        document.querySelectorAll('.cancel-btn').forEach(button => {
            button.addEventListener('click', function () {
                const id = this.getAttribute('data-id');
                openCancelModal(id);
            });
        });


    } else {
        upcomingContainer.innerHTML = '<p class="no-data">لا توجد مواعيد قادمة حالياً.</p>';
    }

    // تحديث قائمة الجلسات السابقة
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

// ==========================================
// 4. جلب عدد الفواتير المعلقة
// ==========================================
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
        console.log(data);
        if (data.status) {
            document.getElementById('billNotPaid').textContent = data.pending_bills_count;
        } else {
            document.getElementById('billNotPaid').textContent = 0;
        }
    })
    .catch(error => console.error('حدث خطأ أثناء جلب العدد:', error));

// ==========================================
// 5. فحص التقييم المعلق وعرض التنبيه
// ==========================================
function checkPendingRating() {
    const ratingAlertCard = document.getElementById('recent-session-alert');
    const apiUrl = '/patient/check-pending-rating';

    fetch(apiUrl, {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        }
    })
        .then(response => response.json())
        .then(result => {
            console.log(result);
            if (result.status && result.has_pending) {
                const alertTextParagraph = ratingAlertCard.querySelector('.alert-text p');
                if (alertTextParagraph && result.data.treatment_name) {
                    alertTextParagraph.innerHTML = `لقد أتممتِ جلسة <strong>(${result.data.treatment_name})</strong> مؤخراً، يسعدنا جداً أن تشاركينا تقييمكِ لمساعدتنا في تقديم الأفضل دائماً.`;
                }
                ratingAlertCard.style.display = 'flex';
            } else {
                if (ratingAlertCard) ratingAlertCard.style.display = 'none';
            }
        })
        .catch(error => {
            console.error('حدث خطأ أثناء جلب بيانات التقييم المعلق:', error);
            if (ratingAlertCard) {
                ratingAlertCard.style.display = 'none';
            }
        });
}
// notefication 
// ==========================================
// 6. عرض إشعار إلغاء الموعد (إذا وُجد)
// ==========================================
// هذه الدالة تظهر الكارد التجميعي
// 1. تعريف العناصر
const showDetailsBtn = document.getElementById('show-details-btn');
const modal = document.getElementById('cancellationDetailsModal');

// 2. عند الضغط على "عرض التفاصيل" في الكارد
showDetailsBtn.onclick = function () {
    modal.style.display = 'flex'; // إظهار المودل
};

// 3. دالة إغلاق المودل
function closeModal() {
    modal.style.display = 'none'; // إخفاء المودل
}

// 4. إغلاق المودل عند الضغط خارج منطقة الكونتير (اختياري)
window.onclick = function (event) {
    if (event.target == modal) {
        closeModal();
    }
}
function checkCancellationNotification(unreadCancellations) {
    const alertCard = document.getElementById('cancellation-alert-card');

    if (unreadCancellations && unreadCancellations.length > 0) {
        // إظهار الكارد في الصفحة
        alertCard.style.display = 'flex';

        // تحديث الرقم في الكارد
        document.getElementById('noteficationNumber').innerText = unreadCancellations.length;

        // عند الضغط على زر "عرض التفاصيل"
        document.getElementById('show-details-btn').onclick = () => {
            // استهداف حاوية القائمة داخل المودل الجديد
            const listContent = document.getElementById('cancelled-appointments-list');
            // ملء القائمة بالكاردات الجديدة
            listContent.innerHTML = unreadCancellations.map(app => `
                <div class="cancellation-card" data-id="${app.id}">
                    <div class="card-info">
                        <h4>${app.treatment}</h4>
                        <p>السبب: ${app.cancellation_reason || 'غير محدد'}</p>
                    </div>
                    <div class="card-action">
                    <button class="btn-rebook" onclick="markAsSeen(event, '${app.id}')">تم</button>                    </div>
                </div>
            `).join('');

            // إظهار المودل الجديد
            document.getElementById('cancellationDetailsModal').style.display = 'flex';
        };
    } else {
        alertCard.style.display = 'none';
    }
}

// دالة الإغلاق الموحدة للمودل الجديد
function closeDropdown() {
    document.getElementById('cancellationDetailsModal').style.display = 'none';
}
function markAsSeen(appointmentId) {
    if (event) event.preventDefault();
    // 1. إرسال الطلب للسيرفر
    fetch(`/appointments/${appointmentId}/mark-as-seen`, {
        method: 'POST',
        headers: {
         'Content-Type': 'application/json',
            'Accept': 'application/json'      ,
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
         }
    })
    .then(response => response.json())
    .then(data => {
        // 2. إزالة الكارد من المودل بعد نجاح العملية
        // const card = document.querySelector(`.cancellation-card[data-id="${appointmentId}"]`);
        // if (card) {
        //     card.remove();
        // }

        // // 3. تحديث الرقم في التنبيه الرئيسي (اختياري)
        // const remainingCards = document.querySelectorAll('.cancellation-card');
        // const countSpan = document.getElementById('noteficationNumber');
        // countSpan.innerText = remainingCards.length;

        // 4. إذا لم يتبقَ أي كاردات، أخفي التنبيه الرئيسي والمودل
        if (remainingCards.length === 0) {
            document.getElementById('cancellation-alert-card').style.display = 'none';
            document.getElementById('cancellationDetailsModal').style.display = 'none';
        }
    })
    .catch(error => console.error('Error:', error));
}