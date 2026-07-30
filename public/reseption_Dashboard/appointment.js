// متغيرات عامة لحفظ المواعيد والـ Patients Map وقيم الخصم
let allAppointmentsData = [];
let patientsMap = {};
let searchTimeout = null;
let promoTimeout = null;
let currentDiscount = { type: null, value: 0 }; 

document.addEventListener('DOMContentLoaded', () => {
    loadTreatments();
    loadDoctors();
    fetchAppointments();
    setupPatientSearch();

    const form = document.getElementById('appointmentForm');
    if (form) {
        form.addEventListener('submit', handleAppointmentSubmit);
    }

    const treatmentSelect = document.getElementById('treatmentSelect');
    if (treatmentSelect) {
        treatmentSelect.addEventListener('change', () => {
            calculateSummaryPrice();
        });
    }

    const promoCodeInput = document.getElementById('promoCodeInput');
    if (promoCodeInput) {
        promoCodeInput.addEventListener('input', handlePromoCodeInput);
    }

    const backBtn = document.getElementById('backModalBtn');
    const closeBtn = document.getElementById('closeModalBtn');
    if (backBtn) backBtn.addEventListener('click', closeCancelModal);
    if (closeBtn) closeBtn.addEventListener('click', closeCancelModal);
});

/**
 * 1. جلب الخدمات
 */
async function loadTreatments() {
    const select = document.getElementById('treatmentSelect');
    if (!select) return;

    try {
        const response = await fetch('/treatments', {
            headers: { 'Accept': 'application/json' }
        });

        if (!response.ok) throw new Error(`HTTP Error: ${response.status}`);

        const treatments = await response.json();
        const items = Array.isArray(treatments) ? treatments : (treatments.data || []);

        select.innerHTML = '';

        items.forEach(item => {
            const option = document.createElement('option');
            option.value = item.id;
            const price = item.discount_price ?? item.price ?? item.base_price ?? 0;
            option.dataset.price = price;
            option.textContent = `${item.name} (${Number(price).toLocaleString()} ل.س)`;
            select.appendChild(option);
        });

        calculateSummaryPrice();
    } catch (error) {
        console.error('خطأ أثناء تحميل الخدمات:', error);
    }
}

/**
 * 2. جلب الأطباء
 */
async function loadDoctors() {
    const select = document.getElementById('doctorSelect');
    if (!select) return;

    try {
        const response = await fetch('/doctors', {
            headers: { 'Accept': 'application/json' }
        });

        if (!response.ok) throw new Error(`HTTP Error: ${response.status}`);

        const result = await response.json();
        const doctors = result.data || (Array.isArray(result) ? result : []);

        select.innerHTML = '<option value="">اختر الطبيبة...</option>';
        doctors.forEach(doctor => {
            const option = document.createElement('option');
            option.value = doctor.id;
            option.textContent = doctor.name;
            select.appendChild(option);
        });
    } catch (error) {
        console.error('خطأ أثناء تحميل الأطباء:', error);
    }
}

/**
 * 3. التعامل مع كود الخصم
 */
function handlePromoCodeInput(e) {
    clearTimeout(promoTimeout);
    const code = e.target.value.trim();
    
    if (!code || code.length === 0) {
        currentDiscount = { type: null, value: 0 };
        calculateSummaryPrice();
        return;
    }

    promoTimeout = setTimeout(async () => {
        const currentCode = e.target.value.trim();
        if (!currentCode) {
            currentDiscount = { type: null, value: 0 };
            calculateSummaryPrice();
            return;
        }

        const patientId = document.getElementById('selectedPatientId')?.value || null;
        const treatmentSelect = document.getElementById('treatmentSelect');
        const treatmentId = treatmentSelect ? treatmentSelect.value : null;

        try {
            const response = await fetch('/validate-promocode', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                },
                body: JSON.stringify({ 
                    code: currentCode, 
                    patient_id: patientId,
                    treatment_id: treatmentId 
                })
            });

            const result = await response.json();

            if (response.ok && result.status === 'success') {
                currentDiscount = {
                    type: result.discount_type,
                    value: parseFloat(result.discount_value || 0)
                };
                showToast(result.message || 'تم تطبيق الخصم بنجاح!', 'success');
            } else {
                currentDiscount = { type: null, value: 0 };
                showToast(result.message || 'كود الخصم غير صالح أو منتهي الصلاحية', 'error');
            }
        } catch (error) {
            console.error('خطأ في فحص كود الخصم:', error);
            currentDiscount = { type: null, value: 0 };
            showToast('حدث خطأ أثناء فحص الكود', 'error');
        }

        calculateSummaryPrice();
    }, 500);
}

