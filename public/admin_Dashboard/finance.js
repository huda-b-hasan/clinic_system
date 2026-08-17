let currentEditId = null;
let promoToDeleteId = null;

// دالة مساعدة لتنسيق التاريخ القادم من الباك إند
function formatDate(dateString) {
    if (!dateString) return '-';
    return dateString.split('T')[0];
}

// دالة التوست
function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    if (!toast) return;

    toast.className = 'toast';
    toast.classList.add(type);
    toast.textContent = message;

    toast.classList.add('show');

    setTimeout(() => {
        toast.classList.remove('show');
    }, 3000);
}

// 1. جلب قائمة الخدمات (Treatments) لتعبئة الـ Select بالـ Modal
async function loadTreatments() {
    try {
        const res = await fetch('/treatments', {
            headers: { 'Accept': 'application/json' }
        });
        if (res.ok) {
            const result = await res.json();
            const treatments = result.data || result;
            
            const select = document.getElementById('treatmentId');
            if (!select) return;

            select.innerHTML = '<option value="">جميع الخدمات (خصم عام) 🌟</option>';
            
            treatments.forEach(item => {
                const opt = document.createElement('option');
                opt.value = item.id;
                opt.textContent = item.name;
                select.appendChild(opt);
            });
        }
    } catch (err) {
        console.error('خطأ في جلب قائمة الخدمات:', err);
    }
}

// 2. جلب الإحصائيات وتمرير البيانات لتحديث الواجهة
async function fetchStats() {
    try {
        const res = await fetch('/promo-codes/stats', {
            headers: { 'Accept': 'application/json' }
        });
        if (res.ok) {
            const result = await res.json();
            const data = result.data || result;
            
            updateStatsCards(data);
        }
    } catch (err) {
        console.error('خطأ في جلب الإحصائيات:', err);
    }
}

// تحديث الكروت بناءً على الـ ID المباشر
function updateStatsCards(data) {
    const activeElem = document.getElementById('activePromosStat');
    const beneficiariesElem = document.getElementById('beneficiariesStat');
    const discountsElem = document.getElementById('totalDiscountsStat');

    if (activeElem) {
        activeElem.textContent = `${data.active_promos || 0} كودات فعالة`;
    }

    if (beneficiariesElem) {
        beneficiariesElem.textContent = `${data.beneficiaries_count || 0} عميل`;
    }

    if (discountsElem) {
        discountsElem.textContent = `$${data.total_discounts || 0}`;
    }
}

// 3. جلب الأكواد وعرضها بالجدول
async function fetchPromoCodes() {
    const search = document.getElementById('searchInput')?.value || '';
    const type = document.getElementById('typeFilter')?.value || '';
    const status = document.getElementById('statusFilter')?.value || '';

    const params = new URLSearchParams({ search, type, status });

    try {
        const res = await fetch(`/promo-codes?${params.toString()}`, {
            headers: { 'Accept': 'application/json' }
        });
        const result = await res.json();
        const promoCodes = result.data || result || [];

        const tbody = document.querySelector('.admin-table tbody');
        if (!tbody) return;

        tbody.innerHTML = '';

        if (promoCodes.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding: 20px; color: #888;">لا توجد أكواد خصم مطابقة للبحث</td></tr>';
            return;
        }

        promoCodes.forEach(code => {
            const discountDisplay = code.discount_type === 'percentage' 
                ? `%${code.discount_value} ` 
                : `$${code.discount_value} `;

            const usageText = code.usage_limit ? `${code.used_count} / ${code.usage_limit}` : `${code.used_count} / ∞`;
            
            const treatmentBadge = code.treatment 
                ? `<span style="background: #f3e8ff; color: #7e22ce; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600;"> ${code.treatment.name}</span>`
                : `<span style="background: #f3f4f6; color: #4b5563; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600;"> جميع الخدمات</span>`;

            // معالجة حالة الكود بمرونة (سواء كانت 1 أو true)
            const isActive = code.is_active === true || code.is_active === 1 || code.is_active === '1';
            
            // مقارنة التواريخ حتى نهاية اليوم
            const expiryDate = new Date(code.expiry_date);
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            
            const isExpired = expiryDate < today;

            const badgeClass = (isExpired || !isActive) ? 'inactive' : 'active';
            const badgeText = isExpired ? 'منتهي' : (isActive ? 'نشط' : 'معطل');

            const formattedExpiryDate = formatDate(code.expiry_date);

            const row = document.createElement('tr');
            row.innerHTML = `
                <td>
                    <span class="code-tag" style="font-weight: bold; color: #8e44ad;">${code.code}</span>
                </td>
                <td>${treatmentBadge}</td>
                <td><span class="discount-value">${discountDisplay}</span></td>
                <td><span class="usage-count">${usageText}</span></td>
                <td><span class="expiry-date">${formattedExpiryDate}</span></td>
                <td><span class="badge-status ${badgeClass}">${badgeText}</span></td>
                <td>
                    <div class="action-buttons">
                        <button class="btn-icon edit-btn" onclick="editPromo(${code.id})" title="تعديل الكود">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16"
                                viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 20h9"></path>
                                <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path>
                            </svg>
                        </button>

                    </div>
                </td>
            `;
            tbody.appendChild(row);
        });

    } catch (err) {
        console.error('خطأ في جلب الأكواد:', err);
        showToast('حدث خطأ في تحميل البيانات', 'error');
    }
}

