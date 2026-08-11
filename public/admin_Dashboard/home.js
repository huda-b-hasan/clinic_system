document.addEventListener('DOMContentLoaded', function () {
    fetchAdminDashboardData();
});

async function fetchAdminDashboardData() {
    try {
        const response = await fetch('/admin/dashboard-data', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }
        });

        const result = await response.json();

        if (response.ok && result.status === 'success') {
            const data = result.data;
            console.log(result)
            // 1. تعبئة كروت الإحصائيات (Summary Cards)
            document.getElementById('monthly_revenue').textContent = data.stats.total_revenue;
            document.getElementById('total_patients').textContent = data.stats.total_patients + ' مريضة';
            document.getElementById('low_stock_materials').textContent = data.stats.low_stock_count + ' مواد';
            document.getElementById('avg_rating').textContent = data.stats.average_rating + ' / 5.0';

            // 2. تعبئة جدول الخدمات الأكثر طلباً
            renderTopServicesTable(data.top_services);

            // 3. تعبئة تنبيهات المخزن للمواد القليلة
            renderLowStockAlerts(data.low_stock_materials);

        } else {
            console.error('خطأ في جلب البيانات:', result.message);
        }
    } catch (error) {
        console.error('حدث خطأ في الشبكة أثناء جلب بيانات الأدمن:', error);
    }
}

// دالة عرض جدول الخدمات
function renderTopServicesTable(services) {
    const tbody = document.querySelector('.admin-table tbody');
    if (!tbody) return;

    if (!services || services.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;">لا توجد خدمات متاحة حالياً</td></tr>';
        return;
    }

    tbody.innerHTML = services.map(service => {
        const category = service.category || 'عام';
        const price = service.base_price ? service.base_price + 'ل.س' : 'غير محدد';
        const appointmentsCount = (service.appointments_count || 0) + ' جلسة';
        const statusBadge = service.status === 'active'
            ? '<span class="badge-status active">نشط</span>'
            : '<span class="badge-status inactive">غير نشط</span>';

        return `
            <tr>
                <td><strong>${service.name}</strong></td>
                <td>${category}</td>
                <td>${price}</td>
                <td>${appointmentsCount}</td>
                <td>${statusBadge}</td>
            </tr>
        `;
    }).join('');
}

// دالة عرض تنبيهات مواد المخزن
function renderLowStockAlerts(materials) {
    const alertsContainer = document.querySelector('.alerts-list');
    if (!alertsContainer) return;

    if (!materials || materials.length === 0) {
        alertsContainer.innerHTML = '<p style="text-align:center; color: #777; padding: 15px;">لا يوجد مواد على وشك الانتهاء</p>';
        return;
    }

    alertsContainer.innerHTML = materials.map(item => `
        <div class="alert-item warning">
            <div class="alert-info">
                <h5>${item.name}</h5>
                <p>المتبقي في المخزن: <strong>${item.quantity} قطعة</strong></p>
            </div>
        </div>
    `).join('');
}