// ==========================================
// 1. المتغيرات العامة للنطاق (Global State)
// ==========================================
const modal = document.getElementById('addPatientModal');
const openEditModalBtn = document.getElementById('openEditModalBtn');
let currentPatientId = null; // تخزين ID المريض المختار حالياً
let rawBirthdate = null;     // تخزين تاريخ الميلاد الخام للتعديل

// ==========================================
// 2. الأحداث الأساسية (Event Listeners)
// ==========================================
document.addEventListener('DOMContentLoaded', () => {
    fetchPatientsList();

    // ربط زر "تعديل الملف" مع النافذة المنبثقة
    if (openEditModalBtn) {
        openEditModalBtn.addEventListener('click', openEditModal);
    }

    // إرسال الفوّرم عند التعديل أو الحفظ
    const patientForm = modal ? modal.querySelector('.modal-form') : null;
    if (patientForm) {
        patientForm.addEventListener('submit', handleFormSubmit);
    }

    // إغلاق المودال عند الضغط على الإكس أو الخلفية
    const closeModalBtn = document.getElementById('closeModalBtn');
    const cancelModalBtn = document.getElementById('cancelModalBtn');
    if (closeModalBtn) closeModalBtn.addEventListener('click', closeModal);
    if (cancelModalBtn) cancelModalBtn.addEventListener('click', closeModal);
});

// دالة إغلاق المودال
function closeModal() {
    if (modal) modal.classList.remove('active');
}

// ==========================================
// 3. دالة حساب العمر
// ==========================================
function calculateAge(birthdateStr) {
    if (!birthdateStr) return 'غير مسجل';
    
    const today = new Date();
    const birthDate = new Date(birthdateStr);

    let age = today.getFullYear() - birthDate.getFullYear();
    const monthDiff = today.getMonth() - birthDate.getMonth();
    const dayDiff = today.getDate() - birthDate.getDate();

    if (monthDiff < 0 || (monthDiff === 0 && dayDiff < 0)) {
        age--;
    }

    return age;
}

// ==========================================
// 4. جلب ورسم قائمة المرضى
// ==========================================
async function fetchPatientsList() {
    try {
        const response = await fetch('/patients', {
            headers: { 'Accept': 'application/json' }
        });
        const result = await response.json();

        if (result.status === 'success') {
            renderPatientsList(result.data);
        }
    } catch (error) {
        console.error('خطأ في جلب بيانات المرضى:', error);
    }
}

function renderPatientsList(patients) {
    const listContainer = document.querySelector('.patients-list');
    listContainer.innerHTML = '';

    if (patients.length === 0) {
        listContainer.innerHTML = '<p class="text-muted">لا يوجد مرضى مسجلين حالياً.</p>';
        return;
    }

    patients.forEach(patient => {
        const card = document.createElement('div');
        card.className = 'patient-card';
        card.onclick = () => fetchPatientDetails(patient.id, card);

        card.innerHTML = `
            <span class="patient-name">${patient.name}</span>
            <button class="btn-view-details">عرض التفاصيل ←</button>
        `;
        listContainer.appendChild(card);
    });
}

// ==========================================
// 5. جلب عرض تفاصيل مريض محدد
// ==========================================
async function fetchPatientDetails(patientId, cardElement) {
    try {
        const response = await fetch(`/patients/${patientId}`, {
            headers: { 'Accept': 'application/json' }
        });
        const result = await response.json();
        
        if (result.status === 'success') {
            const p = result.data;
            
            // حفظ البيانات بالمتغيرات العامة لاستخدامها في التعديل
            currentPatientId = p.id;
            rawBirthdate = p.birthdate;

            document.getElementById('emptyState').style.display = 'none';
            document.getElementById('detailsContent').style.display = 'block';

            // تعبئة البيانات المفصلة
            document.getElementById('detName').textContent = p.name;
            document.getElementById('detPhone').textContent = p.phone || 'غير مسجل';
            document.getElementById('detGender').textContent = p.gender === 'female' ? 'أنثى' : 'ذكر';
            
            // تم تصحيح الـ Variable هنا لـ p.birthdate
            document.getElementById('detBirthdate').textContent = p.birthdate ? `${calculateAge(p.birthdate)} سنة` : 'غير مسجل';
            
            document.getElementById('detAddress').textContent = p.address ? p.address : 'غير مسجل';
            document.getElementById('detNotes').textContent = p.medical_notes ? p.medical_notes : 'لا توجد ملاحظات طبية مسجلة لهذا المريض.';

            // إضافة التأثير البصري والكلاس النشط
            document.querySelectorAll('.patient-card').forEach(c => c.classList.remove('active-card'));
            if (cardElement) cardElement.classList.add('active-card');
        }
    } catch (error) {
        console.error('خطأ في جلب تفاصيل المريض:', error);
    }
}

// ==========================================
// 6. منطق التعديل والـ Modal (جديد)
// ==========================================

// فتح النافذة بوضع التعديل مع تعبئة القيم
function openEditModal() {
    if (!currentPatientId) {
        alert('الرجاء اختيار مريض أولاً للتعديل');
            const toast = document.getElementById('toast');
            toast.textContent = 'الرجاء اختيار مريض أولاً للتعديل';
            toast.classList.add('show');

            setTimeout(() => {
                toast.classList.remove('show');
            }, 3000);
        return;
    }

    // تغيير عناوين المودال
    modal.querySelector('.modal-header h3').textContent = 'تعديل بيانات المريض';
    modal.querySelector('.btn-submit-p').textContent = 'حفظ التعديلات';

    // تعبئة المدخلات من القيمة المعروضة حالياً
    document.getElementById('name').value = document.getElementById('detName').textContent.trim();
    document.getElementById('phone').value = document.getElementById('detPhone').textContent.trim();
    
    const genderText = document.getElementById('detGender').textContent.trim();
    document.getElementById('gender').value = (genderText === 'أنثى') ? 'female' : 'male';
    
    document.getElementById('birthdate').value = rawBirthdate || '';
    
    const addressText = document.getElementById('detAddress').textContent.trim();
    document.getElementById('address').value = (addressText !== 'غير مسجل') ? addressText : '';
    
    const notesText = document.getElementById('detNotes').textContent.trim();
    document.getElementById('medical_notes').value = (notesText !== 'لا توجد ملاحظات طبية مسجلة لهذا المريض.') ? notesText : '';

    // إظهار المودال
    modal.classList.add('active');
}

