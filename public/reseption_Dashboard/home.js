
async function fetchReceptionistProfile() {
    try {
        const response = await fetch('/receptionist/profile-data', {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            }
        });

        const result = await response.json();
        console.log(result)
        if (result.status === 'success') {
            const user = result.data;

            document.getElementById('receptionist-name').innerText = user.name;

        } else {
            console.error('خطأ:', result.message);
            console.log(result.message);
        }

    } catch (error) {
        console.error('حدث خطأ في جلب بيانات الملف الشخصي:', error);
        alert('فشل الاتصال بالسيرفر. يرجى التحقق من تشغيل سيرفر لارافيل.');
    }
}
// main swetch 

// /////////////////
// المتغيرات العامة لتخزين البيانات المجلوبة
let globalAppointments = {};
let currentTab = 'pending';

document.addEventListener('DOMContentLoaded', () => {
    fetchReceptionistProfile();

    // جلب البيانات عند تحميل الصفحة
    fetchDashboardSummary();
    fetchCategorizedAppointments();
    fetchRoomsStatus();
});

/* ================= ================= =================
   1. جلب ملخص الإحصائيات والفواتير
=================================================== */
function fetchDashboardSummary() {
    fetch('/receptionist/bills-summary', {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json'
        }
    })
        .then(res => res.json())
        .then(res => {
            if (res.status === 'success') {
                console.log(res.data)
                const unpaidElement = document.getElementById('paid_bills');
                if (unpaidElement && res.data.stats) {
                    unpaidElement.textContent = res.data.stats.unpaid_count ?? 0;
                }
            }
        })
        .catch(err => console.error('خطأ في جلب ملخص الفواتير:', err));
}

/* ================= ================= =================
   2. جلب وتحديث المواعيد وتبويبها
=================================================== */
function fetchCategorizedAppointments() {
    fetch('/appointments/categorized', {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json'
        }
    })
        .then(res => res.json())
        .then(res => {
            if (res.status === 'success') {
                globalAppointments = res.data;
                const rawData = res.data;
                console.log(res.data)
                // فلترة القوائم لتشمل فقط مواعيد اليوم
                globalAppointments = {
                    pending: (rawData.pending || []).filter(app => isToday(app.appointment_date)),
                    completed: (rawData.completed || []).filter(app => isToday(app.appointment_date)),
                    cancelled: (rawData.cancelled || []).filter(app => isToday(app.appointment_date)),
                    in_progress: (rawData.in_progress || []).filter(app => isToday(app.appointment_date)),
                    arrived: (rawData.arrived || []).filter(app => isToday(app.appointment_date))
                };

                // إجمالي مواعيد اليوم
                const todayTotalCount =
                    globalAppointments.pending.length ;

                // تحديث الكروت العلوية الخاصة بـ "اليوم"
                document.getElementById('today_appointments').textContent = todayTotalCount;
                document.getElementById('waiting_patients').textContent = globalAppointments.arrived.length;

                // إعادة عرض التبويب الحالي
                renderAppointmentsTab(currentTab);
            }
        })
        .catch(err => console.error('خطأ في جلب المواعيد:', err));
}

// التبديل بين التبويبات (Pending, In Progress, Arrived, Completed, Canceled)
function switchTab(tabName) {
    currentTab = tabName;

    // تحديث شكل الأزرار
    document.querySelectorAll('.appointment-tabs .tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });

    // تفعيل الزر المضغوط
    const activeBtn = Array.from(document.querySelectorAll('.appointment-tabs .tab-btn'))
        .find(b => b.getAttribute('onclick')?.includes(`'${tabName}'`));
    if (activeBtn) activeBtn.classList.add('active');

    renderAppointmentsTab(tabName);
}

// بناء وعرض المواعيد داخل التبويب المختار
function renderAppointmentsTab(tab) {
    const list = globalAppointments[tab] || [];
    const container = document.getElementById('pending-appointments'); // الحاوية الرئيسية
    if (!container) return;

    if (list.length === 0) {
        container.innerHTML = `<div class="action-card-one"><p style="margin:0;">لا توجد مواعيد في هذا القسم.</p></div>`;
        return;
    }

    let html = '';
    list.forEach(app => {
        const patientName = app.patient?.name || 'مريض غير معروف';
        const roomName = app.room?.name || 'بدون غرفة';
        const doctorName = app.doctor?.name ? `د. ${app.doctor.name}` : '';
        const treatmentName = app.treatments?.[0]?.name || 'علاج عام';
        const timeStr = app.appointment_date ? new Date(app.appointment_date).toLocaleTimeString('ar-EG', { hour: '2-digit', minute: '2-digit' }) : '';

        html += `
            <div class="action-card-one" id="appointment-card-${app.id}">
                <div class="first-date">
                    <h5>${patientName}</h5>
                    <p>${treatmentName} ${doctorName ? '· ' + doctorName : ''} · ${timeStr} · ${roomName}</p>
                </div>
                <div class="appointment-actions">
                    ${getButtonsByStatus(tab, app.id)}
                </div>
            </div>
        `;
    });

    container.innerHTML = html;
}

// إنشاء الأزرار المتاحة حسب حالة الموعد
function getButtonsByStatus(status, appointmentId) {
    if (status === 'pending') {
        return `
            <button class="btn-start-session" onclick="markAsArrived(${appointmentId}, this)">حضور</button>
            <button class="btn-cancel-session" data-id="${appointmentId}">إلغاء الموعد</button>
        `;
    } else if (status === 'arrived') {
        // <button class="btn-start-session" style="background-color: #28a745; color: white;" onclick="startSession(${appointmentId}, this)">إدخال للغرفة</button>
        // <button class="btn-cancel-session" onclick="cancelAppointment(${appointmentId}, this)">إلغاء</button>
        return `
        `;
    } else if (status === 'in_progress') {
        return `<span class="status-in-progress" style="color: #0d6efd; font-weight: bold;">تُنفذ الآن بداخل الغرفة...</span>`;
    } else if (status === 'completed') {
        return `<span class="status-completed" style="color: #198754;">مكتملة </span>`;
    } else if (status === 'canceled') {
        return `<span class="status-canceled" style="color: #dc3545;">تم الإلغاء</span>`;
    }
    return '';
}

/* ================= ================= =================
   3. دوال الأفعال (حضور - إدخال للغرفة - إلغاء الموعد)
=================================================== */

// أ) تسجيل حضور المريض
function markAsArrived(appointmentId, btnElement) {
    btnElement.disabled = true;

    fetch(`/appointments/${appointmentId}/arrived`, {
        method: 'PATCH',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': getCsrfToken()
        }
    })
        .then(res => res.json())
        .then(res => {
            showToast(res.message || 'تم تسجيل حضور المريض بنجاح');
            refreshAllData();
        })
        .catch(err => {
            console.error(err);
            showToast('حدث خطأ أثناء تسجيل الحضور', 'error');
            btnElement.disabled = false;
        });
}

