const API_URL = '/treatments';

let allTreatments = []; // لتخزين كافة البيانات للفلترة السريعة
let treatmentIdToDelete = null; // تخزين رقم الخدمة المراد حذفها

document.addEventListener('DOMContentLoaded', () => {
    fetchTreatments();

    // ربط نموذج الإضافة والتعديل
    const treatmentForm = document.getElementById('treatmentForm');
    if (treatmentForm) {
        treatmentForm.addEventListener('submit', handleFormSubmit);
    }

    // ربط البحث والفلترة
    document.getElementById('searchInput')?.addEventListener('input', filterTreatments);
    document.getElementById('statusFilter')?.addEventListener('change', filterTreatments);

    // ربط زر تأكيد الحذف بداخل المودال
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
    if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener('click', executeDeleteTreatment);
    }
});

// 1. جلب الخدمات من السيرفر
async function fetchTreatments() {
    try {
        const response = await fetch(API_URL, {
            headers: {
                'Accept': 'application/json'
            }
        });
        const data = await response.json();

        allTreatments = Array.isArray(data) ? data : (data.data || []);
        
        console.log(data)
        updateSummaryCards(allTreatments);
        renderTreatmentsTable(allTreatments);

    } catch (error) {
        console.error('حدث خطأ أثناء جلب الخدمات:', error);
    }
}

// 2. تحديث الكروت السريعة
function updateSummaryCards(treatments) {
    const totalCount = treatments.length;
    const discountedCount = treatments.filter(t => t.discount_price && parseFloat(t.discount_price) > 0).length;

    const totalEl = document.getElementById('totalServicesCount');
    const discountedEl = document.getElementById('discountedServicesCount');

    if (totalEl) totalEl.innerText = `${totalCount} خدمة متوفرة`;
    if (discountedEl) discountedEl.innerText = `${discountedCount} خدمات عليها خصم`;
}

// 3. بناء صفوف الجدول
function renderTreatmentsTable(treatments) {
    const tbody = document.getElementById('treatmentsTableBody');
    if (!tbody) return;

    tbody.innerHTML = '';

    if (treatments.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7" style="text-align: center; padding: 20px; color: #888;">لا توجد خدمات مضافة حالياً.</td></tr>`;
        return;
    }

    const fallbackSvg = `data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='40' height='40' viewBox='0 0 24 24' fill='none' stroke='%23a855f7' stroke-width='1.5'><rect width='18' height='18' x='3' y='3' rx='4' fill='%23f3e8ff'/><path d='M12 8v8M8 12h8'/></svg>`;

    treatments.forEach(item => {
        let imageUrl = fallbackSvg;

        if (item.image) {
            if (item.image.startsWith('http') || item.image.startsWith('data:')) {
                imageUrl = item.image;
            } else if (item.image.startsWith('auth/')) {
                imageUrl = `/${item.image}`;
            } else {
                imageUrl = `/storage/${item.image}`;
            }
        }

        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>
                <div class="service-info-cell">
                    <img src="${imageUrl}" 
                         alt="${item.name}" 
                         class="service-thumb-img" 
                         onerror="this.onerror=null; this.src='${fallbackSvg}';">
                    <div>
                        <strong>${item.name}</strong>
                        <span class="service-desc">${item.description || ''}</span>
                    </div>
                </div>
            </td>
            <td><span class="category-badge">${item.category || 'عام'}</span></td>
            <td><strong class="price-tag original-price">${item.base_price} ل.س</strong></td>
            <td><strong class="price-tag discount-price">${item.discount_price ? item.discount_price + ' ل.س' : '-'}</strong></td>
            <td><span class="time-tag">⏱️ ${item.duration} </span></td>
            <td>
                <button onclick="toggleTreatmentStatus(${item.id})" class="badge-status ${item.status === 'active' ? 'active' : 'inactive'}" style="border:none; cursor:pointer;">
                    ${item.status === 'active' ? 'متاحة' : 'غير متاحة'}
                </button>
            </td>
            <td>
                <div class="action-buttons">
                    <button class="btn-icon edit-btn" title="تعديل الخدمة" onclick="openEditModal(${JSON.stringify(item).replace(/"/g, '&quot;')})">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 20h9"></path>
                            <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path>
                        </svg>
                    </button>
                    <button class="btn-icon danger-btn" title="حذف الخدمة" onclick="deleteTreatment(${item.id})">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M3 6h18"></path>
                            <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path>
                            <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path>
                            <line x1="10" y1="11" x2="10" y2="17"></line>
                            <line x1="14" y1="11" x2="14" y2="17"></line>
                        </svg>
                    </button>
                </div>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

// 4. تعبئة Modal التعديل بالبيانات
function openEditModal(item) {
    document.getElementById('modalTitle').innerText = 'تعديل الخدمة التجميلية 🌸';
    document.getElementById('treatmentId').value = item.id;
    document.getElementById('treatmentName').value = item.name;
    document.getElementById('treatmentCategory').value = item.category || '';
    document.getElementById('treatmentStatus').value = item.status || 'active';
    document.getElementById('basePrice').value = item.base_price;
    document.getElementById('discountPrice').value = item.discount_price || '';
    document.getElementById('treatmentDuration').value = item.duration;
    document.getElementById('treatmentDescription').value = item.description || '';
    
    if (item.features) {
        document.getElementById('treatmentFeatures').value = Array.isArray(item.features) ? item.features.join(', ') : item.features;
    } else {
        document.getElementById('treatmentFeatures').value = '';
    }

    if (typeof openTreatmentModal === 'function') {
        openTreatmentModal();
    }
}


