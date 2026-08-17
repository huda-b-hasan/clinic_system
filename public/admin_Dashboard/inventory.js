// Base API Endpoint
const API_BASE_URL = '/materials';

let allMaterials = [];
let itemToDeleteId = null;

document.addEventListener('DOMContentLoaded', () => {
    // 1. جلب البيانات عند تحميل الصفحة
    fetchMaterials();

    // 2. ربط نموذج الإضافة بالـ API
    const itemForm = document.getElementById('itemForm');
    if (itemForm) {
        itemForm.addEventListener('submit', handleAddMaterial);
    }

    // 3. ربط نموذج التزويد بالـ API ومعالجة حساب الإجمالي تلقائياً
    const restockForm = document.getElementById('restockForm');
    if (restockForm) {
        restockForm.addEventListener('submit', handleRestockSubmit);
    }

    const restockQtyInput = document.getElementById('restockQtyInput');
    const restockUnitPriceInput = document.getElementById('restockUnitPriceInput');
    
    restockQtyInput?.addEventListener('input', calculateRestockTotal);
    restockUnitPriceInput?.addEventListener('input', calculateRestockTotal);

    // 4. ربط أزرار الإلغاء والإغلاق للمودالات
    document.querySelectorAll('[data-close-modal]').forEach(button => {
        button.addEventListener('click', (e) => {
            const modalId = e.currentTarget.getAttribute('data-close-modal');
            closeModalById(modalId);
        });
    });

    // 5. Event Delegation لمعالجة أزرار الجدول (تزويد / حذف)
    const materialsTableBody = document.getElementById('materialsTableBody') || document.querySelector('.admin-table tbody');
    if (materialsTableBody) {
        materialsTableBody.addEventListener('click', handleTableActions);
    }

    // 6. ربط زر تأكيد الحذف
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
    if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener('click', handleConfirmDelete);
    }

    // 7. ربط البحث والتصفية
    document.getElementById('searchInput')?.addEventListener('input', applyFilters);
    document.getElementById('categoryFilter')?.addEventListener('change', applyFilters);
    document.getElementById('stockFilter')?.addEventListener('change', applyFilters);
});

/**
 * جلب المواد من السيرفر
 */
async function fetchMaterials() {
    try {
        const response = await fetch(API_BASE_URL, {
            headers: { 'Accept': 'application/json' }
        });
        const result = await response.json();

        if (result.success || response.ok) {
            allMaterials = result.data || result;
            renderTable(allMaterials);
            updateSummaryCards(allMaterials);
        } else {
            showToast('حدث خطأ أثناء جلب بيانات المخزن', 'error');
        }
    } catch (error) {
        console.error('Fetch error:', error);
        showToast('تعذر الاتصال بالسيرفر', 'error');
    }
}

/**
 * بناء صفوف الجدول ديناميكياً باستخدام data-attributes للأحداث
 */
