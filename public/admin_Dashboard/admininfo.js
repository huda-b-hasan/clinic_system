
async function loadAdminProfile() {
    try {
        const response = await fetch('/admin/profile');
        const result = await response.json();

        // التحقق من نجاح الطلب وتوفر البيانات
        if (response.ok && result?.status === 'success') {
            const adminNameEl = document.getElementById('admin-name');
            
            // تحديث اسم الأدمن إذا وجد العنصر في الصفحة
            if (adminNameEl) {
                adminNameEl.textContent = `أهلاً ${result.data.name}`;
            }
        }
    } catch (error) {
        console.error('Error loading admin profile:', error);
        
        // عرض اسم افتراضي في حالة فشل الاتصال
        const adminNameEl = document.getElementById('admin-name');
        if (adminNameEl) {
            adminNameEl.textContent = 'مسؤول النظام';
        }
    }
}

// تنفيذ الدالة بمجرد تحميل هيكل الصفحة
document.addEventListener('DOMContentLoaded', loadAdminProfile);