// إرسال طلب التعديل للسيرفر
async function handleFormSubmit(e) {
    e.preventDefault();

    if (!currentPatientId) return;

    const formData = {
        name: document.getElementById('name').value,
        phone: document.getElementById('phone').value,
        gender: document.getElementById('gender').value,
        birthdate: document.getElementById('birthdate').value || null,
        address: document.getElementById('address').value || null,
        medical_notes: document.getElementById('medical_notes').value || null,
    };

    try {
        const response = await fetch(`/patients/update/${currentPatientId}`, {
            method: 'POST', // أو PUT بحسب التعريف في الـ Routes
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(formData)
        });

        const result = await response.json();

        if (response.ok && result.status === 'success') {
            console.log('تم تحديث بيانات المريض بنجاح');
            const toast = document.getElementById('toast');
            toast.textContent = 'تم تحديث بيانات المريض بنجاح';
            toast.classList.add('show');

            setTimeout(() => {
                toast.classList.remove('show');
            }, 3000);
            closeModal();
            
            // تحديث القائمة والتفاصيل بدون إنعاش الصفحة
            fetchPatientsList();
            fetchPatientDetails(currentPatientId);
        } else {
            alert('حدث خطأ: ' + (result.message || 'تعذر تحديث البيانات'));
        }
    } catch (error) {
        console.error('خطأ في الاتصال بالخادم:', error);
    }
}
// store
document.addEventListener('DOMContentLoaded', () => {
    // 1. عناصر النافذة المنبثقة (Modal)
    const addModal = document.getElementById('addPatientModal');
    const openModalBtn = document.getElementById('openModalBtn');
    const closeModalBtn = document.getElementById('closeModalBtn');
    const cancelModalBtn = document.getElementById('cancelModalBtn');
    const addPatientForm = document.querySelector('.modal-form');
    const toast = document.getElementById('toast');

    // فتح النافذة المنبثقة
    openModalBtn?.addEventListener('click', () => {
        addModal.classList.add('active'); // أو style.display = 'flex' حسب الـ CSS لديك
    });

    // دالة إغلاق النافذة المنبثقة وتنظيف البيانات
    const closeModal = () => {
        addModal.classList.remove('active');
        addPatientForm.reset();
    };

    closeModalBtn?.addEventListener('click', closeModal);
    cancelModalBtn?.addEventListener('click', closeModal);

    // إغلاق Modal عند النقر خارجه
    window.addEventListener('click', (e) => {
        if (e.target === addModal) {
            closeModal();
        }
    });

    // 2. معالجة تقديم النموذج (Submit Form)
    addPatientForm?.addEventListener('submit', async (e) => {
        e.preventDefault();

        // تجميع البيانات من المدخلات
        const formData = {
            name: document.getElementById('name').value.trim(),
            phone: document.getElementById('phone').value.trim(),
            gender: document.getElementById('gender').value,
            birthdate: document.getElementById('birthdate').value || null,
            address: document.getElementById('address').value.trim() || null,
            medical_notes: document.getElementById('medical_notes').value.trim() || null,
        };

        try {
            const response = await fetch('/patients/add', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    // 'Authorization': `Bearer ${localStorage.getItem('token')}` // في حال وجود مصادقة
                },
                body: JSON.stringify(formData)
            });

            const result = await response.json();

            if (response.ok) {
                // إغلاق النافذة المنبثقة
                closeModal();

                // إظهار رسالة النجاح (Toast)
                showToast("تمت إضافة ملف المريض بنجاح!");

                // إضافة المريض الجديد ديناميكياً لقائمة العرض
                appendPatientToList(result.data || formData);
            } else {
                alert(result.message || 'حدث خطأ أثناء حفظ البيانات.');
            }
        } catch (error) {
            console.error('Error adding patient:', error);
            alert('تعذر الاتصال بالسيرفر. يرجى المحاولة لاحقاً.');
        }
    });

    // دالة إظهار الـ Toast
    function showToast(message) {
        if (!toast) return;
        toast.textContent = message;
        toast.classList.add('show');
        setTimeout(() => {
            toast.classList.remove('show');
        }, 3000);
    }

    // دالة إضافة المريض الجديد إلى القائمة المعروضة بدون إعادة تحميل الصفحة
    function appendPatientToList(patient) {
        const patientsList = document.querySelector('.patients-list');
        const card = document.createElement('div');
        card.className = 'patient-card';
        
        // إعداد الحدث لتمرير بيانات المريض لعرض التفاصيل
        card.onclick = () => {
            showPatientDetails(
                patient.id || '',
                patient.name,
                patient.phone,
                patient.gender === 'female' ? 'أنثى' : 'ذكر',
                patient.birthdate || 'غير محدد',
                patient.address || 'غير محدد',
                patient.medical_notes || 'لا يوجد ملاحظات'
            );
        };

        card.innerHTML = `
            <span class="patient-name">${patient.name}</span>
            <button class="btn-view-details">عرض التفاصيل ←</button>
        `;

        patientsList.prepend(card); // إضافة المريض في أول القائمة
    }
});