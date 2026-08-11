const API_URL = ''; 

let allResources = [];
let editingResourceId = null;
let editingResourceType = null;

// متغيرات مؤقتة لعملية الحذف
let itemToDeleteId = null;
let itemToDeleteType = null;

document.addEventListener('DOMContentLoaded', () => {
    // التأكد من وجود عنصر Toast في الشاشة
    ensureToastElementExists();

    loadResources();

    // ربط نموذج الإضافة والبحث والفلترة
    document.getElementById('resourceForm').addEventListener('submit', handleFormSubmit);
    document.getElementById('searchInput').addEventListener('input', filterResources);
    document.getElementById('typeFilter').addEventListener('change', filterResources);
    document.getElementById('statusFilter').addEventListener('change', filterResources);

    // إظهار/إخفاء حقل الصيانة والنموذج حسب النوع
    document.getElementById('resourceTypeSelect').addEventListener('change', toggleMaintenanceField);

    // ربط زر التأكيد داخل مودال الحذف
    const confirmBtn = document.getElementById('confirmDeleteBtn');
    if (confirmBtn) {
        confirmBtn.addEventListener('click', executeDelete);
    }
});

// ---------------- دالة التحكم بالـ Toast ----------------
function ensureToastElementExists() {
    if (!document.getElementById('toast')) {
        const toastDiv = document.createElement('div');
        toastDiv.id = 'toast';
        toastDiv.className = 'toast';
        document.body.appendChild(toastDiv);
    }
}

function showToast(message, type = 'info') {
    const toast = document.getElementById('toast');
    if (!toast) return;

    toast.textContent = message;
    toast.className = `toast ${type} show`;

    // إخفاء التوست تلقائياً بعد 3 ثوانٍ
    setTimeout(() => {
        toast.classList.remove('show');
    }, 3000);
}

// ---------------- جلب وتحميل البيانات ----------------
async function loadResources() {
    try {
        const [roomsRes, devicesRes] = await Promise.all([
            fetch(`${API_URL}/rooms`),
            fetch(`${API_URL}/devices`)
        ]);

        const roomsData = await roomsRes.json();
        const devicesData = await devicesRes.json();

        const rooms = (roomsData.data || []).map(r => ({
            id: r.id,
            name: r.name,
            resource_type: 'room',
            type_label: 'غرفة علاجية',
            sub_info: r.type || 'جناح علاج طبي',
            location: r.type || 'المبنى الرئيسي',
            last_maintenance: '—',
            status: r.status || 'available'
        }));

        const devices = (devicesData.data || []).map(d => ({
            id: d.id,
            name: d.name,
            resource_type: 'device',
            type_label: 'جهاز طبي',
            sub_info: d.model ? `موديل: ${d.model}` : 'جهاز طبي',
            location: 'غرفة المعدات',
            last_maintenance: d.last_maintenance || '—',
            status: d.status || 'available'
        }));

        allResources = [...rooms, ...devices];
        updateSummaryCards(rooms, devices);
        renderTable(allResources);

    } catch (error) {
        console.error('خطأ في جلب البيانات:', error);
        showToast('فشل في تحميل بيانات الغرف والأجهزة', 'error');
    }
}

// تحديث الكروت العلوية بالإحصائيات
function updateSummaryCards(rooms, devices) {
    const totalRooms = rooms.length;
    const activeDevices = devices.filter(d => d.status !== 'maintenance').length;
    const maintenanceDevices = devices.filter(d => d.status === 'maintenance').length;

    const cards = document.querySelectorAll('.summary-card .text-content p');
    if (cards[0]) cards[0].textContent = `${totalRooms} غرف علاجية`;
    if (cards[1]) cards[1].textContent = `${activeDevices} أجهزة متاحة`;
    if (cards[2]) cards[2].textContent = `${maintenanceDevices} أجهزة قيد الصيانة`;
}