function renderTable(materials) {
    const tbody = document.getElementById('materialsTableBody') || document.querySelector('.admin-table tbody');
    if (!tbody) return;

    if (!materials || materials.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" style="text-align:center; padding: 24px; color: #666;">
                    لا توجد مواد مطابقة للبحث أو المخزن فارغ حالياً.
                </td>
            </tr>`;
        return;
    }

    tbody.innerHTML = materials.map(item => {
        const minLimit = item.low_stock_limit || 3;
        const isOut = item.quantity <= 0;
        const isLow = item.quantity <= minLimit;

        let statusBadge = '<span class="badge-status active">متوفر</span>';
        if (isOut) {
            statusBadge = '<span class="badge-status low-stock">منتهي من المخزن</span>';
        } else if (isLow) {
            statusBadge = '<span class="badge-status low-stock">منخفض جداً</span>';
        }

        const categoryMap = {
            'fillers': { name: 'فيلر وبوتوكس', badgeClass: 'category-injection', icon: '🧪', bgClass: 'filler-bg' },
            'skincare': { name: 'عناية بالبشرة', badgeClass: 'category-skin', icon: '🧴', bgClass: 'skin-bg' },
            'disposables': { name: 'مستهلكات طبية', badgeClass: 'category-medical', icon: '🩺', bgClass: 'medical-bg' }
        };

        const catInfo = categoryMap[item.category] || { 
            name: item.category || 'عام', 
            badgeClass: 'category-medical', 
            icon: `<svg viewBox="0 0 24 24" fill="none" stroke="#A855F7" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="width: 24px; height: 24px; min-width: 24px; min-height: 24px; display: inline-block;">
  <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
  <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
  <line x1="12" y1="22.08" x2="12" y2="12"></line>
</svg>`, 
            bgClass: 'filler-bg' 
        };

        const formattedExpiry = item.expiry_date ? item.expiry_date : 'غير محدد';

        return `
            <tr>
                <td>
                    <div class="item-info-cell">
                        <div>
                            <strong>${item.name}</strong>
                            <span class="item-code">SKU: ${item.sku || ('MAT-' + item.id)}</span>
                        </div>
                    </div>
                </td>
                <td><span class="category-badge ${catInfo.badgeClass}">${catInfo.name}</span></td>
                <td>
                    <strong class="quantity-text ${isLow ? 'warning-qty' : ''}">
                        ${item.quantity} عبوة
                    </strong>
                </td>
                <td><span class="expiry-date ${isNearExpiry(item.expiry_date) ? 'near-expiry' : ''}">${formattedExpiry}</span></td>
                <td>${statusBadge}</td>
                <td>
                    <div class="action-buttons">
                        <button class="btn-icon edit-btn" data-action="restock" data-id="${item.id}" data-name="${item.name}" title="تزويد الشحنة">
                            ➕
                        </button>

                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

/**
 * معالجة الضغط على أزرار الجدول باستخدام Event Delegation
 */
function handleTableActions(event) {
    const button = event.target.closest('button[data-action]');
    if (!button) return;

    const action = button.getAttribute('data-action');
    const id = button.getAttribute('data-id');
    const name = button.getAttribute('data-name');

    if (action === 'restock') {
        openRestockModal(id, name);
    } else if (action === 'delete') {
        openDeleteModal(id, name);
    }
}

/**
 * إضافة مادة جديدة عبر Modal الإضافة
 */
async function handleAddMaterial(event) {
    event.preventDefault();

    const submitBtn = event.target.querySelector('button[type="submit"]');
    if (submitBtn) submitBtn.disabled = true;

    const nameInput = document.getElementById('item_name');
    const priceInput = document.getElementById('item_unit_price');

    const payload = {
        name: nameInput?.value.trim() || '',
        quantity: 0, // دائماً صفر عند إضافة المادة لأول مرة
        unit_price: parseFloat(priceInput?.value) || 0,
    };

    try {
        const response = await fetch(API_BASE_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(payload)
        });

        const result = await response.json();

        if (response.ok || result.success) {
            showToast('تمت إضافة المادة بنجاح 📦', 'success');
            closeModalById('itemModal');
            event.target.reset();
            fetchMaterials();
        } else {
            const errorMsg = result.message || (result.errors ? Object.values(result.errors)[0][0] : 'فشل في حفظ المادة');
            showToast(errorMsg, 'error');
        }
    } catch (error) {
        console.error('Add Material Error:', error);
        showToast('حدث خطأ أثناء حفظ البيانات', 'error');
    } finally {
        if (submitBtn) submitBtn.disabled = false;
    }
}

/* ==========================================================================
   إدارة Modal التزويد (Restock)
   ========================================================================== */
function openRestockModal(id, name) {
    const idInput = document.getElementById('restockItemId');
    const labelInput = document.getElementById('restockItemNameLabel');
    const qtyInput = document.getElementById('restockQtyInput');
    const priceInput = document.getElementById('restockUnitPriceInput');
    const dateInput = document.getElementById('restockInvoiceDateInput');
    const totalPreview = document.getElementById('restockTotalPricePreview');

    if (idInput) idInput.value = id;
    if (labelInput) labelInput.textContent = `المادة: ${name}`;
    if (qtyInput) qtyInput.value = '';
    if (priceInput) priceInput.value = '';
    if (totalPreview) totalPreview.textContent = '0.00';
    if (dateInput) dateInput.value = new Date().toISOString().split('T')[0];

    openModalById('restockModal');
}

function calculateRestockTotal() {
    const qty = parseFloat(document.getElementById('restockQtyInput')?.value) || 0;
    const price = parseFloat(document.getElementById('restockUnitPriceInput')?.value) || 0;
    const totalPreview = document.getElementById('restockTotalPricePreview');

    if (totalPreview) {
        totalPreview.textContent = (qty * price).toFixed(2);
    }
}

async function handleRestockSubmit(event) {
    event.preventDefault();

    const id = document.getElementById('restockItemId').value;
    const qtyToAdd = parseInt(document.getElementById('restockQtyInput').value);
    const unitPrice = parseFloat(document.getElementById('restockUnitPriceInput').value);
    const invoiceDate = document.getElementById('restockInvoiceDateInput')?.value;

    if (!qtyToAdd || qtyToAdd <= 0) return;

    try {
        const response = await fetch(`${API_BASE_URL}/${id}/restock`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                quantity_added: qtyToAdd,
                unit_price: unitPrice,
                invoice_date: invoiceDate
            })
        });

        const result = await response.json();

        if (response.ok || result.success) {
            showToast('تمت إضافة الكمية وتحديث الفاتورة ', 'success');
            closeModalById('restockModal');
            fetchMaterials();
        } else {
            showToast(result.message || 'تعذر إضافة الكمية', 'error');
        }
    } catch (error) {
        showToast('خطأ في الاتصال بالخادم', 'error');
    }
}

