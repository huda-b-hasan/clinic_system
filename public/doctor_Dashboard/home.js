// choose button appointment canceel or pending
window.switchTab = function(tabType) {
    const activeTab = document.getElementById('active-appointments');
    const canceledTab = document.getElementById('canceled-appointments');
    const buttons = document.querySelectorAll('.tab-btn');

    buttons.forEach(btn => btn.classList.remove('active'));

    if (tabType === 'active') {
        activeTab.style.display = 'flex'; // استخدام flex ليتطابق مع تصاميم الكروت
        canceledTab.style.display = 'none';
        buttons[0].classList.add('active');
    } else {
        activeTab.style.display = 'none';
        canceledTab.style.display = 'flex';
        buttons[1].classList.add('active');
    }
};
// end switch button

document.addEventListener('DOMContentLoaded', () => {
    initDashboard();
});

async function initDashboard() {
    try {
        const [profileRes, dashboardRes] = await Promise.all([
            fetch('/doctor/profile-data'), 
            fetch('/doctor/dashboard-data')
        ]);

        if (profileRes.ok) {
            const profile = await profileRes.json();
            document.getElementById('doctorName').textContent = profile.data.name;
            console.log(profile);
        }

        if (dashboardRes.ok) {
            const dashboard = await dashboardRes.json();
            renderDashboard(dashboard.data);
            console.log(dashboard);
        }
    } catch (error) {
        console.error('Dashboard Error:', error);
    }
}

function renderDashboard(data) {
    const stats = data.statistics;
    document.getElementById('today_sessions').textContent = stats.today_appointments_count || 0;
    document.getElementById('active_Patients').textContent = stats.total_patients_count || 0;
    document.getElementById('total_completed_sessions').textContent = stats.total_completed_sessions_count || 0;

    // امرر ل التوابع الداتا الي جبتا من الباك
    renderActiveAppointments(data.appointments?.pending || []);
    renderCancelledAppointments(data.appointments?.cancelled || []);
}

function renderActiveAppointments(appointments) {
    const container = document.getElementById('active-appointments');
    
    if (!appointments || appointments.length === 0) {
        container.innerHTML = '<p class="no-data" style="text-align:center; padding:20px; color:#888;">لا توجد مواعيد قائمة اليوم</p>';
        return;
    }

    container.innerHTML = appointments.map(app => {
        // تنسيق وقت الموعد
        const time = app.appointment_date ? app.appointment_date.split(' ')[1] : 'غير محدد';
        const treatments = app.treatments?.map(t => t.name).join(' · ') || 'جلسة معالجة';
        const room = app.room?.name || 'جناح العيادة';

        return `
            <div class="action-card-one">
                <div class="first-date">
                    <h5>${app.patient?.name || 'مريض غير معروف'}</h5>
                    <p>${treatments} · ${time} · ${room}</p>
                </div>
                <button class="btn-start-session" onclick="startSession(${app.id})">ابدئي الجلسة</button>
            </div>
        `;
    }).join('');
}

function renderCancelledAppointments(appointments) {
    const container = document.getElementById('canceled-appointments');
    
    if (!appointments || appointments.length === 0) {
        container.innerHTML = '<p class="no-data" style="text-align:center; padding:20px; color:#888;">لا توجد مواعيد ملغاة اليوم</p>';
        return;
    }

    container.innerHTML = appointments.map(app => {
        const treatments = app.treatments?.map(t => t.name).join(' · ') || 'جلسة ملغاة';
        const time = app.appointment_date ? app.appointment_date.split(' ')[1] : 'غير محدد';
        
        // 1. تحديد نص الشارة بناءً على حقل cancelled_via القادم من جدول الـ appointments تعيتك
        let roleName = 'النظام';
        let roleClass = 'role-system';
        
        if (app.cancelled_via === 'Doctor') {
            roleName = 'الطبيب (أنتِ)';
            roleClass = 'role-doctor';
        } else if (app.cancelled_via === 'Patient') {
            roleName = 'المريض';
            roleClass = 'role-patient';
        } else if (app.cancelled_via === 'Receptionist') {
            roleName = 'الاستقبال';
            roleClass = 'role-receptionist';
        }

        // 2. فحص هل هناك سبب مكتوب أم لا لإظهار شريط السبب السفلي بشكل مرن
        const reasonHTML = app.cancellation_reason 
            ? `<div class="cancellation-reason-bar"><strong>السبب:</strong> ${app.cancellation_reason}</div>` 
            : `<div class="cancellation-reason-bar text-muted-reason">لم يتم ذكر سبب للإلغاء</div>`;

        return `
            <div class="action-card-one canceled-card-expanded">
                <div class="canceled-main-info">
                    <div class="first-date">
                        <h5><del>${app.patient?.name || 'مريض غير معروف'}</del></h5>
                        <p>${treatments} · ${time}</p>
                    </div>
                    <div class="canceled-meta">
                        <span class="role-badge ${roleClass}">${roleName}</span>
                        <span class="status-canceled">تم الإلغاء</span>
                    </div>
                </div>
                ${reasonHTML}
            </div>
        `;
    }).join('');
}

window.startSession = function(appointmentId) {
    console.log(`Starting session for appointment: ${appointmentId}`);
    // window.location.href = `treatments.html?appointment_id=${appointmentId}`;
};