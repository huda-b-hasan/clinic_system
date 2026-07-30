// جلب عناصر واجهة المستخدم
const profileForm = document.getElementById('profileForm');
const nameInput = document.getElementById('name');
const phoneInput = document.getElementById('phone');
const emailInput = document.getElementById('email');

const currentPasswordInput = document.getElementById('current_password');
const newPasswordInput = document.getElementById('new_password');
const newPasswordConfirmInput = document.getElementById('new_password_confirmation');

const userInitials = document.getElementById('userInitials');
const patientHeaderName = document.getElementById('patientHeaderName');
const userRoleBadge = document.getElementById('userRoleBadge');

function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    if (!toast) return;

    // إعادة تعيين الكلاسات لإزالة الألوان القديمة
    toast.className = 'toast'; 

    // تطبيق النص والنوع (success, error, warning, info)
    toast.textContent = message;
    toast.classList.add(type, 'show');

    // إخفاء الـ Toast تلقائياً بعد 3 ثوانٍ
    setTimeout(() => {
        toast.classList.remove('show');
    }, 3000);
}

// 1. جلب بيانات الملف الشخصي عند تحميل الصفحة
async function fetchProfileData() {
    try {
        const response = await fetch('/profile', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }
        });

        if (!response.ok) {
            throw new Error('فشل في جلب بيانات الملف الشخصي.');
        }

        const data = await response.json();

        nameInput.value = data.profile.name || '';
        phoneInput.value = data.profile.phone || '';
        emailInput.value = data.email || '';
        
        patientHeaderName.textContent = data.profile.name || 'مستخدِم لافندر';
        userRoleBadge.textContent = data.role === 'Patient' ? 'مريض/ة' : data.role;

        if (data.profile.name) {
            userInitials.textContent = data.profile.name.charAt(0).toUpperCase();
        }

    } catch (error) {
        console.error('Error:', error);
        showToast('حدث خطأ أثناء تحميل بيانات الملف الشخصي.', 'error');
    }
}

document.addEventListener('DOMContentLoaded', fetchProfileData);

// 2. معالجة إرسال نموذج التحديث
profileForm.addEventListener('submit', async (e) => {
    e.preventDefault();

    // 1. فحص مطابقة كلمة المرور بالفرونت إند قبل الإرسال
    if (newPasswordInput.value && (newPasswordInput.value !== newPasswordConfirmInput.value)) {
        showToast('كلمة المرور الجديدة غير مطابقة لتأكيد كلمة المرور.', 'warning');
        return;
    }

    // 2. بناء الكائن وإضافة حقول كلمة المرور
    const formData = {
        name: nameInput.value,
        phone: phoneInput.value,
        current_password: currentPasswordInput.value || null,
        new_password: newPasswordInput.value || null,
        new_password_confirmation: newPasswordConfirmInput.value || null,
        birthdate: null,
        address: null,
        medical_notes: null
    };

    try {
        const response = await fetch('/profile/update', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            },
            body: JSON.stringify(formData)
        });

        const result = await response.json();

        if (response.ok && result.status === 'success') {
            // إظهار توست النجاح (أخضر)
            showToast(result.message || 'تم تحديث ملفكِ الشخصي بنجاح!', 'success');

            // تفريغ حقول كلمات المرور بعد النجاح
            currentPasswordInput.value = '';
            newPasswordInput.value = '';
            newPasswordConfirmInput.value = '';

            // إعادة جلب البيانات
            fetchProfileData(); 
        } else {
            // إظهار توست الخطأ القادم من السيرفر (مثل: كلمة المرور الحالية غير صحيحة) باللون الأحمر
            showToast(result.message || 'حدث خطأ ما أثناء التحديث.', 'error');
        }

    } catch (error) {
        console.error('Error:', error);
        showToast('فشل الاتصال بالسيرفر، يرجى المحاولة لاحقاً.', 'error');
    }
});