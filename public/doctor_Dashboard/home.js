// choose button appointment canceel or pending
function switchTab(tabName) {
    // 1. جلب عناصر المحتوى والأزرار
    const activeContent = document.getElementById('active-appointments');
    const canceledContent = document.getElementById('canceled-appointments');
    const tabs = document.querySelectorAll('.tab-btn');

    // 2. إزالة كلاس active من كل الأزرار
    tabs.forEach(tab => tab.classList.remove('active'));

    // 3. التحكم بالظهور والإخفاء بناءً على التبويب المختار
    if (tabName === 'active') {
        activeContent.style.display = 'flex';
        canceledContent.style.display = 'none';
        event.currentTarget.classList.add('active');
    } else if (tabName === 'canceled') {
        activeContent.style.display = 'none';
        canceledContent.style.display = 'flex';
        event.currentTarget.classList.add('active');
    }
}
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
            console.log(profile)
        }

        if (dashboardRes.ok) {
            const dashboard = await dashboardRes.json();
            renderDashboard(dashboard.data);
            console.log(dashboard)
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

    // امرر ل التوابع الداتا الي جبتا من  الباك
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
        const time = app.appointment_date ? app.appointment_date.split(' ')[1] : 'غير محدد';
        const treatments = app.treatments?.map(t => t.name).join(' · ') || 'جلسة معالجة';
        const room = app.room?.name || 'جناح العيادة';

        return `
            <div class="action-card-one">
                <div class="first-date">
                    <h5>${app.patient?.name || 'مريض غير معروف'}</h5>
                    <p>${treatments} · ${time} · ${room}</p>
                </div>
                <button onclick="startSession(${app.id})">ابدئي الجلسة</button>
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
        return `
            <div class="action-card-one canceled-card">
                <div class="first-date">
                    <h5><del>${app.patient?.name || 'مريض غير معروف'}</del></h5>
                    <p>${treatments}</p>
                </div>
                <span class="status-canceled">تم الإلغاء</span>
            </div>
        `;
    }).join('');
}

window.switchTab = function(tabType) {
    const activeTab = document.getElementById('active-appointments');
    const canceledTab = document.getElementById('canceled-appointments');
    const buttons = document.querySelectorAll('.tab-btn');

    buttons.forEach(btn => btn.classList.remove('active'));

    if (tabType === 'active') {
        activeTab.style.display = 'block';
        canceledTab.style.display = 'none';
        buttons[0].classList.add('active');
    } else {
        activeTab.style.display = 'none';
        canceledTab.style.display = 'block';
        buttons[1].classList.add('active');
    }
};

window.startSession = function(appointmentId) {
    console.log(`Starting session for appointment: ${appointmentId}`);
    // التوجيه لصفحة الجلسة عند الضغط
    // window.location.href = `treatments.html?appointment_id=${appointmentId}`;
};