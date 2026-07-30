// ==========================================
// 1. المتغيرات العامة للنطاق (Global State)
// ==========================================
const modal = document.getElementById('addPatientModal');
const openEditModalBtn = document.getElementById('openEditModalBtn');
const openModalBtn = document.getElementById('openModalBtn'); // زر إضافة مريض جديد
let currentPatientId = null; // تخزين ID المريض المختار حالياً (null تعني وضع الإضافة)
let rawBirthdate = null;     // تخزين تاريخ الميلاد الخام للتعديل

// ==========================================
// 2. الأحداث الأساسية (Event Listeners)
// ==========================================
document.addEventListener('DOMContentLoaded', () => {
    fetchPatientsList();

    // فتح المودال لإضافة مريض جديد
    if (openModalBtn) {
        openModalBtn.addEventListener('click', openAddModal);
    }

    // فتح المودال لتعديل المريض الحالي
    if (openEditModalBtn) {
        openEditModalBtn.addEventListener('click', openEditModal);
    }

    // إدارة إرسال النموذج (تحديد هل هو إضافة أم تعديل)
    const patientForm = modal ? modal.querySelector('.modal-form') : null;
    if (patientForm) {
        patientForm.addEventListener('submit', handleFormSubmit);
    }

    // إغلاق المودال
    const closeModalBtn = document.getElementById('closeModalBtn');
    const cancelModalBtn = document.getElementById('cancelModalBtn');
    if (closeModalBtn) closeModalBtn.addEventListener('click', closeModal);
    if (cancelModalBtn) cancelModalBtn.addEventListener('click', closeModal);

    // إغلاق المودال عند النقر خارجه
    window.addEventListener('click', (e) => {
        if (e.target === modal) {
            closeModal();
        }
    });

    // إعداد البحث المباشر
    initLiveSearch();
});

// ==========================================
// 3. إدارة المودال (فتح / إغلاق)
// ==========================================

// فتح النافذة بوضع الإضافة
function openAddModal() {
    currentPatientId = null; // تفريغ الـ ID للتمييز بأنه مريض جديد
    const form = modal.querySelector('.modal-form');
    if (form) form.reset();

    // ضبط العناوين
    modal.querySelector('.modal-header h3').textContent = 'إضافة مريض جديد';
    const submitBtn = modal.querySelector('.btn-submit-p') || modal.querySelector('button[type="submit"]');
    if (submitBtn) submitBtn.textContent = 'حفظ البيانات';

    modal.classList.add('active');
}

// فتح النافذة بوضع التعديل
function openEditModal() {
    if (!currentPatientId) {
        showToast('الرجاء اختيار مريض أولاً للتعديل', 'error');
        return;
    }

    // ضبط العناوين
    modal.querySelector('.modal-header h3').textContent = 'تعديل بيانات المريض';
    const submitBtn = modal.querySelector('.btn-submit-p') || modal.querySelector('button[type="submit"]');
    if (submitBtn) submitBtn.textContent = 'حفظ التعديلات';

    // تعبئة البيانات المفصلة في الحقول
    document.getElementById('name').value = document.getElementById('detName').textContent.trim();
    document.getElementById('phone').value = document.getElementById('detPhone').textContent.trim();
    
    const genderText = document.getElementById('detGender').textContent.trim();
    document.getElementById('gender').value = (genderText === 'أنثى') ? 'female' : 'male';
    
    document.getElementById('birthdate').value = rawBirthdate || '';
    
    const addressText = document.getElementById('detAddress').textContent.trim();
    document.getElementById('address').value = (addressText !== 'غير مسجل') ? addressText : '';
    
    const notesText = document.getElementById('detNotes').textContent.trim();
    document.getElementById('medical_notes').value = (notesText !== 'لا توجد ملاحظات طبية مسجلة لهذا المريض.') ? notesText : '';

    modal.classList.add('active');
}

// إغلاق المودال وتصفير البيانات
function closeModal() {
    if (modal) modal.classList.remove('active');
    const form = modal ? modal.querySelector('.modal-form') : null;
    if (form) form.reset();
}

// ==========================================
// 4. معالجة حفظ البيانات (إضافة / تعديل)
// ==========================================
async function handleFormSubmit(e) {
    e.preventDefault();

    const formData = {
        name: document.getElementById('name').value.trim(),
        phone: document.getElementById('phone').value.trim(),
        gender: document.getElementById('gender').value,
        birthdate: document.getElementById('birthdate').value || null,
        address: document.getElementById('address').value.trim() || null,
        medical_notes: document.getElementById('medical_notes').value.trim() || null,
    };

    // تحديد المسار وطريقة الطلب بناءً على وجود ID المريض
    const isEdit = currentPatientId !== null;
    const url = isEdit ? `/patients/update/${currentPatientId}` : '/patients/add';

    try {
        const response = await fetch(url, {
            method: 'POST', 
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(formData)
        });

        const result = await response.json();

        if (response.ok && (result.status === 'success' || result.success)) {
            showToast(isEdit ? 'تم تحديث بيانات المريض بنجاح' : 'تمت إضافة ملف المريض بنجاح');
            closeModal();
            
            // تحديث قائمة المرضى
            fetchPatientsList();

            // إذا كان تعديلاً، ننعش التفاصيل المعروضة
            if (isEdit) {
                fetchPatientDetails(currentPatientId);
            }
        } else {
            alert('حدث خطأ: ' + (result.message || 'تعذر حفظ البيانات'));
        }
    } catch (error) {
        console.error('خطأ في الاتصال بالخادم:', error);
        alert('تعذر الاتصال بالسيرفر. يرجى المحاولة لاحقاً.');
    }
}