// رسم الجدول ديناميكياً
function renderTable(resources) {
    const tbody = document.querySelector('.admin-table tbody');
    tbody.innerHTML = '';

    if (resources.length === 0) {
        tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; padding: 20px;">لا توجد بيانات للعرض</td></tr>`;
        return;
    }

    resources.forEach(item => {
        const isRoom = item.resource_type === 'room';
        const icon = isRoom ? '🛋️' : '⚡';
        const iconBg = isRoom ? 'room-bg' : 'device-bg';
        const categoryBadge = isRoom ? 'category-room' : 'category-device';

        let statusBadgeClass = 'active';
        let statusText = 'متاحة';

        if (item.status === 'busy') {
            statusBadgeClass = 'busy';
            statusText = 'قيد الاستخدام';
        } else if (item.status === 'maintenance') {
            statusBadgeClass = 'maintenance';
            statusText = 'تحت الصيانة';
        }

        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>
                <div class="resource-cell">
                    <div class="resource-icon ${iconBg}">${icon}</div>
                    <div>
                        <strong>${item.name}</strong>
                        <span class="resource-subtitle">${item.sub_info}</span>
                    </div>
                </div>
            </td>
            <td><span class="category-badge ${categoryBadge}">${item.type_label}</span></td>
            <td><span class="location-text">${item.location}</span></td>
            <td><span class="date-text">${item.last_maintenance}</span></td>
            <td><span class="badge-status ${statusBadgeClass}">${statusText}</span></td>
            <td>
                <div class="action-buttons">
                    <button class="btn-icon edit-btn" onclick="openEditModal('${item.resource_type}', ${item.id})" title="تعديل">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
                    </button>
                    <button class="btn-icon danger-btn" onclick="openDeleteModal('${item.resource_type}', ${item.id})" title="حذف">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                    </button>
                </div>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

// التصفية والبحث
function filterResources() {
    const searchVal = document.getElementById('searchInput').value.toLowerCase();
    const typeVal = document.getElementById('typeFilter').value;
    const statusVal = document.getElementById('statusFilter').value;

    const filtered = allResources.filter(item => {
        const matchesSearch = item.name.toLowerCase().includes(searchVal) || item.sub_info.toLowerCase().includes(searchVal);
        const matchesType = !typeVal || item.resource_type === typeVal;
        const matchesStatus = !statusVal || item.status === statusVal;

        return matchesSearch && matchesType && matchesStatus;
    });

    renderTable(filtered);
}

// إظهار وإخفاء حقل الصيانة بناءً على النوع
function toggleMaintenanceField() {
    const type = document.getElementById('resourceTypeSelect').value;
    const maintenanceGroup = document.getElementById('maintenanceGroup');
    if (maintenanceGroup) {
        maintenanceGroup.style.display = type === 'device' ? 'block' : 'none';
    }
}

// معالجة إضافة أو تعديل العنصر
async function handleFormSubmit(e) {
    e.preventDefault();

    const name = document.getElementById('resourceNameInput').value;
    const type = document.getElementById('resourceTypeSelect').value;
    const location = document.getElementById('resourceLocationInput').value;
    const status = document.getElementById('resourceStatusSelect').value;
    const lastMaintenance = document.getElementById('resourceMaintenanceInput')?.value;

    const isDevice = type === 'device';
    const endpoint = isDevice ? `${API_URL}/devices` : `${API_URL}/rooms`;

    const payload = isDevice ? {
        name: name,
        model: location,
        status: status,
        last_maintenance: lastMaintenance || null
    } : {
        name: name,
        type: location,
        status: status
    };

    let url = endpoint;
    let method = 'POST';
    const isEditing = Boolean(editingResourceId);

    if (isEditing) {
        url = `${endpoint}/${editingResourceId}`;
        method = 'PUT';
    }

    try {
        const response = await fetch(url, {
            method: method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        if (response.ok) {
            closeResourceModal();
            loadResources();
            showToast(isEditing ? 'تم تعديل البيانات بنجاح ✏️' : 'تمت الإضافة بنجاح ✨', 'success');
        } else {
            const resData = await response.json();
            showToast(resData.message || 'حدث خطأ أثناء حفظ البيانات', 'error');
        }
    } catch (error) {
        console.error('حدث خطأ أثناء الحفظ:', error);
        showToast('تعذر الاتصال بالسيرفر', 'error');
    }
}

// فتح نموذج التعديل
function openEditModal(resourceType, id) {
    const item = allResources.find(r => r.resource_type === resourceType && r.id === id);
    if (!item) return;

    editingResourceId = id;
    editingResourceType = resourceType;

    document.getElementById('resourceNameInput').value = item.name;
    document.getElementById('resourceTypeSelect').value = item.resource_type;
    document.getElementById('resourceLocationInput').value = item.location !== '—' ? item.location : '';
    document.getElementById('resourceStatusSelect').value = item.status;

    toggleMaintenanceField();

    if (resourceType === 'device' && item.last_maintenance !== '—') {
        document.getElementById('resourceMaintenanceInput').value = item.last_maintenance;
    }

    document.getElementById('resourceModalTitle').textContent = 'تعديل بيانات ' + (resourceType === 'device' ? 'الجهاز' : 'الغرفة');
    openResourceModal();
}

// ---------------- إدارة مودال الحذف ----------------

function openDeleteModal(resourceType, id) {
    itemToDeleteId = id;
    itemToDeleteType = resourceType;

    const messageElement = document.getElementById('deleteModalMessage');
    const typeLabel = resourceType === 'device' ? 'هذا الجهاز الطبي' : 'هذه الغرفة العلاجية';
    
    if (messageElement) {
        messageElement.textContent = `هل أنتِ متأكدة من رغبتك في حذف ${typeLabel} نهائياً؟ قد يؤدي ذلك لإلغاء ربطه بالمواعيد والجلسات الحالية.`;
    }

    document.getElementById('deleteModal').classList.add('active');
}

function closeDeleteModal() {
    itemToDeleteId = null;
    itemToDeleteType = null;
    document.getElementById('deleteModal').classList.remove('active');
}

async function executeDelete() {
    if (!itemToDeleteId || !itemToDeleteType) return;

    const endpoint = itemToDeleteType === 'device' ? `${API_URL}/devices` : `${API_URL}/rooms`;

    try {
        const response = await fetch(`${endpoint}/${itemToDeleteId}`, {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' }
        });

        if (response.ok) {
            closeDeleteModal();
            loadResources();
            showToast('تم الحذف بنجاح ', 'warning');
        } else {
            const result = await response.json();
            showToast(result.message || 'حدث خطأ أثناء عملية الحذف', 'error');
        }
    } catch (error) {
        console.error('خطأ في الاتصال أثناء الحذف:', error);
        showToast('تعذر الاتصال بالسيرفر أثناء الحذف', 'error');
    }
}

// ---------------- إدارة مودال الإضافة والتعديل ----------------

function openResourceModal() {
    document.getElementById('resourceModal').classList.add('active');
}

function closeResourceModal() {
    editingResourceId = null;
    editingResourceType = null;
    document.getElementById('resourceForm').reset();
    document.getElementById('resourceModalTitle').textContent = 'إضافة غرفة أو جهاز جديد ';
    document.getElementById('resourceModal').classList.remove('active');
}