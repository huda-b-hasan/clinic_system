// دالة لجلب بيانات الأدمن وعرضها في الهيدر
async function loadAdminProfile() {
    try {
        const response = await fetch(`/admin/profile`); 
        const result = await response.json();

        if (response.ok && result.status === 'success') {
            const adminNameElement = document.getElementById('admin-name');
            console.log(adminNameElement)
            if (adminNameElement) {
                adminNameElement.textContent = ` اهلا ${result.data.name}` ; 
            }
        }
        console.log(result)
    } catch (error) {
        console.error('خطأ في جلب بيانات الأدمن:', error);
        const adminNameElement = document.getElementById('adminName');
        if (adminNameElement) {
            adminNameElement.textContent = 'مسؤول النظام'; // قيمة احتياطية في حال الخطأ
        }
    }
}

// استدعاء الدالة عند تحميل الصفحة مباشرة
document.addEventListener('DOMContentLoaded', () => {
    loadAdminProfile();
});