// ==========================================
// 5. حساب العمر ودعم التنبيهات (Toast)
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

function showToast(message) {
    const toast = document.getElementById('toast');
    if (!toast) return;
    toast.textContent = message;
    toast.classList.add('show');
    setTimeout(() => {
        toast.classList.remove('show');
    }, 3000);
}

// ==========================================
// 6. جلب ورسم قائمة وتفاصيل المرضى
// ==========================================
async function fetchPatientsList() {
    try {
        const response = await fetch('/patients', {
            headers: { 'Accept': 'application/json' }
        });
        const result = await response.json();

        if (result.status === 'success' || result.success) {
            renderPatientsList(result.data || []);
        }
    } catch (error) {
        console.error('خطأ في جلب بيانات المرضى:', error);
    }
}

function renderPatientsList(patients) {
    const listContainer = document.querySelector('.patients-list');
    if (!listContainer) return;
    listContainer.innerHTML = '';

    if (patients.length === 0) {
        listContainer.innerHTML = '<p class="text-muted">لا يوجد مرضى مسجلين حالياً.</p>';
        return;
    }

    patients.forEach(patient => {
        const card = document.createElement('div');
        card.className = 'patient-card';
        if (currentPatientId === patient.id) card.classList.add('active-card');
        
        card.onclick = () => fetchPatientDetails(patient.id, card);

        card.innerHTML = `
            <span class="patient-name">${patient.name}</span>
            <button class="btn-view-details">عرض التفاصيل ←</button>
        `;
        listContainer.appendChild(card);
    });
}

async function fetchPatientDetails(patientId, cardElement = null) {
    try {
        const response = await fetch(`/patients/${patientId}`, {
            headers: { 'Accept': 'application/json' }
        });
        const result = await response.json();
        
        if (result.status === 'success' || result.success) {
            const p = result.data;
            
            currentPatientId = p.id;
            rawBirthdate = p.birthdate;

            document.getElementById('emptyState').style.display = 'none';
            document.getElementById('detailsContent').style.display = 'block';

            showPatientDetails(
                p.id,
                p.name,
                p.phone,
                p.gender,
                p.birthdate ? `${calculateAge(p.birthdate)} سنة` : 'غير مسجل',
                p.address,
                p.medical_notes
            );

            // تحديد الكارت النشط
            document.querySelectorAll('.patient-card').forEach(c => c.classList.remove('active-card'));
            if (cardElement) {
                cardElement.classList.add('active-card');
            }
        }
    } catch (error) {
        console.error('خطأ في جلب تفاصيل المريض:', error);
    }
}

function showPatientDetails(id, name, phone, gender, birthdateText, address, notes) {
    const emptyState = document.getElementById('emptyState');
    const detailsContent = document.getElementById('detailsContent');

    if (emptyState) emptyState.style.display = 'none';
    if (detailsContent) detailsContent.style.display = 'block';

    document.getElementById('detName').textContent = name || 'غير مسجل';
    document.getElementById('detPhone').textContent = phone || 'غير مسجل';
    document.getElementById('detGender').textContent = (gender === 'female' || gender === 'أنثى') ? 'أنثى' : 'ذكر';
    document.getElementById('detBirthdate').textContent = birthdateText || 'غير مسجل';
    document.getElementById('detAddress').textContent = address || 'غير مسجل';
    document.getElementById('detNotes').textContent = notes || 'لا توجد ملاحظات طبية مسجلة لهذا المريض.';
}

// ==========================================
// 7. منطق البحث المباشر (Live Search)
// ==========================================
function initLiveSearch() {
    const searchInput = document.getElementById('patientSearchInput');
    const dropdown = document.getElementById('searchResultsDropdown');
    let searchDebounceTimer;

    if (!searchInput || !dropdown) return;

    searchInput.addEventListener('input', (e) => {
        const query = e.target.value.trim();

        clearTimeout(searchDebounceTimer);

        if (query.length === 0) {
            dropdown.style.display = 'none';
            dropdown.innerHTML = '';
            return;
        }

        searchDebounceTimer = setTimeout(async () => {
            try {
                const response = await fetch(`/get-patients-list?q=${encodeURIComponent(query)}`, {
                    headers: { 'Accept': 'application/json' }
                });

                const result = await response.json();

                if (result.status && result.data.length > 0) {
                    renderSearchResults(result.data);
                } else {
                    dropdown.innerHTML = '<div class="no-results-item">لم يتم العثور على مريض بهذا الاسم/الرقم</div>';
                    dropdown.style.display = 'block';
                }
            } catch (error) {
                console.error('Error fetching patients:', error);
            }
        }, 300);
    });

    function renderSearchResults(patients) {
        dropdown.innerHTML = '';

        patients.forEach(patient => {
            const item = document.createElement('div');
            item.className = 'search-result-item';

            item.innerHTML = `
                <span class="search-result-name">${patient.name}</span>
                <span class="search-result-phone">${patient.phone || ''}</span>
            `;

            item.addEventListener('click', () => {
                fetchPatientDetails(patient.id);
                dropdown.style.display = 'none';
                searchInput.value = '';
            });

            dropdown.appendChild(item);
        });

        dropdown.style.display = 'block';
    }

    document.addEventListener('click', (e) => {
        if (!searchInput.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.style.display = 'none';
        }
    });
}