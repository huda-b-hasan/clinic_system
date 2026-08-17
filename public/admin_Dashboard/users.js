let editingUserId = null;
let userIdToDelete = null;

document.addEventListener('DOMContentLoaded', function () {
    // جلب البيانات فور تحميل الصفحة
    fetchUsers();

    // أحداث الفلترة والبحث الفوري
    const searchInput = document.getElementById('searchInput');
    const roleFilter = document.getElementById('roleFilter');

    if (searchInput) searchInput.addEventListener('input', debounce(fetchUsers, 300));
    if (roleFilter) roleFilter.addEventListener('change', fetchUsers);

    // حدث حفظ نموذج الإضافة / التعديل
    const userForm = document.getElementById('userForm');
    if (userForm) userForm.addEventListener('submit', handleUserFormSubmit);

    // حدث تأكيد الحذف من المودال
    document.getElementById('confirmDeleteBtn')?.addEventListener('click', confirmDeleteUser);
});

// ==========================================
// 1. دالة الـ Toast الإشعارية
// ==========================================
function showToast(message, type = "success") {
    const toast = document.getElementById("toast");
    if (!toast) return;

    toast.textContent = message;
    toast.className = `toast ${type} show`;

    setTimeout(() => {
        toast.className = toast.className.replace("show", "").trim();
    }, 3000);
}

