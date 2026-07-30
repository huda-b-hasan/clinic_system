document.addEventListener("DOMContentLoaded", function () {
    const profileForm = document.getElementById("profileForm");
    const userInitials = document.getElementById("userInitials");
    const patientHeaderName = document.getElementById("patientHeaderName");

    const currentPasswordInput = document.getElementById("current_password");
    const newPasswordInput = document.getElementById("new_password");
    const newPasswordConfirmInput = document.getElementById("new_password_confirmation");

    function showToast(message, type = 'success') {
        const toast = document.getElementById('toast');
        if (!toast) return;

        // إعادة تعيين الكلاسات لإزالة الألوان القديمة
        toast.className = 'toast'; 

        // تطبيق النص والنوع (success, error, warning)
        toast.textContent = message;
        toast.classList.add(type, 'show');

        // إخفاء الـ Toast تلقائياً بعد 3 ثوانٍ
        setTimeout(() => {
            toast.classList.remove('show');
        }, 3000);
    }

    // 1. جلب البيانات عند تحميل الصفحة
    function fetchProfileData() {
        fetch('/profile')
            .then(response => {
                if (!response.ok) throw new Error('فشل في جلب البيانات.');
                return response.json();
            })
            .then(data => {
                if (document.getElementById("name")) document.getElementById("name").value = data.profile.name || "";
                if (document.getElementById("phone")) document.getElementById("phone").value = data.profile.phone || "";
                if (document.getElementById("email")) document.getElementById("email").value = data.email || "";
                if (document.getElementById("birthdate")) document.getElementById("birthdate").value = data.profile.birthdate || "";

                const genderText = data.profile.gender === 'female' ? 'أنثى' : (data.profile.gender === 'male' ? 'ذكر' : 'غير محدد');
                if (document.getElementById("genderStatic")) document.getElementById("genderStatic").value = genderText;
                if (document.getElementById("userRoleBadge")) document.getElementById("userRoleBadge").innerText = data.role === 'Patient' ? 'مريض/ة' : data.role;

                if (document.getElementById("address")) document.getElementById("address").value = data.profile.address || "";
                if (document.getElementById("medical_notes")) document.getElementById("medical_notes").value = data.profile.medical_notes || "";

                if (data.profile.name) {
                    if (patientHeaderName) patientHeaderName.innerText = data.profile.name;
                    if (userInitials) userInitials.innerText = data.profile.name.charAt(0).toUpperCase();
                }
            })
            .catch(error => {
                console.error("Error fetching profile:", error);
                showToast("حدث خطأ أثناء تحميل بيانات الملف الشخصي.", "error");
            });
    }

    // تنفيذ الجلب عند تحميل الصفحة
    fetchProfileData();

    // 2. حفظ وتحديث البيانات
    profileForm.addEventListener("submit", function (e) {
        e.preventDefault();

        const currentPassword = currentPasswordInput ? currentPasswordInput.value : '';
        const newPassword = newPasswordInput ? newPasswordInput.value : '';
        const newPasswordConfirm = newPasswordConfirmInput ? newPasswordConfirmInput.value : '';

        // فحص مطابقة كلمة المرور الجديدة بالفرونت إند قبل الإرسال
        if (newPassword && (newPassword !== newPasswordConfirm)) {
            showToast('كلمة المرور الجديدة غير مطابقة لتأكيد كلمة المرور.', 'warning');
            return;
        }

        const formData = {
            _method: 'PUT',
            name: document.getElementById("name") ? document.getElementById("name").value : '',
            phone: document.getElementById("phone") ? document.getElementById("phone").value : '',
            birthdate: document.getElementById("birthdate") ? document.getElementById("birthdate").value || null : null,
            address: document.getElementById("address") ? document.getElementById("address").value : null,
            medical_notes: document.getElementById("medical_notes") ? document.getElementById("medical_notes").value : null,
            current_password: currentPassword || null,
            new_password: newPassword || null,
            new_password_confirmation: newPasswordConfirm || null
        };

        fetch('/profile/update', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            },
            body: JSON.stringify(formData)
        })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    // إظهار توست النجاح باللون الأخضر
                    showToast(data.message || 'تم تحديث البيانات بنجاح!', 'success');

                    // تحديث اسمها والأحرف الأولى مباشرة في الـ Header
                    if (patientHeaderName) patientHeaderName.innerText = formData.name;
                    if (userInitials && formData.name) userInitials.innerText = formData.name.charAt(0).toUpperCase();

                    // تفريغ حقول كلمة المرور بعد النجاح
                    if (currentPasswordInput) currentPasswordInput.value = '';
                    if (newPasswordInput) newPasswordInput.value = '';
                    if (newPasswordConfirmInput) newPasswordConfirmInput.value = '';

                    // إعادة جلب البيانات لتحديث أي معلومات إضافية
                    fetchProfileData();
                } else {
                    // إظهار توست الخطأ (مثل كلمة المرور الحالية غير صحيحة) باللون الأحمر
                    showToast(data.message || 'حدث خطأ ما أثناء التحديث.', 'error');
                }
            })
            .catch(error => {
                console.error("Error updating profile:", error);
                showToast('فشل الاتصال بالسيرفر، يرجى المحاولة لاحقاً.', 'error');
            });
    });
});