// 5. حفظ البيانات (إضافة / تعديل)
async function handleFormSubmit(e) {
    e.preventDefault();

    const form = e.target;
    const formData = new FormData(form);
    const treatmentId = document.getElementById('treatmentId').value;

    // تحويل نص الميزات (features) المكتوب بفاصلات إلى Array ليطابق Validation الخادم
    const featuresInput = document.getElementById('treatmentFeatures').value;
    formData.delete('features');

    if (featuresInput && featuresInput.trim() !== '') {
        const featuresArray = featuresInput.split(',').map(f => f.trim()).filter(f => f !== '');
        featuresArray.forEach((feature, index) => {
            formData.append(`features[${index}]`, feature);
        });
    }

    // استبعاد حقل الصورة إذا لم يتم اختيار ملف جديد
    const imageInput = document.getElementById('treatmentImage');
    if (imageInput && imageInput.files.length === 0) {
        formData.delete('image');
    }

    let url = API_URL;

    if (treatmentId) {
        url = `${API_URL}/${treatmentId}`;
        formData.append('_method', 'PUT');
    }

    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            },
            body: formData
        });

        const result = await response.json();

        if (response.ok) {
            // توست النجاح (أخضر تركوازي)
            showToast(result.message || 'تمت العملية بنجاح 🌸', 'success');
            if (typeof closeTreatmentModal === 'function') closeTreatmentModal();
            form.reset();
            document.getElementById('treatmentId').value = '';
            fetchTreatments();
        } else {
            // توست الخطأ (أحمر)
            if (response.status === 422 && result.errors) {
                const firstErrorKey = Object.keys(result.errors)[0];
                const firstErrorMessage = result.errors[firstErrorKey][0];
                showToast('خطأ: ' + firstErrorMessage, 'error');
            } else {
                showToast('حدث خطأ: ' + (result.message || 'تعذر حفظ البيانات'), 'error');
            }
        }
    } catch (error) {
        console.error('خطأ في الاتصال بالخادم:', error);
        showToast('حدث خطأ في الاتصال بالخادم', 'error');
    }
}

// 6. إدارة مودال وتأكيد الحذف (مربوطة بـ window للوصول المباشر)
window.deleteTreatment = function(id) {
    treatmentIdToDelete = id;
    const deleteModal = document.getElementById('deleteModal');
    if (deleteModal) {
        deleteModal.style.display = 'flex';
        setTimeout(() => {
            deleteModal.classList.add('active');
        }, 10);
    }
};

window.closeDeleteModal = function() {
    treatmentIdToDelete = null;
    const deleteModal = document.getElementById('deleteModal');
    if (deleteModal) {
        deleteModal.classList.remove('active');
        setTimeout(() => {
            deleteModal.style.display = 'none';
        }, 250);
    }
};

// دالة تنفيذ الحذف الفعلية
async function executeDeleteTreatment() {
    if (!treatmentIdToDelete) return;

    try {
        const response = await fetch(`${API_URL}/${treatmentIdToDelete}`, {
            method: 'DELETE',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            }
        });

        if (response.ok) {
            showToast('تم حذف الخدمة بنجاح', 'success');
            closeDeleteModal();
            fetchTreatments();
        } else {
            showToast('تعذر حذف الخدمة', 'error');
        }
    } catch (error) {
        console.error('خطأ أثناء الحذف:', error);
        showToast('حدث خطأ في الاتصال بالخادم', 'error');
    }
}

// 7. تبديل حالة الخدمة
async function toggleTreatmentStatus(id) {
    try {
        const response = await fetch(`${API_URL}/${id}/toggle-status`, {
            method: 'PATCH',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            }
        });

        if (response.ok) {
            fetchTreatments();
        }
    } catch (error) {
        console.error('خطأ أثناء تغيير الحالة:', error);
    }
}

// 8. البحث والفلترة
function filterTreatments() {
    const searchValue = document.getElementById('searchInput')?.value.toLowerCase().trim() || '';
    const statusValue = document.getElementById('statusFilter')?.value || '';

    const filtered = allTreatments.filter(item => {
        const nameText = (item.name || '').toLowerCase();
        const categoryText = (item.category || '').toLowerCase();
        const descriptionText = (item.description || '').toLowerCase();

        const matchesSearch = !searchValue || 
            nameText.includes(searchValue) || 
            categoryText.includes(searchValue) || 
            descriptionText.includes(searchValue);

        const matchesStatus = !statusValue || item.status === statusValue;

        return matchesSearch && matchesStatus;
    });

    renderTreatmentsTable(filtered);
}

// دالة عرض التنبيه الجانبي (Toast)
// دالة إظهار التوست بألوان الـ CSS المحددة
function showToast(message, type = 'info') {
    const toast = document.getElementById('toast');
    if (!toast) return;

    toast.innerText = message;
    
    toast.classList.remove('show', 'success', 'error', 'warning', 'info');
    
    toast.classList.add(type);
    
    // إظهار التوست
    setTimeout(() => {
        toast.classList.add('show');
    }, 10);

    setTimeout(() => {
        toast.classList.remove('show');
    }, 3000);
}