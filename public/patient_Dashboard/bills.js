document.addEventListener('DOMContentLoaded', () => {

    const totalPaidElement = document.getElementById('totalPaid');
    const totalUnpaidElement = document.getElementById('totalUnpaid');
    const billsTableBody = document.getElementById('billsTableBody');


    async function fetchBillsData() {
        try {

            billsTableBody.innerHTML = `<tr><td colspan="5" style="text-align: center;">جاري تحميل الفواتير...</td></tr>`;

            const response = await fetch("/patient/bills", {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) {
                if (response.status === 404) {
                    throw new Error('لم يتم العثور على ملف مريض مرتبط بهذا الحساب.');
                }
                throw new Error('حدث خطأ أثناء جلب البيانات من السيرفر.');
            }

            const data = await response.json();
            console.log(data)
            renderSummary(data.summary);
            renderTable(data.invoices);

        } catch (error) {
            console.error('Error fetching bills:', error);

            billsTableBody.innerHTML = `<tr><td colspan="5" style="text-align: center; color: red;">${error.message}</td></tr>`;
        }
    }

    function renderSummary(summary) {
        totalUnpaidElement.textContent = summary.total_pending;
    }

    function renderTable(invoices) {
        billsTableBody.innerHTML = '';

        if (invoices.length === 0) {
            billsTableBody.innerHTML = `<tr><td colspan="5" style="text-align: center;">لا توجد فواتير مسجلة حالياً.</td></tr>`;
            return;
        }

        invoices.forEach(bill => {
            const tr = document.createElement('tr');

            const statusClass = bill.raw_status === 'paid' ? 'paid' : 'unpaid';

            tr.innerHTML = `
                <td class="bill-id">${bill.invoice_number}</td>
                <td>${bill.session_name}</td>
                <td><strong>${bill.amount}</strong></td>
                <td>${bill.date}</td>
                <td><span class="status-badge ${statusClass}">${bill.status}</span></td>
            `;

            billsTableBody.appendChild(tr);
        });
    }

    fetchBillsData();
});