/**
 * 4. حساب السعر الديناميكي بالملخص
 */
function calculateSummaryPrice() {
    const select = document.getElementById('treatmentSelect');
    const originalPriceEl = document.getElementById('originalPrice');
    const discountAmountEl = document.getElementById('discountAmount');
    const finalPriceEl = document.getElementById('finalPrice');

    if (!select || !originalPriceEl || !finalPriceEl) return;

    let totalOriginal = 0;
    Array.from(select.selectedOptions).forEach(option => {
        totalOriginal += parseFloat(option.dataset.price || 0);
    });

    let discountCalculated = 0;
    if (currentDiscount.type === 'percentage') {
        discountCalculated = (totalOriginal * currentDiscount.value) / 100;
    } else if (currentDiscount.type === 'fixed') {
        discountCalculated = currentDiscount.value;
    }

    if (discountCalculated > totalOriginal) {
        discountCalculated = totalOriginal;
    }

    const finalTotal = totalOriginal - discountCalculated;

    originalPriceEl.textContent = `${totalOriginal.toLocaleString()} ل.س`;
    if (discountAmountEl) {
        discountAmountEl.textContent = `${discountCalculated.toLocaleString()} ل.س`;
    }
    finalPriceEl.textContent = `${finalTotal.toLocaleString()} ل.س`;
}

/**
 * 5. إرسال أو تعديل الموعد
 */
async function handleAppointmentSubmit(e) {
    e.preventDefault();

    const form = e.target;
    const submitBtn = form.querySelector('button[type="submit"]');

    // 1. جلب وتحويل ID المريض والتأكد من وجوده كـ Integer
    const rawPatientId = document.getElementById('selectedPatientId')?.value;
    const patientId = rawPatientId ? parseInt(rawPatientId, 10) : null;

    //  فحص مبدئي: إذا لم يتم اختيار مريض نمنع الإرسال وننبه المستخدم
    if (!patientId || isNaN(patientId)) {
        showToast('يرجى اختيار مريض من القائمة المنسدلة أولاً', 'error');
        return;
    }

    if (submitBtn) submitBtn.disabled = true;

    // 2. تجهيز باقي البيانات وتحويل الـ IDs إلى أرقام صحيحة
    const treatmentSelect = document.getElementById('treatmentSelect');
    const selectedTreatments = Array.from(treatmentSelect.selectedOptions)
        .map(opt => parseInt(opt.value, 10))
        .filter(val => !isNaN(val));

    const dateVal = document.getElementById('appointmentDate').value;
    const timeVal = document.getElementById('appointmentTime').value;
    const formattedDateTime = `${dateVal} ${timeVal}:00`;

    const rawDoctorId = document.getElementById('doctorSelect').value;
    const doctorId = rawDoctorId ? parseInt(rawDoctorId, 10) : null;

    const payload = {
        patient_id: patientId, // رقّمي صريح
        doctor_id: doctorId,   // رقّمي صريح
        treatment_ids: selectedTreatments,
        appointment_date: formattedDateTime,
        promo_code: document.getElementById('promoCodeInput')?.value.trim() || null,
        notes: document.getElementById('appointmentNotes')?.value.trim() || ''
    };

    const editId = form.dataset.editId;
    const url = editId ? `/appointments/${editId}` : '/appointments';
    const method = editId ? 'PUT' : 'POST';

    try {
        const response = await fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
            },
            body: JSON.stringify(payload)
        });

        const result = await response.json();

        if (response.ok && (result.status === true || result.status === 'success')) {
            showToast(editId ? 'تم تعديل الموعد بنجاح!' : 'تم تسجيل الموعد بنجاح!', 'success');
            toggleModal(false);
            fetchAppointments();
        } else {
            let errorMsg = 'حدث خطأ أثناء حفظ الموعد.';
            if (result.errors) {
                // تجميع كل أخطاء الـ Validation المرجعة من لارافيل
                errorMsg = Object.values(result.errors).flat().join('\n');
            } else if (result.message) {
                errorMsg = result.message;
            }
            showToast(errorMsg, 'error');
        }
    } catch (error) {
        console.error('خطأ في الاتصال بالسيرفر:', error);
        showToast('حدث خطأ في الاتصال بالسيرفر، يرجى المحاولة لاحقاً.', 'error');
    } finally {
        if (submitBtn) submitBtn.disabled = false;
    }
}
/**
 * 6. البحث السريع عن المريض
 */