// 4. حفظ كود جديد أو تعديل
const promoForm = document.getElementById('promoForm');
if (promoForm) {
    promoForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        // تجميع البيانات + قراءة حالة النشاط إما من خيار بالنموذج أو إرسال true افتراضياً
        const isActiveInput = document.getElementById('isActive');
        
        const body = {
            code: document.getElementById('promoCode')?.value?.trim() || '',
            discount_type: document.getElementById('discountType')?.value || 'percentage',
            discount_value: document.getElementById('discountValue')?.value || 0,
            treatment_id: document.getElementById('treatmentId')?.value || null,
            usage_limit: document.getElementById('usageLimit')?.value || null,
            expiry_date: document.getElementById('expiryDate')?.value || '',
            is_active: isActiveInput ? isActiveInput.checked : true
        };

        const url = currentEditId ? `/promo-codes/${currentEditId}` : '/promo-codes';
        const method = currentEditId ? 'PUT' : 'POST';

        try {
            const res = await fetch(url, {
                method: method,
                headers: { 
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(body)
            });

            const result = await res.json();

            if (res.ok) {
                closePromoModal();
                fetchPromoCodes();
                fetchStats();
                showToast(result.message || 'تم حفظ الكود بنجاح ✨', 'success');
            } else {
                let errorMessage = result.message || 'تحققي من البيانات المدخلة';
                if (result.errors) {
                    const firstKey = Object.keys(result.errors)[0];
                    if (firstKey) errorMessage = result.errors[firstKey][0];
                }
                showToast(errorMessage, 'warning');
            }
        } catch (err) {
            console.error('خطأ بالحفظ:', err);
            showToast('حدث خطأ أثناء حفظ البيانات', 'error');
        }
    });
}

// 5. تعبئة البيانات للتعديل
async function editPromo(id) {
    try {
        const res = await fetch(`/promo-codes/${id}`, {
            headers: { 'Accept': 'application/json' }
        });
        const result = await res.json();
        const code = result.data || result;
        
        currentEditId = code.id;

        // تعديل عنوان الموديل
        const modalTitle = document.getElementById('modalTitle');
        if (modalTitle) modalTitle.textContent = 'تعديل كود الخصم ✏️';

        // تعبئة البيانات في الحقول
        if (document.getElementById('promoCode')) document.getElementById('promoCode').value = code.code || '';
        if (document.getElementById('discountType')) document.getElementById('discountType').value = code.discount_type || 'percentage';
        if (document.getElementById('discountValue')) document.getElementById('discountValue').value = code.discount_value || 0;
        if (document.getElementById('treatmentId')) document.getElementById('treatmentId').value = code.treatment_id || '';
        if (document.getElementById('usageLimit')) document.getElementById('usageLimit').value = code.usage_limit || '';
        if (document.getElementById('expiryDate')) document.getElementById('expiryDate').value = formatDate(code.expiry_date);
        
        const isActiveInput = document.getElementById('isActive');
        if (isActiveInput) {
            isActiveInput.checked = code.is_active === true || code.is_active === 1 || code.is_active === '1';
        }

        openPromoModal();
    } catch (err) {
        console.error('خطأ جلب بيانات الكود:', err);
        showToast('تعذر جلب تفاصيل الكود', 'error');
    }
}

// 6. إدارة موديل الحذف ودالة الحذف الفعلية
function openDeleteModal(id) {
    promoToDeleteId = id;
    const modal = document.getElementById('deleteModal');
    if (modal) modal.classList.add('active');
}

function closeDeleteModal() {
    promoToDeleteId = null;
    const modal = document.getElementById('deleteModal');
    if (modal) modal.classList.remove('active');
}

document.getElementById('confirmDeleteBtn')?.addEventListener('click', async () => {
    if (!promoToDeleteId) return;

    try {
        const res = await fetch(`/promo-codes/${promoToDeleteId}`, { 
            method: 'DELETE',
            headers: { 'Accept': 'application/json' }
        });
        const result = await res.json();

        if (res.ok) {
            closeDeleteModal();
            fetchPromoCodes();
            fetchStats();
            showToast(result.message || 'تم حذف الكود بنجاح', 'info');
        } else {
            showToast('فشل حذف الكود', 'error');
        }
    } catch (err) {
        console.error('خطأ أثناء الحذف:', err);
        showToast('حدث خطأ أثناء الاتصال بالسيرفر', 'error');
    }
});

function openPromoModal() {
    const modal = document.getElementById('promoModal');
    if (modal) modal.classList.add('active');
}

function closePromoModal() {
    currentEditId = null;
    const form = document.getElementById('promoForm');
    if (form) form.reset();

    // إعادة العنوان للوضع الافتراضي
    const modalTitle = document.getElementById('modalTitle');
    if (modalTitle) modalTitle.textContent = 'إضافة كود خصم جديد 🏷️';

    const modal = document.getElementById('promoModal');
    if (modal) modal.classList.remove('active');
}

// الفلترة والبحث
document.getElementById('searchInput')?.addEventListener('input', fetchPromoCodes);
document.getElementById('typeFilter')?.addEventListener('change', fetchPromoCodes);
document.getElementById('statusFilter')?.addEventListener('change', fetchPromoCodes);

// التحميل الأول
window.addEventListener('DOMContentLoaded', () => {
    loadTreatments();
    fetchStats();
    fetchPromoCodes();
});
// زر الحذف تم الغاءه 
                        // <button class="btn-icon danger-btn" onclick="openDeleteModal(${code.id})" title="حذف الكود">
                        //     <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16"
                        //         viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        //         stroke-linecap="round" stroke-linejoin="round">
                        //         <path d="M3 6h18"></path>
                        //         <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path>
                        //         <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path>
                        //         <line x1="10" y1="11" x2="10" y2="17"></line>
                        //         <line x1="14" y1="11" x2="14" y2="17"></line>
                        //     </svg>
                        // </button>