// ==========================================
// 2. جلب الموظفين والإحصائيات
// ==========================================
async function fetchUsers() {
    const searchVal = document.getElementById('searchInput') ? document.getElementById('searchInput').value : '';
    const roleVal = document.getElementById('roleFilter') ? document.getElementById('roleFilter').value : '';

    let queryParts = [];
    if (searchVal.trim() !== '') {
        queryParts.push('search=' + encodeURIComponent(searchVal.trim()));
    }
    if (roleVal.trim() !== '') {
        queryParts.push('role=' + encodeURIComponent(roleVal.trim()));
    }

    let requestUrl = '/users';
    if (queryParts.length > 0) {
        requestUrl += '?' + queryParts.join('&');
    }

    try {
        const response = await fetch(requestUrl, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        const result = await response.json();

        if (response.ok) {
            const resData = result.data || result;
            console.log(resData)

            if (resData.stats) {
                updateStatsCards(resData.stats);
            }
            if (resData.users) {
                renderUsersTable(resData.users);
            }
        } else {
            showToast("حدث خطأ أثناء تحميل بيانات الموظفين", "error");
        }
    } catch (error) {
        showToast("فشل الاتصال بالخادم", "error");
    }
}

// ==========================================
// 3. تحديث الإحصائيات
// ==========================================
function updateStatsCards(stats) {
    if (!stats) return;

    const totalEl = document.getElementById('totalStaffCount') || document.querySelector('.summary-cards .summary-card:nth-child(1) .text-content p');
    const doctorsEl = document.getElementById('doctorsCount') || document.querySelector('.summary-cards .summary-card:nth-child(2) .text-content p');
    const receptionEl = document.getElementById('receptionistsCount') || document.querySelector('.summary-cards .summary-card:nth-child(3) .text-content p');

    if (totalEl) {
        totalEl.textContent = (stats.total_users !== undefined ? stats.total_users : (stats.total_staff || 0)) + ' موظف';
    }
    if (doctorsEl) {
        doctorsEl.textContent = (stats.doctors_count !== undefined ? stats.doctors_count : 0) + ' أطباء';
    }
    if (receptionEl) {
        receptionEl.textContent = (stats.reseptions_count !== undefined ? stats.reseptions_count : (stats.receptionists_count || 0)) + ' موظف';
    }
}

// ==========================================
// 4. عرض الجدول
// ==========================================
function renderUsersTable(users) {
    const tbody = document.getElementById('usersTableBody') || document.querySelector('.admin-table tbody');
    if (!tbody) return;

    if (!users || users.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding: 25px; color: #777;">لا يوجد موظفون مطابقون للبحث</td></tr>';
        return;
    }

    let rowsHtml = '';
    for (let i = 0; i < users.length; i++) {
        const user = users[i];
        const initials = getInitials(user.name);

        const rolesList = user.roles && user.roles.length > 0 ? user.roles : ['staff'];
        let rolesHtml = '<div class="roles-container" style="display: flex; gap: 5px; flex-wrap: wrap;">';

        for (let j = 0; j < rolesList.length; j++) {
            const roleInfo = getRoleBadge(rolesList[j]);
            rolesHtml += '<span class="role-badge ' + roleInfo.badgeClass + '">' + roleInfo.label + '</span>';
        }
        rolesHtml += '</div>';

        const userJson = JSON.stringify(user).replace(/"/g, '&quot;');

        rowsHtml += '<tr>' +
            '<td>' +
                '<div class="user-info-cell">' +
                    '<div class="user-avatar">' + initials + '</div>' +
                    '<div>' +
                        '<strong>' + (user.name || '') + '</strong>' +
                        '<span class="user-email">' + (user.email || '') + '</span>' +
                    '</div>' +
                '</div>' +
            '</td>' +
            '<td>' + rolesHtml + '</td>' +
            '<td>' + (user.phone || '-') + '</td>' +
            '<td>' +
                '<div class="action-buttons">' +
                    // زر إضافة الدور
                    '<button class="btn-icon edit-btn" title="إضافة دور وظيفي للموظف" onclick="openAddRoleModal(' + userJson + ')">' +
                        '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><line x1="20" y1="8" x2="20" y2="14"></line><line x1="23" y1="11" x2="17" y2="11"></line></svg>' +
                    '</button>' +
                    '<button class="btn-icon danger-btn" title="حذف الموظف" onclick="deleteUser(' + user.id + ')">' +
                        '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>' +
                    '</button>' +
                '</div>' +
            '</td>' +
        '</tr>';
    }

    tbody.innerHTML = rowsHtml;
}

// ==========================================
// 5. حفظ الموظف (إضافة / تعديل الدور) مع Toast
// ==========================================
async function handleUserFormSubmit(e) {
    e.preventDefault();

    const roleValue = getInputValue('userRole');
    const isEdit = editingUserId !== null;

    let payload = {};

    if (isEdit) {
        // عند إضافة دور لموظف حالي: نرسل الدور الجديد فقط ولا نعدل البيانات الأخرى
        if (roleValue !== '') {
            payload.role = roleValue;
        }
    } else {
        // عند إضافة موظف جديد: نرسل كافة البيانات بحرية تامة
        payload = {
            name: getInputValue('userName'),
            email: getInputValue('userEmail'),
            phone: getInputValue('userPhone'),
        };

        if (roleValue !== '') {
            payload.role = roleValue;
        }

        const passwordVal = getInputValue('userPassword');
        if (passwordVal !== '') {
            payload.password = passwordVal;
        }
    }

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    let targetUrl = isEdit ? '/users/' + editingUserId : '/users';
    const methodType = isEdit ? 'PUT' : 'POST';

    try {
        const response = await fetch(targetUrl, {
            method: methodType,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(payload)
        });

        const result = await response.json();

        if (response.ok) {
            closeUserModal();
            fetchUsers();

            if (isEdit) {
                showToast("تم إضافة الدور الوظيفي بنجاح", "success");
            } else {
                showToast("تم إضافة الموظف بنجاح", "success");
            }
        } else {
            showToast(result.message || "فشلت عملية الحفظ، يرجى التأكد من البيانات", "error");
        }
    } catch (error) {
        showToast("حدث خطأ في الاتصال بالسيرفر", "error");
    }
}

// ==========================================
// 6. دوال الحذف والـ Modal والتأكيد مع Toast
// ==========================================
function deleteUser(id) {
    userIdToDelete = id;
    const modal = document.getElementById('deleteConfirmModal');
    if (modal) modal.classList.add('active');
}

function closeDeleteModal() {
    userIdToDelete = null;
    const modal = document.getElementById('deleteConfirmModal');
    if (modal) modal.classList.remove('active');
}

async function confirmDeleteUser() {
    if (!userIdToDelete) return;

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    try {
        const response = await fetch('/users/' + userIdToDelete, {
            method: 'DELETE',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        const result = await response.json();

        if (response.ok) {
            closeDeleteModal();
            fetchUsers();
            showToast("تم حذف الموظف بنجاح ", "success");
        } else {
            showToast(result.message || "فشل حذف الموظف", "error");
        }
    } catch (error) {
        showToast("حدث خطأ أثناء الاتصال بالخادم", "error");
    }
}

// ==========================================
// 7. التحكم في المودالات (إضافة دور vs إضافة موظف جديد)
// ==========================================
function openAddRoleModal(user) {
    editingUserId = user.id;

    // تعبئة البيانات للاطلاع فقط
    setInputValue('userName', user.name || '');
    setInputValue('userEmail', user.email || '');
    setInputValue('userPhone', user.phone || '');

    // 🔒 قفل الحقول الأساسية تماماً لكي لا يتمكن المدير من تعديلها
    const nameInput = document.getElementById('userName');
    const emailInput = document.getElementById('userEmail');
    const phoneInput = document.getElementById('userPhone');
    
    if (nameInput) nameInput.disabled = true;
    if (emailInput) emailInput.disabled = true;
    if (phoneInput) phoneInput.disabled = true;

    // إخفاء حقل كلمة المرور تماماً وإلغاء إلزاميته
    const passGroup = document.getElementById('userPassword')?.closest('.form-group');
    if (passGroup) passGroup.style.display = 'none';
    
    const passInput = document.getElementById('userPassword');
    if (passInput) passInput.required = false;

    // فلترة الأدوار لإظهار الأدوار المفقودة فقط التي لا يمتلكها الموظف حالياً
    const roleSelect = document.getElementById('userRole');
    if (roleSelect) {
        const userCurrentRoles = user.roles ? user.roles.map(r => r.toString().toLowerCase()) : [];
        
        for (let i = 0; i < roleSelect.options.length; i++) {
            const optVal = roleSelect.options[i].value.toLowerCase();
            if (optVal === "") continue; 
            
            if (userCurrentRoles.includes(optVal)) {
                roleSelect.options[i].style.display = 'none';
            } else {
                roleSelect.options[i].style.display = 'block';
            }
        }
        roleSelect.value = ''; 
    }

    // تغيير العنوان
    const modalTitle = document.querySelector('#userModal .modal-header h3');
    if (modalTitle) {
        modalTitle.innerHTML = `
            <span style="display: inline-flex; align-items: center; gap: 8px;">
              <svg viewBox="0 0 24 24" fill="none" stroke="#A855F7" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="width: 22px; height: 22px; display: inline-block;">
                <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                <circle cx="8.5" cy="7" r="4"></circle>
                <line x1="20" y1="8" x2="20" y2="14"></line>
                <line x1="23" y1="11" x2="17" y2="11"></line>
              </svg>
              إضافة دور وظيفي لـ: ${user.name}
            </span>
        `;
    }

    const modal = document.getElementById('userModal');
    if (modal) modal.classList.add('active');
}

function openUserModal() {
    editingUserId = null;
    const form = document.getElementById('userForm');
    if (form) form.reset();

    // 🔓 فتح وتفعيل جميع الحقول لتكون قابلة للكتابة والإضافة بحرية تامة
    const nameInput = document.getElementById('userName');
    const emailInput = document.getElementById('userEmail');
    const phoneInput = document.getElementById('userPhone');
    
    if (nameInput) nameInput.disabled = false;
    if (emailInput) emailInput.disabled = false;
    if (phoneInput) phoneInput.disabled = false;

    // إظهار حقل كلمة المرور وجعله مطلوباً
    const passGroup = document.getElementById('userPassword')?.closest('.form-group');
    if (passGroup) passGroup.style.display = 'block';
    
    const passInput = document.getElementById('userPassword');
    if (passInput) passInput.required = true;

    // إظهار جميع خيارات الأدوار دون أي فلترة
    const roleSelect = document.getElementById('userRole');
    if (roleSelect) {
        for (let i = 0; i < roleSelect.options.length; i++) {
            roleSelect.options[i].style.display = 'block';
        }
    }

    const modalTitle = document.querySelector('#userModal .modal-header h3');
    if (modalTitle) {
        modalTitle.innerHTML = `
            <span style="display: inline-flex; align-items: center; gap: 8px;">
              <svg viewBox="0 0 24 24" fill="none" stroke="#A855F7" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="width: 22px; height: 22px; display: inline-block;">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                <circle cx="12" cy="7" r="4"></circle>
              </svg>
              إضافة موظف جديد
            </span>
        `;
    }

    const modal = document.getElementById('userModal');
    if (modal) modal.classList.add('active');
}

function closeUserModal() {
    const modal = document.getElementById('userModal');
    if (modal) modal.classList.remove('active');
}

function getRoleBadge(role) {
    const normalizedRole = (role || '').toString().toLowerCase();

    if (normalizedRole === 'admin' || normalizedRole === 'manager') {
        return { label: 'مدير ', badgeClass: 'admin' };
    } else if (normalizedRole === 'doctor') {
        return { label: 'طبيب ', badgeClass: 'doctor' };
    } else if (normalizedRole === 'receptionist' || normalizedRole === 'reception') {
        return { label: 'استقبال', badgeClass: 'receptionist' };
    } else {
        return { label: role || 'موظف', badgeClass: '' };
    }
}

function getInitials(name) {
    if (!name) return 'م';
    const parts = name.trim().split(' ');
    if (parts.length >= 2) {
        return parts[0][0] + '.' + parts[1][0];
    }
    return parts[0][0];
}

function getInputValue(id) {
    const el = document.getElementById(id);
    return el ? el.value.trim() : '';
}

function setInputValue(id, val) {
    const el = document.getElementById(id);
    if (el) el.value = val;
}

function debounce(func, wait) {
    let timeout;
    return function (...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), wait);
    };
}