function setupPatientSearch() {
    const searchInput = document.getElementById('patientSearchInput');
    const hiddenIdInput = document.getElementById('selectedPatientId');
    const dataList = document.getElementById('patients-list');

    if (!searchInput || !hiddenIdInput || !dataList) return;

    const updateHiddenId = () => {
        const value = searchInput.value.trim();
        hiddenIdInput.value = patientsMap[value] || '';
    };

    searchInput.addEventListener('change', updateHiddenId);

    searchInput.addEventListener('input', (e) => {
        const query = e.target.value.trim();
        updateHiddenId();
        if (hiddenIdInput.value) return;

        clearTimeout(searchTimeout);

        if (query.length < 1) {
            dataList.innerHTML = '';
            patientsMap = {};
            return;
        }

        searchTimeout = setTimeout(async () => {
            try {
                const response = await fetch(`/get-patients-list?q=${encodeURIComponent(query)}`, {
                    headers: { 'Accept': 'application/json' }
                });

                if (!response.ok) return;

                const result = await response.json();
                const patients = result.data || (Array.isArray(result) ? result : []);

                dataList.innerHTML = '';
                patientsMap = {};

                if (Array.isArray(patients)) {
                    patients.forEach(patient => {
                        const optionText = `${patient.name} - ${patient.phone || 'بدون رقم'}`;
                        const option = document.createElement('option');
                        option.value = optionText;
                        dataList.appendChild(option);

                        patientsMap[optionText] = patient.id;
                    });
                    updateHiddenId();
                }
            } catch (err) {
                console.error('خطأ في جلب بيانات المرضى:', err);
            }
        }, 300);
    });
}

/**
 * 7. جلب وعرض المواعيد في الجدول
 */
async function fetchAppointments() {
    try {
        const response = await fetch('/appointments/categorized');
        const result = await response.json();

        if (result.status === 'success' && result.data) {
            allAppointmentsData = [
                ...(result.data.pending || []),
                ...(result.data.arrived || []),
                ...(result.data.in_progress || []),
                ...(result.data.completed || []),
                ...(result.data.cancelled || [])
            ];
            applyFilters();
        }
    } catch (error) {
        console.error('حدث خطأ أثناء جلب المواعيد:', error);
    }
}