// ب) إدخال المريض للغرفة (بدء الجلسة)
function startSession(appointmentId, btnElement) {
    btnElement.disabled = true;

    fetch(`/appointments/${appointmentId}/start-session`, {
        method: 'PATCH',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': getCsrfToken()
        }
    })
        .then(res => res.json())
        .then(res => {
            showToast(res.message || 'تم إدخال المريض للغرفة وبدء الجلسة');
            refreshAllData();
        })
        .catch(err => {
            console.error(err);
            showToast('حدث خطأ أثناء إدخال المريض للغرفة', 'error');
            btnElement.disabled = false;
        });
}



/* ================= ================= =================
   4. جلب وتحديث حالة الغرفة مباشرة
=================================================== */
function fetchRoomsStatus() {
    fetch('/reception/rooms-status', {
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': getCsrfToken()
        }
    })
        .then(res => res.json())
        .then(res => {

            if (res.status === 'success') {
                console.log(res)

                // تحديث كرت الغرف المشغولة
                const occupiedRoomsElem = document.getElementById('occupied_rooms');
                if (occupiedRoomsElem && res.summary) {
                    occupiedRoomsElem.textContent = `${res.summary.occupied_rooms} / ${res.summary.total_rooms}`;
                }

                // تحديث شبكة الغرف (Rooms Grid)
                renderRoomsGrid(res.rooms);
            }
        })
        .catch(err => console.error('خطأ في جلب حالة الغرف:', err));
}

function renderRoomsGrid(rooms) {
    const gridContainer = document.querySelector('.rooms-grid');
    if (!gridContainer) return;

    let html = '';
    rooms.forEach(room => {
        const isOccupied = room.is_occupied;
        const session = room.current_session;
        const busy = room.status;

        if (isOccupied && session ) {
            const patientName = session.patient?.name || 'غير محدد';
            const doctorName = session.doctor?.name || 'غير محدد';
            const treatmentName = session.treatments?.[0]?.name || 'غير محدد';

            html += `
                <div class="room-status-card occupied">
                    <div class="room-header">
                        <h5>${room.name}</h5>
                        <span class="badge">مشغولة</span>
                    </div>
                    <div class="room-body">
                        <p><strong>المريضة:</strong> ${patientName}</p>
                        <p><strong>الطبيبة:</strong> ${doctorName}</p>
                        <p><strong>الإجراء:</strong> ${treatmentName}</p>
                    </div>
                </div>
            `;
        }else if(busy=="busy"){
                        html += `
                <div class="room-status-card occupied">
                    <div class="room-header">
                        <h5>${room.name}</h5>
                        <span class="badge">مشغولة</span>
                    </div>
                    <div class="room-body">
                        <p class="empty-status">لا يوجد تفاصيل عن الغرفه</p>
                    </div>
                </div>
            `;
        }
        else {
            html += `
                <div class="room-status-card available">
                    <div class="room-header">
                        <h5>${room.name}</h5>
                        <span class="badge">متاحة</span>
                    </div>
                    <div class="room-body">
                        <p class="empty-status">جاهزة لاستقبال المريضة التالية</p>
                    </div>
                </div>
            `;
        }
    });

    gridContainer.innerHTML = html;
}

/* ================= ================= =================
   5. دوال مساعدة (Helper Functions)
=================================================== */
function refreshAllData() {
    fetchDashboardSummary();
    fetchCategorizedAppointments();
    fetchRoomsStatus();
}

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    if (!toast) return;

    toast.textContent = message;
    toast.style.backgroundColor = type === 'error' ? '#dc3545' : '#28a745';
    toast.classList.add('show');

    setTimeout(() => {
        toast.classList.remove('show');
    }, 3000);
}
function isToday(dateString) {
    if (!dateString) return false;
    const appDate = new Date(dateString);
    const today = new Date();

    return appDate.getFullYear() === today.getFullYear() &&
        appDate.getMonth() === today.getMonth() &&
        appDate.getDate() === today.getDate();
}