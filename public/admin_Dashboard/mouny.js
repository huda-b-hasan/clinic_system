document.addEventListener("DOMContentLoaded", function () {
    fetch('/admin/financial-summary', { // استبدلي المسار حسب ما يناسب مساراتك
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            // 'Authorization': 'Bearer ' + localStorage.getItem('token') // إذا كنتِ تستخدمين التوكن
        }
    })
    .then(response => response.json())
    .then(res => {
        if (res.status === 'success') {
            const data = res.data;

            // 1. تعبئة الكروت الإحصائية الأربعة
            const cardElements = document.querySelectorAll('.summary-cards .summary-card .text-content p');
            if (cardElements.length >= 4) {
                cardElements[0].textContent = data.cards.yearly_revenue;   // صافي أرباح العام
                cardElements[1].textContent = data.cards.monthly_revenue;  // صافي أرباح الشهر
                cardElements[2].textContent = data.cards.daily_revenue;    // إيرادات اليوم
                cardElements[3].textContent = data.cards.unpaid_total;     // فواتير غير مسددة
            }

            // 2. تعبئة جدول الفواتير والمعاملات
            let tableBody = document.getElementById('invoiceTableBody');
            tableBody.innerHTML = '';

            data.bills.forEach(bill => {
                let badgeClass = '';
                let badgeText = bill.status_text;

                if (bill.data_status === 'paid') {
                    badgeClass = 'active';
                } else if (bill.data_status === 'unpaid') {
                    badgeClass = 'unpaid';
                } else if (bill.data_status === 'pending') {
                    badgeClass = 'pending';
                } else if (bill.data_status === 'expense') {
                    badgeClass = 'pending'; // كلاس خاص لفواتير الشراء/المصاريف إذا أردتِ
                }

                let row = `
                    <tr data-status="${bill.data_status}">
                        <td><strong>${bill.bill_number}</strong></td>
                        <td>${bill.patient_name}</td>
                        <td>${bill.session_name}</td>
                        <td>${bill.amount}</td>
                        <td>${bill.date}</td>
                        <td><span class="badge-status ${badgeClass}">${badgeText}</span></td>
                    </tr>
                `;
                tableBody.insertAdjacentHTML('beforeend', row);
            });

            // إعادة تفعيل أزرار الفلترة بعد تعبئة العناصر ديناميكياً
            initDynamicFilters();
        }
    })
    .catch(error => {
        console.error('حدث خطأ أثناء جلب البيانات المالية:', error);
    });
});

// وظيفة الفلترة للجدول بعد تحميل البيانات
function initDynamicFilters() {
    const filterButtons = document.querySelectorAll('.filter-btn');
    
    filterButtons.forEach(button => {
        button.addEventListener('click', () => {
            filterButtons.forEach(btn => btn.classList.remove('active'));
            button.classList.add('active');

            const filterValue = button.getAttribute('data-filter');
            const invoiceRows = document.querySelectorAll('#invoiceTableBody tr');

            invoiceRows.forEach(row => {
                const rowStatus = row.getAttribute('data-status');
                
                // زر "شراء" يطابق الـ status الخاص بالمصاريف (expense) أو (pending)
                if (filterValue === 'all' || rowStatus === filterValue || (filterValue === 'pending' && rowStatus === 'expense')) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    });
}