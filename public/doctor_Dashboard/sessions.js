document.addEventListener("DOMContentLoaded", function () {
    const container = document.getElementById("sessionsContainer");

    function showToast(message, type = "success") {
        const toast = document.createElement("div");
        toast.className = `custom-toast ${type}`;
        toast.innerText = message;

        document.body.appendChild(toast);

        setTimeout(() => {
            toast.classList.add("show");
        }, 50);

        setTimeout(() => {
            toast.classList.remove("show");
            setTimeout(() => toast.remove(), 400);
        }, 3000);
    }

    // 1. جلب المواعيد من السيرفر
    fetch("/doctor/appointments")
        .then(response => {
            if (!response.ok) throw new Error("فشل في جلب البيانات");
            return response.json();
        })
        .then(data => {
            if (data.success && data.pending && data.pending.length > 0) {
                container.innerHTML = "";

                data.pending.forEach(appointment => {
                    const patientName = appointment.patient ? appointment.patient.name : "مريض غير معروف";
                    const roomName = appointment.room ? appointment.room.name : "غير محددة";

                    let treatmentsText = "لا توجد علاجات محددة";
                    if (appointment.treatments && appointment.treatments.length > 0) {
                        treatmentsText = appointment.treatments.map(t => t.name).join(" ، ");
                    }

                    const card = document.createElement("div");
                    card.className = "session-card";
                    card.innerHTML = `
                        <div class="session-info">
                            <h3>المريض: ${patientName}</h3>
                            <p><strong>التاريخ والوقت:</strong> ${new Date(appointment.appointment_date).toLocaleString('ar-EG')}</p>
                            <p><strong>الغرفة:</strong> ${roomName}</p>
                            <p><strong>العلاجات المطلوبة:</strong> ${treatmentsText}</p>
                        </div>
                        <div class="session-actions">
                            <button class="btn btn-primary start-btn" data-id="${appointment.id}">بدء الجلسة</button>
                            <button class="btn btn-danger cancel-btn" data-id="${appointment.id}">إلغاء الجلسة</button>
                        </div>
                    `;
                    container.appendChild(card);
                });

            } else {
                container.innerHTML = '<p style="text-align: center; color: #888;">لا توجد جلسات نشطة أو معلقة اليوم.</p>';
            }
        })
        .catch(error => {
            console.error("Error:", error);
            container.innerHTML = '<p style="text-align: center; color: red;">حدث خطأ أثناء تحميل الجلسات.</p>';
        });

    // 2. معالجة الضغط على زر "بدء الجلسة" للتوجيه لصفحة تسجيل الجلسة
    container.addEventListener("click", function (e) {
        if (e.target.classList.contains("start-btn")) {
            const appointmentId = e.target.getAttribute("data-id");
            if (appointmentId) {
                // تعديل اسم الملف هنا لـ session.html
                window.location.href = `session.html?appointment_id=${appointmentId}`;
            }
        }
    });

});