/* ==========================================================================
   إدارة Modal الحذف المخصص (#deleteModal)
   ========================================================================== */
function openDeleteModal(id, name) {
    itemToDeleteId = id;
    const deleteModal = document.getElementById('deleteModal');
    
    const textElement = deleteModal?.querySelector('p');
    if (textElement) {
        textElement.textContent = `هل أنتِ متأكدة من رغبتكِ في حذف المادة "${name}"؟ لن يمكنكِ استعادة البيانات بعد الحذف.`;
    }

    openModalById('deleteModal');
}

async function handleConfirmDelete() {
    if (!itemToDeleteId) return;

    try {
        const response = await fetch(`${API_BASE_URL}/${itemToDeleteId}`, {
            method: 'DELETE',
            headers: { 'Accept': 'application/json' }
        });

        const result = await response.json();

        if (response.ok || result.success) {
            showToast('تم حذف المادة بنجاح', 'success');
            closeModalById('deleteModal');
            itemToDeleteId = null;
            fetchMaterials();
        } else {
            showToast(result.message || 'تعذر حذف المادة', 'error');
        }
    } catch (error) {
        showToast('حدث خطأ أثناء الاتصال', 'error');
    }
}

/* ==========================================================================
   دوال عامة للتعامل مع المودالات عبر الـ DOM
   ========================================================================== */
function openModalById(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('active');
        modal.style.display = 'flex';
    }
}

function closeModalById(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
        modal.style.display = 'none';
    }
}

/* ==========================================================================
   الدوال المساعدة
   ========================================================================== */
function applyFilters() {
    const query = document.getElementById('searchInput')?.value.toLowerCase().trim() || '';
    const selectedCategory = document.getElementById('categoryFilter')?.value || '';
    const selectedStock = document.getElementById('stockFilter')?.value || '';

    const filtered = allMaterials.filter(item => {
        const matchQuery = !query || 
            item.name.toLowerCase().includes(query) || 
            (item.sku && item.sku.toLowerCase().includes(query));

        const matchCat = !selectedCategory || item.category === selectedCategory;

        let matchStock = true;
        const minLimit = item.low_stock_limit || 3;
        if (selectedStock === 'available') matchStock = item.quantity > minLimit;
        if (selectedStock === 'low') matchStock = item.quantity <= minLimit && item.quantity > 0;
        if (selectedStock === 'out') matchStock = item.quantity <= 0;

        return matchQuery && matchCat && matchStock;
    });

    renderTable(filtered);
}

function updateSummaryCards(materials) {
    const totalCount = materials.length;
    const lowStockCount = materials.filter(m => m.quantity <= (m.low_stock_limit || 3)).length;
    const expiringSoonCount = materials.filter(m => isNearExpiry(m.expiry_date)).length;

    const cards = document.querySelectorAll('.summary-card p');
    if (cards.length >= 3) {
        cards[0].textContent = `${totalCount} عنصر`;
        cards[1].textContent = `${lowStockCount} مواد فقط`;
        cards[2].textContent = `${expiringSoonCount} مواد (خلال شهر)`;
    }
}

function isNearExpiry(dateString) {
    if (!dateString) return false;
    const expiryDate = new Date(dateString);
    const today = new Date();
    const diffDays = (expiryDate - today) / (1000 * 60 * 60 * 24);
    return diffDays >= 0 && diffDays <= 30;
}

function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    if (!toast) return;

    toast.textContent = message;

    toast.style.backgroundColor = '';
    toast.style.color = '';
    toast.className = `toast show ${type}`;

    setTimeout(() => {
        toast.className = 'toast';
    }, 3000);
}
function closeDeleteModal() {
    closeModalById('deleteModal');
}
// زر الحذف تم الغاءه
                        // <button class="btn-icon danger-btn" data-action="delete" data-id="${item.id}" data-name="${item.name}" title="حذف المادة">
                        //     <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                        // </button>