// 1. الرابط الخاص بالـ API (المحافظة على مساركِ المعتمد)
const API_URL = '/receptionist/bills-summary'; 

// مصفوفة البيانات الأساسية المجهزة لاستقبال بيانات السيرفر
let billsData = {
    stats: { paid_count: 0, unpaid_count: 0 },
    bills: { paid: [], unpaid: [] }
};
let currentTab = 'unpaid'; // التبويب النشط افتراضياً عند التحميل

// 2. عند تحميل الصفحة بالكامل ابدأ بجلب البيانات
document.addEventListener('DOMContentLoaded', () => {
    fetchBillsData();
});

// 3. جلب البيانات من الـ API وتحديث الصفحة
async function fetchBillsData() {
    const container = document.getElementById('billsCardsContainer');
    // استخدام رسالة تحميل بسيطة بنفس خط وتصميم العيادة
    container.innerHTML = `<div style="text-align: center; padding: 40px; color: var(--text-muted); font-weight: 600;">جاري تحميل الفواتير...</div>`;

    try {
        const response = await fetch(API_URL, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json'
            }
        });

        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);

        const result = await response.json();

        // مطابقة الهيكل الراجع من Laravel Controller بدقة
        if (result.status === 'success' && result.data) {
            billsData = result.data;
            updateSummaryCards();
            renderBills(currentTab);
        } else {
            throw new Error(result.message || 'خطأ في تنسيق البيانات المستلمة.');
        }

    } catch (error) {
        console.error('Error fetching bills:', error);
        container.innerHTML = `
            <div style="text-align: center; padding: 40px; color: #d32f2f;">
                <p>عذراً، واجهنا مشكلة في جلب فواتير العيادة.</p>
                <button onclick="fetchBillsData()" class="tab-btn active" style="margin-top: 15px;">إعادة المحاولة</button>
            </div>
        `;
    }
}

// 4. تحديث الكروت العلوية (العدد والمبالغ المعلقة)
function updateSummaryCards() {
    const pendingCountEl = document.getElementById('pendingInvoicesCount');
    const totalUnpaidEl = document.getElementById('totalUnpaid');

    const unpaidList = billsData.bills.unpaid || [];
    
    // تحديث عدد الفواتير المعلقة
    pendingCountEl.textContent = unpaidList.length;

    // حساب إجمالي المبالغ المعلقة من الـ API
    const totalUnpaidAmount = unpaidList.reduce((sum, bill) => {
        return sum + (parseFloat(bill.amount) || 0);
    }, 0);

    totalUnpaidEl.textContent = `${totalUnpaidAmount.toLocaleString('ar-SA')} ر.س`;
}

// 5. بناء وعرض كروت الفواتير بناءً على التبويب المختار
function renderBills(status) {
    const container = document.getElementById('billsCardsContainer');
    const gridHeader = document.querySelector('.bills-grid-header');
    const actionHeader = document.getElementById('gridActionHeader');
    
    container.innerHTML = ''; // تفريغ الحاوية

    const list = billsData.bills[status] || [];

    // ضبط شكل وحالة الأعمدة بناءً على التبويب (تفعيل الكلاس الخاص بكِ)
    if (status === 'paid') {
        if (gridHeader) gridHeader.classList.add('hide-action-column');
        if (actionHeader) actionHeader.style.display = 'none'; // إخفاء كلمة "الإجراء" من الترويسة
    } else {
        if (gridHeader) gridHeader.classList.remove('hide-action-column');
        if (actionHeader) actionHeader.style.display = 'block'; // إظهار كلمة "الإجراء" عند العودة للمعلقة
    }

    if (list.length === 0) {
        container.innerHTML = `<div style="text-align: center; padding: 50px; color: var(--text-muted);">لا توجد فواتير معروضة في هذا القسم حالياً.</div>`;
        return;
    }

    list.forEach(bill => {
        const cardItem = document.createElement('div');
        
        // تطبيق كلاس التنسيق الخماسي أو السداسي بناءً على الحالة
        if (status === 'paid') {
            cardItem.className = `bill-card-item bills-grid-layout paid-border hide-action-column`;
        } else {
            cardItem.className = `bill-card-item bills-grid-layout pending-border`;
        }

        const badgeClass = status === 'paid' ? 'status-completed' : 'status-in-progress';
        const badgeText = status === 'paid' ? 'مدفوعة' : 'معلقة';

        // كود الأكشن يظهر فقط للفواتير المعلقة
        const actionHtml = status === 'unpaid' ? `
            <!-- 6. الإجراء -->
            <div class="appointment-actions">
                <button class="btn-complete-session" onclick="payInvoice('${bill.id}')">تسوية الدفع</button>
            </div>
        ` : '';

        cardItem.innerHTML = `
            <!-- 1. رقم الفاتورة والجلسة -->
            <div class="bill-title-info">
                <span class="id-badge">${bill.bill_number}</span>
                <span class="session-title">جلسة علاجية مخصصة</span>
            </div>
            
            <!-- 2. المريضة -->
            <div class="bill-meta-group" data-label="المريضة">
                <span class="meta-val">${bill.patient_name}</span>
            </div>
            
            <!-- 3. التاريخ -->
            <div class="bill-meta-group" data-label="التاريخ">
                <span class="meta-val">${bill.date || '---'}</span>
            </div>
            
            <!-- 4. المبلغ المطلوب -->
            <div class="bill-meta-group" data-label="المبلغ المطلوب">
                <span class="price-val">${parseFloat(bill.amount).toFixed(2)} ر.س</span>
            </div>
            
            <!-- 5. الحالة -->
            <div class="bill-meta-group" data-label="الحالة">
                <span class="status-badge ${badgeClass}">${badgeText}</span>
            </div>
            
            ${actionHtml}
        `;
        
        container.appendChild(cardItem);
    });
}

