
    function fetchDoctorPatients() {
        const apiUrl = '/doctor/dashboard-data'; 

        fetch(apiUrl, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            }
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('حدث خطأ أثناء جلب البيانات من السيرفر');
            }
            return response.json();
        })
        .then(res => {
            console.log (res)
            if (res.status === 'success' && res.data.pateints) {
                renderPatientsTable(res.data.pateints);
            } else {
                console.error('بنية البيانات المستلمة غير متطابقة أو فارغة');
            }
        })
        .catch(error => {
            console.error('Error fetching patients:', error);
            // عرض رسالة خطأ داخل الجدول في حال فشل الاتصال
            document.querySelector(".patients-table tbody").innerHTML = `
                <tr>
                    <td colspan="5" style="text-align: center; color: red; padding: 20px;">
                        فشل في تحميل بيانات المرضى. يرجى المحاولة لاحقاً.
                    </td>
                </tr>
            `;
        });
    }

    function renderPatientsTable(patients) {
        const tbody = document.querySelector(".patients-table tbody");
        tbody.innerHTML = ""; 

        if (patients.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="5" style="text-align: center; padding: 20px; color: #888;">
                        لا يوجد مرضى تم علاجهم بعد.
                    </td>
                </tr>
            `;
            return;
        }

        patients.forEach(patient => {
            // حساب العمر تلقائياً من تاريخ الميلاد
            let age = 'غير محدد';
            if (patient.birthdate) {
                const birthDate = new Date(patient.birthdate);
                const today = new Date();
                age = today.getFullYear() - birthDate.getFullYear();
                const m = today.getMonth() - birthDate.getMonth();
                if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) {
                    age--;
                }
            }

            // إنشاء سطر المريض
            const row = document.createElement("tr");
            row.innerHTML = `
                <td><strong>${patient.name}</strong></td>
                <td>${age} سنة</td>
                <td>${patient.address ? patient.address : '---'}</td>
                <td>${patient.phone}</td>
                <td><span style="font-size: 0.9em; color: #666;">${patient.medical_notes ? patient.medical_notes : 'لا توجد ملاحظات'}</span></td>
            `;
            tbody.appendChild(row);
        });
    }
document.addEventListener("DOMContentLoaded", () => {
    fetchDoctorPatients();
});