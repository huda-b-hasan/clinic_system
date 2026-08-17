// Base API Endpoint
const API_BASE_URL = '/materials';

let allMaterials = [];

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

    // 5. Event Delegation لمعالجة أزرار الجدول (تزويد فقط)
    const materialsTableBody = document.getElementById('materialsTableBody') || document.querySelector('.admin-table tbody');
    if (materialsTableBody) {
        materialsTableBody.addEventListener('click', handleTableActions);
    }

    // 6. ربط البحث والتصفية (بدون فلاتر الفئات)
    document.getElementById('searchInput')?.addEventListener('input', applyFilters);
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
 * بناء صفوف الجدول ديناميكياً (بدون عمود التصنيف)
 */
function renderTable(materials) {
    const tbody = document.getElementById('materialsTableBody') || document.querySelector('.admin-table tbody');
    if (!tbody) return;

    if (!materials || materials.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="4" style="text-align:center; padding: 24px; color: #666;">
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
                <td>
                    <strong class="quantity-text ${isLow ? 'warning-qty' : ''}">
                        ${item.quantity} عبوة
                    </strong>
                </td>
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
    const qtyInput = document.getElementById('item_quantity');
    const priceInput = document.getElementById('item_unit_price');

    const payload = {
        name: nameInput?.value.trim() || '',
        quantity: parseInt(qtyInput?.value) || 0,
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
            showToast('تمت إضافة الكمية وتحديث الشحنة بنجاح', 'success');
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
    const selectedStock = document.getElementById('stockFilter')?.value || '';

    const filtered = allMaterials.filter(item => {
        const matchQuery = !query || 
            item.name.toLowerCase().includes(query) || 
            (item.sku && item.sku.toLowerCase().includes(query));

        let matchStock = true;
        const minLimit = item.low_stock_limit || 3;
        if (selectedStock === 'available') matchStock = item.quantity > minLimit;
        if (selectedStock === 'low') matchStock = item.quantity <= minLimit && item.quantity > 0;
        if (selectedStock === 'out') matchStock = item.quantity <= 0;

        return matchQuery && matchStock;
    });

    renderTable(filtered);
}

function updateSummaryCards(materials) {
    const totalCount = materials.length;
    const lowStockCount = materials.filter(m => m.quantity <= (m.low_stock_limit || 3)).length;
    const totalValue = materials.reduce((acc, m) => acc + ((m.quantity || 0) * (m.unit_price || 0)), 0);

    const totalEl = document.getElementById('total_items_count');
    const lowEl = document.getElementById('low_stock_count');
    const valueEl = document.getElementById('total_inventory_value');

    if (totalEl) totalEl.textContent = totalCount;
    if (lowEl) lowEl.textContent = lowStockCount;
    if (valueEl) valueEl.textContent = totalValue.toFixed(2);
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