// 6. التنقل السلس بين التبويبات (Tabs)
function switchTab(status) {
    currentTab = status;

    // تحديث تفعيل التبويب المختار بصرياً
    document.getElementById('tabPending').classList.toggle('active', status === 'unpaid');
    document.getElementById('tabPaid').classList.toggle('active', status === 'paid');

    // عرض القائمة المحدثة فوراً
    renderBills(status);
}

// 7. معالجة العمليات التفاعلية  
let activeBillId = null; // للاحتفاظ برقم الفاتورة المراد تسويتها حالياً

// 1. استدعاء نافذة التأكيد وتجهيز المعرف
function payInvoice(id) {
    activeBillId = id;
    const modal = document.getElementById('confirmModal');
    if (modal) {
        modal.classList.add('show');
    }
    
    // ربط عملية التأكيد بالزر داخل المودال عند النقر عليه
    const confirmBtn = document.getElementById('modalConfirmBtn');
    if (confirmBtn) {
        confirmBtn.onclick = executePayment;
    }
}

// 2. إغلاق نافذة التأكيد وتفريغ المعرف
function closeConfirmModal() {
    const modal = document.getElementById('confirmModal');
    if (modal) {
        modal.classList.remove('show');
    }
    activeBillId = null;
}

// 3. تنفيذ عملية الدفع الفعلية مع السيرفر بعد التأكيد (تمت معالجة مشكلة الـ null بنجاح هنا)
async function executePayment() {
    if (!activeBillId) return;

    // حفظ قيمة المعرف في متغير محلي مستقل قبل البدء بأي عمليات إغلاق لتلافي مشكلة الـ null
    const billIdToPay = activeBillId;

    const confirmBtn = document.getElementById('modalConfirmBtn');
    const originalText = confirmBtn ? confirmBtn.textContent : 'نعم، تم الدفع';
    
    // قفل الزر وتغيير نصه منعاً للنقرات المكررة أثناء إرسال الطلب
    if (confirmBtn) {
        confirmBtn.disabled = true;
        confirmBtn.textContent = 'جاري الحفظ...';
    }

    try {
        // إرسال الطلب لـ Laravel API باستخدام المتغير المحلي المضمون
        const response = await fetch(`/bills/${billIdToPay}/pay`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }
        });

        const result = await response.json();

        if (response.ok && result.status === 'success') {
            // تحديث البيانات محلياً في الصفحة فوراً دون الحاجة لعمل Reload
            const unpaidIndex = billsData.bills.unpaid.findIndex(b => b.id == billIdToPay);
            
            if (unpaidIndex !== -1) {
                const [settledBill] = billsData.bills.unpaid.splice(unpaidIndex, 1);
                settledBill.status = 'paid';
                billsData.bills.paid.unshift(settledBill); 
                
                updateSummaryCards();
                renderBills(currentTab);
                
                // إظهار نوتيفيكيشن النجاح (Toast) الراقية
                showToast("تمت عملية الدفع بنجاح");
            }
            
            // إغلاق المودال بعد إتمام النجاح
            closeConfirmModal();
        } else {
            throw new Error(result.message || 'فشلت عملية  الدفع في السيرفر.');
        }

    } catch (error) {
        console.error('Error paying invoice:', error);
        showToast("عذراً، حدث خطأ أثناء محاولة تسوية الدفع.", true);
        closeConfirmModal();
    } finally {
        // إعادة تهيئة أزرار المودال لحالتها الأصلية
        if (confirmBtn) {
            confirmBtn.disabled = false;
            confirmBtn.textContent = originalText;
        }
    }
}

// 4. دالة مخصصة لإظهار التوست واختفائه تلقائياً
// 4. دالة مخصصة لإظهار التوست واختفائه تلقائياً (متوافقة مع ستايل الـ .toast الجديد)
function showToast(message, isError = false) {
    // جلب عنصر التوست باستخدام الكلاس .toast
    const toast = document.querySelector('.toast');

    if (!toast) return;

    // 1. تعيين نص التنبيه داخل العنصر
    toast.textContent = message;

    // 2. تغيير لون الخلفية حسب الحالة (أخضر للنجاح / أحمر للفشل)
    if (isError) {
        toast.style.backgroundColor = '#d32f2f'; // لون أحمر متناسق مع العيادة للفشل
    } else {
        toast.style.backgroundColor = '#2ecc71'; // اللون الأخضر الأساسي المعتمد في الستايل الخاص بكِ
    }

    // 3. إضافة كلاس .show لتفعيل ظهور التوست والحركة
    toast.classList.add('show');

    // 4. إخفاء التوست تلقائياً بعد 3 ثوانٍ بإزالة كلاس .show
    setTimeout(() => {
        toast.classList.remove('show');
    }, 3000);
}