function renderAppointments(appointments) {
    const tbody = document.getElementById('appointmentsTableBody');
    if (!tbody) return;

    tbody.innerHTML = '';

    if (!appointments || appointments.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" style="text-align: center; padding: 2rem; color: #888;">
                    لا توجد مواعيد مطابقة للبحث.
                </td>
            </tr>`;
        return;
    }

    appointments.forEach(app => {
        const patientName = app.patient?.name || app.patient_name || (app.patient_id ? `مريض #${app.patient_id}` : 'غير محدد');
        const patientPhone = app.patient?.phone || app.patient_phone || '-';
        const avatarLetter = patientName.charAt(0);

        let serviceName = 'جلسة علاجية';
        if (app.treatment?.name) {
            serviceName = app.treatment.name;
        } else if (app.treatments && app.treatments.length > 0) {
            serviceName = app.treatments.map(t => t.name).join(' + ');
        } else if (app.service?.name) {
            serviceName = app.service.name;
        } else if (app.service_name || app.treatment_name) {
            serviceName = app.service_name || app.treatment_name;
        } else if (app.id) {
            serviceName = `جلسة رقم #${app.id}`;
        }

        const doctorName = app.doctor?.name || app.doctor_name || (app.doctor_id ? `طبيب #${app.doctor_id}` : 'غير محدد');

        const dateStr = app.appointment_date || app.date || '';
        const timeStr = app.appointment_time || app.time || '';

        const statusConfig = getStatusBadge(app.status);

        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>
                <div class="patient-cell">
                    <span class="patient-avatar">${avatarLetter}</span>
                    <div class="patient-meta">
                        <span class="p-name">${patientName}</span>
                        <span class="p-phone">${patientPhone}</span>
                    </div>
                </div>
            </td>
            <td>${serviceName}</td>
            <td>${doctorName}</td>
            <td>
                <div class="date-time-cell">
                    <span class="d-date">${dateStr}</span>
                    <span class="d-time">${timeStr}</span>
                </div>
            </td>
            <td><span class="status-badge ${statusConfig.class}">${statusConfig.label}</span></td>
            <td>
                <div class="action-buttons">
                    <button class="btn-action edit" title="تعديل الموعد" onclick="editAppointment(${app.id})">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                        </svg>
                    </button>
                    <button class="btn-action delete" title="إلغاء الموعد" onclick="openCancelModal(${app.id})">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                </div>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

function applyFilters() {
    const searchVal = document.getElementById('searchInput')?.value.toLowerCase().trim() || '';
    const dateVal = document.getElementById('dateFilter')?.value || '';
    const statusVal = document.getElementById('statusFilter')?.value || 'all';

    const filtered = allAppointmentsData.filter(app => {
        const patientName = (app.patient?.name || '').toLowerCase();
        const doctorName = (app.doctor?.name || '').toLowerCase();
        const serviceName = (app.treatments ? app.treatments.map(t => t.name).join(' ') : '').toLowerCase();
        
        const matchesSearch = !searchVal || 
            patientName.includes(searchVal) || 
            doctorName.includes(searchVal) || 
            serviceName.includes(searchVal);

        let matchesDate = true;
        if (dateVal) {
            const rawDate = app.appointment_date || app.date || '';
            const appFormattedDate = rawDate ? rawDate.split('T')[0].split(' ')[0] : '';
            matchesDate = (appFormattedDate === dateVal);
        }

        const matchesStatus = (statusVal === 'all') || (app.status === statusVal);

        return matchesSearch && matchesDate && matchesStatus;
    });

    renderAppointments(filtered);
}

function openCancelModal(id) {
    const modal = document.getElementById('cancelModal');
    const inputId = document.getElementById('cancelAppointmentId');
    if (modal && inputId) {
        inputId.value = id;
        modal.style.display = 'flex';
    }
}

function closeCancelModal() {
    const modal = document.getElementById('cancelModal');
    if (modal) modal.style.display = 'none';
}

function toggleModal(show) {
    const modal = document.getElementById('appointmentModal');
    const form = document.getElementById('appointmentForm');
    if (!modal) return;

    if (show) {
        modal.classList.add('active');
        modal.style.display = 'flex';
    } else {
        modal.classList.remove('active');
        modal.style.display = 'none';
        
        if (form) {
            form.reset();
            delete form.dataset.editId;
            currentDiscount = { type: null, value: 0 };
            calculateSummaryPrice();

            const modalTitle = modal.querySelector('.modal-header h3');
            if (modalTitle) modalTitle.textContent = 'حجز موعد جديد';
        }
    }
}

function editAppointment(id) {
    const appointment = allAppointmentsData.find(app => app.id === id);
    if (!appointment) return;

    const modal = document.getElementById('appointmentModal');
    if (!modal) return;

    const modalTitle = modal.querySelector('.modal-header h3');
    if (modalTitle) modalTitle.textContent = 'تعديل بيانات الموعد';

    const patientInput = document.getElementById('patientSearchInput');
    const patientIdHidden = document.getElementById('selectedPatientId');
    
    if (patientInput) {
        const patientName = appointment.patient?.name || appointment.patient_name || '';
        const patientPhone = appointment.patient?.phone || appointment.patient_phone || '';
        patientInput.value = patientPhone ? `${patientName} - ${patientPhone}` : patientName;
    }
    if (patientIdHidden) {
        patientIdHidden.value = appointment.patient_id || '';
    }

    const treatmentSelect = document.getElementById('treatmentSelect');
    if (treatmentSelect) {
        let selectedIds = [];
        if (appointment.treatments && Array.isArray(appointment.treatments)) {
            selectedIds = appointment.treatments.map(t => t.id);
        } else if (appointment.treatment_id) {
            selectedIds = [appointment.treatment_id];
        }

        Array.from(treatmentSelect.options).forEach(option => {
            option.selected = selectedIds.includes(parseInt(option.value));
        });
        
        calculateSummaryPrice();
    }

    const doctorSelect = document.getElementById('doctorSelect');
    if (doctorSelect) {
        doctorSelect.value = appointment.doctor_id || '';
    }

    const dateInput = document.getElementById('appointmentDate');
    const timeInput = document.getElementById('appointmentTime');
    const rawDate = appointment.appointment_date || appointment.date || '';

    if (rawDate) {
        const dateTimeParts = rawDate.replace('T', ' ').split(' ');
        if (dateInput && dateTimeParts[0]) {
            dateInput.value = dateTimeParts[0];
        }
        if (timeInput) {
            if (appointment.appointment_time) {
                timeInput.value = appointment.appointment_time;
            } else if (dateTimeParts[1]) {
                timeInput.value = dateTimeParts[1].substring(0, 5);
            }
        }
    }

    const notesInput = document.getElementById('appointmentNotes');
    if (notesInput) {
        notesInput.value = appointment.notes || '';
    }

    const form = document.getElementById('appointmentForm');
    if (form) {
        form.dataset.editId = id;
    }

    toggleModal(true);
}

/**
 * 🔑 8. تحسين ربط فئات الحالات (Status Badges)
 */
function getStatusBadge(status) {
    switch (status) {
        case 'pending':
        case 'upcoming':
            return { label: 'منتظر', class: 'status-pending' };
        case 'arrived':
            return { label: 'حضر', class: 'status-arrived' };
        case 'in_progress':
            return { label: 'قيد العلاج', class: 'status-in-progress' };
        case 'completed':
            return { label: 'مكتمل', class: 'status-completed' };
        case 'canceled':
        case 'cancelled':
            return { label: 'ملغى', class: 'status-cancelled' };
        default:
            return { label: status || 'غير محدد', class: 'status-pending' };
    }
}

/**
 * 🔑 9. تحسين إشعارات الـ Toast لتتقبل نوع الحالة (success, error, info, warning)
 */
function showToast(message, type = 'info') {
    const toast = document.getElementById('toast');
    if (toast) {
        toast.textContent = message;
        
        // إزالة الكلاسات القديمة وإضافة كلاس الحالة والـ show
        toast.className = `toast ${type} show`;
        
        setTimeout(() => {
            toast.classList.remove('show');
        }, 3000);
    }
}