// ==========================================
// إدارة ومراقبة المودال والتوست لإلغاء المواعيد
// ==========================================
let appointmentIdToCancel = null;

// دالة فتح المودال وتحديد رقم الموعد
function openCancelModal(id) {
    appointmentIdToCancel = id;
    const modal = document.getElementById('cancelModal');
    if (modal) {
        modal.style.display = 'flex';
    }
}

// دالة إغلاق المودال وتفريغ الحقول
function closeCancelModal() {
    const modal = document.getElementById('cancelModal');
    if (modal) {
        modal.style.display = 'none';
    }
    const reasonInput = document.getElementById('cancellation_reason');
    if (reasonInput) {
        reasonInput.value = ""; // تفريغ حقل النص
    }
    appointmentIdToCancel = null;
}

// تفعيل أحداث المودال عند تحميل الصفحة
document.addEventListener("DOMContentLoaded", () => {
    
    // 1. مراقبة الضغط على أزرار الإلغاء ديناميكياً (حتى لو تم إنشاؤها بعد تحميل الصفحة)
    document.addEventListener('click', function(event) {
        if (event.target && event.target.classList.contains('cancel-btn')) {
            const id = event.target.getAttribute('data-id');
            if (id) {
                openCancelModal(id);
            }
        }
    });

    // 2. ربط زر الإغلاق العلوي (x)
    const closeBtn = document.getElementById('closeModalBtn');
    if (closeBtn) {
        closeBtn.addEventListener('click', closeCancelModal);
    }

    // 3. ربط زر التراجع السفلي الناعم
    const backBtn = document.getElementById('backModalBtn');
    if (backBtn) {
        backBtn.addEventListener('click', closeCancelModal);
    }

    // 4. ربط زر تأكيد الإلغاء الفعلي وإرسال البيانات للـ API
    const confirmBtn = document.getElementById('confirmCancelBtn');
    if (confirmBtn) {
        confirmBtn.addEventListener('click', () => {
            if (!appointmentIdToCancel) return;

            const cancellationReasonInput = document.getElementById('cancellation_reason');
            const cancellationReason = cancellationReasonInput ? cancellationReasonInput.value : '';
            const toast = document.getElementById('toastNotification');

            // إرسال طلب الإلغاء للـ API بالـ Method PUT
            fetch(`/appointments/${appointmentIdToCancel}/cancel`, { 
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                },
                body: JSON.stringify({
                    cancellation_reason: cancellationReason
                })
            })
            .then(response => response.json())
            .then(data => {
                closeCancelModal(); // إغلاق المودال فوراً لتوفير تجربة مستخدم سريعة

                if (data.success) {
                    if (toast) {
                        toast.style.background = '#28a745'; // أخضر للنجاح
                        toast.textContent = `✓ ${data.message}`; 
                        toast.style.display = 'block';
                    }

                    // الانتظار 4 ثوانٍ ثم تحديث الصفحة لرؤية التغيير
                    setTimeout(() => {
                        if (toast) toast.style.display = 'none';
                        location.reload(); 
                    }, 4000);

                } else {
                    // في حال رفض السيرفر للإلغاء (أحمر)
                    if (toast) {
                        toast.style.background = '#dc3545'; 
                        toast.textContent = `⚠️ ${data.message}`; 
                        toast.style.display = 'block';
                    }

                    setTimeout(() => {
                        if (toast) toast.style.display = 'none';
                    }, 4000);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                closeCancelModal();

                // في حال حدوث خطأ غير متوقع بالاتصال (أحمر)
                if (toast) {
                    toast.style.background = '#dc3545'; 
                    toast.textContent = '⚠️ حدث خطأ غير متوقع أثناء الاتصال بالخادم.'; 
                    toast.style.display = 'block';
                }

                setTimeout(() => {
                    if (toast) toast.style.display = 'none';
                }, 4000);
            });
        });
    }
});